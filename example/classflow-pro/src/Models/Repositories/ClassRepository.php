<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Repositories;

use ClassFlowPro\Core\Database;
use ClassFlowPro\Models\Entities\ClassEntity;

class ClassRepository {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function find(int $id): ?ClassEntity {
        $data = $this->db->getOne('classes', ['id' => $id]);
        return $data ? ClassEntity::fromArray($data) : null;
    }

    public function findBySlug(string $slug): ?ClassEntity {
        $data = $this->db->getOne('classes', ['slug' => $slug]);
        return $data ? ClassEntity::fromArray($data) : null;
    }

    public function findAll(array $filters = [], string $orderBy = 'name ASC', int $limit = 0): array {
        $where = $this->buildWhereClause($filters);
        $results = $this->db->get('classes', $where, $orderBy, $limit);
        
        return array_map(fn($data) => ClassEntity::fromArray($data), $results);
    }

    public function findByCategory(int $categoryId, bool $activeOnly = true): array {
        $filters = ['category_id' => $categoryId];
        if ($activeOnly) {
            $filters['status'] = 'active';
        }
        
        return $this->findAll($filters);
    }

    public function search(string $keyword): array {
        $sql = "SELECT * FROM {$this->db->getTable('classes')} 
                WHERE (name LIKE %s OR description LIKE %s) 
                AND status = 'active' 
                ORDER BY name ASC";
        
        $keyword = '%' . $this->db->getWpdb()->esc_like($keyword) . '%';
        $results = $this->db->query($sql, $keyword, $keyword);
        
        return array_map(fn($data) => ClassEntity::fromArray($data), $results);
    }

    public function save(ClassEntity $class): ClassEntity {
        $data = $class->toArray();
        unset($data['id']); // Remove ID for insert/update logic
        
        if ($class->getId()) {
            $this->db->update('classes', $data, ['id' => $class->getId()]);
        } else {
            $id = $this->db->insert('classes', $data);
            $class->setId($id);
        }
        
        return $class;
    }

    public function delete(int $id): bool {
        return $this->db->delete('classes', ['id' => $id]) > 0;
    }

    public function count(array $filters = []): int {
        $where = $this->buildWhereClause($filters);
        $whereString = '';
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                $conditions[] = $this->db->getWpdb()->prepare("`{$column}` = %s", $value);
            }
            $whereString = ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $sql = "SELECT COUNT(*) FROM {$this->db->getTable('classes')}" . $whereString;
        return (int) $this->db->getWpdb()->get_var($sql);
    }

    public function paginate(int $page, int $perPage, array $filters = [], string $orderBy = 'name ASC'): array {
        $offset = ($page - 1) * $perPage;
        $where = $this->buildWhereClause($filters);
        
        $sql = "SELECT * FROM {$this->db->getTable('classes')}";
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                $conditions[] = $this->db->getWpdb()->prepare("`{$column}` = %s", $value);
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY {$orderBy} LIMIT %d OFFSET %d";
        
        $results = $this->db->query($sql, $perPage, $offset);
        $items = array_map(fn($data) => ClassEntity::fromArray($data), $results);
        
        return [
            'items' => $items,
            'total' => $this->count($filters),
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($this->count($filters) / $perPage),
        ];
    }

    public function getUpcomingClasses(int $limit = 10): array {
        $sql = "SELECT DISTINCT c.* 
                FROM {$this->db->getTable('classes')} c
                INNER JOIN {$this->db->getTable('schedules')} s ON c.id = s.class_id
                WHERE c.status = 'active' 
                AND s.status = 'scheduled'
                AND s.start_time > NOW()
                ORDER BY s.start_time ASC
                LIMIT %d";
        
        $results = $this->db->query($sql, $limit);
        return array_map(fn($data) => ClassEntity::fromArray($data), $results);
    }

    public function getPopularClasses(int $limit = 10): array {
        $sql = "SELECT c.*, COUNT(b.id) as booking_count
                FROM {$this->db->getTable('classes')} c
                LEFT JOIN {$this->db->getTable('schedules')} s ON c.id = s.class_id
                LEFT JOIN {$this->db->getTable('bookings')} b ON s.id = b.schedule_id
                WHERE c.status = 'active'
                AND b.status IN ('confirmed', 'completed')
                GROUP BY c.id
                ORDER BY booking_count DESC
                LIMIT %d";
        
        $results = $this->db->query($sql, $limit);
        return array_map(fn($data) => ClassEntity::fromArray($data), $results);
    }

    private function buildWhereClause(array $filters): array {
        $where = [];
        
        if (isset($filters['status'])) {
            $where['status'] = $filters['status'];
        }
        
        if (isset($filters['category_id'])) {
            $where['category_id'] = $filters['category_id'];
        }
        
        if (isset($filters['skill_level'])) {
            $where['skill_level'] = $filters['skill_level'];
        }
        
        return $where;
    }

    public function updateStatus(int $id, string $status): bool {
        return $this->db->update('classes', ['status' => $status], ['id' => $id]) > 0;
    }

    public function generateUniqueSlug(string $name, ?int $excludeId = null): string {
        $slug = sanitize_title($name);
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool {
        $where = ['slug' => $slug];
        $sql = "SELECT COUNT(*) FROM {$this->db->getTable('classes')} WHERE slug = %s";
        
        if ($excludeId) {
            $sql .= " AND id != %d";
            return (int) $this->db->getWpdb()->get_var($this->db->getWpdb()->prepare($sql, $slug, $excludeId)) > 0;
        }
        
        return (int) $this->db->getWpdb()->get_var($this->db->getWpdb()->prepare($sql, $slug)) > 0;
    }
}