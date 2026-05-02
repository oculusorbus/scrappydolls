<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if (auth_user()) redirect('/admin/dashboard.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'Session expired — please try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        if ($email === '' || $pass === '') {
            $error = 'Email and password are required.';
        } elseif (!auth_attempt($email, $pass)) {
            usleep(400000); // small delay to slow brute force
            $error = 'Incorrect email or password.';
        } else {
            redirect('/admin/dashboard.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Sign in — Scrappy Dolls Admin</title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/admin/styles.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..600;1,9..144,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-page">
<form class="login-card" method="post" autocomplete="on">
  <h1>Sign in</h1>
  <p class="sub">Scrappy Dolls admin</p>
  <?php if ($error): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
  <?php endif; ?>
  <?= csrf_field() ?>
  <div class="row" style="display:grid;gap:1rem;margin-bottom:1.25rem">
    <div class="field">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" name="password" id="password" required>
    </div>
  </div>
  <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Sign in</button>
</form>
</body>
</html>
