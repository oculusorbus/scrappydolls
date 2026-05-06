<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$pageTitle = 'Confirm shipping';
$pageDesc  = 'Confirm your contact and shipping details before we charge your card.';

$orderId = trim((string)($_GET['order'] ?? ''));
if ($orderId === '') {
    header('Location: /shop/cart.php');
    exit;
}

// Sanity-check: the order must have been created in this session.
// PayPal order IDs are short-lived, but defense-in-depth in case one is shared.
$intentStmt = db()->prepare(
    'SELECT session_hash FROM order_intents WHERE paypal_order_id = :p ORDER BY id DESC LIMIT 1'
);
$intentStmt->execute([':p' => $orderId]);
$intent = $intentStmt->fetch();
if (!$intent || $intent['session_hash'] !== tracking_session_hash()) {
    header('Location: /shop/cart.php');
    exit;
}

// If the buyer's cart was somehow emptied, send them back.
$items = cart_items();
if (!$items) {
    header('Location: /shop/cart.php');
    exit;
}

// Prefill from PayPal. Payer/shipping calls can fail if the order expired
// — that's not fatal; we just render an empty form.
$prefillName  = '';
$prefillEmail = '';
$prefillPhone = '';
$prefillAddr  = [
    'address_line_1' => '',
    'address_line_2' => '',
    'admin_area_2'   => '',
    'admin_area_1'   => '',
    'postal_code'    => '',
    'country_code'   => 'US',
];
try {
    $orderData = paypal_get_order($orderId);
    $payer    = paypal_extract_payer($orderData);
    $shipping = paypal_extract_shipping($orderData);

    $prefillName  = (string)($payer['name'] ?? '');
    $prefillEmail = (string)($payer['email'] ?? '');

    $rawPayer = $orderData['payer'] ?? [];
    $phoneNum = $rawPayer['phone']['phone_number']['national_number']
        ?? $rawPayer['phone_number']['national_number']
        ?? '';
    $prefillPhone = (string)$phoneNum;

    if ($shipping) {
        if (empty($prefillName) && !empty($shipping['name'])) {
            $prefillName = (string)$shipping['name'];
        }
        $a = $shipping['address'] ?? [];
        foreach ($prefillAddr as $k => $_) {
            if (!empty($a[$k])) $prefillAddr[$k] = (string)$a[$k];
        }
        if (empty($prefillAddr['country_code'])) $prefillAddr['country_code'] = 'US';
    }
} catch (Throwable $e) {
    error_log('confirm.php prefill error: ' . $e->getMessage());
}

$itemsTotal = 0;
foreach ($items as $it) $itemsTotal += (int)$it['price_cents'];
$shippingCents = shipping_cents_for_count(count($items));
$grandTotal    = $itemsTotal + $shippingCents;

require __DIR__ . '/header.php';
?>

<style>
.confirm-wrap { max-width: 44rem; margin: 0 auto; padding: 2rem 0 4rem; }
.confirm-head { margin-bottom: 1.5rem; }
.confirm-head .eyebrow { color: var(--rose); }
.confirm-summary {
  background: var(--paper-soft, #fbf8f5);
  border: 1px solid var(--rule, #ead7d2);
  border-radius: 14px;
  padding: 1rem 1.25rem;
  margin-bottom: 1.5rem;
}
.confirm-summary dl { margin: 0; display: grid; grid-template-columns: 1fr auto; row-gap: 0.35rem; column-gap: 1rem; }
.confirm-summary dt { font-weight: 500; }
.confirm-summary dd { margin: 0; text-align: right; }
.confirm-summary .grand { font-weight: 700; padding-top: 0.5rem; border-top: 1px dashed var(--rule, #ead7d2); margin-top: 0.5rem; }
.confirm-summary .grand dt, .confirm-summary .grand dd { padding-top: 0.5rem; }
.confirm-section {
  border: 1px solid var(--rule, #ead7d2);
  border-radius: 14px;
  padding: 1.25rem 1.25rem 1.5rem;
  margin: 0 0 1.25rem;
}
.confirm-section legend {
  padding: 0 0.5rem;
  font-weight: 600;
  font-size: 0.95rem;
  color: var(--ink, #2c1f1c);
}
.confirm-help { margin: 0.25rem 0 0.75rem; color: var(--ink-soft, #6b5852); font-size: 0.92rem; }
.confirm-row { display: grid; gap: 0.85rem; margin-top: 0.85rem; grid-template-columns: 1fr; }
.confirm-row.two { grid-template-columns: 1fr 1fr; }
@media (min-width: 640px) {
  .confirm-row.split { grid-template-columns: 1fr 1fr; }
}
.confirm-row label {
  display: flex; flex-direction: column; gap: 0.3rem;
  font-size: 0.88rem; color: var(--ink-soft, #6b5852);
}
.confirm-row input {
  font: inherit;
  padding: 0.7rem 0.85rem;
  border: 1px solid var(--rule, #ead7d2);
  border-radius: 8px;
  background: #fff;
  color: var(--ink, #2c1f1c);
}
.confirm-row input:focus {
  outline: none;
  border-color: var(--rose, #b13e54);
  box-shadow: 0 0 0 3px rgba(177,62,84,0.15);
}
.confirm-gift-toggle {
  display: flex; align-items: flex-start; gap: 0.6rem;
  padding: 0.85rem 0;
  cursor: pointer; user-select: none;
  font-size: 0.95rem;
}
.confirm-gift-toggle input { margin-top: 0.25rem; }
.confirm-ship-block { padding-top: 0.5rem; }
.confirm-submit { width: 100%; margin-top: 0.75rem; justify-content: center; }
.confirm-submit[disabled] { opacity: 0.7; cursor: not-allowed; }
.confirm-back { display: inline-block; margin-top: 1rem; color: var(--ink-soft, #6b5852); }
.flash.flash-error { display: none; padding: 0.85rem 1rem; border-radius: 8px; background: #fde8eb; color: #7a1d2c; margin: 1rem 0; }
</style>

<section>
  <div class="wrap">
    <div class="confirm-wrap">
      <div class="confirm-head">
        <p class="eyebrow">One last step</p>
        <h1 class="h-display">Confirm <em style="color: var(--rose); font-style: italic; font-weight: 400;">shipping</em>.</h1>
        <p class="confirm-help">Your card hasn't been charged yet. Tell us where to send the dolls and we'll finish the order.</p>
      </div>

      <div class="confirm-summary">
        <dl>
          <?php foreach ($items as $it): ?>
            <div style="display:contents">
              <dt><?= h($it['title']) ?></dt>
              <dd><?= fmt_price((int)$it['price_cents']) ?></dd>
            </div>
          <?php endforeach; ?>
          <div style="display:contents">
            <dt>Shipping</dt>
            <dd><?= fmt_price($shippingCents) ?></dd>
          </div>
          <div class="grand" style="display:contents">
            <dt>Total</dt>
            <dd><?= fmt_price($grandTotal) ?></dd>
          </div>
        </dl>
      </div>

      <form id="confirm-form" novalidate>
        <input type="hidden" name="order_id" value="<?= h($orderId) ?>">

        <fieldset class="confirm-section">
          <legend>Your contact</legend>
          <p class="confirm-help">We'll email tracking here and call only if there's a question about your order.</p>

          <div class="confirm-row">
            <label>Your name
              <input type="text" name="name" required maxlength="255"
                     value="<?= h($prefillName) ?>" autocomplete="name">
            </label>
          </div>
          <div class="confirm-row split">
            <label>Email
              <input type="email" name="email" required maxlength="255"
                     value="<?= h($prefillEmail) ?>" autocomplete="email">
            </label>
            <label>Phone
              <input type="tel" name="phone" required maxlength="40"
                     value="<?= h($prefillPhone) ?>" autocomplete="tel"
                     placeholder="(555) 123-4567">
            </label>
          </div>
        </fieldset>

        <fieldset class="confirm-section">
          <legend>Shipping</legend>

          <label class="confirm-gift-toggle">
            <input type="checkbox" id="is-gift" name="is_gift" value="1">
            <span>This is a gift — ship it to a different recipient</span>
          </label>

          <div id="ship-self" class="confirm-ship-block">
            <p class="confirm-help">We'll send your dolls here. Edit if you'd rather ship somewhere else you live.</p>
            <div class="confirm-row">
              <label>Street address
                <input type="text" name="self[address_line_1]" data-shipreq required maxlength="255"
                       value="<?= h($prefillAddr['address_line_1']) ?>" autocomplete="address-line1">
              </label>
            </div>
            <div class="confirm-row">
              <label>Apt / Suite / Unit <span style="opacity:.6">(optional)</span>
                <input type="text" name="self[address_line_2]" maxlength="255"
                       value="<?= h($prefillAddr['address_line_2']) ?>" autocomplete="address-line2">
              </label>
            </div>
            <div class="confirm-row split">
              <label>City
                <input type="text" name="self[admin_area_2]" data-shipreq required maxlength="120"
                       value="<?= h($prefillAddr['admin_area_2']) ?>" autocomplete="address-level2">
              </label>
              <label>State / Region
                <input type="text" name="self[admin_area_1]" data-shipreq required maxlength="120"
                       value="<?= h($prefillAddr['admin_area_1']) ?>" autocomplete="address-level1">
              </label>
            </div>
            <div class="confirm-row split">
              <label>Postal code
                <input type="text" name="self[postal_code]" data-shipreq required maxlength="32"
                       value="<?= h($prefillAddr['postal_code']) ?>" autocomplete="postal-code">
              </label>
              <label>Country
                <input type="text" name="self[country_code]" data-shipreq required maxlength="2"
                       value="<?= h($prefillAddr['country_code'] ?: 'US') ?>"
                       autocomplete="country" pattern="[A-Za-z]{2}" style="text-transform:uppercase">
              </label>
            </div>
          </div>

          <div id="ship-gift" class="confirm-ship-block" hidden>
            <p class="confirm-help">Where should we send it? We'll address the package to your recipient.</p>
            <div class="confirm-row">
              <label>Recipient name
                <input type="text" name="gift_recipient_name" data-giftreq maxlength="255"
                       autocomplete="off">
              </label>
            </div>
            <div class="confirm-row">
              <label>Street address
                <input type="text" name="gift[address_line_1]" data-giftreq maxlength="255"
                       autocomplete="off">
              </label>
            </div>
            <div class="confirm-row">
              <label>Apt / Suite / Unit <span style="opacity:.6">(optional)</span>
                <input type="text" name="gift[address_line_2]" maxlength="255" autocomplete="off">
              </label>
            </div>
            <div class="confirm-row split">
              <label>City
                <input type="text" name="gift[admin_area_2]" data-giftreq maxlength="120" autocomplete="off">
              </label>
              <label>State / Region
                <input type="text" name="gift[admin_area_1]" data-giftreq maxlength="120" autocomplete="off">
              </label>
            </div>
            <div class="confirm-row split">
              <label>Postal code
                <input type="text" name="gift[postal_code]" data-giftreq maxlength="32" autocomplete="off">
              </label>
              <label>Country
                <input type="text" name="gift[country_code]" data-giftreq maxlength="2"
                       value="US" pattern="[A-Za-z]{2}" style="text-transform:uppercase" autocomplete="off">
              </label>
            </div>
          </div>
        </fieldset>

        <div id="confirm-error" class="flash flash-error"></div>

        <button type="submit" class="btn btn-primary confirm-submit">Confirm and pay <?= fmt_price($grandTotal) ?></button>
        <a class="confirm-back" href="/shop/cart.php">← Back to cart</a>
      </form>
    </div>
  </div>
</section>

<script>
(function(){
  var form = document.getElementById('confirm-form');
  var giftCheckbox = document.getElementById('is-gift');
  var shipSelf = document.getElementById('ship-self');
  var shipGift = document.getElementById('ship-gift');
  var errBox = document.getElementById('confirm-error');
  var submitBtn = form.querySelector('.confirm-submit');

  function setVisibility(isGift) {
    shipSelf.hidden = isGift;
    shipGift.hidden = !isGift;
    // Toggle "required" on the inactive block — hidden inputs that are still
    // required will block form submission with no visible error.
    shipSelf.querySelectorAll('[data-shipreq]').forEach(function(i){ i.required = !isGift; });
    shipGift.querySelectorAll('[data-giftreq]').forEach(function(i){ i.required = isGift; });
  }
  setVisibility(false);
  giftCheckbox.addEventListener('change', function(){ setVisibility(giftCheckbox.checked); });

  function showErr(msg) {
    errBox.textContent = msg || 'Something went wrong. Please review the fields and try again.';
    errBox.style.display = 'block';
    errBox.scrollIntoView({behavior: 'smooth', block: 'center'});
  }

  function val(name) {
    var el = form.elements.namedItem(name);
    return el ? String(el.value || '').trim() : '';
  }
  function addrFromBlock(prefix) {
    return {
      address_line_1: val(prefix + '[address_line_1]'),
      address_line_2: val(prefix + '[address_line_2]'),
      admin_area_2:   val(prefix + '[admin_area_2]'),
      admin_area_1:   val(prefix + '[admin_area_1]'),
      postal_code:    val(prefix + '[postal_code]'),
      country_code:   val(prefix + '[country_code]').toUpperCase(),
    };
  }

  form.addEventListener('submit', function(ev) {
    ev.preventDefault();
    if (!form.reportValidity()) return;
    errBox.style.display = 'none';

    var isGift = giftCheckbox.checked;
    var payload = {
      order_id: val('order_id'),
      name:     val('name'),
      email:    val('email'),
      phone:    val('phone'),
      is_gift:  isGift,
      gift_recipient_name: isGift ? val('gift_recipient_name') : '',
      address:  isGift ? addrFromBlock('gift') : addrFromBlock('self'),
    };

    submitBtn.disabled = true;
    var prevLabel = submitBtn.textContent;
    submitBtn.textContent = 'Processing…';

    fetch('/api/capture-cart-order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
    .then(function(r){ return r.json().then(function(j){ return { status: r.status, body: j }; }); })
    .then(function(res){
      if (res.body.error) {
        if (res.status === 409) {
          // Race: a doll sold while we were on this screen. Bounce to cart.
          showErr(res.body.error + ' Returning to your cart…');
          setTimeout(function(){ window.location.href = '/shop/cart.php'; }, 1800);
          return;
        }
        throw new Error(res.body.error);
      }
      window.location.href = '/shop/success.php?order=' + encodeURIComponent(res.body.order_id);
    })
    .catch(function(err){
      submitBtn.disabled = false;
      submitBtn.textContent = prevLabel;
      showErr(err && err.message ? err.message : '');
    });
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
