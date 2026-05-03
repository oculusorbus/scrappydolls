-- Migration 004: bump every "Scrappy Doll #N" by +100
--
-- Stepdad's catalog protocol numbers all dolls starting at 101 (so what we
-- imported as "Scrappy Doll #1" should be "#101", "#99" → "#199", etc.).
-- Updates both `title` and `slug` for every row matching the bulk-import
-- naming pattern.
--
-- WHY TWO PASSES:
-- A single UPDATE that shifts slugs into the same namespace they already
-- live in (e.g. setting #1's slug to "scrappy-doll-101" while #101 still
-- owns it) hits the slug UNIQUE key, even with ORDER BY — InnoDB's index
-- check sees pending changes within the statement. So we first park every
-- target slug under a row-id-based temp value (guaranteed unique), then
-- set the final values in a clean second pass.
--
-- Idempotent: the WHERE clause only matches rows whose current number is
-- below 101, so re-running the file is a no-op.
--
-- Atomic: wrapped in a transaction. If anything fails, ROLLBACK restores
-- the original state.
--
-- Foreign keys (orders.product_id, product_images.product_id) reference
-- products.id, not title or slug — order history is unaffected.

START TRANSACTION;

-- Pass 1: stash slugs under a temp prefix using `id` to guarantee uniqueness.
UPDATE products
SET slug = CONCAT('__renumber_tmp__', id)
WHERE title REGEXP '^Scrappy Doll #[0-9]+$'
  AND CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) < 101;

-- Pass 2: set final title + slug. The slug namespace is fully clear of
-- "scrappy-doll-N" entries for the target range now, so no collisions.
UPDATE products
SET
  slug  = CONCAT('scrappy-doll-', CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) + 100),
  title = CONCAT('Scrappy Doll #', CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) + 100)
WHERE title REGEXP '^Scrappy Doll #[0-9]+$'
  AND CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) < 101
  AND slug LIKE '\_\_renumber\_tmp\_\_%' ESCAPE '\\';

COMMIT;

-- Verify after running:
--   SELECT MIN(CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED)) AS min_num,
--          MAX(CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED)) AS max_num,
--          COUNT(*) AS total
--   FROM products
--   WHERE title REGEXP '^Scrappy Doll #[0-9]+$';
-- Expected for a 106-doll catalog: min_num=101, max_num=206, total=106.
--
-- If you ever see slugs starting with __renumber_tmp__ after running, the
-- second pass didn't complete. Recover with:
--   UPDATE products
--   SET slug = CONCAT('scrappy-doll-', CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED))
--   WHERE slug LIKE '\\_\\_renumber\\_tmp\\_\\_%';
-- (sets slugs back to whatever the title says — assumes title was untouched).
