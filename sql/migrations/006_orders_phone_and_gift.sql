-- Migration 006: collect phone number and support gift orders
--
-- The new checkout flow inserts a "Confirm shipping" interstitial between
-- PayPal approval and capture so buyers can:
--   1. Add a phone number (we previously only had email).
--   2. Confirm or override the PayPal shipping address.
--   3. Mark the order as a gift and redirect shipping to a different
--      recipient name + address.
--
-- The existing `shipping_address` JSON column remains the authoritative
-- ship-to address. When `is_gift = 1`, the package is addressed to
-- `gift_recipient_name` at `shipping_address`. Otherwise it's addressed to
-- `customer_name`.
--
-- Re-running this migration will fail with "Duplicate column" — that's
-- expected and harmless; the columns are already there.

ALTER TABLE orders
  ADD COLUMN customer_phone      VARCHAR(40)  NULL          AFTER customer_name,
  ADD COLUMN is_gift             TINYINT(1)   NOT NULL DEFAULT 0 AFTER shipping_address,
  ADD COLUMN gift_recipient_name VARCHAR(255) NULL          AFTER is_gift;

-- Verify after running:
--   SHOW COLUMNS FROM orders LIKE 'customer_phone';
--   SHOW COLUMNS FROM orders LIKE 'is_gift';
--   SHOW COLUMNS FROM orders LIKE 'gift_recipient_name';
