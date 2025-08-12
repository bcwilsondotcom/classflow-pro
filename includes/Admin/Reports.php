<?php
namespace ClassFlowPro\Admin;

class Reports
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        $date_from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : gmdate('Y-m-01');
        $date_to = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : gmdate('Y-m-d');
        $from_ts = strtotime($date_from . ' 00:00:00 UTC');
        $to_ts = strtotime($date_to . ' 23:59:59 UTC');
        if (!$from_ts || !$to_ts) {
            $from_ts = strtotime(gmdate('Y-m-01 00:00:00'));
            $to_ts = time();
        }
        global $wpdb;
        $tx = $wpdb->prefix . 'cfp_transactions';
        $bk = $wpdb->prefix . 'cfp_bookings';
        $sc = $wpdb->prefix . 'cfp_schedules';
        $from = gmdate('Y-m-d H:i:s', $from_ts);
        $to = gmdate('Y-m-d H:i:s', $to_ts);

        // Revenue
        $revenue_cents = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount_cents),0) FROM $tx WHERE status='succeeded' AND type IN ('class_payment','package_purchase') AND created_at BETWEEN %s AND %s", $from, $to));
        $refunds_cents = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount_cents),0) FROM $tx WHERE status='succeeded' AND type='refund' AND created_at BETWEEN %s AND %s", $from, $to));
        $net_cents = $revenue_cents + $refunds_cents; // refunds are negative

        // Occupancy: upcoming schedules in range with booked counts
        $occ_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.class_id, s.start_time, s.capacity, (
                SELECT COUNT(*) FROM $bk b WHERE b.schedule_id = s.id AND b.status IN ('pending','confirmed')
            ) AS booked
            FROM $sc s WHERE s.start_time BETWEEN %s AND %s ORDER BY s.start_time ASC LIMIT 500",
            $from, $to
        ), ARRAY_A);

        echo '<div class="wrap"><h1>' . esc_html__('Reports', 'classflow-pro') . '</h1>';
        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="classflow-pro-reports" />';
        echo '<label>From <input type="date" name="from" value="' . esc_attr(gmdate('Y-m-d', $from_ts)) . '"></label> ';
        echo '<label>To <input type="date" name="to" value="' . esc_attr(gmdate('Y-m-d', $to_ts)) . '"></label> ';
        submit_button(__('Filter', 'classflow-pro'), 'secondary', '', false);
        $nonce = wp_create_nonce('cfp_export_csv');
        $base = admin_url('admin-post.php?action=cfp_export_csv&_wpnonce=' . $nonce . '&from=' . rawurlencode(gmdate('Y-m-d', $from_ts)) . '&to=' . rawurlencode(gmdate('Y-m-d', $to_ts)));
        echo ' <a class="button" href="' . esc_url($base . '&type=revenue') . '">Export Revenue CSV</a>';
        echo ' <a class="button" href="' . esc_url($base . '&type=bookings') . '">Export Bookings CSV</a>';
        echo '</form>';

        echo '<h2>' . esc_html__('Revenue', 'classflow-pro') . '</h2>';
        echo '<p><strong>' . esc_html(number_format_i18n($net_cents/100, 2)) . '</strong> USD (gross ' . esc_html(number_format_i18n($revenue_cents/100,2)) . ', refunds ' . esc_html(number_format_i18n($refunds_cents/100,2)) . ')</p>';

        echo '<h2>' . esc_html__('Occupancy', 'classflow-pro') . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Schedule</th><th>Start</th><th>Capacity</th><th>Booked</th><th>Fill %</th></tr></thead><tbody>';
        foreach ($occ_rows as $r) {
            $fill = (int)$r['capacity'] > 0 ? round(100 * (int)$r['booked'] / (int)$r['capacity']) : 0;
            echo '<tr>'
                . '<td>#' . intval($r['id']) . ' — ' . esc_html(\ClassFlowPro\Utils\Entities::class_name((int)$r['class_id'])) . '</td>'
                . '<td>' . esc_html($r['start_time']) . '</td>'
                . '<td>' . intval($r['capacity']) . '</td>'
                . '<td>' . intval($r['booked']) . '</td>'
                . '<td>' . $fill . '%</td>'
                . '</tr>';
        }
        if (!$occ_rows) echo '<tr><td colspan="5">' . esc_html__('No schedules in range.', 'classflow-pro') . '</td></tr>';
        echo '</tbody></table>';

        echo '</div>';
    }

    public static function export_csv(): void
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('cfp_export_csv');
        $type = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : '';
        $date_from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : gmdate('Y-m-01');
        $date_to = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : gmdate('Y-m-d');
        $from = gmdate('Y-m-d 00:00:00', strtotime($date_from));
        $to = gmdate('Y-m-d 23:59:59', strtotime($date_to));
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cfp_' . $type . '_' . gmdate('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        global $wpdb;
        if ($type === 'revenue') {
            $tx = $wpdb->prefix . 'cfp_transactions';
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tx WHERE created_at BETWEEN %s AND %s ORDER BY created_at ASC", $from, $to), ARRAY_A);
            fputcsv($out, ['id','type','status','amount_cents','currency','booking_id','user_id','created_at']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['id'],$r['type'],$r['status'],$r['amount_cents'],$r['currency'],$r['booking_id'],$r['user_id'],$r['created_at']]);
            }
        } else {
            $bk = $wpdb->prefix . 'cfp_bookings';
            $sc = $wpdb->prefix . 'cfp_schedules';
            $rows = $wpdb->get_results($wpdb->prepare("SELECT b.*, s.class_id, s.start_time FROM $bk b LEFT JOIN $sc s ON s.id=b.schedule_id WHERE b.created_at BETWEEN %s AND %s ORDER BY b.created_at ASC", $from, $to), ARRAY_A);
            fputcsv($out, ['id','status','amount_cents','discount_cents','currency','coupon_code','user_id','email','class_id','schedule_id','start_time','created_at']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['id'],$r['status'],$r['amount_cents'],$r['discount_cents'],$r['currency'],$r['coupon_code'],$r['user_id'],$r['customer_email'],$r['class_id'],$r['schedule_id'],$r['start_time'],$r['created_at']]);
            }
        }
        fclose($out);
        exit;
    }
}
