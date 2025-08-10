<?php
declare(strict_types=1);

namespace ClassFlowPro\Admin\Pages;

use ClassFlowPro\Services\Container;

class DashboardPage {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(): void {
        ?>
        <div class="wrap classflow-pro-dashboard">
            <h1><?php echo esc_html__('ClassFlow Pro Dashboard', 'classflow-pro'); ?></h1>
            
            <div class="classflow-pro-dashboard-widgets">
                <?php $this->renderStatsWidget(); ?>
                <?php $this->renderUpcomingClassesWidget(); ?>
                <?php $this->renderRecentBookingsWidget(); ?>
                <?php $this->renderRevenueWidget(); ?>
            </div>
            
            <div class="classflow-pro-dashboard-secondary">
                <?php $this->renderQuickActionsWidget(); ?>
                <?php $this->renderSystemStatusWidget(); ?>
            </div>
        </div>
        <?php
    }

    private function renderStatsWidget(): void {
        $bookingRepo = $this->container->get('booking_repository');
        $classRepo = $this->container->get('class_repository');
        $scheduleRepo = $this->container->get('schedule_repository');
        
        // Get today's stats
        $today = new \DateTime();
        $todayStart = clone $today;
        $todayStart->setTime(0, 0, 0);
        $todayEnd = clone $today;
        $todayEnd->setTime(23, 59, 59);
        
        $todaySchedules = $scheduleRepo->findByDateRange($todayStart, $todayEnd);
        $upcomingSchedules = $scheduleRepo->getUpcomingSchedules(100);
        
        ?>
        <div class="postbox classflow-pro-widget">
            <h2 class="hndle"><?php echo esc_html__('Overview', 'classflow-pro'); ?></h2>
            <div class="inside">
                <div class="classflow-pro-stats-grid">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo count($classRepo->findAll(['status' => 'active'])); ?></span>
                        <span class="stat-label"><?php echo esc_html__('Active Classes', 'classflow-pro'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo count($todaySchedules); ?></span>
                        <span class="stat-label"><?php echo esc_html__("Today's Classes", 'classflow-pro'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo count($upcomingSchedules); ?></span>
                        <span class="stat-label"><?php echo esc_html__('Upcoming Schedules', 'classflow-pro'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $bookingRepo->count(['status' => 'confirmed']); ?></span>
                        <span class="stat-label"><?php echo esc_html__('Active Bookings', 'classflow-pro'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderUpcomingClassesWidget(): void {
        $scheduleRepo = $this->container->get('schedule_repository');
        $classRepo = $this->container->get('class_repository');
        
        $upcomingSchedules = $scheduleRepo->getUpcomingSchedules(5);
        
        ?>
        <div class="postbox classflow-pro-widget">
            <h2 class="hndle"><?php echo esc_html__('Upcoming Classes', 'classflow-pro'); ?></h2>
            <div class="inside">
                <?php if (empty($upcomingSchedules)): ?>
                    <p><?php echo esc_html__('No upcoming classes scheduled.', 'classflow-pro'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Class', 'classflow-pro'); ?></th>
                                <th><?php echo esc_html__('Date & Time', 'classflow-pro'); ?></th>
                                <th><?php echo esc_html__('Instructor', 'classflow-pro'); ?></th>
                                <th><?php echo esc_html__('Bookings', 'classflow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingSchedules as $schedule): 
                                $class = $classRepo->find($schedule->getClassId());
                                $instructor = get_userdata($schedule->getInstructorId());
                                $bookingCount = $this->container->get('booking_repository')->getBookingCountForSchedule($schedule->getId());
                                $availableSpots = $scheduleRepo->getAvailableSpots($schedule->getId());
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=classflow-pro-classes&action=edit&id=' . $class->getId()); ?>">
                                            <?php echo esc_html($class->getName()); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($schedule->getFormattedDateRange()); ?></td>
                                    <td><?php echo $instructor ? esc_html($instructor->display_name) : '-'; ?></td>
                                    <td>
                                        <?php echo sprintf(
                                            esc_html__('%d / %d', 'classflow-pro'),
                                            $bookingCount,
                                            $class->getCapacity()
                                        ); ?>
                                        <?php if ($availableSpots === 0): ?>
                                            <span class="dashicons dashicons-warning" title="<?php esc_attr_e('Fully booked', 'classflow-pro'); ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function renderRecentBookingsWidget(): void {
        $bookingRepo = $this->container->get('booking_repository');
        $recentBookings = $bookingRepo->findAll([], 'created_at DESC', 5);
        
        ?>
        <div class="postbox classflow-pro-widget">
            <h2 class="hndle"><?php echo esc_html__('Recent Bookings', 'classflow-pro'); ?></h2>
            <div class="inside">
                <?php if (empty($recentBookings)): ?>
                    <p><?php echo esc_html__('No bookings yet.', 'classflow-pro'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Booking Code', 'classflow-pro'); ?></th>
                                <th><?php echo esc_html__('Student', 'classflow-pro'); ?></th>
                                <th><?php echo esc_html__('Status', 'classflow-pro'); ?></th>
                                <th><?php echo esc_html__('Date', 'classflow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBookings as $booking): 
                                $student = get_userdata($booking->getStudentId());
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=classflow-pro-bookings&action=view&id=' . $booking->getId()); ?>">
                                            <?php echo esc_html($booking->getBookingCode()); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $student ? esc_html($student->display_name) : '-'; ?></td>
                                    <td>
                                        <span class="classflow-pro-status-badge status-<?php echo esc_attr($booking->getStatus()); ?>">
                                            <?php echo esc_html($booking->getStatusLabel()); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($booking->getCreatedAt()->format('M j, Y')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function renderRevenueWidget(): void {
        $paymentService = $this->container->get('payment_service');
        
        // Get this month's revenue
        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month');
        
        $monthlyRevenue = $paymentService->getRevenueReport($startOfMonth, $endOfMonth);
        
        ?>
        <div class="postbox classflow-pro-widget">
            <h2 class="hndle"><?php echo esc_html__('Revenue This Month', 'classflow-pro'); ?></h2>
            <div class="inside">
                <div class="classflow-pro-revenue-stats">
                    <div class="revenue-item">
                        <span class="revenue-label"><?php echo esc_html__('Total Revenue', 'classflow-pro'); ?></span>
                        <span class="revenue-value"><?php echo esc_html('$' . number_format($monthlyRevenue['total_revenue'], 2)); ?></span>
                    </div>
                    <div class="revenue-item">
                        <span class="revenue-label"><?php echo esc_html__('Refunds', 'classflow-pro'); ?></span>
                        <span class="revenue-value text-red">-<?php echo esc_html('$' . number_format($monthlyRevenue['refunded_amount'], 2)); ?></span>
                    </div>
                    <div class="revenue-item">
                        <span class="revenue-label"><?php echo esc_html__('Net Revenue', 'classflow-pro'); ?></span>
                        <span class="revenue-value text-green"><?php echo esc_html('$' . number_format($monthlyRevenue['net_revenue'], 2)); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderQuickActionsWidget(): void {
        ?>
        <div class="postbox classflow-pro-widget">
            <h2 class="hndle"><?php echo esc_html__('Quick Actions', 'classflow-pro'); ?></h2>
            <div class="inside">
                <div class="classflow-pro-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=classflow-pro-classes&action=new'); ?>" class="button button-primary">
                        <?php echo esc_html__('Add New Class', 'classflow-pro'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=classflow-pro-schedules&action=new'); ?>" class="button">
                        <?php echo esc_html__('Schedule Class', 'classflow-pro'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=classflow-pro-bookings&action=new'); ?>" class="button">
                        <?php echo esc_html__('Create Booking', 'classflow-pro'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=classflow-pro-reports'); ?>" class="button">
                        <?php echo esc_html__('View Reports', 'classflow-pro'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    private function renderSystemStatusWidget(): void {
        $settings = $this->container->get('settings');
        
        $checks = [
            'payment' => $settings->get('payment.stripe_test_secret_key') ? true : false,
            'email' => $settings->get('email.from_email') ? true : false,
            'timezone' => get_option('timezone_string') ? true : false,
        ];
        
        ?>
        <div class="postbox classflow-pro-widget">
            <h2 class="hndle"><?php echo esc_html__('System Status', 'classflow-pro'); ?></h2>
            <div class="inside">
                <ul class="classflow-pro-system-status">
                    <li>
                        <span class="dashicons dashicons-<?php echo $checks['payment'] ? 'yes' : 'warning'; ?>"></span>
                        <?php echo esc_html__('Payment Gateway', 'classflow-pro'); ?>
                        <?php if (!$checks['payment']): ?>
                            <a href="<?php echo admin_url('admin.php?page=classflow-pro-settings&tab=payment'); ?>">
                                <?php echo esc_html__('Configure', 'classflow-pro'); ?>
                            </a>
                        <?php endif; ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-<?php echo $checks['email'] ? 'yes' : 'warning'; ?>"></span>
                        <?php echo esc_html__('Email Settings', 'classflow-pro'); ?>
                        <?php if (!$checks['email']): ?>
                            <a href="<?php echo admin_url('admin.php?page=classflow-pro-settings&tab=email'); ?>">
                                <?php echo esc_html__('Configure', 'classflow-pro'); ?>
                            </a>
                        <?php endif; ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-<?php echo $checks['timezone'] ? 'yes' : 'warning'; ?>"></span>
                        <?php echo esc_html__('Timezone', 'classflow-pro'); ?>
                        <?php if (!$checks['timezone']): ?>
                            <a href="<?php echo admin_url('options-general.php'); ?>">
                                <?php echo esc_html__('Set Timezone', 'classflow-pro'); ?>
                            </a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }
}