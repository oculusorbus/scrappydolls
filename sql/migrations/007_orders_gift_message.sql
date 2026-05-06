-- Migration 007: optional gift message included with the package
--
-- Buyers checking "This is a gift" on /shop/confirm.php can now write a
-- short note. The admin sees the note prominently on the order page and
-- in the new-order email; whoever packs the order is responsible for
-- physically including it with the doll.
--
-- Re-running this migration will fail with "Duplicate column" — that's
-- expected and harmless; the column is already there.

ALTER TABLE orders
  ADD COLUMN gift_message TEXT NULL AFTER gift_recipient_name;

-- Verify after running:
--   SHOW COLUMNS FROM orders LIKE 'gift_message';
