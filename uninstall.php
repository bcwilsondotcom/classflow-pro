<?php
// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load WP if needed
// Use options to determine if we should delete data
$settings = get_option('cfp_settings', []);
$delete = !empty($settings['delete_on_uninstall']);

if (!$delete) {
    return;
}

global $wpdb;
$tables = [
    $wpdb->prefix . 'cfp_schedules',
    $wpdb->prefix . 'cfp_bookings',
    $wpdb->prefix . 'cfp_packages',
    $wpdb->prefix . 'cfp_transactions',
    $wpdb->prefix . 'cfp_customers',
    $wpdb->prefix . 'cfp_waitlist',
    $wpdb->prefix . 'cfp_logs',
    $wpdb->prefix . 'cfp_coupons',
    $wpdb->prefix . 'cfp_private_requests',
    $wpdb->prefix . 'cfp_intake_forms',
    $wpdb->prefix . 'cfp_customer_notes',
];
foreach ($tables as $t) {
    $wpdb->query("DROP TABLE IF EXISTS $t");
}

// Delete options
delete_option('cfp_settings');
delete_option('cfp_quickbooks_tokens');
delete_option('cfp_google_tokens');
delete_option('cfp_db_version');

// Delete user meta
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('cfp_ical_token')");
