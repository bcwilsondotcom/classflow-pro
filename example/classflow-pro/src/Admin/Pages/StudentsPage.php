<?php
declare(strict_types=1);

namespace ClassFlowPro\Admin\Pages;

use ClassFlowPro\Services\Container;

class StudentsPage {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Students', 'classflow-pro'); ?></h1>
            <p><?php echo esc_html__('Manage students.', 'classflow-pro'); ?></p>
        </div>
        <?php
    }
}