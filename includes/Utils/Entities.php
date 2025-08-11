<?php
namespace ClassFlowPro\Utils;

if (!defined('ABSPATH')) { exit; }

class Entities
{
    public static function class_name(int $id): string
    {
        if ($id <= 0) return '';
        global $wpdb;
        try {
            $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}cfp_classes WHERE id = %d", $id));
            if ($name) return (string) $name;
        } catch (\Throwable $e) {}
        $title = get_the_title($id);
        return is_string($title) ? $title : '';
    }

    public static function instructor_name(?int $id): string
    {
        if (!$id) return '';
        global $wpdb;
        try {
            $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}cfp_instructors WHERE id = %d", $id));
            if ($name) return (string) $name;
        } catch (\Throwable $e) {}
        $title = get_the_title($id);
        return is_string($title) ? $title : '';
    }

    public static function location_name(?int $id): string
    {
        if (!$id) return '';
        global $wpdb;
        try {
            $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}cfp_locations WHERE id = %d", $id));
            if ($name) return (string) $name;
        } catch (\Throwable $e) {}
        $title = get_the_title($id);
        return is_string($title) ? $title : '';
    }
}

