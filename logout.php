<?php
/**
 * TailorPro — Logout
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

session_unset();
session_destroy();

header('Location: ' . BASE_URL . '/?msg=logged_out');
exit;
