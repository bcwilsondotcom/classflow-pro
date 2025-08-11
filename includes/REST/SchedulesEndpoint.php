<?php
namespace ClassFlowPro\REST;

if (!defined('ABSPATH')) { exit; }

class SchedulesEndpoint
{
    public function register_routes(): void
    {
        register_rest_route('classflow-pro/v1', '/schedules', [
            'methods' => 'GET',
            'callback' => [$this, 'get_schedules'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => [
                'class_id' => [
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
        ]);

        register_rest_route('classflow-pro/v1', '/schedules', [
            'methods' => 'POST',
            'callback' => [$this, 'create_schedule'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('classflow-pro/v1', '/schedules/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_schedule'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        register_rest_route('classflow-pro/v1', '/schedules/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_schedule'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
        // List attendees for a schedule
        register_rest_route('classflow-pro/v1', '/schedules/(?P<id>\d+)/attendees', [
            'methods' => 'GET',
            'callback' => [$this, 'schedule_attendees'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
        // Cancel a single schedule instance
        register_rest_route('classflow-pro/v1', '/schedules/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel_schedule'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        // Move attendees from one schedule to another
        register_rest_route('classflow-pro/v1', '/schedules/(?P<id>\d+)/reschedule_to', [
            'methods' => 'POST',
            'callback' => [$this, 'reschedule_to'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
        // Preview cancel email content
        register_rest_route('classflow-pro/v1', '/schedules/(?P<id>\d+)/preview_cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'preview_cancel'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);

        // Bulk-cancel upcoming schedules for a class (uses class id and date threshold)
        register_rest_route('classflow-pro/v1', '/classes/(?P<class_id>\d+)/cancel_future', [
            'methods' => 'POST',
            'callback' => [$this, 'cancel_future_for_class'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }

    public function check_permissions(): bool
    {
        return current_user_can('edit_posts');
    }

    public function get_schedules($request): \WP_REST_Response
    {
        $class_id = (int) $request->get_param('class_id');
        
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_schedules';
        $instructors_table = $wpdb->prefix . 'cfp_instructors';
        $locations_table = $wpdb->prefix . 'cfp_locations';
        $resources_table = $wpdb->prefix . 'cfp_resources';
        
        $schedules = $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.*,
                i.name as instructor_name,
                l.name as location_name,
                l.timezone as location_timezone,
                r.name as resource_name
            FROM $table s
            LEFT JOIN $instructors_table i ON s.instructor_id = i.id
            LEFT JOIN $locations_table l ON s.location_id = l.id
            LEFT JOIN $resources_table r ON s.resource_id = r.id
            WHERE s.class_id = %d AND COALESCE(s.status,'active') <> 'cancelled'
            ORDER BY s.start_time DESC
        ", $class_id), ARRAY_A);

        // Get booking counts
        $bookings_table = $wpdb->prefix . 'cfp_bookings';
        foreach ($schedules as &$schedule) {
            $schedule['bookings_count'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $bookings_table WHERE schedule_id = %d AND status IN ('confirmed', 'pending')",
                $schedule['id']
            ));
        }

        return new \WP_REST_Response($schedules, 200);
    }

    public function create_schedule($request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        
        if (!isset($data['class_id']) || !isset($data['start_time'])) {
            return new \WP_REST_Response(['error' => 'Missing required fields'], 400);
        }

        // Validate instructor availability
        if (!empty($data['instructor_id'])) {
            $conflict = $this->check_instructor_conflict($data['instructor_id'], $data['start_time'], $data['end_time']);
            if ($conflict) {
                return new \WP_REST_Response(['error' => 'Instructor is not available at this time'], 400);
            }
        }

        // Validate resource availability
        if (!empty($data['resource_id'])) {
            $conflict = $this->check_resource_conflict($data['resource_id'], $data['start_time'], $data['end_time']);
            if ($conflict) {
                return new \WP_REST_Response(['error' => 'Resource is not available at this time'], 400);
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cfp_schedules';
        
        $wpdb->insert($table, [
            'class_id' => (int) $data['class_id'],
            'instructor_id' => !empty($data['instructor_id']) ? (int) $data['instructor_id'] : null,
            'resource_id' => !empty($data['resource_id']) ? (int) $data['resource_id'] : null,
            'location_id' => !empty($data['location_id']) ? (int) $data['location_id'] : null,
            'start_time' => sanitize_text_field($data['start_time']),
            'end_time' => sanitize_text_field($data['end_time']),
            'capacity' => isset($data['capacity']) ? (int) $data['capacity'] : 8,
            'price_cents' => isset($data['price_cents']) ? (int) $data['price_cents'] : 0,
            'currency' => isset($data['currency']) ? sanitize_text_field($data['currency']) : 'usd',
            'is_private' => !empty($data['is_private']) ? 1 : 0,
        ]);

        $id = $wpdb->insert_id;
        
        return new \WP_REST_Response(['id' => $id, 'success' => true], 201);
    }

    public function update_schedule($request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $data = $request->get_json_params();
        
        // Check for conflicts if instructor or time changed
        if (!empty($data['instructor_id']) || !empty($data['start_time'])) {
            global $wpdb;
            $table = $wpdb->prefix . 'cfp_schedules';
            $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
            
            $instructor_id = $data['instructor_id'] ?? $existing['instructor_id'];
            $start_time = $data['start_time'] ?? $existing['start_time'];
            $end_time = $data['end_time'] ?? $existing['end_time'];
            
            if ($instructor_id) {
                $conflict = $this->check_instructor_conflict($instructor_id, $start_time, $end_time, $id);
                if ($conflict) {
                    return new \WP_REST_Response(['error' => 'Instructor is not available at this time'], 400);
                }
            }
        }

        $update_data = [];
        $allowed_fields = ['instructor_id', 'resource_id', 'location_id', 'start_time', 'end_time', 'capacity', 'price_cents', 'currency', 'is_private'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['instructor_id', 'resource_id', 'location_id']) && empty($data[$field])) {
                    $update_data[$field] = null;
                } else {
                    $update_data[$field] = $field === 'is_private' ? ($data[$field] ? 1 : 0) : $data[$field];
                }
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cfp_schedules';
        $wpdb->update($table, $update_data, ['id' => $id]);
        
        return new \WP_REST_Response(['success' => true], 200);
    }

    public function delete_schedule($request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        
        // Check if there are bookings
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'cfp_bookings';
        $has_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table WHERE schedule_id = %d AND status IN ('confirmed', 'pending')",
            $id
        ));
        
        if ($has_bookings > 0) {
            return new \WP_REST_Response(['error' => 'Cannot delete schedule with existing bookings'], 400);
        }
        
        $table = $wpdb->prefix . 'cfp_schedules';
        $wpdb->delete($table, ['id' => $id]);
        
        return new \WP_REST_Response(['success' => true], 200);
    }

    public function schedule_attendees($request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        global $wpdb;
        $b = $wpdb->prefix . 'cfp_bookings';
        $u = $wpdb->users;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.status, b.user_id, b.customer_email, b.credits_used, b.amount_cents, b.currency, u.display_name, u.user_email
             FROM $b b LEFT JOIN $u u ON u.ID=b.user_id WHERE b.schedule_id = %d ORDER BY b.status='confirmed' DESC, b.id ASC",
            $id
        ), ARRAY_A) ?: [];
        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['user_id'] = $r['user_id'] ? (int)$r['user_id'] : null;
            $r['amount_cents'] = (int)$r['amount_cents'];
            $r['credits_used'] = (int)$r['credits_used'];
        }
        return new \WP_REST_Response($rows, 200);
    }

    public function cancel_schedule($request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $data = $request->get_json_params();
        $notify = !empty($data['notify']);
        $note = isset($data['note']) ? sanitize_textarea_field($data['note']) : '';
        $action = isset($data['action']) ? sanitize_text_field($data['action']) : 'auto'; // auto|refund|credit|cancel
        global $wpdb;
        $b = $wpdb->prefix . 'cfp_bookings';
        $w = $wpdb->prefix . 'cfp_waitlist';
        $s = $wpdb->prefix . 'cfp_schedules';

        // Mark schedule as cancelled and store note
        $wpdb->update($s, [ 'status' => 'cancelled', 'cancel_note' => $note, 'cancelled_at' => gmdate('Y-m-d H:i:s') ], ['id' => $id], ['%s','%s','%s'], ['%d']);

        // Process each active booking: refund/credit/cancel
        $bookings = $wpdb->get_col($wpdb->prepare("SELECT id FROM $b WHERE schedule_id = %d AND status IN ('pending','confirmed')", $id));
        $processed = ['refunded' => 0, 'credited' => 0, 'canceled' => 0, 'errors' => 0];
        if ($bookings) {
            foreach ($bookings as $bid) {
                $opts = ['notify' => $notify, 'action' => $action, 'note' => $note];
                $res = \ClassFlowPro\Booking\Manager::admin_cancel_booking((int)$bid, $opts);
                if (is_wp_error($res)) { $processed['errors']++; continue; }
                $st = $res['status'] ?? 'canceled';
                if ($st === 'refunded') $processed['refunded']++;
                elseif ($st === 'canceled') $processed['canceled']++;
                else $processed['canceled']++;
            }
        }

        // Clear waitlist entries
        $wpdb->delete($w, ['schedule_id' => $id], ['%d']);

        return new \WP_REST_Response(['success' => true, 'processed' => $processed], 200);
    }

    public function cancel_future_for_class($request): \WP_REST_Response
    {
        $class_id = (int)$request->get_param('class_id');
        $data = $request->get_json_params();
        $notify = !empty($data['notify']);
        $note = isset($data['note']) ? sanitize_textarea_field($data['note']) : '';
        $action = isset($data['action']) ? sanitize_text_field($data['action']) : 'auto'; // auto|refund|credit|cancel
        $from = isset($data['date_from']) ? sanitize_text_field($data['date_from']) : gmdate('Y-m-d H:i:s');
        $to = isset($data['date_to']) ? sanitize_text_field($data['date_to']) : null;
        $only_location_id = isset($data['location_id']) ? (int)$data['location_id'] : null;
        $only_weekday = isset($data['only_weekday']) ? (int)$data['only_weekday'] : null; // 0=Sun..6
        $only_time_hm = isset($data['only_time_hm']) ? preg_replace('/[^0-9:]/','', (string)$data['only_time_hm']) : null; // HH:MM
        global $wpdb; $s=$wpdb->prefix.'cfp_schedules';
        // Base select
        $where = [ 'class_id = %d', "COALESCE(status,'active') <> 'cancelled'", 'start_time >= %s' ];
        $params = [ $class_id, $from ];
        if ($to) { $where[] = 'start_time <= %s'; $params[] = $to; }
        if ($only_location_id) { $where[] = 'location_id = %d'; $params[] = $only_location_id; }
        $sql = "SELECT id, start_time, location_id FROM $s WHERE ".implode(' AND ', $where)." ORDER BY start_time ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
        // Optionally filter by weekday/time in location's timezone
        if ($only_weekday !== null || $only_time_hm !== null) {
            $filtered = [];
            foreach ($rows as $r) {
                $tz = \ClassFlowPro\Utils\Timezone::for_location(!empty($r['location_id']) ? (int)$r['location_id'] : null);
                try {
                    $dt = new \DateTime($r['start_time'].' UTC');
                    $dt->setTimezone(new \DateTimeZone($tz));
                    $weekday = (int)$dt->format('w'); // 0=Sun..6
                    $hm = $dt->format('H:i');
                    if ($only_weekday !== null && $weekday !== (int)$only_weekday) continue;
                    if ($only_time_hm !== null && $hm !== $only_time_hm) continue;
                    $filtered[] = $r;
                } catch (\Throwable $e) { /* skip invalid */ }
            }
            $rows = $filtered;
        }
        $counts = ['total' => count($rows), 'processed' => 0, 'errors' => 0];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            // Mark schedule as cancelled
            $wpdb->update($s, [ 'status' => 'cancelled', 'cancel_note' => $note, 'cancelled_at' => gmdate('Y-m-d H:i:s') ], ['id' => $id], ['%s','%s','%s'], ['%d']);
            // Process attendees
            $b = $wpdb->prefix . 'cfp_bookings';
            $w = $wpdb->prefix . 'cfp_waitlist';
            $bookings = $wpdb->get_col($wpdb->prepare("SELECT id FROM $b WHERE schedule_id = %d AND status IN ('pending','confirmed')", $id));
            if ($bookings) {
                foreach ($bookings as $bid) {
                    $opts = ['notify' => $notify, 'action' => $action, 'note' => $note];
                    $res = \ClassFlowPro\Booking\Manager::admin_cancel_booking((int)$bid, $opts);
                    if (is_wp_error($res)) { $counts['errors']++; }
                }
            }
            // Clear waitlist
            $wpdb->delete($w, ['schedule_id' => $id], ['%d']);
            $counts['processed']++;
        }
        return new \WP_REST_Response(['success' => true, 'counts' => $counts], 200);
    }

    private function check_instructor_conflict($instructor_id, $start_time, $end_time, $exclude_id = null): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_schedules';
        
        $query = "SELECT COUNT(*) FROM $table WHERE instructor_id = %d AND 
                  ((start_time >= %s AND start_time < %s) OR 
                   (end_time > %s AND end_time <= %s) OR
                   (start_time <= %s AND end_time >= %s))";
        
        $params = [$instructor_id, $start_time, $end_time, $start_time, $end_time, $start_time, $end_time];
        
        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        return (bool) $wpdb->get_var($wpdb->prepare($query, ...$params));
    }

    private function check_resource_conflict($resource_id, $start_time, $end_time, $exclude_id = null): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_schedules';
        
        $query = "SELECT COUNT(*) FROM $table WHERE resource_id = %d AND 
                  ((start_time >= %s AND start_time < %s) OR 
                   (end_time > %s AND end_time <= %s) OR
                   (start_time <= %s AND end_time >= %s))";
        
        $params = [$resource_id, $start_time, $end_time, $start_time, $end_time, $start_time, $end_time];
        
        if ($exclude_id) {
            $query .= " AND id != %d";
            $params[] = $exclude_id;
        }
        
        return (bool) $wpdb->get_var($wpdb->prepare($query, ...$params));
    }

    public function reschedule_to($request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $data = $request->get_json_params();
        $target = isset($data['target_schedule_id']) ? (int)$data['target_schedule_id'] : 0;
        $notify = !empty($data['notify']);
        if ($target <= 0) return new \WP_REST_Response(['error' => 'Missing target_schedule_id'], 400);
        global $wpdb; $b=$wpdb->prefix.'cfp_bookings';
        $bookings = $wpdb->get_col($wpdb->prepare("SELECT id FROM $b WHERE schedule_id = %d AND status IN ('pending','confirmed')", $id));
        $moved=0; $errors=0;
        if ($bookings) {
            foreach ($bookings as $bid) {
                $res = \ClassFlowPro\Booking\Manager::admin_reschedule_booking((int)$bid, $target, ['notify' => $notify]);
                if (is_wp_error($res)) $errors++; else $moved++;
            }
        }
        return new \WP_REST_Response(['success'=>true,'moved'=>$moved,'errors'=>$errors], 200);
    }

    public function preview_cancel($request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $data = $request->get_json_params();
        $note = isset($data['note']) ? sanitize_textarea_field($data['note']) : '';
        global $wpdb; $s=$wpdb->prefix.'cfp_schedules';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id = %d", $id), ARRAY_A);
        if (!$row) return new \WP_REST_Response(['error'=>'not_found'],404);
        $title = \ClassFlowPro\Utils\Entities::class_name((int)$row['class_id']);
        $tz = \ClassFlowPro\Utils\Timezone::for_location(!empty($row['location_id']) ? (int)$row['location_id'] : null);
        $start = \ClassFlowPro\Utils\Timezone::format_local($row['start_time'], $tz);
        [$subject,$body] = \ClassFlowPro\Notifications\Mailer::build_canceled_email($title, $start, 'canceled', $note);
        return new \WP_REST_Response(['subject'=>$subject,'body'=>$body],200);
    }

}
