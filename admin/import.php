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

$isAjax = !empty($_POST['ajax']) && $_POST['ajax'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Session expired — please try again.';
        if ($isAjax) {
            json_response(['created' => [], 'failed' => [], 'errors' => $errors], 400);
        }
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

        if ($isAjax) {
            $payload = [
                'created' => array_map(function ($c) {
                    return [
                        'id'        => $c['id'],
                        'title'     => $c['title'],
                        'thumb_url' => thumb_url($c['thumb']),
                    ];
                }, $created),
                'failed'  => $failed,
                'errors'  => $errors,
            ];
            json_response($payload);
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
            <img src="<?= h(thumb_url($c['thumb'])) ?>" alt="">
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

  <div id="uploadStatus" style="display:none;margin-top:1rem">
    <div class="flash flash-info" style="margin:0">
      <strong id="uploadStatusLabel">Optimizing photos…</strong>
      <span id="uploadStatusDetail"></span>
      <div style="background:rgba(0,0,0,.08);border-radius:999px;height:.5rem;margin-top:.6rem;overflow:hidden">
        <div id="uploadStatusBar" style="background:var(--rose);height:100%;width:0;transition:width .15s ease"></div>
      </div>
    </div>
  </div>
</form>

<script>
(function(){
  var form        = document.getElementById('importForm');
  var input       = document.getElementById('images');
  var grid        = document.getElementById('previewGrid');
  var dropMsg     = document.getElementById('dropMsg');
  var dropzone    = document.getElementById('dropzone');
  var btn         = document.getElementById('submitBtn');
  var bigHint     = document.getElementById('bigBatchHint');
  var baseEl      = document.getElementById('base_title');
  var previewBase = document.getElementById('previewBase');
  var startEl     = document.getElementById('start_num');
  var statusBox   = document.getElementById('uploadStatus');
  var statusLabel = document.getElementById('uploadStatusLabel');
  var statusDetail= document.getElementById('uploadStatusDetail');
  var statusBar   = document.getElementById('uploadStatusBar');

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
    bigHint.style.display = 'none'; // client-side resize obviates this

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

  /* ---------------------------------------------------------------- */
  /* Client-side resize + chunked upload                              */
  /* ---------------------------------------------------------------- */

  var MAX_EDGE = 1800;       // browser-side downscale (server still resizes again)
  var QUALITY  = 0.9;        // JPEG quality 0–1
  var CHUNK    = 8;          // files per request — small enough for any host

  function setStatus(label, detail, pct) {
    statusBox.style.display = 'block';
    if (label !== null)  statusLabel.textContent  = label;
    if (detail !== null) statusDetail.textContent = detail ? ' — ' + detail : '';
    if (pct !== null)    statusBar.style.width    = Math.round(pct) + '%';
  }

  async function loadAsBitmap(file) {
    // createImageBitmap with EXIF orientation works on modern browsers
    if (window.createImageBitmap) {
      try {
        return await createImageBitmap(file, { imageOrientation: 'from-image' });
      } catch (e) { /* fall through */ }
    }
    // Fallback: <img> tag (modern browsers also auto-rotate from EXIF)
    return new Promise(function(resolve, reject){
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function(){
        URL.revokeObjectURL(url);
        resolve(img);
      };
      img.onerror = function(e){ URL.revokeObjectURL(url); reject(e); };
      img.src = url;
    });
  }

  async function resizeFile(file) {
    var src = await loadAsBitmap(file);
    var w = src.width, h = src.height;
    if (Math.max(w, h) > MAX_EDGE) {
      if (w >= h) { h = Math.round(h * (MAX_EDGE / w)); w = MAX_EDGE; }
      else        { w = Math.round(w * (MAX_EDGE / h)); h = MAX_EDGE; }
    }
    var canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h;
    var ctx = canvas.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(src, 0, 0, w, h);
    if (src.close) src.close();
    return await new Promise(function(resolve, reject){
      canvas.toBlob(function(b){ b ? resolve(b) : reject(new Error('toBlob failed')); },
                    'image/jpeg', QUALITY);
    });
  }

  function readField(name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el ? el.value : '';
  }

  async function postChunk(blobs, names, startNumOverride) {
    var fd = new FormData();
    fd.append('csrf_token',  readField('csrf_token'));
    fd.append('base_title',  readField('base_title'));
    fd.append('price',       readField('price'));
    fd.append('status',      readField('status'));
    fd.append('start_num',   String(startNumOverride));
    fd.append('ajax', '1');
    blobs.forEach(function(b, i){
      var name = (names[i] || ('image-' + i)).replace(/\.[^.]+$/, '') + '.jpg';
      fd.append('images[]', b, name);
    });
    var resp = await fetch(window.location.pathname, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
    });
    if (!resp.ok) throw new Error('Server error ' + resp.status + ' ' + resp.statusText);
    return await resp.json();
  }

  form.addEventListener('submit', async function(e){
    if (!input.files || input.files.length === 0) return; // no files -> default behavior
    e.preventDefault();
    btn.disabled = true;

    var files = Array.prototype.slice.call(input.files);
    var totalSteps = files.length * 2; // resize + upload phases per file
    var doneSteps = 0;

    // Phase 1: resize all in browser
    setStatus('Optimizing photos…', '0 of ' + files.length, 0);
    var resized = [];
    var origNames = [];
    for (var i = 0; i < files.length; i++) {
      try {
        var blob = await resizeFile(files[i]);
        resized.push(blob);
        origNames.push(files[i].name || ('image-' + i));
      } catch (err) {
        console.warn('Resize failed; sending original for', files[i].name, err);
        resized.push(files[i]);
        origNames.push(files[i].name || ('image-' + i));
      }
      doneSteps++;
      setStatus(null, (i + 1) + ' of ' + files.length, (doneSteps / totalSteps) * 100);
    }

    // Phase 2: upload in small chunks so any single request stays well under host limits
    var startNum = parseInt(startEl.value, 10) || 1;
    var allCreated = [];
    var allFailed = [];
    var sentSoFar = 0;
    setStatus('Uploading…', '0 of ' + files.length, (doneSteps / totalSteps) * 100);

    for (var c = 0; c < resized.length; c += CHUNK) {
      var slice = resized.slice(c, c + CHUNK);
      var nameSlice = origNames.slice(c, c + CHUNK);
      try {
        var res = await postChunk(slice, nameSlice, startNum + sentSoFar);
        if (res.created) allCreated = allCreated.concat(res.created);
        if (res.failed)  allFailed  = allFailed.concat(res.failed);
        sentSoFar += slice.length - ((res.failed || []).length);
      } catch (err) {
        for (var k = 0; k < slice.length; k++) {
          allFailed.push({ file: nameSlice[k], error: err.message || 'Upload failed' });
        }
      }
      doneSteps += slice.length;
      setStatus(null, Math.min(c + slice.length, files.length) + ' of ' + files.length, (doneSteps / totalSteps) * 100);
    }

    setStatus('Done', allCreated.length + ' created, ' + allFailed.length + ' failed', 100);

    // Render result inline (no full-page reload — the server JSON has all we need)
    renderResult(allCreated, allFailed);
    btn.disabled = false;
    btn.textContent = 'Import dolls';
  });

  function renderResult(created, failed) {
    // Replace the form area with the result
    var anchor = form;
    var html = '<div class="flash flash-success" style="margin-bottom:1rem">'
             + '<strong>Done.</strong> Created ' + created.length + ' '
             + (created.length === 1 ? 'doll' : 'dolls')
             + (failed.length ? ' · ' + failed.length + ' failed (see below)' : '') + '. '
             + '<a href="/admin/products.php" style="margin-left:.5rem">View all dolls →</a>'
             + '</div>';

    if (created.length) {
      html += '<div class="report-card" style="margin-bottom:1rem"><h2>Created</h2>'
            + '<div class="image-grid" style="grid-template-columns:repeat(auto-fill,minmax(7rem,1fr))">';
      created.forEach(function(c){
        html += '<a class="image-tile" href="/admin/edit.php?id=' + c.id + '" style="text-decoration:none">'
              + '<img src="' + c.thumb_url + '" alt="">'
              + '<span style="position:absolute;left:0;right:0;bottom:0;background:linear-gradient(transparent,rgba(0,0,0,.65));color:#fff;font-size:.72rem;padding:.6rem .5rem .35rem;text-align:center;font-weight:600">'
              + escapeHtml(c.title) + '</span></a>';
      });
      html += '</div></div>';
    }

    if (failed.length) {
      html += '<div class="report-card" style="margin-bottom:1rem">'
            + '<h2 style="color:var(--red)">Failed</h2><table>'
            + '<thead><tr><th>File</th><th>Reason</th></tr></thead><tbody>';
      failed.forEach(function(f){
        html += '<tr><td>' + escapeHtml(f.file) + '</td><td>' + escapeHtml(f.error || '') + '</td></tr>';
      });
      html += '</tbody></table></div>';
    }

    var wrap = document.createElement('div');
    wrap.innerHTML = html;
    anchor.parentNode.insertBefore(wrap, anchor);
    anchor.style.display = 'none';
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function(c){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
    });
  }
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
