-- Migration 006: collect phone number and support gift orders
--
-- The new checkout flow inserts a "Confirm shipping" interstitial between
-- PayPal approval and capture so buyers can:
--   1. Add a phone number (we previously only had email).
--   2. Confirm or override the PayPal shipping address.
--   3. Mark the order as a gift and redirect shipping to a different
--      recipient name + address.
--
-- This migration:
--   1. Adds `customer_phone` to `orders`.
--   2. Adds `is_gift` flag and `gift_recipient_name` to `orders`.
--
-- The existing `shipping_address` JSON column remains the authoritative
-- ship-to address. When `is_gift = 1`, the package is addressed to
-- `gift_recipient_name` at `shipping_address`. Otherwise it's addressed to
-- `customer_name`.
--
-- Idempotent: column-add wrapped in conditional checks so re-running is safe.

START TRANSACTION;

-- customer_phone
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'orders'
    AND column_name  = 'customer_phone'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(40) NULL AFTER customer_name',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- is_gift
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'orders'
    AND column_name  = 'is_gift'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE orders ADD COLUMN is_gift TINYINT(1) NOT NULL DEFAULT 0 AFTER shipping_address',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- gift_recipient_name
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'orders'
    AND column_name  = 'gift_recipient_name'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE orders ADD COLUMN gift_recipient_name VARCHAR(255) NULL AFTER is_gift',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

COMMIT;

-- Verify after running:
--   SHOW COLUMNS FROM orders LIKE 'customer_phone';
--   SHOW COLUMNS FROM orders LIKE 'is_gift';
--   SHOW COLUMNS FROM orders LIKE 'gift_recipient_name';
