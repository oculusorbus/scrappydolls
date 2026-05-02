<?php
declare(strict_types=1);

/**
 * One-time setup wizard. Visit https://yoursite.com/setup.php once after
 * uploading the code + creating /config/config.php. Creates the first
 * admin user. Self-locks once an admin exists.
 *
 * DELETE THIS FILE after you're done — or it'll just refuse to run.
 */

require_once __DIR__ . '/lib/bootstrap.php';

// If any admin already exists, refuse.
try {
    $count = (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
} catch (Throwable $e) {
    setup_render('Database error', 'The database is not reachable, or the schema has not been imported yet. '
        . 'Run <code>sql/schema.sql</code> against your database, then refresh this page. '
        . '<br><br>Error: ' . h($e->getMessage()));
    exit;
}

if ($count > 0) {
    setup_render('Setup already complete', 'An admin user already exists. <strong>Delete this file (<code>setup.php</code>) from your server.</strong> '
        . 'Then sign in at <a href="/admin/login.php">/admin/login.php</a>.');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired. Please try again.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $name  = trim((string)($_POST['name'] ?? ''));
        $pw    = (string)($_POST['password'] ?? '');
        $pw2   = (string)($_POST['password2'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($pw) < 10) $errors[] = 'Password must be at least 10 characters.';
        if ($pw !== $pw2) $errors[] = 'Passwords don’t match.';
        if (!$errors) {
            $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = db()->prepare('INSERT INTO admin_users (email, password_hash, name) VALUES (:e, :h, :n)');
            $stmt->execute([':e' => $email, ':h' => $hash, ':n' => $name ?: null]);
            setup_render('Done!',
                'Admin user created.<br><br>'
                . '<strong>1.</strong> <a href="/admin/login.php">Sign in →</a><br>'
                . '<strong>2.</strong> Delete <code>setup.php</code> from the server (it self-locks but cleaner to remove).<br><br>'
                . 'You can also test PayPal in sandbox mode (default) before going live — set <code>paypal.environment</code> to <code>"live"</code> in <code>config/config.php</code> when ready.');
            exit;
        }
    }
}

setup_form($errors);

function setup_render(string $title, string $body): void {
    ?><!doctype html><html><head><meta charset="utf-8"><title><?= h($title) ?> — Scrappy Dolls Setup</title>
    <link rel="stylesheet" href="/admin/styles.css"></head>
    <body class="login-page"><div class="login-card"><h1><?= h($title) ?></h1>
    <p style="color:var(--ink-soft)"><?= $body /* trusted */ ?></p></div></body></html><?php
}

function setup_form(array $errors): void {
    ?><!doctype html><html><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Setup — Scrappy Dolls</title>
    <link rel="stylesheet" href="/admin/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..600;1,9..144,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    </head><body class="login-page">
    <form class="login-card" method="post" autocomplete="on">
      <h1>Create admin</h1>
      <p class="sub">First-time setup for the Scrappy Dolls store.</p>
      <?php foreach ($errors as $e): ?><div class="flash flash-error"><?= h($e) ?></div><?php endforeach; ?>
      <?= csrf_field() ?>
      <div style="display:grid;gap:1rem;margin-bottom:1.25rem">
        <div class="field"><label>Your name</label><input type="text" name="name" placeholder="Kanda Kay" value="<?= h($_POST['name'] ?? '') ?>"></div>
        <div class="field"><label>Email</label><input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>"></div>
        <div class="field"><label>Password</label><input type="password" name="password" required minlength="10"></div>
        <div class="field"><label>Confirm password</label><input type="password" name="password2" required minlength="10"></div>
      </div>
      <button class="btn btn-primary" style="width:100%;justify-content:center">Create admin</button>
      <p style="margin-top:1.25rem;font-size:.78rem;color:var(--ink-muted);text-align:center">
        Delete <code>setup.php</code> from the server when you're done.
      </p>
    </form>
    </body></html><?php
}
