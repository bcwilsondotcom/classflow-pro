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

        // Business Country/Timezone are inferred from Locations; no manual settings fields shown
        add_settings_field('cancellation_window_hours', __('Cancellation Window (hours)', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_general', ['key' => 'cancellation_window_hours', 'step' => '1', 'help' => __('Minimum hours before start that clients can cancel.', 'classflow-pro')]);
        add_settings_field('reschedule_window_hours', __('Reschedule Window (hours)', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_general', ['key' => 'reschedule_window_hours', 'step' => '1', 'help' => __('Minimum hours before start that clients can reschedule.', 'classflow-pro')]);
        add_settings_field('notify_customer', __('Email Customers', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'notify_customer', 'help' => __('Send booking confirmations and updates to clients.', 'classflow-pro')]);
        add_settings_field('require_login_to_book', __('Require Login To Book', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'require_login_to_book', 'help' => __('Force sign-in/up before booking classes.', 'classflow-pro')]);
        add_settings_field('auto_create_user_on_booking', __('Auto-create User On Booking', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'auto_create_user_on_booking', 'help' => __('Create a WP user for new clients when they book.', 'classflow-pro')]);
        add_settings_field('notify_admin', __('Email Admin', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'notify_admin', 'help' => __('Send booking notifications to site admin.', 'classflow-pro')]);
        add_settings_field('require_intake', __('Require Intake Before First Visit', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'require_intake', 'help' => __('Clients must submit intake form before first class.', 'classflow-pro')]);
        add_settings_field('intake_page_url', __('Intake Page URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_general', ['key' => 'intake_page_url', 'help' => __('URL of the page containing the intake form shortcode.', 'classflow-pro')]);
        add_settings_field('waitlist_response_page_url', __('Waitlist Response Page URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_general', ['key' => 'waitlist_response_page_url', 'help' => __('URL where clients confirm waitlist offers.', 'classflow-pro')]);
        add_settings_field('waitlist_hold_minutes', __('Waitlist Hold Window (minutes)', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_general', ['key' => 'waitlist_hold_minutes', 'step' => '5', 'help' => __('Time a released spot is held for the waitlisted client.', 'classflow-pro')]);
        add_settings_field('delete_on_uninstall', __('Delete Data on Uninstall', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'delete_on_uninstall', 'help' => __('Remove plugin data when uninstalling. Irreversible.', 'classflow-pro')]);

        // Notifications (Email/SMS)
        add_settings_section('cfp_notifications', __('Notifications', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure email and SMS notifications.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        add_settings_field('notify_sms_customer', __('SMS Customers', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_notifications', ['key' => 'notify_sms_customer', 'help' => __('Send SMS confirmations and reminders to clients.', 'classflow-pro')]);
        add_settings_field('notify_sms_instructor', __('SMS Instructors', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_notifications', ['key' => 'notify_sms_instructor', 'help' => __('Send SMS notifications to instructors.', 'classflow-pro')]);
        add_settings_field('twilio_account_sid', __('Twilio Account SID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_notifications', ['key' => 'twilio_account_sid', 'help' => __('From Twilio Console > Account > API Keys.', 'classflow-pro')]);
        add_settings_field('twilio_auth_token', __('Twilio Auth Token', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_notifications', ['key' => 'twilio_auth_token', 'help' => __('Twilio auth token for your account.', 'classflow-pro')]);
        add_settings_field('twilio_from_number', __('Twilio From Number (E.164)', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_notifications', ['key' => 'twilio_from_number', 'help' => __('The sending number, e.g., +15551234567.', 'classflow-pro')]);
        add_settings_field('reminder_hours_before', __('Reminder Hours Before (comma-separated)', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_notifications', ['key' => 'reminder_hours_before', 'help' => __('Ex: 24,2 sends reminders 24h and 2h before start.', 'classflow-pro')]);

        add_settings_section('cfp_stripe', __('Stripe', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure Stripe for payments and taxes. Set your webhook endpoint to /wp-json/classflow/v1/stripe/webhook', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        add_settings_field('stripe_publishable_key', __('Publishable Key', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_publishable_key', 'help' => __('Starts with pk_live_ or pk_test_.', 'classflow-pro')]);
        add_settings_field('stripe_secret_key', __('Secret Key', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_secret_key', 'help' => __('Starts with sk_live_ or sk_test_.', 'classflow-pro')]);
        add_settings_field('stripe_webhook_secret', __('Webhook Secret', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_webhook_secret', 'help' => __('From Stripe Webhooks (whsec_…). Verifies incoming events.', 'classflow-pro')]);
        add_settings_field('stripe_enable_tax', __('Enable Stripe Tax', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_enable_tax', 'help' => __('If enabled, Stripe calculates and adds tax.', 'classflow-pro')]);
        add_settings_field('stripe_connect_enabled', __('Enable Stripe Connect', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_connect_enabled', 'help' => __('Split payments to connected accounts (marketplace).', 'classflow-pro')]);
        add_settings_field('platform_fee_percent', __('Platform Fee %', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_stripe', ['key' => 'platform_fee_percent', 'step' => '0.1', 'help' => __('Percentage fee charged on transactions (Connect).', 'classflow-pro')]);
        // Always use Stripe Checkout; remove toggle
        add_settings_field('stripe_allow_promo_codes', __('Allow Promotion Codes (Checkout)', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_allow_promo_codes', 'help' => __('Let customers enter valid Stripe promo codes at checkout.', 'classflow-pro')]);
        add_settings_field('checkout_success_url', __('Checkout Success URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_stripe', ['key' => 'checkout_success_url', 'help' => __('Where to send clients after successful checkout.', 'classflow-pro')]);
        add_settings_field('checkout_cancel_url', __('Checkout Cancel URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_stripe', ['key' => 'checkout_cancel_url', 'help' => __('Where to send clients if they cancel checkout.', 'classflow-pro')]);

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

        // Google Workspace Settings Section
        add_settings_section('cfp_google', __('Google Workspace Integration', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Connect ClassFlow Pro with Google Workspace services for enhanced functionality. Configure OAuth credentials once to enable multiple Google services.', 'classflow-pro') . '</p>';
            echo '<div style="background:#f0f8ff;padding:10px;border-left:4px solid #0073aa;margin:10px 0;">';
            echo '<strong>' . esc_html__('Setup Instructions:', 'classflow-pro') . '</strong><br>';
            echo esc_html__('1. Go to Google Cloud Console → APIs & Services → Credentials', 'classflow-pro') . '<br>';
            echo esc_html__('2. Create OAuth 2.0 Client ID (Web application)', 'classflow-pro') . '<br>';
            echo esc_html__('3. Add redirect URI: ', 'classflow-pro') . '<code>' . esc_url(site_url('/wp-json/classflow/v1/google/callback')) . '</code><br>';
            echo esc_html__('4. Enable required APIs: Calendar, Gmail, Drive, Meet', 'classflow-pro') . '<br>';
            echo '</div>';
        }, 'classflow-pro');
        
        // OAuth Settings
        add_settings_field('google_oauth_heading', '', function() {
            echo '<h3 style="margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:5px;">' . esc_html__('OAuth Configuration', 'classflow-pro') . '</h3>';
        }, 'classflow-pro', 'cfp_google');
        
        add_settings_field('google_client_id', __('Client ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_client_id', 'help' => __('OAuth 2.0 Client ID from Google Cloud Console', 'classflow-pro')]);
        add_settings_field('google_client_secret', __('Client Secret', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_google', ['key' => 'google_client_secret', 'help' => __('OAuth client secret (keep private)', 'classflow-pro')]);
        add_settings_field('google_redirect_uri', __('Redirect URI', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_redirect_uri', 'help' => __('Must match Google app configuration', 'classflow-pro')]);
        
        // Calendar Settings
        add_settings_field('google_calendar_heading', '', function() {
            echo '<h3 style="margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:5px;">' . esc_html__('Google Calendar', 'classflow-pro') . '</h3>';
        }, 'classflow-pro', 'cfp_google');
        
        add_settings_field('google_calendar_enabled', __('Enable Calendar Sync', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_google', ['key' => 'google_calendar_enabled', 'help' => __('Sync class schedules to Google Calendar', 'classflow-pro')]);
        add_settings_field('google_calendar_id', __('Calendar ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_calendar_id', 'help' => __('Target calendar (e.g., primary or calendar@group.calendar.google.com)', 'classflow-pro')]);
        add_settings_field('google_calendar_sync_bookings', __('Sync Bookings', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_google', ['key' => 'google_calendar_sync_bookings', 'help' => __('Add individual bookings as calendar events', 'classflow-pro')]);
        add_settings_field('google_calendar_color', __('Event Color', 'classflow-pro'), [self::class, 'field_select'], 'classflow-pro', 'cfp_google', ['key' => 'google_calendar_color', 'choices' => [
            '' => __('Default', 'classflow-pro'),
            '1' => __('Lavender', 'classflow-pro'),
            '2' => __('Sage', 'classflow-pro'),
            '3' => __('Grape', 'classflow-pro'),
            '4' => __('Flamingo', 'classflow-pro'),
            '5' => __('Banana', 'classflow-pro'),
            '6' => __('Tangerine', 'classflow-pro'),
            '7' => __('Peacock', 'classflow-pro'),
            '8' => __('Graphite', 'classflow-pro'),
            '9' => __('Blueberry', 'classflow-pro'),
            '10' => __('Basil', 'classflow-pro'),
            '11' => __('Tomato', 'classflow-pro'),
        ]]);
        
        // Gmail Settings
        add_settings_field('google_gmail_heading', '', function() {
            echo '<h3 style="margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:5px;">' . esc_html__('Gmail Integration', 'classflow-pro') . '</h3>';
        }, 'classflow-pro', 'cfp_google');
        
        add_settings_field('gmail_enabled', __('Enable Gmail', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_google', ['key' => 'gmail_enabled', 'help' => __('Use Gmail API for sending emails instead of wp_mail', 'classflow-pro')]);
        add_settings_field('gmail_sender_email', __('Sender Email', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'gmail_sender_email', 'help' => __('Authorized Gmail address for sending (must be authenticated)', 'classflow-pro')]);
        add_settings_field('gmail_sender_name', __('Sender Name', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'gmail_sender_name', 'help' => __('Display name for sent emails', 'classflow-pro')]);
        add_settings_field('gmail_track_opens', __('Track Opens', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_google', ['key' => 'gmail_track_opens', 'help' => __('Track when emails are opened (adds tracking pixel)', 'classflow-pro')]);
        
        // Google Meet Settings
        add_settings_field('google_meet_heading', '', function() {
            echo '<h3 style="margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:5px;">' . esc_html__('Google Meet', 'classflow-pro') . '</h3>';
        }, 'classflow-pro', 'cfp_google');
        
        add_settings_field('google_meet_enabled', __('Enable Meet Links', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_google', ['key' => 'google_meet_enabled', 'help' => __('Automatically create Google Meet links for virtual classes', 'classflow-pro')]);
        add_settings_field('google_meet_auto_create', __('Auto-Create for Virtual', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_google', ['key' => 'google_meet_auto_create', 'help' => __('Automatically add Meet links when location is "Virtual" or "Online"', 'classflow-pro')]);
        
        // Google Drive Settings
        add_settings_field('google_drive_heading', '', function() {
            echo '<h3 style="margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:5px;">' . esc_html__('Google Drive', 'classflow-pro') . '</h3>';
        }, 'classflow-pro', 'cfp_google');
        
        add_settings_field('google_drive_enabled', __('Enable Drive Backup', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_google', ['key' => 'google_drive_enabled', 'help' => __('Backup booking data and reports to Google Drive', 'classflow-pro')]);
        add_settings_field('google_drive_folder_id', __('Folder ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_drive_folder_id', 'help' => __('Google Drive folder ID for backups (leave empty for root)', 'classflow-pro')]);
        add_settings_field('google_drive_auto_export', __('Auto Export Reports', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_google', ['key' => 'google_drive_auto_export', 'help' => __('Automatically export daily/weekly reports to Drive', 'classflow-pro')]);
        
        // Google Contacts Settings
        add_settings_field('google_contacts_heading', '', function() {
            echo '<h3 style="margin-top:20px;border-bottom:1px solid #ccc;padding-bottom:5px;">' . esc_html__('Google Contacts', 'classflow-pro') . '</h3>';
        }, 'classflow-pro', 'cfp_google');
        
        add_settings_field('google_contacts_enabled', __('Enable Contacts Sync', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_google', ['key' => 'google_contacts_enabled', 'help' => __('Sync customer data with Google Contacts', 'classflow-pro')]);
        add_settings_field('google_contacts_group', __('Contact Group', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_contacts_group', 'help' => __('Group name for ClassFlow customers (e.g., "ClassFlow Customers")', 'classflow-pro')]);
        
        // Connection Status
        add_settings_field('google_connection_status', __('Connection Status', 'classflow-pro'), function() {
            $token = get_option('cfp_google_token');
            if ($token && !empty($token['access_token'])) {
                echo '<span style="color:green;font-weight:bold;">✓ ' . esc_html__('Connected', 'classflow-pro') . '</span>';
                echo ' <a href="' . esc_url(site_url('/wp-json/classflow/v1/google/disconnect')) . '" class="button button-small" onclick="return confirm(\'' . esc_js(__('Are you sure you want to disconnect?', 'classflow-pro')) . '\');">' . esc_html__('Disconnect', 'classflow-pro') . '</a>';
            } else {
                echo '<span style="color:red;">✗ ' . esc_html__('Not Connected', 'classflow-pro') . '</span>';
                echo ' <a href="' . esc_url(site_url('/wp-json/classflow/v1/google/connect')) . '" class="button button-primary button-small">' . esc_html__('Connect to Google', 'classflow-pro') . '</a>';
            }
        }, 'classflow-pro', 'cfp_google');
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $tabs = [
            'general' => __('General', 'classflow-pro'),
            'notifications' => __('Notifications', 'classflow-pro'),
            'stripe' => __('Stripe', 'classflow-pro'),
            'quickbooks' => __('QuickBooks', 'classflow-pro'),
            'google' => __('Google Workspace', 'classflow-pro'),
        ];
        $active = isset($_GET['tab']) ? sanitize_key((string)$_GET['tab']) : 'general';
        if (!isset($tabs[$active])) { $active = 'general'; }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ClassFlow Pro Settings', 'classflow-pro') . '</h1>';
        echo '<style>
        .cfp-tab-nav{margin:18px 0; display:flex; gap:8px; border-bottom:1px solid #ccd0d4;}
        .cfp-tab-nav a{padding:8px 12px; text-decoration:none; border:1px solid transparent; border-bottom:none; background:#f6f7f7; color:#1d2327; border-radius:4px 4px 0 0;}
        .cfp-tab-nav a.active{background:#fff; border-color:#ccd0d4;}
        .cfp-help{display:inline-block; margin-left:6px; color:#666; cursor:help;}
        .cfp-help .dashicons{vertical-align:middle;}
        </style>';
        echo '<div class="cfp-tab-nav">';
        foreach ($tabs as $slug => $label) {
            $url = esc_url(add_query_arg(['page' => 'classflow-pro-settings', 'tab' => $slug], admin_url('admin.php')));
            $class = $active === $slug ? 'active' : '';
            echo '<a class="' . esc_attr($class) . '" href="' . $url . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';

        echo '<form method="post" action="options.php">';
        settings_fields('cfp_settings_group');
        // Render only the sections for the active tab
        echo '<div class="cfp-settings-sections">';
        self::render_sections_for_tab($active);
        echo '</div>';
        submit_button();
        echo '</form></div>';
    }

    private static function render_sections_for_tab(string $tab): void
    {
        // Map tabs to section IDs
        $page = 'classflow-pro';
        $map = [
            'general' => ['cfp_general'],
            'notifications' => ['cfp_notifications'],
            'stripe' => ['cfp_stripe'],
            'quickbooks' => ['cfp_quickbooks'],
            'google' => ['cfp_google'],
        ];
        if (empty($map[$tab])) return;
        global $wp_settings_sections, $wp_settings_fields;
        foreach ($map[$tab] as $section_id) {
            if (!isset($wp_settings_sections[$page][$section_id])) continue;
            $section = $wp_settings_sections[$page][$section_id];
            if ($section['title']) {
                echo '<h2>' . esc_html($section['title']) . '</h2>';
            }
            if (!empty($section['callback'])) {
                call_user_func($section['callback']);
            }
            if (isset($wp_settings_fields[$page][$section_id])) {
                echo '<table class="form-table" role="presentation">';
                do_settings_fields($page, $section_id);
                echo '</table>';
            }
        }
    }

    public static function sanitize_settings($input): array
    {
        $defaults = get_option('cfp_settings', []);
        $output = is_array($input) ? $input : [];
        foreach ([
            'stripe_publishable_key','stripe_secret_key','stripe_webhook_secret','quickbooks_client_id','quickbooks_client_secret','quickbooks_realm_id','quickbooks_redirect_uri',
            'template_confirmed_subject','template_canceled_subject','template_rescheduled_subject',
            'google_client_id','google_client_secret','google_calendar_id','google_redirect_uri',
            'gmail_sender_email','gmail_sender_name','google_drive_folder_id','google_contacts_group',
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
        // Google Workspace checkboxes
        $output['google_calendar_enabled'] = isset($output['google_calendar_enabled']) ? 1 : 0;
        $output['google_calendar_sync_bookings'] = isset($output['google_calendar_sync_bookings']) ? 1 : 0;
        $output['gmail_enabled'] = isset($output['gmail_enabled']) ? 1 : 0;
        $output['gmail_track_opens'] = isset($output['gmail_track_opens']) ? 1 : 0;
        $output['google_meet_enabled'] = isset($output['google_meet_enabled']) ? 1 : 0;
        $output['google_meet_auto_create'] = isset($output['google_meet_auto_create']) ? 1 : 0;
        $output['google_drive_enabled'] = isset($output['google_drive_enabled']) ? 1 : 0;
        $output['google_drive_auto_export'] = isset($output['google_drive_auto_export']) ? 1 : 0;
        $output['google_contacts_enabled'] = isset($output['google_contacts_enabled']) ? 1 : 0;
        // Google Calendar color (1-11 or empty)
        $output['google_calendar_color'] = isset($output['google_calendar_color']) ? sanitize_text_field($output['google_calendar_color']) : '';
        // Keep business_country empty if not provided to allow inference from Locations
        $bc = strtoupper(sanitize_text_field($output['business_country'] ?? ''));
        $output['business_country'] = preg_match('/^[A-Z]{2}$/', $bc) ? $bc : '';
        $output['qb_item_per_class_enable'] = isset($output['qb_item_per_class_enable']) ? 1 : 0;
        $output['delete_on_uninstall'] = isset($output['delete_on_uninstall']) ? 1 : 0;
        $output['quickbooks_environment'] = in_array(($output['quickbooks_environment'] ?? 'production'), ['production','sandbox'], true) ? $output['quickbooks_environment'] : 'production';
        return $output;
    }

    // Currency setting removed: plugin uses USD consistently

    public static function field_text(array $args): void
    {
        $key = $args['key'];
        $settings = get_option('cfp_settings', []);
        $value = esc_attr($settings[$key] ?? '');
        echo '<input type="text" name="cfp_settings[' . esc_attr($key) . ']" value="' . $value . '" class="regular-text" autocomplete="off" />';
        self::maybe_help($args);
    }

    public static function field_password(array $args): void
    {
        $key = $args['key'];
        $settings = get_option('cfp_settings', []);
        $value = esc_attr($settings[$key] ?? '');
        echo '<input type="password" name="cfp_settings[' . esc_attr($key) . ']" value="' . $value . '" class="regular-text" autocomplete="off" />';
        self::maybe_help($args);
    }

    public static function field_checkbox(array $args): void
    {
        $key = $args['key'];
        $settings = get_option('cfp_settings', []);
        $checked = !empty($settings[$key]) ? 'checked' : '';
        echo '<label><input type="checkbox" name="cfp_settings[' . esc_attr($key) . ']" value="1" ' . $checked . ' /> ' . esc_html__('Enabled', 'classflow-pro') . '</label>';
        self::maybe_help($args);
    }

    public static function field_number(array $args): void
    {
        $key = $args['key'];
        $step = isset($args['step']) ? esc_attr($args['step']) : '1';
        $settings = get_option('cfp_settings', []);
        $value = esc_attr($settings[$key] ?? '0');
        echo '<input type="number" name="cfp_settings[' . esc_attr($key) . ']" value="' . $value . '" step="' . $step . '" class="small-text" />';
        self::maybe_help($args);
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
        self::maybe_help($args);
    }

    public static function field_textarea(array $args): void
    {
        $key = $args['key'];
        $settings = get_option('cfp_settings', []);
        $value = esc_textarea($settings[$key] ?? '');
        echo '<textarea name="cfp_settings[' . esc_attr($key) . ']" rows="6" class="large-text code">' . $value . '</textarea>';
        echo '<p class="description">' . esc_html__('Placeholders: {class_title}, {start_time}, {old_start_time}, {amount}, {status}', 'classflow-pro') . '</p>';
        self::maybe_help($args);
    }

    private static function maybe_help(array $args): void
    {
        if (!empty($args['help'])) {
            echo ' <span class="cfp-help" title="' . esc_attr($args['help']) . '"><span class="dashicons dashicons-info-outline"></span></span>';
        }
    }

    public static function get(string $key, $default = null)
    {
        $settings = get_option('cfp_settings', []);
        $val = $settings[$key] ?? null;
        if (($key === 'business_country' || $key === 'business_timezone') && (empty($val))) {
            // Try to infer from saved Locations if not explicitly set
            $inferred = ($key === 'business_country') ? self::infer_business_country() : self::infer_business_timezone();
            if (!empty($inferred)) {
                return $inferred;
            }
        }
        return $val ?? $default;
    }

    private static function infer_business_country(): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_locations';
        // Prefer the most frequent non-empty country among locations
        $row = $wpdb->get_row("SELECT country, COUNT(*) as cnt FROM $table WHERE country IS NOT NULL AND country <> '' GROUP BY country ORDER BY cnt DESC LIMIT 1", ARRAY_A);
        $country = strtoupper((string)($row['country'] ?? ''));
        return preg_match('/^[A-Z]{2}$/', $country) ? $country : 'US';
    }

    private static function infer_business_timezone(): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_locations';
        // Prefer the most frequent non-empty timezone among locations
        $row = $wpdb->get_row("SELECT timezone, COUNT(*) as cnt FROM $table WHERE timezone IS NOT NULL AND timezone <> '' GROUP BY timezone ORDER BY cnt DESC LIMIT 1", ARRAY_A);
        $tz = (string)($row['timezone'] ?? '');
        if (strpos($tz, '/') !== false) {
            return $tz;
        }
        // Fall back to the WordPress site timezone from Settings → General
        if (function_exists('wp_timezone_string')) {
            $siteTz = (string) wp_timezone_string();
        } else {
            $siteTz = (string) get_option('timezone_string');
        }
        return $siteTz ?: 'UTC';
    }
}
