<?php
namespace ClassFlowPro\DB;

class Migrations
{
    public static function maybe_run(): void
    {
        $current = get_option('cfp_db_version', '0');
        $target = defined('CFP_DB_VERSION') ? CFP_DB_VERSION : '1.0.0';
        if (version_compare($current, $target, '>=')) {
            return;
        }
        self::run_all();
        update_option('cfp_db_version', $target, false);
    }

    private static function run_all(): void
    {
        // Reuse Activator definitions to ensure schema is current.
        \ClassFlowPro\Activator::activate();
    }
}

