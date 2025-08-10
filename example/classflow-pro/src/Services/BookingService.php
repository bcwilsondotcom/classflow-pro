<?php
declare(strict_types=1);

namespace ClassFlowPro\Services;

use ClassFlowPro\Models\Entities\Booking;
use ClassFlowPro\Models\Repositories\BookingRepository;
use ClassFlowPro\Models\Repositories\ScheduleRepository;
use ClassFlowPro\Models\Repositories\StudentRepository;
use ClassFlowPro\Core\Settings;

class BookingService {
    private BookingRepository $bookingRepository;
    private ScheduleRepository $scheduleRepository;
    private Container $container;
    private Settings $settings;

    public function __construct(Container $container) {
        $this->container = $container;
        $this->bookingRepository = new BookingRepository();
        $this->scheduleRepository = new ScheduleRepository();
        $this->settings = new Settings();
    }

    public function createBooking(array $data): Booking {
        // Validate booking data
        $this->validateBookingData($data);
        
        $scheduleId = (int) $data['schedule_id'];
        $studentId = (int) $data['student_id'];
        
        // Check if schedule exists and is bookable
        $schedule = $this->scheduleRepository->find($scheduleId);
        if (!$schedule) {
            throw new \RuntimeException(__('Schedule not found.', 'classflow-pro'));
        }
        
        if (!$schedule->isActive() || $schedule->isPast()) {
            throw new \RuntimeException(__('This class is no longer available for booking.', 'classflow-pro'));
        }
        
        // Check if student hasn't already booked this schedule
        if ($this->bookingRepository->hasStudentBookedSchedule($studentId, $scheduleId)) {
            throw new \RuntimeException(__('You have already booked this class.', 'classflow-pro'));
        }
        
        // Check available spots
        $availableSpots = $this->scheduleRepository->getAvailableSpots($scheduleId);
        if ($availableSpots <= 0) {
            throw new \RuntimeException(__('This class is fully booked.', 'classflow-pro'));
        }
        
        // Check prerequisites
        $classService = $this->container->get('class_service');
        if (!$classService->checkPrerequisites($schedule->getClassId(), $studentId)) {
            throw new \RuntimeException(__('You must complete the prerequisite classes first.', 'classflow-pro'));
        }
        
        // Calculate amount
        $amount = $this->calculateBookingAmount($schedule);
        
        // Create booking
        $booking = new Booking(
            $scheduleId,
            $studentId,
            $this->bookingRepository->generateBookingCode(),
            $amount,
            $this->settings->get('booking.auto_confirm_bookings') ? 'confirmed' : 'pending',
            'pending'
        );
        
        if (isset($data['notes'])) {
            $booking->setNotes($data['notes']);
        }
        
        if (isset($data['meta'])) {
            $booking->setMeta($data['meta']);
        }
        
        // Save booking
        $booking = $this->bookingRepository->save($booking);
        
        // Trigger actions
        do_action('classflow_pro_booking_created', $booking);
        
        // Send confirmation email if auto-confirmed
        if ($booking->isConfirmed()) {
            $this->container->get('notification_service')->sendBookingConfirmation($booking);
        }
        
        return $booking;
    }

    public function updateBooking(int $id, array $data): Booking {
        $booking = $this->bookingRepository->find($id);
        if (!$booking) {
            throw new \RuntimeException(__('Booking not found.', 'classflow-pro'));
        }
        
        // Check if booking can be modified
        if (!$booking->canBeModified()) {
            throw new \RuntimeException(__('This booking cannot be modified.', 'classflow-pro'));
        }
        
        // Update fields
        if (isset($data['status'])) {
            $oldStatus = $booking->getStatus();
            $booking->setStatus($data['status']);
            
            // Handle status-specific logic
            if ($oldStatus !== $data['status']) {
                $this->handleStatusChange($booking, $oldStatus, $data['status']);
            }
        }
        
        if (isset($data['payment_status'])) {
            $booking->setPaymentStatus($data['payment_status']);
        }
        
        if (isset($data['amount'])) {
            $booking->setAmount((float) $data['amount']);
        }
        
        if (isset($data['notes'])) {
            $booking->setNotes($data['notes']);
        }
        
        if (isset($data['meta'])) {
            $booking->setMeta($data['meta']);
        }
        
        // Save changes
        $booking = $this->bookingRepository->save($booking);
        
        // Trigger action
        do_action('classflow_pro_booking_updated', $booking);
        
        return $booking;
    }

    public function cancelBooking(int $id, string $reason = ''): bool {
        $booking = $this->bookingRepository->find($id);
        if (!$booking) {
            throw new \RuntimeException(__('Booking not found.', 'classflow-pro'));
        }
        
        if (!$booking->canBeCancelled()) {
            throw new \RuntimeException(__('This booking cannot be cancelled.', 'classflow-pro'));
        }
        
        // Check cancellation policy
        $schedule = $this->scheduleRepository->find($booking->getScheduleId());
        if (!$this->canCancelBooking($booking, $schedule)) {
            throw new \RuntimeException(__('Booking cannot be cancelled due to cancellation policy.', 'classflow-pro'));
        }
        
        // Update booking status
        $booking->setStatus('cancelled');
        if ($reason) {
            $meta = $booking->getMeta();
            $meta['cancellation_reason'] = $reason;
            $meta['cancelled_at'] = current_time('mysql');
            $booking->setMeta($meta);
        }
        
        $this->bookingRepository->save($booking);
        
        // Process refund if applicable
        if ($booking->isPaymentCompleted()) {
            $this->container->get('payment_service')->processRefund($booking);
        }
        
        // Send cancellation notification
        $this->container->get('notification_service')->sendBookingCancellation($booking);
        
        // Check waitlist
        $this->processWaitlist($booking->getScheduleId());
        
        // Trigger action
        do_action('classflow_pro_booking_cancelled', $booking);
        
        return true;
    }

    public function confirmBooking(int $id): Booking {
        $booking = $this->bookingRepository->find($id);
        if (!$booking) {
            throw new \RuntimeException(__('Booking not found.', 'classflow-pro'));
        }
        
        if (!$booking->isPending()) {
            throw new \RuntimeException(__('Only pending bookings can be confirmed.', 'classflow-pro'));
        }
        
        $booking->setStatus('confirmed');
        $booking = $this->bookingRepository->save($booking);
        
        // Send confirmation notification
        $this->container->get('notification_service')->sendBookingConfirmation($booking);
        
        // Trigger action
        do_action('classflow_pro_booking_confirmed', $booking);
        
        return $booking;
    }

    public function markAsNoShow(int $id): Booking {
        $booking = $this->bookingRepository->find($id);
        if (!$booking) {
            throw new \RuntimeException(__('Booking not found.', 'classflow-pro'));
        }
        
        $booking->setStatus('no_show');
        $booking = $this->bookingRepository->save($booking);
        
        // Trigger action
        do_action('classflow_pro_booking_no_show', $booking);
        
        return $booking;
    }

    public function getBooking(int $id): ?Booking {
        return $this->bookingRepository->find($id);
    }

    public function getBookingByCode(string $code): ?Booking {
        return $this->bookingRepository->findByCode($code);
    }

    public function getStudentBookings(int $studentId, array $filters = []): array {
        return $this->bookingRepository->findByStudent($studentId, $filters);
    }

    public function getScheduleBookings(int $scheduleId, array $filters = []): array {
        return $this->bookingRepository->findBySchedule($scheduleId, $filters);
    }

    public function getUpcomingBookings(int $studentId, int $limit = 10): array {
        return $this->bookingRepository->getUpcomingBookings($studentId, $limit);
    }

    public function getPastBookings(int $studentId, int $limit = 10): array {
        return $this->bookingRepository->getPastBookings($studentId, $limit);
    }

    public function getBookingStats(int $studentId): array {
        return $this->bookingRepository->getBookingStats($studentId);
    }

    public function canStudentBook(int $studentId, int $scheduleId): array {
        $canBook = true;
        $reasons = [];
        
        // Check if already booked
        if ($this->bookingRepository->hasStudentBookedSchedule($studentId, $scheduleId)) {
            $canBook = false;
            $reasons[] = __('You have already booked this class.', 'classflow-pro');
        }
        
        // Check available spots
        $availableSpots = $this->scheduleRepository->getAvailableSpots($scheduleId);
        if ($availableSpots <= 0) {
            $canBook = false;
            $reasons[] = __('This class is fully booked.', 'classflow-pro');
        }
        
        // Check schedule status
        $schedule = $this->scheduleRepository->find($scheduleId);
        if (!$schedule || !$schedule->isActive() || $schedule->isPast()) {
            $canBook = false;
            $reasons[] = __('This class is no longer available for booking.', 'classflow-pro');
        }
        
        // Check prerequisites
        if ($schedule) {
            $classService = $this->container->get('class_service');
            if (!$classService->checkPrerequisites($schedule->getClassId(), $studentId)) {
                $canBook = false;
                $reasons[] = __('You must complete the prerequisite classes first.', 'classflow-pro');
            }
        }
        
        // Check booking window
        if ($schedule && !$this->isWithinBookingWindow($schedule)) {
            $canBook = false;
            $reasons[] = __('Booking window for this class has closed.', 'classflow-pro');
        }
        
        return [
            'can_book' => $canBook,
            'reasons' => $reasons,
            'available_spots' => $availableSpots,
        ];
    }

    public function rescheduleBooking(int $bookingId, int $newScheduleId): Booking {
        $booking = $this->bookingRepository->find($bookingId);
        if (!$booking) {
            throw new \RuntimeException(__('Booking not found.', 'classflow-pro'));
        }
        
        if (!$booking->canBeModified()) {
            throw new \RuntimeException(__('This booking cannot be rescheduled.', 'classflow-pro'));
        }
        
        // Validate new schedule
        $newSchedule = $this->scheduleRepository->find($newScheduleId);
        if (!$newSchedule) {
            throw new \RuntimeException(__('New schedule not found.', 'classflow-pro'));
        }
        
        // Check if student can book the new schedule
        $canBook = $this->canStudentBook($booking->getStudentId(), $newScheduleId);
        if (!$canBook['can_book']) {
            throw new \RuntimeException(implode(' ', $canBook['reasons']));
        }
        
        // Update booking
        $oldScheduleId = $booking->getScheduleId();
        $booking->setScheduleId($newScheduleId);
        
        // Recalculate amount if needed
        $newAmount = $this->calculateBookingAmount($newSchedule);
        if ($newAmount !== $booking->getAmount()) {
            $booking->setAmount($newAmount);
            // Handle payment difference
            if ($booking->isPaymentCompleted()) {
                // Process additional payment or refund
                $this->container->get('payment_service')->adjustPayment($booking, $newAmount);
            }
        }
        
        $booking = $this->bookingRepository->save($booking);
        
        // Send reschedule notification
        $this->container->get('notification_service')->sendBookingRescheduled($booking);
        
        // Process waitlist for old schedule
        $this->processWaitlist($oldScheduleId);
        
        // Trigger action
        do_action('classflow_pro_booking_rescheduled', $booking, $oldScheduleId);
        
        return $booking;
    }

    public function cleanupExpiredBookings(): int {
        $expiryMinutes = (int) $this->settings->get('booking.pending_expiry_minutes', 30);
        $count = $this->bookingRepository->cleanupExpiredPendingBookings($expiryMinutes);
        
        if ($count > 0) {
            do_action('classflow_pro_expired_bookings_cleaned', $count);
        }
        
        return $count;
    }

    private function validateBookingData(array $data): void {
        $errors = [];
        
        if (empty($data['schedule_id'])) {
            $errors[] = __('Schedule ID is required.', 'classflow-pro');
        }
        
        if (empty($data['student_id'])) {
            $errors[] = __('Student ID is required.', 'classflow-pro');
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }
    }

    private function calculateBookingAmount($schedule): float {
        // Check for price override on schedule
        $priceOverride = $schedule->getPriceOverride();
        if ($priceOverride !== null) {
            return $priceOverride;
        }
        
        // Get class price
        $classService = $this->container->get('class_service');
        return $classService->getClassPrice($schedule->getClassId());
    }

    private function canCancelBooking(Booking $booking, $schedule): bool {
        $cancellationHours = (int) $this->settings->get('booking.cancellation_hours', 24);
        
        if ($cancellationHours === 0) {
            return true; // No cancellation policy
        }
        
        $now = new \DateTime();
        $classStart = $schedule->getStartTime();
        $hoursUntilClass = ($classStart->getTimestamp() - $now->getTimestamp()) / 3600;
        
        return $hoursUntilClass >= $cancellationHours;
    }

    private function isWithinBookingWindow($schedule): bool {
        $minBookingHours = (int) $this->settings->get('booking.min_booking_hours', 24);
        $advanceBookingDays = (int) $this->settings->get('booking.advance_booking_days', 30);
        
        $now = new \DateTime();
        $classStart = $schedule->getStartTime();
        
        // Check minimum booking time
        $hoursUntilClass = ($classStart->getTimestamp() - $now->getTimestamp()) / 3600;
        if ($hoursUntilClass < $minBookingHours) {
            return false;
        }
        
        // Check maximum advance booking
        $daysUntilClass = $hoursUntilClass / 24;
        if ($daysUntilClass > $advanceBookingDays) {
            return false;
        }
        
        return true;
    }

    private function processWaitlist(int $scheduleId): void {
        $availableSpots = $this->scheduleRepository->getAvailableSpots($scheduleId);
        
        if ($availableSpots > 0) {
            // Get next person on waitlist
            $waitlistService = $this->container->get('waitlist_service');
            $waitlistService->processNextInLine($scheduleId);
        }
    }

    private function handleStatusChange(Booking $booking, string $oldStatus, string $newStatus): void {
        // Handle specific status transitions
        switch ($newStatus) {
            case 'confirmed':
                if ($oldStatus === 'pending') {
                    $this->container->get('notification_service')->sendBookingConfirmation($booking);
                }
                break;
                
            case 'completed':
                // Mark attendance as present
                $this->container->get('attendance_service')->markPresent($booking->getId());
                break;
                
            case 'no_show':
                // Mark attendance as absent
                $this->container->get('attendance_service')->markAbsent($booking->getId());
                break;
        }
    }
}