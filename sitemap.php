<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim((string)config('site_url'), '/');
$today = date('Y-m-d');

$products = db()->query("
    SELECT p.slug, p.title, p.updated_at, p.created_at,
      (SELECT filename FROM product_images
         WHERE product_id = p.id
         ORDER BY sort_order ASC, id ASC
         LIMIT 1) AS thumb
    FROM products p
    WHERE p.status = 'available'
    ORDER BY p.created_at DESC
")->fetchAll();

// Static landing-page images that show up on the home page hero/about.
$landingImages = [
    ['file' => 'doll-rainbow-hair.jpg',   'caption' => 'Handmade Scrappy Doll by Kanda Kay with multicolored yarn hair, embroidered features, and a vibrant patchwork dress with lace trim.'],
    ['file' => 'doll-collection.jpg',     'caption' => 'A wall display of dozens of handmade Scrappy Dolls by Kanda Kay.'],
    ['file' => 'doll-feature-horns.jpg',  'caption' => 'Handmade Scrappy Doll with curly brown hair, a poppy headband, and a green floral dress.'],
    ['file' => 'doll-yellow-yarn.jpg',    'caption' => 'Scrappy Doll with yellow yarn hair, a burlap face, and a patterned orange dress.'],
    ['file' => 'doll-route-66.jpg',       'caption' => 'Scrappy Doll wearing a Route 66 Motel Court t-shirt with black yarn pigtails.'],
    ['file' => 'doll-patriotic.jpg',      'caption' => 'Patriotic Scrappy Doll in red, white, and blue with a star headband and beaded jewelry.'],
    ['file' => 'doll-orange-dress.jpg',   'caption' => 'Scrappy Doll with black curly yarn hair and an orange and blue speckled dress.'],
    ['file' => 'doll-owl.jpg',            'caption' => 'Handmade owl Scrappy Doll in blue tweed fabric with burlap eyes and a felt beak.'],
    ['file' => 'doll-mouse-pair.jpg',     'caption' => 'A pair of mouse-eared Scrappy Dolls — one in plaid, one in green floral.'],
    ['file' => 'doll-dog-pair.jpg',       'caption' => 'Two handmade Scrappy Dog Dolls — a brown puppy and a tan terrier with button eyes.'],
    ['file' => 'size.png',                'caption' => 'A handmade Scrappy Doll standing beside a 12-inch wooden ruler, showing the doll is approximately one foot tall.'],
];

function _xe(string $s): string {
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <!-- Landing page -->
  <url>
    <loc><?= _xe($base . '/') ?></loc>
    <lastmod><?= _xe($today) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
    <?php foreach ($landingImages as $img): ?>
    <image:image>
      <image:loc><?= _xe($base . '/images/' . $img['file']) ?></image:loc>
      <image:caption><?= _xe($img['caption']) ?></image:caption>
    </image:image>
    <?php endforeach; ?>
  </url>

  <!-- Shop listing -->
  <url>
    <loc><?= _xe($base . '/shop/') ?></loc>
    <lastmod><?= _xe($today) ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>

  <!-- Each available doll -->
  <?php foreach ($products as $p):
    $url = $base . '/shop/product.php?slug=' . rawurlencode($p['slug']);
    $lastmod = !empty($p['updated_at']) ? substr((string)$p['updated_at'], 0, 10) : $today;
  ?>
  <url>
    <loc><?= _xe($url) ?></loc>
    <lastmod><?= _xe($lastmod) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
    <?php if (!empty($p['thumb'])): ?>
    <image:image>
      <image:loc><?= _xe($base . '/uploads/' . $p['thumb']) ?></image:loc>
      <image:caption><?= _xe($p['title']) ?></image:caption>
    </image:image>
    <?php endif; ?>
  </url>
  <?php endforeach; ?>

</urlset>
