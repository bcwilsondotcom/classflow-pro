<?php
declare(strict_types=1);

namespace ClassFlowPro\Models\Repositories;

use ClassFlowPro\Core\Database;

class InstructorRepository {
    private Database $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function find(int $id): ?\WP_User {
        $user = get_user_by('id', $id);
        return ($user && in_array('classflow_instructor', $user->roles)) ? $user : null;
    }

    public function findAll(array $filters = []): array {
        $args = [
            'role' => 'classflow_instructor',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];

        if (isset($filters['status']) && $filters['status'] === 'active') {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => 'instructor_status',
                    'value' => 'active',
                    'compare' => '='
                ],
                [
                    'key' => 'instructor_status',
                    'compare' => 'NOT EXISTS'
                ]
            ];
        }

        if (isset($filters['search'])) {
            $args['search'] = '*' . $filters['search'] . '*';
        }

        return get_users($args);
    }

    public function getAvailability(int $instructorId, \DateTime $date): array {
        $dayOfWeek = strtolower($date->format('l'));
        $availability = get_user_meta($instructorId, 'instructor_availability', true) ?: [];
        
        if (!isset($availability[$dayOfWeek]) || !$availability[$dayOfWeek]['available']) {
            return [];
        }

        $dayAvailability = $availability[$dayOfWeek];
        $slots = [];

        // Generate time slots based on availability
        $start = new \DateTime($date->format('Y-m-d') . ' ' . $dayAvailability['start_time']);
        $end = new \DateTime($date->format('Y-m-d') . ' ' . $dayAvailability['end_time']);
        $slotDuration = 30; // 30-minute slots

        while ($start < $end) {
            $slotEnd = clone $start;
            $slotEnd->modify("+{$slotDuration} minutes");
            
            if ($slotEnd <= $end) {
                $slots[] = [
                    'start' => clone $start,
                    'end' => clone $slotEnd
                ];
            }
            
            $start->modify("+{$slotDuration} minutes");
        }

        return $slots;
    }

    public function setAvailability(int $instructorId, array $availability): bool {
        return update_user_meta($instructorId, 'instructor_availability', $availability);
    }

    public function getBookedSlots(int $instructorId, \DateTime $date): array {
        $startOfDay = clone $date;
        $startOfDay->setTime(0, 0, 0);
        $endOfDay = clone $date;
        $endOfDay->setTime(23, 59, 59);

        $sql = "SELECT s.start_time, s.end_time 
                FROM {$this->db->getTable('schedules')} s
                WHERE s.instructor_id = %d 
                AND s.start_time >= %s 
                AND s.start_time <= %s
                AND s.status != 'cancelled'
                ORDER BY s.start_time ASC";

        $results = $this->db->query(
            $sql,
            $instructorId,
            $startOfDay->format('Y-m-d H:i:s'),
            $endOfDay->format('Y-m-d H:i:s')
        );

        return array_map(function($row) {
            return [
                'start' => new \DateTime($row->start_time),
                'end' => new \DateTime($row->end_time)
            ];
        }, $results);
    }

    public function getAvailableSlots(int $instructorId, \DateTime $date, int $duration = 60): array {
        $availability = $this->getAvailability($instructorId, $date);
        $bookedSlots = $this->getBookedSlots($instructorId, $date);
        
        if (empty($availability)) {
            return [];
        }

        $availableSlots = [];
        $settings = new \ClassFlowPro\Core\Settings();
        $bufferTime = $settings->get('booking.booking_buffer_time', 15);

        foreach ($availability as $slot) {
            $currentStart = clone $slot['start'];
            $maxEnd = clone $slot['end'];

            while ($currentStart < $maxEnd) {
                $currentEnd = clone $currentStart;
                $currentEnd->modify("+{$duration} minutes");

                if ($currentEnd > $maxEnd) {
                    break;
                }

                // Check if this slot conflicts with any booked slots
                $isAvailable = true;
                foreach ($bookedSlots as $booked) {
                    $bookedStart = clone $booked['start'];
                    $bookedEnd = clone $booked['end'];
                    
                    // Add buffer time
                    $bookedStart->modify("-{$bufferTime} minutes");
                    $bookedEnd->modify("+{$bufferTime} minutes");

                    if (
                        ($currentStart >= $bookedStart && $currentStart < $bookedEnd) ||
                        ($currentEnd > $bookedStart && $currentEnd <= $bookedEnd) ||
                        ($currentStart <= $bookedStart && $currentEnd >= $bookedEnd)
                    ) {
                        $isAvailable = false;
                        break;
                    }
                }

                if ($isAvailable) {
                    $availableSlots[] = [
                        'start' => clone $currentStart,
                        'end' => clone $currentEnd
                    ];
                }

                $currentStart->modify("+30 minutes"); // Move in 30-minute increments
            }
        }

        return $availableSlots;
    }

    public function getStats(int $instructorId): array {
        $sql = "SELECT 
                COUNT(DISTINCT s.id) as total_classes,
                COUNT(DISTINCT CASE WHEN s.status = 'completed' THEN s.id END) as completed_classes,
                COUNT(DISTINCT b.id) as total_bookings,
                COALESCE(SUM(p.amount), 0) as total_revenue
                FROM {$this->db->getTable('schedules')} s
                LEFT JOIN {$this->db->getTable('bookings')} b ON s.id = b.schedule_id
                LEFT JOIN {$this->db->getTable('payments')} p ON b.id = p.booking_id AND p.status = 'completed'
                WHERE s.instructor_id = %d";

        $result = $this->db->getWpdb()->get_row(
            $this->db->getWpdb()->prepare($sql, $instructorId),
            ARRAY_A
        );

        return $result ?: [
            'total_classes' => 0,
            'completed_classes' => 0,
            'total_bookings' => 0,
            'total_revenue' => 0
        ];
    }

    public function updateStatus(int $instructorId, string $status): bool {
        return update_user_meta($instructorId, 'instructor_status', $status);
    }
}