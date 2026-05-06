-- Migration 007: optional gift message included with the package
--
-- Buyers checking "This is a gift" on /shop/confirm.php can now write a
-- short note. The admin sees the note prominently on the order page and
-- in the new-order email; whoever packs the order is responsible for
-- physically including it with the doll.
--
-- Idempotent: column-add wrapped in a conditional check so re-running is safe.

START TRANSACTION;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'orders'
    AND column_name  = 'gift_message'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE orders ADD COLUMN gift_message TEXT NULL AFTER gift_recipient_name',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;

-- Verify after running:
--   SHOW COLUMNS FROM orders LIKE 'gift_message';
