<?php
namespace ClassFlowPro;

class Shortcodes
{
    public static function register(): void
    {
        add_shortcode('cfp_calendar_booking', [self::class, 'calendar_booking']);
        add_shortcode('cfp_small_calendar_booking', [self::class, 'small_calendar_booking']);
        add_shortcode('cfp_step_booking', [self::class, 'step_booking']);
        add_shortcode('cfp_intake_form', [self::class, 'intake_form']);
        add_shortcode('cfp_booking_funnel', [self::class, 'booking_funnel']);
        add_shortcode('cfp_user_portal', [self::class, 'user_portal']);
        add_shortcode('cfp_checkout_success', [self::class, 'checkout_success']);
        add_shortcode('cfp_waitlist_response', [self::class, 'waitlist_response']);
    }

    public static function small_calendar_booking($atts): string
    {
        $atts = shortcode_atts(['class_id' => 0, 'location_id' => 0], $atts, 'cfp_small_calendar_booking');
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-calendar', CFP_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        ?>
        <div class="cfp-calendar-booking cfp-small-calendar" data-class-id="<?php echo esc_attr((int)$atts['class_id']); ?>" data-location-id="<?php echo esc_attr((int)$atts['location_id']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="cfp-cal-container">
                <div class="cfp-cal-main">
                    <div class="cfp-cal-toolbar">
                        <div class="cfp-cal-head">
                            <button class="cfp-cal-prev" aria-label="Previous">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <span class="cfp-cal-title">Loading...</span>
                            <button class="cfp-cal-next" aria-label="Next">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                        <div class="cfp-cal-views">
                            <button class="cfp-view active" data-view="month">Month</button>
                            <button class="cfp-view" data-view="week">Week</button>
                            <button class="cfp-view" data-view="agenda">List</button>
                        </div>
                        <div class="cfp-cal-filters">
                            <select class="cfp-filter-class">
                                <option value="">All Classes</option>
                            </select>
                            <select class="cfp-filter-location">
                                <option value="">All Locations</option>
                            </select>
                            <select class="cfp-filter-instructor">
                                <option value="">All Instructors</option>
                            </select>
                        </div>
                    </div>
                    <div class="cfp-cal-grid cfp-loading"></div>
                    <div class="cfp-agenda" style="display:none"></div>
                </div>
                <div class="cfp-cal-sidebar">
                    <h4>Book Your Class</h4>
                    <div class="cfp-cal-selected"></div>
                    <label>
                        Your Name
                        <input type="text" class="cfp-name" placeholder="Enter your name" required>
                    </label>
                    <label>
                        Email Address
                        <input type="email" class="cfp-email" placeholder="your@email.com" required>
                    </label>
                    
                    <label style="display: flex; align-items: center; font-weight: normal;">
                        <input type="checkbox" class="cfp-use-credits">
                        <span>Use available credits</span>
                    </label>
                    <button class="cfp-book">Book Class</button>
                    <div class="cfp-payment" style="display:none">
                        <div class="cfp-card-element"></div>
                        <button class="cfp-pay">Complete Payment</button>
                    </div>
                    <div class="cfp-msg" aria-live="polite"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function calendar_booking($atts): string
    {
        $atts = shortcode_atts(['class_id' => 0, 'location_id' => 0], $atts, 'cfp_calendar_booking');
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-calendar', CFP_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        ?>
        <div class="cfp-calendar-booking cfp-full-calendar" data-class-id="<?php echo esc_attr((int)$atts['class_id']); ?>" data-location-id="<?php echo esc_attr((int)$atts['location_id']); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="cfp-full-cal-wrapper">
                <div class="cfp-full-cal-header">
                    <div class="cfp-full-cal-nav">
                        <button class="cfp-cal-prev" aria-label="Previous month">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <h2 class="cfp-cal-title">Loading...</h2>
                        <button class="cfp-cal-next" aria-label="Next month">
                            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                    <div class="cfp-full-cal-controls">
                        <div class="cfp-cal-views">
                            <button class="cfp-view active" data-view="month">Month</button>
                            <button class="cfp-view" data-view="week">Week</button>
                            <button class="cfp-view" data-view="agenda">List</button>
                        </div>
                        <div class="cfp-cal-filters">
                            <select class="cfp-filter-class">
                                <option value="">All Classes</option>
                            </select>
                            <select class="cfp-filter-location">
                                <option value="">All Locations</option>
                            </select>
                            <select class="cfp-filter-instructor">
                                <option value="">All Instructors</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="cfp-full-cal-body">
                    <div class="cfp-full-cal-main">
                        <div class="cfp-cal-grid cfp-loading"></div>
                        <div class="cfp-agenda" style="display:none"></div>
                    </div>
                    <div class="cfp-full-cal-sidebar">
                        <div class="cfp-sidebar-card">
                            <h3>Selected Class</h3>
                            <div class="cfp-cal-selected"></div>
                        </div>
                        <div class="cfp-sidebar-card">
                            <h3>Quick Booking</h3>
                            <form class="cfp-booking-form">
                                <label>
                                    Name
                                    <input type="text" class="cfp-name" placeholder="Your full name" required>
                                </label>
                                <label>
                                    Email
                                    <input type="email" class="cfp-email" placeholder="your@email.com" required>
                                </label>
                                <label>
                                    Phone
                                    <input type="tel" class="cfp-phone" placeholder="(555) 123-4567">
                                </label>
                                <label>
                                    Create password
                                    <input type="password" class="cfp-password" autocomplete="new-password" placeholder="Set a password (optional)">
                                </label>
                                <label class="cfp-checkbox-label">
                                    <input type="checkbox" class="cfp-sms-optin">
                                    <span>Send me text messages about my bookings (optional)</span>
                                </label>
                                
                                <label class="cfp-checkbox-label">
                                    <input type="checkbox" class="cfp-use-credits">
                                    <span>Use available credits</span>
                                </label>
                                <button type="button" class="cfp-book">Book This Class</button>
                            </form>
                            <div class="cfp-payment" style="display:none">
                                <h4>Payment Details</h4>
                                <div class="cfp-card-element"></div>
                                <button class="cfp-pay">Complete Payment</button>
                            </div>
                            <div class="cfp-msg" aria-live="polite"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function step_booking($atts): string
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-step', CFP_PLUGIN_URL . 'assets/js/step-booking.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-step-booking" data-nonce="' . esc_attr($nonce) . '">';
        echo '<div class="cfp-step cfp-step-1"><h4>Step 1 — Choose</h4><label>Location <select class="cfp-loc"><option value="">All</option></select></label> <label>Class <select class="cfp-class"><option value="">All</option></select></label> <label>Date <input type="date" class="cfp-date"></label> <button class="button cfp-next-1">Next</button></div>';
        echo '<div class="cfp-step cfp-step-2" style="display:none"><h4>Step 2 — Select Time</h4><div class="cfp-times"></div><button class="button cfp-prev-2">Back</button> <button class="button cfp-next-2">Next</button></div>';
        echo '<div class="cfp-step cfp-step-3" style="display:none"><h4>Step 3 — Your Details</h4><label>Name <input type="text" class="cfp-name"></label> <label>Email <input type="email" class="cfp-email" autocomplete="email"></label> <label>Phone <input type="tel" class="cfp-phone" autocomplete="tel"></label> <div class="cfp-account-fields" style="display:block;margin:8px 0;"><label>Create password <input type="password" class="cfp-password" autocomplete="new-password"></label> <small style="display:block;color:#64748b;">If you don\'t have an account, we\'ll create one using this password.</small> <label style="display:block;margin-top:6px;"><input type="checkbox" class="cfp-sms-optin"> Send me text messages about my bookings (optional)</label></div> <label><input type="checkbox" class="cfp-use-credits"> Use credits</label> <button class="button cfp-prev-3">Back</button> <button class="button button-primary cfp-next-3">Review</button></div>';
        echo '<div class="cfp-step cfp-step-4" style="display:none"><h4>Step 4 — Payment</h4><div class="cfp-review"></div><div class="cfp-payment" style="display:none"><div class="cfp-card-element"></div></div><button class="button cfp-prev-4">Back</button> <button class="button button-primary cfp-pay">Pay</button><div class="cfp-msg" aria-live="polite"></div></div>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function intake_form($atts): string
    {
        if (!is_user_logged_in()) return '<p>Please log in to complete intake.</p>';
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-intake', CFP_PLUGIN_URL . 'assets/js/intake.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-intake" data-nonce="' . esc_attr($nonce) . '"><h3>Client Intake Form</h3><div class="cfp-intake-form">';
        echo '<label>Phone <input type="tel" class="cfp-phone"></label><label>Date of Birth <input type="date" class="cfp-dob"></label><label>Emergency Contact Name <input type="text" class="cfp-emg-name"></label><label>Emergency Contact Phone <input type="tel" class="cfp-emg-phone"></label><label>Medical Conditions <textarea class="cfp-med"></textarea></label><label>Injuries/Surgeries <textarea class="cfp-inj"></textarea></label><label><input type="checkbox" class="cfp-preg"> Currently pregnant</label><label>Type Full Name as Signature <input type="text" class="cfp-sign"></label><label><input type="checkbox" class="cfp-consent"> I agree to the liability waiver and studio policies.</label><button class="button button-primary cfp-intake-submit">Submit Intake</button><div class="cfp-msg" aria-live="polite"></div>';
        echo '</div></div>';
        return ob_get_clean();
    }

    public static function booking_funnel($atts): string
    {
        wp_enqueue_style('cfp-frontend');
        $out = '';
        $login_url = wp_login_url(esc_url(add_query_arg([], home_url($_SERVER['REQUEST_URI'] ?? '/'))));
        $register_url = wp_registration_url();
        if (!is_user_logged_in()) {
            $out .= '<div class="cfp-funnel-auth"><p>Please log in or register to book.</p>';
            $out .= '<p><a class="button" href="' . esc_url($login_url) . '">Log In</a> ';
            $out .= '<a class="button" href="' . esc_url($register_url) . '">Register</a></p></div>';
            return $out;
        }
        // Intake gating if required
        if (\ClassFlowPro\Admin\Settings::get('require_intake', 0)) {
            global $wpdb; $t=$wpdb->prefix.'cfp_intake_forms';
            $has = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE user_id = %d", get_current_user_id()));
            if ($has <= 0) {
                $out .= '<div class="cfp-funnel-intake"><p>Please complete your intake before booking.</p>';
                $out .= do_shortcode('[cfp_intake_form]');
                $out .= '</div>';
                return $out;
            }
        }
        // Show booking widget
        $out .= do_shortcode('[cfp_step_booking]');
        return $out;
    }

    public static function user_portal($atts): string
    {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(esc_url(add_query_arg([], home_url($_SERVER['REQUEST_URI'] ?? '/'))));
            return '<p>Please <a href="' . esc_url($login_url) . '">log in</a> to view your portal.</p>';
        }
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-portal', CFP_PLUGIN_URL . 'assets/js/portal.js', ['jquery'], '1.0.0', true);
        // Localize data expected by portal.js
        wp_localize_script('cfp-portal', 'CFP_DATA', [
            'restUrl' => esc_url_raw(rest_url('classflow/v1/')),
            'businessTimezone' => \ClassFlowPro\Admin\Settings::get('business_timezone', (function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC')),
        ]);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        $show_thanks = isset($_GET['cfp_checkout']) && sanitize_text_field((string)$_GET['cfp_checkout']) === 'success';
        echo '<div class="cfp-portal" data-nonce="' . esc_attr($nonce) . '">';
        if ($show_thanks) {
            echo '<div class="cfp-portal-banner cfp-portal-success" role="status">' . esc_html__('Thank you! Your checkout completed successfully.', 'classflow-pro') . '</div>';
        }
        echo '<div class="cfp-portal-profile"><h3>Your Profile</h3><div class="cfp-profile-fields">';
        $u = wp_get_current_user();
        echo '<p><strong>Name:</strong> ' . esc_html($u->display_name ?: ($u->user_firstname . ' ' . $u->user_lastname)) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html($u->user_email) . '</p>';
        // Client-editable basics
        echo '<div class="cfp-profile-edit">'
            . '<label>Phone <input type="tel" class="cfp-prof-phone"/></label>'
            . '<label>Date of Birth <input type="date" class="cfp-prof-dob"/></label>'
            . '<label>Emergency Contact Name <input type="text" class="cfp-prof-emg-name"/></label>'
            . '<label>Emergency Contact Phone <input type="tel" class="cfp-prof-emg-phone"/></label>'
            . '<button class="button button-primary cfp-prof-save">' . esc_html__('Save Profile', 'classflow-pro') . '</button>'
            . '<div class="cfp-msg" aria-live="polite"></div>'
            . '</div>';
        echo '</div></div>';
        echo '<div class="cfp-portal-upcoming"><h3>Upcoming Classes</h3><div class="cfp-list cfp-upcoming-list">Loading…</div></div>';
        echo '<div class="cfp-portal-past"><h3>Past Classes</h3><div class="cfp-list cfp-past-list">Loading…</div></div>';
        echo '<div class="cfp-portal-credits"><h3>Credits</h3><div class="cfp-credits">Loading…</div></div>';
        echo '<div class="cfp-portal-notes"><h3>' . esc_html__('Notes', 'classflow-pro') . '</h3><div class="cfp-notes-list">Loading…</div></div>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function checkout_success($atts = []): string
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-checkout-success', CFP_PLUGIN_URL . 'assets/js/checkout-success.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-checkout-success" data-nonce="' . esc_attr($nonce) . '"><div class="cfp-msg" aria-live="polite">Processing your checkout result…</div></div>';
        return ob_get_clean();
    }

    public static function waitlist_response($atts = []): string
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-waitlist-response', CFP_PLUGIN_URL . 'assets/js/waitlist-response.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-waitlist-response" data-nonce="' . esc_attr($nonce) . '"><div class="cfp-msg" aria-live="polite">Processing your response…</div></div>';
        return ob_get_clean();
    }
}
