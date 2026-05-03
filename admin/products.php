<?php
$page = 'products';
$title = 'Dolls';
require __DIR__ . '/header.php';

$filter   = $_GET['status'] ?? 'all';
$editMode = !empty($_GET['mode']) && $_GET['mode'] === 'edit';

$where = '';
$params = [];
if (in_array($filter, ['draft', 'available', 'sold'], true)) {
    $where = 'WHERE p.status = :s';
    $params[':s'] = $filter;
} elseif ($filter === 'featured') {
    $where = 'WHERE p.featured = 1';
}

$sql = "
  SELECT p.id, p.slug, p.title, p.price_cents, p.status, p.featured, p.updated_at,
    (SELECT filename FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb
  FROM products p
  $where
  ORDER BY p.featured DESC, p.updated_at DESC
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Preserve current filter when toggling edit mode
$exitEditUrl  = '/admin/products.php' . ($filter !== 'all' ? '?status=' . urlencode($filter) : '');
$enterEditUrl = '/admin/products.php?mode=edit' . ($filter !== 'all' ? '&status=' . urlencode($filter) : '');
?>
<div class="page-head">
  <h1 class="page-title">Dolls<?= $editMode ? ' — bulk edit' : '' ?></h1>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <?php if (!$editMode): ?>
      <?php if ($rows): ?>
        <a class="btn btn-ghost" href="<?= h($enterEditUrl) ?>">Bulk edit</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="/admin/import.php">Bulk import</a>
      <a class="btn btn-primary" href="/admin/edit.php">+ Add new doll</a>
    <?php endif; ?>
  </div>
</div>

<div style="margin-bottom:1.5rem">
  <a class="btn btn-sm <?= $filter==='all' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/products.php">All</a>
  <a class="btn btn-sm <?= $filter==='featured' ? 'btn-primary' : 'btn-ghost' ?>" href="/admin/products.php?status=featured">★ Featured</a>
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
  <?php if ($editMode): ?>
    <form method="post" action="/admin/bulk-edit.php" id="bulkEditForm">
      <?= csrf_field() ?>
      <input type="hidden" name="return_url" value="<?= h($exitEditUrl) ?>">
      <div class="bulk-toolbar">
        <span><strong><?= count($rows) ?></strong> doll<?= count($rows) === 1 ? '' : 's' ?> · changes apply on save</span>
        <span style="display:flex;gap:.5rem">
          <a class="btn btn-sm btn-ghost" href="<?= h($exitEditUrl) ?>">Cancel</a>
          <button class="btn btn-sm btn-primary" type="submit">Save all changes</button>
        </span>
      </div>
  <?php endif; ?>

  <div class="table-wrap <?= $editMode ? 'table-edit' : '' ?>">
    <table>
      <thead>
        <tr>
          <th></th>
          <th>Title</th>
          <th>Price</th>
          <th>Status</th>
          <th title="Featured on landing page" style="width:3rem;text-align:center">★</th>
          <th><?= $editMode ? 'Slug' : 'Updated' ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php $id = (int)$r['id']; ?>
          <tr data-id="<?= $id ?>">
            <td class="thumb">
              <?php if ($r['thumb']): ?>
                <img src="<?= h(thumb_url($r['thumb'])) ?>" alt="">
              <?php else: ?>
                <div style="width:3rem;height:3rem;background:var(--paper-3);border-radius:6px;border:1px solid var(--rule)"></div>
              <?php endif; ?>
            </td>

            <?php if ($editMode): ?>
              <td>
                <input type="hidden" name="ids[]" value="<?= $id ?>">
                <input type="text" name="title[<?= $id ?>]" value="<?= h($r['title']) ?>"
                       class="cell-input" data-orig="<?= h($r['title']) ?>" required maxlength="255">
              </td>
              <td style="width:8rem">
                <div class="cell-prefix">
                  <span>$</span>
                  <input type="text" inputmode="decimal" name="price[<?= $id ?>]"
                         pattern="\d+(\.\d{1,2})?" class="cell-input" required
                         data-orig="<?= h(number_format($r['price_cents']/100, 2, '.', '')) ?>"
                         value="<?= h(number_format($r['price_cents']/100, 2, '.', '')) ?>">
                </div>
              </td>
              <td style="width:9rem">
                <select name="status[<?= $id ?>]" class="cell-input" data-orig="<?= h($r['status']) ?>">
                  <?php foreach (['draft','available','sold'] as $opt): ?>
                    <option value="<?= h($opt) ?>" <?= $r['status']===$opt?'selected':'' ?>><?= h(ucfirst($opt)) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td style="width:3rem;text-align:center">
                <input type="checkbox" name="featured[<?= $id ?>]" value="1"
                       class="cell-input featured-cb" data-orig="<?= (int)$r['featured'] ?>"
                       <?= (int)$r['featured']===1 ? 'checked' : '' ?>>
              </td>
              <td style="width:14rem">
                <input type="text" name="slug[<?= $id ?>]" value="<?= h($r['slug']) ?>"
                       class="cell-input slug" data-orig="<?= h($r['slug']) ?>" maxlength="255">
              </td>
              <td class="actions">
                <a class="btn btn-sm btn-ghost" href="/admin/edit.php?id=<?= $id ?>" title="Open full editor">Open →</a>
              </td>
            <?php else: ?>
              <td>
                <strong><?= h($r['title']) ?></strong><br>
                <span style="font-size:.8rem;color:var(--ink-muted)">/<?= h($r['slug']) ?></span>
              </td>
              <td><?= fmt_price((int)$r['price_cents']) ?></td>
              <td><span class="badge badge-<?= h($r['status']) ?>"><?= h($r['status']) ?></span></td>
              <td style="text-align:center"><?= !empty($r['featured']) ? '<span title="Featured" style="color:var(--rose);font-size:1.1rem">★</span>' : '<span style="color:var(--rule);font-size:1rem">☆</span>' ?></td>
              <td style="color:var(--ink-muted);font-size:.85rem"><?= h(date('M j, Y', strtotime($r['updated_at']))) ?></td>
              <td class="actions">
                <a class="btn btn-sm btn-ghost" href="/shop/product.php?slug=<?= h(urlencode($r['slug'])) ?>" target="_blank" rel="noopener">View</a>
                <a class="btn btn-sm btn-primary" href="/admin/edit.php?id=<?= $id ?>">Edit</a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($editMode): ?>
      <div class="bulk-toolbar bulk-toolbar-bottom">
        <span id="dirtyCount" style="color:var(--ink-muted);font-size:.85rem">No changes yet</span>
        <span style="display:flex;gap:.5rem">
          <a class="btn btn-sm btn-ghost" href="<?= h($exitEditUrl) ?>">Cancel</a>
          <button class="btn btn-sm btn-primary" type="submit">Save all changes</button>
        </span>
      </div>
    </form>
    <script>
    (function(){
      var form = document.getElementById('bulkEditForm');
      var dirty = document.getElementById('dirtyCount');
      function isDirty(el) {
        if (el.type === 'checkbox') {
          return (el.checked ? '1' : '0') !== el.dataset.orig;
        }
        return el.value !== el.dataset.orig;
      }
      function recount() {
        var n = 0;
        form.querySelectorAll('.cell-input').forEach(function(el){
          if (isDirty(el)) {
            el.closest('tr').classList.add('row-dirty');
            n++;
          } else {
            var row = el.closest('tr');
            var anyDirty = Array.prototype.some.call(row.querySelectorAll('.cell-input'), isDirty);
            if (!anyDirty) row.classList.remove('row-dirty');
          }
        });
        dirty.textContent = n === 0
          ? 'No changes yet'
          : (n + ' field' + (n===1?'':'s') + ' changed');
      }
      form.addEventListener('input', recount);
      form.addEventListener('change', recount);
    })();
    </script>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
