<?php
namespace ClassFlowPro\Admin;

class Coupons
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_coupons';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('cfp_add_coupon')) {
            $code = strtoupper(sanitize_text_field($_POST['code'] ?? ''));
            $type = in_array($_POST['type'] ?? 'percent', ['percent','fixed'], true) ? $_POST['type'] : 'percent';
            $amount = (float)($_POST['amount'] ?? 0);
            $currency = sanitize_text_field($_POST['currency'] ?? '');
            $start_at = sanitize_text_field($_POST['start_at'] ?? '');
            $end_at = sanitize_text_field($_POST['end_at'] ?? '');
            $usage_limit = $_POST['usage_limit'] !== '' ? (int)$_POST['usage_limit'] : null;
            $usage_limit_per_user = $_POST['usage_limit_per_user'] !== '' ? (int)$_POST['usage_limit_per_user'] : null;
            $min_amount_cents = $_POST['min_amount_cents'] !== '' ? (int)$_POST['min_amount_cents'] : null;
            $classes = sanitize_text_field($_POST['classes'] ?? '');
            $locations = sanitize_text_field($_POST['locations'] ?? '');
            $instructors = sanitize_text_field($_POST['instructors'] ?? '');
            $resources = sanitize_text_field($_POST['resources'] ?? '');
            if ($code && $amount > 0) {
                $wpdb->insert($table, compact('code','type','amount','currency','start_at','end_at','usage_limit','usage_limit_per_user','min_amount_cents','classes','locations','instructors','resources'));
                echo '<div class="notice notice-success"><p>Coupon saved.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Invalid coupon data.</p></div>';
            }
        }

        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 200", ARRAY_A);
        echo '<div class="wrap"><h1>Coupons</h1>';
        echo '<h2>Add Coupon</h2>';
        echo '<form method="post">';
        wp_nonce_field('cfp_add_coupon');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Code</th><td><input name="code" type="text" class="regular-text" required></td></tr>';
        echo '<tr><th>Type</th><td><select name="type"><option value="percent">Percent</option><option value="fixed">Fixed Amount</option></select></td></tr>';
        echo '<tr><th>Amount</th><td><input name="amount" type="number" step="0.01" min="0.01" class="small-text" required></td></tr>';
        echo '<tr><th>Currency</th><td><input name="currency" type="text" class="small-text" placeholder="usd (for fixed)"></td></tr>';
        echo '<tr><th>Valid From</th><td><input name="start_at" type="datetime-local"></td></tr>';
        echo '<tr><th>Valid To</th><td><input name="end_at" type="datetime-local"></td></tr>';
        echo '<tr><th>Usage Limit (total)</th><td><input name="usage_limit" type="number" min="0" class="small-text" placeholder="unlimited"></td></tr>';
        echo '<tr><th>Usage Per User</th><td><input name="usage_limit_per_user" type="number" min="0" class="small-text" placeholder="unlimited"></td></tr>';
        echo '<tr><th>Min Amount (cents)</th><td><input name="min_amount_cents" type="number" min="0" class="small-text"></td></tr>';
        echo '<tr><th>Classes (IDs)</th><td><input name="classes" type="text" class="regular-text" placeholder="comma-separated IDs or empty for all"></td></tr>';
        echo '<tr><th>Locations (IDs)</th><td><input name="locations" type="text" class="regular-text"></td></tr>';
        echo '<tr><th>Instructors (IDs)</th><td><input name="instructors" type="text" class="regular-text"></td></tr>';
        echo '<tr><th>Resources (IDs)</th><td><input name="resources" type="text" class="regular-text"></td></tr>';
        echo '</tbody></table>';
        submit_button('Save Coupon');
        echo '</form>';

        echo '<h2>Existing Coupons</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Code</th><th>Type</th><th>Amount</th><th>Period</th><th>Limits</th><th>Scopes</th><th>Created</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $amount = $r['type']==='percent' ? (float)$r['amount'] . '%' : number_format_i18n((float)$r['amount'], 2) . ' ' . strtoupper($r['currency'] ?: '');
            $period = ($r['start_at'] ?: '—') . ' → ' . ($r['end_at'] ?: '—');
            $limits = 'Total: ' . ($r['usage_limit'] ?: '∞') . ', Per user: ' . ($r['usage_limit_per_user'] ?: '∞');
            $scopes = [];
            foreach (['classes','locations','instructors','resources'] as $k) { if (!empty($r[$k])) $scopes[] = $k; }
            echo '<tr>'
                . '<td><code>' . esc_html($r['code']) . '</code></td>'
                . '<td>' . esc_html($r['type']) . '</td>'
                . '<td>' . esc_html($amount) . '</td>'
                . '<td>' . esc_html($period) . '</td>'
                . '<td>' . esc_html($limits) . '</td>'
                . '<td>' . esc_html($scopes ? implode(', ', $scopes) : 'All') . '</td>'
                . '<td>' . esc_html($r['created_at']) . '</td>'
                . '</tr>';
        }
        if (!$rows) echo '<tr><td colspan="7">' . esc_html__('No coupons yet.', 'classflow-pro') . '</td></tr>';
        echo '</tbody></table></div>';
    }
}

