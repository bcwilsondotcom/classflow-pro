<?php
namespace ClassFlowPro\Admin;

class System
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb; $t=$wpdb->prefix.'cfp_webhook_events';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cfp_action']) && $_POST['cfp_action']==='retry' && check_admin_referer('cfp_retry_event')) {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // Mark failed to retry now; the cron handler will pick up or retry inline
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A);
                if ($row) {
                    try {
                        \ClassFlowPro\Payments\Webhooks::retry_failed();
                        echo '<div class="notice notice-success"><p>' . esc_html__('Retry triggered.', 'classflow-pro') . '</p></div>';
                    } catch (\Throwable $e) {}
                }
            }
        }
        $status = isset($_GET['status']) ? sanitize_key((string)$_GET['status']) : 'all';
        $where = $status!=='all' ? $wpdb->prepare('WHERE status=%s', $status) : '';
        $rows = $wpdb->get_results("SELECT * FROM $t $where ORDER BY id DESC LIMIT 200", ARRAY_A);
        echo '<div class="wrap"><h1>' . esc_html__('System / Webhooks', 'classflow-pro') . '</h1>';
        $filters = ['all'=>'All','received'=>'Received','processed'=>'Processed','failed'=>'Failed'];
        echo '<p>';
        foreach ($filters as $k=>$label) {
            $url = esc_url(add_query_arg(['page'=>'classflow-pro-system','status'=>$k], admin_url('admin.php')));
            $cls = $status===$k?' style="font-weight:bold;"':'';
            echo '<a href="' . $url . '"' . $cls . '>' . esc_html($label) . '</a> ';
        }
        echo '</p>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Provider</th><th>Event ID</th><th>Type</th><th>Status</th><th>Retries</th><th>Last Error</th><th>Received</th><th>Processed</th><th>Actions</th></tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="10">' . esc_html__('No events', 'classflow-pro') . '</td></tr>';
        foreach ($rows as $r) {
            echo '<tr>'
                . '<td>' . (int)$r['id'] . '</td>'
                . '<td>' . esc_html($r['provider']) . '</td>'
                . '<td><code>' . esc_html($r['event_id']) . '</code></td>'
                . '<td>' . esc_html($r['event_type'] ?: '') . '</td>'
                . '<td>' . esc_html($r['status']) . '</td>'
                . '<td>' . (int)$r['retry_count'] . '</td>'
                . '<td>' . esc_html(mb_strimwidth((string)$r['last_error'], 0, 80, '…')) . '</td>'
                . '<td>' . esc_html($r['received_at']) . '</td>'
                . '<td>' . esc_html($r['processed_at'] ?: '') . '</td>'
                . '<td>';
            if ($r['status']==='failed') {
                echo '<form method="post" style="display:inline;">'; wp_nonce_field('cfp_retry_event');
                echo '<input type="hidden" name="cfp_action" value="retry"/><input type="hidden" name="id" value="' . (int)$r['id'] . '"/>';
                echo '<button class="button">' . esc_html__('Retry', 'classflow-pro') . '</button></form>';
            } else { echo '—'; }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

