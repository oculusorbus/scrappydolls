<?php
declare(strict_types=1);

/**
 * Small key/value store for site-wide settings that mom/stepdad change
 * from the admin panel (as opposed to config.php, which is deploy-time
 * and gitignored). One row per key in `site_settings`.
 *
 * Its user is the promo bar — the colored strip across the top of every
 * public page announcing whatever discount is running. It's edited on
 * /admin/coupons.php, right next to the codes it advertises.
 */

/**
 * All settings, read once per request.
 *
 * If the table isn't there yet (files deployed before migration 010 was
 * run), behave as if nothing is set rather than taking the whole site
 * down — the bar just stays hidden until the migration lands.
 */
function settings_all(): array {
    if (isset($GLOBALS['__settings'])) return $GLOBALS['__settings'];
    $out = [];
    try {
        foreach (db()->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) {
            $out[(string)$row['setting_key']] = (string)$row['setting_value'];
        }
    } catch (Throwable $e) {
        error_log('site_settings unavailable (run sql/migrations/010_add_site_settings.sql): ' . $e->getMessage());
    }
    return $GLOBALS['__settings'] = $out;
}

function setting_get(string $key, string $default = ''): string {
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function setting_set(string $key, string $value): void {
    db()->prepare('
        INSERT INTO site_settings (setting_key, setting_value) VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ')->execute([':k' => $key, ':v' => $value]);
    if (isset($GLOBALS['__settings'])) $GLOBALS['__settings'][$key] = $value;
}

/** Forget a setting entirely — the caller's default takes over again. */
function setting_delete(string $key): void {
    db()->prepare('DELETE FROM site_settings WHERE setting_key = :k')->execute([':k' => $key]);
    if (isset($GLOBALS['__settings'])) unset($GLOBALS['__settings'][$key]);
}

// ---------------------------------------------------------------
// Promo bar
// ---------------------------------------------------------------

const PROMO_ON      = 'promo_on';
const PROMO_TEXT    = 'promo_text';
const PROMO_CODE    = 'promo_code';
const PROMO_PALETTE = 'promo_palette';
const PROMO_LINK    = 'promo_link';

/**
 * The bar wraps, so the message can run long — this cap only exists so
 * a stray paste can't push the whole page below the fold.
 */
const PROMO_TEXT_MAX = 240;
const PROMO_CODE_MAX = 40;

/**
 * The colors the bar is allowed to be. Only the *key* is stored, so
 * nothing a form post says can ever reach the page as raw CSS.
 *
 * `ink` is picked to stay readable against the darker end of each
 * gradient (bright yellow/green get near-black text, the rest white).
 */
function promo_palettes(): array {
    return [
        'cherry'    => ['label' => 'Cherry',    'from' => '#ff3b5c', 'to' => '#b4243d', 'ink' => '#ffffff'],
        'tangerine' => ['label' => 'Tangerine', 'from' => '#ff9a1f', 'to' => '#ef5b0c', 'ink' => '#ffffff'],
        'sunshine'  => ['label' => 'Sunshine',  'from' => '#ffe14d', 'to' => '#f7b500', 'ink' => '#3a2a00'],
        'lime'      => ['label' => 'Lime',      'from' => '#a8ee5c', 'to' => '#5cbb2e', 'ink' => '#11300a'],
        'teal'      => ['label' => 'Teal',      'from' => '#3ce6cb', 'to' => '#0f9b8e', 'ink' => '#043029'],
        'ocean'     => ['label' => 'Ocean',     'from' => '#5cb4ff', 'to' => '#1f5fd0', 'ink' => '#ffffff'],
        'violet'    => ['label' => 'Violet',    'from' => '#b98cff', 'to' => '#6d35d6', 'ink' => '#ffffff'],
        'hotpink'   => ['label' => 'Hot pink',  'from' => '#ff6fb1', 'to' => '#d61f7a', 'ink' => '#ffffff'],
    ];
}

function promo_palette_key(string $key): string {
    return isset(promo_palettes()[$key]) ? $key : 'cherry';
}

function promo_colors(string $key): array {
    return promo_palettes()[promo_palette_key($key)];
}

/**
 * A link target is only kept if it's a same-site path or an http(s)
 * URL — anything else (javascript:, data:) is dropped to ''.
 */
function promo_clean_link(string $link): string {
    $link = trim($link);
    if ($link === '') return '';
    if (preg_match('#^/[^/\\\\]#', $link)) return $link;      // /shop/ but not //evil.com
    if (preg_match('#^https?://#i', $link)) return $link;
    return '';
}

/**
 * Current bar settings, normalized. `on` is only true when there is
 * actually something to say.
 */
function promo_settings(): array {
    $text = trim(setting_get(PROMO_TEXT));
    return [
        'on'      => setting_get(PROMO_ON) === '1' && $text !== '',
        'text'    => $text,
        'code'    => trim(setting_get(PROMO_CODE)),
        'palette' => promo_palette_key(setting_get(PROMO_PALETTE, 'cherry')),
        'link'    => promo_clean_link(setting_get(PROMO_LINK)),
    ];
}

/**
 * The bar's stylesheet, emitted at most once per request.
 *
 * It travels with the markup on purpose: the bar appears on the landing
 * page (inline <style>), the shop (shop/styles.css), and the standalone
 * legal/contact pages (each with their own inline <style>), so there is
 * no one stylesheet all of them already share. The admin preview asks
 * for it directly so it renders the real thing rather than a lookalike.
 */
function promo_bar_css(): string {
    static $emitted = false;
    if ($emitted) return '';
    $emitted = true;

    return <<<CSS
<style>
.promo-bar {
  background: linear-gradient(90deg, var(--promo-from), var(--promo-to));
  color: var(--promo-ink);
  font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
.promo-bar-inner {
  max-width: 74rem;
  margin: 0 auto;
  padding: 0.7rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0.3rem 0.75rem;
  text-align: center;
  text-decoration: none;
  color: inherit;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.45;
}
.promo-bar-tail {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  white-space: nowrap;
}
.promo-bar-code {
  display: inline-block;
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.22);
  background: color-mix(in oklab, var(--promo-ink) 16%, transparent);
  font-size: 0.85em;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  white-space: nowrap;
}
.promo-bar-go { transition: transform 0.2s ease; }
a.promo-bar-inner:hover .promo-bar-go { transform: translateX(3px); }
a.promo-bar-inner:hover .promo-bar-text { text-decoration: underline; text-underline-offset: 3px; }
@media (max-width: 40rem) {
  .promo-bar-inner { font-size: 0.85rem; padding: 0.6rem 1rem; }
}
@media print { .promo-bar { display: none; } }
</style>
CSS;
}

/**
 * The bar itself, stylesheet included. Returns '' when it's switched off.
 * Goes immediately after <body> on every public page.
 */
function promo_bar_html(?array $s = null): string {
    $s = $s ?? promo_settings();
    if (empty($s['on'])) return '';

    $c = promo_colors((string)$s['palette']);
    $style = sprintf(
        '--promo-from:%s;--promo-to:%s;--promo-ink:%s',
        $c['from'], $c['to'], $c['ink']
    );

    // The code pill and the arrow ride together so a narrow screen never
    // wraps the arrow onto a line of its own.
    $tail = '';
    if ($s['code'] !== '') {
        $tail .= '<span class="promo-bar-code">' . h($s['code']) . '</span>';
    }
    if ($s['link'] !== '') {
        $tail .= '<span class="promo-bar-go" aria-hidden="true">&rarr;</span>';
    }
    $inner = '<span class="promo-bar-text">' . h($s['text']) . '</span>'
        . ($tail !== '' ? '<span class="promo-bar-tail">' . $tail . '</span>' : '');

    $row = $s['link'] !== ''
        ? '<a class="promo-bar-inner" href="' . h($s['link']) . '">' . $inner . '</a>'
        : '<div class="promo-bar-inner">' . $inner . '</div>';

    return promo_bar_css()
        . '<div class="promo-bar" style="' . h($style) . '" role="region" aria-label="Store offer">'
        . $row . '</div>';
}
