<?php
/**
 * TailorPro — Reports (Admin only)
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['admin']);

$db   = getDB();
$user = currentUser();
$cid  = currentCompanyId();

// Date range
$date_from = $_GET['from'] ?? date('Y-m-01');  // First day of month
$date_to   = $_GET['to']   ?? date('Y-m-d');   // Today
$currency  = $user['currency'] ?? CURRENCY;

// ── Revenue by day ──────────────────────────────────────
$revenue = $db->prepare("
    SELECT DATE(p.payment_date) AS day,
           SUM(p.amount)         AS total
    FROM   payments p
    WHERE  p.company_id = ?
      AND  p.payment_date BETWEEN ? AND ?
    GROUP  BY DATE(p.payment_date)
    ORDER  BY day
");
$revenue->execute([$cid, $date_from, $date_to]);
$revenue_rows = $revenue->fetchAll();
$total_revenue = array_sum(array_column($revenue_rows, 'total'));

// ── Orders by category ──────────────────────────────────
$by_cat = $db->prepare("
    SELECT o.category_name,
           COUNT(*) AS order_count,
           SUM(o.total_price) AS cat_total
    FROM   orders o
    WHERE  o.company_id = ?
      AND  DATE(o.created_at) BETWEEN ? AND ?
    GROUP  BY o.category_name
    ORDER  BY order_count DESC
");
$by_cat->execute([$cid, $date_from, $date_to]);
$by_cat = $by_cat->fetchAll();

// ── Pending payments ────────────────────────────────────
$pending = $db->prepare("
    SELECT cu.id, cu.name, cu.phone,
           COALESCE(os.owed, 0) AS owed,
           COALESCE(ps.paid_sum, 0) AS paid_sum
    FROM   customers cu
    LEFT   JOIN (
        SELECT o.customer_id, SUM(o.total_price) AS owed
        FROM   orders o
        GROUP  BY o.customer_id
    ) os ON os.customer_id = cu.id
    LEFT   JOIN (
        SELECT p.customer_id, SUM(p.amount) AS paid_sum
        FROM   payments p
        GROUP  BY p.customer_id
    ) ps ON ps.customer_id = cu.id
    WHERE  cu.company_id = ?
    HAVING (owed - paid_sum) > 0
    ORDER  BY (owed - paid_sum) DESC
    LIMIT  20
");
$pending->execute([$cid]);
$pending = $pending->fetchAll();
$total_pending = array_sum(array_map(fn($r) => $r['owed'] - $r['paid_sum'], $pending));

// ── Upcoming appointments ───────────────────────────────
$upcoming_apts = $db->prepare("
    SELECT a.*, cu.name AS customer_name, cu.phone, o.category_name
    FROM   appointments a
    JOIN   customers cu ON cu.id = a.customer_id
    JOIN   orders    o  ON o.id  = a.order_id
    WHERE  a.company_id = ?
      AND  a.appointment_date BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY)
      AND  a.status = 'scheduled'
    ORDER  BY a.appointment_date, a.appointment_time
");
$upcoming_apts->execute([$cid, date('Y-m-d'), date('Y-m-d')]);
$upcoming_apts = $upcoming_apts->fetchAll();

// ── Quick summary stats ─────────────────────────────────
$period_orders = $db->prepare("SELECT COUNT(*) FROM orders WHERE company_id = ? AND DATE(created_at) BETWEEN ? AND ?");
$period_orders->execute([$cid, $date_from, $date_to]);
$period_orders = $period_orders->fetchColumn();

$new_customers = $db->prepare("SELECT COUNT(*) FROM customers WHERE company_id = ? AND date_registered BETWEEN ? AND ?");
$new_customers->execute([$cid, $date_from, $date_to]);
$new_customers = $new_customers->fetchColumn();

// Chart data
$chart_labels = json_encode(array_column($by_cat, 'category_name'));
$chart_data   = json_encode(array_column($by_cat, 'order_count'));
$rev_labels   = json_encode(array_column($revenue_rows, 'day'));
$rev_data     = json_encode(array_column($revenue_rows, 'total'));

$pageTitle  = 'Reports';
$activePage = 'reports';
include ROOT_PATH . '/includes/header.php';
?>

<!-- Date range filter -->
<div class="card" style="margin-bottom:1.25rem;padding:1rem 1.25rem;">
    <form method="GET" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
        <label style="font-size:0.8rem;font-weight:600;color:var(--text-mid);">Date Range:</label>
        <input type="date" name="from" value="<?= $date_from ?>" class="form-input" style="width:160px;">
        <span style="color:var(--text-mid);">to</span>
        <input type="date" name="to" value="<?= $date_to ?>" class="form-input" style="width:160px;">
        <button type="submit" class="btn btn-primary">Apply</button>
        <!-- Quick ranges -->
        <div style="display:flex;gap:0.4rem;margin-left:auto;flex-wrap:wrap;">
            <?php
            $ranges = [
                'Today'     => [date('Y-m-d'), date('Y-m-d')],
                'This Week' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
                'This Month'=> [date('Y-m-01'), date('Y-m-d')],
                'Last Month'=> [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last month'))],
            ];
            foreach ($ranges as $label => [$f, $t]):
            ?>
            <a href="?from=<?= $f ?>&to=<?= $t ?>"
               class="btn btn-outline btn-sm"
               style="<?= ($date_from === $f && $date_to === $t) ? 'background:var(--navy-light);color:#fff;border-color:var(--navy-light);' : '' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
        <a href="javascript:window.print()" class="btn btn-outline btn-sm no-print">🖨 Print</a>
    </form>
</div>

<!-- KPI Summary -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.75rem;margin-bottom:1.5rem;">
    <?php
    $report_stats = [
        ['Revenue',        $currency . number_format($total_revenue, 2), '#d4a017', '#fef3c7'],
        ['Total Pending',  $currency . number_format($total_pending, 2), '#ef4444', '#fee2e2'],
        ['Period Orders',  $period_orders,     '#1e40af', '#dbeafe'],
        ['New Customers',  $new_customers,     '#065f46', '#d1fae5'],
    ];
    foreach ($report_stats as [$label, $val, $color, $bg]):
    ?>
    <div class="stat-card">
        <div style="font-size:1.5rem;font-weight:800;color:<?= $color ?>;"><?= $val ?></div>
        <div style="font-size:0.75rem;color:var(--text-mid);margin-top:3px;"><?= $label ?></div>
        <div style="font-size:0.7rem;color:var(--text-light);margin-top:2px;">
            <?= date('d M', strtotime($date_from)) ?> – <?= date('d M Y', strtotime($date_to)) ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="responsive-grid-2" style="margin-bottom:1.25rem;">
    <!-- Revenue chart -->
    <div class="card" style="padding:1.25rem;">
        <h3 style="font-weight:700;font-size:0.9rem;margin:0 0 1rem;">Daily Revenue</h3>
        <?php if (empty($revenue_rows)): ?>
        <div style="text-align:center;color:var(--text-light);padding:2rem;">No revenue in this period.</div>
        <?php else: ?>
        <canvas id="revenueChart" height="200"></canvas>
        <?php endif; ?>
    </div>
    <!-- Orders by category -->
    <div class="card" style="padding:1.25rem;">
        <h3 style="font-weight:700;font-size:0.9rem;margin:0 0 1rem;">Orders by Category</h3>
        <?php if (empty($by_cat)): ?>
        <div style="text-align:center;color:var(--text-light);padding:2rem;">No orders in this period.</div>
        <?php else: ?>
        <canvas id="catChart" height="200"></canvas>
        <?php endif; ?>
    </div>
</div>

<!-- Category breakdown table -->
<?php if (!empty($by_cat)): ?>
<div class="card" style="margin-bottom:1.25rem;">
    <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
        <h3 style="font-weight:700;font-size:0.9rem;margin:0;">Orders by Category — Details</h3>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Category</th><th>Orders</th><th>Total Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($by_cat as $row): ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($row['category_name']) ?></td>
                <td><?= $row['order_count'] ?></td>
                <td style="font-weight:700;color:var(--success);"><?= $currency ?><?= number_format((float)$row['cat_total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Pending payments -->
<?php if (!empty($pending)): ?>
<div class="card" style="margin-bottom:1.25rem;">
    <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:0.5rem;">
        <span style="color:var(--danger);">⚠️</span>
        <h3 style="font-weight:700;font-size:0.9rem;margin:0;">Outstanding Balances</h3>
        <span style="margin-left:auto;font-weight:700;color:var(--danger);">
            Total: <?= $currency ?><?= number_format($total_pending, 2) ?>
        </span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Customer</th><th>Phone</th><th>Total Owed</th><th>Paid</th><th>Balance</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pending as $p): ?>
            <?php $bal = $p['owed'] - $p['paid_sum']; ?>
            <tr>
                <td style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['phone']) ?></td>
                <td><?= $currency ?><?= number_format((float)$p['owed'], 2) ?></td>
                <td style="color:var(--success);"><?= $currency ?><?= number_format((float)$p['paid_sum'], 2) ?></td>
                <td style="font-weight:700;color:var(--danger);"><?= $currency ?><?= number_format($bal, 2) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/admin/view_customer.php?id=<?= $p['id'] ?>"
                       class="btn btn-outline btn-sm">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Upcoming appointments -->
<?php if (!empty($upcoming_apts)): ?>
<div class="card">
    <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
        <h3 style="font-weight:700;font-size:0.9rem;margin:0;">Upcoming Appointments (Next 30 Days)</h3>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Time</th><th>Customer</th><th>Phone</th><th>Item</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($upcoming_apts as $apt): ?>
            <tr style="<?= $apt['appointment_date'] === date('Y-m-d') ? 'background:#fef3c7;' : '' ?>">
                <td style="font-weight:600;">
                    <?= date('d M Y', strtotime($apt['appointment_date'])) ?>
                    <?= $apt['appointment_date'] === date('Y-m-d') ? '<span class="badge badge-scheduled" style="margin-left:4px;">Today</span>' : '' ?>
                </td>
                <td><?= $apt['appointment_time'] ? date('h:i A', strtotime($apt['appointment_time'])) : '—' ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($apt['customer_name']) ?></td>
                <td><?= htmlspecialchars($apt['phone']) ?></td>
                <td><?= htmlspecialchars($apt['category_name']) ?></td>
                <td><span class="badge badge-<?= $apt['status'] ?>"><?= ucfirst($apt['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$extraScripts = '<script>';

if (!empty($revenue_rows)):
$extraScripts .= <<<JS
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: {$rev_labels},
        datasets: [{
            label: 'Revenue ({$currency})',
            data: {$rev_data},
            backgroundColor: 'rgba(212,160,23,0.7)',
            borderColor: 'rgba(212,160,23,1)',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
JS;
endif;

if (!empty($by_cat)):
$extraScripts .= <<<JS
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: {$chart_labels},
        datasets: [{
            data: {$chart_data},
            backgroundColor: ['#0f2340','#d4a017','#10b981','#ef4444','#6366f1','#f59e0b','#3b82f6','#8b5cf6'],
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: true,
        plugins: { legend: { position: 'right' } }
    }
});
JS;
endif;

$extraScripts .= '</script>';
include ROOT_PATH . '/includes/footer.php';
?>
