<?php
declare(strict_types=1);

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s !== '' ? $s : 'doll-' . substr(bin2hex(random_bytes(4)), 0, 6);
}

function unique_slug(string $base, ?int $excludeId = null): string {
    $slug = slugify($base);
    $i = 0;
    while (true) {
        $candidate = $i ? "$slug-$i" : $slug;
        $sql = 'SELECT id FROM products WHERE slug = :slug';
        $params = [':slug' => $candidate];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) return $candidate;
        $i++;
    }
}

function fmt_price(int $cents): string {
    return '$' . number_format($cents / 100, 2);
}

function flash(string $key, $value = null) {
    if (func_num_args() === 1) {
        $val = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $val;
    }
    $_SESSION['flash'][$key] = $value;
    return null;
}

function redirect(string $path): void {
    if (!headers_sent()) {
        header('Location: ' . $path);
    }
    exit;
}

function url(string $path = ''): string {
    $base = rtrim($GLOBALS['config']['site_url'] ?? '', '/');
    if ($path === '') return $base . '/';
    return $base . '/' . ltrim($path, '/');
}

function asset_url(string $filename): string {
    return url('uploads/' . $filename);
}

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_request_headers(): array {
    if (function_exists('getallheaders')) {
        $hdrs = getallheaders();
        $out = [];
        foreach ($hdrs as $k => $v) $out[strtolower($k)] = $v;
        return $out;
    }
    $out = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = strtolower(str_replace('_', '-', substr($k, 5)));
            $out[$name] = $v;
        }
    }
    return $out;
}
