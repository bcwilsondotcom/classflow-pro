<?php
declare(strict_types=1);

namespace ClassFlowPro\API;

use ClassFlowPro\Services\Container;

class RestApiManager {
    private Container $container;
    private string $namespace = 'classflow-pro/v1';

    public function __construct(Container $container) {
        $this->container = $container;
        
        // Register REST API routes
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void {
        // Classes endpoints
        register_rest_route($this->namespace, '/classes', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getClasses'],
                'permission_callback' => '__return_true',
                'args' => $this->getClassesArgs(),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createClass'],
                'permission_callback' => [$this, 'canManageClasses'],
                'args' => $this->createClassArgs(),
            ],
        ]);

        register_rest_route($this->namespace, '/classes/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getClass'],
                'permission_callback' => '__return_true',
                'args' => [
                    'id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ],
                ],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateClass'],
                'permission_callback' => [$this, 'canManageClasses'],
                'args' => $this->updateClassArgs(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteClass'],
                'permission_callback' => [$this, 'canManageClasses'],
            ],
        ]);

        // Schedules endpoints
        register_rest_route($this->namespace, '/schedules', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSchedules'],
                'permission_callback' => '__return_true',
                'args' => $this->getSchedulesArgs(),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createSchedule'],
                'permission_callback' => [$this, 'canManageClasses'],
                'args' => $this->createScheduleArgs(),
            ],
        ]);

        register_rest_route($this->namespace, '/schedules/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSchedule'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateSchedule'],
                'permission_callback' => [$this, 'canManageClasses'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteSchedule'],
                'permission_callback' => [$this, 'canManageClasses'],
            ],
        ]);

        // Bookings endpoints
        register_rest_route($this->namespace, '/bookings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getBookings'],
                'permission_callback' => [$this, 'canViewBookings'],
                'args' => $this->getBookingsArgs(),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createBooking'],
                'permission_callback' => [$this, 'isAuthenticated'],
                'args' => $this->createBookingArgs(),
            ],
        ]);

        register_rest_route($this->namespace, '/bookings/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getBooking'],
                'permission_callback' => [$this, 'canViewBooking'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateBooking'],
                'permission_callback' => [$this, 'canManageBooking'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'cancelBooking'],
                'permission_callback' => [$this, 'canManageBooking'],
            ],
        ]);

        // Payment endpoints
        register_rest_route($this->namespace, '/payments/create-intent', [
            'methods' => 'POST',
            'callback' => [$this, 'createPaymentIntent'],
            'permission_callback' => [$this, 'isAuthenticated'],
            'args' => [
                'booking_id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/payments/confirm', [
            'methods' => 'POST',
            'callback' => [$this, 'confirmPayment'],
            'permission_callback' => [$this, 'isAuthenticated'],
            'args' => [
                'payment_intent_id' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Webhook endpoint
        register_rest_route($this->namespace, '/webhooks/stripe', [
            'methods' => 'POST',
            'callback' => [$this, 'handleStripeWebhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    // Classes endpoints
    public function getClasses(\WP_REST_Request $request): \WP_REST_Response {
        $filters = [];
        
        if ($request->get_param('status')) {
            $filters['status'] = $request->get_param('status');
        }
        
        if ($request->get_param('category_id')) {
            $filters['category_id'] = (int) $request->get_param('category_id');
        }
        
        $page = (int) $request->get_param('page') ?: 1;
        $perPage = (int) $request->get_param('per_page') ?: 10;
        $orderBy = $request->get_param('orderby') ?: 'name ASC';
        
        $result = $this->container->get('class_repository')->paginate($page, $perPage, $filters, $orderBy);
        
        $classes = array_map(function($class) {
            return $this->formatClassResponse($class);
        }, $result['items']);
        
        return new \WP_REST_Response([
            'classes' => $classes,
            'pagination' => [
                'total' => $result['total'],
                'total_pages' => $result['total_pages'],
                'current_page' => $result['page'],
                'per_page' => $result['per_page'],
            ],
        ]);
    }

    public function getClass(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $class = $this->container->get('class_repository')->find($id);
        
        if (!$class) {
            return new \WP_REST_Response(['error' => 'Class not found'], 404);
        }
        
        return new \WP_REST_Response($this->formatClassResponse($class));
    }

    public function createClass(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $data = $request->get_json_params();
            $class = $this->container->get('class_service')->createClass($data);
            
            return new \WP_REST_Response(
                $this->formatClassResponse($class),
                201
            );
        } catch (\Exception $e) {
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    public function updateClass(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $id = (int) $request->get_param('id');
            $data = $request->get_json_params();
            
            $class = $this->container->get('class_service')->updateClass($id, $data);
            
            return new \WP_REST_Response($this->formatClassResponse($class));
        } catch (\Exception $e) {
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    public function deleteClass(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $id = (int) $request->get_param('id');
            $result = $this->container->get('class_service')->deleteClass($id);
            
            if ($result) {
                return new \WP_REST_Response(null, 204);
            }
            
            return new \WP_REST_Response(['error' => 'Failed to delete class'], 400);
        } catch (\Exception $e) {
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    // Schedules endpoints
    public function getSchedules(\WP_REST_Request $request): \WP_REST_Response {
        $filters = [];
        
        if ($request->get_param('class_id')) {
            $filters['class_id'] = (int) $request->get_param('class_id');
        }
        
        if ($request->get_param('instructor_id')) {
            $filters['instructor_id'] = (int) $request->get_param('instructor_id');
        }
        
        if ($request->get_param('start_date') && $request->get_param('end_date')) {
            $startDate = new \DateTime($request->get_param('start_date'));
            $endDate = new \DateTime($request->get_param('end_date'));
            
            $schedules = $this->container->get('schedule_repository')->findByDateRange(
                $startDate,
                $endDate,
                $filters
            );
        } else {
            $schedules = $this->container->get('schedule_repository')->findAll($filters);
        }
        
        $formattedSchedules = array_map(function($schedule) {
            return $this->formatScheduleResponse($schedule);
        }, $schedules);
        
        return new \WP_REST_Response(['schedules' => $formattedSchedules]);
    }

    public function getSchedule(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $schedule = $this->container->get('schedule_repository')->find($id);
        
        if (!$schedule) {
            return new \WP_REST_Response(['error' => 'Schedule not found'], 404);
        }
        
        return new \WP_REST_Response($this->formatScheduleResponse($schedule));
    }

    public function createSchedule(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $data = $request->get_json_params();
            
            // Convert date strings to DateTime objects
            $data['start_time'] = new \DateTime($data['start_time']);
            $data['end_time'] = new \DateTime($data['end_time']);
            
            $schedule = $this->container->get('schedule_service')->createSchedule($data);
            
            return new \WP_REST_Response(
                $this->formatScheduleResponse($schedule),
                201
            );
        } catch (\Exception $e) {
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    // Bookings endpoints
    public function getBookings(\WP_REST_Request $request): \WP_REST_Response {
        $filters = [];
        
        // If not admin, only show own bookings
        if (!current_user_can('manage_classflow_bookings')) {
            $filters['student_id'] = get_current_user_id();
        } elseif ($request->get_param('student_id')) {
            $filters['student_id'] = (int) $request->get_param('student_id');
        }
        
        if ($request->get_param('status')) {
            $filters['status'] = $request->get_param('status');
        }
        
        $bookings = $this->container->get('booking_repository')->findAll($filters);
        
        $formattedBookings = array_map(function($booking) {
            return $this->formatBookingResponse($booking);
        }, $bookings);
        
        return new \WP_REST_Response(['bookings' => $formattedBookings]);
    }

    public function getBooking(\WP_REST_Request $request): \WP_REST_Response {
        $id = (int) $request->get_param('id');
        $booking = $this->container->get('booking_repository')->find($id);
        
        if (!$booking) {
            return new \WP_REST_Response(['error' => 'Booking not found'], 404);
        }
        
        // Check permission
        if (!current_user_can('manage_classflow_bookings') && 
            $booking->getStudentId() !== get_current_user_id()) {
            return new \WP_REST_Response(['error' => 'Unauthorized'], 403);
        }
        
        return new \WP_REST_Response($this->formatBookingResponse($booking));
    }

    public function createBooking(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $data = $request->get_json_params();
            $data['student_id'] = get_current_user_id();
            
            $booking = $this->container->get('booking_service')->createBooking($data);
            
            return new \WP_REST_Response(
                $this->formatBookingResponse($booking),
                201
            );
        } catch (\Exception $e) {
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    public function cancelBooking(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $id = (int) $request->get_param('id');
            $reason = $request->get_param('reason') ?: '';
            
            $result = $this->container->get('booking_service')->cancelBooking($id, $reason);
            
            if ($result) {
                return new \WP_REST_Response(['message' => 'Booking cancelled successfully']);
            }
            
            return new \WP_REST_Response(['error' => 'Failed to cancel booking'], 400);
        } catch (\Exception $e) {
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    // Payment endpoints
    public function createPaymentIntent(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $bookingId = (int) $request->get_param('booking_id');
            $booking = $this->container->get('booking_repository')->find($bookingId);
            
            if (!$booking) {
                return new \WP_REST_Response(['error' => 'Booking not found'], 404);
            }
            
            if ($booking->getStudentId() !== get_current_user_id()) {
                return new \WP_REST_Response(['error' => 'Unauthorized'], 403);
            }
            
            $paymentData = $this->container->get('payment_service')->createPaymentIntent($booking);
            
            return new \WP_REST_Response($paymentData);
        } catch (\Exception $e) {
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    public function confirmPayment(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $paymentIntentId = $request->get_param('payment_intent_id');
            
            $payment = $this->container->get('payment_service')->confirmPayment($paymentIntentId);
            
            return new \WP_REST_Response([
                'payment_id' => $payment->getId(),
                'status' => $payment->getStatus(),
            ]);
        } catch (\Exception $e) {
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    public function handleStripeWebhook(\WP_REST_Request $request): \WP_REST_Response {
        try {
            $payload = $request->get_body();
            $signature = $request->get_header('stripe-signature');
            
            $this->container->get('payment_service')->handleWebhook(
                json_decode($payload, true),
                $signature
            );
            
            return new \WP_REST_Response(['received' => true]);
        } catch (\Exception $e) {
            return new \WP_REST_Response(
                ['error' => $e->getMessage()],
                400
            );
        }
    }

    // Permission callbacks
    public function canManageClasses(): bool {
        return current_user_can('manage_classflow_classes');
    }

    public function canViewBookings(): bool {
        return is_user_logged_in();
    }

    public function canViewBooking(\WP_REST_Request $request): bool {
        if (current_user_can('manage_classflow_bookings')) {
            return true;
        }
        
        $bookingId = (int) $request->get_param('id');
        $booking = $this->container->get('booking_repository')->find($bookingId);
        
        return $booking && $booking->getStudentId() === get_current_user_id();
    }

    public function canManageBooking(\WP_REST_Request $request): bool {
        return $this->canViewBooking($request);
    }

    public function isAuthenticated(): bool {
        return is_user_logged_in();
    }

    // Response formatters
    private function formatClassResponse($class): array {
        return [
            'id' => $class->getId(),
            'name' => $class->getName(),
            'slug' => $class->getSlug(),
            'description' => $class->getDescription(),
            'category_id' => $class->getCategoryId(),
            'duration' => $class->getDuration(),
            'duration_formatted' => $class->getDurationFormatted(),
            'capacity' => $class->getCapacity(),
            'price' => $class->getPrice(),
            'formatted_price' => $class->getFormattedPrice(),
            'status' => $class->getStatus(),
            'featured_image_id' => $class->getFeaturedImageId(),
            'featured_image_url' => $class->getFeaturedImageId() ? wp_get_attachment_url($class->getFeaturedImageId()) : null,
            'gallery_ids' => $class->getGalleryIds(),
            'prerequisites' => $class->getPrerequisites(),
            'skill_level' => $class->getSkillLevel(),
            'created_at' => $class->getCreatedAt() ? $class->getCreatedAt()->format('c') : null,
            'updated_at' => $class->getUpdatedAt() ? $class->getUpdatedAt()->format('c') : null,
        ];
    }

    private function formatScheduleResponse($schedule): array {
        $class = $this->container->get('class_repository')->find($schedule->getClassId());
        $instructor = get_userdata($schedule->getInstructorId());
        $availableSpots = $this->container->get('schedule_repository')->getAvailableSpots($schedule->getId());
        
        return [
            'id' => $schedule->getId(),
            'class' => $class ? [
                'id' => $class->getId(),
                'name' => $class->getName(),
                'slug' => $class->getSlug(),
            ] : null,
            'instructor' => $instructor ? [
                'id' => $instructor->ID,
                'name' => $instructor->display_name,
            ] : null,
            'location_id' => $schedule->getLocationId(),
            'start_time' => $schedule->getStartTime()->format('c'),
            'end_time' => $schedule->getEndTime()->format('c'),
            'formatted_date' => $schedule->getFormattedDateRange(),
            'recurrence_rule' => $schedule->getRecurrenceRule(),
            'capacity_override' => $schedule->getCapacityOverride(),
            'price_override' => $schedule->getPriceOverride(),
            'status' => $schedule->getStatus(),
            'available_spots' => $availableSpots,
            'is_full' => $availableSpots === 0,
        ];
    }

    private function formatBookingResponse($booking): array {
        $schedule = $this->container->get('schedule_repository')->find($booking->getScheduleId());
        $student = get_userdata($booking->getStudentId());
        
        return [
            'id' => $booking->getId(),
            'booking_code' => $booking->getBookingCode(),
            'schedule_id' => $booking->getScheduleId(),
            'schedule' => $schedule ? $this->formatScheduleResponse($schedule) : null,
            'student' => $student ? [
                'id' => $student->ID,
                'name' => $student->display_name,
                'email' => current_user_can('manage_classflow_bookings') ? $student->user_email : null,
            ] : null,
            'status' => $booking->getStatus(),
            'status_label' => $booking->getStatusLabel(),
            'payment_status' => $booking->getPaymentStatus(),
            'payment_status_label' => $booking->getPaymentStatusLabel(),
            'amount' => $booking->getAmount(),
            'formatted_amount' => $booking->getFormattedAmount(),
            'notes' => $booking->getNotes(),
            'created_at' => $booking->getCreatedAt() ? $booking->getCreatedAt()->format('c') : null,
            'updated_at' => $booking->getUpdatedAt() ? $booking->getUpdatedAt()->format('c') : null,
        ];
    }

    // Argument definitions
    private function getClassesArgs(): array {
        return [
            'status' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'category_id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'page' => [
                'default' => 1,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                }
            ],
            'per_page' => [
                'default' => 10,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0 && $param <= 100;
                }
            ],
            'orderby' => [
                'default' => 'name ASC',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    private function createClassArgs(): array {
        return [
            'name' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description' => [
                'sanitize_callback' => 'wp_kses_post',
            ],
            'category_id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'duration' => [
                'default' => 60,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                }
            ],
            'capacity' => [
                'default' => 10,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                }
            ],
            'price' => [
                'default' => 0,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= 0;
                }
            ],
            'status' => [
                'default' => 'active',
                'enum' => ['active', 'inactive', 'draft'],
            ],
        ];
    }

    private function updateClassArgs(): array {
        return $this->createClassArgs(); // Same args, but none required
    }

    private function getSchedulesArgs(): array {
        return [
            'class_id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'instructor_id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'start_date' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'end_date' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    private function createScheduleArgs(): array {
        return [
            'class_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'instructor_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'start_time' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'end_time' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'location_id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'recurrence_rule' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    private function getBookingsArgs(): array {
        return [
            'student_id' => [
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'status' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    private function createBookingArgs(): array {
        return [
            'schedule_id' => [
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ],
            'notes' => [
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
        ];
    }
}