<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $action = $_POST['action'] ?? '';

    // Revert one field (or one image) to the copy the site shipped with.
    if ($action === 'reset') {
        $key = (string)($_POST['key'] ?? '');
        if (isset(home_fields()[$key]) || home_is_list_key($key)) {
            if ((home_fields()[$key]['type'] ?? '') === 'image') {
                home_delete_uploaded_image(home_raw($key));
                setting_delete(home_setting_key($key . '_alt'));
            }
            setting_delete(home_setting_key($key));
            flash('success', 'Put back the way it was.');
        }
        $back = urlencode((string)($_POST['section'] ?? ''));
            redirect('/admin/home.php?section=' . $back . '#' . $back);
    }

    if ($action === 'save') {
        $section = (string)($_POST['section'] ?? '');
        $schema  = home_schema();
        if (!isset($schema[$section])) {
            flash('error', 'Unknown section.');
            redirect('/admin/home.php');
        }
        $def = $schema[$section];

        try {
            foreach ($def['fields'] as $key => $field) {
                if ($field['type'] === 'image') {
                    // Alt text saves whether or not a new file came with it.
                    if (array_key_exists('alt__' . $key, $_POST)) {
                        setting_set(home_setting_key($key . '_alt'), trim((string)$_POST['alt__' . $key]));
                    }
                    $file = $_FILES['file__' . $key] ?? null;
                    if (is_array($file)) {
                        $res = home_handle_image_upload($key, $file);
                        if ($res['error']) {
                            $errors[] = $res['error'];
                        } elseif ($res['filename'] !== null) {
                            $old = home_raw($key);
                            setting_set(home_setting_key($key), $res['filename']);
                            // Only ever removes a previous upload, never a shipped file.
                            if ($old !== $res['filename']) home_delete_uploaded_image($old);
                        }
                    }
                    continue;
                }
                if (array_key_exists($key, $_POST)) {
                    $val = (string)$_POST[$key];
                    // Normalise line endings so the paragraph split is predictable.
                    $val = str_replace("\r\n", "\n", $val);
                    setting_set(home_setting_key($key), trim($val));
                }
            }

            // Repeating rows: re-index, drop blank ones, store as JSON.
            if (!empty($def['list'])) {
                $list = $def['list'];
                $rows = $_POST['items'] ?? [];
                $clean = [];
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        if (!is_array($row)) continue;
                        $item = [];
                        $any = false;
                        foreach ($list['item_fields'] as $fk => $_) {
                            $v = trim(str_replace("\r\n", "\n", (string)($row[$fk] ?? '')));
                            $item[$fk] = $v;
                            if ($v !== '') $any = true;
                        }
                        if ($any) $clean[] = $item;
                    }
                }
                setting_set(home_setting_key($list['key']), json_encode(array_values($clean), JSON_UNESCAPED_UNICODE));
            }
        } catch (Throwable $e) {
            error_log('Saving home content failed: ' . $e->getMessage());
            $errors[] = 'Could not save — the site_settings table is missing. '
                . 'Run sql/migrations/010_add_site_settings.sql against the database.';
        }

        if (!$errors) {
            flash('success', 'Saved. Take a look at the ' . strtolower($def['label']) . ' on the home page.');
            redirect('/admin/home.php?section=' . urlencode($section) . '#' . urlencode($section));
        }
    }
}

$open = (string)($_GET['section'] ?? '');
$page = 'home';
$title = 'Home page';
require __DIR__ . '/header.php';
?>

<div class="page-head">
  <h1 class="page-title">Home page</h1>
  <a class="btn btn-ghost" href="/" target="_blank" rel="noopener">View the page →</a>
</div>

<?php foreach ($errors as $e): ?>
  <div class="flash flash-error"><?= h($e) ?></div>
<?php endforeach; ?>

<p class="home-intro">
  Everything on the home page lives here. Each block saves on its own, so you can change one
  thing and leave the rest alone. Anything you haven't touched shows the wording the site
  launched with — <strong>Put back the original</strong> returns a field to it.
</p>

<?php foreach (home_schema() as $sectionKey => $section): ?>
  <details class="card home-section" id="<?= h($sectionKey) ?>" <?= $open === $sectionKey ? 'open' : '' ?>>
    <summary>
      <span class="home-section-name"><?= h($section['label']) ?></span>
      <span class="home-section-hint"><?= h($section['blurb']) ?></span>
    </summary>

    <form method="post" enctype="multipart/form-data" class="home-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="section" value="<?= h($sectionKey) ?>">

      <?php foreach ($section['fields'] as $key => $field): ?>
        <?php $custom = home_is_customized($key); ?>
        <div class="field home-field">
          <label for="f_<?= h($key) ?>">
            <?= h($field['label']) ?>
            <?php if ($custom): ?><span class="home-changed">changed</span><?php endif; ?>
          </label>

          <?php if ($field['type'] === 'image'): ?>
            <div class="home-image">
              <img src="<?= h(home_image_url($key)) ?>" alt="" loading="lazy">
              <div class="home-image-controls">
                <input type="file" name="file__<?= h($key) ?>" accept="image/*" id="f_<?= h($key) ?>">
                <p class="hint">
                  Any size photo is fine — it's shrunk to fit the web automatically
                  (long edge <?= IMAGE_DISPLAY_LONG_EDGE ?>px, saved as a JPEG).
                </p>
                <label class="home-alt">
                  <span>Description for screen readers &amp; search engines</span>
                  <input type="text" name="alt__<?= h($key) ?>" maxlength="300"
                         value="<?= h(html_entity_decode(home_image_alt($key), ENT_QUOTES, 'UTF-8')) ?>">
                </label>
              </div>
            </div>

          <?php elseif ($field['type'] === 'rich' || $field['type'] === 'textarea'): ?>
            <textarea id="f_<?= h($key) ?>" name="<?= h($key) ?>" rows="<?= $field['type'] === 'rich' ? 5 : 3 ?>"><?= h(home_raw($key)) ?></textarea>

          <?php elseif ($field['type'] === 'headline'): ?>
            <textarea id="f_<?= h($key) ?>" name="<?= h($key) ?>" rows="2" class="home-headline-input"><?= h(home_raw($key)) ?></textarea>

          <?php else: ?>
            <input type="text" id="f_<?= h($key) ?>" name="<?= h($key) ?>" value="<?= h(home_raw($key)) ?>">
          <?php endif; ?>

          <?php if (!empty($field['hint'])): ?>
            <span class="hint"><?= h($field['hint']) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if (!empty($section['list'])): $list = $section['list']; $rows = home_list($sectionKey); ?>
        <div class="home-list" data-noun="<?= h($list['noun']) ?>">
          <p class="home-list-label"><?= h($list['label']) ?></p>
          <div class="home-rows" id="rows-<?= h($sectionKey) ?>">
            <?php foreach ($rows as $i => $row): ?>
              <div class="home-row">
                <div class="home-row-head">
                  <span class="home-row-n"><?= $i + 1 ?></span>
                  <div class="home-row-moves">
                    <button type="button" class="btn btn-sm btn-ghost" data-move="up" title="Move up">↑</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-move="down" title="Move down">↓</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-remove title="Remove">Remove</button>
                  </div>
                </div>
                <?php foreach ($list['item_fields'] as $fk => $fdef): ?>
                  <div class="field">
                    <label><?= h($fdef['label']) ?></label>
                    <?php if ($fdef['type'] === 'rich'): ?>
                      <textarea name="items[<?= $i ?>][<?= h($fk) ?>]" rows="3"><?= h($row[$fk] ?? '') ?></textarea>
                    <?php else: ?>
                      <input type="text" name="items[<?= $i ?>][<?= h($fk) ?>]" value="<?= h($row[$fk] ?? '') ?>">
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" data-add-row="rows-<?= h($sectionKey) ?>">
            + Add another <?= h($list['noun']) ?>
          </button>
        </div>
      <?php endif; ?>

      <div class="home-actions">
        <button class="btn btn-primary" type="submit">Save this section</button>
      </div>
    </form>

    <?php
      $resettable = [];
      foreach ($section['fields'] as $key => $field) if (home_is_customized($key)) $resettable[$key] = $field['label'];
      if (!empty($section['list']) && home_is_customized($section['list']['key'])) {
          $resettable[$section['list']['key']] = $section['list']['label'];
      }
    ?>
    <?php if ($resettable): ?>
      <div class="home-resets">
        <span class="hint">Put back the original:</span>
        <?php foreach ($resettable as $key => $label): ?>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="key" value="<?= h($key) ?>">
            <input type="hidden" name="section" value="<?= h($sectionKey) ?>">
            <button class="btn btn-sm btn-ghost" type="submit"
                    onclick="return confirm('Put &quot;<?= h($label) ?>&quot; back to the original wording?')">
              <?= h($label) ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </details>
<?php endforeach; ?>

<script>
(function () {
  // Add / remove / reorder rows, renumbering the names so PHP sees a clean list.
  function renumber(container) {
    container.querySelectorAll('.home-row').forEach(function (row, i) {
      row.querySelector('.home-row-n').textContent = String(i + 1);
      row.querySelectorAll('[name^="items["]').forEach(function (input) {
        input.name = input.name.replace(/^items\[\d+\]/, 'items[' + i + ']');
      });
    });
  }

  document.addEventListener('click', function (e) {
    var addBtn = e.target.closest('[data-add-row]');
    if (addBtn) {
      var container = document.getElementById(addBtn.dataset.addRow);
      var last = container.querySelector('.home-row:last-child');
      if (!last) return;
      var copy = last.cloneNode(true);
      copy.querySelectorAll('input[type=text], textarea').forEach(function (i) { i.value = ''; });
      container.appendChild(copy);
      renumber(container);
      copy.querySelector('input, textarea').focus();
      return;
    }

    var rm = e.target.closest('[data-remove]');
    if (rm) {
      var row = rm.closest('.home-row');
      var box = row.parentElement;
      if (box.querySelectorAll('.home-row').length === 1) {
        row.querySelectorAll('input[type=text], textarea').forEach(function (i) { i.value = ''; });
      } else {
        row.remove();
      }
      renumber(box);
      return;
    }

    var mv = e.target.closest('[data-move]');
    if (mv) {
      var r = mv.closest('.home-row');
      var c = r.parentElement;
      if (mv.dataset.move === 'up' && r.previousElementSibling) {
        c.insertBefore(r, r.previousElementSibling);
      } else if (mv.dataset.move === 'down' && r.nextElementSibling) {
        c.insertBefore(r.nextElementSibling, r);
      }
      renumber(c);
    }
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
