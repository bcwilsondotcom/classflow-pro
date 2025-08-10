<?php
namespace ClassFlowPro\Logging;

class Logger
{
    public static function log(string $level, string $source, string $message, array $context = []): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_logs';
        $wpdb->insert($table, [
            'level' => substr($level, 0, 20),
            'source' => substr($source, 0, 60),
            'message' => $message,
            'context' => $context ? wp_json_encode($context) : null,
        ], ['%s','%s','%s','%s']);
    }

    public static function recent(int $limit = 200, ?string $level = null, ?string $source = null): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'cfp_logs';
        $where = [];
        $params = [];
        if ($level) { $where[] = 'level = %s'; $params[] = $level; }
        if ($source) { $where[] = 'source LIKE %s'; $params[] = '%' . $wpdb->esc_like($source) . '%'; }
        $sql = "SELECT * FROM $table" . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . " ORDER BY created_at DESC, id DESC LIMIT %d";
        $params[] = $limit;
        $prepared = $wpdb->prepare($sql, $params);
        return $wpdb->get_results($prepared, ARRAY_A) ?: [];
    }
}

