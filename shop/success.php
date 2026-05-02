<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$ref = (string)($_GET['order'] ?? '');
$order = null;
if ($ref !== '') {
    $stmt = db()->prepare('SELECT id, paypal_order_id, status, customer_name FROM orders WHERE paypal_order_id = :ref OR id = :id LIMIT 1');
    $stmt->execute([':ref' => $ref, ':id' => (int)$ref]);
    $order = $stmt->fetch();
}

$pageTitle = 'Thank you';
$pageDesc  = 'Your order is confirmed.';
require __DIR__ . '/header.php';
?>
<section>
  <div class="success-card">
    <p class="eyebrow" style="justify-content:center">Thank you</p>
    <h1>Your doll is on her way.</h1>
    <p>Kanda will hand-pack and ship within a few days. You'll get a separate email from PayPal with your payment receipt.</p>
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
