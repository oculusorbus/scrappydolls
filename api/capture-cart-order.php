<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: $_POST;
$orderId = trim((string)($body['order_id'] ?? ''));
if ($orderId === '') json_response(['error' => 'Missing order_id'], 400);

try {
    // Track auth state so the outer catch can void any orphan hold if we
    // authorized but never finished capturing. Without this, every failed
    // retry leaves a hold on the buyer's card.
    $authId = null;
    $captureCompleted = false;

    // Idempotency: if already captured, return success.
    $existing = db()->prepare('SELECT id FROM orders WHERE paypal_order_id = :p LIMIT 1');
    $existing->execute([':p' => $orderId]);
    if ($existing->fetch()) {
        cart_clear();
        json_response(['ok' => true, 'order_id' => $orderId, 'duplicate' => true]);
    }

    // Authorize first — this confirms PayPal will hold the funds but doesn't charge yet.
    $authResp = paypal_authorize_order($orderId);
    $authId = paypal_extract_authorization_id($authResp);
    if (!$authId) {
        json_response(['error' => 'PayPal did not return an authorization id'], 502);
    }

    // Re-validate every cart item is still available. If anyone got here first,
    // we void the auth so no money moves and ask the buyer to retry.
    $cartIds = cart_ids();
    if (!$cartIds) {
        try { paypal_void_authorization($authId); } catch (Throwable $vErr) {
            error_log('Void on empty cart failed: ' . $vErr->getMessage());
        }
        json_response(['error' => 'Your cart is empty.'], 400);
    }
    $place = implode(',', array_fill(0, count($cartIds), '?'));
    $stmt = db()->prepare(
        "SELECT id, slug, title, price_cents, status FROM products WHERE id IN ($place)"
    );
    $stmt->execute($cartIds);
    $rows = $stmt->fetchAll();
    $byId = [];
    foreach ($rows as $r) $byId[(int)$r['id']] = $r;

    $unavailable = [];
    foreach ($cartIds as $id) {
        if (!isset($byId[$id]) || $byId[$id]['status'] !== 'available') {
            $unavailable[] = $byId[$id]['title'] ?? "Doll #$id";
        }
    }
    if ($unavailable) {
        try { paypal_void_authorization($authId); } catch (Throwable $vErr) {
            error_log('Void after race-loss failed: ' . $vErr->getMessage());
        }
        // Drop sold items from cart so a retry shows the buyer's remaining items.
        foreach ($cartIds as $id) {
            if (!isset($byId[$id]) || $byId[$id]['status'] !== 'available') {
                cart_remove($id);
            }
        }
        json_response([
            'error'       => count($unavailable) === 1
                ? "Sorry — \"{$unavailable[0]}\" sold to someone else while you were checking out."
                : 'Some dolls in your cart sold to other buyers while you were checking out.',
            'unavailable' => $unavailable,
            'cart_count'  => cart_count(),
        ], 409);
    }

    // All available. Capture the auth.
    $capResp = paypal_capture_authorization($authId);
    $captureId = $capResp['id'] ?? null;
    $capStatus = $capResp['status'] ?? '';
    if ($capStatus !== 'COMPLETED') {
        // Capture didn't complete (DECLINED/PENDING/etc). Void the hold so
        // the buyer's funds aren't tied up while they retry. False-positive
        // voiding of a transient PENDING is preferable to leaking holds.
        try { paypal_void_authorization($authId); } catch (Throwable $vErr) {
            error_log('Void after non-COMPLETED capture failed: ' . $vErr->getMessage());
        }
        json_response(['error' => "Payment not completed (status: $capStatus)"], 400);
    }
    $captureCompleted = true;

    // Re-fetch the order so we have the buyer/shipping data PayPal collected.
    $orderData = paypal_get_order($orderId);
    $payer    = paypal_extract_payer($orderData);
    $shipping = paypal_extract_shipping($orderData);
    $itemsTotal = 0;
    foreach ($cartIds as $id) $itemsTotal += (int)$byId[$id]['price_cents'];
    $shippingCents = shipping_cents_for_count(count($cartIds));
    $totalCents = $itemsTotal + $shippingCents;
    $currency = $orderData['purchase_units'][0]['amount']['currency_code'] ?? 'USD';

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Mark every doll sold; bail (rollback) if any race slipped through.
        $upd = $pdo->prepare("UPDATE products SET status='sold', sold_at=NOW() WHERE id=:id AND status='available'");
        foreach ($cartIds as $id) {
            $upd->execute([':id' => $id]);
            if ($upd->rowCount() !== 1) {
                throw new RuntimeException("Race lost on doll id $id between availability check and capture.");
            }
        }

        // Pull attribution from the most-recent matching intent (any item works — same cart).
        $attrStmt = $pdo->prepare('SELECT utm_source, utm_medium, utm_campaign, session_hash FROM order_intents WHERE paypal_order_id = :p ORDER BY id DESC LIMIT 1');
        $attrStmt->execute([':p' => $orderId]);
        $attr = $attrStmt->fetch() ?: null;
        $ref = tracking_referrer();

        $ins = $pdo->prepare('
            INSERT INTO orders
                (product_id, paypal_order_id, paypal_capture_id, amount_cents, currency,
                 customer_email, customer_name, shipping_address, status, paid_at,
                 utm_source, utm_medium, utm_campaign, session_hash, referrer_host)
            VALUES
                (NULL, :poid, :pcid, :amt, :cur, :email, :name, :ship, "paid", NOW(),
                 :usrc, :umed, :ucamp, :sh, :rh)
        ');
        $ins->execute([
            ':poid'   => $orderId,
            ':pcid'   => $captureId,
            ':amt'    => $totalCents,
            ':cur'    => $currency,
            ':email'  => $payer['email'],
            ':name'   => $payer['name'],
            ':ship'   => $shipping ? json_encode($shipping) : null,
            ':usrc'   => $attr['utm_source']   ?? null,
            ':umed'   => $attr['utm_medium']   ?? null,
            ':ucamp'  => $attr['utm_campaign'] ?? null,
            ':sh'     => $attr['session_hash'] ?? tracking_session_hash(),
            ':rh'     => $ref['host'] ?? null,
        ]);
        $newOrderId = (int)$pdo->lastInsertId();

        $itemIns = $pdo->prepare('
            INSERT INTO order_items (order_id, product_id, title_snapshot, amount_cents, currency)
            VALUES (:oid, :pid, :title, :amt, :cur)
        ');
        foreach ($cartIds as $id) {
            $p = $byId[$id];
            $itemIns->execute([
                ':oid'   => $newOrderId,
                ':pid'   => (int)$p['id'],
                ':title' => $p['title'],
                ':amt'   => (int)$p['price_cents'],
                ':cur'   => $currency,
            ]);
        }

        $pdo->commit();
        track_intent_captured($orderId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('capture-cart-order DB error: ' . $e->getMessage());
        // Funds were captured but our records are inconsistent. Don't void
        // (the buyer was charged) — let admin reconcile via webhook + manual review.
        throw $e;
    }

    cart_clear();

    // Send notifications (best-effort; don't fail the response).
    try {
        $orderRow = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $orderRow->execute([':id' => $newOrderId]);
        $order = $orderRow->fetch();
        $itemsRows = $pdo->prepare('
            SELECT oi.*, p.slug
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :id
            ORDER BY oi.id ASC
        ');
        $itemsRows->execute([':id' => $newOrderId]);
        $orderItems = $itemsRows->fetchAll();
        if ($order && $orderItems) {
            mail_admin_new_order_multi($order, $orderItems);
            mail_customer_receipt_multi($order, $orderItems);
        }
    } catch (Throwable $mailErr) {
        error_log('Order email error: ' . $mailErr->getMessage());
    }

    json_response(['ok' => true, 'order_id' => $orderId]);
} catch (Throwable $e) {
    error_log('capture-cart-order error: ' . $e->getMessage());
    // If we authorized but never finished capturing, release the hold so the
    // buyer doesn't accumulate authorizations across retries.
    if (!empty($authId) && empty($captureCompleted)) {
        try {
            paypal_void_authorization($authId);
        } catch (Throwable $vErr) {
            error_log('Void on capture-cart-order failure failed for auth ' . $authId . ': ' . $vErr->getMessage());
        }
    }
    json_response(['error' => 'Could not capture payment: ' . $e->getMessage()], 500);
}
