<?php
namespace ClassFlowPro\Admin;

class Dashboard
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $thirty = gmdate('Y-m-d H:i:s', strtotime('-30 days'));

        $tblSchedules = $wpdb->prefix . 'cfp_schedules';
        $tblBookings = $wpdb->prefix . 'cfp_bookings';
        $tblPackages = $wpdb->prefix . 'cfp_packages';
        $tblTx = $wpdb->prefix . 'cfp_transactions';

        // KPIs
        $upcoming_schedules = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tblSchedules WHERE start_time >= %s", $now));
        $bookings_30 = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tblBookings WHERE created_at >= %s", $thirty));
        $revenue_30 = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount_cents),0) FROM $tblTx WHERE type IN ('class_payment','package_purchase') AND status='succeeded' AND created_at >= %s", $thirty));
        $clients_total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT IF(user_id IS NOT NULL AND user_id <> 0, CONCAT('u:', user_id), CONCAT('e:', customer_email))) FROM $tblBookings WHERE ((user_id IS NOT NULL AND user_id <> 0) OR (customer_email IS NOT NULL AND customer_email <> '')) AND created_at >= %s", $thirty));
        $credits_outstanding = (int)$wpdb->get_var("SELECT COALESCE(SUM(credits_remaining),0) FROM $tblPackages");

        $recent_bookings = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tblBookings ORDER BY created_at DESC LIMIT %d", 10), ARRAY_A);
        $recent_tx = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tblTx ORDER BY created_at DESC LIMIT %d", 10), ARRAY_A);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ClassFlow Pro Dashboard', 'classflow-pro') . '</h1>';
        echo '<p>' . esc_html__('Overview of schedules, bookings, revenue, and clients.', 'classflow-pro') . '</p>';

        echo '<div class="cfp-cards" style="display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:12px;">';
        self::card(__('Upcoming Schedules', 'classflow-pro'), number_format_i18n($upcoming_schedules));
        self::card(__('Bookings (30d)', 'classflow-pro'), number_format_i18n($bookings_30));
        self::card(__('Revenue (30d)', 'classflow-pro'), self::format_money($revenue_30));
        self::card(__('Clients (30d)', 'classflow-pro'), number_format_i18n($clients_total));
        self::card(__('Credits Outstanding', 'classflow-pro'), number_format_i18n($credits_outstanding));
        echo '</div>';

        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">';
        echo '<div class="cfp-panel"><h2>' . esc_html__('Recent Bookings', 'classflow-pro') . '</h2><table class="widefat striped"><thead><tr><th>ID</th><th>Status</th><th>Amount</th><th>Email</th><th>Created</th></tr></thead><tbody>';
        foreach ($recent_bookings as $b) {
            echo '<tr>'
                . '<td>#' . intval($b['id']) . '</td>'
                . '<td>' . esc_html($b['status']) . '</td>'
                . '<td>' . self::format_money((int)$b['amount_cents'], $b['currency']) . '</td>'
                . '<td>' . esc_html($b['customer_email'] ?: '-') . '</td>'
                . '<td>' . esc_html($b['created_at']) . '</td>'
                . '</tr>';
        }
        if (!$recent_bookings) echo '<tr><td colspan="5">' . esc_html__('No bookings yet.', 'classflow-pro') . '</td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="cfp-panel"><h2>' . esc_html__('Recent Transactions', 'classflow-pro') . '</h2><table class="widefat striped"><thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Amount</th><th>Created</th></tr></thead><tbody>';
        foreach ($recent_tx as $t) {
            echo '<tr>'
                . '<td>#' . intval($t['id']) . '</td>'
                . '<td>' . esc_html($t['type']) . '</td>'
                . '<td>' . esc_html($t['status']) . '</td>'
                . '<td>' . self::format_money((int)$t['amount_cents'], $t['currency']) . '</td>'
                . '<td>' . esc_html($t['created_at']) . '</td>'
                . '</tr>';
        }
        if (!$recent_tx) echo '<tr><td colspan="5">' . esc_html__('No transactions.', 'classflow-pro') . '</td></tr>';
        echo '</tbody></table></div>';
        echo '</div>';

        echo '</div>';
    }

    private static function card(string $title, string $value): void
    {
        echo '<div class="cfp-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:6px;padding:14px;">';
        echo '<div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;">' . esc_html($title) . '</div>';
        echo '<div style="font-size:22px;font-weight:600;margin-top:6px;">' . $value . '</div>';
        echo '</div>';
    }

    private static function format_money(int $amount_cents, string $currency = null): string
    {
        $currency = $currency ?: Settings::get('currency', 'usd');
        $amount = $amount_cents / 100;
        $symbol = strtoupper($currency);
        return number_format_i18n($amount, 2) . ' ' . esc_html($symbol);
    }
}
