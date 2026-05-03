<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();
csrf_require_post();

$returnUrl = (string)($_POST['return_url'] ?? '/admin/products.php');
// Sanity-clamp return URL to admin space — never trust user input as a redirect target
if (!preg_match('#^/admin/#', $returnUrl)) {
    $returnUrl = '/admin/products.php';
}

$ids      = $_POST['ids']     ?? [];
$titles   = $_POST['title']   ?? [];
$slugs    = $_POST['slug']    ?? [];
$prices   = $_POST['price']   ?? [];
$statuses = $_POST['status']  ?? [];
$featured = $_POST['featured'] ?? []; // checkboxes — only present rows that are checked

if (!is_array($ids) || !$ids) {
    flash('info', 'No rows to save.');
    redirect($returnUrl);
}

$pdo = db();

// Pre-fetch current rows for change detection + sold_at logic
$idList = array_values(array_filter(array_map('intval', $ids)));
if (!$idList) {
    redirect($returnUrl);
}
$placeholders = implode(',', array_fill(0, count($idList), '?'));
$sel = $pdo->prepare("SELECT id, slug, title, price_cents, status, featured FROM products WHERE id IN ($placeholders)");
$sel->execute($idList);
$current = [];
foreach ($sel->fetchAll() as $row) $current[(int)$row['id']] = $row;

$errors  = [];
$changed = 0;

$pdo->beginTransaction();
try {
    foreach ($idList as $id) {
        $row = $current[$id] ?? null;
        if (!$row) continue;

        $newTitle  = isset($titles[$id])   ? trim((string)$titles[$id])   : $row['title'];
        $newPriceS = isset($prices[$id])   ? trim((string)$prices[$id])   : null;
        $newStatus = isset($statuses[$id]) ? (string)$statuses[$id]       : $row['status'];
        $newSlugIn = isset($slugs[$id])    ? trim((string)$slugs[$id])    : $row['slug'];

        if ($newTitle === '') { $errors[] = "Row #$id: title can't be empty."; continue; }
        if ($newPriceS !== null && !preg_match('/^\d+(\.\d{1,2})?$/', $newPriceS)) {
            $errors[] = "Row #$id ({$row['title']}): price must look like 125 or 125.00.";
            continue;
        }
        if (!in_array($newStatus, ['draft','available','sold'], true)) {
            $errors[] = "Row #$id: invalid status.";
            continue;
        }

        $newPriceC = $newPriceS !== null
            ? (int)round(((float)$newPriceS) * 100)
            : (int)$row['price_cents'];

        // Slug: blank → regenerate from title; otherwise normalize
        $newSlug = $newSlugIn !== '' ? slugify($newSlugIn) : slugify($newTitle);
        if ($newSlug !== $row['slug']) {
            $newSlug = unique_slug($newSlug, $id);
        }

        $newFeatured = !empty($featured[$id]) ? 1 : 0;

        // Detect any actual change
        $diff = ($newTitle !== $row['title'])
             || ($newSlug  !== $row['slug'])
             || ($newPriceC !== (int)$row['price_cents'])
             || ($newStatus !== $row['status'])
             || ($newFeatured !== (int)$row['featured']);
        if (!$diff) continue;

        $statusToSold = ($newStatus === 'sold' && $row['status'] !== 'sold');

        $sql = 'UPDATE products
                SET title = :t, slug = :slug, price_cents = :p, status = :s, featured = :f'
             . ($statusToSold ? ', sold_at = NOW()' : '')
             . ' WHERE id = :id';
        $upd = $pdo->prepare($sql);
        $upd->execute([
            ':t'    => $newTitle,
            ':slug' => $newSlug,
            ':p'    => $newPriceC,
            ':s'    => $newStatus,
            ':f'    => $newFeatured,
            ':id'   => $id,
        ]);
        $changed++;
    }

    if ($errors) {
        $pdo->rollBack();
        flash('error', implode(' ', $errors));
        redirect($returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'mode=edit');
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('bulk-edit: ' . $e->getMessage());
    flash('error', 'Could not save changes: ' . $e->getMessage());
    redirect($returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'mode=edit');
}

if ($changed === 0) {
    flash('info', 'No changes to save.');
} else {
    flash('success', "Saved $changed " . ($changed === 1 ? 'change' : 'changes') . '.');
}

redirect($returnUrl);
