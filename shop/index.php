<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$pageTitle = 'Shop available dolls';
$pageDesc  = 'One-of-a-kind handmade cloth dolls and memory dolls by Kanda Kay. Shop the dolls available right now.';

$validSorts = ['all', 'newest', 'popular', 'sold'];
$sort = in_array($_GET['sort'] ?? '', $validSorts, true) ? $_GET['sort'] : 'all';

switch ($sort) {
    case 'newest':
        $where = "p.status = 'available'";
        $orderBy = 'p.featured DESC, p.created_at DESC';
        break;
    case 'popular':
        $where = "p.status = 'available'";
        $orderBy = 'p.featured DESC, views_30d DESC, p.created_at DESC';
        break;
    case 'sold':
        $where = "p.status = 'sold'";
        $orderBy = 'p.sold_at DESC, p.created_at DESC';
        break;
    case 'all':
    default:
        // Available first (newest within), then sold (most recently sold first).
        $where = "p.status IN ('available', 'sold')";
        $orderBy = "CASE WHEN p.status = 'available' THEN 0 ELSE 1 END,
                    p.featured DESC,
                    CASE WHEN p.status = 'sold' THEN p.sold_at END DESC,
                    p.created_at DESC";
}

$sqlWithViews = "
  SELECT p.id, p.slug, p.title, p.price_cents, p.featured, p.status, p.created_at,
    (SELECT filename FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb,
    COALESCE(v.views_30d, 0) AS views_30d
  FROM products p
  LEFT JOIN (
    SELECT product_id, COUNT(*) AS views_30d
    FROM page_views
    WHERE product_id IS NOT NULL
      AND created_at >= NOW() - INTERVAL 30 DAY
    GROUP BY product_id
  ) v ON v.product_id = p.id
  WHERE $where
  ORDER BY $orderBy
";

try {
    $rows = db()->query($sqlWithViews)->fetchAll();
} catch (Throwable $e) {
    // page_views or featured column missing — fall back to a basic query
    error_log('shop listing fallback: ' . $e->getMessage());
    $rows = db()->query("
      SELECT p.id, p.slug, p.title, p.price_cents, p.status, p.created_at,
        (SELECT filename FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb,
        0 AS views_30d
      FROM products p
      WHERE $where
      ORDER BY p.created_at DESC
    ")->fetchAll();
}

track_view('/shop/');

require __DIR__ . '/header.php';
?>

<section>
  <div class="wrap">
    <div class="shop-head">
      <p class="eyebrow">Available now</p>
      <h1 class="h-display">Take one home.</h1>
      <p class="lede" style="margin:1rem auto 0">Each Scrappy Doll is one of a kind — when she's gone, she's gone. Pick the doll who's calling to you.</p>
    </div>

    <nav class="shop-filters" aria-label="Filter">
      <a href="/shop/"              class="<?= $sort === 'all'     ? 'is-active' : '' ?>">All</a>
      <a href="/shop/?sort=newest"  class="<?= $sort === 'newest'  ? 'is-active' : '' ?>">Newest</a>
      <a href="/shop/?sort=popular" class="<?= $sort === 'popular' ? 'is-active' : '' ?>">Most popular</a>
      <a href="/shop/?sort=sold"    class="<?= $sort === 'sold'    ? 'is-active' : '' ?>">Sold out</a>
    </nav>

    <?php if (!$rows): ?>
      <div class="empty-shop">
        <?php if ($sort === 'sold'): ?>
          <h3>No sold dolls yet</h3>
          <p>Once a doll finds her home, she'll show up here.</p>
        <?php else: ?>
          <h3>No dolls available right now</h3>
          <p>New work appears first on Facebook — <a href="https://www.facebook.com/kandakayartist/" rel="noopener">follow along</a> to see them as they're finished.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="shop-grid">
        <?php foreach ($rows as $r):
          $isSold = ($r['status'] ?? 'available') === 'sold';
          // NEW / popularity badges only apply to currently-available dolls.
          $isNew = !$isSold && product_is_new($r['created_at'] ?? null, 14);
          $tier  = $isSold ? 0 : product_popularity_tier((int)($r['views_30d'] ?? 0));
        ?>
          <a class="shop-card <?= $isSold ? 'is-sold' : '' ?>" href="/shop/product.php?slug=<?= h(urlencode($r['slug'])) ?>">
            <div class="img">
              <?php if ($r['thumb']): ?>
                <img src="<?= h(thumb_url($r['thumb'])) ?>" alt="<?= h($r['title']) ?>" loading="lazy">
              <?php endif; ?>
              <?php if ($isSold || $isNew || $tier > 0): ?>
                <div class="card-badges">
                  <?php if ($isSold): ?>
                    <span class="card-badge badge-sold">Sold</span>
                  <?php endif; ?>
                  <?php if ($isNew): ?>
                    <span class="card-badge badge-new">New</span>
                  <?php endif; ?>
                  <?php if ($tier > 0): ?>
                    <span class="card-badge badge-popular tier-<?= $tier ?>" title="Popular this month">
                      <?= popularity_flames_html($tier) ?>
                    </span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="meta">
              <h3><?= h($r['title']) ?></h3>
              <?php if ($isSold): ?>
                <span class="price price-sold">Sold</span>
              <?php else: ?>
                <span class="price"><?= fmt_price((int)$r['price_cents']) ?></span>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
