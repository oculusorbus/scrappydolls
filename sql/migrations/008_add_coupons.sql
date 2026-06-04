-- Migration 008: coupon codes
--
-- Lets admin create discount codes that buyers enter on the cart page.
-- A code can take a percent off, a fixed amount off, waive shipping, or
-- any combination. Optional limits: minimum subtotal, max total uses,
-- and an expiration date.
--
-- order_coupon_intents mirrors order_intents: when a PayPal order is
-- created with a coupon applied, the computed discount is snapshotted
-- there. The capture endpoint reads the snapshot (never the session) so
-- the recorded charge always matches what PayPal authorized.
--
-- Re-running this migration will fail with "Table already exists" /
-- "Duplicate column" — that's expected and harmless.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- coupons: one row per code, managed in /admin/coupons.php
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  note VARCHAR(255) DEFAULT NULL,
  percent_off TINYINT UNSIGNED NOT NULL DEFAULT 0,
  amount_off_cents INT UNSIGNED NOT NULL DEFAULT 0,
  free_shipping TINYINT(1) NOT NULL DEFAULT 0,
  min_subtotal_cents INT UNSIGNED NOT NULL DEFAULT 0,
  max_uses INT UNSIGNED DEFAULT NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- order_coupon_intents: coupon snapshot per PayPal order creation
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_coupon_intents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  paypal_order_id VARCHAR(64) NOT NULL,
  coupon_id INT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  discount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  free_shipping TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  INDEX idx_paypal (paypal_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- orders: record which code was used and how much it saved
-- ---------------------------------------------------------------
ALTER TABLE orders
  ADD COLUMN coupon_code VARCHAR(40) DEFAULT NULL AFTER amount_cents,
  ADD COLUMN discount_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER coupon_code;

-- Verify after running:
--   SHOW TABLES LIKE 'coupons';
--   SHOW TABLES LIKE 'order_coupon_intents';
--   SHOW COLUMNS FROM orders LIKE 'coupon_code';
--   SHOW COLUMNS FROM orders LIKE 'discount_cents';
