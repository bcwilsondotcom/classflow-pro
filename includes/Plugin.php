<?php
namespace ClassFlowPro;

use ClassFlowPro\Admin\Settings;
use ClassFlowPro\REST\Routes;
use ClassFlowPro\Elementor\Module as ElementorModule;
use ClassFlowPro\Admin\Reports;
use ClassFlowPro\Admin\Payouts;
use ClassFlowPro\Shortcodes;
use ClassFlowPro\Privacy\Exporters as PrivacyExporters;
use ClassFlowPro\Notifications\Reminders as Reminders;

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

        // Register admin settings, routes, assets, Elementor widgets
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

        // Notifications: schedule reminders
        Reminders::register();
        
        // Google Workspace integrations
        $this->init_google_services();
    }
    
    /**
     * Initialize Google Workspace services
     */
    private function init_google_services(): void
    {
        // Auto-load Google service classes
        if (Settings::get('google_calendar_enabled') || Settings::get('gmail_enabled') || 
            Settings::get('google_drive_enabled') || Settings::get('google_contacts_enabled')) {
            
            // Schedule creation/update hooks for Google Calendar
            if (Settings::get('google_calendar_enabled')) {
                // Hook into schedule CRUD operations
                add_action('cfp_schedule_created', function($schedule_id) {
                    if (class_exists('\ClassFlowPro\Google\CalendarService')) {
                        \ClassFlowPro\Google\CalendarService::sync_schedule($schedule_id);
                    }
                });
                
                add_action('cfp_schedule_updated', function($schedule_id) {
                    if (class_exists('\ClassFlowPro\Google\CalendarService')) {
                        \ClassFlowPro\Google\CalendarService::sync_schedule($schedule_id);
                    }
                });
                
                add_action('cfp_schedule_deleted', function($schedule_id) {
                    if (class_exists('\ClassFlowPro\Google\CalendarService')) {
                        \ClassFlowPro\Google\CalendarService::delete_event($schedule_id);
                    }
                });
                
                // Sync bookings if enabled
                if (Settings::get('google_calendar_sync_bookings')) {
                    add_action('cfp_booking_confirmed', function($booking_id) {
                        if (class_exists('\ClassFlowPro\Google\CalendarService')) {
                            \ClassFlowPro\Google\CalendarService::sync_booking($booking_id);
                        }
                    });
                }
            }
            
            // Schedule automatic Drive exports
            if (Settings::get('google_drive_enabled')) {
                add_action('init', function() {
                    if (class_exists('\ClassFlowPro\Google\DriveService')) {
                        \ClassFlowPro\Google\DriveService::schedule_auto_exports();
                    }
                });
                
                add_action('cfp_drive_auto_export', function() {
                    if (class_exists('\ClassFlowPro\Google\DriveService')) {
                        \ClassFlowPro\Google\DriveService::run_auto_exports();
                    }
                });
            }
        }
    }

    public function register_assets(): void
    {
        wp_register_style('cfp-frontend', CFP_PLUGIN_URL . 'assets/css/frontend.css', [], '1.0.0');
        wp_register_script('cfp-booking', CFP_PLUGIN_URL . 'assets/js/booking.js', ['jquery'], '1.0.0', true);
        wp_register_script('cfp-calendar', CFP_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], '1.0.0', true);
        wp_register_script('cfp-step', CFP_PLUGIN_URL . 'assets/js/step-booking.js', ['jquery'], '1.0.0', true);
        wp_register_script('cfp-intake', CFP_PLUGIN_URL . 'assets/js/intake.js', ['jquery'], '1.0.0', true);
        wp_register_script('cfp-portal', CFP_PLUGIN_URL . 'assets/js/portal.js', ['jquery'], '1.0.0', true);
        wp_register_script('cfp-client', CFP_PLUGIN_URL . 'assets/js/client.js', ['jquery'], '1.0.0', true);
        wp_register_script('cfp-checkout-success', CFP_PLUGIN_URL . 'assets/js/checkout-success.js', ['jquery'], '1.0.0', true);
        wp_register_script('cfp-waitlist-response', CFP_PLUGIN_URL . 'assets/js/waitlist-response.js', ['jquery'], '1.0.0', true);
        $settings = [
            'restUrl' => esc_url_raw(rest_url('classflow/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'stripePublishableKey' => Admin\Settings::get('stripe_publishable_key'),
            'businessCountry' => Admin\Settings::get('business_country', 'US'),
            'siteName' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            'businessTimezone' => Admin\Settings::get('business_timezone', (function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC')),
            // Always use Stripe Checkout for paid flows
            'useStripeCheckout' => true,
            'intakePageUrl' => esc_url_raw(Admin\Settings::get('intake_page_url', '')),
            'isLoggedIn' => is_user_logged_in(),
            'requireLoginToBook' => (bool) Admin\Settings::get('require_login_to_book', 0),
            'userCredits' => is_user_logged_in() ? \ClassFlowPro\Packages\Manager::get_user_credits(get_current_user_id()) : 0,
        ];
        wp_localize_script('cfp-booking', 'CFP_DATA', $settings);
        // Share same data to other frontend scripts if enqueued
        wp_localize_script('cfp-calendar', 'CFP_DATA', $settings);
        wp_localize_script('cfp-step', 'CFP_DATA', $settings);
        wp_localize_script('cfp-intake', 'CFP_DATA', $settings);
        wp_localize_script('cfp-checkout-success', 'CFP_DATA', $settings);
        wp_localize_script('cfp-waitlist-response', 'CFP_DATA', $settings);
    }

    public function enqueue_admin_assets(): void
    {
        // No longer enqueue CPT-specific admin assets; custom admin pages load standard WP assets.
    }
}
