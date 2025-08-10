<?php
declare(strict_types=1);

namespace ClassFlowPro\Frontend\Shortcodes;

use ClassFlowPro\Services\Container;

class CalendarShortcode {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(array $atts): string {
        $atts = shortcode_atts([
            'view' => 'month', // month, week, day
            'category' => '',
            'instructor' => '',
            'location' => '',
            'show_filters' => 'yes',
            'height' => '600',
        ], $atts, 'classflow_calendar');

        // Enqueue calendar scripts
        wp_enqueue_script('moment', 'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js', [], '2.29.4');
        wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js', ['moment'], '6.1.9');
        wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/main.min.css', [], '6.1.9');
        
        // Add custom calendar script
        wp_enqueue_script(
            'classflow-calendar',
            CLASSFLOW_PRO_PLUGIN_URL . 'assets/js/calendar.js',
            ['jquery', 'fullcalendar'],
            CLASSFLOW_PRO_VERSION,
            true
        );

        wp_localize_script('classflow-calendar', 'classflowCalendar', [
            'apiUrl' => rest_url('classflow-pro/v1/schedules'),
            'nonce' => wp_create_nonce('wp_rest'),
            'view' => $atts['view'],
            'filters' => [
                'category' => $atts['category'],
                'instructor' => $atts['instructor'],
                'location' => $atts['location'],
            ],
            'i18n' => [
                'today' => __('Today', 'classflow-pro'),
                'month' => __('Month', 'classflow-pro'),
                'week' => __('Week', 'classflow-pro'),
                'day' => __('Day', 'classflow-pro'),
                'list' => __('List', 'classflow-pro'),
            ],
        ]);

        ob_start();
        ?>
        <div class="classflow-calendar-container">
            <?php if ($atts['show_filters'] === 'yes'): ?>
                <div class="classflow-calendar-filters">
                    <div class="classflow-calendar-filter">
                        <label><?php esc_html_e('Category', 'classflow-pro'); ?></label>
                        <select id="classflow-filter-category" class="classflow-filter">
                            <option value=""><?php esc_html_e('All Categories', 'classflow-pro'); ?></option>
                            <?php foreach ($this->getCategories() as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="classflow-calendar-filter">
                        <label><?php esc_html_e('Instructor', 'classflow-pro'); ?></label>
                        <select id="classflow-filter-instructor" class="classflow-filter">
                            <option value=""><?php esc_html_e('All Instructors', 'classflow-pro'); ?></option>
                            <?php foreach ($this->getInstructors() as $instructor): ?>
                                <option value="<?php echo esc_attr($instructor->ID); ?>">
                                    <?php echo esc_html($instructor->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="classflow-calendar-filter">
                        <label><?php esc_html_e('Location', 'classflow-pro'); ?></label>
                        <select id="classflow-filter-location" class="classflow-filter">
                            <option value=""><?php esc_html_e('All Locations', 'classflow-pro'); ?></option>
                            <?php foreach ($this->getLocations() as $location): ?>
                                <option value="<?php echo esc_attr($location->getId()); ?>">
                                    <?php echo esc_html($location->getName()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
            
            <div id="classflow-calendar" style="height: <?php echo esc_attr($atts['height']); ?>px;"></div>
            
            <!-- Event details modal -->
            <div id="classflow-event-modal" class="classflow-modal" style="display: none;">
                <div class="classflow-modal-content">
                    <span class="classflow-modal-close">&times;</span>
                    <div id="classflow-event-details"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function getCategories(): array {
        return $this->container->get('database')->get('categories', [], 'name ASC');
    }

    private function getInstructors(): array {
        return get_users([
            'role__in' => ['classflow_instructor'],
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
    }

    private function getLocations(): array {
        $locationRepo = $this->container->get('location_repository');
        return $locationRepo->findAll(['status' => 'active']);
    }
}