<?php
namespace ClassFlowPro\Notifications;

use ClassFlowPro\Admin\Settings;

class Reminders
{
    public static function register(): void
    {
        add_action('init', [self::class, 'schedule_event']);
        add_action('cfp_send_reminders_hourly', [self::class, 'run']);
    }

    public static function schedule_event(): void
    {
        if (!wp_next_scheduled('cfp_send_reminders_hourly')) {
            wp_schedule_event(time() + 300, 'hourly', 'cfp_send_reminders_hourly');
        }
    }

    public static function run(): void
    {
        $hours_cfg = (string) Settings::get('reminder_hours_before', '24,2');
        $hours = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $hours_cfg)))));
        if (!$hours) return;
        global $wpdb; $s=$wpdb->prefix.'cfp_schedules'; $b=$wpdb->prefix.'cfp_bookings'; $logs=$wpdb->prefix.'cfp_logs';
        $now = time();
        foreach ($hours as $h) {
            $from = gmdate('Y-m-d H:i:s', $now + ($h*3600));
            $to = gmdate('Y-m-d H:i:s', $now + ($h*3600) + 3600); // 1-hour window
            $sql = $wpdb->prepare("SELECT b.id as booking_id FROM $b b JOIN $s s ON s.id=b.schedule_id WHERE s.start_time >= %s AND s.start_time < %s AND COALESCE(s.status,'active') <> 'cancelled' AND b.status IN ('pending','confirmed') LIMIT 500", $from, $to);
            $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $key = 'booking_reminder:' . (int)$row['booking_id'] . ':' . $h;
                $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $logs WHERE source='reminder' AND message = %s", $key));
                if ($exists > 0) continue;
                try { \ClassFlowPro\Notifications\Mailer::booking_reminder((int)$row['booking_id']); } catch (\Throwable $e) {}
                $wpdb->insert($logs, [ 'level' => 'info', 'source' => 'reminder', 'message' => $key, 'context' => null ], ['%s','%s','%s','%s']);
            }
        }
    }
}

