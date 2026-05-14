<?php
/**
 * TailorPro — All Customers (with search + filter)
 * Accessible by: admin + staff
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['admin', 'staff']);

$db   = getDB();
$user = currentUser();
$cid  = currentCompanyId();

$search = trim($_GET['q']      ?? '');
$filter = trim($_GET['filter'] ?? 'all'); // all | has_balance | today

$where  = 'WHERE cu.company_id = ?';
$params = [$cid];

if ($search) {
    $where   .= ' AND (cu.name LIKE ? OR cu.phone LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$customers = $db->prepare("
    SELECT cu.*,
           COALESCE(os.order_count, 0)    AS order_count,
           COALESCE(os.total_value, 0)    AS total_value,
           COALESCE(ps.total_paid,  0)    AS total_paid,
           (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.customer_id = cu.id AND a.status = 'scheduled') AS next_appointment
    FROM   customers cu
    LEFT   JOIN (
        SELECT o.customer_id,
               COUNT(o.id) AS order_count,
               SUM(o.total_price) AS total_value
        FROM   orders o
        GROUP  BY o.customer_id
    ) os ON os.customer_id = cu.id
    LEFT   JOIN (
        SELECT p.customer_id,
               SUM(p.amount) AS total_paid
        FROM   payments p
        GROUP  BY p.customer_id
    ) ps ON ps.customer_id = cu.id
    $where
    ORDER  BY cu.created_at DESC
");
$customers->execute($params);
$customers = $customers->fetchAll();

// Apply PHP-level filters after fetch
if ($filter === 'has_balance') {
    $customers = array_filter($customers, fn($c) => ((float)$c['total_value'] - (float)$c['total_paid']) > 0);
}
if ($filter === 'today') {
    $today     = date('Y-m-d');
    $customers = array_filter($customers, fn($c) => $c['next_appointment'] === $today);
}

$currency   = $user['currency'] ?? CURRENCY;
$pageTitle  = 'Customers';
$activePage = 'customers';
include ROOT_PATH . '/includes/header.php';
?>

<!-- Toolbar -->
<div class="dashboard-action-bar">
    <form method="GET" class="search-form">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div style="position:relative;flex:1;">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                 style="position:absolute;left:0.7rem;top:50%;transform:translateY(-50%);color:var(--text-light);">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search name or phone…"
                   class="form-input" style="padding-left:2.1rem;">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
        <a href="<?= BASE_URL ?>/admin/customers.php?filter=<?= $filter ?>" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
    </form>

    <a href="<?= BASE_URL ?>/admin/add_customer.php" class="btn btn-gold add-btn">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span>Add Customer</span>
    </a>
</div>

<!-- Filter chips (Now under search) -->
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.25rem;">
    <?php
    $filters = [
        'all'         => 'All Customers',
        'has_balance' => 'Has Balance',
        'today'       => "Today's Pickup",
    ];
    foreach ($filters as $fkey => $flabel):
        $isActive = $filter === $fkey;
    ?>
    <a href="?q=<?= urlencode($search) ?>&filter=<?= $fkey ?>"
       style="padding:0.35rem 0.875rem;border-radius:9999px;font-size:0.8rem;font-weight:500;
               text-decoration:none;border:1.5px solid <?= $isActive ? 'var(--navy-light)' : 'var(--border)' ?>;
               background:<?= $isActive ? 'var(--navy-light)' : '#fff' ?>;
               color:<?= $isActive ? '#fff' : 'var(--text-mid)' ?>;
               transition:all 0.15s;">
        <?= $flabel ?>
    </a>
    <?php endforeach; ?>
</div>

<style>
.dashboard-action-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.search-form { display: flex; gap: 0.5rem; flex: 1; min-width: 300px; }
.add-btn { white-space: nowrap; }

@media (max-width: 640px) {
    .dashboard-action-bar { flex-direction: column; align-items: stretch; }
    .search-form { width: 100%; order: 1; }
    .add-btn { width: 100%; order: 2; margin-top: 0.25rem; }
}
</style>

<div class="card">
    <div style="padding:0.75rem 1.125rem;border-bottom:1px solid var(--border);
                 font-size:0.8rem;color:var(--text-mid);">
        <?= count($customers) ?> customer(s) found
        <?= $search ? " — matching \"" . htmlspecialchars($search) . "\"" : '' ?>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Registered</th>
                    <th>Orders</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Payment</th>
                    <th>Next Pickup</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="11" style="text-align:center;color:var(--text-light);padding:3rem;">
                        No customers found.
                        <a href="<?= BASE_URL ?>/admin/add_customer.php" style="color:var(--gold);">Add one →</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php $i = 1; foreach ($customers as $cu): ?>
                <?php
                    $total   = (float)$cu['total_value'];
                    $paid    = (float)$cu['total_paid'];
                    $balance = $total - $paid;
                    $pct     = $total > 0 ? min(100, round(($paid / $total) * 100)) : 0;
                    $pay_class = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
                ?>
                <tr>
                    <td style="color:var(--text-light);font-size:0.8rem;"><?= $i++ ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/view_customer.php?id=<?= $cu['id'] ?>"
                           style="font-weight:600;color:var(--navy-light);text-decoration:none;">
                            <?= htmlspecialchars($cu['name']) ?>
                        </a>
                    </td>
                    <td style="font-size:0.85rem;"><?= htmlspecialchars($cu['phone']) ?></td>
                    <td style="font-size:0.8rem;color:var(--text-mid);"><?= date('d M Y', strtotime($cu['date_registered'])) ?></td>
                    <td style="text-align:center;"><?= $cu['order_count'] ?></td>
                    <td style="font-size:0.85rem;font-weight:600;"><?= $currency ?><?= number_format($total, 2) ?></td>
                    <td style="font-size:0.85rem;color:var(--success);"><?= $currency ?><?= number_format($paid, 2) ?></td>
                    <td style="font-size:0.85rem;font-weight:600;color:<?= $balance > 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= $balance > 0 ? $currency . number_format($balance, 2) : '—' ?>
                    </td>
                    <td><span class="badge badge-<?= $pay_class ?>"><?= ucfirst($pay_class) ?></span></td>
                    <td style="font-size:0.8rem;color:<?= ($cu['next_appointment'] === date('Y-m-d')) ? 'var(--warning)' : 'var(--text-mid)' ?>;">
                        <?= $cu['next_appointment'] ? date('d M Y', strtotime($cu['next_appointment'])) : '—' ?>
                    </td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/view_customer.php?id=<?= $cu['id'] ?>"
                           class="btn btn-outline btn-sm">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
