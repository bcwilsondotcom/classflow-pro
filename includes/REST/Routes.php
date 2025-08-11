<?php
namespace ClassFlowPro\REST;

use WP_Error;
use WP_REST_Request;
use ClassFlowPro\Booking\Manager as BookingManager;
use ClassFlowPro\Payments\Webhooks as StripeWebhooks;
use ClassFlowPro\Accounting\QuickBooks;
use ClassFlowPro\Calendar\Google as GoogleCalendar;

class Routes
{
    public static function register(): void
    {
        // Register admin schedules endpoint
        $schedules_endpoint = new SchedulesEndpoint();
        $schedules_endpoint->register_routes();
        
        // Entities listings (first-class tables)
        register_rest_route('classflow/v1', '/entities/classes', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [self::class, 'list_classes'],
            'args' => [ 's' => ['type'=>'string','required'=>false], 'per_page' => ['type'=>'integer','required'=>false], 'page' => ['type'=>'integer','required'=>false] ],
        ]);
        register_rest_route('classflow/v1', '/entities/locations', [
            'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => [self::class, 'list_locations'],
            'args' => [ 's' => ['type'=>'string','required'=>false], 'per_page' => ['type'=>'integer','required'=>false], 'page' => ['type'=>'integer','required'=>false] ],
        ]);
        register_rest_route('classflow/v1', '/entities/instructors', [
            'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => [self::class, 'list_instructors'],
            'args' => [ 's' => ['type'=>'string','required'=>false], 'per_page' => ['type'=>'integer','required'=>false], 'page' => ['type'=>'integer','required'=>false] ],
        ]);
        register_rest_route('classflow/v1', '/entities/resources', [
            'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => [self::class, 'list_resources'],
            'args' => [ 's' => ['type'=>'string','required'=>false], 'per_page' => ['type'=>'integer','required'=>false], 'page' => ['type'=>'integer','required'=>false] ],
        ]);

        // Single class detail (for admin scheduling UI)
        register_rest_route('classflow/v1', '/classes/(?P<id>\d+)', [
            'methods' => 'GET',
            'permission_callback' => function () { return current_user_can('manage_options'); },
            'callback' => [self::class, 'get_class_detail'],
        ]);

        register_rest_route('classflow/v1', '/schedules', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_schedules'],
            'permission_callback' => '__return_true',
            'args' => [
                'class_id' => ['type' => 'integer', 'required' => false],
                'location_id' => ['type' => 'integer', 'required' => false],
                'instructor_id' => ['type' => 'integer', 'required' => false],
                'date_from' => ['type' => 'string', 'required' => false],
                'date_to' => ['type' => 'string', 'required' => false],
            ],
        ]);

        register_rest_route('classflow/v1', '/schedules/available', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_available_schedules'],
            'permission_callback' => '__return_true',
            'args' => [
                'class_id' => ['type' => 'integer', 'required' => true],
                'date_from' => ['type' => 'string', 'required' => false],
                'date_to' => ['type' => 'string', 'required' => false],
            ],
        ]);

        // iCal feeds
        register_rest_route('classflow/v1', '/ical/schedules', [
            'methods' => 'GET',
            'callback' => [self::class, 'ical_schedules'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('classflow/v1', '/ical/me_url', [
            'methods' => 'GET',
            'callback' => [self::class, 'ical_me_url'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);
        register_rest_route('classflow/v1', '/ical/my', [
            'methods' => 'GET',
            'callback' => [self::class, 'ical_my_feed'],
            'permission_callback' => '__return_true',
            'args' => [ 'token' => ['type' => 'string', 'required' => true] ],
        ]);

        register_rest_route('classflow/v1', '/book', [
            'methods' => 'POST',
            'callback' => [self::class, 'book'],
            'permission_callback' => function () {
                return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest');
            },
        ]);

        // Auth helpers for modal login/register
        register_rest_route('classflow/v1', '/login', [
            'methods' => 'POST',
            'callback' => [self::class, 'login_modal'],
            'permission_callback' => function () { return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);
        register_rest_route('classflow/v1', '/register', [
            'methods' => 'POST',
            'callback' => [self::class, 'register_modal'],
            'permission_callback' => function () { return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);

        // Admin: cancel a single booking with action (refund|credit|cancel|auto)
        register_rest_route('classflow-pro/v1', '/bookings/(?P<id>\\d+)/admin_cancel', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_cancel_booking'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ]);

        // Private session request
        register_rest_route('classflow/v1', '/private/request', [
            'methods' => 'POST',
            'callback' => [self::class, 'private_request'],
            'permission_callback' => function () { return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);

        register_rest_route('classflow/v1', '/waitlist/join', [
            'methods' => 'POST',
            'callback' => [self::class, 'waitlist_join'],
            'permission_callback' => function () { return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);
        register_rest_route('classflow/v1', '/waitlist/accept', [
            'methods' => 'POST',
            'callback' => [self::class, 'waitlist_accept'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('classflow/v1', '/waitlist/deny', [
            'methods' => 'POST',
            'callback' => [self::class, 'waitlist_deny'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('classflow/v1', '/payment_intent', [
            'methods' => 'POST',
            'callback' => [self::class, 'create_payment_intent'],
            'permission_callback' => function () {
                return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest');
            },
        ]);

        register_rest_route('classflow/v1', '/stripe/checkout_session', [
            'methods' => 'POST',
            'callback' => [self::class, 'create_checkout_session'],
            'permission_callback' => function () {
                return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest');
            },
        ]);

        register_rest_route('classflow/v1', '/stripe/webhook', [
            'methods' => 'POST',
            'callback' => [StripeWebhooks::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        // Inbound SMS webhook (Twilio): update opt-in status based on STOP/START
        register_rest_route('classflow/v1', '/sms/twilio_webhook', [
            'methods' => 'POST',
            'callback' => [self::class, 'sms_twilio_webhook'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('classflow/v1', '/packages/purchase', [
            'methods' => 'POST',
            'callback' => [self::class, 'purchase_package'],
            'permission_callback' => function () {
                return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest');
            },
        ]);

        register_rest_route('classflow/v1', '/quickbooks/connect', [
            'methods' => 'GET',
            'callback' => [QuickBooks::class, 'connect'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('classflow/v1', '/quickbooks/callback', [
            'methods' => 'GET',
            'callback' => [QuickBooks::class, 'callback'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('classflow/v1', '/quickbooks/receipt', [
            'methods' => 'GET',
            'callback' => [self::class, 'quickbooks_receipt_pdf'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
            'args' => [ 'booking_id' => ['type' => 'integer', 'required' => true] ],
        ]);

        // Google Calendar connect (optional)
        register_rest_route('classflow/v1', '/google/connect', [
            'methods' => 'GET',
            'callback' => [GoogleCalendar::class, 'connect'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ]);
        register_rest_route('classflow/v1', '/google/callback', [
            'methods' => 'GET',
            'callback' => [GoogleCalendar::class, 'callback'],
            'permission_callback' => '__return_true',
        ]);

        // Client portal endpoints (auth required)
        register_rest_route('classflow/v1', '/me/overview', [
            'methods' => 'GET',
            'callback' => [self::class, 'me_overview'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);
        register_rest_route('classflow/v1', '/me/profile', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_my_profile'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);
        register_rest_route('classflow/v1', '/me/profile', [
            'methods' => 'POST',
            'callback' => [self::class, 'update_my_profile'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);
        register_rest_route('classflow/v1', '/me/notes', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_my_notes'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);
        register_rest_route('classflow/v1', '/me/intake', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_my_intake'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);
        register_rest_route('classflow/v1', '/me/intake', [
            'methods' => 'POST',
            'callback' => [self::class, 'submit_my_intake'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);

        register_rest_route('classflow/v1', '/bookings/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [self::class, 'cancel_booking'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);

        register_rest_route('classflow/v1', '/bookings/(?P<id>\d+)/reschedule', [
            'methods' => 'POST',
            'callback' => [self::class, 'reschedule_booking'],
            'permission_callback' => function () { return is_user_logged_in() && wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest'); },
        ]);
    }

    public static function get_schedules(WP_REST_Request $req)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_schedules';
        $where = [];
        $params = [];
        if ($req['class_id']) {
            $where[] = 'class_id = %d';
            $params[] = (int)$req['class_id'];
        }
        if ($req['location_id']) {
            $where[] = 'location_id = %d';
            $params[] = (int)$req['location_id'];
        }
        if ($req['instructor_id']) {
            $where[] = 'instructor_id = %d';
            $params[] = (int)$req['instructor_id'];
        }
        if ($req['date_from']) {
            $where[] = 'start_time >= %s';
            $params[] = gmdate('Y-m-d H:i:s', strtotime($req['date_from']));
        }
        if ($req['date_to']) {
            $where[] = 'start_time <= %s';
            $params[] = gmdate('Y-m-d H:i:s', strtotime($req['date_to']));
        }
        // Hide cancelled schedules by default
        $where[] = "COALESCE(status,'active') <> 'cancelled'";
        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT * FROM $table $where_sql ORDER BY start_time ASC LIMIT 500";
        $prepared = $params ? $wpdb->prepare($sql, $params) : $sql;
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        foreach ($rows as &$row) {
            $row['class_title'] = \ClassFlowPro\Utils\Entities::class_name((int)$row['class_id']);
            $row['instructor_name'] = $row['instructor_id'] ? \ClassFlowPro\Utils\Entities::instructor_name((int)$row['instructor_id']) : '';
            $row['location_name'] = $row['location_id'] ? \ClassFlowPro\Utils\Entities::location_name((int)$row['location_id']) : '';
            $row['tz'] = \ClassFlowPro\Utils\Timezone::for_schedule_row($row);
            // Add current bookings count for admin calendar filtering
            try {
                $b_tbl = $wpdb->prefix . 'cfp_bookings';
                $row['booked_count'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $b_tbl WHERE schedule_id = %d AND status IN ('pending','confirmed')", (int)$row['id']));
            } catch (\Throwable $e) { $row['booked_count'] = 0; }
        }
        return rest_ensure_response($rows);
    }

    public static function get_available_schedules(WP_REST_Request $req)
    {
        global $wpdb;
        $class_id = intval($req['class_id']);
        $date_from = $req['date_from'] ? gmdate('Y-m-d H:i:s', strtotime($req['date_from'])) : gmdate('Y-m-d H:i:s');
        $date_to = $req['date_to'] ? gmdate('Y-m-d H:i:s', strtotime($req['date_to'])) : null;
        $s = $wpdb->prefix . 'cfp_schedules';
        $c = $wpdb->prefix . 'cfp_classes';
        $b = $wpdb->prefix . 'cfp_bookings';
        $where = $wpdb->prepare("WHERE s.class_id = %d AND s.start_time >= %s", $class_id, $date_from);
        if ($date_to) {
            $where .= $wpdb->prepare(" AND s.start_time <= %s", $date_to);
        }
        $where .= " AND COALESCE(s.status,'active') <> 'cancelled'";
        $sql = "SELECT s.*, (
                    s.capacity - (SELECT COUNT(*) FROM $b bb WHERE bb.schedule_id = s.id AND bb.status IN ('pending','confirmed'))
                ) AS seats_left,
                c.price_cents AS class_price_cents,
                c.currency AS class_currency
                FROM $s s LEFT JOIN $c c ON c.id = s.class_id
                $where HAVING seats_left > 0 ORDER BY s.start_time ASC LIMIT 200";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        // Add titles
        foreach ($rows as &$row) {
            // Price fallback
            $row['price_cents'] = isset($row['price_cents']) ? (int)$row['price_cents'] : 0;
            if ($row['price_cents'] <= 0) {
                $row['price_cents'] = isset($row['class_price_cents']) ? (int)$row['class_price_cents'] : 0;
                if (empty($row['currency']) && !empty($row['class_currency'])) {
                    $row['currency'] = $row['class_currency'];
                }
            }
            unset($row['class_price_cents'], $row['class_currency']);
            $row['class_title'] = \ClassFlowPro\Utils\Entities::class_name((int)$row['class_id']);
            $row['instructor_name'] = $row['instructor_id'] ? \ClassFlowPro\Utils\Entities::instructor_name((int)$row['instructor_id']) : '';
            $row['location_name'] = $row['location_id'] ? \ClassFlowPro\Utils\Entities::location_name((int)$row['location_id']) : '';
            $row['tz'] = \ClassFlowPro\Utils\Timezone::for_schedule_row($row);
        }
        return rest_ensure_response($rows);
    }

    // Entities list handlers
    public static function list_classes(WP_REST_Request $req)
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_classes';
        $s=trim((string)$req->get_param('s')); $per=max(1,min(200,(int)($req->get_param('per_page')?:100))); $page=max(1,(int)($req->get_param('page')?:1)); $off=($page-1)*$per;
        $where=''; $params=[]; if($s!==''){ $where='WHERE name LIKE %s'; $params[]='%'.$wpdb->esc_like($s).'%'; }
        $sql = $wpdb->prepare("SELECT id, name FROM $t $where ORDER BY name ASC LIMIT %d OFFSET %d", ...array_merge($params, [$per,$off]));
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return rest_ensure_response(array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name']], $rows));
    }
    public static function list_locations(WP_REST_Request $req)
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_locations'; $s=trim((string)$req->get_param('s')); $per=max(1,min(200,(int)($req->get_param('per_page')?:100))); $page=max(1,(int)($req->get_param('page')?:1)); $off=($page-1)*$per;
        $where=''; $params=[]; if($s!==''){ $where='WHERE name LIKE %s'; $params[]='%'.$wpdb->esc_like($s).'%'; }
        $sql = $wpdb->prepare("SELECT id, name FROM $t $where ORDER BY name ASC LIMIT %d OFFSET %d", ...array_merge($params, [$per,$off]));
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return rest_ensure_response(array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name']], $rows));
    }
    public static function list_instructors(WP_REST_Request $req)
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_instructors'; $s=trim((string)$req->get_param('s')); $per=max(1,min(200,(int)($req->get_param('per_page')?:100))); $page=max(1,(int)($req->get_param('page')?:1)); $off=($page-1)*$per;
        $where=''; $params=[]; if($s!==''){ $where='WHERE name LIKE %s'; $params[]='%'.$wpdb->esc_like($s).'%'; }
        $sql = $wpdb->prepare("SELECT id, name FROM $t $where ORDER BY name ASC LIMIT %d OFFSET %d", ...array_merge($params, [$per,$off]));
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return rest_ensure_response(array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name']], $rows));
    }
    public static function list_resources(WP_REST_Request $req)
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_resources'; $s=trim((string)$req->get_param('s')); $per=max(1,min(200,(int)($req->get_param('per_page')?:100))); $page=max(1,(int)($req->get_param('page')?:1)); $off=($page-1)*$per;
        $where=''; $params=[]; if($s!==''){ $where='WHERE name LIKE %s'; $params[]='%'.$wpdb->esc_like($s).'%'; }
        $sql = $wpdb->prepare("SELECT id, name FROM $t $where ORDER BY name ASC LIMIT %d OFFSET %d", ...array_merge($params, [$per,$off]));
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        return rest_ensure_response(array_map(fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name']], $rows));
    }

    public static function get_class_detail(WP_REST_Request $req)
    {
        $id = (int)$req['id'];
        global $wpdb; $t=$wpdb->prefix.'cfp_classes';
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, name, duration_mins, capacity, price_cents, currency, default_location_id FROM $t WHERE id = %d", $id), ARRAY_A);
        if (!$row) return new WP_Error('cfp_not_found', __('Class not found', 'classflow-pro'), ['status' => 404]);
        $row['id']=(int)$row['id']; $row['duration_mins']=(int)$row['duration_mins']; $row['capacity']=(int)$row['capacity']; $row['price_cents']=(int)$row['price_cents']; $row['default_location_id'] = $row['default_location_id'] ? (int)$row['default_location_id'] : null;
        return rest_ensure_response($row);
    }

    public static function book(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $schedule_id = intval($data['schedule_id'] ?? 0);
        $use_credits = !empty($data['use_credits']);
        $customer = [
            'user_id' => get_current_user_id() ?: null,
            'email' => sanitize_email($data['email'] ?? ''),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'coupon_code' => isset($data['coupon_code']) ? sanitize_text_field($data['coupon_code']) : null,
        ];

        // Booking access policy
        $require_login = (bool) \ClassFlowPro\Admin\Settings::get('require_login_to_book', 0);
        $auto_create = (bool) \ClassFlowPro\Admin\Settings::get('auto_create_user_on_booking', 1);
        if ($require_login && empty($customer['user_id'])) {
            return new WP_Error('cfp_auth_required', __('Please log in to book.', 'classflow-pro'), ['status' => 401]);
        }
        if (!$customer['user_id'] && $auto_create && $customer['email']) {
            // Find or create a WordPress user by email
            $existing = get_user_by('email', $customer['email']);
            if ($existing) {
                $customer['user_id'] = (int)$existing->ID;
            } else {
                $username = sanitize_user(current(explode('@', $customer['email'])));
                if (username_exists($username)) { $username .= '_' . wp_generate_password(4, false, false); }
                $pass = !empty($data['password']) ? (string)$data['password'] : wp_generate_password(20, false, false);
                $uid = wp_create_user($username, $pass, $customer['email']);
                if (!is_wp_error($uid)) {
                    $customer['user_id'] = (int)$uid;
                    if (!empty($customer['name'])) {
                        wp_update_user(['ID' => $uid, 'display_name' => $customer['name']]);
                    }
                    // If password was generated (not provided), notify user to set it
                    if (empty($data['password']) && function_exists('wp_send_new_user_notifications')) { wp_send_new_user_notifications($uid, 'user'); }
                }
            }
        }

        // If we have a user_id, optionally store phone and SMS preference from the booking form
        if (!empty($customer['user_id'])) {
            if (!empty($data['phone'])) { update_user_meta((int)$customer['user_id'], 'cfp_phone', sanitize_text_field($data['phone'])); }
            if (isset($data['sms_opt_in'])) { update_user_meta((int)$customer['user_id'], 'cfp_sms_opt_in', !empty($data['sms_opt_in']) ? 1 : 0); }
        }
        // Intake requirement: if enabled and user account exists, flag but do not block booking
        $intake_required = false;
        if ($customer['user_id'] && \ClassFlowPro\Admin\Settings::get('require_intake', 0)) {
            global $wpdb;
            $intake = $wpdb->prefix . 'cfp_intake_forms';
            $has = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $intake WHERE user_id = %d", (int)$customer['user_id']));
            if ($has <= 0) { $intake_required = true; }
        }
        if (!$schedule_id || (!$customer['user_id'] && empty($customer['email']))) {
            return new WP_Error('cfp_invalid_request', __('Missing schedule or customer info', 'classflow-pro'), ['status' => 400]);
        }
        $result = BookingManager::book($schedule_id, $customer, $use_credits);
        if (is_wp_error($result)) {
            return $result;
        }
        $result['intake_required'] = $intake_required;
        return rest_ensure_response($result);
    }

    public static function private_request(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $user_id = get_current_user_id() ?: null;
        $email = sanitize_email($data['email'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');
        $instructor_id = (int)($data['instructor_id'] ?? 0);
        $preferred_date = sanitize_text_field($data['date'] ?? '');
        $preferred_time = sanitize_text_field($data['time'] ?? '');
        $notes = sanitize_textarea_field($data['notes'] ?? '');
        if (!$email) return new WP_Error('cfp_invalid_request', __('Email is required', 'classflow-pro'), ['status' => 400]);
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_private_requests';
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'name' => $name,
            'email' => $email,
            'instructor_id' => $instructor_id ?: null,
            'preferred_date' => $preferred_date ?: null,
            'preferred_time' => $preferred_time ?: null,
            'notes' => $notes,
            'status' => 'pending',
        ], ['%d','%s','%s','%d','%s','%s','%s','%s']);
        $id = (int)$wpdb->insert_id;
        // notify admin
        if (\ClassFlowPro\Admin\Settings::get('notify_admin', 1)) {
            wp_mail(get_option('admin_email'), __('New Private Session Request', 'classflow-pro'), 'Request #' . $id . ' from ' . $name . ' (' . $email . ')');
        }
        return rest_ensure_response(['request_id' => $id, 'status' => 'pending']);
    }

    public static function waitlist_join(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $schedule_id = intval($data['schedule_id'] ?? 0);
        $email = sanitize_email($data['email'] ?? '');
        $user_id = get_current_user_id() ?: null;
        if (!$schedule_id || !$email) return new WP_Error('cfp_invalid_request', __('Missing schedule_id or email', 'classflow-pro'), ['status' => 400]);
        global $wpdb;
        $waitlist = $wpdb->prefix . 'cfp_waitlist';
        $wpdb->insert($waitlist, [ 'schedule_id' => $schedule_id, 'user_id' => $user_id, 'email' => $email ], ['%d','%d','%s']);
        try { \ClassFlowPro\Notifications\Mailer::waitlist_joined($schedule_id, $email); } catch (\Throwable $e) {}
        try { if ($user_id) \ClassFlowPro\Notifications\Sms::waitlist_open($schedule_id, $user_id); } catch (\Throwable $e) {}
        return rest_ensure_response(['status' => 'joined']);
    }

    // Public endpoints for waitlist offer response
    public static function waitlist_accept(WP_REST_Request $req)
    {
        $token = sanitize_text_field($req->get_param('token'));
        if (!$token) return new WP_Error('cfp_invalid_request', __('Missing token', 'classflow-pro'), ['status' => 400]);
        global $wpdb; $wl=$wpdb->prefix.'cfp_waitlist'; $sc=$wpdb->prefix.'cfp_schedules';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wl WHERE token = %s AND status='offered'", $token), ARRAY_A);
        if (!$row) return new WP_Error('cfp_not_found', __('Offer not found or expired', 'classflow-pro'), ['status' => 404]);
        if (!empty($row['expires_at']) && strtotime($row['expires_at'].' UTC') < time()) return new WP_Error('cfp_expired', __('Offer expired', 'classflow-pro'), ['status' => 410]);
        $schedule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $sc WHERE id = %d", (int)$row['schedule_id']), ARRAY_A);
        if (!$schedule) return new WP_Error('cfp_not_found', __('Schedule not found', 'classflow-pro'), ['status' => 404]);
        // Use credits if available; otherwise pending payment
        $customer = [ 'user_id' => $row['user_id'] ?: null, 'email' => $row['email'], 'name' => '' ];
        $result = \ClassFlowPro\Booking\Manager::book((int)$row['schedule_id'], $customer, true);
        if (is_wp_error($result)) return $result;
        // Mark waitlist row
        $wpdb->update($wl, [ 'status' => 'accepted' ], ['id' => (int)$row['id']], ['%s'], ['%d']);
        // If amount due, create Stripe Checkout session and return URL
        $checkout = null;
        if (!empty($result['amount_cents']) && (int)$result['amount_cents'] > 0) {
            $session = self::create_checkout_for_booking_id((int)$result['booking_id']);
            if (is_wp_error($session)) { /* ignore here */ } else { $checkout = $session['url'] ?? null; }
        }
        return rest_ensure_response([ 'ok' => true, 'booking' => $result, 'checkout_url' => $checkout ]);
    }

    public static function waitlist_deny(WP_REST_Request $req)
    {
        $token = sanitize_text_field($req->get_param('token'));
        if (!$token) return new WP_Error('cfp_invalid_request', __('Missing token', 'classflow-pro'), ['status' => 400]);
        global $wpdb; $wl=$wpdb->prefix.'cfp_waitlist';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wl WHERE token = %s AND status='offered'", $token), ARRAY_A);
        if (!$row) return new WP_Error('cfp_not_found', __('Offer not found or expired', 'classflow-pro'), ['status' => 404]);
        $wpdb->update($wl, [ 'status' => 'declined' ], ['id' => (int)$row['id']], ['%s'], ['%d']);
        // Optionally promote next in line
        try { \ClassFlowPro\Booking\Manager::promote_waitlist_public((int)$row['schedule_id']); } catch (\Throwable $e) {}
        return rest_ensure_response([ 'ok' => true ]);
    }

    // helper to create checkout for a booking_id (no auth)
    private static function create_checkout_for_booking_id(int $booking_id)
    {
        global $wpdb; $book=$wpdb->prefix.'cfp_bookings';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $book WHERE id = %d", $booking_id), ARRAY_A);
        if (!$row) return new \WP_Error('cfp_not_found', __('Booking not found', 'classflow-pro'), ['status' => 404]);
        $req = new \WP_REST_Request('POST');
        $req->set_param('booking_id', $booking_id);
        $session = self::create_checkout_session($req);
        if (is_wp_error($session)) return $session;
        return $session;
    }

    public static function create_payment_intent(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $booking_id = intval($data['booking_id'] ?? 0);
        $payment_method = sanitize_text_field($data['payment_method'] ?? 'card');
        $name = sanitize_text_field($data['name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        if (!$booking_id) {
            return new WP_Error('cfp_invalid_request', __('Missing booking_id', 'classflow-pro'), ['status' => 400]);
        }
        $intent = BookingManager::create_payment_intent($booking_id, $payment_method, $name, $email);
        if (is_wp_error($intent)) {
            return $intent;
        }
        return rest_ensure_response($intent);
    }

    public static function create_checkout_session(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $booking_id = (int)($data['booking_id'] ?? 0);
        if (!$booking_id) return new WP_Error('cfp_invalid_request', __('Missing booking_id', 'classflow-pro'), ['status' => 400]);
        global $wpdb;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $schedules = $wpdb->prefix . 'cfp_schedules';
        $row = $wpdb->get_row($wpdb->prepare("SELECT b.*, s.class_id, s.instructor_id, s.location_id, s.start_time FROM $bookings b JOIN $schedules s ON s.id = b.schedule_id WHERE b.id = %d", $booking_id), ARRAY_A);
        if (!$row) return new WP_Error('cfp_not_found', __('Booking not found', 'classflow-pro'), ['status' => 404]);
        $amount_cents = (int)$row['amount_cents'];
        if ($amount_cents <= 0) return new WP_Error('cfp_free_booking', __('No payment required for this booking.', 'classflow-pro'), ['status' => 400]);

        $class_title = \ClassFlowPro\Utils\Entities::class_name((int)$row['class_id']);
        $desc = $class_title . ' — ' . gmdate('Y-m-d H:i', strtotime($row['start_time'])) . ' UTC';
        $default_success = add_query_arg(['cfp_checkout' => 'success', 'booking_id' => $booking_id], home_url('/'));
        $default_cancel = add_query_arg(['cfp_checkout' => 'cancel', 'booking_id' => $booking_id], home_url('/'));
        $conf_success = \ClassFlowPro\Admin\Settings::get('checkout_success_url', '');
        $conf_cancel = \ClassFlowPro\Admin\Settings::get('checkout_cancel_url', '');

        // Ensure success/cancel URLs are absolute and valid for Stripe Checkout
        $make_absolute = function($url, $fallback) {
            $url = trim((string)$url);
            if (!$url) return $fallback;
            // If it's already a valid absolute URL, keep it
            if (function_exists('wp_http_validate_url') && wp_http_validate_url($url)) {
                return $url;
            }
            // Support site-relative paths like /thank-you
            if (str_starts_with($url, '/')) {
                $abs = home_url($url);
                if (!function_exists('wp_http_validate_url') || wp_http_validate_url($abs)) return $abs;
            }
            // Fallback if still invalid
            return $fallback;
        };

        $success_url = $make_absolute($conf_success, $default_success);
        $cancel_url = $make_absolute($conf_cancel, $default_cancel);

        // Always use USD for Stripe Checkout Session
        $currency = 'usd';

        $session = \ClassFlowPro\Payments\StripeGateway::create_checkout_session([
            'amount_cents' => $amount_cents,
            'currency' => $currency,
            'class_title' => $class_title,
            'description' => $desc,
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'booking_id' => $booking_id,
            'instructor_id' => (int)$row['instructor_id'],
        ]);
        if (is_wp_error($session)) return $session;
        // Attach session id to booking for reference
        $wpdb->update($bookings, ['payment_intent_id' => $session['id']], ['id' => $booking_id], ['%s'], ['%d']);
        return rest_ensure_response(['id' => $session['id'], 'url' => $session['url']]);
    }

    public static function login_modal(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $user_login = sanitize_text_field($data['user_login'] ?? '');
        $password = (string)($data['password'] ?? '');
        $remember = !empty($data['remember']);
        if (!$user_login || !$password) return new \WP_Error('cfp_invalid', __('Missing credentials', 'classflow-pro'), ['status' => 400]);
        $creds = [ 'user_login' => $user_login, 'user_password' => $password, 'remember' => $remember ];
        $user = wp_signon($creds, false);
        if (is_wp_error($user)) return $user;
        return rest_ensure_response(['ok' => true, 'user_id' => (int)$user->ID]);
    }

    public static function register_modal(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $email = sanitize_email($data['email'] ?? '');
        $password = (string)($data['password'] ?? '');
        $name = sanitize_text_field($data['name'] ?? '');
        if (!$email || !$password) return new \WP_Error('cfp_invalid', __('Email and password are required', 'classflow-pro'), ['status' => 400]);
        if (email_exists($email)) return new \WP_Error('cfp_exists', __('Email already registered', 'classflow-pro'), ['status' => 400]);
        $username = sanitize_user(current(explode('@', $email)));
        if (username_exists($username)) { $username .= '_' . wp_generate_password(4, false, false); }
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) return $user_id;
        if ($name) { wp_update_user(['ID' => $user_id, 'display_name' => $name]); }
        // Auto sign in
        $user = wp_signon([ 'user_login' => $username, 'user_password' => $password, 'remember' => true ], false);
        if (is_wp_error($user)) return $user;
        return rest_ensure_response(['ok' => true, 'user_id' => (int)$user_id]);
    }

    public static function purchase_package(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $name = sanitize_text_field($data['name'] ?? '');
        $credits = max(1, intval($data['credits'] ?? 0));
        $price_cents = max(50, intval($data['price_cents'] ?? 0));
        $buyer_name = sanitize_text_field($data['buyer_name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $user_id = get_current_user_id();
        if (\ClassFlowPro\Admin\Settings::get('stripe_use_checkout', 0)) {
            $session = \ClassFlowPro\Packages\Manager::create_checkout_session($user_id ?: null, $name, $credits, $price_cents, $email, $buyer_name);
            if (is_wp_error($session)) return $session;
            return rest_ensure_response($session);
        } else {
            $intent = \ClassFlowPro\Packages\Manager::create_purchase_intent($user_id ?: null, $name, $credits, $price_cents, $email, $buyer_name);
            if (is_wp_error($intent)) return $intent;
            return rest_ensure_response($intent);
        }
    }

    public static function me_overview(WP_REST_Request $req)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('cfp_not_logged_in', __('Authentication required', 'classflow-pro'), ['status' => 401]);
        }
        global $wpdb;
        $bookings_tbl = $wpdb->prefix . 'cfp_bookings';
        $schedules_tbl = $wpdb->prefix . 'cfp_schedules';
        $now = gmdate('Y-m-d H:i:s');
        $upcoming = $wpdb->get_results($wpdb->prepare("SELECT b.*, s.class_id, s.location_id, s.start_time, s.end_time FROM $bookings_tbl b JOIN $schedules_tbl s ON s.id = b.schedule_id WHERE b.user_id = %d AND s.start_time >= %s AND b.status IN ('pending','confirmed') ORDER BY s.start_time ASC LIMIT 50", $user_id, $now), ARRAY_A);
        $past = $wpdb->get_results($wpdb->prepare("SELECT b.*, s.class_id, s.location_id, s.start_time, s.end_time FROM $bookings_tbl b JOIN $schedules_tbl s ON s.id = b.schedule_id WHERE b.user_id = %d AND s.start_time < %s ORDER BY s.start_time DESC LIMIT 50", $user_id, $now), ARRAY_A);
        $credits = \ClassFlowPro\Packages\Manager::get_user_credits($user_id);
        return rest_ensure_response([
            'credits' => $credits,
            'upcoming' => array_map([self::class, 'map_booking'], $upcoming),
            'past' => array_map([self::class, 'map_booking'], $past),
            'packages' => self::get_packages($user_id),
        ]);
    }

    private static function map_booking(array $b): array
    {
        $out = [
            'id' => (int)$b['id'],
            'status' => $b['status'],
            'class_title' => \ClassFlowPro\Utils\Entities::class_name((int)$b['class_id']),
            'class_id' => (int)$b['class_id'],
            'schedule_id' => (int)$b['schedule_id'],
            'location_name' => !empty($b['location_id']) ? get_the_title((int)$b['location_id']) : '',
            'start_time' => $b['start_time'],
            'end_time' => $b['end_time'],
            'amount_cents' => (int)$b['amount_cents'],
            'currency' => $b['currency'],
            'credits_used' => (int)$b['credits_used'] > 0,
        ];
        // timezone
        $out['timezone'] = \ClassFlowPro\Utils\Timezone::for_location(!empty($b['location_id']) ? (int)$b['location_id'] : null);
        // Attach receipts info
        global $wpdb;
        $tx = $wpdb->prefix . 'cfp_transactions';
        $stripe = $wpdb->get_row($wpdb->prepare("SELECT receipt_url FROM $tx WHERE booking_id = %d AND processor = 'stripe' AND status='succeeded' ORDER BY id DESC LIMIT 1", (int)$b['id']), ARRAY_A);
        $qb = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tx WHERE booking_id = %d AND processor = 'quickbooks' AND type='sales_receipt'", (int)$b['id'])) > 0;
        if (!empty($stripe['receipt_url'])) $out['receipt_url'] = $stripe['receipt_url'];
        $out['has_quickbooks_receipt'] = $qb;
        return $out;
    }

    private static function get_packages(int $user_id): array
    {
        global $wpdb;
        $pk = $wpdb->prefix . 'cfp_packages';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, name, credits, credits_remaining, price_cents, currency, expires_at, created_at FROM $pk WHERE user_id = %d ORDER BY created_at DESC LIMIT 100", $user_id), ARRAY_A);
        foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['credits']=(int)$r['credits']; $r['credits_remaining']=(int)$r['credits_remaining']; $r['price_cents']=(int)$r['price_cents']; }
        return $rows;
    }

    public static function get_my_profile(WP_REST_Request $req)
    {
        $u = wp_get_current_user();
        $profile = [
            'display_name' => $u->display_name,
            'email' => $u->user_email,
            'phone' => get_user_meta($u->ID, 'cfp_phone', true) ?: '',
            'sms_opt_in' => (int)get_user_meta($u->ID, 'cfp_sms_opt_in', true) === 1,
            'dob' => get_user_meta($u->ID, 'cfp_dob', true) ?: '',
            'emergency_name' => get_user_meta($u->ID, 'cfp_emergency_name', true) ?: '',
            'emergency_phone' => get_user_meta($u->ID, 'cfp_emergency_phone', true) ?: '',
        ];
        return rest_ensure_response($profile);
    }

    public static function update_my_profile(WP_REST_Request $req)
    {
        $u = wp_get_current_user();
        $data = $req->get_json_params();
        $fields = [
            'cfp_phone' => sanitize_text_field($data['phone'] ?? ''),
            'cfp_sms_opt_in' => !empty($data['sms_opt_in']) ? 1 : 0,
            'cfp_dob' => sanitize_text_field($data['dob'] ?? ''),
            'cfp_emergency_name' => sanitize_text_field($data['emergency_name'] ?? ''),
            'cfp_emergency_phone' => sanitize_text_field($data['emergency_phone'] ?? ''),
        ];
        foreach ($fields as $k => $v) { update_user_meta($u->ID, $k, $v); }
        return rest_ensure_response(['ok' => true]);
    }

    public static function get_my_notes(WP_REST_Request $req)
    {
        $u = wp_get_current_user();
        global $wpdb; $t=$wpdb->prefix.'cfp_customer_notes';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT note, created_at FROM $t WHERE user_id = %d AND visible_to_user = 1 ORDER BY created_at DESC LIMIT 100", (int)$u->ID), ARRAY_A);
        return rest_ensure_response($rows ?: []);
    }

    public static function get_my_intake(WP_REST_Request $req)
    {
        $user_id = get_current_user_id();
        global $wpdb;
        $t = $wpdb->prefix . 'cfp_intake_forms';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id = %d ORDER BY signed_at DESC LIMIT 1", $user_id), ARRAY_A);
        if (!$row) return rest_ensure_response(['data' => null]);
        return rest_ensure_response(['data' => json_decode($row['data'], true), 'signed_at' => $row['signed_at'], 'version' => $row['version']]);
    }

    public static function submit_my_intake(WP_REST_Request $req)
    {
        $user_id = get_current_user_id();
        $data = $req->get_json_params();
        $payload = [
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'dob' => sanitize_text_field($data['dob'] ?? ''),
            'emergency_name' => sanitize_text_field($data['emergency_name'] ?? ''),
            'emergency_phone' => sanitize_text_field($data['emergency_phone'] ?? ''),
            'medical' => sanitize_textarea_field($data['medical'] ?? ''),
            'injuries' => sanitize_textarea_field($data['injuries'] ?? ''),
            'pregnant' => !empty($data['pregnant']) ? 1 : 0,
            'signature' => sanitize_text_field($data['signature'] ?? ''),
            'consent' => !empty($data['consent']) ? 1 : 0,
        ];
        if (empty($payload['signature']) || empty($payload['consent'])) {
            return new WP_Error('cfp_invalid', __('Signature and consent required', 'classflow-pro'), ['status' => 400]);
        }
        global $wpdb;
        $t = $wpdb->prefix . 'cfp_intake_forms';
        $wpdb->insert($t, [
            'user_id' => $user_id,
            'data' => wp_json_encode($payload),
            'version' => 'v1',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ], ['%d','%s','%s','%s','%s']);
        return rest_ensure_response(['ok' => true]);
    }

    public static function quickbooks_receipt_pdf(WP_REST_Request $req)
    {
        $user_id = get_current_user_id();
        $booking_id = (int)$req->get_param('booking_id');
        global $wpdb;
        $bookings = $wpdb->prefix . 'cfp_bookings';
        $owner = (int)$wpdb->get_var($wpdb->prepare("SELECT user_id FROM $bookings WHERE id = %d", $booking_id));
        if ($owner !== $user_id) return new WP_Error('cfp_forbidden', __('Not allowed', 'classflow-pro'), ['status' => 403]);
        $tx = $wpdb->prefix . 'cfp_transactions';
        $qb = $wpdb->get_row($wpdb->prepare("SELECT processor_id FROM $tx WHERE booking_id = %d AND processor='quickbooks' AND type='sales_receipt' ORDER BY id DESC LIMIT 1", $booking_id), ARRAY_A);
        if (!$qb || empty($qb['processor_id'])) return new WP_Error('cfp_not_found', __('No QuickBooks receipt found', 'classflow-pro'), ['status' => 404]);
        $res = \ClassFlowPro\Accounting\QuickBooks::download_sales_receipt_pdf($qb['processor_id']);
        if (is_wp_error($res)) return $res;
        $body = wp_remote_retrieve_body($res);
        return new \WP_REST_Response($body, 200, [ 'Content-Type' => 'application/pdf' ]);
    }

    public static function cancel_booking(WP_REST_Request $req)
    {
        $user_id = get_current_user_id();
        $id = (int)$req['id'];
        $result = \ClassFlowPro\Booking\Manager::cancel($id, $user_id);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function admin_cancel_booking(WP_REST_Request $req)
    {
        $id = (int)$req['id'];
        $data = $req->get_json_params();
        $action = isset($data['action']) ? sanitize_text_field($data['action']) : 'auto';
        $notify = !empty($data['notify']);
        $note = isset($data['note']) ? sanitize_textarea_field($data['note']) : '';
        $res = \ClassFlowPro\Booking\Manager::admin_cancel_booking($id, [ 'action' => $action, 'notify' => $notify, 'note' => $note ]);
        if (is_wp_error($res)) return $res;
        return rest_ensure_response($res);
    }

    public static function reschedule_booking(WP_REST_Request $req)
    {
        $user_id = get_current_user_id();
        $id = (int)$req['id'];
        $data = $req->get_json_params();
        $schedule_id = (int)($data['schedule_id'] ?? 0);
        if (!$schedule_id) return new WP_Error('cfp_invalid_request', __('Missing schedule_id', 'classflow-pro'), ['status' => 400]);
        $result = \ClassFlowPro\Booking\Manager::reschedule($id, $user_id, $schedule_id);
        if (is_wp_error($result)) return $result;
        return rest_ensure_response($result);
    }

    public static function sms_twilio_webhook(WP_REST_Request $req)
    {
        $from = sanitize_text_field($req->get_param('From'));
        $body = strtoupper(trim((string)$req->get_param('Body')));
        if (!$from || !$body) return rest_ensure_response(['ok' => true]);
        global $wpdb; $um=$wpdb->usermeta;
        $user_id = (int)$wpdb->get_var($wpdb->prepare("SELECT user_id FROM $um WHERE meta_key='cfp_phone' AND meta_value=%s LIMIT 1", $from));
        if ($user_id > 0) {
            if (in_array($body, ['STOP','STOPALL','UNSUBSCRIBE','CANCEL','END'], true)) {
                update_user_meta($user_id, 'cfp_sms_opt_in', 0);
            } elseif (in_array($body, ['START','UNSTOP','YES'], true)) {
                update_user_meta($user_id, 'cfp_sms_opt_in', 1);
            }
        }
        return rest_ensure_response(['ok' => true]);
    }

    // iCal schedule feed
    public static function ical_schedules(WP_REST_Request $req)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_schedules';
        $where = ['start_time >= %s'];
        $params = [ gmdate('Y-m-d H:i:s', strtotime('-7 days')) ];
        if ($req['class_id']) { $where[] = 'class_id = %d'; $params[] = (int)$req['class_id']; }
        if ($req['location_id']) { $where[] = 'location_id = %d'; $params[] = (int)$req['location_id']; }
        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY start_time ASC LIMIT 1000";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        $events = [];
        foreach ($rows as $r) {
            $class_title = \ClassFlowPro\Utils\Entities::class_name((int)$r['class_id']);
            $loc = !empty($r['location_id']) ? get_the_title((int)$r['location_id']) : '';
            $summary = $class_title;
            $desc = $loc ? ('Location: ' . $loc) : '';
            $events[] = [
                'uid' => 'cfp-sched-' . $r['id'] . '@' . parse_url(home_url(), PHP_URL_HOST),
                'start' => $r['start_time'],
                'end' => $r['end_time'],
                'summary' => $summary,
                'description' => $desc,
                'location' => $loc,
                'url' => get_permalink((int)$r['class_id']) ?: home_url('/'),
            ];
        }
        $ics = \ClassFlowPro\Calendar\Ical::build($events);
        header('Content-Type: text/calendar; charset=utf-8');
        echo $ics;
        exit;
    }

    public static function ical_me_url(WP_REST_Request $req)
    {
        $user_id = get_current_user_id();
        if (!$user_id) return new WP_Error('cfp_not_logged_in', __('Authentication required', 'classflow-pro'), ['status' => 401]);
        $token = get_user_meta($user_id, 'cfp_ical_token', true);
        if (!$token) {
            $token = wp_generate_password(24, false, false);
            update_user_meta($user_id, 'cfp_ical_token', $token);
        }
        $url = add_query_arg(['token' => $token], rest_url('classflow/v1/ical/my'));
        return rest_ensure_response(['url' => $url]);
    }

    public static function ical_my_feed(WP_REST_Request $req)
    {
        $token = sanitize_text_field($req['token']);
        global $wpdb;
        $usermeta = $wpdb->usermeta;
        $user_id = (int)$wpdb->get_var($wpdb->prepare("SELECT user_id FROM $usermeta WHERE meta_key = 'cfp_ical_token' AND meta_value = %s", $token));
        if (!$user_id) {
            return new WP_Error('cfp_forbidden', __('Invalid token', 'classflow-pro'), ['status' => 403]);
        }
        $bk = $wpdb->prefix . 'cfp_bookings';
        $sc = $wpdb->prefix . 'cfp_schedules';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT b.*, s.class_id, s.location_id, s.start_time, s.end_time FROM $bk b JOIN $sc s ON s.id=b.schedule_id WHERE b.user_id = %d AND b.status IN ('pending','confirmed') ORDER BY s.start_time ASC LIMIT 1000", $user_id), ARRAY_A);
        $events = [];
        foreach ($rows as $r) {
            $class_title = \ClassFlowPro\Utils\Entities::class_name((int)$r['class_id']);
            $loc = !empty($r['location_id']) ? get_the_title((int)$r['location_id']) : '';
            $events[] = [
                'uid' => 'cfp-booking-' . $r['id'] . '@' . parse_url(home_url(), PHP_URL_HOST),
                'start' => $r['start_time'],
                'end' => $r['end_time'],
                'summary' => $class_title,
                'description' => '',
                'location' => $loc,
                'url' => home_url('/'),
            ];
        }
        $ics = \ClassFlowPro\Calendar\Ical::build($events);
        header('Content-Type: text/calendar; charset=utf-8');
        echo $ics;
        exit;
    }
}
