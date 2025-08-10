<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Repositories;

use ClassFlowPro\Core\Database;
use ClassFlowPro\Models\Entities\Payment;

class PaymentRepository {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function find(int $id): ?Payment {
        $data = $this->db->getOne('payments', ['id' => $id]);
        return $data ? Payment::fromArray($data) : null;
    }

    public function findByTransactionId(string $transactionId): ?Payment {
        $data = $this->db->getOne('payments', ['transaction_id' => $transactionId]);
        return $data ? Payment::fromArray($data) : null;
    }

    public function findByBooking(int $bookingId): array {
        $results = $this->db->get('payments', ['booking_id' => $bookingId], 'created_at DESC');
        return array_map(fn($data) => Payment::fromArray($data), $results);
    }

    public function findCompletedPaymentForBooking(int $bookingId): ?Payment {
        $sql = "SELECT * FROM {$this->db->getTable('payments')} 
                WHERE booking_id = %d 
                AND status = 'completed' 
                AND amount > 0 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $results = $this->db->query($sql, $bookingId);
        return !empty($results) ? Payment::fromArray($results[0]) : null;
    }

    public function findByPackagePurchase(int $packagePurchaseId): array {
        $results = $this->db->get('payments', ['package_purchase_id' => $packagePurchaseId], 'created_at DESC');
        return array_map(fn($data) => Payment::fromArray($data), $results);
    }

    public function findByDateRange(\DateTime $startDate, \DateTime $endDate, array $statuses = []): array {
        $sql = "SELECT * FROM {$this->db->getTable('payments')} 
                WHERE created_at >= %s AND created_at <= %s";
        $params = [
            $startDate->format('Y-m-d 00:00:00'),
            $endDate->format('Y-m-d 23:59:59')
        ];
        
        if (!empty($statuses)) {
            $placeholders = array_fill(0, count($statuses), '%s');
            $sql .= " AND status IN (" . implode(',', $placeholders) . ")";
            $params = array_merge($params, $statuses);
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $results = $this->db->query($sql, ...$params);
        return array_map(fn($data) => Payment::fromArray($data), $results);
    }

    public function save(Payment $payment): Payment {
        $data = $payment->toArray();
        unset($data['id']);
        
        if ($payment->getId()) {
            $this->db->update('payments', $data, ['id' => $payment->getId()]);
        } else {
            $id = $this->db->insert('payments', $data);
            $payment->setId($id);
        }
        
        return $payment;
    }

    public function delete(int $id): bool {
        return $this->db->delete('payments', ['id' => $id]) > 0;
    }

    public function getTotalRevenue(\DateTime $startDate = null, \DateTime $endDate = null): float {
        $sql = "SELECT SUM(amount) FROM {$this->db->getTable('payments')} 
                WHERE status = 'completed' AND amount > 0";
        $params = [];
        
        if ($startDate) {
            $sql .= " AND created_at >= %s";
            $params[] = $startDate->format('Y-m-d 00:00:00');
        }
        
        if ($endDate) {
            $sql .= " AND created_at <= %s";
            $params[] = $endDate->format('Y-m-d 23:59:59');
        }
        
        $result = $this->db->getWpdb()->get_var(
            empty($params) ? $sql : $this->db->getWpdb()->prepare($sql, ...$params)
        );
        
        return (float) ($result ?: 0);
    }

    public function getRevenueByGateway(\DateTime $startDate = null, \DateTime $endDate = null): array {
        $sql = "SELECT gateway, SUM(amount) as total 
                FROM {$this->db->getTable('payments')} 
                WHERE status = 'completed' AND amount > 0";
        $params = [];
        
        if ($startDate) {
            $sql .= " AND created_at >= %s";
            $params[] = $startDate->format('Y-m-d 00:00:00');
        }
        
        if ($endDate) {
            $sql .= " AND created_at <= %s";
            $params[] = $endDate->format('Y-m-d 23:59:59');
        }
        
        $sql .= " GROUP BY gateway";
        
        $results = $this->db->query($sql, ...$params);
        
        $revenue = [];
        foreach ($results as $result) {
            $revenue[$result['gateway']] = (float) $result['total'];
        }
        
        return $revenue;
    }

    public function getPaymentStats(\DateTime $startDate = null, \DateTime $endDate = null): array {
        $sql = "SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'completed' AND amount > 0 THEN amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'completed' AND amount < 0 THEN ABS(amount) ELSE 0 END) as total_refunds
                FROM {$this->db->getTable('payments')}";
        $params = [];
        
        $conditions = [];
        if ($startDate) {
            $conditions[] = "created_at >= %s";
            $params[] = $startDate->format('Y-m-d 00:00:00');
        }
        
        if ($endDate) {
            $conditions[] = "created_at <= %s";
            $params[] = $endDate->format('Y-m-d 23:59:59');
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $result = $this->db->getWpdb()->get_row(
            empty($params) ? $sql : $this->db->getWpdb()->prepare($sql, ...$params),
            ARRAY_A
        );
        
        return [
            'total_transactions' => (int) ($result['total_count'] ?? 0),
            'completed_transactions' => (int) ($result['completed_count'] ?? 0),
            'failed_transactions' => (int) ($result['failed_count'] ?? 0),
            'pending_transactions' => (int) ($result['pending_count'] ?? 0),
            'total_revenue' => (float) ($result['total_revenue'] ?? 0),
            'total_refunds' => (float) ($result['total_refunds'] ?? 0),
            'net_revenue' => (float) (($result['total_revenue'] ?? 0) - ($result['total_refunds'] ?? 0)),
        ];
    }

    public function getRecentPayments(int $limit = 10): array {
        $sql = "SELECT * FROM {$this->db->getTable('payments')} 
                ORDER BY created_at DESC 
                LIMIT %d";
        
        $results = $this->db->query($sql, $limit);
        return array_map(fn($data) => Payment::fromArray($data), $results);
    }

    public function updateStatus(int $id, string $status): bool {
        return $this->db->update('payments', ['status' => $status], ['id' => $id]) > 0;
    }

    public function getPendingPayments(int $olderThanMinutes = 30): array {
        $sql = "SELECT * FROM {$this->db->getTable('payments')} 
                WHERE status = 'pending' 
                AND created_at < DATE_SUB(NOW(), INTERVAL %d MINUTE)
                ORDER BY created_at ASC";
        
        $results = $this->db->query($sql, $olderThanMinutes);
        return array_map(fn($data) => Payment::fromArray($data), $results);
    }

    public function getPaymentsByStudent(int $studentId): array {
        $sql = "SELECT p.* FROM {$this->db->getTable('payments')} p
                JOIN {$this->db->getTable('bookings')} b ON p.booking_id = b.id
                WHERE b.student_id = %d
                ORDER BY p.created_at DESC";
        
        $results = $this->db->query($sql, $studentId);
        return array_map(fn($data) => Payment::fromArray($data), $results);
    }
    
    public function findByBookingAndType(int $bookingId, string $type): array {
        $sql = "SELECT * FROM {$this->db->getTable('payments')} 
                WHERE booking_id = %d 
                AND JSON_EXTRACT(meta, '$.type') = %s
                ORDER BY created_at DESC";
        
        $results = $this->db->query($sql, $bookingId, $type);
        return array_map(fn($data) => Payment::fromArray($data), $results);
    }
}