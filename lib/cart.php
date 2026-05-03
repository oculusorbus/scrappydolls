<?php
declare(strict_types=1);

/**
 * Session-backed shopping cart. Each doll is OOAK so the cart is just an
 * ordered list of product IDs (no quantities). Stale IDs (sold/draft/missing)
 * are filtered out lazily on read.
 */

const CART_SESSION_KEY = 'cart_product_ids';
const CART_MAX_ITEMS = 25;

// Flat-rate shipping: $7.99 first doll, $2.99 each additional.
const SHIPPING_FIRST_CENTS      = 799;
const SHIPPING_ADDITIONAL_CENTS = 299;

function shipping_cents_for_count(int $count): int {
    if ($count <= 0) return 0;
    return SHIPPING_FIRST_CENTS + (max(0, $count - 1) * SHIPPING_ADDITIONAL_CENTS);
}

function cart_shipping_cents(): int {
    return shipping_cents_for_count(cart_count());
}

function cart_grand_total_cents(): int {
    return cart_total_cents() + cart_shipping_cents();
}

function cart_ids(): array {
    $ids = $_SESSION[CART_SESSION_KEY] ?? [];
    return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
}

function cart_set(array $ids): void {
    $_SESSION[CART_SESSION_KEY] = array_values(array_unique(array_map('intval', $ids)));
}

function cart_add(int $productId): bool {
    if ($productId <= 0) return false;
    $ids = cart_ids();
    if (in_array($productId, $ids, true)) return true;
    if (count($ids) >= CART_MAX_ITEMS) return false;
    $ids[] = $productId;
    cart_set($ids);
    return true;
}

function cart_remove(int $productId): void {
    $ids = array_values(array_filter(cart_ids(), fn($id) => $id !== $productId));
    cart_set($ids);
}

function cart_clear(): void {
    unset($_SESSION[CART_SESSION_KEY]);
}

function cart_has(int $productId): bool {
    return in_array($productId, cart_ids(), true);
}

/**
 * Returns available products in the cart, in insertion order. Drops any
 * product that is no longer available (sold, deleted, or draft) and prunes
 * those IDs from the session so the cart stays clean.
 */
function cart_items(): array {
    $ids = cart_ids();
    if (!$ids) return [];
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT id, slug, title, price_cents, status FROM products
         WHERE id IN ($place) AND status = 'available'"
    );
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    $byId = [];
    foreach ($rows as $r) $byId[(int)$r['id']] = $r;
    $out = [];
    $kept = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $out[] = $byId[$id];
            $kept[] = $id;
        }
    }
    if (count($kept) !== count($ids)) cart_set($kept);
    return $out;
}

function cart_count(): int {
    return count(cart_ids());
}

function cart_total_cents(): int {
    $sum = 0;
    foreach (cart_items() as $item) $sum += (int)$item['price_cents'];
    return $sum;
}

/**
 * Suggests up to $limit available dolls not currently in the cart.
 * Random order — encourages discovery.
 */
function cart_suggestions(int $limit = 4): array {
    $exclude = cart_ids();
    $sql = "SELECT id, slug, title, price_cents FROM products WHERE status = 'available'";
    $params = [];
    if ($exclude) {
        $place = implode(',', array_fill(0, count($exclude), '?'));
        $sql .= " AND id NOT IN ($place)";
        $params = $exclude;
    }
    $sql .= ' ORDER BY RAND() LIMIT ' . max(1, $limit);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Return up to $limit random available-doll suggestions with a ready-to-use
 * thumbnail URL. Excludes cart items and an optional extra list.
 *
 * Soft fallback: if excluding $excludeExtraIds leaves fewer than $limit
 * candidates, retry without the extra exclusion so a "refresh lineup"
 * action still returns a full strip when stock is thin.
 */
function cart_suggestions_with_thumbs(int $limit, array $excludeExtraIds = []): array {
    $rows = _cart_suggest_query($limit, $excludeExtraIds);
    if (count($rows) < $limit && $excludeExtraIds) {
        // Re-query with only the cart excluded so the strip refills.
        $rows = _cart_suggest_query($limit, []);
    }
    return array_map(function ($r) {
        return [
            'id'          => (int)$r['id'],
            'slug'        => (string)$r['slug'],
            'title'       => (string)$r['title'],
            'price_cents' => (int)$r['price_cents'],
            'price'       => fmt_price((int)$r['price_cents']),
            'thumb_url'   => $r['thumb'] ? thumb_url($r['thumb']) : null,
            'product_url' => '/shop/product.php?slug=' . rawurlencode($r['slug']),
        ];
    }, $rows);
}

function _cart_suggest_query(int $limit, array $excludeExtraIds): array {
    $exclude = array_unique(array_merge(
        array_map('intval', cart_ids()),
        array_map('intval', $excludeExtraIds)
    ));
    $sql = "SELECT id, slug, title, price_cents,
              (SELECT filename FROM product_images
                 WHERE product_id = products.id
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 1) AS thumb
            FROM products
            WHERE status = 'available'";
    $params = [];
    if ($exclude) {
        $place = implode(',', array_fill(0, count($exclude), '?'));
        $sql .= " AND id NOT IN ($place)";
        $params = $exclude;
    }
    $sql .= ' ORDER BY RAND() LIMIT ' . max(1, $limit);
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Pick one random available doll, excluding cart items + an explicit list
 * (e.g. dolls already shown in the on-page suggestion strip). Includes a
 * ready-to-use thumbnail URL so the client can render without a second
 * round-trip. Returns null if no candidate exists.
 */
function cart_suggestion_one(array $excludeExtraIds = []): ?array {
    $exclude = array_unique(array_merge(
        array_map('intval', cart_ids()),
        array_map('intval', $excludeExtraIds)
    ));
    $sql = "SELECT id, slug, title, price_cents,
              (SELECT filename FROM product_images
                 WHERE product_id = products.id
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 1) AS thumb
            FROM products
            WHERE status = 'available'";
    $params = [];
    if ($exclude) {
        $place = implode(',', array_fill(0, count($exclude), '?'));
        $sql .= " AND id NOT IN ($place)";
        $params = $exclude;
    }
    $sql .= ' ORDER BY RAND() LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'id'          => (int)$row['id'],
        'slug'        => (string)$row['slug'],
        'title'       => (string)$row['title'],
        'price_cents' => (int)$row['price_cents'],
        'price'       => fmt_price((int)$row['price_cents']),
        'thumb_url'   => $row['thumb'] ? thumb_url($row['thumb']) : null,
        'product_url' => '/shop/product.php?slug=' . rawurlencode($row['slug']),
    ];
}
