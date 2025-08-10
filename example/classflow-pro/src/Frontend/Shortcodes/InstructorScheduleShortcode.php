<?php
declare(strict_types=1);

namespace ClassFlowPro\Frontend\Shortcodes;

use ClassFlowPro\Services\Container;

class InstructorScheduleShortcode {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(array $atts): string {
        $atts = shortcode_atts([
            'instructor_id' => '',
            'days' => 7,
            'show_capacity' => 'yes',
        ], $atts, 'classflow_instructor_schedule');

        // If no instructor ID provided, use current user if they're an instructor
        if (empty($atts['instructor_id']) && is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array('classflow_instructor', $user->roles) || in_array('administrator', $user->roles)) {
                $atts['instructor_id'] = $user->ID;
            }
        }

        if (empty($atts['instructor_id'])) {
            return '<div class="classflow-alert classflow-alert-danger">' . 
                   __('Instructor ID is required.', 'classflow-pro') . 
                   '</div>';
        }

        $instructorId = (int) $atts['instructor_id'];
        $instructor = get_userdata($instructorId);
        
        if (!$instructor) {
            return '<div class="classflow-alert classflow-alert-danger">' . 
                   __('Instructor not found.', 'classflow-pro') . 
                   '</div>';
        }

        $scheduleRepo = $this->container->get('schedule_repository');
        $classRepo = $this->container->get('class_repository');
        $bookingRepo = $this->container->get('booking_repository');
        
        // Get upcoming schedules for this instructor
        $startDate = new \DateTime();
        $endDate = clone $startDate;
        $endDate->add(new \DateInterval('P' . $atts['days'] . 'D'));
        
        $schedules = $scheduleRepo->findByInstructor($instructorId, $startDate, $endDate);

        ob_start();
        ?>
        <div class="classflow-instructor-schedule">
            <h2><?php echo sprintf(
                esc_html__('Schedule for %s', 'classflow-pro'),
                esc_html($instructor->display_name)
            ); ?></h2>

            <?php if (empty($schedules)): ?>
                <div class="classflow-alert classflow-alert-info">
                    <?php esc_html_e('No upcoming classes scheduled.', 'classflow-pro'); ?>
                </div>
            <?php else: ?>
                <div class="classflow-schedule-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date & Time', 'classflow-pro'); ?></th>
                                <th><?php esc_html_e('Class', 'classflow-pro'); ?></th>
                                <th><?php esc_html_e('Location', 'classflow-pro'); ?></th>
                                <?php if ($atts['show_capacity'] === 'yes'): ?>
                                    <th><?php esc_html_e('Bookings', 'classflow-pro'); ?></th>
                                <?php endif; ?>
                                <th><?php esc_html_e('Status', 'classflow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schedules as $schedule): 
                                $class = $classRepo->find($schedule->getClassId());
                                $location = null;
                                if ($schedule->getLocationId()) {
                                    $locationRepo = $this->container->get('location_repository');
                                    $location = $locationRepo->find($schedule->getLocationId());
                                }
                                $bookingCount = $bookingRepo->getBookingCountForSchedule($schedule->getId());
                                $capacity = $schedule->getCapacityOverride() ?: $class->getCapacity();
                            ?>
                                <tr>
                                    <td><?php echo esc_html($schedule->getFormattedDateRange()); ?></td>
                                    <td><?php echo esc_html($class->getName()); ?></td>
                                    <td><?php echo $location ? esc_html($location->getName()) : '-'; ?></td>
                                    <?php if ($atts['show_capacity'] === 'yes'): ?>
                                        <td>
                                            <?php echo sprintf(
                                                esc_html__('%d / %d', 'classflow-pro'),
                                                $bookingCount,
                                                $capacity
                                            ); ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="classflow-status-badge status-<?php echo esc_attr($schedule->getStatus()); ?>">
                                            <?php echo esc_html(ucfirst($schedule->getStatus())); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}