<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;

class StepBookingWidget extends Widget_Base
{
    public function get_name() { return 'cfp_step_booking'; }
    public function get_title() { return __('CFP – Step Booking', 'classflow-pro'); }
    public function get_icon() { return 'eicon-wizard'; }
    public function get_categories() { return ['general']; }

    protected function render()
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-step', CFP_PLUGIN_URL . 'assets/js/step-booking.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="cfp-step-booking" data-nonce="' . esc_attr($nonce) . '">';
        echo '<div class="cfp-step cfp-step-1">';
        echo '<h4>Step 1 — Choose</h4>';
        echo '<label>Location <select class="cfp-loc"><option value="">All</option></select></label> ';
        echo '<label>Class <select class="cfp-class"><option value="">All</option></select></label> ';
        echo '<label>Date <input type="date" class="cfp-date"></label> ';
        echo '<button class="button cfp-next-1">Next</button>';
        echo '</div>';
        echo '<div class="cfp-step cfp-step-2" style="display:none">';
        echo '<h4>Step 2 — Select Time</h4><div class="cfp-times"></div>';
        echo '<button class="button cfp-prev-2">Back</button> <button class="button cfp-next-2">Next</button>';
        echo '</div>';
        echo '<div class="cfp-step cfp-step-3" style="display:none">';
        echo '<h4>Step 3 — Your Details</h4>';
        echo '<label>Name <input type="text" class="cfp-name"></label> ';
        echo '<label>Email <input type="email" class="cfp-email"></label> ';
        echo '<label>Coupon <input type="text" class="cfp-coupon"></label> ';
        echo '<label><input type="checkbox" class="cfp-use-credits"> Use credits</label> ';
        echo '<button class="button cfp-prev-3">Back</button> <button class="button button-primary cfp-next-3">Review</button>';
        echo '</div>';
        echo '<div class="cfp-step cfp-step-4" style="display:none">';
        echo '<h4>Step 4 — Payment</h4>';
        echo '<div class="cfp-review"></div>';
        echo '<div class="cfp-payment" style="display:none"><div class="cfp-card-element"></div></div>';
        echo '<button class="button cfp-prev-4">Back</button> <button class="button button-primary cfp-pay">Pay</button>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';
        echo '</div>';
    }
}
