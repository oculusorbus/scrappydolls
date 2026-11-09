-- Migration 012: Bargain flag on products
--
-- Lets the admin move slow-moving or older dolls into a "Bargains" tab on
-- the shop. A bargain doll stays status='available' (still buyable) — the
-- flag just files it under Bargains and takes it out of the New and
-- Popular tabs. It still shows in All, with a Bargain badge.

ALTER TABLE products
  ADD COLUMN bargain TINYINT(1) NOT NULL DEFAULT 0 AFTER featured,
  ADD INDEX idx_bargain (bargain, status);
