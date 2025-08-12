<?php
namespace ClassFlowPro\Admin;

class Settings
{
    public static function register_menu(): void
    {
        add_menu_page(
            __('ClassFlow Pro', 'classflow-pro'),
            __('ClassFlow Pro', 'classflow-pro'),
            'manage_options',
            'classflow-pro',
            ['ClassFlowPro\\Admin\\Dashboard', 'render'],
            'dashicons-universal-access',
            60
        );
        add_submenu_page('classflow-pro', __('Dashboard', 'classflow-pro'), __('Dashboard', 'classflow-pro'), 'manage_options', 'classflow-pro', ['ClassFlowPro\\Admin\\Dashboard', 'render']);
        // Custom Classes admin page (replaces default CPT UI)
        add_submenu_page('classflow-pro', __('Classes', 'classflow-pro'), __('Classes', 'classflow-pro'), 'edit_posts', 'classflow-pro-classes', ['ClassFlowPro\\Admin\\Classes', 'render']);
        // Custom entity admin pages (non-CPT)
        add_submenu_page('classflow-pro', __('Instructors', 'classflow-pro'), __('Instructors', 'classflow-pro'), 'manage_options', 'classflow-pro-instructors', ['ClassFlowPro\\Admin\\Instructors', 'render']);
        add_submenu_page('classflow-pro', __('Locations', 'classflow-pro'), __('Locations', 'classflow-pro'), 'manage_options', 'classflow-pro-locations', ['ClassFlowPro\\Admin\\Locations', 'render']);
        add_submenu_page('classflow-pro', __('Resources', 'classflow-pro'), __('Resources', 'classflow-pro'), 'manage_options', 'classflow-pro-resources', ['ClassFlowPro\\Admin\\Resources', 'render']);
        // New full-screen Schedules calendar
        add_submenu_page('classflow-pro', __('Schedules', 'classflow-pro'), __('Schedules', 'classflow-pro'), 'manage_options', 'classflow-pro-schedules', ['ClassFlowPro\\Admin\\Schedules', 'render']);
        add_submenu_page('classflow-pro', __('Bookings', 'classflow-pro'), __('Bookings', 'classflow-pro'), 'manage_options', 'classflow-pro-bookings', ['ClassFlowPro\\Admin\\Bookings', 'render']);
        // Coupons removed: no submenu
        add_submenu_page('classflow-pro', __('QuickBooks Tools', 'classflow-pro'), __('QuickBooks Tools', 'classflow-pro'), 'manage_options', 'classflow-pro-qbtools', ['ClassFlowPro\\Admin\\QuickBooksTools', 'render']);
        // Schedules are now managed within Classes
        add_submenu_page('classflow-pro', __('Private Requests', 'classflow-pro'), __('Private Requests', 'classflow-pro'), 'manage_options', 'classflow-pro-privreq', ['ClassFlowPro\\Admin\\PrivateRequests', 'render']);
        add_submenu_page('classflow-pro', __('Settings', 'classflow-pro'), __('Settings', 'classflow-pro'), 'manage_options', 'classflow-pro-settings', [self::class, 'render_settings_page']);
        add_submenu_page('classflow-pro', __('Logs', 'classflow-pro'), __('Logs', 'classflow-pro'), 'manage_options', 'classflow-pro-logs', ['ClassFlowPro\\Admin\\Logs', 'render']);
        add_submenu_page('classflow-pro', __('Reports', 'classflow-pro'), __('Reports', 'classflow-pro'), 'manage_options', 'classflow-pro-reports', ['ClassFlowPro\\Admin\\Reports', 'render']);
        add_submenu_page('classflow-pro', __('Payouts', 'classflow-pro'), __('Payouts', 'classflow-pro'), 'manage_options', 'classflow-pro-payouts', ['ClassFlowPro\\Admin\\Payouts', 'render']);
        add_submenu_page('classflow-pro', __('Customer Notes', 'classflow-pro'), __('Customer Notes', 'classflow-pro'), 'manage_options', 'classflow-pro-notes', ['ClassFlowPro\\Admin\\CustomerNotes', 'render']);
        add_submenu_page('classflow-pro', __('Intake Forms', 'classflow-pro'), __('Intake Forms', 'classflow-pro'), 'manage_options', 'classflow-pro-intake', ['ClassFlowPro\\Admin\\IntakeForms', 'render']);
    }

    public static function register_settings(): void
    {
        // Important: do not set a dynamic default to the current option value; it causes
        // unchecked checkboxes to be backfilled before sanitize runs, making them impossible to disable.
        register_setting('cfp_settings_group', 'cfp_settings', [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_settings'],
            'default' => [],
        ]);

        add_settings_section('cfp_general', __('General', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('General configuration for ClassFlow Pro.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');

        add_settings_field('currency', __('Currency', 'classflow-pro'), [self::class, 'field_currency'], 'classflow-pro', 'cfp_general');
        add_settings_field('business_country', __('Business Country (ISO-2)', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_general', ['key' => 'business_country']);
        add_settings_field('business_timezone', __('Business Timezone (IANA)', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_general', ['key' => 'business_timezone']);
        add_settings_field('cancellation_window_hours', __('Cancellation Window (hours)', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_general', ['key' => 'cancellation_window_hours', 'step' => '1']);
        add_settings_field('reschedule_window_hours', __('Reschedule Window (hours)', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_general', ['key' => 'reschedule_window_hours', 'step' => '1']);
        add_settings_field('notify_customer', __('Email Customers', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'notify_customer']);
        add_settings_field('require_login_to_book', __('Require Login To Book', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'require_login_to_book']);
        add_settings_field('auto_create_user_on_booking', __('Auto-create User On Booking', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'auto_create_user_on_booking']);
        add_settings_field('notify_admin', __('Email Admin', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'notify_admin']);
        add_settings_field('require_intake', __('Require Intake Before First Visit', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'require_intake']);
        add_settings_field('intake_page_url', __('Intake Page URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_general', ['key' => 'intake_page_url']);
        add_settings_field('waitlist_response_page_url', __('Waitlist Response Page URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_general', ['key' => 'waitlist_response_page_url']);
        add_settings_field('waitlist_hold_minutes', __('Waitlist Hold Window (minutes)', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_general', ['key' => 'waitlist_hold_minutes', 'step' => '5']);
        add_settings_field('delete_on_uninstall', __('Delete Data on Uninstall', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'delete_on_uninstall']);

        // Notifications (Email/SMS)
        add_settings_section('cfp_notifications', __('Notifications', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure email and SMS notifications.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        add_settings_field('notify_sms_customer', __('SMS Customers', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_notifications', ['key' => 'notify_sms_customer']);
        add_settings_field('notify_sms_instructor', __('SMS Instructors', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_notifications', ['key' => 'notify_sms_instructor']);
        add_settings_field('twilio_account_sid', __('Twilio Account SID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_notifications', ['key' => 'twilio_account_sid']);
        add_settings_field('twilio_auth_token', __('Twilio Auth Token', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_notifications', ['key' => 'twilio_auth_token']);
        add_settings_field('twilio_from_number', __('Twilio From Number (E.164)', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_notifications', ['key' => 'twilio_from_number']);
        add_settings_field('reminder_hours_before', __('Reminder Hours Before (comma-separated)', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_notifications', ['key' => 'reminder_hours_before']);

        add_settings_section('cfp_stripe', __('Stripe', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure Stripe for payments and taxes. Set your webhook endpoint to /wp-json/classflow/v1/stripe/webhook', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        add_settings_field('stripe_publishable_key', __('Publishable Key', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_publishable_key']);
        add_settings_field('stripe_secret_key', __('Secret Key', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_secret_key']);
        add_settings_field('stripe_webhook_secret', __('Webhook Secret', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_webhook_secret']);
        add_settings_field('stripe_enable_tax', __('Enable Stripe Tax', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_enable_tax']);
        add_settings_field('stripe_connect_enabled', __('Enable Stripe Connect', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_connect_enabled']);
        add_settings_field('platform_fee_percent', __('Platform Fee %', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_stripe', ['key' => 'platform_fee_percent', 'step' => '0.1']);
        // Always use Stripe Checkout; remove toggle
        add_settings_field('stripe_allow_promo_codes', __('Allow Promotion Codes (Checkout)', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_allow_promo_codes']);
        add_settings_field('checkout_success_url', __('Checkout Success URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_stripe', ['key' => 'checkout_success_url']);
        add_settings_field('checkout_cancel_url', __('Checkout Cancel URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_stripe', ['key' => 'checkout_cancel_url']);

        add_settings_section('cfp_quickbooks', __('QuickBooks Online', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Connect to QuickBooks to create sales receipts automatically on successful payments.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        add_settings_field('quickbooks_environment', __('Environment', 'classflow-pro'), [self::class, 'field_select'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_environment', 'choices' => [
            'production' => __('Production', 'classflow-pro'),
            'sandbox' => __('Sandbox', 'classflow-pro'),
        ]]);
        add_settings_field('quickbooks_client_id', __('Client ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_client_id']);
        add_settings_field('quickbooks_client_secret', __('Client Secret', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_client_secret']);
        add_settings_field('quickbooks_realm_id', __('Company Realm ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_realm_id']);
        add_settings_field('quickbooks_redirect_uri', __('Redirect URI', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_redirect_uri']);
        add_settings_field('qb_item_per_class_enable', __('Create Items per Class', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_item_per_class_enable']);
        add_settings_field('qb_item_prefix', __('Item Name Prefix', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_item_prefix']);
        add_settings_field('qb_default_item_name', __('Default Item Name', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_default_item_name']);
        add_settings_field('qb_income_account_ref', __('Income Account Ref', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_income_account_ref']);
        add_settings_field('qb_tax_code_ref', __('Tax Code Ref', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_tax_code_ref']);

        add_settings_section('cfp_notifications', __('Notifications', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Email templates and delivery options.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        add_settings_field('notify_instructor', __('Email Instructors', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_notifications', ['key' => 'notify_instructor']);
        add_settings_field('template_confirmed_subject', __('Confirmed Subject', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_notifications', ['key' => 'template_confirmed_subject']);
        add_settings_field('template_confirmed_body', __('Confirmed Body (HTML)', 'classflow-pro'), [self::class, 'field_textarea'], 'classflow-pro', 'cfp_notifications', ['key' => 'template_confirmed_body']);
        add_settings_field('template_canceled_subject', __('Canceled Subject', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_notifications', ['key' => 'template_canceled_subject']);
        add_settings_field('template_canceled_body', __('Canceled Body (HTML)', 'classflow-pro'), [self::class, 'field_textarea'], 'classflow-pro', 'cfp_notifications', ['key' => 'template_canceled_body']);
        add_settings_field('template_rescheduled_subject', __('Rescheduled Subject', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_notifications', ['key' => 'template_rescheduled_subject']);
        add_settings_field('template_rescheduled_body', __('Rescheduled Body (HTML)', 'classflow-pro'), [self::class, 'field_textarea'], 'classflow-pro', 'cfp_notifications', ['key' => 'template_rescheduled_body']);

        add_settings_section('cfp_google', __('Google Calendar', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Optionally sync schedules to a Google Calendar. Use connect at /wp-json/classflow/v1/google/connect', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        add_settings_field('google_client_id', __('Client ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_client_id']);
        add_settings_field('google_client_secret', __('Client Secret', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_google', ['key' => 'google_client_secret']);
        add_settings_field('google_calendar_id', __('Calendar ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_calendar_id']);
        add_settings_field('google_redirect_uri', __('Redirect URI', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_redirect_uri']);
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('ClassFlow Pro Settings', 'classflow-pro') . '</h1>';
        // Show current effective value for key toggles for clarity
        try {
            $settings = get_option('cfp_settings', []);
            $require_login = !empty($settings['require_login_to_book']);
            echo '<div class="notice notice-info" style="margin-top:12px;">'
                . '<p><strong>' . esc_html__('Require Login To Book:', 'classflow-pro') . '</strong> '
                . ($require_login ? esc_html__('Enabled', 'classflow-pro') : esc_html__('Disabled', 'classflow-pro'))
                . '</p></div>';
        } catch (\Throwable $e) {}
        echo '<form method="post" action="options.php">';
        settings_fields('cfp_settings_group');
        do_settings_sections('classflow-pro');
        submit_button();
        echo '</form></div>';
    }

    public static function sanitize_settings($input): array
    {
        $defaults = get_option('cfp_settings', []);
        $output = is_array($input) ? $input : [];
        $output['currency'] = isset($output['currency']) ? sanitize_text_field($output['currency']) : ($defaults['currency'] ?? 'usd');
        foreach ([
            'stripe_publishable_key','stripe_secret_key','stripe_webhook_secret','quickbooks_client_id','quickbooks_client_secret','quickbooks_realm_id','quickbooks_redirect_uri',
            'template_confirmed_subject','template_canceled_subject','template_rescheduled_subject',
            'google_client_id','google_client_secret','google_calendar_id','google_redirect_uri',
            'qb_item_prefix','qb_default_item_name','qb_income_account_ref','qb_tax_code_ref'
        ] as $k) {
            if (isset($output[$k])) {
                $output[$k] = trim(wp_unslash($output[$k]));
            }
        }
        foreach ([
            'template_confirmed_body','template_canceled_body','template_rescheduled_body'
        ] as $k) {
            if (isset($output[$k])) {
                $output[$k] = wp_kses_post(wp_unslash($output[$k]));
            }
        }
        $output['stripe_enable_tax'] = isset($output['stripe_enable_tax']) ? 1 : 0;
        $output['stripe_connect_enabled'] = isset($output['stripe_connect_enabled']) ? 1 : 0;
        $output['platform_fee_percent'] = isset($output['platform_fee_percent']) ? floatval($output['platform_fee_percent']) : 0.0;
        // Always use Stripe Checkout; no option persisted
        $output['stripe_allow_promo_codes'] = isset($output['stripe_allow_promo_codes']) ? 1 : 0;
        foreach (['checkout_success_url','checkout_cancel_url'] as $uk) {
            if (isset($output[$uk])) {
                $out = trim(wp_unslash($output[$uk]));
                $output[$uk] = $out ? esc_url_raw($out) : '';
            }
        }
        $output['cancellation_window_hours'] = isset($output['cancellation_window_hours']) ? max(0, intval($output['cancellation_window_hours'])) : 0;
        $output['reschedule_window_hours'] = isset($output['reschedule_window_hours']) ? max(0, intval($output['reschedule_window_hours'])) : 0;
        $output['notify_customer'] = isset($output['notify_customer']) ? 1 : 0;
        $output['notify_admin'] = isset($output['notify_admin']) ? 1 : 0;
        $output['require_login_to_book'] = isset($output['require_login_to_book']) ? 1 : 0;
        $output['auto_create_user_on_booking'] = isset($output['auto_create_user_on_booking']) ? 1 : 0;
        $output['notify_instructor'] = isset($output['notify_instructor']) ? 1 : 0;
        $bc = strtoupper(sanitize_text_field($output['business_country'] ?? ''));
        $output['business_country'] = preg_match('/^[A-Z]{2}$/', $bc) ? $bc : 'US';
        $output['qb_item_per_class_enable'] = isset($output['qb_item_per_class_enable']) ? 1 : 0;
        $output['delete_on_uninstall'] = isset($output['delete_on_uninstall']) ? 1 : 0;
        $output['quickbooks_environment'] = in_array(($output['quickbooks_environment'] ?? 'production'), ['production','sandbox'], true) ? $output['quickbooks_environment'] : 'production';
        return $output;
    }

    public static function field_currency(): void
    {
        $settings = get_option('cfp_settings', []);
        $value = esc_attr($settings['currency'] ?? 'usd');
        echo '<input type="text" name="cfp_settings[currency]" value="' . $value . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('ISO currency code, e.g., usd, eur, gbp.', 'classflow-pro') . '</p>';
    }

    public static function field_text(array $args): void
    {
        $key = $args['key'];
        $settings = get_option('cfp_settings', []);
        $value = esc_attr($settings[$key] ?? '');
        echo '<input type="text" name="cfp_settings[' . esc_attr($key) . ']" value="' . $value . '" class="regular-text" autocomplete="off" />';
    }

    public static function field_password(array $args): void
    {
        $key = $args['key'];
        $settings = get_option('cfp_settings', []);
        $value = esc_attr($settings[$key] ?? '');
        echo '<input type="password" name="cfp_settings[' . esc_attr($key) . ']" value="' . $value . '" class="regular-text" autocomplete="off" />';
    }

    public static function field_checkbox(array $args): void
    {
        $key = $args['key'];
        $settings = get_option('cfp_settings', []);
        $checked = !empty($settings[$key]) ? 'checked' : '';
        echo '<label><input type="checkbox" name="cfp_settings[' . esc_attr($key) . ']" value="1" ' . $checked . ' /> ' . esc_html__('Enabled', 'classflow-pro') . '</label>';
    }

    public static function field_number(array $args): void
    {
        $key = $args['key'];
        $step = isset($args['step']) ? esc_attr($args['step']) : '1';
        $settings = get_option('cfp_settings', []);
        $value = esc_attr($settings[$key] ?? '0');
        echo '<input type="number" name="cfp_settings[' . esc_attr($key) . ']" value="' . $value . '" step="' . $step . '" class="small-text" />';
    }

    public static function field_select(array $args): void
    {
        $key = $args['key'];
        $choices = $args['choices'];
        $settings = get_option('cfp_settings', []);
        $value = esc_attr($settings[$key] ?? '');
        echo '<select name="cfp_settings[' . esc_attr($key) . ']">';
        foreach ($choices as $k => $label) {
            echo '<option value="' . esc_attr($k) . '"' . selected($value, $k, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public static function field_textarea(array $args): void
    {
        $key = $args['key'];
        $settings = get_option('cfp_settings', []);
        $value = esc_textarea($settings[$key] ?? '');
        echo '<textarea name="cfp_settings[' . esc_attr($key) . ']" rows="6" class="large-text code">' . $value . '</textarea>';
        echo '<p class="description">' . esc_html__('Placeholders: {class_title}, {start_time}, {old_start_time}, {amount}, {status}', 'classflow-pro') . '</p>';
    }

    public static function get(string $key, $default = null)
    {
        $settings = get_option('cfp_settings', []);
        return $settings[$key] ?? $default;
    }
}
