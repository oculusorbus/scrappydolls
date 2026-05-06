<?php
declare(strict_types=1);

/**
 * Lightweight wrapper around PHP mail(). Many shared hosts allow this; if
 * yours doesn't, install PHPMailer via Composer and replace this body.
 */
function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool {
    $cfg = config('mail');
    $from = $cfg['from_email'] ?? 'no-reply@localhost';
    $fromName = $cfg['from_name'] ?? 'Scrappy Dolls';
    $headers = [
        'From: ' . _mail_addr($fromName, $from),
        'Reply-To: ' . ($replyTo ?: $from),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: scrappydolls/1.0',
    ];
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function _mail_addr(string $name, string $email): string {
    $name = preg_replace('/[\r\n,<>]/', '', $name);
    return $name === '' ? $email : "$name <$email>";
}

function _mail_format_shipping(?string $shipJson): string {
    if (!$shipJson) return '';
    $addr = json_decode($shipJson, true);
    if (!$addr) return '';
    $a = $addr['address'] ?? $addr;
    return "Ship to:\n"
         . trim(($addr['name'] ?? '') . "\n"
              . ($a['address_line_1'] ?? '') . "\n"
              . ($a['address_line_2'] ?? '') . "\n"
              . trim(($a['admin_area_2'] ?? '') . ', ' . ($a['admin_area_1'] ?? '') . ' ' . ($a['postal_code'] ?? '')) . "\n"
              . ($a['country_code'] ?? '')) . "\n\n";
}

function _mail_order_breakdown(array $order, array $items): array {
    $itemsTotal = 0;
    foreach ($items as $it) $itemsTotal += (int)$it['amount_cents'];
    $orderTotal = (int)$order['amount_cents'];
    return [
        'items_total'   => $itemsTotal,
        'shipping'      => max(0, $orderTotal - $itemsTotal),
        'order_total'   => $orderTotal,
    ];
}

function mail_admin_new_order_multi(array $order, array $items): void {
    $cfg = config('mail');
    $to = $cfg['admin_email'] ?? null;
    if (!$to) return;
    $b = _mail_order_breakdown($order, $items);
    $cust = $order['customer_name'] ?: ($order['customer_email'] ?: 'unknown buyer');
    $lines = '';
    foreach ($items as $it) {
        $lines .= '  • ' . ($it['title_snapshot'] ?? '(unknown)')
               . '  — ' . fmt_price((int)$it['amount_cents']) . "\n";
    }
    $count = count($items);
    $totalsBlock = "Subtotal: " . fmt_price($b['items_total']) . "\n"
                 . ($b['shipping'] > 0 ? "Shipping: " . fmt_price($b['shipping']) . "\n" : '')
                 . "Total: " . fmt_price($b['order_total']) . "\n";
    $isGift = !empty($order['is_gift']);
    $contactBlock = "Buyer: $cust\n"
          . ($order['customer_email'] ? "Email: {$order['customer_email']}\n" : '')
          . (!empty($order['customer_phone']) ? "Phone: {$order['customer_phone']}\n" : '');
    $giftBlock = $isGift
        ? "** GIFT ORDER **\nAddress the package to: " . ($order['gift_recipient_name'] ?? '(missing recipient name)') . "\n\n"
        : '';
    $body = "You sold $count " . ($count === 1 ? 'doll' : 'dolls') . "!\n\n"
          . "Items:\n$lines\n"
          . $totalsBlock . "\n"
          . $giftBlock
          . $contactBlock
          . "PayPal Order: {$order['paypal_order_id']}\n\n"
          . _mail_format_shipping($order['shipping_address'] ?? null)
          . "Manage in admin: " . url('admin/order.php?id=' . (int)$order['id']) . "\n";
    $totalStr = fmt_price($b['order_total']);
    $subject = $count === 1
        ? "New order — " . ($items[0]['title_snapshot'] ?? 'doll') . " ($totalStr)"
        : "New order — $count dolls ($totalStr)";
    send_mail($to, $subject, $body, $order['customer_email'] ?: null);
}

function mail_customer_receipt_multi(array $order, array $items): void {
    $email = $order['customer_email'] ?? null;
    if (!$email) return;
    $cfg = config('mail');
    $replyTo = $cfg['admin_email'] ?? null;
    $b = _mail_order_breakdown($order, $items);
    $count = count($items);
    $lines = '';
    foreach ($items as $it) {
        $lines .= '  • ' . ($it['title_snapshot'] ?? '(unknown)')
               . '  — ' . fmt_price((int)$it['amount_cents']) . "\n";
    }
    $intro = $count === 1
        ? "Thank you for your order!\n\n"
        : "Thank you for your order of $count dolls!\n\n";
    $totalsBlock = "Subtotal: " . fmt_price($b['items_total']) . "\n"
                 . ($b['shipping'] > 0 ? "Shipping: " . fmt_price($b['shipping']) . "\n" : '')
                 . "Total: " . fmt_price($b['order_total']) . "\n";
    $body = $intro
          . $lines . "\n"
          . $totalsBlock . "\n"
          . ($count === 1 ? "Your doll" : "Your dolls") . " will be hand-packed and shipped within a few days. "
          . "You'll get a separate note from PayPal with your payment receipt.\n\n"
          . "If you have questions, just reply to this email.\n\n"
          . "— Kanda Kay\n  scrappydolls.com\n";
    $subject = $count === 1
        ? "Order confirmed — " . ($items[0]['title_snapshot'] ?? 'your doll')
        : "Order confirmed — $count dolls";
    send_mail($email, $subject, $body, $replyTo);
}
