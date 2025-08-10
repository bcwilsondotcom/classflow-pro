<?php
declare(strict_types=1);

namespace ClassFlowPro\Frontend\Shortcodes;

use ClassFlowPro\Services\Container;

class MyBookingsShortcode {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(array $atts): string {
        if (!is_user_logged_in()) {
            return '<div class="classflow-alert classflow-alert-info">' . 
                   sprintf(
                       __('Please <a href="%s">log in</a> to view your bookings.', 'classflow-pro'),
                       wp_login_url(get_permalink())
                   ) . 
                   '</div>';
        }

        $atts = shortcode_atts([
            'view' => 'upcoming', // upcoming, past, all
            'limit' => 10,
        ], $atts, 'classflow_my_bookings');

        $bookingRepo = $this->container->get('booking_repository');
        $scheduleRepo = $this->container->get('schedule_repository');
        $classRepo = $this->container->get('class_repository');
        
        $studentId = get_current_user_id();
        
        if ($atts['view'] === 'upcoming') {
            $bookings = $bookingRepo->getUpcomingBookings($studentId, (int) $atts['limit']);
        } elseif ($atts['view'] === 'past') {
            $bookings = $bookingRepo->getPastBookings($studentId, (int) $atts['limit']);
        } else {
            $bookings = $bookingRepo->findByStudent($studentId);
        }

        ob_start();
        ?>
        <div class="classflow-bookings-list">
            <div class="classflow-bookings-header">
                <h2><?php esc_html_e('My Bookings', 'classflow-pro'); ?></h2>
                <div class="classflow-bookings-filters">
                    <a href="?view=upcoming" class="<?php echo $atts['view'] === 'upcoming' ? 'active' : ''; ?>">
                        <?php esc_html_e('Upcoming', 'classflow-pro'); ?>
                    </a>
                    <a href="?view=past" class="<?php echo $atts['view'] === 'past' ? 'active' : ''; ?>">
                        <?php esc_html_e('Past', 'classflow-pro'); ?>
                    </a>
                    <a href="?view=all" class="<?php echo $atts['view'] === 'all' ? 'active' : ''; ?>">
                        <?php esc_html_e('All', 'classflow-pro'); ?>
                    </a>
                </div>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="classflow-alert classflow-alert-info">
                    <?php esc_html_e('You have no bookings yet.', 'classflow-pro'); ?>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): 
                    $schedule = $scheduleRepo->find($booking->getScheduleId());
                    if (!$schedule) continue;
                    
                    $class = $classRepo->find($schedule->getClassId());
                    $instructor = get_userdata($schedule->getInstructorId());
                ?>
                    <div class="classflow-booking-item">
                        <div class="classflow-booking-header">
                            <div>
                                <h3><?php echo esc_html($class->getName()); ?></h3>
                                <div class="classflow-booking-meta">
                                    <span class="classflow-booking-code">
                                        <?php echo esc_html($booking->getBookingCode()); ?>
                                    </span>
                                    <span class="classflow-booking-status booking-status-<?php echo esc_attr($booking->getStatus()); ?>">
                                        <?php echo esc_html($booking->getStatusLabel()); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="classflow-booking-price">
                                <?php echo esc_html($booking->getFormattedAmount()); ?>
                            </div>
                        </div>

                        <div class="classflow-booking-details">
                            <p><strong><?php esc_html_e('Date & Time:', 'classflow-pro'); ?></strong> 
                               <?php echo esc_html($schedule->getFormattedDateRange()); ?></p>
                            <?php if ($instructor): ?>
                                <p><strong><?php esc_html_e('Instructor:', 'classflow-pro'); ?></strong> 
                                   <?php echo esc_html($instructor->display_name); ?></p>
                            <?php endif; ?>
                            <p><strong><?php esc_html_e('Booked on:', 'classflow-pro'); ?></strong> 
                               <?php echo esc_html($booking->getCreatedAt()->format('F j, Y')); ?></p>
                        </div>

                        <?php if ($booking->canBeCancelled() && $schedule->isUpcoming()): ?>
                            <div class="classflow-booking-actions">
                                <button class="classflow-btn classflow-btn-danger classflow-cancel-booking" 
                                        data-booking-id="<?php echo esc_attr($booking->getId()); ?>">
                                    <?php esc_html_e('Cancel Booking', 'classflow-pro'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}