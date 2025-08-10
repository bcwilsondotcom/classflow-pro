<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package ClassFlow_Pro
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has proper permissions
if (!current_user_can('activate_plugins')) {
    return;
}

// Load plugin file
require_once plugin_dir_path(__FILE__) . 'classflow-pro.php';

// Get settings to check if data should be removed
$settings = get_option('classflow_pro_settings', []);
$remove_data = isset($settings['general']['remove_data_on_uninstall']) ? $settings['general']['remove_data_on_uninstall'] : false;

if ($remove_data) {
    global $wpdb;
    
    // Remove all database tables
    $tables = [
        $wpdb->prefix . 'cf_classes',
        $wpdb->prefix . 'cf_categories',
        $wpdb->prefix . 'cf_instructors',
        $wpdb->prefix . 'cf_locations',
        $wpdb->prefix . 'cf_schedules',
        $wpdb->prefix . 'cf_students',
        $wpdb->prefix . 'cf_bookings',
        $wpdb->prefix . 'cf_payments',
        $wpdb->prefix . 'cf_waitlists',
        $wpdb->prefix . 'cf_attendance',
        $wpdb->prefix . 'cf_packages',
        $wpdb->prefix . 'cf_student_packages',
        $wpdb->prefix . 'cf_email_logs',
    ];
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Remove options
    delete_option('classflow_pro_settings');
    delete_option('classflow_pro_version');
    delete_option('classflow_pro_db_version');
    delete_option('classflow_pro_install_date');
    
    // Remove user roles
    remove_role('classflow_manager');
    remove_role('classflow_instructor');
    remove_role('classflow_student');
    
    // Remove capabilities from administrator
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('manage_classflow');
        $admin_role->remove_cap('manage_classflow_settings');
        $admin_role->remove_cap('manage_classflow_classes');
        $admin_role->remove_cap('manage_classflow_bookings');
        $admin_role->remove_cap('manage_classflow_students');
        $admin_role->remove_cap('manage_classflow_instructors');
        $admin_role->remove_cap('view_classflow_reports');
    }
    
    // Clear scheduled hooks
    wp_clear_scheduled_hook('classflow_pro_hourly_tasks');
    wp_clear_scheduled_hook('classflow_pro_daily_tasks');
    
    // Clear transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_classflow_pro_%' 
         OR option_name LIKE '_transient_timeout_classflow_pro_%'"
    );
}