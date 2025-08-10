<?php
declare(strict_types=1);

namespace ClassFlowPro\Admin\Pages;

use ClassFlowPro\Services\Container;

class ClassesPage {
    private Container $container;

    public function __construct(Container $container) {
        $this->container = $container;
    }

    public function render(): void {
        // Handle form submissions
        if (isset($_POST['action']) && $_POST['action'] === 'save_class') {
            $this->handleSave();
        }
        
        // Handle actions
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'new':
            case 'edit':
                $this->renderForm();
                break;
            case 'delete':
                $this->handleDelete();
                break;
            default:
                $this->renderList();
                break;
        }
    }

    private function renderList(): void {
        $classRepo = $this->container->get('class_repository');
        
        // Get filter parameters
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }
        
        // Get classes with pagination
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        if ($search) {
            $classes = $classRepo->search($search);
            $total = count($classes);
        } else {
            $result = $classRepo->paginate($page, $per_page, $filters);
            $classes = $result['items'];
            $total = $result['total'];
        }
        
        // Show admin notices
        if (isset($_GET['message'])) {
            $this->showAdminNotice();
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Classes', 'classflow-pro'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=classflow-pro-classes&action=new'); ?>" class="page-title-action">
                <?php echo esc_html__('Add New', 'classflow-pro'); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="page" value="classflow-pro-classes">
                    
                    <div class="alignleft actions">
                        <select name="status">
                            <option value=""><?php esc_html_e('All Statuses', 'classflow-pro'); ?></option>
                            <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active', 'classflow-pro'); ?></option>
                            <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Inactive', 'classflow-pro'); ?></option>
                            <option value="draft" <?php selected($status, 'draft'); ?>><?php esc_html_e('Draft', 'classflow-pro'); ?></option>
                        </select>
                        
                        <?php submit_button(__('Filter', 'classflow-pro'), 'button', 'filter_action', false); ?>
                    </div>
                    
                    <div class="alignright">
                        <p class="search-box">
                            <label class="screen-reader-text" for="class-search-input"><?php esc_html_e('Search Classes:', 'classflow-pro'); ?></label>
                            <input type="search" id="class-search-input" name="s" value="<?php echo esc_attr($search); ?>">
                            <?php submit_button(__('Search Classes', 'classflow-pro'), 'button', '', false); ?>
                        </p>
                    </div>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Name', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Duration', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Capacity', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Price', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Status', 'classflow-pro'); ?></th>
                        <th scope="col" class="manage-column"><?php echo esc_html__('Actions', 'classflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classes)): ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No classes found.', 'classflow-pro'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=classflow-pro-classes&action=edit&id=' . $class->getId()); ?>">
                                            <?php echo esc_html($class->getName()); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo admin_url('admin.php?page=classflow-pro-classes&action=edit&id=' . $class->getId()); ?>">
                                                <?php esc_html_e('Edit', 'classflow-pro'); ?>
                                            </a> |
                                        </span>
                                        <span class="trash">
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=classflow-pro-classes&action=delete&id=' . $class->getId()), 'delete_class_' . $class->getId()); ?>" 
                                               onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this class?', 'classflow-pro'); ?>');">
                                                <?php esc_html_e('Delete', 'classflow-pro'); ?>
                                            </a>
                                        </span>
                                    </div>
                                </td>
                                <td><?php echo esc_html($class->getDurationFormatted()); ?></td>
                                <td><?php echo esc_html($class->getCapacity()); ?></td>
                                <td><?php echo esc_html($class->getFormattedPrice()); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($class->getStatus()); ?>">
                                        <?php echo esc_html(ucfirst($class->getStatus())); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=classflow-pro-schedules&class_id=' . $class->getId()); ?>" class="button button-small">
                                        <?php esc_html_e('View Schedules', 'classflow-pro'); ?>
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
            .status-active { color: #46b450; font-weight: bold; }
            .status-inactive { color: #dc3232; }
            .status-draft { color: #f56e28; }
        </style>
        <?php
    }

    private function renderForm(): void {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $class = null;
        
        if ($id) {
            $classRepo = $this->container->get('class_repository');
            $class = $classRepo->find($id);
        }
        ?>
        <div class="wrap">
            <h1><?php echo $class ? esc_html__('Edit Class', 'classflow-pro') : esc_html__('Add New Class', 'classflow-pro'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('classflow_pro_save_class'); ?>
                <input type="hidden" name="action" value="save_class">
                <?php if ($class): ?>
                    <input type="hidden" name="id" value="<?php echo esc_attr($class->getId()); ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="name"><?php echo esc_html__('Name', 'classflow-pro'); ?></label></th>
                        <td>
                            <input type="text" id="name" name="name" class="regular-text" 
                                   value="<?php echo $class ? esc_attr($class->getName()) : ''; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description"><?php echo esc_html__('Description', 'classflow-pro'); ?></label></th>
                        <td>
                            <?php 
                            wp_editor(
                                $class ? $class->getDescription() : '',
                                'description',
                                [
                                    'textarea_rows' => 10,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'quicktags' => true
                                ]
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="duration"><?php echo esc_html__('Duration (minutes)', 'classflow-pro'); ?></label></th>
                        <td>
                            <input type="number" id="duration" name="duration" class="small-text" 
                                   value="<?php echo $class ? esc_attr($class->getDuration()) : '60'; ?>" min="1" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="capacity"><?php echo esc_html__('Capacity', 'classflow-pro'); ?></label></th>
                        <td>
                            <input type="number" id="capacity" name="capacity" class="small-text" 
                                   value="<?php echo $class ? esc_attr($class->getCapacity()) : '10'; ?>" min="1" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="price"><?php echo esc_html__('Price', 'classflow-pro'); ?></label></th>
                        <td>
                            <input type="number" id="price" name="price" class="small-text" step="0.01" 
                                   value="<?php echo $class ? esc_attr($class->getPrice()) : '0'; ?>" min="0" required>
                            <span class="description"><?php esc_html_e('Enter 0 for free classes', 'classflow-pro'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="status"><?php echo esc_html__('Status', 'classflow-pro'); ?></label></th>
                        <td>
                            <select id="status" name="status">
                                <option value="active" <?php echo $class && $class->getStatus() === 'active' ? 'selected' : ''; ?>>
                                    <?php esc_html_e('Active', 'classflow-pro'); ?>
                                </option>
                                <option value="inactive" <?php echo $class && $class->getStatus() === 'inactive' ? 'selected' : ''; ?>>
                                    <?php esc_html_e('Inactive', 'classflow-pro'); ?>
                                </option>
                                <option value="draft" <?php echo $class && $class->getStatus() === 'draft' ? 'selected' : ''; ?>>
                                    <?php esc_html_e('Draft', 'classflow-pro'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="scheduling_type"><?php echo esc_html__('Scheduling Type', 'classflow-pro'); ?></label></th>
                        <td>
                            <select id="scheduling_type" name="scheduling_type">
                                <option value="fixed" <?php echo $class && $class->getSchedulingType() === 'fixed' ? 'selected' : ''; ?>>
                                    <?php esc_html_e('Fixed Schedule', 'classflow-pro'); ?>
                                </option>
                                <option value="flexible" <?php echo $class && $class->getSchedulingType() === 'flexible' ? 'selected' : ''; ?>>
                                    <?php esc_html_e('Flexible Booking', 'classflow-pro'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Fixed Schedule: Pre-scheduled classes at specific times. Flexible Booking: On-demand booking based on instructor availability.', 'classflow-pro'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="featured_image_id"><?php echo esc_html__('Featured Image', 'classflow-pro'); ?></label></th>
                        <td>
                            <?php
                            $featured_image_id = $class ? $class->getFeaturedImageId() : null;
                            ?>
                            <div id="featured-image-preview">
                                <?php if ($featured_image_id): ?>
                                    <?php echo wp_get_attachment_image($featured_image_id, 'thumbnail'); ?>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="featured_image_id" name="featured_image_id" value="<?php echo esc_attr($featured_image_id); ?>">
                            <button type="button" class="button" id="upload-featured-image">
                                <?php esc_html_e('Select Image', 'classflow-pro'); ?>
                            </button>
                            <button type="button" class="button" id="remove-featured-image" <?php echo !$featured_image_id ? 'style="display:none;"' : ''; ?>>
                                <?php esc_html_e('Remove Image', 'classflow-pro'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($class ? __('Update Class', 'classflow-pro') : __('Add Class', 'classflow-pro')); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Media uploader for featured image
            $('#upload-featured-image').click(function(e) {
                e.preventDefault();
                
                var mediaUploader = wp.media({
                    title: '<?php esc_html_e('Select Featured Image', 'classflow-pro'); ?>',
                    button: {
                        text: '<?php esc_html_e('Use this image', 'classflow-pro'); ?>'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#featured_image_id').val(attachment.id);
                    $('#featured-image-preview').html('<img src="' + attachment.sizes.thumbnail.url + '" />');
                    $('#remove-featured-image').show();
                });
                
                mediaUploader.open();
            });
            
            $('#remove-featured-image').click(function(e) {
                e.preventDefault();
                $('#featured_image_id').val('');
                $('#featured-image-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }
    
    private function handleSave(): void {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'classflow_pro_save_class')) {
            wp_die(__('Security check failed.', 'classflow-pro'));
        }
        
        if (!current_user_can('manage_classflow_classes')) {
            wp_die(__('You do not have permission to perform this action.', 'classflow-pro'));
        }
        
        $classService = $this->container->get('class_service');
        
        try {
            $data = [
                'name' => sanitize_text_field($_POST['name']),
                'description' => wp_kses_post($_POST['description']),
                'duration' => intval($_POST['duration']),
                'capacity' => intval($_POST['capacity']),
                'price' => floatval($_POST['price']),
                'status' => sanitize_text_field($_POST['status']),
                'scheduling_type' => sanitize_text_field($_POST['scheduling_type'] ?? 'fixed'),
                'featured_image_id' => intval($_POST['featured_image_id']),
            ];
            
            if (isset($_POST['id']) && $_POST['id']) {
                $class = $classService->updateClass(intval($_POST['id']), $data);
                $message = 'updated';
            } else {
                $class = $classService->createClass($data);
                $message = 'created';
            }
            
            wp_redirect(admin_url('admin.php?page=classflow-pro-classes&message=' . $message));
            exit;
            
        } catch (\Exception $e) {
            wp_die($e->getMessage());
        }
    }
    
    private function handleDelete(): void {
        if (!isset($_GET['id']) || !isset($_GET['_wpnonce'])) {
            wp_die(__('Invalid request.', 'classflow-pro'));
        }
        
        $id = intval($_GET['id']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_class_' . $id)) {
            wp_die(__('Security check failed.', 'classflow-pro'));
        }
        
        if (!current_user_can('manage_classflow_classes')) {
            wp_die(__('You do not have permission to perform this action.', 'classflow-pro'));
        }
        
        $classService = $this->container->get('class_service');
        
        try {
            $classService->deleteClass($id);
            wp_redirect(admin_url('admin.php?page=classflow-pro-classes&message=deleted'));
            exit;
        } catch (\Exception $e) {
            wp_die($e->getMessage());
        }
    }
    
    private function showAdminNotice(): void {
        $message = isset($_GET['message']) ? $_GET['message'] : '';
        
        switch ($message) {
            case 'created':
                $text = __('Class created successfully.', 'classflow-pro');
                $type = 'success';
                break;
            case 'updated':
                $text = __('Class updated successfully.', 'classflow-pro');
                $type = 'success';
                break;
            case 'deleted':
                $text = __('Class deleted successfully.', 'classflow-pro');
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