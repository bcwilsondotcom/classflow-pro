<?php
namespace ClassFlowPro\Admin;

class Schedules
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_schedules';
        // Handle create
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('cfp_add_schedule')) {
            $class_id = intval($_POST['class_id'] ?? 0);
            $instructor_id = intval($_POST['instructor_id'] ?? 0);
            $resource_id = intval($_POST['resource_id'] ?? 0);
            $location_id = intval($_POST['location_id'] ?? 0);
            $start_time = sanitize_text_field($_POST['start_time'] ?? '');
            $end_time = sanitize_text_field($_POST['end_time'] ?? '');
            $capacity = max(1, intval($_POST['capacity'] ?? 1));
            $price_cents = max(0, intval($_POST['price_cents'] ?? 0));
            $currency = sanitize_text_field($_POST['currency'] ?? 'usd');
            $is_private = !empty($_POST['is_private']) ? 1 : 0;
            if ($class_id && $start_time && $end_time) {
                // Conflict and availability checks
                $startUtc = gmdate('Y-m-d H:i:s', strtotime($start_time));
                $endUtc = gmdate('Y-m-d H:i:s', strtotime($end_time));
                // Instructor availability
                if ($instructor_id) {
                    $weekly = get_post_meta($instructor_id, '_cfp_availability_weekly', true) ?: '';
                    $blackouts = get_post_meta($instructor_id, '_cfp_blackout_dates', true) ?: '';
                    $avail = \ClassFlowPro\Utils\Time::parseWeeklyAvailability($weekly);
                    $dt = new \DateTimeImmutable($startUtc, new \DateTimeZone('UTC'));
                    if ($weekly && !\ClassFlowPro\Utils\Time::withinAvailability($avail, $dt)) {
                        echo '<div class="notice notice-error"><p>' . esc_html__('Instructor is not available at the selected time.', 'classflow-pro') . '</p></div>';
                        echo '</div>';
                        return;
                    }
                    if ($blackouts && \ClassFlowPro\Utils\Time::isBlackout($blackouts, $dt)) {
                        echo '<div class="notice notice-error"><p>' . esc_html__('Instructor is blacked out on this date.', 'classflow-pro') . '</p></div>';
                        echo '</div>';
                        return;
                    }
                }
                // Conflicts: instructor/resource overlapping schedule
                $conflict = false;
                if ($instructor_id) {
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE instructor_id = %d AND ( (start_time < %s) AND (end_time > %s) )", $instructor_id, $endUtc, $startUtc));
                    if ($exists) { $conflict = true; $who = 'instructor'; }
                }
                if (!$conflict && $resource_id) {
                    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE resource_id = %d AND ( (start_time < %s) AND (end_time > %s) )", $resource_id, $endUtc, $startUtc));
                    if ($exists) { $conflict = true; $who = 'resource'; }
                }
                if ($conflict) {
                    echo '<div class="notice notice-error"><p>' . esc_html(sprintf(__('Conflict detected with existing %s schedule in that timeframe.', 'classflow-pro'), $who)) . '</p></div>';
                    echo '</div>';
                    return;
                }
                $wpdb->insert($table, [
                    'class_id' => $class_id,
                    'instructor_id' => $instructor_id ?: null,
                    'resource_id' => $resource_id ?: null,
                    'location_id' => $location_id ?: null,
                    'start_time' => $startUtc,
                    'end_time' => $endUtc,
                    'capacity' => $capacity,
                    'price_cents' => $price_cents,
                    'currency' => $currency,
                    'is_private' => $is_private,
                ], ['%d','%d','%d','%d','%s','%s','%d','%d','%s','%d']);
                echo '<div class="notice notice-success"><p>Schedule created.</p></div>';
                // Google Calendar sync
                try { \ClassFlowPro\Calendar\Google::upsert_event((int)$wpdb->insert_id); } catch (\Throwable $e) {}
            } else {
                echo '<div class="notice notice-error"><p>Missing required fields.</p></div>';
            }
        }

        // AJAX for class default location
        add_action('wp_ajax_cfp_get_class_default_location', function () use ($wpdb) {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            check_ajax_referer('cfp_admin');
            if (!$id) { wp_send_json_error(['message' => 'Invalid ID'], 400); }
            $loc = (int) $wpdb->get_var($wpdb->prepare("SELECT default_location_id FROM {$wpdb->prefix}cfp_classes WHERE id=%d", $id));
            wp_send_json(['location_id' => $loc]);
        });

        // List schedules
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY start_time DESC LIMIT 100", ARRAY_A);
        echo '<div class="wrap"><h1>Schedules</h1>';
        echo '<h2>Add Schedule</h2>';
        echo '<form method="post">';
        wp_nonce_field('cfp_add_schedule');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Class ID</th><td><input name="class_id" type="number" min="1" required></td></tr>';
        // Instructors (first-class table)
        $ins = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}cfp_instructors ORDER BY name ASC", ARRAY_A);
        echo '<tr><th>Instructor</th><td><select name="instructor_id"><option value="">— Select —</option>';
        foreach ($ins as $i) { echo '<option value="'.esc_attr((string)$i['id']).'">'.esc_html($i['name']).'</option>'; }
        echo '</select></td></tr>';
        // Resources
        $res = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}cfp_resources ORDER BY name ASC", ARRAY_A);
        echo '<tr><th>Resource</th><td><select name="resource_id"><option value="">— Select —</option>';
        foreach ($res as $rr) { echo '<option value="'.esc_attr((string)$rr['id']).'">'.esc_html($rr['name']).'</option>'; }
        echo '</select></td></tr>';
        // Locations
        $locations = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}cfp_locations ORDER BY name ASC", ARRAY_A);
        echo '<tr><th>Location</th><td><select name="location_id"><option value="">— Select —</option>';
        foreach ($locations as $loc) { echo '<option value="' . esc_attr((string)$loc['id']) . '">' . esc_html($loc['name']) . '</option>'; }
        echo '</select></td></tr>';
        echo '<tr><th>Start (UTC)</th><td><input name="start_time" type="datetime-local" required></td></tr>';
        echo '<tr><th>End (UTC)</th><td><input name="end_time" type="datetime-local" required></td></tr>';
        echo '<tr><th>Capacity</th><td><input name="capacity" type="number" value="8" min="1"></td></tr>';
        echo '<tr><th>Price (cents)</th><td><input name="price_cents" type="number" value="3000" min="0"></td></tr>';
        echo '<tr><th>Currency</th><td><input name="currency" type="text" value="' . esc_attr(\ClassFlowPro\Admin\Settings::get('currency','usd')) . '"></td></tr>';
        echo '<tr><th>Private</th><td><label><input name="is_private" type="checkbox"> Private session</label></td></tr>';
        echo '</tbody></table>';
        submit_button('Add Schedule');
        echo '</form>';
        $ajax = esc_js(admin_url('admin-ajax.php'));
        $nonce = esc_js(wp_create_nonce('cfp_admin'));
        echo '<script>(function(){var cls=document.querySelector("input[name=class_id]");var loc=document.querySelector("select[name=location_id]");function go(){var id=parseInt(cls.value,10);if(!id||!loc||loc.value) return; var u="'.$ajax.'?action=cfp_get_class_default_location&_ajax_nonce='.$nonce.'&id="+id; fetch(u).then(function(r){return r.json()}).then(function(d){try{ if(d && d.location_id){ loc.value=String(d.location_id);} }catch(e){} }).catch(function(){});} if(cls){cls.addEventListener("change",go);cls.addEventListener("blur",go);} })();</script>';
        echo '<h2>Recent Schedules</h2>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Class</th><th>Instructor</th><th>Location</th><th>Start</th><th>End</th><th>Capacity</th><th>Price</th><th>Private</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $class_name = \ClassFlowPro\Utils\Entities::class_name((int)$r['class_id']);
            echo '<tr>';
            echo '<td>' . intval($r['id']) . '</td>';
            echo '<td>' . esc_html($class_name) . ' (#' . intval($r['class_id']) . ')</td>';
            $iname = $r['instructor_id'] ? $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}cfp_instructors WHERE id=%d", (int)$r['instructor_id'])) : null;
            $lname = $r['location_id'] ? $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}cfp_locations WHERE id=%d", (int)$r['location_id'])) : null;
            echo '<td>' . ($r['instructor_id'] ? esc_html($iname ?: ('#'.intval($r['instructor_id']))) : '-') . '</td>';
            echo '<td>' . ($r['location_id'] ? esc_html($lname ?: ('#'.intval($r['location_id']))) : '-') . '</td>';
            echo '<td>' . esc_html($r['start_time']) . '</td>';
            echo '<td>' . esc_html($r['end_time']) . '</td>';
            echo '<td>' . intval($r['capacity']) . '</td>';
            echo '<td>' . number_format_i18n(((int)$r['price_cents'])/100, 2) . ' ' . esc_html(strtoupper($r['currency'])) . '</td>';
            echo '<td>' . ($r['is_private'] ? 'Yes' : 'No') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}
