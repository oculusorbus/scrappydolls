<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

/**
 * PayPal OAuth (Log In with PayPal) callback.
 *
 * Handles the three response shapes:
 *   1. Bare visit / crawler with no params  → home.
 *   2. PayPal-reported error (denied consent) → back to checkout, guest path.
 *   3. Success with a one-time `code`        → exchange for the buyer's
 *      profile + address, stash it in the session to pre-fill checkout,
 *      then return to the confirm page.
 *
 * Docs: https://developer.paypal.com/docs/log-in-with-paypal/integrate/
 */

$code  = isset($_GET['code'])  ? trim((string)$_GET['code'])  : '';
$state = isset($_GET['state']) ? trim((string)$_GET['state']) : '';
$err   = isset($_GET['error']) ? trim((string)$_GET['error']) : '';

// 1. Bare visit / crawler hit with no OAuth params — send them home.
if ($code === '' && $err === '') {
    redirect(url(''));
}

// 2. PayPal reported an error (user denied consent, scope problem, etc.).
if ($err !== '') {
    $desc = isset($_GET['error_description']) ? trim((string)$_GET['error_description']) : '';
    error_log('paypal-login-callback error: ' . $err . ($desc !== '' ? ' — ' . $desc : ''));
    // Not fatal: they can still check out as a guest by typing their address.
    redirect(url('shop/confirm.php'));
}

// 3. Success path. Verify state (CSRF), then exchange the code for the
//    buyer's profile. Any failure here is non-fatal — we just fall back to
//    the manual address form on the confirm page.
$expected = $_SESSION['paypal_login_state'] ?? '';
unset($_SESSION['paypal_login_state']);
if ($expected === '' || !hash_equals((string)$expected, $state)) {
    error_log('paypal-login-callback: state mismatch (possible CSRF or expired session)');
    redirect(url('shop/confirm.php'));
}

try {
    $tokens  = paypal_login_exchange_code($code);
    $info    = paypal_login_userinfo((string)$tokens['access_token']);
    $profile = paypal_login_profile_from_userinfo($info);
    // Stash for the confirm page to pre-fill. Cleared once consumed there.
    $_SESSION['paypal_login_profile'] = $profile;
} catch (Throwable $e) {
    error_log('paypal-login-callback exchange/userinfo error: ' . $e->getMessage());
    // fall through — confirm page renders with an empty form.
}

redirect(url('shop/confirm.php'));
