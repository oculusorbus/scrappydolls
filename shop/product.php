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
                <img src="<?= h(asset_url($img['filename'])) ?>" alt="" loading="lazy">
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

        <?php if ($product['status'] === 'available'): ?>
          <?php if (paypal_is_configured()): ?>
            <div class="buy-card">
              <div id="paypal-button-container"></div>
              <div id="buy-error" class="flash flash-error" style="display:none;margin-top:1rem"></div>
              <p class="note">Pay with PayPal or any major credit card. Your payment is processed securely by PayPal.</p>
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

<?php if ($product['status'] === 'available' && paypal_is_configured()): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= h(urlencode(paypal_client_id())) ?>&currency=<?= h(paypal_currency()) ?>&intent=capture&components=buttons"
        data-namespace="paypalSDK"></script>
<script>
(function(){
  var productId = <?= (int)$product['id'] ?>;
  var slug = <?= json_encode($product['slug']) ?>;
  var errBox = document.getElementById('buy-error');
  function showErr(msg){
    errBox.textContent = msg || 'Something went wrong. Please try again.';
    errBox.style.display = 'block';
  }
  if (!window.paypalSDK || !window.paypalSDK.Buttons) {
    showErr('PayPal failed to load. Refresh the page or try again later.');
    return;
  }
  window.paypalSDK.Buttons({
    style: { layout: 'vertical', shape: 'pill', label: 'paypal' },
    createOrder: function(){
      return fetch('/api/create-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
      })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.error) throw new Error(data.error);
        return data.id;
      });
    },
    onApprove: function(data){
      return fetch('/api/capture-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: data.orderID, product_id: productId })
      })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res.error) throw new Error(res.error);
        window.location.href = '/shop/success.php?order=' + encodeURIComponent(res.order_id);
      });
    },
    onError: function(err){
      console.error(err);
      showErr('Payment could not be completed. ' + (err && err.message ? err.message : ''));
    },
    onCancel: function(){
      // user cancelled — no-op
    }
  }).render('#paypal-button-container').catch(function(err){
    showErr('Could not load PayPal buttons.');
    console.error(err);
  });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
