<?php
namespace ClassFlowPro\Utils;

use ClassFlowPro\Admin\Settings;

class Timezone
{
    public static function business(): string
    {
        $tz = Settings::get('business_timezone', '') ?: (function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC');
        return $tz ?: 'UTC';
    }

    public static function for_location(?int $location_id): string
    {
        if ($location_id) {
            $tz = get_post_meta($location_id, '_cfp_timezone', true);
            if ($tz) return $tz;
        }
        return self::business();
    }

    public static function for_schedule_row(array $row): string
    {
        $loc = !empty($row['location_id']) ? (int)$row['location_id'] : null;
        return self::for_location($loc);
    }

    public static function format_local(string $utc_datetime, string $tz, string $format = 'Y-m-d H:i'): string
    {
        try {
            $dt = new \DateTimeImmutable($utc_datetime, new \DateTimeZone('UTC'));
            $local = $dt->setTimezone(new \DateTimeZone($tz));
            return $local->format($format) . ' ' . $tz;
        } catch (\Throwable $e) {
            return $utc_datetime . ' UTC';
        }
    }
}

