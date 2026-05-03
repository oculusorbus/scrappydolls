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

/**
 * Find the next free enumeration number for a base title — e.g. given
 * "Scrappy Doll" and existing rows titled "Scrappy Doll #3" / "#7", returns 8.
 */
function next_enumeration_number(string $baseTitle): int {
    $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $baseTitle) . ' #%';
    $stmt = db()->prepare("SELECT title FROM products WHERE title LIKE :like ESCAPE '\\\\'");
    $stmt->execute([':like' => $like]);
    $max = 0;
    foreach ($stmt->fetchAll() as $row) {
        if (preg_match('/#(\d+)\s*$/', (string)$row['title'], $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return $max + 1;
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

/**
 * URL of the small thumbnail variant for a product image.
 * Falls back to the display version if the thumb file doesn't exist
 * (e.g. for files saved before the resize pipeline was added).
 */
function thumb_url(string $filename): string {
    $thumbName = image_thumb_filename($filename);
    $dir = realpath(__DIR__ . '/../uploads');
    if ($dir && is_file($dir . DIRECTORY_SEPARATOR . $thumbName)) {
        return url('uploads/' . $thumbName);
    }
    return url('uploads/' . $filename);
}

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Did this doll go up recently? Used to drive the NEW badge.
 */
function product_is_new(?string $createdAt, int $withinDays = 14): bool {
    if (!$createdAt) return false;
    $ts = strtotime($createdAt);
    if ($ts === false) return false;
    return $ts > (time() - $withinDays * 86400);
}

/**
 * Bucket a 30-day view count into a popularity tier (0–3).
 * Tuned for a low-volume artisan shop — adjust thresholds if traffic grows.
 *   0 = nothing                                (no badge)
 *   1 = trending  (≥5 views/30d)               (1 flame)
 *   2 = hot       (≥15 views/30d)              (2 flames)
 *   3 = on fire   (≥30 views/30d)              (3 flames)
 */
function product_popularity_tier(int $views): int {
    if ($views >= 30) return 3;
    if ($views >= 15) return 2;
    if ($views >= 5)  return 1;
    return 0;
}

/**
 * Render flames for a popularity tier as inline SVG. Returns '' for tier 0.
 */
function popularity_flames_html(int $tier): string {
    if ($tier <= 0) return '';
    $flame = '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z"/></svg>';
    return str_repeat($flame, min(3, $tier));
}

/**
 * Pull dolls for landing-page surfaces in priority order:
 *   1) featured + available  (random within bucket)
 *   2) other available       (random within bucket)
 *   3) sold                  (random within bucket — only used to fill
 *      remaining slots if available stock is exhausted)
 *
 * Drafts are never surfaced. Returns up to $count rows including a
 * `thumb` filename when the doll has at least one image.
 */
function landing_dolls(int $count): array {
    $count = max(0, $count);
    if ($count === 0) return [];
    $stmt = db()->prepare("
        SELECT
            p.id, p.slug, p.title, p.price_cents, p.status, p.featured,
            (SELECT filename FROM product_images
              WHERE product_id = p.id
              ORDER BY sort_order ASC, id ASC
              LIMIT 1) AS thumb
        FROM products p
        WHERE p.status IN ('available', 'sold')
        ORDER BY
          CASE
            WHEN p.featured = 1 AND p.status = 'available' THEN 1
            WHEN p.status = 'available' THEN 2
            ELSE 3
          END,
          RAND()
        LIMIT :n
    ");
    $stmt->bindValue(':n', $count, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
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
