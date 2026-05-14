<?php
/**
 * TailorPro — View / Edit Customer Detail
 * Shows: profile, all orders+measurements, payments, appointment
 * Accessible by: admin + staff
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['admin', 'staff']);

$db   = getDB();
$user = currentUser();
$cid  = currentCompanyId();
$id   = (int)($_GET['id'] ?? 0);

// Load customer (must belong to same company)
$customer = $db->prepare('SELECT * FROM customers WHERE id = ? AND company_id = ?');
$customer->execute([$id, $cid]);
$customer = $customer->fetch();

if (!$customer) {
    $_SESSION['flash_error'] = 'Customer not found.';
    header('Location: ' . BASE_URL . '/admin/customers.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add payment
    if ($action === 'add_payment') {
        $ord_id  = (int)$_POST['order_id'];
        $amount  = (float)$_POST['amount'];
        $method  = $_POST['method']  ?? 'cash';
        $pdate   = $_POST['pdate']   ?? date('Y-m-d');
        $pnotes  = trim($_POST['pnotes'] ?? '');

        if ($amount > 0 && $ord_id) {
            $db->prepare('
                INSERT INTO payments (order_id, customer_id, company_id, amount, payment_date, payment_method, notes, received_by)
                VALUES (?,?,?,?,?,?,?,?)
            ')->execute([$ord_id, $id, $cid, $amount, $pdate, $method, $pnotes, $user['id']]);
            $_SESSION['flash_success'] = 'Payment recorded successfully.';
        } else {
            $_SESSION['flash_error'] = 'Invalid payment amount.';
        }
        header('Location: ' . BASE_URL . '/admin/view_customer.php?id=' . $id);
        exit;
    }

    // Update order status
    if ($action === 'update_status') {
        $ord_id = (int)$_POST['order_id'];
        $status = $_POST['status'] ?? 'pending';
        $allowed = ['pending','in_progress','completed','delivered'];
        if (in_array($status, $allowed)) {
            $db->prepare('UPDATE orders SET status = ? WHERE id = ? AND company_id = ?')
               ->execute([$status, $ord_id, $cid]);
            $_SESSION['flash_success'] = 'Order status updated.';
        }
        header('Location: ' . BASE_URL . '/admin/view_customer.php?id=' . $id);
        exit;
    }

    // Update appointment status
    if ($action === 'complete_appointment') {
        $apt_id = (int)$_POST['appt_id'];
        $db->prepare('UPDATE appointments SET status = "completed" WHERE id = ? AND company_id = ?')
           ->execute([$apt_id, $cid]);
        $_SESSION['flash_success'] = 'Appointment marked as completed.';
        header('Location: ' . BASE_URL . '/admin/view_customer.php?id=' . $id);
        exit;
    }
}

// Load orders with measurements & paid amounts using subquery for reliability
$orders = $db->prepare('
    SELECT o.*,
           m.L, m.S, m.M1, m.M2, m.B, m.K, m.P, m.C, m.T,
           (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = o.id) AS paid_amount
    FROM   orders o
    LEFT   JOIN order_measurements m ON m.order_id = o.id
    WHERE  o.customer_id = ? AND o.company_id = ?
    ORDER  BY o.created_at DESC
');
$orders->execute([$id, $cid]);
$orders = $orders->fetchAll(PDO::FETCH_ASSOC);

// Load all payments
$payments = $db->prepare('
    SELECT p.*, o.category_name, u.name AS received_by_name
    FROM   payments p
    JOIN   orders   o ON o.id = p.order_id
    LEFT   JOIN users u ON u.id = p.received_by
    WHERE  p.customer_id = ? AND p.company_id = ?
    ORDER  BY p.payment_date DESC, p.created_at DESC
');
$payments->execute([$id, $cid]);
$payments = $payments->fetchAll(PDO::FETCH_ASSOC);

// Load appointments
$appointments = $db->prepare('
    SELECT a.*, o.category_name
    FROM   appointments a
    JOIN   orders o ON o.id = a.order_id
    WHERE  a.customer_id = ? AND a.company_id = ?
    ORDER  BY a.appointment_date DESC
');
$appointments->execute([$id, $cid]);
$appointments = $appointments->fetchAll(PDO::FETCH_ASSOC);

// Totals
$grand_total = array_sum(array_column($orders, 'total_price'));
$grand_paid  = array_sum(array_column($orders, 'paid_amount'));
$grand_balance = $grand_total - $grand_paid;

$currency   = $user['currency'] ?? CURRENCY;
$pageTitle  = htmlspecialchars($customer['name']);
$activePage = 'customers';
include ROOT_PATH . '/includes/header.php';
?>

<!-- Customer header card -->
<div class="card" style="margin-bottom:1.25rem;background:linear-gradient(135deg,#0f2340,#1e3a5f);border:none;">
    <div style="padding:1.5rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div style="display:flex;align-items:center;gap:1rem;">
                <div style="width:56px;height:56px;background:var(--gold);border-radius:50%;
                             display:flex;align-items:center;justify-content:center;
                             font-size:1.5rem;font-weight:800;color:#fff;flex-shrink:0;">
                    <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                </div>
                <div>
                    <h2 style="font-size:1.375rem;font-weight:800;color:#fff;margin:0 0 0.25rem;">
                        <?= htmlspecialchars($customer['name']) ?>
                    </h2>
                    <div style="font-size:0.875rem;color:rgba(255,255,255,0.55);">
                        📞 <?= htmlspecialchars($customer['phone']) ?>
                        <?php if ($customer['email']): ?>
                        &nbsp;&nbsp;✉️ <?= htmlspecialchars($customer['email']) ?>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(255,255,255,0.35);margin-top:4px;">
                        Registered: <?= date('d M Y', strtotime($customer['date_registered'])) ?>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>/admin/invoice.php?customer_id=<?= $id ?>"
                   target="_blank" class="btn btn-outline btn-sm"
                   style="border-color:rgba(255,255,255,0.25);color:rgba(255,255,255,0.7);">
                    🖨 Invoice
                </a>
                <a href="<?= BASE_URL ?>/admin/invoice.php?customer_id=<?= $id ?>&type=receipt"
                   target="_blank" class="btn btn-outline btn-sm"
                   style="border-color:rgba(255,255,255,0.25);color:rgba(255,255,255,0.7);">
                    🧾 Receipt
                </a>
                <a href="<?= BASE_URL ?>/admin/add_customer.php" class="btn btn-gold btn-sm">+ New Order</a>
            </div>
        </div>

        <!-- Summary bar -->
        <div style="margin-top:1.25rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:0.625rem;">
            <?php
            $summary = [
                ['Orders', count($orders), '#dbeafe', '#1e40af'],
                ['Total Value', $currency . number_format($grand_total, 2), '#fef3c7', '#92400e'],
                ['Total Paid',  $currency . number_format($grand_paid, 2),  '#d1fae5', '#065f46'],
                ['Balance',     $currency . number_format(max(0,$grand_balance), 2), '#fee2e2', '#991b1b'],
            ];
            foreach ($summary as [$label, $val, $bg, $color]):
            ?>
            <div style="background:rgba(255,255,255,0.07);border-radius:10px;padding:0.75rem 1rem;">
                <div style="font-size:0.65rem;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:0.07em;">
                    <?= $label ?>
                </div>
                <div style="font-size:1.1rem;font-weight:800;color:#fff;margin-top:4px;"><?= $val ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Payment bar -->
        <?php if ($grand_total > 0): ?>
        <?php $pct = min(100, round(($grand_paid / $grand_total) * 100)); ?>
        <div style="margin-top:1rem;">
            <div style="height:6px;background:rgba(255,255,255,0.1);border-radius:9999px;overflow:hidden;">
                <div style="height:100%;width:<?= $pct ?>%;background:var(--gold);border-radius:9999px;"></div>
            </div>
            <div style="font-size:0.7rem;color:rgba(255,255,255,0.35);margin-top:4px;"><?= $pct ?>% paid</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:1.25rem;">

    <!-- Orders & Measurements -->
    <div>
        <div style="font-weight:700;font-size:0.9rem;margin-bottom:0.75rem;">
            Orders (<?= count($orders) ?>)
        </div>

        <?php if (empty($orders)): ?>
        <div class="card" style="padding:2rem;text-align:center;color:var(--text-light);">
            No orders yet.
        </div>
        <?php endif; ?>

        <?php foreach ($orders as $o): ?>
        <?php
        $o_paid    = (float)$o['paid_amount'];
        $o_balance = (float)$o['total_price'] - $o_paid;
        $status_colors = [
            'pending'     => ['#fef3c7','#92400e'],
            'in_progress' => ['#dbeafe','#1e40af'],
            'completed'   => ['#d1fae5','#065f46'],
            'delivered'   => ['#e0e7ff','#3730a3'],
        ];
        [$sbg, $scolor] = $status_colors[$o['status']] ?? ['#f1f5f9','#64748b'];
        ?>
        <div class="card" style="margin-bottom:1.5rem;border:1px solid var(--border);border-left:4px solid var(--gold);overflow:hidden;">
            <div style="padding:1.25rem;background:#fcfdfd;border-bottom:1px solid var(--border);
                         display:flex;align-items:flex-start;gap:1.25rem;flex-wrap:wrap;">
                
                <?php if ($o['fabric_image']): ?>
                <div style="flex-shrink:0;">
                    <img src="<?= BASE_URL . htmlspecialchars($o['fabric_image']) ?>" 
                         alt="Fabric"
                         onclick="openImageModal('<?= BASE_URL . htmlspecialchars($o['fabric_image']) ?>', 'Fabric Snapshot')"
                         style="width:120px;height:120px;object-fit:cover;border-radius:12px;border:2px solid #fff;box-shadow:0 4px 12px rgba(0,0,0,0.1);cursor:pointer;transition:transform 0.2s;"
                         onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </div>
                <?php else: ?>
                <div style="width:120px;height:120px;background:#f1f5f9;border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--text-light);font-size:2rem;">🧵</div>
                <?php endif; ?>

                <div style="flex:1;min-width:200px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                        <h3 style="font-size:1.1rem;font-weight:800;color:var(--navy);margin:0;">
                            <?= htmlspecialchars($o['category_name']) ?>
                        </h3>
                        <span style="background:<?= $sbg ?>;color:<?= $scolor ?>;padding:0.25rem 0.75rem;
                                      border-radius:9999px;font-size:0.7rem;font-weight:700;text-transform:uppercase;">
                            <?= ucfirst(str_replace('_',' ',$o['status'])) ?>
                        </span>
                    </div>
                    
                    <div style="font-size:0.8rem;color:var(--text-mid);margin-bottom:1rem;display:flex;gap:1rem;">
                        <span>Order #<?= $o['id'] ?></span>
                        <span>📅 <?= date('d M Y', strtotime($o['created_at'])) ?></span>
                    </div>

                    <div style="display:flex;gap:1.5rem;align-items:center;">
                        <div>
                            <div style="font-size:0.65rem;color:var(--text-light);text-transform:uppercase;letter-spacing:0.05em;">Total Price</div>
                            <div style="font-weight:800;color:var(--navy);font-size:1.1rem;"><?= $currency ?><?= number_format((float)$o['total_price'], 2) ?></div>
                        </div>
                        <div>
                            <div style="font-size:0.65rem;color:var(--text-light);text-transform:uppercase;letter-spacing:0.05em;">Balance</div>
                            <div style="font-weight:800;color:<?= $o_balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;font-size:1.1rem;">
                                <?= $currency ?><?= number_format($o_balance, 2) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:0.5rem;align-self:stretch;justify-content:center;">
                    <form method="POST" style="display:flex;gap:0.35rem;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                        <select name="status" class="form-input" style="font-size:0.8rem;padding:0.4rem 0.75rem;width:130px;">
                            <?php foreach (['pending','in_progress','completed','delivered'] as $s): ?>
                            <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_',' ',$s)) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Update</button>
                    </form>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid var(--border);">
                <!-- Measurements Section -->
                <div style="padding:1.25rem;border-right:1px solid var(--border);">
                    <div style="font-size:0.75rem;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;">
                        📐 Measurements (Inch)
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(60px, 1fr));gap:0.75rem;">
                        <?php
                        $meas_display = [
                            ['L', $o['L']], ['S', $o['S']], ['M', $o['M1']], ['M', $o['M2']],
                            ['B', $o['B']], ['K', $o['K']], ['P', $o['P']], ['C', $o['C']], ['T', $o['T']]
                        ];
                        foreach ($meas_display as [$label, $val]):
                            if ($val === null || $val === '' || $val == 0) continue;
                        ?>
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:0.5rem;text-align:center;">
                            <div style="font-size:0.65rem;color:var(--text-mid);font-weight:700;"><?= $label ?></div>
                            <div style="font-weight:800;color:var(--navy);font-size:1rem;"><?= number_format((float)$val, 1) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Notes & Payments Preview -->
                <div style="padding:1.25rem;background:#fafbfc;">
                    <?php if ($o['notes']): ?>
                    <div style="margin-bottom:1.25rem;">
                        <div style="font-size:0.75rem;font-weight:700;color:var(--navy);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.5rem;">📝 Order Notes</div>
                        <div style="font-size:0.875rem;color:var(--text-mid);line-height:1.5;background:#fff;padding:0.75rem;border-radius:8px;border:1px solid #eee;">
                            <?= htmlspecialchars($o['notes']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <button type="button" onclick="togglePayForm('pay_<?= $o['id'] ?>')" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;background:#fff;">
                            💰 Add Quick Payment
                        </button>
                    </div>
                </div>
            </div>

            <!-- Payment Form (Expandable) -->
            <div id="pay_<?= $o['id'] ?>" style="display:none;padding:1.25rem;background:#fff;border-top:1px solid var(--border);">
                <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(140px, 1fr));gap:1rem;align-items:flex-end;">
                    <input type="hidden" name="action" value="add_payment">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <div>
                        <label class="form-label">Amount (<?= $currency ?>)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required 
                               <?php if ($o_balance > 0): ?>max="<?= $o_balance ?>"<?php endif; ?>
                               placeholder="<?= number_format(max(0,$o_balance), 2) ?>" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Method</label>
                        <select name="method" class="form-input">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="mobile">Mobile</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Date</label>
                        <input type="date" name="pdate" class="form-input" value="<?= date('Y-m-d') ?>">
                    </div>
                    <button type="submit" class="btn btn-success">Save Payment</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Appointments + Payment History -->
    <div class="responsive-grid-2" style="align-items:start;">

        <!-- Appointments -->
        <div class="card">
            <div style="padding:1rem 1.125rem;border-bottom:1px solid var(--border);font-weight:700;font-size:0.9rem;">
                📅 Appointments
            </div>
            <?php if (empty($appointments)): ?>
            <div style="padding:1.5rem;text-align:center;color:var(--text-light);font-size:0.875rem;">
                No appointments scheduled.
            </div>
            <?php else: ?>
            <?php foreach ($appointments as $apt): ?>
            <div style="padding:0.875rem 1.125rem;border-bottom:1px solid var(--border);">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:0.5rem;">
                    <div>
                        <div style="font-weight:600;font-size:0.875rem;">
                            📅 <?= date('d M Y', strtotime($apt['appointment_date'])) ?>
                            <?php if ($apt['appointment_time']): ?>
                            ⏰ <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-mid);">
                            <?= htmlspecialchars($apt['category_name']) ?>
                        </div>
                        <?php if ($apt['notes']): ?>
                        <div style="font-size:0.75rem;color:var(--text-mid);"><?= htmlspecialchars($apt['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="badge badge-<?= $apt['status'] ?>"><?= ucfirst($apt['status']) ?></span>
                </div>
                <?php if ($apt['status'] === 'scheduled'): ?>
                <form method="POST" style="margin-top:0.5rem;">
                    <input type="hidden" name="action" value="complete_appointment">
                    <input type="hidden" name="appt_id" value="<?= $apt['id'] ?>">
                    <button type="submit" class="btn btn-success btn-sm">Mark Completed</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Payment History -->
        <div class="card">
            <div style="padding:1rem 1.125rem;border-bottom:1px solid var(--border);font-weight:700;font-size:0.9rem;">
                💰 Payment History
            </div>
            <?php if (empty($payments)): ?>
            <div style="padding:1.5rem;text-align:center;color:var(--text-light);font-size:0.875rem;">
                No payments recorded yet.
            </div>
            <?php else: ?>
            <?php foreach ($payments as $p): ?>
            <div style="padding:0.75rem 1.125rem;border-bottom:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;color:var(--success);">
                            + <?= $currency ?><?= number_format((float)$p['amount'], 2) ?>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-mid);">
                            <?= htmlspecialchars($p['category_name']) ?>
                            &nbsp;·&nbsp; <?= ucfirst($p['payment_method']) ?>
                        </div>
                        <?php if ($p['received_by_name']): ?>
                        <div style="font-size:0.7rem;color:var(--text-light);">By: <?= htmlspecialchars($p['received_by_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:0.8rem;color:var(--text-mid);">
                        <?= date('d M Y', strtotime($p['payment_date'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <!-- Total paid summary -->
            <div style="padding:0.875rem 1.125rem;background:#f8fafc;display:flex;justify-content:space-between;">
                <span style="font-weight:700;font-size:0.875rem;">Total Paid:</span>
                <span style="font-weight:800;color:var(--success);"><?= $currency ?><?= number_format($grand_paid, 2) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Image Modal -->
<div id="imageModal" class="modal-overlay" onclick="closeImageModal(event)">
    <div class="modal-box" style="max-width:500px;background:transparent;box-shadow:none;text-align:center;">
        <img id="modalImg" src="" style="max-width:100%;max-height:80vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.5);">
        <div id="modalCaption" style="color:#fff;margin-top:15px;font-weight:700;font-size:1.2rem;text-shadow:0 2px 8px rgba(0,0,0,0.8);"></div>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
function togglePayForm(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

function openImageModal(src, name) {
    document.getElementById('modalImg').src = src;
    document.getElementById('modalCaption').innerText = name;
    document.getElementById('imageModal').classList.add('open');
}

function closeImageModal(e) {
    if (e.target.id === 'imageModal' || e.target.id === 'modalImg') {
        document.getElementById('imageModal').classList.remove('open');
    }
}
</script>
JS;
include ROOT_PATH . '/includes/footer.php';
?>
