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
          // From a cart-page suggestion card: insert a new row in place from
          // the suggestion's own data, fade out the suggestion, and refresh
          // totals — without reloading, so the rest of the suggestion strip
          // doesn't get reshuffled out from under the buyer.
          var sug = addBtn.closest('.cart-suggestion');
          if (sug) {
            insertCartRowFromSuggestion(sug, pid);
            updateCartTotals(data);
            sug.classList.add('is-added');
            setTimeout(function () { sug.remove(); }, 500);
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

    var clearBtn = e.target.closest('[data-cart-clear]');
    if (clearBtn) {
      e.preventDefault();
      if (!window.confirm('Remove all dolls from your cart?')) return;
      clearBtn.disabled = true;
      postCart({ action: 'clear' })
        .then(function () { window.location.reload(); })
        .catch(function (err) { showError(err.message); clearBtn.disabled = false; });
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

  // Build a .cart-row from a suggestion card and append it to the cart list.
  // Markup mirrors what shop/cart.php renders for items.
  function insertCartRowFromSuggestion(sug, pid) {
    var list = document.querySelector('.cart-list');
    if (!list) return;
    var titleA = sug.querySelector('.cart-suggestion-title');
    var imgA   = sug.querySelector('.cart-suggestion-img');
    var img    = imgA ? imgA.querySelector('img') : null;
    var price  = sug.querySelector('.cart-suggestion-price');
    var title  = titleA ? titleA.textContent.trim() : 'Doll';
    var slug   = titleA ? (titleA.getAttribute('href') || '') : '';

    var row = document.createElement('div');
    row.className = 'cart-row';
    row.setAttribute('data-product-id', String(pid));
    var thumbHtml = img ? '<img src="' + img.getAttribute('src') + '" alt="' + escapeAttr(title) + '">' : '';
    row.innerHTML =
      '<a class="cart-row-img" href="' + escapeAttr(slug) + '">' + thumbHtml + '</a>' +
      '<div class="cart-row-meta">' +
        '<a class="cart-row-title" href="' + escapeAttr(slug) + '"></a>' +
        '<p class="cart-row-tag">One of a kind</p>' +
      '</div>' +
      '<div class="cart-row-side">' +
        '<span class="cart-row-price"></span>' +
        '<button type="button" class="cart-row-remove" data-cart-remove="' + pid + '">Remove</button>' +
      '</div>';
    // Set text via textContent to avoid double-encoding.
    row.querySelector('.cart-row-title').textContent = title;
    row.querySelector('.cart-row-price').textContent = price ? price.textContent.trim() : '';
    row.querySelector('.cart-row-remove').setAttribute('aria-label', 'Remove ' + title);
    list.appendChild(row);
  }

  function escapeAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function updateCartTotals(data) {
    var sub  = document.querySelector('[data-cart-subtotal]');
    var ship = document.querySelector('[data-cart-shipping]');
    var tot  = document.querySelector('[data-cart-total]');
    var n    = document.querySelector('[data-cart-itemcount]');
    if (sub  && typeof data.subtotal_cents === 'number')    sub.textContent  = fmtCents(data.subtotal_cents);
    if (ship && typeof data.shipping_cents === 'number')    ship.textContent = fmtCents(data.shipping_cents);
    if (tot  && typeof data.grand_total_cents === 'number') tot.textContent  = fmtCents(data.grand_total_cents);
    if (n    && typeof data.count === 'number')             n.textContent    = String(data.count);
  }
})();
