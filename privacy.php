<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$pageTitle = 'Privacy Policy';
$pageDesc  = 'How Scrappy Dolls collects, uses, and protects your information.';
$pageUrl   = url('privacy.php');
$effective = 'May 18, 2026';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> — Scrappy Dolls</title>
<meta name="description" content="<?= h($pageDesc) ?>">
<link rel="canonical" href="<?= h($pageUrl) ?>">
<meta name="robots" content="index,follow">
<meta name="theme-color" content="#b13e54">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
<link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png">
<link rel="apple-touch-icon" href="/favicon-192.png">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= h($pageTitle) ?> — Scrappy Dolls">
<meta property="og:description" content="<?= h($pageDesc) ?>">
<meta property="og:url" content="<?= h($pageUrl) ?>">
<meta property="og:site_name" content="Scrappy Dolls">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..900,0..100;1,9..144,300..900,0..100&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/shop/styles.css">
<?php require __DIR__ . '/lib/google_analytics.php'; ?>
<style>
.legal-wrap { max-width: 44rem; margin: 0 auto; padding: 2rem 0 4rem; }
.legal-wrap h1 { margin-bottom: 0.25rem; }
.legal-wrap .eyebrow { color: var(--rose); }
.legal-wrap .effective { color: var(--ink-muted, #8a7770); font-size: 0.9rem; margin: 0 0 2rem; }
.legal-wrap h2 { margin-top: 2rem; font-size: 1.2rem; }
.legal-wrap p, .legal-wrap li { line-height: 1.65; }
.legal-wrap ul { padding-left: 1.25rem; }
</style>
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
      <a href="/shop/">Shop</a>
      <a href="/#about">About</a>
      <a href="/contact.php">Contact</a>
      <a class="btn-mini" href="https://www.facebook.com/kandakayartist/" rel="noopener">Follow</a>
      <?php $sd_cart_n = cart_count(); ?>
      <a class="cart-link <?= $sd_cart_n > 0 ? 'has-items' : '' ?>" href="/shop/cart.php" aria-label="Cart (<?= $sd_cart_n ?> item<?= $sd_cart_n === 1 ? '' : 's' ?>)">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h2l2.4 12.3a2 2 0 0 0 2 1.7h8.2a2 2 0 0 0 2-1.6L21 8H6"/><circle cx="9" cy="21" r="1.2"/><circle cx="18" cy="21" r="1.2"/></svg>
        <span class="cart-count" data-cart-count><?= $sd_cart_n ?></span>
      </a>
    </nav>
  </div>
</header>

<main>
<section>
  <div class="wrap">
    <div class="legal-wrap">
      <p class="eyebrow">Legal</p>
      <h1 class="h-display">Privacy <em style="color: var(--rose); font-style: italic; font-weight: 400;">Policy</em></h1>
      <p class="effective">Effective <?= h($effective) ?></p>

      <p>Scrappy Dolls ("we," "us") is a small handmade-goods studio operated by Kanda Kay in San Antonio, Texas. This policy explains what information we collect when you visit <a href="https://scrappydolls.com">scrappydolls.com</a> or place an order, and how we use it. We aim to keep things simple: we collect what we need to fulfill orders and answer questions, and nothing else.</p>

      <h2>Information we collect</h2>
      <ul>
        <li><strong>Contact form submissions.</strong> When you message us through the contact form, we receive your name, email address, optional phone number, and the contents of your message.</li>
        <li><strong>Order information.</strong> When you place an order, we collect your name, shipping address, email address, and the items you ordered. We use your shipping address to calculate sales tax where required (e.g., Texas) and to deliver your order.</li>
        <li><strong>Payment information.</strong> Payments are processed by PayPal. We do not see or store your credit card number, bank account, or PayPal password. If you sign in with PayPal during checkout, PayPal shares with us only the profile information you authorize — typically your name, email address, shipping address, and a PayPal account identifier.</li>
        <li><strong>Analytics and server logs.</strong> We use Google Analytics to understand how visitors find and use the site (pages viewed, approximate location, device type). Our server also keeps standard request logs (IP address, browser type, requested page) for a limited period to diagnose problems and protect against abuse.</li>
        <li><strong>Cookies.</strong> We use a small number of cookies — to keep your shopping cart between pages, to prevent spam on the contact form (Cloudflare Turnstile), and for analytics. You can disable cookies in your browser, but the cart will not function without them.</li>
      </ul>

      <h2>How we use your information</h2>
      <ul>
        <li>To fulfill and ship your order, including calculating any applicable sales tax.</li>
        <li>To respond to your questions or messages.</li>
        <li>To send order-related emails (confirmation, shipping updates). We do not run a marketing email list and will not send you promotional email.</li>
        <li>To prevent fraud, abuse, and spam.</li>
        <li>To understand site usage in aggregate so we can improve the site.</li>
      </ul>

      <h2>Who we share information with</h2>
      <p>We do not sell, rent, or trade your information. We share it only with the service providers we need to operate the shop:</p>
      <ul>
        <li><strong>PayPal</strong> — for payment processing and (if you use it) login.</li>
        <li><strong>Shipping carriers</strong> (e.g., USPS) — for delivering your order.</li>
        <li><strong>Google Analytics</strong> — for aggregate site analytics.</li>
        <li><strong>Cloudflare</strong> — for Turnstile spam protection on the contact form.</li>
        <li><strong>Our web host and email provider</strong> — to store data and deliver emails on our behalf.</li>
      </ul>
      <p>We may also disclose information if required by law (e.g., a valid subpoena or court order).</p>

      <h2>Data retention</h2>
      <p>We retain order records for as long as needed for accounting, tax, and customer-service purposes, generally several years. Contact form submissions are kept while we work on the conversation and for a reasonable period afterward. You can ask us to delete your information by contacting us, subject to any retention we're required by law to maintain.</p>

      <h2>Your choices</h2>
      <p>You can ask us what information we have about you, request a copy, ask us to correct it, or ask us to delete it. Email us at <a href="mailto:<?= h(config('mail.support_email') ?: 'hello@scrappydolls.com') ?>"><?= h(config('mail.support_email') ?: 'hello@scrappydolls.com') ?></a> and we'll respond within a reasonable time. If you're a resident of a state with specific privacy rights (such as California or Texas), you may have additional rights — we will honor them on request.</p>

      <h2>Children</h2>
      <p>Scrappy Dolls are textile art pieces intended for adult collectors and display, and the site is not directed to children under 13. We do not knowingly collect information from children under 13. If you believe a child has provided us information, please contact us and we will delete it.</p>

      <h2>Security</h2>
      <p>The site is served over HTTPS, and payment processing happens directly between you and PayPal. We take reasonable steps to protect the information we hold, but no system is 100% secure.</p>

      <h2>Changes</h2>
      <p>We may update this policy from time to time. The "Effective" date at the top reflects the latest version. Material changes will be flagged on this page.</p>

      <h2>Contact</h2>
      <p>Questions about this policy? Email <a href="mailto:<?= h(config('mail.support_email') ?: 'hello@scrappydolls.com') ?>"><?= h(config('mail.support_email') ?: 'hello@scrappydolls.com') ?></a> or use the <a href="/contact.php">contact form</a>.</p>
    </div>
  </div>
</section>
</main>

<footer class="site">
  <div class="wrap">
    <div class="row">
      <div>
        <p class="sig">Scrappy Dolls</p>
        <p style="margin: 0;"><a href="https://www.facebook.com/kandakayartist/" rel="noopener">from Art Safari Studio · Handmade by Kanda Kay</a></p>
      </div>
      <div style="text-align: right;">
        <p style="margin: 0 0 0.5rem;"><a href="/privacy.php">Privacy</a> · <a href="/terms.php">Terms</a> · <a href="https://www.facebook.com/kandakayartist/" rel="noopener">Facebook</a></p>
        <p class="legal">&copy; <?= date('Y') ?> Scrappy Dolls · San Antonio, Texas.</p>
      </div>
    </div>
  </div>
</footer>
</body>
</html>
