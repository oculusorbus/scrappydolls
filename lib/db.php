<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $cfg = $GLOBALS['config']['db'];
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('DB connection failed: ' . $e->getMessage());
        echo 'Database connection failed.';
        exit;
    }
    return $pdo;
}
