-- Migration 010: site settings (landing-page offer snipe)
--
-- A tiny key/value table for things the admin panel can change at any
-- time, as opposed to config.php which is deploy-time and gitignored.
--
-- Its first user is the offer "snipe" — the bright diagonal banner that
-- swipes across the top-right corner of the home page to announce a
-- discount. It's edited on /admin/coupons.php, next to the codes it
-- advertises. Keys used:
--
--   snipe_on       '1' / '0'  — show it or not
--   snipe_text     the offer, e.g. "20% OFF EVERY DOLL"
--   snipe_subtext  optional second line, e.g. "code SUMMER20"
--   snipe_palette  one of the palette keys in lib/settings.php
--   snipe_link     where clicking it goes, e.g. /shop/
--
-- Re-running this migration is safe (CREATE TABLE IF NOT EXISTS).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
  setting_value TEXT,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify after running:
--   SHOW TABLES LIKE 'site_settings';
