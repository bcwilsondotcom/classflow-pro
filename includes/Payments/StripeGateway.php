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

    /**
     * Calculate payment split between instructor and platform
     * CRITICAL: This handles real money - must be 100% accurate
     * 
     * @param int $amount_cents Total payment amount in cents
     * @param int $instructor_id Instructor post ID or database ID
     * @return array|WP_Error Array with split details or error
     */
    private static function calculate_payment_split(int $amount_cents, int $instructor_id)
    {
        // Validate inputs
        if ($amount_cents <= 0) {
            return new WP_Error('invalid_amount', 'Payment amount must be positive');
        }
        
        if ($instructor_id <= 0) {
            return new WP_Error('invalid_instructor', 'Invalid instructor ID');
        }
        
        // Check if instructor is from post meta (old system) or database
        $stripe_account = null;
        $payout_percent = null;
        
        // Try post meta first (for backward compatibility)
        if (get_post_type($instructor_id) === 'cfp_instructor') {
            $stripe_account = get_post_meta($instructor_id, '_cfp_stripe_account_id', true);
            $payout_percent = get_post_meta($instructor_id, '_cfp_payout_percent', true);
        }
        
        // If not found in post meta, check database
        if (!$stripe_account) {
            global $wpdb;
            $table = $wpdb->prefix . 'cfp_instructors';
            $instructor = $wpdb->get_row($wpdb->prepare(
                "SELECT stripe_account_id, payout_percent FROM {$table} WHERE id = %d",
                $instructor_id
            ), ARRAY_A);
            
            if ($instructor) {
                $stripe_account = $instructor['stripe_account_id'];
                $payout_percent = $instructor['payout_percent'];
            }
        }
        
        // Validate Stripe account
        if (!$stripe_account) {
            return new WP_Error('no_stripe_account', 'Instructor does not have a Stripe Connect account configured');
        }
        
        // Validate and sanitize payout percentage
        if (!is_numeric($payout_percent)) {
            \ClassFlowPro\Logging\Logger::log('error', 'payment_split', 'Invalid payout percentage', [
                'instructor_id' => $instructor_id,
                'payout_percent' => $payout_percent
            ]);
            return new WP_Error('invalid_payout_percent', 'Instructor payout percentage is not configured or invalid');
        }
        
        $payout_percent = (float)$payout_percent;
        
        // Enforce percentage bounds (0-100)
        if ($payout_percent < 0 || $payout_percent > 100) {
            \ClassFlowPro\Logging\Logger::log('error', 'payment_split', 'Payout percentage out of bounds', [
                'instructor_id' => $instructor_id,
                'payout_percent' => $payout_percent
            ]);
            return new WP_Error('invalid_payout_percent', 'Payout percentage must be between 0 and 100');
        }
        
        // Calculate amounts with proper rounding
        // CRITICAL: Always round DOWN for instructor to avoid overpayment
        $instructor_amount = (int)floor($amount_cents * ($payout_percent / 100.0));
        
        // Platform gets the remainder (this ensures no penny is lost)
        $platform_amount = $amount_cents - $instructor_amount;
        
        // Sanity checks
        if ($instructor_amount < 0) {
            $instructor_amount = 0;
        }
        
        if ($platform_amount < 0) {
            \ClassFlowPro\Logging\Logger::log('error', 'payment_split', 'Platform amount negative - calculation error', [
                'total' => $amount_cents,
                'instructor_amount' => $instructor_amount,
                'platform_amount' => $platform_amount
            ]);
            return new WP_Error('calculation_error', 'Payment split calculation error');
        }
        
        // Verify total matches (critical for accounting)
        if (($instructor_amount + $platform_amount) !== $amount_cents) {
            \ClassFlowPro\Logging\Logger::log('error', 'payment_split', 'Split amounts do not match total', [
                'total' => $amount_cents,
                'instructor_amount' => $instructor_amount,
                'platform_amount' => $platform_amount,
                'sum' => ($instructor_amount + $platform_amount)
            ]);
            return new WP_Error('calculation_error', 'Payment split does not match total amount');
        }
        
        return [
            'stripe_account' => $stripe_account,
            'payout_percent' => $payout_percent,
            'instructor_amount' => $instructor_amount,
            'platform_amount' => $platform_amount,
            'total_amount' => $amount_cents
        ];
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

        // Stripe Connect split: use instructor payout percent (platform keeps remainder)
        if (Settings::get('stripe_connect_enabled', 0) && $instructor_id) {
            $split_result = self::calculate_payment_split($amount_cents, $instructor_id);
            if ($split_result && !is_wp_error($split_result)) {
                $params['transfer_data[destination]'] = $split_result['stripe_account'];
                $params['application_fee_amount'] = $split_result['platform_amount'];
                
                // Log the payment split for audit trail
                \ClassFlowPro\Logging\Logger::log('info', 'payment_split', 'Payment split calculated', [
                    'instructor_id' => $instructor_id,
                    'total_amount' => $amount_cents,
                    'instructor_amount' => $split_result['instructor_amount'],
                    'platform_amount' => $split_result['platform_amount'],
                    'payout_percent' => $split_result['payout_percent']
                ]);
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

        // Append GA amounts to success URL
        $success_url = add_query_arg([
            'amount' => number_format(($amount_cents/100), 2, '.', ''),
            'currency' => strtoupper($currency),
        ], $success_url);

        // Single-line item checkout: success URL already includes correct amount
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
            $split_result = self::calculate_payment_split($amount_cents, $instructor_id);
            if ($split_result && !is_wp_error($split_result)) {
                $params['payment_intent_data[transfer_data][destination]'] = $split_result['stripe_account'];
                $params['payment_intent_data[application_fee_amount]'] = $split_result['platform_amount'];
                $params['payment_intent_data[metadata][booking_id]'] = (string)$booking_id;
                $params['payment_intent_data[metadata][instructor_payout_percent]'] = (string)$split_result['payout_percent'];
                $params['payment_intent_data[metadata][instructor_amount]'] = (string)$split_result['instructor_amount'];
                
                // Log the checkout session split
                \ClassFlowPro\Logging\Logger::log('info', 'payment_split', 'Checkout session split calculated', [
                    'instructor_id' => $instructor_id,
                    'booking_id' => $booking_id,
                    'total_amount' => $amount_cents,
                    'instructor_amount' => $split_result['instructor_amount'],
                    'platform_amount' => $split_result['platform_amount'],
                    'payout_percent' => $split_result['payout_percent']
                ]);
            } else {
                // No split possible, attach booking_id only
                $params['payment_intent_data[metadata][booking_id]'] = (string)$booking_id;
                
                if (is_wp_error($split_result)) {
                    \ClassFlowPro\Logging\Logger::log('warning', 'payment_split', 'Payment split failed', [
                        'instructor_id' => $instructor_id,
                        'error' => $split_result->get_error_message()
                    ]);
                }
            }
        } else {
            // Still attach booking_id to payment intent metadata via session param
            $params['payment_intent_data[metadata][booking_id]'] = (string)$booking_id;
        }

        $session = self::api_request('POST', '/checkout/sessions', $params);
        if (is_wp_error($session)) return $session;
        return [ 'id' => $session['id'], 'url' => $session['url'] ];
    }

    // One-off fee (late cancel / no-show) as Checkout Session
    public static function create_checkout_session_oneoff(array $args)
    {
        $amount_cents = (int)($args['amount_cents'] ?? 0);
        $currency = 'usd';
        $name = $args['name'] ?? 'Fee';
        $description = $args['description'] ?? '';
        $success_url = $args['success_url'] ?? home_url('/');
        $cancel_url = $args['cancel_url'] ?? home_url('/');
        $customer_email = $args['customer_email'] ?? null;
        if ($amount_cents <= 0) return new \WP_Error('cfp_invalid_amount', __('Invalid amount', 'classflow-pro'));
        $metadata = $args['metadata'] ?? [];
        // Ensure GA params on success URL
        $success_url = add_query_arg([
            'amount' => number_format(($amount_cents/100), 2, '.', ''),
            'currency' => strtoupper($currency),
        ], $success_url);
        $params = [
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [ 'name' => $name, 'description' => $description ],
                    'unit_amount' => $amount_cents,
                ],
                'quantity' => 1,
            ]],
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'allow_promotion_codes' => 'false',
        ];
        if ($customer_email) { $params['customer_email'] = $customer_email; }
        foreach ($metadata as $k=>$v) { $params['metadata['.$k.']'] = (string)$v; }
        $session = self::api_request('POST', '/checkout/sessions', $params);
        if (is_wp_error($session)) return $session;
        return ['id' => $session['id'], 'url' => $session['url']];
    }

    // Create Checkout Session for a subscription (membership)
    public static function create_checkout_session_subscription(array $args)
    {
        $price_id = $args['price_id'] ?? '';
        $customer_email = $args['customer_email'] ?? null;
        $success_url = $args['success_url'] ?? home_url('/');
        $cancel_url = $args['cancel_url'] ?? home_url('/');
        $metadata = $args['metadata'] ?? [];
        if (!$price_id) return new \WP_Error('cfp_invalid_price', __('Missing Stripe price ID', 'classflow-pro'));
        $params = [
            'mode' => 'subscription',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'allow_promotion_codes' => 'true',
            'line_items[0][price]' => $price_id,
            'line_items[0][quantity]' => 1,
        ];
        if ($customer_email) { $params['customer_email'] = $customer_email; }
        foreach ($metadata as $k=>$v) { $params['metadata['.$k.']'] = (string)$v; }
        $session = self::api_request('POST', '/checkout/sessions', $params);
        if (is_wp_error($session)) return $session;
        return ['id' => $session['id'], 'url' => $session['url']];
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
