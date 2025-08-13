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
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Class</th><th>Status</th><th>Amount</th><th>Email</th><th>Created</th><th>Actions</th></tr></thead><tbody>';
        foreach ($recent as $r) {
            $amount = number_format_i18n(((int)$r['amount_cents'])/100, 2) . ' ' . strtoupper($r['currency']);
            $title = $r['class_id'] ? \ClassFlowPro\Utils\Entities::class_name((int)$r['class_id']) : '-';
            $actions = '';
            
            // Add refund action for confirmed bookings with payments
            if ($r['status'] === 'confirmed' && !empty($r['payment_intent_id']) && (int)$r['amount_cents'] > 0) {
                $actions .= '<button class="button button-small cfp-refund-btn" data-booking-id="' . intval($r['id']) . '" data-amount="' . esc_attr($amount) . '">' . esc_html__('Refund', 'classflow-pro') . '</button> ';
            }
            
            // Add cancel action for confirmed bookings
            if ($r['status'] === 'confirmed') {
                $actions .= '<button class="button button-small cfp-cancel-btn" data-booking-id="' . intval($r['id']) . '">' . esc_html__('Cancel', 'classflow-pro') . '</button>';
            }
            
            // Show refund details for refunded bookings
            if ($r['status'] === 'refunded') {
                $transactions = $wpdb->prefix . 'cfp_transactions';
                $refund = $wpdb->get_row($wpdb->prepare("SELECT * FROM $transactions WHERE booking_id = %d AND type = 'refund' ORDER BY created_at DESC LIMIT 1", $r['id']), ARRAY_A);
                if ($refund) {
                    $refund_amount = number_format_i18n(abs((int)$refund['amount_cents'])/100, 2) . ' ' . strtoupper($refund['currency']);
                    $actions .= '<span class="description">' . sprintf(__('Refunded %s', 'classflow-pro'), $refund_amount) . '</span>';
                }
            }
            
            echo '<tr>'
                . '<td>#' . intval($r['id']) . '</td>'
                . '<td>' . esc_html($title) . '</td>'
                . '<td><span class="cfp-status-' . esc_attr($r['status']) . '">' . esc_html($r['status']) . '</span></td>'
                . '<td>' . esc_html($amount) . '</td>'
                . '<td>' . esc_html($r['customer_email'] ?: '-') . '</td>'
                . '<td>' . esc_html($r['created_at']) . '</td>'
                . '<td>' . $actions . '</td>'
                . '</tr>';
        }
        if (!$recent) echo '<tr><td colspan="7">' . esc_html__('No bookings yet.', 'classflow-pro') . '</td></tr>';
        echo '</tbody></table>';
        
        // Add refund modal
        echo '<div id="cfp-refund-modal" style="display:none;">';
        echo '<div class="cfp-modal-overlay"></div>';
        echo '<div class="cfp-modal-content" style="background:white;padding:20px;border-radius:8px;max-width:500px;margin:50px auto;position:relative;">';
        echo '<h3>' . esc_html__('Process Refund', 'classflow-pro') . '</h3>';
        echo '<p>' . esc_html__('Booking ID:', 'classflow-pro') . ' <strong id="cfp-refund-booking-id"></strong></p>';
        echo '<p>' . esc_html__('Original Amount:', 'classflow-pro') . ' <strong id="cfp-refund-original-amount"></strong></p>';
        echo '<p><label>' . esc_html__('Refund Type:', 'classflow-pro') . '<br/>';
        echo '<select id="cfp-refund-type" class="regular-text">';
        echo '<option value="full">' . esc_html__('Full Refund', 'classflow-pro') . '</option>';
        echo '<option value="partial">' . esc_html__('Partial Refund', 'classflow-pro') . '</option>';
        echo '<option value="credit">' . esc_html__('Studio Credit Only', 'classflow-pro') . '</option>';
        echo '</select></label></p>';
        echo '<p id="cfp-partial-amount-wrap" style="display:none;"><label>' . esc_html__('Refund Amount ($):', 'classflow-pro') . '<br/>';
        echo '<input type="number" id="cfp-refund-amount" step="0.01" min="0" class="regular-text"/></label></p>';
        echo '<p><label>' . esc_html__('Reason (optional):', 'classflow-pro') . '<br/>';
        echo '<textarea id="cfp-refund-reason" class="regular-text" rows="3" style="width:100%;"></textarea></label></p>';
        echo '<p>';
        echo '<button class="button button-primary" id="cfp-process-refund">' . esc_html__('Process Refund', 'classflow-pro') . '</button> ';
        echo '<button class="button" id="cfp-cancel-refund">' . esc_html__('Cancel', 'classflow-pro') . '</button>';
        echo '</p>';
        echo '<div id="cfp-refund-msg" aria-live="polite"></div>';
        echo '</div>';
        echo '</div>';

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
