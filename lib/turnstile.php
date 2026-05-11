<?php
declare(strict_types=1);

/**
 * Cloudflare Turnstile verification.
 * Server posts the buyer-supplied token to Cloudflare; Cloudflare confirms
 * the human passed the challenge. Without verification, anyone can POST
 * directly to our form endpoint and bypass the widget on the page.
 */

function turnstile_is_configured(): bool {
    return (string)config('turnstile.site_key') !== ''
        && (string)config('turnstile.secret_key') !== '';
}

function turnstile_site_key(): string {
    return (string)config('turnstile.site_key');
}

/**
 * Verify a token submitted by the client. Returns true on success.
 * Failures are logged so we can spot widget breakage or attacker
 * traffic.
 */
function turnstile_verify(?string $token, ?string $remoteIp = null): bool {
    if (!turnstile_is_configured()) {
        // Fail closed in production — but if no keys are configured yet,
        // refuse rather than silently letting traffic through.
        error_log('Turnstile verify called but no keys configured');
        return false;
    }
    $token = trim((string)$token);
    if ($token === '') return false;

    $payload = [
        'secret'   => (string)config('turnstile.secret_key'),
        'response' => $token,
    ];
    if ($remoteIp) $payload['remoteip'] = $remoteIp;

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        error_log('Turnstile verify HTTP error: ' . $err);
        return false;
    }
    $data = json_decode((string)$resp, true);
    if (!is_array($data)) {
        error_log('Turnstile verify: bad JSON response');
        return false;
    }
    if (empty($data['success'])) {
        $codes = isset($data['error-codes']) ? implode(',', (array)$data['error-codes']) : '(none)';
        error_log('Turnstile verify failed: ' . $codes);
        return false;
    }
    return true;
}
