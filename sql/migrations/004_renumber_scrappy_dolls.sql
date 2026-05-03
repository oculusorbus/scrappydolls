-- Migration 004: bump every "Scrappy Doll #N" by +100
--
-- Stepdad's catalog protocol numbers all dolls starting at 101 (so what we
-- imported as "Scrappy Doll #1" should be "#101", "#99" → "#199", etc.).
-- Because the original import created 106 rows numbered 1–106, ALL of them
-- need bumping (#101 → #201, #106 → #206, etc.) — not just those below 101.
-- Updates both `title` and `slug` for every matching row.
--
-- !! NOT IDEMPOTENT !! Run this ONCE.
-- After it runs, every Scrappy Doll #N is at N+100. Running again would
-- bump them again to N+200 (BAD).
--
-- Sanity-check before running — should be > 0:
--   SELECT COUNT(*) FROM products
--   WHERE title REGEXP '^Scrappy Doll #[0-9]+$'
--     AND CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) < 101;
-- If it returns 0, the migration has already run. STOP — do not re-run.
--
-- WHY TWO PASSES:
-- A single UPDATE that shifts slugs into the same "scrappy-doll-N"
-- namespace they already live in hits the slug UNIQUE key (e.g. setting
-- #1's slug to "scrappy-doll-101" while #101 still holds it). InnoDB's
-- index check sees pending in-statement changes, so ORDER BY tricks
-- don't help. Two passes solve it cleanly:
--   Pass 1 parks every row's slug under a row-id-keyed temp value
--          (guaranteed unique). This empties the entire "scrappy-doll-N"
--          namespace for all bumped rows.
--   Pass 2 sets the final title + slug to the original number + 100. No
--          conflicts because no row holds any target slug.
--
-- Atomic: wrapped in a transaction. If anything fails, ROLLBACK restores
-- the original state. Foreign keys (orders, product_images) reference
-- products.id, not title or slug — order history is unaffected.

START TRANSACTION;

-- Pass 1: stash EVERY "Scrappy Doll #N" row (including #101–#106) into a
-- row-id-keyed temp slug. Frees the entire scrappy-doll-N namespace.
-- Re-stashing already-stashed rows is harmless (slug recomputes to same
-- temp value), so this also recovers from a partially-failed prior run.
UPDATE products
SET slug = CONCAT('__renumber_tmp__', id)
WHERE title REGEXP '^Scrappy Doll #[0-9]+$';

-- Pass 2: set final title + slug = (current title number) + 100 for every
-- stashed row.
UPDATE products
SET
  slug  = CONCAT('scrappy-doll-', CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) + 100),
  title = CONCAT('Scrappy Doll #', CAST(SUBSTRING(title, LOCATE('#', title) + 1) AS UNSIGNED) + 100)
WHERE slug LIKE '\_\_renumber\_tmp\_\_%' ESCAPE '\\';

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
