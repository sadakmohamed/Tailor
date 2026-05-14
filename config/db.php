<?php
/**
 * TailorPro — Core Configuration
 * Edit DB_USER / DB_PASS / DB_NAME to match your MySQL setup.
 */

// ── Database credentials ────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Change to your MySQL username
define('DB_PASS', '');              // Change to your MySQL password
define('DB_NAME', 'tailor_db');

// ── App settings ────────────────────────────────────────────
define('APP_NAME', 'TailorPro');
define('CURRENCY', '$');

// ── Auto-detect project root paths ──────────────────────────
// ROOT_PATH = absolute filesystem path to project root
define('ROOT_PATH', dirname(__DIR__));

// BASE_URL = web URL prefix for links/assets
// Works with XAMPP (htdocs/Tailor), WAMP, or any server
if (!defined('BASE_URL')) {
    $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot    = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projRoot   = str_replace('\\', '/', ROOT_PATH);
    $docRootN   = str_replace('\\', '/', (string)$docRoot);
    $urlPath    = $docRoot ? str_replace($docRootN, '', $projRoot) : '/Tailor';
    define('BASE_URL', rtrim($protocol . '://' . $host . $urlPath, '/'));
}

// ── PDO singleton ────────────────────────────────────────────
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('
            <div style="font-family:sans-serif;max-width:600px;margin:4rem auto;padding:2rem;
                        background:#fef2f2;border:1px solid #fca5a5;border-radius:12px;color:#991b1b">
                <h2 style="margin:0 0 1rem">⚠️ Database Connection Error</h2>
                <p style="margin:0 0 .5rem"><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                <p style="margin:0;font-size:.875rem;color:#7f1d1d">
                    Please check your credentials in <code>config/db.php</code>
                    and make sure the <code>tailor_db</code> database exists.
                </p>
            </div>');
        }
    }
    return $pdo;
}
