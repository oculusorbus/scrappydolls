<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$pageTitle = 'Shop available dolls';
$pageDesc  = 'One-of-a-kind handmade cloth dolls and memory dolls by Kanda Kay. Shop the dolls available right now.';

$sort = ($_GET['sort'] ?? '') === 'popular' ? 'popular' : 'newest';

$orderBy = $sort === 'popular'
    ? 'p.featured DESC, views_30d DESC, p.created_at DESC'
    : 'p.featured DESC, p.created_at DESC';

$sqlWithViews = "
  SELECT p.id, p.slug, p.title, p.price_cents, p.featured, p.created_at,
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
  WHERE p.status = 'available'
  ORDER BY $orderBy
";

try {
    $rows = db()->query($sqlWithViews)->fetchAll();
} catch (Throwable $e) {
    // page_views or featured column missing — fall back to a basic query
    error_log('shop listing fallback: ' . $e->getMessage());
    $rows = db()->query("
      SELECT p.id, p.slug, p.title, p.price_cents, p.created_at,
        (SELECT filename FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb,
        0 AS views_30d
      FROM products p
      WHERE p.status = 'available'
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

    <?php if ($rows): ?>
      <nav class="shop-filters" aria-label="Sort">
        <a href="/shop/" class="<?= $sort === 'newest' ? 'is-active' : '' ?>">Newest</a>
        <a href="/shop/?sort=popular" class="<?= $sort === 'popular' ? 'is-active' : '' ?>">Most popular</a>
      </nav>
    <?php endif; ?>

    <?php if (!$rows): ?>
      <div class="empty-shop">
        <h3>No dolls available right now</h3>
        <p>New work appears first on Facebook — <a href="https://www.facebook.com/kandakayartist/" rel="noopener">follow along</a> to see them as they're finished.</p>
      </div>
    <?php else: ?>
      <div class="shop-grid">
        <?php foreach ($rows as $r):
          $isNew = product_is_new($r['created_at'] ?? null, 14);
          $tier  = product_popularity_tier((int)($r['views_30d'] ?? 0));
        ?>
          <a class="shop-card" href="/shop/product.php?slug=<?= h(urlencode($r['slug'])) ?>">
            <div class="img">
              <?php if ($r['thumb']): ?>
                <img src="<?= h(thumb_url($r['thumb'])) ?>" alt="<?= h($r['title']) ?>" loading="lazy">
              <?php endif; ?>
              <?php if ($isNew || $tier > 0): ?>
                <div class="card-badges">
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
              <span class="price"><?= fmt_price((int)$r['price_cents']) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
