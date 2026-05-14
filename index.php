<?php
/**
 * TailorPro — Login Page
 */
require_once __DIR__ . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → redirect
if (isset($_SESSION['user'])) {
    $r = $_SESSION['user']['role'];
    header('Location: ' . BASE_URL . ($r === 'superadmin' ? '/superadmin/dashboard.php' : '/admin/dashboard.php'));
    exit;
}

$error  = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
$notice = ($_GET['msg'] ?? '') === 'logged_out' ? 'You have been logged out.' : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TailorPro — Login to your tailor management dashboard">
    <title>Login — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
</head>
<body>
<div class="login-bg flex items-center justify-center min-h-screen p-4 relative z-10">

    <div class="w-full max-w-md relative z-10">

        <!-- Logo / Brand -->
        <div style="text-align:center;margin-bottom:2rem;">
            <div style="width:64px;height:64px;background:var(--gold);border-radius:16px;
                         display:inline-flex;align-items:center;justify-content:center;
                         box-shadow:0 8px 32px rgba(212,160,23,0.35);margin-bottom:1rem;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/>
                    <line x1="20" y1="4" x2="8.12" y2="15.88"/>
                    <line x1="14.47" y1="14.48" x2="20" y2="20"/>
                    <line x1="8.12" y1="8.12" x2="12" y2="12"/>
                </svg>
            </div>
            <h1 style="font-size:1.875rem;font-weight:800;color:#fff;margin:0 0 0.25rem;">
                <?= APP_NAME ?>
            </h1>
            <p style="color:rgba(255,255,255,0.45);font-size:0.875rem;margin:0;">
                Tailor Management System
            </p>
        </div>

        <!-- Login card -->
        <div class="login-card" style="padding:2.5rem 2rem;">

            <h2 style="font-size:1.25rem;font-weight:700;color:#fff;margin:0 0 0.375rem;">
                Welcome back
            </h2>
            <p style="font-size:0.8125rem;color:rgba(255,255,255,0.4);margin:0 0 1.75rem;">
                Sign in to continue to your dashboard
            </p>

            <!-- Alerts -->
            <?php if ($error): ?>
            <div style="background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.3);
                         color:#fca5a5;padding:0.75rem 1rem;border-radius:0.5rem;
                         font-size:0.875rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($notice): ?>
            <div style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);
                         color:#6ee7b7;padding:0.75rem 1rem;border-radius:0.5rem;
                         font-size:0.875rem;margin-bottom:1.25rem;">
                <?= htmlspecialchars($notice) ?>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="<?= BASE_URL ?>/auth/login.php" method="POST" id="loginForm">

                <div style="margin-bottom:1.125rem;">
                    <label for="email"
                           style="display:block;font-size:0.8125rem;font-weight:500;
                                  color:rgba(255,255,255,0.7);margin-bottom:0.4rem;">
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           placeholder="you@example.com"
                           style="width:100%;background:rgba(255,255,255,0.08);
                                  border:1.5px solid rgba(255,255,255,0.15);
                                  border-radius:0.5rem;padding:0.75rem 1rem;
                                  color:#fff;font-size:0.9rem;font-family:inherit;
                                  outline:none;transition:border-color 0.2s,box-shadow 0.2s;
                                  box-sizing:border-box;"
                           onfocus="this.style.borderColor='var(--gold)';this.style.boxShadow='0 0 0 3px rgba(212,160,23,0.15)'"
                           onblur="this.style.borderColor='rgba(255,255,255,0.15)';this.style.boxShadow='none'">
                </div>

                <div style="margin-bottom:1.5rem;">
                    <label for="password"
                           style="display:block;font-size:0.8125rem;font-weight:500;
                                  color:rgba(255,255,255,0.7);margin-bottom:0.4rem;">
                        Password
                    </label>
                    <div style="position:relative;">
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               style="width:100%;background:rgba(255,255,255,0.08);
                                      border:1.5px solid rgba(255,255,255,0.15);
                                      border-radius:0.5rem;padding:0.75rem 1rem;
                                      padding-right:3rem;
                                      color:#fff;font-size:0.9rem;font-family:inherit;
                                      outline:none;transition:border-color 0.2s,box-shadow 0.2s;
                                      box-sizing:border-box;"
                               onfocus="this.style.borderColor='var(--gold)';this.style.boxShadow='0 0 0 3px rgba(212,160,23,0.15)'"
                               onblur="this.style.borderColor='rgba(255,255,255,0.15)';this.style.boxShadow='none'">
                        <button type="button" onclick="togglePassword()"
                                style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);
                                        background:none;border:none;cursor:pointer;color:rgba(255,255,255,0.35);
                                        padding:0;display:flex;">
                            <svg id="eyeIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" id="loginBtn"
                        style="width:100%;background:var(--gold);color:#fff;font-weight:700;
                                font-size:0.9375rem;padding:0.875rem;border-radius:0.5rem;border:none;
                                cursor:pointer;font-family:inherit;
                                transition:background 0.2s,transform 0.15s,box-shadow 0.2s;
                                box-shadow:0 4px 20px rgba(212,160,23,0.3);"
                        onmouseover="this.style.background='var(--gold-light)'"
                        onmouseout="this.style.background='var(--gold)'"
                        onmousedown="this.style.transform='scale(0.98)'"
                        onmouseup="this.style.transform='scale(1)'">
                    Sign In
                </button>
            </form>
        </div>

        <p style="text-align:center;margin-top:1.5rem;font-size:0.75rem;color:rgba(255,255,255,0.2);">
            &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.
        </p>
    </div>
</div>

<script>
function togglePassword() {
    const pwd = document.getElementById('password');
    pwd.type = pwd.type === 'password' ? 'text' : 'password';
}

// Show loading state on submit
document.getElementById('loginForm').addEventListener('submit', function () {
    const btn = document.getElementById('loginBtn');
    btn.textContent = 'Signing in…';
    btn.disabled = true;
    btn.style.opacity = '0.7';
});
</script>
</body>
</html>
