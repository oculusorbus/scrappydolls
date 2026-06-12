<?php
declare(strict_types=1);

/**
 * Sales tax. Scrappy Dolls is a San Antonio, TX seller; we charge a flat
 * Texas rate only when the package ships to a Texas (US) address. Texas
 * uses origin-based sourcing for intrastate sales, so a single flat rate
 * (the seller's San Antonio rate, = the TX maximum combined rate) is the
 * correct charge for any in-state destination. Nothing is charged outside
 * Texas — see terms.php.
 *
 * The taxable base is (item subtotal − coupon discount + shipping). In
 * Texas, shipping/handling is taxable when the item sold is taxable, and
 * tax applies to the discounted sales price.
 */

// Fallback if config is missing or malformed. 8.25% = TX state 6.25% + the
// 2% maximum local rate that applies in San Antonio.
const TX_TAX_RATE_DEFAULT = 0.0825;

/**
 * The configured Texas rate as a fraction (0.0825 = 8.25%). Guards against
 * a value accidentally entered as a percent (8.25) or out of range by
 * falling back to the default.
 */
function tax_rate_tx(): float {
    $r = config('paypal.tax.tx_rate');
    if (!is_numeric($r)) return TX_TAX_RATE_DEFAULT;
    $r = (float)$r;
    if ($r <= 0 || $r >= 1) return TX_TAX_RATE_DEFAULT;
    return $r;
}

/** US state name → USPS code, for normalizing free-text / PayPal-supplied state. */
const US_STATE_NAME_TO_CODE = [
    'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR',
    'CALIFORNIA' => 'CA', 'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE',
    'DISTRICT OF COLUMBIA' => 'DC', 'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI',
    'IDAHO' => 'ID', 'ILLINOIS' => 'IL', 'INDIANA' => 'IN', 'IOWA' => 'IA',
    'KANSAS' => 'KS', 'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA', 'MAINE' => 'ME',
    'MARYLAND' => 'MD', 'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN',
    'MISSISSIPPI' => 'MS', 'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE',
    'NEVADA' => 'NV', 'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM',
    'NEW YORK' => 'NY', 'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH',
    'OKLAHOMA' => 'OK', 'OREGON' => 'OR', 'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI',
    'SOUTH CAROLINA' => 'SC', 'SOUTH DAKOTA' => 'SD', 'TENNESSEE' => 'TN', 'TEXAS' => 'TX',
    'UTAH' => 'UT', 'VERMONT' => 'VT', 'VIRGINIA' => 'VA', 'WASHINGTON' => 'WA',
    'WEST VIRGINIA' => 'WV', 'WISCONSIN' => 'WI', 'WYOMING' => 'WY',
    'PUERTO RICO' => 'PR',
];

/**
 * Normalize a US state to its 2-letter USPS code. Accepts an existing code
 * ("tx", "TX") or a full name ("Texas"). Returns the uppercased input
 * unchanged when it can't be resolved (e.g. a non-US region) so callers
 * pass through foreign addresses without mangling them.
 */
function normalize_us_state(string $state): string {
    $s = strtoupper(trim($state));
    if ($s === '') return '';
    if (preg_match('/^[A-Z]{2}$/', $s)) return $s;
    return US_STATE_NAME_TO_CODE[$s] ?? $s;
}

/** Does this state resolve to Texas? */
function state_is_texas(string $state): bool {
    return normalize_us_state($state) === 'TX';
}

/**
 * Sales tax in cents for a taxable base shipping to ($state, $country).
 * Tax only applies to Texas, US destinations; everywhere else returns 0.
 */
function order_tax_cents(int $baseCents, string $state, string $country): int {
    if ($baseCents <= 0) return 0;
    if (strtoupper(trim($country)) !== 'US') return 0;
    if (!state_is_texas($state)) return 0;
    return (int)round($baseCents * tax_rate_tx());
}
