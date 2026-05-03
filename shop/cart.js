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

  // Disable submit buttons on cart-add forms so a fast double-click can't
  // submit the same form twice. The form still submits normally — we just
  // visually freeze the button until navigation happens.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form && form.classList && form.classList.contains('cart-add-form')) {
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.textContent = 'Adding…';
      }
    }
  });

  document.addEventListener('click', function (e) {
    var refreshBtn = e.target.closest('[data-cart-refresh-suggestions]');
    if (refreshBtn) {
      e.preventDefault();
      refreshBtn.disabled = true;
      refreshBtn.classList.add('is-spinning');
      var grid = document.querySelector('.cart-suggestion-grid');
      if (grid) grid.classList.add('is-fading');
      var current = collectVisibleSuggestionIds();
      var limit = current.length || 5;
      fetch('/api/cart-suggestions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ limit: limit, exclude_ids: current }),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data.error) throw new Error(data.error);
          if (grid) {
            grid.innerHTML = '';
            (data.suggestions || []).forEach(appendSuggestion);
            grid.classList.remove('is-fading');
          }
        })
        .catch(function (err) { showError(err.message); })
        .finally(function () {
          refreshBtn.disabled = false;
          // Let the spin finish before resetting so it animates a full turn.
          setTimeout(function () { refreshBtn.classList.remove('is-spinning'); }, 400);
        });
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

  function escapeAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function collectVisibleSuggestionIds() {
    var ids = [];
    document.querySelectorAll('.cart-suggestion .cart-add-btn').forEach(function (b) {
      var id = parseInt(b.getAttribute('data-product-id') || '0', 10);
      if (id) ids.push(id);
    });
    return ids;
  }

  // Build a .cart-suggestion card from server data and append it to the
  // suggestion grid so the strip stays full after a buyer adds a friend.
  function appendSuggestion(s) {
    var grid = document.querySelector('.cart-suggestion-grid');
    if (!grid || !s || !s.id) return;
    var card = document.createElement('div');
    card.className = 'cart-suggestion';
    var imgHtml = s.thumb_url
      ? '<img src="' + escapeAttr(s.thumb_url) + '" alt="' + escapeAttr(s.title) + '" loading="lazy">'
      : '';
    card.innerHTML =
      '<a class="cart-suggestion-img" href="' + escapeAttr(s.product_url) + '">' + imgHtml + '</a>' +
      '<div class="cart-suggestion-meta">' +
        '<a class="cart-suggestion-title" href="' + escapeAttr(s.product_url) + '"></a>' +
        '<span class="cart-suggestion-price"></span>' +
      '</div>' +
      '<button type="button" class="btn btn-ghost cart-add-btn" data-product-id="' + s.id + '" style="width:100%;justify-content:center">' +
        '<span class="cart-add-label">+ Add to cart</span>' +
      '</button>';
    card.querySelector('.cart-suggestion-title').textContent = s.title;
    card.querySelector('.cart-suggestion-price').textContent = s.price;
    grid.appendChild(card);
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
