-- Migration 002: analytics tracking
-- Adds page_views (every visit), order_intents (Buy clicks),
-- and UTM attribution columns on orders.
--
-- Safe to run on a database created from schema.sql.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- page_views: every public-page hit (deduped at query time by session)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED DEFAULT NULL,
  path VARCHAR(255) NOT NULL,
  referrer VARCHAR(500) DEFAULT NULL,
  referrer_host VARCHAR(255) DEFAULT NULL,
  utm_source VARCHAR(100) DEFAULT NULL,
  utm_medium VARCHAR(100) DEFAULT NULL,
  utm_campaign VARCHAR(100) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  session_hash CHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  INDEX idx_created (created_at),
  INDEX idx_product (product_id, created_at),
  INDEX idx_session (session_hash),
  INDEX idx_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- order_intents: every PayPal order created (Buy button clicked)
-- One per create-order.php call. May or may not become an order.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_intents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  paypal_order_id VARCHAR(64) DEFAULT NULL,
  session_hash CHAR(64) DEFAULT NULL,
  ip_hash CHAR(64) DEFAULT NULL,
  utm_source VARCHAR(100) DEFAULT NULL,
  utm_medium VARCHAR(100) DEFAULT NULL,
  utm_campaign VARCHAR(100) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  amount_cents INT UNSIGNED DEFAULT NULL,
  status ENUM('created','captured','abandoned') NOT NULL DEFAULT 'created',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  captured_at DATETIME DEFAULT NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_created (created_at),
  INDEX idx_product (product_id),
  INDEX idx_session (session_hash),
  INDEX idx_status (status),
  INDEX idx_paypal (paypal_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Add attribution columns to orders
-- ---------------------------------------------------------------
ALTER TABLE orders
  ADD COLUMN utm_source VARCHAR(100) DEFAULT NULL AFTER notes,
  ADD COLUMN utm_medium VARCHAR(100) DEFAULT NULL AFTER utm_source,
  ADD COLUMN utm_campaign VARCHAR(100) DEFAULT NULL AFTER utm_medium,
  ADD COLUMN session_hash CHAR(64) DEFAULT NULL AFTER utm_campaign,
  ADD COLUMN referrer_host VARCHAR(255) DEFAULT NULL AFTER session_hash,
  ADD INDEX idx_session_hash (session_hash),
  ADD INDEX idx_utm_source (utm_source);
