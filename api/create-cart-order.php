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
    // cart_coupon() re-validates (expiry, uses, minimum) — a code that went
    // stale since it was applied silently drops to no discount here.
    $subtotal      = cart_total_cents();
    $coupon        = cart_coupon();
    $discountCents = $coupon ? coupon_discount_cents($coupon, $subtotal) : 0;
    $shippingCents = cart_shipping_cents(); // coupon-aware (free shipping)

    if ($subtotal - $discountCents + $shippingCents <= 0) {
        // PayPal rejects zero-amount orders. A 100%-off-everything code is
        // really a gift — handle those off-site.
        json_response(['error' => 'This code covers the whole order — message Kanda on Facebook to arrange it directly.'], 400);
    }

    $referenceId = 'cart-' . substr(tracking_session_hash(), 0, 24);
    $resp = paypal_create_cart_order(
        $items,
        $shippingCents,
        $referenceId,
        url('shop/success.php'),
        url('shop/cart.php'),
        $discountCents
    );
    if (empty($resp['id'])) json_response(['error' => 'PayPal returned no order id'], 502);

    foreach ($items as $item) {
        track_order_intent((int)$item['id'], $resp['id'], (int)$item['price_cents']);
    }
    if ($coupon) {
        // Snapshot the discount against this PayPal order — capture reads
        // this row, never the session (same pattern as order_intents).
        coupon_track_intent($resp['id'], $coupon, $discountCents);
    }

    json_response(['id' => $resp['id']]);
} catch (Throwable $e) {
    error_log('create-cart-order error: ' . $e->getMessage());
    json_response(['error' => 'Could not create order'], 500);
}
