<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;

class BuyPackageWidget extends Widget_Base
{
    public function get_name() { return 'cfp_buy_package'; }
    public function get_title() { return __('CFP – Buy Package', 'classflow-pro'); }
    public function get_icon() { return 'eicon-cart'; }
    public function get_categories() { return ['general']; }

    protected function render()
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-booking');
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="cfp-buy-package" data-nonce="' . esc_attr($nonce) . '">';
        echo '<h4>' . esc_html__('Purchase Class Package', 'classflow-pro') . '</h4>';
        echo '<label>' . esc_html__('Package Name', 'classflow-pro') . ' <input type="text" class="cfp-pkg-name" placeholder="10-Class Pack"></label>';
        echo '<label>' . esc_html__('Credits', 'classflow-pro') . ' <input type="number" class="cfp-pkg-credits" value="10" min="1"></label>';
        echo '<label>' . esc_html__('Price (cents)', 'classflow-pro') . ' <input type="number" class="cfp-pkg-price" value="15000" min="50"></label>';
        echo '<div class="cfp-card-element"></div>';
        echo '<button class="button button-primary cfp-pkg-pay">' . esc_html__('Pay & Add Credits', 'classflow-pro') . '</button>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';
    }
}
