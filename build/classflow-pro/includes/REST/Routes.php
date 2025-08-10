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

        register_rest_route('classflow/v1', '/book', [
            'methods' => 'POST',
            'callback' => [self::class, 'book'],
            'permission_callback' => function () {
                return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest');
            },
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

        register_rest_route('classflow/v1', '/payment_intent', [
            'methods' => 'POST',
            'callback' => [self::class, 'create_payment_intent'],
            'permission_callback' => function () {
                return wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE'] ?? '', 'wp_rest');
            },
        ]);

        register_rest_route('classflow/v1', '/stripe/webhook', [
            'methods' => 'POST',
            'callback' => [StripeWebhooks::class, 'handle'],
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
        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT * FROM $table $where_sql ORDER BY start_time ASC LIMIT 500";
        $prepared = $params ? $wpdb->prepare($sql, $params) : $sql;
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        foreach ($rows as &$row) {
            $row['location_name'] = $row['location_id'] ? get_the_title((int)$row['location_id']) : '';
            $row['tz'] = \ClassFlowPro\Utils\Timezone::for_schedule_row($row);
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
        $b = $wpdb->prefix . 'cfp_bookings';
        $where = $wpdb->prepare("WHERE s.class_id = %d AND s.start_time >= %s", $class_id, $date_from);
        if ($date_to) {
            $where .= $wpdb->prepare(" AND s.start_time <= %s", $date_to);
        }
        $sql = "SELECT s.*, (
                    s.capacity - (SELECT COUNT(*) FROM $b bb WHERE bb.schedule_id = s.id AND bb.status IN ('pending','confirmed'))
                ) AS seats_left
                FROM $s s $where HAVING seats_left > 0 ORDER BY s.start_time ASC LIMIT 200";
        $rows = $wpdb->get_results($sql, ARRAY_A);
        // Add titles
        foreach ($rows as &$row) {
            $row['class_title'] = get_the_title((int)$row['class_id']);
            $row['instructor_name'] = $row['instructor_id'] ? get_the_title((int)$row['instructor_id']) : '';
            $row['location_name'] = $row['location_id'] ? get_the_title((int)$row['location_id']) : '';
            $row['tz'] = \ClassFlowPro\Utils\Timezone::for_schedule_row($row);
        }
        return rest_ensure_response($rows);
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
        // Intake requirement
        if ($customer['user_id'] && \ClassFlowPro\Admin\Settings::get('require_intake', 0)) {
            global $wpdb;
            $intake = $wpdb->prefix . 'cfp_intake_forms';
            $has = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $intake WHERE user_id = %d", (int)$customer['user_id']));
            if ($has <= 0) {
                return new WP_Error('cfp_intake_required', __('Intake form required before booking', 'classflow-pro'), ['status' => 403]);
            }
        }
        if (!$schedule_id || (!$customer['user_id'] && empty($customer['email']))) {
            return new WP_Error('cfp_invalid_request', __('Missing schedule or customer info', 'classflow-pro'), ['status' => 400]);
        }
        $result = BookingManager::book($schedule_id, $customer, $use_credits);
        if (is_wp_error($result)) {
            return $result;
        }
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
        return rest_ensure_response(['status' => 'joined']);
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

    public static function purchase_package(WP_REST_Request $req)
    {
        $data = $req->get_json_params();
        $name = sanitize_text_field($data['name'] ?? '');
        $credits = max(1, intval($data['credits'] ?? 0));
        $price_cents = max(50, intval($data['price_cents'] ?? 0));
        $buyer_name = sanitize_text_field($data['buyer_name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $user_id = get_current_user_id();
        $intent = \ClassFlowPro\Packages\Manager::create_purchase_intent($user_id ?: null, $name, $credits, $price_cents, $email, $buyer_name);
        if (is_wp_error($intent)) return $intent;
        return rest_ensure_response($intent);
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
            'class_title' => get_the_title((int)$b['class_id']),
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
            $class_title = get_the_title((int)$r['class_id']);
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
            $class_title = get_the_title((int)$r['class_id']);
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
