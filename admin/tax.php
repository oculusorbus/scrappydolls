<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
auth_require();

$pdo = db();

// Needs migration 009 (orders.tax_cents). Detect it up front so a missing
// migration shows a friendly message instead of a SQL error.
$migrationReady = true;
try {
    $pdo->query('SELECT tax_cents FROM orders LIMIT 1');
} catch (Throwable $e) {
    $migrationReady = false;
}

/* ------------------------------------------------------------------ */
/* Period model — aligned to Texas filing periods                     */
/* ------------------------------------------------------------------ */

const TX_TAX_STATE_FRACTION = 0.0625 / 0.0825; // state share of the 8.25%
const TX_ANNUAL_STATE_THRESHOLD_CENTS = 100000; // $1,000 state tax/yr → file yearly
const TX_MONTHLY_STATE_THRESHOLD_CENTS = 150000; // $1,500 state tax/quarter → monthly

/**
 * Resolve a (type, period) selection into concrete bounds + the filing due
 * date. Returns from/to (datetime strings), a label, the due date, and the
 * containing calendar year's bounds (used for the frequency-bracket hint).
 */
function tax_period_bounds(string $ptype, string $period): array {
    $thisYear = (int)date('Y');
    $mk = fn(string $d) => new DateTimeImmutable($d);

    if ($ptype === 'quarter' && preg_match('/^(\d{4})Q([1-4])$/', $period, $m)) {
        $y = (int)$m[1]; $q = (int)$m[2];
        $sm = ($q - 1) * 3 + 1;
        $start = $mk(sprintf('%04d-%02d-01', $y, $sm));
        $end   = $start->modify('+2 months')->modify('last day of this month');
        $label = "Q$q $y (" . $start->format('M') . '–' . $end->format('M Y') . ')';
    } elseif ($ptype === 'month' && preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
        $y = (int)$m[1];
        $start = $mk(sprintf('%04d-%02d-01', $y, (int)$m[2]));
        $end   = $start->modify('last day of this month');
        $label = $start->format('F Y');
    } else { // year (default)
        $y = preg_match('/^(\d{4})$/', $period) ? (int)$period : $thisYear;
        $start = $mk(sprintf('%04d-01-01', $y));
        $end   = $mk(sprintf('%04d-12-31', $y));
        $label = "Calendar year $y";
        $ptype = 'year';
    }

    // Due: the 20th of the month after the period ends.
    $due = $end->modify('first day of next month');
    $due = new DateTimeImmutable($due->format('Y-m') . '-20');

    return [
        'type'       => $ptype,
        'from'       => $start->format('Y-m-d') . ' 00:00:00',
        'to'         => $end->format('Y-m-d') . ' 23:59:59',
        'label'      => $label,
        'due_label'  => $due->format('F j, Y'),
        'year'       => $y,
        'year_from'  => sprintf('%04d-01-01 00:00:00', $y),
        'year_to'    => sprintf('%04d-12-31 23:59:59', $y),
    ];
}

/** Build the <select> options for the chosen period type, newest first. */
function tax_period_options(string $ptype, int $firstYear): array {
    $now = new DateTimeImmutable('now');
    $thisYear = (int)$now->format('Y');
    $opts = [];
    if ($ptype === 'quarter') {
        $curQ = (int)ceil((int)$now->format('n') / 3);
        for ($y = $thisYear; $y >= $firstYear; $y--) {
            $qStart = ($y === $thisYear) ? $curQ : 4;
            for ($q = $qStart; $q >= 1; $q--) $opts[] = ["{$y}Q{$q}", "Q$q $y"];
        }
    } elseif ($ptype === 'month') {
        $cursor = $now->modify('first day of this month');
        $stop   = new DateTimeImmutable(sprintf('%04d-01-01', $firstYear));
        while ($cursor >= $stop) {
            $opts[] = [$cursor->format('Y-m'), $cursor->format('F Y')];
            $cursor = $cursor->modify('-1 month');
        }
    } else {
        for ($y = $thisYear; $y >= $firstYear; $y--) $opts[] = ["$y", "$y"];
    }
    return $opts;
}

$ptype = in_array($_GET['ptype'] ?? '', ['year', 'quarter', 'month'], true) ? $_GET['ptype'] : 'year';

// Default selection for each type.
$defaults = [
    'year'    => date('Y'),
    'quarter' => date('Y') . 'Q' . (int)ceil((int)date('n') / 3),
    'month'   => date('Y-m'),
];
$period = (string)($_GET['period'] ?? $defaults[$ptype]);
$b = tax_period_bounds($ptype, $period);

$firstYear = (int)date('Y');
if ($migrationReady) {
    $minY = $pdo->query("SELECT MIN(YEAR(paid_at)) FROM orders WHERE status IN ('paid','shipped','refunded')")->fetchColumn();
    if ($minY) $firstYear = (int)$minY;
}

/* ------------------------------------------------------------------ */
/* Figures for the selected period                                    */
/* ------------------------------------------------------------------ */

$figures = [
    'total_sales' => 0, 'order_count' => 0,
    'taxable_sales' => 0, 'tax_collected' => 0, 'tx_orders' => 0,
    'refunded_tax' => 0, 'refunded_orders' => 0,
    'year_tax' => 0,
];
$detail = [];

if ($migrationReady) {
    // Total sales (ex-tax), all destinations.
    $t = $pdo->prepare("SELECT COALESCE(SUM(amount_cents - tax_cents),0) AS sales, COUNT(*) AS n
        FROM orders WHERE status IN ('paid','shipped') AND paid_at BETWEEN :f AND :t");
    $t->execute([':f' => $b['from'], ':t' => $b['to']]);
    $row = $t->fetch();
    $figures['total_sales'] = (int)$row['sales'];
    $figures['order_count'] = (int)$row['n'];

    // Taxable (TX) sales ex-tax + tax collected. tax_cents > 0 ⟺ TX/US ship-to.
    $tx = $pdo->prepare("SELECT COALESCE(SUM(amount_cents - tax_cents),0) AS taxable,
            COALESCE(SUM(tax_cents),0) AS tax, COUNT(*) AS n
        FROM orders WHERE status IN ('paid','shipped') AND tax_cents > 0 AND paid_at BETWEEN :f AND :t");
    $tx->execute([':f' => $b['from'], ':t' => $b['to']]);
    $row = $tx->fetch();
    $figures['taxable_sales'] = (int)$row['taxable'];
    $figures['tax_collected'] = (int)$row['tax'];
    $figures['tx_orders']     = (int)$row['n'];

    // Refunds (orders that were paid in this period and later refunded).
    $rf = $pdo->prepare("SELECT COALESCE(SUM(tax_cents),0) AS tax, COUNT(*) AS n
        FROM orders WHERE status = 'refunded' AND tax_cents > 0 AND paid_at BETWEEN :f AND :t");
    $rf->execute([':f' => $b['from'], ':t' => $b['to']]);
    $row = $rf->fetch();
    $figures['refunded_tax']    = (int)$row['tax'];
    $figures['refunded_orders'] = (int)$row['n'];

    // Calendar-year tax collected → state portion drives the filing bracket.
    $yr = $pdo->prepare("SELECT COALESCE(SUM(tax_cents),0)
        FROM orders WHERE status IN ('paid','shipped') AND tax_cents > 0 AND paid_at BETWEEN :f AND :t");
    $yr->execute([':f' => $b['year_from'], ':t' => $b['year_to']]);
    $figures['year_tax'] = (int)$yr->fetchColumn();

    // Per-order detail for the table + CSV.
    $d = $pdo->prepare("SELECT id, paypal_order_id, paid_at, amount_cents, tax_cents,
            JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.address.admin_area_2')) AS city,
            JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.address.admin_area_1')) AS state
        FROM orders
        WHERE status IN ('paid','shipped') AND tax_cents > 0 AND paid_at BETWEEN :f AND :t
        ORDER BY paid_at ASC");
    $d->execute([':f' => $b['from'], ':t' => $b['to']]);
    $detail = $d->fetchAll();
}

// Derived: state vs local split of what was collected (sums to the whole).
$stateCents = (int)round($figures['tax_collected'] * TX_TAX_STATE_FRACTION);
$localCents = $figures['tax_collected'] - $stateCents;
$yearStateCents = (int)round($figures['year_tax'] * TX_TAX_STATE_FRACTION);

// Frequency-bracket hint from the calendar-year state tax.
if ($yearStateCents < TX_ANNUAL_STATE_THRESHOLD_CENTS) {
    $bracket = 'You qualify to file <strong>yearly</strong> (state tax under $1,000/yr) — due January 20 of the following year.';
} elseif ($yearStateCents < TX_MONTHLY_STATE_THRESHOLD_CENTS * 4) {
    $bracket = 'Your state tax is over $1,000/yr — you likely file <strong>quarterly</strong>.';
} else {
    $bracket = 'Your state tax is high enough that you likely file <strong>monthly</strong>.';
}

/* ------------------------------------------------------------------ */
/* CSV export — must run before any HTML                              */
/* ------------------------------------------------------------------ */

if (($_GET['export'] ?? '') === 'csv' && $migrationReady) {
    $fname = 'scrappydolls-tax-' . preg_replace('/[^A-Za-z0-9]+/', '-', $period) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order ID', 'PayPal order', 'Paid at', 'Ship city', 'Ship state', 'Taxable (USD)', 'Tax collected (USD)']);
    foreach ($detail as $r) {
        fputcsv($out, [
            (int)$r['id'],
            (string)$r['paypal_order_id'],
            (string)$r['paid_at'],
            (string)($r['city'] ?? ''),
            (string)($r['state'] ?? ''),
            number_format(((int)$r['amount_cents'] - (int)$r['tax_cents']) / 100, 2, '.', ''),
            number_format((int)$r['tax_cents'] / 100, 2, '.', ''),
        ]);
    }
    // Totals row for quick reconciliation.
    fputcsv($out, []);
    fputcsv($out, ['', '', '', '', 'TOTAL',
        number_format($figures['taxable_sales'] / 100, 2, '.', ''),
        number_format($figures['tax_collected'] / 100, 2, '.', ''),
    ]);
    fclose($out);
    exit;
}

$page = 'tax';
$title = 'Sales tax';
require __DIR__ . '/header.php';

if (!$migrationReady): ?>
  <h1 class="page-title">Sales tax</h1>
  <div class="flash flash-info">
    <strong>One step left.</strong> The tax column hasn't been created yet. Run this migration:
  </div>
  <pre style="background:var(--paper);border:1px solid var(--rule);border-radius:10px;padding:1rem 1.25rem;font-size:.85rem;overflow:auto"><code>mysql -u &lt;user&gt; -p &lt;dbname&gt; &lt; sql/migrations/009_add_tax_and_checkout_intents.sql</code></pre>
  <?php require __DIR__ . '/footer.php'; exit;
endif;

$qs = fn(array $over) => '?' . http_build_query(array_merge(['ptype' => $ptype, 'period' => $period], $over));
?>

<div class="page-head">
  <div>
    <h1 class="page-title" style="margin-bottom:.25rem">Sales tax to remit</h1>
    <span style="color:var(--ink-muted)"><?= h($b['label']) ?> · file/pay by <strong><?= h($b['due_label']) ?></strong></span>
  </div>
  <a href="<?= h($qs(['export' => 'csv'])) ?>" class="btn btn-ghost btn-sm">Export CSV</a>
</div>

<form method="get" style="display:flex;gap:.6rem;align-items:center;margin-bottom:1.25rem;flex-wrap:wrap">
  <label style="display:flex;gap:.4rem;align-items:center">
    <span style="color:var(--ink-muted);font-size:.9rem">Period</span>
    <select name="ptype" onchange="this.form.period.value='';this.form.submit()">
      <option value="year"    <?= $ptype === 'year'    ? 'selected' : '' ?>>Yearly</option>
      <option value="quarter" <?= $ptype === 'quarter' ? 'selected' : '' ?>>Quarterly</option>
      <option value="month"   <?= $ptype === 'month'   ? 'selected' : '' ?>>Monthly</option>
    </select>
  </label>
  <select name="period" onchange="this.form.submit()">
    <?php foreach (tax_period_options($ptype, $firstYear) as [$val, $lbl]): ?>
      <option value="<?= h($val) ?>" <?= $val === $period ? 'selected' : '' ?>><?= h($lbl) ?></option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn btn-ghost btn-sm">Go</button></noscript>
</form>

<div class="flash flash-info" style="margin-bottom:1.25rem">
  <?= $bracket ?> State tax this calendar year (<?= (int)$b['year'] ?>): <strong><?= fmt_price($yearStateCents) ?></strong>.
  Your assigned frequency is on your Comptroller permit letter.
</div>

<div class="detail-grid">
  <div>
    <div class="card" style="margin-bottom:1.25rem">
      <h3>For the Texas return</h3>
      <dl class="kv">
        <dt>Total sales (all destinations)</dt><dd><?= fmt_price($figures['total_sales']) ?></dd>
        <dt>Taxable sales (shipped to TX)</dt><dd><?= fmt_price($figures['taxable_sales']) ?></dd>
        <dt>&nbsp;&nbsp;↳ orders taxed</dt><dd><?= (int)$figures['tx_orders'] ?> of <?= (int)$figures['order_count'] ?></dd>
        <dt><strong>Tax collected</strong></dt><dd><strong><?= fmt_price($figures['tax_collected']) ?></strong></dd>
        <dt>&nbsp;&nbsp;↳ state portion (6.25%)</dt><dd><?= fmt_price($stateCents) ?></dd>
        <dt>&nbsp;&nbsp;↳ local portion (2%)</dt><dd><?= fmt_price($localCents) ?></dd>
      </dl>
      <p style="margin:.85rem 0 0;font-size:.85rem;color:var(--ink-muted)">
        The state and local portions are <strong>both remitted to the Texas Comptroller</strong> on the
        same return — they're shown split only to fill in the return's lines. The Comptroller distributes
        the local share to San Antonio's jurisdictions.
      </p>
    </div>

    <?php if ($figures['refunded_orders'] > 0): ?>
      <div class="card" style="margin-bottom:1.25rem;border-color:var(--rose,#b13e54)">
        <h3 style="color:var(--rose,#b13e54)">Refunds in this period</h3>
        <dl class="kv">
          <dt>Refunded orders (paid this period)</dt><dd><?= (int)$figures['refunded_orders'] ?></dd>
          <dt>Tax already excluded above</dt><dd><?= fmt_price($figures['refunded_tax']) ?></dd>
        </dl>
        <p style="margin:.85rem 0 0;font-size:.85rem;color:var(--ink-muted)">
          Refunded orders are already left out of "Tax collected." If an order was paid in one period
          but refunded in a later one, adjust that on the later return by hand — refunds aren't separately
          dated yet.
        </p>
      </div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <h3>Taxed orders (<?= count($detail) ?>)</h3>
      <?php if (!$detail): ?>
        <p style="color:var(--ink-muted);margin:0">No taxable (Texas) orders in this period.</p>
      <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:.88rem">
          <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--rule)">
              <th style="padding:.4rem .3rem">Order</th>
              <th style="padding:.4rem .3rem">Date</th>
              <th style="padding:.4rem .3rem">Ship to</th>
              <th style="padding:.4rem .3rem;text-align:right">Taxable</th>
              <th style="padding:.4rem .3rem;text-align:right">Tax</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($detail as $r): $base = (int)$r['amount_cents'] - (int)$r['tax_cents']; ?>
              <tr style="border-bottom:1px solid var(--rule-soft,#f0e6e2)">
                <td style="padding:.4rem .3rem"><a href="/admin/order.php?id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
                <td style="padding:.4rem .3rem;color:var(--ink-muted)"><?= h(date('M j, Y', strtotime((string)$r['paid_at']))) ?></td>
                <td style="padding:.4rem .3rem;color:var(--ink-muted)"><?= h(trim(((string)($r['city'] ?? '')) . ', ' . ((string)($r['state'] ?? '')), ', ')) ?></td>
                <td style="padding:.4rem .3rem;text-align:right"><?= fmt_price($base) ?></td>
                <td style="padding:.4rem .3rem;text-align:right"><?= fmt_price((int)$r['tax_cents']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
