<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require_post();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_snipe') {
        $text = trim((string)($_POST['snipe_text'] ?? ''));
        $sub  = trim((string)($_POST['snipe_subtext'] ?? ''));
        $on   = !empty($_POST['snipe_on']) ? '1' : '0';

        if ($on === '1' && $text === '') {
            $errors[] = 'Type the offer before turning the banner on — e.g. "20% OFF EVERY DOLL".';
        }
        if (!$errors) {
            try {
                setting_set(SNIPE_ON,      $on);
                setting_set(SNIPE_TEXT,    mb_substr($text, 0, SNIPE_TEXT_MAX));
                setting_set(SNIPE_SUBTEXT, mb_substr($sub,  0, SNIPE_SUBTEXT_MAX));
                setting_set(SNIPE_PALETTE, snipe_palette_key((string)($_POST['snipe_palette'] ?? '')));
                setting_set(SNIPE_LINK,    snipe_clean_link((string)($_POST['snipe_link'] ?? '')));
            } catch (Throwable $e) {
                // Almost always the one cause: migration 010 hasn't been run.
                error_log('Saving banner settings failed: ' . $e->getMessage());
                $errors[] = 'Could not save the banner — the site_settings table is missing. '
                    . 'Run sql/migrations/010_add_site_settings.sql against the database.';
            }
            if (!$errors) {
                flash('success', $on === '1'
                    ? 'Banner saved — it\'s live on the home page now.'
                    : 'Banner saved and turned off.');
                redirect('/admin/coupons.php');
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE coupons SET active = 1 - active WHERE id = :id');
        $stmt->execute([':id' => $id]);
        flash('success', 'Coupon updated.');
        redirect('/admin/coupons.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM coupons WHERE id = :id');
        $stmt->execute([':id' => $id]);
        flash('success', 'Coupon deleted. (Past orders keep their recorded discount.)');
        redirect('/admin/coupons.php');
    }

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $code        = coupon_normalize((string)($_POST['code'] ?? ''));
        $note        = trim((string)($_POST['note'] ?? ''));
        $percent     = trim((string)($_POST['percent_off'] ?? ''));
        $amount      = trim((string)($_POST['amount_off'] ?? ''));
        $freeShip    = !empty($_POST['free_shipping']) ? 1 : 0;
        $minSubtotal = trim((string)($_POST['min_subtotal'] ?? ''));
        $maxUses     = trim((string)($_POST['max_uses'] ?? ''));
        $expires     = trim((string)($_POST['expires_at'] ?? ''));
        $active      = !empty($_POST['active']) ? 1 : 0;

        if (!preg_match('/^[A-Z0-9_-]{2,40}$/', $code)) {
            $errors[] = 'Code must be 2–40 letters, numbers, dashes, or underscores (e.g. SUMMER10).';
        }
        $percentOff = 0;
        if ($percent !== '') {
            if (!preg_match('/^\d{1,3}$/', $percent) || (int)$percent < 1 || (int)$percent > 100) {
                $errors[] = 'Percent off must be a whole number from 1 to 100.';
            } else {
                $percentOff = (int)$percent;
            }
        }
        $amountOffCents = 0;
        if ($amount !== '') {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
                $errors[] = 'Dollar amount off must look like 5 or 5.00.';
            } else {
                $amountOffCents = (int)round(((float)$amount) * 100);
            }
        }
        if ($percentOff === 0 && $amountOffCents === 0 && !$freeShip) {
            $errors[] = 'Give the code something to do: percent off, dollars off, and/or free shipping.';
        }
        $minSubtotalCents = 0;
        if ($minSubtotal !== '') {
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $minSubtotal)) {
                $errors[] = 'Minimum order must look like 50 or 50.00.';
            } else {
                $minSubtotalCents = (int)round(((float)$minSubtotal) * 100);
            }
        }
        $maxUsesVal = null;
        if ($maxUses !== '') {
            if (!preg_match('/^\d+$/', $maxUses) || (int)$maxUses < 1) {
                $errors[] = 'Max uses must be a whole number of 1 or more (or leave blank for unlimited).';
            } else {
                $maxUsesVal = (int)$maxUses;
            }
        }
        $expiresAt = null;
        if ($expires !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $expires);
            if (!$d) {
                $errors[] = 'Expiration date is not valid.';
            } else {
                // Valid through the end of the chosen day.
                $expiresAt = $d->format('Y-m-d') . ' 23:59:59';
            }
        }

        if (!$errors) {
            // Code must be unique (other than the row being edited).
            $dupe = db()->prepare('SELECT id FROM coupons WHERE code = :c' . ($id ? ' AND id <> :id' : ''));
            $dupe->execute($id ? [':c' => $code, ':id' => $id] : [':c' => $code]);
            if ($dupe->fetch()) $errors[] = "A coupon with code $code already exists.";
        }

        if (!$errors) {
            $params = [
                ':code' => $code,
                ':note' => $note !== '' ? $note : null,
                ':pct'  => $percentOff,
                ':amt'  => $amountOffCents,
                ':fs'   => $freeShip,
                ':min'  => $minSubtotalCents,
                ':max'  => $maxUsesVal,
                ':exp'  => $expiresAt,
                ':act'  => $active,
            ];
            if ($id) {
                $params[':id'] = $id;
                db()->prepare('
                    UPDATE coupons SET code=:code, note=:note, percent_off=:pct, amount_off_cents=:amt,
                        free_shipping=:fs, min_subtotal_cents=:min, max_uses=:max, expires_at=:exp, active=:act
                    WHERE id=:id
                ')->execute($params);
                flash('success', "Coupon $code updated.");
            } else {
                db()->prepare('
                    INSERT INTO coupons (code, note, percent_off, amount_off_cents, free_shipping,
                        min_subtotal_cents, max_uses, expires_at, active)
                    VALUES (:code, :note, :pct, :amt, :fs, :min, :max, :exp, :act)
                ')->execute($params);
                flash('success', "Coupon $code created. Share the code with whoever should use it.");
            }
            redirect('/admin/coupons.php');
        }
    }
}

// Edit mode: load the coupon being edited (form pre-fill).
$editId = (int)($_GET['id'] ?? 0);
$edit = null;
if ($editId) {
    $stmt = db()->prepare('SELECT * FROM coupons WHERE id = :id');
    $stmt->execute([':id' => $editId]);
    $edit = $stmt->fetch();
    if (!$edit) { flash('error', 'Coupon not found.'); redirect('/admin/coupons.php'); }
}

// On a failed POST, re-fill the form from what was submitted.
$f = function (string $key, string $default = '') use ($edit) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') return (string)($_POST[$key] ?? $default);
    return $default;
};
$showForm = $edit || isset($_GET['new'])
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'save_snipe');

$coupons = db()->query('SELECT * FROM coupons ORDER BY active DESC, created_at DESC')->fetchAll();

// Home-page offer banner. On a failed save, show back what was typed.
$snipe = snipe_settings();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_snipe') {
    $snipe = [
        'on'      => !empty($_POST['snipe_on']),
        'text'    => mb_substr(trim((string)($_POST['snipe_text'] ?? '')), 0, SNIPE_TEXT_MAX),
        'subtext' => mb_substr(trim((string)($_POST['snipe_subtext'] ?? '')), 0, SNIPE_SUBTEXT_MAX),
        'palette' => snipe_palette_key((string)($_POST['snipe_palette'] ?? '')),
        'link'    => snipe_clean_link((string)($_POST['snipe_link'] ?? '')),
    ];
}

$page = 'coupons';
$title = 'Coupons';
require __DIR__ . '/header.php';
?>

<div class="page-head">
  <h1 class="page-title">Coupons</h1>
  <?php if (!$showForm): ?>
    <a class="btn btn-primary" href="/admin/coupons.php?new=1">+ New coupon</a>
  <?php endif; ?>
</div>

<?php foreach ($errors as $e): ?>
  <div class="flash flash-error"><?= h($e) ?></div>
<?php endforeach; ?>

<!-- snipe-card:start — the home-page offer banner, edited here because it
     advertises the codes on this page. Preview mirrors the real thing. -->
<div class="card snipe-card" style="margin-bottom:1.5rem">
  <h3>Home page banner</h3>
  <p class="snipe-blurb">
    A bright diagonal banner that swipes across the top-right corner of the home page.
    Type the offer, pick a color, and turn it on — everyone landing on
    <a href="/" target="_blank" rel="noopener">scrappydolls.com</a> sees it.
  </p>

  <form method="post" id="snipe-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_snipe">

    <div class="snipe-editor">
      <div class="snipe-fields">
        <div class="field" style="margin-bottom:1rem">
          <label for="snipe_text">The offer — keep it short and loud</label>
          <input type="text" id="snipe_text" name="snipe_text" maxlength="<?= SNIPE_TEXT_MAX ?>"
                 placeholder="e.g. 20% OFF EVERY DOLL"
                 value="<?= h($snipe['text']) ?>">
          <span class="hint"><span class="snipe-count" data-for="snipe_text"></span> characters left</span>
        </div>

        <div class="field" style="margin-bottom:1rem">
          <label for="snipe_subtext">Second line — the code (optional)</label>
          <input type="text" id="snipe_subtext" name="snipe_subtext" maxlength="<?= SNIPE_SUBTEXT_MAX ?>"
                 placeholder="e.g. CODE SUMMER10"
                 value="<?= h($snipe['subtext']) ?>">
          <span class="hint"><span class="snipe-count" data-for="snipe_subtext"></span> characters left</span>
        </div>

        <div class="field" style="margin-bottom:1rem">
          <label>Color</label>
          <div class="snipe-swatches">
            <?php foreach (snipe_palettes() as $key => $p): ?>
              <label class="snipe-swatch" title="<?= h($p['label']) ?>">
                <input type="radio" name="snipe_palette" value="<?= h($key) ?>"
                       <?= $snipe['palette'] === $key ? 'checked' : '' ?>>
                <span style="background:linear-gradient(135deg,<?= h($p['from']) ?>,<?= h($p['to']) ?>)"></span>
                <em><?= h($p['label']) ?></em>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field" style="margin-bottom:1rem">
          <label for="snipe_link">Where it goes when clicked</label>
          <input type="text" id="snipe_link" name="snipe_link" maxlength="255"
                 placeholder="/shop/" value="<?= h($snipe['link']) ?>">
          <span class="hint">Leave blank and the banner is just a sign — nothing to click.</span>
        </div>

        <div class="field" style="margin-bottom:1.25rem">
          <label style="display:flex;align-items:center;gap:.5rem;text-transform:none;letter-spacing:0;font-size:.95rem">
            <input type="checkbox" id="snipe_on" name="snipe_on" value="1" <?= $snipe['on'] ? 'checked' : '' ?>>
            Show it on the home page
          </label>
        </div>

        <button class="btn btn-primary" type="submit">Save banner</button>
      </div>

      <div class="snipe-preview-wrap">
        <p class="snipe-preview-label">Preview</p>
        <div class="snipe-preview" id="snipe-preview" aria-hidden="true">
          <div class="pv-header"><span class="pv-brand">scrappy<em>dolls</em></span><span class="pv-nav"></span></div>
          <div class="pv-body"><span class="pv-line pv-line-1"></span><span class="pv-line pv-line-2"></span></div>
          <div class="pv-band" id="snipe-band">
            <span class="pv-band-line"></span>
            <span class="pv-band-sub"></span>
          </div>
        </div>
        <p class="hint" style="margin-top:.6rem">Long offers shrink to fit the corner.</p>
      </div>
    </div>
  </form>
</div>

<script>
(function () {
  var palettes = <?= json_encode(snipe_palettes(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var form = document.getElementById('snipe-form');
  if (!form) return;
  var text = document.getElementById('snipe_text');
  var sub  = document.getElementById('snipe_subtext');
  var on   = document.getElementById('snipe_on');
  var band = document.getElementById('snipe-band');
  var bandLine = band.querySelector('.pv-band-line');
  var bandSub  = band.querySelector('.pv-band-sub');
  var preview  = document.getElementById('snipe-preview');

  function paint() {
    var t = text.value.trim() || text.placeholder.replace(/^e\.g\. /, '');
    var s = sub.value.trim();
    var key = (form.querySelector('input[name=snipe_palette]:checked') || {}).value;
    var p = palettes[key] || palettes.cherry;

    bandLine.textContent = t;
    bandSub.textContent = s;
    bandSub.style.display = s ? '' : 'none';

    band.style.setProperty('--pv-from', p.from);
    band.style.setProperty('--pv-to', p.to);
    band.style.color = p.ink;

    // Same length-based step-down the live page uses.
    var len = Math.max(t.length, Math.round(s.length * 0.8));
    band.classList.toggle('pv-med',  len > 16 && len <= 22);
    band.classList.toggle('pv-long', len > 22);

    preview.classList.toggle('is-off', !on.checked);

    form.querySelectorAll('.snipe-count').forEach(function (el) {
      var input = document.getElementById(el.dataset.for);
      el.textContent = String(input.maxLength - input.value.length);
    });
  }

  form.addEventListener('input', paint);
  form.addEventListener('change', paint);
  paint();
})();
</script>
<!-- snipe-card:end -->

<?php if ($showForm): ?>
  <div class="card" style="margin-bottom:1.5rem">
    <h3><?= $edit ? 'Edit coupon' : 'New coupon' ?></h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">

      <div class="field" style="margin-bottom:1rem">
        <label>Code — what the customer types at checkout</label>
        <input type="text" name="code" required maxlength="40" style="text-transform:uppercase"
               placeholder="e.g. SUMMER10 or INHAND"
               value="<?= h($f('code', $edit ? $edit['code'] : '')) ?>">
      </div>

      <div class="field" style="margin-bottom:1rem">
        <label>Note (just for you — customers never see this)</label>
        <input type="text" name="note" maxlength="255" placeholder="e.g. For my sister's visit, June 2026"
               value="<?= h($f('note', $edit ? (string)$edit['note'] : '')) ?>">
      </div>

      <p style="margin:.25rem 0 .5rem;font-weight:600;font-size:.9rem">What does it do? (pick one or combine)</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:1rem;margin-bottom:1rem">
        <div class="field">
          <label>Percent off items</label>
          <input type="text" inputmode="numeric" name="percent_off" placeholder="e.g. 10"
                 value="<?= h($f('percent_off', $edit && (int)$edit['percent_off'] > 0 ? (string)(int)$edit['percent_off'] : '')) ?>">
        </div>
        <div class="field">
          <label>Dollars off items ($)</label>
          <input type="text" inputmode="decimal" name="amount_off" placeholder="e.g. 5.00"
                 value="<?= h($f('amount_off', $edit && (int)$edit['amount_off_cents'] > 0 ? number_format($edit['amount_off_cents']/100, 2, '.', '') : '')) ?>">
        </div>
        <div class="field">
          <label style="display:flex;align-items:center;gap:.5rem;margin-top:1.4rem">
            <input type="checkbox" name="free_shipping" value="1"
                   <?= ($_SERVER['REQUEST_METHOD'] === 'POST' ? !empty($_POST['free_shipping']) : ($edit && !empty($edit['free_shipping']))) ? 'checked' : '' ?>>
            Free shipping
          </label>
        </div>
      </div>

      <p style="margin:.25rem 0 .5rem;font-weight:600;font-size:.9rem">Limits (all optional)</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:1rem;margin-bottom:1rem">
        <div class="field">
          <label>Max number of uses</label>
          <input type="text" inputmode="numeric" name="max_uses" placeholder="blank = unlimited"
                 value="<?= h($f('max_uses', $edit && $edit['max_uses'] !== null ? (string)(int)$edit['max_uses'] : '')) ?>">
        </div>
        <div class="field">
          <label>Expires on (valid through that day)</label>
          <input type="date" name="expires_at"
                 value="<?= h($f('expires_at', $edit && $edit['expires_at'] ? substr((string)$edit['expires_at'], 0, 10) : '')) ?>">
        </div>
        <div class="field">
          <label>Minimum order ($, before shipping)</label>
          <input type="text" inputmode="decimal" name="min_subtotal" placeholder="blank = none"
                 value="<?= h($f('min_subtotal', $edit && (int)$edit['min_subtotal_cents'] > 0 ? number_format($edit['min_subtotal_cents']/100, 2, '.', '') : '')) ?>">
        </div>
      </div>

      <div class="field" style="margin-bottom:1.25rem">
        <label style="display:flex;align-items:center;gap:.5rem">
          <input type="checkbox" name="active" value="1"
                 <?= ($_SERVER['REQUEST_METHOD'] === 'POST' ? !empty($_POST['active']) : (!$edit || !empty($edit['active']))) ? 'checked' : '' ?>>
          Active — customers can use it right away
        </label>
      </div>

      <div style="display:flex;gap:.5rem">
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Create coupon' ?></button>
        <a class="btn btn-ghost" href="/admin/coupons.php">Cancel</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php if (!$coupons): ?>
  <?php if (!$showForm): ?>
    <div class="empty">
      <h3>No coupons yet</h3>
      <p>Create a code to give someone a discount or waive their shipping —<br>
         for example a one-use <strong>INHAND</strong> code with free shipping for a local pickup.</p>
      <p style="margin-top:1rem"><a class="btn btn-primary" href="/admin/coupons.php?new=1">+ New coupon</a></p>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Code</th>
          <th>Discount</th>
          <th>Uses</th>
          <th>Expires</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($coupons as $c):
          $expired = !empty($c['expires_at']) && strtotime((string)$c['expires_at']) < time();
          $usedUp  = $c['max_uses'] !== null && (int)$c['used_count'] >= (int)$c['max_uses'];
        ?>
          <tr>
            <td>
              <strong style="font-family:monospace;font-size:1rem"><?= h($c['code']) ?></strong>
              <?php if (!empty($c['note'])): ?>
                <br><span style="font-size:.8rem;color:var(--ink-muted)"><?= h($c['note']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?= h(coupon_summary($c)) ?>
              <?php if ((int)$c['min_subtotal_cents'] > 0): ?>
                <br><span style="font-size:.8rem;color:var(--ink-muted)">min order <?= fmt_price((int)$c['min_subtotal_cents']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?= (int)$c['used_count'] ?><?= $c['max_uses'] !== null ? ' / ' . (int)$c['max_uses'] : '' ?>
              <?php if ($usedUp): ?><br><span style="font-size:.8rem;color:var(--ink-muted)">used up</span><?php endif; ?>
            </td>
            <td style="color:var(--ink-muted);font-size:.85rem">
              <?php if (!empty($c['expires_at'])): ?>
                <?= h(date('M j, Y', strtotime((string)$c['expires_at']))) ?>
                <?php if ($expired): ?><br><span style="font-size:.8rem">expired</span><?php endif; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge-<?= !empty($c['active']) && !$expired && !$usedUp ? 'available' : 'sold' ?>">
                <?= !empty($c['active']) ? ($expired ? 'expired' : ($usedUp ? 'used up' : 'active')) : 'off' ?>
              </span>
            </td>
            <td class="actions">
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit"><?= !empty($c['active']) ? 'Turn off' : 'Turn on' ?></button>
              </form>
              <a class="btn btn-sm btn-primary" href="/admin/coupons.php?id=<?= (int)$c['id'] ?>">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete coupon <?= h($c['code']) ?>? Customers will no longer be able to use it.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p style="margin-top:1rem;font-size:.85rem;color:var(--ink-muted)">
    Customers enter the code in the “Have a coupon code?” box on the cart page.
    A use is only counted when an order actually completes.
  </p>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
