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
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            cancel_note TEXT NULL,
            cancelled_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY class_id (class_id),
            KEY instructor_id (instructor_id),
            KEY location_id (location_id),
            KEY google_event_id (google_event_id),
            KEY start_time (start_time),
            KEY is_private (is_private),
            KEY status (status)
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

        // First-class entities tables
        $classes_sql = "CREATE TABLE {$wpdb->prefix}cfp_classes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            description LONGTEXT NULL,
            duration_mins INT NOT NULL DEFAULT 60,
            capacity INT NOT NULL DEFAULT 8,
            price_cents BIGINT NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'usd',
            status VARCHAR(20) NOT NULL DEFAULT 'active', -- active|inactive|draft
            scheduling_type VARCHAR(20) NOT NULL DEFAULT 'fixed', -- fixed|flexible
            featured_image_id BIGINT UNSIGNED NULL,
            default_location_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY name (name),
            KEY default_location_id (default_location_id)
        ) $charset_collate;";
        dbDelta($classes_sql);

        $instructors_sql = "CREATE TABLE {$wpdb->prefix}cfp_instructors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            bio LONGTEXT NULL,
            email VARCHAR(191) NULL,
            payout_percent DECIMAL(5,2) NULL,
            stripe_account_id VARCHAR(191) NULL,
            availability_weekly TEXT NULL,
            blackout_dates TEXT NULL,
            featured_image_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email)
        ) $charset_collate;";
        dbDelta($instructors_sql);

        $locations_sql = "CREATE TABLE {$wpdb->prefix}cfp_locations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            address1 VARCHAR(191) NULL,
            address2 VARCHAR(191) NULL,
            city VARCHAR(100) NULL,
            state VARCHAR(100) NULL,
            postal_code VARCHAR(20) NULL,
            country VARCHAR(2) NULL,
            timezone VARCHAR(40) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name)
        ) $charset_collate;";
        dbDelta($locations_sql);

        $resources_sql = "CREATE TABLE {$wpdb->prefix}cfp_resources (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            type VARCHAR(50) NULL,
            capacity INT NULL,
            location_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY location_id (location_id)
        ) $charset_collate;";
        dbDelta($resources_sql);

        // Customer notes (staff-entered; some visible to user)
        $notes_sql = "CREATE TABLE {$wpdb->prefix}cfp_customer_notes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            note TEXT NOT NULL,
            visible_to_user TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY visible_to_user (visible_to_user),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($notes_sql);

        // Add missing performance indexes and define foreign key relationships.
        // Since dbDelta() does not manage FKs, apply them explicitly and ignore errors if they already exist.
        try {
            // Ensure InnoDB engine for FK support (ignore if no-op)
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules ENGINE=InnoDB");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_bookings ENGINE=InnoDB");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_waitlist ENGINE=InnoDB");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_classes ENGINE=InnoDB");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_instructors ENGINE=InnoDB");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_locations ENGINE=InnoDB");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_resources ENGINE=InnoDB");
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_coupons ENGINE=InnoDB");
        } catch (\Throwable $e) {}

        // Indexes
        // Columns that may not exist on older dev DBs
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules ADD COLUMN cancel_note TEXT NULL"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules ADD COLUMN cancelled_at DATETIME NULL"); } catch (\Throwable $e) {}
        // Indexes
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules ADD INDEX idx_schedules_class_start (class_id, start_time)"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules ADD INDEX idx_schedules_instr_start (instructor_id, start_time)"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules ADD INDEX idx_schedules_resource (resource_id)"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules ADD INDEX idx_schedules_status (status)"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_bookings ADD INDEX idx_bookings_sched_status (schedule_id, status)"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_bookings ADD INDEX idx_bookings_customer_email (customer_email)"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_waitlist ADD INDEX idx_waitlist_created (created_at)"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_instructors ADD UNIQUE INDEX uniq_instructors_email (email)"); } catch (\Throwable $e) {}
        try { $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_private_requests ADD INDEX idx_private_requests_instr_status (instructor_id, status)"); } catch (\Throwable $e) {}

        // Foreign keys
        try {
            // Schedules -> Classes
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules
                ADD CONSTRAINT fk_cfp_schedules_class
                FOREIGN KEY (class_id)
                REFERENCES {$wpdb->prefix}cfp_classes(id)
                ON DELETE CASCADE ON UPDATE CASCADE");
        } catch (\Throwable $e) {}
        try {
            // Schedules -> Instructors (nullable)
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules
                ADD CONSTRAINT fk_cfp_schedules_instructor
                FOREIGN KEY (instructor_id)
                REFERENCES {$wpdb->prefix}cfp_instructors(id)
                ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (\Throwable $e) {}
        try {
            // Schedules -> Locations (nullable)
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules
                ADD CONSTRAINT fk_cfp_schedules_location
                FOREIGN KEY (location_id)
                REFERENCES {$wpdb->prefix}cfp_locations(id)
                ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (\Throwable $e) {}
        try {
            // Schedules -> Resources (nullable)
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_schedules
                ADD CONSTRAINT fk_cfp_schedules_resource
                FOREIGN KEY (resource_id)
                REFERENCES {$wpdb->prefix}cfp_resources(id)
                ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (\Throwable $e) {}
        try {
            // Bookings -> Schedules
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_bookings
                ADD CONSTRAINT fk_cfp_bookings_schedule
                FOREIGN KEY (schedule_id)
                REFERENCES {$wpdb->prefix}cfp_schedules(id)
                ON DELETE RESTRICT ON UPDATE CASCADE");
        } catch (\Throwable $e) {}
        try {
            // Waitlist -> Schedules
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_waitlist
                ADD CONSTRAINT fk_cfp_waitlist_schedule
                FOREIGN KEY (schedule_id)
                REFERENCES {$wpdb->prefix}cfp_schedules(id)
                ON DELETE CASCADE ON UPDATE CASCADE");
        } catch (\Throwable $e) {}
        try {
            // Private Requests -> Instructors (nullable)
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_private_requests
                ADD CONSTRAINT fk_cfp_private_requests_instructor
                FOREIGN KEY (instructor_id)
                REFERENCES {$wpdb->prefix}cfp_instructors(id)
                ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (\Throwable $e) {}
        try {
            // Resources -> Locations (nullable)
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_resources
                ADD CONSTRAINT fk_cfp_resources_location
                FOREIGN KEY (location_id)
                REFERENCES {$wpdb->prefix}cfp_locations(id)
                ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (\Throwable $e) {}
        try {
            // Classes -> Locations (nullable)
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_classes
                ADD CONSTRAINT fk_cfp_classes_default_location
                FOREIGN KEY (default_location_id)
                REFERENCES {$wpdb->prefix}cfp_locations(id)
                ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (\Throwable $e) {}
        try {
            // Bookings -> Coupons (nullable)
            $wpdb->query("ALTER TABLE {$wpdb->prefix}cfp_bookings
                ADD CONSTRAINT fk_cfp_bookings_coupon
                FOREIGN KEY (coupon_id)
                REFERENCES {$wpdb->prefix}cfp_coupons(id)
                ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (\Throwable $e) {}


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
            'require_login_to_book' => 0,
            'auto_create_user_on_booking' => 1,
            'notify_sms_customer' => 0,
            'notify_sms_instructor' => 0,
            'twilio_account_sid' => '',
            'twilio_auth_token' => '',
            'twilio_from_number' => '',
            'reminder_hours_before' => '24,2',
            'sms_confirmed_body' => '[{site}] Confirmed: {class_title} @ {start_time}.',
            'sms_canceled_body' => '[{site}] {status}: {class_title} @ {start_time}.',
            'sms_rescheduled_body' => '[{site}] Rescheduled: {class_title} now @ {start_time}.',
            'sms_waitlist_body' => '[{site}] Waitlist open: {class_title} @ {start_time}.',
            'sms_reminder_body' => '[{site}] Reminder: {class_title} @ {start_time}.',
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
