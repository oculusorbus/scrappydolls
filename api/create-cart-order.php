<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

if (!paypal_is_configured()) {
    json_response(['error' => 'Online payment is not configured yet — please message via Facebook to buy.'], 503);
}

$items = cart_items();
if (!$items) json_response(['error' => 'Your cart is empty.'], 400);

try {
    $referenceId = 'cart-' . substr(tracking_session_hash(), 0, 24);
    $resp = paypal_create_cart_order(
        $items,
        $referenceId,
        url('shop/success.php'),
        url('shop/cart.php')
    );
    if (empty($resp['id'])) json_response(['error' => 'PayPal returned no order id'], 502);

    foreach ($items as $item) {
        track_order_intent((int)$item['id'], $resp['id'], (int)$item['price_cents']);
    }

    json_response(['id' => $resp['id']]);
} catch (Throwable $e) {
    error_log('create-cart-order error: ' . $e->getMessage());
    json_response(['error' => 'Could not create order'], 500);
}
