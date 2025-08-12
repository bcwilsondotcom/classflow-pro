<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;

class BookingFunnelWidget extends Widget_Base
{
    public function get_name() { return 'cfp_booking_funnel'; }
    public function get_title() { return __('CFP – Booking Funnel', 'classflow-pro'); }
    public function get_icon() { return 'eicon-flow'; }
    public function get_categories() { return ['general']; }

    protected function render()
    {
        echo do_shortcode('[cfp_booking_funnel]');
    }
}

