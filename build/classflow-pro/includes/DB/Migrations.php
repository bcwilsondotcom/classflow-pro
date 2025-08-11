<?php
namespace ClassFlowPro\DB;

class Migrations
{
    public static function maybe_run(): void
    {
        global $wpdb;
        $current = get_option('cfp_db_version', '0');
        $target = defined('CFP_DB_VERSION') ? CFP_DB_VERSION : '1.0.0';

        // If version is behind, run migrations
        $needs_migration = version_compare($current, $target, '<');

        // Additionally, detect missing core tables (handles volume mounts where activation didn't run)
        $required = [
            $wpdb->prefix . 'cfp_classes',
            $wpdb->prefix . 'cfp_instructors',
            $wpdb->prefix . 'cfp_locations',
            $wpdb->prefix . 'cfp_resources',
            $wpdb->prefix . 'cfp_schedules',
            $wpdb->prefix . 'cfp_bookings',
        ];
        foreach ($required as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!$exists) { $needs_migration = true; break; }
        }

        if ($needs_migration) {
            self::run_all();
            update_option('cfp_db_version', $target, false);
        }
    }

    private static function run_all(): void
    {
        // Reuse Activator definitions to ensure schema is current.
        \ClassFlowPro\Activator::activate();
    }
}
