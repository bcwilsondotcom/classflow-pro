<?php
namespace ClassFlowPro\Admin;

class Payouts
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        $from = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : gmdate('Y-m-01');
        $to = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : gmdate('Y-m-d');
        $from_dt = gmdate('Y-m-d 00:00:00', strtotime($from));
        $to_dt = gmdate('Y-m-d 23:59:59', strtotime($to));
        global $wpdb;
        $tx = $wpdb->prefix . 'cfp_transactions';
        $bk = $wpdb->prefix . 'cfp_bookings';
        $sc = $wpdb->prefix . 'cfp_schedules';
        $ins = $wpdb->prefix . 'cfp_instructors';
        // Compute counts per instructor for flat payouts
        $counts = $wpdb->get_results($wpdb->prepare(
            "SELECT s.instructor_id, COUNT(*) AS cnt
             FROM $tx t JOIN $bk b ON b.id=t.booking_id JOIN $sc s ON s.id=b.schedule_id
             WHERE t.type='class_payment' AND t.processor='stripe' AND t.status='succeeded' AND t.created_at BETWEEN %s AND %s AND s.instructor_id IS NOT NULL
             GROUP BY s.instructor_id",
            $from_dt, $to_dt
        ), ARRAY_A);
        $countMap=[]; foreach ($counts as $c){ $countMap[(int)$c['instructor_id']] = (int)$c['cnt']; }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.instructor_id,
                    SUM(t.amount_cents) AS gross_cents,
                    SUM(t.fee_amount_cents) AS fee_cents,
                    COALESCE(i.payout_mode,'percent') AS mode,
                    COALESCE(i.payout_percent,0) AS pct,
                    COALESCE(i.payout_flat_cents,0) AS flat
             FROM $tx t
             JOIN $bk b ON b.id = t.booking_id
             JOIN $sc s ON s.id = b.schedule_id
             LEFT JOIN $ins i ON i.id = s.instructor_id
             WHERE t.type = 'class_payment' AND t.processor = 'stripe' AND t.status='succeeded' AND t.created_at BETWEEN %s AND %s AND s.instructor_id IS NOT NULL
             GROUP BY s.instructor_id, i.payout_mode, i.payout_percent, i.payout_flat_cents ORDER BY gross_cents DESC",
            $from_dt, $to_dt
        ), ARRAY_A);

        echo '<div class="wrap"><h1>Instructor Payouts</h1>';
        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="classflow-pro-payouts" />';
        echo '<label>From <input type="date" name="from" value="' . esc_attr($from) . '"></label> ';
        echo '<label>To <input type="date" name="to" value="' . esc_attr($to) . '"></label> ';
        submit_button(__('Filter', 'classflow-pro'), 'secondary', '', false);
        $url = wp_nonce_url(admin_url('admin-post.php?action=cfp_export_payouts&from=' . rawurlencode($from) . '&to=' . rawurlencode($to)), 'cfp_export_payouts');
        echo ' <a class="button" href="' . esc_url($url) . '">Export CSV</a>';
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr><th>Instructor</th><th>Gross</th><th>Platform Fee</th><th>Payout Share</th><th>Count</th></tr></thead><tbody>';
        $currency = 'usd';
        foreach ($rows as $r) {
            $name = $r['instructor_id'] ? get_the_title((int)$r['instructor_id']) : '—';
            $gross = (int)$r['gross_cents'];
            $fee = (int)$r['fee_cents'];
            $mode = $r['mode'] ?: 'percent';
            if ($mode === 'flat') {
                $cnt = $countMap[(int)$r['instructor_id']] ?? 0;
                $payout = (int)$r['flat'] * $cnt;
            } else {
                $payout = (int)round(((int)$r['gross_cents']) * ((float)$r['pct']) / 100);
            }
            echo '<tr>'
                . '<td>' . esc_html($name) . ' (#' . intval($r['instructor_id']) . ')</td>'
                . '<td>' . number_format_i18n($gross/100, 2) . ' ' . esc_html(strtoupper($currency)) . '</td>'
                . '<td>' . number_format_i18n($fee/100, 2) . '</td>'
                . '<td><strong>' . number_format_i18n($payout/100, 2) . '</strong></td>'
                . '<td>' . intval($r['cnt']) . '</td>'
                . '</tr>';
        }
        if (!$rows) echo '<tr><td colspan="5">No payouts in range.</td></tr>';
        echo '</tbody></table></div>';
    }

    public static function export_csv(): void
    {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('cfp_export_payouts');
        $from = sanitize_text_field(wp_unslash($_GET['from'] ?? ''));
        $to = sanitize_text_field(wp_unslash($_GET['to'] ?? ''));
        $from_dt = gmdate('Y-m-d 00:00:00', strtotime($from));
        $to_dt = gmdate('Y-m-d 23:59:59', strtotime($to));
        global $wpdb;
        $tx = $wpdb->prefix . 'cfp_transactions';
        $bk = $wpdb->prefix . 'cfp_bookings';
        $sc = $wpdb->prefix . 'cfp_schedules';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.instructor_id, SUM(t.amount_cents) AS gross_cents, SUM(t.fee_amount_cents) AS fee_cents, COUNT(*) AS cnt
             FROM $tx t JOIN $bk b ON b.id = t.booking_id JOIN $sc s ON s.id = b.schedule_id
             WHERE t.type = 'class_payment' AND t.processor = 'stripe' AND t.status='succeeded' AND t.created_at BETWEEN %s AND %s AND s.instructor_id IS NOT NULL
             GROUP BY s.instructor_id ORDER BY gross_cents DESC",
            $from_dt, $to_dt
        ), ARRAY_A);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="payouts_' . gmdate('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['instructor_id','instructor_name','gross_cents','platform_fee_cents','payout_cents','count']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['instructor_id'],
                get_the_title((int)$r['instructor_id']),
                (int)$r['gross_cents'],
                (int)$r['fee_cents'],
                (int)$r['gross_cents'] - (int)$r['fee_cents'],
                (int)$r['cnt'],
            ]);
        }
        fclose($out);
        exit;
    }
}
