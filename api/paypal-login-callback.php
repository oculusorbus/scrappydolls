<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

/**
 * PayPal OAuth (Log In with PayPal) callback.
 *
 * STUB: handles the three response shapes safely so the URL is never a
 * 404/500 for PayPal review crawlers or stray visitors. The real token
 * exchange + profile fetch goes in the marked block below once the
 * developer-account credentials are live.
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
    flash('cart_error', "We couldn't sign you in with PayPal. You can keep checking out as a guest.");
    redirect(url('shop/cart.php'));
}

// 3. Success path. PayPal sent us back with a one-time auth `code`.
//
// TODO: implement once developer credentials are live.
//   a) Verify $state matches $_SESSION['paypal_login_state'] (CSRF defense)
//      and unset it. Reject if mismatched.
//   b) POST to /v1/oauth2/token with grant_type=authorization_code, the
//      code, and the registered redirect_uri — using paypal_request().
//   c) Call /v1/identity/oauth2/userinfo?schema=paypalv1.1 with the
//      returned access token to fetch profile + address.
//   d) Stash the profile fields we need (name, email, address) in the
//      session and bounce the user back to the checkout step that
//      computes Texas sales tax from the shipping address.
//
// For now, log that we got this far and send the user to the cart so
// nothing 500s if someone stumbles in with a code.
error_log('paypal-login-callback: received code (stub — exchange not implemented yet)');
redirect(url('shop/cart.php'));
