<?php
/**
 * Plugin Name: ClassFlow Pro
 * Plugin URI: https://classflowpro.com
 * Description: Modern WordPress class and course booking system with advanced scheduling, payments, and management features
 * Version: 1.0.0
 * Author: ClassFlow Pro Team
 * Author URI: https://classflowpro.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: classflow-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CLASSFLOW_PRO_VERSION', '1.0.0');
define('CLASSFLOW_PRO_PLUGIN_FILE', __FILE__);
define('CLASSFLOW_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLASSFLOW_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLASSFLOW_PRO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
require_once CLASSFLOW_PRO_PLUGIN_DIR . 'vendor/autoload.php';

// Plugin activation/deactivation hooks
register_activation_hook(__FILE__, ['ClassFlowPro\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['ClassFlowPro\Core\Deactivator', 'deactivate']);

// Initialize the plugin
add_action('plugins_loaded', function() {
    ClassFlowPro\Core\Plugin::getInstance()->init();
});