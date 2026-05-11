<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

if (!csrf_check()) {
    json_response(['error' => 'Your session has expired. Refresh the page and try again.'], 403);
}

if (!turnstile_is_configured()) {
    error_log('contact-send: Turnstile not configured');
    json_response(['error' => 'Contact form is not configured yet. Please email hello@scrappydolls.com directly.'], 503);
}

$name    = trim((string)($_POST['name']    ?? ''));
$email   = trim((string)($_POST['email']   ?? ''));
$phone   = trim((string)($_POST['phone']   ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$token   = (string)($_POST['cf-turnstile-response'] ?? '');

// Honeypot: bots usually fill every field they see. Real browsers don't
// touch a CSS-hidden one. If this is non-empty, drop on the floor with a
// fake-success response so bots don't learn anything.
$honey = trim((string)($_POST['website'] ?? ''));
if ($honey !== '') {
    json_response(['ok' => true]);
}

// Length caps mirror the form's maxlength attributes.
$name    = mb_substr($name,    0, 100);
$email   = mb_substr($email,   0, 255);
$phone   = mb_substr($phone,   0, 40);
$subject = mb_substr($subject, 0, 200);
$message = mb_substr($message, 0, 5000);

$errors = [];
if ($name === '')    $errors[] = 'Please tell us your name.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email so we can write back.';
}
if ($subject === '') $errors[] = 'Please add a short subject.';
if ($message === '' || mb_strlen($message) < 5) {
    $errors[] = 'Please write a longer message.';
}
if ($errors) {
    json_response(['error' => implode(' ', $errors)], 422);
}

$remoteIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
if (!turnstile_verify($token, $remoteIp)) {
    json_response(['error' => 'We couldn\'t verify the human check. Please reload the page and try again.'], 422);
}

try {
    $ok = mail_contact_message($name, $email, $phone, $subject, $message);
    if (!$ok) {
        error_log('contact-send: mail_contact_message returned false');
        json_response(['error' => 'We couldn\'t send your message. Please try again, or email hello@scrappydolls.com directly.'], 500);
    }
} catch (Throwable $e) {
    error_log('contact-send error: ' . $e->getMessage());
    json_response(['error' => 'Something went wrong sending your message. Please try again.'], 500);
}

json_response(['ok' => true]);
