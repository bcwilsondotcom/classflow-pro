<?php
declare(strict_types=1);

namespace ClassFlowPro\Admin\Pages;

use ClassFlowPro\Services\Container;
use ClassFlowPro\Models\Entities\Schedule;

class SchedulesPage {
    private Container $container;
    private string $currentView;
    private string $currentAction;

    public function __construct(Container $container) {
        $this->container = $container;
        $this->currentView = $_GET['view'] ?? 'calendar';
        $this->currentAction = $_GET['action'] ?? '';
        
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleActions();
        }
    }

    public function render(): void {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Schedules', 'classflow-pro'); ?></h1>
            
            <?php if ($this->currentAction !== 'new' && $this->currentAction !== 'edit'): ?>
                <a href="<?php echo esc_url(add_query_arg(['action' => 'new'])); ?>" class="page-title-action">
                    <?php echo esc_html__('Add New Schedule', 'classflow-pro'); ?>
                </a>
            <?php endif; ?>
            
            <hr class="wp-header-end">
            
            <?php $this->displayNotices(); ?>
            
            <?php
            switch ($this->currentAction) {
                case 'new':
                case 'edit':
                    $this->renderForm();
                    break;
                default:
                    $this->renderViews();
                    $this->renderContent();
            }
            ?>
        </div>
        <?php
    }

    private function renderViews(): void {
        $views = [
            'calendar' => __('Calendar View', 'classflow-pro'),
            'list' => __('List View', 'classflow-pro'),
            'timeline' => __('Timeline View', 'classflow-pro'),
        ];
        
        ?>
        <ul class="subsubsub">
            <?php
            $total = count($views);
            $count = 0;
            foreach ($views as $view => $label):
                $count++;
                $url = add_query_arg('view', $view);
                $class = $this->currentView === $view ? 'current' : '';
                ?>
                <li>
                    <a href="<?php echo esc_url($url); ?>" class="<?php echo esc_attr($class); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                    <?php if ($count < $total) echo ' |'; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="clear"></div>
        <?php
    }

    private function renderContent(): void {
        switch ($this->currentView) {
            case 'list':
                $this->renderListView();
                break;
            case 'timeline':
                $this->renderTimelineView();
                break;
            case 'calendar':
            default:
                $this->renderCalendarView();
        }
    }

    private function renderCalendarView(): void {
        $currentMonth = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');
        $date = \DateTime::createFromFormat('Y-m', $currentMonth);
        if (!$date) {
            $date = new \DateTime();
        }
        
        // Get schedules for the month
        $startDate = clone $date;
        $startDate->modify('first day of this month');
        $endDate = clone $date;
        $endDate->modify('last day of this month');
        
        $scheduleRepo = $this->container->get('schedule_repository');
        $schedules = $scheduleRepo->findByDateRange($startDate, $endDate);
        
        // Organize schedules by date
        $schedulesByDate = [];
        foreach ($schedules as $schedule) {
            $dateKey = $schedule->getStartTime()->format('Y-m-d');
            if (!isset($schedulesByDate[$dateKey])) {
                $schedulesByDate[$dateKey] = [];
            }
            $schedulesByDate[$dateKey][] = $schedule;
        }
        
        ?>
        <div class="classflow-calendar-view">
            <div class="calendar-header">
                <div class="calendar-navigation">
                    <?php
                    $prevMonth = clone $date;
                    $prevMonth->modify('-1 month');
                    $nextMonth = clone $date;
                    $nextMonth->modify('+1 month');
                    ?>
                    <a href="<?php echo esc_url(add_query_arg('month', $prevMonth->format('Y-m'))); ?>" class="button">
                        &larr; <?php echo esc_html($prevMonth->format('F')); ?>
                    </a>
                    <h2><?php echo esc_html($date->format('F Y')); ?></h2>
                    <a href="<?php echo esc_url(add_query_arg('month', $nextMonth->format('Y-m'))); ?>" class="button">
                        <?php echo esc_html($nextMonth->format('F')); ?> &rarr;
                    </a>
                </div>
                
                <div class="calendar-filters">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="classflow-pro-schedules">
                        <input type="hidden" name="view" value="calendar">
                        <input type="hidden" name="month" value="<?php echo esc_attr($currentMonth); ?>">
                        
                        <?php $this->renderFilters(); ?>
                        
                        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'classflow-pro'); ?>">
                        <?php if (!empty($_GET['class_id']) || !empty($_GET['instructor_id']) || !empty($_GET['location_id'])): ?>
                            <a href="<?php echo esc_url(add_query_arg(['view' => 'calendar', 'month' => $currentMonth], admin_url('admin.php?page=classflow-pro-schedules'))); ?>" class="button">
                                <?php esc_html_e('Clear Filters', 'classflow-pro'); ?>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <table class="wp-calendar">
                <thead>
                    <tr>
                        <?php
                        $weekDays = [
                            __('Sunday', 'classflow-pro'),
                            __('Monday', 'classflow-pro'),
                            __('Tuesday', 'classflow-pro'),
                            __('Wednesday', 'classflow-pro'),
                            __('Thursday', 'classflow-pro'),
                            __('Friday', 'classflow-pro'),
                            __('Saturday', 'classflow-pro'),
                        ];
                        
                        $settings = $this->container->get('settings');
                        $weekStartsOn = $settings->get('general.week_starts_on', 1);
                        
                        // Reorder days based on week start setting
                        if ($weekStartsOn > 0) {
                            $weekDays = array_merge(
                                array_slice($weekDays, $weekStartsOn),
                                array_slice($weekDays, 0, $weekStartsOn)
                            );
                        }
                        
                        foreach ($weekDays as $day): ?>
                            <th><?php echo esc_html(substr($day, 0, 3)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $firstDay = clone $startDate;
                    $firstDayOfWeek = (int) $firstDay->format('w');
                    
                    // Adjust for week start setting
                    $dayOffset = ($firstDayOfWeek - $weekStartsOn + 7) % 7;
                    
                    $currentDate = clone $firstDay;
                    $currentDate->modify("-{$dayOffset} days");
                    
                    $today = new \DateTime();
                    $today->setTime(0, 0, 0);
                    
                    while ($currentDate <= $endDate || $currentDate->format('w') != $weekStartsOn):
                        if ($currentDate->format('w') == $weekStartsOn): ?>
                            <tr>
                        <?php endif; ?>
                        
                        <td class="<?php echo $this->getCalendarCellClasses($currentDate, $date, $today); ?>">
                            <div class="calendar-date">
                                <?php echo esc_html($currentDate->format('j')); ?>
                            </div>
                            
                            <?php
                            $dateKey = $currentDate->format('Y-m-d');
                            if (isset($schedulesByDate[$dateKey])):
                                ?>
                                <div class="calendar-schedules">
                                    <?php
                                    $displayCount = 3;
                                    $scheduleCount = count($schedulesByDate[$dateKey]);
                                    
                                    foreach (array_slice($schedulesByDate[$dateKey], 0, $displayCount) as $schedule):
                                        $class = $this->getClassById($schedule->getClassId());
                                        $instructor = $this->getInstructorById($schedule->getInstructorId());
                                        $bookingTypeClass = 'booking-type-' . $schedule->getBookingType();
                                        ?>
                                        <div class="calendar-schedule-item <?php echo esc_attr($bookingTypeClass); ?>">
                                            <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $schedule->getId()])); ?>" 
                                               title="<?php echo esc_attr($class ? $class->getName() : __('Unknown Class', 'classflow-pro')); ?>">
                                                <span class="schedule-time"><?php echo esc_html($schedule->getStartTime()->format('g:i A')); ?></span>
                                                <span class="schedule-class"><?php echo esc_html($class ? $class->getName() : __('Unknown Class', 'classflow-pro')); ?></span>
                                                <?php if ($schedule->isPrivateSession()): ?>
                                                    <span class="booking-type-indicator"><?php echo esc_html__('Private', 'classflow-pro'); ?></span>
                                                <?php elseif ($schedule->isSemiPrivate()): ?>
                                                    <span class="booking-type-indicator"><?php echo esc_html__('Semi-Private', 'classflow-pro'); ?></span>
                                                <?php endif; ?>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($scheduleCount > $displayCount): ?>
                                        <div class="calendar-more">
                                            <a href="<?php echo esc_url(add_query_arg(['view' => 'list', 'date' => $dateKey])); ?>">
                                                <?php printf(esc_html__('+ %d more', 'classflow-pro'), $scheduleCount - $displayCount); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        
                        <?php
                        if ($currentDate->format('w') == (($weekStartsOn + 6) % 7)): ?>
                            </tr>
                        <?php endif;
                        
                        $currentDate->modify('+1 day');
                    endwhile;
                    ?>
                </tbody>
            </table>
        </div>
        
        <style>
            .classflow-calendar-view {
                margin-top: 20px;
            }
            
            .calendar-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            
            .calendar-navigation {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            .calendar-navigation h2 {
                margin: 0;
                font-size: 24px;
            }
            
            .calendar-filters form {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            
            .wp-calendar {
                width: 100%;
                border-collapse: collapse;
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            
            .wp-calendar th,
            .wp-calendar td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: left;
                vertical-align: top;
            }
            
            .wp-calendar th {
                background: #f5f5f5;
                font-weight: 600;
            }
            
            .wp-calendar td {
                height: 100px;
                position: relative;
            }
            
            .wp-calendar td.other-month {
                background: #f9f9f9;
                color: #999;
            }
            
            .wp-calendar td.today {
                background: #fff9e6;
            }
            
            .calendar-date {
                font-weight: 600;
                margin-bottom: 5px;
            }
            
            .calendar-schedules {
                font-size: 12px;
            }
            
            .calendar-schedule-item {
                margin-bottom: 3px;
                background: #f0f6fc;
                border-left: 3px solid #2271b1;
                padding: 2px 5px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            .calendar-schedule-item a {
                text-decoration: none;
                color: #333;
                display: block;
            }
            
            .calendar-schedule-item a:hover {
                color: #2271b1;
            }
            
            .schedule-time {
                font-weight: 600;
                margin-right: 5px;
            }
            
            .calendar-more {
                text-align: center;
                margin-top: 3px;
            }
            
            .calendar-more a {
                color: #2271b1;
                text-decoration: none;
                font-size: 11px;
            }
            
            /* Booking type indicators */
            .booking-type-private {
                border-left-color: #9c27b0 !important;
                background: #f3e5f5 !important;
            }
            
            .booking-type-semi-private {
                border-left-color: #ff9800 !important;
                background: #fff3e0 !important;
            }
            
            .booking-type-indicator {
                font-size: 10px;
                font-weight: 600;
                display: block;
                margin-top: 2px;
                opacity: 0.8;
            }
        </style>
        <?php
    }

    private function renderListView(): void {
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $perPage = 20;
        
        // Build filters
        $filters = [];
        if (!empty($_GET['class_id'])) {
            $filters['class_id'] = intval($_GET['class_id']);
        }
        if (!empty($_GET['instructor_id'])) {
            $filters['instructor_id'] = intval($_GET['instructor_id']);
        }
        if (!empty($_GET['location_id'])) {
            $filters['location_id'] = intval($_GET['location_id']);
        }
        if (!empty($_GET['status'])) {
            $filters['status'] = sanitize_text_field($_GET['status']);
        }
        
        // Date filter
        if (!empty($_GET['date'])) {
            $date = sanitize_text_field($_GET['date']);
            $startDate = new \DateTime($date);
            $endDate = clone $startDate;
            $endDate->modify('+1 day');
        } else {
            // Default to upcoming schedules
            $startDate = new \DateTime();
            $endDate = null;
        }
        
        $scheduleRepo = $this->container->get('schedule_repository');
        
        // Get all schedules for counting
        if ($endDate) {
            $allSchedules = $scheduleRepo->findByDateRange($startDate, $endDate, $filters);
        } else {
            $filters['start_time_after'] = $startDate->format('Y-m-d H:i:s');
            $allSchedules = $scheduleRepo->findAll($filters);
        }
        
        $totalItems = count($allSchedules);
        $totalPages = ceil($totalItems / $perPage);
        
        // Get paginated schedules
        $offset = ($paged - 1) * $perPage;
        $schedules = array_slice($allSchedules, $offset, $perPage);
        
        ?>
        <form method="get" action="">
            <input type="hidden" name="page" value="classflow-pro-schedules">
            <input type="hidden" name="view" value="list">
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <?php $this->renderFilters(); ?>
                    <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'classflow-pro'); ?>">
                </div>
                
                <?php if ($totalItems > 0): ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(
                                esc_html(_n('%s item', '%s items', $totalItems, 'classflow-pro')),
                                number_format_i18n($totalItems)
                            ); ?>
                        </span>
                        
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $totalPages,
                            'current' => $paged,
                        ]);
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($schedules)): ?>
                <p><?php echo esc_html__('No schedules found.', 'classflow-pro'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><?php echo esc_html__('ID', 'classflow-pro'); ?></th>
                            <th><?php echo esc_html__('Class', 'classflow-pro'); ?></th>
                            <th><?php echo esc_html__('Type', 'classflow-pro'); ?></th>
                            <th><?php echo esc_html__('Instructor', 'classflow-pro'); ?></th>
                            <th><?php echo esc_html__('Date & Time', 'classflow-pro'); ?></th>
                            <th><?php echo esc_html__('Location', 'classflow-pro'); ?></th>
                            <th><?php echo esc_html__('Capacity', 'classflow-pro'); ?></th>
                            <th><?php echo esc_html__('Status', 'classflow-pro'); ?></th>
                            <th><?php echo esc_html__('Actions', 'classflow-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): 
                            $class = $this->getClassById($schedule->getClassId());
                            $instructor = $this->getInstructorById($schedule->getInstructorId());
                            $location = $schedule->getLocationId() ? $this->getLocationById($schedule->getLocationId()) : null;
                            $bookingCount = $this->getBookingCount($schedule->getId());
                            $capacity = $schedule->getCapacityOverride() ?: ($class ? $class->getCapacity() : 0);
                            ?>
                            <tr>
                                <td><?php echo esc_html($schedule->getId()); ?></td>
                                <td>
                                    <strong>
                                        <?php echo esc_html($class ? $class->getName() : __('Unknown Class', 'classflow-pro')); ?>
                                    </strong>
                                    <?php if ($schedule->isRecurring()): ?>
                                        <br><span class="description"><?php echo esc_html__('Recurring', 'classflow-pro'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $typeLabels = [
                                        'group' => __('Group', 'classflow-pro'),
                                        'private' => __('Private', 'classflow-pro'),
                                        'semi-private' => __('Semi-Private', 'classflow-pro'),
                                    ];
                                    $typeClass = 'type-' . $schedule->getBookingType();
                                    ?>
                                    <span class="classflow-booking-type <?php echo esc_attr($typeClass); ?>">
                                        <?php echo esc_html($typeLabels[$schedule->getBookingType()] ?? $schedule->getBookingType()); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($instructor ? $instructor->display_name : __('Unknown Instructor', 'classflow-pro')); ?>
                                </td>
                                <td>
                                    <?php echo esc_html($schedule->getStartTime()->format(get_option('date_format') . ' ' . get_option('time_format'))); ?>
                                    <br>
                                    <span class="description">
                                        <?php printf(
                                            esc_html__('Duration: %d minutes', 'classflow-pro'),
                                            $schedule->getDuration()
                                        ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($location ? $location->getName() : __('No location', 'classflow-pro')); ?>
                                </td>
                                <td>
                                    <?php printf(
                                        '<span class="%s">%d / %d</span>',
                                        $bookingCount >= $capacity ? 'full' : 'available',
                                        $bookingCount,
                                        $capacity
                                    ); ?>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'scheduled' => __('Scheduled', 'classflow-pro'),
                                        'cancelled' => __('Cancelled', 'classflow-pro'),
                                        'completed' => __('Completed', 'classflow-pro'),
                                    ];
                                    $statusClass = 'status-' . $schedule->getStatus();
                                    ?>
                                    <span class="classflow-pro-status-badge <?php echo esc_attr($statusClass); ?>">
                                        <?php echo esc_html($statusLabels[$schedule->getStatus()] ?? $schedule->getStatus()); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $schedule->getId()])); ?>" 
                                       class="button button-small">
                                        <?php echo esc_html__('Edit', 'classflow-pro'); ?>
                                    </a>
                                    
                                    <?php if ($schedule->isActive() && $schedule->isUpcoming()): ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(
                                            add_query_arg(['action' => 'cancel', 'id' => $schedule->getId()]),
                                            'cancel_schedule_' . $schedule->getId()
                                        )); ?>" 
                                           class="button button-small"
                                           onclick="return confirm('<?php echo esc_attr__('Are you sure you want to cancel this schedule?', 'classflow-pro'); ?>');">
                                            <?php echo esc_html__('Cancel', 'classflow-pro'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </form>
        
        <style>
            .status-scheduled { background-color: #d4edda; color: #155724; }
            .status-cancelled { background-color: #f8d7da; color: #721c24; }
            .status-completed { background-color: #cce5ff; color: #004085; }
            .full { color: #dc3545; font-weight: bold; }
            .available { color: #28a745; }
            
            .classflow-booking-type {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .type-group { background-color: #e3f2fd; color: #1976d2; }
            .type-private { background-color: #f3e5f5; color: #7b1fa2; }
            .type-semi-private { background-color: #fff3e0; color: #f57c00; }
        </style>
        <?php
    }

    private function renderTimelineView(): void {
        // Get date range
        $date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
        $currentDate = new \DateTime($date);
        
        // Get schedules for the day
        $startDate = clone $currentDate;
        $startDate->setTime(0, 0, 0);
        $endDate = clone $currentDate;
        $endDate->setTime(23, 59, 59);
        
        $scheduleRepo = $this->container->get('schedule_repository');
        $schedules = $scheduleRepo->findByDateRange($startDate, $endDate);
        
        // Get locations for timeline rows
        $locationRepo = $this->container->get('location_repository');
        $locations = $locationRepo->findAll();
        
        ?>
        <div class="classflow-timeline-view">
            <div class="timeline-header">
                <div class="timeline-navigation">
                    <?php
                    $prevDate = clone $currentDate;
                    $prevDate->modify('-1 day');
                    $nextDate = clone $currentDate;
                    $nextDate->modify('+1 day');
                    ?>
                    <a href="<?php echo esc_url(add_query_arg('date', $prevDate->format('Y-m-d'))); ?>" class="button">
                        &larr; <?php echo esc_html__('Previous Day', 'classflow-pro'); ?>
                    </a>
                    <h2><?php echo esc_html($currentDate->format('l, F j, Y')); ?></h2>
                    <a href="<?php echo esc_url(add_query_arg('date', $nextDate->format('Y-m-d'))); ?>" class="button">
                        <?php echo esc_html__('Next Day', 'classflow-pro'); ?> &rarr;
                    </a>
                </div>
                
                <div class="timeline-actions">
                    <a href="<?php echo esc_url(add_query_arg('date', date('Y-m-d'))); ?>" class="button">
                        <?php echo esc_html__('Today', 'classflow-pro'); ?>
                    </a>
                </div>
            </div>
            
            <div class="timeline-grid">
                <div class="timeline-header-row">
                    <div class="timeline-location-header"><?php echo esc_html__('Location', 'classflow-pro'); ?></div>
                    <?php for ($hour = 6; $hour < 22; $hour++): ?>
                        <div class="timeline-hour-header">
                            <?php echo esc_html(date('g A', mktime($hour, 0, 0))); ?>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <?php foreach ($locations as $location): 
                    // Get schedules for this location
                    $locationSchedules = array_filter($schedules, function($schedule) use ($location) {
                        return $schedule->getLocationId() == $location->getId();
                    });
                    ?>
                    <div class="timeline-row">
                        <div class="timeline-location-name">
                            <?php echo esc_html($location->getName()); ?>
                        </div>
                        <div class="timeline-schedule-container">
                            <?php foreach ($locationSchedules as $schedule): 
                                $class = $this->getClassById($schedule->getClassId());
                                $instructor = $this->getInstructorById($schedule->getInstructorId());
                                
                                // Calculate position and width
                                $startHour = (int) $schedule->getStartTime()->format('H');
                                $startMinute = (int) $schedule->getStartTime()->format('i');
                                $duration = $schedule->getDuration();
                                
                                $leftPosition = (($startHour - 6) * 60 + $startMinute) / (16 * 60) * 100;
                                $width = $duration / (16 * 60) * 100;
                                ?>
                                <div class="timeline-schedule-block" 
                                     style="left: <?php echo $leftPosition; ?>%; width: <?php echo $width; ?>%;">
                                    <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $schedule->getId()])); ?>">
                                        <div class="schedule-time">
                                            <?php echo esc_html($schedule->getStartTime()->format('g:i A')); ?>
                                        </div>
                                        <div class="schedule-name">
                                            <?php echo esc_html($class ? $class->getName() : __('Unknown Class', 'classflow-pro')); ?>
                                        </div>
                                        <div class="schedule-instructor">
                                            <?php echo esc_html($instructor ? $instructor->display_name : ''); ?>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($locations)): ?>
                    <p><?php echo esc_html__('No locations found. Please add locations first.', 'classflow-pro'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .classflow-timeline-view {
                margin-top: 20px;
            }
            
            .timeline-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            
            .timeline-navigation {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            .timeline-navigation h2 {
                margin: 0;
                font-size: 20px;
            }
            
            .timeline-grid {
                background: #fff;
                border: 1px solid #ddd;
                overflow-x: auto;
            }
            
            .timeline-header-row {
                display: flex;
                background: #f5f5f5;
                border-bottom: 2px solid #ddd;
            }
            
            .timeline-location-header {
                width: 150px;
                padding: 10px;
                font-weight: 600;
                border-right: 1px solid #ddd;
                flex-shrink: 0;
            }
            
            .timeline-hour-header {
                flex: 1;
                padding: 10px 5px;
                text-align: center;
                font-size: 12px;
                border-right: 1px solid #eee;
                min-width: 60px;
            }
            
            .timeline-row {
                display: flex;
                border-bottom: 1px solid #ddd;
                min-height: 60px;
            }
            
            .timeline-location-name {
                width: 150px;
                padding: 10px;
                background: #f9f9f9;
                border-right: 1px solid #ddd;
                flex-shrink: 0;
            }
            
            .timeline-schedule-container {
                flex: 1;
                position: relative;
                background-image: repeating-linear-gradient(
                    to right,
                    transparent,
                    transparent 59px,
                    #eee 59px,
                    #eee 60px
                );
            }
            
            .timeline-schedule-block {
                position: absolute;
                top: 5px;
                bottom: 5px;
                background: #2271b1;
                color: #fff;
                border-radius: 4px;
                padding: 5px;
                overflow: hidden;
                cursor: pointer;
                transition: background-color 0.2s;
            }
            
            .timeline-schedule-block:hover {
                background: #135e96;
            }
            
            .timeline-schedule-block a {
                color: #fff;
                text-decoration: none;
                display: block;
                height: 100%;
            }
            
            .schedule-time {
                font-size: 11px;
                font-weight: 600;
            }
            
            .schedule-name {
                font-size: 12px;
                margin: 2px 0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .schedule-instructor {
                font-size: 11px;
                opacity: 0.9;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        </style>
        <?php
    }

    private function renderForm(): void {
        $scheduleId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $schedule = null;
        
        if ($scheduleId) {
            $scheduleRepo = $this->container->get('schedule_repository');
            $schedule = $scheduleRepo->find($scheduleId);
            
            if (!$schedule) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Schedule not found.', 'classflow-pro') . '</p></div>';
                return;
            }
        }
        
        // Get data for dropdowns
        $classRepo = $this->container->get('class_repository');
        $classes = $classRepo->findAll(['status' => 'active']);
        
        $instructorRepo = $this->container->get('instructor_repository');
        $instructors = $instructorRepo->findAll(['status' => 'active']);
        
        $locationRepo = $this->container->get('location_repository');
        $locations = $locationRepo->findAll(['status' => 'active']);
        
        ?>
        <form method="post" action="" id="schedule-form">
            <?php wp_nonce_field('save_schedule', 'schedule_nonce'); ?>
            <input type="hidden" name="action" value="save_schedule">
            <?php if ($schedule): ?>
                <input type="hidden" name="schedule_id" value="<?php echo esc_attr($schedule->getId()); ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="booking_type"><?php echo esc_html__('Booking Type', 'classflow-pro'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <select name="booking_type" id="booking_type" required>
                            <option value="group" <?php selected($schedule ? $schedule->getBookingType() : 'group', 'group'); ?>>
                                <?php echo esc_html__('Group Class', 'classflow-pro'); ?>
                            </option>
                            <option value="private" <?php selected($schedule ? $schedule->getBookingType() : '', 'private'); ?>>
                                <?php echo esc_html__('Private Session', 'classflow-pro'); ?>
                            </option>
                            <option value="semi-private" <?php selected($schedule ? $schedule->getBookingType() : '', 'semi-private'); ?>>
                                <?php echo esc_html__('Semi-Private Session', 'classflow-pro'); ?>
                            </option>
                        </select>
                        <p class="description"><?php echo esc_html__('Select the type of booking for this schedule.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="class_id"><?php echo esc_html__('Class', 'classflow-pro'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <select name="class_id" id="class_id" required>
                            <option value=""><?php echo esc_html__('Select a class', 'classflow-pro'); ?></option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo esc_attr($class->getId()); ?>" 
                                    <?php selected($schedule ? $schedule->getClassId() : 0, $class->getId()); ?>
                                    data-capacity="<?php echo esc_attr($class->getCapacity()); ?>"
                                    data-price="<?php echo esc_attr($class->getPrice()); ?>"
                                    data-duration="<?php echo esc_attr($class->getDuration()); ?>">
                                    <?php echo esc_html($class->getName()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="instructor_id"><?php echo esc_html__('Instructor', 'classflow-pro'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <select name="instructor_id" id="instructor_id" required>
                            <option value=""><?php echo esc_html__('Select an instructor', 'classflow-pro'); ?></option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?php echo esc_attr($instructor->ID); ?>" 
                                    <?php selected($schedule ? $schedule->getInstructorId() : 0, $instructor->ID); ?>>
                                    <?php echo esc_html($instructor->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="location_id"><?php echo esc_html__('Location', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select name="location_id" id="location_id">
                            <option value=""><?php echo esc_html__('No location', 'classflow-pro'); ?></option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo esc_attr($location->getId()); ?>" 
                                    <?php selected($schedule ? $schedule->getLocationId() : 0, $location->getId()); ?>>
                                    <?php echo esc_html($location->getName()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="start_date"><?php echo esc_html__('Date', 'classflow-pro'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="date" name="start_date" id="start_date" 
                               value="<?php echo esc_attr($schedule ? $schedule->getStartTime()->format('Y-m-d') : ''); ?>" 
                               required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="start_time"><?php echo esc_html__('Start Time', 'classflow-pro'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="time" name="start_time" id="start_time" 
                               value="<?php echo esc_attr($schedule ? $schedule->getStartTime()->format('H:i') : ''); ?>" 
                               required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="duration"><?php echo esc_html__('Duration (minutes)', 'classflow-pro'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="number" name="duration" id="duration" 
                               value="<?php echo esc_attr($schedule ? $schedule->getDuration() : 60); ?>" 
                               min="15" max="480" step="5" required>
                        <p class="description"><?php echo esc_html__('Duration will be auto-filled based on selected class.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                
                <tr class="group-class-field">
                    <th scope="row">
                        <label for="capacity_override"><?php echo esc_html__('Capacity Override', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="capacity_override" id="capacity_override" 
                               value="<?php echo esc_attr($schedule ? $schedule->getCapacityOverride() : ''); ?>" 
                               min="1" max="500">
                        <p class="description"><?php echo esc_html__('Leave empty to use class default capacity. For private sessions, capacity is always 1.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="price_override"><?php echo esc_html__('Price Override', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <?php $currency = $this->container->get('settings')->get('general.currency', 'USD'); ?>
                        <input type="number" name="price_override" id="price_override" 
                               value="<?php echo esc_attr($schedule ? $schedule->getPriceOverride() : ''); ?>" 
                               min="0" step="0.01">
                        <span><?php echo esc_html($currency); ?></span>
                        <p class="description"><?php echo esc_html__('Leave empty to use class default price.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                
                <tr class="group-class-field">
                    <th scope="row">
                        <label for="recurrence_type"><?php echo esc_html__('Recurrence', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select name="recurrence_type" id="recurrence_type">
                            <option value=""><?php echo esc_html__('One-time (no recurrence)', 'classflow-pro'); ?></option>
                            <option value="daily"><?php echo esc_html__('Daily', 'classflow-pro'); ?></option>
                            <option value="weekly"><?php echo esc_html__('Weekly', 'classflow-pro'); ?></option>
                            <option value="biweekly"><?php echo esc_html__('Bi-weekly', 'classflow-pro'); ?></option>
                            <option value="monthly"><?php echo esc_html__('Monthly', 'classflow-pro'); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__('Recurring schedules are only available for group classes.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                
                <tr class="recurrence-options" style="display: none;">
                    <th scope="row">
                        <label for="recurrence_end"><?php echo esc_html__('Recurrence End Date', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <input type="date" name="recurrence_end" id="recurrence_end" 
                               value="<?php echo esc_attr($schedule && $schedule->getRecurrenceEnd() ? $schedule->getRecurrenceEnd()->format('Y-m-d') : ''); ?>">
                        <p class="description"><?php echo esc_html__('Leave empty for no end date.', 'classflow-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="status"><?php echo esc_html__('Status', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <select name="status" id="status">
                            <option value="scheduled" <?php selected($schedule ? $schedule->getStatus() : 'scheduled', 'scheduled'); ?>>
                                <?php echo esc_html__('Scheduled', 'classflow-pro'); ?>
                            </option>
                            <option value="cancelled" <?php selected($schedule ? $schedule->getStatus() : '', 'cancelled'); ?>>
                                <?php echo esc_html__('Cancelled', 'classflow-pro'); ?>
                            </option>
                            <?php if ($schedule && $schedule->isPast()): ?>
                                <option value="completed" <?php selected($schedule->getStatus(), 'completed'); ?>>
                                    <?php echo esc_html__('Completed', 'classflow-pro'); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" 
                       value="<?php echo $schedule ? esc_attr__('Update Schedule', 'classflow-pro') : esc_attr__('Create Schedule', 'classflow-pro'); ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-schedules')); ?>" class="button">
                    <?php echo esc_html__('Cancel', 'classflow-pro'); ?>
                </a>
            </p>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle booking type changes
            function updateFormFields() {
                var bookingType = $('#booking_type').val();
                
                if (bookingType === 'group') {
                    $('.group-class-field').show();
                    $('#capacity_override').prop('disabled', false);
                } else {
                    $('.group-class-field').hide();
                    $('#recurrence_type').val('').trigger('change');
                    
                    // Set capacity based on booking type
                    if (bookingType === 'private') {
                        $('#capacity_override').val(1).prop('disabled', true);
                    } else if (bookingType === 'semi-private') {
                        $('#capacity_override').val(3).prop('disabled', false);
                    }
                }
            }
            
            $('#booking_type').on('change', updateFormFields);
            
            // Auto-fill duration based on selected class
            $('#class_id').on('change', function() {
                var duration = $(this).find(':selected').data('duration');
                if (duration) {
                    $('#duration').val(duration);
                }
                
                // Update price if needed
                var price = $(this).find(':selected').data('price');
                if (price && !$('#price_override').val()) {
                    $('#price_override').attr('placeholder', 'Default: $' + price);
                }
            });
            
            // Show/hide recurrence options
            $('#recurrence_type').on('change', function() {
                if ($(this).val()) {
                    $('.recurrence-options').show();
                } else {
                    $('.recurrence-options').hide();
                    $('#recurrence_end').val('');
                }
            });
            
            // Trigger on page load
            updateFormFields();
            $('#recurrence_type').trigger('change');
        });
        </script>
        <?php
    }

    private function renderFilters(): void {
        $classRepo = $this->container->get('class_repository');
        $classes = $classRepo->findAll(['status' => 'active']);
        
        $instructorRepo = $this->container->get('instructor_repository');
        $instructors = $instructorRepo->findAll(['status' => 'active']);
        
        $locationRepo = $this->container->get('location_repository');
        $locations = $locationRepo->findAll(['status' => 'active']);
        
        ?>
        <select name="class_id">
            <option value=""><?php echo esc_html__('All Classes', 'classflow-pro'); ?></option>
            <?php foreach ($classes as $class): ?>
                <option value="<?php echo esc_attr($class->getId()); ?>" 
                    <?php selected(isset($_GET['class_id']) ? $_GET['class_id'] : '', $class->getId()); ?>>
                    <?php echo esc_html($class->getName()); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select name="instructor_id">
            <option value=""><?php echo esc_html__('All Instructors', 'classflow-pro'); ?></option>
            <?php foreach ($instructors as $instructor): ?>
                <option value="<?php echo esc_attr($instructor->ID); ?>" 
                    <?php selected(isset($_GET['instructor_id']) ? $_GET['instructor_id'] : '', $instructor->ID); ?>>
                    <?php echo esc_html($instructor->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select name="location_id">
            <option value=""><?php echo esc_html__('All Locations', 'classflow-pro'); ?></option>
            <?php foreach ($locations as $location): ?>
                <option value="<?php echo esc_attr($location->getId()); ?>" 
                    <?php selected(isset($_GET['location_id']) ? $_GET['location_id'] : '', $location->getId()); ?>>
                    <?php echo esc_html($location->getName()); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <select name="status">
            <option value=""><?php echo esc_html__('All Statuses', 'classflow-pro'); ?></option>
            <option value="scheduled" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'scheduled'); ?>>
                <?php echo esc_html__('Scheduled', 'classflow-pro'); ?>
            </option>
            <option value="cancelled" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'cancelled'); ?>>
                <?php echo esc_html__('Cancelled', 'classflow-pro'); ?>
            </option>
            <option value="completed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'completed'); ?>>
                <?php echo esc_html__('Completed', 'classflow-pro'); ?>
            </option>
        </select>
        <?php
    }

    private function handleActions(): void {
        if (!isset($_POST['schedule_nonce']) || !wp_verify_nonce($_POST['schedule_nonce'], 'save_schedule')) {
            wp_die(__('Security check failed', 'classflow-pro'));
        }

        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'save_schedule':
                $this->saveSchedule();
                break;
        }
    }

    private function saveSchedule(): void {
        // Validate required fields
        $required = ['class_id', 'instructor_id', 'start_date', 'start_time', 'duration'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                wp_die(__('Please fill in all required fields.', 'classflow-pro'));
            }
        }
        
        // Create DateTime objects
        $startDateTime = \DateTime::createFromFormat('Y-m-d H:i', $_POST['start_date'] . ' ' . $_POST['start_time']);
        if (!$startDateTime) {
            wp_die(__('Invalid date or time format.', 'classflow-pro'));
        }
        
        $duration = intval($_POST['duration']);
        $endDateTime = clone $startDateTime;
        $endDateTime->modify("+{$duration} minutes");
        
        // Get or create schedule
        $scheduleId = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
        $scheduleRepo = $this->container->get('schedule_repository');
        
        if ($scheduleId) {
            $schedule = $scheduleRepo->find($scheduleId);
            if (!$schedule) {
                wp_die(__('Schedule not found.', 'classflow-pro'));
            }
        } else {
            $schedule = new Schedule(
                intval($_POST['class_id']),
                intval($_POST['instructor_id']),
                $startDateTime,
                $endDateTime
            );
        }
        
        // Update schedule properties
        if (!empty($_POST['location_id'])) {
            $schedule->setLocationId(intval($_POST['location_id']));
        }
        
        if (!empty($_POST['capacity_override'])) {
            $schedule->setCapacityOverride(intval($_POST['capacity_override']));
        }
        
        if (!empty($_POST['price_override'])) {
            $schedule->setPriceOverride(floatval($_POST['price_override']));
        }
        
        $schedule->setStatus(sanitize_text_field($_POST['status'] ?? 'scheduled'));
        $schedule->setBookingType(sanitize_text_field($_POST['booking_type'] ?? 'group'));
        
        // Handle recurrence
        if (!empty($_POST['recurrence_type'])) {
            $recurrenceType = sanitize_text_field($_POST['recurrence_type']);
            $recurrenceRule = $this->buildRecurrenceRule($recurrenceType);
            $schedule->setRecurrenceRule($recurrenceRule);
            
            if (!empty($_POST['recurrence_end'])) {
                $recurrenceEnd = \DateTime::createFromFormat('Y-m-d', $_POST['recurrence_end']);
                if ($recurrenceEnd) {
                    $recurrenceEnd->setTime(23, 59, 59);
                    $schedule->setRecurrenceEnd($recurrenceEnd);
                }
            }
        }
        
        // Save schedule
        $scheduleRepo->save($schedule);
        
        // If recurring, create recurring instances
        if ($schedule->isRecurring() && !$scheduleId) {
            $this->createRecurringInstances($schedule);
        }
        
        // Redirect with success message
        wp_redirect(add_query_arg([
            'page' => 'classflow-pro-schedules',
            'message' => $scheduleId ? 'updated' : 'created'
        ], admin_url('admin.php')));
        exit;
    }

    private function buildRecurrenceRule(string $type): string {
        switch ($type) {
            case 'daily':
                return 'FREQ=DAILY;INTERVAL=1';
            case 'weekly':
                return 'FREQ=WEEKLY;INTERVAL=1';
            case 'biweekly':
                return 'FREQ=WEEKLY;INTERVAL=2';
            case 'monthly':
                return 'FREQ=MONTHLY;INTERVAL=1';
            default:
                return '';
        }
    }

    private function createRecurringInstances(Schedule $parentSchedule): void {
        $recurrenceRule = $parentSchedule->getRecurrenceRule();
        if (!$recurrenceRule) {
            return;
        }
        
        // Parse recurrence rule
        $parts = [];
        foreach (explode(';', $recurrenceRule) as $part) {
            list($key, $value) = explode('=', $part);
            $parts[$key] = $value;
        }
        
        $freq = $parts['FREQ'] ?? '';
        $interval = intval($parts['INTERVAL'] ?? 1);
        
        // Determine date modifier
        $modifier = '';
        switch ($freq) {
            case 'DAILY':
                $modifier = "+{$interval} days";
                break;
            case 'WEEKLY':
                $modifier = "+{$interval} weeks";
                break;
            case 'MONTHLY':
                $modifier = "+{$interval} months";
                break;
        }
        
        if (!$modifier) {
            return;
        }
        
        // Create instances
        $currentStart = clone $parentSchedule->getStartTime();
        $currentEnd = clone $parentSchedule->getEndTime();
        $endDate = $parentSchedule->getRecurrenceEnd() ?: new \DateTime('+1 year');
        
        $scheduleRepo = $this->container->get('schedule_repository');
        $count = 0;
        $maxInstances = 100; // Safety limit
        
        while ($currentStart <= $endDate && $count < $maxInstances) {
            $currentStart->modify($modifier);
            $currentEnd->modify($modifier);
            
            if ($currentStart > $endDate) {
                break;
            }
            
            $instance = new Schedule(
                $parentSchedule->getClassId(),
                $parentSchedule->getInstructorId(),
                clone $currentStart,
                clone $currentEnd
            );
            
            $instance->setLocationId($parentSchedule->getLocationId());
            $instance->setCapacityOverride($parentSchedule->getCapacityOverride());
            $instance->setPriceOverride($parentSchedule->getPriceOverride());
            $instance->setStatus($parentSchedule->getStatus());
            
            $scheduleRepo->save($instance);
            $count++;
        }
    }

    private function displayNotices(): void {
        if (isset($_GET['message'])) {
            $message = '';
            switch ($_GET['message']) {
                case 'created':
                    $message = __('Schedule created successfully.', 'classflow-pro');
                    break;
                case 'updated':
                    $message = __('Schedule updated successfully.', 'classflow-pro');
                    break;
                case 'cancelled':
                    $message = __('Schedule cancelled successfully.', 'classflow-pro');
                    break;
                case 'deleted':
                    $message = __('Schedule deleted successfully.', 'classflow-pro');
                    break;
            }
            
            if ($message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }

    private function getCalendarCellClasses(\DateTime $date, \DateTime $currentMonth, \DateTime $today): string {
        $classes = [];
        
        if ($date->format('Y-m') !== $currentMonth->format('Y-m')) {
            $classes[] = 'other-month';
        }
        
        if ($date->format('Y-m-d') === $today->format('Y-m-d')) {
            $classes[] = 'today';
        }
        
        return implode(' ', $classes);
    }

    private function getClassById(int $id): ?object {
        static $cache = [];
        
        if (!isset($cache[$id])) {
            $classRepo = $this->container->get('class_repository');
            $cache[$id] = $classRepo->find($id);
        }
        
        return $cache[$id];
    }

    private function getInstructorById(int $id): ?object {
        static $cache = [];
        
        if (!isset($cache[$id])) {
            $cache[$id] = get_user_by('id', $id);
        }
        
        return $cache[$id];
    }

    private function getLocationById(int $id): ?object {
        static $cache = [];
        
        if (!isset($cache[$id])) {
            $locationRepo = $this->container->get('location_repository');
            $cache[$id] = $locationRepo->find($id);
        }
        
        return $cache[$id];
    }

    private function getBookingCount(int $scheduleId): int {
        $bookingRepo = $this->container->get('booking_repository');
        return $bookingRepo->count(['schedule_id' => $scheduleId, 'status' => ['confirmed', 'pending']]);
    }
}