<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();

$rangeKey = $_GET['range'] ?? '30d';
$r = range_resolve($rangeKey);
$pdo = db();

// Detect missing analytics tables (migration 002 not yet applied)
try {
    $pdo->query('SELECT 1 FROM page_views LIMIT 1');
    $pdo->query('SELECT 1 FROM order_intents LIMIT 1');
    $pdo->query('SELECT utm_source FROM orders LIMIT 1');
} catch (Throwable $e) {
    $page = 'reports';
    $title = 'Reports — migration needed';
    require __DIR__ . '/header.php';
    ?>
    <h1 class="page-title">Reports</h1>
    <div class="flash flash-info">
      <strong>One step left.</strong> The analytics tables haven't been created yet.
      Run this migration on your database to enable Reports:
    </div>
    <pre style="background:var(--paper);border:1px solid var(--rule);border-radius:10px;padding:1rem 1.25rem;font-size:.85rem;overflow:auto"><code>mysql -u &lt;user&gt; -p &lt;dbname&gt; &lt; sql/migrations/002_add_analytics.sql</code></pre>
    <p style="color:var(--ink-muted);font-size:.9rem">It's safe to run on a populated database — only adds new tables and columns.</p>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

/* ---------------------------------------------------------------- */
/* KPIs (current vs previous period)                                */
/* ---------------------------------------------------------------- */

function kpi_block(PDO $pdo, string $from, string $to): array {
    $rev = (int)$pdo->query("SELECT COALESCE(SUM(amount_cents),0) FROM orders WHERE status IN ('paid','shipped') AND paid_at BETWEEN " . $pdo->quote($from) . " AND " . $pdo->quote($to))->fetchColumn();
    $orders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('paid','shipped') AND paid_at BETWEEN " . $pdo->quote($from) . " AND " . $pdo->quote($to))->fetchColumn();
    $visitors = (int)$pdo->query("SELECT COUNT(DISTINCT session_hash) FROM page_views WHERE created_at BETWEEN " . $pdo->quote($from) . " AND " . $pdo->quote($to))->fetchColumn();
    $productViewers = (int)$pdo->query("SELECT COUNT(DISTINCT session_hash) FROM page_views WHERE product_id IS NOT NULL AND created_at BETWEEN " . $pdo->quote($from) . " AND " . $pdo->quote($to))->fetchColumn();
    $intents = (int)$pdo->query("SELECT COUNT(DISTINCT session_hash) FROM order_intents WHERE created_at BETWEEN " . $pdo->quote($from) . " AND " . $pdo->quote($to))->fetchColumn();
    $aov = $orders > 0 ? (int)round($rev / $orders) : 0;
    $convRate = $visitors > 0 ? round(($orders / $visitors) * 100, 2) : 0.0;

    $sellTimeStmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, p.created_at, p.sold_at)) FROM products p WHERE p.sold_at BETWEEN :f AND :t");
    $sellTimeStmt->execute([':f' => $from, ':t' => $to]);
    $avgSellHrs = (float)($sellTimeStmt->fetchColumn() ?: 0);

    $shipTimeStmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, paid_at, shipped_at)) FROM orders WHERE shipped_at IS NOT NULL AND shipped_at BETWEEN :f AND :t");
    $shipTimeStmt->execute([':f' => $from, ':t' => $to]);
    $avgShipHrs = (float)($shipTimeStmt->fetchColumn() ?: 0);

    return [
        'revenue'           => $rev,
        'orders'            => $orders,
        'visitors'          => $visitors,
        'product_viewers'   => $productViewers,
        'intents'           => $intents,
        'aov'               => $aov,
        'conversion_rate'   => $convRate,
        'avg_sell_hours'    => $avgSellHrs,
        'avg_ship_hours'    => $avgShipHrs,
    ];
}

$cur  = kpi_block($pdo, $r['from'],      $r['to']);
$prev = kpi_block($pdo, $r['prev_from'], $r['prev_to']);

/* ---------------------------------------------------------------- */
/* Daily revenue series                                             */
/* ---------------------------------------------------------------- */

$dailyStmt = $pdo->prepare("
  SELECT DATE(paid_at) AS d,
         COALESCE(SUM(amount_cents), 0) AS rev,
         COUNT(*) AS orders
  FROM orders
  WHERE status IN ('paid','shipped')
    AND paid_at BETWEEN :f AND :t
  GROUP BY DATE(paid_at)
  ORDER BY DATE(paid_at) ASC
");
$dailyStmt->execute([':f' => $r['from'], ':t' => $r['to']]);
$dailyRows = $dailyStmt->fetchAll();

$daily = [];
$cursor = new DateTimeImmutable($r['from']);
$endC   = new DateTimeImmutable($r['to']);
$byDate = [];
foreach ($dailyRows as $row) $byDate[$row['d']] = $row;
while ($cursor <= $endC) {
    $key = $cursor->format('Y-m-d');
    $daily[] = [
        'date'   => $key,
        'rev'    => isset($byDate[$key]) ? (int)$byDate[$key]['rev'] / 100 : 0,
        'orders' => isset($byDate[$key]) ? (int)$byDate[$key]['orders'] : 0,
    ];
    $cursor = $cursor->modify('+1 day');
}

/* ---------------------------------------------------------------- */
/* Top dolls (by revenue, then orders, then views)                  */
/* ---------------------------------------------------------------- */

$topStmt = $pdo->prepare("
  SELECT
    p.id, p.title, p.slug, p.status, p.price_cents, p.created_at,
    (SELECT filename FROM product_images WHERE product_id=p.id ORDER BY sort_order ASC, id ASC LIMIT 1) AS thumb,
    COALESCE(v.views, 0)        AS views,
    COALESCE(v.viewers, 0)      AS viewers,
    COALESCE(i.intents, 0)      AS intents,
    COALESCE(o.units, 0)        AS units,
    COALESCE(o.revenue, 0)      AS revenue
  FROM products p
  LEFT JOIN (
    SELECT product_id, COUNT(*) AS views, COUNT(DISTINCT session_hash) AS viewers
    FROM page_views
    WHERE product_id IS NOT NULL AND created_at BETWEEN :f1 AND :t1
    GROUP BY product_id
  ) v ON v.product_id = p.id
  LEFT JOIN (
    SELECT product_id, COUNT(*) AS intents
    FROM order_intents
    WHERE created_at BETWEEN :f2 AND :t2
    GROUP BY product_id
  ) i ON i.product_id = p.id
  LEFT JOIN (
    SELECT product_id, COUNT(*) AS units, SUM(amount_cents) AS revenue
    FROM orders
    WHERE status IN ('paid','shipped') AND paid_at BETWEEN :f3 AND :t3
    GROUP BY product_id
  ) o ON o.product_id = p.id
  WHERE COALESCE(o.revenue,0) + COALESCE(v.views,0) + COALESCE(i.intents,0) > 0
  ORDER BY revenue DESC, units DESC, views DESC
  LIMIT 10
");
$topStmt->execute([
    ':f1'=>$r['from'], ':t1'=>$r['to'],
    ':f2'=>$r['from'], ':t2'=>$r['to'],
    ':f3'=>$r['from'], ':t3'=>$r['to'],
]);
$topProducts = $topStmt->fetchAll();

/* ---------------------------------------------------------------- */
/* Channels (by revenue, w/ "direct" bucket for null UTMs)          */
/* ---------------------------------------------------------------- */

$chanStmt = $pdo->prepare("
  SELECT
    COALESCE(NULLIF(LOWER(utm_source), ''), LOWER(referrer_host), 'direct') AS channel,
    COUNT(*) AS orders,
    SUM(amount_cents) AS revenue
  FROM orders
  WHERE status IN ('paid','shipped') AND paid_at BETWEEN :f AND :t
  GROUP BY channel
  ORDER BY revenue DESC
  LIMIT 10
");
$chanStmt->execute([':f' => $r['from'], ':t' => $r['to']]);
$channels = $chanStmt->fetchAll();

/* ---------------------------------------------------------------- */
/* Geography (US states from shipping)                              */
/* ---------------------------------------------------------------- */

$geoStmt = $pdo->prepare("
  SELECT
    JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.address.admin_area_1')) AS state,
    JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.address.country_code')) AS country,
    COUNT(*) AS orders,
    SUM(amount_cents) AS revenue
  FROM orders
  WHERE status IN ('paid','shipped')
    AND shipping_address IS NOT NULL
    AND paid_at BETWEEN :f AND :t
  GROUP BY state, country
  ORDER BY revenue DESC
  LIMIT 10
");
$geoStmt->execute([':f' => $r['from'], ':t' => $r['to']]);
$geo = $geoStmt->fetchAll();

/* ---------------------------------------------------------------- */
/* Operational                                                      */
/* ---------------------------------------------------------------- */

$opsRefunded = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='refunded' AND paid_at BETWEEN " . $pdo->quote($r['from']) . " AND " . $pdo->quote($r['to']))->fetchColumn();
$opsTotalPaid = $cur['orders'] + $opsRefunded;
$refundRate  = $opsTotalPaid > 0 ? round(($opsRefunded / $opsTotalPaid) * 100, 1) : 0.0;

$repeatStmt = $pdo->prepare("
  SELECT COUNT(*) FROM (
    SELECT customer_email
    FROM orders
    WHERE status IN ('paid','shipped') AND customer_email IS NOT NULL AND customer_email <> ''
    GROUP BY customer_email
    HAVING COUNT(*) >= 2
  ) t
");
$repeatStmt->execute();
$repeatBuyers = (int)$repeatStmt->fetchColumn();
$totalBuyersStmt = $pdo->query("SELECT COUNT(DISTINCT customer_email) FROM orders WHERE customer_email IS NOT NULL AND customer_email <> '' AND status IN ('paid','shipped')");
$totalBuyers = (int)$totalBuyersStmt->fetchColumn();
$repeatRate = $totalBuyers > 0 ? round(($repeatBuyers / $totalBuyers) * 100, 1) : 0.0;

$inventoryStmt = $pdo->query("
  SELECT
    SUM(status='available') AS available,
    SUM(status='draft')     AS drafts,
    SUM(status='sold')      AS sold
  FROM products
");
$inventory = $inventoryStmt->fetch() ?: ['available'=>0,'drafts'=>0,'sold'=>0];

$agingStmt = $pdo->query("
  SELECT id, slug, title, created_at, price_cents,
    DATEDIFF(NOW(), created_at) AS days_listed
  FROM products
  WHERE status='available' AND DATEDIFF(NOW(), created_at) >= 30
  ORDER BY created_at ASC
  LIMIT 10
");
$aging = $agingStmt->fetchAll();

/* ---------------------------------------------------------------- */
/* Smart insights                                                   */
/* ---------------------------------------------------------------- */

$insights = [];
if ($cur['orders'] >= 1) {
    // Best day of week
    $dowStmt = $pdo->prepare("
      SELECT DAYNAME(paid_at) AS dow, COUNT(*) AS c, SUM(amount_cents) AS rev
      FROM orders WHERE status IN ('paid','shipped') AND paid_at BETWEEN :f AND :t
      GROUP BY DAYOFWEEK(paid_at), DAYNAME(paid_at)
      ORDER BY rev DESC LIMIT 1
    ");
    $dowStmt->execute([':f'=>$r['from'], ':t'=>$r['to']]);
    if ($best = $dowStmt->fetch()) {
        $insights[] = "<strong>{$best['dow']}</strong> is your best sales day this period — "
                    . fmt_price((int)$best['rev']) . " across {$best['c']} order"
                    . ($best['c']>1?'s':'') . '.';
    }
}
if ($cur['visitors'] >= 20) {
    $delta = pct_delta((float)$cur['conversion_rate'], (float)$prev['conversion_rate']);
    $dir = $delta === null ? '' : ($delta >= 0 ? "up {$delta}%" : "down " . abs($delta) . '%');
    $insights[] = "Conversion rate is <strong>{$cur['conversion_rate']}%</strong>"
                . ($dir ? " ($dir vs prior period)" : '') . '.';
}
if (!empty($channels) && $channels[0]['revenue'] > 0) {
    $top = $channels[0];
    $share = round(((int)$top['revenue'] / max(1, $cur['revenue'])) * 100);
    $insights[] = "Top channel is <strong>" . h($top['channel']) . "</strong> "
                . "— $share% of revenue ({$top['orders']} orders).";
}
if (count($aging) > 0) {
    $oldest = $aging[0];
    $insights[] = "<strong>" . h($oldest['title']) . "</strong> has been listed for "
                . (int)$oldest['days_listed'] . ' days. Consider featuring her or revisiting price.';
}
if ($cur['intents'] > $cur['orders']) {
    $abandoned = $cur['intents'] - $cur['orders'];
    $abPct = $cur['intents'] > 0 ? round(($abandoned / $cur['intents']) * 100) : 0;
    $insights[] = "<strong>$abandoned</strong> shopper" . ($abandoned>1?'s':'') . " started checkout but didn't finish ($abPct% abandonment).";
}
if ($repeatBuyers >= 1) {
    $insights[] = "Repeat buyers: <strong>$repeatBuyers</strong> of $totalBuyers customers ($repeatRate%) have come back for a second doll.";
}

$page = 'reports';
$title = 'Reports';
require __DIR__ . '/header.php';

function delta_html(float $cur, float $prev): string {
    $d = pct_delta($cur, $prev);
    if ($d === null) return '<span class="delta flat">— no prior</span>';
    if ($d > 0)      return '<span class="delta up">▲ ' . abs($d) . '%</span>';
    if ($d < 0)      return '<span class="delta down">▼ ' . abs($d) . '%</span>';
    return '<span class="delta flat">— flat</span>';
}
?>

<div class="page-head">
  <div>
    <h1 class="page-title" style="margin-bottom:.4rem">Reports</h1>
    <p style="margin:0;color:var(--ink-muted);font-size:.9rem"><?= h($r['label']) ?> · <?= h(date('M j', strtotime($r['from']))) ?> – <?= h(date('M j, Y', strtotime($r['to']))) ?></p>
  </div>
</div>

<nav class="range-tabs" aria-label="Date range">
  <?php foreach ([['7d','7 days'],['30d','30 days'],['90d','90 days'],['ytd','YTD'],['all','All time']] as [$k,$lbl]): ?>
    <a href="?range=<?= h($k) ?>" class="<?= $r['key']===$k?'is-active':'' ?>"><?= h($lbl) ?></a>
  <?php endforeach; ?>
</nav>

<div class="kpi-grid">
  <div class="kpi">
    <span class="label">Revenue</span>
    <span class="value"><?= fmt_price($cur['revenue']) ?></span>
    <?= delta_html((float)$cur['revenue'], (float)$prev['revenue']) ?>
  </div>
  <div class="kpi">
    <span class="label">Orders</span>
    <span class="value"><?= number_format($cur['orders']) ?></span>
    <?= delta_html((float)$cur['orders'], (float)$prev['orders']) ?>
  </div>
  <div class="kpi">
    <span class="label">Avg order value</span>
    <span class="value"><?= fmt_price($cur['aov']) ?></span>
    <?= delta_html((float)$cur['aov'], (float)$prev['aov']) ?>
  </div>
  <div class="kpi">
    <span class="label">Visitors</span>
    <span class="value"><?= number_format($cur['visitors']) ?></span>
    <?= delta_html((float)$cur['visitors'], (float)$prev['visitors']) ?>
  </div>
  <div class="kpi">
    <span class="label">Conversion</span>
    <span class="value"><?= h((string)$cur['conversion_rate']) ?>%</span>
    <?= delta_html((float)$cur['conversion_rate'], (float)$prev['conversion_rate']) ?>
  </div>
  <div class="kpi">
    <span class="label">Avg time to ship</span>
    <span class="value"><?= $cur['avg_ship_hours'] > 0 ? round($cur['avg_ship_hours']/24, 1) . 'd' : '—' ?></span>
    <span class="sub">paid → shipped</span>
  </div>
</div>

<?php if ($insights): ?>
  <div class="insights">
    <h2>Highlights</h2>
    <ul>
      <?php foreach ($insights as $line): ?>
        <li><?= $line /* contains intentional inline <strong> */ ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="report-card" style="margin-bottom:1.25rem">
  <h2>Revenue trend</h2>
  <p class="h-sub">Daily revenue · <?= h($r['label']) ?></p>
  <div class="chart-host"><canvas id="revChart"></canvas></div>
</div>

<div class="report-grid cols-3">
  <div class="report-card">
    <h2>Sales funnel</h2>
    <p class="h-sub">From visitor to paid order · unique sessions</p>
    <?php
      $stages = [
          ['Visitors',           (int)$cur['visitors'],         null],
          ['Viewed a doll',      (int)$cur['product_viewers'],  $cur['visitors']],
          ['Started checkout',   (int)$cur['intents'],          $cur['product_viewers']],
          ['Completed purchase', (int)$cur['orders'],           $cur['intents']],
      ];
      $maxStage = max(1, ...array_column($stages, 1));
    ?>
    <div class="funnel">
      <?php foreach ($stages as $i => [$label, $count, $base]): ?>
        <?php
          $pct = $maxStage > 0 ? max(8, ($count / $maxStage) * 100) : 8;
          $conv = ($base !== null && $base > 0) ? round(($count / $base) * 100, 1) . '%' : null;
          $dark = $i === count($stages) - 1;
        ?>
        <div class="step <?= $dark ? 'dark' : '' ?>">
          <div class="fill" style="width: <?= $pct ?>%"></div>
          <div class="row">
            <span class="label"><?= h($label) ?></span>
            <span>
              <span class="count"><?= number_format($count) ?></span>
              <?php if ($conv !== null): ?><span class="conv"><?= h($conv) ?></span><?php endif; ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="report-card">
    <h2>Channels</h2>
    <p class="h-sub">Revenue by acquisition source</p>
    <?php if (!$channels): ?>
      <div class="report-empty">No attribution data yet.</div>
    <?php else: ?>
      <?php $maxChan = max(1, ...array_column($channels, 'revenue')); ?>
      <div class="bar-list">
        <?php foreach ($channels as $c): ?>
          <div class="bar">
            <span class="label"><?= h($c['channel']) ?></span>
            <span class="track"><span class="fill" style="width: <?= round(((int)$c['revenue']/$maxChan)*100) ?>%"></span></span>
            <span class="val"><?= fmt_price((int)$c['revenue']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="h-sub" style="margin-top:1rem">Tag your campaigns with <code>?utm_source=facebook&amp;utm_medium=post&amp;utm_campaign=fall-launch</code> to see attribution here.</p>
    <?php endif; ?>
  </div>
</div>

<div class="report-grid cols-2">
  <div class="report-card">
    <h2>Top dolls</h2>
    <p class="h-sub">Performance for the period</p>
    <?php if (!$topProducts): ?>
      <div class="report-empty">Once you have orders or page views, your bestsellers will appear here.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th></th><th>Title</th><th style="text-align:right">Views</th>
            <th style="text-align:right">Buy clicks</th><th style="text-align:right">Sold</th>
            <th style="text-align:right">Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topProducts as $p): ?>
            <tr>
              <td class="thumb">
                <?php if ($p['thumb']): ?><img src="<?= h(asset_url($p['thumb'])) ?>" alt=""><?php endif; ?>
              </td>
              <td>
                <a href="/admin/edit.php?id=<?= (int)$p['id'] ?>"><?= h($p['title']) ?></a><br>
                <span class="badge badge-<?= h($p['status']) ?>"><?= h($p['status']) ?></span>
              </td>
              <td style="text-align:right;font-variant-numeric:tabular-nums"><?= number_format((int)$p['views']) ?></td>
              <td style="text-align:right;font-variant-numeric:tabular-nums"><?= number_format((int)$p['intents']) ?></td>
              <td style="text-align:right;font-variant-numeric:tabular-nums"><?= number_format((int)$p['units']) ?></td>
              <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt_price((int)$p['revenue']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="report-card">
    <h2>Where buyers ship</h2>
    <p class="h-sub">Top regions by revenue</p>
    <?php if (!$geo): ?>
      <div class="report-empty">No shipping data yet.</div>
    <?php else: ?>
      <?php $maxGeo = max(1, ...array_column($geo, 'revenue')); ?>
      <div class="bar-list">
        <?php foreach ($geo as $g): ?>
          <div class="bar">
            <span class="label"><?= h(trim(($g['state'] ?? '') . ' · ' . ($g['country'] ?? ''), ' ·')) ?: '—' ?></span>
            <span class="track"><span class="fill" style="width: <?= round(((int)$g['revenue']/$maxGeo)*100) ?>%"></span></span>
            <span class="val"><?= fmt_price((int)$g['revenue']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="report-grid cols-2">
  <div class="report-card">
    <h2>Operations</h2>
    <p class="h-sub">Fulfillment & customer signals</p>
    <div class="ops-strip">
      <div class="ops-stat"><div class="v"><?= $cur['avg_sell_hours'] > 0 ? round($cur['avg_sell_hours']/24, 1) . ' days' : '—' ?></div><div class="l">Avg time to sell</div></div>
      <div class="ops-stat"><div class="v"><?= $cur['avg_ship_hours'] > 0 ? round($cur['avg_ship_hours']/24, 1) . ' days' : '—' ?></div><div class="l">Avg time to ship</div></div>
      <div class="ops-stat"><div class="v"><?= h((string)$refundRate) ?>%</div><div class="l">Refund rate</div></div>
      <div class="ops-stat"><div class="v"><?= h((string)$repeatRate) ?>%</div><div class="l">Repeat customers</div></div>
    </div>
    <hr style="border:none;border-top:1px solid var(--rule);margin:1.4rem 0">
    <div class="ops-strip">
      <div class="ops-stat"><div class="v"><?= number_format((int)$inventory['available']) ?></div><div class="l">Available now</div></div>
      <div class="ops-stat"><div class="v"><?= number_format((int)$inventory['drafts']) ?></div><div class="l">In drafts</div></div>
      <div class="ops-stat"><div class="v"><?= number_format((int)$inventory['sold']) ?></div><div class="l">Sold (lifetime)</div></div>
    </div>
  </div>

  <div class="report-card">
    <h2>Aging inventory</h2>
    <p class="h-sub">Available dolls listed 30+ days</p>
    <?php if (!$aging): ?>
      <div class="report-empty">Nothing aging — every available doll has been listed less than 30 days. Keep going!</div>
    <?php else: ?>
      <table>
        <thead><tr><th>Doll</th><th style="text-align:right">Days listed</th><th style="text-align:right">Price</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($aging as $a): ?>
          <tr>
            <td><a href="/admin/edit.php?id=<?= (int)$a['id'] ?>"><?= h($a['title']) ?></a></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= (int)$a['days_listed'] ?></td>
            <td style="text-align:right;font-variant-numeric:tabular-nums"><?= fmt_price((int)$a['price_cents']) ?></td>
            <td style="text-align:right"><a class="btn btn-sm btn-ghost" href="/shop/product.php?slug=<?= h(urlencode($a['slug'])) ?>" target="_blank" rel="noopener">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"
        crossorigin="anonymous"></script>
<script>
(function () {
  if (!window.Chart) return;
  Chart.defaults.font.family = '"Inter", -apple-system, sans-serif';
  Chart.defaults.color = '#5a4a52';
  Chart.defaults.borderColor = '#ede0d2';

  var labels = <?= json_encode(array_map(fn($d) => date('M j', strtotime($d['date'])), $daily)) ?>;
  var revenues = <?= json_encode(array_map(fn($d) => (float)$d['rev'], $daily)) ?>;

  var ctx = document.getElementById('revChart').getContext('2d');
  var grad = ctx.createLinearGradient(0, 0, 0, 280);
  grad.addColorStop(0, 'rgba(177, 62, 84, 0.30)');
  grad.addColorStop(1, 'rgba(177, 62, 84, 0.02)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Revenue',
        data: revenues,
        borderColor: '#b13e54',
        backgroundColor: grad,
        borderWidth: 2,
        tension: 0.32,
        fill: true,
        pointRadius: 0,
        pointHoverRadius: 5,
        pointHoverBackgroundColor: '#b13e54',
        pointHoverBorderColor: '#fff',
        pointHoverBorderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1a1318',
          titleColor: '#faf3ee',
          bodyColor: '#faf3ee',
          padding: 10,
          callbacks: {
            label: function (ctx) {
              return '  $' + ctx.parsed.y.toFixed(2);
            }
          }
        }
      },
      scales: {
        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 16 } },
        y: { beginAtZero: true, ticks: { callback: function (v) { return '$' + v; } } }
      }
    }
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
