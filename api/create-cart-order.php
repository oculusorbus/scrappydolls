<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

/**
 * Create a PayPal order for the session cart, priced from the buyer's
 * confirmed ship-to (so Texas sales tax is baked into the authorized
 * amount). The confirm page (/shop/confirm.php) POSTs the contact +
 * shipping form here when the buyer clicks the PayPal button.
 *
 * The whole priced submission is snapshotted into order_checkout_intents;
 * capture-cart-order.php bills from that snapshot, never the session.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

if (!paypal_is_configured()) {
    json_response(['error' => 'Online payment is not configured yet — please message via Facebook to buy.'], 503);
}

$raw  = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: $_POST;
if (!is_array($body)) $body = [];

$items = cart_items();
if (!$items) json_response(['error' => 'Your cart is empty.'], 400);

// Validate + normalize the confirmed contact and ship-to before we price.
$parsed = checkout_parse_submission($body);
if ($parsed['errors']) {
    json_response(['error' => implode(' ', $parsed['errors']), 'fields' => $parsed['errors']], 422);
}
$d = $parsed['data'];

try {
    // cart_coupon() re-validates (expiry, uses, minimum) — a code that went
    // stale since it was applied silently drops to no discount here.
    $subtotal      = cart_total_cents();
    $coupon        = cart_coupon();
    $discountCents = $coupon ? coupon_discount_cents($coupon, $subtotal) : 0;
    $shippingCents = cart_shipping_cents(); // coupon-aware (free shipping)

    // Texas sales tax on the discounted base + shipping, from the ship-to.
    $taxBase  = max(0, $subtotal - $discountCents) + $shippingCents;
    $taxCents = order_tax_cents($taxBase, $d['address']['admin_area_1'], $d['address']['country_code']);

    $grandTotal = $subtotal - $discountCents + $shippingCents + $taxCents;
    if ($grandTotal <= 0) {
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
        $discountCents,
        $taxCents,
        $d['shipping_address']
    );
    if (empty($resp['id'])) json_response(['error' => 'PayPal returned no order id'], 502);
    $orderId = (string)$resp['id'];

    foreach ($items as $item) {
        track_order_intent((int)$item['id'], $orderId, (int)$item['price_cents']);
    }
    if ($coupon) {
        // Snapshot the discount against this PayPal order — capture reads
        // this row, never the session (same pattern as order_intents).
        coupon_track_intent($orderId, $coupon, $discountCents);
    }
    // Snapshot the confirmed contact + ship-to + priced breakdown.
    checkout_track_intent($orderId, $d, [
        'item_total' => $subtotal,
        'discount'   => $discountCents,
        'shipping'   => $shippingCents,
        'tax'        => $taxCents,
        'total'      => $grandTotal,
    ]);

    json_response(['id' => $orderId]);
} catch (Throwable $e) {
    error_log('create-cart-order error: ' . $e->getMessage());
    json_response(['error' => 'Could not create order'], 500);
}
