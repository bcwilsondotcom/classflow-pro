<?php
declare(strict_types=1);

namespace ClassFlowPro\Core;

class Settings {
    private const OPTION_NAME = 'classflow_pro_settings';
    private array $settings;
    private array $defaults;

    public function __construct() {
        $this->setDefaults();
        $this->settings = wp_parse_args(get_option(self::OPTION_NAME, []), $this->defaults);
    }

    private function setDefaults(): void {
        $this->defaults = [
            'general' => [
                'business_name' => get_bloginfo('name'),
                'business_address' => '',
                'contact_phone' => '',
                'support_email' => get_option('admin_email'),
                'business_hours' => '',
                'timezone' => wp_timezone_string(),
                'date_format' => get_option('date_format'),
                'time_format' => get_option('time_format'),
                'week_starts_on' => 1,
                'currency' => 'USD',
                'country_code' => 'US',
            ],
            'booking' => [
                'advance_booking_days' => 30,
                'min_booking_hours' => 24,
                'cancellation_hours' => 24,
                'auto_confirm_bookings' => true,
                'pending_expiry_minutes' => 30,
                'enable_waitlist' => true,
                'max_waitlist_size' => 5,
                'default_class_capacity' => 10,
                'default_class_duration' => 60,
                'booking_buffer_time' => 15,
            ],
            'payment' => [
                'enabled' => true,
                'require_payment' => true,
                'stripe_mode' => 'test',
                'stripe_test_secret_key' => '',
                'stripe_test_publishable_key' => '',
                'stripe_live_secret_key' => '',
                'stripe_live_publishable_key' => '',
                'stripe_webhook_secret' => '',
                'allow_partial_payment' => false,
                'partial_payment_percentage' => 50,
                'platform_fee_percentage' => 80, // This is now instructor commission percentage
                'use_connected_accounts' => false,
                'stripe_fee_percentage' => 2.9,
                'stripe_fee_fixed' => 0.30,
            ],
            'email' => [
                'from_name' => get_bloginfo('name'),
                'from_email' => get_option('admin_email'),
                'admin_email' => get_option('admin_email'),
                'logo' => '',
                'enable_notifications' => true,
                'primary_color' => '#2271b1',
                'signature' => '',
            ],
            'notifications' => [
                'booking_confirmation' => ['enabled' => true],
                'booking_cancellation' => ['enabled' => true],
                'class_reminder' => ['enabled' => true],
                'payment_confirmation' => ['enabled' => true],
                'payment_failed' => ['enabled' => true],
                'refund_confirmation' => ['enabled' => true],
                'waitlist_notification' => ['enabled' => true],
                'instructor_booking' => ['enabled' => true],
                'instructor_cancellation' => ['enabled' => true],
                'admin_new_booking' => ['enabled' => true],
                'reminder_hours' => 24,
            ],
            'frontend' => [
                'primary_color' => '#3b82f6',
                'secondary_color' => '#1e40af',
                'success_color' => '#28a745',
                'warning_color' => '#ffc107',
                'error_color' => '#dc3545',
                'items_per_page' => 12,
                'show_instructor_bio' => true,
                'calendar_default_view' => 'month',
            ],
            'advanced' => [
                'api_items_per_page' => 10,
                'package_validity_days' => 30,
                'max_bookings_per_day' => 0,
                'max_bookings_per_week' => 0,
                'max_bookings_per_month' => 0,
                'minimum_age' => 0,
                'enable_cache' => true,
            ],
            'system' => [
                'debug_mode' => false,
                'log_level' => 'error',
            ],
        ];
    }

    public function get(string $key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->settings;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                // Try to get from defaults if not found
                $defaultValue = $this->getDefault($key);
                return $defaultValue !== null ? $defaultValue : $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    private function getDefault(string $key) {
        $keys = explode('.', $key);
        $value = $this->defaults;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, $value): void {
        $keys = explode('.', $key);
        $settings = &$this->settings;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $settings[$k] = $value;
            } else {
                if (!isset($settings[$k]) || !is_array($settings[$k])) {
                    $settings[$k] = [];
                }
                $settings = &$settings[$k];
            }
        }

        $this->save();
    }

    public function getAll(): array {
        return $this->settings;
    }

    public function save(): void {
        update_option(self::OPTION_NAME, $this->settings);
    }

    public function reset(): void {
        delete_option(self::OPTION_NAME);
        $this->settings = $this->defaults;
        $this->save();
    }
}