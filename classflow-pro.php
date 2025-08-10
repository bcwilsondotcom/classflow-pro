<?php
/**
 * Plugin Name: ClassFlow Pro — Pilates Studio Manager
 * Description: End-to-end Pilates studio management: classes, instructors, schedules, bookings, packages/credits, Stripe/Stripe Connect payments with tax, QuickBooks Online accounting, and Elementor widgets for client booking.
 * Version: 1.0.0
 * Author: ClassFlow Pro
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: classflow-pro
 */

if (!defined('ABSPATH')) {
    exit;
}

// Simple PSR-4 autoloader for the plugin
spl_autoload_register(function ($class) {
    $prefix = 'ClassFlowPro\\';
    $base_dir = __DIR__ . '/includes/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Define constants
define('CFP_PLUGIN_FILE', __FILE__);
define('CFP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CFP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Bootstrap plugin
if (!defined('CFP_DB_VERSION')) {
    define('CFP_DB_VERSION', '1.0.0');
}
add_action('plugins_loaded', function () {
    // Check minimal requirements
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__('ClassFlow Pro requires PHP 8.0 or newer.', 'classflow-pro') . '</p></div>';
        });
        return;
    }

    if (!did_action('elementor/loaded')) {
        // Not fatal; Elementor widgets are conditional.
    }
    // Run migrations if needed
    ClassFlowPro\DB\Migrations::maybe_run();

    ClassFlowPro\Plugin::instance()->init();
});

register_activation_hook(__FILE__, function () {
    ClassFlowPro\Activator::activate();
});

register_deactivation_hook(__FILE__, function () {
    ClassFlowPro\Activator::deactivate();
});
