<?php
namespace ClassFlowPro\Admin;

if (!defined('ABSPATH')) { exit; }

class Rosters
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        $schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
        if ($_SERVER['REQUEST_METHOD']==='POST' && check_admin_referer('cfp_roster_action')) {
            self::handle_post();
            $schedule_id = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Rosters', 'classflow-pro') . '</h1>';
        if ($schedule_id) {
            self::render_roster($schedule_id);
        } else {
            self::render_upcoming();
        }
        echo '</div>';
    }

    private static function render_upcoming(): void
    {
        global $wpdb; $s=$wpdb->prefix.'cfp_schedules'; $c=$wpdb->prefix.'cfp_classes';
        $now = gmdate('Y-m-d H:i:s');
        $where = 'WHERE s.start_time >= %s';
        $params = [$now];
        // Per-location scoping
        $locs = \ClassFlowPro\Utils\Permissions::allowed_location_ids_for_user();
        if (!empty($locs)) {
            $in = implode(',', array_map('intval', $locs));
            $where .= " AND s.location_id IN ($in)";
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT s.*, c.name AS class_name FROM $s s LEFT JOIN $c c ON c.id=s.class_id $where ORDER BY s.start_time ASC LIMIT 100", ...$params), ARRAY_A);
        echo '<p>' . esc_html__('Select a session to manage roster and check-ins.', 'classflow-pro') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('When','classflow-pro') . '</th><th>' . esc_html__('Class','classflow-pro') . '</th><th>' . esc_html__('Capacity','classflow-pro') . '</th><th>' . esc_html__('Actions','classflow-pro') . '</th></tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="4">' . esc_html__('No upcoming sessions found.', 'classflow-pro') . '</td></tr>';
        foreach ($rows as $r) {
            $link = esc_url(add_query_arg(['page'=>'classflow-pro-rosters','schedule_id'=>(int)$r['id']], admin_url('admin.php')));
            echo '<tr>';
            echo '<td>' . esc_html(gmdate('Y-m-d H:i', strtotime($r['start_time']))) . ' UTC</td>';
            echo '<td>' . esc_html($r['class_name'] ?: ('#'.$r['class_id'])) . '</td>';
            echo '<td>' . esc_html((string)$r['capacity']) . '</td>';
            echo '<td><a class="button" href="' . $link . '">' . esc_html__('View Roster','classflow-pro') . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_roster(int $schedule_id): void
    {
        global $wpdb; $s=$wpdb->prefix.'cfp_schedules'; $b=$wpdb->prefix.'cfp_bookings';
        $schedule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d", $schedule_id), ARRAY_A);
        if (!$schedule) { echo '<p>' . esc_html__('Schedule not found.', 'classflow-pro') . '</p>'; return; }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $b WHERE schedule_id=%d AND status IN ('pending','confirmed') ORDER BY created_at ASC", $schedule_id), ARRAY_A);
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=classflow-pro-rosters')) . '">&larr; ' . esc_html__('Back','classflow-pro') . '</a></p>';
        echo '<h2>' . esc_html__('Session', 'classflow-pro') . ' #' . (int)$schedule_id . ' — ' . esc_html(gmdate('Y-m-d H:i', strtotime($schedule['start_time']))) . ' UTC</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Client','classflow-pro') . '</th><th>' . esc_html__('Status','classflow-pro') . '</th><th>' . esc_html__('Attendance','classflow-pro') . '</th><th>' . esc_html__('Actions','classflow-pro') . '</th></tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="4">' . esc_html__('No attendees yet.', 'classflow-pro') . '</td></tr>';
        foreach ($rows as $r) {
            $u = $r['user_id'] ? get_user_by('id', (int)$r['user_id']) : null;
            $name = $u ? ($u->display_name ?: $u->user_email) : ($r['customer_email'] ?: ('#'.$r['id']));
            $att = $r['attendance_status'] ?: '-';
            echo '<tr><td>' . esc_html($name) . '</td>';
            echo '<td>' . esc_html($r['status']) . '</td>';
            echo '<td>' . esc_html($att) . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:inline">'; wp_nonce_field('cfp_roster_action');
            echo '<input type="hidden" name="schedule_id" value="' . esc_attr((string)$schedule_id) . '" />';
            echo '<input type="hidden" name="booking_id" value="' . esc_attr((string)$r['id']) . '" />';
            echo '<button class="button" name="cfp_do" value="check_in">' . esc_html__('Check In','classflow-pro') . '</button> ';
            echo '<button class="button" name="cfp_do" value="mark_no_show">' . esc_html__('No-Show','classflow-pro') . '</button> ';
            echo '<button class="button" name="cfp_do" value="late_cancel">' . esc_html__('Late Cancel','classflow-pro') . '</button> ';
            echo '<button class="button" name="cfp_do" value="charge_fee">' . esc_html__('Charge Fee','classflow-pro') . '</button>';
            echo '</form>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        // Add Client form
        echo '<h3 style="margin-top:18px;">' . esc_html__('Add Client', 'classflow-pro') . '</h3>';
        echo '<form method="post" style="display:flex; gap:8px; align-items:end;">'; wp_nonce_field('cfp_roster_action');
        echo '<input type="hidden" name="schedule_id" value="' . esc_attr((string)$schedule_id) . '" />';
        echo '<input type="email" name="client_email" placeholder="' . esc_attr__('Customer email', 'classflow-pro') . '" required />';
        echo '<label><input type="checkbox" name="comp" value="1" /> ' . esc_html__('Comp (no charge)', 'classflow-pro') . '</label>';
        echo '<button class="button button-primary" name="cfp_do" value="add_client">' . esc_html__('Add', 'classflow-pro') . '</button>';
        echo '</form>';

        // Transfer Booking form
        echo '<h3 style="margin-top:18px;">' . esc_html__('Transfer Booking', 'classflow-pro') . '</h3>';
        echo '<form method="post" style="display:flex; gap:8px; align-items:end;">'; wp_nonce_field('cfp_roster_action');
        echo '<input type="hidden" name="schedule_id" value="' . esc_attr((string)$schedule_id) . '" />';
        echo '<input type="number" min="1" name="booking_id" placeholder="' . esc_attr__('Booking ID', 'classflow-pro') . '" required />';
        // Target schedules (same class upcoming)
        $other = $wpdb->get_results($wpdb->prepare("SELECT id, start_time FROM $s WHERE class_id=%d AND id<>%d AND start_time >= UTC_TIMESTAMP() ORDER BY start_time ASC LIMIT 50", (int)$schedule['class_id'], $schedule_id), ARRAY_A);
        echo '<select name="target_schedule_id" required><option value="">' . esc_html__('Select target time', 'classflow-pro') . '</option>';
        foreach ($other as $o) { echo '<option value="' . (int)$o['id'] . '">' . esc_html(gmdate('Y-m-d H:i', strtotime($o['start_time']))) . ' UTC</option>'; }
        echo '</select>';
        echo '<button class="button" name="cfp_do" value="transfer">' . esc_html__('Transfer', 'classflow-pro') . '</button>';
        echo '</form>';
    }

    private static function handle_post(): void
    {
        $do = sanitize_text_field($_POST['cfp_do'] ?? '');
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        if (!$booking_id) return;
        global $wpdb; $b=$wpdb->prefix.'cfp_bookings';
        if ($do === 'check_in') {
            $wpdb->update($b, [ 'attendance_status' => 'checked_in', 'check_in_at' => gmdate('Y-m-d H:i:s') ], [ 'id' => $booking_id ], ['%s','%s'], ['%d']);
            self::notice('success', __('Checked in.', 'classflow-pro'));
        } elseif ($do === 'mark_no_show') {
            $wpdb->update($b, [ 'attendance_status' => 'no_show' ], [ 'id' => $booking_id ], ['%s'], ['%d']);
            self::apply_policy($booking_id, 'no_show');
        } elseif ($do === 'late_cancel') {
            $wpdb->update($b, [ 'status' => 'canceled' ], [ 'id' => $booking_id ], ['%s'], ['%d']);
            self::apply_policy($booking_id, 'late_cancel');
        } elseif ($do === 'charge_fee') {
            self::apply_policy($booking_id, 'manual');
        } elseif ($do === 'add_client') {
            $schedule_id = (int)($_POST['schedule_id'] ?? 0);
            $email = sanitize_email($_POST['client_email'] ?? '');
            $comp = !empty($_POST['comp']);
            if ($schedule_id && $email) {
                // Find or create user
                $user = get_user_by('email', $email);
                if (!$user) {
                    $username = sanitize_user(current(explode('@', $email))); if (username_exists($username)) { $username .= '_' . wp_generate_password(4, false, false); }
                    $uid = wp_create_user($username, wp_generate_password(12, true), $email);
                    if (!is_wp_error($uid)) { $user = get_user_by('id', $uid); (new \WP_User($uid))->set_role('customer'); }
                }
                $uid = $user ? (int)$user->ID : 0;
                if ($comp) {
                    // Direct comp booking
                    global $wpdb; $s=$wpdb->prefix.'cfp_schedules'; $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d", $schedule_id), ARRAY_A);
                    if ($row) {
                        $b=$wpdb->prefix.'cfp_bookings';
                        $booked = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $b WHERE schedule_id=%d AND status IN ('pending','confirmed')", $schedule_id));
                        if ($booked < (int)$row['capacity']) {
                            $wpdb->insert($b, [
                                'schedule_id'=>$schedule_id,
                                'user_id'=>$uid?:null,
                                'customer_email'=>$email,
                                'status'=>'confirmed',
                                'payment_status'=>'comp',
                                'credits_used'=>0,
                                'amount_cents'=>0,
                                'discount_cents'=>0,
                                'currency'=>$row['currency'] ?: 'usd',
                                'coupon_id'=>null,
                                'coupon_code'=>null,
                                'metadata'=>wp_json_encode(['admin_comp'=>1]),
                            ], ['%d','%d','%s','%s','%s','%d','%d','%d','%s','%d','%s','%s']);
                            try { \ClassFlowPro\Notifications\Mailer::booking_confirmed((int)$wpdb->insert_id); } catch (\Throwable $e) {}
                            self::notice('success', __('Client added (comp).', 'classflow-pro'));
                        } else { self::notice('error', __('Class is full.', 'classflow-pro')); }
                    }
                } else {
                    // Standard path: create pending booking (client can pay)
                    $res = \ClassFlowPro\Booking\Manager::book($schedule_id, ['user_id'=>$uid?:null,'email'=>$email], false);
                    if (is_wp_error($res)) { self::notice('error', esc_html($res->get_error_message())); }
                    else { self::notice('success', __('Client added; pending payment if required.', 'classflow-pro')); }
                }
            }
        } elseif ($do === 'transfer') {
            $booking_id = (int)($_POST['booking_id'] ?? 0);
            $target_schedule_id = (int)($_POST['target_schedule_id'] ?? 0);
            if ($booking_id && $target_schedule_id) {
                global $wpdb; $b=$wpdb->prefix.'cfp_bookings'; $s=$wpdb->prefix.'cfp_schedules';
                $bk = $wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d", $booking_id), ARRAY_A);
                $to = $wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d", $target_schedule_id), ARRAY_A);
                if (!$bk || !$to) { self::notice('error', __('Invalid booking or target', 'classflow-pro')); return; }
                // Require same class
                $from = $wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d", (int)$bk['schedule_id']), ARRAY_A);
                if (!$from || (int)$from['class_id'] !== (int)$to['class_id']) { self::notice('error', __('Target must be the same class', 'classflow-pro')); return; }
                // Capacity check
                $booked = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $b WHERE schedule_id=%d AND status IN ('pending','confirmed')", (int)$to['id']));
                if ($booked >= (int)$to['capacity']) { self::notice('error', __('Target time is full', 'classflow-pro')); return; }
                $wpdb->update($b, ['schedule_id'=>(int)$to['id']], ['id'=>$booking_id], ['%d'], ['%d']);
                self::notice('success', __('Booking transferred.', 'classflow-pro'));
            }
        }
    }

    private static function apply_policy(int $booking_id, string $type): void
    {
        $late_fee = (int)\ClassFlowPro\Admin\Settings::get('late_cancel_fee_cents', 0);
        $no_show_fee = (int)\ClassFlowPro\Admin\Settings::get('no_show_fee_cents', 0);
        $deduct_late = (bool)\ClassFlowPro\Admin\Settings::get('late_cancel_deduct_credit', 0);
        $deduct_no_show = (bool)\ClassFlowPro\Admin\Settings::get('no_show_deduct_credit', 0);
        global $wpdb; $b=$wpdb->prefix.'cfp_bookings'; $s=$wpdb->prefix.'cfp_schedules';
        $row = $wpdb->get_row($wpdb->prepare("SELECT b.*, s.class_id, s.start_time FROM $b b JOIN $s s ON s.id=b.schedule_id WHERE b.id=%d", $booking_id), ARRAY_A);
        if (!$row) return;
        $user_id = (int)$row['user_id']; $email = (string)$row['customer_email'];
        if ($type === 'late_cancel') {
            if ($deduct_late && $user_id) { \ClassFlowPro\Packages\Manager::consume_one_credit($user_id); }
            if ($late_fee > 0) { self::fee_checkout($email, $late_fee, 'Late Cancel Fee', $row); }
            self::notice('info', __('Late cancel processed.', 'classflow-pro'));
        } elseif ($type === 'no_show') {
            if ($deduct_no_show && $user_id) { \ClassFlowPro\Packages\Manager::consume_one_credit($user_id); }
            if ($no_show_fee > 0) { self::fee_checkout($email, $no_show_fee, 'No-Show Fee', $row); }
            self::notice('info', __('No-show processed.', 'classflow-pro'));
        } elseif ($type === 'manual') {
            $fee = max($late_fee, $no_show_fee);
            if ($fee > 0) { self::fee_checkout($email, $fee, 'Fee', $row); }
        }
    }

    private static function fee_checkout(string $email, int $amount_cents, string $label, array $booking): void
    {
        $desc = \ClassFlowPro\Utils\Entities::class_name((int)$booking['class_id']) . ' — ' . gmdate('Y-m-d H:i', strtotime($booking['start_time'])) . ' UTC';
        $session = \ClassFlowPro\Payments\StripeGateway::create_checkout_session_oneoff([
            'amount_cents' => $amount_cents,
            'name' => $label,
            'description' => $desc,
            'customer_email' => $email,
            'success_url' => add_query_arg(['cfp_checkout'=>'success','type'=>'fee'], home_url('/')),
            'cancel_url' => add_query_arg(['cfp_checkout'=>'cancel','type'=>'fee'], home_url('/')),
        ]);
        if (!is_wp_error($session)) {
            $url = esc_url($session['url']);
            self::notice('success', sprintf(__('Fee checkout link created: %s', 'classflow-pro'), '<a target="_blank" href="' . $url . '">' . $url . '</a>'));
        } else {
            self::notice('error', __('Failed to create fee payment link: ', 'classflow-pro') . esc_html($session->get_error_message()));
        }
    }

    private static function notice(string $type, string $msg): void
    {
        $class = $type==='error' ? 'notice-error' : ($type==='success' ? 'notice-success' : 'notice-info');
        echo '<div class="notice ' . esc_attr($class) . '"><p>' . wp_kses_post($msg) . '</p></div>';
    }
}
