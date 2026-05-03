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

$excludeIds = $body['exclude_ids'] ?? [];
if (!is_array($excludeIds)) $excludeIds = [];

$suggestions = cart_suggestions_with_thumbs($limit, $excludeIds);
json_response(['suggestions' => $suggestions]);
