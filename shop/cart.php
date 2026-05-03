<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$pageTitle = 'Your cart';
$pageDesc  = 'Review the dolls in your cart and check out securely.';

$items = cart_items();
$itemsTotal = 0;
foreach ($items as $it) $itemsTotal += (int)$it['price_cents'];
$shippingCents = shipping_cents_for_count(count($items));
$grandTotal    = $itemsTotal + $shippingCents;

$thumbsByProduct = [];
if ($items) {
    $ids = array_map(fn($i) => (int)$i['id'], $items);
    $place = implode(',', array_fill(0, count($ids), '?'));
    $thumbStmt = db()->prepare("
        SELECT product_id, filename
        FROM product_images
        WHERE product_id IN ($place)
        ORDER BY product_id ASC, sort_order ASC, id ASC
    ");
    $thumbStmt->execute($ids);
    foreach ($thumbStmt->fetchAll() as $row) {
        $pid = (int)$row['product_id'];
        if (!isset($thumbsByProduct[$pid])) $thumbsByProduct[$pid] = $row['filename'];
    }
}

$suggestions = $items ? cart_suggestions(4) : [];
$suggestionThumbs = [];
if ($suggestions) {
    $sids = array_map(fn($i) => (int)$i['id'], $suggestions);
    $place = implode(',', array_fill(0, count($sids), '?'));
    $st = db()->prepare("
        SELECT product_id, filename
        FROM product_images
        WHERE product_id IN ($place)
        ORDER BY product_id ASC, sort_order ASC, id ASC
    ");
    $st->execute($sids);
    foreach ($st->fetchAll() as $row) {
        $pid = (int)$row['product_id'];
        if (!isset($suggestionThumbs[$pid])) $suggestionThumbs[$pid] = $row['filename'];
    }
}

track_view('/shop/cart.php');

require __DIR__ . '/header.php';
?>

<section>
  <div class="wrap">
    <div class="shop-head">
      <p class="eyebrow">Your cart</p>
      <h1 class="h-display"><?= $items ? 'Almost there.' : 'Your cart is empty.' ?></h1>
    </div>

    <?php if (!$items): ?>
      <div class="empty-shop">
        <p>Nothing here yet — <a href="/shop/">browse the shop</a> to find a doll to take home.</p>
      </div>
    <?php else: ?>

      <div class="cart-layout">
        <div class="cart-list">
          <?php foreach ($items as $it):
            $thumb = $thumbsByProduct[(int)$it['id']] ?? null;
          ?>
            <div class="cart-row" data-product-id="<?= (int)$it['id'] ?>">
              <a class="cart-row-img" href="/shop/product.php?slug=<?= h(urlencode($it['slug'])) ?>">
                <?php if ($thumb): ?>
                  <img src="<?= h(thumb_url($thumb)) ?>" alt="<?= h($it['title']) ?>">
                <?php endif; ?>
              </a>
              <div class="cart-row-meta">
                <a class="cart-row-title" href="/shop/product.php?slug=<?= h(urlencode($it['slug'])) ?>"><?= h($it['title']) ?></a>
                <p class="cart-row-tag">One of a kind</p>
              </div>
              <div class="cart-row-side">
                <span class="cart-row-price"><?= fmt_price((int)$it['price_cents']) ?></span>
                <button type="button" class="cart-row-remove" data-cart-remove="<?= (int)$it['id'] ?>" aria-label="Remove <?= h($it['title']) ?>">Remove</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <aside class="cart-summary">
          <h2 class="cart-summary-title">Order summary</h2>
          <dl class="cart-summary-rows">
            <div><dt>Items</dt><dd><?= count($items) ?></dd></div>
            <div><dt>Subtotal</dt><dd data-cart-subtotal><?= fmt_price($itemsTotal) ?></dd></div>
            <div><dt>Shipping</dt><dd data-cart-shipping><?= fmt_price($shippingCents) ?></dd></div>
            <div class="cart-summary-grand"><dt>Total</dt><dd data-cart-total><?= fmt_price($grandTotal) ?></dd></div>
          </dl>
          <p class="cart-shipping-note">Flat-rate shipping: $7.99 first doll, $2.99 each additional.</p>

          <?php if (paypal_is_configured()): ?>
            <div id="paypal-button-container"></div>
            <div id="cart-error" class="flash flash-error" style="display:none;margin-top:1rem"></div>
            <p class="note">Pay with PayPal or any major credit card. Your payment is processed securely by PayPal.</p>
          <?php else: ?>
            <p class="note">Online checkout isn't configured yet. Message Kanda on Facebook to buy these dolls.</p>
            <a class="btn btn-primary" href="https://www.facebook.com/kandakayartist/" rel="noopener" target="_blank" style="width:100%;justify-content:center">Message on Facebook →</a>
          <?php endif; ?>
        </aside>
      </div>

      <?php if ($suggestions): ?>
        <section class="cart-suggestions" aria-label="You might also like">
          <h2 class="h-display cart-suggestions-title">Add a friend?</h2>
          <p class="lede" style="margin:.5rem auto 1.5rem">Each doll ships with the same care no matter how many you bring home.</p>
          <div class="cart-suggestion-grid">
            <?php foreach ($suggestions as $s):
              $sthumb = $suggestionThumbs[(int)$s['id']] ?? null;
            ?>
              <div class="cart-suggestion">
                <a class="cart-suggestion-img" href="/shop/product.php?slug=<?= h(urlencode($s['slug'])) ?>">
                  <?php if ($sthumb): ?>
                    <img src="<?= h(thumb_url($sthumb)) ?>" alt="<?= h($s['title']) ?>" loading="lazy">
                  <?php endif; ?>
                </a>
                <div class="cart-suggestion-meta">
                  <a class="cart-suggestion-title" href="/shop/product.php?slug=<?= h(urlencode($s['slug'])) ?>"><?= h($s['title']) ?></a>
                  <span class="cart-suggestion-price"><?= fmt_price((int)$s['price_cents']) ?></span>
                </div>
                <button type="button" class="btn btn-ghost cart-add-btn" data-product-id="<?= (int)$s['id'] ?>" style="width:100%;justify-content:center">
                  <span class="cart-add-label">+ Add to cart</span>
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</section>

<?php if ($items && paypal_is_configured()): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= h(urlencode(paypal_client_id())) ?>&currency=<?= h(paypal_currency()) ?>&intent=authorize&components=buttons"
        data-namespace="paypalSDK"></script>
<script>
(function(){
  var errBox = document.getElementById('cart-error');
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
      errBox.style.display = 'none';
      return fetch('/api/create-cart-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
      })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.error) throw new Error(data.error);
        return data.id;
      });
    },
    onApprove: function(data){
      return fetch('/api/capture-cart-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: data.orderID })
      })
      .then(function(r){ return r.json().then(function(j){ return { status: r.status, body: j }; }); })
      .then(function(res){
        if (res.body.error) {
          // 409 = race; reload so cart updates with sold items removed.
          if (res.status === 409) {
            showErr(res.body.error + ' Redirecting back to your cart…');
            setTimeout(function(){ window.location.reload(); }, 1800);
            return;
          }
          throw new Error(res.body.error);
        }
        window.location.href = '/shop/success.php?order=' + encodeURIComponent(res.body.order_id);
      });
    },
    onError: function(err){
      console.error(err);
      showErr('Payment could not be completed. ' + (err && err.message ? err.message : ''));
    },
    onCancel: function(){ /* no-op */ }
  }).render('#paypal-button-container').catch(function(err){
    showErr('Could not load PayPal buttons.');
    console.error(err);
  });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
