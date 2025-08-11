<?php
namespace ClassFlowPro\Admin;
if (!defined('ABSPATH')) { exit; }

class Instructors
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        if (isset($_POST['cfp_action']) && $_POST['cfp_action'] === 'save') { self::handle_save(); return; }
        if ((isset($_GET['action']) && $_GET['action']==='delete') && isset($_GET['id'])) { self::handle_delete(); return; }
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        if ($action === 'new' || $action === 'edit') self::render_form(); else self::render_list();
    }

    private static function render_list(): void
    {
        $paged = isset($_GET['paged']) ? max(1,(int)$_GET['paged']) : 1; $per=20; $s = isset($_GET['s'])?sanitize_text_field($_GET['s']):'';
        $repo = new \ClassFlowPro\DB\Repositories\InstructorsRepository(); $res = $repo->paginate($paged,$per,$s); $items=$res['items']; $total=(int)$res['total'];
        ?>
        <div class="wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Instructors','classflow-pro'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-instructors&action=new')); ?>" class="page-title-action"><?php esc_html_e('Add New','classflow-pro'); ?></a>
        <hr class="wp-header-end"/>
        <form method="get"><input type="hidden" name="page" value="classflow-pro-instructors"/>
        <p class="search-box"><label class="screen-reader-text" for="ins-search"><?php esc_html_e('Search Instructors','classflow-pro'); ?></label>
        <input type="search" id="ins-search" name="s" value="<?php echo esc_attr($s); ?>"/><?php submit_button(__('Search'), 'button', '', false); ?></p></form>
        <table class="wp-list-table widefat fixed striped"><thead><tr>
        <th><?php esc_html_e('Name','classflow-pro'); ?></th><th><?php esc_html_e('Email','classflow-pro'); ?></th><th><?php esc_html_e('Payout %','classflow-pro'); ?></th><th><?php esc_html_e('Actions','classflow-pro'); ?></th>
        </tr></thead><tbody>
        <?php if (empty($items)): ?><tr><td colspan="4"><?php esc_html_e('No instructors found.','classflow-pro'); ?></td></tr>
        <?php else: foreach ($items as $r): ?>
        <tr><td><strong><a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-instructors&action=edit&id='.$r['id'])); ?>"><?php echo esc_html($r['name']); ?></a></strong></td>
        <td><?php echo esc_html($r['email'] ?: ''); ?></td>
        <td><?php echo esc_html($r['payout_percent'] !== null ? rtrim(rtrim(number_format((float)$r['payout_percent'],2,'.',''), '0'), '.') : ''); ?></td>
        <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-instructors&action=edit&id='.$r['id'])); ?>"><?php esc_html_e('Edit','classflow-pro'); ?></a>
        <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=classflow-pro-instructors&action=delete&id='.$r['id']), 'cfp_delete_instructor_'.$r['id'])); ?>" onclick="return confirm('<?php echo esc_attr__('Are you sure?','classflow-pro'); ?>');"><?php esc_html_e('Delete','classflow-pro'); ?></a></td></tr>
        <?php endforeach; endif; ?></tbody></table>
        <?php $pages = (int)ceil($total/$per); if ($pages>1){ echo '<div class="tablenav bottom"><div class="tablenav-pages">'; echo paginate_links(['base'=>add_query_arg('paged','%#%'),'format'=>'','total'=>$pages,'current'=>$paged]); echo '</div></div>'; } ?>
        </div>
        <?php
    }

    private static function render_form(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0; $repo = new \ClassFlowPro\DB\Repositories\InstructorsRepository(); $row = $id ? $repo->find($id) : null;
        if ($id && !$row) wp_die(esc_html__('Invalid instructor.','classflow-pro'));
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        
        $name = $row['name'] ?? ''; $bio = $row['bio'] ?? ''; $email = $row['email'] ?? ''; $payout = $row['payout_percent'] ?? ''; $stripe = $row['stripe_account_id'] ?? '';
        $weekly = $row['availability_weekly'] ?? ''; $blackouts = $row['blackout_dates'] ?? ''; $thumb = !empty($row['featured_image_id']) ? (int)$row['featured_image_id'] : 0;
        
        // Parse weekly availability JSON
        $weeklyData = [];
        if ($weekly) {
            $decoded = json_decode($weekly, true);
            if (is_array($decoded)) $weeklyData = $decoded;
        }
        
        // Parse blackout dates JSON
        $blackoutData = [];
        if ($blackouts) {
            $decoded = json_decode($blackouts, true);
            if (is_array($decoded)) $blackoutData = $decoded;
        }
        ?>
        <div class="wrap"><h1><?php echo $id?esc_html__('Edit Instructor','classflow-pro'):esc_html__('Add Instructor','classflow-pro'); ?></h1>
        <form method="post"><?php wp_nonce_field('cfp_save_instructor'); ?><input type="hidden" name="cfp_action" value="save"/><?php if($id):?><input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>"/><?php endif; ?>
        <table class="form-table"><tr><th><label for="cfp_name"><?php esc_html_e('Name','classflow-pro'); ?></label></th><td><input class="regular-text" id="cfp_name" name="name" value="<?php echo esc_attr($name); ?>" required/></td></tr>
        <tr><th><label for="cfp_email"><?php esc_html_e('Email','classflow-pro'); ?></label></th><td><input type="email" id="cfp_email" name="email" value="<?php echo esc_attr($email); ?>" class="regular-text"/></td></tr>
        <tr><th><label for="cfp_payout"><?php esc_html_e('Payout %','classflow-pro'); ?></label></th><td><input type="number" step="0.1" min="0" max="100" id="cfp_payout" name="payout_percent" value="<?php echo esc_attr((string)$payout); ?>"/></td></tr>
        <tr><th><label for="cfp_stripe"><?php esc_html_e('Stripe Account','classflow-pro'); ?></label></th><td><input id="cfp_stripe" name="stripe_account_id" value="<?php echo esc_attr($stripe); ?>" class="regular-text"/></td></tr>
        <tr><th><label for="cfp_bio"><?php esc_html_e('Bio','classflow-pro'); ?></label></th><td><?php wp_editor($bio,'cfp_bio',['textarea_rows'=>6]); ?></td></tr>
        
        <tr><th><?php esc_html_e('Weekly Availability','classflow-pro'); ?></th><td>
            <div id="cfp-availability-container">
                <p class="description"><?php esc_html_e('Set available hours for each day of the week','classflow-pro'); ?></p>
                <?php
                $days = ['monday' => __('Monday','classflow-pro'), 'tuesday' => __('Tuesday','classflow-pro'), 'wednesday' => __('Wednesday','classflow-pro'), 
                         'thursday' => __('Thursday','classflow-pro'), 'friday' => __('Friday','classflow-pro'), 
                         'saturday' => __('Saturday','classflow-pro'), 'sunday' => __('Sunday','classflow-pro')];
                foreach ($days as $day => $label):
                    $dayData = $weeklyData[$day] ?? ['available' => false, 'start' => '09:00', 'end' => '17:00'];
                ?>
                <div class="cfp-day-availability" style="margin-bottom:10px;">
                    <label style="display:inline-block;width:100px;">
                        <input type="checkbox" name="availability[<?php echo esc_attr($day); ?>][available]" value="1" <?php checked($dayData['available'] ?? false); ?> class="cfp-day-toggle"/>
                        <?php echo esc_html($label); ?>
                    </label>
                    <span class="cfp-time-range" style="<?php echo ($dayData['available'] ?? false) ? '' : 'display:none;'; ?>">
                        <input type="time" name="availability[<?php echo esc_attr($day); ?>][start]" value="<?php echo esc_attr($dayData['start'] ?? '09:00'); ?>" />
                        <?php esc_html_e('to','classflow-pro'); ?>
                        <input type="time" name="availability[<?php echo esc_attr($day); ?>][end]" value="<?php echo esc_attr($dayData['end'] ?? '17:00'); ?>" />
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" id="cfp_avail" name="availability_weekly" value=""/>
        </td></tr>
        
        <tr><th><?php esc_html_e('Blackout Dates','classflow-pro'); ?></th><td>
            <div id="cfp-blackout-container">
                <p class="description"><?php esc_html_e('Select dates when instructor is unavailable','classflow-pro'); ?></p>
                <input type="text" id="cfp-blackout-picker" class="regular-text" placeholder="<?php esc_attr_e('Click to select date','classflow-pro'); ?>"/>
                <button type="button" class="button" id="cfp-add-blackout"><?php esc_html_e('Add Date','classflow-pro'); ?></button>
                <div id="cfp-blackout-list" style="margin-top:10px;">
                    <?php foreach ($blackoutData as $date): ?>
                    <div class="cfp-blackout-item" style="display:inline-block;margin:5px;padding:5px 10px;background:#f0f0f0;border-radius:3px;">
                        <span><?php echo esc_html($date); ?></span>
                        <button type="button" class="cfp-remove-blackout" style="margin-left:5px;cursor:pointer;border:none;background:none;color:#a00;">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <input type="hidden" id="cfp_blackouts" name="blackout_dates" value=""/>
        </td></tr>
        
        <tr><th><?php esc_html_e('Photo','classflow-pro'); ?></th><td><div id="cfp-photo-preview"><?php if($thumb) echo wp_get_attachment_image($thumb,'thumbnail'); ?></div><input type="hidden" name="featured_image_id" id="cfp_photo_id" value="<?php echo esc_attr((string)$thumb); ?>"/> <button type="button" class="button" id="cfp-photo-upload"><?php esc_html_e('Select Image','classflow-pro'); ?></button> <button type="button" class="button" id="cfp-photo-remove" <?php echo $thumb?'':'style="display:none;"'; ?>><?php esc_html_e('Remove','classflow-pro'); ?></button></td></tr>
        </table>
        <?php submit_button($id?__('Update Instructor','classflow-pro'):__('Add Instructor','classflow-pro')); ?>
        </form></div>
        
        <script>
        jQuery(function($){ 
            // Photo upload
            $('#cfp-photo-upload').on('click',function(e){e.preventDefault(); var f=wp.media({title:'<?php echo esc_js(__('Select Image','classflow-pro')); ?>',button:{text:'<?php echo esc_js(__('Use this image','classflow-pro')); ?>'},multiple:false}); f.on('select',function(){ var a=f.state().get('selection').first().toJSON(); $('#cfp_photo_id').val(a.id); $('#cfp-photo-preview').html('<img src="'+(a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url)+'"/>'); $('#cfp-photo-remove').show();}); f.open();}); 
            $('#cfp-photo-remove').on('click',function(e){e.preventDefault(); $('#cfp_photo_id').val(''); $('#cfp-photo-preview').empty(); $(this).hide();});
            
            // Weekly availability toggles
            $('.cfp-day-toggle').on('change', function() {
                var $range = $(this).closest('.cfp-day-availability').find('.cfp-time-range');
                if ($(this).is(':checked')) {
                    $range.show();
                } else {
                    $range.hide();
                }
            });
            
            // Blackout dates
            $('#cfp-blackout-picker').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0
            });
            
            $('#cfp-add-blackout').on('click', function() {
                var date = $('#cfp-blackout-picker').val();
                if (date && !$('#cfp-blackout-list').find('span:contains("' + date + '")').length) {
                    var item = $('<div class="cfp-blackout-item" style="display:inline-block;margin:5px;padding:5px 10px;background:#f0f0f0;border-radius:3px;">' +
                               '<span>' + date + '</span>' +
                               '<button type="button" class="cfp-remove-blackout" style="margin-left:5px;cursor:pointer;border:none;background:none;color:#a00;">&times;</button>' +
                               '</div>');
                    $('#cfp-blackout-list').append(item);
                    $('#cfp-blackout-picker').val('');
                }
            });
            
            $(document).on('click', '.cfp-remove-blackout', function() {
                $(this).closest('.cfp-blackout-item').remove();
            });
            
            // Serialize data before submit
            $('form').on('submit', function() {
                // Serialize weekly availability
                var availability = {};
                $('.cfp-day-availability').each(function() {
                    var $this = $(this);
                    var day = $this.find('input[type="checkbox"]').attr('name').match(/availability\[(\w+)\]/)[1];
                    availability[day] = {
                        available: $this.find('input[type="checkbox"]').is(':checked'),
                        start: $this.find('input[type="time"]:first').val(),
                        end: $this.find('input[type="time"]:last').val()
                    };
                });
                $('#cfp_avail').val(JSON.stringify(availability));
                
                // Serialize blackout dates
                var blackouts = [];
                $('#cfp-blackout-list .cfp-blackout-item span').each(function() {
                    blackouts.push($(this).text());
                });
                $('#cfp_blackouts').val(JSON.stringify(blackouts));
            });
        });
        </script>
        <?php
    }

    private static function handle_save(): void
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'],'cfp_save_instructor')) wp_die(esc_html__('Security check failed.','classflow-pro'));
        if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.','classflow-pro'));
        $id = isset($_POST['id'])?(int)$_POST['id']:0; $repo=new \ClassFlowPro\DB\Repositories\InstructorsRepository();
        
        // Process availability_weekly - it comes as JSON string from the hidden field
        $availability_weekly = isset($_POST['availability_weekly']) ? sanitize_text_field($_POST['availability_weekly']) : '';
        // Validate JSON structure
        if ($availability_weekly) {
            $decoded = json_decode($availability_weekly, true);
            if (!is_array($decoded)) $availability_weekly = '';
        }
        
        // Process blackout_dates - it comes as JSON string from the hidden field
        $blackout_dates = isset($_POST['blackout_dates']) ? sanitize_text_field($_POST['blackout_dates']) : '';
        // Validate JSON structure
        if ($blackout_dates) {
            $decoded = json_decode($blackout_dates, true);
            if (!is_array($decoded)) $blackout_dates = '';
        }
        
        $data = [
            'name'=>sanitize_text_field($_POST['name']??''),
            'email'=>isset($_POST['email'])?sanitize_email($_POST['email']):null,
            'payout_percent'=>isset($_POST['payout_percent'])?(float)$_POST['payout_percent']:null,
            'stripe_account_id'=>isset($_POST['stripe_account_id'])?sanitize_text_field($_POST['stripe_account_id']):null,
            'bio'=>wp_kses_post($_POST['cfp_bio']??''),
            'availability_weekly'=>$availability_weekly ?: null,
            'blackout_dates'=>$blackout_dates ?: null,
            'featured_image_id'=>isset($_POST['featured_image_id'])?(int)$_POST['featured_image_id']:null,
        ];
        if ($id) { $repo->update($id,$data); $msg='updated'; } else { $repo->create($data); $msg='created'; }
        wp_safe_redirect(admin_url('admin.php?page=classflow-pro-instructors&message='.$msg)); exit;
    }

    private static function handle_delete(): void
    {
        $id = isset($_GET['id'])?(int)$_GET['id']:0; if(!$id) wp_die(esc_html__('Invalid request.','classflow-pro'));
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'],'cfp_delete_instructor_'.$id)) wp_die(esc_html__('Security check failed.','classflow-pro'));
        if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.','classflow-pro'));
        $repo=new \ClassFlowPro\DB\Repositories\InstructorsRepository(); $repo->delete($id); wp_safe_redirect(admin_url('admin.php?page=classflow-pro-instructors&message=deleted')); exit;
    }
}

