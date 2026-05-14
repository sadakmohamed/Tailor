<?php
/**
 * TailorPro — Create Company + Admin
 */
require_once __DIR__ . '/../config/db.php';
require_once ROOT_PATH . '/auth/middleware.php';
requireAuth(['superadmin']);

$db     = getDB();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Company fields
    $co_name    = trim($_POST['co_name']    ?? '');
    $co_address = trim($_POST['co_address'] ?? '');
    $co_phone   = trim($_POST['co_phone']   ?? '');
    $co_email   = trim($_POST['co_email']   ?? '');
    $currency   = trim($_POST['currency']   ?? '$');
    // Admin fields
    $ad_name    = trim($_POST['ad_name']    ?? '');
    $ad_email   = trim($_POST['ad_email']   ?? '');
    $ad_password= trim($_POST['ad_password']?? '');

    if (!$co_name)  $errors[] = 'Company name is required.';
    if (!$ad_name)  $errors[] = 'Admin name is required.';
    if (!$ad_email) $errors[] = 'Admin email is required.';
    elseif (!filter_var($ad_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid admin email.';
    if (strlen($ad_password) < 6) $errors[] = 'Admin password must be at least 6 characters.';

    // Check email uniqueness
    if (!$errors) {
        $chk = $db->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$ad_email]);
        if ($chk->fetch()) $errors[] = 'This email is already registered.';
    }

    if (!$errors) {
        try {
            $db->beginTransaction();

            // Insert company
            $ins = $db->prepare(
                'INSERT INTO companies (name, address, phone, email, currency) VALUES (?,?,?,?,?)'
            );
            $ins->execute([$co_name, $co_address, $co_phone, $co_email, $currency]);
            $company_id = (int)$db->lastInsertId();

            // Insert admin user
            $hash = password_hash($ad_password, PASSWORD_BCRYPT);
            $db->prepare(
                'INSERT INTO users (company_id, name, email, password, role) VALUES (?,?,?,?,?)'
            )->execute([$company_id, $ad_name, $ad_email, $hash, 'admin']);

            // Seed default categories
            $cats = ['Shirt', 'Pant', 'Kamis'];
            $catIns = $db->prepare('INSERT INTO categories (company_id, name) VALUES (?,?)');
            foreach ($cats as $cat) {
                $catIns->execute([$company_id, $cat]);
            }

            $db->commit();
            $_SESSION['flash_success'] = "Company \"{$co_name}\" created with admin account!";
            header('Location: ' . BASE_URL . '/superadmin/dashboard.php');
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle  = 'New Company';
$activePage = 'create_company';
include ROOT_PATH . '/includes/header.php';
?>

<div style="max-width:720px;">

    <!-- Breadcrumb -->
    <div style="font-size:0.8rem;color:var(--text-mid);margin-bottom:1.25rem;">
        <a href="<?= BASE_URL ?>/superadmin/dashboard.php" style="color:var(--gold);text-decoration:none;">Companies</a>
        &rsaquo; Create New Company
    </div>

    <?php if ($errors): ?>
    <div class="flash-error" style="margin-bottom:1.25rem;flex-direction:column;align-items:flex-start;gap:0.25rem;">
        <?php foreach ($errors as $e): ?>
        <div style="display:flex;align-items:center;gap:0.4rem;">
            <span>⚠️</span> <?= htmlspecialchars($e) ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="createCompanyForm">

        <!-- Company Info -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
                <span class="section-title">Company Information</span>
            </div>
            <div style="padding:1.25rem;">
                <div class="responsive-grid-2">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Company Name <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="co_name" class="form-input" required
                               value="<?= htmlspecialchars($_POST['co_name'] ?? '') ?>"
                               placeholder="e.g. Addis Tailor Shop">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="co_phone" class="form-input"
                               value="<?= htmlspecialchars($_POST['co_phone'] ?? '') ?>"
                               placeholder="+251 91 234 5678">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="co_email" class="form-input"
                               value="<?= htmlspecialchars($_POST['co_email'] ?? '') ?>"
                               placeholder="info@shop.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" name="currency" class="form-input" maxlength="5"
                               value="<?= htmlspecialchars($_POST['currency'] ?? '$') ?>"
                               placeholder="$ or Br or €">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" name="co_address" class="form-input"
                               value="<?= htmlspecialchars($_POST['co_address'] ?? '') ?>"
                               placeholder="Street, City">
                    </div>
                </div>
                <div style="margin-top:0.5rem;padding:0.75rem;background:#f8fafc;border-radius:0.5rem;font-size:0.8rem;color:var(--text-mid);">
                    💡 Default categories (Shirt, Pant, Kamis) will be auto-created for this company.
                </div>
            </div>
        </div>

        <!-- Admin Info -->
        <div class="card" style="margin-bottom:1.25rem;">
            <div style="padding:1.125rem 1.25rem;border-bottom:1px solid var(--border);">
                <span class="section-title">Admin Account</span>
            </div>
            <div style="padding:1.25rem;">
                <div class="responsive-grid-2">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">Admin Full Name <span style="color:var(--danger);">*</span></label>
                        <input type="text" name="ad_name" class="form-input" required
                               value="<?= htmlspecialchars($_POST['ad_name'] ?? '') ?>"
                               placeholder="Manager Name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Admin Email <span style="color:var(--danger);">*</span></label>
                        <input type="email" name="ad_email" class="form-input" required
                               value="<?= htmlspecialchars($_POST['ad_email'] ?? '') ?>"
                               placeholder="admin@shop.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Admin Password <span style="color:var(--danger);">*</span></label>
                        <input type="password" name="ad_password" class="form-input" required minlength="6"
                               placeholder="Min 6 characters">
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:0.75rem;">
            <button type="submit" class="btn btn-gold">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                Create Company
            </button>
            <a href="<?= BASE_URL ?>/superadmin/dashboard.php" class="btn btn-outline">Cancel</a>
        </div>

    </form>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
