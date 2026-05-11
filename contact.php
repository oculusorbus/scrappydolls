<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

$pageTitle = 'Contact';
$pageDesc  = 'Get in touch with Scrappy Dolls — questions about a doll, custom commissions, or order help.';
$pageUrl   = url('contact.php');

$turnstileConfigured = turnstile_is_configured();
$turnstileSiteKey    = $turnstileConfigured ? turnstile_site_key() : '';
$csrfToken           = csrf_token();
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
<meta property="og:type" content="website">
<meta property="og:title" content="<?= h($pageTitle) ?> — Scrappy Dolls">
<meta property="og:description" content="<?= h($pageDesc) ?>">
<meta property="og:url" content="<?= h($pageUrl) ?>">
<meta property="og:site_name" content="Scrappy Dolls">
<meta property="og:image" content="<?= h(url('images/og-image.jpg')) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($pageTitle) ?> — Scrappy Dolls">
<meta name="twitter:description" content="<?= h($pageDesc) ?>">
<meta name="twitter:image" content="<?= h(url('images/og-image.jpg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..900,0..100;1,9..144,300..900,0..100&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/shop/styles.css">
<?php if ($turnstileConfigured): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?php require __DIR__ . '/lib/google_analytics.php'; ?>
<style>
.contact-wrap { max-width: 42rem; margin: 0 auto; padding: 2rem 0 4rem; }
.contact-head { margin-bottom: 1.5rem; }
.contact-head .eyebrow { color: var(--rose); }
.contact-help { margin: 0.25rem 0 0.75rem; color: var(--ink-soft, #6b5852); font-size: 0.95rem; line-height: 1.55; }
.contact-section {
  border: 1px solid var(--rule, #ead7d2);
  border-radius: 14px;
  padding: 1.25rem 1.25rem 1.5rem;
}
.contact-row { display: grid; gap: 0.85rem; margin-top: 0.85rem; grid-template-columns: 1fr; }
@media (min-width: 640px) {
  .contact-row.split { grid-template-columns: 1fr 1fr; }
}
.contact-row label {
  display: flex; flex-direction: column; gap: 0.3rem;
  font-size: 0.88rem; color: var(--ink-soft, #6b5852);
}
.contact-row input,
.contact-row textarea {
  font: inherit;
  padding: 0.7rem 0.85rem;
  border: 1px solid var(--rule, #ead7d2);
  border-radius: 8px;
  background: #fff;
  color: var(--ink, #2c1f1c);
}
.contact-row textarea { resize: vertical; min-height: 8rem; }
.contact-row input:focus,
.contact-row textarea:focus {
  outline: none;
  border-color: var(--rose, #b13e54);
  box-shadow: 0 0 0 3px rgba(177,62,84,0.15);
}
.contact-turnstile { margin-top: 1rem; }
.contact-submit { width: 100%; margin-top: 0.75rem; justify-content: center; }
.contact-submit[disabled] { opacity: 0.7; cursor: not-allowed; }
.flash { display: none; padding: 0.85rem 1rem; border-radius: 8px; margin: 1rem 0; }
.flash.flash-error { background: #fde8eb; color: #7a1d2c; }
.flash.flash-success { background: #e6f4ea; color: #1f5a2e; }
/* Honeypot: hidden from real users but bots see it in the DOM and fill it */
.contact-hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
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
      <a href="/contact.php" class="is-current">Contact</a>
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
    <div class="contact-wrap">
      <div class="contact-head">
        <p class="eyebrow">Get in touch</p>
        <h1 class="h-display">Say <em style="color: var(--rose); font-style: italic; font-weight: 400;">hello</em>.</h1>
        <p class="contact-help">Question about a doll, custom commission, an order, or anything else — drop a note here and we'll get back to you. Replies usually come within a day or two.</p>
      </div>

      <div id="contact-error" class="flash flash-error" role="alert"></div>
      <div id="contact-success" class="flash flash-success" role="status">
        <strong>Thanks — your message is on its way.</strong> We'll be in touch soon. If it's urgent, you can also reach us on <a href="https://www.facebook.com/kandakayartist/" rel="noopener">Facebook</a>.
      </div>

      <form id="contact-form" class="contact-section" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

        <div class="contact-row">
          <label>Your name
            <input type="text" name="name" required maxlength="100" autocomplete="name">
          </label>
        </div>
        <div class="contact-row split">
          <label>Email
            <input type="email" name="email" required maxlength="255" autocomplete="email">
          </label>
          <label>Phone <span style="opacity:.6">(optional)</span>
            <input type="tel" name="phone" maxlength="40" autocomplete="tel">
          </label>
        </div>
        <div class="contact-row">
          <label>Subject
            <input type="text" name="subject" required maxlength="200" placeholder="What's this about?">
          </label>
        </div>
        <div class="contact-row">
          <label>Message
            <textarea name="message" required maxlength="5000" rows="6" placeholder="Tell us what's on your mind…"></textarea>
          </label>
        </div>

        <!-- Honeypot: hidden from people, irresistible to dumb bots. -->
        <div class="contact-hp" aria-hidden="true">
          <label>Website (leave blank)<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <?php if ($turnstileConfigured): ?>
          <div class="contact-turnstile cf-turnstile" data-sitekey="<?= h($turnstileSiteKey) ?>" data-theme="light"></div>
        <?php else: ?>
          <div class="flash flash-error" style="display:block;margin-top:1rem">
            <strong>Heads up:</strong> Turnstile keys haven't been configured yet. The form is up but submissions will be rejected until <code>turnstile.site_key</code> and <code>turnstile.secret_key</code> are filled in <code>config/config.php</code>.
          </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary contact-submit" <?= $turnstileConfigured ? '' : 'disabled' ?>>Send message</button>
      </form>
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
        <p style="margin: 0 0 0.5rem;"><a href="https://www.facebook.com/kandakayartist/" rel="noopener">Facebook</a></p>
        <p class="legal">&copy; <?= date('Y') ?> Scrappy Dolls · San Antonio, Texas.</p>
      </div>
    </div>
  </div>
</footer>

<script>
(function(){
  var form = document.getElementById('contact-form');
  var errBox = document.getElementById('contact-error');
  var okBox  = document.getElementById('contact-success');
  var submitBtn = form.querySelector('.contact-submit');
  if (submitBtn.disabled) return; // Turnstile not configured — leave form alone.

  function showErr(msg) {
    okBox.style.display = 'none';
    errBox.textContent = msg || 'Something went wrong. Please try again.';
    errBox.style.display = 'block';
    errBox.scrollIntoView({behavior: 'smooth', block: 'center'});
  }
  function showOk() {
    errBox.style.display = 'none';
    okBox.style.display = 'block';
    form.style.display = 'none';
    okBox.scrollIntoView({behavior: 'smooth', block: 'center'});
  }

  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    if (!form.reportValidity()) return;
    errBox.style.display = 'none';

    var fd = new FormData(form);
    submitBtn.disabled = true;
    var prev = submitBtn.textContent;
    submitBtn.textContent = 'Sending…';

    fetch('/api/contact-send.php', { method: 'POST', body: fd })
      .then(function(r){ return r.json().then(function(j){ return { status: r.status, body: j }; }); })
      .then(function(res){
        if (res.body.error) throw new Error(res.body.error);
        showOk();
      })
      .catch(function(err){
        submitBtn.disabled = false;
        submitBtn.textContent = prev;
        showErr(err && err.message ? err.message : '');
        // Reset Turnstile so the buyer can try again with a fresh token.
        if (window.turnstile && typeof window.turnstile.reset === 'function') {
          try { window.turnstile.reset(); } catch (e) {}
        }
      });
  });
})();
</script>
</body>
</html>
