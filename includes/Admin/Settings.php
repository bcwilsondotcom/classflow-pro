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
        add_submenu_page('classflow-pro', __('Classes', 'classflow-pro'), __('Classes', 'classflow-pro'), 'cfp_manage_schedules', 'classflow-pro-classes', ['ClassFlowPro\\Admin\\Classes', 'render']);
        // Custom entity admin pages (non-CPT)
        add_submenu_page('classflow-pro', __('Instructors', 'classflow-pro'), __('Instructors', 'classflow-pro'), 'cfp_manage_schedules', 'classflow-pro-instructors', ['ClassFlowPro\\Admin\\Instructors', 'render']);
        add_submenu_page('classflow-pro', __('Locations', 'classflow-pro'), __('Locations', 'classflow-pro'), 'cfp_manage_schedules', 'classflow-pro-locations', ['ClassFlowPro\\Admin\\Locations', 'render']);
        add_submenu_page('classflow-pro', __('Resources', 'classflow-pro'), __('Resources', 'classflow-pro'), 'cfp_manage_schedules', 'classflow-pro-resources', ['ClassFlowPro\\Admin\\Resources', 'render']);
        // New full-screen Schedules calendar
        add_submenu_page('classflow-pro', __('Schedules', 'classflow-pro'), __('Schedules', 'classflow-pro'), 'cfp_manage_schedules', 'classflow-pro-schedules', ['ClassFlowPro\\Admin\\Schedules', 'render']);
        add_submenu_page('classflow-pro', __('Series', 'classflow-pro'), __('Series', 'classflow-pro'), 'cfp_manage_schedules', 'classflow-pro-series', ['ClassFlowPro\\Admin\\Series', 'render']);
        add_submenu_page('classflow-pro', __('Bookings', 'classflow-pro'), __('Bookings', 'classflow-pro'), 'cfp_manage_bookings', 'classflow-pro-bookings', ['ClassFlowPro\\Admin\\Bookings', 'render']);
        add_submenu_page('classflow-pro', __('Rosters', 'classflow-pro'), __('Rosters', 'classflow-pro'), 'manage_options', 'classflow-pro-rosters', ['ClassFlowPro\\Admin\\Rosters', 'render']);
        // Coupons removed: no submenu
        add_submenu_page('classflow-pro', __('QuickBooks Tools', 'classflow-pro'), __('QuickBooks Tools', 'classflow-pro'), 'cfp_view_reports', 'classflow-pro-qbtools', ['ClassFlowPro\\Admin\\QuickBooksTools', 'render']);
        // Schedules are now managed within Classes
        add_submenu_page('classflow-pro', __('Private Requests', 'classflow-pro'), __('Private Requests', 'classflow-pro'), 'cfp_manage_bookings', 'classflow-pro-privreq', ['ClassFlowPro\\Admin\\PrivateRequests', 'render']);
        add_submenu_page('classflow-pro', __('Settings', 'classflow-pro'), __('Settings', 'classflow-pro'), 'manage_options', 'classflow-pro-settings', [self::class, 'render_settings_page']);
        add_submenu_page('classflow-pro', __('Logs', 'classflow-pro'), __('Logs', 'classflow-pro'), 'manage_options', 'classflow-pro-logs', ['ClassFlowPro\\Admin\\Logs', 'render']);
        add_submenu_page('classflow-pro', __('Reports', 'classflow-pro'), __('Reports', 'classflow-pro'), 'cfp_view_reports', 'classflow-pro-reports', ['ClassFlowPro\\Admin\\Reports', 'render']);
        add_submenu_page('classflow-pro', __('Memberships', 'classflow-pro'), __('Memberships', 'classflow-pro'), 'cfp_manage_memberships', 'classflow-pro-memberships', ['ClassFlowPro\\Admin\\Memberships', 'render']);
        add_submenu_page('classflow-pro', __('Payouts', 'classflow-pro'), __('Payouts', 'classflow-pro'), 'manage_options', 'classflow-pro-payouts', ['ClassFlowPro\\Admin\\Payouts', 'render']);
        add_submenu_page('classflow-pro', __('Customers', 'classflow-pro'), __('Customers', 'classflow-pro'), 'cfp_manage_customers', 'classflow-pro-customers', ['ClassFlowPro\\Admin\\Customers', 'render']);
        add_submenu_page('classflow-pro', __('Import', 'classflow-pro'), __('Import', 'classflow-pro'), 'manage_options', 'classflow-pro-import', ['ClassFlowPro\\Admin\\Import', 'render']);
        add_submenu_page('classflow-pro', __('System', 'classflow-pro'), __('System', 'classflow-pro'), 'manage_options', 'classflow-pro-system', ['ClassFlowPro\\Admin\\System', 'render']);
        add_submenu_page('classflow-pro', __('Gift Cards', 'classflow-pro'), __('Gift Cards', 'classflow-pro'), 'cfp_manage_customers', 'classflow-pro-giftcards', ['ClassFlowPro\\Admin\\GiftCards', 'render']);
        add_submenu_page('classflow-pro', __('Intake Forms', 'classflow-pro'), __('Intake Forms', 'classflow-pro'), 'cfp_manage_customers', 'classflow-pro-intake', ['ClassFlowPro\\Admin\\IntakeForms', 'render']);
    }

    public static function register_settings(): void
    {
        register_setting('cfp_settings_group', 'cfp_settings', [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_settings'],
            'default' => [],
        ]);

        // General Settings
        add_settings_section('cfp_general', __('Business Settings', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Core business configuration and operational settings.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        add_settings_field('delete_on_uninstall', __('Delete Data on Uninstall', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_general', ['key' => 'delete_on_uninstall', 'help' => __('Remove all plugin data when uninstalling. This action is irreversible.', 'classflow-pro')]);

        // Booking & Policies Settings
        add_settings_section('cfp_booking', __('Booking Rules', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure booking requirements and customer access.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        add_settings_field('require_login_to_book', __('Require Login', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_booking', ['key' => 'require_login_to_book', 'help' => __('Customers must sign in or create an account before booking.', 'classflow-pro')]);
        add_settings_field('auto_create_user_on_booking', __('Auto-Create Accounts', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_booking', ['key' => 'auto_create_user_on_booking', 'help' => __('Automatically create WordPress user accounts for new customers.', 'classflow-pro')]);
        
        // Intake Forms
        add_settings_section('cfp_intake', __('Intake Forms', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Collect important customer information before their first visit.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        add_settings_field('require_intake', __('Require Intake Form', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_intake', ['key' => 'require_intake', 'help' => __('New customers must complete an intake form before their first class.', 'classflow-pro')]);
        add_settings_field('intake_page_url', __('Intake Form Page', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_intake', ['key' => 'intake_page_url', 'help' => __('URL of the page containing the [classflow_intake] shortcode.', 'classflow-pro')]);
        
        // Waitlist Settings
        add_settings_section('cfp_waitlist', __('Waitlist Management', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Manage waitlist behavior when spots become available.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        add_settings_field('waitlist_response_page_url', __('Waitlist Response Page', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_waitlist', ['key' => 'waitlist_response_page_url', 'help' => __('Page where customers confirm or decline waitlist offers.', 'classflow-pro')]);
        add_settings_field('waitlist_hold_minutes', __('Hold Time', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_waitlist', ['key' => 'waitlist_hold_minutes', 'step' => '5', 'help' => __('Minutes to hold a spot for waitlisted customer before offering to next person.', 'classflow-pro')]);
        
        // Cancellation & Refund Policies
        add_settings_section('cfp_policies', __('Cancellation & Refund Policies', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure cancellation windows, refund policies, and fees for late cancellations or no-shows.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        // Time Windows
        add_settings_field('cancellation_window_hours', __('Cancellation Window', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_policies', ['key' => 'cancellation_window_hours', 'step' => '1', 'help' => __('Hours before class start when cancellations are allowed without penalty.', 'classflow-pro')]);
        add_settings_field('reschedule_window_hours', __('Reschedule Window', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_policies', ['key' => 'reschedule_window_hours', 'step' => '1', 'help' => __('Hours before class start when rescheduling is allowed.', 'classflow-pro')]);
        
        // Policy Configuration
        add_settings_field('cancellation_policy_enabled', __('Enable Policy', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_policies', ['key' => 'cancellation_policy_enabled', 'help' => __('Enforce cancellation policies for all bookings.', 'classflow-pro')]);
        add_settings_field('cancellation_policy_type', __('Policy Type', 'classflow-pro'), [self::class, 'field_select'], 'classflow-pro', 'cfp_policies', ['key' => 'cancellation_policy_type', 'choices' => [
            'flexible' => __('Flexible - Full refund within window', 'classflow-pro'),
            'moderate' => __('Moderate - 50% refund for late cancellations', 'classflow-pro'),
            'strict' => __('Strict - No refund for late cancellations', 'classflow-pro'),
            'custom' => __('Custom - Configure below', 'classflow-pro'),
        ]]);
        
        // Refund Configuration
        add_settings_field('refund_policy_enabled', __('Enable Refunds', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_policies', ['key' => 'refund_policy_enabled', 'help' => __('Allow refund processing for eligible cancellations.', 'classflow-pro')]);
        add_settings_field('refund_processing_type', __('Refund Processing', 'classflow-pro'), [self::class, 'field_select'], 'classflow-pro', 'cfp_policies', ['key' => 'refund_processing_type', 'choices' => [
            'automatic' => __('Automatic - Process immediately', 'classflow-pro'),
            'manual' => __('Manual - Require admin approval', 'classflow-pro'),
            'credit_only' => __('Credits Only - No monetary refunds', 'classflow-pro'),
        ]]);
        add_settings_field('refund_percentage', __('Refund Amount', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_policies', ['key' => 'refund_percentage', 'step' => '5', 'min' => '0', 'max' => '100', 'help' => __('Percentage of payment to refund for valid cancellations (0-100%).', 'classflow-pro')]);
        add_settings_field('refund_processing_fee', __('Retain Processing Fees', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_policies', ['key' => 'refund_processing_fee', 'help' => __('Keep payment processing fees when issuing refunds.', 'classflow-pro')]);
        
        // Late Cancel & No-Show Fees
        add_settings_field('late_cancel_fee_cents', __('Late Cancel Fee', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_policies', ['key' => 'late_cancel_fee_cents', 'step' => '50', 'help' => __('Fee in cents for cancellations outside the window (e.g., 1000 = $10).', 'classflow-pro')]);
        add_settings_field('late_cancel_deduct_credit', __('Deduct Credit (Late)', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_policies', ['key' => 'late_cancel_deduct_credit', 'help' => __('Deduct one credit for late cancellations.', 'classflow-pro')]);
        add_settings_field('no_show_fee_cents', __('No-Show Fee', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_policies', ['key' => 'no_show_fee_cents', 'step' => '50', 'help' => __('Fee in cents when customer doesn\'t attend (e.g., 2000 = $20).', 'classflow-pro')]);
        add_settings_field('no_show_deduct_credit', __('Deduct Credit (No-Show)', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_policies', ['key' => 'no_show_deduct_credit', 'help' => __('Deduct one credit for no-shows.', 'classflow-pro')]);

        // Communications - Email Settings
        add_settings_section('cfp_email', __('Email Notifications', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure email notifications for customers, instructors, and administrators.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        add_settings_field('notify_customer', __('Customer Emails', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_email', ['key' => 'notify_customer', 'help' => __('Send booking confirmations and updates to customers.', 'classflow-pro')]);
        add_settings_field('notify_instructor', __('Instructor Emails', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_email', ['key' => 'notify_instructor', 'help' => __('Send class updates and rosters to instructors.', 'classflow-pro')]);
        add_settings_field('notify_admin', __('Admin Emails', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_email', ['key' => 'notify_admin', 'help' => __('Send booking notifications to site administrators.', 'classflow-pro')]);
        add_settings_field('reminder_hours_before', __('Reminder Schedule', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_email', ['key' => 'reminder_hours_before', 'help' => __('Hours before class to send reminders (comma-separated, e.g., "24,2").', 'classflow-pro')]);
        
        // Email Templates
        add_settings_section('cfp_email_templates', __('Email Templates', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Customize email templates for different booking events.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        add_settings_field('template_confirmed_subject', __('Confirmation Subject', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_email_templates', ['key' => 'template_confirmed_subject']);
        add_settings_field('template_confirmed_body', __('Confirmation Body', 'classflow-pro'), [self::class, 'field_textarea'], 'classflow-pro', 'cfp_email_templates', ['key' => 'template_confirmed_body']);
        add_settings_field('template_canceled_subject', __('Cancellation Subject', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_email_templates', ['key' => 'template_canceled_subject']);
        add_settings_field('template_canceled_body', __('Cancellation Body', 'classflow-pro'), [self::class, 'field_textarea'], 'classflow-pro', 'cfp_email_templates', ['key' => 'template_canceled_body']);
        add_settings_field('template_rescheduled_subject', __('Reschedule Subject', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_email_templates', ['key' => 'template_rescheduled_subject']);
        add_settings_field('template_rescheduled_body', __('Reschedule Body', 'classflow-pro'), [self::class, 'field_textarea'], 'classflow-pro', 'cfp_email_templates', ['key' => 'template_rescheduled_body']);
        add_settings_field('template_giftcard_subject', __('Gift Card Subject', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_email_templates', ['key' => 'template_giftcard_subject', 'help' => __('Available: {site}', 'classflow-pro')]);
        add_settings_field('template_giftcard_body', __('Gift Card Body', 'classflow-pro'), [self::class, 'field_textarea'], 'classflow-pro', 'cfp_email_templates', ['key' => 'template_giftcard_body', 'help' => __('Available: {site}, {code}, {credits}, {amount}, {recipient_email}, {purchaser_email}, {redeem_url}', 'classflow-pro')]);
        add_settings_field('giftcard_bcc_admin', __('BCC Gift Cards', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_email_templates', ['key' => 'giftcard_bcc_admin', 'help' => __('Send copy of gift card emails to admin.', 'classflow-pro')]);
        
        // SMS Settings
        add_settings_section('cfp_sms', __('SMS Notifications', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure SMS text messaging through Twilio.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        add_settings_field('notify_sms_customer', __('Customer SMS', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_sms', ['key' => 'notify_sms_customer', 'help' => __('Send SMS confirmations and reminders to customers.', 'classflow-pro')]);
        add_settings_field('notify_sms_instructor', __('Instructor SMS', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_sms', ['key' => 'notify_sms_instructor', 'help' => __('Send SMS notifications to instructors.', 'classflow-pro')]);
        add_settings_field('twilio_account_sid', __('Account SID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_sms', ['key' => 'twilio_account_sid', 'help' => __('Found in Twilio Console > Account > API Keys.', 'classflow-pro')]);
        add_settings_field('twilio_auth_token', __('Auth Token', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_sms', ['key' => 'twilio_auth_token', 'help' => __('Your Twilio account auth token.', 'classflow-pro')]);
        add_settings_field('twilio_from_number', __('From Number', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_sms', ['key' => 'twilio_from_number', 'help' => __('Your Twilio phone number (E.164 format: +15551234567).', 'classflow-pro')]);

        // Payments - Stripe
        add_settings_section('cfp_stripe', __('Stripe Payments', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure Stripe for payment processing. Webhook endpoint: ', 'classflow-pro') . '<code>' . esc_url(site_url('/wp-json/classflow/v1/stripe/webhook')) . '</code></p>';
        }, 'classflow-pro');
        
        add_settings_field('stripe_publishable_key', __('Publishable Key', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_publishable_key', 'help' => __('Starts with pk_live_ or pk_test_', 'classflow-pro')]);
        add_settings_field('stripe_secret_key', __('Secret Key', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_secret_key', 'help' => __('Starts with sk_live_ or sk_test_', 'classflow-pro')]);
        add_settings_field('stripe_webhook_secret', __('Webhook Secret', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_webhook_secret', 'help' => __('Webhook endpoint secret (whsec_...)', 'classflow-pro')]);
        add_settings_field('stripe_enable_tax', __('Enable Tax', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_enable_tax', 'help' => __('Let Stripe automatically calculate and collect taxes.', 'classflow-pro')]);
        add_settings_field('stripe_connect_enabled', __('Enable Connect', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_connect_enabled', 'help' => __('Split payments between platform and connected accounts.', 'classflow-pro')]);
        add_settings_field('platform_fee_percent', __('Platform Fee', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_stripe', ['key' => 'platform_fee_percent', 'step' => '0.1', 'help' => __('Percentage fee for platform (Connect only).', 'classflow-pro')]);
        add_settings_field('stripe_allow_promo_codes', __('Promo Codes', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_stripe', ['key' => 'stripe_allow_promo_codes', 'help' => __('Allow customers to enter Stripe promotion codes.', 'classflow-pro')]);
        add_settings_field('checkout_success_url', __('Success URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_stripe', ['key' => 'checkout_success_url', 'help' => __('Redirect after successful payment.', 'classflow-pro')]);
        add_settings_field('checkout_cancel_url', __('Cancel URL', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_stripe', ['key' => 'checkout_cancel_url', 'help' => __('Redirect if checkout is cancelled.', 'classflow-pro')]);
        
        // Gift Cards
        add_settings_section('cfp_giftcards', __('Gift Cards', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Configure gift card pricing and limits.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        add_settings_field('giftcard_credit_value_cents', __('Credit Price', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_giftcards', ['key' => 'giftcard_credit_value_cents', 'step' => '50', 'help' => __('Price per credit in cents (1500 = $15/credit).', 'classflow-pro')]);
        add_settings_field('giftcard_min_credits', __('Minimum Credits', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_giftcards', ['key' => 'giftcard_min_credits', 'step' => '1', 'help' => __('Minimum credits per purchase.', 'classflow-pro')]);
        add_settings_field('giftcard_max_credits', __('Maximum Credits', 'classflow-pro'), [self::class, 'field_number'], 'classflow-pro', 'cfp_giftcards', ['key' => 'giftcard_max_credits', 'step' => '1', 'help' => __('Maximum credits per purchase.', 'classflow-pro')]);

        // Integrations - QuickBooks
        add_settings_section('cfp_quickbooks', __('QuickBooks Online', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Sync sales data with QuickBooks Online.', 'classflow-pro') . '</p>';
        }, 'classflow-pro');
        
        add_settings_field('quickbooks_environment', __('Environment', 'classflow-pro'), [self::class, 'field_select'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_environment', 'choices' => [
            'production' => __('Production', 'classflow-pro'),
            'sandbox' => __('Sandbox', 'classflow-pro'),
        ]]);
        add_settings_field('quickbooks_client_id', __('Client ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_client_id']);
        add_settings_field('quickbooks_client_secret', __('Client Secret', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_client_secret']);
        add_settings_field('quickbooks_realm_id', __('Realm ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_realm_id']);
        add_settings_field('quickbooks_redirect_uri', __('Redirect URI', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'quickbooks_redirect_uri']);
        add_settings_field('qb_item_per_class_enable', __('Items per Class', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_item_per_class_enable']);
        add_settings_field('qb_item_per_instructor_enable', __('Items per Instructor', 'classflow-pro'), [self::class, 'field_checkbox'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_item_per_instructor_enable']);
        add_settings_field('qb_item_prefix', __('Item Prefix', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_item_prefix']);
        add_settings_field('qb_default_item_name', __('Default Item', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_default_item_name']);
        add_settings_field('qb_income_account_ref', __('Income Account', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_income_account_ref']);
        add_settings_field('qb_tax_code_ref', __('Tax Code', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_quickbooks', ['key' => 'qb_tax_code_ref']);

        // Google Workspace Integration
        self::register_google_settings();
        
        // Zoom Integration
        self::register_zoom_settings();
    }
    
    private static function register_google_settings(): void
    {
        add_settings_section('cfp_google', __('Google Workspace', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Connect with Google services for enhanced functionality.', 'classflow-pro') . '</p>';
            echo '<div class="cfp-info-box">';
            echo '<strong>' . esc_html__('Setup:', 'classflow-pro') . '</strong> ';
            echo esc_html__('Create OAuth credentials in Google Cloud Console with redirect URI: ', 'classflow-pro');
            echo '<code>' . esc_url(site_url('/wp-json/classflow/v1/google/callback')) . '</code>';
            echo '</div>';
        }, 'classflow-pro');
        
        // OAuth Configuration
        add_settings_field('google_client_id', __('Client ID', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_client_id']);
        add_settings_field('google_client_secret', __('Client Secret', 'classflow-pro'), [self::class, 'field_password'], 'classflow-pro', 'cfp_google', ['key' => 'google_client_secret']);
        add_settings_field('google_redirect_uri', __('Redirect URI', 'classflow-pro'), [self::class, 'field_text'], 'classflow-pro', 'cfp_google', ['key' => 'google_redirect_uri']);
        
        // Individual service settings with feature toggles
        add_settings_field('google_calendar_enabled', __('Calendar Sync', 'classflow-pro'), [self::class, 'field_feature_toggle'], 'classflow-pro', 'cfp_google', [
            'key' => 'google_calendar_enabled',
            'help' => __('Sync schedules with Google Calendar', 'classflow-pro'),
            'settings' => [
                ['key' => 'google_calendar_id', 'label' => __('Calendar ID', 'classflow-pro'), 'type' => 'text'],
                ['key' => 'google_calendar_sync_bookings', 'label' => __('Sync Individual Bookings', 'classflow-pro'), 'type' => 'checkbox'],
                ['key' => 'google_calendar_color', 'label' => __('Event Color', 'classflow-pro'), 'type' => 'select', 'choices' => [
                    '' => __('Default', 'classflow-pro'),
                    '1' => __('Lavender', 'classflow-pro'),
                    '2' => __('Sage', 'classflow-pro'),
                    '3' => __('Grape', 'classflow-pro'),
                    '4' => __('Flamingo', 'classflow-pro'),
                ]],
            ]
        ]);
        
        add_settings_field('gmail_enabled', __('Gmail Integration', 'classflow-pro'), [self::class, 'field_feature_toggle'], 'classflow-pro', 'cfp_google', [
            'key' => 'gmail_enabled',
            'help' => __('Send emails via Gmail API', 'classflow-pro'),
            'settings' => [
                ['key' => 'gmail_sender_email', 'label' => __('Sender Email', 'classflow-pro'), 'type' => 'text'],
                ['key' => 'gmail_sender_name', 'label' => __('Sender Name', 'classflow-pro'), 'type' => 'text'],
                ['key' => 'gmail_track_opens', 'label' => __('Track Opens', 'classflow-pro'), 'type' => 'checkbox'],
            ]
        ]);
        
        add_settings_field('google_meet_enabled', __('Google Meet', 'classflow-pro'), [self::class, 'field_feature_toggle'], 'classflow-pro', 'cfp_google', [
            'key' => 'google_meet_enabled',
            'help' => __('Create Meet links for virtual classes', 'classflow-pro'),
            'settings' => [
                ['key' => 'google_meet_auto_create', 'label' => __('Auto-create for virtual locations', 'classflow-pro'), 'type' => 'checkbox'],
            ]
        ]);
        
        add_settings_field('google_drive_enabled', __('Google Drive', 'classflow-pro'), [self::class, 'field_feature_toggle'], 'classflow-pro', 'cfp_google', [
            'key' => 'google_drive_enabled',
            'help' => __('Backup data to Google Drive', 'classflow-pro'),
            'settings' => [
                ['key' => 'google_drive_folder_id', 'label' => __('Folder ID', 'classflow-pro'), 'type' => 'text'],
                ['key' => 'google_drive_auto_export', 'label' => __('Auto-export reports', 'classflow-pro'), 'type' => 'checkbox'],
            ]
        ]);
        
        add_settings_field('google_contacts_enabled', __('Google Contacts', 'classflow-pro'), [self::class, 'field_feature_toggle'], 'classflow-pro', 'cfp_google', [
            'key' => 'google_contacts_enabled',
            'help' => __('Sync customer data', 'classflow-pro'),
            'settings' => [
                ['key' => 'google_contacts_group', 'label' => __('Contact Group', 'classflow-pro'), 'type' => 'text'],
            ]
        ]);
        
        // Connection Status
        add_settings_field('google_connection_status', __('Status', 'classflow-pro'), function() {
            $token = get_option('cfp_google_token');
            echo '<div class="cfp-connection-status">';
            if ($token && !empty($token['access_token'])) {
                echo '<span class="cfp-status-badge connected">✓ ' . esc_html__('Connected', 'classflow-pro') . '</span>';
                echo ' <a href="' . esc_url(site_url('/wp-json/classflow/v1/google/disconnect')) . '" class="button button-small">' . esc_html__('Disconnect', 'classflow-pro') . '</a>';
            } else {
                echo '<span class="cfp-status-badge disconnected">✗ ' . esc_html__('Not Connected', 'classflow-pro') . '</span>';
                echo ' <a href="' . esc_url(site_url('/wp-json/classflow/v1/google/connect')) . '" class="button button-primary">' . esc_html__('Connect', 'classflow-pro') . '</a>';
            }
            echo '</div>';
        }, 'classflow-pro', 'cfp_google');
    }
    
    private static function register_zoom_settings(): void
    {
        add_settings_section('cfp_zoom', __('Zoom Integration', 'classflow-pro'), function () {
            echo '<p>' . esc_html__('Create Zoom meetings for virtual classes.', 'classflow-pro') . '</p>';
            echo '<div class="cfp-info-box">';
            echo '<strong>' . esc_html__('Setup:', 'classflow-pro') . '</strong> ';
            echo esc_html__('Create Server-to-Server OAuth app in Zoom Marketplace.', 'classflow-pro');
            echo '</div>';
        }, 'classflow-pro');
        
        add_settings_field('zoom_enabled', __('Enable Zoom', 'classflow-pro'), [self::class, 'field_feature_toggle'], 'classflow-pro', 'cfp_zoom', [
            'key' => 'zoom_enabled',
            'help' => __('Create meetings for virtual classes', 'classflow-pro'),
            'settings' => [
                ['key' => 'zoom_account_id', 'label' => __('Account ID', 'classflow-pro'), 'type' => 'text'],
                ['key' => 'zoom_client_id', 'label' => __('Client ID', 'classflow-pro'), 'type' => 'text'],
                ['key' => 'zoom_client_secret', 'label' => __('Client Secret', 'classflow-pro'), 'type' => 'password'],
                ['key' => 'zoom_auto_create', 'label' => __('Auto-create for virtual locations', 'classflow-pro'), 'type' => 'checkbox'],
                ['key' => 'zoom_join_before_minutes', 'label' => __('Join before host (minutes)', 'classflow-pro'), 'type' => 'number', 'step' => '1'],
                ['key' => 'zoom_waiting_room', 'label' => __('Enable waiting room', 'classflow-pro'), 'type' => 'checkbox'],
                ['key' => 'zoom_mute_on_entry', 'label' => __('Mute on entry', 'classflow-pro'), 'type' => 'checkbox'],
                ['key' => 'zoom_auto_recording', 'label' => __('Recording', 'classflow-pro'), 'type' => 'select', 'choices' => [
                    'none' => __('No Recording', 'classflow-pro'),
                    'local' => __('Local Recording', 'classflow-pro'),
                    'cloud' => __('Cloud Recording', 'classflow-pro'),
                ]],
            ]
        ]);
        
        // Connection Test
        add_settings_field('zoom_connection_status', __('Status', 'classflow-pro'), function() {
            $account_id = Settings::get('zoom_account_id');
            $client_id = Settings::get('zoom_client_id');
            $client_secret = Settings::get('zoom_client_secret');
            
            echo '<div class="cfp-connection-status">';
            if ($account_id && $client_id && $client_secret) {
                echo '<span class="cfp-status-badge connected">✓ ' . esc_html__('Configured', 'classflow-pro') . '</span>';
                echo ' <button type="button" class="button button-small" id="test-zoom">'. esc_html__('Test', 'classflow-pro') . '</button>';
                echo '<span id="zoom-test-result" style="margin-left:10px;"></span>';
            } else {
                echo '<span class="cfp-status-badge disconnected">⚠ ' . esc_html__('Not Configured', 'classflow-pro') . '</span>';
            }
            echo '</div>';
        }, 'classflow-pro', 'cfp_zoom');
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Enqueue admin styles
        wp_enqueue_style('cfp-admin', CFP_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.' . time());
        
        $tabs = [
            'general' => ['icon' => 'dashicons-admin-generic', 'label' => __('General', 'classflow-pro')],
            'booking' => ['icon' => 'dashicons-calendar-alt', 'label' => __('Booking & Policies', 'classflow-pro')],
            'communications' => ['icon' => 'dashicons-email', 'label' => __('Communications', 'classflow-pro')],
            'payments' => ['icon' => 'dashicons-cart', 'label' => __('Payments', 'classflow-pro')],
            'integrations' => ['icon' => 'dashicons-admin-plugins', 'label' => __('Integrations', 'classflow-pro')],
        ];
        
        $active = isset($_GET['tab']) ? sanitize_key((string)$_GET['tab']) : 'general';
        if (!isset($tabs[$active])) { $active = 'general'; }

        echo '<div class="cfp-settings-wrap">';
        
        // Header
        echo '<div class="cfp-settings-header">';
        echo '<h1>' . esc_html__('ClassFlow Pro Settings', 'classflow-pro') . '</h1>';
        echo '<p>' . esc_html__('Configure your ClassFlow Pro installation to match your business needs', 'classflow-pro') . '</p>';
        echo '</div>';
        
        // Tabs
        echo '<div class="cfp-settings-tabs">';
        foreach ($tabs as $slug => $tab) {
            $url = esc_url(add_query_arg(['page' => 'classflow-pro-settings', 'tab' => $slug], admin_url('admin.php')));
            $class = $active === $slug ? 'active' : '';
            echo '<a class="' . esc_attr($class) . '" href="' . $url . '">';
            echo '<span class="dashicons ' . esc_attr($tab['icon']) . '"></span> ';
            echo esc_html($tab['label']);
            echo '</a>';
        }
        echo '</div>';

        echo '<form method="post" action="options.php" id="cfp-settings-form">';
        settings_fields('cfp_settings_group');
        echo '<div class="cfp-settings-content">';
        self::render_sections_for_tab($active);
        echo '</div>';
        echo '<div class="submit">';
        submit_button(__('Save Settings', 'classflow-pro'), 'primary', 'submit', false);
        echo '</div>';
        echo '</form>';
        
        // Add JavaScript for collapsible sections and feature toggles
        self::render_settings_javascript();
        
        echo '</div>';
    }
    
    private static function render_settings_javascript(): void
    {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Collapsible sections
            $('.cfp-settings-section-header').on('click', function() {
                var $section = $(this).parent();
                var $toggle = $(this).find('.cfp-settings-toggle');
                
                $section.toggleClass('active');
                $toggle.toggleClass('active');
            });
            
            // Feature toggles
            $('.cfp-feature-toggle input[type="checkbox"]').on('change', function() {
                var $settings = $(this).closest('.cfp-feature-container').find('.cfp-feature-settings');
                if ($(this).is(':checked')) {
                    $settings.addClass('active').slideDown(200);
                } else {
                    $settings.removeClass('active').slideUp(200);
                }
            }).trigger('change');
            
            // Zoom connection test
            $('#test-zoom').on('click', function() {
                var $result = $('#zoom-test-result');
                $result.html('<span style="color:#666;">Testing...</span>');
                
                $.get('<?php echo esc_url(rest_url('classflow/v1/zoom/test')); ?>', {
                    _wpnonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
                })
                .done(function(data) {
                    if (data.success) {
                        $result.html('<span style="color:green;">✓ ' + data.message + '</span>');
                    } else {
                        $result.html('<span style="color:red;">✗ ' + data.message + '</span>');
                    }
                })
                .fail(function() {
                    $result.html('<span style="color:red;">✗ Connection test failed</span>');
                });
            });
            
            // Open first section by default
            $('.cfp-settings-section:first').addClass('active');
            $('.cfp-settings-section:first .cfp-settings-toggle').addClass('active');
        });
        </script>
        <?php
    }

    private static function render_sections_for_tab(string $tab): void
    {
        // Map tabs to section IDs
        $page = 'classflow-pro';
        $map = [
            'general' => ['cfp_general'],
            'booking' => ['cfp_booking', 'cfp_intake', 'cfp_waitlist', 'cfp_policies'],
            'communications' => ['cfp_email', 'cfp_email_templates', 'cfp_sms'],
            'payments' => ['cfp_stripe', 'cfp_giftcards'],
            'integrations' => ['cfp_quickbooks', 'cfp_google', 'cfp_zoom'],
        ];
        
        if (empty($map[$tab])) return;
        
        global $wp_settings_sections, $wp_settings_fields;
        
        foreach ($map[$tab] as $section_id) {
            if (!isset($wp_settings_sections[$page][$section_id])) continue;
            
            $section = $wp_settings_sections[$page][$section_id];
            
            echo '<div class="cfp-settings-section">';
            echo '<div class="cfp-settings-section-header">';
            echo '<h2>' . esc_html($section['title']) . '</h2>';
            echo '<span class="cfp-settings-toggle"><span class="dashicons dashicons-arrow-down"></span></span>';
            echo '</div>';
            echo '<div class="cfp-settings-section-body">';
            
            if (!empty($section['callback'])) {
                call_user_func($section['callback']);
            }
            
            if (isset($wp_settings_fields[$page][$section_id])) {
                echo '<table class="form-table" role="presentation">';
                do_settings_fields($page, $section_id);
                echo '</table>';
            }
            
            echo '</div>';
            echo '</div>';
        }
    }

    public static function sanitize_settings($input): array
    {
        $defaults = get_option('cfp_settings', []);
        $output = is_array($input) ? $input : [];
        foreach ([
            'stripe_publishable_key','stripe_secret_key','stripe_webhook_secret','quickbooks_client_id','quickbooks_client_secret','quickbooks_realm_id','quickbooks_redirect_uri',
            'template_confirmed_subject','template_canceled_subject','template_rescheduled_subject','template_giftcard_subject',
            'google_client_id','google_client_secret','google_calendar_id','google_redirect_uri',
            'gmail_sender_email','gmail_sender_name','google_drive_folder_id','google_contacts_group',
            'zoom_account_id','zoom_client_id','zoom_client_secret',
            'qb_item_prefix','qb_default_item_name','qb_income_account_ref','qb_tax_code_ref'
        ] as $k) {
            if (isset($output[$k])) {
                $output[$k] = trim(wp_unslash($output[$k]));
            }
        }
        foreach ([
            'template_confirmed_body','template_canceled_body','template_rescheduled_body','template_giftcard_body'
        ] as $k) {
            if (isset($output[$k])) {
                $output[$k] = wp_kses_post(wp_unslash($output[$k]));
            }
        }
        $output['giftcard_bcc_admin'] = isset($output['giftcard_bcc_admin']) ? 1 : 0;
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
        // Policies
        $output['late_cancel_fee_cents'] = isset($output['late_cancel_fee_cents']) ? max(0, (int)$output['late_cancel_fee_cents']) : 0;
        $output['late_cancel_deduct_credit'] = isset($output['late_cancel_deduct_credit']) ? 1 : 0;
        $output['no_show_fee_cents'] = isset($output['no_show_fee_cents']) ? max(0, (int)$output['no_show_fee_cents']) : 0;
        $output['no_show_deduct_credit'] = isset($output['no_show_deduct_credit']) ? 1 : 0;
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
        $output['qb_item_per_instructor_enable'] = isset($output['qb_item_per_instructor_enable']) ? 1 : 0;
        // Zoom settings
        $output['zoom_enabled'] = isset($output['zoom_enabled']) ? 1 : 0;
        $output['zoom_auto_create'] = isset($output['zoom_auto_create']) ? 1 : 0;
        $output['zoom_waiting_room'] = isset($output['zoom_waiting_room']) ? 1 : 0;
        $output['zoom_mute_on_entry'] = isset($output['zoom_mute_on_entry']) ? 1 : 0;
        $output['zoom_join_before_minutes'] = isset($output['zoom_join_before_minutes']) ? max(0, intval($output['zoom_join_before_minutes'])) : 5;
        $output['zoom_auto_recording'] = in_array(($output['zoom_auto_recording'] ?? 'none'), ['none','local','cloud'], true) ? $output['zoom_auto_recording'] : 'none';
        // Keep business_country empty if not provided to allow inference from Locations
        $bc = strtoupper(sanitize_text_field($output['business_country'] ?? ''));
        $output['business_country'] = preg_match('/^[A-Z]{2}$/', $bc) ? $bc : '';
        $output['qb_item_per_class_enable'] = isset($output['qb_item_per_class_enable']) ? 1 : 0;
        $output['delete_on_uninstall'] = isset($output['delete_on_uninstall']) ? 1 : 0;
        $output['quickbooks_environment'] = in_array(($output['quickbooks_environment'] ?? 'production'), ['production','sandbox'], true) ? $output['quickbooks_environment'] : 'production';
        
        // Additional sanitization for new policy fields
        $output['cancellation_policy_enabled'] = isset($output['cancellation_policy_enabled']) ? 1 : 0;
        $output['cancellation_policy_type'] = sanitize_text_field($output['cancellation_policy_type'] ?? 'flexible');
        $output['refund_policy_enabled'] = isset($output['refund_policy_enabled']) ? 1 : 0;
        $output['refund_processing_type'] = sanitize_text_field($output['refund_processing_type'] ?? 'automatic');
        $output['refund_percentage'] = isset($output['refund_percentage']) ? min(100, max(0, intval($output['refund_percentage']))) : 100;
        $output['refund_processing_fee'] = isset($output['refund_processing_fee']) ? 1 : 0;
        
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
            echo '<p class="description">' . esc_html($args['help']) . '</p>';
        }
    }
    
    public static function field_feature_toggle(array $args): void
    {
        $key = $args['key'];
        $settings = get_option('cfp_settings', []);
        $checked = !empty($settings[$key]) ? 'checked' : '';
        $feature_settings = $args['settings'] ?? [];
        
        echo '<div class="cfp-feature-container">';
        echo '<div class="cfp-feature-toggle">';
        echo '<input type="checkbox" id="' . esc_attr($key) . '" name="cfp_settings[' . esc_attr($key) . ']" value="1" ' . $checked . ' />';
        echo '<label for="' . esc_attr($key) . '">' . esc_html($args['help'] ?? '') . '</label>';
        echo '</div>';
        
        if (!empty($feature_settings)) {
            echo '<div class="cfp-feature-settings">';
            echo '<table class="form-table">';
            
            foreach ($feature_settings as $setting) {
                echo '<tr>';
                echo '<th scope="row">' . esc_html($setting['label']) . '</th>';
                echo '<td>';
                
                switch ($setting['type']) {
                    case 'text':
                        $value = esc_attr($settings[$setting['key']] ?? '');
                        echo '<input type="text" name="cfp_settings[' . esc_attr($setting['key']) . ']" value="' . $value . '" class="regular-text" />';
                        break;
                        
                    case 'password':
                        $value = esc_attr($settings[$setting['key']] ?? '');
                        echo '<input type="password" name="cfp_settings[' . esc_attr($setting['key']) . ']" value="' . $value . '" class="regular-text" />';
                        break;
                        
                    case 'checkbox':
                        $sub_checked = !empty($settings[$setting['key']]) ? 'checked' : '';
                        echo '<label><input type="checkbox" name="cfp_settings[' . esc_attr($setting['key']) . ']" value="1" ' . $sub_checked . ' /> ' . esc_html__('Enabled', 'classflow-pro') . '</label>';
                        break;
                        
                    case 'number':
                        $value = esc_attr($settings[$setting['key']] ?? '0');
                        $step = isset($setting['step']) ? esc_attr($setting['step']) : '1';
                        echo '<input type="number" name="cfp_settings[' . esc_attr($setting['key']) . ']" value="' . $value . '" step="' . $step . '" class="small-text" />';
                        break;
                        
                    case 'select':
                        $value = esc_attr($settings[$setting['key']] ?? '');
                        echo '<select name="cfp_settings[' . esc_attr($setting['key']) . ']">';
                        foreach ($setting['choices'] as $k => $label) {
                            echo '<option value="' . esc_attr($k) . '"' . selected($value, $k, false) . '>' . esc_html($label) . '</option>';
                        }
                        echo '</select>';
                        break;
                }
                
                if (!empty($setting['help'])) {
                    echo '<p class="description">' . esc_html($setting['help']) . '</p>';
                }
                
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            echo '</div>';
        }
        
        echo '</div>';
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
