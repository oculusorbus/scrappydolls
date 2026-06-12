<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$pageTitle = 'Your cart';
$pageDesc  = 'Review the dolls in your cart and check out securely.';

$items = cart_items();
$itemsTotal = 0;
foreach ($items as $it) $itemsTotal += (int)$it['price_cents'];
$coupon          = cart_coupon();
$discountCents   = $coupon ? coupon_discount_cents($coupon, $itemsTotal) : 0;
$couponFreeShip  = $coupon && coupon_waives_shipping($coupon);
$shippingCents   = $couponFreeShip ? 0 : shipping_cents(count($items), $itemsTotal);
$grandTotal      = max(0, $itemsTotal - $discountCents) + $shippingCents;
$freeShippingRemaining = max(0, SHIPPING_FREE_THRESHOLD_CENTS - $itemsTotal);
$freeShippingUnlocked  = $itemsTotal >= SHIPPING_FREE_THRESHOLD_CENTS;

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

$suggestions = $items ? cart_stable_suggestions(3) : [];

track_view('/shop/cart.php');

require __DIR__ . '/header.php';
?>

<section class="cart-section">
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
        <div class="cart-items-col">
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
          <div class="cart-list-actions">
            <button type="button" class="cart-clear" data-cart-clear>Remove all items</button>
          </div>
        </div>

        <aside class="cart-summary">
          <h2 class="cart-summary-title">Order summary</h2>
          <dl class="cart-summary-rows">
            <div><dt>Items</dt><dd data-cart-itemcount><?= count($items) ?></dd></div>
            <div><dt>Subtotal</dt><dd data-cart-subtotal><?= fmt_price($itemsTotal) ?></dd></div>
            <?php if ($coupon): ?>
              <div data-cart-discount-row<?= $discountCents > 0 ? '' : ' style="display:none"' ?>>
                <dt>Discount (<?= h($coupon['code']) ?>)</dt>
                <dd data-cart-discount>−<?= fmt_price($discountCents) ?></dd>
              </div>
            <?php endif; ?>
            <div><dt>Shipping</dt><dd data-cart-shipping><?= fmt_price($shippingCents) ?></dd></div>
            <div class="cart-summary-grand"><dt>Total</dt><dd data-cart-total><?= fmt_price($grandTotal) ?></dd></div>
          </dl>
          <?php if ($couponFreeShip): ?>
            <p class="cart-shipping-note cart-shipping-unlocked">
              <strong>Free shipping applied with your code!</strong>
            </p>
          <?php elseif ($freeShippingUnlocked): ?>
            <p class="cart-shipping-note cart-shipping-unlocked">
              <strong>You unlocked free shipping!</strong>
            </p>
          <?php else: ?>
            <p class="cart-shipping-note">
              Add <strong><?= fmt_price($freeShippingRemaining) ?> more</strong> for free shipping.<br>
              <span class="cart-shipping-rate">Flat-rate: $7.99 first doll, $2.99 each additional under $50.</span>
            </p>
          <?php endif; ?>

          <div class="cart-coupon">
            <?php if ($coupon): ?>
              <p class="cart-coupon-applied">
                Code <strong><?= h($coupon['code']) ?></strong> applied — <?= h(coupon_summary($coupon)) ?>.
                <button type="button" class="cart-coupon-remove" data-coupon-remove>Remove</button>
              </p>
            <?php else: ?>
              <form data-coupon-form>
                <label class="cart-coupon-label" for="coupon-code">Have a coupon code?</label>
                <div class="cart-coupon-row">
                  <input type="text" id="coupon-code" name="code" maxlength="40"
                         autocomplete="off" autocapitalize="characters" spellcheck="false"
                         placeholder="Enter code">
                  <button type="submit" class="btn btn-ghost">Apply</button>
                </div>
              </form>
            <?php endif; ?>
            <p class="cart-coupon-error" data-coupon-error hidden></p>
          </div>

          <?php if (paypal_is_configured()): ?>
            <a class="btn btn-primary" href="/shop/confirm.php" style="width:100%;justify-content:center">Proceed to checkout →</a>
            <p class="note">Next: confirm where it ships, then pay with PayPal or any major credit card — processed securely by PayPal.</p>
            <p class="note" style="margin-top:.35rem">Texas sales tax (if shipping to TX) is calculated at checkout.</p>
          <?php else: ?>
            <p class="note">Online checkout isn't configured yet. Message Kanda on Facebook to buy these dolls.</p>
            <a class="btn btn-primary" href="https://www.facebook.com/kandakayartist/" rel="noopener" target="_blank" style="width:100%;justify-content:center">Message on Facebook →</a>
          <?php endif; ?>
        </aside>

      <?php if ($suggestions): ?>
        <section class="cart-suggestions" aria-label="You might also like">
          <h2 class="h-display cart-suggestions-title">Add a friend?</h2>
          <p class="lede" style="margin:.5rem auto 1.5rem">Each doll ships with the same care no matter how many you bring home.</p>
          <div class="cart-suggestion-grid">
            <?php foreach ($suggestions as $s): ?>
              <div class="cart-suggestion">
                <a class="cart-suggestion-img" href="<?= h($s['product_url']) ?>">
                  <?php if ($s['thumb_url']): ?>
                    <img src="<?= h($s['thumb_url']) ?>" alt="<?= h($s['title']) ?>" loading="lazy">
                  <?php endif; ?>
                </a>
                <div class="cart-suggestion-meta">
                  <a class="cart-suggestion-title" href="<?= h($s['product_url']) ?>"><?= h($s['title']) ?></a>
                  <span class="cart-suggestion-price"><?= h($s['price']) ?></span>
                </div>
                <form method="POST" action="/api/cart-add-form.php" class="cart-add-form">
                  <input type="hidden" name="product_id" value="<?= (int)$s['id'] ?>">
                  <input type="hidden" name="return_url" value="/shop/cart.php">
                  <button type="submit" class="btn btn-ghost" style="width:100%;justify-content:center">+ Add to cart</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="cart-suggestions-actions">
            <form method="POST" action="/api/cart-suggestions-refresh.php" style="margin:0">
              <button type="submit" class="cart-suggestions-refresh">
                <span class="refresh-icon" aria-hidden="true">↻</span> Don't see a friend? Refresh lineup
              </button>
            </form>
            <a class="btn btn-ghost" href="/shop/">Keep browsing all dolls <span aria-hidden="true">→</span></a>
          </div>
        </section>
      <?php endif; ?>
      </div><!-- /.cart-layout -->

    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
