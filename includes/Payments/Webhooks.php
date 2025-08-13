<?php
namespace ClassFlowPro\Payments;

use ClassFlowPro\Admin\Settings;
use ClassFlowPro\Accounting\QuickBooks;

class Webhooks
{
    public static function handle(\WP_REST_Request $request)
    {
        $secret = Settings::get('stripe_webhook_secret', '');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $payload = file_get_contents('php://input');
        $event = json_decode($payload, true);

        // ALWAYS require signature verification in production
        if (!$secret || !$signature) {
            \ClassFlowPro\Logging\Logger::log('error', 'stripe_webhook', 'Missing webhook secret or signature');
            return new \WP_Error('cfp_stripe_no_sig', __('Webhook signature verification required', 'classflow-pro'), ['status' => 401]);
        }

        // Verify signature: Stripe requires signed payload
        $timestamp = 0;
        $signed = '';
        foreach (explode(',', $signature) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, null);
            if ($k === 't') $timestamp = (int)$v;
            if ($k === 'v1') $signed = $v;
        }
        if (!$timestamp || !$signed) {
            \ClassFlowPro\Logging\Logger::log('error', 'stripe_webhook', 'Missing signature parameters');
            return new \WP_Error('cfp_stripe_bad_sig', __('Invalid Stripe signature header', 'classflow-pro'), ['status' => 400]);
        }
        // 5 minute tolerance
        if (abs(time() - $timestamp) > 300) {
            \ClassFlowPro\Logging\Logger::log('error', 'stripe_webhook', 'Signature timestamp out of tolerance', ['t' => $timestamp]);
            return new \WP_Error('cfp_stripe_sig_expired', __('Signature timestamp out of tolerance', 'classflow-pro'), ['status' => 400]);
        }
        $payload_to_sign = $timestamp . '.' . $payload;
        $computed = hash_hmac('sha256', $payload_to_sign, $secret);
        if (!hash_equals($computed, $signed)) {
            \ClassFlowPro\Logging\Logger::log('error', 'stripe_webhook', 'Signature mismatch');
            return new \WP_Error('cfp_stripe_sig_mismatch', __('Stripe signature mismatch', 'classflow-pro'), ['status' => 400]);
        }

        if (!isset($event['type'])) {
            \ClassFlowPro\Logging\Logger::log('error', 'stripe_webhook', 'Invalid payload');
            return new \WP_Error('cfp_invalid_event', __('Invalid webhook payload', 'classflow-pro'), ['status' => 400]);
        }

        // Idempotency and logging
        $provider = 'stripe';
        $event_id = (string)($event['id'] ?? '');
        if ($event_id) {
            global $wpdb; $log = $wpdb->prefix . 'cfp_webhook_events';
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $log WHERE provider=%s AND event_id=%s", $provider, $event_id), ARRAY_A);
            if ($existing && $existing['status']==='processed') {
                return rest_ensure_response(['received' => true, 'duplicate' => true]);
            }
            if (!$existing) {
                // Sanitize payload - remove sensitive payment data
                $sanitized_event = self::sanitize_webhook_payload($event);
                $wpdb->insert($log, [ 'provider'=>$provider, 'event_id'=>$event_id, 'event_type'=>$event['type'], 'status'=>'received', 'payload'=>json_encode($sanitized_event) ], ['%s','%s','%s','%s','%s']);
            }
        }

        try {
            switch ($event['type']) {
            case 'checkout.session.completed':
                self::on_checkout_completed($event['data']['object'] ?? []);
                break;
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                self::on_subscription_event($event['data']['object'] ?? []);
                break;
            case 'checkout.session.async_payment_succeeded':
                self::on_checkout_completed($event['data']['object'] ?? []);
                break;
            case 'payment_intent.succeeded':
                self::on_payment_succeeded($event['data']['object'] ?? []);
                break;
            case 'payment_intent.payment_failed':
                self::on_payment_failed($event['data']['object'] ?? []);
                break;
            case 'invoice.paid':
                self::on_invoice_paid($event['data']['object'] ?? []);
                break;
            }
            if (!empty($event_id)) {
                global $wpdb; $log = $wpdb->prefix . 'cfp_webhook_events';
                $wpdb->update($log, [ 'status'=>'processed', 'processed_at'=>gmdate('Y-m-d H:i:s') ], [ 'provider'=>$provider, 'event_id'=>$event_id ], ['%s','%s'], ['%s','%s']);
            }
            return rest_ensure_response(['received' => true]);
        } catch (\Throwable $e) {
            \ClassFlowPro\Logging\Logger::log('error', 'stripe_webhook', 'Handler error', ['error'=>$e->getMessage(), 'type'=>$event['type']]);
            if (!empty($event_id)) {
                global $wpdb; $log = $wpdb->prefix . 'cfp_webhook_events';
                $row = $wpdb->get_row($wpdb->prepare("SELECT retry_count FROM $log WHERE provider=%s AND event_id=%s", $provider, $event_id), ARRAY_A);
                $retry = (int)($row['retry_count'] ?? 0) + 1;
                $wpdb->update($log, [ 'status'=>'failed', 'retry_count'=>$retry, 'last_error'=>$e->getMessage() ], [ 'provider'=>$provider, 'event_id'=>$event_id ], ['%s','%d','%s'], ['%s','%s']);
            }
            return new \WP_Error('cfp_webhook_error', __('Webhook handling failed', 'classflow-pro'), ['status'=>500]);
        }
    }

    public static function retry_failed(): void
    {
        global $wpdb; $log = $wpdb->prefix . 'cfp_webhook_events';
        $rows = $wpdb->get_results("SELECT * FROM $log WHERE status='failed' AND retry_count < 5 ORDER BY id ASC LIMIT 10", ARRAY_A);
        foreach ($rows as $row) {
            $payload = json_decode((string)$row['payload'], true);
            if (!$payload || empty($payload['type'])) continue;
            $request = new \WP_REST_Request('POST');
            // Reuse handler in-process
            try {
                switch ($payload['type']) {
                    case 'checkout.session.completed': self::on_checkout_completed($payload['data']['object'] ?? []); break;
                    case 'customer.subscription.created':
                    case 'customer.subscription.updated':
                    case 'customer.subscription.deleted': self::on_subscription_event($payload['data']['object'] ?? []); break;
                    case 'checkout.session.async_payment_succeeded': self::on_checkout_completed($payload['data']['object'] ?? []); break;
                    case 'payment_intent.succeeded': self::on_payment_succeeded($payload['data']['object'] ?? []); break;
                    case 'payment_intent.payment_failed': self::on_payment_failed($payload['data']['object'] ?? []); break;
                    case 'invoice.paid': self::on_invoice_paid($payload['data']['object'] ?? []); break;
                }
                $wpdb->update($log, [ 'status'=>'processed', 'processed_at'=>gmdate('Y-m-d H:i:s') ], [ 'id'=>(int)$row['id'] ], ['%s','%s'], ['%d']);
            } catch (\Throwable $e) {
                $wpdb->update($log, [ 'retry_count'=>((int)$row['retry_count']+1), 'last_error'=>$e->getMessage() ], [ 'id'=>(int)$row['id'] ], ['%d','%s'], ['%d']);
            }
        }
    }

    private static function on_payment_succeeded(array $intent): void
    {
        global $wpdb;
        $intent_id = $intent['id'] ?? '';
        if (!$intent_id) return;
        // Gift card purchase
        $meta = $intent['metadata'] ?? [];
        if (!empty($meta['type']) && $meta['type'] === 'giftcard') {
            $credits = (int)($meta['giftcard_credits'] ?? 0);
            $amount_cents = (int)($intent['amount_received'] ?? ($intent['amount'] ?? 0));
            $currency = strtolower((string)($intent['currency'] ?? 'usd'));
            $purchaser = (string)($intent['receipt_email'] ?? ($intent['charges']['data'][0]['billing_details']['email'] ?? ''));
            $recipient = (string)($meta['recipient_email'] ?? '');
            if ($credits > 0) {
                $code = substr(strtoupper(wp_generate_password(12, false, false)), 0, 12);
                $gc = $wpdb->prefix . 'cfp_gift_cards';
                $wpdb->insert($gc, [
                    'code' => $code,
                    'credits' => $credits,
                    'amount_cents' => $amount_cents,
                    'currency' => $currency,
                    'purchaser_email' => $purchaser ?: null,
                    'recipient_email' => $recipient ?: null,
                    'status' => 'new',
                ], ['%s','%d','%d','%s','%s','%s','%s']);
                // Insert transaction for reporting
                try {
                    $tx = $wpdb->prefix . 'cfp_transactions';
                    $receipt_url = !empty($intent['charges']['data'][0]['receipt_url']) ? $intent['charges']['data'][0]['receipt_url'] : '';
                    $wpdb->insert($tx, [
                        'user_id' => null,
                        'booking_id' => null,
                        'amount_cents' => $amount_cents,
                        'currency' => $currency,
                        'type' => 'giftcard_purchase',
                        'processor' => 'stripe',
                        'processor_id' => $intent_id,
                        'status' => 'succeeded',
                        'tax_amount_cents' => 0,
                        'fee_amount_cents' => isset($intent['application_fee_amount']) ? (int)$intent['application_fee_amount'] : 0,
                        'receipt_url' => $receipt_url,
                    ], ['%s','%s','%d','%s','%s','%s','%s','%s','%d','%s']);
                } catch (\Throwable $e) {}
                // Send recipient email if provided
                try { \ClassFlowPro\Notifications\Mailer::gift_card_issued($code, $credits, $amount_cents, $recipient ?: null, $purchaser ?: null); } catch (\Throwable $e) {}
            }
            return; // do not double-handle as booking payment
        }
        // Series enrollment
        if (!empty($meta['type']) && $meta['type'] === 'series') {
            $series_id = (int)($meta['series_id'] ?? 0);
            $user_id = (int)($meta['user_id'] ?? 0);
            if ($series_id > 0 && $user_id > 0) {
                $se = $wpdb->prefix . 'cfp_series_enrollments';
                // Upsert enrollment
                $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $se WHERE series_id=%d AND user_id=%d", $series_id, $user_id));
                if ($exists <= 0) {
                    $wpdb->insert($se, [ 'series_id'=>$series_id, 'user_id'=>$user_id, 'status'=>'active', 'payment_intent_id'=>$intent_id ], ['%d','%d','%s','%s']);
                } else {
                    $wpdb->update($se, [ 'status'=>'active', 'payment_intent_id'=>$intent_id ], [ 'series_id'=>$series_id, 'user_id'=>$user_id ], ['%s','%s'], ['%d','%d']);
                }
                // Create bookings for each session
                $ss = $wpdb->prefix . 'cfp_series_sessions';
                $s = $wpdb->prefix . 'cfp_schedules';
                $b = $wpdb->prefix . 'cfp_bookings';
                $sessions = $wpdb->get_results($wpdb->prepare("SELECT s.* FROM $s s JOIN $ss x ON x.schedule_id=s.id WHERE x.series_id=%d ORDER BY s.start_time ASC", $series_id), ARRAY_A);
                $email = ''; $u = get_userdata($user_id); if ($u) { $email = (string)$u->user_email; }
                foreach ($sessions as $row) {
                    // Ensure not already booked
                    $already = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $b WHERE schedule_id=%d AND user_id=%d AND status IN ('pending','confirmed')", (int)$row['id'], $user_id));
                    if ($already > 0) continue;
                    $booked = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $b WHERE schedule_id=%d AND status IN ('pending','confirmed')", (int)$row['id']));
                    if ($booked >= (int)$row['capacity']) continue; // skip full session
                    $wpdb->insert($b, [
                        'schedule_id' => (int)$row['id'],
                        'user_id' => $user_id,
                        'customer_email' => $email,
                        'status' => 'confirmed',
                        'payment_intent_id' => null,
                        'payment_status' => 'series',
                        'credits_used' => 0,
                        'amount_cents' => 0,
                        'discount_cents' => 0,
                        'currency' => $row['currency'] ?: 'usd',
                        'coupon_id' => null,
                        'coupon_code' => null,
                        'metadata' => wp_json_encode(['series_id' => $series_id]),
                    ], ['%d','%d','%s','%s','%s','%d','%d','%d','%s','%d','%s','%s']);
                    try { \ClassFlowPro\Notifications\Mailer::booking_confirmed((int)$wpdb->insert_id); } catch (\Throwable $e) {}
                }
                // Transaction record
                try {
                    $tx = $wpdb->prefix . 'cfp_transactions';
                    $amount_cents = (int)($intent['amount_received'] ?? ($intent['amount'] ?? 0));
                    $currency = strtolower((string)($intent['currency'] ?? 'usd'));
                    $receipt_url = !empty($intent['charges']['data'][0]['receipt_url']) ? $intent['charges']['data'][0]['receipt_url'] : '';
                    $wpdb->insert($tx, [
                        'user_id' => $user_id,
                        'booking_id' => null,
                        'amount_cents' => $amount_cents,
                        'currency' => $currency,
                        'type' => 'series_purchase',
                        'processor' => 'stripe',
                        'processor_id' => $intent_id,
                        'status' => 'succeeded',
                        'tax_amount_cents' => 0,
                        'fee_amount_cents' => isset($intent['application_fee_amount']) ? (int)$intent['application_fee_amount'] : 0,
                        'receipt_url' => $receipt_url,
                    ], ['%d','%s','%d','%s','%s','%s','%s','%d','%d','%s']);
                } catch (\Throwable $e) {}
            }
            return;
        }
        $bookings = $wpdb->prefix . 'cfp_bookings';
        // Try match by intent id (previous PI flow) or by metadata booking_id (Checkout flow)
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE payment_intent_id = %s", $intent_id), ARRAY_A);
        $metadata = $intent['metadata'] ?? [];
        if (!$booking && !empty($metadata['booking_id'])) {
            $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE id = %d", (int)$metadata['booking_id']), ARRAY_A);
        }
        $transactions = $wpdb->prefix . 'cfp_transactions';
        $fee_cents = isset($intent['application_fee_amount']) ? (int)$intent['application_fee_amount'] : 0;
        $tax_cents = isset($intent['amount_details']['amount_tax']) ? (int)$intent['amount_details']['amount_tax'] : 0;

        if ($booking) {
            // Determine final amount and currency from Stripe
            $final_amount = isset($intent['amount_received']) ? (int)$intent['amount_received'] : (isset($intent['amount']) ? (int)$intent['amount'] : (int)$booking['amount_cents']);
            $final_currency = !empty($intent['currency']) ? strtolower((string)$intent['currency']) : (string)$booking['currency'];

            // Update booking with final amount/currency and status
            $wpdb->update($bookings, [
                'status' => 'confirmed',
                'payment_status' => 'succeeded',
                'amount_cents' => $final_amount,
                'currency' => $final_currency,
            ], ['id' => $booking['id']], ['%s','%s','%d','%s'], ['%d']);

            $receipt_url = !empty($intent['charges']['data'][0]['receipt_url']) ? $intent['charges']['data'][0]['receipt_url'] : '';
            $wpdb->insert($transactions, [
                'user_id' => $booking['user_id'],
                'booking_id' => $booking['id'],
                'amount_cents' => $final_amount,
                'currency' => $final_currency,
                'type' => 'class_payment',
                'processor' => 'stripe',
                'processor_id' => $intent_id,
                'status' => 'succeeded',
                'tax_amount_cents' => $tax_cents,
                'fee_amount_cents' => $fee_cents,
                'receipt_url' => $receipt_url,
            ], ['%d','%d','%d','%s','%s','%s','%s','%d','%d','%s']);

            try {
                QuickBooks::create_sales_receipt_for_booking((int)$booking['id']);
            } catch (\Throwable $e) {
                error_log('[CFP] QuickBooks create_sales_receipt error: ' . $e->getMessage());
            }

            // Notifications
            try {
                \ClassFlowPro\Notifications\Mailer::booking_confirmed((int)$booking['id']);
            } catch (\Throwable $e) {
                error_log('[CFP] notify booking_confirmed error: ' . $e->getMessage());
            }
        } elseif (!empty($metadata['booking_ids'])) {
            // Multi-booking payment: allocate amounts proportionally and confirm each booking
            $ids = array_filter(array_map('intval', explode(',', (string)$metadata['booking_ids'])));
            if ($ids) {
                $bookings_tbl = $wpdb->prefix . 'cfp_bookings';
                $rows = $wpdb->get_results('SELECT * FROM ' . $bookings_tbl . ' WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')', ARRAY_A);
                if ($rows) {
                    $total_amount = isset($intent['amount_received']) ? (int)$intent['amount_received'] : (isset($intent['amount']) ? (int)$intent['amount'] : 0);
                    $tax_total = isset($intent['amount_details']['amount_tax']) ? (int)$intent['amount_details']['amount_tax'] : 0;
                    $sum = 0; foreach ($rows as $r) { $sum += (int)$r['amount_cents']; }
                    if ($sum <= 0) { $sum = count($rows); }
                    $remaining_amount = $total_amount; $remaining_tax = $tax_total;
                    foreach ($rows as $idx => $row) {
                        $share = ($idx === count($rows)-1) ? $remaining_amount : (int)round($total_amount * ((int)$row['amount_cents'] / $sum));
                        $tax_share = ($idx === count($rows)-1) ? $remaining_tax : (int)round($tax_total * ((int)$row['amount_cents'] / $sum));
                        $remaining_amount -= $share; $remaining_tax -= $tax_share;
                        // Update booking
                        $wpdb->update($bookings_tbl, [ 'status' => 'confirmed', 'payment_status' => 'succeeded', 'amount_cents' => $share, 'currency' => ($intent['currency'] ?? $row['currency']) ], ['id' => (int)$row['id']], ['%s','%s','%d','%s'], ['%d']);
                        // Transaction per booking
                        $transactions = $wpdb->prefix . 'cfp_transactions';
                        $receipt_url = !empty($intent['charges']['data'][0]['receipt_url']) ? $intent['charges']['data'][0]['receipt_url'] : '';
                        $wpdb->insert($transactions, [
                            'user_id' => $row['user_id'],
                            'booking_id' => $row['id'],
                            'amount_cents' => $share,
                            'currency' => ($intent['currency'] ?? $row['currency']),
                            'type' => 'class_payment',
                            'processor' => 'stripe',
                            'processor_id' => $intent_id,
                            'status' => 'succeeded',
                            'tax_amount_cents' => $tax_share,
                            'fee_amount_cents' => 0,
                            'receipt_url' => $receipt_url,
                        ], ['%d','%d','%d','%s','%s','%s','%s','%d','%d','%s']);
                        try { QuickBooks::create_sales_receipt_for_booking((int)$row['id']); } catch (\Throwable $e) {}
                    }
                    // Notifications
                    foreach ($rows as $row) {
                        try { \ClassFlowPro\Notifications\Mailer::booking_confirmed((int)$row['id']); } catch (\Throwable $e) {}
                    }
                }
            }
        } elseif (!empty($metadata['type']) && $metadata['type'] === 'package_purchase') {
            // Grant credits to user based on metadata
            $user_id = (int)($metadata['user_id'] ?? 0);
            if (!$user_id && !empty($metadata['buyer_email'])) {
                $u = get_user_by('email', sanitize_email($metadata['buyer_email']));
                if ($u) $user_id = (int)$u->ID;
            }
            $credits = (int)($metadata['package_credits'] ?? 0);
            $name = sanitize_text_field($metadata['package_name'] ?? 'Package');
            if ($user_id && $credits > 0) {
                $amount_cents = (int)($intent['amount'] ?? 0);
                $currency = $intent['currency'] ?? 'usd';
                \ClassFlowPro\Packages\Manager::grant_package($user_id, $name, $credits, $amount_cents, $currency, null);
            }
            // mark transaction succeeded
            $wpdb->update($transactions, [
                'status' => 'succeeded',
                'tax_amount_cents' => $tax_cents,
                'fee_amount_cents' => $fee_cents,
            ], ['processor_id' => $intent_id], ['%s','%d','%d'], ['%s']);
        } elseif (!$booking && empty($metadata['type']) && !empty($intent['amount'])) {
            // Checkout Session for packages may not carry our custom metadata if created directly; fallback: look up pending package tx by PI id
            $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM $transactions WHERE processor='stripe' AND type='package_purchase' AND processor_id = %s", $intent_id), ARRAY_A);
            if ($tx && $tx['status'] !== 'succeeded') {
                $wpdb->update($transactions, [ 'status' => 'succeeded' ], ['id' => $tx['id']], ['%s'], ['%d']);
                // Unable to infer credits without metadata — recommend using REST path that sets metadata. We log for operator to reconcile.
                \ClassFlowPro\Logging\Logger::log('warning', 'stripe_webhook', 'Package purchase without metadata; manual grant may be required', ['processor_id' => $intent_id]);
            }
        }
    }

    private static function on_subscription_event(array $sub): void
    {
        try { \ClassFlowPro\Memberships\Manager::update_membership_from_stripe((string)($sub['id'] ?? ''), $sub); } catch (\Throwable $e) {}
    }

    private static function on_invoice_paid(array $invoice): void
    {
        try { \ClassFlowPro\Memberships\Manager::grant_periodic_credits_from_invoice($invoice); } catch (\Throwable $e) {}
    }

    private static function on_checkout_completed(array $session): void
    {
        // For Checkout, we rely on the underlying PaymentIntent for final status and metadata
        $intent_id = $session['payment_intent'] ?? '';
        if (!$intent_id) return;
        // Call existing intent handler by fetching PI from Stripe if needed; but metadata should be propagated
        // For simplicity, we emulate a minimal PI object with metadata and ids when session carries total.
        $fake_intent = [
            'id' => $intent_id,
            'metadata' => $session['metadata'] ?? [],
            'amount' => $session['amount_total'] ?? null,
            'currency' => $session['currency'] ?? null,
            // charges/receipt not available here; subsequent payment_intent.succeeded will fill it.
        ];
        self::on_payment_succeeded($fake_intent);
    }

    private static function on_payment_failed(array $intent): void
    {
        global $wpdb;
        $intent_id = $intent['id'] ?? '';
        if (!$intent_id) return;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE payment_intent_id = %s", $intent_id), ARRAY_A);
        if (!$booking) return;
        $wpdb->update($bookings, [
            'payment_status' => 'failed',
        ], ['id' => $booking['id']], ['%s'], ['%d']);
    }
    
    /**
     * Sanitize webhook payload to remove sensitive payment data before logging
     */
    private static function sanitize_webhook_payload(array $event): array
    {
        $sanitized = $event;
        
        // List of sensitive fields to redact
        $sensitive_fields = [
            'payment_method_details',
            'payment_method',
            'card',
            'bank_account',
            'source',
            'charges',
            'invoice',
            'subscription',
            'customer'
        ];
        
        // Recursively redact sensitive fields
        $redact = function(&$data) use (&$redact, $sensitive_fields) {
            if (!is_array($data)) return;
            
            foreach ($data as $key => &$value) {
                // Redact sensitive fields
                if (in_array($key, $sensitive_fields, true)) {
                    if (is_array($value) && isset($value['id'])) {
                        // Keep only the ID for reference
                        $value = ['id' => $value['id'], 'redacted' => true];
                    } else {
                        $value = '[REDACTED]';
                    }
                } elseif (is_array($value)) {
                    // Recursively check nested arrays
                    $redact($value);
                }
                
                // Redact specific sensitive string patterns
                if (is_string($value)) {
                    // Redact card numbers
                    if (preg_match('/^\d{13,19}$/', $value)) {
                        $value = '[CARD_NUMBER_REDACTED]';
                    }
                    // Redact CVV
                    elseif (preg_match('/^\d{3,4}$/', $value) && in_array($key, ['cvc', 'cvv', 'security_code'], true)) {
                        $value = '[CVV_REDACTED]';
                    }
                    // Redact bank account numbers
                    elseif (stripos($key, 'account') !== false && preg_match('/^\d{6,}$/', $value)) {
                        $value = '[ACCOUNT_REDACTED]';
                    }
                }
            }
        };
        
        if (isset($sanitized['data']['object'])) {
            $redact($sanitized['data']['object']);
        }
        
        return $sanitized;
    }
}
