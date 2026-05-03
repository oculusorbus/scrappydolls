-- Migration 003: Featured flag on products
-- Lets the admin pin specific dolls to the top of landing-page surfaces
-- (carousel + roster). Featured dolls take precedence; remaining slots
-- pad with random available, then random sold.

ALTER TABLE products
  ADD COLUMN featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD INDEX idx_featured (featured, status);
