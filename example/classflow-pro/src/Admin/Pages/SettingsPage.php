<?php
declare(strict_types=1);

namespace ClassFlowPro\Admin\Pages;

use ClassFlowPro\Services\Container;

class SettingsPage {
    private Container $container;
    private array $tabs;
    private string $currentTab;

    public function __construct(Container $container) {
        $this->container = $container;
        $this->initializeTabs();
        $this->currentTab = $_GET['tab'] ?? 'general';
        
        // Handle form submission
        if (isset($_POST['classflow_pro_save_settings'])) {
            $this->handleFormSubmission();
        }
    }

    private function initializeTabs(): void {
        $this->tabs = [
            'general' => __('General', 'classflow-pro'),
            'booking' => __('Booking', 'classflow-pro'),
            'payment' => __('Payment', 'classflow-pro'),
            'email' => __('Email', 'classflow-pro'),
            'appearance' => __('Appearance', 'classflow-pro'),
            'advanced' => __('Advanced', 'classflow-pro'),
            'system' => __('System', 'classflow-pro'),
        ];
    }

    public function render(): void {
        $settings = $this->container->get('settings');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('ClassFlow Pro Settings', 'classflow-pro'); ?></h1>
            
            <?php $this->renderTabs(); ?>
            
            <form method="post" action="" id="classflow-settings-form">
                <?php wp_nonce_field('classflow_pro_settings', 'classflow_pro_settings_nonce'); ?>
                <input type="hidden" name="classflow_pro_save_settings" value="1">
                <input type="hidden" name="tab" value="<?php echo esc_attr($this->currentTab); ?>">
                
                <div class="classflow-settings-content">
                    <?php $this->renderTabContent($settings); ?>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="submit">
                        <?php echo esc_html__('Save Settings', 'classflow-pro'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    private function renderTabs(): void {
        ?>
        <nav class="nav-tab-wrapper">
            <?php foreach ($this->tabs as $tab => $label): ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab)); ?>" 
                   class="nav-tab <?php echo $this->currentTab === $tab ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    private function renderTabContent($settings): void {
        switch ($this->currentTab) {
            case 'general':
                $this->renderGeneralTab($settings);
                break;
            case 'booking':
                $this->renderBookingTab($settings);
                break;
            case 'payment':
                $this->renderPaymentTab($settings);
                break;
            case 'email':
                $this->renderEmailTab($settings);
                break;
            case 'appearance':
                $this->renderAppearanceTab($settings);
                break;
            case 'advanced':
                $this->renderAdvancedTab($settings);
                break;
            case 'system':
                $this->renderSystemTab($settings);
                break;
        }
    }

    private function renderGeneralTab($settings): void {
        ?>
        <div class="classflow-settings-section">
            <h2><?php echo esc_html__('Business Information', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="business_name"><?php echo esc_html__('Business Name', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="business_name" name="general[business_name]" 
                               value="<?php echo esc_attr($settings->get('general.business_name', get_bloginfo('name'))); ?>" 
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__('Your business or studio name.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="business_address"><?php echo esc_html__('Business Address', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <textarea id="business_address" name="general[business_address]" 
                                  rows="3" class="large-text"><?php echo esc_textarea($settings->get('general.business_address', '')); ?></textarea>
                        <p class="description"><?php echo esc_html__('Your business address for invoices and communications.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="contact_phone"><?php echo esc_html__('Contact Phone', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="tel" id="contact_phone" name="general[contact_phone]" 
                               value="<?php echo esc_attr($settings->get('general.contact_phone', '')); ?>" 
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="support_email"><?php echo esc_html__('Support Email', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="support_email" name="general[support_email]" 
                               value="<?php echo esc_attr($settings->get('general.support_email', get_option('admin_email'))); ?>" 
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__('Email address for customer support inquiries.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="business_hours"><?php echo esc_html__('Business Hours', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <textarea id="business_hours" name="general[business_hours]" 
                                  rows="3" class="large-text"><?php echo esc_textarea($settings->get('general.business_hours', '')); ?></textarea>
                        <p class="description"><?php echo esc_html__('Your business operating hours.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Regional Settings', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="timezone"><?php echo esc_html__('Timezone', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select id="timezone" name="general[timezone]" class="regular-text">
                            <?php 
                            $selected_timezone = $settings->get('general.timezone', wp_timezone_string());
                            echo wp_timezone_choice($selected_timezone); 
                            ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Choose your local timezone for class schedules.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="date_format"><?php echo esc_html__('Date Format', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <?php
                        $date_format = $settings->get('general.date_format', get_option('date_format'));
                        $date_formats = array('Y-m-d', 'm/d/Y', 'd/m/Y', 'F j, Y');
                        ?>
                        <fieldset>
                            <?php foreach ($date_formats as $format): ?>
                                <label>
                                    <input type="radio" name="general[date_format]" 
                                           value="<?php echo esc_attr($format); ?>"
                                           <?php checked($date_format, $format); ?> />
                                    <span><?php echo date($format); ?></span>
                                </label><br>
                            <?php endforeach; ?>
                            <label>
                                <input type="radio" name="general[date_format]" value="custom"
                                       <?php checked(!in_array($date_format, $date_formats)); ?> />
                                <?php echo esc_html__('Custom:', 'classflow-pro'); ?>
                            </label>
                            <input type="text" name="general[date_format_custom]" 
                                   value="<?php echo esc_attr($date_format); ?>" 
                                   class="small-text" />
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="time_format"><?php echo esc_html__('Time Format', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <?php
                        $time_format = $settings->get('general.time_format', get_option('time_format'));
                        $time_formats = array('g:i a', 'g:i A', 'H:i');
                        ?>
                        <fieldset>
                            <?php foreach ($time_formats as $format): ?>
                                <label>
                                    <input type="radio" name="general[time_format]" 
                                           value="<?php echo esc_attr($format); ?>"
                                           <?php checked($time_format, $format); ?> />
                                    <span><?php echo date($format); ?></span>
                                </label><br>
                            <?php endforeach; ?>
                            <label>
                                <input type="radio" name="general[time_format]" value="custom"
                                       <?php checked(!in_array($time_format, $time_formats)); ?> />
                                <?php echo esc_html__('Custom:', 'classflow-pro'); ?>
                            </label>
                            <input type="text" name="general[time_format_custom]" 
                                   value="<?php echo esc_attr($time_format); ?>" 
                                   class="small-text" />
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="week_starts_on"><?php echo esc_html__('Week Starts On', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select id="week_starts_on" name="general[week_starts_on]">
                            <?php
                            $week_starts = $settings->get('general.week_starts_on', 1);
                            $days = array(
                                0 => __('Sunday', 'classflow-pro'),
                                1 => __('Monday', 'classflow-pro'),
                                2 => __('Tuesday', 'classflow-pro'),
                                3 => __('Wednesday', 'classflow-pro'),
                                4 => __('Thursday', 'classflow-pro'),
                                5 => __('Friday', 'classflow-pro'),
                                6 => __('Saturday', 'classflow-pro'),
                            );
                            foreach ($days as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php selected($week_starts, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="currency"><?php echo esc_html__('Currency', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select id="currency" name="general[currency]">
                            <?php
                            $currency = $settings->get('general.currency', 'USD');
                            $currencies = $this->getCurrencies();
                            foreach ($currencies as $code => $name): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                                    <?php echo esc_html($code . ' - ' . $name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="country_code"><?php echo esc_html__('Country Code', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select id="country_code" name="general[country_code]">
                            <?php
                            $country = $settings->get('general.country_code', 'US');
                            $countries = $this->getCountries();
                            foreach ($countries as $code => $name): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($country, $code); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Used for payment processing and tax calculations.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function renderBookingTab($settings): void {
        ?>
        <div class="classflow-settings-section">
            <h2><?php echo esc_html__('Booking Rules', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="advance_booking_days"><?php echo esc_html__('Advance Booking Days', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="advance_booking_days" name="booking[advance_booking_days]" 
                               value="<?php echo esc_attr($settings->get('booking.advance_booking_days', 30)); ?>" 
                               min="1" max="365" class="small-text" />
                        <p class="description"><?php echo esc_html__('How many days in advance can students book classes?', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="min_booking_hours"><?php echo esc_html__('Minimum Booking Hours', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="min_booking_hours" name="booking[min_booking_hours]" 
                               value="<?php echo esc_attr($settings->get('booking.min_booking_hours', 24)); ?>" 
                               min="0" max="168" step="0.5" class="small-text" />
                        <p class="description"><?php echo esc_html__('Minimum hours before class start time that bookings are allowed.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cancellation_hours"><?php echo esc_html__('Cancellation Hours', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="cancellation_hours" name="booking[cancellation_hours]" 
                               value="<?php echo esc_attr($settings->get('booking.cancellation_hours', 24)); ?>" 
                               min="0" max="168" step="0.5" class="small-text" />
                        <p class="description"><?php echo esc_html__('Hours before class when cancellations are no longer allowed.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="auto_confirm_bookings"><?php echo esc_html__('Auto-Confirm Bookings', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="auto_confirm_bookings" name="booking[auto_confirm_bookings]" 
                                   value="1" <?php checked($settings->get('booking.auto_confirm_bookings', true)); ?> />
                            <?php echo esc_html__('Automatically confirm bookings (no manual approval needed)', 'classflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pending_expiry_minutes"><?php echo esc_html__('Pending Booking Expiry', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="pending_expiry_minutes" name="booking[pending_expiry_minutes]" 
                               value="<?php echo esc_attr($settings->get('booking.pending_expiry_minutes', 30)); ?>" 
                               min="5" max="1440" class="small-text" />
                        <span><?php echo esc_html__('minutes', 'classflow-pro'); ?></span>
                        <p class="description"><?php echo esc_html__('Time before pending bookings expire if payment is not completed.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Waitlist Settings', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable_waitlist"><?php echo esc_html__('Enable Waitlist', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="enable_waitlist" name="booking[enable_waitlist]" 
                                   value="1" <?php checked($settings->get('booking.enable_waitlist', true)); ?> />
                            <?php echo esc_html__('Allow students to join waitlist for full classes', 'classflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="max_waitlist_size"><?php echo esc_html__('Maximum Waitlist Size', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max_waitlist_size" name="booking[max_waitlist_size]" 
                               value="<?php echo esc_attr($settings->get('booking.max_waitlist_size', 5)); ?>" 
                               min="1" max="50" class="small-text" />
                        <p class="description"><?php echo esc_html__('Maximum number of students allowed on the waitlist.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Class Defaults', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="default_class_capacity"><?php echo esc_html__('Default Class Capacity', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="default_class_capacity" name="booking[default_class_capacity]" 
                               value="<?php echo esc_attr($settings->get('booking.default_class_capacity', 10)); ?>" 
                               min="1" max="500" class="small-text" />
                        <p class="description"><?php echo esc_html__('Default maximum number of students per class.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="default_class_duration"><?php echo esc_html__('Default Class Duration', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="default_class_duration" name="booking[default_class_duration]" 
                               value="<?php echo esc_attr($settings->get('booking.default_class_duration', 60)); ?>" 
                               min="1" max="480" step="1" class="small-text" />
                        <span><?php echo esc_html__('minutes', 'classflow-pro'); ?></span>
                        <p class="description"><?php echo esc_html__('Default duration for new classes.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="booking_buffer_time"><?php echo esc_html__('Booking Buffer Time', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="booking_buffer_time" name="booking[booking_buffer_time]" 
                               value="<?php echo esc_attr($settings->get('booking.booking_buffer_time', 15)); ?>" 
                               min="0" max="120" step="1" class="small-text" />
                        <span><?php echo esc_html__('minutes', 'classflow-pro'); ?></span>
                        <p class="description"><?php echo esc_html__('Minimum time between back-to-back class bookings.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function renderPaymentTab($settings): void {
        ?>
        <div class="classflow-settings-section">
            <h2><?php echo esc_html__('Payment Gateway', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="payment_enabled"><?php echo esc_html__('Enable Payments', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="payment_enabled" name="payment[enabled]" 
                                   value="1" <?php checked($settings->get('payment.enabled', true)); ?> />
                            <?php echo esc_html__('Enable payment processing for bookings', 'classflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="require_payment"><?php echo esc_html__('Require Payment', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="require_payment" name="payment[require_payment]" 
                                   value="1" <?php checked($settings->get('payment.require_payment', true)); ?> />
                            <?php echo esc_html__('Require payment to confirm bookings', 'classflow-pro'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('If disabled, bookings can be confirmed without payment.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stripe_mode"><?php echo esc_html__('Payment Mode', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select id="stripe_mode" name="payment[stripe_mode]">
                            <?php $stripe_mode = $settings->get('payment.stripe_mode', 'test'); ?>
                            <option value="test" <?php selected($stripe_mode, 'test'); ?>>
                                <?php echo esc_html__('Test Mode', 'classflow-pro'); ?>
                            </option>
                            <option value="live" <?php selected($stripe_mode, 'live'); ?>>
                                <?php echo esc_html__('Live Mode', 'classflow-pro'); ?>
                            </option>
                        </select>
                        <p class="description"><?php echo esc_html__('Use test mode for development and testing.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Stripe Configuration', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="stripe_test_secret_key"><?php echo esc_html__('Test Secret Key', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="stripe_test_secret_key" name="payment[stripe_test_secret_key]" 
                               value="<?php echo esc_attr($settings->get('payment.stripe_test_secret_key', '')); ?>" 
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__('Your Stripe test mode secret key (sk_test_...)', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stripe_test_publishable_key"><?php echo esc_html__('Test Publishable Key', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="stripe_test_publishable_key" name="payment[stripe_test_publishable_key]" 
                               value="<?php echo esc_attr($settings->get('payment.stripe_test_publishable_key', '')); ?>" 
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__('Your Stripe test mode publishable key (pk_test_...)', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stripe_live_secret_key"><?php echo esc_html__('Live Secret Key', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="stripe_live_secret_key" name="payment[stripe_live_secret_key]" 
                               value="<?php echo esc_attr($settings->get('payment.stripe_live_secret_key', '')); ?>" 
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__('Your Stripe live mode secret key (sk_live_...)', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stripe_live_publishable_key"><?php echo esc_html__('Live Publishable Key', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="stripe_live_publishable_key" name="payment[stripe_live_publishable_key]" 
                               value="<?php echo esc_attr($settings->get('payment.stripe_live_publishable_key', '')); ?>" 
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__('Your Stripe live mode publishable key (pk_live_...)', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stripe_webhook_secret"><?php echo esc_html__('Webhook Secret', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="stripe_webhook_secret" name="payment[stripe_webhook_secret]" 
                               value="<?php echo esc_attr($settings->get('payment.stripe_webhook_secret', '')); ?>" 
                               class="regular-text" />
                        <p class="description">
                            <?php echo esc_html__('Your Stripe webhook signing secret. Configure webhook endpoint:', 'classflow-pro'); ?>
                            <code><?php echo esc_url(home_url('/wp-json/classflow-pro/v1/webhook/stripe')); ?></code>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Payment Options', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="allow_partial_payment"><?php echo esc_html__('Allow Partial Payments', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="allow_partial_payment" name="payment[allow_partial_payment]" 
                                   value="1" <?php checked($settings->get('payment.allow_partial_payment', false)); ?> 
                                   class="classflow-toggle-trigger" data-toggle-target=".partial-payment-settings" />
                            <?php echo esc_html__('Allow students to pay a deposit instead of full amount', 'classflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr class="partial-payment-settings" <?php echo $settings->get('payment.allow_partial_payment', false) ? '' : 'style="display:none;"'; ?>>
                    <th scope="row">
                        <label for="partial_payment_percentage"><?php echo esc_html__('Deposit Percentage', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="partial_payment_percentage" name="payment[partial_payment_percentage]" 
                               value="<?php echo esc_attr($settings->get('payment.partial_payment_percentage', 50)); ?>" 
                               min="10" max="90" class="small-text" />
                        <span>%</span>
                        <p class="description"><?php echo esc_html__('Percentage of total amount required as initial deposit. Students will need to pay the remaining balance before the class starts.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="use_connected_accounts"><?php echo esc_html__('Use Stripe Connect', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="use_connected_accounts" name="payment[use_connected_accounts]" 
                                   value="1" <?php checked($settings->get('payment.use_connected_accounts', false)); ?> 
                                   class="classflow-toggle-trigger" data-toggle-target=".stripe-connect-settings" />
                            <?php echo esc_html__('Use Stripe Connect for instructor payouts', 'classflow-pro'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('Enable automatic payment splitting with instructors through Stripe Connect.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr class="stripe-connect-settings" <?php echo $settings->get('payment.use_connected_accounts', false) ? '' : 'style="display:none;"'; ?>>
                    <th scope="row">
                        <label for="platform_fee_percentage"><?php echo esc_html__('Default Instructor Commission', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="platform_fee_percentage" name="payment[platform_fee_percentage]" 
                               value="<?php echo esc_attr($settings->get('payment.platform_fee_percentage', 80)); ?>" 
                               min="0" max="100" step="0.1" class="small-text" />
                        <span>%</span>
                        <p class="description">
                            <?php echo esc_html__('Default percentage of class revenue that goes to the instructor. This is the amount instructors receive after the platform fee is deducted.', 'classflow-pro'); ?><br>
                            <strong><?php echo esc_html__('Example:', 'classflow-pro'); ?></strong> <?php echo esc_html__('If set to 80%, instructors receive $80 from a $100 class, and the platform keeps $20.', 'classflow-pro'); ?><br>
                            <em><?php echo esc_html__('Note: This can be overridden for individual instructors in their profile settings.', 'classflow-pro'); ?></em>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php echo esc_html__('Stripe Processing Fees', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <p class="description"><?php echo esc_html__('Configure Stripe processing fees for accurate financial calculations and reporting.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stripe_fee_percentage"><?php echo esc_html__('Stripe Fee Percentage', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="stripe_fee_percentage" name="payment[stripe_fee_percentage]" 
                               value="<?php echo esc_attr($settings->get('payment.stripe_fee_percentage', 2.9)); ?>" 
                               min="0" max="10" step="0.1" class="small-text" />
                        <span>%</span>
                        <p class="description"><?php echo esc_html__('Stripe processing fee percentage (typically 2.9% for card payments).', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="stripe_fee_fixed"><?php echo esc_html__('Stripe Fixed Fee', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="stripe_fee_fixed" name="payment[stripe_fee_fixed]" 
                               value="<?php echo esc_attr($settings->get('payment.stripe_fee_fixed', 0.30)); ?>" 
                               min="0" max="5" step="0.01" class="small-text" />
                        <span><?php echo esc_html($settings->get('general.currency', 'USD')); ?></span>
                        <p class="description"><?php echo esc_html__('Stripe fixed fee per transaction (typically $0.30 per transaction).', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function renderEmailTab($settings): void {
        ?>
        <div class="classflow-settings-section">
            <h2><?php echo esc_html__('Email Configuration', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="from_name"><?php echo esc_html__('From Name', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="from_name" name="email[from_name]" 
                               value="<?php echo esc_attr($settings->get('email.from_name', get_bloginfo('name'))); ?>" 
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__('Name that appears in the "From" field of emails.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="from_email"><?php echo esc_html__('From Email', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="from_email" name="email[from_email]" 
                               value="<?php echo esc_attr($settings->get('email.from_email', get_option('admin_email'))); ?>" 
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__('Email address that appears in the "From" field.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="admin_email"><?php echo esc_html__('Admin Notification Email', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="admin_email" name="email[admin_email]" 
                               value="<?php echo esc_attr($settings->get('email.admin_email', get_option('admin_email'))); ?>" 
                               class="regular-text" />
                        <p class="description"><?php echo esc_html__('Email address to receive admin notifications.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="email_logo"><?php echo esc_html__('Email Logo', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <div class="classflow-media-upload">
                            <input type="hidden" id="email_logo" name="email[logo]" 
                                   value="<?php echo esc_attr($settings->get('email.logo', '')); ?>" />
                            <button type="button" class="button classflow-upload-button" 
                                    data-target="#email_logo" data-preview="#email_logo_preview">
                                <?php echo esc_html__('Upload Logo', 'classflow-pro'); ?>
                            </button>
                            <button type="button" class="button classflow-remove-button" 
                                    data-target="#email_logo" data-preview="#email_logo_preview" 
                                    <?php echo empty($settings->get('email.logo')) ? 'style="display:none;"' : ''; ?>>
                                <?php echo esc_html__('Remove Logo', 'classflow-pro'); ?>
                            </button>
                            <div id="email_logo_preview" class="classflow-image-preview">
                                <?php if ($logo_id = $settings->get('email.logo')): ?>
                                    <?php echo wp_get_attachment_image($logo_id, 'thumbnail'); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="description"><?php echo esc_html__('Logo to display in email headers.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="enable_notifications"><?php echo esc_html__('Enable Notifications', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="enable_notifications" name="email[enable_notifications]" 
                                   value="1" <?php checked($settings->get('email.enable_notifications', true)); ?> />
                            <?php echo esc_html__('Enable email notifications system', 'classflow-pro'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Notification Types', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <?php
                $notification_types = [
                    'booking_confirmation' => __('Booking Confirmation', 'classflow-pro'),
                    'booking_cancellation' => __('Booking Cancellation', 'classflow-pro'),
                    'class_reminder' => __('Class Reminder', 'classflow-pro'),
                    'payment_confirmation' => __('Payment Confirmation', 'classflow-pro'),
                    'payment_failed' => __('Payment Failed', 'classflow-pro'),
                    'refund_confirmation' => __('Refund Confirmation', 'classflow-pro'),
                    'waitlist_notification' => __('Waitlist Notification', 'classflow-pro'),
                    'instructor_booking' => __('Instructor Booking Notification', 'classflow-pro'),
                    'instructor_cancellation' => __('Instructor Cancellation Notification', 'classflow-pro'),
                    'admin_new_booking' => __('Admin New Booking Notification', 'classflow-pro'),
                ];
                
                foreach ($notification_types as $type => $label): ?>
                <tr>
                    <th scope="row"><?php echo esc_html($label); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="notifications[<?php echo $type; ?>][enabled]" 
                                   value="1" <?php checked($settings->get("notifications.{$type}.enabled", true)); ?> />
                            <?php echo esc_html__('Enable', 'classflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <tr>
                    <th scope="row">
                        <label for="reminder_hours"><?php echo esc_html__('Class Reminder Hours', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="reminder_hours" name="notifications[reminder_hours]" 
                               value="<?php echo esc_attr($settings->get('notifications.reminder_hours', 24)); ?>" 
                               min="1" max="168" class="small-text" />
                        <p class="description"><?php echo esc_html__('Send class reminders this many hours before class starts.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Email Branding', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="email_primary_color"><?php echo esc_html__('Primary Color', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="email_primary_color" name="email[primary_color]" 
                               value="<?php echo esc_attr($settings->get('email.primary_color', '#2271b1')); ?>" 
                               class="classflow-color-picker" />
                        <p class="description"><?php echo esc_html__('Primary color for email headers and buttons.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="email_signature"><?php echo esc_html__('Email Signature', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <textarea id="email_signature" name="email[signature]" 
                                  rows="5" class="large-text"><?php echo esc_textarea($settings->get('email.signature', '')); ?></textarea>
                        <p class="description"><?php echo esc_html__('Custom signature to append to all emails.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function renderAppearanceTab($settings): void {
        ?>
        <div class="classflow-settings-section">
            <h2><?php echo esc_html__('Theme Colors', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="primary_color"><?php echo esc_html__('Primary Color', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="primary_color" name="frontend[primary_color]" 
                               value="<?php echo esc_attr($settings->get('frontend.primary_color', '#3b82f6')); ?>" 
                               class="classflow-color-picker" />
                        <p class="description"><?php echo esc_html__('Primary theme color for buttons and links.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="secondary_color"><?php echo esc_html__('Secondary Color', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="secondary_color" name="frontend[secondary_color]" 
                               value="<?php echo esc_attr($settings->get('frontend.secondary_color', '#1e40af')); ?>" 
                               class="classflow-color-picker" />
                        <p class="description"><?php echo esc_html__('Secondary theme color for accents.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="success_color"><?php echo esc_html__('Success Color', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="success_color" name="frontend[success_color]" 
                               value="<?php echo esc_attr($settings->get('frontend.success_color', '#28a745')); ?>" 
                               class="classflow-color-picker" />
                        <p class="description"><?php echo esc_html__('Color for success messages and confirmations.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="warning_color"><?php echo esc_html__('Warning Color', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="warning_color" name="frontend[warning_color]" 
                               value="<?php echo esc_attr($settings->get('frontend.warning_color', '#ffc107')); ?>" 
                               class="classflow-color-picker" />
                        <p class="description"><?php echo esc_html__('Color for warning messages.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="error_color"><?php echo esc_html__('Error Color', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="error_color" name="frontend[error_color]" 
                               value="<?php echo esc_attr($settings->get('frontend.error_color', '#dc3545')); ?>" 
                               class="classflow-color-picker" />
                        <p class="description"><?php echo esc_html__('Color for error messages.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Display Options', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="items_per_page"><?php echo esc_html__('Items Per Page', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="items_per_page" name="frontend[items_per_page]" 
                               value="<?php echo esc_attr($settings->get('frontend.items_per_page', 12)); ?>" 
                               min="6" max="50" step="6" class="small-text" />
                        <p class="description"><?php echo esc_html__('Number of items to display per page in listings.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="show_instructor_bio"><?php echo esc_html__('Show Instructor Bio', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="show_instructor_bio" name="frontend[show_instructor_bio]" 
                                   value="1" <?php checked($settings->get('frontend.show_instructor_bio', true)); ?> />
                            <?php echo esc_html__('Display instructor biographies on class pages', 'classflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="calendar_default_view"><?php echo esc_html__('Calendar Default View', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select id="calendar_default_view" name="frontend[calendar_default_view]">
                            <?php
                            $default_view = $settings->get('frontend.calendar_default_view', 'month');
                            $views = [
                                'month' => __('Month', 'classflow-pro'),
                                'week' => __('Week', 'classflow-pro'),
                                'day' => __('Day', 'classflow-pro'),
                                'list' => __('List', 'classflow-pro'),
                            ];
                            foreach ($views as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($default_view, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Default view when calendar loads.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function renderAdvancedTab($settings): void {
        ?>
        <div class="classflow-settings-section">
            <h2><?php echo esc_html__('API Settings', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_items_per_page"><?php echo esc_html__('API Items Per Page', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="api_items_per_page" name="advanced[api_items_per_page]" 
                               value="<?php echo esc_attr($settings->get('advanced.api_items_per_page', 10)); ?>" 
                               min="1" max="100" class="small-text" />
                        <p class="description"><?php echo esc_html__('Default number of items per page in API responses (max 100).', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Package Settings', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="package_validity_days"><?php echo esc_html__('Default Package Validity', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="package_validity_days" name="advanced[package_validity_days]" 
                               value="<?php echo esc_attr($settings->get('advanced.package_validity_days', 30)); ?>" 
                               min="1" max="365" class="small-text" />
                        <span><?php echo esc_html__('days', 'classflow-pro'); ?></span>
                        <p class="description"><?php echo esc_html__('Default validity period for class packages.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Restrictions', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="max_bookings_per_day"><?php echo esc_html__('Max Bookings Per Day', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max_bookings_per_day" name="advanced[max_bookings_per_day]" 
                               value="<?php echo esc_attr($settings->get('advanced.max_bookings_per_day', 0)); ?>" 
                               min="0" max="20" class="small-text" />
                        <p class="description"><?php echo esc_html__('Maximum bookings a student can make per day (0 = unlimited).', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="max_bookings_per_week"><?php echo esc_html__('Max Bookings Per Week', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max_bookings_per_week" name="advanced[max_bookings_per_week]" 
                               value="<?php echo esc_attr($settings->get('advanced.max_bookings_per_week', 0)); ?>" 
                               min="0" max="50" class="small-text" />
                        <p class="description"><?php echo esc_html__('Maximum bookings a student can make per week (0 = unlimited).', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="max_bookings_per_month"><?php echo esc_html__('Max Bookings Per Month', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max_bookings_per_month" name="advanced[max_bookings_per_month]" 
                               value="<?php echo esc_attr($settings->get('advanced.max_bookings_per_month', 0)); ?>" 
                               min="0" max="200" class="small-text" />
                        <p class="description"><?php echo esc_html__('Maximum bookings a student can make per month (0 = unlimited).', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="minimum_age"><?php echo esc_html__('Minimum Age Requirement', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="minimum_age" name="advanced[minimum_age]" 
                               value="<?php echo esc_attr($settings->get('advanced.minimum_age', 0)); ?>" 
                               min="0" max="100" class="small-text" />
                        <p class="description"><?php echo esc_html__('Minimum age for class bookings (0 = no restriction).', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Performance', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable_cache"><?php echo esc_html__('Enable Settings Cache', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="enable_cache" name="advanced[enable_cache]" 
                                   value="1" <?php checked($settings->get('advanced.enable_cache', true)); ?> />
                            <?php echo esc_html__('Cache settings for improved performance', 'classflow-pro'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('Recommended for production sites.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function renderSystemTab($settings): void {
        ?>
        <div class="classflow-settings-section">
            <h2><?php echo esc_html__('Debug Options', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="debug_mode"><?php echo esc_html__('Debug Mode', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="debug_mode" name="system[debug_mode]" 
                                   value="1" <?php checked($settings->get('system.debug_mode', false)); ?> />
                            <?php echo esc_html__('Enable debug logging', 'classflow-pro'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('Logs debug information to the WordPress debug log.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="log_level"><?php echo esc_html__('Log Level', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select id="log_level" name="system[log_level]">
                            <?php
                            $log_level = $settings->get('system.log_level', 'error');
                            $levels = [
                                'error' => __('Error', 'classflow-pro'),
                                'warning' => __('Warning', 'classflow-pro'),
                                'info' => __('Info', 'classflow-pro'),
                                'debug' => __('Debug', 'classflow-pro'),
                            ];
                            foreach ($levels as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($log_level, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__('Minimum severity level to log.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('Data Management', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Export Settings', 'classflow-pro'); ?></th>
                    <td>
                        <button type="button" class="button" id="export-settings">
                            <?php echo esc_html__('Export Settings', 'classflow-pro'); ?>
                        </button>
                        <p class="description"><?php echo esc_html__('Download all settings as a JSON file.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Import Settings', 'classflow-pro'); ?></th>
                    <td>
                        <input type="file" id="import-settings-file" accept=".json" />
                        <button type="button" class="button" id="import-settings">
                            <?php echo esc_html__('Import Settings', 'classflow-pro'); ?>
                        </button>
                        <p class="description"><?php echo esc_html__('Import settings from a JSON file.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Reset Settings', 'classflow-pro'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="reset-settings">
                            <?php echo esc_html__('Reset to Defaults', 'classflow-pro'); ?>
                        </button>
                        <p class="description"><?php echo esc_html__('Reset all settings to their default values.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php echo esc_html__('System Information', 'classflow-pro'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Plugin Version', 'classflow-pro'); ?></th>
                    <td><?php echo CLASSFLOW_PRO_VERSION; ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('PHP Version', 'classflow-pro'); ?></th>
                    <td><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('WordPress Version', 'classflow-pro'); ?></th>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Database Status', 'classflow-pro'); ?></th>
                    <td>
                        <?php
                        global $wpdb;
                        $tables = [
                            $wpdb->prefix . 'classflow_classes',
                            $wpdb->prefix . 'classflow_schedules',
                            $wpdb->prefix . 'classflow_bookings',
                            $wpdb->prefix . 'classflow_payments',
                        ];
                        $all_exist = true;
                        foreach ($tables as $table) {
                            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                                $all_exist = false;
                                break;
                            }
                        }
                        ?>
                        <span class="classflow-pro-status-badge <?php echo $all_exist ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $all_exist ? __('All tables present', 'classflow-pro') : __('Missing tables', 'classflow-pro'); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function handleFormSubmission(): void {
        if (!wp_verify_nonce($_POST['classflow_pro_settings_nonce'], 'classflow_pro_settings')) {
            wp_die(__('Security check failed', 'classflow-pro'));
        }

        if (!current_user_can('manage_classflow_settings')) {
            wp_die(__('You do not have permission to access this page.', 'classflow-pro'));
        }

        $settings = $this->container->get('settings');
        $tab = sanitize_text_field($_POST['tab']);

        switch ($tab) {
            case 'general':
                $this->saveGeneralSettings($settings);
                break;
            case 'booking':
                $this->saveBookingSettings($settings);
                break;
            case 'payment':
                $this->savePaymentSettings($settings);
                break;
            case 'email':
                $this->saveEmailSettings($settings);
                break;
            case 'appearance':
                $this->saveAppearanceSettings($settings);
                break;
            case 'advanced':
                $this->saveAdvancedSettings($settings);
                break;
            case 'system':
                $this->saveSystemSettings($settings);
                break;
        }

        // Clear any caches
        wp_cache_flush();

        // Redirect with success message
        wp_redirect(add_query_arg([
            'page' => 'classflow-pro-settings',
            'tab' => $tab,
            'message' => 'settings-saved'
        ], admin_url('admin.php')));
        exit;
    }

    private function saveGeneralSettings($settings): void {
        $general = $_POST['general'] ?? [];
        
        $settings->set('general.business_name', sanitize_text_field($general['business_name'] ?? ''));
        $settings->set('general.business_address', sanitize_textarea_field($general['business_address'] ?? ''));
        $settings->set('general.contact_phone', sanitize_text_field($general['contact_phone'] ?? ''));
        $settings->set('general.support_email', sanitize_email($general['support_email'] ?? ''));
        $settings->set('general.business_hours', sanitize_textarea_field($general['business_hours'] ?? ''));
        $settings->set('general.timezone', sanitize_text_field($general['timezone'] ?? ''));
        
        // Handle date format
        if (isset($general['date_format'])) {
            $date_format = $general['date_format'] === 'custom' 
                ? sanitize_text_field($general['date_format_custom'] ?? '') 
                : sanitize_text_field($general['date_format']);
            $settings->set('general.date_format', $date_format);
        }
        
        // Handle time format
        if (isset($general['time_format'])) {
            $time_format = $general['time_format'] === 'custom' 
                ? sanitize_text_field($general['time_format_custom'] ?? '') 
                : sanitize_text_field($general['time_format']);
            $settings->set('general.time_format', $time_format);
        }
        
        $settings->set('general.week_starts_on', (int) ($general['week_starts_on'] ?? 1));
        $settings->set('general.currency', sanitize_text_field($general['currency'] ?? 'USD'));
        $settings->set('general.country_code', sanitize_text_field($general['country_code'] ?? 'US'));
    }

    private function saveBookingSettings($settings): void {
        $booking = $_POST['booking'] ?? [];
        
        $settings->set('booking.advance_booking_days', (int) ($booking['advance_booking_days'] ?? 30));
        $settings->set('booking.min_booking_hours', (float) ($booking['min_booking_hours'] ?? 24));
        $settings->set('booking.cancellation_hours', (float) ($booking['cancellation_hours'] ?? 24));
        $settings->set('booking.auto_confirm_bookings', !empty($booking['auto_confirm_bookings']));
        $settings->set('booking.pending_expiry_minutes', (int) ($booking['pending_expiry_minutes'] ?? 30));
        $settings->set('booking.enable_waitlist', !empty($booking['enable_waitlist']));
        $settings->set('booking.max_waitlist_size', (int) ($booking['max_waitlist_size'] ?? 5));
        $settings->set('booking.default_class_capacity', (int) ($booking['default_class_capacity'] ?? 10));
        $settings->set('booking.default_class_duration', (int) ($booking['default_class_duration'] ?? 60));
        $settings->set('booking.booking_buffer_time', (int) ($booking['booking_buffer_time'] ?? 15));
    }

    private function savePaymentSettings($settings): void {
        $payment = $_POST['payment'] ?? [];
        
        $settings->set('payment.enabled', !empty($payment['enabled']));
        $settings->set('payment.require_payment', !empty($payment['require_payment']));
        $settings->set('payment.stripe_mode', sanitize_text_field($payment['stripe_mode'] ?? 'test'));
        
        // Stripe keys
        $settings->set('payment.stripe_test_secret_key', sanitize_text_field($payment['stripe_test_secret_key'] ?? ''));
        $settings->set('payment.stripe_test_publishable_key', sanitize_text_field($payment['stripe_test_publishable_key'] ?? ''));
        $settings->set('payment.stripe_live_secret_key', sanitize_text_field($payment['stripe_live_secret_key'] ?? ''));
        $settings->set('payment.stripe_live_publishable_key', sanitize_text_field($payment['stripe_live_publishable_key'] ?? ''));
        $settings->set('payment.stripe_webhook_secret', sanitize_text_field($payment['stripe_webhook_secret'] ?? ''));
        
        // Payment options
        $settings->set('payment.allow_partial_payment', !empty($payment['allow_partial_payment']));
        $settings->set('payment.partial_payment_percentage', (float) ($payment['partial_payment_percentage'] ?? 50));
        
        // Note: platform_fee_percentage is now the instructor commission percentage
        // The actual platform fee would be 100 - instructor_commission
        $settings->set('payment.platform_fee_percentage', (float) ($payment['platform_fee_percentage'] ?? 80));
        
        $settings->set('payment.use_connected_accounts', !empty($payment['use_connected_accounts']));
        $settings->set('payment.stripe_fee_percentage', (float) ($payment['stripe_fee_percentage'] ?? 2.9));
        $settings->set('payment.stripe_fee_fixed', (float) ($payment['stripe_fee_fixed'] ?? 0.30));
    }

    private function saveEmailSettings($settings): void {
        $email = $_POST['email'] ?? [];
        $notifications = $_POST['notifications'] ?? [];
        
        $settings->set('email.from_name', sanitize_text_field($email['from_name'] ?? ''));
        $settings->set('email.from_email', sanitize_email($email['from_email'] ?? ''));
        $settings->set('email.admin_email', sanitize_email($email['admin_email'] ?? ''));
        $settings->set('email.logo', (int) ($email['logo'] ?? 0));
        $settings->set('email.enable_notifications', !empty($email['enable_notifications']));
        $settings->set('email.primary_color', sanitize_hex_color($email['primary_color'] ?? '#2271b1'));
        $settings->set('email.signature', sanitize_textarea_field($email['signature'] ?? ''));
        
        // Notification types
        $notification_types = [
            'booking_confirmation', 'booking_cancellation', 'class_reminder',
            'payment_confirmation', 'payment_failed', 'refund_confirmation',
            'waitlist_notification', 'instructor_booking', 'instructor_cancellation',
            'admin_new_booking'
        ];
        
        foreach ($notification_types as $type) {
            $enabled = !empty($notifications[$type]['enabled']);
            $settings->set("notifications.{$type}.enabled", $enabled);
        }
        
        $settings->set('notifications.reminder_hours', (int) ($notifications['reminder_hours'] ?? 24));
    }

    private function saveAppearanceSettings($settings): void {
        $frontend = $_POST['frontend'] ?? [];
        
        $settings->set('frontend.primary_color', sanitize_hex_color($frontend['primary_color'] ?? '#3b82f6'));
        $settings->set('frontend.secondary_color', sanitize_hex_color($frontend['secondary_color'] ?? '#1e40af'));
        $settings->set('frontend.success_color', sanitize_hex_color($frontend['success_color'] ?? '#28a745'));
        $settings->set('frontend.warning_color', sanitize_hex_color($frontend['warning_color'] ?? '#ffc107'));
        $settings->set('frontend.error_color', sanitize_hex_color($frontend['error_color'] ?? '#dc3545'));
        $settings->set('frontend.items_per_page', (int) ($frontend['items_per_page'] ?? 12));
        $settings->set('frontend.show_instructor_bio', !empty($frontend['show_instructor_bio']));
        $settings->set('frontend.calendar_default_view', sanitize_text_field($frontend['calendar_default_view'] ?? 'month'));
    }

    private function saveAdvancedSettings($settings): void {
        $advanced = $_POST['advanced'] ?? [];
        
        $settings->set('advanced.api_items_per_page', (int) ($advanced['api_items_per_page'] ?? 10));
        $settings->set('advanced.package_validity_days', (int) ($advanced['package_validity_days'] ?? 30));
        $settings->set('advanced.max_bookings_per_day', (int) ($advanced['max_bookings_per_day'] ?? 0));
        $settings->set('advanced.max_bookings_per_week', (int) ($advanced['max_bookings_per_week'] ?? 0));
        $settings->set('advanced.max_bookings_per_month', (int) ($advanced['max_bookings_per_month'] ?? 0));
        $settings->set('advanced.minimum_age', (int) ($advanced['minimum_age'] ?? 0));
        $settings->set('advanced.enable_cache', !empty($advanced['enable_cache']));
    }

    private function saveSystemSettings($settings): void {
        $system = $_POST['system'] ?? [];
        
        $settings->set('system.debug_mode', !empty($system['debug_mode']));
        $settings->set('system.log_level', sanitize_text_field($system['log_level'] ?? 'error'));
    }

    private function getCurrencies(): array {
        return [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar',
            'JPY' => 'Japanese Yen',
            'CHF' => 'Swiss Franc',
            'CNY' => 'Chinese Yuan',
            'SEK' => 'Swedish Krona',
            'NZD' => 'New Zealand Dollar',
            'MXN' => 'Mexican Peso',
            'SGD' => 'Singapore Dollar',
            'HKD' => 'Hong Kong Dollar',
            'NOK' => 'Norwegian Krone',
            'KRW' => 'South Korean Won',
            'TRY' => 'Turkish Lira',
            'RUB' => 'Russian Ruble',
            'INR' => 'Indian Rupee',
            'BRL' => 'Brazilian Real',
            'ZAR' => 'South African Rand',
        ];
    }

    private function getCountries(): array {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IE' => 'Ireland',
            'PT' => 'Portugal',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'ZA' => 'South Africa',
        ];
    }
}