<?php
/**
 * TailorPro — Staff Management (Admin only)
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['admin']); // Admin only

$db   = getDB();
$user = currentUser();
$cid  = currentCompanyId();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add staff
    if ($action === 'add_staff') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$name)  $errors[] = 'Name is required.';
        if (!$email) $errors[] = 'Email is required.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

        if (!$errors) {
            $chk = $db->prepare('SELECT id FROM users WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $errors[] = 'Email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $db->prepare(
                    'INSERT INTO users (company_id, name, email, password, role) VALUES (?,?,?,?,?)'
                )->execute([$cid, $name, $email, $hash, 'staff']);
                $_SESSION['flash_success'] = "Staff member \"{$name}\" added.";
                header('Location: ' . BASE_URL . '/admin/staff.php');
                exit;
            }
        }
    }

    // Toggle staff status
    if ($action === 'toggle_status') {
        $uid    = (int)$_POST['user_id'];
        $status = $_POST['current_status'] === 'active' ? 'inactive' : 'active';
        $db->prepare('UPDATE users SET status = ? WHERE id = ? AND company_id = ? AND role = "staff"')
           ->execute([$status, $uid, $cid]);
        $_SESSION['flash_success'] = 'Staff status updated.';
        header('Location: ' . BASE_URL . '/admin/staff.php');
        exit;
    }

    // Reset password
    if ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = trim($_POST['new_password'] ?? '');
        if (strlen($pass) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare('UPDATE users SET password = ? WHERE id = ? AND company_id = ? AND role = "staff"')
               ->execute([$hash, $uid, $cid]);
            $_SESSION['flash_success'] = 'Staff password updated.';
            header('Location: ' . BASE_URL . '/admin/staff.php');
            exit;
        }
    }
}

$staff = $db->prepare('
    SELECT u.*,
           COUNT(DISTINCT o.id) AS orders_handled
    FROM   users u
    LEFT   JOIN orders o ON o.created_by = u.id
    WHERE  u.company_id = ? AND u.role = "staff"
    GROUP  BY u.id
    ORDER  BY u.created_at DESC
');
$staff->execute([$cid]);
$staff = $staff->fetchAll();

$pageTitle  = 'Staff Management';
$activePage = 'staff';
include ROOT_PATH . '/includes/header.php';
?>

<div class="responsive-sidebar-layout" style="max-width:1000px;">

    <!-- Staff list -->
    <div>
        <div class="card">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
                <h2 style="font-weight:700;font-size:1rem;color:var(--text);margin:0;">
                    Staff Members (<?= count($staff) ?>)
                </h2>
            </div>

            <?php if (empty($staff)): ?>
            <div style="padding:3rem;text-align:center;color:var(--text-light);">
                No staff added yet. Add your first staff member →
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($staff as $i => $s): ?>
                    <tr>
                        <td style="color:var(--text-light);font-size:0.8rem;"><?= $i + 1 ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:0.625rem;">
                                <div style="width:32px;height:32px;background:var(--gold);border-radius:50%;
                                             display:flex;align-items:center;justify-content:center;
                                             color:#fff;font-weight:700;font-size:0.75rem;flex-shrink:0;">
                                    <?= strtoupper(substr($s['name'],0,1)) ?>
                                </div>
                                <span style="font-weight:600;"><?= htmlspecialchars($s['name']) ?></span>
                            </div>
                        </td>
                        <td style="font-size:0.85rem;color:var(--text-mid);"><?= htmlspecialchars($s['email']) ?></td>
                        <td style="text-align:center;"><?= $s['orders_handled'] ?></td>
                        <td><span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
                        <td style="font-size:0.8rem;color:var(--text-mid);"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                        <td>
                            <div style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                                <button onclick="openResetModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')"
                                        class="btn btn-outline btn-sm">🔑 Reset</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle status?')">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $s['status'] ?>">
                                    <button type="submit"
                                            class="btn btn-sm <?= $s['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>">
                                        <?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Staff Form -->
    <div style="min-width:280px;">
        <div class="card" style="position:sticky;top:80px;">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
                <h3 style="font-weight:700;font-size:0.9rem;color:var(--text);margin:0;">Add New Staff</h3>
            </div>
            <div style="padding:1.25rem;">
                <?php if ($errors): ?>
                <div class="flash-error" style="margin-bottom:1rem;flex-direction:column;gap:0.2rem;align-items:flex-start;font-size:0.8rem;">
                    <?php foreach ($errors as $e): ?><div>⚠️ <?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                </div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add_staff">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-input" required
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Staff Name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-input" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="staff@shop.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-input" required minlength="6"
                               placeholder="Min 6 characters">
                    </div>
                    <button type="submit" class="btn btn-gold" style="width:100%;">Add Staff Member</button>
                </form>
            </div>
        </div>
    </div>

</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            🔑 Reset Staff Password
            <button onclick="closeResetModal()" style="background:none;border:none;cursor:pointer;color:var(--text-mid);">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_uid">
                <p style="font-size:0.875rem;color:var(--text-mid);margin:0 0 1rem;">
                    Resetting password for: <strong id="reset_name"></strong>
                </p>
                <div class="form-group">
                    <label class="form-label">New Password (min 6 chars)</label>
                    <input type="password" name="new_password" required minlength="6" class="form-input">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeResetModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
function openResetModal(uid, name) {
    document.getElementById('reset_uid').value = uid;
    document.getElementById('reset_name').textContent = name;
    document.getElementById('resetModal').classList.add('open');
}
function closeResetModal() {
    document.getElementById('resetModal').classList.remove('open');
}
</script>
JS;
include ROOT_PATH . '/includes/footer.php';
?>
