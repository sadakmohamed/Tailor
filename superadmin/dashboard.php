<?php
/**
 * TailorPro — Super Admin Dashboard
 * Lists all tailor companies with their admin accounts.
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['superadmin']);

$db   = getDB();
$user = currentUser();

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = trim($_POST['new_password'] ?? '');
        if ($uid > 0 && strlen($pass) >= 6) {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password = ? WHERE id = ? AND role = "admin"')
               ->execute([$hash, $uid]);
            $_SESSION['flash_success'] = 'Admin password updated successfully.';
        } else {
            $_SESSION['flash_error'] = 'Password must be at least 6 characters.';
        }
        header('Location: ' . BASE_URL . '/superadmin/dashboard.php');
        exit;
    }
    if ($_POST['action'] === 'toggle_company') {
        $cid    = (int)$_POST['company_id'];
        $status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        $db->prepare('UPDATE companies SET status = ? WHERE id = ?')->execute([$status, $cid]);
        $_SESSION['flash_success'] = 'Company status updated.';
        header('Location: ' . BASE_URL . '/superadmin/dashboard.php');
        exit;
    }
}

// Search
$search = trim($_GET['q'] ?? '');
$where  = $search ? 'WHERE c.name LIKE ?' : '';
$params = $search ? ["%$search%"] : [];

$companies = $db->prepare("
    SELECT c.*,
           u.id   AS admin_id,
           u.name AS admin_name,
           u.email AS admin_email,
           u.status AS admin_status,
           (SELECT COUNT(*) FROM customers cu WHERE cu.company_id = c.id) AS customer_count,
           (SELECT COUNT(*) FROM users us WHERE us.company_id = c.id AND us.role = 'staff') AS staff_count
    FROM   companies c
    LEFT   JOIN users u ON u.company_id = c.id AND u.role = 'admin'
    $where
    ORDER  BY c.created_at DESC
");
$companies->execute($params);
$companies = $companies->fetchAll();

// KPIs
$total_companies = $db->query('SELECT COUNT(*) FROM companies')->fetchColumn();
$total_admins    = $db->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn();
$total_staff     = $db->query('SELECT COUNT(*) FROM users WHERE role = "staff"')->fetchColumn();
$total_customers = $db->query('SELECT COUNT(*) FROM customers')->fetchColumn();

$pageTitle  = 'Companies';
$activePage = 'dashboard';
include ROOT_PATH . '/includes/header.php';
?>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.75rem;margin-bottom:1.5rem;">
    <?php
    $stats = [
        ['Total Companies', $total_companies, '#1e3a5f', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['Total Admins',     $total_admins,    '#b45309', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
        ['Total Staff',      $total_staff,     '#065f46', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
        ['Total Customers',  $total_customers, '#6d28d9', 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75'],
    ];
    foreach ($stats as [$label, $val, $color, $path]):
    ?>
    <div class="stat-card" style="display:flex;align-items:center;gap:1rem;">
        <div style="width:46px;height:46px;border-radius:12px;background:<?= $color ?>1a;
                     display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="22" height="22" fill="none" stroke="<?= $color ?>" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $path ?>"/>
            </svg>
        </div>
        <div>
            <div style="font-size:1.75rem;font-weight:800;color:var(--text);line-height:1;">
                <?= number_format((int)$val) ?>
            </div>
            <div style="font-size:0.75rem;color:var(--text-mid);margin-top:2px;"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Companies table header -->
<div class="card">
    <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);
                 display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
        <h2 style="font-weight:700;font-size:1rem;color:var(--text);margin:0;">
            All Companies
        </h2>
        <form method="GET" style="display:flex;gap:0.5rem;margin-left:auto;flex-wrap:wrap;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search company…" class="form-input" style="width:200px;">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <?php if ($search): ?>
            <a href="<?= BASE_URL ?>/superadmin/dashboard.php" class="btn btn-outline btn-sm">Clear</a>
            <?php endif; ?>
        </form>
        <a href="<?= BASE_URL ?>/superadmin/create_company.php" class="btn btn-gold btn-sm">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New Company
        </a>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Company</th>
                    <th>Contact</th>
                    <th>Admin</th>
                    <th>Customers</th>
                    <th>Staff</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($companies)): ?>
                <tr>
                    <td colspan="9" style="text-align:center;color:var(--text-light);padding:3rem;">
                        No companies found. <a href="<?= BASE_URL ?>/superadmin/create_company.php" style="color:var(--gold);">Create one →</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($companies as $i => $co): ?>
                <tr>
                    <td style="color:var(--text-light);font-size:0.8rem;"><?= $i + 1 ?></td>
                    <td>
                        <div style="font-weight:600;color:var(--text);"><?= htmlspecialchars($co['name']) ?></div>
                        <?php if ($co['address']): ?>
                        <div style="font-size:0.75rem;color:var(--text-light);"><?= htmlspecialchars(substr($co['address'], 0, 50)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size:0.8rem;"><?= htmlspecialchars($co['phone'] ?? '—') ?></div>
                        <div style="font-size:0.75rem;color:var(--text-light);"><?= htmlspecialchars($co['email'] ?? '') ?></div>
                    </td>
                    <td>
                        <?php if ($co['admin_name']): ?>
                        <div style="font-weight:500;font-size:0.85rem;"><?= htmlspecialchars($co['admin_name']) ?></div>
                        <div style="font-size:0.75rem;color:var(--text-light);"><?= htmlspecialchars($co['admin_email']) ?></div>
                        <?php else: ?>
                        <span style="color:var(--danger);font-size:0.8rem;">No admin</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;font-weight:700;"><?= $co['customer_count'] ?></td>
                    <td style="text-align:center;font-weight:700;"><?= $co['staff_count'] ?></td>
                    <td>
                        <span class="badge badge-<?= $co['status'] ?>"><?= ucfirst($co['status']) ?></span>
                    </td>
                    <td style="font-size:0.8rem;color:var(--text-mid);"><?= date('d M Y', strtotime($co['created_at'])) ?></td>
                    <td>
                        <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                            <?php if ($co['admin_id']): ?>
                            <button onclick="openPasswordModal(<?= $co['admin_id'] ?>, '<?= htmlspecialchars(addslashes($co['admin_name'])) ?>')"
                                    class="btn btn-outline btn-sm" title="Reset admin password">
                                🔑 Reset
                            </button>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Change company status?')">
                                <input type="hidden" name="action" value="toggle_company">
                                <input type="hidden" name="company_id" value="<?= $co['id'] ?>">
                                <input type="hidden" name="status" value="<?= $co['status'] ?>">
                                <button type="submit" class="btn btn-sm <?= $co['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>">
                                    <?= $co['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Password Reset Modal -->
<div id="passwordModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            🔑 Reset Admin Password
            <button onclick="closePasswordModal()" style="background:none;border:none;cursor:pointer;color:var(--text-mid);">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_password">
                <input type="hidden" name="user_id" id="modal_user_id">
                <p style="font-size:0.875rem;color:var(--text-mid);margin:0 0 1rem;">
                    Resetting password for: <strong id="modal_admin_name"></strong>
                </p>
                <div class="form-group">
                    <label class="form-label">New Password (min 6 chars)</label>
                    <input type="password" name="new_password" required minlength="6" class="form-input" placeholder="New password…">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closePasswordModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
function openPasswordModal(userId, adminName) {
    document.getElementById('modal_user_id').value  = userId;
    document.getElementById('modal_admin_name').textContent = adminName;
    document.getElementById('passwordModal').classList.add('open');
}
function closePasswordModal() {
    document.getElementById('passwordModal').classList.remove('open');
}
</script>
JS;
include ROOT_PATH . '/includes/footer.php';
?>
