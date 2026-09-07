<?php
declare(strict_types=1);

/**
 * Small key/value store for site-wide settings that mom/stepdad change
 * from the admin panel (as opposed to config.php, which is deploy-time
 * and gitignored). One row per key in `site_settings`.
 *
 * First user: the landing-page offer snipe — the diagonal banner that
 * swipes across the top-right corner of the home page announcing a
 * discount. It's edited on /admin/coupons.php, right next to the codes
 * it advertises.
 */

/**
 * All settings, read once per request.
 *
 * If the table isn't there yet (files deployed before migration 010 was
 * run), behave as if nothing is set rather than taking the whole site
 * down — the banner just stays hidden until the migration lands.
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

// ---------------------------------------------------------------
// Landing-page offer snipe
// ---------------------------------------------------------------

const SNIPE_ON      = 'snipe_on';
const SNIPE_TEXT    = 'snipe_text';
const SNIPE_SUBTEXT = 'snipe_subtext';
const SNIPE_PALETTE = 'snipe_palette';
const SNIPE_LINK    = 'snipe_link';

const SNIPE_TEXT_MAX    = 26;
const SNIPE_SUBTEXT_MAX = 22;

/**
 * The colors the banner is allowed to be. Only the *key* is stored, so
 * nothing a form post says can ever reach the page as raw CSS.
 *
 * `ink` is picked to stay readable against the darker end of each
 * gradient (bright yellow/green get near-black text, the rest white).
 */
function snipe_palettes(): array {
    return [
        'cherry'   => ['label' => 'Cherry',    'from' => '#ff3b5c', 'to' => '#b4243d', 'ink' => '#ffffff'],
        'tangerine'=> ['label' => 'Tangerine', 'from' => '#ff9a1f', 'to' => '#ef5b0c', 'ink' => '#ffffff'],
        'sunshine' => ['label' => 'Sunshine',  'from' => '#ffe14d', 'to' => '#f7b500', 'ink' => '#3a2a00'],
        'lime'     => ['label' => 'Lime',      'from' => '#a8ee5c', 'to' => '#5cbb2e', 'ink' => '#11300a'],
        'teal'     => ['label' => 'Teal',      'from' => '#3ce6cb', 'to' => '#0f9b8e', 'ink' => '#043029'],
        'ocean'    => ['label' => 'Ocean',     'from' => '#5cb4ff', 'to' => '#1f5fd0', 'ink' => '#ffffff'],
        'violet'   => ['label' => 'Violet',    'from' => '#b98cff', 'to' => '#6d35d6', 'ink' => '#ffffff'],
        'hotpink'  => ['label' => 'Hot pink',  'from' => '#ff6fb1', 'to' => '#d61f7a', 'ink' => '#ffffff'],
    ];
}

function snipe_palette_key(string $key): string {
    return isset(snipe_palettes()[$key]) ? $key : 'cherry';
}

function snipe_colors(string $key): array {
    return snipe_palettes()[snipe_palette_key($key)];
}

/**
 * A link target is only kept if it's a same-site path or an http(s)
 * URL — anything else (javascript:, data:) is dropped to ''.
 */
function snipe_clean_link(string $link): string {
    $link = trim($link);
    if ($link === '') return '';
    if (preg_match('#^/[^/\\\\]#', $link)) return $link;      // /shop/ but not //evil.com
    if (preg_match('#^https?://#i', $link)) return $link;
    return '';
}

/**
 * Current banner settings, normalized. `on` is only true when there is
 * actually something to say.
 */
function snipe_settings(): array {
    $text = trim(setting_get(SNIPE_TEXT));
    return [
        'on'      => setting_get(SNIPE_ON) === '1' && $text !== '',
        'text'    => $text,
        'subtext' => trim(setting_get(SNIPE_SUBTEXT)),
        'palette' => snipe_palette_key(setting_get(SNIPE_PALETTE, 'cherry')),
        'link'    => snipe_clean_link(setting_get(SNIPE_LINK)),
    ];
}

/**
 * The corner banner itself. Returns '' when it's switched off.
 *
 * Markup only — the CSS lives with the rest of the landing-page styles
 * in index.php (`.snipe`).
 */
function snipe_html(?array $s = null): string {
    $s = $s ?? snipe_settings();
    if (empty($s['on'])) return '';

    $c = snipe_colors((string)$s['palette']);

    // Longer offers step down a size so the band still fits the corner.
    $len  = max(mb_strlen($s['text']), (int)round(mb_strlen($s['subtext']) * 0.8));
    $size = $len > 22 ? ' snipe-long' : ($len > 16 ? ' snipe-med' : '');
    $style = sprintf(
        '--snipe-from:%s;--snipe-to:%s;--snipe-ink:%s',
        $c['from'], $c['to'], $c['ink']
    );
    $label = $s['text'] . ($s['subtext'] !== '' ? ' — ' . $s['subtext'] : '');

    $inner = '<span class="snipe-line">' . h($s['text']) . '</span>';
    if ($s['subtext'] !== '') {
        $inner .= '<span class="snipe-sub">' . h($s['subtext']) . '</span>';
    }

    if ($s['link'] !== '') {
        return '<a class="snipe' . $size . '" href="' . h($s['link']) . '" style="' . h($style) . '"'
            . ' aria-label="' . h($label) . '">' . $inner . '</a>';
    }
    return '<div class="snipe' . $size . '" style="' . h($style) . '" role="note"'
        . ' aria-label="' . h($label) . '">' . $inner . '</div>';
}
