<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') redirect('/shop/');

$stmt = db()->prepare('SELECT * FROM products WHERE slug = :slug LIMIT 1');
$stmt->execute([':slug' => $slug]);
$product = $stmt->fetch();
if (!$product) {
    http_response_code(404);
    require __DIR__ . '/header.php';
    echo '<section class="wrap-narrow" style="padding:6rem 1.5rem;text-align:center"><h1 class="h-display">Doll not found</h1><p style="margin-top:1rem"><a class="btn btn-primary" href="/shop/">See available dolls</a></p></section>';
    require __DIR__ . '/footer.php';
    exit;
}

$imgs = db()->prepare('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC');
$imgs->execute([':id' => $product['id']]);
$images = $imgs->fetchAll();

$pageTitle = $product['title'];
$pageDesc  = $product['description']
    ? mb_substr(preg_replace('/\s+/', ' ', $product['description']), 0, 155)
    : "{$product['title']} — handmade one-of-a-kind cloth doll by Kanda Kay.";
$pageImage = $images ? url('uploads/' . $images[0]['filename']) : url('images/og-image.jpg');
$pageUrl   = url('shop/product.php?slug=' . urlencode($product['slug']));
$ogType    = 'product';

track_view('/shop/product/' . $product['slug'], (int)$product['id']);

require __DIR__ . '/header.php';
?>

<section>
  <div class="wrap">
    <a class="back-link" href="/shop/">← Back to shop</a>

    <div class="product">
      <div class="product-gallery">
        <div class="main-img">
          <?php if ($images): ?>
            <img id="main-img" src="<?= h(asset_url($images[0]['filename'])) ?>" alt="<?= h($product['title']) ?>" fetchpriority="high">
          <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <div class="thumbs" id="thumbs">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" data-src="<?= h(asset_url($img['filename'])) ?>" class="<?= $i === 0 ? 'active' : '' ?>" aria-label="Image <?= $i+1 ?>">
                <img src="<?= h(thumb_url($img['filename'])) ?>" alt="" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="product-info">
        <p class="eyebrow">One of a kind</p>
        <h1 class="h-display"><?= h($product['title']) ?></h1>
        <p class="price"><?= fmt_price((int)$product['price_cents']) ?></p>

        <?php if ($product['description']): ?>
          <div class="description"><?= h($product['description']) ?></div>
        <?php endif; ?>

        <p class="ai-disclaimer">Due to photographic embellishments that occur when generating AI scenes, each doll may have very slight differences than depicted in photos.</p>

        <?php if ($product['status'] === 'available'): ?>
          <?php if (paypal_is_configured()): ?>
            <?php $inCart = cart_has((int)$product['id']); ?>
            <div class="buy-card">
              <?php if ($inCart): ?>
                <a class="btn btn-primary" href="/shop/cart.php" style="width:100%;justify-content:center">In your cart — view cart →</a>
                <a class="btn btn-ghost" href="/shop/" style="width:100%;justify-content:center;margin-top:.65rem">Keep shopping</a>
              <?php else: ?>
                <form method="POST" action="/api/cart-add-form.php" style="margin:0">
                  <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                  <input type="hidden" name="return_url" value="/shop/product.php?slug=<?= h(urlencode($product['slug'])) ?>">
                  <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Add to cart</button>
                </form>
                <a class="btn btn-ghost" href="/shop/cart.php" style="width:100%;justify-content:center;margin-top:.65rem">
                  View cart <span aria-hidden="true">→</span>
                </a>
              <?php endif; ?>
              <p class="note">Pay with PayPal or any major credit card at checkout. Each Scrappy Doll is one of a kind — adding her to your cart doesn't reserve her until you check out.</p>
            </div>
          <?php else: ?>
            <div class="buy-card">
              <p style="font-family:var(--font-display);font-weight:500;font-size:1.2rem;margin:0 0 1.25rem;line-height:1.3">To take this doll home, send Kanda a message on Facebook.</p>
              <a class="btn btn-primary" href="https://www.facebook.com/kandakayartist/" rel="noopener" target="_blank" style="width:100%;justify-content:center">
                Message on Facebook <span aria-hidden="true">→</span>
              </a>
              <p class="note">Mention <em>“<?= h($product['title']) ?>”</em> so she knows which one.</p>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="sold-banner">
            <strong>Sold</strong>
            This doll has found her home. <a href="/shop/">See what's still available →</a>
          </div>
          <p><a class="btn btn-ghost" href="https://www.facebook.com/kandakayartist/" rel="noopener">Follow on Facebook for new work</a></p>
        <?php endif; ?>

        <?php
          // Share targets — full URLs / encoded params for each network.
          $shareUrl   = url('shop/product.php?slug=' . urlencode($product['slug']));
          $shareTitle = $product['title'];
          $shareText  = $product['title'] . ' — handmade by Kanda Kay';
          $shareImage = $images ? url('uploads/' . $images[0]['filename']) : url('images/og-image.jpg');

          $eUrl   = rawurlencode($shareUrl);
          $eText  = rawurlencode($shareText);
          $eTitle = rawurlencode($shareTitle);
          $eImg   = rawurlencode($shareImage);

          $emailSubject = rawurlencode('Check out this handmade doll: ' . $shareTitle);
          $emailBody    = rawurlencode($shareText . "\n\n" . $shareUrl);
          $smsBody      = rawurlencode($shareText . ' ' . $shareUrl);
        ?>
        <div class="share-row" aria-label="Share this doll">
          <span class="share-label">Share:</span>
          <a class="share-btn" href="mailto:?subject=<?= $emailSubject ?>&body=<?= $emailBody ?>" aria-label="Share by email">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            <span>Email</span>
          </a>
          <a class="share-btn" href="sms:&body=<?= $smsBody ?>" aria-label="Share by text message">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 21l1.9-5.4A8 8 0 1 1 21 12z"/></svg>
            <span>Text</span>
          </a>
          <a class="share-btn" href="https://www.facebook.com/sharer/sharer.php?u=<?= $eUrl ?>" target="_blank" rel="noopener" aria-label="Share on Facebook">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.6 9.9V15h-2.5v-3h2.5V9.7c0-2.5 1.5-3.9 3.7-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12H17l-.4 3h-2.7v6.9A10 10 0 0 0 22 12z"/></svg>
            <span>Facebook</span>
          </a>
          <a class="share-btn" href="https://twitter.com/intent/tweet?url=<?= $eUrl ?>&text=<?= $eText ?>" target="_blank" rel="noopener" aria-label="Share on X (Twitter)">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M18.244 2H21.5l-7.6 8.687L23 22h-7.012l-5.49-7.182L4.2 22H.94l8.13-9.293L1 2h7.193l4.962 6.56L18.244 2zm-2.46 18h1.81L7.31 4H5.36l10.424 16z"/></svg>
            <span>X</span>
          </a>
          <a class="share-btn" href="https://pinterest.com/pin/create/button/?url=<?= $eUrl ?>&media=<?= $eImg ?>&description=<?= $eText ?>" target="_blank" rel="noopener" aria-label="Pin on Pinterest">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12 0a12 12 0 0 0-4.4 23.2c-.1-1-.2-2.5 0-3.6l1.5-6.4s-.4-.8-.4-1.9c0-1.8 1-3.1 2.4-3.1 1.1 0 1.6.8 1.6 1.8 0 1.1-.7 2.7-1 4.2-.3 1.3.6 2.3 1.9 2.3 2.3 0 4-2.4 4-5.9 0-3.1-2.2-5.2-5.4-5.2a5.6 5.6 0 0 0-5.8 5.6c0 1.1.4 2.3 1 2.9.1.1.1.2.1.4l-.4 1.5c-.1.2-.2.3-.5.2-1.7-.8-2.7-3.2-2.7-5.2 0-4.2 3-8.1 8.8-8.1 4.6 0 8.2 3.3 8.2 7.7 0 4.6-2.9 8.3-6.9 8.3-1.4 0-2.6-.7-3-1.5l-.8 3.1c-.3 1.1-1.1 2.6-1.6 3.4A12 12 0 1 0 12 0z"/></svg>
            <span>Pinterest</span>
          </a>
          <button type="button" class="share-btn share-copy" data-share-url="<?= h($shareUrl) ?>" aria-label="Copy link">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
            <span class="share-copy-label">Copy link</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Product schema -->
<script type="application/ld+json">
<?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Product',
    'name'     => $product['title'],
    'description' => $product['description'] ?: "Handmade one-of-a-kind cloth doll by Kanda Kay.",
    'image'    => $images ? url('uploads/' . $images[0]['filename']) : url('images/og-image.jpg'),
    'brand'    => ['@type' => 'Brand', 'name' => 'Scrappy Dolls'],
    'offers'   => [
        '@type'         => 'Offer',
        'url'           => $pageUrl,
        'priceCurrency' => 'USD',
        'price'         => number_format($product['price_cents']/100, 2, '.', ''),
        'availability'  => $product['status'] === 'available'
            ? 'https://schema.org/InStock'
            : 'https://schema.org/SoldOut',
        'itemCondition' => 'https://schema.org/NewCondition',
        'seller'        => ['@type' => 'Person', 'name' => 'Kanda Kay'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>

<?php if (count($images) > 1): ?>
<script>
(function(){
  var main = document.getElementById('main-img');
  var thumbs = document.querySelectorAll('#thumbs button');
  thumbs.forEach(function(b){
    b.addEventListener('click', function(){
      thumbs.forEach(function(t){ t.classList.remove('active'); });
      b.classList.add('active');
      main.src = b.getAttribute('data-src');
    });
  });
})();
</script>
<?php endif; ?>
<script>
(function(){
  var copyBtn = document.querySelector('.share-copy');
  if (!copyBtn) return;
  var label = copyBtn.querySelector('.share-copy-label');
  copyBtn.addEventListener('click', function(){
    var url = copyBtn.getAttribute('data-share-url') || window.location.href;
    var done = function(){
      copyBtn.classList.add('is-copied');
      if (label) label.textContent = 'Copied!';
      setTimeout(function(){
        copyBtn.classList.remove('is-copied');
        if (label) label.textContent = 'Copy link';
      }, 1800);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done).catch(function(){
        // Fallback: select and copy via a temp textarea
        var t = document.createElement('textarea');
        t.value = url; t.style.position='fixed'; t.style.opacity='0';
        document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); done(); } catch (e) {}
        document.body.removeChild(t);
      });
    }
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
