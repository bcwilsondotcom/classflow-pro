<?php
namespace ClassFlowPro\Elementor;

class Module
{
    public static function register_widgets(): void
    {
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\BookClassWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\BuyPackageWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\BookPrivateWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\ClientDashboardWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\ClientPortalWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\IntakeFormWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\CalendarBookingWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\SmallCalendarBookingWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\BookingFunnelWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\StepBookingWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\CheckoutSuccessWidget());
        \Elementor\Plugin::instance()->widgets_manager->register(new Widgets\WaitlistResponseWidget());
    }
}
