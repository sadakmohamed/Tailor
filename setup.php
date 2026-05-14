<?php
/**
 * TailorPro — Initial Setup
 * Run this ONCE in your browser: http://localhost/Tailor/setup.php
 * Then DELETE this file for security.
 */
require_once __DIR__ . '/config/db.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']    ?? '');
    $email    = trim($_POST['email']   ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (!$name || !$email || !$password) {
        $message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id FROM users WHERE role = "superadmin" LIMIT 1');
        $stmt->execute();
        if ($stmt->fetch()) {
            $message = 'A superadmin already exists. Delete this file for security.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $ins  = $db->prepare(
                'INSERT INTO users (company_id, name, email, password, role) VALUES (NULL, ?, ?, ?, "superadmin")'
            );
            $ins->execute([$name, $email, $hash]);
            $success = true;
            $message = 'Superadmin created successfully! <strong>DELETE this file now</strong> for security.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup — <?= APP_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 flex items-center justify-center p-4">
<div class="w-full max-w-md bg-white/10 backdrop-blur-xl rounded-2xl border border-white/20 shadow-2xl p-8">
    <div class="text-center mb-6">
        <div class="w-14 h-14 bg-amber-500 rounded-2xl flex items-center justify-center mx-auto mb-3">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-white"><?= APP_NAME ?> Setup</h1>
        <p class="text-slate-400 text-sm mt-1">Create your superadmin account</p>
    </div>

    <?php if ($message): ?>
    <div class="mb-4 p-3 rounded-lg text-sm <?= $success ? 'bg-green-500/20 text-green-300 border border-green-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30' ?>">
        <?= $message ?>
    </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-sm text-slate-300 mb-1">Full Name</label>
            <input type="text" name="name" required
                   class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition"
                   placeholder="Super Admin">
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Email</label>
            <input type="email" name="email" required
                   class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition"
                   placeholder="superadmin@example.com">
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Password (min 8 chars)</label>
            <input type="password" name="password" required minlength="8"
                   class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition"
                   placeholder="••••••••">
        </div>
        <div>
            <label class="block text-sm text-slate-300 mb-1">Confirm Password</label>
            <input type="password" name="confirm" required
                   class="w-full bg-white/10 border border-white/20 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-amber-500 transition"
                   placeholder="••••••••">
        </div>
        <button type="submit"
                class="w-full bg-amber-500 hover:bg-amber-400 text-white font-semibold py-3 rounded-lg transition-colors">
            Create Superadmin
        </button>
    </form>
    <?php else: ?>
    <div class="text-center">
        <a href="<?= BASE_URL ?>/index.php"
           class="inline-block bg-amber-500 hover:bg-amber-400 text-white font-semibold px-6 py-3 rounded-lg transition-colors">
            Go to Login →
        </a>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
