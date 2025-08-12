<?php
namespace ClassFlowPro\Payments;

use ClassFlowPro\Admin\Settings;
use WP_Error;

class StripeGateway
{
    public static function api_request(string $method, string $path, array $params = [])
    {
        $secret = Settings::get('stripe_secret_key', '');
        if (!$secret) {
            return new WP_Error('cfp_stripe_not_configured', __('Stripe secret key is not configured.', 'classflow-pro'));
        }
        $url = 'https://api.stripe.com/v1' . $path;
        $headers = [
            'Authorization' => 'Bearer ' . $secret,
        ];
        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 45,
        ];
        if ($method === 'GET') {
            $url = add_query_arg($params, $url);
        } else {
            $args['body'] = $params;
        }
        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            \ClassFlowPro\Logging\Logger::log('error', 'stripe', 'HTTP error', ['path' => $path, 'error' => $res->get_error_message()]);
            return $res;
        }
        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $json = json_decode($body, true);
        if ($code >= 400) {
            $msg = $json['error']['message'] ?? 'Stripe API error';
            \ClassFlowPro\Logging\Logger::log('error', 'stripe', $msg, ['status' => $code, 'path' => $path, 'response' => $json]);
            return new WP_Error('cfp_stripe_error', $msg, ['status' => $code, 'body' => $json]);
        }
        \ClassFlowPro\Logging\Logger::log('info', 'stripe', 'API request', ['path' => $path, 'status' => $code]);
        return $json;
    }

    public static function create_customer_if_needed(?string $email, ?string $name)
    {
        if (!$email) {
            return null;
        }
        // Simple approach: always attempt to create or get by email
        $existing = self::api_request('GET', '/customers', ['email' => $email, 'limit' => 1]);
        if (!is_wp_error($existing) && isset($existing['data'][0]['id'])) {
            return $existing['data'][0]['id'];
        }
        $created = self::api_request('POST', '/customers', [
            'email' => $email,
            'name' => $name,
        ]);
        if (is_wp_error($created)) {
            return null;
        }
        return $created['id'] ?? null;
    }

    public static function create_intent(array $args)
    {
        $amount_cents = (int)$args['amount_cents'];
        $currency = $args['currency'];
        $description = $args['description'] ?? '';
        $receipt_email = $args['receipt_email'] ?? null;
        $customer_name = $args['customer_name'] ?? null;
        $metadata = $args['metadata'] ?? [];
        $instructor_id = (int)($args['instructor_id'] ?? 0);

        $params = [
            'amount' => $amount_cents,
            'currency' => $currency,
            'description' => $description,
            // Ensure broad compatibility with older Stripe API versions
            'payment_method_types[]' => 'card',
            'metadata' => $metadata,
        ];
        if (Settings::get('stripe_enable_tax', 1)) {
            $params['automatic_tax[enabled]'] = 'true';
        }

        if ($receipt_email) {
            $customer_id = self::create_customer_if_needed($receipt_email, $customer_name);
            if ($customer_id) {
                $params['customer'] = $customer_id;
                $params['receipt_email'] = $receipt_email;
            }
        }

        // Stripe Connect split: use instructor payout percent if available, otherwise platform fee percent
        if (Settings::get('stripe_connect_enabled', 0) && $instructor_id) {
            $acct = get_post_meta($instructor_id, '_cfp_stripe_account_id', true);
            if ($acct) {
                $payout_percent = get_post_meta($instructor_id, '_cfp_payout_percent', true);
                $payout_percent = is_numeric($payout_percent) ? (float)$payout_percent : null;
                if ($payout_percent === null) {
                    // Fallback: derive payout from platform fee percent
                    $platform_fee_percent = (float)Settings::get('platform_fee_percent', 0);
                    $payout_percent = max(0.0, min(100.0, 100.0 - $platform_fee_percent));
                } else {
                    $payout_percent = max(0.0, min(100.0, $payout_percent));
                }
                $instructor_amount = (int)round($amount_cents * ($payout_percent / 100.0));
                $application_fee_amount = max(0, $amount_cents - $instructor_amount);
                $params['transfer_data[destination]'] = $acct;
                $params['application_fee_amount'] = $application_fee_amount;
            }
        }

        $intent = self::api_request('POST', '/payment_intents', $params);
        if (is_wp_error($intent)) {
            return $intent;
        }
        return [
            'id' => $intent['id'],
            'client_secret' => $intent['client_secret'],
        ];
    }

    public static function refund_intent(string $payment_intent_id, ?int $amount_cents = null)
    {
        $params = [ 'payment_intent' => $payment_intent_id ];
        if ($amount_cents !== null && $amount_cents > 0) {
            $params['amount'] = $amount_cents;
        }
        $res = self::api_request('POST', '/refunds', $params);
        if (is_wp_error($res)) return $res;
        return $res;
    }

    public static function create_checkout_session(array $args)
    {
        $amount_cents = (int)$args['amount_cents'];
        $currency = $args['currency'];
        $class_title = $args['class_title'] ?? 'Class';
        $description = $args['description'] ?? '';
        $success_url = $args['success_url'];
        $cancel_url = $args['cancel_url'];
        $booking_id = (int)($args['booking_id'] ?? 0);
        $instructor_id = (int)($args['instructor_id'] ?? 0);

        $params = [
            'mode' => 'payment',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $amount_cents,
                        'product_data' => [
                            'name' => $class_title,
                            'description' => $description,
                        ],
                    ],
                ],
            ],
            'metadata[booking_id]' => (string)$booking_id,
        ];
        if (Settings::get('stripe_enable_tax', 1)) {
            $params['automatic_tax[enabled]'] = 'true';
        }
        // Always allow promotion codes on Checkout
        $params['allow_promotion_codes'] = 'true';

        // Stripe Connect split handled via payment_intent_data
        if (Settings::get('stripe_connect_enabled', 0) && $instructor_id) {
            $acct = get_post_meta($instructor_id, '_cfp_stripe_account_id', true);
            if ($acct) {
                $payout_percent = get_post_meta($instructor_id, '_cfp_payout_percent', true);
                $payout_percent = is_numeric($payout_percent) ? (float)$payout_percent : null;
                if ($payout_percent === null) {
                    $platform_fee_percent = (float)Settings::get('platform_fee_percent', 0);
                    $payout_percent = max(0.0, min(100.0, 100.0 - $platform_fee_percent));
                } else {
                    $payout_percent = max(0.0, min(100.0, $payout_percent));
                }
                $instructor_amount = (int)round($amount_cents * ($payout_percent / 100.0));
                $application_fee_amount = max(0, $amount_cents - $instructor_amount);
                $params['payment_intent_data[transfer_data][destination]'] = $acct;
                $params['payment_intent_data[application_fee_amount]'] = $application_fee_amount;
                $params['payment_intent_data[metadata][booking_id]'] = (string)$booking_id;
            }
        } else {
            // Still attach booking_id to payment intent metadata via session param
            $params['payment_intent_data[metadata][booking_id]'] = (string)$booking_id;
        }

        $session = self::api_request('POST', '/checkout/sessions', $params);
        if (is_wp_error($session)) return $session;
        return [ 'id' => $session['id'], 'url' => $session['url'] ];
    }

    public static function create_checkout_session_multi(array $args)
    {
        // args: line_items: [ ['amount_cents'=>, 'currency'=>, 'name'=>, 'description'=>], ... ], success_url, cancel_url, booking_ids: array<int>
        $line_items = $args['line_items'] ?? [];
        $success_url = $args['success_url'];
        $cancel_url = $args['cancel_url'];
        $booking_ids = array_map('intval', $args['booking_ids'] ?? []);
        $currency = $line_items && !empty($line_items[0]['currency']) ? $line_items[0]['currency'] : 'usd';

        $items = [];
        foreach ($line_items as $li) {
            $items[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $li['currency'] ?? $currency,
                    'unit_amount' => (int)$li['amount_cents'],
                    'product_data' => [
                        'name' => $li['name'] ?? 'Class',
                        'description' => $li['description'] ?? '',
                    ],
                ],
            ];
        }
        $params = [
            'mode' => 'payment',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'line_items' => $items,
            'allow_promotion_codes' => 'true',
        ];
        if (Settings::get('stripe_enable_tax', 1)) {
            $params['automatic_tax[enabled]'] = 'true';
        }
        if (!empty($booking_ids)) {
            $params['payment_intent_data[metadata][booking_ids]'] = implode(',', $booking_ids);
        }
        $session = self::api_request('POST', '/checkout/sessions', $params);
        if (is_wp_error($session)) return $session;
        return [ 'id' => $session['id'], 'url' => $session['url'] ];
    }
}
