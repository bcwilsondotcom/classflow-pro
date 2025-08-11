<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;

class WaitlistResponseWidget extends Widget_Base
{
    public function get_name() { return 'cfp_waitlist_response'; }
    public function get_title() { return __('CFP – Waitlist Response', 'classflow-pro'); }
    public function get_icon() { return 'eicon-response'; }
    public function get_categories() { return ['general']; }

    protected function render()
    {
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-waitlist-response', CFP_PLUGIN_URL . 'assets/js/waitlist-response.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="cfp-waitlist-response" data-nonce="' . esc_attr($nonce) . '">';
        echo '<div class="cfp-msg" aria-live="polite">' . esc_html__('Processing your response…', 'classflow-pro') . '</div>';
        echo '</div>';
    }
}

