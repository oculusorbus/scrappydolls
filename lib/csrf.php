<?php
declare(strict_types=1);

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): bool {
    $tok = $_POST['csrf_token'] ?? '';
    if (!is_string($tok) || $tok === '') return false;
    $expected = $_SESSION['csrf_token'] ?? '';
    return hash_equals($expected, $tok);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
        http_response_code(403);
        echo 'Invalid request token.';
        exit;
    }
}
