<?php
declare(strict_types=1);

/**
 * Session-backed shopping cart. Each doll is OOAK so the cart is just an
 * ordered list of product IDs (no quantities). Stale IDs (sold/draft/missing)
 * are filtered out lazily on read.
 */

const CART_SESSION_KEY = 'cart_product_ids';
const CART_MAX_ITEMS = 25;
const CART_SUGGESTION_SESSION_KEY = 'cart_suggestion_ids';

// Flat-rate shipping: $7.99 first doll, $2.99 each additional.
// Free shipping kicks in once the item subtotal hits the threshold.
const SHIPPING_FIRST_CENTS         = 799;
const SHIPPING_ADDITIONAL_CENTS    = 299;
const SHIPPING_FREE_THRESHOLD_CENTS = 5000; // $50.00

/**
 * Shipping cost in cents. Subtotal-aware so free-shipping kicks in once the
 * cart reaches the threshold.
 */
function shipping_cents(int $count, int $subtotalCents): int {
    if ($count <= 0) return 0;
    if ($subtotalCents >= SHIPPING_FREE_THRESHOLD_CENTS) return 0;
    return SHIPPING_FIRST_CENTS + (max(0, $count - 1) * SHIPPING_ADDITIONAL_CENTS);
}

/**
 * Backward-compatible wrapper. Without a subtotal we charge the full rate;
 * callers that know the subtotal should use shipping_cents() directly so
 * the free-shipping threshold applies.
 */
function shipping_cents_for_count(int $count): int {
    return shipping_cents($count, 0);
}

function cart_shipping_cents(): int {
    $coupon = cart_coupon();
    if ($coupon && coupon_waives_shipping($coupon)) return 0;
    return shipping_cents(cart_count(), cart_total_cents());
}

/** Item discount from the applied coupon (0 when none). */
function cart_discount_cents(): int {
    $coupon = cart_coupon();
    return $coupon ? coupon_discount_cents($coupon, cart_total_cents()) : 0;
}

function cart_grand_total_cents(): int {
    return max(0, cart_total_cents() - cart_discount_cents()) + cart_shipping_cents();
}

/**
 * How many more dollars the cart needs to qualify for free shipping.
 * Returns 0 once the threshold is met (or cart is empty).
 */
function cart_free_shipping_remaining_cents(): int {
    $subtotal = cart_total_cents();
    if ($subtotal === 0) return SHIPPING_FREE_THRESHOLD_CENTS;
    return max(0, SHIPPING_FREE_THRESHOLD_CENTS - $subtotal);
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
 * Stable suggestion strip: persists the chosen doll IDs in the session and
 * returns the same lineup across reloads, only swapping out IDs that are
 * no longer eligible (sold, deleted, or now in the cart). Tops the list
 * back up to $limit with fresh random picks when the eligible count drops
 * (e.g. after the buyer adds one to the cart).
 *
 * Returns enriched rows (with thumb_url) ready to render.
 */
function cart_stable_suggestions(int $limit = 5): array {
    $stored = $_SESSION[CART_SUGGESTION_SESSION_KEY] ?? [];
    if (!is_array($stored)) $stored = [];
    $stored = array_values(array_unique(array_map('intval', $stored)));

    $cartIds = cart_ids();
    // Drop any stored IDs that are now in the cart.
    $stored = array_values(array_filter($stored, fn($id) => !in_array($id, $cartIds, true)));

    // Validate availability against the DB and keep the original order.
    $byId = [];
    if ($stored) {
        $place = implode(',', array_fill(0, count($stored), '?'));
        $stmt = db()->prepare("
            SELECT id, slug, title, price_cents,
              (SELECT filename FROM product_images
                 WHERE product_id = products.id
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 1) AS thumb
            FROM products
            WHERE status = 'available' AND id IN ($place)
        ");
        $stmt->execute($stored);
        foreach ($stmt->fetchAll() as $r) $byId[(int)$r['id']] = $r;
    }
    $rows = [];
    foreach ($stored as $id) if (isset($byId[$id])) $rows[] = $byId[$id];

    // Trim down if the stored lineup is larger than the requested limit
    // (e.g. we used to suggest 5 and now suggest 3).
    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
    }

    // Top up with fresh random picks if we're under the target.
    $need = $limit - count($rows);
    if ($need > 0) {
        $exclude = array_unique(array_merge(
            $cartIds,
            array_map(fn($r) => (int)$r['id'], $rows)
        ));
        $sql = "SELECT id, slug, title, price_cents,
                  (SELECT filename FROM product_images
                     WHERE product_id = products.id
                     ORDER BY sort_order ASC, id ASC
                     LIMIT 1) AS thumb
                FROM products WHERE status = 'available'";
        $params = [];
        if ($exclude) {
            $place = implode(',', array_fill(0, count($exclude), '?'));
            $sql .= " AND id NOT IN ($place)";
            $params = $exclude;
        }
        $sql .= ' ORDER BY RAND() LIMIT ' . $need;
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) $rows[] = $r;
    }

    // Persist the final lineup.
    $_SESSION[CART_SUGGESTION_SESSION_KEY] = array_map(fn($r) => (int)$r['id'], $rows);

    // Enrich with display fields.
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

function cart_reset_suggestions(): void {
    unset($_SESSION[CART_SUGGESTION_SESSION_KEY]);
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
