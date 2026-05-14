<?php
/**
 * TailorPro — Admin Dashboard
 * Accessible by: admin + staff
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['admin', 'staff']);

$db      = getDB();
$user    = currentUser();
$cid     = currentCompanyId();
$today   = date('Y-m-d');

// ── KPIs ──────────────────────────────────────────────────
$total_customers = $db->prepare('SELECT COUNT(*) FROM customers WHERE company_id = ?');
$total_customers->execute([$cid]);
$total_customers = $total_customers->fetchColumn();

$total_orders = $db->prepare('SELECT COUNT(*) FROM orders WHERE company_id = ?');
$total_orders->execute([$cid]);
$total_orders = $total_orders->fetchColumn();

$pending_orders = $db->prepare("SELECT COUNT(*) FROM orders WHERE company_id = ? AND status IN ('pending','in_progress')");
$pending_orders->execute([$cid]);
$pending_orders = $pending_orders->fetchColumn();

// Revenue this month
$month_revenue = $db->prepare("
    SELECT COALESCE(SUM(p.amount), 0)
    FROM   payments p
    WHERE  p.company_id = ? AND DATE_FORMAT(p.payment_date,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')
");
$month_revenue->execute([$cid]);
$month_revenue = $month_revenue->fetchColumn();

// Pending balance across all orders
$pending_balance = $db->prepare("
    SELECT COALESCE(SUM(sub.balance), 0)
    FROM (
        SELECT o.id,
               o.total_price - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.order_id = o.id), 0) AS balance
        FROM   orders o
        WHERE  o.company_id = ? AND o.status NOT IN ('delivered')
        HAVING balance > 0
    ) sub
");
$pending_balance->execute([$cid]);
$pending_balance = $pending_balance->fetchColumn();

// Today's appointments
$today_appointments = $db->prepare("
    SELECT a.*, cu.name AS customer_name, cu.phone, o.category_name
    FROM   appointments a
    JOIN   customers cu ON cu.id = a.customer_id
    JOIN   orders o     ON o.id  = a.order_id
    WHERE  a.company_id = ? AND a.appointment_date = ? AND a.status = 'scheduled'
    ORDER  BY a.appointment_time ASC
");
$today_appointments->execute([$cid, $today]);
$today_appointments = $today_appointments->fetchAll();

// Recent customers (Synchronized with Customers page columns)
$search = trim($_GET['q'] ?? '');
$recent_params = [$cid];
$search_clause = '';
if ($search) {
    $search_clause = 'AND (cu.name LIKE ? OR cu.phone LIKE ?)';
    $recent_params[] = "%$search%";
    $recent_params[] = "%$search%";
}

$recent_query = $db->prepare("
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
    WHERE  cu.company_id = ? $search_clause
    ORDER  BY cu.created_at DESC
    LIMIT  15
");
$recent_query->execute($recent_params);
$recent_customers = $recent_query->fetchAll();

// Upcoming appointments (next 7 days, excluding today)
$upcoming = $db->prepare("
    SELECT a.*, cu.name AS customer_name, o.category_name, o.status AS order_status
    FROM   appointments a
    JOIN   customers cu ON cu.id = a.customer_id
    JOIN   orders o     ON o.id  = a.order_id
    WHERE  a.company_id = ?
      AND  a.appointment_date > ?
      AND  a.appointment_date <= DATE_ADD(?, INTERVAL 7 DAY)
      AND  a.status = 'scheduled'
    ORDER  BY a.appointment_date, a.appointment_time
    LIMIT  8
");
$upcoming->execute([$cid, $today, $today]);
$upcoming = $upcoming->fetchAll();

$currency   = $user['currency'] ?? CURRENCY;
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include ROOT_PATH . '/includes/header.php';
?>

<!-- ── Top action bar ───────────────────────────────────── -->
<div class="dashboard-action-bar">
    <form method="GET" class="search-form">
        <div style="position:relative;flex:1;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                 style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);color:var(--text-light);">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search customer..."
                   class="form-input" style="padding-left:2.25rem;">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline btn-sm">Clear</a>
        <?php endif; ?>
    </form>
    <a href="<?= BASE_URL ?>/admin/add_customer.php" class="btn btn-gold add-btn">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span>Add New Customer</span>
    </a>
</div>

<style>
.dashboard-action-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
.search-form { display: flex; gap: 0.5rem; flex: 1; min-width: 300px; }
.add-btn { white-space: nowrap; }

@media (max-width: 640px) {
    .dashboard-action-bar { flex-direction: column; align-items: stretch; }
    .search-form { width: 100%; order: 1; }
    .add-btn { width: 100%; order: 2; margin-top: 0.25rem; }
}
</style>

<?php if (!$search): ?>
<!-- ── KPI Cards ─────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.75rem;margin-bottom:1.5rem;">

    <?php
    $kpis = [
        [$total_customers, 'Total Customers',   '#1e3a5f', '#e0e7ff', 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75'],
        [$total_orders,    'Total Orders',       '#065f46', '#d1fae5', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        [$pending_orders,  'Pending Orders',     '#92400e', '#fef3c7', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0'],
        [count($today_appointments), "Today's Appointments", '#1e40af', '#dbeafe', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ];
    foreach ($kpis as [$val, $label, $color, $bg, $path]):
    ?>
    <div class="stat-card">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.625rem;">
            <div style="width:40px;height:40px;border-radius:10px;background:<?= $bg ?>;
                         display:flex;align-items:center;justify-content:center;">
                <svg width="20" height="20" fill="none" stroke="<?= $color ?>" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= $path ?>"/>
                </svg>
            </div>
        </div>
        <div style="font-size:1.875rem;font-weight:800;color:var(--text);line-height:1.1;">
            <?= is_numeric($val) ? number_format((float)$val) : $val ?>
        </div>
        <div style="font-size:0.75rem;color:var(--text-mid);margin-top:3px;"><?= $label ?></div>
    </div>
    <?php endforeach; ?>

    <!-- Revenue card -->
    <div class="stat-card" style="background:linear-gradient(135deg,#0f2340,#1e3a5f);border:none;">
        <div style="margin-bottom:0.625rem;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(212,160,23,0.2);
                         display:flex;align-items:center;justify-content:center;">
                <svg width="20" height="20" fill="none" stroke="#d4a017" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
        <div style="font-size:1.5rem;font-weight:800;color:var(--gold-light);line-height:1.1;">
            <?= $currency ?><?= number_format((float)$month_revenue, 2) ?>
        </div>
        <div style="font-size:0.75rem;color:rgba(255,255,255,0.45);margin-top:3px;">Revenue This Month</div>
        <?php if ($pending_balance > 0): ?>
        <div style="font-size:0.7rem;color:#fca5a5;margin-top:6px;">
            <?= $currency ?><?= number_format((float)$pending_balance, 2) ?> outstanding
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Today's Appointments ──────────────────────────────── -->
<?php if (!empty($today_appointments)): ?>
<div class="card" style="margin-bottom:1.25rem;border-left:4px solid var(--gold);">
    <div style="padding:0.875rem 1.125rem;border-bottom:1px solid var(--border);
                 display:flex;align-items:center;gap:0.5rem;">
        <svg width="16" height="16" fill="none" stroke="var(--gold)" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0"/>
        </svg>
        <span style="font-weight:700;font-size:0.9rem;color:var(--text);">
            Today's Appointments (<?= count($today_appointments) ?>)
        </span>
    </div>
    <div style="padding:0.75rem 1.125rem;display:flex;flex-wrap:wrap;gap:0.75rem;">
        <?php foreach ($today_appointments as $apt): ?>
        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:0.5rem;padding:0.625rem 0.875rem;min-width:200px;">
            <div style="font-weight:600;font-size:0.85rem;color:#92400e;">
                <?= htmlspecialchars($apt['customer_name']) ?>
            </div>
            <div style="font-size:0.75rem;color:#b45309;">
                📞 <?= htmlspecialchars($apt['phone']) ?>
            </div>
            <div style="font-size:0.75rem;color:#92400e;margin-top:3px;">
                👕 <?= htmlspecialchars($apt['category_name']) ?>
                <?php if ($apt['appointment_time']): ?>
                &nbsp;⏰ <?= date('h:i A', strtotime($apt['appointment_time'])) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Main grid: Recent Customers + Upcoming Appointments ─ -->
<div style="display:block; width:100%; max-width:100%; overflow:hidden;">
    <?php if (!empty($upcoming) && !$search): ?>
    <div style="display:grid;grid-template-columns:1fr;gap:1.25rem;align-items:start;width:100%;max-width:100%;">
    <?php endif; ?>

    <!-- Recent Customers (Synced Table) -->
    <div class="card">
        <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);
                     display:flex;align-items:center;justify-content:space-between;">
            <h2 style="font-weight:700;font-size:0.9rem;color:var(--text);margin:0;">
                <?= $search ? "Search Results" : 'Recent Customers' ?>
            </h2>
            <a href="<?= BASE_URL ?>/admin/customers.php" style="font-size:0.8rem;color:var(--gold);text-decoration:none;">
                View all →
            </a>
        </div>
        <div class="table-wrap" style="width:100%; max-width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; display:block;">
            <table class="data-table" style="min-width:900px; width:100%; table-layout:auto;">
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
                <?php if (empty($recent_customers)): ?>
                    <tr>
                        <td colspan="11" style="text-align:center;color:var(--text-light);padding:2.5rem;">
                            No customers yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i=1; foreach ($recent_customers as $cu): ?>
                    <?php
                        $total   = (float)$cu['total_value'];
                        $paid    = (float)$cu['total_paid'];
                        $balance = $total - $paid;
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

    <!-- Upcoming Appointments sidebar -->
    <?php if (!empty($upcoming) && !$search): ?>
    <div class="card" style="margin-top:1.25rem;">
        <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
            <h2 style="font-weight:700;font-size:0.9rem;color:var(--text);margin:0;">
                Upcoming Appointments
            </h2>
        </div>
        <div style="padding:0.75rem;">
            <?php foreach ($upcoming as $apt): ?>
            <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.7rem 0.5rem;
                         border-bottom:1px solid var(--border);">
                <div style="text-align:center;min-width:40px;">
                    <div style="font-size:1.2rem;font-weight:800;color:var(--navy-light);line-height:1;">
                        <?= date('d', strtotime($apt['appointment_date'])) ?>
                    </div>
                    <div style="font-size:0.65rem;color:var(--text-light);text-transform:uppercase;">
                        <?= date('M', strtotime($apt['appointment_date'])) ?>
                    </div>
                </div>
                <div style="min-width:0;">
                    <div style="font-weight:600;font-size:0.83rem;color:var(--text);">
                        <?= htmlspecialchars($apt['customer_name']) ?>
                    </div>
                    <div style="font-size:0.75rem;color:var(--text-mid);">
                        <?= htmlspecialchars($apt['category_name']) ?>
                    </div>
                    <span class="badge badge-<?= $apt['order_status'] ?>" style="margin-top:3px;">
                        <?= ucfirst(str_replace('_',' ',$apt['order_status'])) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
