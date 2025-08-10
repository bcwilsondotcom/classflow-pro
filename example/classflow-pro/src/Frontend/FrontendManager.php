<?php
declare(strict_types=1);

namespace ClassFlowPro\Frontend;

use ClassFlowPro\Services\Container;

class FrontendManager {
    private Container $container;
    private array $shortcodes;

    public function __construct(Container $container) {
        $this->container = $container;
        
        // Initialize components
        $this->initializeComponents();
        
        // Register hooks
        $this->registerHooks();
    }

    private function initializeComponents(): void {
        // Initialize shortcodes
        $this->shortcodes = [
            'classflow_classes' => new Shortcodes\ClassesShortcode($this->container),
            'classflow_schedule' => new Shortcodes\ScheduleShortcode($this->container),
            'classflow_calendar' => new Shortcodes\CalendarShortcode($this->container),
            'classflow_booking_form' => new Shortcodes\BookingFormShortcode($this->container),
            'classflow_private_booking' => new Shortcodes\PrivateBookingShortcode($this->container),
            'classflow_my_bookings' => new Shortcodes\MyBookingsShortcode($this->container),
            'classflow_instructor_schedule' => new Shortcodes\InstructorScheduleShortcode($this->container),
        ];
        
        // Register shortcodes
        foreach ($this->shortcodes as $tag => $shortcode) {
            add_shortcode($tag, [$shortcode, 'render']);
        }
    }

    private function registerHooks(): void {
        // Frontend scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_classflow_get_schedule_details', [$this, 'ajaxGetScheduleDetails']);
        add_action('wp_ajax_nopriv_classflow_get_schedule_details', [$this, 'ajaxGetScheduleDetails']);
        
        add_action('wp_ajax_classflow_create_booking', [$this, 'ajaxCreateBooking']);
        add_action('wp_ajax_nopriv_classflow_create_booking', [$this, 'ajaxCreateBooking']);
        
        add_action('wp_ajax_classflow_cancel_booking', [$this, 'ajaxCancelBooking']);
        
        // Private booking AJAX handlers
        add_action('wp_ajax_classflow_get_class_instructors', [$this, 'ajaxGetClassInstructors']);
        add_action('wp_ajax_classflow_get_available_slots', [$this, 'ajaxGetAvailableSlots']);
        add_action('wp_ajax_classflow_book_private_session', [$this, 'ajaxBookPrivateSession']);
        
        // Rewrite rules
        add_action('init', [$this, 'addRewriteRules']);
        
        // Query vars
        add_filter('query_vars', [$this, 'addQueryVars']);
        
        // Template redirect
        add_action('template_redirect', [$this, 'handleTemplateRedirect']);
    }

    public function enqueueFrontendAssets(): void {
        // Core styles
        wp_enqueue_style(
            'classflow-pro-frontend',
            CLASSFLOW_PRO_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            CLASSFLOW_PRO_VERSION
        );
        
        // Core scripts
        wp_enqueue_script(
            'classflow-pro-frontend',
            CLASSFLOW_PRO_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            CLASSFLOW_PRO_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('classflow-pro-frontend', 'classflowProFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('classflow_pro_frontend'),
            'isLoggedIn' => is_user_logged_in(),
            'stripePublishableKey' => $this->container->get('payment_service')->getStripePublishableKey(),
            'i18n' => [
                'loading' => __('Loading...', 'classflow-pro'),
                'error' => __('An error occurred. Please try again.', 'classflow-pro'),
                'confirmCancel' => __('Are you sure you want to cancel this booking?', 'classflow-pro'),
                'bookingSuccess' => __('Your booking has been confirmed!', 'classflow-pro'),
                'paymentRequired' => __('Payment is required to complete your booking.', 'classflow-pro'),
            ],
        ]);
        
        // Conditionally load Stripe
        if ($this->shouldLoadStripe()) {
            wp_enqueue_script(
                'stripe-js',
                'https://js.stripe.com/v3/',
                [],
                null,
                true
            );
        }
    }

    private function shouldLoadStripe(): bool {
        // Check if we're on a page that needs Stripe
        return is_page() && (
            has_shortcode(get_post()->post_content, 'classflow_booking_form') ||
            has_shortcode(get_post()->post_content, 'classflow_classes')
        );
    }

    public function addRewriteRules(): void {
        // Class detail page
        add_rewrite_rule(
            'classes/([^/]+)/?$',
            'index.php?classflow_class=$matches[1]',
            'top'
        );
        
        // Booking confirmation page
        add_rewrite_rule(
            'booking/([^/]+)/?$',
            'index.php?classflow_booking=$matches[1]',
            'top'
        );
    }

    public function addQueryVars(array $vars): array {
        $vars[] = 'classflow_class';
        $vars[] = 'classflow_booking';
        return $vars;
    }

    public function handleTemplateRedirect(): void {
        global $wp_query;
        
        // Handle class detail page
        if (get_query_var('classflow_class')) {
            $this->loadClassTemplate();
            exit;
        }
        
        // Handle booking confirmation page
        if (get_query_var('classflow_booking')) {
            $this->loadBookingTemplate();
            exit;
        }
    }

    private function loadClassTemplate(): void {
        $classSlug = get_query_var('classflow_class');
        $class = $this->container->get('class_service')->getClassBySlug($classSlug);
        
        if (!$class) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            exit;
        }
        
        // Set up data
        $schedules = $this->container->get('schedule_repository')->findByClass($class->getId(), true);
        
        // Load template
        $template = $this->getTemplate('single-class');
        
        if ($template) {
            global $classflow_class, $classflow_schedules;
            $classflow_class = $class;
            $classflow_schedules = $schedules;
            
            include $template;
        }
    }

    private function loadBookingTemplate(): void {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }
        
        $bookingCode = get_query_var('classflow_booking');
        $booking = $this->container->get('booking_service')->getBookingByCode($bookingCode);
        
        if (!$booking || $booking->getStudentId() !== get_current_user_id()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            get_template_part(404);
            exit;
        }
        
        // Load template
        $template = $this->getTemplate('booking-details');
        
        if ($template) {
            global $classflow_booking;
            $classflow_booking = $booking;
            
            include $template;
        }
    }

    private function getTemplate(string $template): ?string {
        // Check theme override first
        $themeTemplate = locate_template(['classflow-pro/' . $template . '.php']);
        if ($themeTemplate) {
            return $themeTemplate;
        }
        
        // Use plugin template
        $pluginTemplate = CLASSFLOW_PRO_PLUGIN_DIR . 'templates/frontend/' . $template . '.php';
        if (file_exists($pluginTemplate)) {
            return $pluginTemplate;
        }
        
        return null;
    }

    public function ajaxGetScheduleDetails(): void {
        check_ajax_referer('classflow_pro_frontend', 'nonce');
        
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
        if (!$scheduleId) {
            wp_send_json_error(['message' => __('Invalid schedule ID', 'classflow-pro')]);
        }
        
        $schedule = $this->container->get('schedule_repository')->find($scheduleId);
        if (!$schedule) {
            wp_send_json_error(['message' => __('Schedule not found', 'classflow-pro')]);
        }
        
        $class = $this->container->get('class_repository')->find($schedule->getClassId());
        $instructor = get_userdata($schedule->getInstructorId());
        $availableSpots = $this->container->get('schedule_repository')->getAvailableSpots($scheduleId);
        
        // Check if user can book
        $canBook = ['can_book' => true, 'reasons' => []];
        if (is_user_logged_in()) {
            $studentId = get_current_user_id();
            $canBook = $this->container->get('booking_service')->canStudentBook($studentId, $scheduleId);
        }
        
        wp_send_json_success([
            'schedule' => [
                'id' => $schedule->getId(),
                'start_time' => $schedule->getStartTime()->format('c'),
                'end_time' => $schedule->getEndTime()->format('c'),
                'formatted_date' => $schedule->getFormattedDateRange(),
            ],
            'class' => [
                'id' => $class->getId(),
                'name' => $class->getName(),
                'description' => $class->getDescription(),
                'duration' => $class->getDurationFormatted(),
                'price' => $class->getFormattedPrice(),
            ],
            'instructor' => [
                'name' => $instructor ? $instructor->display_name : '',
            ],
            'availability' => [
                'total_capacity' => $class->getCapacity(),
                'available_spots' => $availableSpots,
                'is_full' => $availableSpots === 0,
            ],
            'can_book' => $canBook['can_book'],
            'booking_issues' => $canBook['reasons'],
        ]);
    }

    public function ajaxCreateBooking(): void {
        check_ajax_referer('classflow_pro_frontend', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to book a class', 'classflow-pro')]);
        }
        
        $scheduleId = (int) ($_POST['schedule_id'] ?? 0);
        if (!$scheduleId) {
            wp_send_json_error(['message' => __('Invalid schedule ID', 'classflow-pro')]);
        }
        
        try {
            $bookingData = [
                'schedule_id' => $scheduleId,
                'student_id' => get_current_user_id(),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            ];
            
            $booking = $this->container->get('booking_service')->createBooking($bookingData);
            
            // Check if payment is required
            $paymentRequired = $this->container->get('payment_service')->isPaymentRequired($booking);
            
            if ($paymentRequired) {
                // Create payment intent
                $paymentData = $this->container->get('payment_service')->createPaymentIntent($booking);
                
                wp_send_json_success([
                    'booking_id' => $booking->getId(),
                    'booking_code' => $booking->getBookingCode(),
                    'requires_payment' => true,
                    'payment' => $paymentData,
                ]);
            } else {
                // No payment required, confirm booking
                $this->container->get('booking_service')->confirmBooking($booking->getId());
                
                wp_send_json_success([
                    'booking_id' => $booking->getId(),
                    'booking_code' => $booking->getBookingCode(),
                    'requires_payment' => false,
                    'redirect_url' => home_url('/booking/' . $booking->getBookingCode()),
                ]);
            }
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajaxCancelBooking(): void {
        check_ajax_referer('classflow_pro_frontend', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to cancel a booking', 'classflow-pro')]);
        }
        
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        if (!$bookingId) {
            wp_send_json_error(['message' => __('Invalid booking ID', 'classflow-pro')]);
        }
        
        try {
            $booking = $this->container->get('booking_repository')->find($bookingId);
            
            if (!$booking || $booking->getStudentId() !== get_current_user_id()) {
                wp_send_json_error(['message' => __('Booking not found', 'classflow-pro')]);
            }
            
            $reason = sanitize_textarea_field($_POST['reason'] ?? '');
            $this->container->get('booking_service')->cancelBooking($bookingId, $reason);
            
            wp_send_json_success([
                'message' => __('Your booking has been cancelled', 'classflow-pro'),
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajaxGetClassInstructors(): void {
        check_ajax_referer('classflow_pro_frontend', 'nonce');
        
        $classId = (int) ($_POST['class_id'] ?? 0);
        if (!$classId) {
            wp_send_json_error(['message' => __('Invalid class ID', 'classflow-pro')]);
        }
        
        // Get all active instructors
        $instructorRepo = $this->container->get('instructor_repository');
        $instructors = $instructorRepo->findAll(['status' => 'active']);
        
        // TODO: Filter instructors who can teach this class
        // For now, show all instructors
        
        ob_start();
        ?>
        <div class="instructor-grid">
            <?php foreach ($instructors as $instructor): ?>
                <div class="instructor-card" data-instructor-id="<?php echo esc_attr($instructor->ID); ?>">
                    <h4><?php echo esc_html($instructor->display_name); ?></h4>
                    <?php 
                    $bio = get_user_meta($instructor->ID, 'instructor_bio', true);
                    if ($bio): ?>
                        <div class="bio">
                            <?php echo wp_trim_words(esc_html($bio), 20); ?>
                        </div>
                    <?php endif; ?>
                    <?php
                    $specialties = get_user_meta($instructor->ID, 'instructor_specialties', true);
                    if ($specialties): ?>
                        <div class="specialty">
                            <?php echo esc_html($specialties); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        wp_send_json_success(['html' => ob_get_clean()]);
    }

    public function ajaxGetAvailableSlots(): void {
        check_ajax_referer('classflow_pro_frontend', 'nonce');
        
        $instructorId = (int) ($_POST['instructor_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');
        $classId = (int) ($_POST['class_id'] ?? 0);
        
        if (!$instructorId || !$date || !$classId) {
            wp_send_json_error(['message' => __('Missing required parameters', 'classflow-pro')]);
        }
        
        try {
            $dateObj = new \DateTime($date);
            $class = $this->container->get('class_repository')->find($classId);
            
            if (!$class) {
                wp_send_json_error(['message' => __('Class not found', 'classflow-pro')]);
            }
            
            $instructorRepo = $this->container->get('instructor_repository');
            $availableSlots = $instructorRepo->getAvailableSlots($instructorId, $dateObj, $class->getDuration());
            
            ob_start();
            ?>
            <h4><?php echo esc_html__('Available Times', 'classflow-pro'); ?></h4>
            <?php if (empty($availableSlots)): ?>
                <p><?php echo esc_html__('No available time slots for this date.', 'classflow-pro'); ?></p>
            <?php else: ?>
                <div class="time-slots">
                    <?php foreach ($availableSlots as $slot): ?>
                        <div class="time-slot" data-time="<?php echo esc_attr($slot['start']->format('H:i')); ?>">
                            <?php echo esc_html($slot['start']->format('g:i A')); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php
            
            wp_send_json_success(['html' => ob_get_clean()]);
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajaxBookPrivateSession(): void {
        check_ajax_referer('classflow_pro_frontend', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in to book a session', 'classflow-pro')]);
        }
        
        $classId = (int) ($_POST['class_id'] ?? 0);
        $instructorId = (int) ($_POST['instructor_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');
        $time = sanitize_text_field($_POST['time'] ?? '');
        $bookingType = sanitize_text_field($_POST['booking_type'] ?? 'private');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        if (!$classId || !$instructorId || !$date || !$time) {
            wp_send_json_error(['message' => __('Missing required booking information', 'classflow-pro')]);
        }
        
        try {
            $class = $this->container->get('class_repository')->find($classId);
            if (!$class) {
                wp_send_json_error(['message' => __('Class not found', 'classflow-pro')]);
            }
            
            // Create date/time objects
            $startDateTime = new \DateTime($date . ' ' . $time);
            $endDateTime = clone $startDateTime;
            $endDateTime->modify('+' . $class->getDuration() . ' minutes');
            
            // Check instructor availability
            $scheduleRepo = $this->container->get('schedule_repository');
            if (!$scheduleRepo->isTimeSlotAvailable($instructorId, $startDateTime, $endDateTime)) {
                wp_send_json_error(['message' => __('This time slot is no longer available', 'classflow-pro')]);
            }
            
            // Create the schedule
            $schedule = new \ClassFlowPro\Models\Entities\Schedule(
                $classId,
                $instructorId,
                $startDateTime,
                $endDateTime
            );
            
            $schedule->setBookingType($bookingType);
            $schedule->setCapacityOverride($bookingType === 'private' ? 1 : 3);
            
            $savedSchedule = $scheduleRepo->save($schedule);
            
            // Create the booking
            $bookingData = [
                'schedule_id' => $savedSchedule->getId(),
                'student_id' => get_current_user_id(),
                'notes' => $notes,
            ];
            
            $booking = $this->container->get('booking_service')->createBooking($bookingData);
            
            // Check if payment is required
            $paymentRequired = $this->container->get('payment_service')->isPaymentRequired($booking);
            
            if ($paymentRequired) {
                // Create payment intent
                $paymentData = $this->container->get('payment_service')->createPaymentIntent($booking);
                
                wp_send_json_success([
                    'booking_id' => $booking->getId(),
                    'booking_code' => $booking->getBookingCode(),
                    'requires_payment' => true,
                    'payment' => $paymentData,
                    'message' => __('Booking created. Please complete payment to confirm.', 'classflow-pro'),
                ]);
            } else {
                // No payment required, confirm booking
                $this->container->get('booking_service')->confirmBooking($booking->getId());
                
                wp_send_json_success([
                    'booking_id' => $booking->getId(),
                    'booking_code' => $booking->getBookingCode(),
                    'requires_payment' => false,
                    'redirect_url' => home_url('/booking/' . $booking->getBookingCode()),
                    'message' => __('Your private session has been booked successfully!', 'classflow-pro'),
                ]);
            }
            
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}