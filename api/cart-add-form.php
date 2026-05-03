<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

// Plain HTML form -> server add -> 302 redirect. No JS needed.
// Used by every "+ Add to cart" button on the site.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/shop/');
}

$productId = (int)($_POST['product_id'] ?? 0);
$returnUrl = (string)($_POST['return_url'] ?? '/shop/cart.php');

// Only allow same-origin path-only return URLs to prevent open-redirect.
// Use ~ as the regex delimiter so # is just a literal inside the class.
if (!preg_match('~^/[A-Za-z0-9/_.?&=%#-]*$~', $returnUrl)) {
    $returnUrl = '/shop/cart.php';
}

if ($productId > 0) {
    $stmt = db()->prepare("SELECT id FROM products WHERE id = :id AND status = 'available' LIMIT 1");
    $stmt->execute([':id' => $productId]);
    if ($stmt->fetch()) {
        cart_add($productId);
    }
}

redirect($returnUrl);
