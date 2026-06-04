<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

$raw = file_get_contents('php://input') ?: '';
$headers = get_request_headers();

$verified = paypal_verify_webhook($headers, $raw);
if (!$verified) {
    error_log('PayPal webhook signature verification FAILED. Headers: ' . json_encode($headers));
    http_response_code(400);
    echo 'invalid signature';
    exit;
}

$event = json_decode($raw, true) ?: [];
$type = $event['event_type'] ?? '';
$resource = $event['resource'] ?? [];

switch ($type) {
    case 'PAYMENT.CAPTURE.COMPLETED':
        // Backup path in case onApprove never reached capture-cart-order.php.
        // The capture event references the parent order via a "up" link.
        $captureId = $resource['id'] ?? null;
        $orderLink = null;
        foreach (($resource['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'up') {
                if (preg_match('#/orders/([^/]+)#', $link['href'] ?? '', $m)) {
                    $orderLink = $m[1];
                }
            }
        }
        if (!$orderLink) break;

        // Idempotent: skip if we already wrote this order.
        $exists = db()->prepare('SELECT id FROM orders WHERE paypal_order_id = :p');
        $exists->execute([':p' => $orderLink]);
        if ($exists->fetch()) break;

        try {
            $orderData = paypal_get_order($orderLink);
            $unit = $orderData['purchase_units'][0] ?? [];
            $payer    = paypal_extract_payer($orderData);
            $shipping = paypal_extract_shipping($orderData);
            $totalAmt = $resource['amount']['value'] ?? ($unit['amount']['value'] ?? '0');
            $currency = $resource['amount']['currency_code'] ?? ($unit['amount']['currency_code'] ?? 'USD');

            // Pull product IDs from PayPal items[] (sku = "doll-<id>"), falling back
            // to the legacy single-product reference_id format for old orders.
            $productIds = [];
            foreach (($unit['items'] ?? []) as $it) {
                if (preg_match('/^doll-(\d+)$/', (string)($it['sku'] ?? ''), $m)) {
                    $productIds[] = (int)$m[1];
                }
            }
            if (!$productIds && preg_match('/^doll-(\d+)$/', (string)($unit['reference_id'] ?? ''), $m)) {
                $productIds = [(int)$m[1]];
            }
            if (!$productIds) break;

            // Fetch product snapshots for the items we're inserting.
            $place = implode(',', array_fill(0, count($productIds), '?'));
            $pStmt = db()->prepare("SELECT id, title, price_cents FROM products WHERE id IN ($place)");
            $pStmt->execute($productIds);
            $byId = [];
            foreach ($pStmt->fetchAll() as $p) $byId[(int)$p['id']] = $p;

            // Coupon snapshot from order creation (if any) — the captured
            // amount already reflects it; this records which code was used.
            $couponIntent = coupon_intent_for_order($orderLink);

            $pdo = db();
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE products SET status='sold', sold_at=COALESCE(sold_at, NOW()) WHERE id=:id AND status='available'");
            foreach ($productIds as $pid) $upd->execute([':id' => $pid]);

            $ins = $pdo->prepare('
                INSERT INTO orders
                    (product_id, paypal_order_id, paypal_capture_id, amount_cents,
                     coupon_code, discount_cents, currency,
                     customer_email, customer_name, shipping_address, status, paid_at)
                VALUES
                    (NULL, :poid, :pcid, :amt, :ccode, :disc, :cur, :email, :name, :ship, "paid", NOW())
            ');
            $ins->execute([
                ':poid'  => $orderLink,
                ':pcid'  => $captureId,
                ':amt'   => (int)round(((float)$totalAmt) * 100),
                ':ccode' => $couponIntent ? (string)$couponIntent['code'] : null,
                ':disc'  => $couponIntent ? (int)$couponIntent['discount_cents'] : 0,
                ':cur'   => $currency,
                ':email' => $payer['email'],
                ':name'  => $payer['name'],
                ':ship'  => $shipping ? json_encode($shipping) : null,
            ]);
            $newOrderId = (int)$pdo->lastInsertId();

            $itemIns = $pdo->prepare('
                INSERT INTO order_items (order_id, product_id, title_snapshot, amount_cents, currency)
                VALUES (:oid, :pid, :title, :amt, :cur)
            ');
            foreach ($productIds as $pid) {
                $p = $byId[$pid] ?? null;
                $itemIns->execute([
                    ':oid'   => $newOrderId,
                    ':pid'   => $pid,
                    ':title' => $p['title'] ?? "Doll #$pid",
                    ':amt'   => (int)($p['price_cents'] ?? 0),
                    ':cur'   => $currency,
                ]);
            }
            if ($couponIntent) {
                $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id')
                    ->execute([':id' => (int)$couponIntent['coupon_id']]);
            }
            $pdo->commit();

            $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
            $orderStmt->execute([':id' => $newOrderId]);
            $order = $orderStmt->fetch();
            $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC');
            $itemsStmt->execute([':id' => $newOrderId]);
            $items = $itemsStmt->fetchAll();
            if ($order && $items) {
                @mail_admin_new_order_multi($order, $items);
                @mail_customer_receipt_multi($order, $items);
            }
        } catch (Throwable $e) {
            error_log('Webhook capture-completed error: ' . $e->getMessage());
        }
        break;

    case 'PAYMENT.CAPTURE.REFUNDED':
        $captureId = $resource['id'] ?? null;
        if (!$captureId) break;
        try {
            db()->prepare("UPDATE orders SET status='refunded' WHERE paypal_capture_id = :cid")
                ->execute([':cid' => $captureId]);
        } catch (Throwable $e) {
            error_log('Webhook refund error: ' . $e->getMessage());
        }
        break;

    case 'PAYMENT.CAPTURE.DENIED':
        $captureId = $resource['id'] ?? null;
        if (!$captureId) break;
        try {
            db()->prepare("UPDATE orders SET status='failed' WHERE paypal_capture_id = :cid")
                ->execute([':cid' => $captureId]);
        } catch (Throwable $e) {
            error_log('Webhook denied error: ' . $e->getMessage());
        }
        break;

    default:
        // ignore other events
        break;
}

http_response_code(200);
echo 'ok';
