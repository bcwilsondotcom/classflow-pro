<?php
namespace ClassFlowPro\Admin;

use ClassFlowPro\Logging\Logger;

class Logs
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        $level = isset($_GET['level']) ? sanitize_text_field(wp_unslash($_GET['level'])) : '';
        $source = isset($_GET['source']) ? sanitize_text_field(wp_unslash($_GET['source'])) : '';
        $limit = isset($_GET['limit']) ? max(1, min(1000, intval($_GET['limit']))) : 200;
        $rows = Logger::recent($limit, $level ?: null, $source ?: null);
        echo '<div class="wrap"><h1>' . esc_html__('ClassFlow Pro Logs', 'classflow-pro') . '</h1>';
        echo '<form method="get" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="classflow-pro-logs" />';
        echo '<label>Level <input type="text" name="level" value="' . esc_attr($level) . '" class="regular-text" placeholder="error, info"></label> ';
        echo '<label>Source <input type="text" name="source" value="' . esc_attr($source) . '" class="regular-text" placeholder="stripe, quickbooks"></label> ';
        echo '<label>Limit <input type="number" name="limit" value="' . esc_attr((string)$limit) . '" class="small-text" min="1" max="1000"></label> ';
        submit_button(__('Filter', 'classflow-pro'), 'secondary', '', false);
        echo '</form>';
        echo '<table class="widefat striped"><thead><tr><th>Time (UTC)</th><th>Level</th><th>Source</th><th>Message</th><th>Context</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $ctx = $r['context'] ? json_decode($r['context'], true) : null;
            $ctx_disp = $ctx ? '<code style="white-space:pre-wrap;word-break:break-word;">' . esc_html(wp_json_encode($ctx)) . '</code>' : '—';
            echo '<tr>'
                . '<td>' . esc_html($r['created_at']) . '</td>'
                . '<td>' . esc_html($r['level']) . '</td>'
                . '<td>' . esc_html($r['source']) . '</td>'
                . '<td>' . esc_html($r['message']) . '</td>'
                . '<td>' . $ctx_disp . '</td>'
                . '</tr>';
        }
        if (!$rows) echo '<tr><td colspan="5">' . esc_html__('No logs yet.', 'classflow-pro') . '</td></tr>';
        echo '</tbody></table></div>';
    }
}

