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
    $discount   = (int)($order['discount_cents'] ?? 0);
    $tax        = (int)($order['tax_cents'] ?? 0);
    return [
        'items_total'   => $itemsTotal,
        'discount'      => $discount,
        'coupon_code'   => $order['coupon_code'] ?? null,
        'tax'           => $tax,
        // Shipping is whatever's left after items − discount + tax.
        'shipping'      => max(0, $orderTotal - ($itemsTotal - $discount) - $tax),
        'order_total'   => $orderTotal,
    ];
}

function _mail_totals_block(array $b): string {
    $out = 'Subtotal: ' . fmt_price($b['items_total']) . "\n";
    if ($b['discount'] > 0 && !empty($b['coupon_code'])) {
        $out .= 'Discount (' . $b['coupon_code'] . '): -' . fmt_price($b['discount']) . "\n";
    }
    if ($b['shipping'] > 0) {
        $out .= 'Shipping: ' . fmt_price($b['shipping']) . "\n";
    } elseif (!empty($b['coupon_code'])) {
        // Free-shipping coupons get a visible line so the buyer sees the perk.
        $out .= 'Shipping: free (' . $b['coupon_code'] . ")\n";
    }
    if (!empty($b['tax']) && $b['tax'] > 0) {
        $out .= 'Sales tax (TX): ' . fmt_price($b['tax']) . "\n";
    }
    $out .= 'Total: ' . fmt_price($b['order_total']) . "\n";
    return $out;
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
    $totalsBlock = _mail_totals_block($b);
    $isGift = !empty($order['is_gift']);
    $contactBlock = "Buyer: $cust\n"
          . ($order['customer_email'] ? "Email: {$order['customer_email']}\n" : '')
          . (!empty($order['customer_phone']) ? "Phone: {$order['customer_phone']}\n" : '');
    if ($isGift) {
        $giftBlock = "** GIFT ORDER **\n"
                   . "Address the package to: " . ($order['gift_recipient_name'] ?? '(missing recipient name)') . "\n";
        if (!empty($order['gift_message'])) {
            $giftBlock .= "\nGift note to include with the package:\n"
                       . "  > " . str_replace("\n", "\n  > ", $order['gift_message']) . "\n";
        } else {
            $giftBlock .= "No gift note from the buyer.\n";
        }
        $giftBlock .= "\n";
    } else {
        $giftBlock = '';
    }
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
    // Prefer the public support address so customer replies land in the
    // monitored inbox; fall back to admin if support isn't configured yet.
    $replyTo = $cfg['support_email'] ?? $cfg['admin_email'] ?? null;
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
    $totalsBlock = _mail_totals_block($b);
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

/**
 * Best-effort carrier guess from a tracking number's format, returning a
 * ['carrier' => label, 'url' => trackingUrl] pair (empty strings if we
 * can't tell). Stripped of spaces first since admins paste them grouped.
 *   • UPS    — starts with "1Z", or an 18-digit all-numeric account form.
 *   • USPS   — 20–22 digit numeric (often begins 9400/9205/9407/93…).
 *   • FedEx  — 12 or 15 digit numeric.
 * When uncertain we return no URL and the email falls back to a bare number.
 */
function _mail_tracking_carrier(string $tracking): array {
    $t = strtoupper(preg_replace('/\s+/', '', $tracking));
    if ($t === '') return ['carrier' => '', 'url' => ''];

    if (preg_match('/^1Z[0-9A-Z]{16}$/', $t)) {
        return ['carrier' => 'UPS', 'url' => 'https://www.ups.com/track?tracknum=' . rawurlencode($t)];
    }
    if (ctype_digit($t)) {
        $len = strlen($t);
        if ($len >= 20 && $len <= 22) {
            return ['carrier' => 'USPS', 'url' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=' . rawurlencode($t)];
        }
        if ($len === 12 || $len === 15) {
            return ['carrier' => 'FedEx', 'url' => 'https://www.fedex.com/fedextrack/?trknbr=' . rawurlencode($t)];
        }
        if ($len === 18) {
            return ['carrier' => 'UPS', 'url' => 'https://www.ups.com/track?tracknum=' . rawurlencode($t)];
        }
    }
    return ['carrier' => '', 'url' => ''];
}

/**
 * "Your order is on its way" note, sent when an admin marks an order shipped.
 * Includes the tracking number (and a carrier-specific tracking link when the
 * number's format is recognizable). Once a package is in the carrier's hands,
 * delivery issues are theirs to resolve — the note nudges the customer to the
 * carrier rather than back to us. No-op if we have no email on file.
 */
function mail_customer_shipped(array $order, array $items): void {
    $email = $order['customer_email'] ?? null;
    if (!$email) return;
    $cfg = config('mail');
    $replyTo = $cfg['support_email'] ?? $cfg['admin_email'] ?? null;

    $count = count($items);
    $lines = '';
    foreach ($items as $it) {
        $lines .= '  • ' . ($it['title_snapshot'] ?? '(unknown)') . "\n";
    }

    $tracking = trim((string)($order['tracking_number'] ?? ''));
    $trackBlock = '';
    if ($tracking !== '') {
        $info = _mail_tracking_carrier($tracking);
        $label = $info['carrier'] !== '' ? $info['carrier'] . ' tracking number' : 'Tracking number';
        $trackBlock = $label . ': ' . $tracking . "\n";
        if ($info['url'] !== '') {
            $trackBlock .= 'Track your package: ' . $info['url'] . "\n";
        }
        $trackBlock .= "\nOnce your package is in the carrier's hands, they're best placed to "
                     . "help with any delivery questions — you can use the tracking number above "
                     . "to check status or open a claim with them directly.\n\n";
    }

    $intro = $count === 1
        ? "Good news — your doll is on its way!\n\n"
        : "Good news — your order of $count dolls is on its way!\n\n";
    $body = $intro
          . $lines . "\n"
          . $trackBlock
          . "Thanks again for your order.\n\n"
          . "— Kanda Kay\n  scrappydolls.com\n";
    $subject = $count === 1
        ? "Shipped — " . ($items[0]['title_snapshot'] ?? 'your doll')
        : "Shipped — $count dolls";
    send_mail($email, $subject, $body, $replyTo);
}

/**
 * Inbound message from the public contact form. Goes to the support
 * address; Reply-To is set to the customer's email so a one-click reply
 * lands back in their inbox.
 */
function mail_contact_message(string $name, string $email, string $phone, string $subject, string $message): bool {
    $cfg = config('mail');
    $to = $cfg['support_email'] ?? $cfg['admin_email'] ?? null;
    if (!$to) return false;

    $cleanSubject = trim($subject);
    if ($cleanSubject === '') $cleanSubject = 'New message';
    $mailSubject = '[Contact form] ' . $cleanSubject;

    $body = "Someone sent a message via the website contact form.\n\n"
          . "From: $name <$email>\n"
          . ($phone !== '' ? "Phone: $phone\n" : '')
          . "Subject: $cleanSubject\n\n"
          . "Message:\n"
          . "----------------------------------------------------------\n"
          . $message . "\n"
          . "----------------------------------------------------------\n\n"
          . "Reply directly to this email to respond to the customer.\n";

    return send_mail($to, $mailSubject, $body, $email);
}
