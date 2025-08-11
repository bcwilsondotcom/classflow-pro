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
            WHERE s.class_id = %d
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
}