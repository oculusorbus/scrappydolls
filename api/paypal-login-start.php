<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

/**
 * Kick off "Log in with PayPal". Mints a CSRF state token, stashes it in
 * the session, and redirects the buyer to PayPal's consent screen. PayPal
 * sends them back to /api/paypal-login-callback.php, which verifies the
 * state and pulls their profile + address to pre-fill checkout.
 */

if (!paypal_is_configured()) {
    redirect(url('shop/cart.php'));
}

// Nothing to check out for — don't start a login dance.
if (!cart_items()) {
    redirect(url('shop/cart.php'));
}

$state = bin2hex(random_bytes(16));
$_SESSION['paypal_login_state'] = $state;

$redirectUri = url('api/paypal-login-callback.php');
redirect(paypal_login_authorize_url($state, $redirectUri));
