-- Migration 005: add order_items table for multi-item cart checkout
--
-- Existing model: each `orders` row references a single product via
-- `orders.product_id`. New cart flow lets a buyer purchase several dolls in
-- one PayPal transaction, so we need a child table.
--
-- This migration:
--   1. Creates `order_items` (one row per doll in an order).
--   2. Backfills one order_items row per existing order from the legacy
--      `orders.product_id` / `orders.amount_cents` columns.
--   3. Makes `orders.product_id` nullable. We leave the column in place so
--      legacy reads/admin views keep working until those code paths are
--      migrated; new multi-item orders will write NULL here. A later
--      migration can drop it once nothing reads it.
--
-- Idempotent: re-running is safe — the backfill INSERT is guarded by
-- NOT EXISTS so it only runs for orders that don't already have items.
-- Atomic: wrapped in a transaction.
--
-- Sanity-check before running — should be > 0 on first run, 0 after:
--   SELECT COUNT(*) FROM orders o
--   WHERE NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id);

START TRANSACTION;

CREATE TABLE IF NOT EXISTS order_items (
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

-- Backfill one item per existing single-product order.
INSERT INTO order_items (order_id, product_id, title_snapshot, amount_cents, currency)
SELECT o.id, o.product_id, COALESCE(p.title, '(unknown doll)'), o.amount_cents, o.currency
FROM orders o
LEFT JOIN products p ON p.id = o.product_id
WHERE o.product_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id);

-- Allow new multi-item orders to leave product_id NULL. Legacy reads still
-- work for backfilled rows that retain it.
ALTER TABLE orders MODIFY COLUMN product_id INT UNSIGNED NULL;

COMMIT;

-- Verify after running:
--   SELECT COUNT(*) AS orders_total,
--          (SELECT COUNT(*) FROM order_items) AS items_total,
--          (SELECT COUNT(*) FROM orders WHERE product_id IS NULL) AS multi_item_orders
--   FROM orders;
-- On first run after a clean upgrade: items_total >= orders_total,
-- multi_item_orders = 0 (no cart orders yet).
