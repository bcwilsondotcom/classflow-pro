<?php
namespace ClassFlowPro\Utils;

class Time
{
    private static $days = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];

    public static function parseWeeklyAvailability(string $input): array
    {
        $avail = [];
        $parts = preg_split('/[;\n]+/', strtolower($input));
        foreach ($parts as $p) {
            $p = trim($p);
            if (!$p) continue;
            if (!preg_match('/^(sun|mon|tue|wed|thu|fri|sat)\s+([0-2]\d:[0-5]\d)\s*-\s*([0-2]\d:[0-5]\d)$/', $p, $m)) continue;
            $day = self::$days[$m[1]];
            $start = $m[2];
            $end = $m[3];
            $avail[$day][] = [$start, $end];
        }
        return $avail; // map of weekday -> [ [HH:MM, HH:MM], ... ]
    }

    public static function withinAvailability(array $avail, \DateTimeImmutable $dt): bool
    {
        $w = (int)$dt->format('w'); // 0=Sun
        if (empty($avail[$w])) return false;
        $hm = $dt->format('H:i');
        foreach ($avail[$w] as [$s,$e]) {
            if ($hm >= $s && $hm <= $e) return true;
        }
        return false;
    }

    public static function isBlackout(string $blackouts, \DateTimeImmutable $dt): bool
    {
        if (!$blackouts) return false;
        $list = array_filter(array_map('trim', explode(',', strtolower($blackouts))));
        $d = $dt->format('Y-m-d');
        return in_array($d, $list, true);
    }

    public static function overlaps(string $startA, string $endA, string $startB, string $endB): bool
    {
        return ($startA < $endB) && ($endA > $startB);
    }
}

