<?php
namespace ClassFlowPro\Admin;

use ClassFlowPro\Notifications\Mailer;

class GiftCards
{
    public static function render(): void
    {
        if (!current_user_can('cfp_manage_customers') && !current_user_can('manage_options')) return;
        $action = isset($_POST['cfp_action']) ? sanitize_key((string)$_POST['cfp_action']) : '';
        $message = '';
        if ($action && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'cfp_giftcards')) {
            $id = isset($_POST['gc_id']) ? intval($_POST['gc_id']) : 0;
            if ($id > 0) {
                switch ($action) {
                    case 'resend':
                        $message = self::handle_resend($id);
                        break;
                    case 'void':
                        $message = self::handle_void($id);
                        break;
                }
            }
        }
        $status = isset($_GET['status']) ? sanitize_key((string)$_GET['status']) : 'all';
        $s = isset($_GET['s']) ? sanitize_text_field((string)$_GET['s']) : '';
        $page = max(1, intval($_GET['paged'] ?? 1));
        $per_page = 25;
        $filters = [ 'status' => $status, 's' => $s, 'page' => $page, 'per_page' => $per_page ];
        $data = self::query_gift_cards($filters);
        $total = (int)$data['total'];
        $rows = $data['rows'];
        $pages = max(1, (int)ceil($total / $per_page));

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gift Cards', 'classflow-pro') . '</h1>';
        if ($message) {
            echo '<div class="notice notice-success"><p>' . wp_kses_post($message) . '</p></div>';
        }
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="classflow-pro-giftcards" />';
        echo '<div class="tablenav top" style="display:flex; gap:8px; align-items:center;">';
        echo '<select name="status">';
        $opts = [ 'all' => __('All', 'classflow-pro'), 'new' => __('New', 'classflow-pro'), 'used' => __('Used', 'classflow-pro'), 'void' => __('Void', 'classflow-pro') ];
        foreach ($opts as $k => $label) { echo '<option value="' . esc_attr($k) . '"' . selected($status, $k, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select>';
        echo '<input type="search" name="s" value="' . esc_attr($s) . '" placeholder="' . esc_attr__('Search code or email', 'classflow-pro') . '" />';
        submit_button(__('Filter'), 'secondary', '', false);
        echo ' <a class="button" href="' . esc_url(add_query_arg(array_merge($_GET, ['export' => 'csv', 'paged' => null]), admin_url('admin.php'))) . '">' . esc_html__('Export CSV', 'classflow-pro') . '</a>';
        echo '</div>';
        echo '</form>';

        // CSV export
        if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
            self::export_csv($rows);
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>'
            . '<th>' . esc_html__('Code', 'classflow-pro') . '</th>'
            . '<th>' . esc_html__('Credits', 'classflow-pro') . '</th>'
            . '<th>' . esc_html__('Value', 'classflow-pro') . '</th>'
            . '<th>' . esc_html__('Recipient', 'classflow-pro') . '</th>'
            . '<th>' . esc_html__('Purchaser', 'classflow-pro') . '</th>'
            . '<th>' . esc_html__('Status', 'classflow-pro') . '</th>'
            . '<th>' . esc_html__('Used By', 'classflow-pro') . '</th>'
            . '<th>' . esc_html__('Issued', 'classflow-pro') . '</th>'
            . '<th>' . esc_html__('Actions', 'classflow-pro') . '</th>'
            . '</tr></thead><tbody>';
        if (!$rows) {
            echo '<tr><td colspan="9">' . esc_html__('No gift cards found.', 'classflow-pro') . '</td></tr>';
        } else {
            foreach ($rows as $r) {
                $value = '$' . number_format_i18n(((int)$r['amount_cents'])/100, 2) . ' ' . strtoupper($r['currency'] ?: 'usd');
                $used_by = '';
                if (!empty($r['used_by_user_id'])) { $u = get_userdata((int)$r['used_by_user_id']); if ($u) { $used_by = esc_html($u->display_name ?: $u->user_email); } }
                $issued = $r['created_at'] ? esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $r['created_at'], true)) : '';
                echo '<tr>'
                    . '<td><code>' . esc_html($r['code']) . '</code></td>'
                    . '<td>' . intval($r['credits']) . '</td>'
                    . '<td>' . esc_html($value) . '</td>'
                    . '<td>' . esc_html($r['recipient_email'] ?: '—') . '</td>'
                    . '<td>' . esc_html($r['purchaser_email'] ?: '—') . '</td>'
                    . '<td>' . esc_html($r['status']) . '</td>'
                    . '<td>' . ($used_by ?: '—') . '</td>'
                    . '<td>' . $issued . '</td>'
                    . '<td>' . self::actions_html((int)$r['id'], (string)$r['status'], (string)($r['recipient_email'] ?: '')) . '</td>'
                    . '</tr>';
            }
        }
        echo '</tbody></table>';

        // Pagination
        if ($pages > 1) {
            $base = remove_query_arg('paged');
            echo '<div class="tablenav bottom"><div class="tablenav-pages">';
            for ($i = 1; $i <= $pages; $i++) {
                $link = esc_url(add_query_arg(['paged' => $i], $base));
                $class = $i === $page ? ' class="page-numbers current"' : ' class="page-numbers"';
                echo '<a' . $class . ' href="' . $link . '">' . $i . '</a> ';
            }
            echo '</div></div>';
        }

        echo '</div>';
    }

    private static function actions_html(int $id, string $status, string $recipient_email): string
    {
        $out = '';
        $nonce = wp_create_nonce('cfp_giftcards');
        if ($status === 'new') {
            $out .= '<form method="post" style="display:inline;">'
                . '<input type="hidden" name="gc_id" value="' . esc_attr((string)$id) . '" />'
                . '<input type="hidden" name="cfp_action" value="resend" />'
                . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '" />';
            if (empty($recipient_email)) {
                $out .= '<input type="email" name="recipient_email" placeholder="' . esc_attr__('Recipient email', 'classflow-pro') . '" /> ';
            }
            $out .= '<button class="button" type="submit">' . esc_html__('Resend', 'classflow-pro') . '</button></form> ';
            $out .= '<form method="post" style="display:inline; margin-left:6px;" onsubmit="return confirm(\'' . esc_js(__('Void this gift card?', 'classflow-pro')) . '\');">'
                . '<input type="hidden" name="gc_id" value="' . esc_attr((string)$id) . '" />'
                . '<input type="hidden" name="cfp_action" value="void" />'
                . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '" />'
                . '<button class="button button-link-delete" type="submit">' . esc_html__('Void', 'classflow-pro') . '</button></form>';
        } elseif ($status === 'used') {
            $out .= '<span class="dashicons dashicons-yes-alt" title="' . esc_attr__('Used', 'classflow-pro') . '"></span>';
        } else {
            $out .= '—';
        }
        return $out;
    }

    private static function handle_resend(int $id): string
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_gift_cards';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A);
        if (!$row) return __('Gift card not found.', 'classflow-pro');
        if ($row['status'] !== 'new') return __('Only new gift cards can be resent.', 'classflow-pro');
        $recipient = sanitize_email($_POST['recipient_email'] ?? ($row['recipient_email'] ?? ''));
        if (!$recipient) return __('Recipient email is required to resend.', 'classflow-pro');
        try {
            Mailer::gift_card_issued((string)$row['code'], (int)$row['credits'], (int)$row['amount_cents'], $recipient, (string)($row['purchaser_email'] ?? ''));
            if (empty($row['recipient_email'])) {
                $wpdb->update($t, ['recipient_email' => $recipient], ['id' => $id], ['%s'], ['%d']);
            }
        } catch (\Throwable $e) { return __('Failed to send email.', 'classflow-pro'); }
        return __('Gift card email resent.', 'classflow-pro');
    }

    private static function handle_void(int $id): string
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_gift_cards';
        $row = $wpdb->get_row($wpdb->prepare("SELECT status FROM $t WHERE id=%d", $id), ARRAY_A);
        if (!$row) return __('Gift card not found.', 'classflow-pro');
        if ($row['status'] !== 'new') return __('Only new gift cards can be voided.', 'classflow-pro');
        $wpdb->update($t, ['status' => 'void'], ['id' => $id], ['%s'], ['%d']);
        return __('Gift card voided.', 'classflow-pro');
    }

    private static function query_gift_cards(array $filters): array
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_gift_cards';
        $where = [];$params=[];
        if (!empty($filters['status']) && $filters['status'] !== 'all') { $where[] = 'status = %s'; $params[] = $filters['status']; }
        if (!empty($filters['s'])) {
            $like = '%' . $wpdb->esc_like((string)$filters['s']) . '%';
            $where[] = '(code LIKE %s OR recipient_email LIKE %s OR purchaser_email LIKE %s)';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $wsql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $total = (int)$wpdb->get_var($params ? $wpdb->prepare("SELECT COUNT(*) FROM $t $wsql", $params) : "SELECT COUNT(*) FROM $t $wsql");
        $page = max(1, (int)$filters['page']); $per = max(1, (int)$filters['per_page']); $off = ($page-1)*$per;
        $sql = "SELECT * FROM $t $wsql ORDER BY id DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($params ? $wpdb->prepare($sql, ...array_merge($params, [$per, $off])) : $wpdb->prepare($sql, $per, $off), ARRAY_A) ?: [];
        return ['total'=>$total, 'rows'=>$rows];
    }

    private static function export_csv(array $rows): void
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename=gift_cards.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Code','Credits','Amount','Currency','Recipient','Purchaser','Status','Used By','Used At','Created']);
        foreach ($rows as $r) {
            $used_by = '';
            if (!empty($r['used_by_user_id'])) { $u = get_userdata((int)$r['used_by_user_id']); if ($u) { $used_by = $u->user_email; } }
            fputcsv($out, [
                (int)$r['id'], (string)$r['code'], (int)$r['credits'], (int)$r['amount_cents'], (string)($r['currency'] ?: 'usd'), (string)($r['recipient_email'] ?: ''), (string)($r['purchaser_email'] ?: ''), (string)$r['status'], $used_by, (string)($r['used_at'] ?: ''), (string)($r['created_at'] ?: '')
            ]);
        }
        fclose($out);
        exit;
    }
}

