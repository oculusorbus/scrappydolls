<?php
declare(strict_types=1);

if (!file_exists(__DIR__ . '/../config/config.php')) {
    http_response_code(500);
    echo 'Site is not configured yet. Copy /config/config.example.php to /config/config.php and fill in values.';
    exit;
}

$GLOBALS['config'] = require __DIR__ . '/../config/config.php';

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'UTC');

$secCfg = $GLOBALS['config']['security'] ?? [];
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => (bool)($secCfg['cookie_secure'] ?? true),
    'httponly' => true,
    'samesite' => $secCfg['cookie_samesite'] ?? 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/paypal.php';
require_once __DIR__ . '/mailer.php';

function config(?string $key = null) {
    if ($key === null) return $GLOBALS['config'];
    $parts = explode('.', $key);
    $val = $GLOBALS['config'];
    foreach ($parts as $p) {
        if (!is_array($val) || !array_key_exists($p, $val)) return null;
        $val = $val[$p];
    }
    return $val;
}
