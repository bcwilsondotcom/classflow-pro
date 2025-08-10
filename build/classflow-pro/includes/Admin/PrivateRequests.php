<?php
namespace ClassFlowPro\Admin;

class PrivateRequests
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $t = $wpdb->prefix . 'cfp_private_requests';
        // Approve and create schedule
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('cfp_priv_requests')) {
            $rid = intval($_POST['request_id'] ?? 0);
            $class_id = intval($_POST['class_id'] ?? 0);
            $instructor_id = intval($_POST['instructor_id'] ?? 0);
            $location_id = intval($_POST['location_id'] ?? 0);
            $start = sanitize_text_field($_POST['start_time'] ?? '');
            $end = sanitize_text_field($_POST['end_time'] ?? '');
            $price_cents = intval($_POST['price_cents'] ?? 0);
            if ($rid && $class_id && $start && $end) {
                $s = $wpdb->prefix . 'cfp_schedules';
                $wpdb->insert($s, [
                    'class_id' => $class_id,
                    'instructor_id' => $instructor_id ?: null,
                    'location_id' => $location_id ?: null,
                    'resource_id' => null,
                    'start_time' => gmdate('Y-m-d H:i:s', strtotime($start)),
                    'end_time' => gmdate('Y-m-d H:i:s', strtotime($end)),
                    'capacity' => 1,
                    'price_cents' => $price_cents,
                    'currency' => Settings::get('currency','usd'),
                    'is_private' => 1,
                ], ['%d','%d','%d','%s','%s','%d','%d','%s','%d']);
                $schedule_id = (int)$wpdb->insert_id;
                $wpdb->update($t, ['status' => 'approved'], ['id' => $rid], ['%s'], ['%d']);
                echo '<div class="notice notice-success"><p>Approved request #' . intval($rid) . ' and created schedule #' . $schedule_id . '.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Missing required fields.</p></div>';
            }
        }

        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY created_at DESC LIMIT 100", ARRAY_A);
        echo '<div class="wrap"><h1>Private Session Requests</h1>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Client</th><th>Email</th><th>Instructor</th><th>Preferred</th><th>Status</th><th>Notes</th><th>Approve</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . intval($r['id']) . '</td>';
            echo '<td>' . esc_html($r['name'] ?: '-') . '</td>';
            echo '<td>' . esc_html($r['email']) . '</td>';
            echo '<td>' . ($r['instructor_id'] ? esc_html(get_the_title((int)$r['instructor_id'])) : '—') . '</td>';
            echo '<td>' . esc_html(trim(($r['preferred_date'] ?: '') . ' ' . ($r['preferred_time'] ?: ''))) . '</td>';
            echo '<td>' . esc_html($r['status']) . '</td>';
            echo '<td>' . esc_html($r['notes'] ?: '') . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:flex;gap:6px;align-items:center;">';
            wp_nonce_field('cfp_priv_requests');
            echo '<input type="hidden" name="request_id" value="' . intval($r['id']) . '" />';
            echo '<input type="number" name="class_id" placeholder="Class ID" min="1" style="width:90px" required />';
            echo '<input type="number" name="instructor_id" placeholder="Instructor ID" min="0" style="width:120px" />';
            echo '<input type="number" name="location_id" placeholder="Location ID" min="0" style="width:110px" />';
            echo '<input type="datetime-local" name="start_time" required />';
            echo '<input type="datetime-local" name="end_time" required />';
            echo '<input type="number" name="price_cents" placeholder="Price (cents)" min="0" style="width:140px" />';
            submit_button('Approve & Create', 'primary', '', false);
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        if (!$rows) echo '<tr><td colspan="8">No requests.</td></tr>';
        echo '</tbody></table></div>';
    }
}

