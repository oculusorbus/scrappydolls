-- Migration 011: corner snipe → site-wide promo bar
--
-- The offer banner started as a diagonal "snipe" across the top-right
-- corner of the home page only. It's now a horizontal bar across the top
-- of every public page, so the message can run as long as it likes.
--
-- Same settings, new names:
--
--   snipe_on      -> promo_on
--   snipe_text    -> promo_text
--   snipe_subtext -> promo_code
--   snipe_palette -> promo_palette
--   snipe_link    -> promo_link
--
-- Copy across, then drop the old rows. Safe to re-run and safe on a
-- fresh install — with no snipe_* rows both statements match nothing.
-- (The derived table is what lets MySQL read the table it inserts into.)

SET NAMES utf8mb4;

INSERT INTO site_settings (setting_key, setting_value)
SELECT REPLACE(REPLACE(setting_key, 'snipe_subtext', 'promo_code'), 'snipe_', 'promo_'),
       setting_value
  FROM (SELECT setting_key, setting_value
          FROM site_settings
         WHERE setting_key LIKE 'snipe\_%') AS old
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

DELETE FROM site_settings WHERE setting_key LIKE 'snipe\_%';

-- Verify after running:
--   SELECT setting_key, setting_value FROM site_settings;
