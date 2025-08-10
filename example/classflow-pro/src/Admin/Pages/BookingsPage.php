<?php
declare(strict_types=1);

namespace ClassFlowPro\Admin\Pages;

use ClassFlowPro\Services\Container;

class BookingsPage {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(): void {
        // Handle actions
        if (isset($_POST['action'])) {
            $this->handleAction();
        }
        
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'view':
                $this->renderView();
                break;
            default:
                $this->renderList();
                break;
        }
    }
    
    private function renderList(): void {
        $bookingRepo = $this->container->get('booking_repository');
        
        // Get filter parameters
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
        $schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }
        if ($student_id) {
            $filters['student_id'] = $student_id;
        }
        if ($schedule_id) {
            $filters['schedule_id'] = $schedule_id;
        }
        
        // Get bookings with pagination
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        if ($search) {
            // Search by booking code
            $booking = $bookingRepo->findByBookingCode($search);
            $bookings = $booking ? [$booking] : [];
            $total = count($bookings);
        } else {
            $result = $bookingRepo->paginate($page, $per_page, $filters);
            $bookings = $result['items'];
            $total = $result['total'];
        }
        
        // Show admin notices
        if (isset($_GET['message'])) {
            $this->showAdminNotice();
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Bookings', 'classflow-pro'); ?></h1>
            
            <hr class="wp-header-end">
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="page" value="classflow-pro-bookings">
                    
                    <div class="alignleft actions">
                        <select name="status">
                            <option value=""><?php esc_html_e('All Statuses', 'classflow-pro'); ?></option>
                            <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'classflow-pro'); ?></option>
                            <option value="confirmed" <?php selected($status, 'confirmed'); ?>><?php esc_html_e('Confirmed', 'classflow-pro'); ?></option>
                            <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'classflow-pro'); ?></option>
                            <option value="completed" <?php selected($status, 'completed'); ?>><?php esc_html_e('Completed', 'classflow-pro'); ?></option>
                            <option value="no_show" <?php selected($status, 'no_show'); ?>><?php esc_html_e('No Show', 'classflow-pro'); ?></option>
                        </select>
                        
                        <?php submit_button(__('Filter', 'classflow-pro'), 'button', 'filter_action', false); ?>
                    </div>
                    
                    <div class="alignright">
                        <p class="search-box">
                            <label class="screen-reader-text" for="booking-search-input"><?php esc_html_e('Search by Booking Code:', 'classflow-pro'); ?></label>
                            <input type="search" id="booking-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Booking code...', 'classflow-pro'); ?>">
                            <?php submit_button(__('Search', 'classflow-pro'), 'button', '', false); ?>
                        </p>
                    </div>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Booking Code', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Student', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Class', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Date & Time', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Status', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Payment', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Amount', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Actions', 'classflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('No bookings found.', 'classflow-pro'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): 
                            $schedule = $this->container->get('schedule_repository')->find($booking->getScheduleId());
                            $class = $schedule ? $this->container->get('class_repository')->find($schedule->getClassId()) : null;
                            $student = get_userdata($booking->getStudentId());
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=classflow-pro-bookings&action=view&id=' . $booking->getId()); ?>">
                                            <?php echo esc_html($booking->getBookingCode()); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <span class="view">
                                            <a href="<?php echo admin_url('admin.php?page=classflow-pro-bookings&action=view&id=' . $booking->getId()); ?>">
                                                <?php esc_html_e('View', 'classflow-pro'); ?>
                                            </a>
                                        </span>
                                        <?php if ($booking->canBeCancelled()): ?>
                                            | <span class="cancel">
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=classflow-pro-bookings&action=cancel&id=' . $booking->getId()), 'cancel_booking_' . $booking->getId()); ?>"
                                                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to cancel this booking?', 'classflow-pro'); ?>');">
                                                    <?php esc_html_e('Cancel', 'classflow-pro'); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($student): ?>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $student->ID); ?>">
                                            <?php echo esc_html($student->display_name); ?>
                                        </a>
                                        <br>
                                        <small><?php echo esc_html($student->user_email); ?></small>
                                    <?php else: ?>
                                        <em><?php esc_html_e('Unknown', 'classflow-pro'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($class): ?>
                                        <a href="<?php echo admin_url('admin.php?page=classflow-pro-classes&action=edit&id=' . $class->getId()); ?>">
                                            <?php echo esc_html($class->getName()); ?>
                                        </a>
                                    <?php else: ?>
                                        <em><?php esc_html_e('Unknown', 'classflow-pro'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($schedule): ?>
                                        <?php echo esc_html($schedule->getStartTime()->format('M j, Y')); ?><br>
                                        <small><?php echo esc_html($schedule->getStartTime()->format('g:i A') . ' - ' . $schedule->getEndTime()->format('g:i A')); ?></small>
                                    <?php else: ?>
                                        <em><?php esc_html_e('Unknown', 'classflow-pro'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="booking-status status-<?php echo esc_attr($booking->getStatus()); ?>">
                                        <?php echo esc_html($booking->getStatusLabel()); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="payment-status status-<?php echo esc_attr($booking->getPaymentStatus()); ?>">
                                        <?php echo esc_html($booking->getPaymentStatusLabel()); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($booking->getFormattedAmount()); ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=classflow-pro-bookings&action=view&id=' . $booking->getId()); ?>" class="button button-small">
                                        <?php esc_html_e('Details', 'classflow-pro'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if (!$search && $total > $per_page): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $total_pages = ceil($total / $per_page);
                        $pagination_args = [
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'total' => $total_pages,
                            'current' => $page,
                            'show_all' => false,
                            'end_size' => 1,
                            'mid_size' => 2,
                            'prev_next' => true,
                            'prev_text' => __('&laquo; Previous', 'classflow-pro'),
                            'next_text' => __('Next &raquo;', 'classflow-pro'),
                            'type' => 'plain',
                        ];
                        
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
            .booking-status, .payment-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .status-pending { background-color: #f0f0f1; color: #50575e; }
            .status-confirmed { background-color: #edfaef; color: #1e8e3e; }
            .status-cancelled { background-color: #fef1f1; color: #d63638; }
            .status-completed { background-color: #e0f2fe; color: #0073aa; }
            .status-no_show { background-color: #fcf0e4; color: #996800; }
            .status-paid { background-color: #edfaef; color: #1e8e3e; }
            .status-refunded { background-color: #fef1f1; color: #d63638; }
            .status-failed { background-color: #fef1f1; color: #d63638; }
        </style>
        <?php
    }
    
    private function renderView(): void {
        if (!isset($_GET['id'])) {
            wp_die(__('Invalid booking ID.', 'classflow-pro'));
        }
        
        $id = intval($_GET['id']);
        $booking = $this->container->get('booking_repository')->find($id);
        
        if (!$booking) {
            wp_die(__('Booking not found.', 'classflow-pro'));
        }
        
        $schedule = $this->container->get('schedule_repository')->find($booking->getScheduleId());
        $class = $schedule ? $this->container->get('class_repository')->find($schedule->getClassId()) : null;
        $student = get_userdata($booking->getStudentId());
        $instructor = $schedule ? get_userdata($schedule->getInstructorId()) : null;
        $location = $schedule && $schedule->getLocationId() ? 
            $this->container->get('location_repository')->find($schedule->getLocationId()) : null;
        
        // Get payment history
        $payments = $this->container->get('payment_repository')->findByBooking($booking->getId());
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html__('Booking Details', 'classflow-pro'); ?>
                <a href="<?php echo admin_url('admin.php?page=classflow-pro-bookings'); ?>" class="page-title-action">
                    <?php echo esc_html__('Back to Bookings', 'classflow-pro'); ?>
                </a>
            </h1>
            
            <?php if (isset($_GET['message'])): ?>
                <?php $this->showAdminNotice(); ?>
            <?php endif; ?>
            
            <div class="booking-details-container">
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Booking Information', 'classflow-pro'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Booking Code:', 'classflow-pro'); ?></th>
                                <td><strong><?php echo esc_html($booking->getBookingCode()); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Status:', 'classflow-pro'); ?></th>
                                <td>
                                    <span class="booking-status status-<?php echo esc_attr($booking->getStatus()); ?>">
                                        <?php echo esc_html($booking->getStatusLabel()); ?>
                                    </span>
                                    <?php if ($booking->canBeCancelled()): ?>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=classflow-pro-bookings&action=cancel&id=' . $booking->getId()), 'cancel_booking_' . $booking->getId()); ?>" 
                                           class="button button-small" style="margin-left: 10px;"
                                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to cancel this booking?', 'classflow-pro'); ?>');">
                                            <?php esc_html_e('Cancel Booking', 'classflow-pro'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Created:', 'classflow-pro'); ?></th>
                                <td><?php echo esc_html($booking->getCreatedAt()->format('F j, Y g:i A')); ?></td>
                            </tr>
                            <?php if ($booking->getNotes()): ?>
                            <tr>
                                <th><?php esc_html_e('Notes:', 'classflow-pro'); ?></th>
                                <td><?php echo esc_html($booking->getNotes()); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Student Information', 'classflow-pro'); ?></h2>
                    <div class="inside">
                        <?php if ($student): ?>
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e('Name:', 'classflow-pro'); ?></th>
                                    <td>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $student->ID); ?>">
                                            <?php echo esc_html($student->display_name); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Email:', 'classflow-pro'); ?></th>
                                    <td><?php echo esc_html($student->user_email); ?></td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <p><?php esc_html_e('Student information not available.', 'classflow-pro'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Class Information', 'classflow-pro'); ?></h2>
                    <div class="inside">
                        <?php if ($class && $schedule): ?>
                            <table class="form-table">
                                <tr>
                                    <th><?php esc_html_e('Class:', 'classflow-pro'); ?></th>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=classflow-pro-classes&action=edit&id=' . $class->getId()); ?>">
                                            <?php echo esc_html($class->getName()); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Date:', 'classflow-pro'); ?></th>
                                    <td><?php echo esc_html($schedule->getStartTime()->format('F j, Y')); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Time:', 'classflow-pro'); ?></th>
                                    <td><?php echo esc_html($schedule->getStartTime()->format('g:i A') . ' - ' . $schedule->getEndTime()->format('g:i A')); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Instructor:', 'classflow-pro'); ?></th>
                                    <td>
                                        <?php if ($instructor): ?>
                                            <a href="<?php echo admin_url('user-edit.php?user_id=' . $instructor->ID); ?>">
                                                <?php echo esc_html($instructor->display_name); ?>
                                            </a>
                                        <?php else: ?>
                                            <em><?php esc_html_e('Not assigned', 'classflow-pro'); ?></em>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Location:', 'classflow-pro'); ?></th>
                                    <td>
                                        <?php if ($location): ?>
                                            <?php echo esc_html($location->getName()); ?>
                                            <?php if ($location->getAddress()): ?>
                                                <br><small><?php echo esc_html($location->getAddress()); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php esc_html_e('Online', 'classflow-pro'); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <p><?php esc_html_e('Class information not available.', 'classflow-pro'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Payment Information', 'classflow-pro'); ?></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Amount:', 'classflow-pro'); ?></th>
                                <td><strong><?php echo esc_html($booking->getFormattedAmount()); ?></strong></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Payment Status:', 'classflow-pro'); ?></th>
                                <td>
                                    <span class="payment-status status-<?php echo esc_attr($booking->getPaymentStatus()); ?>">
                                        <?php echo esc_html($booking->getPaymentStatusLabel()); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if (!empty($payments)): ?>
                            <h3><?php esc_html_e('Transaction History', 'classflow-pro'); ?></h3>
                            <table class="widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Date', 'classflow-pro'); ?></th>
                                        <th><?php esc_html_e('Amount', 'classflow-pro'); ?></th>
                                        <th><?php esc_html_e('Gateway', 'classflow-pro'); ?></th>
                                        <th><?php esc_html_e('Transaction ID', 'classflow-pro'); ?></th>
                                        <th><?php esc_html_e('Status', 'classflow-pro'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo esc_html($payment->getCreatedAt()->format('M j, Y g:i A')); ?></td>
                                            <td><?php echo esc_html($payment->getFormattedAmount()); ?></td>
                                            <td><?php echo esc_html(ucfirst($payment->getGateway())); ?></td>
                                            <td>
                                                <code><?php echo esc_html($payment->getTransactionId() ?: '-'); ?></code>
                                            </td>
                                            <td>
                                                <span class="payment-status status-<?php echo esc_attr($payment->getStatus()); ?>">
                                                    <?php echo esc_html($payment->getStatus()); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($booking->getStatus() === 'confirmed' || $booking->getStatus() === 'completed'): ?>
                <div class="postbox">
                    <h2 class="hndle"><?php esc_html_e('Actions', 'classflow-pro'); ?></h2>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('booking_action_' . $booking->getId()); ?>
                            <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->getId()); ?>">
                            
                            <button type="submit" name="action" value="resend_confirmation" class="button">
                                <?php esc_html_e('Resend Confirmation Email', 'classflow-pro'); ?>
                            </button>
                            
                            <?php if ($booking->getStatus() === 'confirmed'): ?>
                                <button type="submit" name="action" value="mark_attended" class="button">
                                    <?php esc_html_e('Mark as Attended', 'classflow-pro'); ?>
                                </button>
                                
                                <button type="submit" name="action" value="mark_no_show" class="button">
                                    <?php esc_html_e('Mark as No Show', 'classflow-pro'); ?>
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .booking-details-container {
                max-width: 800px;
                margin-top: 20px;
            }
            .postbox {
                margin-bottom: 20px;
            }
            .postbox .hndle {
                padding: 10px 15px;
                font-size: 14px;
                margin: 0;
                line-height: 1.4;
                cursor: default;
            }
            .postbox .inside {
                padding: 10px 15px;
            }
            .form-table th {
                width: 150px;
                font-weight: 600;
            }
        </style>
        <?php
    }
    
    private function handleAction(): void {
        if (!isset($_POST['booking_id']) || !isset($_POST['_wpnonce'])) {
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'booking_action_' . $booking_id)) {
            wp_die(__('Security check failed.', 'classflow-pro'));
        }
        
        if (!current_user_can('manage_classflow_bookings')) {
            wp_die(__('You do not have permission to perform this action.', 'classflow-pro'));
        }
        
        $action = $_POST['action'];
        $bookingService = $this->container->get('booking_service');
        $booking = $this->container->get('booking_repository')->find($booking_id);
        
        if (!$booking) {
            wp_die(__('Booking not found.', 'classflow-pro'));
        }
        
        switch ($action) {
            case 'resend_confirmation':
                $notificationService = $this->container->get('notification_service');
                $notificationService->sendBookingConfirmation($booking);
                wp_redirect(add_query_arg(['message' => 'email_sent'], wp_get_referer()));
                exit;
                
            case 'mark_attended':
                $booking->setStatus('completed');
                $this->container->get('booking_repository')->save($booking);
                wp_redirect(add_query_arg(['message' => 'status_updated'], wp_get_referer()));
                exit;
                
            case 'mark_no_show':
                $booking->setStatus('no_show');
                $this->container->get('booking_repository')->save($booking);
                wp_redirect(add_query_arg(['message' => 'status_updated'], wp_get_referer()));
                exit;
        }
        
        // Handle cancel action from list
        if (isset($_GET['action']) && $_GET['action'] === 'cancel') {
            $booking_id = intval($_GET['id']);
            
            if (!wp_verify_nonce($_GET['_wpnonce'], 'cancel_booking_' . $booking_id)) {
                wp_die(__('Security check failed.', 'classflow-pro'));
            }
            
            try {
                $bookingService->cancelBooking($booking_id, __('Cancelled by admin', 'classflow-pro'));
                wp_redirect(admin_url('admin.php?page=classflow-pro-bookings&message=cancelled'));
                exit;
            } catch (\Exception $e) {
                wp_die($e->getMessage());
            }
        }
    }
    
    private function showAdminNotice(): void {
        $message = isset($_GET['message']) ? $_GET['message'] : '';
        
        switch ($message) {
            case 'cancelled':
                $text = __('Booking cancelled successfully.', 'classflow-pro');
                $type = 'success';
                break;
            case 'email_sent':
                $text = __('Confirmation email sent successfully.', 'classflow-pro');
                $type = 'success';
                break;
            case 'status_updated':
                $text = __('Booking status updated successfully.', 'classflow-pro');
                $type = 'success';
                break;
            default:
                return;
        }
        
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($text); ?></p>
        </div>
        <?php
    }
}