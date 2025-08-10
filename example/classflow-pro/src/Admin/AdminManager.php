<?php
declare(strict_types=1);

namespace ClassFlowPro\Admin;

use ClassFlowPro\Services\Container;

class AdminManager {
    private Container $container;
    private array $pages = [];

    public function __construct(Container $container) {
        $this->container = $container;
        
        // Initialize admin components
        $this->initializeComponents();
        
        // Register hooks
        $this->registerHooks();
    }

    private function initializeComponents(): void {
        // Initialize admin pages
        $this->pages = [
            'dashboard' => new Pages\DashboardPage($this->container),
            'classes' => new Pages\ClassesPage($this->container),
            'schedules' => new Pages\SchedulesPage($this->container),
            'bookings' => new Pages\BookingsPage($this->container),
            'students' => new Pages\StudentsPage($this->container),
            'instructors' => new Pages\InstructorsPage($this->container),
            'instructor_availability' => new Pages\InstructorAvailabilityPage($this->container),
            'payments' => new Pages\PaymentsPage($this->container),
            'settings' => new Pages\SettingsPage($this->container),
            'reports' => new Pages\ReportsPage($this->container),
        ];
    }

    private function registerHooks(): void {
        // Admin menu
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'displayAdminNotices']);
        
        // AJAX handlers
        add_action('wp_ajax_classflow_pro_search_students', [$this, 'ajaxSearchStudents']);
        add_action('wp_ajax_classflow_pro_search_instructors', [$this, 'ajaxSearchInstructors']);
        add_action('wp_ajax_classflow_pro_get_schedule_availability', [$this, 'ajaxGetScheduleAvailability']);
        add_action('wp_ajax_classflow_pro_export_settings', [$this, 'ajaxExportSettings']);
        add_action('wp_ajax_classflow_pro_import_settings', [$this, 'ajaxImportSettings']);
        add_action('wp_ajax_classflow_pro_reset_settings', [$this, 'ajaxResetSettings']);
        
        // Admin bar
        add_action('admin_bar_menu', [$this, 'addAdminBarItems'], 100);
    }

    public function registerAdminMenu(): void {
        // Main menu
        add_menu_page(
            __('ClassFlow Pro', 'classflow-pro'),
            __('ClassFlow Pro', 'classflow-pro'),
            'manage_classflow',
            'classflow-pro',
            [$this->pages['dashboard'], 'render'],
            'dashicons-calendar-alt',
            30
        );
        
        // Dashboard
        add_submenu_page(
            'classflow-pro',
            __('Dashboard', 'classflow-pro'),
            __('Dashboard', 'classflow-pro'),
            'manage_classflow',
            'classflow-pro',
            [$this->pages['dashboard'], 'render']
        );
        
        // Classes
        add_submenu_page(
            'classflow-pro',
            __('Classes', 'classflow-pro'),
            __('Classes', 'classflow-pro'),
            'manage_classflow_classes',
            'classflow-pro-classes',
            [$this->pages['classes'], 'render']
        );
        
        // Schedules
        add_submenu_page(
            'classflow-pro',
            __('Schedules', 'classflow-pro'),
            __('Schedules', 'classflow-pro'),
            'manage_classflow_classes',
            'classflow-pro-schedules',
            [$this->pages['schedules'], 'render']
        );
        
        // Bookings
        add_submenu_page(
            'classflow-pro',
            __('Bookings', 'classflow-pro'),
            __('Bookings', 'classflow-pro'),
            'manage_classflow_bookings',
            'classflow-pro-bookings',
            [$this->pages['bookings'], 'render']
        );
        
        // Students
        add_submenu_page(
            'classflow-pro',
            __('Students', 'classflow-pro'),
            __('Students', 'classflow-pro'),
            'manage_classflow_students',
            'classflow-pro-students',
            [$this->pages['students'], 'render']
        );
        
        // Instructors
        add_submenu_page(
            'classflow-pro',
            __('Instructors', 'classflow-pro'),
            __('Instructors', 'classflow-pro'),
            'manage_classflow_instructors',
            'classflow-pro-instructors',
            [$this->pages['instructors'], 'render']
        );
        
        // Instructor Availability
        add_submenu_page(
            'classflow-pro',
            __('Instructor Availability', 'classflow-pro'),
            __('Availability', 'classflow-pro'),
            'manage_own_classflow_schedule',
            'classflow-pro-instructor-availability',
            [$this->pages['instructor_availability'], 'render']
        );
        
        // Payments
        add_submenu_page(
            'classflow-pro',
            __('Payments', 'classflow-pro'),
            __('Payments', 'classflow-pro'),
            'manage_classflow_bookings',
            'classflow-pro-payments',
            [$this->pages['payments'], 'render']
        );
        
        // Reports
        add_submenu_page(
            'classflow-pro',
            __('Reports', 'classflow-pro'),
            __('Reports', 'classflow-pro'),
            'view_classflow_reports',
            'classflow-pro-reports',
            [$this->pages['reports'], 'render']
        );
        
        // Settings
        add_submenu_page(
            'classflow-pro',
            __('Settings', 'classflow-pro'),
            __('Settings', 'classflow-pro'),
            'manage_classflow_settings',
            'classflow-pro-settings',
            [$this->pages['settings'], 'render']
        );
    }

    public function enqueueAdminAssets(string $hook): void {
        // Only load on our admin pages
        if (!strpos($hook, 'classflow-pro')) {
            return;
        }
        
        // Core styles
        wp_enqueue_style(
            'classflow-pro-admin',
            CLASSFLOW_PRO_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CLASSFLOW_PRO_VERSION
        );
        
        // Core scripts
        wp_enqueue_script(
            'classflow-pro-admin',
            CLASSFLOW_PRO_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-util'],
            CLASSFLOW_PRO_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('classflow-pro-admin', 'classflowPro', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('classflow_pro_admin'),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'classflow-pro'),
                'confirm_cancel' => __('Are you sure you want to cancel this booking?', 'classflow-pro'),
                'loading' => __('Loading...', 'classflow-pro'),
                'error' => __('An error occurred. Please try again.', 'classflow-pro'),
            ],
        ]);
        
        // Page-specific assets
        $this->enqueuePageAssets($hook);
    }

    private function enqueuePageAssets(string $hook): void {
        $page = str_replace('classflow-pro_page_classflow-pro-', '', $hook);
        
        switch ($page) {
            case 'schedules':
                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_style('jquery-ui');
                break;
                
            case 'reports':
                wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1');
                break;
                
            case 'settings':
                wp_enqueue_media();
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
                break;
        }
    }

    public function displayAdminNotices(): void {
        // Check for setup requirements
        $this->checkSetupRequirements();
        
        // Display transient notices
        $this->displayTransientNotices();
    }

    private function checkSetupRequirements(): void {
        $screen = get_current_screen();
        if (!$screen || !strpos($screen->id, 'classflow-pro')) {
            return;
        }
        
        $settings = $this->container->get('settings');
        
        // Check if Stripe is configured
        if ($settings->get('payment.enabled') && !$settings->get('payment.stripe_test_secret_key')) {
            $this->addNotice(
                'warning',
                sprintf(
                    __('ClassFlow Pro: Payment gateway is not configured. %s', 'classflow-pro'),
                    '<a href="' . admin_url('admin.php?page=classflow-pro-settings&tab=payment') . '">' .
                    __('Configure now', 'classflow-pro') . '</a>'
                )
            );
        }
        
        // Check if email settings are configured
        if (!$settings->get('email.from_email')) {
            $this->addNotice(
                'warning',
                sprintf(
                    __('ClassFlow Pro: Email settings are not configured. %s', 'classflow-pro'),
                    '<a href="' . admin_url('admin.php?page=classflow-pro-settings&tab=email') . '">' .
                    __('Configure now', 'classflow-pro') . '</a>'
                )
            );
        }
    }

    private function displayTransientNotices(): void {
        $notices = get_transient('classflow_pro_admin_notices');
        if (!$notices) {
            return;
        }
        
        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                wp_kses_post($notice['message'])
            );
        }
        
        delete_transient('classflow_pro_admin_notices');
    }

    public function addNotice(string $type, string $message): void {
        $notices = get_transient('classflow_pro_admin_notices') ?: [];
        $notices[] = [
            'type' => $type,
            'message' => $message,
        ];
        set_transient('classflow_pro_admin_notices', $notices, 60);
    }

    public function ajaxSearchStudents(): void {
        check_ajax_referer('classflow_pro_admin', 'nonce');
        
        if (!current_user_can('manage_classflow_students')) {
            wp_die(-1);
        }
        
        $search = sanitize_text_field($_GET['q'] ?? '');
        
        $users = get_users([
            'search' => '*' . $search . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 20,
            'role__in' => ['classflow_student', 'subscriber'],
        ]);
        
        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user->ID,
                'text' => sprintf('%s (%s)', $user->display_name, $user->user_email),
            ];
        }
        
        wp_send_json(['results' => $results]);
    }

    public function ajaxSearchInstructors(): void {
        check_ajax_referer('classflow_pro_admin', 'nonce');
        
        if (!current_user_can('manage_classflow_instructors')) {
            wp_die(-1);
        }
        
        $search = sanitize_text_field($_GET['q'] ?? '');
        
        $users = get_users([
            'search' => '*' . $search . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 20,
            'role' => 'classflow_instructor',
        ]);
        
        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user->ID,
                'text' => sprintf('%s (%s)', $user->display_name, $user->user_email),
            ];
        }
        
        wp_send_json(['results' => $results]);
    }

    public function ajaxGetScheduleAvailability(): void {
        check_ajax_referer('classflow_pro_admin', 'nonce');
        
        if (!current_user_can('manage_classflow_bookings')) {
            wp_die(-1);
        }
        
        $scheduleId = (int) ($_GET['schedule_id'] ?? 0);
        if (!$scheduleId) {
            wp_send_json_error(['message' => __('Invalid schedule ID', 'classflow-pro')]);
        }
        
        $scheduleRepo = $this->container->get('schedule_repository');
        $availableSpots = $scheduleRepo->getAvailableSpots($scheduleId);
        
        wp_send_json_success([
            'available_spots' => $availableSpots,
        ]);
    }

    public function addAdminBarItems(\WP_Admin_Bar $adminBar): void {
        if (!current_user_can('manage_classflow')) {
            return;
        }
        
        // Add main node
        $adminBar->add_node([
            'id' => 'classflow-pro',
            'title' => '<span class="ab-icon dashicons dashicons-calendar-alt"></span>' . 
                      '<span class="ab-label">' . __('ClassFlow Pro', 'classflow-pro') . '</span>',
            'href' => admin_url('admin.php?page=classflow-pro'),
        ]);
        
        // Add quick links
        $adminBar->add_node([
            'parent' => 'classflow-pro',
            'id' => 'classflow-pro-new-class',
            'title' => __('New Class', 'classflow-pro'),
            'href' => admin_url('admin.php?page=classflow-pro-classes&action=new'),
        ]);
        
        $adminBar->add_node([
            'parent' => 'classflow-pro',
            'id' => 'classflow-pro-new-schedule',
            'title' => __('New Schedule', 'classflow-pro'),
            'href' => admin_url('admin.php?page=classflow-pro-schedules&action=new'),
        ]);
        
        $adminBar->add_node([
            'parent' => 'classflow-pro',
            'id' => 'classflow-pro-today',
            'title' => __("Today's Classes", 'classflow-pro'),
            'href' => admin_url('admin.php?page=classflow-pro-schedules&date=' . date('Y-m-d')),
        ]);
    }

    public function ajaxExportSettings(): void {
        check_ajax_referer('classflow_pro_admin', 'nonce');
        
        if (!current_user_can('manage_classflow_settings')) {
            wp_die(-1);
        }
        
        $settings = $this->container->get('settings');
        $export_data = [
            'version' => CLASSFLOW_PRO_VERSION,
            'exported_at' => current_time('mysql'),
            'settings' => $settings->getAll()
        ];
        
        wp_send_json_success([
            'filename' => 'classflow-pro-settings-' . date('Y-m-d-His') . '.json',
            'data' => json_encode($export_data, JSON_PRETTY_PRINT)
        ]);
    }

    public function ajaxImportSettings(): void {
        check_ajax_referer('classflow_pro_admin', 'nonce');
        
        if (!current_user_can('manage_classflow_settings')) {
            wp_die(-1);
        }
        
        $import_data = $_POST['import_data'] ?? '';
        if (empty($import_data)) {
            wp_send_json_error(['message' => __('No import data provided', 'classflow-pro')]);
        }
        
        $data = json_decode(stripslashes($import_data), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => __('Invalid JSON data', 'classflow-pro')]);
        }
        
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            wp_send_json_error(['message' => __('Invalid settings format', 'classflow-pro')]);
        }
        
        $settings = $this->container->get('settings');
        
        // Clear existing settings first
        $settings->reset();
        
        // Import all settings at once
        update_option('classflow_pro_settings', $data['settings']);
        
        // Clear caches
        wp_cache_flush();
        
        wp_send_json_success(['message' => __('Settings imported successfully', 'classflow-pro')]);
    }

    public function ajaxResetSettings(): void {
        check_ajax_referer('classflow_pro_admin', 'nonce');
        
        if (!current_user_can('manage_classflow_settings')) {
            wp_die(-1);
        }
        
        $confirm = $_POST['confirm'] ?? false;
        if ($confirm !== 'yes') {
            wp_send_json_error(['message' => __('Please confirm the reset action', 'classflow-pro')]);
        }
        
        $settings = $this->container->get('settings');
        $settings->reset();
        
        // Clear caches
        wp_cache_flush();
        
        wp_send_json_success(['message' => __('Settings reset to defaults', 'classflow-pro')]);
    }
}