<?php
declare(strict_types=1);

namespace ClassFlowPro\Core;

class Database {
    private \wpdb $wpdb;
    private array $tables;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->initializeTables();
    }

    private function initializeTables(): void {
        $prefix = $this->wpdb->prefix . 'cf_';
        
        $this->tables = [
            'classes' => $prefix . 'classes',
            'categories' => $prefix . 'categories',
            'instructors' => $prefix . 'instructors',
            'locations' => $prefix . 'locations',
            'schedules' => $prefix . 'schedules',
            'students' => $prefix . 'students',
            'bookings' => $prefix . 'bookings',
            'payments' => $prefix . 'payments',
            'waitlists' => $prefix . 'waitlists',
            'attendance' => $prefix . 'attendance',
            'packages' => $prefix . 'packages',
            'student_packages' => $prefix . 'student_packages',
            'email_logs' => $prefix . 'email_logs',
        ];
    }

    public function getTable(string $table): string {
        if (!isset($this->tables[$table])) {
            throw new \InvalidArgumentException("Table '{$table}' does not exist.");
        }
        
        return $this->tables[$table];
    }

    public function insert(string $table, array $data): int {
        $result = $this->wpdb->insert(
            $this->getTable($table),
            $data,
            $this->getFormats($data)
        );

        if ($result === false) {
            throw new \RuntimeException($this->wpdb->last_error);
        }

        return $this->wpdb->insert_id;
    }

    public function update(string $table, array $data, array $where): int {
        $result = $this->wpdb->update(
            $this->getTable($table),
            $data,
            $where,
            $this->getFormats($data),
            $this->getFormats($where)
        );

        if ($result === false) {
            throw new \RuntimeException($this->wpdb->last_error);
        }

        return $result;
    }

    public function delete(string $table, array $where): int {
        $result = $this->wpdb->delete(
            $this->getTable($table),
            $where,
            $this->getFormats($where)
        );

        if ($result === false) {
            throw new \RuntimeException($this->wpdb->last_error);
        }

        return $result;
    }

    public function get(string $table, array $where = [], string $orderBy = '', int $limit = 0): array {
        $sql = "SELECT * FROM " . $this->getTable($table);
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                if (is_null($value)) {
                    $conditions[] = "`{$column}` IS NULL";
                } else {
                    $conditions[] = $this->wpdb->prepare("`{$column}` = %s", $value);
                }
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function getOne(string $table, array $where): ?array {
        $results = $this->get($table, $where, '', 1);
        return $results ? $results[0] : null;
    }

    public function query(string $sql, ...$args): array {
        if (!empty($args)) {
            $sql = $this->wpdb->prepare($sql, ...$args);
        }
        
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        if ($this->wpdb->last_error) {
            throw new \RuntimeException($this->wpdb->last_error);
        }
        
        return $results ?: [];
    }

    public function execute(string $sql, ...$args): int {
        if (!empty($args)) {
            $sql = $this->wpdb->prepare($sql, ...$args);
        }
        
        $result = $this->wpdb->query($sql);
        
        if ($result === false) {
            throw new \RuntimeException($this->wpdb->last_error);
        }
        
        return $result;
    }

    public function beginTransaction(): void {
        $this->wpdb->query('START TRANSACTION');
    }

    public function commit(): void {
        $this->wpdb->query('COMMIT');
    }

    public function rollback(): void {
        $this->wpdb->query('ROLLBACK');
    }

    private function getFormats(array $data): array {
        $formats = [];
        
        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        
        return $formats;
    }

    public function getWpdb(): \wpdb {
        return $this->wpdb;
    }
}