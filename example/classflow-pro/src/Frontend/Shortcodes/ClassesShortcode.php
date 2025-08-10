<?php
declare(strict_types=1);

namespace ClassFlowPro\Frontend\Shortcodes;

use ClassFlowPro\Services\Container;

class ClassesShortcode {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(array $atts): string {
        $atts = shortcode_atts([
            'category' => '',
            'limit' => 12,
            'columns' => 3,
            'orderby' => 'name',
            'order' => 'ASC',
        ], $atts, 'classflow_classes');

        $classRepo = $this->container->get('class_repository');
        
        $filters = [];
        if ($atts['category']) {
            $filters['category_id'] = (int) $atts['category'];
        }
        $filters['status'] = 'active';
        
        $classes = $classRepo->findAll($filters, $atts['orderby'] . ' ' . $atts['order'], (int) $atts['limit']);
        
        ob_start();
        ?>
        <div class="classflow-classes-grid" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <?php foreach ($classes as $class): ?>
                <div class="classflow-class-card">
                    <?php if ($class->getFeaturedImageId()): ?>
                        <img src="<?php echo esc_url(wp_get_attachment_url($class->getFeaturedImageId())); ?>" 
                             alt="<?php echo esc_attr($class->getName()); ?>" 
                             class="classflow-class-image">
                    <?php endif; ?>
                    
                    <div class="classflow-class-content">
                        <h3 class="classflow-class-title">
                            <a href="<?php echo esc_url(home_url('/classes/' . $class->getSlug())); ?>">
                                <?php echo esc_html($class->getName()); ?>
                            </a>
                        </h3>
                        
                        <div class="classflow-class-meta">
                            <span class="classflow-class-meta-item">
                                <span class="dashicons dashicons-clock"></span>
                                <?php echo esc_html($class->getDurationFormatted()); ?>
                            </span>
                            <span class="classflow-class-meta-item">
                                <span class="dashicons dashicons-groups"></span>
                                <?php echo esc_html($class->getCapacity()); ?> spots
                            </span>
                        </div>
                        
                        <?php if ($class->getDescription()): ?>
                            <div class="classflow-class-description">
                                <?php echo wp_trim_words(wp_kses_post($class->getDescription()), 20); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="classflow-class-footer">
                            <span class="classflow-class-price"><?php echo esc_html($class->getFormattedPrice()); ?></span>
                            <a href="<?php echo esc_url(home_url('/classes/' . $class->getSlug())); ?>" 
                               class="classflow-btn classflow-btn-primary">
                                <?php esc_html_e('View Details', 'classflow-pro'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}