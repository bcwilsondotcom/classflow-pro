<?php
declare(strict_types=1);

namespace ClassFlowPro\Services;

use ClassFlowPro\Models\Entities\Booking;
use ClassFlowPro\Models\Entities\Schedule;
use ClassFlowPro\Core\Settings;

class NotificationService {
    private Container $container;
    private Settings $settings;
    private array $emailQueue = [];

    public function __construct(Container $container) {
        $this->container = $container;
        $this->settings = new Settings();
    }

    public function sendBookingConfirmation(Booking $booking): bool {
        if (!$this->isNotificationEnabled('booking_confirmation')) {
            return false;
        }

        $data = $this->prepareBookingData($booking);
        
        return $this->sendEmail(
            $data['student_email'],
            __('Booking Confirmation', 'classflow-pro') . ' - ' . $data['class_name'],
            'booking-confirmation',
            $data
        );
    }

    public function sendBookingCancellation(Booking $booking): bool {
        if (!$this->isNotificationEnabled('booking_cancellation')) {
            return false;
        }

        $data = $this->prepareBookingData($booking);
        
        return $this->sendEmail(
            $data['student_email'],
            __('Booking Cancelled', 'classflow-pro') . ' - ' . $data['class_name'],
            'booking-cancellation',
            $data
        );
    }

    public function sendBookingRescheduled(Booking $booking): bool {
        if (!$this->isNotificationEnabled('booking_rescheduled')) {
            return false;
        }

        $data = $this->prepareBookingData($booking);
        
        return $this->sendEmail(
            $data['student_email'],
            __('Booking Rescheduled', 'classflow-pro') . ' - ' . $data['class_name'],
            'booking-rescheduled',
            $data
        );
    }

    public function sendPaymentConfirmation(Booking $booking): bool {
        if (!$this->isNotificationEnabled('payment_confirmation')) {
            return false;
        }

        $data = $this->prepareBookingData($booking);
        
        return $this->sendEmail(
            $data['student_email'],
            __('Payment Confirmation', 'classflow-pro') . ' - ' . $data['class_name'],
            'payment-confirmation',
            $data
        );
    }

    public function sendPaymentFailed(Booking $booking): bool {
        if (!$this->isNotificationEnabled('payment_failed')) {
            return false;
        }

        $data = $this->prepareBookingData($booking);
        
        return $this->sendEmail(
            $data['student_email'],
            __('Payment Failed', 'classflow-pro') . ' - ' . $data['class_name'],
            'payment-failed',
            $data
        );
    }

    public function sendRefundConfirmation(Booking $booking, float $amount): bool {
        if (!$this->isNotificationEnabled('refund_confirmation')) {
            return false;
        }

        $data = $this->prepareBookingData($booking);
        $data['refund_amount'] = $amount;
        $data['formatted_refund_amount'] = '$' . number_format($amount, 2);
        
        return $this->sendEmail(
            $data['student_email'],
            __('Refund Processed', 'classflow-pro') . ' - ' . $data['class_name'],
            'refund-confirmation',
            $data
        );
    }

    public function sendUpcomingClassReminders(): int {
        if (!$this->isNotificationEnabled('class_reminder')) {
            return 0;
        }

        $reminderHours = (int) $this->settings->get('notifications.reminder_hours', 24);
        $reminderTime = new \DateTime();
        $reminderTime->add(new \DateInterval('PT' . $reminderHours . 'H'));
        
        // Get upcoming schedules
        $scheduleRepo = $this->container->get('schedule_repository');
        $bookingRepo = $this->container->get('booking_repository');
        
        $startTime = new \DateTime();
        $endTime = clone $reminderTime;
        $endTime->add(new \DateInterval('PT1H'));
        
        $schedules = $scheduleRepo->findByDateRange($startTime, $endTime, ['status' => 'scheduled']);
        
        $sentCount = 0;
        foreach ($schedules as $schedule) {
            // Get confirmed bookings for this schedule
            $bookings = $bookingRepo->findBySchedule($schedule->getId(), ['status' => 'confirmed']);
            
            foreach ($bookings as $booking) {
                if ($this->sendClassReminder($booking, $schedule)) {
                    $sentCount++;
                }
            }
        }
        
        return $sentCount;
    }

    public function sendClassReminder(Booking $booking, Schedule $schedule): bool {
        $data = $this->prepareBookingData($booking);
        
        // Calculate hours until class
        $now = new \DateTime();
        $hoursUntil = round(($schedule->getStartTime()->getTimestamp() - $now->getTimestamp()) / 3600, 1);
        $data['hours_until_class'] = $hoursUntil;
        
        return $this->sendEmail(
            $data['student_email'],
            __('Class Reminder', 'classflow-pro') . ' - ' . $data['class_name'],
            'class-reminder',
            $data
        );
    }

    public function sendWaitlistAvailable(int $studentId, Schedule $schedule): bool {
        if (!$this->isNotificationEnabled('waitlist_available')) {
            return false;
        }

        $student = get_userdata($studentId);
        if (!$student) {
            return false;
        }

        $class = $this->container->get('class_repository')->find($schedule->getClassId());
        $instructor = get_userdata($schedule->getInstructorId());
        
        $data = [
            'student_name' => $student->display_name,
            'student_email' => $student->user_email,
            'class_name' => $class->getName(),
            'class_date' => $schedule->getStartTime()->format('F j, Y'),
            'class_time' => $schedule->getStartTime()->format('g:i A'),
            'instructor_name' => $instructor ? $instructor->display_name : '',
            'booking_url' => $this->getBookingUrl($schedule),
        ];
        
        return $this->sendEmail(
            $data['student_email'],
            __('Spot Available', 'classflow-pro') . ' - ' . $data['class_name'],
            'waitlist-available',
            $data
        );
    }

    public function sendInstructorNewBooking(Booking $booking): bool {
        if (!$this->isNotificationEnabled('instructor_new_booking')) {
            return false;
        }

        $data = $this->prepareBookingData($booking);
        $schedule = $this->container->get('schedule_repository')->find($booking->getScheduleId());
        $instructor = get_userdata($schedule->getInstructorId());
        
        if (!$instructor) {
            return false;
        }

        return $this->sendEmail(
            $instructor->user_email,
            __('New Booking', 'classflow-pro') . ' - ' . $data['class_name'],
            'instructor-new-booking',
            $data
        );
    }

    public function sendInstructorCancellation(Booking $booking): bool {
        if (!$this->isNotificationEnabled('instructor_cancellation')) {
            return false;
        }

        $data = $this->prepareBookingData($booking);
        $schedule = $this->container->get('schedule_repository')->find($booking->getScheduleId());
        $instructor = get_userdata($schedule->getInstructorId());
        
        if (!$instructor) {
            return false;
        }

        return $this->sendEmail(
            $instructor->user_email,
            __('Booking Cancelled', 'classflow-pro') . ' - ' . $data['class_name'],
            'instructor-booking-cancelled',
            $data
        );
    }

    public function sendAdminNotification(string $subject, string $message, array $data = []): bool {
        $adminEmail = $this->settings->get('email.admin_email', get_option('admin_email'));
        
        return $this->sendEmail(
            $adminEmail,
            $subject,
            'admin-notification',
            array_merge(['message' => $message], $data)
        );
    }

    private function prepareBookingData(Booking $booking): array {
        $schedule = $this->container->get('schedule_repository')->find($booking->getScheduleId());
        $class = $this->container->get('class_repository')->find($schedule->getClassId());
        $student = get_userdata($booking->getStudentId());
        $instructor = get_userdata($schedule->getInstructorId());
        $location = null;
        
        if ($schedule->getLocationId()) {
            $locationRepo = $this->container->get('location_repository');
            $location = $locationRepo->find($schedule->getLocationId());
        }

        return [
            'booking_code' => $booking->getBookingCode(),
            'booking_status' => $booking->getStatus(),
            'booking_amount' => $booking->getFormattedAmount(),
            'student_name' => $student ? $student->display_name : '',
            'student_email' => $student ? $student->user_email : '',
            'class_name' => $class->getName(),
            'class_description' => $class->getDescription(),
            'class_duration' => $class->getDurationFormatted(),
            'class_date' => $schedule->getStartTime()->format('F j, Y'),
            'class_time' => $schedule->getStartTime()->format('g:i A'),
            'class_end_time' => $schedule->getEndTime()->format('g:i A'),
            'instructor_name' => $instructor ? $instructor->display_name : '',
            'location_name' => $location ? $location->getName() : __('Online', 'classflow-pro'),
            'location_address' => $location ? $location->getAddress() : '',
            'booking_url' => $this->getBookingDetailsUrl($booking),
            'cancel_url' => $this->getCancelBookingUrl($booking),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
        ];
    }

    private function sendEmail(string $to, string $subject, string $template, array $data): bool {
        // Get email template
        $templatePath = $this->getTemplatePath($template);
        if (!file_exists($templatePath)) {
            return false;
        }

        // Prepare email content
        ob_start();
        extract($data);
        include $templatePath;
        $message = ob_get_clean();

        // Email headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->settings->get('email.from_name', get_bloginfo('name')) . 
            ' <' . $this->settings->get('email.from_email', get_option('admin_email')) . '>',
        ];

        // Send email
        $sent = wp_mail($to, $subject, $message, $headers);

        // Log email
        $this->logEmail($to, $subject, $template, $sent);

        return $sent;
    }

    private function getTemplatePath(string $template): string {
        // Check theme override first
        $themeTemplate = get_stylesheet_directory() . '/classflow-pro/emails/' . $template . '.php';
        if (file_exists($themeTemplate)) {
            return $themeTemplate;
        }

        // Use plugin template
        return CLASSFLOW_PRO_PLUGIN_DIR . 'templates/emails/' . $template . '.php';
    }

    private function isNotificationEnabled(string $type): bool {
        if (!$this->settings->get('email.enable_notifications', true)) {
            return false;
        }

        return $this->settings->get('notifications.' . $type . '.enabled', true);
    }

    private function getBookingUrl(Schedule $schedule): string {
        return add_query_arg([
            'action' => 'book',
            'schedule_id' => $schedule->getId(),
        ], home_url('/classes/'));
    }

    private function getBookingDetailsUrl(Booking $booking): string {
        return add_query_arg([
            'booking_code' => $booking->getBookingCode(),
        ], home_url('/my-account/bookings/'));
    }

    private function getCancelBookingUrl(Booking $booking): string {
        return add_query_arg([
            'action' => 'cancel',
            'booking_code' => $booking->getBookingCode(),
            'nonce' => wp_create_nonce('cancel_booking_' . $booking->getId()),
        ], home_url('/my-account/bookings/'));
    }

    private function logEmail(string $to, string $subject, string $template, bool $sent): void {
        $this->container->get('database')->insert('email_logs', [
            'recipient_email' => $to,
            'subject' => $subject,
            'template' => $template,
            'status' => $sent ? 'sent' : 'failed',
            'error_message' => $sent ? null : 'Failed to send email',
            'meta' => json_encode([
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id(),
            ]),
        ]);
    }

    public function sendTestEmail(string $to, string $template): bool {
        // Create dummy data for testing
        $testData = [
            'booking_code' => 'TEST123',
            'student_name' => 'Test Student',
            'student_email' => $to,
            'class_name' => 'Test Class',
            'class_date' => date('F j, Y'),
            'class_time' => '10:00 AM',
            'instructor_name' => 'Test Instructor',
            'location_name' => 'Test Location',
            'booking_amount' => '$50.00',
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
        ];

        return $this->sendEmail(
            $to,
            __('Test Email', 'classflow-pro') . ' - ' . $template,
            $template,
            $testData
        );
    }

    public function processEmailQueue(): int {
        if (empty($this->emailQueue)) {
            return 0;
        }

        $sent = 0;
        foreach ($this->emailQueue as $email) {
            if ($this->sendEmail(
                $email['to'],
                $email['subject'],
                $email['template'],
                $email['data']
            )) {
                $sent++;
            }
        }

        $this->emailQueue = [];
        return $sent;
    }

    public function queueEmail(string $to, string $subject, string $template, array $data): void {
        $this->emailQueue[] = [
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
            'data' => $data,
        ];
    }
}