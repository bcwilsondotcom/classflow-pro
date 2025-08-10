<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Repositories;

use ClassFlowPro\Core\Database;
use ClassFlowPro\Models\Entities\Location;

class LocationRepository {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function find(int $id): ?Location {
        $data = $this->db->getOne('locations', ['id' => $id]);
        return $data ? Location::fromArray($data) : null;
    }

    public function findAll(array $filters = [], string $orderBy = 'name ASC', int $limit = 0): array {
        $where = $this->buildWhereClause($filters);
        $results = $this->db->get('locations', $where, $orderBy, $limit);
        
        return array_map(fn($data) => Location::fromArray($data), $results);
    }

    public function save(Location $location): Location {
        $data = $location->toArray();
        unset($data['id']);
        
        if ($location->getId()) {
            $this->db->update('locations', $data, ['id' => $location->getId()]);
        } else {
            $id = $this->db->insert('locations', $data);
            $location->setId($id);
        }
        
        return $location;
    }

    public function delete(int $id): bool {
        return $this->db->delete('locations', ['id' => $id]) > 0;
    }

    public function getActiveLocations(): array {
        return $this->findAll(['status' => 'active']);
    }

    public function findByCapacity(int $minCapacity): array {
        $sql = "SELECT * FROM {$this->db->getTable('locations')} 
                WHERE capacity >= %d AND status = 'active' 
                ORDER BY capacity ASC";
        
        $results = $this->db->query($sql, $minCapacity);
        return array_map(fn($data) => Location::fromArray($data), $results);
    }

    public function checkAvailability(int $locationId, \DateTime $startTime, \DateTime $endTime): bool {
        $sql = "SELECT COUNT(*) FROM {$this->db->getTable('schedules')} 
                WHERE location_id = %d 
                AND status != 'cancelled'
                AND ((start_time <= %s AND end_time > %s) 
                     OR (start_time < %s AND end_time >= %s)
                     OR (start_time >= %s AND end_time <= %s))";
        
        $count = (int) $this->db->getWpdb()->get_var(
            $this->db->getWpdb()->prepare(
                $sql,
                $locationId,
                $startTime->format('Y-m-d H:i:s'),
                $startTime->format('Y-m-d H:i:s'),
                $endTime->format('Y-m-d H:i:s'),
                $endTime->format('Y-m-d H:i:s'),
                $startTime->format('Y-m-d H:i:s'),
                $endTime->format('Y-m-d H:i:s')
            )
        );
        
        return $count === 0;
    }

    private function buildWhereClause(array $filters): array {
        $where = [];
        
        if (isset($filters['status'])) {
            $where['status'] = $filters['status'];
        }
        
        return $where;
    }

    public function updateStatus(int $id, string $status): bool {
        return $this->db->update('locations', ['status' => $status], ['id' => $id]) > 0;
    }
}