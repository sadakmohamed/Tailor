<?php
/**
 * TailorPro — Session middleware & role helpers
 * Include this at the TOP of every protected page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require authentication and optionally restrict to specific roles.
 * Redirects to login if not authenticated or unauthorized.
 *
 * @param array $roles   Allowed roles, e.g. ['admin','staff']. Empty = any role.
 */
function requireAuth(array $roles = []): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . '/');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['user']['role'], $roles, true)) {
        // Redirect back to their own dashboard
        $r = $_SESSION['user']['role'];
        if ($r === 'superadmin') {
            header('Location: ' . BASE_URL . '/superadmin/dashboard.php');
        } else {
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
        }
        exit;
    }
}

/** Return the full user array from session, or null. */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Return the current user's role string, or null. */
function currentRole(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

/** Return the current user's company_id, or null (for superadmin). */
function currentCompanyId(): ?int
{
    $cid = $_SESSION['user']['company_id'] ?? null;
    return $cid !== null ? (int)$cid : null;
}

/** Check if currently logged-in user is admin or superadmin. */
function isAdmin(): bool
{
    return in_array(currentRole(), ['admin', 'superadmin'], true);
}
