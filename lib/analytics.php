<?php
declare(strict_types=1);

/**
 * First-party analytics: lightweight, privacy-respecting tracking
 * for page views, Buy-button intents, and UTM attribution.
 *
 * No third-party services. No personal data leaves your DB.
 * IPs are sha256-hashed with the site URL as salt — used only for
 * approximate unique-visitor counting, never stored raw.
 */

const ANALYTICS_SESSION_COOKIE = 'sd_sid';
const ANALYTICS_SESSION_DAYS   = 30;

/* ------------------------------------------------------------------ */
/* Identity helpers                                                   */
/* ------------------------------------------------------------------ */

function tracking_session_hash(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $sid = $_COOKIE[ANALYTICS_SESSION_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/', $sid)) {
        $sid = bin2hex(random_bytes(16));
        if (!headers_sent()) {
            setcookie(ANALYTICS_SESSION_COOKIE, $sid, [
                'expires'  => time() + (ANALYTICS_SESSION_DAYS * 86400),
                'path'     => '/',
                'secure'   => (bool)config('security.cookie_secure'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[ANALYTICS_SESSION_COOKIE] = $sid;
        }
    }
    $cached = hash('sha256', $sid . '|' . (config('site_url') ?? ''));
    return $cached;
}

function tracking_ip_hash(): string {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
    if (str_contains($ip, ',')) $ip = trim(explode(',', $ip)[0]);
    return hash('sha256', $ip . '|' . (config('site_url') ?? ''));
}

function tracking_is_bot(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') return true;
    return (bool)preg_match(
        '/bot|crawl|spider|slurp|bing|google|yandex|duckduck|baidu|preview|fetch|monitor|uptime|curl|wget|python|axios|java\/|httpclient|headless/i',
        $ua
    );
}

function tracking_user_agent(): ?string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return $ua === '' ? null : substr($ua, 0, 500);
}

/* ------------------------------------------------------------------ */
/* UTM attribution (first-touch, persisted in PHP session)            */
/* ------------------------------------------------------------------ */

function tracking_capture_utms(): array {
    $keys = ['utm_source', 'utm_medium', 'utm_campaign'];
    $current = [];
    foreach ($keys as $k) {
        if (!empty($_GET[$k])) {
            $current[$k] = substr((string)$_GET[$k], 0, 100);
        }
    }
    if ($current) {
        // First-touch: only set if not already attributed
        if (empty($_SESSION['utm'])) $_SESSION['utm'] = $current;
    }
    return $_SESSION['utm'] ?? $current;
}

function tracking_referrer(): array {
    $ref = $_SERVER['HTTP_REFERER'] ?? null;
    if (!$ref) return ['url' => null, 'host' => null];
    $host = parse_url($ref, PHP_URL_HOST) ?: null;
    if ($host && $host === parse_url((string)config('site_url'), PHP_URL_HOST)) {
        // Internal referrer — not interesting for attribution
        return ['url' => substr($ref, 0, 500), 'host' => null];
    }
    return ['url' => substr($ref, 0, 500), 'host' => $host];
}

/* ------------------------------------------------------------------ */
/* Recording                                                          */
/* ------------------------------------------------------------------ */

function track_view(string $path, ?int $productId = null): void {
    if (tracking_is_bot()) return;
    try {
        $utm = tracking_capture_utms();
        $ref = tracking_referrer();
        $stmt = db()->prepare('
            INSERT INTO page_views
                (product_id, path, referrer, referrer_host,
                 utm_source, utm_medium, utm_campaign,
                 user_agent, ip_hash, session_hash)
            VALUES
                (:pid, :path, :ref, :refh,
                 :usrc, :umed, :ucamp,
                 :ua, :iph, :sh)
        ');
        $stmt->execute([
            ':pid'   => $productId,
            ':path'  => substr($path, 0, 255),
            ':ref'   => $ref['url'],
            ':refh'  => $ref['host'],
            ':usrc'  => $utm['utm_source']   ?? null,
            ':umed'  => $utm['utm_medium']   ?? null,
            ':ucamp' => $utm['utm_campaign'] ?? null,
            ':ua'    => tracking_user_agent(),
            ':iph'   => tracking_ip_hash(),
            ':sh'    => tracking_session_hash(),
        ]);
    } catch (Throwable $e) {
        error_log('track_view: ' . $e->getMessage());
    }
}

/**
 * Record a checkout intent: one row per cart item per PayPal order.
 *
 * Load-bearing for capture-cart-order.php — these rows are the canonical
 * record of what the buyer agreed to pay for. The capture endpoint reads
 * them to lock the line items and prices, so a buyer who modifies their
 * cart between PayPal-popup approval and the confirm-page submit can't
 * desync the charge amount from the items shipped.
 *
 * Bot UAs are recorded too (no skip): the row is required for capture
 * regardless of who is checking out, and bots essentially never reach
 * this endpoint anyway. Errors propagate — the create-order caller is
 * expected to abort and let the PayPal order expire unused if we can't
 * record the intent.
 */
function track_order_intent(int $productId, string $paypalOrderId, int $amountCents): void {
    $utm = tracking_capture_utms();
    $stmt = db()->prepare('
        INSERT INTO order_intents
            (product_id, paypal_order_id, session_hash, ip_hash,
             utm_source, utm_medium, utm_campaign,
             user_agent, amount_cents)
        VALUES
            (:pid, :poid, :sh, :iph,
             :usrc, :umed, :ucamp,
             :ua, :amt)
    ');
    $stmt->execute([
        ':pid'   => $productId,
        ':poid'  => $paypalOrderId,
        ':sh'    => tracking_session_hash(),
        ':iph'   => tracking_ip_hash(),
        ':usrc'  => $utm['utm_source']   ?? null,
        ':umed'  => $utm['utm_medium']   ?? null,
        ':ucamp' => $utm['utm_campaign'] ?? null,
        ':ua'    => tracking_user_agent(),
        ':amt'   => $amountCents,
    ]);
}

function track_intent_captured(string $paypalOrderId): void {
    try {
        $stmt = db()->prepare("UPDATE order_intents SET status='captured', captured_at=NOW() WHERE paypal_order_id = :p");
        $stmt->execute([':p' => $paypalOrderId]);
    } catch (Throwable $e) {
        error_log('track_intent_captured: ' . $e->getMessage());
    }
}

/* ------------------------------------------------------------------ */
/* Reporting helpers (used by /admin/reports.php)                     */
/* ------------------------------------------------------------------ */

/**
 * Resolve a date range key into [from, to, prevFrom, prevTo, label, days].
 */
function range_resolve(string $key): array {
    $now = new DateTimeImmutable('now');
    $end = $now->setTime(23, 59, 59);
    switch ($key) {
        case '7d':   $days = 7;   $start = $end->modify('-6 days')->setTime(0,0,0);  $label = 'Last 7 days'; break;
        case '90d':  $days = 90;  $start = $end->modify('-89 days')->setTime(0,0,0); $label = 'Last 90 days'; break;
        case 'ytd':
            $start = $now->setDate((int)$now->format('Y'), 1, 1)->setTime(0,0,0);
            $days = max(1, (int)$start->diff($end)->days + 1);
            $label = 'Year to date';
            break;
        case 'all':
            $start = (new DateTimeImmutable('2000-01-01'))->setTime(0,0,0);
            $days = max(1, (int)$start->diff($end)->days + 1);
            $label = 'All time';
            break;
        case '30d':
        default:
            $days  = 30;
            $start = $end->modify('-29 days')->setTime(0,0,0);
            $label = 'Last 30 days';
            $key   = '30d';
    }
    $prevEnd   = $start->modify('-1 second');
    $prevStart = $prevEnd->modify('-' . ($days - 1) . ' days')->setTime(0,0,0);
    return [
        'key'        => $key,
        'label'      => $label,
        'days'       => $days,
        'from'       => $start->format('Y-m-d H:i:s'),
        'to'         => $end->format('Y-m-d H:i:s'),
        'prev_from'  => $prevStart->format('Y-m-d H:i:s'),
        'prev_to'    => $prevEnd->format('Y-m-d H:i:s'),
    ];
}

function pct_delta(float $current, float $previous): ?float {
    if ($previous == 0.0) return $current > 0 ? null : 0.0;
    return round((($current - $previous) / $previous) * 100, 1);
}
