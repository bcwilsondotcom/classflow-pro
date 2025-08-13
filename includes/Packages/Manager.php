<?php
namespace ClassFlowPro\Packages;

class Manager
{
    public static function get_user_credits(int $user_id): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_packages';
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(credits_remaining),0) FROM $table WHERE user_id = %d AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())", $user_id));
    }

    public static function consume_one_credit(int $user_id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_packages';
        
        // Use atomic UPDATE with WHERE condition to prevent race conditions
        // This will only update if credits_remaining > 0, ensuring atomicity
        $sql = $wpdb->prepare(
            "UPDATE $table 
             SET credits_remaining = credits_remaining - 1 
             WHERE user_id = %d 
               AND credits_remaining > 0 
               AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
             ORDER BY expires_at ASC, id ASC
             LIMIT 1",
            $user_id
        );
        
        $rows_affected = $wpdb->query($sql);
        return $rows_affected > 0;
    }

    public static function grant_package(int $user_id, string $name, int $credits, int $price_cents, string $currency, ?string $expires_at = null): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_packages';
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'name' => $name,
            'credits' => $credits,
            'credits_remaining' => $credits,
            'price_cents' => $price_cents,
            'currency' => $currency,
            'expires_at' => $expires_at,
        ], ['%d','%s','%d','%d','%s','%s']);
        return (int)$wpdb->insert_id;
    }

    public static function create_purchase_intent(?int $user_id, string $name, int $credits, int $price_cents, string $email, string $buyer_name)
    {
        // Always use USD
        $currency = 'usd';
        $intent = \ClassFlowPro\Payments\StripeGateway::create_intent([
            'amount_cents' => $price_cents,
            'currency' => $currency,
            'description' => 'Package: ' . $name . ' (' . $credits . ' credits)',
            'receipt_email' => $email,
            'customer_name' => $buyer_name,
            'instructor_id' => 0,
            'metadata' => [
                'type' => 'package_purchase',
                'user_id' => (string)($user_id ?: 0),
                'package_name' => $name,
                'package_credits' => (string)$credits,
                'buyer_email' => $email,
            ],
        ]);
        if (is_wp_error($intent)) return $intent;
        global $wpdb;
        $transactions = $wpdb->prefix . 'cfp_transactions';
        $wpdb->insert($transactions, [
            'user_id' => $user_id,
            'booking_id' => null,
            'amount_cents' => $price_cents,
            'currency' => $currency,
            'type' => 'package_purchase',
            'processor' => 'stripe',
            'processor_id' => $intent['id'],
            'status' => 'requires_payment',
            'tax_amount_cents' => 0,
            'fee_amount_cents' => 0,
        ], ['%d','%s','%d','%s','%s','%s','%s','%d','%d']);
        return [
            'payment_intent_client_secret' => $intent['client_secret'],
            'payment_intent_id' => $intent['id'],
        ];
    }

    public static function create_checkout_session(?int $user_id, string $name, int $credits, int $price_cents, string $email, string $buyer_name)
    {
        // Always use USD
        $currency = 'usd';
        $success = \ClassFlowPro\Admin\Settings::get('checkout_success_url', '');
        $cancel = \ClassFlowPro\Admin\Settings::get('checkout_cancel_url', '');
        $default_success = add_query_arg(['cfp_checkout' => 'success', 'type' => 'package'], home_url('/'));
        $default_cancel = add_query_arg(['cfp_checkout' => 'cancel', 'type' => 'package'], home_url('/'));
        $make_absolute = function($url, $fallback) {
            $url = trim((string)$url);
            if (!$url) return $fallback;
            if (function_exists('wp_http_validate_url') && wp_http_validate_url($url)) return $url;
            if (str_starts_with($url, '/')) {
                $abs = home_url($url);
                if (!function_exists('wp_http_validate_url') || wp_http_validate_url($abs)) return $abs;
            }
            return $fallback;
        };
        $success = $make_absolute($success, $default_success);
        $cancel = $make_absolute($cancel, $default_cancel);
        $session = \ClassFlowPro\Payments\StripeGateway::create_checkout_session([
            'amount_cents' => $price_cents,
            'currency' => $currency,
            'class_title' => 'Package: ' . $name,
            'description' => 'Package: ' . $name . ' (' . $credits . ' credits)',
            'success_url' => $success,
            'cancel_url' => $cancel,
            'booking_id' => 0,
            'instructor_id' => 0,
            'type' => 'package',
        ]);
        if (is_wp_error($session)) return $session;
        global $wpdb;
        $transactions = $wpdb->prefix . 'cfp_transactions';
        $wpdb->insert($transactions, [
            'user_id' => $user_id,
            'booking_id' => null,
            'amount_cents' => $price_cents,
            'currency' => $currency,
            'type' => 'package_purchase',
            'processor' => 'stripe',
            'processor_id' => $session['id'],
            'status' => 'requires_payment',
            'tax_amount_cents' => 0,
            'fee_amount_cents' => 0,
        ], ['%d','%s','%d','%s','%s','%s','%s','%d','%d']);
        // Attach package metadata to PI via webhook path: we rely on payment_intent.succeeded branch with metadata type=package_purchase
        // Since we cannot attach metadata to the Session-level directly for our DB, we process credits in webhook using user/email hints.
        return [ 'id' => $session['id'], 'url' => $session['url'] ];
    }
}
