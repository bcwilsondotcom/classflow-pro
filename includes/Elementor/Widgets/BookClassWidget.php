<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class BookClassWidget extends Widget_Base
{
    public function get_name() { return 'cfp_book_class'; }
    public function get_title() { return __('CFP – Book Class', 'classflow-pro'); }
    public function get_icon() { return 'eicon-calendar'; }
    public function get_categories() { return ['general']; }

    protected function register_controls()
    {
        $this->start_controls_section('content', [ 'label' => __('Content', 'classflow-pro') ]);
        $this->add_control('class_id', [
            'label' => __('Class', 'classflow-pro'),
            'type' => Controls_Manager::NUMBER,
            'min' => 0,
            'step' => 1,
            'description' => __('Optional: filter schedules by Class post ID', 'classflow-pro'),
        ]);
        $this->add_control('location_id', [
            'label' => __('Location', 'classflow-pro'),
            'type' => Controls_Manager::NUMBER,
            'min' => 0,
            'step' => 1,
            'description' => __('Optional: filter schedules by Location post ID', 'classflow-pro'),
        ]);
        $this->end_controls_section();
    }

    protected function render()
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-booking');
        $settings = $this->get_settings_for_display();
        $class_id = isset($settings['class_id']) ? intval($settings['class_id']) : 0;
        $location_id = isset($settings['location_id']) ? intval($settings['location_id']) : 0;
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="cfp-book-class" data-class-id="' . esc_attr($class_id) . '" data-location-id="' . esc_attr($location_id) . '" data-nonce="' . esc_attr($nonce) . '">';
        echo '<div class="cfp-book-class__filters">';
        echo '<label>' . esc_html__('Date from', 'classflow-pro') . ' <input type="date" class="cfp-date-from"></label> ';
        echo '<label>' . esc_html__('Date to', 'classflow-pro') . ' <input type="date" class="cfp-date-to"></label> ';
        echo '<button class="button cfp-load-schedules">' . esc_html__('Load Schedules', 'classflow-pro') . '</button>';
        echo '</div>';
        echo '<div class="cfp-schedules"></div>';
        echo '<div class="cfp-booking-form" style="display:none">';
        echo '<h4>' . esc_html__('Book Selected Class', 'classflow-pro') . '</h4>';
        echo '<label>' . esc_html__('Your name', 'classflow-pro') . ' <input type="text" class="cfp-name"></label>';
        echo '<label>' . esc_html__('Email', 'classflow-pro') . ' <input type="email" class="cfp-email" autocomplete="email"></label>';
        echo '<label>' . esc_html__('Phone', 'classflow-pro') . ' <input type="tel" class="cfp-phone" autocomplete="tel"></label>';
        echo '<div class="cfp-account-fields" style="display:block;margin:8px 0;">';
        echo '<label>' . esc_html__('Create password', 'classflow-pro') . ' <input type="password" class="cfp-password" autocomplete="new-password"></label> ';
        echo '<small style="display:block;color:#64748b;">' . esc_html__('If you don\'t have an account, we\'ll create one using this password.', 'classflow-pro') . '</small>';
        echo '<label style="display:block;margin-top:6px;"><input type="checkbox" class="cfp-sms-optin"> ' . esc_html__('Send me text messages about my bookings (optional)', 'classflow-pro') . '</label>';
        echo '</div>';
        
        echo '<label><input type="checkbox" class="cfp-use-credits"> ' . esc_html__('Use available credits', 'classflow-pro') . '</label>';
        echo '<button class="button button-primary cfp-book">' . esc_html__('Book Now', 'classflow-pro') . '</button>';
        echo '<div class="cfp-payment" style="display:none">';
        echo '<div class="cfp-prb" style="display:none;margin:8px 0;"><div class="cfp-prb-element"></div></div>';
        echo '<div class="cfp-card-element"></div>';
        echo '<button class="button button-primary cfp-pay">' . esc_html__('Pay', 'classflow-pro') . '</button>';
        echo '</div>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';
        echo '</div>';
    }
}
