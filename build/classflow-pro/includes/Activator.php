<?php
namespace ClassFlowPro;

class Activator
{
    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [];

        $tables[] = "CREATE TABLE {$wpdb->prefix}cfp_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            class_id BIGINT UNSIGNED NOT NULL,
            instructor_id BIGINT UNSIGNED NULL,
            resource_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            capacity INT NOT NULL DEFAULT 1,
            price_cents BIGINT NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'usd',
            is_private TINYINT(1) NOT NULL DEFAULT 0,
            google_event_id VARCHAR(191) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY class_id (class_id),
            KEY instructor_id (instructor_id),
            KEY location_id (location_id),
            KEY google_event_id (google_event_id),
            KEY start_time (start_time),
            KEY is_private (is_private)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cfp_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            schedule_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            customer_email VARCHAR(191) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payment_intent_id VARCHAR(191) NULL,
            payment_status VARCHAR(30) NULL,
            credits_used INT NOT NULL DEFAULT 0,
            amount_cents BIGINT NOT NULL DEFAULT 0,
            discount_cents BIGINT NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'usd',
            coupon_id BIGINT UNSIGNED NULL,
            coupon_code VARCHAR(100) NULL,
            metadata LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY schedule_id (schedule_id),
            KEY user_id (user_id),
            KEY payment_intent_id (payment_intent_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cfp_packages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(191) NOT NULL,
            credits INT NOT NULL,
            credits_remaining INT NOT NULL,
            price_cents BIGINT NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'usd',
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cfp_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            booking_id BIGINT UNSIGNED NULL,
            amount_cents BIGINT NOT NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'usd',
            type VARCHAR(40) NOT NULL,
            processor VARCHAR(40) NOT NULL,
            processor_id VARCHAR(191) NULL,
            status VARCHAR(30) NOT NULL,
            tax_amount_cents BIGINT NOT NULL DEFAULT 0,
            fee_amount_cents BIGINT NOT NULL DEFAULT 0,
            receipt_url VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY booking_id (booking_id),
            KEY processor_id (processor_id)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cfp_customers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(191) NOT NULL,
            stripe_customer_id VARCHAR(191) NULL,
            quickbooks_customer_id VARCHAR(191) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cfp_waitlist (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            schedule_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY schedule_id (schedule_id),
            KEY email (email)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cfp_private_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            name VARCHAR(191) NULL,
            email VARCHAR(191) NOT NULL,
            instructor_id BIGINT UNSIGNED NULL,
            preferred_date DATE NULL,
            preferred_time VARCHAR(20) NULL,
            notes TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY instructor_id (instructor_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cfp_intake_forms (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            data LONGTEXT NOT NULL,
            version VARCHAR(20) NOT NULL DEFAULT 'v1',
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            signed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY signed_at (signed_at)
        ) $charset_collate;";

        $tables[] = "CREATE TABLE {$wpdb->prefix}cfp_coupons (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(100) NOT NULL,
            type VARCHAR(20) NOT NULL, -- percent|fixed
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NULL,
            start_at DATETIME NULL,
            end_at DATETIME NULL,
            usage_limit INT NULL,
            usage_limit_per_user INT NULL,
            min_amount_cents BIGINT NULL,
            classes TEXT NULL,
            locations TEXT NULL,
            instructors TEXT NULL,
            resources TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";

        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        // Logs table
        $logs_sql = "CREATE TABLE {$wpdb->prefix}cfp_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL,
            source VARCHAR(60) NOT NULL,
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY source (source),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($logs_sql);

        // Default options
        add_option('cfp_settings', [
            'currency' => 'usd',
            'business_country' => 'US',
            'business_timezone' => get_option('timezone_string') ?: 'UTC',
            'stripe_publishable_key' => '',
            'stripe_secret_key' => '',
            'stripe_webhook_secret' => '',
            'stripe_enable_tax' => 1,
            'stripe_connect_enabled' => 0,
            'platform_fee_percent' => 0,
            'quickbooks_environment' => 'production',
            'quickbooks_client_id' => '',
            'quickbooks_client_secret' => '',
            'quickbooks_realm_id' => '',
            'quickbooks_redirect_uri' => home_url('/wp-json/classflow/v1/quickbooks/callback'),
            'notify_customer' => 1,
            'notify_admin' => 1,
            'notify_instructor' => 0,
            'cancellation_window_hours' => 0,
            'reschedule_window_hours' => 0,
            'require_intake' => 0,
        ]);
    }

    public static function deactivate(): void
    {
        // Keep data for safety; no destructive action.
    }
}
