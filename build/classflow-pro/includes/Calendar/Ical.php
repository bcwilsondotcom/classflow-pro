<?php
namespace ClassFlowPro\Calendar;

class Ical
{
    private static function header(): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ClassFlow Pro//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n";
    }

    private static function footer(): string
    {
        return "END:VCALENDAR\r\n";
    }

    private static function esc(string $s): string
    {
        return addcslashes($s, ",;\\");
    }

    private static function dt($ts): string
    {
        if (!is_int($ts)) $ts = strtotime($ts . ' UTC');
        return gmdate('Ymd\THis\Z', $ts);
    }

    public static function event(array $e): string
    {
        $lines = [];
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . self::esc($e['uid']);
        $lines[] = 'DTSTAMP:' . self::dt(time());
        $lines[] = 'DTSTART:' . self::dt($e['start']);
        $lines[] = 'DTEND:' . self::dt($e['end']);
        if (!empty($e['summary'])) $lines[] = 'SUMMARY:' . self::esc($e['summary']);
        if (!empty($e['description'])) $lines[] = 'DESCRIPTION:' . self::esc($e['description']);
        if (!empty($e['location'])) $lines[] = 'LOCATION:' . self::esc($e['location']);
        if (!empty($e['url'])) $lines[] = 'URL:' . self::esc($e['url']);
        $lines[] = 'END:VEVENT';
        return implode("\r\n", $lines) . "\r\n";
    }

    public static function build(array $events): string
    {
        $out = self::header();
        foreach ($events as $ev) {
            $out .= self::event($ev);
        }
        $out .= self::footer();
        return $out;
    }
}

