<?php
declare(strict_types=1);

namespace ClassFlowPro\Admin\Pages;

use ClassFlowPro\Services\Container;

class InstructorAvailabilityPage {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
        
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleActions();
        }
    }

    public function render(): void {
        $currentUserId = get_current_user_id();
        $isAdmin = current_user_can('manage_classflow_instructors');
        $instructorId = $isAdmin && isset($_GET['instructor_id']) ? intval($_GET['instructor_id']) : $currentUserId;
        
        // Verify instructor role
        $user = get_user_by('id', $instructorId);
        if (!$user || (!in_array('instructor', $user->roles) && !in_array('administrator', $user->roles))) {
            wp_die(__('Invalid instructor ID.', 'classflow-pro'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Instructor Availability', 'classflow-pro'); ?></h1>
            
            <?php if ($isAdmin): ?>
                <div class="instructor-selector">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="classflow-pro-instructor-availability">
                        <label for="instructor_id"><?php echo esc_html__('Select Instructor:', 'classflow-pro'); ?></label>
                        <select name="instructor_id" id="instructor_id" onchange="this.form.submit()">
                            <?php
                            $instructorRepo = $this->container->get('instructor_repository');
                            $instructors = $instructorRepo->findAll();
                            foreach ($instructors as $instructor):
                                ?>
                                <option value="<?php echo esc_attr($instructor->ID); ?>" 
                                    <?php selected($instructorId, $instructor->ID); ?>>
                                    <?php echo esc_html($instructor->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <hr>
            <?php endif; ?>
            
            <?php $this->displayNotices(); ?>
            
            <div class="classflow-availability-container">
                <div class="availability-grid">
                    <div class="availability-settings">
                        <h2><?php echo esc_html__('Weekly Availability', 'classflow-pro'); ?></h2>
                        <p class="description">
                            <?php echo esc_html__('Set your general availability for each day of the week. Students will be able to book private sessions during these times.', 'classflow-pro'); ?>
                        </p>
                        
                        <?php $this->renderAvailabilityForm($instructorId); ?>
                    </div>
                    
                    <div class="availability-preview">
                        <h2><?php echo esc_html__('Availability Preview', 'classflow-pro'); ?></h2>
                        <div class="preview-calendar">
                            <?php $this->renderAvailabilityPreview($instructorId); ?>
                        </div>
                    </div>
                </div>
                
                <div class="time-off-section">
                    <h2><?php echo esc_html__('Time Off / Blocked Dates', 'classflow-pro'); ?></h2>
                    <p class="description">
                        <?php echo esc_html__('Block specific dates when you are not available for bookings.', 'classflow-pro'); ?>
                    </p>
                    <?php $this->renderTimeOffSection($instructorId); ?>
                </div>
            </div>
        </div>
        
        <style>
            .classflow-availability-container {
                margin-top: 20px;
            }
            
            .availability-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin-bottom: 30px;
            }
            
            @media (max-width: 1200px) {
                .availability-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            .availability-settings {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            
            .availability-preview {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            
            .day-availability {
                margin-bottom: 20px;
                padding: 15px;
                background: #f8f9fa;
                border: 1px solid #e2e4e7;
                border-radius: 4px;
            }
            
            .day-availability.unavailable {
                opacity: 0.6;
            }
            
            .day-header {
                display: flex;
                align-items: center;
                margin-bottom: 10px;
            }
            
            .day-header h3 {
                margin: 0 15px 0 0;
                font-size: 16px;
            }
            
            .time-slots {
                display: flex;
                gap: 15px;
                align-items: center;
                margin-top: 10px;
            }
            
            .time-slots input[type="time"] {
                width: 120px;
            }
            
            .time-off-section {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            
            .time-off-list {
                margin-top: 20px;
            }
            
            .time-off-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px;
                background: #f8f9fa;
                border: 1px solid #e2e4e7;
                margin-bottom: 10px;
                border-radius: 4px;
            }
            
            .preview-day {
                margin-bottom: 15px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            
            .preview-day h4 {
                margin: 0 0 5px 0;
                font-size: 14px;
                font-weight: 600;
            }
            
            .preview-slots {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 10px;
            }
            
            .time-slot {
                padding: 5px 10px;
                background: #e3f2fd;
                border: 1px solid #90caf9;
                border-radius: 3px;
                font-size: 12px;
            }
            
            .time-slot.booked {
                background: #ffebee;
                border-color: #ef9a9a;
                text-decoration: line-through;
            }
            
            .instructor-selector {
                margin-bottom: 20px;
                padding: 15px;
                background: #f0f0f1;
                border-radius: 4px;
            }
            
            .instructor-selector label {
                font-weight: 600;
                margin-right: 10px;
            }
        </style>
        <?php
    }

    private function renderAvailabilityForm(int $instructorId): void {
        $availability = get_user_meta($instructorId, 'instructor_availability', true) ?: [];
        $settings = $this->container->get('settings');
        $weekStartsOn = $settings->get('general.week_starts_on', 1);
        
        $daysOfWeek = [
            'monday' => __('Monday', 'classflow-pro'),
            'tuesday' => __('Tuesday', 'classflow-pro'),
            'wednesday' => __('Wednesday', 'classflow-pro'),
            'thursday' => __('Thursday', 'classflow-pro'),
            'friday' => __('Friday', 'classflow-pro'),
            'saturday' => __('Saturday', 'classflow-pro'),
            'sunday' => __('Sunday', 'classflow-pro'),
        ];
        
        // Reorder days based on week start setting
        if ($weekStartsOn === 0) { // Sunday
            $sunday = ['sunday' => $daysOfWeek['sunday']];
            unset($daysOfWeek['sunday']);
            $daysOfWeek = $sunday + $daysOfWeek;
        }
        
        ?>
        <form method="post" action="" id="availability-form">
            <?php wp_nonce_field('save_availability', 'availability_nonce'); ?>
            <input type="hidden" name="action" value="save_availability">
            <input type="hidden" name="instructor_id" value="<?php echo esc_attr($instructorId); ?>">
            
            <?php foreach ($daysOfWeek as $dayKey => $dayName): 
                $dayAvailability = $availability[$dayKey] ?? ['available' => false, 'start_time' => '09:00', 'end_time' => '17:00'];
                ?>
                <div class="day-availability <?php echo !$dayAvailability['available'] ? 'unavailable' : ''; ?>">
                    <div class="day-header">
                        <h3><?php echo esc_html($dayName); ?></h3>
                        <label>
                            <input type="checkbox" 
                                   name="availability[<?php echo esc_attr($dayKey); ?>][available]" 
                                   value="1" 
                                   <?php checked($dayAvailability['available']); ?>
                                   class="day-toggle">
                            <?php echo esc_html__('Available', 'classflow-pro'); ?>
                        </label>
                    </div>
                    
                    <div class="time-slots" <?php echo !$dayAvailability['available'] ? 'style="display:none;"' : ''; ?>>
                        <label>
                            <?php echo esc_html__('From:', 'classflow-pro'); ?>
                            <input type="time" 
                                   name="availability[<?php echo esc_attr($dayKey); ?>][start_time]" 
                                   value="<?php echo esc_attr($dayAvailability['start_time']); ?>">
                        </label>
                        
                        <label>
                            <?php echo esc_html__('To:', 'classflow-pro'); ?>
                            <input type="time" 
                                   name="availability[<?php echo esc_attr($dayKey); ?>][end_time]" 
                                   value="<?php echo esc_attr($dayAvailability['end_time']); ?>">
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" 
                       value="<?php echo esc_attr__('Save Availability', 'classflow-pro'); ?>">
            </p>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            $('.day-toggle').on('change', function() {
                const $container = $(this).closest('.day-availability');
                const $timeSlots = $container.find('.time-slots');
                
                if ($(this).is(':checked')) {
                    $container.removeClass('unavailable');
                    $timeSlots.slideDown();
                } else {
                    $container.addClass('unavailable');
                    $timeSlots.slideUp();
                }
            });
        });
        </script>
        <?php
    }

    private function renderAvailabilityPreview(int $instructorId): void {
        $instructorRepo = $this->container->get('instructor_repository');
        $today = new \DateTime();
        
        // Show next 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = clone $today;
            $date->modify("+{$i} days");
            
            $availableSlots = $instructorRepo->getAvailableSlots($instructorId, $date, 60);
            $bookedSlots = $instructorRepo->getBookedSlots($instructorId, $date);
            
            ?>
            <div class="preview-day">
                <h4><?php echo esc_html($date->format('l, F j')); ?></h4>
                
                <?php if (empty($availableSlots)): ?>
                    <p class="no-availability"><?php echo esc_html__('No availability', 'classflow-pro'); ?></p>
                <?php else: ?>
                    <div class="preview-slots">
                        <?php foreach ($availableSlots as $slot): 
                            $isBooked = false;
                            foreach ($bookedSlots as $booked) {
                                if ($slot['start'] == $booked['start']) {
                                    $isBooked = true;
                                    break;
                                }
                            }
                            ?>
                            <span class="time-slot <?php echo $isBooked ? 'booked' : ''; ?>">
                                <?php echo esc_html($slot['start']->format('g:i A')); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    private function renderTimeOffSection(int $instructorId): void {
        $timeOffDates = get_user_meta($instructorId, 'instructor_time_off', true) ?: [];
        
        ?>
        <form method="post" action="" id="time-off-form">
            <?php wp_nonce_field('save_time_off', 'time_off_nonce'); ?>
            <input type="hidden" name="action" value="save_time_off">
            <input type="hidden" name="instructor_id" value="<?php echo esc_attr($instructorId); ?>">
            
            <div class="add-time-off">
                <h3><?php echo esc_html__('Add Time Off', 'classflow-pro'); ?></h3>
                <div style="display: flex; gap: 15px; align-items: end;">
                    <label>
                        <?php echo esc_html__('Start Date:', 'classflow-pro'); ?><br>
                        <input type="date" name="time_off_start" required>
                    </label>
                    
                    <label>
                        <?php echo esc_html__('End Date:', 'classflow-pro'); ?><br>
                        <input type="date" name="time_off_end" required>
                    </label>
                    
                    <label>
                        <?php echo esc_html__('Reason:', 'classflow-pro'); ?><br>
                        <input type="text" name="time_off_reason" placeholder="<?php echo esc_attr__('Optional', 'classflow-pro'); ?>">
                    </label>
                    
                    <input type="submit" class="button" value="<?php echo esc_attr__('Add Time Off', 'classflow-pro'); ?>">
                </div>
            </div>
            
            <?php if (!empty($timeOffDates)): ?>
                <div class="time-off-list">
                    <h3><?php echo esc_html__('Scheduled Time Off', 'classflow-pro'); ?></h3>
                    <?php foreach ($timeOffDates as $index => $timeOff): 
                        $start = new \DateTime($timeOff['start']);
                        $end = new \DateTime($timeOff['end']);
                        ?>
                        <div class="time-off-item">
                            <div>
                                <strong><?php echo esc_html($start->format('F j, Y')); ?></strong>
                                <?php if ($start != $end): ?>
                                    - <strong><?php echo esc_html($end->format('F j, Y')); ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($timeOff['reason'])): ?>
                                    <br><em><?php echo esc_html($timeOff['reason']); ?></em>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="remove_time_off" value="<?php echo esc_attr($index); ?>" 
                                    class="button button-small"
                                    onclick="return confirm('<?php echo esc_attr__('Are you sure you want to remove this time off?', 'classflow-pro'); ?>');">
                                <?php echo esc_html__('Remove', 'classflow-pro'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </form>
        <?php
    }

    private function handleActions(): void {
        if (!isset($_POST['action'])) {
            return;
        }

        $action = sanitize_text_field($_POST['action']);
        
        switch ($action) {
            case 'save_availability':
                if (!wp_verify_nonce($_POST['availability_nonce'] ?? '', 'save_availability')) {
                    wp_die(__('Security check failed', 'classflow-pro'));
                }
                $this->saveAvailability();
                break;
                
            case 'save_time_off':
                if (!wp_verify_nonce($_POST['time_off_nonce'] ?? '', 'save_time_off')) {
                    wp_die(__('Security check failed', 'classflow-pro'));
                }
                $this->saveTimeOff();
                break;
        }
    }

    private function saveAvailability(): void {
        $instructorId = intval($_POST['instructor_id']);
        
        // Verify permissions
        if (!current_user_can('manage_classflow_instructors') && get_current_user_id() !== $instructorId) {
            wp_die(__('You do not have permission to edit this availability.', 'classflow-pro'));
        }
        
        $availability = $_POST['availability'] ?? [];
        $cleanAvailability = [];
        
        foreach ($availability as $day => $settings) {
            $cleanAvailability[$day] = [
                'available' => !empty($settings['available']),
                'start_time' => sanitize_text_field($settings['start_time'] ?? '09:00'),
                'end_time' => sanitize_text_field($settings['end_time'] ?? '17:00'),
            ];
        }
        
        update_user_meta($instructorId, 'instructor_availability', $cleanAvailability);
        
        wp_redirect(add_query_arg([
            'page' => 'classflow-pro-instructor-availability',
            'instructor_id' => $instructorId,
            'message' => 'availability_saved'
        ], admin_url('admin.php')));
        exit;
    }

    private function saveTimeOff(): void {
        $instructorId = intval($_POST['instructor_id']);
        
        // Verify permissions
        if (!current_user_can('manage_classflow_instructors') && get_current_user_id() !== $instructorId) {
            wp_die(__('You do not have permission to edit this availability.', 'classflow-pro'));
        }
        
        $timeOffDates = get_user_meta($instructorId, 'instructor_time_off', true) ?: [];
        
        if (isset($_POST['remove_time_off'])) {
            // Remove time off
            $index = intval($_POST['remove_time_off']);
            if (isset($timeOffDates[$index])) {
                unset($timeOffDates[$index]);
                $timeOffDates = array_values($timeOffDates); // Re-index array
            }
        } else {
            // Add time off
            $start = sanitize_text_field($_POST['time_off_start'] ?? '');
            $end = sanitize_text_field($_POST['time_off_end'] ?? '');
            $reason = sanitize_text_field($_POST['time_off_reason'] ?? '');
            
            if ($start && $end) {
                $timeOffDates[] = [
                    'start' => $start,
                    'end' => $end,
                    'reason' => $reason,
                ];
            }
        }
        
        update_user_meta($instructorId, 'instructor_time_off', $timeOffDates);
        
        wp_redirect(add_query_arg([
            'page' => 'classflow-pro-instructor-availability',
            'instructor_id' => $instructorId,
            'message' => 'time_off_saved'
        ], admin_url('admin.php')));
        exit;
    }

    private function displayNotices(): void {
        if (!isset($_GET['message'])) {
            return;
        }

        $message = '';
        switch ($_GET['message']) {
            case 'availability_saved':
                $message = __('Availability settings saved successfully.', 'classflow-pro');
                break;
            case 'time_off_saved':
                $message = __('Time off updated successfully.', 'classflow-pro');
                break;
        }

        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}