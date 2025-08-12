<?php
namespace ClassFlowPro\Admin;

if (!defined('ABSPATH')) { exit; }

class Customers
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        
        // Enqueue admin styles with cache busting
        wp_enqueue_style('cfp-admin', CFP_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.' . time());
        
        // Add inline styles to ensure new design loads immediately
        wp_add_inline_style('cfp-admin', '
            /* Critical inline styles for immediate rendering */
            .wrap h1, .wrap h2 { display: none !important; }
            .wrap > p.search-box { display: none !important; }
            .wrap > form[method="get"] { display: none !important; }
            .wrap > table.wp-list-table { display: none !important; }
            .wrap > .tablenav { display: none !important; }
        ');
        
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        $user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('cfp_customers_action')) {
            self::handle_post_action();
            // Redirect to prevent form resubmission
            $redirect_url = $action === 'view' && $user_id ? 
                admin_url('admin.php?page=classflow-pro-customers&action=view&user_id=' . $user_id) :
                admin_url('admin.php?page=classflow-pro-customers');
            wp_redirect($redirect_url);
            exit;
        }

        echo '<div class="cfp-admin-wrap">';
        
        if ($action === 'view' && $user_id) {
            self::render_customer_detail($user_id);
        } else {
            self::render_customers_list();
        }
        
        echo '</div>';
    }

    private static function handle_post_action(): void
    {
        $do = sanitize_text_field($_POST['cfp_do'] ?? '');
        $uid = (int)($_POST['user_id'] ?? 0);
        
        if (!$uid) return;
        
        switch ($do) {
            case 'save_profile':
                update_user_meta($uid, 'cfp_phone', sanitize_text_field($_POST['cfp_phone'] ?? ''));
                update_user_meta($uid, 'cfp_dob', sanitize_text_field($_POST['cfp_dob'] ?? ''));
                update_user_meta($uid, 'cfp_emergency_name', sanitize_text_field($_POST['cfp_emergency_name'] ?? ''));
                update_user_meta($uid, 'cfp_emergency_phone', sanitize_text_field($_POST['cfp_emergency_phone'] ?? ''));
                update_user_meta($uid, 'cfp_medical', wp_kses_post($_POST['cfp_medical'] ?? ''));
                update_user_meta($uid, 'cfp_injuries', wp_kses_post($_POST['cfp_injuries'] ?? ''));
                set_transient('cfp_admin_notice', ['type' => 'success', 'message' => __('Profile updated successfully.', 'classflow-pro')], 5);
                break;
                
            case 'add_credits':
                $amount = (int)($_POST['credits_amount'] ?? 0);
                if ($amount > 0) {
                    $reason = sanitize_text_field($_POST['credits_reason'] ?? __('Manual credit adjustment', 'classflow-pro'));
                    \ClassFlowPro\Packages\Manager::grant_package($uid, $reason, $amount, 0, 'usd', null);
                    set_transient('cfp_admin_notice', ['type' => 'success', 'message' => sprintf(__('Added %d credits successfully.', 'classflow-pro'), $amount)], 5);
                }
                break;
                
            case 'remove_credits':
                $amount = (int)($_POST['credits_amount'] ?? 0);
                if ($amount > 0) {
                    $reason = sanitize_text_field($_POST['credits_reason'] ?? __('Manual credit removal', 'classflow-pro'));
                    \ClassFlowPro\Packages\Manager::grant_package($uid, $reason, -$amount, 0, 'usd', null);
                    set_transient('cfp_admin_notice', ['type' => 'success', 'message' => sprintf(__('Removed %d credits successfully.', 'classflow-pro'), $amount)], 5);
                }
                break;
                
            case 'add_note':
                global $wpdb;
                $t = $wpdb->prefix . 'cfp_customer_notes';
                $note = wp_kses_post($_POST['note'] ?? '');
                $visible = !empty($_POST['visible_to_user']) ? 1 : 0;
                if ($note) {
                    $wpdb->insert($t, [
                        'user_id' => $uid,
                        'note' => $note,
                        'visible_to_user' => $visible,
                        'created_by' => get_current_user_id() ?: null
                    ], ['%d', '%s', '%d', '%d']);
                    set_transient('cfp_admin_notice', ['type' => 'success', 'message' => __('Note added successfully.', 'classflow-pro')], 5);
                }
                break;
        }
    }

    private static function render_customers_list(): void
    {
        global $wpdb;
        
        // Get filter parameters
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $filter_credits = isset($_GET['filter_credits']) ? sanitize_text_field($_GET['filter_credits']) : '';
        $filter_bookings = isset($_GET['filter_bookings']) ? sanitize_text_field($_GET['filter_bookings']) : '';
        $sort_by = isset($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : 'name';
        $paged = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        // Display any admin notices
        self::display_admin_notice();
        
        // Calculate stats
        $total_customers = count_users()['avail_roles']['customer'] ?? 0;
        $active_customers = self::get_active_customers_count();
        $customers_with_credits = self::get_customers_with_credits_count();
        $new_this_month = self::get_new_customers_this_month();
        
        ?>
        <!-- Header -->
        <div class="cfp-admin-header">
            <h1><?php esc_html_e('Customers', 'classflow-pro'); ?></h1>
            <p><?php esc_html_e('Manage your customer relationships and track their activity', 'classflow-pro'); ?></p>
        </div>
        
        <!-- Stats Grid -->
        <div class="cfp-stats-grid">
            <div class="cfp-stat-card">
                <div class="cfp-stat-label"><?php esc_html_e('Total Customers', 'classflow-pro'); ?></div>
                <div class="cfp-stat-value"><?php echo number_format_i18n($total_customers); ?></div>
                <div class="cfp-stat-change"><?php printf(esc_html__('+%d this month', 'classflow-pro'), $new_this_month); ?></div>
            </div>
            <div class="cfp-stat-card">
                <div class="cfp-stat-label"><?php esc_html_e('Active Customers', 'classflow-pro'); ?></div>
                <div class="cfp-stat-value"><?php echo number_format_i18n($active_customers); ?></div>
                <div class="cfp-stat-change"><?php esc_html_e('With future bookings', 'classflow-pro'); ?></div>
            </div>
            <div class="cfp-stat-card">
                <div class="cfp-stat-label"><?php esc_html_e('With Credits', 'classflow-pro'); ?></div>
                <div class="cfp-stat-value"><?php echo number_format_i18n($customers_with_credits); ?></div>
                <div class="cfp-stat-change"><?php esc_html_e('Have unused credits', 'classflow-pro'); ?></div>
            </div>
        </div>
        
        <!-- Filters Bar -->
        <div class="cfp-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="classflow-pro-customers" />
                <div class="cfp-filters-row">
                    <div class="cfp-filter-group">
                        <label class="cfp-filter-label"><?php esc_html_e('Search', 'classflow-pro'); ?></label>
                        <input type="search" name="s" class="cfp-filter-input" placeholder="<?php esc_attr_e('Name or email...', 'classflow-pro'); ?>" value="<?php echo esc_attr($search); ?>" />
                    </div>
                    
                    <div class="cfp-filter-group">
                        <label class="cfp-filter-label"><?php esc_html_e('Credits', 'classflow-pro'); ?></label>
                        <select name="filter_credits" class="cfp-filter-select">
                            <option value=""><?php esc_html_e('All', 'classflow-pro'); ?></option>
                            <option value="has_credits" <?php selected($filter_credits, 'has_credits'); ?>><?php esc_html_e('Has Credits', 'classflow-pro'); ?></option>
                            <option value="no_credits" <?php selected($filter_credits, 'no_credits'); ?>><?php esc_html_e('No Credits', 'classflow-pro'); ?></option>
                        </select>
                    </div>
                    
                    <div class="cfp-filter-group">
                        <label class="cfp-filter-label"><?php esc_html_e('Bookings', 'classflow-pro'); ?></label>
                        <select name="filter_bookings" class="cfp-filter-select">
                            <option value=""><?php esc_html_e('All', 'classflow-pro'); ?></option>
                            <option value="has_future" <?php selected($filter_bookings, 'has_future'); ?>><?php esc_html_e('Has Future Bookings', 'classflow-pro'); ?></option>
                            <option value="no_future" <?php selected($filter_bookings, 'no_future'); ?>><?php esc_html_e('No Future Bookings', 'classflow-pro'); ?></option>
                        </select>
                    </div>
                    
                    <div class="cfp-filter-group">
                        <label class="cfp-filter-label"><?php esc_html_e('Sort By', 'classflow-pro'); ?></label>
                        <select name="sort_by" class="cfp-filter-select">
                            <option value="name" <?php selected($sort_by, 'name'); ?>><?php esc_html_e('Name', 'classflow-pro'); ?></option>
                            <option value="newest" <?php selected($sort_by, 'newest'); ?>><?php esc_html_e('Newest First', 'classflow-pro'); ?></option>
                            <option value="credits" <?php selected($sort_by, 'credits'); ?>><?php esc_html_e('Credits (High to Low)', 'classflow-pro'); ?></option>
                            <option value="last_booking" <?php selected($sort_by, 'last_booking'); ?>><?php esc_html_e('Last Booking', 'classflow-pro'); ?></option>
                        </select>
                    </div>
                    
                    <div class="cfp-filter-buttons">
                        <button type="submit" class="cfp-btn cfp-btn-primary">
                            <span class="dashicons dashicons-filter"></span>
                            <?php esc_html_e('Apply Filters', 'classflow-pro'); ?>
                        </button>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-customers')); ?>" class="cfp-btn cfp-btn-secondary">
                            <?php esc_html_e('Clear', 'classflow-pro'); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Customers Table -->
        <div class="cfp-customers-table">
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Customer', 'classflow-pro'); ?></th>
                        <th><?php esc_html_e('Credits', 'classflow-pro'); ?></th>
                        <th><?php esc_html_e('Total Bookings', 'classflow-pro'); ?></th>
                        <th><?php esc_html_e('Status', 'classflow-pro'); ?></th>
                        <th><?php esc_html_e('Joined', 'classflow-pro'); ?></th>
                        <th><?php esc_html_e('Actions', 'classflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $customers = self::get_filtered_customers($search, $filter_credits, $filter_bookings, $sort_by, $per_page, $offset);
                    
                    if (empty($customers)) {
                        echo '<tr><td colspan="6" style="text-align: center; padding: 40px;">';
                        echo esc_html__('No customers found matching your criteria.', 'classflow-pro');
                        echo '</td></tr>';
                    } else {
                        foreach ($customers as $customer) {
                            self::render_customer_row($customer);
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php
        $total_filtered = self::get_filtered_customers_count($search, $filter_credits, $filter_bookings);
        $total_pages = ceil($total_filtered / $per_page);
        
        if ($total_pages > 1) {
            echo '<div class="cfp-pagination">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'total' => $total_pages,
                'current' => $paged,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ]);
            echo '</div>';
        }
        ?>
        <?php
    }

    private static function render_customer_row($customer): void
    {
        $user_id = $customer->ID;
        $credits = \ClassFlowPro\Packages\Manager::get_user_credits($user_id);
        $bookings_count = self::get_user_bookings_count($user_id);
        $has_future_bookings = self::user_has_future_bookings($user_id);
        $user_registered = get_userdata($user_id)->user_registered;
        
        // Get initials for avatar
        $name_parts = explode(' ', $customer->display_name ?: 'Unknown');
        $initials = '';
        foreach ($name_parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }
        if (empty($initials)) {
            $initials = strtoupper(substr($customer->user_email, 0, 2));
        }
        
        $view_url = admin_url('admin.php?page=classflow-pro-customers&action=view&user_id=' . $user_id);
        ?>
        <tr>
            <td>
                <div class="cfp-customer-info">
                    <div class="cfp-customer-avatar"><?php echo esc_html($initials); ?></div>
                    <div class="cfp-customer-details">
                        <h4><?php echo esc_html($customer->display_name ?: __('(No name)', 'classflow-pro')); ?></h4>
                        <p><?php echo esc_html($customer->user_email); ?></p>
                    </div>
                </div>
            </td>
            <td>
                <?php if ($credits > 0): ?>
                    <span class="cfp-badge cfp-badge-success"><?php echo number_format_i18n($credits); ?> credits</span>
                <?php else: ?>
                    <span class="cfp-badge cfp-badge-neutral"><?php esc_html_e('No credits', 'classflow-pro'); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php echo number_format_i18n($bookings_count); ?>
            </td>
            <td>
                <?php if ($has_future_bookings): ?>
                    <span class="cfp-badge cfp-badge-info"><?php esc_html_e('Active', 'classflow-pro'); ?></span>
                <?php else: ?>
                    <span class="cfp-badge cfp-badge-neutral"><?php esc_html_e('Inactive', 'classflow-pro'); ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php echo esc_html(human_time_diff(strtotime($user_registered), current_time('timestamp')) . ' ' . __('ago', 'classflow-pro')); ?>
            </td>
            <td>
                <div class="cfp-actions">
                    <a href="<?php echo esc_url($view_url); ?>" class="cfp-action-btn">
                        <?php esc_html_e('View Details', 'classflow-pro'); ?>
                    </a>
                </div>
            </td>
        </tr>
        <?php
    }

    private static function render_customer_detail(int $user_id): void
    {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Customer not found.', 'classflow-pro') . '</p></div>';
            return;
        }
        
        // Get customer data
        $credits = \ClassFlowPro\Packages\Manager::get_user_credits($user_id);
        $phone = get_user_meta($user_id, 'cfp_phone', true);
        $dob = get_user_meta($user_id, 'cfp_dob', true);
        $emergency_name = get_user_meta($user_id, 'cfp_emergency_name', true);
        $emergency_phone = get_user_meta($user_id, 'cfp_emergency_phone', true);
        $medical = get_user_meta($user_id, 'cfp_medical', true);
        $injuries = get_user_meta($user_id, 'cfp_injuries', true);
        
        // Get initials for avatar
        $name_parts = explode(' ', $user->display_name ?: 'Unknown');
        $initials = '';
        foreach ($name_parts as $part) {
            if (!empty($part)) {
                $initials .= strtoupper(substr($part, 0, 1));
                if (strlen($initials) >= 2) break;
            }
        }
        if (empty($initials)) {
            $initials = strtoupper(substr($user->user_email, 0, 2));
        }
        
        // Get stats
        $total_bookings = self::get_user_bookings_count($user_id);
        $future_bookings = self::get_user_future_bookings_count($user_id);
        $total_spent = self::get_user_total_spent($user_id);
        $last_booking = self::get_user_last_booking_date($user_id);
        
        // Display any admin notices
        self::display_admin_notice();
        ?>
        
        <!-- Back Button -->
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-customers')); ?>" class="cfp-btn cfp-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e('Back to Customers', 'classflow-pro'); ?>
            </a>
        </p>
        
        <!-- Customer Detail Layout -->
        <div class="cfp-customer-detail">
            <!-- Main Content -->
            <div class="cfp-main-content">
                <!-- Profile Card -->
                <div class="cfp-profile-card">
                    <div class="cfp-profile-header">
                        <div class="cfp-profile-avatar"><?php echo esc_html($initials); ?></div>
                        <div class="cfp-profile-info">
                            <h2><?php echo esc_html($user->display_name ?: __('(No name)', 'classflow-pro')); ?></h2>
                            <p><?php echo esc_html($user->user_email); ?></p>
                        </div>
                    </div>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('cfp_customers_action'); ?>
                        <input type="hidden" name="cfp_do" value="save_profile" />
                        <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" />
                        
                        <div class="cfp-form-grid">
                            <div class="cfp-form-group">
                                <label class="cfp-form-label"><?php esc_html_e('Phone Number', 'classflow-pro'); ?></label>
                                <input type="tel" name="cfp_phone" class="cfp-form-input" value="<?php echo esc_attr($phone); ?>" placeholder="<?php esc_attr_e('(555) 123-4567', 'classflow-pro'); ?>" />
                            </div>
                            
                            <div class="cfp-form-group">
                                <label class="cfp-form-label"><?php esc_html_e('Date of Birth', 'classflow-pro'); ?></label>
                                <input type="date" name="cfp_dob" class="cfp-form-input" value="<?php echo esc_attr($dob); ?>" />
                            </div>
                            
                            <div class="cfp-form-group">
                                <label class="cfp-form-label"><?php esc_html_e('Emergency Contact Name', 'classflow-pro'); ?></label>
                                <input type="text" name="cfp_emergency_name" class="cfp-form-input" value="<?php echo esc_attr($emergency_name); ?>" placeholder="<?php esc_attr_e('John Doe', 'classflow-pro'); ?>" />
                            </div>
                            
                            <div class="cfp-form-group">
                                <label class="cfp-form-label"><?php esc_html_e('Emergency Contact Phone', 'classflow-pro'); ?></label>
                                <input type="tel" name="cfp_emergency_phone" class="cfp-form-input" value="<?php echo esc_attr($emergency_phone); ?>" placeholder="<?php esc_attr_e('(555) 987-6543', 'classflow-pro'); ?>" />
                            </div>
                            
                            <div class="cfp-form-group full-width">
                                <label class="cfp-form-label"><?php esc_html_e('Medical Conditions', 'classflow-pro'); ?></label>
                                <textarea name="cfp_medical" class="cfp-form-textarea" placeholder="<?php esc_attr_e('Any medical conditions we should be aware of...', 'classflow-pro'); ?>"><?php echo esc_textarea($medical); ?></textarea>
                            </div>
                            
                            <div class="cfp-form-group full-width">
                                <label class="cfp-form-label"><?php esc_html_e('Injuries/Surgeries', 'classflow-pro'); ?></label>
                                <textarea name="cfp_injuries" class="cfp-form-textarea" placeholder="<?php esc_attr_e('Any past injuries or surgeries...', 'classflow-pro'); ?>"><?php echo esc_textarea($injuries); ?></textarea>
                            </div>
                        </div>
                        
                        <div style="margin-top: 24px;">
                            <button type="submit" class="cfp-btn cfp-btn-primary">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e('Save Changes', 'classflow-pro'); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Tabs Section -->
                <div class="cfp-tabs">
                    <div class="cfp-tab-nav">
                        <button class="active" data-tab="notes"><?php esc_html_e('Notes', 'classflow-pro'); ?></button>
                        <button data-tab="bookings"><?php esc_html_e('Bookings', 'classflow-pro'); ?></button>
                        <button data-tab="intake"><?php esc_html_e('Intake Forms', 'classflow-pro'); ?></button>
                        <button data-tab="packages"><?php esc_html_e('Packages', 'classflow-pro'); ?></button>
                    </div>
                    
                    <!-- Notes Tab -->
                    <div class="cfp-tab-content" data-content="notes">
                        <form method="post" action="" style="margin-bottom: 24px;">
                            <?php wp_nonce_field('cfp_customers_action'); ?>
                            <input type="hidden" name="cfp_do" value="add_note" />
                            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" />
                            
                            <div class="cfp-form-group">
                                <label class="cfp-form-label"><?php esc_html_e('Add Note', 'classflow-pro'); ?></label>
                                <textarea name="note" class="cfp-form-textarea" placeholder="<?php esc_attr_e('Enter your note here...', 'classflow-pro'); ?>"></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 12px; align-items: center;">
                                <label>
                                    <input type="checkbox" name="visible_to_user" value="1" />
                                    <?php esc_html_e('Visible to customer', 'classflow-pro'); ?>
                                </label>
                                <button type="submit" class="cfp-btn cfp-btn-primary">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php esc_html_e('Add Note', 'classflow-pro'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <div class="cfp-notes-list">
                            <?php
                            global $wpdb;
                            $t = $wpdb->prefix . 'cfp_customer_notes';
                            $notes = $wpdb->get_results($wpdb->prepare(
                                "SELECT n.*, u.display_name AS author 
                                FROM $t n 
                                LEFT JOIN {$wpdb->users} u ON u.ID = n.created_by 
                                WHERE n.user_id = %d 
                                ORDER BY n.created_at DESC",
                                $user_id
                            ), ARRAY_A);
                            
                            if (empty($notes)) {
                                echo '<p style="text-align: center; color: #6b7280; padding: 20px;">' . esc_html__('No notes yet.', 'classflow-pro') . '</p>';
                            } else {
                                foreach ($notes as $note) {
                                    ?>
                                    <div class="cfp-note-item">
                                        <div class="cfp-note-header">
                                            <span><?php echo esc_html($note['author'] ?: __('System', 'classflow-pro')); ?></span>
                                            <span><?php echo esc_html(human_time_diff(strtotime($note['created_at']), current_time('timestamp')) . ' ' . __('ago', 'classflow-pro')); ?></span>
                                        </div>
                                        <div class="cfp-note-content">
                                            <?php echo wp_kses_post($note['note']); ?>
                                            <?php if ($note['visible_to_user']): ?>
                                                <span class="cfp-badge cfp-badge-info" style="margin-left: 8px; font-size: 11px;"><?php esc_html_e('Visible to customer', 'classflow-pro'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Bookings Tab -->
                    <div class="cfp-tab-content" data-content="bookings" style="display: none;">
                        <?php self::render_customer_bookings($user_id); ?>
                    </div>
                    
                    <!-- Intake Forms Tab -->
                    <div class="cfp-tab-content" data-content="intake" style="display: none;">
                        <?php self::render_customer_intake_forms($user_id); ?>
                    </div>
                    
                    <!-- Packages Tab -->
                    <div class="cfp-tab-content" data-content="packages" style="display: none;">
                        <?php self::render_customer_packages($user_id); ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="cfp-sidebar">
                <!-- Credits Card -->
                <div class="cfp-credits-card">
                    <h3><?php esc_html_e('Credits', 'classflow-pro'); ?></h3>
                    <div class="cfp-credits-balance"><?php echo number_format_i18n($credits); ?></div>
                    <div class="cfp-credits-actions">
                        <button onclick="openCreditsModal('add')" class="cfp-credits-btn">
                            <?php esc_html_e('Add Credits', 'classflow-pro'); ?>
                        </button>
                        <button onclick="openCreditsModal('remove')" class="cfp-credits-btn">
                            <?php esc_html_e('Remove Credits', 'classflow-pro'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="cfp-quick-stats">
                    <h3><?php esc_html_e('Quick Stats', 'classflow-pro'); ?></h3>
                    <div class="cfp-stat-item">
                        <span class="cfp-stat-item-label"><?php esc_html_e('Total Bookings', 'classflow-pro'); ?></span>
                        <span class="cfp-stat-item-value"><?php echo number_format_i18n($total_bookings); ?></span>
                    </div>
                    <div class="cfp-stat-item">
                        <span class="cfp-stat-item-label"><?php esc_html_e('Future Bookings', 'classflow-pro'); ?></span>
                        <span class="cfp-stat-item-value"><?php echo number_format_i18n($future_bookings); ?></span>
                    </div>
                    <div class="cfp-stat-item">
                        <span class="cfp-stat-item-label"><?php esc_html_e('Total Spent', 'classflow-pro'); ?></span>
                        <span class="cfp-stat-item-value">$<?php echo number_format($total_spent / 100, 2); ?></span>
                    </div>
                    <div class="cfp-stat-item">
                        <span class="cfp-stat-item-label"><?php esc_html_e('Last Booking', 'classflow-pro'); ?></span>
                        <span class="cfp-stat-item-value">
                            <?php 
                            if ($last_booking) {
                                echo esc_html(human_time_diff(strtotime($last_booking), current_time('timestamp')) . ' ' . __('ago', 'classflow-pro'));
                            } else {
                                esc_html_e('Never', 'classflow-pro');
                            }
                            ?>
                        </span>
                    </div>
                    <div class="cfp-stat-item">
                        <span class="cfp-stat-item-label"><?php esc_html_e('Member Since', 'classflow-pro'); ?></span>
                        <span class="cfp-stat-item-value"><?php echo esc_html(date_i18n('M j, Y', strtotime($user->user_registered))); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Credits Modal -->
        <div id="credits-modal" class="cfp-modal-overlay">
            <div class="cfp-modal">
                <div class="cfp-modal-header">
                    <h3 id="credits-modal-title"><?php esc_html_e('Add Credits', 'classflow-pro'); ?></h3>
                </div>
                <form method="post" action="">
                    <?php wp_nonce_field('cfp_customers_action'); ?>
                    <input type="hidden" name="cfp_do" id="credits-action" value="add_credits" />
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" />
                    
                    <div class="cfp-modal-body">
                        <div class="cfp-form-group">
                            <label class="cfp-form-label"><?php esc_html_e('Number of Credits', 'classflow-pro'); ?></label>
                            <input type="number" name="credits_amount" class="cfp-form-input" min="1" value="1" required />
                        </div>
                        
                        <div class="cfp-form-group">
                            <label class="cfp-form-label"><?php esc_html_e('Reason (Optional)', 'classflow-pro'); ?></label>
                            <input type="text" name="credits_reason" class="cfp-form-input" placeholder="<?php esc_attr_e('e.g., Promotional credit, Refund, etc.', 'classflow-pro'); ?>" />
                        </div>
                    </div>
                    
                    <div class="cfp-modal-footer">
                        <button type="button" onclick="closeCreditsModal()" class="cfp-btn cfp-btn-secondary">
                            <?php esc_html_e('Cancel', 'classflow-pro'); ?>
                        </button>
                        <button type="submit" class="cfp-btn cfp-btn-primary">
                            <span id="credits-submit-text"><?php esc_html_e('Add Credits', 'classflow-pro'); ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function openCreditsModal(action) {
            const modal = document.getElementById('credits-modal');
            const title = document.getElementById('credits-modal-title');
            const actionInput = document.getElementById('credits-action');
            const submitText = document.getElementById('credits-submit-text');
            
            if (action === 'add') {
                title.textContent = '<?php echo esc_js(__('Add Credits', 'classflow-pro')); ?>';
                actionInput.value = 'add_credits';
                submitText.textContent = '<?php echo esc_js(__('Add Credits', 'classflow-pro')); ?>';
            } else {
                title.textContent = '<?php echo esc_js(__('Remove Credits', 'classflow-pro')); ?>';
                actionInput.value = 'remove_credits';
                submitText.textContent = '<?php echo esc_js(__('Remove Credits', 'classflow-pro')); ?>';
            }
            
            modal.classList.add('active');
        }
        
        function closeCreditsModal() {
            document.getElementById('credits-modal').classList.remove('active');
        }
        
        // Tab functionality
        document.querySelectorAll('.cfp-tab-nav button').forEach(button => {
            button.addEventListener('click', function() {
                const tab = this.getAttribute('data-tab');
                
                // Update active button
                document.querySelectorAll('.cfp-tab-nav button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                document.querySelectorAll('.cfp-tab-content').forEach(content => {
                    if (content.getAttribute('data-content') === tab) {
                        content.style.display = 'block';
                    } else {
                        content.style.display = 'none';
                    }
                });
            });
        });
        </script>
        <?php
    }

    private static function render_customer_bookings(int $user_id): void
    {
        global $wpdb;
        $bookings_tbl = $wpdb->prefix . 'cfp_bookings';
        $schedules_tbl = $wpdb->prefix . 'cfp_schedules';
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, s.class_id, s.location_id, s.start_time, s.end_time, s.instructor_id
            FROM $bookings_tbl b 
            JOIN $schedules_tbl s ON s.id = b.schedule_id 
            WHERE b.user_id = %d 
            ORDER BY s.start_time DESC 
            LIMIT 100",
            $user_id
        ), ARRAY_A);
        
        if (empty($bookings)) {
            echo '<p style="text-align: center; color: #6b7280; padding: 20px;">' . esc_html__('No bookings found.', 'classflow-pro') . '</p>';
            return;
        }
        ?>
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Class', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Date & Time', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Amount', 'classflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo esc_html(\ClassFlowPro\Utils\Entities::class_name((int)$booking['class_id'])); ?></td>
                        <td><?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($booking['start_time'] . ' UTC'))); ?></td>
                        <td>
                            <?php
                            $now = current_time('mysql', true);
                            if ($booking['status'] === 'cancelled') {
                                echo '<span class="cfp-badge cfp-badge-danger">' . esc_html__('Cancelled', 'classflow-pro') . '</span>';
                            } elseif ($booking['start_time'] > $now) {
                                echo '<span class="cfp-badge cfp-badge-info">' . esc_html__('Upcoming', 'classflow-pro') . '</span>';
                            } else {
                                echo '<span class="cfp-badge cfp-badge-success">' . esc_html__('Completed', 'classflow-pro') . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($booking['credits_used']) {
                                echo '<span class="cfp-badge cfp-badge-warning">' . esc_html__('Credit', 'classflow-pro') . '</span>';
                            } elseif ($booking['amount_cents'] > 0) {
                                echo '$' . number_format($booking['amount_cents'] / 100, 2);
                            } else {
                                echo '<span class="cfp-badge cfp-badge-neutral">' . esc_html__('Free', 'classflow-pro') . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function render_customer_intake_forms(int $user_id): void
    {
        global $wpdb;
        $intake_tbl = $wpdb->prefix . 'cfp_intake_forms';
        $intakes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $intake_tbl WHERE user_id = %d ORDER BY signed_at DESC",
            $user_id
        ), ARRAY_A);
        
        if (empty($intakes)) {
            echo '<p style="text-align: center; color: #6b7280; padding: 20px;">' . esc_html__('No intake forms on file.', 'classflow-pro') . '</p>';
            return;
        }
        
        foreach ($intakes as $intake) {
            $data = json_decode($intake['data'], true);
            ?>
            <div class="cfp-note-item">
                <div class="cfp-note-header">
                    <span><?php esc_html_e('Signed', 'classflow-pro'); ?>: <?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($intake['signed_at'] . ' UTC'))); ?></span>
                    <span><?php esc_html_e('Version', 'classflow-pro'); ?>: <?php echo esc_html($intake['version']); ?></span>
                </div>
                <div class="cfp-note-content">
                    <?php if (is_array($data)): ?>
                        <dl style="margin: 0;">
                            <?php foreach ($data as $key => $value): ?>
                                <dt style="font-weight: 600; margin-top: 8px;"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?>:</dt>
                                <dd style="margin: 0 0 0 20px; color: #6b7280;">
                                    <?php 
                                    if (is_bool($value)) {
                                        echo $value ? esc_html__('Yes', 'classflow-pro') : esc_html__('No', 'classflow-pro');
                                    } else {
                                        echo esc_html($value ?: __('(Not provided)', 'classflow-pro'));
                                    }
                                    ?>
                                </dd>
                            <?php endforeach; ?>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }

    private static function render_customer_packages(int $user_id): void
    {
        global $wpdb;
        $pk = $wpdb->prefix . 'cfp_packages';
        $packages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $pk WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
        
        if (empty($packages)) {
            echo '<p style="text-align: center; color: #6b7280; padding: 20px;">' . esc_html__('No packages purchased.', 'classflow-pro') . '</p>';
            return;
        }
        ?>
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Package', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Credits', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Remaining', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Price', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Expires', 'classflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packages as $package): ?>
                    <tr>
                        <td><?php echo esc_html($package['name']); ?></td>
                        <td><?php echo number_format_i18n($package['credits']); ?></td>
                        <td>
                            <?php 
                            if ($package['credits_remaining'] > 0) {
                                echo '<span class="cfp-badge cfp-badge-success">' . number_format_i18n($package['credits_remaining']) . '</span>';
                            } else {
                                echo '<span class="cfp-badge cfp-badge-neutral">' . esc_html__('Used', 'classflow-pro') . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($package['price_cents'] > 0) {
                                echo '$' . number_format($package['price_cents'] / 100, 2);
                            } else {
                                echo '<span class="cfp-badge cfp-badge-info">' . esc_html__('Free', 'classflow-pro') . '</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if ($package['expires_at']) {
                                $expires = strtotime($package['expires_at'] . ' UTC');
                                if ($expires < current_time('timestamp')) {
                                    echo '<span class="cfp-badge cfp-badge-danger">' . esc_html__('Expired', 'classflow-pro') . '</span>';
                                } else {
                                    echo esc_html(date_i18n('M j, Y', $expires));
                                }
                            } else {
                                echo '<span class="cfp-badge cfp-badge-success">' . esc_html__('Never', 'classflow-pro') . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // Helper methods
    private static function get_filtered_customers($search, $filter_credits, $filter_bookings, $sort_by, $per_page, $offset)
    {
        global $wpdb;
        
        $args = [
            'role__in' => ['customer'],
            'number' => $per_page,
            'offset' => $offset,
            'fields' => 'all',
        ];
        
        if ($search) {
            $args['search'] = '*' . esc_attr($search) . '*';
            $args['search_columns'] = ['user_email', 'user_login', 'display_name'];
        }
        
        // Get initial user list
        $users = get_users($args);
        
        // Filter by credits
        if ($filter_credits === 'has_credits') {
            $users = array_filter($users, function($user) {
                return \ClassFlowPro\Packages\Manager::get_user_credits($user->ID) > 0;
            });
        } elseif ($filter_credits === 'no_credits') {
            $users = array_filter($users, function($user) {
                return \ClassFlowPro\Packages\Manager::get_user_credits($user->ID) === 0;
            });
        }
        
        // Filter by bookings
        if ($filter_bookings === 'has_future') {
            $users = array_filter($users, function($user) {
                return self::user_has_future_bookings($user->ID);
            });
        } elseif ($filter_bookings === 'no_future') {
            $users = array_filter($users, function($user) {
                return !self::user_has_future_bookings($user->ID);
            });
        }
        
        // Sort
        if ($sort_by === 'credits') {
            usort($users, function($a, $b) {
                $credits_a = \ClassFlowPro\Packages\Manager::get_user_credits($a->ID);
                $credits_b = \ClassFlowPro\Packages\Manager::get_user_credits($b->ID);
                return $credits_b - $credits_a;
            });
        } elseif ($sort_by === 'newest') {
            usort($users, function($a, $b) {
                return strtotime($b->user_registered) - strtotime($a->user_registered);
            });
        } elseif ($sort_by === 'last_booking') {
            usort($users, function($a, $b) {
                $last_a = self::get_user_last_booking_date($a->ID);
                $last_b = self::get_user_last_booking_date($b->ID);
                return strtotime($last_b ?: '1970-01-01') - strtotime($last_a ?: '1970-01-01');
            });
        }
        
        return $users;
    }

    private static function get_filtered_customers_count($search, $filter_credits, $filter_bookings)
    {
        $args = [
            'role__in' => ['customer'],
            'fields' => 'ID',
        ];
        
        if ($search) {
            $args['search'] = '*' . esc_attr($search) . '*';
            $args['search_columns'] = ['user_email', 'user_login', 'display_name'];
        }
        
        $users = get_users($args);
        
        // Apply same filters as in get_filtered_customers
        if ($filter_credits === 'has_credits') {
            $users = array_filter($users, function($user_id) {
                return \ClassFlowPro\Packages\Manager::get_user_credits($user_id) > 0;
            });
        } elseif ($filter_credits === 'no_credits') {
            $users = array_filter($users, function($user_id) {
                return \ClassFlowPro\Packages\Manager::get_user_credits($user_id) === 0;
            });
        }
        
        if ($filter_bookings === 'has_future') {
            $users = array_filter($users, function($user_id) {
                return self::user_has_future_bookings($user_id);
            });
        } elseif ($filter_bookings === 'no_future') {
            $users = array_filter($users, function($user_id) {
                return !self::user_has_future_bookings($user_id);
            });
        }
        
        return count($users);
    }

    private static function get_active_customers_count()
    {
        global $wpdb;
        $bookings_tbl = $wpdb->prefix . 'cfp_bookings';
        $schedules_tbl = $wpdb->prefix . 'cfp_schedules';
        $now = current_time('mysql', true);
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT b.user_id) 
            FROM $bookings_tbl b 
            JOIN $schedules_tbl s ON s.id = b.schedule_id 
            WHERE s.start_time > '$now' 
            AND b.status IN ('pending', 'confirmed')"
        );
    }

    private static function get_customers_with_credits_count()
    {
        global $wpdb;
        $pk = $wpdb->prefix . 'cfp_packages';
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
            FROM $pk 
            WHERE credits_remaining > 0 
            AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())"
        );
    }

    private static function get_new_customers_this_month()
    {
        $args = [
            'role' => 'customer',
            'date_query' => [
                [
                    'after' => '1 month ago',
                    'inclusive' => true,
                ],
            ],
            'fields' => 'ID',
        ];
        
        return count(get_users($args));
    }

    private static function get_user_bookings_count(int $user_id)
    {
        global $wpdb;
        $bookings_tbl = $wpdb->prefix . 'cfp_bookings';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_tbl WHERE user_id = %d",
            $user_id
        ));
    }

    private static function get_user_future_bookings_count(int $user_id)
    {
        global $wpdb;
        $bookings_tbl = $wpdb->prefix . 'cfp_bookings';
        $schedules_tbl = $wpdb->prefix . 'cfp_schedules';
        $now = current_time('mysql', true);
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM $bookings_tbl b 
            JOIN $schedules_tbl s ON s.id = b.schedule_id 
            WHERE b.user_id = %d 
            AND s.start_time > %s 
            AND b.status IN ('pending', 'confirmed')",
            $user_id,
            $now
        ));
    }

    private static function user_has_future_bookings(int $user_id)
    {
        return self::get_user_future_bookings_count($user_id) > 0;
    }

    private static function get_user_total_spent(int $user_id)
    {
        global $wpdb;
        $bookings_tbl = $wpdb->prefix . 'cfp_bookings';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount_cents) 
            FROM $bookings_tbl 
            WHERE user_id = %d 
            AND status IN ('confirmed', 'completed')",
            $user_id
        ));
        
        return (int) ($total ?: 0);
    }

    private static function get_user_last_booking_date(int $user_id)
    {
        global $wpdb;
        $bookings_tbl = $wpdb->prefix . 'cfp_bookings';
        $schedules_tbl = $wpdb->prefix . 'cfp_schedules';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT s.start_time 
            FROM $bookings_tbl b 
            JOIN $schedules_tbl s ON s.id = b.schedule_id 
            WHERE b.user_id = %d 
            ORDER BY s.start_time DESC 
            LIMIT 1",
            $user_id
        ));
    }

    private static function display_admin_notice()
    {
        $notice = get_transient('cfp_admin_notice');
        if ($notice) {
            $type = $notice['type'] ?? 'info';
            $message = $notice['message'] ?? '';
            
            if ($message) {
                echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible">';
                echo '<p>' . esc_html($message) . '</p>';
                echo '</div>';
            }
            
            delete_transient('cfp_admin_notice');
        }
    }
}