<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;

class ClientDashboardWidget extends Widget_Base
{
    public function get_name() { return 'cfp_client_dashboard'; }
    public function get_title() { return __('CFP – Client Dashboard', 'classflow-pro'); }
    public function get_icon() { return 'eicon-user-circle-o'; }
    public function get_categories() { return ['general']; }

    protected function render()
    {
        if (!is_user_logged_in()) {
            echo '<p>' . esc_html__('Please log in to view your bookings and packages.', 'classflow-pro') . '</p>';
            return;
        }
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-client', CFP_PLUGIN_URL . 'assets/js/client.js', ['jquery'], '1.0.0', true);
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="cfp-client-dashboard" data-nonce="' . esc_attr($nonce) . '">';
        echo '<h3>' . esc_html__('My Dashboard', 'classflow-pro') . '</h3>';
        echo '<div class="cfp-kpis" style="display:flex;gap:12px;flex-wrap:wrap;">';
        echo '<div class="cfp-kpi"><strong>' . esc_html__('Credits', 'classflow-pro') . ':</strong> <span class="cfp-credits">—</span></div>';
        echo '</div>';
        echo '<h4>' . esc_html__('Upcoming Bookings', 'classflow-pro') . '</h4>';
        echo '<div class="cfp-upcoming"></div>';
        echo '<h4>' . esc_html__('Past Bookings', 'classflow-pro') . '</h4>';
        echo '<div class="cfp-past"></div>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';
    }
}

