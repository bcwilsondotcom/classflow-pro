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

        if ($secret && $signature) {
            // Verify signature: Stripe requires signed payload. We do minimal verification to ensure production safety.
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
        }

        if (!isset($event['type'])) {
            \ClassFlowPro\Logging\Logger::log('error', 'stripe_webhook', 'Invalid payload');
            return new \WP_Error('cfp_invalid_event', __('Invalid webhook payload', 'classflow-pro'), ['status' => 400]);
        }

        switch ($event['type']) {
            case 'payment_intent.succeeded':
                self::on_payment_succeeded($event['data']['object'] ?? []);
                break;
            case 'payment_intent.payment_failed':
                self::on_payment_failed($event['data']['object'] ?? []);
                break;
        }

        return rest_ensure_response(['received' => true]);
    }

    private static function on_payment_succeeded(array $intent): void
    {
        global $wpdb;
        $intent_id = $intent['id'] ?? '';
        if (!$intent_id) return;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $bookings WHERE payment_intent_id = %s", $intent_id), ARRAY_A);
        $metadata = $intent['metadata'] ?? [];
        $transactions = $wpdb->prefix . 'cfp_transactions';
        $fee_cents = isset($intent['application_fee_amount']) ? (int)$intent['application_fee_amount'] : 0;
        $tax_cents = isset($intent['amount_details']['amount_tax']) ? (int)$intent['amount_details']['amount_tax'] : 0;

        if ($booking) {
            $wpdb->update($bookings, [
                'status' => 'confirmed',
                'payment_status' => 'succeeded',
            ], ['id' => $booking['id']], ['%s','%s'], ['%d']);

            $receipt_url = !empty($intent['charges']['data'][0]['receipt_url']) ? $intent['charges']['data'][0]['receipt_url'] : '';
            $wpdb->insert($transactions, [
                'user_id' => $booking['user_id'],
                'booking_id' => $booking['id'],
                'amount_cents' => $booking['amount_cents'],
                'currency' => $booking['currency'],
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
        }
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
}
