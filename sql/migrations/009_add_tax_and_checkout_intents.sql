-- Migration 009: sales tax + checkout snapshot
--
-- Adds Texas sales tax to the cart → confirm → pay flow. Because the tax
-- line depends on the shipping destination, the buyer's confirmed contact
-- and ship-to are now collected BEFORE the PayPal order is created. The
-- whole priced submission is snapshotted into order_checkout_intents at
-- create time; capture-cart-order.php reads that snapshot (never the
-- session or a re-POSTed body), the same pattern as order_intents and
-- order_coupon_intents.
--
-- Re-running this migration will fail with "Table already exists" /
-- "Duplicate column" — that's expected and harmless.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- orders: record the tax charged so the receipt/admin breakdown can
-- separate it from shipping (both are derived from amount_cents).
-- ---------------------------------------------------------------
ALTER TABLE orders
  ADD COLUMN tax_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER discount_cents;

-- ---------------------------------------------------------------
-- order_checkout_intents: confirmed contact + ship-to + priced
-- breakdown, snapshotted per PayPal order at creation time.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_checkout_intents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  paypal_order_id VARCHAR(64) NOT NULL,
  customer_name VARCHAR(255) NOT NULL,
  customer_email VARCHAR(255) NOT NULL,
  customer_phone VARCHAR(40) NOT NULL,
  is_gift TINYINT(1) NOT NULL DEFAULT 0,
  gift_recipient_name VARCHAR(255) DEFAULT NULL,
  gift_message TEXT DEFAULT NULL,
  shipping_address JSON NOT NULL,
  item_total_cents INT UNSIGNED NOT NULL,
  discount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  shipping_cents INT UNSIGNED NOT NULL DEFAULT 0,
  tax_cents INT UNSIGNED NOT NULL DEFAULT 0,
  total_cents INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_paypal (paypal_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify after running:
--   SHOW COLUMNS FROM orders LIKE 'tax_cents';
--   SHOW TABLES LIKE 'order_checkout_intents';
