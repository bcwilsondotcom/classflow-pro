<?php
namespace ClassFlowPro\Admin;

class CustomerNotes
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_customer_notes';
        // Handle add
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cfp_add_note']) && check_admin_referer('cfp_add_note')) {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $note = wp_kses_post($_POST['note'] ?? '');
            $visible = !empty($_POST['visible_to_user']) ? 1 : 0;
            if ($user_id && $note) {
                $wpdb->insert($table, [
                    'user_id' => $user_id,
                    'note' => $note,
                    'visible_to_user' => $visible,
                    'created_by' => get_current_user_id() ?: null,
                ], ['%d','%s','%d','%d']);
                echo '<div class="notice notice-success"><p>Note added.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>User and note are required.</p></div>';
            }
        }
        // Filters
        $filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        echo '<div class="wrap"><h1>Customer Notes</h1>';
        echo '<h2>Add Note</h2><form method="post">';
        wp_nonce_field('cfp_add_note');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>User</th><td><select name="user_id">';
        $users = get_users(['fields' => ['ID','display_name','user_email'], 'number' => 200]);
        echo '<option value="">— Select —</option>';
        foreach ($users as $u) {
            echo '<option value="' . esc_attr((string)$u->ID) . '">' . esc_html($u->display_name . ' (' . $u->user_email . ')') . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>Note</th><td><textarea name="note" rows="4" class="large-text"></textarea></td></tr>';
        echo '<tr><th>Visibility</th><td><label><input type="checkbox" name="visible_to_user" value="1"> Visible to user</label></td></tr>';
        echo '</tbody></table>';
        echo '<p><button class="button button-primary">Add Note</button><input type="hidden" name="cfp_add_note" value="1"></p></form>';

        echo '<h2>Notes</h2>';
        echo '<form method="get" style="margin-bottom:10px;">';
        echo '<input type="hidden" name="page" value="classflow-pro-notes" />';
        echo '<label>Filter by user: <select name="user_id"><option value="">All</option>';
        foreach ($users as $u) {
            echo '<option value="' . esc_attr((string)$u->ID) . '"' . selected($filter_user, (int)$u->ID, false) . '>' . esc_html($u->display_name) . '</option>';
        }
        echo '</select></label> ';
        submit_button('Filter', 'secondary', '', false);
        echo '</form>';

        $where = ''; $params = [];
        if ($filter_user) { $where = 'WHERE n.user_id = %d'; $params[] = $filter_user; }
        $sql = "SELECT n.*, u.display_name, u.user_email, a.display_name AS author FROM $table n LEFT JOIN {$wpdb->users} u ON u.ID = n.user_id LEFT JOIN {$wpdb->users} a ON a.ID = n.created_by $where ORDER BY n.created_at DESC LIMIT 200";
        $rows = $params ? $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        echo '<table class="widefat striped"><thead><tr><th>User</th><th>Note</th><th>Visible to User</th><th>Created By</th><th>Created At (UTC)</th></tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="5">No notes.</td></tr>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . esc_html(($r['display_name'] ?: 'User #' . (int)$r['user_id']) . ' (' . ($r['user_email'] ?: '') . ')') . '</td>';
            echo '<td>' . wp_kses_post($r['note']) . '</td>';
            echo '<td>' . ($r['visible_to_user'] ? 'Yes' : 'No') . '</td>';
            echo '<td>' . esc_html($r['author'] ?: '-') . '</td>';
            echo '<td>' . esc_html($r['created_at']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}

