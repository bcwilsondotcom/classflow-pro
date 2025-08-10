<?php
declare(strict_types=1);

namespace ClassFlowPro\Frontend\Shortcodes;

use ClassFlowPro\Services\Container;

class ScheduleShortcode {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(array $atts): string {
        $atts = shortcode_atts([
            'class_id' => '',
            'instructor_id' => '',
            'location_id' => '',
            'view' => 'list', // list or calendar
            'days' => 7,
        ], $atts, 'classflow_schedule');

        $scheduleRepo = $this->container->get('schedule_repository');
        $classRepo = $this->container->get('class_repository');
        
        // Get upcoming schedules
        $startDate = new \DateTime();
        $endDate = clone $startDate;
        $endDate->add(new \DateInterval('P' . $atts['days'] . 'D'));
        
        $filters = ['status' => 'scheduled'];
        if ($atts['class_id']) {
            $filters['class_id'] = (int) $atts['class_id'];
        }
        if ($atts['instructor_id']) {
            $filters['instructor_id'] = (int) $atts['instructor_id'];
        }
        if ($atts['location_id']) {
            $filters['location_id'] = (int) $atts['location_id'];
        }
        
        $schedules = $scheduleRepo->findByDateRange($startDate, $endDate, $filters);
        
        ob_start();
        ?>
        <div class="classflow-schedule-list">
            <?php if (empty($schedules)): ?>
                <p><?php esc_html_e('No upcoming classes scheduled.', 'classflow-pro'); ?></p>
            <?php else: ?>
                <?php foreach ($schedules as $schedule): 
                    $class = $classRepo->find($schedule->getClassId());
                    $instructor = get_userdata($schedule->getInstructorId());
                    $availableSpots = $scheduleRepo->getAvailableSpots($schedule->getId());
                ?>
                    <div class="classflow-schedule-item">
                        <div class="classflow-schedule-header">
                            <div>
                                <h3 class="classflow-schedule-title">
                                    <?php echo esc_html($class->getName()); ?>
                                </h3>
                                <div class="classflow-schedule-datetime">
                                    <?php echo esc_html($schedule->getFormattedDateRange()); ?>
                                </div>
                                <?php if ($instructor): ?>
                                    <div class="classflow-schedule-instructor">
                                        <?php esc_html_e('Instructor:', 'classflow-pro'); ?> 
                                        <?php echo esc_html($instructor->display_name); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="classflow-schedule-price">
                                <?php echo esc_html($class->getFormattedPrice()); ?>
                            </div>
                        </div>
                        
                        <div class="classflow-schedule-availability">
                            <div class="classflow-availability-bar">
                                <div class="classflow-availability-fill <?php echo $availableSpots === 0 ? 'classflow-availability-full' : ''; ?>" 
                                     style="width: <?php echo esc_attr((($class->getCapacity() - $availableSpots) / $class->getCapacity()) * 100); ?>%"></div>
                            </div>
                            <span class="classflow-availability-text">
                                <?php echo sprintf(
                                    esc_html__('%d of %d spots available', 'classflow-pro'),
                                    $availableSpots,
                                    $class->getCapacity()
                                ); ?>
                            </span>
                        </div>
                        
                        <div class="classflow-schedule-actions">
                            <?php if ($availableSpots > 0): ?>
                                <button class="classflow-btn classflow-btn-primary classflow-view-schedule" 
                                        data-schedule-id="<?php echo esc_attr($schedule->getId()); ?>">
                                    <?php esc_html_e('Book Now', 'classflow-pro'); ?>
                                </button>
                            <?php else: ?>
                                <button class="classflow-btn classflow-btn-secondary" disabled>
                                    <?php esc_html_e('Fully Booked', 'classflow-pro'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}