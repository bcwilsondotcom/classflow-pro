<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class CalendarBookingWidget extends Widget_Base
{
    public function get_name() { return 'cfp_calendar_booking'; }
    public function get_title() { return __('CFP – Calendar Booking', 'classflow-pro'); }
    public function get_icon() { return 'eicon-calendar'; }
    public function get_categories() { return ['general']; }

    protected function register_controls()
    {
        $this->start_controls_section('content', [ 'label' => __('Content', 'classflow-pro') ]);
        $this->add_control('class_id', [ 'label' => __('Class ID (optional)', 'classflow-pro'), 'type' => Controls_Manager::NUMBER, 'min' => 0, 'step' => 1 ]);
        $this->add_control('location_id', [ 'label' => __('Location ID (optional)', 'classflow-pro'), 'type' => Controls_Manager::NUMBER, 'min' => 0, 'step' => 1 ]);
        $this->end_controls_section();
    }

    protected function render()
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-calendar', CFP_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], '1.0.0', true);
        $settings = $this->get_settings_for_display();
        $nonce = wp_create_nonce('wp_rest');
        $class_id = isset($settings['class_id']) ? intval($settings['class_id']) : 0;
        $location_id = isset($settings['location_id']) ? intval($settings['location_id']) : 0;
        echo '<div class="cfp-calendar-booking" data-class-id="' . esc_attr($class_id) . '" data-location-id="' . esc_attr($location_id) . '" data-nonce="' . esc_attr($nonce) . '">';
        echo '<div class="cfp-cal-toolbar">';
        echo '<div class="cfp-cal-head">';
        echo '<button class="button cfp-cal-prev">◀</button> ';
        echo '<span class="cfp-cal-title"></span> ';
        echo '<button class="button cfp-cal-next">▶</button>';
        echo '</div>';
        echo '<div class="cfp-cal-views">';
        echo '<button class="button cfp-view" data-view="month">' . esc_html__('Month', 'classflow-pro') . '</button> ';
        echo '<button class="button cfp-view" data-view="week">' . esc_html__('Week', 'classflow-pro') . '</button> ';
        echo '<button class="button cfp-view" data-view="agenda">' . esc_html__('Agenda', 'classflow-pro') . '</button>';
        echo '</div>';
        echo '<div class="cfp-cal-filters">';
        echo '<select class="cfp-filter-class"><option value="">' . esc_html__('All Classes', 'classflow-pro') . '</option></select> ';
        echo '<select class="cfp-filter-location"><option value="">' . esc_html__('All Locations', 'classflow-pro') . '</option></select> ';
        echo '<select class="cfp-filter-instructor"><option value="">' . esc_html__('All Instructors', 'classflow-pro') . '</option></select>';
        echo '</div>';
        echo '</div>';
        echo '<div class="cfp-cal-grid"></div>';
        echo '<div class="cfp-agenda" style="display:none"></div>';
        echo '<div class="cfp-cal-sidebar">';
        echo '<h4>' . esc_html__('Book Session', 'classflow-pro') . '</h4>';
        echo '<div class="cfp-cal-selected"></div>';
        echo '<label>' . esc_html__('Your name', 'classflow-pro') . ' <input type="text" class="cfp-name"></label>';
        echo '<label>' . esc_html__('Email', 'classflow-pro') . ' <input type="email" class="cfp-email"></label>';
        echo '<label>' . esc_html__('Coupon code', 'classflow-pro') . ' <input type="text" class="cfp-coupon" placeholder="WELCOME10"></label>';
        echo '<label><input type="checkbox" class="cfp-use-credits"> ' . esc_html__('Use available credits', 'classflow-pro') . '</label>';
        echo '<button class="button button-primary cfp-book">' . esc_html__('Book', 'classflow-pro') . '</button>';
        echo '<div class="cfp-payment" style="display:none">';
        echo '<div class="cfp-card-element"></div>';
        echo '<button class="button button-primary cfp-pay">' . esc_html__('Pay', 'classflow-pro') . '</button>';
        echo '</div>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';
        echo '</div>';
    }
}
