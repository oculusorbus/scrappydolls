<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$pageTitle = 'Checkout';
$pageDesc  = 'Confirm your contact and shipping details, then pay securely with PayPal.';

// Need a cart and a working PayPal config to check out.
$items = cart_items();
if (!$items) {
    header('Location: /shop/cart.php');
    exit;
}
if (!paypal_is_configured()) {
    header('Location: /shop/cart.php');
    exit;
}

// Pre-fill from a completed "Log in with PayPal" round-trip, if any.
$profile  = $_SESSION['paypal_login_profile'] ?? null;
$loggedIn = is_array($profile);

$prefillName  = $loggedIn ? (string)($profile['name']  ?? '') : '';
$prefillEmail = $loggedIn ? (string)($profile['email'] ?? '') : '';
$prefillPhone = $loggedIn ? (string)($profile['phone'] ?? '') : '';
$prefillAddr  = [
    'address_line_1' => '',
    'address_line_2' => '',
    'admin_area_2'   => '',
    'admin_area_1'   => '',
    'postal_code'    => '',
    'country_code'   => 'US',
];
if ($loggedIn && is_array($profile['address'] ?? null)) {
    foreach ($prefillAddr as $k => $_) {
        if (!empty($profile['address'][$k])) $prefillAddr[$k] = (string)$profile['address'][$k];
    }
    if (empty($prefillAddr['country_code'])) $prefillAddr['country_code'] = 'US';
}

// Totals from the current cart/coupon. Tax depends on the ship-to and is
// previewed live in JS; the authoritative tax is recomputed server-side in
// create-cart-order.php from the same rule.
$itemsTotal     = 0;
foreach ($items as $it) $itemsTotal += (int)$it['price_cents'];
$coupon         = cart_coupon();
$discountCents  = $coupon ? coupon_discount_cents($coupon, $itemsTotal) : 0;
$couponFreeShip = $coupon && coupon_waives_shipping($coupon);
$shippingCents  = $couponFreeShip ? 0 : shipping_cents(count($items), $itemsTotal);

$taxBase      = max(0, $itemsTotal - $discountCents) + $shippingCents;
$taxRate      = tax_rate_tx();
$initialTax   = order_tax_cents($taxBase, $prefillAddr['admin_area_1'], $prefillAddr['country_code']);
$initialTotal = $itemsTotal - $discountCents + $shippingCents + $initialTax;

track_view('/shop/confirm.php');

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
.confirm-login { margin: 0 0 1.5rem; }
.confirm-login .btn-paypal-login {
  display: inline-flex; align-items: center; gap: 0.5rem;
  width: 100%; justify-content: center;
  background: #ffc439; color: #0c0c0c; border: 1px solid #e3a900;
  border-radius: 999px; padding: 0.7rem 1rem; font: inherit; font-weight: 600;
  text-decoration: none;
}
.confirm-login .btn-paypal-login:hover { background: #f0b72c; }
.confirm-login-note { margin: 0.5rem 0 0; font-size: 0.9rem; color: var(--ink-soft, #6b5852); }
.confirm-login-or { text-align: center; color: var(--ink-soft, #6b5852); font-size: 0.88rem; margin: 0.9rem 0 0; }
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
.confirm-pay { margin-top: 0.75rem; }
.confirm-pay-note { margin: 0.5rem 0 0; font-size: 0.88rem; color: var(--ink-soft, #6b5852); text-align: center; }
.confirm-back { display: inline-block; margin-top: 1rem; color: var(--ink-soft, #6b5852); }
.flash.flash-error { display: none; padding: 0.85rem 1rem; border-radius: 8px; background: #fde8eb; color: #7a1d2c; margin: 1rem 0; }
</style>

<section>
  <div class="wrap">
    <div class="confirm-wrap">
      <div class="confirm-head">
        <p class="eyebrow">One last step</p>
        <h1 class="h-display">Confirm <em style="color: var(--rose); font-style: italic; font-weight: 400;">shipping</em>.</h1>
        <p class="confirm-help">Your card hasn't been charged yet. Tell us where to send the dolls, then pay securely with PayPal.</p>
      </div>

      <div class="confirm-summary">
        <dl>
          <?php foreach ($items as $it): ?>
            <div style="display:contents">
              <dt><?= h($it['title']) ?></dt>
              <dd><?= fmt_price((int)$it['price_cents']) ?></dd>
            </div>
          <?php endforeach; ?>
          <?php if ($coupon && $discountCents > 0): ?>
            <div style="display:contents">
              <dt>Discount (<?= h($coupon['code']) ?>)</dt>
              <dd>−<?= fmt_price($discountCents) ?></dd>
            </div>
          <?php endif; ?>
          <div style="display:contents">
            <dt>Shipping<?= $couponFreeShip ? ' (waived with code ' . h($coupon['code']) . ')' : '' ?></dt>
            <dd><?= fmt_price($shippingCents) ?></dd>
          </div>
          <div id="sum-tax-row" style="display:<?= $initialTax > 0 ? 'contents' : 'none' ?>">
            <dt>Sales tax (TX)</dt>
            <dd id="sum-tax"><?= fmt_price($initialTax) ?></dd>
          </div>
          <div class="grand" style="display:contents">
            <dt>Total</dt>
            <dd id="sum-total"><?= fmt_price($initialTotal) ?></dd>
          </div>
        </dl>
      </div>

      <div class="confirm-login">
        <a class="btn-paypal-login" href="/api/paypal-login-start.php">
          <strong>Log in with PayPal</strong> to autofill
        </a>
        <?php if ($loggedIn): ?>
          <p class="confirm-login-note">✓ Filled in from your PayPal account — edit anything below before you pay.</p>
        <?php else: ?>
          <p class="confirm-login-or">— or just enter your details below —</p>
        <?php endif; ?>
      </div>

      <form id="confirm-form" novalidate>
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
                <input type="text" name="self[admin_area_1]" data-shipreq data-taxstate required maxlength="120"
                       value="<?= h($prefillAddr['admin_area_1']) ?>" autocomplete="address-level1">
              </label>
            </div>
            <div class="confirm-row split">
              <label>Postal code
                <input type="text" name="self[postal_code]" data-shipreq required maxlength="32"
                       value="<?= h($prefillAddr['postal_code']) ?>" autocomplete="postal-code">
              </label>
              <label>Country
                <input type="text" name="self[country_code]" data-shipreq data-taxcountry required maxlength="2"
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
              <label>Gift note <span style="opacity:.6">(optional — we'll include it with the package)</span>
                <textarea name="gift_message" maxlength="500" rows="3"
                          placeholder="A short note for your recipient — left blank, no card is included."
                          style="font:inherit;padding:.7rem .85rem;border:1px solid var(--rule,#ead7d2);border-radius:8px;background:#fff;color:var(--ink,#2c1f1c);resize:vertical;min-height:4.5rem"
                          autocomplete="off"></textarea>
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
                <input type="text" name="gift[admin_area_1]" data-giftreq data-taxstate maxlength="120" autocomplete="off">
              </label>
            </div>
            <div class="confirm-row split">
              <label>Postal code
                <input type="text" name="gift[postal_code]" data-giftreq maxlength="32" autocomplete="off">
              </label>
              <label>Country
                <input type="text" name="gift[country_code]" data-giftreq data-taxcountry maxlength="2"
                       value="US" pattern="[A-Za-z]{2}" style="text-transform:uppercase" autocomplete="off">
              </label>
            </div>
          </div>
        </fieldset>

        <div id="confirm-error" class="flash flash-error"></div>

        <div id="paypal-button-container" class="confirm-pay"></div>
        <p class="confirm-pay-note">Pay with PayPal or any major credit card — no PayPal account required. Processed securely by PayPal.</p>
        <a class="confirm-back" href="/shop/cart.php">← Back to cart</a>
      </form>
    </div>
  </div>
</section>

<script src="https://www.paypal.com/sdk/js?client-id=<?= h(urlencode(paypal_client_id())) ?>&currency=<?= h(paypal_currency()) ?>&intent=authorize&commit=false&components=buttons&enable-funding=venmo"
        data-namespace="paypalSDK"></script>
<script>
(function(){
  var TAX_BASE_CENTS = <?= (int)$taxBase ?>;
  var TAX_RATE = <?= json_encode((float)$taxRate) ?>;
  var BASE_TOTAL_CENTS = <?= (int)($itemsTotal - $discountCents + $shippingCents) ?>; // total before tax

  var form = document.getElementById('confirm-form');
  var giftCheckbox = document.getElementById('is-gift');
  var shipSelf = document.getElementById('ship-self');
  var shipGift = document.getElementById('ship-gift');
  var errBox = document.getElementById('confirm-error');
  var taxRow = document.getElementById('sum-tax-row');
  var taxCell = document.getElementById('sum-tax');
  var totalCell = document.getElementById('sum-total');

  function money(cents){
    return '$' + (cents / 100).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }

  function setVisibility(isGift) {
    shipSelf.hidden = isGift;
    shipGift.hidden = !isGift;
    // Toggle "required" on the inactive block — hidden inputs that are still
    // required will block submission with no visible error.
    shipSelf.querySelectorAll('[data-shipreq]').forEach(function(i){ i.required = !isGift; });
    shipGift.querySelectorAll('[data-giftreq]').forEach(function(i){ i.required = isGift; });
  }

  // --- Live Texas sales-tax preview ---
  function activeBlock(){ return giftCheckbox.checked ? shipGift : shipSelf; }
  function isTexas(state){
    var s = String(state || '').trim().toUpperCase().replace(/\./g, '');
    return s === 'TX' || s === 'TEXAS';
  }
  function computeTaxCents(){
    var block = activeBlock();
    var stateEl = block.querySelector('[data-taxstate]');
    var countryEl = block.querySelector('[data-taxcountry]');
    var state = stateEl ? stateEl.value : '';
    var country = countryEl ? String(countryEl.value || '').trim().toUpperCase() : '';
    if (country === 'US' && isTexas(state)) {
      return Math.round(TAX_BASE_CENTS * TAX_RATE);
    }
    return 0;
  }
  function refreshTax(){
    var tax = computeTaxCents();
    if (tax > 0) {
      taxRow.style.display = 'contents';
      taxCell.textContent = money(tax);
    } else {
      taxRow.style.display = 'none';
    }
    totalCell.textContent = money(BASE_TOTAL_CENTS + tax);
  }

  setVisibility(false);
  refreshTax();
  giftCheckbox.addEventListener('change', function(){ setVisibility(giftCheckbox.checked); refreshTax(); });
  form.addEventListener('input', function(ev){
    if (ev.target.matches('[data-taxstate], [data-taxcountry]')) refreshTax();
  });

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
  function buildPayload() {
    var isGift = giftCheckbox.checked;
    return {
      name:     val('name'),
      email:    val('email'),
      phone:    val('phone'),
      is_gift:  isGift,
      gift_recipient_name: isGift ? val('gift_recipient_name') : '',
      gift_message: isGift ? val('gift_message') : '',
      address:  isGift ? addrFromBlock('gift') : addrFromBlock('self'),
    };
  }

  if (!window.paypalSDK || !window.paypalSDK.Buttons) {
    showErr('PayPal failed to load. Refresh the page or try again later.');
    return;
  }

  window.paypalSDK.Buttons({
    style: { layout: 'vertical', shape: 'pill', label: 'paypal' },
    // Validate the form, then create the order priced with the confirmed
    // ship-to (so tax is included). Rejecting here keeps the popup closed.
    createOrder: function(){
      errBox.style.display = 'none';
      if (!form.reportValidity()) {
        return Promise.reject(new Error('Please complete the required fields above.'));
      }
      return fetch('/api/create-cart-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildPayload())
      })
      .then(function(r){ return r.json().then(function(j){ return { status: r.status, body: j }; }); })
      .then(function(res){
        if (res.body.error) throw new Error(res.body.error);
        return res.body.id;
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
          if (res.status === 409) {
            showErr(res.body.error + ' Returning to your cart…');
            setTimeout(function(){ window.location.href = '/shop/cart.php'; }, 1800);
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
    onCancel: function(){ /* buyer closed the popup — no-op */ }
  }).render('#paypal-button-container').catch(function(err){
    console.error(err);
    showErr('Could not load the PayPal buttons. Refresh and try again.');
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
