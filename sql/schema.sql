-- Scrappy Dolls store — schema
-- Run once on a fresh MySQL/MariaDB database.
-- Charset: utf8mb4 (full Unicode incl. emoji).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_checkout_intents;
DROP TABLE IF EXISTS order_coupon_intents;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS admin_users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- admin_users: who can log in to /admin
-- ---------------------------------------------------------------
CREATE TABLE admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- products: one row per doll (OOAK = sold once)
-- ---------------------------------------------------------------
CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(255) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  price_cents INT UNSIGNED NOT NULL,
  status ENUM('draft','available','sold') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  sold_at DATETIME DEFAULT NULL,
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- product_images: one or more images per product
-- ---------------------------------------------------------------
CREATE TABLE product_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  filename VARCHAR(255) NOT NULL,
  alt_text VARCHAR(500) DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_product (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- coupons: discount codes managed in /admin/coupons.php
-- ---------------------------------------------------------------
CREATE TABLE coupons (
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
-- orders: one row per completed PayPal capture
-- ---------------------------------------------------------------
CREATE TABLE orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NULL,
  paypal_order_id VARCHAR(64) NOT NULL UNIQUE,
  paypal_capture_id VARCHAR(64) DEFAULT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  coupon_code VARCHAR(40) DEFAULT NULL,
  discount_cents INT UNSIGNED NOT NULL DEFAULT 0,
  tax_cents INT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  customer_email VARCHAR(255) DEFAULT NULL,
  customer_name VARCHAR(255) DEFAULT NULL,
  customer_phone VARCHAR(40) DEFAULT NULL,
  shipping_address JSON DEFAULT NULL,
  is_gift TINYINT(1) NOT NULL DEFAULT 0,
  gift_recipient_name VARCHAR(255) DEFAULT NULL,
  gift_message TEXT DEFAULT NULL,
  status ENUM('pending','paid','shipped','refunded','failed') NOT NULL DEFAULT 'pending',
  tracking_number VARCHAR(255) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME DEFAULT NULL,
  shipped_at DATETIME DEFAULT NULL,
  FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_status (status),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- order_items: line items for cart-based multi-doll orders
-- ---------------------------------------------------------------
CREATE TABLE order_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  title_snapshot VARCHAR(255) NOT NULL,
  amount_cents INT UNSIGNED NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_order (order_id),
  INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- order_coupon_intents: coupon snapshot per PayPal order creation.
-- Mirrors order_intents — capture reads this row, never the session.
-- ---------------------------------------------------------------
CREATE TABLE order_coupon_intents (
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
-- order_checkout_intents: confirmed contact + ship-to + priced
-- breakdown, snapshotted per PayPal order at creation time. Capture
-- reads this row, never the session — so the recorded charge, tax,
-- and ship-to always match what PayPal authorized.
-- ---------------------------------------------------------------
CREATE TABLE order_checkout_intents (
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
