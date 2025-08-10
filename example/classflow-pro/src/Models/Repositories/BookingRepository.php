<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Repositories;

use ClassFlowPro\Core\Database;
use ClassFlowPro\Models\Entities\Booking;

class BookingRepository {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function find(int $id): ?Booking {
        $data = $this->db->getOne('bookings', ['id' => $id]);
        return $data ? Booking::fromArray($data) : null;
    }

    public function findByCode(string $bookingCode): ?Booking {
        $data = $this->db->getOne('bookings', ['booking_code' => $bookingCode]);
        return $data ? Booking::fromArray($data) : null;
    }
    
    public function findByBookingCode(string $bookingCode): ?Booking {
        return $this->findByCode($bookingCode);
    }

    public function findAll(array $filters = [], string $orderBy = 'created_at DESC', int $limit = 0): array {
        $where = $this->buildWhereClause($filters);
        $results = $this->db->get('bookings', $where, $orderBy, $limit);
        
        return array_map(fn($data) => Booking::fromArray($data), $results);
    }

    public function findByStudent(int $studentId, array $filters = []): array {
        $sql = "SELECT * FROM {$this->db->getTable('bookings')} WHERE student_id = %d";
        $params = [$studentId];
        
        if (isset($filters['status'])) {
            $sql .= " AND status = %s";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['payment_status'])) {
            $sql .= " AND payment_status = %s";
            $params[] = $filters['payment_status'];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $results = $this->db->query($sql, ...$params);
        return array_map(fn($data) => Booking::fromArray($data), $results);
    }

    public function findBySchedule(int $scheduleId, array $filters = []): array {
        $sql = "SELECT * FROM {$this->db->getTable('bookings')} WHERE schedule_id = %d";
        $params = [$scheduleId];
        
        if (isset($filters['status'])) {
            $sql .= " AND status = %s";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY created_at ASC";
        
        $results = $this->db->query($sql, ...$params);
        return array_map(fn($data) => Booking::fromArray($data), $results);
    }

    public function save(Booking $booking): Booking {
        $data = $booking->toArray();
        unset($data['id']);
        
        if ($booking->getId()) {
            $this->db->update('bookings', $data, ['id' => $booking->getId()]);
        } else {
            $id = $this->db->insert('bookings', $data);
            $booking->setId($id);
        }
        
        return $booking;
    }

    public function delete(int $id): bool {
        return $this->db->delete('bookings', ['id' => $id]) > 0;
    }

    public function generateBookingCode(): string {
        do {
            $code = 'BK' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while ($this->findByCode($code) !== null);
        
        return $code;
    }

    public function hasStudentBookedSchedule(int $studentId, int $scheduleId): bool {
        $sql = "SELECT COUNT(*) FROM {$this->db->getTable('bookings')} 
                WHERE student_id = %d AND schedule_id = %d 
                AND status NOT IN ('cancelled')";
        
        $count = (int) $this->db->getWpdb()->get_var(
            $this->db->getWpdb()->prepare($sql, $studentId, $scheduleId)
        );
        
        return $count > 0;
    }

    public function getBookingCountForSchedule(int $scheduleId, array $statuses = ['confirmed', 'completed']): int {
        $placeholders = array_fill(0, count($statuses), '%s');
        $sql = "SELECT COUNT(*) FROM {$this->db->getTable('bookings')} 
                WHERE schedule_id = %d AND status IN (" . implode(',', $placeholders) . ")";
        
        $params = array_merge([$scheduleId], $statuses);
        
        return (int) $this->db->getWpdb()->get_var(
            $this->db->getWpdb()->prepare($sql, ...$params)
        );
    }

    public function getUpcomingBookings(int $studentId, int $limit = 10): array {
        $sql = "SELECT b.* FROM {$this->db->getTable('bookings')} b
                JOIN {$this->db->getTable('schedules')} s ON b.schedule_id = s.id
                WHERE b.student_id = %d 
                AND b.status IN ('confirmed', 'pending')
                AND s.start_time > NOW()
                ORDER BY s.start_time ASC
                LIMIT %d";
        
        $results = $this->db->query($sql, $studentId, $limit);
        return array_map(fn($data) => Booking::fromArray($data), $results);
    }

    public function getPastBookings(int $studentId, int $limit = 10): array {
        $sql = "SELECT b.* FROM {$this->db->getTable('bookings')} b
                JOIN {$this->db->getTable('schedules')} s ON b.schedule_id = s.id
                WHERE b.student_id = %d 
                AND s.end_time < NOW()
                ORDER BY s.start_time DESC
                LIMIT %d";
        
        $results = $this->db->query($sql, $studentId, $limit);
        return array_map(fn($data) => Booking::fromArray($data), $results);
    }

    public function updateStatus(int $id, string $status): bool {
        return $this->db->update('bookings', ['status' => $status], ['id' => $id]) > 0;
    }

    public function updatePaymentStatus(int $id, string $paymentStatus): bool {
        return $this->db->update('bookings', ['payment_status' => $paymentStatus], ['id' => $id]) > 0;
    }

    public function cancelBookingsForSchedule(int $scheduleId): int {
        return $this->db->update(
            'bookings',
            ['status' => 'cancelled'],
            ['schedule_id' => $scheduleId, 'status' => 'confirmed']
        );
    }

    public function getRevenueByDateRange(\DateTime $startDate, \DateTime $endDate): float {
        $sql = "SELECT SUM(amount) FROM {$this->db->getTable('bookings')} 
                WHERE payment_status = 'completed' 
                AND created_at >= %s 
                AND created_at <= %s";
        
        $revenue = $this->db->getWpdb()->get_var(
            $this->db->getWpdb()->prepare(
                $sql,
                $startDate->format('Y-m-d 00:00:00'),
                $endDate->format('Y-m-d 23:59:59')
            )
        );
        
        return (float) ($revenue ?: 0);
    }

    public function getBookingStats(int $studentId): array {
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show
                FROM {$this->db->getTable('bookings')} 
                WHERE student_id = %d";
        
        $result = $this->db->getWpdb()->get_row(
            $this->db->getWpdb()->prepare($sql, $studentId),
            ARRAY_A
        );
        
        return $result ?: [
            'total' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'no_show' => 0
        ];
    }

    private function buildWhereClause(array $filters): array {
        $where = [];
        
        if (isset($filters['status'])) {
            $where['status'] = $filters['status'];
        }
        
        if (isset($filters['payment_status'])) {
            $where['payment_status'] = $filters['payment_status'];
        }
        
        if (isset($filters['student_id'])) {
            $where['student_id'] = $filters['student_id'];
        }
        
        if (isset($filters['schedule_id'])) {
            $where['schedule_id'] = $filters['schedule_id'];
        }
        
        return $where;
    }

    public function cleanupExpiredPendingBookings(int $expiryMinutes = 30): int {
        $sql = "UPDATE {$this->db->getTable('bookings')} 
                SET status = 'expired' 
                WHERE status = 'pending' 
                AND payment_status = 'pending'
                AND created_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)";
        
        return $this->db->execute($sql, $expiryMinutes);
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
        
        $sql = "SELECT COUNT(*) FROM {$this->db->getTable('bookings')}" . $whereString;
        return (int) $this->db->getWpdb()->get_var($sql);
    }
    
    public function paginate(int $page, int $perPage, array $filters = [], string $orderBy = 'created_at DESC'): array {
        $offset = ($page - 1) * $perPage;
        $where = $this->buildWhereClause($filters);
        
        $sql = "SELECT * FROM {$this->db->getTable('bookings')}";
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                $conditions[] = $this->db->getWpdb()->prepare("`{$column}` = %s", $value);
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        
        $sql .= " ORDER BY {$orderBy} LIMIT %d OFFSET %d";
        
        $results = $this->db->query($sql, $perPage, $offset);
        $items = array_map(fn($data) => Booking::fromArray($data), $results);
        
        return [
            'items' => $items,
            'total' => $this->count($filters),
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($this->count($filters) / $perPage),
        ];
    }
}