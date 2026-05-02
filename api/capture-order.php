<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw  = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: $_POST;
$orderId   = trim((string)($body['order_id'] ?? ''));
$productId = (int)($body['product_id'] ?? 0);
if ($orderId === '' || !$productId) json_response(['error' => 'Missing order_id or product_id'], 400);

try {
    $stmt = db()->prepare('SELECT id, slug, title, price_cents, status FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch();
    if (!$product) json_response(['error' => 'Doll not found'], 404);

    // Idempotency: if already captured, just return success
    $existing = db()->prepare('SELECT id, status FROM orders WHERE paypal_order_id = :pid LIMIT 1');
    $existing->execute([':pid' => $orderId]);
    if ($existingRow = $existing->fetch()) {
        json_response(['ok' => true, 'order_id' => $orderId, 'duplicate' => true]);
    }

    if ($product['status'] !== 'available') {
        // Race: another buyer captured first. Confirm this order didn't already capture.
        $check = paypal_get_order($orderId);
        $captured = paypal_extract_capture_id($check);
        if (!$captured) {
            json_response(['error' => 'This doll just sold to someone else.'], 409);
        }
        // If it actually did capture, fall through to record it (defensive).
    }

    $captureResp = paypal_capture_order($orderId);
    $status = $captureResp['status'] ?? '';
    if ($status !== 'COMPLETED') {
        json_response(['error' => "Payment not completed (status: $status)"], 400);
    }

    $captureId = paypal_extract_capture_id($captureResp);
    $payer     = paypal_extract_payer($captureResp);
    $shipping  = paypal_extract_shipping($captureResp);

    $amount = $captureResp['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null;
    $amountCents = $amount !== null ? (int)round(((float)$amount) * 100) : (int)$product['price_cents'];
    $currency = $captureResp['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? 'USD';

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Mark product sold (only if still available — protects against race)
        $upd = $pdo->prepare("UPDATE products SET status='sold', sold_at=NOW() WHERE id=:id AND status='available'");
        $upd->execute([':id' => $product['id']]);

        // Pull UTMs/session attribution from the buyer's session if we recorded an intent
        $attr = null;
        $intentSel = $pdo->prepare('SELECT utm_source, utm_medium, utm_campaign, session_hash FROM order_intents WHERE paypal_order_id = :p ORDER BY id DESC LIMIT 1');
        $intentSel->execute([':p' => $orderId]);
        $attr = $intentSel->fetch() ?: null;

        $ref = tracking_referrer();

        $ins = $pdo->prepare('
            INSERT INTO orders
                (product_id, paypal_order_id, paypal_capture_id, amount_cents, currency,
                 customer_email, customer_name, shipping_address, status, paid_at,
                 utm_source, utm_medium, utm_campaign, session_hash, referrer_host)
            VALUES
                (:pid, :poid, :pcid, :amt, :cur, :email, :name, :ship, :status, NOW(),
                 :usrc, :umed, :ucamp, :sh, :rh)
        ');
        $ins->execute([
            ':pid'    => $product['id'],
            ':poid'   => $orderId,
            ':pcid'   => $captureId,
            ':amt'    => $amountCents,
            ':cur'    => $currency,
            ':email'  => $payer['email'],
            ':name'   => $payer['name'],
            ':ship'   => $shipping ? json_encode($shipping) : null,
            ':status' => 'paid',
            ':usrc'   => $attr['utm_source']   ?? null,
            ':umed'   => $attr['utm_medium']   ?? null,
            ':ucamp'  => $attr['utm_campaign'] ?? null,
            ':sh'     => $attr['session_hash'] ?? tracking_session_hash(),
            ':rh'     => $ref['host'] ?? null,
        ]);
        $newOrderId = (int)$pdo->lastInsertId();
        $pdo->commit();
        track_intent_captured($orderId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Send notifications (best-effort; don't fail the response)
    try {
        $orderRow = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $orderRow->execute([':id' => $newOrderId]);
        $order = $orderRow->fetch();
        if ($order) {
            mail_admin_new_order($order, $product);
            mail_customer_receipt($order, $product);
        }
    } catch (Throwable $mailErr) {
        error_log('Order email error: ' . $mailErr->getMessage());
    }

    json_response(['ok' => true, 'order_id' => $orderId]);
} catch (Throwable $e) {
    error_log('capture-order error: ' . $e->getMessage());
    json_response(['error' => 'Could not capture payment: ' . $e->getMessage()], 500);
}
