<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: $_POST;
$productId = (int)($body['product_id'] ?? 0);
if (!$productId) json_response(['error' => 'Missing product_id'], 400);

try {
    $stmt = db()->prepare('SELECT id, slug, title, price_cents, status FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    if (!$product) json_response(['error' => 'Doll not found'], 404);
    if ($product['status'] !== 'available') json_response(['error' => 'This doll is no longer available'], 409);

    $referenceId = 'doll-' . $product['id'];
    $resp = paypal_create_order(
        (int)$product['price_cents'],
        $product['title'],
        $referenceId,
        url('shop/success.php'),
        url('shop/product.php?slug=' . urlencode($product['slug']))
    );
    if (empty($resp['id'])) json_response(['error' => 'PayPal returned no order id'], 502);

    json_response(['id' => $resp['id']]);
} catch (Throwable $e) {
    error_log('create-order error: ' . $e->getMessage());
    json_response(['error' => 'Could not create order'], 500);
}
