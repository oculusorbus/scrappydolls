<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
$pageTitle = $pageTitle ?? 'Shop';
$pageDesc  = $pageDesc  ?? 'Available one-of-a-kind handmade cloth dolls by Kanda Kay.';
$pageImage = $pageImage ?? url('images/og-image.jpg');
$pageUrl   = $pageUrl   ?? url($_SERVER['REQUEST_URI'] ?? '/shop/');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> — Scrappy Dolls</title>
<meta name="description" content="<?= h($pageDesc) ?>">
<link rel="canonical" href="<?= h($pageUrl) ?>">
<meta name="theme-color" content="#b13e54">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
<link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png">
<link rel="apple-touch-icon" href="/favicon-192.png">
<meta property="og:type" content="<?= isset($ogType) ? h($ogType) : 'website' ?>">
<meta property="og:title" content="<?= h($pageTitle) ?> — Scrappy Dolls">
<meta property="og:description" content="<?= h($pageDesc) ?>">
<meta property="og:url" content="<?= h($pageUrl) ?>">
<meta property="og:site_name" content="Scrappy Dolls">
<meta property="og:image" content="<?= h($pageImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($pageTitle) ?> — Scrappy Dolls">
<meta name="twitter:description" content="<?= h($pageDesc) ?>">
<meta name="twitter:image" content="<?= h($pageImage) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..900,0..100;1,9..144,300..900,0..100&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/shop/styles.css">
</head>
<body>
<header class="site">
  <div class="wrap">
    <div class="brand">
      <a class="brand-name" href="/">scrappy<em>dolls</em></a>
      <a class="brand-attribution" href="https://www.facebook.com/kandakayartist/" target="_blank" rel="noopener" aria-label="From Art Safari Studio, handmade by Kanda Kay — visit on Facebook">
        <span class="brand-by">from Art Safari Studio</span>
        <span class="brand-artist">Handmade by Kanda Kay</span>
      </a>
    </div>
    <nav class="primary" aria-label="Primary">
      <a href="/">Home</a>
      <a href="/shop/" class="<?= ($_SERVER['REQUEST_URI'] === '/shop/' || $_SERVER['REQUEST_URI'] === '/shop/index.php') ? 'is-current' : '' ?>">Shop</a>
      <a href="/#about">About</a>
      <a class="btn-mini" href="https://www.facebook.com/kandakayartist/" rel="noopener">Follow</a>
    </nav>
  </div>
</header>
<main>
