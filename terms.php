<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$pageTitle = 'Terms of Use';
$pageDesc  = 'Terms governing your use of scrappydolls.com and purchases from Scrappy Dolls.';
$pageUrl   = url('terms.php');
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
      <h1 class="h-display">Terms of <em style="color: var(--rose); font-style: italic; font-weight: 400;">Use</em></h1>
      <p class="effective">Effective <?= h($effective) ?></p>

      <p>Welcome. These terms ("Terms") govern your use of <a href="https://scrappydolls.com">scrappydolls.com</a> (the "Site") and purchases from Scrappy Dolls, a handmade-goods studio operated by Kanda Kay in San Antonio, Texas. By using the Site or placing an order, you agree to these Terms. If you do not agree, please don't use the Site.</p>

      <h2>About the products</h2>
      <p>Every Scrappy Doll is handmade and one of a kind. Photos on the Site represent the actual doll for sale, but slight variation in color and texture between screens and the physical piece is normal. Scrappy Dolls are textile art objects intended for display and adult collectors — they are not designed or tested as children's toys and may contain small or fragile elements.</p>

      <h2>Orders and pricing</h2>
      <p>Prices are shown in U.S. dollars. We reserve the right to correct pricing or product errors and to cancel any order affected by a clearly erroneous price; if we cancel for this reason, we will refund any amount paid in full.</p>
      <p>Because each doll is one of a kind, availability is updated in real time. In rare cases — for example, an in-person sale at a market — an item may sell between when you add it to your cart and when you check out. If that happens after you've paid, we will refund you in full.</p>

      <h2>Sales tax</h2>
      <p>Orders shipped to addresses in Texas are subject to Texas state and local sales tax, which is calculated at checkout based on your shipping address and added to your order total. Orders shipped outside of Texas are not currently charged sales tax by us; you may owe use tax in your own state.</p>

      <h2>Payment</h2>
      <p>Payments are processed by PayPal. You may pay with a PayPal account or, where supported, a credit or debit card through PayPal's guest checkout. We do not see or store your full payment details. Your order is confirmed only after PayPal successfully captures the payment.</p>

      <h2>Shipping</h2>
      <p>Orders typically ship within a few business days from San Antonio, Texas, via USPS. Once a package leaves our hands, delivery times are controlled by the carrier. Title and risk of loss pass to you when the carrier accepts the package. If your order is lost or damaged in transit, reach us through the <a href="/contact.php">contact form</a> and we'll help you file a claim with the carrier.</p>

      <h2>Returns and refunds</h2>
      <p>Because dolls are one-of-a-kind handmade pieces, we generally do not accept returns for change of mind. If a doll arrives damaged or materially different from its description, contact us within 7 days of delivery and we will work with you on a refund, replacement (when possible — these are one-of-a-kind), or repair. Custom and memory dolls are made from your own materials and are non-returnable.</p>

      <h2>Care</h2>
      <p>Spot clean only, with a damp cloth and mild soap if needed. Hand-washing, soaking, or laundering will loosen adhesives and can damage the doll. Damage from improper care is not covered.</p>

      <h2>Custom and memory dolls</h2>
      <p>Memory dolls are made from fabric you provide (clothing, quilts, etc.). Once we begin work on a custom or memory doll, the project is non-refundable. We will discuss design, materials, and expected timeline with you before starting. We cannot recover materials once cut or assembled.</p>

      <h2>Intellectual property</h2>
      <p>The Site's content — photographs, text, design, and the Scrappy Dolls and Art Safari Studio names and marks — is the property of Kanda Kay and may not be reproduced without permission. You retain ownership of any materials you provide for a custom or memory doll; by providing them, you grant us the limited right to use them to make your doll and to photograph the finished piece for portfolio and promotional use unless you ask us not to.</p>

      <h2>Acceptable use</h2>
      <p>Don't use the Site to do anything illegal, abusive, or designed to interfere with its operation. Don't attempt to access systems or data you're not authorized to access.</p>

      <h2>Disclaimers and limitation of liability</h2>
      <p>The Site and products are provided "as is." To the maximum extent permitted by law, we disclaim all implied warranties, including merchantability and fitness for a particular purpose. Our total liability arising out of any order is limited to the amount you paid for that order. We are not liable for indirect or consequential damages.</p>

      <h2>Governing law</h2>
      <p>These Terms are governed by the laws of the State of Texas, without regard to its conflict-of-laws rules. Any dispute will be brought in the state or federal courts located in Bexar County, Texas, and you consent to their jurisdiction.</p>

      <h2>Changes</h2>
      <p>We may update these Terms from time to time. The "Effective" date at the top reflects the latest version. Continued use of the Site after a change constitutes acceptance of the updated Terms.</p>

      <h2>Contact</h2>
      <p>Questions? Reach us through the <a href="/contact.php">contact form</a>. See also our <a href="/privacy.php">Privacy Policy</a>.</p>
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
