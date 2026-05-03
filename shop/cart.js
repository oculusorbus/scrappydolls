/* Scrappy Dolls — cart client.
 * Handles "Add to cart" / "Remove" buttons via delegation.
 * Buttons opt in by:
 *   - data-cart-add="<product_id>"  (or class="cart-add-btn" + data-product-id)
 *   - data-cart-remove="<product_id>"
 *
 * Updates the header cart count badge ([data-cart-count]) on success.
 * Buttons can render their own state via data-in-cart="1".
 */
(function () {
  function setCartCount(n) {
    document.querySelectorAll('[data-cart-count]').forEach(function (el) {
      el.textContent = String(n);
      var link = el.closest('.cart-link');
      if (link) link.classList.toggle('has-items', n > 0);
    });
  }

  function showError(msg) {
    var box = document.getElementById('cart-error');
    if (box) {
      box.textContent = msg || 'Something went wrong.';
      box.style.display = 'block';
    } else {
      alert(msg || 'Something went wrong.');
    }
  }

  function postCart(payload) {
    return fetch('/api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }).then(function (r) {
      return r.json().then(function (data) {
        if (!r.ok || data.error) throw new Error(data.error || 'Request failed');
        return data;
      });
    });
  }

  function flashAdded(btn) {
    var label = btn.querySelector('.cart-add-label');
    var prev = label ? label.textContent : btn.textContent;
    btn.dataset.inCart = '1';
    if (label) label.textContent = 'Added ✓';
    btn.classList.add('is-added');
    setTimeout(function () {
      if (label) label.textContent = 'In your cart';
      btn.classList.remove('is-added');
    }, 1400);
  }

  document.addEventListener('click', function (e) {
    var addBtn = e.target.closest('[data-cart-add], .cart-add-btn');
    if (addBtn) {
      e.preventDefault();
      var pid = parseInt(addBtn.getAttribute('data-cart-add') || addBtn.getAttribute('data-product-id') || '0', 10);
      if (!pid) return;
      addBtn.disabled = true;
      postCart({ action: 'add', product_id: pid })
        .then(function (data) {
          setCartCount(data.count);
          // From a cart-page suggestion card: fade it out and reload so the
          // new row, subtotal, and fresh suggestions all refresh together.
          var sug = addBtn.closest('.cart-suggestion');
          if (sug) {
            sug.classList.add('is-added');
            setTimeout(function () { window.location.reload(); }, 500);
            return;
          }
          // Anywhere else (product detail, listing): take the buyer straight
          // to the cart so they see what's in it and can check out.
          flashAdded(addBtn);
          window.location.href = '/shop/cart.php';
        })
        .catch(function (err) { showError(err.message); addBtn.disabled = false; });
      return;
    }

    var rmBtn = e.target.closest('[data-cart-remove]');
    if (rmBtn) {
      e.preventDefault();
      var pid2 = parseInt(rmBtn.getAttribute('data-cart-remove'), 10);
      if (!pid2) return;
      rmBtn.disabled = true;
      postCart({ action: 'remove', product_id: pid2 })
        .then(function (data) {
          setCartCount(data.count);
          var row = rmBtn.closest('.cart-row');
          if (row) row.remove();
          // If the cart is empty, reload so the "empty cart" view renders.
          if (data.count === 0) window.location.reload();
          else updateCartTotals(data);
        })
        .catch(function (err) { showError(err.message); rmBtn.disabled = false; });
    }
  });

  function fmtCents(c) { return '$' + (c / 100).toFixed(2); }

  function updateCartTotals(data) {
    var sub  = document.querySelector('[data-cart-subtotal]');
    var ship = document.querySelector('[data-cart-shipping]');
    var tot  = document.querySelector('[data-cart-total]');
    if (sub  && typeof data.subtotal_cents === 'number')    sub.textContent  = fmtCents(data.subtotal_cents);
    if (ship && typeof data.shipping_cents === 'number')    ship.textContent = fmtCents(data.shipping_cents);
    if (tot  && typeof data.grand_total_cents === 'number') tot.textContent  = fmtCents(data.grand_total_cents);
  }
})();
