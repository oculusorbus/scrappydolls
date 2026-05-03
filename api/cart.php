<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: $_POST;
$action = (string)($body['action'] ?? '');
$productId = (int)($body['product_id'] ?? 0);

$replacementSuggestion = null;
$addedItem = null;

switch ($action) {
    case 'add':
        if (!$productId) json_response(['error' => 'Missing product_id'], 400);
        $stmt = db()->prepare("
            SELECT p.id, p.slug, p.title, p.price_cents,
              (SELECT filename FROM product_images
                 WHERE product_id = p.id
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 1) AS thumb
            FROM products p
            WHERE p.id = :id AND p.status = 'available'
            LIMIT 1");
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => 'This doll is not available'], 409);
        if (!cart_add($productId)) {
            json_response(['error' => 'Cart is full (' . CART_MAX_ITEMS . ' max)'], 400);
        }
        $addedItem = [
            'id'          => (int)$row['id'],
            'slug'        => (string)$row['slug'],
            'title'       => (string)$row['title'],
            'price_cents' => (int)$row['price_cents'],
            'price'       => fmt_price((int)$row['price_cents']),
            'thumb_url'   => $row['thumb'] ? thumb_url($row['thumb']) : null,
            'product_url' => '/shop/product.php?slug=' . rawurlencode($row['slug']),
        ];
        // Optional: if the client tells us which suggestions are currently on
        // screen, we return a fresh one to refill the strip after this add.
        $excludeIds = $body['exclude_ids'] ?? null;
        if (is_array($excludeIds)) {
            $replacementSuggestion = cart_suggestion_one(array_map('intval', $excludeIds));
        }
        break;

    case 'remove':
        if (!$productId) json_response(['error' => 'Missing product_id'], 400);
        cart_remove($productId);
        break;

    case 'clear':
        cart_clear();
        break;

    default:
        json_response(['error' => 'Unknown action'], 400);
}

json_response([
    'ok'                 => true,
    'count'              => cart_count(),
    'subtotal_cents'     => cart_total_cents(),
    'shipping_cents'     => cart_shipping_cents(),
    'grand_total_cents'  => cart_grand_total_cents(),
    'product_ids'        => cart_ids(),
    'added_item'         => $addedItem,
    'suggestion'         => $replacementSuggestion,
]);
