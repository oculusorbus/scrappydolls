<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?: $_POST;
if (!is_array($body)) $body = [];

$orderId = trim((string)($body['order_id'] ?? ''));
if ($orderId === '') json_response(['error' => 'Missing order_id'], 400);

// ---- Resolve buyer contact + ship-to ----
// Authoritative source is the order_checkout_intents snapshot written by
// create-cart-order.php at order-creation time — same items/coupon PayPal
// locked the charge against. A re-POSTed body is NOT trusted when a
// snapshot exists, so the recorded ship-to and tax always match what was
// authorized. The body is used only as a legacy fallback for an order
// created before this snapshot existed (e.g. in flight during a deploy).
$snap = checkout_intent_for_order($orderId);
if ($snap) {
    $contactEmail = (string)$snap['customer_email'];
    $contactPhone = (string)$snap['customer_phone'];
    $buyerName    = (string)$snap['customer_name'];
    $isGift       = !empty($snap['is_gift']);
    $giftName     = (string)($snap['gift_recipient_name'] ?? '');
    $giftMessage  = (string)($snap['gift_message'] ?? '');
    $shippingForDb = json_decode((string)$snap['shipping_address'], true) ?: null;
    $snapTaxCents  = (int)$snap['tax_cents'];
} else {
    // Legacy fallback: validate the posted form the way the old flow did.
    $parsed = checkout_parse_submission($body);
    if ($parsed['errors']) {
        json_response(['error' => implode(' ', $parsed['errors']), 'fields' => $parsed['errors']], 422);
    }
    $d = $parsed['data'];
    $contactEmail = $d['email'];
    $contactPhone = $d['phone'];
    $buyerName    = $d['name'];
    $isGift       = !empty($d['is_gift']);
    $giftName     = (string)($d['gift_recipient_name'] ?? '');
    $giftMessage  = (string)($d['gift_message'] ?? '');
    $shippingForDb = $d['shipping_address'];
    $snapTaxCents  = 0; // old orders authorized no tax line
}

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
        cart_coupon_remove();
        unset($_SESSION['paypal_login_profile']);
        json_response(['ok' => true, 'order_id' => $orderId, 'duplicate' => true]);
    }

    // Authorize first — this confirms PayPal will hold the funds but doesn't charge yet.
    $authResp = paypal_authorize_order($orderId);
    $authId = paypal_extract_authorization_id($authResp);
    if (!$authId) {
        json_response(['error' => 'PayPal did not return an authorization id'], 502);
    }

    // Source of truth: the order_intents rows written when this PayPal
    // order was created. PayPal locked the charge amount against that
    // exact set of items, so we bill, ship, and mark sold against THAT
    // list — never the session cart, which can drift if the buyer
    // modifies their cart in another tab between approval and this submit.
    $intentStmt = db()->prepare(
        'SELECT product_id, amount_cents
         FROM order_intents
         WHERE paypal_order_id = :p
         ORDER BY id ASC'
    );
    $intentStmt->execute([':p' => $orderId]);
    $intentRows = $intentStmt->fetchAll();
    if (!$intentRows) {
        try { paypal_void_authorization($authId); } catch (Throwable $vErr) {
            error_log('Void on missing order intents failed: ' . $vErr->getMessage());
        }
        json_response(['error' => 'We could not find your order. Please return to the cart and try again.'], 400);
    }
    $cartIds = [];
    $intentPriceById = [];
    foreach ($intentRows as $r) {
        $pid = (int)$r['product_id'];
        $cartIds[] = $pid;
        $intentPriceById[$pid] = (int)$r['amount_cents'];
    }

    // Re-validate every item is still available. If a doll sold to
    // another buyer between order creation and now, we void the auth so
    // no money moves and surface the conflict.
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

    // Use prices snapshotted at order-creation time (intents), not the
    // current products.price_cents — the buyer paid the price PayPal
    // authorized. Coupon from order_coupon_intents, tax from the checkout
    // snapshot — all fixed at create time so the recorded total equals the
    // authorized amount exactly.
    $itemsTotal = 0;
    foreach ($cartIds as $id) $itemsTotal += $intentPriceById[$id];
    $couponIntent  = coupon_intent_for_order($orderId);
    $discountCents = $couponIntent ? min((int)$couponIntent['discount_cents'], $itemsTotal) : 0;
    $shippingCents = ($couponIntent && !empty($couponIntent['free_shipping']))
        ? 0
        : shipping_cents(count($cartIds), $itemsTotal);
    $taxCents   = max(0, $snapTaxCents);
    $totalCents = $itemsTotal - $discountCents + $shippingCents + $taxCents;
    $currency = paypal_currency();

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
                (product_id, paypal_order_id, paypal_capture_id, amount_cents,
                 coupon_code, discount_cents, tax_cents, currency,
                 customer_email, customer_name, customer_phone,
                 shipping_address, is_gift, gift_recipient_name, gift_message,
                 status, paid_at,
                 utm_source, utm_medium, utm_campaign, session_hash, referrer_host)
            VALUES
                (NULL, :poid, :pcid, :amt,
                 :ccode, :disc, :tax, :cur,
                 :email, :name, :phone,
                 :ship, :gift, :grname, :gmsg,
                 "paid", NOW(),
                 :usrc, :umed, :ucamp, :sh, :rh)
        ');
        $ins->execute([
            ':poid'   => $orderId,
            ':pcid'   => $captureId,
            ':amt'    => $totalCents,
            ':ccode'  => $couponIntent ? (string)$couponIntent['code'] : null,
            ':disc'   => $discountCents,
            ':tax'    => $taxCents,
            ':cur'    => $currency,
            ':email'  => $contactEmail,
            ':name'   => $buyerName,
            ':phone'  => $contactPhone,
            ':ship'   => $shippingForDb ? json_encode($shippingForDb) : null,
            ':gift'   => $isGift ? 1 : 0,
            ':grname' => $isGift ? $giftName : null,
            ':gmsg'   => ($isGift && $giftMessage !== '') ? $giftMessage : null,
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
                ':amt'   => $intentPriceById[$id],
                ':cur'   => $currency,
            ]);
        }

        // Count the coupon use only on a completed purchase — abandoned
        // checkouts don't burn a limited-use code.
        if ($couponIntent) {
            $cup = $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = :id');
            $cup->execute([':id' => (int)$couponIntent['coupon_id']]);
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
    cart_coupon_remove();
    unset($_SESSION['paypal_login_profile']);

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
