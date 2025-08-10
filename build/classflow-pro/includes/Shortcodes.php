<?php
namespace ClassFlowPro;

class Shortcodes
{
    public static function register(): void
    {
        add_shortcode('cfp_calendar_booking', [self::class, 'calendar_booking']);
        add_shortcode('cfp_step_booking', [self::class, 'step_booking']);
        add_shortcode('cfp_intake_form', [self::class, 'intake_form']);
    }

    public static function calendar_booking($atts): string
    {
        $atts = shortcode_atts(['class_id' => 0, 'location_id' => 0], $atts, 'cfp_calendar_booking');
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-calendar', CFP_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        ob_start();
        echo '<div class="cfp-calendar-booking" data-class-id="' . esc_attr((int)$atts['class_id']) . '" data-location-id="' . esc_attr((int)$atts['location_id']) . '" data-nonce="' . esc_attr($nonce) . '">';
        echo '<div class="cfp-cal-toolbar">';
        echo '<div class="cfp-cal-head"><button class="button cfp-cal-prev">◀</button> <span class="cfp-cal-title"></span> <button class="button cfp-cal-next">▶</button></div>';
        echo '<div class="cfp-cal-views"><button class="button cfp-view" data-view="month">Month</button> <button class="button cfp-view" data-view="week">Week</button> <button class="button cfp-view" data-view="agenda">Agenda</button></div>';
        echo '<div class="cfp-cal-filters"><select class="cfp-filter-class"><option value="">All Classes</option></select> <select class="cfp-filter-location"><option value="">All Locations</option></select> <select class="cfp-filter-instructor"><option value="">All Instructors</option></select></div>';
        echo '</div>';
        echo '<div class="cfp-cal-grid"></div><div class="cfp-agenda" style="display:none"></div>';
        echo '<div class="cfp-cal-sidebar"><h4>Book Session</h4><div class="cfp-cal-selected"></div><label>Your name <input type="text" class="cfp-name"></label><label>Email <input type="email" class="cfp-email"></label><label>Coupon code <input type="text" class="cfp-coupon" placeholder="WELCOME10"></label><label><input type="checkbox" class="cfp-use-credits"> Use available credits</label><button class="button button-primary cfp-book">Book</button><div class="cfp-payment" style="display:none"><div class="cfp-card-element"></div><button class="button button-primary cfp-pay">Pay</button></div><div class="cfp-msg" aria-live="polite"></div></div>';
        echo '</div>';
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
        echo '<div class="cfp-step cfp-step-3" style="display:none"><h4>Step 3 — Your Details</h4><label>Name <input type="text" class="cfp-name"></label> <label>Email <input type="email" class="cfp-email"></label> <label>Coupon <input type="text" class="cfp-coupon"></label> <label><input type="checkbox" class="cfp-use-credits"> Use credits</label> <button class="button cfp-prev-3">Back</button> <button class="button button-primary cfp-next-3">Review</button></div>';
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
}

