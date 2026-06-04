<?php
declare(strict_types=1);

/**
 * Coupon codes: percent off, fixed amount off, free shipping, or any
 * combination. Admin creates codes in /admin/coupons.php; buyers enter
 * them on the cart page.
 *
 * The applied code lives in the session (like the cart itself). When a
 * PayPal order is created, the computed discount is snapshotted into
 * order_coupon_intents — capture reads that snapshot, never the session,
 * so the recorded charge always matches what PayPal authorized even if
 * the buyer changes their cart or coupon in another tab mid-checkout.
 */

const COUPON_SESSION_KEY = 'cart_coupon_code';

function coupon_normalize(string $code): string {
    return strtoupper(trim($code));
}

function coupon_find(string $code): ?array {
    $code = coupon_normalize($code);
    if ($code === '') return null;
    $stmt = db()->prepare('SELECT * FROM coupons WHERE code = :c LIMIT 1');
    $stmt->execute([':c' => $code]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Why this coupon can't be used right now, or null if it can.
 * $subtotalCents is the pre-discount item subtotal it would apply to.
 */
function coupon_problem(array $coupon, int $subtotalCents): ?string {
    if (empty($coupon['active'])) {
        return 'That code is no longer active.';
    }
    if (!empty($coupon['expires_at']) && strtotime((string)$coupon['expires_at']) < time()) {
        return 'That code has expired.';
    }
    if ($coupon['max_uses'] !== null && (int)$coupon['used_count'] >= (int)$coupon['max_uses']) {
        return 'That code has already been used.';
    }
    if ((int)$coupon['min_subtotal_cents'] > 0 && $subtotalCents < (int)$coupon['min_subtotal_cents']) {
        return 'That code needs an order of at least '
            . fmt_price((int)$coupon['min_subtotal_cents']) . ' (before shipping).';
    }
    return null;
}

/**
 * Item discount in cents (percent + fixed combined), capped at the
 * subtotal so the items line can never go negative. Shipping waiving is
 * separate — see coupon_waives_shipping().
 */
function coupon_discount_cents(array $coupon, int $subtotalCents): int {
    $d = 0;
    if ((int)$coupon['percent_off'] > 0) {
        $d += (int)round($subtotalCents * min(100, (int)$coupon['percent_off']) / 100);
    }
    $d += (int)$coupon['amount_off_cents'];
    return min($d, $subtotalCents);
}

function coupon_waives_shipping(array $coupon): bool {
    return !empty($coupon['free_shipping']);
}

/** Human description, e.g. "10% off + free shipping". */
function coupon_summary(array $coupon): string {
    $parts = [];
    if ((int)$coupon['percent_off'] > 0)      $parts[] = (int)$coupon['percent_off'] . '% off';
    if ((int)$coupon['amount_off_cents'] > 0) $parts[] = fmt_price((int)$coupon['amount_off_cents']) . ' off';
    if (!empty($coupon['free_shipping']))     $parts[] = 'free shipping';
    return $parts ? implode(' + ', $parts) : '—';
}

/**
 * Apply a code to the session cart.
 * Returns ['ok' => bool, 'error' => string|null].
 */
function cart_coupon_apply(string $code): array {
    $coupon = coupon_find($code);
    if (!$coupon) {
        return ['ok' => false, 'error' => "We don't recognize that code — check the spelling?"];
    }
    $problem = coupon_problem($coupon, cart_total_cents());
    if ($problem) {
        return ['ok' => false, 'error' => $problem];
    }
    $_SESSION[COUPON_SESSION_KEY] = (string)$coupon['code'];
    return ['ok' => true, 'error' => null];
}

function cart_coupon_remove(): void {
    unset($_SESSION[COUPON_SESSION_KEY]);
}

/**
 * The coupon currently applied to the session cart, or null. Re-validated
 * on every read against the current subtotal; a code that went invalid
 * since it was applied (expired, used up, deactivated, cart shrank below
 * the minimum) is dropped silently so totals never show a stale discount.
 */
function cart_coupon(): ?array {
    $code = $_SESSION[COUPON_SESSION_KEY] ?? '';
    if (!is_string($code) || $code === '') return null;
    $coupon = coupon_find($code);
    if (!$coupon || coupon_problem($coupon, cart_total_cents()) !== null) {
        cart_coupon_remove();
        return null;
    }
    return $coupon;
}

/**
 * Snapshot the coupon against a PayPal order at creation time.
 * Load-bearing for capture-cart-order.php, same as track_order_intent():
 * capture trusts this row, never the session.
 */
function coupon_track_intent(string $paypalOrderId, array $coupon, int $discountCents): void {
    $stmt = db()->prepare('
        INSERT INTO order_coupon_intents (paypal_order_id, coupon_id, code, discount_cents, free_shipping)
        VALUES (:poid, :cid, :code, :d, :fs)
    ');
    $stmt->execute([
        ':poid' => $paypalOrderId,
        ':cid'  => (int)$coupon['id'],
        ':code' => (string)$coupon['code'],
        ':d'    => $discountCents,
        ':fs'   => coupon_waives_shipping($coupon) ? 1 : 0,
    ]);
}

/**
 * The coupon snapshot recorded when this PayPal order was created, or
 * null if no coupon was applied.
 */
function coupon_intent_for_order(string $paypalOrderId): ?array {
    $stmt = db()->prepare('
        SELECT * FROM order_coupon_intents
        WHERE paypal_order_id = :p
        ORDER BY id DESC LIMIT 1
    ');
    $stmt->execute([':p' => $paypalOrderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
