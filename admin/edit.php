<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$images = [];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch();
    if (!$product) { flash('error', 'Doll not found.'); redirect('/admin/products.php'); }
    $istmt = db()->prepare('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC');
    $istmt->execute([':id' => $id]);
    $images = $istmt->fetchAll();
}

// New doll: pre-fill the title with the next number in the catalog protocol
// (Scrappy Doll #N where N is auto-floored at 101 and increments from the
// highest existing). Mom can override by typing — this just saves her the
// look-up step.
$defaultTitle = '';
if (!$product) {
    $defaultTitle = 'Scrappy Doll #' . next_enumeration_number('Scrappy Doll');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired — please try again.';
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $slugRaw = trim((string)($_POST['slug'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $price = (string)($_POST['price'] ?? '');
        $status = (string)($_POST['status'] ?? 'draft');
        $featured = !empty($_POST['featured']) ? 1 : 0;

        if ($title === '') $errors[] = 'Title is required.';
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $price)) $errors[] = 'Price must look like 125 or 125.00.';
        if (!in_array($status, ['draft', 'available', 'sold'], true)) $status = 'draft';

        $priceCents = (int)round(((float)$price) * 100);
        $slug = $slugRaw !== '' ? slugify($slugRaw) : slugify($title);
        $slug = unique_slug($slug, $id ?: null);

        if (!$errors) {
            if ($id) {
                $up = db()->prepare('UPDATE products SET slug=:slug,title=:t,description=:d,price_cents=:p,status=:s,featured=:f WHERE id=:id');
                $up->execute([
                    ':slug' => $slug, ':t' => $title, ':d' => $description,
                    ':p' => $priceCents, ':s' => $status, ':f' => $featured, ':id' => $id,
                ]);
                $productId = $id;
            } else {
                $ins = db()->prepare('INSERT INTO products (slug,title,description,price_cents,status,featured) VALUES (:slug,:t,:d,:p,:s,:f)');
                $ins->execute([
                    ':slug' => $slug, ':t' => $title, ':d' => $description,
                    ':p' => $priceCents, ':s' => $status, ':f' => $featured,
                ]);
                $productId = (int)db()->lastInsertId();
            }

            $maxSort = 0;
            if ($id && $images) {
                foreach ($images as $img) $maxSort = max($maxSort, (int)$img['sort_order']);
                $maxSort++;
            }
            if (!empty($_FILES['images']) && !empty($_FILES['images']['name'])) {
                $files = normalize_files_array($_FILES['images']);
                $res = handle_image_upload($productId, $files, $maxSort);
                foreach ($res['errors'] as $e) $errors[] = $e;
            }

            if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                $ids = array_filter(array_map('intval', $_POST['delete_images']));
                if ($ids) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $sel = db()->prepare("SELECT filename FROM product_images WHERE product_id = ? AND id IN ($in)");
                    $sel->execute(array_merge([$productId], $ids));
                    foreach ($sel->fetchAll() as $row) delete_image_file($row['filename']);
                    $del = db()->prepare("DELETE FROM product_images WHERE product_id = ? AND id IN ($in)");
                    $del->execute(array_merge([$productId], $ids));
                }
            }

            if (!$errors) {
                flash('success', $id ? 'Doll updated.' : 'Doll created.');
                redirect('/admin/edit.php?id=' . $productId);
            }
        }

        // Refresh product/images for redisplay
        $stmt = db()->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute([':id' => $productId ?? $id]);
        $product = $stmt->fetch() ?: $product;
        if ($product) {
            $istmt = db()->prepare('SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC');
            $istmt->execute([':id' => $product['id']]);
            $images = $istmt->fetchAll();
        }
    }
}

$page = 'products';
$title = $product ? ('Edit: ' . $product['title']) : 'Add doll';
require __DIR__ . '/header.php';
?>

<div class="page-head">
  <h1 class="page-title"><?= $product ? 'Edit doll' : 'Add doll' ?></h1>
  <?php if ($product): ?>
    <a class="btn btn-ghost btn-sm" href="/shop/product.php?slug=<?= h(urlencode($product['slug'])) ?>" target="_blank" rel="noopener">View on site →</a>
  <?php endif; ?>
</div>

<?php foreach ($errors as $e): ?>
  <div class="flash flash-error"><?= h($e) ?></div>
<?php endforeach; ?>

<form class="form" method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="row cols-2">
    <div class="field">
      <label for="title">Title</label>
      <input type="text" name="title" id="title" required maxlength="255"
             value="<?= h($_POST['title'] ?? ($product['title'] ?? $defaultTitle)) ?>"
             placeholder="e.g. Marigold the Sunflower Doll">
      <?php if (!$product): ?>
        <span class="hint">Pre-filled from the catalog sequence — type to override.</span>
      <?php endif; ?>
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select name="status" id="status">
        <?php $cur = $_POST['status'] ?? ($product['status'] ?? 'draft'); ?>
        <option value="draft"      <?= $cur==='draft'?'selected':'' ?>>Draft (not on site)</option>
        <option value="available"  <?= $cur==='available'?'selected':'' ?>>Available (on site)</option>
        <option value="sold"       <?= $cur==='sold'?'selected':'' ?>>Sold</option>
      </select>
      <?php $isFeatured = !empty($_POST['featured']) || (!isset($_POST['featured']) && !empty($product['featured'])); ?>
      <label style="display:flex;align-items:center;gap:.5rem;margin-top:.6rem;font-size:.92rem;color:var(--ink);text-transform:none;letter-spacing:0;font-weight:500">
        <input type="checkbox" name="featured" value="1" <?= $isFeatured ? 'checked' : '' ?>>
        ★ Featured (prioritized on the landing page)
      </label>
    </div>
  </div>

  <div class="row cols-2">
    <div class="field">
      <label for="price">Price (USD)</label>
      <div class="input-prefix">
        <span>$</span>
        <input type="text" inputmode="decimal" name="price" id="price" required
               pattern="\d+(\.\d{1,2})?"
               value="<?= h($_POST['price'] ?? (isset($product['price_cents']) ? number_format($product['price_cents']/100,2,'.','') : '')) ?>"
               placeholder="125.00">
      </div>
      <span class="hint">Enter total including any shipping you want to bake in.</span>
    </div>
    <div class="field">
      <label for="slug">URL slug</label>
      <input type="text" name="slug" id="slug" maxlength="255"
             value="<?= h($_POST['slug'] ?? ($product['slug'] ?? '')) ?>"
             placeholder="leave blank to auto-generate">
      <span class="hint">Used in the doll's web address. Lowercase, dashes only.</span>
    </div>
  </div>

  <div class="row">
    <div class="field">
      <label for="description">Description</label>
      <textarea name="description" id="description" rows="6" placeholder="The story of this doll. Materials, vibe, who she's for."><?= h($_POST['description'] ?? ($product['description'] ?? '')) ?></textarea>
    </div>
  </div>

  <h2 style="margin-top:1.5rem">Images</h2>
  <?php if ($images): ?>
    <div class="image-grid">
      <?php foreach ($images as $img): ?>
        <div class="image-tile">
          <img src="<?= h(thumb_url($img['filename'])) ?>" alt="">
          <label class="del" title="Mark for deletion on save">
            <input type="checkbox" name="delete_images[]" value="<?= (int)$img['id'] ?>" style="display:none"
                   onchange="this.parentElement.classList.toggle('marked', this.checked)">
            ×
          </label>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin-bottom:1rem">Click the × on an image to mark it for deletion when you save.</p>
  <?php endif; ?>

  <div class="dropzone">
    <p>Add new images (you can pick multiple)</p>
    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif">
  </div>

  <div class="form-actions">
    <a href="/admin/products.php" class="btn btn-ghost">Cancel</a>
    <?php if ($product): ?>
      <button type="button" class="btn btn-danger" onclick="document.getElementById('delform').submit()">Delete doll</button>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary"><?= $product ? 'Save changes' : 'Create doll' ?></button>
  </div>
</form>

<?php if ($product): ?>
<form id="delform" method="post" action="/admin/delete.php" onsubmit="return confirm('Permanently delete this doll? This can’t be undone.');" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
</form>
<?php endif; ?>

<style>
  .image-tile.marked{outline:3px solid var(--red);outline-offset:-3px}
  .image-tile.marked img{opacity:.4}
  .image-tile .del{user-select:none}
</style>

<?php require __DIR__ . '/footer.php'; ?>
