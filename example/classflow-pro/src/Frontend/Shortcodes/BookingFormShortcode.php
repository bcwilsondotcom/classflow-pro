<?php
declare(strict_types=1);

namespace ClassFlowPro\Frontend\Shortcodes;

use ClassFlowPro\Services\Container;

class BookingFormShortcode {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(array $atts): string {
        if (!is_user_logged_in()) {
            return '<div class="classflow-alert classflow-alert-info">' . 
                   sprintf(
                       __('Please <a href="%s">log in</a> to book a class.', 'classflow-pro'),
                       wp_login_url(get_permalink())
                   ) . 
                   '</div>';
        }

        $atts = shortcode_atts([
            'schedule_id' => '',
        ], $atts, 'classflow_booking_form');

        if (empty($atts['schedule_id'])) {
            return '<div class="classflow-alert classflow-alert-danger">' . 
                   __('Schedule ID is required.', 'classflow-pro') . 
                   '</div>';
        }

        $scheduleId = (int) $atts['schedule_id'];
        $schedule = $this->container->get('schedule_repository')->find($scheduleId);
        
        if (!$schedule) {
            return '<div class="classflow-alert classflow-alert-danger">' . 
                   __('Schedule not found.', 'classflow-pro') . 
                   '</div>';
        }

        $class = $this->container->get('class_repository')->find($schedule->getClassId());
        $instructor = get_userdata($schedule->getInstructorId());
        $availableSpots = $this->container->get('schedule_repository')->getAvailableSpots($scheduleId);

        ob_start();
        ?>
        <div class="classflow-booking-form">
            <div class="classflow-booking-summary">
                <h3><?php echo esc_html($class->getName()); ?></h3>
                <p><strong><?php esc_html_e('Date & Time:', 'classflow-pro'); ?></strong> 
                   <?php echo esc_html($schedule->getFormattedDateRange()); ?></p>
                <?php if ($instructor): ?>
                    <p><strong><?php esc_html_e('Instructor:', 'classflow-pro'); ?></strong> 
                       <?php echo esc_html($instructor->display_name); ?></p>
                <?php endif; ?>
                <p><strong><?php esc_html_e('Price:', 'classflow-pro'); ?></strong> 
                   <?php echo esc_html($class->getFormattedPrice()); ?></p>
                <p><strong><?php esc_html_e('Available Spots:', 'classflow-pro'); ?></strong> 
                   <?php echo esc_html($availableSpots); ?></p>
            </div>

            <?php if ($availableSpots > 0): ?>
                <form id="classflow-booking-form" method="post">
                    <input type="hidden" name="schedule_id" value="<?php echo esc_attr($scheduleId); ?>">
                    
                    <div class="classflow-form-group">
                        <label for="notes"><?php esc_html_e('Additional Notes (Optional)', 'classflow-pro'); ?></label>
                        <textarea name="notes" id="notes" rows="3" class="classflow-form-control"></textarea>
                        <div class="classflow-form-help">
                            <?php esc_html_e('Any special requirements or requests?', 'classflow-pro'); ?>
                        </div>
                    </div>

                    <div class="classflow-form-actions">
                        <button type="submit" class="classflow-btn classflow-btn-primary classflow-btn-block">
                            <?php esc_html_e('Book Class', 'classflow-pro'); ?>
                        </button>
                    </div>
                </form>

                <div id="classflow-booking-message" style="display: none;"></div>
            <?php else: ?>
                <div class="classflow-alert classflow-alert-warning">
                    <?php esc_html_e('This class is fully booked.', 'classflow-pro'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}