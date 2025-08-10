<?php
declare(strict_types=1);

namespace ClassFlowPro\Admin\Pages;

use ClassFlowPro\Services\Container;

class InstructorsPage {
    private Container $container;

    public function __construct(Container $container) {
        error_log('InstructorsPage::__construct() called');
        $this->container = $container;
    }

    public function render(): void {
        error_log('InstructorsPage::render() called');
        error_log('GET params: ' . print_r($_GET, true));
        error_log('POST params: ' . print_r($_POST, true));
        error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
        error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
        error_log('Current user: ' . wp_get_current_user()->user_login);
        error_log('User can manage instructors: ' . (current_user_can('manage_classflow_instructors') ? 'YES' : 'NO'));
        
        // Check if ANY POST data exists
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log('POST REQUEST DETECTED!');
            error_log('Raw POST data: ' . file_get_contents('php://input'));
            error_log('All headers: ' . print_r(getallheaders(), true));
        }
        
        // Handle form submissions - match the pattern from ClassesPage
        if (isset($_POST['action']) && $_POST['action'] === 'save_instructor') {
            error_log('Save instructor action detected');
            $this->handleSave();
        }
        
        $action = $_GET['action'] ?? 'list';
        error_log('Current action: ' . $action);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Instructors', 'classflow-pro'); ?></h1>
            
            <?php if ($action === 'list'): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-instructors&action=new')); ?>" class="page-title-action">
                    <?php echo esc_html__('Add New Instructor', 'classflow-pro'); ?>
                </a>
            <?php endif; ?>
            
            <hr class="wp-header-end">
            
            <?php $this->displayMessages(); ?>
            
            <?php
            switch ($action) {
                case 'new':
                    $this->renderForm();
                    break;
                case 'edit':
                    $this->renderForm();
                    break;
                default:
                    $this->renderList();
            }
            ?>
        </div>
        <?php
    }
    
    private function renderList(): void {
        error_log('InstructorsPage::renderList() called');
        
        // Get instructors
        $instructorRepo = $this->container->get('instructor_repository');
        $instructors = $instructorRepo->findAll();
        
        error_log('Found ' . count($instructors) . ' instructors');
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Name', 'classflow-pro'); ?></th>
                    <th><?php echo esc_html__('Email', 'classflow-pro'); ?></th>
                    <th><?php echo esc_html__('Status', 'classflow-pro'); ?></th>
                    <th><?php echo esc_html__('Actions', 'classflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($instructors)): ?>
                    <tr>
                        <td colspan="4"><?php echo esc_html__('No instructors found.', 'classflow-pro'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($instructors as $instructor): ?>
                        <tr>
                            <td><?php echo esc_html($instructor->display_name); ?></td>
                            <td><?php echo esc_html($instructor->user_email); ?></td>
                            <td>
                                <?php 
                                $status = get_user_meta($instructor->ID, 'instructor_status', true) ?: 'active';
                                echo esc_html(ucfirst($status));
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-instructors&action=edit&id=' . $instructor->ID)); ?>">
                                    <?php echo esc_html__('Edit', 'classflow-pro'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }
    
    private function renderForm(): void {
        error_log('InstructorsPage::renderForm() called');
        
        $instructorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $instructor = null;
        
        if ($instructorId) {
            $instructor = get_user_by('id', $instructorId);
            error_log('Editing instructor ID: ' . $instructorId);
        } else {
            error_log('Creating new instructor');
        }
        
        
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('classflow_pro_save_instructor'); ?>
            
            <!-- Use same pattern as ClassesPage -->
            <input type="hidden" name="action" value="save_instructor">
            
            <?php if ($instructor): ?>
                <input type="hidden" name="instructor_id" value="<?php echo esc_attr($instructor->ID); ?>">
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="user_email"><?php echo esc_html__('Email', 'classflow-pro'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="email" name="user_email" id="user_email" 
                               value="<?php echo $instructor ? esc_attr($instructor->user_email) : ''; ?>" 
                               class="regular-text" required>
                    </td>
                </tr>
                
                <?php if (!$instructor): ?>
                    <tr>
                        <th scope="row">
                            <label for="user_login"><?php echo esc_html__('Username', 'classflow-pro'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="user_login" id="user_login" class="regular-text" required>
                            <p class="description"><?php echo esc_html__('Username cannot be changed after creation.', 'classflow-pro'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="user_pass"><?php echo esc_html__('Password', 'classflow-pro'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="password" name="user_pass" id="user_pass" class="regular-text" required>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <tr>
                    <th scope="row">
                        <label for="first_name"><?php echo esc_html__('First Name', 'classflow-pro'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="first_name" id="first_name" 
                               value="<?php echo $instructor ? esc_attr($instructor->first_name) : ''; ?>" 
                               class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="last_name"><?php echo esc_html__('Last Name', 'classflow-pro'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="last_name" id="last_name" 
                               value="<?php echo $instructor ? esc_attr($instructor->last_name) : ''; ?>" 
                               class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="status"><?php echo esc_html__('Status', 'classflow-pro'); ?></label>
                    </th>
                    <td>
                        <?php 
                        $status = $instructor ? get_user_meta($instructor->ID, 'instructor_status', true) : 'active';
                        ?>
                        <select name="status" id="status">
                            <option value="active" <?php selected($status, 'active'); ?>><?php echo esc_html__('Active', 'classflow-pro'); ?></option>
                            <option value="inactive" <?php selected($status, 'inactive'); ?>><?php echo esc_html__('Inactive', 'classflow-pro'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" 
                       value="<?php echo $instructor ? esc_attr__('Update Instructor', 'classflow-pro') : esc_attr__('Add Instructor', 'classflow-pro'); ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-instructors')); ?>" class="button">
                    <?php echo esc_html__('Cancel', 'classflow-pro'); ?>
                </a>
            </p>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('InstructorsPage form script loaded');
            
            // Log form details
            var $form = $('form');
            console.log('Form found:', $form.length);
            console.log('Form action:', $form.attr('action'));
            console.log('Form method:', $form.attr('method'));
            
            // Intercept form submission
            $form.on('submit', function(e) {
                console.log('Form submit event triggered');
                console.log('Form is valid:', this.checkValidity());
                
                // Log all form data
                var formData = $(this).serializeArray();
                console.log('Form data being submitted:', formData);
                
                // Check for nonce
                var nonceField = $(this).find('input[name="instructor_nonce"]');
                console.log('Nonce field exists:', nonceField.length > 0);
                if (nonceField.length) {
                    console.log('Nonce value:', nonceField.val());
                }
                
                // Allow submission to continue
                console.log('Allowing form submission...');
                // Don't prevent default - let it submit
            });
            
            $('#submit').on('click', function(e) {
                console.log('Submit button clicked');
                var $btn = $(this);
                console.log('Button type:', $btn.attr('type'));
                console.log('Button disabled:', $btn.prop('disabled'));
            });
            
            // Check for any global AJAX errors
            $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
                console.error('AJAX Error:', thrownError);
                console.error('URL:', settings.url);
                console.error('Type:', settings.type);
                console.error('Data:', settings.data);
                console.error('Response:', jqxhr.responseText);
            });
        });
        </script>
        <?php
    }
    
    private function handleSave(): void {
        error_log('InstructorsPage::handleSave() called');
        error_log('Full POST data: ' . print_r($_POST, true));
        
        // Check nonce - exactly like ClassesPage
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'classflow_pro_save_instructor')) {
            error_log('Nonce verification failed');
            error_log('_wpnonce value: ' . ($_POST['_wpnonce'] ?? 'not set'));
            wp_die(__('Security check failed.', 'classflow-pro'));
        }
        
        // Check permissions
        if (!current_user_can('manage_classflow_instructors')) {
            error_log('ERROR: User does not have permission');
            wp_die('You do not have permission to perform this action.');
        }
        
        error_log('Security checks passed, saving instructor...');
        
        try {
            $instructorId = isset($_POST['instructor_id']) ? intval($_POST['instructor_id']) : 0;
            
            if ($instructorId) {
                error_log('Updating existing instructor ID: ' . $instructorId);
                
                $userdata = [
                    'ID' => $instructorId,
                    'user_email' => sanitize_email($_POST['user_email']),
                    'first_name' => sanitize_text_field($_POST['first_name']),
                    'last_name' => sanitize_text_field($_POST['last_name']),
                ];
                
                $userId = wp_update_user($userdata);
                
                if (is_wp_error($userId)) {
                    error_log('ERROR updating user: ' . $userId->get_error_message());
                    throw new \Exception($userId->get_error_message());
                }
                
                $message = 'updated';
            } else {
                error_log('Creating new instructor');
                
                $username = sanitize_user($_POST['user_login']);
                $email = sanitize_email($_POST['user_email']);
                $password = $_POST['user_pass'];
                
                error_log('New user - username: ' . $username . ', email: ' . $email);
                
                if (username_exists($username)) {
                    throw new \Exception(__('Username already exists.', 'classflow-pro'));
                }
                
                if (email_exists($email)) {
                    throw new \Exception(__('Email already exists.', 'classflow-pro'));
                }
                
                $userdata = [
                    'user_login' => $username,
                    'user_email' => $email,
                    'user_pass' => $password,
                    'first_name' => sanitize_text_field($_POST['first_name']),
                    'last_name' => sanitize_text_field($_POST['last_name']),
                    'role' => 'classflow_instructor',
                ];
                
                $userId = wp_insert_user($userdata);
                
                if (is_wp_error($userId)) {
                    error_log('ERROR creating user: ' . $userId->get_error_message());
                    throw new \Exception($userId->get_error_message());
                }
                
                error_log('User created successfully with ID: ' . $userId);
                $message = 'created';
            }
            
            // Update status
            update_user_meta($userId, 'instructor_status', sanitize_text_field($_POST['status']));
            
            error_log('Instructor saved successfully, redirecting...');
            
            // Redirect
            wp_redirect(add_query_arg([
                'page' => 'classflow-pro-instructors',
                'message' => $message
            ], admin_url('admin.php')));
            exit;
            
        } catch (\Exception $e) {
            error_log('ERROR in save: ' . $e->getMessage());
            wp_die($e->getMessage());
        }
    }
    
    private function displayMessages(): void {
        if (!isset($_GET['message'])) {
            return;
        }
        
        $message = '';
        switch ($_GET['message']) {
            case 'created':
                $message = __('Instructor created successfully.', 'classflow-pro');
                break;
            case 'updated':
                $message = __('Instructor updated successfully.', 'classflow-pro');
                break;
        }
        
        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
}