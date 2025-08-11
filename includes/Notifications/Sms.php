<?php
namespace ClassFlowPro\Notifications;

use ClassFlowPro\Admin\Settings;
use WP_Error;

class Sms
{
    private static function enabled_for_customers(): bool
    {
        return (bool) Settings::get('notify_sms_customer', 0);
    }

    private static function enabled_for_instructors(): bool
    {
        return (bool) Settings::get('notify_sms_instructor', 0);
    }

    private static function from_number(): string
    {
        return (string) Settings::get('twilio_from_number', '');
    }

    private static function phone_for_user(?int $user_id): ?string
    {
        if (!$user_id) return null;
        $opt_in = (int) get_user_meta($user_id, 'cfp_sms_opt_in', true) === 1;
        if (!$opt_in) return null;
        $phone = get_user_meta($user_id, 'cfp_phone', true) ?: '';
        if (!$phone) return null;
        return $phone; // assume already E.164 or valid enough for provider
    }

    private static function send_to_number(string $to, string $message)
    {
        $from = self::from_number();
        if (!$from) return new WP_Error('cfp_sms_not_configured', 'SMS not configured');
        $provider = new \ClassFlowPro\Notifications\SmsProviders\Twilio();
        return $provider::send($from, $to, $message);
    }

    private static function footer(): string
    {
        return ' Reply STOP to opt out';
    }

    private static function render_sms(string $key, array $vars): string
    {
        $defaults = [
            'sms_confirmed_body' => '[{site}] Confirmed: {class_title} @ {start_time}.',
            'sms_canceled_body' => '[{site}] {status}: {class_title} @ {start_time}.',
            'sms_rescheduled_body' => '[{site}] Rescheduled: {class_title} now @ {start_time}.',
            'sms_waitlist_body' => '[{site}] Waitlist open: {class_title} @ {start_time}.',
            'sms_reminder_body' => '[{site}] Reminder: {class_title} @ {start_time}.',
        ];
        $settings = get_option('cfp_settings', []);
        $tpl = $settings[$key] ?? $defaults[$key] ?? '';
        $repl = static function (string $tpl) use ($vars): string {
            return strtr($tpl, [
                '{site}' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
                '{class_title}' => $vars['class_title'] ?? '',
                '{start_time}' => $vars['start_time'] ?? '',
                '{old_start_time}' => $vars['old_start_time'] ?? '',
                '{status}' => $vars['status'] ?? '',
            ]);
        };
        $msg = $repl($tpl);
        return trim($msg . self::footer());
    }

    public static function booking_confirmed(int $booking_id): void
    {
        if (!self::enabled_for_customers()) return;
        global $wpdb; $b=$wpdb->prefix.'cfp_bookings'; $s=$wpdb->prefix.'cfp_schedules';
        $bk=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d", $booking_id), ARRAY_A); if(!$bk) return;
        $sc=$wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d", (int)$bk['schedule_id']), ARRAY_A); if(!$sc) return;
        $user_id = $bk['user_id'] ? (int)$bk['user_id'] : null; $to = self::phone_for_user($user_id); if(!$to) return;
        $title = \ClassFlowPro\Utils\Entities::class_name((int)$sc['class_id']);
        $start = \ClassFlowPro\Utils\Timezone::format_local($sc['start_time'], \ClassFlowPro\Utils\Timezone::for_location(!empty($sc['location_id'])?(int)$sc['location_id']:null));
        $msg = self::render_sms('sms_confirmed_body', [ 'class_title' => $title, 'start_time' => $start ]);
        self::send_to_number($to, $msg);
    }

    public static function booking_canceled(int $booking_id, string $status): void
    {
        if (!self::enabled_for_customers()) return;
        global $wpdb; $b=$wpdb->prefix.'cfp_bookings'; $s=$wpdb->prefix.'cfp_schedules';
        $bk=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d", $booking_id), ARRAY_A); if(!$bk) return;
        $sc=$wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d", (int)$bk['schedule_id']), ARRAY_A); if(!$sc) return;
        $to = self::phone_for_user($bk['user_id'] ? (int)$bk['user_id'] : null); if(!$to) return;
        $title = \ClassFlowPro\Utils\Entities::class_name((int)$sc['class_id']);
        $start = \ClassFlowPro\Utils\Timezone::format_local($sc['start_time'], \ClassFlowPro\Utils\Timezone::for_location(!empty($sc['location_id'])?(int)$sc['location_id']:null));
        $msg = self::render_sms('sms_canceled_body', [ 'class_title' => $title, 'start_time' => $start, 'status' => $status ]);
        self::send_to_number($to, $msg);
    }

    public static function booking_rescheduled(int $booking_id, int $old_schedule_id): void
    {
        if (!self::enabled_for_customers()) return;
        global $wpdb; $b=$wpdb->prefix.'cfp_bookings'; $s=$wpdb->prefix.'cfp_schedules';
        $bk=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d", $booking_id), ARRAY_A); if(!$bk) return;
        $new=$wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d", (int)$bk['schedule_id']), ARRAY_A); if(!$new) return;
        $to = self::phone_for_user($bk['user_id'] ? (int)$bk['user_id'] : null); if(!$to) return;
        $title = \ClassFlowPro\Utils\Entities::class_name((int)$new['class_id']);
        $start = \ClassFlowPro\Utils\Timezone::format_local($new['start_time'], \ClassFlowPro\Utils\Timezone::for_location(!empty($new['location_id'])?(int)$new['location_id']:null));
        $msg = self::render_sms('sms_rescheduled_body', [ 'class_title' => $title, 'start_time' => $start ]);
        self::send_to_number($to, $msg);
    }

    public static function waitlist_open(int $schedule_id, ?int $user_id = null): void
    {
        if (!self::enabled_for_customers()) return;
        if (!$user_id) return; // only if we know the user
        global $wpdb; $s=$wpdb->prefix.'cfp_schedules';
        $sc=$wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d", $schedule_id), ARRAY_A); if(!$sc) return;
        $to = self::phone_for_user($user_id); if(!$to) return;
        $title = \ClassFlowPro\Utils\Entities::class_name((int)$sc['class_id']);
        $start = \ClassFlowPro\Utils\Timezone::format_local($sc['start_time'], \ClassFlowPro\Utils\Timezone::for_location(!empty($sc['location_id'])?(int)$sc['location_id']:null));
        $msg = self::render_sms('sms_waitlist_body', [ 'class_title' => $title, 'start_time' => $start ]);
        self::send_to_number($to, $msg);
    }

    public static function waitlist_offer(int $schedule_id, int $user_id, string $token): void
    {
        if (!self::enabled_for_customers()) return;
        $to = self::phone_for_user($user_id); if(!$to) return;
        $url = \ClassFlowPro\Admin\Settings::get('waitlist_response_page_url', '');
        if (!$url) return;
        $accept = add_query_arg([ 'action' => 'accept', 'token' => $token ], $url);
        $deny = add_query_arg([ 'action' => 'deny', 'token' => $token ], $url);
        $msg = '[' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES) . "] Spot Available: Accept " . $accept . " or Decline " . $deny;
        self::send_to_number($to, $msg);
    }

    public static function booking_reminder(int $booking_id): void
    {
        if (!self::enabled_for_customers()) return;
        global $wpdb; $b=$wpdb->prefix.'cfp_bookings'; $s=$wpdb->prefix.'cfp_schedules';
        $bk=$wpdb->get_row($wpdb->prepare("SELECT * FROM $b WHERE id=%d", $booking_id), ARRAY_A); if(!$bk) return;
        $sc=$wpdb->get_row($wpdb->prepare("SELECT * FROM $s WHERE id=%d", (int)$bk['schedule_id']), ARRAY_A); if(!$sc) return;
        $to = self::phone_for_user($bk['user_id'] ? (int)$bk['user_id'] : null); if(!$to) return;
        $title = \ClassFlowPro\Utils\Entities::class_name((int)$sc['class_id']);
        $start = \ClassFlowPro\Utils\Timezone::format_local($sc['start_time'], \ClassFlowPro\Utils\Timezone::for_location(!empty($sc['location_id'])?(int)$sc['location_id']:null));
        $msg = self::render_sms('sms_reminder_body', [ 'class_title' => $title, 'start_time' => $start ]);
        self::send_to_number($to, $msg);
    }
}
