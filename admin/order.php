<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('/admin/orders.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_shipped') {
        $tn = trim((string)($_POST['tracking_number'] ?? ''));
        $stmt = db()->prepare("UPDATE orders SET status='shipped', shipped_at = NOW(), tracking_number = :tn WHERE id = :id");
        $stmt->execute([':tn' => $tn ?: null, ':id' => $id]);
        flash('success', 'Marked as shipped.');
    } elseif ($action === 'save_notes') {
        $notes = trim((string)($_POST['notes'] ?? ''));
        $stmt = db()->prepare("UPDATE orders SET notes = :n WHERE id = :id");
        $stmt->execute([':n' => $notes ?: null, ':id' => $id]);
        flash('success', 'Notes saved.');
    }
    redirect('/admin/order.php?id=' . $id);
}

$stmt = db()->prepare("SELECT * FROM orders WHERE id = :id");
$stmt->execute([':id' => $id]);
$order = $stmt->fetch();
if (!$order) { flash('error','Order not found.'); redirect('/admin/orders.php'); }

$itStmt = db()->prepare("
  SELECT oi.*, p.slug AS product_slug,
    (SELECT filename FROM product_images WHERE product_id = oi.product_id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb
  FROM order_items oi
  LEFT JOIN products p ON p.id = oi.product_id
  WHERE oi.order_id = :id
  ORDER BY oi.id ASC
");
$itStmt->execute([':id' => $id]);
$items = $itStmt->fetchAll();

$shipping = $order['shipping_address'] ? json_decode($order['shipping_address'], true) : null;

$page = 'orders';
$title = 'Order #' . $id;
require __DIR__ . '/header.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-title" style="margin-bottom:.25rem">Order #<?= (int)$order['id'] ?></h1>
    <span class="badge badge-<?= h($order['status']) ?>"><?= h($order['status']) ?></span>
  </div>
  <a href="/admin/orders.php" class="btn btn-ghost btn-sm">← Back</a>
</div>

<div class="detail-grid">
  <div>
    <div class="card" style="margin-bottom:1.25rem">
      <h3><?= count($items) === 1 ? 'Doll' : 'Dolls (' . count($items) . ')' ?></h3>
      <?php if (!$items): ?>
        <p style="color:var(--ink-muted);margin:0">No items recorded for this order.</p>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:.85rem">
          <?php foreach ($items as $it): ?>
            <div style="display:flex;gap:1rem;align-items:center">
              <?php if ($it['thumb']): ?>
                <img src="<?= h(thumb_url($it['thumb'])) ?>" alt="" style="width:4.5rem;height:4.5rem;object-fit:cover;object-position:center top;border-radius:8px;border:1px solid var(--rule);flex-shrink:0">
              <?php endif; ?>
              <div style="min-width:0">
                <strong style="font-size:1.05rem"><?= h($it['title_snapshot']) ?></strong>
                <span style="color:var(--ink-muted);margin-left:.5rem"><?= fmt_price((int)$it['amount_cents']) ?></span><br>
                <?php if ($it['product_slug']): ?>
                  <a href="/shop/product.php?slug=<?= h(urlencode($it['product_slug'])) ?>" target="_blank" rel="noopener" style="font-size:.85rem">View on site →</a>
                <?php else: ?>
                  <span style="font-size:.82rem;color:var(--ink-muted)">Product was deleted from catalog</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($order['status'] === 'paid'): ?>
      <div class="card" style="margin-bottom:1.25rem">
        <h3>Mark as shipped</h3>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="mark_shipped">
          <div class="field" style="margin-bottom:1rem">
            <label>Tracking number (optional)</label>
            <input type="text" name="tracking_number" maxlength="255" placeholder="e.g. 9400 1112 0000 0000 0000 00">
          </div>
          <button class="btn btn-primary">Mark shipped</button>
        </form>
      </div>
    <?php endif; ?>

    <div class="card">
      <h3>Internal notes</h3>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_notes">
        <div class="field" style="margin-bottom:1rem">
          <textarea name="notes" rows="4" placeholder="Anything you want to remember about this order"><?= h($order['notes'] ?? '') ?></textarea>
        </div>
        <button class="btn btn-ghost btn-sm">Save notes</button>
      </form>
    </div>
  </div>

  <div>
    <div class="card" style="margin-bottom:1.25rem">
      <h3>Buyer</h3>
      <dl class="kv">
        <dt>Name</dt><dd><?= h($order['customer_name'] ?: '—') ?></dd>
        <dt>Email</dt><dd><?php if ($order['customer_email']): ?><a href="mailto:<?= h($order['customer_email']) ?>"><?= h($order['customer_email']) ?></a><?php else: ?>—<?php endif; ?></dd>
        <dt>Phone</dt><dd><?php if (!empty($order['customer_phone'])): ?><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $order['customer_phone'])) ?>"><?= h($order['customer_phone']) ?></a><?php else: ?>—<?php endif; ?></dd>
      </dl>
    </div>

    <?php if ($shipping): $isGift = !empty($order['is_gift']); ?>
      <?php $a = $shipping['address'] ?? $shipping; ?>
      <div class="card" style="margin-bottom:1.25rem<?= $isGift ? ';border-color:var(--rose,#b13e54)' : '' ?>">
        <h3 style="<?= $isGift ? 'color:var(--rose,#b13e54)' : '' ?>"><?= $isGift ? 'Gift — ship to recipient' : 'Ship to' ?></h3>
        <?php if ($isGift): ?>
          <p style="margin:0 0 .65rem;font-size:.85rem;color:var(--ink-muted,#6b5852)">Address the package to the recipient, not the buyer.</p>
        <?php endif; ?>
        <p style="margin:0;line-height:1.6">
          <?= h($shipping['name'] ?? ($order['customer_name'] ?? '')) ?><br>
          <?= h($a['address_line_1'] ?? '') ?><br>
          <?php if (!empty($a['address_line_2'])): ?><?= h($a['address_line_2']) ?><br><?php endif; ?>
          <?= h(trim(($a['admin_area_2'] ?? '') . ', ' . ($a['admin_area_1'] ?? '') . ' ' . ($a['postal_code'] ?? ''), ', ')) ?><br>
          <?= h($a['country_code'] ?? '') ?>
        </p>
      </div>

      <?php if ($isGift): ?>
        <div class="card" style="margin-bottom:1.25rem;border:2px solid var(--rose,#b13e54);background:#fff7f7">
          <h3 style="color:var(--rose,#b13e54);margin-top:0">📝 Include this note with the package</h3>
          <?php if (!empty($order['gift_message'])): ?>
            <blockquote style="margin:0;padding:.75rem 1rem;background:#fff;border-left:3px solid var(--rose,#b13e54);border-radius:4px;white-space:pre-wrap;font-style:italic;line-height:1.55"><?= h($order['gift_message']) ?></blockquote>
            <p style="margin:.65rem 0 0;font-size:.85rem;color:var(--ink-muted,#6b5852)">Print or hand-write this on a card and pack it with the doll.</p>
          <?php else: ?>
            <p style="margin:0;font-size:.9rem;color:var(--ink-muted,#6b5852)">No note from the buyer. Pack the doll without a card.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php
      $itemsSubtotal = 0;
      foreach ($items as $it) $itemsSubtotal += (int)$it['amount_cents'];
      $discountPaid = (int)($order['discount_cents'] ?? 0);
      $taxPaid      = (int)($order['tax_cents'] ?? 0);
      $shippingPaid = max(0, (int)$order['amount_cents'] - ($itemsSubtotal - $discountPaid) - $taxPaid);
    ?>
    <div class="card">
      <h3>Payment</h3>
      <dl class="kv">
        <dt>Subtotal</dt><dd><?= fmt_price($itemsSubtotal) ?></dd>
        <?php if (!empty($order['coupon_code'])): ?>
          <dt>Coupon</dt><dd><span style="font-family:monospace"><?= h($order['coupon_code']) ?></span><?= $discountPaid > 0 ? ' (−' . fmt_price($discountPaid) . ')' : ' (free shipping)' ?></dd>
        <?php endif; ?>
        <dt>Shipping</dt><dd><?= fmt_price($shippingPaid) ?></dd>
        <?php if ($taxPaid > 0): ?>
          <dt>Sales tax (TX)</dt><dd><?= fmt_price($taxPaid) ?></dd>
        <?php endif; ?>
        <dt>Total</dt><dd><strong><?= fmt_price((int)$order['amount_cents']) ?></strong> <?= h($order['currency']) ?></dd>
        <dt>PayPal Order</dt><dd style="font-family:monospace;font-size:.82rem;word-break:break-all"><?= h($order['paypal_order_id']) ?></dd>
        <?php if ($order['paypal_capture_id']): ?>
          <dt>Capture ID</dt><dd style="font-family:monospace;font-size:.82rem;word-break:break-all"><?= h($order['paypal_capture_id']) ?></dd>
        <?php endif; ?>
        <?php if ($order['tracking_number']): ?>
          <dt>Tracking</dt><dd><?= h($order['tracking_number']) ?></dd>
        <?php endif; ?>
        <dt>Created</dt><dd><?= h(date('M j, Y g:ia', strtotime($order['created_at']))) ?></dd>
        <?php if ($order['paid_at']): ?>
          <dt>Paid</dt><dd><?= h(date('M j, Y g:ia', strtotime($order['paid_at']))) ?></dd>
        <?php endif; ?>
        <?php if ($order['shipped_at']): ?>
          <dt>Shipped</dt><dd><?= h(date('M j, Y g:ia', strtotime($order['shipped_at']))) ?></dd>
        <?php endif; ?>
      </dl>
    </div>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
