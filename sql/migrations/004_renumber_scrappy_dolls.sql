-- Migration 004: bump every "Scrappy Doll #N" by +100
--
-- Stepdad's catalog protocol numbers all dolls starting at 101 (so what we
-- imported as "Scrappy Doll #1" should be "#101", "#99" → "#199", etc.).
-- Updates both `title` and `slug` for every row matching the bulk-import
-- naming pattern.
--
-- ORDER BY DESC is REQUIRED to avoid a slug-uniqueness collision: updating
-- low → high would try to set #1's slug to "scrappy-doll-101" while row
-- #101 still owns that slug → "Duplicate entry" error. Processing
-- highest-first means each old slug is freed before any lower row claims it.
--
-- Idempotent: the WHERE clause only matches rows whose current number is
-- below 101, so re-running the file is a no-op.
--
-- Foreign keys (orders.product_id, product_images.product_id) reference
-- products.id, not title or slug — order history is unaffected.

UPDATE products
SET
  slug  = CONCAT('scrappy-doll-', CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) + 100),
  title = CONCAT('Scrappy Doll #', CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) + 100)
WHERE title REGEXP '^Scrappy Doll #[0-9]+$'
  AND CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) < 101
ORDER BY CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) DESC;

-- Verify after running:
--   SELECT MIN(CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED)) AS min_num,
--          MAX(CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED)) AS max_num,
--          COUNT(*) AS total
--   FROM products
--   WHERE title REGEXP '^Scrappy Doll #[0-9]+$';
-- Expected for a 106-doll catalog: min_num=101, max_num=206, total=106.
