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

function mail_admin_new_order(array $order, array $product): void {
    $cfg = config('mail');
    $to = $cfg['admin_email'] ?? null;
    if (!$to) return;
    $title = $product['title'] ?? '(unknown doll)';
    $price = fmt_price((int)$order['amount_cents']);
    $cust = $order['customer_name'] ?: ($order['customer_email'] ?: 'unknown buyer');
    $ship = '';
    if (!empty($order['shipping_address'])) {
        $addr = is_string($order['shipping_address'])
            ? json_decode($order['shipping_address'], true)
            : $order['shipping_address'];
        if ($addr) {
            $a = $addr['address'] ?? $addr;
            $ship = "Ship to:\n"
                  . trim(($a['address_line_1'] ?? '') . "\n"
                       . ($a['address_line_2'] ?? '') . "\n"
                       . trim(($a['admin_area_2'] ?? '') . ', ' . ($a['admin_area_1'] ?? '') . ' ' . ($a['postal_code'] ?? '')) . "\n"
                       . ($a['country_code'] ?? '')) . "\n\n";
        }
    }
    $body = "You sold a doll!\n\n"
          . "Doll: $title\n"
          . "Price: $price\n"
          . "Buyer: $cust\n"
          . ($order['customer_email'] ? "Email: {$order['customer_email']}\n" : '')
          . "PayPal Order: {$order['paypal_order_id']}\n\n"
          . $ship
          . "Manage in admin: " . url('admin/order.php?id=' . (int)$order['id']) . "\n";
    send_mail($to, "New order — $title ($price)", $body, $order['customer_email'] ?: null);
}

function mail_customer_receipt(array $order, array $product): void {
    $email = $order['customer_email'] ?? null;
    if (!$email) return;
    $cfg = config('mail');
    $replyTo = $cfg['admin_email'] ?? null;
    $title = $product['title'] ?? 'your doll';
    $price = fmt_price((int)$order['amount_cents']);
    $body = "Thank you for your order!\n\n"
          . "$title — $price\n\n"
          . "Your doll will be hand-packed and shipped within a few days. "
          . "You'll get a separate note from PayPal with your payment receipt.\n\n"
          . "If you have questions, just reply to this email.\n\n"
          . "— Kanda Kay\n  scrappydolls.com\n";
    send_mail($email, "Order confirmed — $title", $body, $replyTo);
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

function mail_admin_new_order_multi(array $order, array $items): void {
    $cfg = config('mail');
    $to = $cfg['admin_email'] ?? null;
    if (!$to) return;
    $total = fmt_price((int)$order['amount_cents']);
    $cust = $order['customer_name'] ?: ($order['customer_email'] ?: 'unknown buyer');
    $lines = '';
    foreach ($items as $it) {
        $lines .= '  • ' . ($it['title_snapshot'] ?? '(unknown)')
               . '  — ' . fmt_price((int)$it['amount_cents']) . "\n";
    }
    $count = count($items);
    $body = "You sold $count " . ($count === 1 ? 'doll' : 'dolls') . "!\n\n"
          . "Items:\n$lines\n"
          . "Total: $total\n"
          . "Buyer: $cust\n"
          . ($order['customer_email'] ? "Email: {$order['customer_email']}\n" : '')
          . "PayPal Order: {$order['paypal_order_id']}\n\n"
          . _mail_format_shipping($order['shipping_address'] ?? null)
          . "Manage in admin: " . url('admin/order.php?id=' . (int)$order['id']) . "\n";
    $subject = $count === 1
        ? "New order — " . ($items[0]['title_snapshot'] ?? 'doll') . " ($total)"
        : "New order — $count dolls ($total)";
    send_mail($to, $subject, $body, $order['customer_email'] ?: null);
}

function mail_customer_receipt_multi(array $order, array $items): void {
    $email = $order['customer_email'] ?? null;
    if (!$email) return;
    $cfg = config('mail');
    $replyTo = $cfg['admin_email'] ?? null;
    $total = fmt_price((int)$order['amount_cents']);
    $count = count($items);
    $lines = '';
    foreach ($items as $it) {
        $lines .= '  • ' . ($it['title_snapshot'] ?? '(unknown)')
               . '  — ' . fmt_price((int)$it['amount_cents']) . "\n";
    }
    $intro = $count === 1
        ? "Thank you for your order!\n\n"
        : "Thank you for your order of $count dolls!\n\n";
    $body = $intro
          . $lines . "\n"
          . "Total: $total\n\n"
          . ($count === 1 ? "Your doll" : "Your dolls") . " will be hand-packed and shipped within a few days. "
          . "You'll get a separate note from PayPal with your payment receipt.\n\n"
          . "If you have questions, just reply to this email.\n\n"
          . "— Kanda Kay\n  scrappydolls.com\n";
    $subject = $count === 1
        ? "Order confirmed — " . ($items[0]['title_snapshot'] ?? 'your doll')
        : "Order confirmed — $count dolls";
    send_mail($email, $subject, $body, $replyTo);
}
