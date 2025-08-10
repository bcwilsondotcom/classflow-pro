<?php
declare(strict_types=1);

namespace ClassFlowPro\Frontend\Shortcodes;

use ClassFlowPro\Services\Container;

class PrivateBookingShortcode {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(array $atts): string {
        if (!is_user_logged_in()) {
            return '<div class="classflow-alert classflow-alert-info">' . 
                   sprintf(
                       __('Please <a href="%s">log in</a> to book a private session.', 'classflow-pro'),
                       wp_login_url(get_permalink())
                   ) . 
                   '</div>';
        }

        $atts = shortcode_atts([
            'class_id' => '',
            'instructor_id' => '',
            'type' => 'private', // private or semi-private
        ], $atts, 'classflow_private_booking');

        ob_start();
        ?>
        <div class="classflow-private-booking">
            <form id="classflow-private-booking-form" method="post">
                <input type="hidden" name="booking_type" value="<?php echo esc_attr($atts['type']); ?>">
                
                <!-- Step 1: Select Class -->
                <div class="booking-step" id="step-class">
                    <h3><?php echo esc_html__('Step 1: Select a Class', 'classflow-pro'); ?></h3>
                    <?php $this->renderClassSelection($atts['class_id']); ?>
                </div>

                <!-- Step 2: Select Instructor -->
                <div class="booking-step" id="step-instructor" style="display: none;">
                    <h3><?php echo esc_html__('Step 2: Select an Instructor', 'classflow-pro'); ?></h3>
                    <div id="instructor-selection">
                        <?php if ($atts['instructor_id']): ?>
                            <?php $this->renderInstructorSelection(intval($atts['instructor_id'])); ?>
                        <?php else: ?>
                            <p class="loading"><?php echo esc_html__('Please select a class first...', 'classflow-pro'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 3: Select Date & Time -->
                <div class="booking-step" id="step-datetime" style="display: none;">
                    <h3><?php echo esc_html__('Step 3: Select Date & Time', 'classflow-pro'); ?></h3>
                    <div id="datetime-selection">
                        <p class="loading"><?php echo esc_html__('Please select an instructor first...', 'classflow-pro'); ?></p>
                    </div>
                </div>

                <!-- Step 4: Confirm Booking -->
                <div class="booking-step" id="step-confirm" style="display: none;">
                    <h3><?php echo esc_html__('Step 4: Confirm Your Booking', 'classflow-pro'); ?></h3>
                    <div id="booking-summary">
                        <!-- Summary will be populated by JavaScript -->
                    </div>

                    <div class="classflow-form-group">
                        <label for="notes"><?php echo esc_html__('Additional Notes (Optional)', 'classflow-pro'); ?></label>
                        <textarea name="notes" id="notes" rows="3" class="classflow-form-control"></textarea>
                        <div class="classflow-form-help">
                            <?php echo esc_html__('Any special requirements or requests?', 'classflow-pro'); ?>
                        </div>
                    </div>

                    <div class="classflow-form-actions">
                        <button type="button" class="classflow-btn classflow-btn-secondary" id="btn-back">
                            <?php echo esc_html__('Back', 'classflow-pro'); ?>
                        </button>
                        <button type="submit" class="classflow-btn classflow-btn-primary">
                            <?php echo esc_html__('Confirm Booking', 'classflow-pro'); ?>
                        </button>
                    </div>
                </div>
            </form>

            <div id="classflow-booking-message" style="display: none;"></div>
        </div>

        <style>
            .classflow-private-booking {
                max-width: 800px;
                margin: 0 auto;
            }

            .booking-step {
                margin-bottom: 30px;
                padding: 20px;
                background: #f9f9f9;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
            }

            .booking-step h3 {
                margin-top: 0;
                margin-bottom: 20px;
                color: #333;
            }

            .class-grid, .instructor-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }

            .class-card, .instructor-card {
                padding: 15px;
                background: #fff;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .class-card:hover, .instructor-card:hover {
                border-color: #2271b1;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .class-card.selected, .instructor-card.selected {
                border-color: #2271b1;
                background: #f0f6fc;
            }

            .class-card h4, .instructor-card h4 {
                margin: 0 0 10px 0;
                font-size: 18px;
            }

            .class-card .duration, .instructor-card .specialty {
                color: #666;
                font-size: 14px;
                margin-bottom: 5px;
            }

            .class-card .price {
                font-weight: bold;
                color: #2271b1;
                font-size: 16px;
            }

            .date-picker {
                margin-bottom: 20px;
            }

            .date-picker label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }

            .date-picker input[type="date"] {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
                width: 100%;
                max-width: 300px;
            }

            .time-slots {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                gap: 10px;
                margin-top: 20px;
            }

            .time-slot {
                padding: 10px;
                text-align: center;
                background: #fff;
                border: 2px solid #e0e0e0;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .time-slot:hover {
                border-color: #2271b1;
            }

            .time-slot.selected {
                border-color: #2271b1;
                background: #2271b1;
                color: #fff;
            }

            .time-slot.unavailable {
                opacity: 0.5;
                cursor: not-allowed;
                background: #f5f5f5;
            }

            .booking-summary {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
                margin-bottom: 20px;
            }

            .booking-summary h4 {
                margin-top: 0;
                margin-bottom: 15px;
            }

            .booking-summary dl {
                margin: 0;
            }

            .booking-summary dt {
                font-weight: 600;
                display: inline-block;
                width: 120px;
            }

            .booking-summary dd {
                display: inline;
                margin: 0;
            }

            .booking-summary dd::after {
                content: "\A";
                white-space: pre;
            }

            .classflow-form-actions {
                display: flex;
                gap: 10px;
                margin-top: 20px;
            }

            .classflow-btn {
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .classflow-btn-primary {
                background: #2271b1;
                color: #fff;
            }

            .classflow-btn-primary:hover {
                background: #135e96;
            }

            .classflow-btn-secondary {
                background: #6c757d;
                color: #fff;
            }

            .classflow-btn-secondary:hover {
                background: #5a6268;
            }

            .loading {
                text-align: center;
                color: #666;
                padding: 20px;
            }

            @media (max-width: 768px) {
                .class-grid, .instructor-grid {
                    grid-template-columns: 1fr;
                }
                
                .time-slots {
                    grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            let selectedClass = null;
            let selectedInstructor = null;
            let selectedDate = null;
            let selectedTime = null;

            // Class selection
            $(document).on('click', '.class-card', function() {
                $('.class-card').removeClass('selected');
                $(this).addClass('selected');
                selectedClass = $(this).data('class-id');
                
                // Load instructors for this class
                loadInstructors(selectedClass);
                $('#step-instructor').slideDown();
                $('#step-datetime').slideUp();
                $('#step-confirm').slideUp();
            });

            // Instructor selection
            $(document).on('click', '.instructor-card', function() {
                $('.instructor-card').removeClass('selected');
                $(this).addClass('selected');
                selectedInstructor = $(this).data('instructor-id');
                
                // Show date selection
                showDateSelection();
                $('#step-datetime').slideDown();
                $('#step-confirm').slideUp();
            });

            // Date selection
            $(document).on('change', '#booking-date', function() {
                selectedDate = $(this).val();
                if (selectedDate) {
                    loadAvailableSlots(selectedInstructor, selectedDate);
                }
            });

            // Time slot selection
            $(document).on('click', '.time-slot:not(.unavailable)', function() {
                $('.time-slot').removeClass('selected');
                $(this).addClass('selected');
                selectedTime = $(this).data('time');
                
                // Show confirmation
                showBookingSummary();
                $('#step-confirm').slideDown();
            });

            // Back button
            $('#btn-back').on('click', function() {
                $('#step-confirm').slideUp();
            });

            // Form submission
            $('#classflow-private-booking-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = {
                    action: 'classflow_book_private_session',
                    nonce: classflowProFrontend.nonce,
                    class_id: selectedClass,
                    instructor_id: selectedInstructor,
                    date: selectedDate,
                    time: selectedTime,
                    booking_type: $('input[name="booking_type"]').val(),
                    notes: $('#notes').val()
                };

                $.ajax({
                    url: classflowProFrontend.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $('#classflow-booking-message')
                                .removeClass('classflow-alert-danger')
                                .addClass('classflow-alert classflow-alert-success')
                                .html(response.data.message)
                                .slideDown();
                            
                            $('#classflow-private-booking-form').slideUp();
                        } else {
                            $('#classflow-booking-message')
                                .removeClass('classflow-alert-success')
                                .addClass('classflow-alert classflow-alert-danger')
                                .html(response.data.message)
                                .slideDown();
                        }
                    }
                });
            });

            function loadInstructors(classId) {
                $('#instructor-selection').html('<p class="loading"><?php echo esc_js(__('Loading instructors...', 'classflow-pro')); ?></p>');
                
                $.ajax({
                    url: classflowProFrontend.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'classflow_get_class_instructors',
                        nonce: classflowProFrontend.nonce,
                        class_id: classId
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#instructor-selection').html(response.data.html);
                        }
                    }
                });
            }

            function showDateSelection() {
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                const maxDate = new Date();
                maxDate.setDate(maxDate.getDate() + 30);

                const html = `
                    <div class="date-picker">
                        <label for="booking-date"><?php echo esc_js(__('Select Date:', 'classflow-pro')); ?></label>
                        <input type="date" id="booking-date" 
                               min="${tomorrow.toISOString().split('T')[0]}" 
                               max="${maxDate.toISOString().split('T')[0]}"
                               required>
                    </div>
                    <div id="available-slots"></div>
                `;
                
                $('#datetime-selection').html(html);
            }

            function loadAvailableSlots(instructorId, date) {
                $('#available-slots').html('<p class="loading"><?php echo esc_js(__('Loading available times...', 'classflow-pro')); ?></p>');
                
                $.ajax({
                    url: classflowProFrontend.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'classflow_get_available_slots',
                        nonce: classflowProFrontend.nonce,
                        instructor_id: instructorId,
                        date: date,
                        class_id: selectedClass
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#available-slots').html(response.data.html);
                        }
                    }
                });
            }

            function showBookingSummary() {
                const classCard = $('.class-card.selected');
                const instructorCard = $('.instructor-card.selected');
                
                const html = `
                    <div class="booking-summary">
                        <h4><?php echo esc_js(__('Booking Summary', 'classflow-pro')); ?></h4>
                        <dl>
                            <dt><?php echo esc_js(__('Class:', 'classflow-pro')); ?></dt>
                            <dd>${classCard.find('h4').text()}</dd>
                            
                            <dt><?php echo esc_js(__('Instructor:', 'classflow-pro')); ?></dt>
                            <dd>${instructorCard.find('h4').text()}</dd>
                            
                            <dt><?php echo esc_js(__('Date:', 'classflow-pro')); ?></dt>
                            <dd>${new Date(selectedDate).toLocaleDateString()}</dd>
                            
                            <dt><?php echo esc_js(__('Time:', 'classflow-pro')); ?></dt>
                            <dd>${selectedTime}</dd>
                            
                            <dt><?php echo esc_js(__('Price:', 'classflow-pro')); ?></dt>
                            <dd>${classCard.find('.price').text()}</dd>
                        </dl>
                    </div>
                `;
                
                $('#booking-summary').html(html);
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    private function renderClassSelection(?string $preselectedClassId = null): void {
        $classRepo = $this->container->get('class_repository');
        $classes = $classRepo->findAll(['status' => 'active']);
        
        // Filter to only show classes that support flexible scheduling
        $flexibleClasses = array_filter($classes, function($class) {
            return $class->getSchedulingType() === 'flexible';
        });

        if (empty($flexibleClasses)) {
            echo '<p>' . esc_html__('No classes available for private booking at this time.', 'classflow-pro') . '</p>';
            return;
        }

        ?>
        <div class="class-grid">
            <?php foreach ($flexibleClasses as $class): ?>
                <div class="class-card <?php echo $preselectedClassId == $class->getId() ? 'selected' : ''; ?>" 
                     data-class-id="<?php echo esc_attr($class->getId()); ?>">
                    <h4><?php echo esc_html($class->getName()); ?></h4>
                    <div class="duration">
                        <?php echo esc_html($class->getDurationFormatted()); ?>
                    </div>
                    <?php if ($class->getDescription()): ?>
                        <div class="description">
                            <?php echo wp_trim_words(esc_html($class->getDescription()), 15); ?>
                        </div>
                    <?php endif; ?>
                    <div class="price">
                        <?php echo esc_html($class->getFormattedPrice()); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function renderInstructorSelection(int $instructorId): void {
        $instructor = get_user_by('id', $instructorId);
        if (!$instructor) {
            echo '<p>' . esc_html__('Instructor not found.', 'classflow-pro') . '</p>';
            return;
        }

        ?>
        <div class="instructor-grid">
            <div class="instructor-card selected" data-instructor-id="<?php echo esc_attr($instructorId); ?>">
                <h4><?php echo esc_html($instructor->display_name); ?></h4>
                <?php 
                $bio = get_user_meta($instructorId, 'instructor_bio', true);
                if ($bio): ?>
                    <div class="bio">
                        <?php echo wp_trim_words(esc_html($bio), 20); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}