<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();

$errors  = [];
$created = [];     // [['id'=>int, 'title'=>string], ...]
$failed  = [];     // [['file'=>string, 'error'=>string], ...]

$defaultBase   = 'Scrappy Doll';
$baseTitleIn   = $_POST['base_title']   ?? $defaultBase;
$priceIn       = $_POST['price']        ?? '';
$startNumIn    = $_POST['start_num']    ?? '';
$statusIn      = $_POST['status']       ?? 'draft';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired — please try again.';
    } else {
        $baseTitle = trim((string)$baseTitleIn) ?: $defaultBase;
        if (!preg_match('/^\d+(\.\d{1,2})?$/', (string)$priceIn)) {
            $errors[] = 'Price must look like 125 or 125.00.';
        }
        if (!in_array($statusIn, ['draft', 'available'], true)) $statusIn = 'draft';
        if (empty($_FILES['images']) || empty($_FILES['images']['name'][0])) {
            $errors[] = 'Pick at least one image.';
        }

        if (!$errors) {
            $priceCents = (int)round(((float)$priceIn) * 100);
            $startNum = (int)$startNumIn > 0
                ? (int)$startNumIn
                : next_enumeration_number($baseTitle);

            $files = normalize_files_array($_FILES['images']);
            $pdo = db();
            $i = 0;
            foreach ($files as $file) {
                $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
                if ($err === UPLOAD_ERR_NO_FILE) continue;

                $filename = $file['name'] ?? "image-$i";
                $title = $baseTitle . ' #' . ($startNum + $i);
                $i++;

                try {
                    $pdo->beginTransaction();

                    $slug = unique_slug(slugify($title));
                    $ins = $pdo->prepare(
                        'INSERT INTO products (slug, title, description, price_cents, status)
                         VALUES (:slug, :t, :d, :p, :s)'
                    );
                    $ins->execute([
                        ':slug' => $slug,
                        ':t'    => $title,
                        ':d'    => '',
                        ':p'    => $priceCents,
                        ':s'    => $statusIn,
                    ]);
                    $pid = (int)$pdo->lastInsertId();

                    $res = handle_image_upload($pid, [$file], 0);
                    if (!empty($res['errors']) || empty($res['saved'])) {
                        $pdo->rollBack();
                        $failed[] = ['file' => $filename, 'error' => $res['errors'][0] ?? 'No image saved'];
                        $i--; // don't burn this number
                        continue;
                    }

                    $pdo->commit();
                    $created[] = ['id' => $pid, 'title' => $title, 'thumb' => $res['saved'][0]];
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $failed[] = ['file' => $filename, 'error' => $e->getMessage()];
                    $i--;
                }
            }

            if ($created) {
                $count = count($created);
                $msg = "Imported $count " . ($count === 1 ? 'doll' : 'dolls') . " in " . $statusIn . " status.";
                flash('success', $msg);
            }
        }
    }
}

$nextNum = next_enumeration_number(trim((string)$baseTitleIn) ?: $defaultBase);

$page = 'products';
$title = 'Bulk import';
require __DIR__ . '/header.php';
?>

<div class="page-head">
  <div>
    <h1 class="page-title" style="margin-bottom:.4rem">Bulk import dolls</h1>
    <p style="margin:0;color:var(--ink-muted);font-size:.9rem">One doll per image — generic numbered titles, same price across the batch.</p>
  </div>
  <a class="btn btn-ghost btn-sm" href="/admin/products.php">← Back to dolls</a>
</div>

<?php foreach ($errors as $e): ?>
  <div class="flash flash-error"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if ($created || $failed): ?>
  <div class="flash flash-success" style="margin-bottom:1rem">
    <strong>Done.</strong>
    Created <?= count($created) ?> <?= count($created) === 1 ? 'doll' : 'dolls' ?>
    <?php if ($failed): ?>· <?= count($failed) ?> failed (see below)<?php endif; ?>.
    <a href="/admin/products.php" style="margin-left:.5rem">View all dolls →</a>
  </div>
  <?php if ($created): ?>
    <div class="report-card" style="margin-bottom:1rem">
      <h2>Created</h2>
      <div class="image-grid" style="grid-template-columns:repeat(auto-fill,minmax(7rem,1fr))">
        <?php foreach ($created as $c): ?>
          <a class="image-tile" href="/admin/edit.php?id=<?= (int)$c['id'] ?>" title="<?= h($c['title']) ?>" style="text-decoration:none">
            <img src="<?= h(asset_url($c['thumb'])) ?>" alt="">
            <span style="position:absolute;left:0;right:0;bottom:0;background:linear-gradient(transparent,rgba(0,0,0,.65));color:#fff;font-size:.72rem;padding:.6rem .5rem .35rem;text-align:center;font-weight:600">
              <?= h($c['title']) ?>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($failed): ?>
    <div class="report-card" style="margin-bottom:1rem">
      <h2 style="color:var(--red)">Failed</h2>
      <table>
        <thead><tr><th>File</th><th>Reason</th></tr></thead>
        <tbody>
          <?php foreach ($failed as $f): ?>
            <tr><td><?= h($f['file']) ?></td><td><?= h($f['error']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>

<form class="form" method="post" enctype="multipart/form-data" id="importForm">
  <?= csrf_field() ?>

  <div class="row cols-2">
    <div class="field">
      <label for="base_title">Base title</label>
      <input type="text" name="base_title" id="base_title" maxlength="200"
             value="<?= h(trim((string)$baseTitleIn) ?: $defaultBase) ?>"
             placeholder="<?= h($defaultBase) ?>">
      <span class="hint">Each doll will be named "<span id="previewBase"><?= h(trim((string)$baseTitleIn) ?: $defaultBase) ?></span> #N".</span>
    </div>
    <div class="field">
      <label for="start_num">Starting number</label>
      <input type="number" name="start_num" id="start_num" min="1" step="1"
             value="<?= h((string)($startNumIn ?: $nextNum)) ?>">
      <span class="hint">Auto-detected from existing rows. Override if you want.</span>
    </div>
  </div>

  <div class="row cols-2">
    <div class="field">
      <label for="price">Price (USD) — applies to every doll</label>
      <div class="input-prefix">
        <span>$</span>
        <input type="text" inputmode="decimal" name="price" id="price" required
               pattern="\d+(\.\d{1,2})?"
               value="<?= h((string)$priceIn) ?>" placeholder="125.00">
      </div>
    </div>
    <div class="field">
      <label for="status">Status</label>
      <select name="status" id="status">
        <option value="draft"     <?= $statusIn==='draft'?'selected':'' ?>>Draft (review before publishing)</option>
        <option value="available" <?= $statusIn==='available'?'selected':'' ?>>Available (live on /shop immediately)</option>
      </select>
      <span class="hint">Draft is the safer default — preview them all first, then mark available.</span>
    </div>
  </div>

  <div class="row">
    <div class="field">
      <label for="images">Images</label>
      <div class="dropzone" id="dropzone">
        <p style="margin:0 0 .25rem;font-weight:500"><span id="dropMsg">Drop images here</span></p>
        <p style="margin:0;color:var(--ink-muted);font-size:.85rem">or</p>
        <input type="file" name="images[]" id="images" multiple accept="image/jpeg,image/png,image/webp,image/gif" style="margin-top:.75rem">
        <p class="hint" style="margin:1rem 0 0">Each image becomes one doll. JPEG/PNG/WebP/GIF, up to <?= h((string)round(((int)config('uploads.max_size'))/1024/1024)) ?>MB each.</p>
      </div>
      <div id="previewGrid" class="image-grid" style="margin-top:1rem"></div>
      <p class="hint" id="bigBatchHint" style="display:none;color:var(--amber)">
        Heads up: large batches may exceed your host's upload limit. If the import fails or stalls, split it into 10–20 at a time.
      </p>
    </div>
  </div>

  <div class="form-actions">
    <a href="/admin/products.php" class="btn btn-ghost">Cancel</a>
    <button type="submit" class="btn btn-primary" id="submitBtn">Import dolls</button>
  </div>
</form>

<script>
(function(){
  var input    = document.getElementById('images');
  var grid     = document.getElementById('previewGrid');
  var dropMsg  = document.getElementById('dropMsg');
  var dropzone = document.getElementById('dropzone');
  var btn      = document.getElementById('submitBtn');
  var bigHint  = document.getElementById('bigBatchHint');
  var baseEl   = document.getElementById('base_title');
  var previewBase = document.getElementById('previewBase');
  var startEl  = document.getElementById('start_num');

  baseEl.addEventListener('input', function(){
    previewBase.textContent = baseEl.value || 'Scrappy Doll';
  });

  function refreshPreview() {
    grid.innerHTML = '';
    var files = input.files;
    if (!files || files.length === 0) {
      dropMsg.textContent = 'Drop images here';
      btn.textContent = 'Import dolls';
      bigHint.style.display = 'none';
      return;
    }
    dropMsg.textContent = files.length + ' file' + (files.length === 1 ? '' : 's') + ' selected';
    btn.textContent = 'Import ' + files.length + ' ' + (files.length === 1 ? 'doll' : 'dolls');
    bigHint.style.display = files.length > 25 ? 'block' : 'none';

    var startNum = parseInt(startEl.value, 10) || 1;
    var base = baseEl.value || 'Scrappy Doll';

    Array.prototype.forEach.call(files, function(file, i) {
      var tile = document.createElement('div');
      tile.className = 'image-tile';
      var img = document.createElement('img');
      img.alt = '';
      img.src = URL.createObjectURL(file);
      img.onload = function(){ URL.revokeObjectURL(img.src); };
      tile.appendChild(img);

      var caption = document.createElement('span');
      caption.style.cssText = 'position:absolute;left:0;right:0;bottom:0;background:linear-gradient(transparent,rgba(0,0,0,.65));color:#fff;font-size:.7rem;padding:.5rem .4rem .3rem;text-align:center;font-weight:600;line-height:1.2';
      caption.textContent = base + ' #' + (startNum + i);
      tile.appendChild(caption);
      grid.appendChild(tile);
    });
  }

  input.addEventListener('change', refreshPreview);
  baseEl.addEventListener('input', refreshPreview);
  startEl.addEventListener('input', refreshPreview);

  // Drag and drop into the dropzone (just routes files into the input)
  ['dragenter','dragover'].forEach(function(ev){
    dropzone.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); dropzone.style.borderColor = 'var(--rose)'; });
  });
  ['dragleave','drop'].forEach(function(ev){
    dropzone.addEventListener(ev, function(e){ e.preventDefault(); e.stopPropagation(); dropzone.style.borderColor = ''; });
  });
  dropzone.addEventListener('drop', function(e){
    if (!e.dataTransfer || !e.dataTransfer.files) return;
    var dt = new DataTransfer();
    Array.prototype.forEach.call(e.dataTransfer.files, function(f){
      if (f.type && f.type.indexOf('image/') === 0) dt.items.add(f);
    });
    input.files = dt.files;
    refreshPreview();
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
