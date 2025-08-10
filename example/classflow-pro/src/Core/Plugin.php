<?php
declare(strict_types=1);

namespace ClassFlowPro\Core;

use ClassFlowPro\Admin\AdminManager;
use ClassFlowPro\Frontend\FrontendManager;
use ClassFlowPro\API\RestApiManager;
use ClassFlowPro\Services\Container;

class Plugin {
    private static ?Plugin $instance = null;
    private Container $container;
    private bool $initialized = false;

    private function __construct() {
        $this->container = new Container();
    }

    public static function getInstance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new Plugin();
        }
        return self::$instance;
    }

    public function init(): void {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        // Load text domain
        add_action('init', [$this, 'loadTextDomain']);

        // Register services
        $this->registerServices();

        // Initialize components
        $this->initializeComponents();

        // Register hooks
        $this->registerHooks();
    }

    public function loadTextDomain(): void {
        load_plugin_textdomain(
            'classflow-pro',
            false,
            dirname(CLASSFLOW_PRO_PLUGIN_BASENAME) . '/languages'
        );
    }

    private function registerServices(): void {
        // Core services
        $this->container->register('database', fn() => new Database());
        $this->container->register('settings', fn() => new Settings());
        
        // Model repositories
        $this->container->register('class_repository', fn() => new \ClassFlowPro\Models\Repositories\ClassRepository());
        $this->container->register('category_repository', fn() => new \ClassFlowPro\Models\Repositories\CategoryRepository());
        $this->container->register('instructor_repository', fn() => new \ClassFlowPro\Models\Repositories\InstructorRepository());
        $this->container->register('student_repository', fn() => new \ClassFlowPro\Models\Repositories\StudentRepository());
        $this->container->register('schedule_repository', fn() => new \ClassFlowPro\Models\Repositories\ScheduleRepository());
        $this->container->register('booking_repository', fn() => new \ClassFlowPro\Models\Repositories\BookingRepository());
        $this->container->register('location_repository', fn() => new \ClassFlowPro\Models\Repositories\LocationRepository());
        $this->container->register('payment_repository', fn() => new \ClassFlowPro\Models\Repositories\PaymentRepository());
        
        // Business services
        $this->container->register('class_service', fn() => new \ClassFlowPro\Services\ClassService($this->container));
        $this->container->register('booking_service', fn() => new \ClassFlowPro\Services\BookingService($this->container));
        $this->container->register('payment_service', fn() => new \ClassFlowPro\Services\PaymentService($this->container));
        $this->container->register('notification_service', fn() => new \ClassFlowPro\Services\NotificationService($this->container));
    }

    private function initializeComponents(): void {
        // Admin area
        if (is_admin()) {
            new AdminManager($this->container);
        }

        // Frontend
        if (!is_admin()) {
            new FrontendManager($this->container);
        }

        // REST API
        new RestApiManager($this->container);
    }

    private function registerHooks(): void {
        // Add custom cron schedules
        add_filter('cron_schedules', [$this, 'addCronSchedules']);

        // Schedule events
        if (!wp_next_scheduled('classflow_pro_daily_tasks')) {
            wp_schedule_event(time(), 'daily', 'classflow_pro_daily_tasks');
        }

        // Hook daily tasks
        add_action('classflow_pro_daily_tasks', [$this, 'runDailyTasks']);
    }

    public function addCronSchedules(array $schedules): array {
        $schedules['classflow_pro_hourly'] = [
            'interval' => HOUR_IN_SECONDS,
            'display' => __('ClassFlow Pro Hourly', 'classflow-pro')
        ];

        return $schedules;
    }

    public function runDailyTasks(): void {
        // Clean up expired bookings
        $this->container->get('booking_service')->cleanupExpiredBookings();
        
        // Send reminders
        $this->container->get('notification_service')->sendUpcomingClassReminders();
        
        // Future: Update package expirations
        // $this->container->get('package_service')->processExpiredPackages();
    }

    public function getContainer(): Container {
        return $this->container;
    }
}