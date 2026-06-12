<?php
/**
 * Scrappy Dolls — config
 *
 * Copy this file to /config/config.php and fill in real values.
 * /config/config.php is gitignored — never commit secrets.
 */

return [

    // -----------------------------------------------------------------
    // Site
    // -----------------------------------------------------------------
    'site_url'    => 'https://scrappydolls.com',  // no trailing slash
    'site_name'   => 'Scrappy Dolls',
    'timezone'    => 'America/Chicago',           // San Antonio = Central

    // -----------------------------------------------------------------
    // Database (MySQL / MariaDB)
    // -----------------------------------------------------------------
    'db' => [
        'host' => 'localhost',
        'name' => 'scrappydolls',
        'user' => 'CHANGE_ME',
        'pass' => 'CHANGE_ME',
    ],

    // -----------------------------------------------------------------
    // PayPal — get these at https://developer.paypal.com
    //
    // 1. Log in with mom's PayPal account
    // 2. Apps & Credentials → Create App (name: "Scrappy Dolls Store")
    // 3. Copy Client ID + Secret (Sandbox tab to test, Live tab for prod)
    // 4. After site is live, set up Webhooks:
    //      Webhook URL: https://scrappydolls.com/api/webhook.php
    //      Events: PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED,
    //              PAYMENT.CAPTURE.REFUNDED
    //      Copy the Webhook ID into 'webhook_id' below.
    //
    // 5. "Log in with PayPal" (used to pre-fill checkout + compute tax)
    //    reuses these SAME client_id/secret. In Apps & Credentials →
    //    your app → enable "Log in with PayPal" and, under its settings,
    //    request the Address attribute (plus name + email) and set the
    //    Return URL to https://scrappydolls.com/api/paypal-login-callback.php
    // -----------------------------------------------------------------
    'paypal' => [
        'environment' => 'sandbox',     // 'sandbox' or 'live'
        'client_id'   => 'CHANGE_ME',
        'secret'      => 'CHANGE_ME',
        'webhook_id'  => '',            // optional but recommended
        'currency'    => 'USD',

        // Sales tax. Charged ONLY when the package ships to a Texas (US)
        // address (Scrappy Dolls is a San Antonio seller). 0.0825 = 8.25%,
        // the TX maximum combined rate. Omit this block to use that default.
        'tax' => [
            'tx_rate' => 0.0825,
        ],
    ],

    // -----------------------------------------------------------------
    // Mail — order notifications
    // Uses PHP's mail() by default; if your host blocks it, switch to
    // SMTP via PHPMailer (Composer) and update lib/mailer.php.
    // -----------------------------------------------------------------
    'mail' => [
        'from_email'    => 'no-reply@scrappydolls.com',
        'from_name'     => 'Scrappy Dolls',
        'admin_email'   => 'CHANGE_ME@example.com',  // mom's email — gets order alerts
        // Public-facing customer service address. Used as Reply-To on
        // customer-facing emails and as the To: for contact-form submissions.
        // Set up as a forwarding alias so it can hand off to multiple inboxes
        // without changing what customers see.
        'support_email' => 'hello@scrappydolls.com',
    ],

    // -----------------------------------------------------------------
    // Cloudflare Turnstile — anti-spam for the contact form.
    // Sign up at https://dash.cloudflare.com → Turnstile → Add site.
    // Pick "Managed" mode. Copy the keys here.
    // -----------------------------------------------------------------
    'turnstile' => [
        'site_key'   => '',  // public, rendered into the contact form
        'secret_key' => '',  // private, used server-side to verify tokens
    ],

    // -----------------------------------------------------------------
    // Uploads
    // -----------------------------------------------------------------
    'uploads' => [
        'max_size'      => 10 * 1024 * 1024,           // 10 MB per file
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],

    // -----------------------------------------------------------------
    // Security
    // -----------------------------------------------------------------
    'security' => [
        // Session cookie secure flag: set true if site is HTTPS-only (it should be).
        'cookie_secure'   => true,
        'cookie_samesite' => 'Lax',
    ],
];
