<?php
namespace ClassFlowPro\Admin;

class Bookings
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, true);
        wp_enqueue_script('cfp-admin-bookings', CFP_PLUGIN_URL . 'assets/js/admin-bookings.js', ['jquery'], '1.0.0', true);
        wp_localize_script('cfp-admin-bookings', 'CFP_ADMIN', [
            'restUrl' => esc_url_raw(rest_url('classflow/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'currency' => 'usd',
            'stripePublishableKey' => Settings::get('stripe_publishable_key', ''),
        ]);

        global $wpdb;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $schedules = $wpdb->prefix . 'cfp_schedules';
        $recent = $wpdb->get_results("SELECT b.*, s.class_id, s.start_time FROM $bookings b LEFT JOIN $schedules s ON s.id = b.schedule_id ORDER BY b.created_at DESC LIMIT 50", ARRAY_A);

        echo '<div class="wrap"><h1>' . esc_html__('Bookings', 'classflow-pro') . '</h1>';
        echo '<h2>' . esc_html__('Create Booking', 'classflow-pro') . '</h2>';
        echo '<div id="cfp-create-booking" class="cfp-panel" style="padding:12px;border:1px solid #e2e8f0;border-radius:6px;">';
        echo '<p><label>' . esc_html__('Schedule', 'classflow-pro') . '<br/>';
        echo '<select class="cfp-schedule-select"><option value="">' . esc_html__('— Select Schedule —', 'classflow-pro') . '</option>' . self::schedule_options() . '</select></label></p>';
        echo '<p><label>' . esc_html__('Customer Email (required if no user)', 'classflow-pro') . '<br/><input type="email" class="cfp-email" placeholder="customer@example.com" class="regular-text"/></label></p>';
        echo '<p><label>' . esc_html__('Customer Name', 'classflow-pro') . '<br/><input type="text" class="cfp-name" placeholder="Jane Doe" class="regular-text"/></label></p>';
        echo '<p><label><input type="checkbox" class="cfp-use-credits"/> ' . esc_html__('Use available credits (if customer is logged in)', 'classflow-pro') . '</label></p>';
        echo '<p><button class="button button-primary cfp-create-booking-btn">' . esc_html__('Create Booking', 'classflow-pro') . '</button></p>';
        echo '<div class="cfp-admin-payment" style="display:none">';
        echo '<div class="cfp-card-element"></div>';
        echo '<p><button class="button button-primary cfp-admin-pay-btn">' . esc_html__('Take Payment', 'classflow-pro') . '</button></p>';
        echo '</div>';
        echo '<div class="cfp-msg" aria-live="polite"></div>';
        echo '</div>';

        echo '<h2 style="margin-top:20px;">' . esc_html__('Recent Bookings', 'classflow-pro') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Class</th><th>Status</th><th>Amount</th><th>Email</th><th>Created</th></tr></thead><tbody>';
        foreach ($recent as $r) {
            $amount = number_format_i18n(((int)$r['amount_cents'])/100, 2) . ' ' . strtoupper($r['currency']);
            $title = $r['class_id'] ? \ClassFlowPro\Utils\Entities::class_name((int)$r['class_id']) : '-';
            echo '<tr>'
                . '<td>#' . intval($r['id']) . '</td>'
                . '<td>' . esc_html($title) . '</td>'
                . '<td>' . esc_html($r['status']) . '</td>'
                . '<td>' . esc_html($amount) . '</td>'
                . '<td>' . esc_html($r['customer_email'] ?: '-') . '</td>'
                . '<td>' . esc_html($r['created_at']) . '</td>'
                . '</tr>';
        }
        if (!$recent) echo '<tr><td colspan="6">' . esc_html__('No bookings yet.', 'classflow-pro') . '</td></tr>';
        echo '</tbody></table>';

        echo '</div>';
    }

    private static function schedule_options(): string
    {
        global $wpdb;
        $s = $wpdb->prefix . 'cfp_schedules';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $s WHERE start_time >= %s ORDER BY start_time ASC LIMIT 500", gmdate('Y-m-d H:i:s')), ARRAY_A);
        $out = '';
        foreach ($rows as $r) {
            $label = \ClassFlowPro\Utils\Entities::class_name((int)$r['class_id']) . ' — ' . gmdate('Y-m-d H:i', strtotime($r['start_time'])) . ' UTC';
            if (!empty($r['location_id'])) { $label .= ' — ' . get_the_title((int)$r['location_id']); }
            $out .= '<option value="' . intval($r['id']) . '">' . esc_html($label) . '</option>';
        }
        return $out;
    }
}
