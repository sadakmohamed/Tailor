<?php
/**
 * TailorPro — Invoice & Receipt (printable)
 * URL params:
 *   ?customer_id=N         → Invoice (all orders)
 *   ?customer_id=N&type=receipt → Receipt (payments made)
 *   ?order_id=N             → Single order invoice
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['admin', 'staff']);

$db       = getDB();
$user     = currentUser();
$cid      = currentCompanyId();
$currency = $user['currency'] ?? CURRENCY;
$type     = $_GET['type'] ?? 'invoice'; // 'invoice' or 'receipt'

// Load company info
$company = $db->prepare('SELECT * FROM companies WHERE id = ?');
$company->execute([$cid]);
$company = $company->fetch();

// Load customer
$customer_id = (int)($_GET['customer_id'] ?? 0);
if (!$customer_id) {
    // If order_id given, get customer from it
    $oid = (int)($_GET['order_id'] ?? 0);
    if ($oid) {
        $r = $db->prepare('SELECT customer_id FROM orders WHERE id = ? AND company_id = ?');
        $r->execute([$oid, $cid]);
        $customer_id = (int)($r->fetchColumn() ?: 0);
    }
}

$customer = $db->prepare('SELECT * FROM customers WHERE id = ? AND company_id = ?');
$customer->execute([$customer_id, $cid]);
$customer = $customer->fetch();

if (!$customer) {
    die('<p>Customer not found.</p>');
}

// Load orders for this customer
$order_id_filter = (int)($_GET['order_id'] ?? 0);
$q = $order_id_filter
    ? 'SELECT * FROM orders WHERE id = ? AND company_id = ?'
    : 'SELECT * FROM orders WHERE customer_id = ? AND company_id = ?';
$orders_stmt = $db->prepare($q);
$orders_stmt->execute($order_id_filter ? [$order_id_filter, $cid] : [$customer_id, $cid]);
$orders = $orders_stmt->fetchAll();

// Load measurements per order
$order_ids    = array_column($orders, 'id');
$measurements = [];
if ($order_ids) {
    $in = implode(',', array_fill(0, count($order_ids), '?'));
    $m  = $db->prepare("SELECT * FROM order_measurements WHERE order_id IN ($in)");
    $m->execute($order_ids);
    foreach ($m->fetchAll() as $row) {
        $measurements[$row['order_id']] = $row;
    }
}

// Load appointments
$appointments = $db->prepare("SELECT a.*, o.category_name FROM appointments a JOIN orders o ON o.id = a.order_id WHERE a.customer_id = ? AND a.company_id = ? ORDER BY a.appointment_date");
$appointments->execute([$customer_id, $cid]);
$appointments = $appointments->fetchAll();

// Load payments
$payments = $db->prepare('SELECT p.*, o.category_name FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.customer_id = ? AND p.company_id = ? ORDER BY p.payment_date');
$payments->execute([$customer_id, $cid]);
$payments = $payments->fetchAll();

// Totals
$grand_total = array_sum(array_column($orders, 'total_price'));
$grand_paid  = array_sum(array_column($payments, 'amount'));
$balance     = $grand_total - $grand_paid;
$inv_number  = 'TLP-' . date('Y') . '-' . str_pad($customer_id, 5, '0', STR_PAD_LEFT);
$today       = date('d M Y');

// Measurement labels
$meas_labels = ['L'=>'L','S'=>'S','M1'=>'M','M2'=>'M','B'=>'B','K'=>'K','P'=>'P','C'=>'C','T'=>'T'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($type) ?> — <?= htmlspecialchars($customer['name']) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
    <style>
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; }
        .invoice-wrap {
            max-width: 780px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .inv-header {
            background: linear-gradient(135deg, #0f2340, #1e3a5f);
            color: #fff;
            padding: 2rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .inv-brand h1 { font-size: 1.5rem; font-weight: 800; margin: 0 0 0.25rem; }
        .inv-brand p  { font-size: 0.8rem; color: rgba(255,255,255,0.5); margin: 0; }
        .inv-type {
            text-align: right;
        }
        .inv-type h2 {
            font-size: 2rem; font-weight: 900; color: #d4a017; margin: 0 0 0.25rem;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .inv-type p { font-size: 0.8rem; color: rgba(255,255,255,0.5); margin: 0; }
        .inv-body { padding: 2rem 2.5rem; }
        .inv-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.75rem; }
        .inv-party-label { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; margin-bottom: 0.4rem; }
        .inv-party-name { font-weight: 700; font-size: 1rem; color: #1e293b; }
        .inv-party-sub  { font-size: 0.8rem; color: #64748b; margin-top: 2px; }
        .inv-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
        .inv-table th {
            background: #f8fafc; padding: 0.625rem 0.875rem;
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; color: #64748b; text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        .inv-table td { padding: 0.75rem 0.875rem; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; vertical-align: top; }
        .inv-table td.right { text-align: right; font-weight: 600; }
        .inv-totals { margin-left: auto; width: 280px; }
        .inv-total-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.875rem; }
        .inv-total-row.grand { font-size: 1rem; font-weight: 800; border-top: 2px solid #e2e8f0; padding-top: 0.5rem; margin-top: 0.25rem; }
        .inv-balance-row { background: #fee2e2; border-radius: 8px; padding: 0.5rem 0.75rem; display: flex; justify-content: space-between; font-weight: 700; color: #991b1b; margin-top: 0.5rem; }
        .inv-paid-row { background: #d1fae5; border-radius: 8px; padding: 0.5rem 0.75rem; display: flex; justify-content: space-between; font-weight: 700; color: #065f46; margin-top: 0.5rem; }
        .meas-grid { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.375rem; }
        .meas-chip  { background: #f1f5f9; border-radius: 5px; padding: 0.2rem 0.5rem; font-size: 0.72rem; color: #64748b; }
        .inv-footer { border-top: 1px solid #e2e8f0; padding: 1.25rem 2.5rem; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .inv-footer p { font-size: 0.75rem; color: #94a3b8; margin: 0; }
        .inv-signature { text-align: right; }
        .inv-signature div { width: 160px; border-top: 1px solid #cbd5e1; padding-top: 0.375rem; font-size: 0.75rem; color: #64748b; margin-left: auto; }
        .action-bar { position: fixed; bottom: 1.5rem; right: 1.5rem; display: flex; gap: 0.75rem; z-index: 100; }
        @media print {
            body { background: #fff; }
            .action-bar { display: none; }
            .invoice-wrap { margin: 0; border-radius: 0; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="invoice-wrap invoice-print">

    <!-- Header -->
    <div class="inv-header">
        <div class="inv-brand">
            <h1><?= APP_NAME ?></h1>
            <?php if ($company): ?>
            <p><?= htmlspecialchars($company['name']) ?></p>
            <?php if ($company['address']): ?><p><?= htmlspecialchars($company['address']) ?></p><?php endif; ?>
            <?php if ($company['phone']): ?><p><?= htmlspecialchars($company['phone']) ?></p><?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="inv-type">
            <h2><?= $type === 'receipt' ? 'Receipt' : 'Invoice' ?></h2>
            <p style="font-size:0.875rem;color:rgba(255,255,255,0.6);margin-bottom:0.5rem;">#<?= $inv_number ?></p>
            <p>Date: <?= $today ?></p>
        </div>
    </div>

    <div class="inv-body">

        <!-- Parties -->
        <div class="inv-parties">
            <div>
                <div class="inv-party-label">Bill To</div>
                <div class="inv-party-name"><?= htmlspecialchars($customer['name']) ?></div>
                <div class="inv-party-sub">📞 <?= htmlspecialchars($customer['phone']) ?></div>
                <?php if ($customer['email']): ?>
                <div class="inv-party-sub">✉️ <?= htmlspecialchars($customer['email']) ?></div>
                <?php endif; ?>
                <div class="inv-party-sub" style="margin-top:0.375rem;font-size:0.75rem;color:#94a3b8;">
                    Customer since: <?= date('d M Y', strtotime($customer['date_registered'])) ?>
                </div>
            </div>
            <div>
                <div class="inv-party-label">
                    <?= $type === 'receipt' ? 'Payment Summary' : 'Order Summary' ?>
                </div>
                <div class="inv-party-sub">
                    <?= count($orders) ?> item<?= count($orders) != 1 ? 's' : '' ?>
                </div>
                <?php if (!empty($appointments)): ?>
                <div class="inv-party-sub" style="margin-top:0.5rem;">
                    📅 Pickup:
                    <?= date('d M Y', strtotime($appointments[0]['appointment_date'])) ?>
                    <?php if ($appointments[0]['appointment_time']): ?>
                    at <?= date('h:i A', strtotime($appointments[0]['appointment_time'])) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <!-- Payment status badge -->
                <?php if ($balance <= 0): ?>
                <div style="display:inline-block;background:#d1fae5;color:#065f46;border-radius:9999px;padding:0.25rem 0.875rem;font-size:0.75rem;font-weight:700;margin-top:0.5rem;">
                    ✓ FULLY PAID
                </div>
                <?php elseif ($grand_paid > 0): ?>
                <div style="display:inline-block;background:#fef3c7;color:#92400e;border-radius:9999px;padding:0.25rem 0.875rem;font-size:0.75rem;font-weight:700;margin-top:0.5rem;">
                    ⚠️ PARTIAL PAYMENT
                </div>
                <?php else: ?>
                <div style="display:inline-block;background:#fee2e2;color:#991b1b;border-radius:9999px;padding:0.25rem 0.875rem;font-size:0.75rem;font-weight:700;margin-top:0.5rem;">
                    ❌ UNPAID
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($type === 'invoice'): ?>
        <!-- INVOICE: Show orders + measurements -->
        <table class="inv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item / Category</th>
                    <th>Measurements</th>
                    <th>Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $i => $o): ?>
            <tr>
                <td style="color:#94a3b8;"><?= $i + 1 ?></td>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($o['category_name']) ?></div>
                    <?php if ($o['notes']): ?>
                    <div style="font-size:0.75rem;color:#94a3b8;"><?= htmlspecialchars($o['notes']) ?></div>
                    <?php endif; ?>
                    <span style="font-size:0.7rem;background:<?= ['pending'=>'#fef3c7','in_progress'=>'#dbeafe','completed'=>'#d1fae5','delivered'=>'#e0e7ff'][$o['status']] ?? '#f1f5f9' ?>;
                                  color:<?= ['pending'=>'#92400e','in_progress'=>'#1e40af','completed'=>'#065f46','delivered'=>'#3730a3'][$o['status']] ?? '#64748b' ?>;
                                  padding:1px 6px;border-radius:9999px;font-weight:600;">
                        <?= ucfirst(str_replace('_',' ',$o['status'])) ?>
                    </span>
                </td>
                <td>
                    <?php if (isset($measurements[$o['id']])): ?>
                    <?php $m = $measurements[$o['id']]; ?>
                    <div class="meas-grid">
                        <?php foreach ($meas_labels as $col => $label): ?>
                        <?php if (!empty($m[$col])): ?>
                        <span class="meas-chip"><?= $label ?>: <?= number_format((float)$m[$col],1) ?> in</span>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <span style="color:#94a3b8;font-size:0.8rem;">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;">1</td>
                <td class="right"><?= $currency ?><?= number_format($o['total_price'], 2) ?></td>
                <td class="right"><?= $currency ?><?= number_format((float)$o['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php else: ?>
        <!-- RECEIPT: Show payment records -->
        <table class="inv-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($payments)): ?>
            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:2rem;">No payments recorded.</td></tr>
            <?php else: ?>
            <?php foreach ($payments as $i => $p): ?>
            <tr>
                <td style="color:#94a3b8;"><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($p['category_name']) ?></td>
                <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                <td><?= ucfirst($p['payment_method']) ?></td>
                <td class="right" style="color:#065f46;"><?= $currency ?><?= number_format((float)$p['amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Totals -->
        <div style="display:flex;justify-content:flex-end;margin-bottom:2rem;">
            <div class="inv-totals">
                <div class="inv-total-row">
                    <span>Subtotal:</span>
                    <span><?= $currency ?><?= number_format($grand_total, 2) ?></span>
                </div>
                <div class="inv-total-row grand">
                    <span>Grand Total:</span>
                    <span><?= $currency ?><?= number_format($grand_total, 2) ?></span>
                </div>
                <?php if ($grand_paid > 0): ?>
                <div class="inv-paid-row">
                    <span>✓ Total Paid:</span>
                    <span><?= $currency ?><?= number_format($grand_paid, 2) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($balance > 0): ?>
                <div class="inv-balance-row">
                    <span>⚠️ Balance Due:</span>
                    <span><?= $currency ?><?= number_format($balance, 2) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointments (invoice only) -->
        <?php if ($type === 'invoice' && !empty($appointments)): ?>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:1rem;margin-bottom:1.5rem;">
            <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#94a3b8;margin-bottom:0.5rem;">
                Pickup Appointment
            </div>
            <?php foreach ($appointments as $apt): ?>
            <div style="font-size:0.875rem;margin-bottom:0.25rem;">
                📅 <strong><?= date('D, d M Y', strtotime($apt['appointment_date'])) ?></strong>
                <?php if ($apt['appointment_time']): ?>
                at <strong><?= date('h:i A', strtotime($apt['appointment_time'])) ?></strong>
                <?php endif; ?>
                — <?= htmlspecialchars($apt['category_name']) ?>
                <?php if ($apt['notes']): ?>
                &nbsp; <span style="color:#94a3b8;">(<?= htmlspecialchars($apt['notes']) ?>)</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if ($customer['notes']): ?>
        <div style="font-size:0.8rem;color:#94a3b8;margin-bottom:1rem;">
            Note: <?= htmlspecialchars($customer['notes']) ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- Footer -->
    <div class="inv-footer">
        <div>
            <p><?= APP_NAME ?> — <?= htmlspecialchars($company['name'] ?? APP_NAME) ?></p>
            <p>Generated on <?= $today ?> &nbsp;|&nbsp; <?= $inv_number ?></p>
            <p style="margin-top:0.5rem;font-style:italic;">Thank you for your business! 🙏</p>
        </div>
        <div class="inv-signature">
            <div>Authorized Signature</div>
        </div>
    </div>

</div>

<!-- Action bar (hidden on print) -->
<div class="action-bar no-print">
    <a href="<?= BASE_URL ?>/admin/view_customer.php?id=<?= $customer_id ?>"
       style="background:#fff;border:1.5px solid #e2e8f0;color:#1e293b;padding:0.625rem 1.25rem;
               border-radius:0.5rem;text-decoration:none;font-size:0.875rem;font-weight:500;
               display:inline-flex;align-items:center;gap:0.4rem;">
        ← Back
    </a>
    <?php if ($type === 'invoice'): ?>
    <a href="?customer_id=<?= $customer_id ?>&type=receipt"
       style="background:#1e3a5f;color:#fff;padding:0.625rem 1.25rem;
               border-radius:0.5rem;text-decoration:none;font-size:0.875rem;font-weight:500;">
        🧾 Receipt
    </a>
    <?php else: ?>
    <a href="?customer_id=<?= $customer_id ?>"
       style="background:#1e3a5f;color:#fff;padding:0.625rem 1.25rem;
               border-radius:0.5rem;text-decoration:none;font-size:0.875rem;font-weight:500;">
        📄 Invoice
    </a>
    <?php endif; ?>
    <button onclick="window.print()"
            style="background:#d4a017;color:#fff;padding:0.625rem 1.25rem;
                    border-radius:0.5rem;border:none;cursor:pointer;font-size:0.875rem;font-weight:600;">
        🖨 Print
    </button>
</div>

</body>
</html>
