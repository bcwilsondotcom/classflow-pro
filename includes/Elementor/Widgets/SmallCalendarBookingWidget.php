<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class SmallCalendarBookingWidget extends Widget_Base
{
    public function get_name() { return 'cfp_small_calendar_booking'; }
    public function get_title() { return __('CFP – Small Calendar Booking', 'classflow-pro'); }
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
        $s = $this->get_settings_for_display();
        $class_id = isset($s['class_id']) ? intval($s['class_id']) : 0;
        $location_id = isset($s['location_id']) ? intval($s['location_id']) : 0;
        echo do_shortcode('[cfp_small_calendar_booking class_id="' . esc_attr($class_id) . '" location_id="' . esc_attr($location_id) . '"]');
    }
}

