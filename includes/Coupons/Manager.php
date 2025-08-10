<?php
namespace ClassFlowPro\Coupons;

class Manager
{
    public static function find_by_code(string $code): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_coupons';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE code = %s", strtoupper(trim($code))), ARRAY_A);
        return $row ?: null;
    }

    public static function validate_and_discount(array $coupon, array $schedule, ?int $user_id, ?string $email, int $amount_cents): array
    {
        $now = gmdate('Y-m-d H:i:s');
        if (!empty($coupon['start_at']) && $coupon['start_at'] > $now) {
            return ['error' => __('Coupon not yet active', 'classflow-pro')];
        }
        if (!empty($coupon['end_at']) && $coupon['end_at'] < $now) {
            return ['error' => __('Coupon expired', 'classflow-pro')];
        }
        if (!empty($coupon['min_amount_cents']) && $amount_cents < (int)$coupon['min_amount_cents']) {
            return ['error' => __('Order amount too low for coupon', 'classflow-pro')];
        }
        // Usage limits (count confirmed bookings using this coupon)
        global $wpdb;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        if (!empty($coupon['usage_limit'])) {
            $used = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bookings WHERE coupon_id = %d AND status IN ('confirmed')", (int)$coupon['id']));
            if ($used >= (int)$coupon['usage_limit']) {
                return ['error' => __('Coupon usage limit reached', 'classflow-pro')];
            }
        }
        if (!empty($coupon['usage_limit_per_user']) && ($user_id || $email)) {
            $used = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings WHERE coupon_id = %d AND status IN ('confirmed') AND (user_id = %d OR customer_email = %s)",
                (int)$coupon['id'], (int)($user_id ?: 0), (string)($email ?: '')
            ));
            if ($used >= (int)$coupon['usage_limit_per_user']) {
                return ['error' => __('You have already used this coupon the maximum number of times', 'classflow-pro')];
            }
        }
        // Scope checks
        $checks = [
            'classes' => 'class_id',
            'locations' => 'location_id',
            'instructors' => 'instructor_id',
            'resources' => 'resource_id',
        ];
        foreach ($checks as $field => $key) {
            if (!empty($coupon[$field])) {
                $allowed = array_filter(array_map('intval', explode(',', $coupon[$field])));
                if ($allowed && !in_array((int)$schedule[$key], $allowed, true)) {
                    return ['error' => __('Coupon not valid for this class/location/instructor/resource', 'classflow-pro')];
                }
            }
        }
        // Currency check for fixed
        $discount_cents = 0;
        if ($coupon['type'] === 'percent') {
            $discount_cents = (int)round($amount_cents * (float)$coupon['amount'] / 100);
        } else {
            $discount_cents = (int)round(((float)$coupon['amount']) * 100);
        }
        $discount_cents = max(0, min($amount_cents, $discount_cents));
        return ['discount_cents' => $discount_cents];
    }
}

