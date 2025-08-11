<?php
namespace ClassFlowPro\Admin;
if (!defined('ABSPATH')) { exit; }

class Resources
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['cfp_action']) && $_POST['cfp_action']==='save') { self::handle_save(); return; }
        if ((isset($_GET['action']) && $_GET['action']==='delete') && isset($_GET['id'])) { self::handle_delete(); return; }
        $action = $_GET['action'] ?? 'list';
        if ($action==='new' || $action==='edit') self::render_form(); else self::render_list();
    }
    private static function render_list(): void
    {
        $page=isset($_GET['paged'])?max(1,(int)$_GET['paged']):1; $per=20; $s=isset($_GET['s'])?sanitize_text_field($_GET['s']):'';
        $repo=new \ClassFlowPro\DB\Repositories\ResourcesRepository(); $res=$repo->paginate($page,$per,$s); $items=$res['items']; $total=(int)$res['total'];
        
        // Get locations for display
        $locRepo = new \ClassFlowPro\DB\Repositories\LocationsRepository();
        $locations = [];
        foreach($locRepo->all() as $loc) {
            $locations[$loc['id']] = $loc['name'];
        }
        
        // Add success message handling
        if (isset($_GET['message'])) {
            $messages = [
                'created' => __('Resource created successfully.', 'classflow-pro'),
                'created_multiple' => __('Resources created successfully.', 'classflow-pro'),
                'updated' => __('Resource updated successfully.', 'classflow-pro'),
                'deleted' => __('Resource deleted successfully.', 'classflow-pro'),
            ];
            if (isset($messages[$_GET['message']])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$_GET['message']]) . '</p></div>';
            }
        }
        ?>
        <div class="wrap"><h1 class="wp-heading-inline"><?php esc_html_e('Resources','classflow-pro'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-resources&action=new')); ?>" class="page-title-action"><?php esc_html_e('Add New','classflow-pro'); ?></a>
        <hr class="wp-header-end"/>
        <form method="get"><input type="hidden" name="page" value="classflow-pro-resources"/><p class="search-box"><label class="screen-reader-text" for="res-search"><?php esc_html_e('Search Resources','classflow-pro'); ?></label><input type="search" id="res-search" name="s" value="<?php echo esc_attr($s); ?>"/><?php submit_button(__('Search'),'button','',false); ?></p></form>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name','classflow-pro'); ?></th>
                    <th><?php esc_html_e('Type','classflow-pro'); ?></th>
                    <th><?php esc_html_e('Location','classflow-pro'); ?></th>
                    <th><?php esc_html_e('Capacity','classflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions','classflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
        <?php if(empty($items)): ?>
            <tr><td colspan="5"><?php esc_html_e('No resources found.','classflow-pro'); ?></td></tr>
        <?php else: foreach($items as $r): 
            // Format type for display
            $type_display = ucwords(str_replace('_', ' ', $r['type'] ?? ''));
        ?>
        <tr>
            <td>
                <strong><a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-resources&action=edit&id='.$r['id'])); ?>"><?php echo esc_html($r['name']); ?></a></strong>
                <div class="row-actions">
                    <span class="edit"><a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-resources&action=edit&id='.$r['id'])); ?>"><?php esc_html_e('Edit','classflow-pro'); ?></a> | </span>
                    <span class="trash"><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=classflow-pro-resources&action=delete&id='.$r['id']),'cfp_delete_resource_'.$r['id'])); ?>" onclick="return confirm('<?php echo esc_attr__('Are you sure? This will affect all schedules using this resource.','classflow-pro'); ?>');"><?php esc_html_e('Delete','classflow-pro'); ?></a></span>
                </div>
            </td>
            <td><?php echo esc_html($type_display); ?></td>
            <td><?php echo esc_html($locations[$r['location_id']] ?? '-'); ?></td>
            <td><?php echo esc_html($r['capacity'] > 1 ? $r['capacity'] . ' ' . __('people', 'classflow-pro') : '1 ' . __('person', 'classflow-pro')); ?></td>
            <td>
                <a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-schedules&resource_id='.$r['id'])); ?>"><?php esc_html_e('View Schedule','classflow-pro'); ?></a>
            </td>
        </tr>
        <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php $pages=(int)ceil($total/$per); if($pages>1){ echo '<div class="tablenav bottom"><div class="tablenav-pages">'.paginate_links(['base'=>add_query_arg('paged','%#%'),'total'=>$pages,'current'=>$page]).'</div></div>'; } ?>
        </div>
        <?php
    }
    private static function render_form(): void
    {
        $id=isset($_GET['id'])?(int)$_GET['id']:0; $repo=new \ClassFlowPro\DB\Repositories\ResourcesRepository(); $row=$id?$repo->find($id):null; if($id && !$row) wp_die(esc_html__('Invalid resource.','classflow-pro'));
        $name=$row['name']??''; $type=$row['type']??''; $cap=$row['capacity']??1; $loc=$row['location_id']??'';
        $locRepo=new \ClassFlowPro\DB\Repositories\LocationsRepository(); $locations=$locRepo->all();
        
        // Common resource types for Pilates studios
        $resource_types = [
            'reformer' => __('Reformer', 'classflow-pro'),
            'cadillac' => __('Cadillac/Trapeze Table', 'classflow-pro'),
            'chair' => __('Wunda Chair', 'classflow-pro'),
            'barrel' => __('Ladder Barrel', 'classflow-pro'),
            'spine_corrector' => __('Spine Corrector', 'classflow-pro'),
            'mat' => __('Mat Space', 'classflow-pro'),
            'tower' => __('Tower', 'classflow-pro'),
            'springboard' => __('Springboard', 'classflow-pro'),
            'barre' => __('Barre Station', 'classflow-pro'),
            'trx' => __('TRX Station', 'classflow-pro'),
            'room' => __('Private Room', 'classflow-pro'),
            'studio' => __('Studio Space', 'classflow-pro'),
            'other' => __('Other Equipment', 'classflow-pro'),
        ];
        ?>
        <div class="wrap"><h1><?php echo $id?esc_html__('Edit Resource','classflow-pro'):esc_html__('Add Resource','classflow-pro'); ?></h1>
        
        <div class="notice notice-info" style="margin-top:20px;">
            <p><?php esc_html_e('Resources are equipment or spaces that have limited availability. When a resource is used in a class or booking, it becomes unavailable for other sessions at the same time.', 'classflow-pro'); ?></p>
        </div>
        
        <form method="post"><?php wp_nonce_field('cfp_save_resource'); ?><input type="hidden" name="cfp_action" value="save"/><?php if($id):?><input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>"/><?php endif; ?>
        <table class="form-table">
        
        <tr><th><label for="location_id"><?php esc_html_e('Location','classflow-pro'); ?> <span class="required">*</span></label></th>
            <td>
                <select id="location_id" name="location_id" required class="regular-text">
                    <option value=""><?php esc_html_e('Select a location', 'classflow-pro'); ?></option>
                    <?php foreach($locations as $l): ?>
                        <option value="<?php echo esc_attr((string)$l['id']); ?>" <?php selected((int)$loc,(int)$l['id']); ?>>
                            <?php echo esc_html($l['name']); ?>
                            <?php if($l['address1']): ?>
                                - <?php echo esc_html($l['city'] ?: $l['address1']); ?>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Resources are tied to specific locations', 'classflow-pro'); ?></p>
            </td>
        </tr>
        
        <tr><th><label for="type"><?php esc_html_e('Resource Type','classflow-pro'); ?> <span class="required">*</span></label></th>
            <td>
                <select id="type" name="type" required class="regular-text">
                    <option value=""><?php esc_html_e('Select type', 'classflow-pro'); ?></option>
                    <?php foreach($resource_types as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="type_custom" name="type_custom" class="regular-text" placeholder="<?php esc_attr_e('Enter custom type', 'classflow-pro'); ?>" style="display:none;margin-top:10px;" value="<?php echo !array_key_exists($type, $resource_types) ? esc_attr($type) : ''; ?>"/>
                <p class="description"><?php esc_html_e('The type of equipment or space', 'classflow-pro'); ?></p>
            </td>
        </tr>
        
        <tr><th><label for="name"><?php esc_html_e('Resource Name','classflow-pro'); ?> <span class="required">*</span></label></th>
            <td>
                <input id="name" name="name" class="regular-text" value="<?php echo esc_attr($name); ?>" required placeholder="<?php esc_attr_e('e.g., Reformer #1, Blue Mat, Studio A', 'classflow-pro'); ?>"/>
                <p class="description"><?php esc_html_e('A unique identifier for this specific resource', 'classflow-pro'); ?></p>
            </td>
        </tr>
        
        <tr><th><label for="capacity"><?php esc_html_e('Capacity','classflow-pro'); ?></label></th>
            <td>
                <input id="capacity" name="capacity" type="number" min="1" value="<?php echo esc_attr((string)$cap); ?>" class="small-text"/>
                <p class="description"><?php esc_html_e('How many people can use this resource simultaneously (usually 1 for equipment, more for rooms)', 'classflow-pro'); ?></p>
            </td>
        </tr>
        
        <tr id="quantity-row" style="display:none;">
            <th><label for="quantity"><?php esc_html_e('Quick Add Multiple','classflow-pro'); ?></label></th>
            <td>
                <input id="quantity" name="quantity" type="number" min="1" max="20" value="1" class="small-text"/>
                <p class="description"><?php esc_html_e('Create multiple resources of the same type (e.g., 6 reformers). They will be numbered automatically.', 'classflow-pro'); ?></p>
            </td>
        </tr>
        
        </table>
        
        <?php if($id): ?>
            <h2><?php esc_html_e('Resource Availability', 'classflow-pro'); ?></h2>
            <div class="card" style="max-width:600px;padding:15px;">
                <?php
                // Check current usage of this resource
                global $wpdb;
                $now = current_time('mysql');
                $upcoming = $wpdb->get_results($wpdb->prepare(
                    "SELECT s.*, b.status as booking_status, COUNT(b.id) as bookings 
                     FROM {$wpdb->prefix}cfp_schedules s 
                     LEFT JOIN {$wpdb->prefix}cfp_bookings b ON b.schedule_id = s.id 
                     WHERE s.resource_id = %d 
                     AND s.start_time >= %s 
                     AND b.status IN ('confirmed', 'pending')
                     GROUP BY s.id 
                     ORDER BY s.start_time ASC 
                     LIMIT 5",
                    $id, $now
                ));
                
                if($upcoming): ?>
                    <h3><?php esc_html_e('Upcoming Usage', 'classflow-pro'); ?></h3>
                    <ul>
                    <?php foreach($upcoming as $schedule): ?>
                        <li>
                            <?php echo esc_html(date_i18n('M j, Y g:i A', strtotime($schedule->start_time))); ?> - 
                            <?php echo esc_html(date_i18n('g:i A', strtotime($schedule->end_time))); ?>
                            (<?php echo esc_html($schedule->bookings); ?> bookings)
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><?php esc_html_e('This resource has no upcoming bookings.', 'classflow-pro'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php submit_button($id?__('Update Resource','classflow-pro'):__('Add Resource','classflow-pro')); ?>
        </form></div>
        
        <style>
        .required { color: #d63638; font-weight: bold; }
        .form-table th { width: 200px; }
        .card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; }
        </style>
        
        <script>
        jQuery(function($) {
            // Show quantity field only for new resources
            <?php if(!$id): ?>
            $('#quantity-row').show();
            <?php endif; ?>
            
            // Handle custom type
            $('#type').on('change', function() {
                if($(this).val() === 'other') {
                    $('#type_custom').show().prop('required', true);
                } else {
                    $('#type_custom').hide().prop('required', false);
                }
            });
            
            // Initialize on load
            <?php if(!array_key_exists($type, $resource_types) && $type): ?>
            $('#type').val('other').trigger('change');
            $('#type_custom').val('<?php echo esc_js($type); ?>');
            <?php else: ?>
            $('#type').trigger('change');
            <?php endif; ?>
            
            // Auto-generate name based on type and location
            $('#type, #location_id').on('change', function() {
                if(!$('#name').val() && $('#type').val() && $('#location_id').val()) {
                    var type = $('#type option:selected').text();
                    if($('#type').val() === 'other' && $('#type_custom').val()) {
                        type = $('#type_custom').val();
                    }
                    var location = $('#location_id option:selected').text().split(' - ')[0];
                    $('#name').attr('placeholder', type + ' at ' + location);
                }
            });
            
            // Validate capacity based on type
            $('#type').on('change', function() {
                var type = $(this).val();
                if(type === 'room' || type === 'studio' || type === 'mat') {
                    $('#capacity').attr('min', '1').attr('placeholder', '<?php echo esc_js(__('e.g., 10 for a group room', 'classflow-pro')); ?>');
                } else {
                    $('#capacity').attr('min', '1').attr('max', '2').attr('placeholder', '<?php echo esc_js(__('Usually 1 for equipment', 'classflow-pro')); ?>');
                    if($('#capacity').val() > 2) $('#capacity').val(1);
                }
            });
        });
        </script>
        <?php
    }
    private static function handle_save(): void
    {
        if(!isset($_POST['_wpnonce'])||!wp_verify_nonce($_POST['_wpnonce'],'cfp_save_resource')) wp_die(esc_html__('Security check failed.','classflow-pro'));
        if(!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.','classflow-pro'));
        $id=isset($_POST['id'])?(int)$_POST['id']:0; 
        $repo=new \ClassFlowPro\DB\Repositories\ResourcesRepository();
        
        // Handle custom type
        $type = sanitize_text_field($_POST['type'] ?? '');
        if ($type === 'other' && !empty($_POST['type_custom'])) {
            $type = sanitize_text_field($_POST['type_custom']);
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $capacity = isset($_POST['capacity']) ? max(1, (int)$_POST['capacity']) : 1;
        $location_id = isset($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        
        if($id) {
            // Update existing resource
            $data = [
                'name' => $name,
                'type' => $type,
                'capacity' => $capacity,
                'location_id' => $location_id
            ];
            $repo->update($id, $data);
            $m = 'updated';
        } else {
            // Handle bulk creation for new resources
            $quantity = isset($_POST['quantity']) ? max(1, min(20, (int)$_POST['quantity'])) : 1;
            
            if ($quantity > 1) {
                // Create multiple resources with numbering
                $base_name = rtrim($name, ' 0123456789#'); // Remove trailing numbers
                for ($i = 1; $i <= $quantity; $i++) {
                    $data = [
                        'name' => $base_name . ' #' . $i,
                        'type' => $type,
                        'capacity' => $capacity,
                        'location_id' => $location_id
                    ];
                    $repo->create($data);
                }
                $m = 'created_multiple';
            } else {
                // Create single resource
                $data = [
                    'name' => $name,
                    'type' => $type,
                    'capacity' => $capacity,
                    'location_id' => $location_id
                ];
                $repo->create($data);
                $m = 'created';
            }
        }
        
        wp_safe_redirect(admin_url('admin.php?page=classflow-pro-resources&message='.$m)); 
        exit;
    }
    private static function handle_delete(): void
    {
        $id=isset($_GET['id'])?(int)$_GET['id']:0; if(!$id) wp_die(esc_html__('Invalid request.','classflow-pro'));
        if(!isset($_GET['_wpnonce'])||!wp_verify_nonce($_GET['_wpnonce'],'cfp_delete_resource_'.$id)) wp_die(esc_html__('Security check failed.','classflow-pro'));
        if(!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.','classflow-pro'));
        $repo=new \ClassFlowPro\DB\Repositories\ResourcesRepository(); $repo->delete($id); wp_safe_redirect(admin_url('admin.php?page=classflow-pro-resources&message=deleted')); exit;
    }
}

