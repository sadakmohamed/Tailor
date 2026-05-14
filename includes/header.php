<?php
/**
 * TailorPro — Shared Page Header
 * Include at the top of every authenticated page.
 * Expected variables before including:
 *   $pageTitle  (string)  — Page title
 *   $activePage (string)  — Active nav key (e.g. 'dashboard', 'customers')
 */
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TailorPro — Smart Tailor Management System">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: { DEFAULT: '#0f2340', mid: '#1a3356', light: '#1e3a5f' },
                        gold: { DEFAULT: '#d4a017', light: '#f0c040', muted: 'rgba(212,160,23,0.15)' }
                    }
                }
            }
        }
    </script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">

    <!-- Chart.js (for reports) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <?= $extraHead ?? '' ?>
</head>
<body>

<!-- Sidebar overlay (mobile) -->
<div id="sidebarOverlay" onclick="closeSidebar()"></div>

<div style="display:flex;min-height:100vh;width:100%;">

    <!-- ══════════════════ SIDEBAR ══════════════════ -->
    <aside id="sidebar">

        <!-- Brand / Logo -->
        <div style="padding:1.25rem 1rem; border-bottom:1px solid rgba(255,255,255,0.1);">
            <div style="display:flex;align-items:center;gap:0.75rem;">
                <div style="width:38px;height:38px;background:var(--gold);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <!-- Scissors icon -->
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/>
                        <line x1="20" y1="4" x2="8.12" y2="15.88"/>
                        <line x1="14.47" y1="14.48" x2="20" y2="20"/>
                        <line x1="8.12" y1="8.12" x2="12" y2="12"/>
                    </svg>
                </div>
                <div>
                    <div style="font-weight:700;color:#fff;font-size:0.9rem;line-height:1.2;"><?= APP_NAME ?></div>
                    <?php if ($user['role'] !== 'superadmin' && !empty($user['company_name'])): ?>
                    <div style="font-size:0.7rem;color:rgba(255,255,255,0.4);margin-top:1px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($user['company_name']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav style="flex:1;padding:1rem 0.75rem;overflow-y:auto;">
            <?php include ROOT_PATH . '/includes/sidebar_nav.php'; ?>
        </nav>

        <!-- User footer -->
        <div style="padding:1rem;border-top:1px solid rgba(255,255,255,0.08);">
            <div style="display:flex;align-items:center;gap:0.625rem;">
                <div style="width:32px;height:32px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.8rem;flex-shrink:0;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div style="min-width:0;">
                    <div style="font-size:0.8rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($user['name']) ?>
                    </div>
                    <div style="font-size:0.68rem;color:rgba(255,255,255,0.35);">
                        <?= ucfirst($user['role']) ?>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/logout.php" title="Logout"
                   style="margin-left:auto;color:rgba(255,255,255,0.3);transition:color 0.2s;"
                   onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='rgba(255,255,255,0.3)'">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- ══════════════════ MAIN AREA ══════════════════ -->
    <div id="main-wrapper" style="flex:1;display:flex;flex-direction:column;min-width:0;">

        <!-- Top bar -->
        <header style="background:#fff;border-bottom:1px solid var(--border);padding:0.875rem 1.25rem;
                        display:flex;align-items:center;gap:1rem;position:sticky;top:0;z-index:10;
                        box-shadow:0 1px 4px rgba(0,0,0,0.04);">
            <!-- Hamburger (mobile) -->
            <button id="menuBtn" onclick="openSidebar()"
                    style="display:none;background:none;border:none;cursor:pointer;padding:6px;border-radius:8px;color:var(--text-mid);">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <h1 style="font-size:1rem;font-weight:700;color:var(--text);margin:0;">
                <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
            </h1>

            <div style="flex:1;"></div>

            <!-- Date/Time -->
            <div style="font-size:0.75rem;color:var(--text-light);">
                <?= date('D, d M Y') ?>
            </div>

            <!-- User avatar -->
            <div style="width:34px;height:34px;background:var(--gold);border-radius:50%;
                         display:flex;align-items:center;justify-content:center;
                         color:#fff;font-weight:700;font-size:0.8rem;cursor:default;flex-shrink:0;"
                 title="<?= htmlspecialchars($user['name']) ?> — <?= ucfirst($user['role']) ?>">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
        </header>

        <!-- Flash messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="flash-success" style="margin:1rem 1.25rem 0;font-size:0.875rem;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <?= htmlspecialchars($_SESSION['flash_success']) ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="flash-error" style="margin:1rem 1.25rem 0;font-size:0.875rem;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <?= htmlspecialchars($_SESSION['flash_error']) ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <!-- Page content starts here -->
        <main style="flex:1;padding:1.25rem;">
