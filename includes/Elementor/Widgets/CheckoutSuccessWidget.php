<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;

class CheckoutSuccessWidget extends Widget_Base
{
    public function get_name() { return 'cfp_checkout_success'; }
    public function get_title() { return __('CFP – Checkout Success', 'classflow-pro'); }
    public function get_icon() { return 'eicon-check'; }
    public function get_categories() { return ['general']; }

    protected function render()
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-checkout-success', CFP_PLUGIN_URL . 'assets/js/checkout-success.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="cfp-checkout-success" data-nonce="' . esc_attr($nonce) . '">';
        echo '<div class="cfp-msg" aria-live="polite">' . esc_html__('Processing your checkout result…', 'classflow-pro') . '</div>';
        echo '</div>';
    }
}

