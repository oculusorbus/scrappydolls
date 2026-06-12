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
 * $discountCents: coupon discount on the item subtotal (shipping waiving is
 *   handled by the caller passing $shippingCents = 0)
 */
function paypal_create_cart_order(array $items, int $shippingCents, string $referenceId, ?string $returnUrl = null, ?string $cancelUrl = null, int $discountCents = 0, int $taxCents = 0, ?array $shipTo = null): array {
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
    $discountCents = max(0, min($discountCents, $itemTotalCents));
    $shippingCents = max(0, $shippingCents);
    $taxCents      = max(0, $taxCents);
    $grandCents = $itemTotalCents - $discountCents + $shippingCents + $taxCents;
    $fmt = fn(int $c) => number_format($c / 100, 2, '.', '');
    $breakdown = [
        'item_total' => ['currency_code' => $currency, 'value' => $fmt($itemTotalCents)],
    ];
    if ($shippingCents > 0) {
        $breakdown['shipping'] = ['currency_code' => $currency, 'value' => $fmt($shippingCents)];
    }
    if ($taxCents > 0) {
        $breakdown['tax_total'] = ['currency_code' => $currency, 'value' => $fmt($taxCents)];
    }
    if ($discountCents > 0) {
        $breakdown['discount'] = ['currency_code' => $currency, 'value' => $fmt($discountCents)];
    }
    $unit = [
        'reference_id' => substr($referenceId, 0, 256),
        'amount'       => [
            'currency_code' => $currency,
            'value'         => $fmt($grandCents),
            'breakdown'     => $breakdown,
        ],
        'items' => $ppItems,
    ];
    // When we have the buyer's confirmed ship-to (the address tax was
    // computed from), pin it on the order so the PayPal popup can't change
    // the destination out from under the tax line.
    if ($shipTo && !empty($shipTo['address'])) {
        // Drop empty optional fields (e.g. address_line_2) — PayPal wants
        // populated values only.
        $shipAddress = array_filter($shipTo['address'], fn($v) => $v !== '' && $v !== null);
        $unit['shipping'] = [
            'name'    => ['full_name' => substr((string)($shipTo['name'] ?? ''), 0, 300)],
            'address' => $shipAddress,
        ];
    }
    $body = [
        'intent'         => 'AUTHORIZE',
        'purchase_units' => [$unit],
    ];
    $body['application_context'] = array_filter([
        'return_url'          => $returnUrl,
        'cancel_url'          => $cancelUrl,
        'shipping_preference' => $shipTo ? 'SET_PROVIDED_ADDRESS' : 'GET_FROM_FILE',
        'user_action'         => 'CONTINUE',
        'brand_name'          => config('site_name') ?: 'Scrappy Dolls',
    ]);
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

/* ------------------------------------------------------------------ */
/* Log in with PayPal (OpenID Connect)                                */
/* Lets a buyer authorize us to read their PayPal profile + address   */
/* so we can pre-fill checkout. Reuses the same REST app credentials  */
/* as the Orders API above; the "Log in with PayPal" feature and the  */
/* Address attribute must be enabled on that app in the PayPal         */
/* developer dashboard. Docs:                                          */
/* https://developer.paypal.com/docs/log-in-with-paypal/               */
/* ------------------------------------------------------------------ */

/** Browser-facing PayPal host for the consent screen (not the API host). */
function paypal_login_web_base(): string {
    return paypal_environment() === 'live'
        ? 'https://www.paypal.com'
        : 'https://www.sandbox.paypal.com';
}

/** Space-separated OpenID scopes we request. `address` requires approval. */
function paypal_login_scopes(): string {
    return 'openid profile email address';
}

/**
 * The consent-screen URL to redirect the buyer to. $state is an opaque
 * CSRF token we mint and later verify; $redirectUri must exactly match a
 * Return URL registered on the app.
 */
function paypal_login_authorize_url(string $state, string $redirectUri): string {
    $params = http_build_query([
        'flowEntry'     => 'static',
        'client_id'     => paypal_client_id(),
        'response_type' => 'code',
        'scope'         => paypal_login_scopes(),
        'redirect_uri'  => $redirectUri,
        'state'         => $state,
    ]);
    return paypal_login_web_base() . '/connect?' . $params;
}

/**
 * Exchange the one-time auth `code` for tokens. Returns the decoded token
 * response (access_token, refresh_token, id_token, ...). Throws on failure.
 */
function paypal_login_exchange_code(string $code): array {
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
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'authorization_code',
            'code'       => $code,
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) throw new RuntimeException("PayPal login token cURL error: $err");
    if ($httpCode !== 200) throw new RuntimeException("PayPal login token failed (HTTP $httpCode): $resp");
    $data = json_decode($resp, true);
    if (!isset($data['access_token'])) throw new RuntimeException("PayPal login token: malformed response: $resp");
    return $data;
}

/** Fetch the buyer's profile + address with an access token from the exchange. */
function paypal_login_userinfo(string $accessToken): array {
    $ch = curl_init(paypal_base_url() . '/v1/identity/oauth2/userinfo?schema=paypalv1.1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken, 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) throw new RuntimeException("PayPal userinfo cURL error: $err");
    if ($httpCode !== 200) throw new RuntimeException("PayPal userinfo failed (HTTP $httpCode): $resp");
    $data = json_decode($resp, true);
    if (!is_array($data)) throw new RuntimeException("PayPal userinfo: malformed response: $resp");
    return $data;
}

/**
 * Map a userinfo response into the checkout-form shape we pre-fill from.
 * PayPal's OpenID address uses street_address/locality/region/postal_code/
 * country; we translate to our PayPal-Orders-style address keys. Returns
 * ['name','email','phone','address'=>[...]] with empty strings for fields
 * the buyer didn't share.
 */
function paypal_login_profile_from_userinfo(array $info): array {
    $name = trim((string)($info['name'] ?? ''));
    if ($name === '') {
        $name = trim(((string)($info['given_name'] ?? '')) . ' ' . ((string)($info['family_name'] ?? '')));
    }
    // Email may be a string or the first of an `emails` list depending on schema.
    $email = (string)($info['email'] ?? '');
    if ($email === '' && !empty($info['emails'][0]['value'])) {
        $email = (string)$info['emails'][0]['value'];
    }
    $phone = (string)($info['phone_number'] ?? ($info['phone'] ?? ''));

    $a = is_array($info['address'] ?? null) ? $info['address'] : [];
    $country = strtoupper((string)($a['country'] ?? ''));
    $region  = (string)($a['region'] ?? '');
    if ($country === 'US' && $region !== '') $region = normalize_us_state($region);

    return [
        'name'    => $name,
        'email'   => $email,
        'phone'   => $phone,
        'address' => [
            'address_line_1' => (string)($a['street_address'] ?? ''),
            'address_line_2' => '',
            'admin_area_2'   => (string)($a['locality'] ?? ''),
            'admin_area_1'   => $region,
            'postal_code'    => (string)($a['postal_code'] ?? ''),
            'country_code'   => $country !== '' ? $country : 'US',
        ],
    ];
}
