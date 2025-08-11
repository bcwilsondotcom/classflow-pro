<?php
namespace ClassFlowPro\DB\Repositories;

if (!defined('ABSPATH')) { exit; }

class InstructorsRepository
{
    private \wpdb $db;
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'cfp_instructors';
    }

    public function find(int $id): ?array
    {
        $row = $this->db->get_row($this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return $row ?: null;
    }

    public function paginate(int $page = 1, int $per_page = 20, string $search = ''): array
    {
        $offset = max(0, ($page - 1) * $per_page);
        $where = 'WHERE 1=1';
        $params = [];
        if ($search) {
            $where .= ' AND name LIKE %s';
            $params[] = '%' . $this->db->esc_like($search) . '%';
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

    public function create(array $data): int
    {
        $row = wp_parse_args($data, [
            'name' => '', 'bio' => '', 'email' => null,
            'payout_percent' => null, 'stripe_account_id' => null,
            'availability_weekly' => null, 'blackout_dates' => null,
            'featured_image_id' => null,
        ]);
        $this->db->insert($this->table, [
            'name' => $row['name'], 'bio' => $row['bio'], 'email' => $row['email'],
            'payout_percent' => $row['payout_percent'], 'stripe_account_id' => $row['stripe_account_id'],
            'availability_weekly' => $row['availability_weekly'], 'blackout_dates' => $row['blackout_dates'],
            'featured_image_id' => $row['featured_image_id'],
            'created_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true),
        ], ['%s','%s','%s','%f','%s','%s','%s','%d','%s','%s']);
        return (int) $this->db->insert_id;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];$formats = [];
        $map = ['name'=>'%s','bio'=>'%s','email'=>'%s','payout_percent'=>'%f','stripe_account_id'=>'%s','availability_weekly'=>'%s','blackout_dates'=>'%s','featured_image_id'=>'%d'];
        foreach ($map as $k=>$fmt) if (array_key_exists($k,$data)) { $fields[$k]=$data[$k]; $formats[]=$fmt; }
        $fields['updated_at'] = current_time('mysql', true); $formats[]='%s';
        return false !== $this->db->update($this->table, $fields, ['id'=>$id], $formats, ['%d']);
    }

    public function delete(int $id): bool
    {
        return false !== $this->db->delete($this->table, ['id'=>$id], ['%d']);
    }
}
