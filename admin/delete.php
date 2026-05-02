<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();
csrf_require_post();

$id = (int)($_POST['id'] ?? 0);
if (!$id) redirect('/admin/products.php');

$stmt = db()->prepare('SELECT id FROM products WHERE id = :id');
$stmt->execute([':id' => $id]);
if (!$stmt->fetch()) {
    flash('error', 'Doll not found.');
    redirect('/admin/products.php');
}

$hasOrders = db()->prepare('SELECT COUNT(*) FROM orders WHERE product_id = :id');
$hasOrders->execute([':id' => $id]);
if ((int)$hasOrders->fetchColumn() > 0) {
    // Don't break order history. Mark sold + soft-hide instead.
    $upd = db()->prepare("UPDATE products SET status='sold', sold_at = COALESCE(sold_at, NOW()) WHERE id = :id");
    $upd->execute([':id' => $id]);
    flash('info', 'This doll has order history, so it was marked Sold instead of deleted.');
    redirect('/admin/products.php');
}

$imgs = db()->prepare('SELECT filename FROM product_images WHERE product_id = :id');
$imgs->execute([':id' => $id]);
foreach ($imgs->fetchAll() as $row) delete_image_file($row['filename']);

$del = db()->prepare('DELETE FROM products WHERE id = :id');
$del->execute([':id' => $id]);

flash('success', 'Doll deleted.');
redirect('/admin/products.php');
