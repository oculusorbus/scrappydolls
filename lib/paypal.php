<?php
declare(strict_types=1);

/**
 * PayPal v2 Orders API helpers — pure cURL, no SDK.
 * Docs: https://developer.paypal.com/docs/api/orders/v2/
 */

function paypal_base_url(): string {
    $env = config('paypal.environment');
    return $env === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

function paypal_is_configured(): bool {
    $id = trim((string)config('paypal.client_id'));
    $secret = trim((string)config('paypal.secret'));
    if ($id === '' || $secret === '') return false;
    if ($id === 'CHANGE_ME' || $secret === 'CHANGE_ME') return false;
    return true;
}

function paypal_client_id(): string {
    return (string)config('paypal.client_id');
}

function paypal_environment(): string {
    return (string)config('paypal.environment');
}

function paypal_currency(): string {
    return (string)(config('paypal.currency') ?: 'USD');
}

function paypal_access_token(): string {
    static $token = null;
    static $expires = 0;
    if ($token !== null && time() < $expires - 60) return $token;

    $clientId = config('paypal.client_id');
    $secret   = config('paypal.secret');
    if (!$clientId || !$secret) {
        throw new RuntimeException('PayPal credentials are not configured.');
    }

    $ch = curl_init(paypal_base_url() . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $clientId . ':' . $secret,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) throw new RuntimeException("PayPal token cURL error: $err");
    if ($code !== 200)   throw new RuntimeException("PayPal token failed (HTTP $code): $resp");

    $data = json_decode($resp, true);
    if (!isset($data['access_token'])) throw new RuntimeException("PayPal token: malformed response: $resp");
    $token = $data['access_token'];
    $expires = time() + (int)($data['expires_in'] ?? 30000);
    return $token;
}

function paypal_request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array {
    $ch = curl_init(paypal_base_url() . $path);
    $headers = array_merge([
        'Authorization: Bearer ' . paypal_access_token(),
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=representation',
    ], $extraHeaders);

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) throw new RuntimeException("PayPal $method $path cURL error: $err");
    $data = json_decode($resp, true) ?? [];
    if ($code >= 400) {
        $msg = $data['message'] ?? $resp;
        throw new RuntimeException("PayPal $method $path failed (HTTP $code): $msg");
    }
    return $data;
}

function paypal_create_order(int $amountCents, string $description, string $referenceId, ?string $returnUrl = null, ?string $cancelUrl = null): array {
    $value = number_format($amountCents / 100, 2, '.', '');
    $purchase = [
        'reference_id' => substr($referenceId, 0, 256),
        'description'  => substr($description, 0, 127),
        'amount'       => [
            'currency_code' => paypal_currency(),
            'value'         => $value,
        ],
    ];
    $body = [
        'intent'         => 'CAPTURE',
        'purchase_units' => [$purchase],
    ];
    if ($returnUrl || $cancelUrl) {
        $body['application_context'] = array_filter([
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'shipping_preference' => 'GET_FROM_FILE',
            'user_action' => 'PAY_NOW',
            'brand_name' => config('site_name') ?: 'Scrappy Dolls',
        ]);
    }
    return paypal_request('POST', '/v2/checkout/orders', $body);
}

function paypal_capture_order(string $orderId): array {
    return paypal_request('POST', '/v2/checkout/orders/' . urlencode($orderId) . '/capture', null);
}

function paypal_get_order(string $orderId): array {
    return paypal_request('GET', '/v2/checkout/orders/' . urlencode($orderId));
}

/**
 * Cart-aware order: multiple items, single shipping address. Uses the
 * AUTHORIZE intent so the server can void cleanly if any item sold to
 * another buyer between approval and capture.
 *
 * $items: list of ['id' => int, 'title' => string, 'price_cents' => int]
 * $referenceId: identifier for this whole cart order (e.g. "cart-<sessionhash>")
 */
function paypal_create_cart_order(array $items, int $shippingCents, string $referenceId, ?string $returnUrl = null, ?string $cancelUrl = null): array {
    $currency = paypal_currency();
    $itemTotalCents = 0;
    $ppItems = [];
    foreach ($items as $it) {
        $cents = (int)$it['price_cents'];
        $itemTotalCents += $cents;
        $ppItems[] = [
            'name'        => substr((string)$it['title'], 0, 127),
            'quantity'    => '1',
            'sku'         => 'doll-' . (int)$it['id'],
            'unit_amount' => [
                'currency_code' => $currency,
                'value'         => number_format($cents / 100, 2, '.', ''),
            ],
            'category'    => 'PHYSICAL_GOODS',
        ];
    }
    $grandCents = $itemTotalCents + max(0, $shippingCents);
    $fmt = fn(int $c) => number_format($c / 100, 2, '.', '');
    $breakdown = [
        'item_total' => ['currency_code' => $currency, 'value' => $fmt($itemTotalCents)],
    ];
    if ($shippingCents > 0) {
        $breakdown['shipping'] = ['currency_code' => $currency, 'value' => $fmt($shippingCents)];
    }
    $body = [
        'intent'         => 'AUTHORIZE',
        'purchase_units' => [[
            'reference_id' => substr($referenceId, 0, 256),
            'amount'       => [
                'currency_code' => $currency,
                'value'         => $fmt($grandCents),
                'breakdown'     => $breakdown,
            ],
            'items' => $ppItems,
        ]],
    ];
    if ($returnUrl || $cancelUrl) {
        $body['application_context'] = array_filter([
            'return_url'          => $returnUrl,
            'cancel_url'          => $cancelUrl,
            'shipping_preference' => 'GET_FROM_FILE',
            'user_action'         => 'CONTINUE',
            'brand_name'          => config('site_name') ?: 'Scrappy Dolls',
        ]);
    }
    return paypal_request('POST', '/v2/checkout/orders', $body);
}

function paypal_authorize_order(string $orderId): array {
    return paypal_request('POST', '/v2/checkout/orders/' . urlencode($orderId) . '/authorize', null);
}

function paypal_capture_authorization(string $authId): array {
    return paypal_request('POST', '/v2/payments/authorizations/' . urlencode($authId) . '/capture', null);
}

function paypal_void_authorization(string $authId): void {
    // Void returns 204 No Content on success — paypal_request handles 4xx as throw.
    paypal_request('POST', '/v2/payments/authorizations/' . urlencode($authId) . '/void', null);
}

function paypal_extract_authorization_id(array $orderData): ?string {
    $units = $orderData['purchase_units'] ?? [];
    if (empty($units)) return null;
    $auths = $units[0]['payments']['authorizations'] ?? [];
    if (empty($auths)) return null;
    return $auths[0]['id'] ?? null;
}

function paypal_verify_webhook(array $headers, string $rawBody): bool {
    $webhookId = config('paypal.webhook_id');
    if (!$webhookId) return false;

    $payload = [
        'auth_algo'         => $headers['paypal-auth-algo'] ?? '',
        'cert_url'          => $headers['paypal-cert-url'] ?? '',
        'transmission_id'   => $headers['paypal-transmission-id'] ?? '',
        'transmission_sig'  => $headers['paypal-transmission-sig'] ?? '',
        'transmission_time' => $headers['paypal-transmission-time'] ?? '',
        'webhook_id'        => $webhookId,
        'webhook_event'     => json_decode($rawBody, true),
    ];
    try {
        $resp = paypal_request('POST', '/v1/notifications/verify-webhook-signature', $payload);
    } catch (Throwable $e) {
        error_log('PayPal webhook verify error: ' . $e->getMessage());
        return false;
    }
    return ($resp['verification_status'] ?? '') === 'SUCCESS';
}

/**
 * Convenience: extract shipping address from PayPal capture response.
 */
function paypal_extract_shipping(array $orderData): ?array {
    $units = $orderData['purchase_units'] ?? [];
    if (empty($units)) return null;
    $ship = $units[0]['shipping'] ?? null;
    if (!$ship) return null;
    return [
        'name'    => $ship['name']['full_name'] ?? null,
        'address' => $ship['address'] ?? null,
    ];
}

function paypal_extract_payer(array $orderData): array {
    $payer = $orderData['payer'] ?? [];
    $name = trim(($payer['name']['given_name'] ?? '') . ' ' . ($payer['name']['surname'] ?? ''));
    return [
        'email' => $payer['email_address'] ?? null,
        'name'  => $name !== '' ? $name : null,
    ];
}

function paypal_extract_capture_id(array $orderData): ?string {
    $units = $orderData['purchase_units'] ?? [];
    if (empty($units)) return null;
    $caps = $units[0]['payments']['captures'] ?? [];
    if (empty($caps)) return null;
    return $caps[0]['id'] ?? null;
}
