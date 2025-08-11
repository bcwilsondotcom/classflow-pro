<?php
namespace ClassFlowPro\Admin;

class Schedules
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;

        // Full-screen calendar view with side scheduler panel
        wp_enqueue_style('cfp-frontend');
        wp_enqueue_script('cfp-admin-schedules', CFP_PLUGIN_URL . 'assets/js/admin-schedules.js', ['jquery'], '1.0.0', true);
        wp_localize_script('cfp-admin-schedules', 'CFP_ADMIN', [
            'restUrl' => esc_url_raw(rest_url('classflow/v1/')),
            'adminRestUrl' => esc_url_raw(rest_url('classflow-pro/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'timezone' => \ClassFlowPro\Admin\Settings::get('business_timezone', (function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC')),
            'presetClassId' => isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0,
        ]);

        echo '<div class="wrap cfp-admin-schedules">';
        echo '<h1>' . esc_html__('Schedules', 'classflow-pro') . '</h1>';
        echo '<div class="cfp-sched-layout" style="display:grid;grid-template-columns:1fr 340px;gap:16px;min-height:70vh;">';
        // Calendar
        echo '<div class="cfp-sched-calendar">';
        echo '<div class="cfp-sched-toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">';
        echo '<div><button class="button cfp-cal-prev">' . esc_html__('Prev', 'classflow-pro') . '</button> ';
        echo '<strong class="cfp-cal-title"></strong> ';
        echo '<button class="button cfp-cal-next">' . esc_html__('Next', 'classflow-pro') . '</button></div>';
        echo '<div class="cfp-cal-filters">';
        echo '<select class="cfp-filter-class"><option value="">' . esc_html__('All Classes', 'classflow-pro') . '</option></select> ';
        echo '<select class="cfp-filter-instructor"><option value="">' . esc_html__('All Instructors', 'classflow-pro') . '</option></select> ';
        echo '<select class="cfp-filter-location"><option value="">' . esc_html__('All Locations', 'classflow-pro') . '</option></select>';
        echo '</div>';
        echo '</div>';
        echo '<div class="cfp-cal-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;"></div>';
        echo '</div>';

        // Side panel
        echo '<div class="cfp-sched-panel" style="border:1px solid #e2e8f0;border-radius:8px;padding:12px;">';
        echo '<h2 style="margin-top:0;">' . esc_html__('Schedule Classes', 'classflow-pro') . '</h2>';
        echo '<div class="cfp-form">';
        echo '<p><label>' . esc_html__('Class', 'classflow-pro') . '<br><select class="cfp-form-class" style="width:100%"></select></label></p>';
        echo '<p><label>' . esc_html__('Instructor', 'classflow-pro') . '<br><select class="cfp-form-instructor" style="width:100%"><option value="">' . esc_html__('— None —', 'classflow-pro') . '</option></select></label></p>';
        echo '<p><label>' . esc_html__('Location', 'classflow-pro') . '<br><select class="cfp-form-location" style="width:100%"></select></label></p>';
        echo '<p><label>' . esc_html__('Start Date', 'classflow-pro') . ' <input type="date" class="cfp-form-start" style="width:100%"></label></p>';
        echo '<p><label>' . esc_html__('End Date (optional)', 'classflow-pro') . ' <input type="date" class="cfp-form-end" style="width:100%" placeholder="' . esc_attr__('None', 'classflow-pro') . '"></label></p>';
        echo '<div class="cfp-dow" style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;">';
        foreach ([['mon','Monday'],['tue','Tuesday'],['wed','Wednesday'],['thu','Thursday'],['fri','Friday'],['sat','Saturday'],['sun','Sunday']] as $d) {
            echo '<label><input type="checkbox" class="cfp-dow-ck" value="' . esc_attr($d[0]) . '"> ' . esc_html__($d[1], 'classflow-pro') . ' <input type="time" class="cfp-time-' . esc_attr($d[0]) . '" style="margin-left:6px;width:120px" disabled></label>';
        }
        echo '</div>';
        echo '<p><label><input type="checkbox" class="cfp-form-private"> ' . esc_html__('Private session', 'classflow-pro') . '</label></p>';
        echo '<p><button class="button button-primary cfp-form-create">' . esc_html__('Create', 'classflow-pro') . '</button> <span class="cfp-msg" style="margin-left:8px;"></span></p>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // layout
        echo '</div>'; // wrap
    }
    
    private static function render_add_form($preset_class_id = 0): void
    {
        global $wpdb;
        
        // Get data for dropdowns
        $classes = $wpdb->get_results("SELECT id, name, duration_mins, capacity, price_cents, currency, default_location_id FROM {$wpdb->prefix}cfp_classes WHERE status='active' ORDER BY name ASC", ARRAY_A);
        $instructors = $wpdb->get_results("SELECT id, name, availability_weekly FROM {$wpdb->prefix}cfp_instructors ORDER BY name ASC", ARRAY_A);
        $locations = $wpdb->get_results("SELECT id, name, timezone FROM {$wpdb->prefix}cfp_locations ORDER BY name ASC", ARRAY_A);
        $resources = $wpdb->get_results("SELECT id, name, type, location_id, capacity FROM {$wpdb->prefix}cfp_resources ORDER BY location_id, name ASC", ARRAY_A);
        
        // Get default values from preset class
        $default_values = [];
        if ($preset_class_id) {
            foreach ($classes as $class) {
                if ($class['id'] == $preset_class_id) {
                    $default_values = $class;
                    break;
                }
            }
        }
        ?>
        
        <div class="cfp-schedule-form">
            <h2><?php esc_html_e('Schedule Class', 'classflow-pro'); ?></h2>
            
            <form method="post" id="cfp-schedule-form">
                <?php wp_nonce_field('cfp_add_schedule'); ?>
                <input type="hidden" name="cfp_action" value="add_schedule"/>
                
                <table class="form-table">
                    <!-- Class Selection -->
                    <tr>
                        <th><label for="class_id"><?php esc_html_e('Class', 'classflow-pro'); ?> <span class="required">*</span></label></th>
                        <td>
                            <select name="class_id" id="class_id" required class="regular-text">
                                <option value=""><?php esc_html_e('Select a class', 'classflow-pro'); ?></option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo esc_attr($class['id']); ?>" 
                                            data-duration="<?php echo esc_attr($class['duration_mins']); ?>"
                                            data-capacity="<?php echo esc_attr($class['capacity']); ?>"
                                            data-price="<?php echo esc_attr($class['price_cents']); ?>"
                                            data-currency="<?php echo esc_attr($class['currency']); ?>"
                                            data-location="<?php echo esc_attr($class['default_location_id']); ?>"
                                            <?php selected($preset_class_id, $class['id']); ?>>
                                        <?php echo esc_html($class['name']); ?>
                                        (<?php echo esc_html($class['duration_mins']); ?> min, 
                                        <?php echo esc_html(number_format($class['price_cents']/100, 2)); ?> <?php echo esc_html(strtoupper($class['currency'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Select the class template to schedule', 'classflow-pro'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Location Selection -->
                    <tr>
                        <th><label for="location_id"><?php esc_html_e('Location', 'classflow-pro'); ?> <span class="required">*</span></label></th>
                        <td>
                            <select name="location_id" id="location_id" required class="regular-text">
                                <option value=""><?php esc_html_e('Select location', 'classflow-pro'); ?></option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo esc_attr($location['id']); ?>" 
                                            data-timezone="<?php echo esc_attr($location['timezone']); ?>"
                                            <?php selected($default_values['default_location_id'] ?? 0, $location['id']); ?>>
                                        <?php echo esc_html($location['name']); ?>
                                        (<?php echo esc_html($location['timezone']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Instructor Selection -->
                    <tr>
                        <th><label for="instructor_id"><?php esc_html_e('Instructor', 'classflow-pro'); ?></label></th>
                        <td>
                            <select name="instructor_id" id="instructor_id" class="regular-text">
                                <option value=""><?php esc_html_e('No instructor assigned', 'classflow-pro'); ?></option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?php echo esc_attr($instructor['id']); ?>"
                                            data-availability='<?php echo esc_attr($instructor['availability_weekly']); ?>'>
                                        <?php echo esc_html($instructor['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="instructor-availability-info" style="display:none;margin-top:10px;padding:10px;background:#f0f0f0;border-radius:4px;">
                                <strong><?php esc_html_e('Instructor Availability:', 'classflow-pro'); ?></strong>
                                <div id="availability-display"></div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Resource Selection -->
                    <tr>
                        <th><label for="resource_id"><?php esc_html_e('Resource/Equipment', 'classflow-pro'); ?></label></th>
                        <td>
                            <select name="resource_id" id="resource_id" class="regular-text">
                                <option value=""><?php esc_html_e('No specific resource', 'classflow-pro'); ?></option>
                                <?php 
                                $current_location = null;
                                foreach ($resources as $resource): 
                                    if ($current_location !== $resource['location_id']):
                                        if ($current_location !== null) echo '</optgroup>';
                                        $loc_name = '';
                                        foreach ($locations as $loc) {
                                            if ($loc['id'] == $resource['location_id']) {
                                                $loc_name = $loc['name'];
                                                break;
                                            }
                                        }
                                        echo '<optgroup label="' . esc_attr($loc_name) . '" data-location="' . esc_attr($resource['location_id']) . '">';
                                        $current_location = $resource['location_id'];
                                    endif;
                                ?>
                                    <option value="<?php echo esc_attr($resource['id']); ?>" 
                                            data-location="<?php echo esc_attr($resource['location_id']); ?>"
                                            data-capacity="<?php echo esc_attr($resource['capacity']); ?>">
                                        <?php echo esc_html($resource['name']); ?> 
                                        (<?php echo esc_html(ucwords(str_replace('_', ' ', $resource['type']))); ?>)
                                    </option>
                                <?php endforeach; 
                                if ($current_location !== null) echo '</optgroup>';
                                ?>
                            </select>
                            <p class="description"><?php esc_html_e('Optional: Assign specific equipment or room', 'classflow-pro'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Date & Time -->
                    <tr>
                        <th><?php esc_html_e('Schedule Date & Time', 'classflow-pro'); ?> <span class="required">*</span></th>
                        <td>
                            <div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">
                                <div>
                                    <label><?php esc_html_e('Date', 'classflow-pro'); ?><br/>
                                    <input type="date" name="schedule_date" id="schedule_date" required 
                                           min="<?php echo esc_attr(date('Y-m-d')); ?>"/>
                                    </label>
                                </div>
                                <div>
                                    <label><?php esc_html_e('Start Time', 'classflow-pro'); ?><br/>
                                    <input type="time" name="start_time" id="start_time" required/>
                                    </label>
                                </div>
                                <div>
                                    <label><?php esc_html_e('End Time', 'classflow-pro'); ?><br/>
                                    <input type="time" name="end_time" id="end_time" required/>
                                    </label>
                                </div>
                                <div>
                                    <span id="duration-display" style="font-weight:bold;color:#2271b1;"></span>
                                </div>
                            </div>
                            <p class="description">
                                <?php esc_html_e('Times are in the location\'s timezone', 'classflow-pro'); ?>
                                <span id="timezone-display"></span>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Recurring Schedule -->
                    <tr>
                        <th><?php esc_html_e('Recurring Schedule', 'classflow-pro'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_recurring" id="is_recurring" value="1"/>
                                <?php esc_html_e('Create recurring schedule', 'classflow-pro'); ?>
                            </label>
                            
                            <div id="recurring-options" style="display:none;margin-top:15px;padding:15px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
                                <div style="margin-bottom:10px;">
                                    <label><?php esc_html_e('Repeat', 'classflow-pro'); ?>
                                        <select name="recurrence_type" id="recurrence_type">
                                            <option value="daily"><?php esc_html_e('Daily', 'classflow-pro'); ?></option>
                                            <option value="weekly" selected><?php esc_html_e('Weekly', 'classflow-pro'); ?></option>
                                            <option value="biweekly"><?php esc_html_e('Bi-weekly', 'classflow-pro'); ?></option>
                                            <option value="monthly"><?php esc_html_e('Monthly', 'classflow-pro'); ?></option>
                                        </select>
                                    </label>
                                </div>
                                
                                <div id="weekly-days" style="margin-bottom:10px;">
                                    <label><?php esc_html_e('On days:', 'classflow-pro'); ?></label><br/>
                                    <?php 
                                    $days = ['monday' => __('Mon', 'classflow-pro'), 'tuesday' => __('Tue', 'classflow-pro'), 
                                             'wednesday' => __('Wed', 'classflow-pro'), 'thursday' => __('Thu', 'classflow-pro'), 
                                             'friday' => __('Fri', 'classflow-pro'), 'saturday' => __('Sat', 'classflow-pro'), 
                                             'sunday' => __('Sun', 'classflow-pro')];
                                    foreach ($days as $key => $label): ?>
                                        <label style="margin-right:15px;">
                                            <input type="checkbox" name="recurring_days[]" value="<?php echo esc_attr($key); ?>"/>
                                            <?php echo esc_html($label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div style="margin-bottom:10px;">
                                    <label><?php esc_html_e('For', 'classflow-pro'); ?>
                                        <input type="number" name="recurrence_weeks" id="recurrence_weeks" value="4" min="1" max="52" style="width:60px;"/>
                                        <span id="recurrence-unit"><?php esc_html_e('weeks', 'classflow-pro'); ?></span>
                                    </label>
                                </div>
                                
                                <div style="padding:10px;background:#fff;border:1px solid #ddd;border-radius:4px;">
                                    <strong><?php esc_html_e('Preview:', 'classflow-pro'); ?></strong>
                                    <div id="recurrence-preview" style="margin-top:5px;color:#666;">
                                        <?php esc_html_e('Select options above to see preview', 'classflow-pro'); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Capacity Override -->
                    <tr>
                        <th><label for="capacity"><?php esc_html_e('Capacity', 'classflow-pro'); ?></label></th>
                        <td>
                            <input type="number" name="capacity" id="capacity" min="1" class="small-text"
                                   value="<?php echo esc_attr($default_values['capacity'] ?? 8); ?>"/>
                            <span class="description"><?php esc_html_e('Maximum number of participants', 'classflow-pro'); ?></span>
                        </td>
                    </tr>
                    
                    <!-- Price Override -->
                    <tr>
                        <th><label for="price_override"><?php esc_html_e('Price Override', 'classflow-pro'); ?></label></th>
                        <td>
                            <input type="number" name="price_override" id="price_override" min="0" step="0.01" class="small-text"
                                   placeholder="<?php esc_attr_e('Use class default', 'classflow-pro'); ?>"/>
                            <select name="currency" id="currency" style="width:80px;">
                                <option value="usd">USD</option>
                                <option value="eur">EUR</option>
                                <option value="gbp">GBP</option>
                                <option value="aud">AUD</option>
                                <option value="cad">CAD</option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Leave empty to use class default price', 'classflow-pro'); ?>
                                <span id="default-price-display"></span>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Private Session -->
                    <tr>
                        <th><?php esc_html_e('Session Type', 'classflow-pro'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_private" id="is_private" value="1"/>
                                <?php esc_html_e('Private session (not publicly bookable)', 'classflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <div id="schedule-conflicts" style="display:none;margin:20px 0;padding:15px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">
                    <strong><?php esc_html_e('Potential Conflicts:', 'classflow-pro'); ?></strong>
                    <div id="conflicts-list"></div>
                </div>
                
                <?php submit_button(__('Create Schedule', 'classflow-pro'), 'primary', 'submit', false); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-schedules')); ?>" class="button">
                    <?php esc_html_e('Cancel', 'classflow-pro'); ?>
                </a>
            </form>
        </div>
        
        <style>
        .required { color: #d63638; }
        .cfp-schedule-form { max-width: 800px; }
        .form-table th { width: 200px; }
        </style>
        
        <script>
        jQuery(function($) {
            // Auto-fill class defaults
            $('#class_id').on('change', function() {
                var $selected = $(this).find('option:selected');
                if (!$selected.val()) return;
                
                var duration = $selected.data('duration');
                var capacity = $selected.data('capacity');
                var price = $selected.data('price');
                var currency = $selected.data('currency');
                var location = $selected.data('location');
                
                $('#capacity').val(capacity);
                $('#currency').val(currency);
                $('#default-price-display').text(' (Default: ' + (price/100).toFixed(2) + ' ' + currency.toUpperCase() + ')');
                
                if (location && !$('#location_id').val()) {
                    $('#location_id').val(location).trigger('change');
                }
                
                // Calculate end time based on duration
                if ($('#start_time').val() && duration) {
                    calculateEndTime(duration);
                }
            });
            
            // Calculate end time
            function calculateEndTime(duration) {
                var startTime = $('#start_time').val();
                if (!startTime || !duration) return;
                
                var [hours, minutes] = startTime.split(':').map(Number);
                var totalMinutes = hours * 60 + minutes + parseInt(duration);
                var endHours = Math.floor(totalMinutes / 60) % 24;
                var endMinutes = totalMinutes % 60;
                
                var endTime = String(endHours).padStart(2, '0') + ':' + String(endMinutes).padStart(2, '0');
                $('#end_time').val(endTime);
                updateDurationDisplay();
            }
            
            $('#start_time').on('change', function() {
                var $selected = $('#class_id option:selected');
                var duration = $selected.data('duration');
                if (duration) {
                    calculateEndTime(duration);
                }
            });
            
            // Update duration display
            function updateDurationDisplay() {
                var start = $('#start_time').val();
                var end = $('#end_time').val();
                if (!start || !end) return;
                
                var [startH, startM] = start.split(':').map(Number);
                var [endH, endM] = end.split(':').map(Number);
                var durationMinutes = (endH * 60 + endM) - (startH * 60 + startM);
                
                if (durationMinutes > 0) {
                    var hours = Math.floor(durationMinutes / 60);
                    var mins = durationMinutes % 60;
                    var display = hours > 0 ? hours + 'h ' : '';
                    display += mins > 0 ? mins + 'min' : '';
                    $('#duration-display').text('Duration: ' + display);
                }
            }
            
            $('#start_time, #end_time').on('change', updateDurationDisplay);
            
            // Location timezone display
            $('#location_id').on('change', function() {
                var $selected = $(this).find('option:selected');
                var timezone = $selected.data('timezone');
                if (timezone) {
                    $('#timezone-display').text(' (' + timezone + ')');
                }
                
                // Filter resources by location
                var locationId = $(this).val();
                $('#resource_id optgroup').hide();
                $('#resource_id option').prop('disabled', true);
                $('#resource_id option[value=""]').prop('disabled', false);
                
                if (locationId) {
                    $('#resource_id optgroup[data-location="' + locationId + '"]').show();
                    $('#resource_id optgroup[data-location="' + locationId + '"] option').prop('disabled', false);
                }
            });
            
            // Instructor availability display
            $('#instructor_id').on('change', function() {
                var availability = $(this).find('option:selected').data('availability');
                if (availability && typeof availability === 'object') {
                    var display = '';
                    var days = {'monday': 'Mon', 'tuesday': 'Tue', 'wednesday': 'Wed', 'thursday': 'Thu', 
                               'friday': 'Fri', 'saturday': 'Sat', 'sunday': 'Sun'};
                    
                    for (var day in availability) {
                        if (availability[day].available) {
                            display += days[day] + ': ' + availability[day].start + '-' + availability[day].end + ' ';
                        }
                    }
                    
                    if (display) {
                        $('#availability-display').text(display);
                        $('#instructor-availability-info').show();
                    } else {
                        $('#instructor-availability-info').hide();
                    }
                } else {
                    $('#instructor-availability-info').hide();
                }
            });
            
            // Recurring options
            $('#is_recurring').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#recurring-options').slideDown();
                    updateRecurrencePreview();
                } else {
                    $('#recurring-options').slideUp();
                }
            });
            
            $('#recurrence_type').on('change', function() {
                var type = $(this).val();
                if (type === 'weekly' || type === 'biweekly') {
                    $('#weekly-days').show();
                    $('#recurrence-unit').text('<?php echo esc_js(__('weeks', 'classflow-pro')); ?>');
                } else {
                    $('#weekly-days').hide();
                    $('#recurrence-unit').text(type === 'daily' ? '<?php echo esc_js(__('days', 'classflow-pro')); ?>' : '<?php echo esc_js(__('months', 'classflow-pro')); ?>');
                }
                updateRecurrencePreview();
            });
            
            // Update recurrence preview
            function updateRecurrencePreview() {
                var type = $('#recurrence_type').val();
                var weeks = $('#recurrence_weeks').val();
                var date = $('#schedule_date').val();
                
                if (!date) {
                    $('#recurrence-preview').text('<?php echo esc_js(__('Select a start date first', 'classflow-pro')); ?>');
                    return;
                }
                
                var preview = '';
                if (type === 'daily') {
                    preview = 'Daily for ' + weeks + ' days';
                } else if (type === 'weekly') {
                    var days = $('input[name="recurring_days[]"]:checked').map(function() {
                        return $(this).val();
                    }).get();
                    if (days.length) {
                        preview = 'Weekly on selected days for ' + weeks + ' weeks';
                    } else {
                        preview = 'Weekly on the same day for ' + weeks + ' weeks';
                    }
                } else if (type === 'biweekly') {
                    preview = 'Every 2 weeks for ' + (weeks * 2) + ' weeks';
                } else if (type === 'monthly') {
                    preview = 'Monthly on the same date for ' + weeks + ' months';
                }
                
                $('#recurrence-preview').text(preview);
            }
            
            $('input[name="recurring_days[]"], #recurrence_weeks, #schedule_date').on('change', updateRecurrencePreview);
            
            // Set current day checkbox when date is selected
            $('#schedule_date').on('change', function() {
                var date = new Date($(this).val());
                var days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                var dayName = days[date.getDay()];
                
                if ($('#recurrence_type').val() === 'weekly' && !$('input[name="recurring_days[]"]:checked').length) {
                    $('input[name="recurring_days[]"][value="' + dayName + '"]').prop('checked', true);
                    updateRecurrencePreview();
                }
            });
            
            // Private session handling
            $('#is_private').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#capacity').val(1);
                }
            });
            
            // Initialize
            $('#class_id').trigger('change');
        });
        </script>
        <?php
    }
    
    private static function render_list(): void
    {
        global $wpdb;
        
        // Filters
        $filter_class = isset($_GET['filter_class']) ? (int)$_GET['filter_class'] : 0;
        $filter_instructor = isset($_GET['filter_instructor']) ? (int)$_GET['filter_instructor'] : 0;
        $filter_location = isset($_GET['filter_location']) ? (int)$_GET['filter_location'] : 0;
        $filter_date = isset($_GET['filter_date']) ? sanitize_text_field($_GET['filter_date']) : '';
        
        // Build query
        $where = [];
        $params = [];
        
        if ($filter_class) {
            $where[] = 's.class_id = %d';
            $params[] = $filter_class;
        }
        if ($filter_instructor) {
            $where[] = 's.instructor_id = %d';
            $params[] = $filter_instructor;
        }
        if ($filter_location) {
            $where[] = 's.location_id = %d';
            $params[] = $filter_location;
        }
        if ($filter_date) {
            $where[] = 'DATE(s.start_time) = %s';
            $params[] = $filter_date;
        } else {
            // Default: show upcoming schedules
            $where[] = 's.start_time >= %s';
            $params[] = current_time('mysql');
        }
        
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT s.*, 
                  c.name as class_name, 
                  i.name as instructor_name,
                  l.name as location_name,
                  l.timezone as location_timezone,
                  r.name as resource_name,
                  (SELECT COUNT(*) FROM {$wpdb->prefix}cfp_bookings WHERE schedule_id = s.id AND status IN ('pending','confirmed')) as bookings_count
                  FROM {$wpdb->prefix}cfp_schedules s
                  LEFT JOIN {$wpdb->prefix}cfp_classes c ON s.class_id = c.id
                  LEFT JOIN {$wpdb->prefix}cfp_instructors i ON s.instructor_id = i.id
                  LEFT JOIN {$wpdb->prefix}cfp_locations l ON s.location_id = l.id
                  LEFT JOIN {$wpdb->prefix}cfp_resources r ON s.resource_id = r.id
                  $where_sql
                  ORDER BY s.start_time ASC
                  LIMIT 100";
        
        $schedules = $params ? $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A) : $wpdb->get_results($query, ARRAY_A);
        
        // Get filter options
        $classes = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}cfp_classes WHERE status='active' ORDER BY name", ARRAY_A);
        $instructors = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}cfp_instructors ORDER BY name", ARRAY_A);
        $locations = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}cfp_locations ORDER BY name", ARRAY_A);
        
        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" style="display:flex;gap:10px;align-items:center;">
                    <input type="hidden" name="page" value="classflow-pro-schedules"/>
                    
                    <select name="filter_class">
                        <option value=""><?php esc_html_e('All Classes', 'classflow-pro'); ?></option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo esc_attr($class['id']); ?>" <?php selected($filter_class, $class['id']); ?>>
                                <?php echo esc_html($class['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="filter_instructor">
                        <option value=""><?php esc_html_e('All Instructors', 'classflow-pro'); ?></option>
                        <?php foreach ($instructors as $instructor): ?>
                            <option value="<?php echo esc_attr($instructor['id']); ?>" <?php selected($filter_instructor, $instructor['id']); ?>>
                                <?php echo esc_html($instructor['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="filter_location">
                        <option value=""><?php esc_html_e('All Locations', 'classflow-pro'); ?></option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo esc_attr($location['id']); ?>" <?php selected($filter_location, $location['id']); ?>>
                                <?php echo esc_html($location['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" name="filter_date" value="<?php echo esc_attr($filter_date); ?>" 
                           placeholder="<?php esc_attr_e('Filter by date', 'classflow-pro'); ?>"/>
                    
                    <?php submit_button(__('Filter', 'classflow-pro'), 'button', '', false); ?>
                    
                    <?php if ($filter_class || $filter_instructor || $filter_location || $filter_date): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-schedules')); ?>" class="button">
                            <?php esc_html_e('Clear Filters', 'classflow-pro'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Class', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Date & Time', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Instructor', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Location', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Resource', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Bookings', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Price', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'classflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="8"><?php esc_html_e('No schedules found.', 'classflow-pro'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $schedule): 
                        $timezone = $schedule['location_timezone'] ?: wp_timezone_string();
                        $start_local = new DateTime($schedule['start_time'], new DateTimeZone('UTC'));
                        $start_local->setTimezone(new DateTimeZone($timezone));
                        $end_local = new DateTime($schedule['end_time'], new DateTimeZone('UTC'));
                        $end_local->setTimezone(new DateTimeZone($timezone));
                        
                        $is_past = strtotime($schedule['start_time']) < time();
                        $is_full = $schedule['bookings_count'] >= $schedule['capacity'];
                    ?>
                        <tr <?php if ($is_past) echo 'style="opacity:0.6;"'; ?>>
                            <td>
                                <strong><?php echo esc_html($schedule['class_name']); ?></strong>
                                <?php if ($schedule['is_private']): ?>
                                    <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Private', 'classflow-pro'); ?>"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($start_local->format('M j, Y')); ?><br/>
                                <?php echo esc_html($start_local->format('g:i A') . ' - ' . $end_local->format('g:i A')); ?>
                            </td>
                            <td><?php echo esc_html($schedule['instructor_name'] ?: '-'); ?></td>
                            <td><?php echo esc_html($schedule['location_name'] ?: '-'); ?></td>
                            <td><?php echo esc_html($schedule['resource_name'] ?: '-'); ?></td>
                            <td>
                                <span class="<?php echo $is_full ? 'text-danger' : ''; ?>">
                                    <?php echo esc_html($schedule['bookings_count'] . '/' . $schedule['capacity']); ?>
                                </span>
                                <?php if ($is_full): ?>
                                    <span style="color:#d63638;"><?php esc_html_e('FULL', 'classflow-pro'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html(number_format($schedule['price_cents']/100, 2) . ' ' . strtoupper($schedule['currency'])); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-bookings&schedule_id=' . $schedule['id'])); ?>" 
                                   class="button button-small">
                                    <?php esc_html_e('View Bookings', 'classflow-pro'); ?>
                                </a>
                                <?php if (!$is_past): ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('cfp_delete_schedule_' . $schedule['id']); ?>
                                        <input type="hidden" name="cfp_action" value="delete_schedule"/>
                                        <input type="hidden" name="schedule_id" value="<?php echo esc_attr($schedule['id']); ?>"/>
                                        <button type="submit" class="button button-small button-link-delete" 
                                                onclick="return confirm('<?php esc_attr_e('Delete this schedule? This will cancel all bookings.', 'classflow-pro'); ?>');">
                                            <?php esc_html_e('Delete', 'classflow-pro'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    private static function handle_add_schedule(): void
    {
        if (!check_admin_referer('cfp_add_schedule')) {
            wp_die(__('Security check failed', 'classflow-pro'));
        }
        
        global $wpdb;
        
        $class_id = (int)$_POST['class_id'];
        $instructor_id = (int)$_POST['instructor_id'];
        $resource_id = (int)$_POST['resource_id'];
        $location_id = (int)$_POST['location_id'];
        $schedule_date = sanitize_text_field($_POST['schedule_date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        $capacity = max(1, (int)$_POST['capacity']);
        $is_private = !empty($_POST['is_private']) ? 1 : 0;
        $is_recurring = !empty($_POST['is_recurring']);
        
        // Get location timezone
        $timezone = $wpdb->get_var($wpdb->prepare(
            "SELECT timezone FROM {$wpdb->prefix}cfp_locations WHERE id = %d",
            $location_id
        )) ?: 'UTC';
        
        // Convert local time to UTC
        $start_local = new DateTime($schedule_date . ' ' . $start_time, new DateTimeZone($timezone));
        $end_local = new DateTime($schedule_date . ' ' . $end_time, new DateTimeZone($timezone));
        $start_local->setTimezone(new DateTimeZone('UTC'));
        $end_local->setTimezone(new DateTimeZone('UTC'));
        
        // Price handling
        if (!empty($_POST['price_override'])) {
            $price_cents = (int)(floatval($_POST['price_override']) * 100);
            $currency = sanitize_text_field($_POST['currency']);
        } else {
            // Get from class
            $class = $wpdb->get_row($wpdb->prepare(
                "SELECT price_cents, currency FROM {$wpdb->prefix}cfp_classes WHERE id = %d",
                $class_id
            ), ARRAY_A);
            $price_cents = (int)$class['price_cents'];
            $currency = $class['currency'];
        }
        
        $schedules_to_create = [];
        
        if ($is_recurring) {
            $recurrence_type = sanitize_text_field($_POST['recurrence_type']);
            $recurrence_weeks = max(1, (int)$_POST['recurrence_weeks']);
            $recurring_days = isset($_POST['recurring_days']) ? array_map('sanitize_text_field', $_POST['recurring_days']) : [];
            
            $current_date = new DateTime($schedule_date, new DateTimeZone($timezone));
            $end_date = clone $current_date;
            
            if ($recurrence_type === 'daily') {
                $end_date->modify('+' . $recurrence_weeks . ' days');
                $interval = new DateInterval('P1D');
            } elseif ($recurrence_type === 'weekly') {
                $end_date->modify('+' . $recurrence_weeks . ' weeks');
                $interval = new DateInterval('P1W');
            } elseif ($recurrence_type === 'biweekly') {
                $end_date->modify('+' . ($recurrence_weeks * 2) . ' weeks');
                $interval = new DateInterval('P2W');
            } else { // monthly
                $end_date->modify('+' . $recurrence_weeks . ' months');
                $interval = new DateInterval('P1M');
            }
            
            $period = new DatePeriod($current_date, $interval, $end_date);
            
            foreach ($period as $date) {
                // For weekly with specific days
                if (($recurrence_type === 'weekly' || $recurrence_type === 'biweekly') && !empty($recurring_days)) {
                    $day_name = strtolower($date->format('l'));
                    if (!in_array($day_name, $recurring_days)) {
                        continue;
                    }
                }
                
                $schedule_start = clone $date;
                $schedule_start->setTime((int)$start_local->format('H'), (int)$start_local->format('i'));
                $schedule_end = clone $date;
                $schedule_end->setTime((int)$end_local->format('H'), (int)$end_local->format('i'));
                
                $schedule_start->setTimezone(new DateTimeZone('UTC'));
                $schedule_end->setTimezone(new DateTimeZone('UTC'));
                
                $schedules_to_create[] = [
                    'start' => $schedule_start->format('Y-m-d H:i:s'),
                    'end' => $schedule_end->format('Y-m-d H:i:s')
                ];
            }
        } else {
            $schedules_to_create[] = [
                'start' => $start_local->format('Y-m-d H:i:s'),
                'end' => $end_local->format('Y-m-d H:i:s')
            ];
        }
        
        $created = 0;
        $conflicts = 0;
        
        foreach ($schedules_to_create as $schedule) {
            // Check for conflicts
            $has_conflict = false;
            
            if ($instructor_id) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cfp_schedules 
                     WHERE instructor_id = %d AND start_time < %s AND end_time > %s",
                    $instructor_id, $schedule['end'], $schedule['start']
                ));
                if ($exists) $has_conflict = true;
            }
            
            if (!$has_conflict && $resource_id) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cfp_schedules 
                     WHERE resource_id = %d AND start_time < %s AND end_time > %s",
                    $resource_id, $schedule['end'], $schedule['start']
                ));
                if ($exists) $has_conflict = true;
            }
            
            if ($has_conflict) {
                $conflicts++;
                continue;
            }
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'cfp_schedules',
                [
                    'class_id' => $class_id,
                    'instructor_id' => $instructor_id ?: null,
                    'resource_id' => $resource_id ?: null,
                    'location_id' => $location_id ?: null,
                    'start_time' => $schedule['start'],
                    'end_time' => $schedule['end'],
                    'capacity' => $capacity,
                    'price_cents' => $price_cents,
                    'currency' => $currency,
                    'is_private' => $is_private,
                ],
                ['%d','%d','%d','%d','%s','%s','%d','%d','%s','%d']
            );
            
            if ($result) {
                $created++;
                // Try Google Calendar sync
                try {
                    \ClassFlowPro\Calendar\Google::upsert_event($wpdb->insert_id);
                } catch (\Throwable $e) {
                    // Silent fail
                }
            }
        }
        
        if ($created > 0) {
            echo '<div class="notice notice-success"><p>';
            printf(
                esc_html(_n('%d schedule created successfully.', '%d schedules created successfully.', $created, 'classflow-pro')),
                $created
            );
            if ($conflicts > 0) {
                echo ' ';
                printf(
                    esc_html(_n('%d schedule skipped due to conflicts.', '%d schedules skipped due to conflicts.', $conflicts, 'classflow-pro')),
                    $conflicts
                );
            }
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html__('No schedules were created. Check for conflicts.', 'classflow-pro') . '</p></div>';
        }
    }
    
    private static function handle_delete_schedule(): void
    {
        $schedule_id = (int)$_POST['schedule_id'];
        
        if (!check_admin_referer('cfp_delete_schedule_' . $schedule_id)) {
            wp_die(__('Security check failed', 'classflow-pro'));
        }
        
        global $wpdb;
        
        // Cancel all bookings for this schedule
        $wpdb->update(
            $wpdb->prefix . 'cfp_bookings',
            ['status' => 'canceled'],
            ['schedule_id' => $schedule_id],
            ['%s'],
            ['%d']
        );
        
        // Delete the schedule
        $wpdb->delete(
            $wpdb->prefix . 'cfp_schedules',
            ['id' => $schedule_id],
            ['%d']
        );
        
        echo '<div class="notice notice-success"><p>' . esc_html__('Schedule deleted successfully.', 'classflow-pro') . '</p></div>';
    }
}
