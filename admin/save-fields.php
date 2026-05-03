<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}
if (!csrf_check()) {
    json_response(['error' => 'Session expired — please reload the page.'], 403);
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) json_response(['error' => 'Missing id'], 400);

$title       = trim((string)($_POST['title'] ?? ''));
$slugRaw     = trim((string)($_POST['slug'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$price       = (string)($_POST['price'] ?? '');
$status      = (string)($_POST['status'] ?? 'draft');
$featured    = !empty($_POST['featured']) ? 1 : 0;

$errors = [];
if ($title === '') $errors[] = 'Title is required.';
if (!preg_match('/^\d+(\.\d{1,2})?$/', $price)) $errors[] = 'Price must look like 125 or 125.00.';
if (!in_array($status, ['draft', 'available', 'sold'], true)) $status = 'draft';
if ($errors) json_response(['error' => implode(' ', $errors)], 422);

$priceCents = (int)round(((float)$price) * 100);
$slug = $slugRaw !== '' ? slugify($slugRaw) : slugify($title);
$slug = unique_slug($slug, $id);

try {
    $up = db()->prepare('UPDATE products SET slug=:slug,title=:t,description=:d,price_cents=:p,status=:s,featured=:f WHERE id=:id');
    $up->execute([
        ':slug' => $slug, ':t' => $title, ':d' => $description,
        ':p' => $priceCents, ':s' => $status, ':f' => $featured, ':id' => $id,
    ]);
} catch (Throwable $e) {
    error_log('save-fields error: ' . $e->getMessage());
    json_response(['error' => 'Could not save changes.'], 500);
}

flash('success', 'Doll updated.');
json_response(['ok' => true, 'slug' => $slug, 'redirect' => '/admin/products.php']);
