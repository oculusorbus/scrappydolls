# Scrappy Dolls — Store setup

Self-contained PHP/MySQL e-commerce for Kanda's dolls, with a public shop, an admin panel, and embedded PayPal Smart Buttons. No frameworks, no Composer, no build step.

## What you're deploying

```
/                       <- public root
  index.html            <- existing marketing landing page (unchanged)
  setup.php             <- one-time wizard to create first admin (delete after)
  shop/                 <- public store
  admin/                <- admin panel (login required)
  api/                  <- PayPal create-order, capture-order, webhook
  uploads/              <- product images live here
  config/               <- DB + PayPal creds (gitignored)
  lib/                  <- shared PHP (db, auth, csrf, paypal, mailer, upload)
  sql/schema.sql        <- run this against MySQL once
```

## Prerequisites on the host

- **PHP 8.2+** with extensions: `pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `gd` *(or `imagick`)*. Run `php -m` or check `phpinfo()`.
- **MySQL or MariaDB** database, plus credentials.
- **HTTPS** on the domain (Let's Encrypt is fine). PayPal **will refuse to fire webhooks** to non-SSL endpoints.
- **Apache with mod_rewrite + AllowOverride All** (the included `.htaccess` files do auth gating, deny lists, and HTTPS redirect). If you're on nginx instead, port the `.htaccess` rules into your server config.
- Ability to send email via PHP `mail()` *or* swap in PHPMailer SMTP later (see `lib/mailer.php`).

## Step 1 — Database

1. Create an empty database (utf8mb4): `CREATE DATABASE scrappydolls CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Create a DB user with full privileges on that database.
3. Import the schema:
   ```bash
   mysql -u <user> -p scrappydolls < sql/schema.sql
   ```
   Or paste the contents of `sql/schema.sql` into phpMyAdmin → SQL.
4. Run the migrations in order (numbered files in `sql/migrations/`):
   ```bash
   mysql -u <user> -p scrappydolls < sql/migrations/002_add_analytics.sql
   ```
   Migrations are additive — safe to run on an already-populated DB. If you set up before this migration existed, run it now to enable Reports.

## Step 2 — Config

1. Copy the template:
   ```bash
   cp config/config.example.php config/config.php
   ```
2. Open `config/config.php` and fill in:
   - `site_url` — `https://scrappydolls.com` (no trailing slash)
   - `db.*` — host, name, user, pass
   - `paypal.client_id` and `paypal.secret` — see Step 3
   - `paypal.environment` — `'sandbox'` to test, `'live'` once you've verified everything
   - `paypal.webhook_id` — see Step 4 (optional but recommended for live)
   - `mail.from_email` — a valid email at your domain (some hosts require this matches)
   - `mail.admin_email` — Kanda's email; this is where order alerts go
   - `security.cookie_secure` — keep `true` once HTTPS is on

`config/config.php` is gitignored. Never commit it.

## Step 3 — PayPal app credentials

1. Sign in at <https://developer.paypal.com> using **mom's PayPal account** (the one that should receive the money).
2. **Apps & Credentials** → **Create App**:
   - Type: **Merchant**
   - Name: *Scrappy Dolls Store*
3. Copy:
   - **Sandbox tab** → Client ID + Secret → paste into config under `'sandbox'` environment
   - **Live tab** → Client ID + Secret → paste in once you flip `environment` to `'live'`
4. Sandbox testing: PayPal also gives you sandbox **test buyer accounts** under *Sandbox Accounts*. Use those to test the checkout end-to-end before going live.

## Step 4 — PayPal webhook (recommended)

The site captures payment in the browser via Smart Buttons. The webhook is a **safety net** that catches any payment that reaches PayPal but doesn't make it back to the browser (closed tab, lost connection, etc.). Without it, you'd occasionally see paid-but-not-recorded orders.

1. PayPal Developer Dashboard → your app → **Webhooks** → **Add Webhook**
2. URL: `https://scrappydolls.com/api/webhook.php`
3. Events to subscribe to:
   - `Payment capture completed`
   - `Payment capture refunded`
   - `Payment capture denied`
4. Save → copy the resulting **Webhook ID** → paste into `config.php` as `paypal.webhook_id`
5. **Do this twice** — once for sandbox, once for live (different webhook IDs).

If `webhook_id` is empty, the webhook endpoint refuses all requests (signature verification fails).

## Step 5 — Upload + run setup

1. Upload everything (FTP / git pull on the server / `rsync`).
2. Make sure `/uploads/` is writable by PHP (`chmod 755 uploads/` is usually fine; some hosts need `775` or 777-then-tighten).
3. Visit `https://scrappydolls.com/setup.php` once.
4. Create the first admin (Kanda's email + a strong password ≥10 chars).
5. **Delete `setup.php`** from the server. (It self-locks once an admin exists, but cleaner to remove.)

## Step 6 — Smoke test

1. Sign in at `/admin/login.php`.
2. Add a test doll with a placeholder image, status = **Available**, price = $1.00.
3. Open `/shop/` → click the doll → click the PayPal button.
4. In sandbox: pay with a sandbox test buyer. In live: pay $1 with a real card or transfer it back later.
5. Verify:
   - Doll moves to **Sold** in `/admin/products.php`
   - Order appears in `/admin/orders.php`
   - Email arrives at the admin email address
   - PayPal dashboard shows the transaction
6. **Mark the order as shipped** to test the shipping flow.

## Going live

- Flip `paypal.environment` from `'sandbox'` to `'live'`.
- Replace sandbox client_id + secret with **live** ones.
- Replace sandbox webhook_id with **live** one.
- (Re)test with a real $1 doll.

## Operational notes

**Adding a doll** (mom's flow): `/admin/products.php` → **+ Add new doll** → fill title, price, description, drag images, set status to Available → Save. Doll instantly appears at `/shop/`.

**An order arrives**: mom gets email → opens `/admin/orders.php` → opens the order → ships → enters tracking number → clicks **Mark shipped**.

**A buyer cancels mid-checkout**: nothing happens — no order is recorded, doll stays Available.

**A doll sells while two browsers have it open**: capture endpoint protects against double-sale via row locking on `status='available'`. The losing buyer gets an error before money moves.

**Refunds**: process in PayPal dashboard. The webhook will mark the order as `refunded` automatically. You'll need to manually flip the doll back to `available` if you want to relist.

**Email deliverability**: PHP `mail()` on shared hosting often lands in spam. If that's a problem, install PHPMailer (`composer require phpmailer/phpmailer`) and swap `lib/mailer.php` to send via SMTP through Mailgun / Postmark / SES.

**Sales tax**: not handled. You're responsible for collecting/remitting Texas sales tax if applicable. For low volume out-of-state, most states have de minimis thresholds — check Texas Comptroller guidance.

**Backup**: at minimum, `mysqldump` the database weekly and back up `/uploads/` to S3 or similar.

## Reports & analytics (`/admin/reports.php`)

Once orders start flowing, the Reports page surfaces:

- **KPI strip** — revenue, orders, AOV, visitors, conversion rate, time-to-ship — each with delta vs the prior equivalent period.
- **Highlights** — auto-generated insights (best sales day, top channel, abandoned-checkout count, repeat-buyer rate, oldest aging doll).
- **Revenue trend** — daily line chart for the selected range.
- **Sales funnel** — visitors → product viewers → checkout starts → completed purchases, with conversion % between stages.
- **Channels** — revenue by acquisition source (UTM `source` first, falling back to referrer host, then "direct").
- **Top dolls** — views, buy clicks, units sold, revenue per doll.
- **Geography** — top regions by revenue (state + country from PayPal shipping addresses).
- **Operations** — avg time to sell, time to ship, refund rate, repeat customer rate, inventory counts.
- **Aging inventory** — available dolls listed 30+ days, sorted oldest first.

Date ranges: 7d / 30d / 90d / YTD / All time (querystring `?range=`).

### How to attribute campaigns

Tag your social/email links with UTM parameters and they'll show up in the Channels chart and on each order:

```
https://scrappydolls.com/shop/?utm_source=facebook&utm_medium=post&utm_campaign=fall-launch
https://scrappydolls.com/shop/?utm_source=instagram&utm_medium=story
https://scrappydolls.com/shop/?utm_source=newsletter&utm_medium=email&utm_campaign=oct-issue
```

Attribution is **first-touch** — the UTM from the buyer's first visit is what gets credited, even if they come back days later via a different path. Stored on the order row (`utm_source`, `utm_medium`, `utm_campaign`).

### Privacy

Tracking is first-party and minimal:
- IPs are sha256-hashed with the site URL as salt — never stored raw.
- A long-lived cookie (`sd_sid`, 30 days, HttpOnly + SameSite=Lax) creates a per-visitor pseudonymous ID.
- Bots are filtered by user-agent before any row hits the DB.
- No third-party services. No data leaves your server.

If the EU/UK ever becomes a meaningful audience, you'll likely want to add a cookie banner per ePrivacy. For Texas/US-only audiences, this setup is fine as-is.

## File reference

| Path | Purpose |
|---|---|
| `index.html` | Existing marketing landing page (unchanged by this work) |
| `shop/index.php` | Public shop listing |
| `shop/product.php?slug=...` | Single doll page with PayPal Smart Buttons |
| `shop/success.php` | Post-payment thank-you |
| `admin/login.php` | Admin sign-in |
| `admin/dashboard.php` | Stats + recent orders |
| `admin/products.php` | List/manage dolls |
| `admin/edit.php` | Add/edit doll, multi-image upload |
| `admin/import.php` | Bulk import — one doll per image, shared price, auto-numbered titles |
| `admin/delete.php` | Delete doll (POST only, soft-delete if orders exist) |
| `admin/orders.php` | Order list |
| `admin/order.php` | Single order detail, mark shipped, notes |
| `admin/reports.php` | Analytics dashboard: KPIs, funnel, channels, geography, operations |
| `lib/analytics.php` | First-party tracking helpers (page views, intents, UTM) |
| `sql/migrations/002_add_analytics.sql` | Adds page_views, order_intents, UTM cols on orders |
| `api/create-order.php` | Called by Smart Buttons to create the PayPal order |
| `api/capture-order.php` | Called on user approval; captures payment, marks sold, emails |
| `api/webhook.php` | PayPal webhook (verify signature, idempotent) |
| `lib/paypal.php` | Pure-cURL PayPal v2 Orders client |
| `lib/auth.php` | Session-based admin auth, bcrypt passwords |
| `lib/csrf.php` | CSRF tokens on all admin POSTs |
| `lib/upload.php` | Image upload validation, mime sniff, safe rename |
| `sql/schema.sql` | One-shot schema |
| `setup.php` | First-run admin creation wizard (delete after) |

## Security notes

- All admin POSTs are CSRF-protected.
- Passwords are bcrypt (cost 12).
- File uploads are mime-sniffed (`finfo`) and renamed to random hex — original filename never touches the filesystem.
- `/uploads/.htaccess` blocks PHP execution from the upload dir.
- `/config/.htaccess` and `/lib/.htaccess` deny all direct access.
- The root `.htaccess` forces HTTPS and 403s `/config`, `/lib`, `/sql`, `/vendor`.
- PayPal webhook signature is verified against `paypal.webhook_id`.

## What was intentionally left out (v1)

- Customer accounts / login (OOAK = no real reason for one)
- Wishlist / cart with multiple items (each doll is unique, single-item checkout)
- Shipping rate calculator (price is total — bake shipping into the doll price)
- Coupon codes
- Sales tax automation
- Multi-currency
- Multi-admin user management UI (insert directly via DB if needed)
- Customer-facing order lookup (PayPal sends the receipt; `success.php` shows the reference)

These are all clean future additions; the schema doesn't preclude any of them.
