<?php
declare(strict_types=1);

/**
 * Checkout submission: the buyer-confirmed contact + shipping details
 * collected on /shop/confirm.php.
 *
 * In the cart → confirm → pay flow, the order amount (and its tax line)
 * depends on the destination, so the address must be known BEFORE the
 * PayPal order is created. create-cart-order.php validates the submission,
 * prices it (including Texas tax), creates the PayPal order, and snapshots
 * the whole thing into order_checkout_intents. capture-cart-order.php then
 * trusts that snapshot — never the session or a re-POSTed body — so the
 * recorded charge and ship-to always match what PayPal authorized.
 */

function _checkout_trimstr($v, int $max): string {
    return mb_substr(trim((string)$v), 0, $max);
}

/**
 * Validate and normalize a confirm-page submission.
 *
 * Returns ['errors' => string[], 'data' => array|null]. When errors is
 * empty, data holds normalized fields:
 *   name, email, phone, is_gift, gift_recipient_name, gift_message,
 *   ship_name, address (PayPal-shaped: address_line_1, address_line_2,
 *   admin_area_2, admin_area_1, postal_code, country_code),
 *   shipping_address (['name' => ship_name, 'address' => address]).
 *
 * The shipping state is normalized to a USPS 2-letter code for US
 * addresses so tax detection and PayPal's SET_PROVIDED_ADDRESS both get a
 * clean value.
 */
function checkout_parse_submission(array $body): array {
    $name        = _checkout_trimstr($body['name']  ?? '', 255);
    $email       = _checkout_trimstr($body['email'] ?? '', 255);
    $phone       = _checkout_trimstr($body['phone'] ?? '', 40);
    $isGift      = !empty($body['is_gift']);
    $giftName    = _checkout_trimstr($body['gift_recipient_name'] ?? '', 255);
    $giftMessage = _checkout_trimstr($body['gift_message'] ?? '', 500);

    $addr    = is_array($body['address'] ?? null) ? $body['address'] : [];
    $addr1   = _checkout_trimstr($addr['address_line_1'] ?? '', 255);
    $addr2   = _checkout_trimstr($addr['address_line_2'] ?? '', 255);
    $city    = _checkout_trimstr($addr['admin_area_2']   ?? '', 120);
    $country = strtoupper(_checkout_trimstr($addr['country_code'] ?? '', 2));
    if ($country === '') $country = 'US';
    // Normalize US state to a 2-letter code; pass foreign regions through.
    $stateRaw = _checkout_trimstr($addr['admin_area_1'] ?? '', 120);
    $state    = ($country === 'US') ? normalize_us_state($stateRaw) : $stateRaw;
    $postal   = _checkout_trimstr($addr['postal_code'] ?? '', 32);

    $errors = [];
    if ($name === '')  $errors[] = 'Your name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if ($phone === '' || !preg_match('/[0-9]/', $phone) || strlen($phone) < 7) {
        $errors[] = 'A phone number is required.';
    }
    if ($addr1 === '')  $errors[] = 'Street address is required.';
    if ($city === '')   $errors[] = 'City is required.';
    if ($postal === '') $errors[] = 'Postal code is required.';
    if (!preg_match('/^[A-Z]{2}$/', $country)) $errors[] = 'Country must be a 2-letter code.';
    if ($country === 'US' && $state === '')    $errors[] = 'State is required.';
    if ($country === 'US' && $state !== '' && !preg_match('/^[A-Z]{2}$/', $state)) {
        $errors[] = 'Use a 2-letter state code for US addresses (e.g. TX).';
    }
    if ($isGift && $giftName === '') $errors[] = 'Recipient name is required for gifts.';

    if ($errors) return ['errors' => $errors, 'data' => null];

    $shipName = $isGift ? $giftName : $name;
    $address = [
        'address_line_1' => $addr1,
        'address_line_2' => $addr2,
        'admin_area_2'   => $city,
        'admin_area_1'   => $state,
        'postal_code'    => $postal,
        'country_code'   => $country,
    ];

    return [
        'errors' => [],
        'data'   => [
            'name'                => $name,
            'email'               => $email,
            'phone'               => $phone,
            'is_gift'             => $isGift,
            'gift_recipient_name' => $isGift ? $giftName : null,
            'gift_message'        => ($isGift && $giftMessage !== '') ? $giftMessage : null,
            'ship_name'           => $shipName,
            'address'             => $address,
            'shipping_address'    => ['name' => $shipName, 'address' => $address],
        ],
    ];
}

/**
 * Snapshot the confirmed submission + computed amounts against a PayPal
 * order at creation time. Load-bearing for capture-cart-order.php, same
 * pattern as track_order_intent() / coupon_track_intent(): capture trusts
 * this row, never the session.
 *
 * $amounts: ['item_total','discount','shipping','tax','total'] in cents.
 */
function checkout_track_intent(string $paypalOrderId, array $d, array $amounts): void {
    $stmt = db()->prepare('
        INSERT INTO order_checkout_intents
            (paypal_order_id, customer_name, customer_email, customer_phone,
             is_gift, gift_recipient_name, gift_message, shipping_address,
             item_total_cents, discount_cents, shipping_cents, tax_cents, total_cents)
        VALUES
            (:poid, :name, :email, :phone,
             :gift, :grname, :gmsg, :ship,
             :items, :disc, :shipc, :tax, :total)
    ');
    $stmt->execute([
        ':poid'   => $paypalOrderId,
        ':name'   => $d['name'],
        ':email'  => $d['email'],
        ':phone'  => $d['phone'],
        ':gift'   => !empty($d['is_gift']) ? 1 : 0,
        ':grname' => $d['gift_recipient_name'],
        ':gmsg'   => $d['gift_message'],
        ':ship'   => json_encode($d['shipping_address']),
        ':items'  => (int)$amounts['item_total'],
        ':disc'   => (int)$amounts['discount'],
        ':shipc'  => (int)$amounts['shipping'],
        ':tax'    => (int)$amounts['tax'],
        ':total'  => (int)$amounts['total'],
    ]);
}

/** The checkout snapshot recorded when this PayPal order was created, or null. */
function checkout_intent_for_order(string $paypalOrderId): ?array {
    $stmt = db()->prepare('
        SELECT * FROM order_checkout_intents
        WHERE paypal_order_id = :p
        ORDER BY id DESC LIMIT 1
    ');
    $stmt->execute([':p' => $paypalOrderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
