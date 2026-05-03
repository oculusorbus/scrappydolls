<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$pageTitle = 'Shop available dolls';
$pageDesc  = 'One-of-a-kind handmade cloth dolls and memory dolls by Kanda Kay. Shop the dolls available right now.';

$rows = db()->query("
  SELECT p.id, p.slug, p.title, p.price_cents,
    (SELECT filename FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb
  FROM products p
  WHERE p.status = 'available'
  ORDER BY p.created_at DESC
")->fetchAll();

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

    <?php if (!$rows): ?>
      <div class="empty-shop">
        <h3>No dolls available right now</h3>
        <p>New work appears first on Facebook — <a href="https://www.facebook.com/kandakayartist/" rel="noopener">follow along</a> to see them as they're finished.</p>
      </div>
    <?php else: ?>
      <div class="shop-grid">
        <?php foreach ($rows as $r): ?>
          <a class="shop-card" href="/shop/product.php?slug=<?= h(urlencode($r['slug'])) ?>">
            <div class="img">
              <?php if ($r['thumb']): ?>
                <img src="<?= h(thumb_url($r['thumb'])) ?>" alt="<?= h($r['title']) ?>" loading="lazy">
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
