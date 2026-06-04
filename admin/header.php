<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();
$user = auth_user();
$page = $page ?? 'dashboard';
$title = $title ?? 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> — Scrappy Dolls Admin</title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<?php $sd_admin_css_v = (int)(@filemtime(__DIR__ . '/styles.css') ?: 0); ?>
<link rel="stylesheet" href="/admin/styles.css?v=<?= $sd_admin_css_v ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..600;1,9..144,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app">
<aside class="sidebar">
  <a class="brand" href="/admin/dashboard.php">scrappy<em>dolls</em><span class="tag">admin</span></a>
  <nav>
    <a href="/admin/dashboard.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="/admin/products.php"  class="<?= $page === 'products'  ? 'active' : '' ?>">Dolls</a>
    <a href="/admin/orders.php"    class="<?= $page === 'orders'    ? 'active' : '' ?>">Orders</a>
    <a href="/admin/coupons.php"   class="<?= $page === 'coupons'   ? 'active' : '' ?>">Coupons</a>
    <a href="/admin/reports.php"   class="<?= $page === 'reports'   ? 'active' : '' ?>">Reports</a>
  </nav>
  <div class="sidebar-foot">
    <p class="who"><?= h($user['name'] ?? $user['email']) ?></p>
    <a href="/admin/logout.php" class="logout">Sign out</a>
    <a href="/" class="back">View site →</a>
  </div>
</aside>
<main class="content">
<?php
foreach (['success', 'error', 'info'] as $kind) {
    $msg = flash($kind);
    if ($msg) {
        echo '<div class="flash flash-' . h($kind) . '">' . h($msg) . '</div>';
    }
}
?>
