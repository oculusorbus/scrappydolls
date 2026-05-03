<?php
$page = 'products';
$title = 'Dolls';
require __DIR__ . '/header.php';

$filter = $_GET['status'] ?? 'all';
$where = '';
$params = [];
if (in_array($filter, ['draft', 'available', 'sold'], true)) {
    $where = 'WHERE p.status = :s';
    $params[':s'] = $filter;
}

$sql = "
  SELECT p.id, p.slug, p.title, p.price_cents, p.status, p.updated_at,
    (SELECT filename FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb
  FROM products p
  $where
  ORDER BY p.updated_at DESC
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<div class="page-head">
  <h1 class="page-title">Dolls</h1>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <a class="btn btn-ghost" href="/admin/import.php">Bulk import</a>
    <a class="btn btn-primary" href="/admin/edit.php">+ Add new doll</a>
  </div>
</div>

<div style="margin-bottom:1.5rem">
  <a class="btn btn-sm <?= $filter==='all' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/products.php">All</a>
  <a class="btn btn-sm <?= $filter==='available' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/products.php?status=available">Available</a>
  <a class="btn btn-sm <?= $filter==='draft' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/products.php?status=draft">Drafts</a>
  <a class="btn btn-sm <?= $filter==='sold' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/products.php?status=sold">Sold</a>
</div>

<?php if (!$rows): ?>
  <div class="empty">
    <h3>No dolls yet</h3>
    <p>Add a single doll, or bulk-import a folder of photos all at once.</p>
    <p style="margin-top:1rem">
      <a class="btn btn-primary" href="/admin/edit.php">+ Add new doll</a>
      <a class="btn btn-ghost" href="/admin/import.php">Bulk import</a>
    </p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th></th>
          <th>Title</th>
          <th>Price</th>
          <th>Status</th>
          <th>Updated</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="thumb">
              <?php if ($r['thumb']): ?>
                <img src="<?= h(thumb_url($r['thumb'])) ?>" alt="">
              <?php else: ?>
                <div style="width:3rem;height:3rem;background:var(--paper-3);border-radius:6px;border:1px solid var(--rule)"></div>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= h($r['title']) ?></strong><br>
              <span style="font-size:.8rem;color:var(--ink-muted)">/<?= h($r['slug']) ?></span>
            </td>
            <td><?= fmt_price((int)$r['price_cents']) ?></td>
            <td><span class="badge badge-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
            <td style="color:var(--ink-muted);font-size:.85rem"><?= h(date('M j, Y', strtotime($r['updated_at']))) ?></td>
            <td class="actions">
              <a class="btn btn-sm btn-ghost" href="/shop/product.php?slug=<?= h(urlencode($r['slug'])) ?>" target="_blank" rel="noopener">View</a>
              <a class="btn btn-sm btn-primary" href="/admin/edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
