<?php
$page = 'dashboard';
$title = 'Dashboard';
require __DIR__ . '/header.php';

$counts = db()->query("
  SELECT
    SUM(status='available') AS available,
    SUM(status='draft')     AS drafts,
    SUM(status='sold')      AS sold
  FROM products
")->fetch() ?: ['available' => 0, 'drafts' => 0, 'sold' => 0];

$pending = (int)db()->query("SELECT COUNT(*) FROM orders WHERE status='paid'")->fetchColumn();
$revenue = (int)db()->query("SELECT COALESCE(SUM(amount_cents),0) FROM orders WHERE status IN ('paid','shipped')")->fetchColumn();

$recent = db()->query("
  SELECT o.id, o.amount_cents, o.status, o.created_at, o.customer_name, p.title
  FROM orders o
  JOIN products p ON p.id = o.product_id
  ORDER BY o.created_at DESC
  LIMIT 8
")->fetchAll();
?>
<h1 class="page-title">Dashboard</h1>

<div class="stats">
  <div class="stat"><div class="k"><?= (int)$counts['available'] ?></div><div class="v">Dolls available</div></div>
  <div class="stat"><div class="k"><?= (int)$counts['drafts'] ?></div><div class="v">Drafts</div></div>
  <div class="stat"><div class="k"><?= (int)$counts['sold'] ?></div><div class="v">Sold</div></div>
  <div class="stat"><div class="k"><?= $pending ?></div><div class="v">Awaiting ship</div></div>
  <div class="stat"><div class="k"><?= fmt_price($revenue) ?></div><div class="v">Total revenue</div></div>
</div>

<div class="page-head">
  <h2 style="margin:0">Recent orders</h2>
  <a class="btn btn-ghost btn-sm" href="/admin/orders.php">All orders →</a>
</div>

<?php if (!$recent): ?>
  <div class="empty">
    <h3>No orders yet</h3>
    <p>When someone buys a doll, you'll see it here.</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Doll</th><th>Buyer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr>
          <td><a href="/admin/order.php?id=<?= (int)$o['id'] ?>"><?= h($o['title']) ?></a></td>
          <td><?= h($o['customer_name'] ?: '—') ?></td>
          <td><?= fmt_price((int)$o['amount_cents']) ?></td>
          <td><span class="badge badge-<?= h($o['status']) ?>"><?= h($o['status']) ?></span></td>
          <td><?= h(date('M j, Y', strtotime($o['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<h2>Quick actions</h2>
<p>
  <a class="btn btn-primary" href="/admin/edit.php">+ Add new doll</a>
  <a class="btn btn-ghost" href="/admin/products.php">Manage dolls</a>
</p>

<?php require __DIR__ . '/footer.php'; ?>
