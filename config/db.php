<?php
/**
 * TailorPro — Core Configuration
 * Edit DB_USER / DB_PASS / DB_NAME to match your MySQL setup.
 */

// ── Load .env file without Composer ─────────────────────────
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
} else {
    die("Error: .env file not found. Please create one.");
}

// ── Database credentials ────────────────────────────────────
$dbHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '';
$dbUser = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? '';
$dbPass = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '';
$dbName = $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? '';

if (empty($dbHost) || empty($dbUser) || empty($dbName)) {
    die("Error: Database credentials are missing. Please check your .env file on the server and ensure DB_HOST, DB_USERNAME, DB_PASSWORD, and DB_DATABASE are filled out.");
}

define('DB_HOST', $dbHost);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_NAME', $dbName);

// ── App settings ────────────────────────────────────────────
define('APP_NAME', $_ENV['APP_NAME'] ?? 'TailorPro');
define('CURRENCY', $_ENV['CURRENCY'] ?? '$');

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
