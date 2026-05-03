<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$ref = (string)($_GET['order'] ?? '');
$order = null;
$items = [];
if ($ref !== '') {
    $stmt = db()->prepare('SELECT id, paypal_order_id, status, customer_name, amount_cents, product_id FROM orders WHERE paypal_order_id = :ref OR id = :id LIMIT 1');
    $stmt->execute([':ref' => $ref, ':id' => (int)$ref]);
    $order = $stmt->fetch();
    if ($order) {
        $itStmt = db()->prepare('SELECT title_snapshot, amount_cents FROM order_items WHERE order_id = :id ORDER BY id ASC');
        $itStmt->execute([':id' => $order['id']]);
        $items = $itStmt->fetchAll();
        // Legacy single-item orders (pre-cart) had product_id on orders and no order_items rows.
        if (!$items && $order['product_id']) {
            $legacy = db()->prepare('SELECT title FROM products WHERE id = :id');
            $legacy->execute([':id' => $order['product_id']]);
            $row = $legacy->fetch();
            if ($row) {
                $items = [['title_snapshot' => $row['title'], 'amount_cents' => $order['amount_cents']]];
            }
        }
    }
}

$pageTitle = 'Thank you';
$pageDesc  = 'Your order is confirmed.';
require __DIR__ . '/header.php';
?>
<section>
  <div class="success-card">
    <p class="eyebrow" style="justify-content:center">Thank you</p>
    <h1><?= count($items) > 1 ? 'Your dolls are on their way.' : 'Your doll is on her way.' ?></h1>
    <p>Kanda will hand-pack and ship within a few days. You'll get a separate email from PayPal with your payment receipt.</p>
    <?php if ($items):
      $itemsTotal = 0;
      foreach ($items as $it) $itemsTotal += (int)$it['amount_cents'];
      $orderTotal  = (int)($order['amount_cents'] ?? $itemsTotal);
      $shippingPaid = max(0, $orderTotal - $itemsTotal);
    ?>
      <ul style="list-style:none;padding:0;margin:1.5rem 0 0;text-align:left;display:inline-block">
        <?php foreach ($items as $it): ?>
          <li style="padding:.4rem 0;border-bottom:1px dashed var(--rule-soft);min-width:18rem;display:flex;justify-content:space-between;gap:1rem">
            <span><?= h($it['title_snapshot']) ?></span>
            <span style="color:var(--ink-soft)"><?= fmt_price((int)$it['amount_cents']) ?></span>
          </li>
        <?php endforeach; ?>
        <?php if ($shippingPaid > 0): ?>
          <li style="padding:.4rem 0;border-bottom:1px dashed var(--rule-soft);min-width:18rem;display:flex;justify-content:space-between;gap:1rem;color:var(--ink-soft)">
            <span>Shipping</span><span><?= fmt_price($shippingPaid) ?></span>
          </li>
        <?php endif; ?>
        <li style="padding:.5rem 0 0;min-width:18rem;display:flex;justify-content:space-between;gap:1rem;font-weight:600">
          <span>Total</span><span><?= fmt_price($orderTotal) ?></span>
        </li>
      </ul>
    <?php endif; ?>
    <?php if ($order): ?>
      <p style="margin-top:1.5rem;font-size:.85rem;color:var(--ink-muted)">Order reference: <span style="font-family:monospace"><?= h($order['paypal_order_id']) ?></span></p>
    <?php endif; ?>
    <p style="margin-top:2rem">
      <a class="btn btn-primary" href="/shop/">Keep browsing</a>
      <a class="btn btn-ghost" href="/">Back to home</a>
    </p>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
