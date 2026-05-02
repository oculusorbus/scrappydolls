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
        // Backup path in case onApprove never reached capture-order.php
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

        // Idempotent insert
        $exists = db()->prepare('SELECT id FROM orders WHERE paypal_order_id = :p');
        $exists->execute([':p' => $orderLink]);
        if ($exists->fetch()) break;

        try {
            $orderData = paypal_get_order($orderLink);
            $refId = $orderData['purchase_units'][0]['reference_id'] ?? '';
            if (!preg_match('/^doll-(\d+)$/', $refId, $m)) break;
            $productId = (int)$m[1];

            $payer    = paypal_extract_payer($orderData);
            $shipping = paypal_extract_shipping($orderData);
            $amt = $resource['amount']['value'] ?? '0';
            $currency = $resource['amount']['currency_code'] ?? 'USD';

            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE products SET status='sold', sold_at=COALESCE(sold_at, NOW()) WHERE id=:id AND status='available'")
                ->execute([':id' => $productId]);

            $ins = $pdo->prepare('
                INSERT INTO orders
                    (product_id, paypal_order_id, paypal_capture_id, amount_cents, currency,
                     customer_email, customer_name, shipping_address, status, paid_at)
                VALUES
                    (:pid, :poid, :pcid, :amt, :cur, :email, :name, :ship, "paid", NOW())
            ');
            $ins->execute([
                ':pid'   => $productId,
                ':poid'  => $orderLink,
                ':pcid'  => $captureId,
                ':amt'   => (int)round(((float)$amt) * 100),
                ':cur'   => $currency,
                ':email' => $payer['email'],
                ':name'  => $payer['name'],
                ':ship'  => $shipping ? json_encode($shipping) : null,
            ]);
            $newOrderId = (int)$pdo->lastInsertId();
            $pdo->commit();

            $prodStmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
            $prodStmt->execute([':id' => $productId]);
            $product = $prodStmt->fetch();
            $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
            $orderStmt->execute([':id' => $newOrderId]);
            $order = $orderStmt->fetch();
            if ($order && $product) {
                @mail_admin_new_order($order, $product);
                @mail_customer_receipt($order, $product);
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
