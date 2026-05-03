<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: $_POST;

$limit = (int)($body['limit'] ?? 5);
$limit = max(1, min(12, $limit));

// Refresh-lineup: reset the persisted session strip and re-roll a fresh
// one so the buyer gets a new lineup AND a future reload shows the same
// lineup (stable across reloads).
cart_reset_suggestions();
$suggestions = cart_stable_suggestions($limit);
json_response(['suggestions' => $suggestions]);
