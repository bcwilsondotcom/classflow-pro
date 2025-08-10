<?php
namespace ClassFlowPro;

use ClassFlowPro\Admin\Settings;
use ClassFlowPro\PostTypes\ClassType;
use ClassFlowPro\PostTypes\InstructorType;
use ClassFlowPro\PostTypes\ResourceType;
use ClassFlowPro\PostTypes\LocationType;
use ClassFlowPro\REST\Routes;
use ClassFlowPro\Elementor\Module as ElementorModule;
use ClassFlowPro\Admin\Reports;
use ClassFlowPro\Admin\Payouts;
use ClassFlowPro\Shortcodes;
use ClassFlowPro\Privacy\Exporters as PrivacyExporters;

class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void
    {
        // Load textdomain
        load_plugin_textdomain('classflow-pro', false, basename(CFP_PLUGIN_DIR) . '/languages');

        // Register admin settings, post types, routes, assets, Elementor widgets
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_assets']);
        add_action('admin_menu', [Settings::class, 'register_menu']);
        add_action('admin_init', [Settings::class, 'register_settings']);
        add_action('rest_api_init', [Routes::class, 'register']);
        add_action('admin_post_cfp_export_csv', [Reports::class, 'export_csv']);
        add_action('admin_post_cfp_export_payouts', [Payouts::class, 'export_csv']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Elementor integration only if Elementor is loaded
        add_action('elementor/widgets/register', function() {
            if (did_action('elementor/loaded')) {
                ElementorModule::register_widgets();
            }
        });

        // Shortcodes for non-Elementor sites
        add_action('init', [Shortcodes::class, 'register']);

        // Privacy exporters/erasers
        PrivacyExporters::register();
    }

    public function register_post_types(): void
    {
        ClassType::register();
        InstructorType::register();
        ResourceType::register();
        LocationType::register();
    }

    public function register_assets(): void
    {
        wp_register_style('cfp-frontend', CFP_PLUGIN_URL . 'assets/css/frontend.css', [], '1.0.0');
        wp_register_script('cfp-booking', CFP_PLUGIN_URL . 'assets/js/booking.js', ['jquery'], '1.0.0', true);
        $settings = [
            'restUrl' => esc_url_raw(rest_url('classflow/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'stripePublishableKey' => Admin\Settings::get('stripe_publishable_key'),
            'businessCountry' => Admin\Settings::get('business_country', 'US'),
            'siteName' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            'businessTimezone' => Admin\Settings::get('business_timezone', (function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC')),
        ];
        wp_localize_script('cfp-booking', 'CFP_DATA', $settings);
    }

    public function enqueue_admin_assets(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) return;
        $pt = $screen->post_type ?? '';
        if (in_array($pt, ['cfp_instructor','cfp_resource','cfp_class'], true)) {
            wp_enqueue_script('cfp-admin', CFP_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], '1.0.0', true);
        }
    }
}
