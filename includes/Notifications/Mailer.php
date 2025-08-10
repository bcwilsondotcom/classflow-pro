<?php
namespace ClassFlowPro\Notifications;

use ClassFlowPro\Admin\Settings;

class Mailer
{
    private static function get_template(string $subject_key, string $body_key, array $vars): array
    {
        $settings = get_option('cfp_settings', []);
        $subjects = [
            'confirmed' => $settings['template_confirmed_subject'] ?? '[{site}] Booking Confirmed: {class_title}',
            'canceled' => $settings['template_canceled_subject'] ?? '[{site}] Booking {status}: {class_title}',
            'rescheduled' => $settings['template_rescheduled_subject'] ?? '[{site}] Booking Rescheduled: {class_title}',
        ];
        $bodies = [
            'confirmed' => $settings['template_confirmed_body'] ?? '<p>Your class is confirmed.</p><p><strong>{class_title}</strong><br>{start_time}<br>{amount}</p>',
            'canceled' => $settings['template_canceled_body'] ?? '<p>Your booking has been canceled. ({status})</p><p><strong>{class_title}</strong><br>{start_time}</p>',
            'rescheduled' => $settings['template_rescheduled_body'] ?? '<p>Your booking has been rescheduled.</p><p><strong>{class_title}</strong><br>From: {old_start_time}<br>To: {start_time}</p>',
        ];
        $subject_tpl = $subjects[$subject_key] ?? '';
        $body_tpl = $bodies[$body_key] ?? '';
        $repl = static function (string $tpl) use ($vars): string {
            return strtr($tpl, [
                '{site}' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
                '{class_title}' => $vars['class_title'] ?? '',
                '{start_time}' => $vars['start_time'] ?? '',
                '{old_start_time}' => $vars['old_start_time'] ?? '',
                '{amount}' => $vars['amount'] ?? '',
                '{status}' => $vars['status'] ?? '',
            ]);
        };
        return [$repl($subject_tpl), $repl($body_tpl)];
    }
    private static function send($to, string $subject, string $body): void
    {
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        if (is_array($to)) {
            foreach ($to as $addr) { wp_mail($addr, $subject, $body, $headers); }
        } else {
            wp_mail($to, $subject, $body, $headers);
        }
    }

    private static function recipients(?int $user_id, ?string $customer_email): array
    {
        $to = [];
        if ($customer_email) $to[] = $customer_email;
        if ($user_id) {
            $u = get_userdata($user_id);
            if ($u && $u->user_email) $to[] = $u->user_email;
        }
        $to = array_values(array_unique(array_filter($to)));
        return $to;
    }

    public static function booking_confirmed(int $booking_id): void
    {
        if (!Settings::get('notify_customer', 1) && !Settings::get('notify_admin', 1)) return;
        global $wpdb;
        $btable = $wpdb->prefix . 'cfp_bookings';
        $stable = $wpdb->prefix . 'cfp_schedules';
        $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM $btable WHERE id = %d", $booking_id), ARRAY_A);
        if (!$b) return;
        $s = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stable WHERE id = %d", $b['schedule_id']), ARRAY_A);
        if (!$s) return;
        $title = get_the_title((int)$s['class_id']);
        $tz = \ClassFlowPro\Utils\Timezone::for_location(!empty($s['location_id']) ? (int)$s['location_id'] : null);
        $start = \ClassFlowPro\Utils\Timezone::format_local($s['start_time'], $tz);
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $price = ((int)$b['amount_cents'] > 0) ? number_format_i18n($b['amount_cents']/100, 2) . ' ' . strtoupper($b['currency']) : __('Credit', 'classflow-pro');
        [$subject, $body] = self::get_template('confirmed', 'confirmed', [
            'class_title' => $title,
            'start_time' => $start,
            'amount' => $price,
        ]);

        if (Settings::get('notify_customer', 1)) {
            $to = self::recipients($b['user_id'] ? (int)$b['user_id'] : null, $b['customer_email']);
            if ($to) self::send($to, $subject, $body);
        }
        if (Settings::get('notify_admin', 1)) self::send(get_option('admin_email'), $subject, $body);

        // Instructor notification
        if (Settings::get('notify_instructor', 1)) {
            $instructor_id = (int)$s['instructor_id'];
            if ($instructor_id) {
                $email = get_post_meta($instructor_id, '_cfp_email', true);
                if ($email) self::send($email, $subject, $body);
            }
        }
    }

    public static function booking_canceled(int $booking_id, string $status): void
    {
        if (!Settings::get('notify_customer', 1) && !Settings::get('notify_admin', 1)) return;
        global $wpdb;
        $btable = $wpdb->prefix . 'cfp_bookings';
        $stable = $wpdb->prefix . 'cfp_schedules';
        $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM $btable WHERE id = %d", $booking_id), ARRAY_A);
        if (!$b) return;
        $s = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stable WHERE id = %d", $b['schedule_id']), ARRAY_A);
        if (!$s) return;
        $title = get_the_title((int)$s['class_id']);
        $tz = \ClassFlowPro\Utils\Timezone::for_location(!empty($s['location_id']) ? (int)$s['location_id'] : null);
        $start = \ClassFlowPro\Utils\Timezone::format_local($s['start_time'], $tz);
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        [$subject, $body] = self::get_template('canceled', 'canceled', [
            'class_title' => $title,
            'start_time' => $start,
            'status' => $status,
        ]);
        if (Settings::get('notify_customer', 1)) {
            $to = self::recipients($b['user_id'] ? (int)$b['user_id'] : null, $b['customer_email']);
            if ($to) self::send($to, $subject, $body);
        }
        if (Settings::get('notify_admin', 1)) self::send(get_option('admin_email'), $subject, $body);
        if (Settings::get('notify_instructor', 1)) {
            $instructor_id = (int)$s['instructor_id'];
            if ($instructor_id) {
                $email = get_post_meta($instructor_id, '_cfp_email', true);
                if ($email) self::send($email, $subject, $body);
            }
        }
    }

    public static function booking_rescheduled(int $booking_id, int $old_schedule_id): void
    {
        if (!Settings::get('notify_customer', 1) && !Settings::get('notify_admin', 1)) return;
        global $wpdb;
        $btable = $wpdb->prefix . 'cfp_bookings';
        $stable = $wpdb->prefix . 'cfp_schedules';
        $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM $btable WHERE id = %d", $booking_id), ARRAY_A);
        if (!$b) return;
        $new = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stable WHERE id = %d", $b['schedule_id']), ARRAY_A);
        $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stable WHERE id = %d", $old_schedule_id), ARRAY_A);
        if (!$new) return;
        $title = get_the_title((int)$new['class_id']);
        $tz_new = \ClassFlowPro\Utils\Timezone::for_location(!empty($new['location_id']) ? (int)$new['location_id'] : null);
        $start_old = $old ? (\ClassFlowPro\Utils\Timezone::format_local($old['start_time'], \ClassFlowPro\Utils\Timezone::for_location(!empty($old['location_id']) ? (int)$old['location_id'] : null))) : '';
        $start_new = \ClassFlowPro\Utils\Timezone::format_local($new['start_time'], $tz_new);
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        [$subject, $body] = self::get_template('rescheduled', 'rescheduled', [
            'class_title' => $title,
            'old_start_time' => $start_old,
            'start_time' => $start_new,
        ]);
        if (Settings::get('notify_customer', 1)) {
            $to = self::recipients($b['user_id'] ? (int)$b['user_id'] : null, $b['customer_email']);
            if ($to) self::send($to, $subject, $body);
        }
        if (Settings::get('notify_admin', 1)) self::send(get_option('admin_email'), $subject, $body);
        if (Settings::get('notify_instructor', 1)) {
            $instructor_id = (int)$new['instructor_id'];
            if ($instructor_id) {
                $email = get_post_meta($instructor_id, '_cfp_email', true);
                if ($email) self::send($email, $subject, $body);
            }
        }
    }

    public static function waitlist_open(int $schedule_id, string $email): void
    {
        $s = get_post((int)$schedule_id);
        global $wpdb;
        $stable = $wpdb->prefix . 'cfp_schedules';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stable WHERE id = %d", $schedule_id), ARRAY_A);
        if (!$row) return;
        $class_title = get_the_title((int)$row['class_id']);
        $start = gmdate('Y-m-d H:i', strtotime($row['start_time'])) . ' UTC';
        [$subject, $body] = self::get_template('confirmed', 'confirmed', [
            'class_title' => $class_title,
            'start_time' => $start,
            'amount' => __('Open Seat', 'classflow-pro'),
        ]);
        self::send($email, $subject, '<p>' . esc_html__('A spot just opened in your waitlisted class. Please book now to secure it:', 'classflow-pro') . '</p>' . $body);
    }
}
