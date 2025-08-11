<?php
namespace ClassFlowPro\Elementor\Widgets;

use Elementor\Widget_Base;

class ClientPortalWidget extends Widget_Base
{
    public function get_name() { return 'cfp_user_portal'; }
    public function get_title() { return __('CFP – Client Portal', 'classflow-pro'); }
    public function get_icon() { return 'eicon-lock-user'; }
    public function get_categories() { return ['general']; }

    protected function render()
    {
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(esc_url(add_query_arg([], home_url($_SERVER['REQUEST_URI'] ?? '/'))));
            echo '<p>' . sprintf(esc_html__('Please %slog in%s to view your portal.', 'classflow-pro'), '<a href="' . esc_url($login_url) . '">', '</a>') . '</p>';
            return;
        }
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-portal', CFP_PLUGIN_URL . 'assets/js/portal.js', ['jquery'], '1.0.0', true);
        // Localize data for portal.js
        wp_localize_script('cfp-portal', 'CFP_DATA', [
            'restUrl' => esc_url_raw(rest_url('classflow/v1/')),
            'businessTimezone' => \ClassFlowPro\Admin\Settings::get('business_timezone', (function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC')),
        ]);
        $nonce = wp_create_nonce('wp_rest');
        $show_thanks = isset($_GET['cfp_checkout']) && sanitize_text_field((string)$_GET['cfp_checkout']) === 'success';
        echo '<div class="cfp-portal" data-nonce="' . esc_attr($nonce) . '">';
        if ($show_thanks) {
            echo '<div class="cfp-portal-banner cfp-portal-success" role="status">' . esc_html__('Thank you! Your checkout completed successfully.', 'classflow-pro') . '</div>';
        }
        echo '<div class="cfp-portal-profile"><h3>' . esc_html__('Your Profile', 'classflow-pro') . '</h3><div class="cfp-profile-fields">';
        $u = wp_get_current_user();
        $display = $u->display_name ?: trim(($u->user_firstname . ' ' . $u->user_lastname));
        echo '<p><strong>' . esc_html__('Name:', 'classflow-pro') . '</strong> ' . esc_html($display) . '</p>';
        echo '<p><strong>' . esc_html__('Email:', 'classflow-pro') . '</strong> ' . esc_html($u->user_email) . '</p>';
        echo '<div class="cfp-profile-edit">'
            . '<label>' . esc_html__('Phone', 'classflow-pro') . ' <input type="tel" class="cfp-prof-phone"/></label>'
            . '<label>' . esc_html__('Date of Birth', 'classflow-pro') . ' <input type="date" class="cfp-prof-dob"/></label>'
            . '<label>' . esc_html__('Emergency Contact Name', 'classflow-pro') . ' <input type="text" class="cfp-prof-emg-name"/></label>'
            . '<label>' . esc_html__('Emergency Contact Phone', 'classflow-pro') . ' <input type="tel" class="cfp-prof-emg-phone"/></label>'
            . '<button class="button button-primary cfp-prof-save">' . esc_html__('Save Profile', 'classflow-pro') . '</button>'
            . '<div class="cfp-msg" aria-live="polite"></div>'
            . '</div>';
        echo '</div></div>';
        echo '<div class="cfp-portal-upcoming"><h3>' . esc_html__('Upcoming Classes', 'classflow-pro') . '</h3><div class="cfp-list cfp-upcoming-list">' . esc_html__('Loading…', 'classflow-pro') . '</div></div>';
        echo '<div class="cfp-portal-past"><h3>' . esc_html__('Past Classes', 'classflow-pro') . '</h3><div class="cfp-list cfp-past-list">' . esc_html__('Loading…', 'classflow-pro') . '</div></div>';
        echo '<div class="cfp-portal-credits"><h3>' . esc_html__('Credits', 'classflow-pro') . '</h3><div class="cfp-credits">' . esc_html__('Loading…', 'classflow-pro') . '</div></div>';
        echo '<div class="cfp-portal-notes"><h3>' . esc_html__('Notes', 'classflow-pro') . '</h3><div class="cfp-notes-list">' . esc_html__('Loading…', 'classflow-pro') . '</div></div>';
        echo '</div>';
    }
}

