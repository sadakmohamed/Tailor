<?php
/**
 * TailorPro — Login POST Handler
 */
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → redirect
if (isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role'];
    if ($role === 'superadmin') {
        header('Location: ' . BASE_URL . '/superadmin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if (!$email || !$password) {
    $_SESSION['login_error'] = 'Please enter your email and password.';
    header('Location: ' . BASE_URL . '/');
    exit;
}

$db   = getDB();
$stmt = $db->prepare('
    SELECT u.id, u.name, u.email, u.password, u.role, u.status,
           u.company_id, c.name AS company_name, c.currency
    FROM   users u
    LEFT   JOIN companies c ON c.id = u.company_id
    WHERE  u.email = ?
    LIMIT  1
');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: ' . BASE_URL . '/');
    exit;
}

if ($user['status'] !== 'active') {
    $_SESSION['login_error'] = 'Your account is inactive. Contact your administrator.';
    header('Location: ' . BASE_URL . '/');
    exit;
}

// Store user data in session (never store the raw password)
unset($user['password']);
$_SESSION['user'] = $user;

// Redirect by role
if ($user['role'] === 'superadmin') {
    header('Location: ' . BASE_URL . '/superadmin/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
}
exit;
