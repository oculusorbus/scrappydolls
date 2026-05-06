<?php
$page = 'orders';
$title = 'Orders';
require __DIR__ . '/header.php';

$filter = $_GET['status'] ?? 'all';
$where = '';
$params = [];
if (in_array($filter, ['pending','paid','shipped','refunded','failed'], true)) {
    $where = 'WHERE o.status = :s';
    $params[':s'] = $filter;
}

$sql = "
  SELECT o.*,
    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
    (SELECT title_snapshot FROM order_items oi WHERE oi.order_id = o.id ORDER BY id ASC LIMIT 1) AS first_item_title
  FROM orders o
  $where
  ORDER BY o.created_at DESC
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<h1 class="page-title">Orders</h1>

<div style="margin-bottom:1.5rem">
  <a class="btn btn-sm <?= $filter==='all' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/orders.php">All</a>
  <a class="btn btn-sm <?= $filter==='paid' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/orders.php?status=paid">Awaiting ship</a>
  <a class="btn btn-sm <?= $filter==='shipped' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/orders.php?status=shipped">Shipped</a>
  <a class="btn btn-sm <?= $filter==='refunded' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/orders.php?status=refunded">Refunded</a>
</div>

<?php if (!$rows): ?>
  <div class="empty">
    <h3>No orders yet</h3>
    <p>Customers' orders will show up here once payments come through.</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Items</th><th>Buyer</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $o):
          $count = (int)($o['item_count'] ?? 0);
          $label = $o['first_item_title']
              ? ($count > 1 ? h($o['first_item_title']) . " <span style=\"color:var(--ink-muted)\">+ " . ($count - 1) . " more</span>" : h($o['first_item_title']))
              : '<span style="color:var(--ink-muted)">(no items)</span>';
        ?>
          <tr>
            <td>#<?= (int)$o['id'] ?></td>
            <td><?= $label ?></td>
            <td>
              <?= h($o['customer_name'] ?: '—') ?>
              <?php if (!empty($o['is_gift'])): ?>
                <span style="display:inline-block;margin-left:.4rem;padding:.05rem .4rem;border-radius:4px;background:var(--rose,#b13e54);color:#fff;font-size:.7rem;font-weight:600;letter-spacing:.02em;text-transform:uppercase">Gift</span>
              <?php endif; ?>
              <?php if ($o['customer_email']): ?><br><span style="font-size:.8rem;color:var(--ink-muted)"><?= h($o['customer_email']) ?></span><?php endif; ?>
            </td>
            <td><?= fmt_price((int)$o['amount_cents']) ?></td>
            <td><span class="badge badge-<?= h($o['status']) ?>"><?= h($o['status']) ?></span></td>
            <td style="font-size:.85rem;color:var(--ink-muted)"><?= h(date('M j, Y g:ia', strtotime($o['created_at']))) ?></td>
            <td class="actions"><a class="btn btn-sm btn-primary" href="/admin/order.php?id=<?= (int)$o['id'] ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
