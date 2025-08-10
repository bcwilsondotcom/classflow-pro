<?php
declare(strict_types=1);

namespace ClassFlowPro\Core;

class Deactivator {
    public static function deactivate(): void {
        // Unschedule cron events
        wp_clear_scheduled_hook('classflow_pro_hourly_tasks');
        wp_clear_scheduled_hook('classflow_pro_daily_tasks');
        
        // Clear any transients
        self::clearTransients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private static function clearTransients(): void {
        global $wpdb;
        
        // Delete all transients created by our plugin
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_classflow_pro_%' 
             OR option_name LIKE '_transient_timeout_classflow_pro_%'"
        );
    }
}