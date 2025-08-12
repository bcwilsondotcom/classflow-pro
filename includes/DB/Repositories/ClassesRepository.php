<?php
namespace ClassFlowPro\DB\Repositories;

if (!defined('ABSPATH')) { exit; }

class ClassesRepository
{
    private \wpdb $db;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'cfp_classes';
    }

    public function find(int $id): ?array
    {
        $row = $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    public function paginate(int $page = 1, int $per_page = 20, array $filters = []): array
    {
        $offset = max(0, ($page - 1) * $per_page);
        $where = 'WHERE 1=1';
        $params = [];
        if (!empty($filters['status'])) {
            $where .= ' AND status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where .= ' AND name LIKE %s';
            $params[] = '%' . $this->db->esc_like($filters['search']) . '%';
        }
        if ($params) {
            $total = (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$this->table} {$where}", ...$params));
        } else {
            $total = (int) $this->db->get_var("SELECT COUNT(*) FROM {$this->table} {$where}");
        }
        $items = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page, $offset])
        ), ARRAY_A);
        return ['items' => $items, 'total' => $total];
    }

    public function search(string $term, int $limit = 50): array
    {
        $like = '%' . $this->db->esc_like($term) . '%';
        return $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->table} WHERE name LIKE %s ORDER BY name ASC LIMIT %d",
            $like,
            $limit
        ), ARRAY_A);
    }

    public function create(array $data): int
    {
        $defaults = [
            'name' => '',
            'description' => '',
            'duration_mins' => 60,
            'capacity' => 8,
            'price_cents' => 0,
            'currency' => 'usd',
            'status' => 'active',
            'scheduling_type' => 'fixed',
            'featured_image_id' => null,
            'default_location_id' => null,
            'color_hex' => null,
        ];
        $row = array_merge($defaults, $data);
        $this->db->insert($this->table, [
            'name' => $row['name'],
            'description' => $row['description'],
            'duration_mins' => (int) $row['duration_mins'],
            'capacity' => (int) $row['capacity'],
            'price_cents' => (int) $row['price_cents'],
            'currency' => $row['currency'],
            'status' => $row['status'],
            'scheduling_type' => $row['scheduling_type'],
            'featured_image_id' => $row['featured_image_id'] ? (int) $row['featured_image_id'] : null,
            'default_location_id' => $row['default_location_id'] ? (int) $row['default_location_id'] : null,
            'color_hex' => $row['color_hex'] ? $row['color_hex'] : null,
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['%s','%s','%d','%d','%d','%s','%s','%s','%d','%d','%s','%s','%s']);
        return (int) $this->db->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $formats = [];
        $map = [
            'name' => '%s', 'description' => '%s', 'duration_mins' => '%d', 'capacity' => '%d',
            'price_cents' => '%d', 'currency' => '%s', 'status' => '%s', 'scheduling_type' => '%s',
            'featured_image_id' => '%d', 'default_location_id' => '%d', 'color_hex' => '%s'
        ];
        foreach ($map as $k => $fmt) {
            if (array_key_exists($k, $data)) {
                $fields[$k] = $data[$k];
                $formats[] = $fmt;
            }
        }
        if (!$fields) return true;
        $fields['updated_at'] = current_time('mysql', true);
        $formats[] = '%s';
        return false !== $this->db->update($this->table, $fields, ['id' => $id], $formats, ['%d']);
    }

    public function delete(int $id): bool
    {
        return false !== $this->db->delete($this->table, ['id' => $id], ['%d']);
    }
}
