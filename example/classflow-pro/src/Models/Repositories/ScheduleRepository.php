<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Repositories;

use ClassFlowPro\Core\Database;
use ClassFlowPro\Models\Entities\Schedule;

class ScheduleRepository {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function find(int $id): ?Schedule {
        $data = $this->db->getOne('schedules', ['id' => $id]);
        return $data ? Schedule::fromArray($data) : null;
    }

    public function findAll(array $filters = [], string $orderBy = 'start_time ASC', int $limit = 0): array {
        $where = $this->buildWhereClause($filters);
        $results = $this->db->get('schedules', $where, $orderBy, $limit);
        
        return array_map(fn($data) => Schedule::fromArray($data), $results);
    }

    public function findByClass(int $classId, bool $upcomingOnly = false): array {
        $sql = "SELECT * FROM {$this->db->getTable('schedules')} WHERE class_id = %d";
        
        if ($upcomingOnly) {
            $sql .= " AND start_time > NOW()";
        }
        
        $sql .= " ORDER BY start_time ASC";
        
        $results = $this->db->query($sql, $classId);
        return array_map(fn($data) => Schedule::fromArray($data), $results);
    }

    public function findByInstructor(int $instructorId, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array {
        $sql = "SELECT * FROM {$this->db->getTable('schedules')} WHERE instructor_id = %d";
        $params = [$instructorId];
        
        if ($startDate) {
            $sql .= " AND start_time >= %s";
            $params[] = $startDate->format('Y-m-d H:i:s');
        }
        
        if ($endDate) {
            $sql .= " AND end_time <= %s";
            $params[] = $endDate->format('Y-m-d H:i:s');
        }
        
        $sql .= " ORDER BY start_time ASC";
        
        $results = $this->db->query($sql, ...$params);
        return array_map(fn($data) => Schedule::fromArray($data), $results);
    }

    public function findByDateRange(\DateTime $startDate, \DateTime $endDate, array $filters = []): array {
        $sql = "SELECT * FROM {$this->db->getTable('schedules')} 
                WHERE start_time >= %s AND end_time <= %s";
        $params = [$startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')];
        
        if (isset($filters['class_id'])) {
            $sql .= " AND class_id = %d";
            $params[] = $filters['class_id'];
        }
        
        if (isset($filters['instructor_id'])) {
            $sql .= " AND instructor_id = %d";
            $params[] = $filters['instructor_id'];
        }
        
        if (isset($filters['location_id'])) {
            $sql .= " AND location_id = %d";
            $params[] = $filters['location_id'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND status = %s";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY start_time ASC";
        
        $results = $this->db->query($sql, ...$params);
        return array_map(fn($data) => Schedule::fromArray($data), $results);
    }

    public function save(Schedule $schedule): Schedule {
        $data = $schedule->toArray();
        unset($data['id']);
        
        if ($schedule->getId()) {
            $this->db->update('schedules', $data, ['id' => $schedule->getId()]);
        } else {
            $id = $this->db->insert('schedules', $data);
            $schedule->setId($id);
        }
        
        return $schedule;
    }

    public function delete(int $id): bool {
        return $this->db->delete('schedules', ['id' => $id]) > 0;
    }

    public function getAvailableSpots(int $scheduleId): int {
        $schedule = $this->find($scheduleId);
        if (!$schedule) {
            return 0;
        }
        
        // Get the class to determine capacity
        $sql = "SELECT c.capacity, s.capacity_override 
                FROM {$this->db->getTable('schedules')} s
                JOIN {$this->db->getTable('classes')} c ON s.class_id = c.id
                WHERE s.id = %d";
        
        $result = $this->db->getWpdb()->get_row($this->db->getWpdb()->prepare($sql, $scheduleId));
        $capacity = $result->capacity_override ?? $result->capacity;
        
        // Count confirmed bookings
        $sql = "SELECT COUNT(*) FROM {$this->db->getTable('bookings')} 
                WHERE schedule_id = %d AND status IN ('confirmed', 'completed')";
        
        $bookedCount = (int) $this->db->getWpdb()->get_var($this->db->getWpdb()->prepare($sql, $scheduleId));
        
        return max(0, $capacity - $bookedCount);
    }

    public function isTimeSlotAvailable(int $instructorId, \DateTime $startTime, \DateTime $endTime, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM {$this->db->getTable('schedules')} 
                WHERE instructor_id = %d 
                AND status != 'cancelled'
                AND ((start_time <= %s AND end_time > %s) 
                     OR (start_time < %s AND end_time >= %s)
                     OR (start_time >= %s AND end_time <= %s))";
        
        $params = [
            $instructorId,
            $startTime->format('Y-m-d H:i:s'),
            $startTime->format('Y-m-d H:i:s'),
            $endTime->format('Y-m-d H:i:s'),
            $endTime->format('Y-m-d H:i:s'),
            $startTime->format('Y-m-d H:i:s'),
            $endTime->format('Y-m-d H:i:s')
        ];
        
        if ($excludeId) {
            $sql .= " AND id != %d";
            $params[] = $excludeId;
        }
        
        $count = (int) $this->db->getWpdb()->get_var($this->db->getWpdb()->prepare($sql, ...$params));
        
        return $count === 0;
    }

    public function createRecurringSchedules(Schedule $baseSchedule, string $recurrenceRule, \DateTime $endDate): array {
        $schedules = [];
        $currentDate = clone $baseSchedule->getStartTime();
        
        // Parse recurrence rule (simplified for now)
        $interval = $this->parseRecurrenceRule($recurrenceRule);
        
        while ($currentDate <= $endDate) {
            // Skip the first iteration as it's the base schedule
            if ($currentDate != $baseSchedule->getStartTime()) {
                $newSchedule = clone $baseSchedule;
                $newSchedule->setId(null); // Reset ID for new schedule
                
                $duration = $baseSchedule->getStartTime()->diff($baseSchedule->getEndTime());
                $newEndTime = clone $currentDate;
                $newEndTime->add($duration);
                
                $newSchedule->setStartTime($currentDate);
                $newSchedule->setEndTime($newEndTime);
                
                $schedules[] = $this->save($newSchedule);
            }
            
            $currentDate->add($interval);
        }
        
        return $schedules;
    }

    private function parseRecurrenceRule(string $rule): \DateInterval {
        // Simple parsing for common patterns
        switch ($rule) {
            case 'daily':
                return new \DateInterval('P1D');
            case 'weekly':
                return new \DateInterval('P7D');
            case 'biweekly':
                return new \DateInterval('P14D');
            case 'monthly':
                return new \DateInterval('P1M');
            default:
                // Try to parse custom interval
                return new \DateInterval($rule);
        }
    }

    public function updateStatus(int $id, string $status): bool {
        return $this->db->update('schedules', ['status' => $status], ['id' => $id]) > 0;
    }

    public function cancelFutureRecurringSchedules(int $classId, int $instructorId, \DateTime $afterDate): int {
        $sql = "UPDATE {$this->db->getTable('schedules')} 
                SET status = 'cancelled' 
                WHERE class_id = %d 
                AND instructor_id = %d 
                AND start_time > %s 
                AND status = 'scheduled'";
        
        return $this->db->execute($sql, $classId, $instructorId, $afterDate->format('Y-m-d H:i:s'));
    }

    private function buildWhereClause(array $filters): array {
        $where = [];
        
        if (isset($filters['status'])) {
            $where['status'] = $filters['status'];
        }
        
        if (isset($filters['class_id'])) {
            $where['class_id'] = $filters['class_id'];
        }
        
        if (isset($filters['instructor_id'])) {
            $where['instructor_id'] = $filters['instructor_id'];
        }
        
        if (isset($filters['location_id'])) {
            $where['location_id'] = $filters['location_id'];
        }
        
        return $where;
    }

    public function getUpcomingSchedules(int $limit = 10): array {
        $sql = "SELECT * FROM {$this->db->getTable('schedules')} 
                WHERE start_time > NOW() 
                AND status = 'scheduled' 
                ORDER BY start_time ASC 
                LIMIT %d";
        
        $results = $this->db->query($sql, $limit);
        return array_map(fn($data) => Schedule::fromArray($data), $results);
    }
}