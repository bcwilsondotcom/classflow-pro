<?php
declare(strict_types=1);

namespace ClassFlowPro\Core;

class Activator {
    public static function activate(): void {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(CLASSFLOW_PRO_PLUGIN_BASENAME);
            wp_die(__('ClassFlow Pro requires PHP 7.4 or higher.', 'classflow-pro'));
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.8', '<')) {
            deactivate_plugins(CLASSFLOW_PRO_PLUGIN_BASENAME);
            wp_die(__('ClassFlow Pro requires WordPress 5.8 or higher.', 'classflow-pro'));
        }

        // Create database tables
        self::createDatabaseTables();

        // Create default options
        self::createDefaultOptions();

        // Create user roles and capabilities
        self::createRolesAndCapabilities();

        // Schedule cron events
        self::scheduleCronEvents();

        // Clear rewrite rules
        flush_rewrite_rules();
    }

    private static function createDatabaseTables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            // Classes table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_classes (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                slug varchar(255) NOT NULL,
                description longtext,
                category_id bigint(20) UNSIGNED DEFAULT NULL,
                duration int(11) NOT NULL DEFAULT 60,
                capacity int(11) NOT NULL DEFAULT 10,
                price decimal(10,2) NOT NULL DEFAULT 0.00,
                status varchar(20) NOT NULL DEFAULT 'active',
                featured_image_id bigint(20) UNSIGNED DEFAULT NULL,
                gallery_ids longtext,
                prerequisites longtext,
                skill_level varchar(50) DEFAULT NULL,
                scheduling_type varchar(20) NOT NULL DEFAULT 'fixed',
                meta longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY category_id (category_id),
                KEY status (status)
            ) $charset_collate",

            // Categories table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_categories (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                slug varchar(255) NOT NULL,
                description text,
                parent_id bigint(20) UNSIGNED DEFAULT NULL,
                color varchar(7) DEFAULT NULL,
                icon varchar(50) DEFAULT NULL,
                position int(11) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug),
                KEY parent_id (parent_id)
            ) $charset_collate",

            // Instructors table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_instructors (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                bio longtext,
                specialties longtext,
                availability longtext,
                hourly_rate decimal(10,2) DEFAULT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                meta longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate",

            // Locations table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_locations (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                address text,
                capacity int(11) DEFAULT NULL,
                resources longtext,
                status varchar(20) NOT NULL DEFAULT 'active',
                meta longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY status (status)
            ) $charset_collate",

            // Schedules table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_schedules (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                class_id bigint(20) UNSIGNED NOT NULL,
                instructor_id bigint(20) UNSIGNED NOT NULL,
                location_id bigint(20) UNSIGNED DEFAULT NULL,
                start_time datetime NOT NULL,
                end_time datetime NOT NULL,
                recurrence_rule text,
                recurrence_end datetime DEFAULT NULL,
                capacity_override int(11) DEFAULT NULL,
                price_override decimal(10,2) DEFAULT NULL,
                booking_type varchar(20) NOT NULL DEFAULT 'group',
                status varchar(20) NOT NULL DEFAULT 'scheduled',
                meta longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY class_id (class_id),
                KEY instructor_id (instructor_id),
                KEY location_id (location_id),
                KEY start_time (start_time),
                KEY status (status)
            ) $charset_collate",

            // Students table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_students (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id bigint(20) UNSIGNED NOT NULL,
                emergency_contact longtext,
                medical_notes longtext,
                preferences longtext,
                status varchar(20) NOT NULL DEFAULT 'active',
                meta longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate",

            // Bookings table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_bookings (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                schedule_id bigint(20) UNSIGNED NOT NULL,
                student_id bigint(20) UNSIGNED NOT NULL,
                booking_code varchar(20) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                payment_status varchar(20) NOT NULL DEFAULT 'pending',
                amount decimal(10,2) NOT NULL DEFAULT 0.00,
                notes text,
                meta longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY booking_code (booking_code),
                KEY schedule_id (schedule_id),
                KEY student_id (student_id),
                KEY status (status),
                KEY payment_status (payment_status)
            ) $charset_collate",

            // Payments table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_payments (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) UNSIGNED DEFAULT NULL,
                package_purchase_id bigint(20) UNSIGNED DEFAULT NULL,
                amount decimal(10,2) NOT NULL,
                currency varchar(3) NOT NULL DEFAULT 'USD',
                gateway varchar(50) NOT NULL,
                transaction_id varchar(255) DEFAULT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                gateway_response longtext,
                meta longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY booking_id (booking_id),
                KEY package_purchase_id (package_purchase_id),
                KEY transaction_id (transaction_id),
                KEY status (status)
            ) $charset_collate",

            // Waitlist table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_waitlists (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                schedule_id bigint(20) UNSIGNED NOT NULL,
                student_id bigint(20) UNSIGNED NOT NULL,
                position int(11) NOT NULL DEFAULT 1,
                status varchar(20) NOT NULL DEFAULT 'waiting',
                notified_at datetime DEFAULT NULL,
                expires_at datetime DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY schedule_id (schedule_id),
                KEY student_id (student_id),
                KEY position (position),
                KEY status (status)
            ) $charset_collate",

            // Attendance table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_attendance (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) UNSIGNED NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'absent',
                checked_in_at datetime DEFAULT NULL,
                checked_in_by bigint(20) UNSIGNED DEFAULT NULL,
                notes text,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY booking_id (booking_id),
                KEY status (status)
            ) $charset_collate",

            // Packages table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_packages (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description text,
                classes_count int(11) NOT NULL,
                price decimal(10,2) NOT NULL,
                validity_days int(11) NOT NULL DEFAULT 30,
                class_restrictions longtext,
                status varchar(20) NOT NULL DEFAULT 'active',
                meta longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY status (status)
            ) $charset_collate",

            // Student packages table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_student_packages (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                student_id bigint(20) UNSIGNED NOT NULL,
                package_id bigint(20) UNSIGNED NOT NULL,
                purchase_id bigint(20) UNSIGNED NOT NULL,
                remaining_classes int(11) NOT NULL,
                expires_at datetime NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'active',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY student_id (student_id),
                KEY package_id (package_id),
                KEY status (status),
                KEY expires_at (expires_at)
            ) $charset_collate",

            // Email notifications table
            "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cf_email_logs (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient_email varchar(255) NOT NULL,
                subject varchar(255) NOT NULL,
                template varchar(100) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'sent',
                error_message text,
                meta longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY recipient_email (recipient_email),
                KEY template (template),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset_collate"
        ];

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table) {
            dbDelta($table);
        }

        // Store database version
        update_option('classflow_pro_db_version', '1.0.0');
    }

    private static function createDefaultOptions(): void {
        $default_options = [
            'classflow_pro_settings' => [
                'general' => [
                    'business_name' => get_bloginfo('name'),
                    'timezone' => get_option('timezone_string', 'UTC'),
                    'date_format' => get_option('date_format'),
                    'time_format' => get_option('time_format'),
                    'week_starts_on' => 1, // Monday
                    'currency' => 'USD',
                ],
                'booking' => [
                    'advance_booking_days' => 30,
                    'min_booking_hours' => 24,
                    'cancellation_hours' => 24,
                    'enable_waitlist' => true,
                    'max_waitlist_size' => 5,
                    'auto_confirm_bookings' => true,
                ],
                'payment' => [
                    'enabled' => true,
                    'require_payment' => true,
                    'allow_partial_payment' => false,
                    'partial_payment_percentage' => 50,
                    'stripe_mode' => 'test',
                ],
                'email' => [
                    'from_name' => get_bloginfo('name'),
                    'from_email' => get_option('admin_email'),
                    'enable_notifications' => true,
                ],
                'frontend' => [
                    'primary_color' => '#3b82f6',
                    'secondary_color' => '#1e40af',
                    'items_per_page' => 12,
                    'show_instructor_bio' => true,
                ],
            ],
            'classflow_pro_version' => CLASSFLOW_PRO_VERSION,
            'classflow_pro_install_date' => current_time('mysql'),
        ];

        foreach ($default_options as $option_name => $option_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $option_value);
            }
        }
    }

    private static function createRolesAndCapabilities(): void {
        // ClassFlow Manager role
        add_role('classflow_manager', __('ClassFlow Manager', 'classflow-pro'), [
            'read' => true,
            'manage_classflow' => true,
            'manage_classflow_classes' => true,
            'manage_classflow_bookings' => true,
            'manage_classflow_students' => true,
            'manage_classflow_instructors' => true,
            'view_classflow_reports' => true,
        ]);

        // ClassFlow Instructor role
        add_role('classflow_instructor', __('ClassFlow Instructor', 'classflow-pro'), [
            'read' => true,
            'view_classflow_schedule' => true,
            'manage_own_classflow_schedule' => true,
            'view_classflow_students' => true,
            'manage_classflow_attendance' => true,
        ]);

        // ClassFlow Student role
        add_role('classflow_student', __('ClassFlow Student', 'classflow-pro'), [
            'read' => true,
            'view_classflow_classes' => true,
            'book_classflow_classes' => true,
            'manage_own_classflow_bookings' => true,
        ]);

        // Add capabilities to administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_classflow');
            $admin_role->add_cap('manage_classflow_settings');
            $admin_role->add_cap('manage_classflow_classes');
            $admin_role->add_cap('manage_classflow_bookings');
            $admin_role->add_cap('manage_classflow_students');
            $admin_role->add_cap('manage_classflow_instructors');
            $admin_role->add_cap('view_classflow_reports');
        }
    }

    private static function scheduleCronEvents(): void {
        // Schedule hourly tasks
        if (!wp_next_scheduled('classflow_pro_hourly_tasks')) {
            wp_schedule_event(time(), 'hourly', 'classflow_pro_hourly_tasks');
        }

        // Schedule daily tasks
        if (!wp_next_scheduled('classflow_pro_daily_tasks')) {
            wp_schedule_event(time(), 'daily', 'classflow_pro_daily_tasks');
        }
    }
}