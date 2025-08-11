<?php
namespace ClassFlowPro\Admin;

if (!defined('ABSPATH')) { exit; }

class Classes
{
    public static function render(): void
    {
        if (!current_user_can('edit_posts')) return;

        if (isset($_POST['cfp_action']) && $_POST['cfp_action'] === 'save_class') {
            self::handle_save();
            return;
        }
        if ((isset($_GET['action']) && $_GET['action'] === 'delete') && isset($_GET['id'])) {
            self::handle_delete();
            return;
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        if ($action === 'new' || $action === 'edit') {
            self::render_form();
        } else {
            self::render_list();
        }
    }

    private static function render_list(): void
    {
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $per_page = 20;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        $repo = new \ClassFlowPro\DB\Repositories\ClassesRepository();
        $result = $repo->paginate($paged, $per_page, [
            'status' => $status ?: null,
            'search' => $search ?: null,
        ]);
        $items = $result['items'];
        $total = (int) $result['total'];

        if (isset($_GET['message'])) {
            self::admin_notice();
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Classes', 'classflow-pro'); ?></h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-classes&action=new')); ?>" class="page-title-action"><?php echo esc_html__('Add New', 'classflow-pro'); ?></a>
            <hr class="wp-header-end"/>

            <form method="get" class="search-form">
                <input type="hidden" name="page" value="classflow-pro-classes" />
                <p class="search-box">
                    <label class="screen-reader-text" for="class-search-input"><?php esc_html_e('Search Classes:', 'classflow-pro'); ?></label>
                    <input type="search" id="class-search-input" name="s" value="<?php echo esc_attr($search); ?>" />
                    <?php submit_button(__('Search', 'classflow-pro'), 'button', '', false); ?>
                </p>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Duration', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Capacity', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Price', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'classflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'classflow-pro'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) : ?>
                    <tr><td colspan="6"><?php esc_html_e('No classes found.', 'classflow-pro'); ?></td></tr>
                <?php else: foreach ($items as $row): $post_id = (int) $row['id']; ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-classes&action=edit&id=' . $post_id)); ?>"><?php echo esc_html($row['name']); ?></a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit"><a href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-classes&action=edit&id=' . $post_id)); ?>"><?php esc_html_e('Edit', 'classflow-pro'); ?></a> | </span>
                                <span class="trash"><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=classflow-pro-classes&action=delete&id=' . $post_id), 'cfp_delete_class_' . $post_id)); ?>" onclick="return confirm('<?php echo esc_attr__('Are you sure?', 'classflow-pro'); ?>');"><?php esc_html_e('Delete', 'classflow-pro'); ?></a></span>
                            </div>
                        </td>
                        <td><?php echo esc_html((string) (int) $row['duration_mins']) . ' ' . esc_html__('mins', 'classflow-pro'); ?></td>
                        <td><?php echo esc_html((string) (int) $row['capacity']); ?></td>
                        <td>
                            <?php $cents = (int) $row['price_cents']; $cur = $row['currency'] ?: \ClassFlowPro\Admin\Settings::get('currency','usd'); echo esc_html(number_format_i18n($cents/100, 2) . ' ' . strtoupper($cur)); ?>
                        </td>
                        <td>
                            <?php $st = $row['status']; $label = $st === 'active' ? __('Active', 'classflow-pro') : ($st === 'inactive' ? __('Inactive', 'classflow-pro') : __('Draft', 'classflow-pro')); echo esc_html($label); ?>
                        </td>
                        <td>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=classflow-pro-schedules&class_id=' . $post_id)); ?>"><?php esc_html_e('Open Schedules', 'classflow-pro'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php
            $total_pages = (int) ceil($total / $per_page);
            if ($total_pages > 1) {
                echo '<div class="tablenav bottom"><div class="tablenav-pages">';
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'total' => $total_pages,
                    'current' => $paged,
                    'show_all' => false,
                    'end_size' => 1,
                    'mid_size' => 2,
                    'prev_next' => true,
                    'prev_text' => __('&laquo; Previous', 'classflow-pro'),
                    'next_text' => __('Next &raquo;', 'classflow-pro'),
                ]);
                echo '</div></div>';
            }
            ?>
        </div>
        <?php
    }

    private static function render_form(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $repo = new \ClassFlowPro\DB\Repositories\ClassesRepository();
        $row = $id ? $repo->find($id) : null;
        if ($id && !$row) {
            wp_die(esc_html__('Invalid class.', 'classflow-pro'));
        }

        wp_enqueue_media();

        $name = $row['name'] ?? '';
        $description = $row['description'] ?? '';
        $duration = isset($row['duration_mins']) ? (int)$row['duration_mins'] : 60;
        $capacity = isset($row['capacity']) ? (int)$row['capacity'] : 8;
        $price_cents = isset($row['price_cents']) ? (int)$row['price_cents'] : 0;
        $currency = $row['currency'] ?? \ClassFlowPro\Admin\Settings::get('currency','usd');
        $status = $row['status'] ?? 'active';
        $thumb_id = !empty($row['featured_image_id']) ? (int)$row['featured_image_id'] : 0;
        $default_location_id = !empty($row['default_location_id']) ? (int)$row['default_location_id'] : 0;

        global $wpdb;
        $locations = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}cfp_locations ORDER BY name", ARRAY_A);
        ?>
        <div class="wrap">
            <h1><?php echo $id ? esc_html__('Edit Class', 'classflow-pro') : esc_html__('Add New Class', 'classflow-pro'); ?></h1>
            <form method="post" id="class-form">
                <?php wp_nonce_field('cfp_save_class'); ?>
                <input type="hidden" name="cfp_action" value="save_class" />
                <?php if ($id): ?><input type="hidden" name="id" value="<?php echo esc_attr((string)$id); ?>" /><?php endif; ?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e('Class Details', 'classflow-pro'); ?></span></h2>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th><label for="cfp_name"><?php esc_html_e('Name', 'classflow-pro'); ?></label></th>
                                            <td><input type="text" class="regular-text" id="cfp_name" name="name" required value="<?php echo esc_attr($name); ?>" /></td>
                                        </tr>
                                        <tr>
                                            <th><label for="cfp_description"><?php esc_html_e('Description', 'classflow-pro'); ?></label></th>
                                            <td><textarea class="large-text" id="cfp_description" name="cfp_description" rows="6"><?php echo esc_textarea($description); ?></textarea></td>
                                        </tr>
                                        <tr>
                                            <th><label for="cfp_duration"><?php esc_html_e('Duration (minutes)', 'classflow-pro'); ?></label></th>
                                            <td><input type="number" min="1" step="1" class="small-text" id="cfp_duration" name="duration" value="<?php echo esc_attr((string)$duration); ?>" /></td>
                                        </tr>
                                        <tr>
                                            <th><label for="cfp_capacity"><?php esc_html_e('Capacity', 'classflow-pro'); ?></label></th>
                                            <td><input type="number" min="1" step="1" class="small-text" id="cfp_capacity" name="capacity" value="<?php echo esc_attr((string)$capacity); ?>" /></td>
                                        </tr>
                                        <tr>
                                            <th><label for="cfp_price"><?php esc_html_e('Price', 'classflow-pro'); ?></label></th>
                                            <td>
                                                <input type="number" step="0.01" min="0" class="small-text" id="cfp_price" name="price" value="<?php echo esc_attr(number_format((float)($price_cents/100), 2, '.', '')); ?>" />
                                                <select id="cfp_currency" name="currency">
                                                    <?php foreach (['usd','eur','gbp','cad','aud'] as $cur): ?>
                                                        <option value="<?php echo esc_attr($cur); ?>" <?php selected($currency, $cur); ?>><?php echo esc_html(strtoupper($cur)); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="default_location_id"><?php esc_html_e('Default Location', 'classflow-pro'); ?></label></th>
                                            <td>
                                                <select id="default_location_id" name="default_location_id">
                                                    <option value="">— <?php esc_html_e('Select', 'classflow-pro'); ?> —</option>
                                                    <?php foreach ($locations as $loc): ?>
                                                        <option value="<?php echo esc_attr((string)$loc['id']); ?>" <?php selected($default_location_id, (int)$loc['id']); ?>><?php echo esc_html($loc['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="cfp_status"><?php esc_html_e('Status', 'classflow-pro'); ?></label></th>
                                            <td>
                                                <select id="cfp_status" name="status">
                                                    <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Active', 'classflow-pro'); ?></option>
                                                    <option value="draft" <?php selected($status, 'draft'); ?>><?php esc_html_e('Draft', 'classflow-pro'); ?></option>
                                                    <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Inactive', 'classflow-pro'); ?></option>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="postbox-container-1" class="postbox-container">
                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e('Featured Image', 'classflow-pro'); ?></span></h2>
                                <div class="inside">
                                    <div id="cfp-featured-preview" style="margin-bottom: 10px;">
                                        <?php if ($thumb_id) echo wp_get_attachment_image($thumb_id, 'thumbnail'); ?>
                                    </div>
                                    <input type="hidden" id="cfp_featured_id" name="featured_image_id" value="<?php echo esc_attr((string)$thumb_id); ?>" />
                                    <button type="button" class="button" id="cfp-upload-image"><?php esc_html_e('Select Image', 'classflow-pro'); ?></button>
                                    <button type="button" class="button" id="cfp-remove-image" <?php echo $thumb_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove Image', 'classflow-pro'); ?></button>
                                </div>
                            </div>

                            <div class="postbox">
                                <h2 class="hndle"><span><?php esc_html_e('Publish', 'classflow-pro'); ?></span></h2>
                                <div class="inside">
                                    <div id="major-publishing-actions">
                                        <?php submit_button($id ? __('Update Class', 'classflow-pro') : __('Add Class', 'classflow-pro'), 'primary large', 'submit', false); ?>
                                        <div class="clear"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <script>
        jQuery(function($){
            $('#cfp-upload-image').on('click', function(e){
                e.preventDefault();
                var frame = wp.media({ title: '<?php echo esc_js(__('Select Featured Image', 'classflow-pro')); ?>', button: { text: '<?php echo esc_js(__('Use this image', 'classflow-pro')); ?>' }, multiple: false });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#cfp_featured_id').val(attachment.id);
                    $('#cfp-featured-preview').html('<img src="'+ (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) +'" style="max-width:150px;height:auto;" />');
                    $('#cfp-remove-image').show();
                });
                frame.open();
            });
            $('#cfp-remove-image').on('click', function(e){ e.preventDefault(); $('#cfp_featured_id').val(''); $('#cfp-featured-preview').empty(); $(this).hide(); });
        });
        </script>
        <?php
    }

    private static function handle_save(): void
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cfp_save_class')) {
            wp_die(esc_html__('Security check failed.', 'classflow-pro'));
        }
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Permission denied.', 'classflow-pro'));
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $content = isset($_POST['cfp_description']) ? wp_kses_post($_POST['cfp_description']) : '';
        $duration = isset($_POST['duration']) ? max(1, (int) $_POST['duration']) : 60;
        $capacity = isset($_POST['capacity']) ? max(1, (int) $_POST['capacity']) : 8;
        $price = isset($_POST['price']) ? max(0, (float) $_POST['price']) : 0.0;
        $currency = isset($_POST['currency']) ? sanitize_text_field($_POST['currency']) : \ClassFlowPro\Admin\Settings::get('currency','usd');
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'active';
        $featured_id = isset($_POST['featured_image_id']) ? (int) $_POST['featured_image_id'] : 0;
        $default_location_id = isset($_POST['default_location_id']) ? (int) $_POST['default_location_id'] : 0;

        $repo = new \ClassFlowPro\DB\Repositories\ClassesRepository();
        $was_existing = $id > 0;
        if ($was_existing) {
            $repo->update($id, [
                'name' => $name,
                'description' => $content,
                'duration_mins' => $duration,
                'capacity' => $capacity,
                'price_cents' => (int) round($price * 100),
                'currency' => $currency,
                'status' => in_array($status, ['active','draft','inactive'], true) ? $status : 'active',
                'featured_image_id' => $featured_id ?: null,
                'default_location_id' => $default_location_id ?: null,
            ]);
        } else {
            $id = $repo->create([
                'name' => $name,
                'description' => $content,
                'duration_mins' => $duration,
                'capacity' => $capacity,
                'price_cents' => (int) round($price * 100),
                'currency' => $currency,
                'status' => in_array($status, ['active','draft','inactive'], true) ? $status : 'active',
                'featured_image_id' => $featured_id ?: null,
                'default_location_id' => $default_location_id ?: null,
            ]);
        }
        $msg = $was_existing ? 'updated' : 'created';
        if ($was_existing) {
            $url = admin_url('admin.php?page=classflow-pro-classes&action=edit&id=' . $id . '&message=' . $msg);
        } else {
            $url = admin_url('admin.php?page=classflow-pro-classes&message=' . $msg);
        }
        if (!headers_sent()) { wp_safe_redirect($url); exit; }
        echo '<script>window.location.href = ' . json_encode($url) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($url) . '"></noscript>';
        exit;
    }

    private static function handle_delete(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$id) {
            wp_die(esc_html__('Invalid request.', 'classflow-pro'));
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'cfp_delete_class_' . $id)) {
            wp_die(esc_html__('Security check failed.', 'classflow-pro'));
        }
        if (!current_user_can('delete_posts')) {
            wp_die(esc_html__('Permission denied.', 'classflow-pro'));
        }
        // Block deletion if there are any future bookings for this class
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $s_tbl = $wpdb->prefix . 'cfp_schedules';
        $b_tbl = $wpdb->prefix . 'cfp_bookings';
        $future_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $b_tbl b JOIN $s_tbl s ON s.id=b.schedule_id WHERE s.class_id = %d AND s.start_time >= %s AND b.status IN ('pending','confirmed')",
            $id, $now
        ));
        if ($future_count > 0) {
            $url = add_query_arg(['error' => 'cannot_delete_booked', 'count' => $future_count], admin_url('admin.php?page=classflow-pro-classes'));
            if (!headers_sent()) { wp_safe_redirect($url); exit; }
            echo '<script>window.location.href = ' . json_encode($url) . '</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' + esc_url($url) + '"></noscript>';
            exit;
        }
        // No future bookings: cascade delete schedules and related rows, then delete the class
        $w_tbl = $wpdb->prefix . 'cfp_waitlist';
        $schedule_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $s_tbl WHERE class_id = %d", $id));
        if (!empty($schedule_ids)) {
            foreach ($schedule_ids as $sid) {
                $wpdb->update($b_tbl, ['status' => 'canceled'], ['schedule_id' => (int)$sid], ['%s'], ['%d']);
                $wpdb->delete($w_tbl, ['schedule_id' => (int)$sid], ['%d']);
                $wpdb->delete($s_tbl, ['id' => (int)$sid], ['%d']);
            }
        }
        $repo = new \ClassFlowPro\DB\Repositories\ClassesRepository();
        $repo->delete($id);
        $url = admin_url('admin.php?page=classflow-pro-classes&message=deleted');
        if (!headers_sent()) { wp_safe_redirect($url); exit; }
        echo '<script>window.location.href = ' . json_encode($url) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($url) . '"></noscript>';
        exit;
    }

    private static function admin_notice(): void
    {
        $message = isset($_GET['message']) ? sanitize_key($_GET['message']) : '';
        $error = isset($_GET['error']) ? sanitize_key($_GET['error']) : '';
        if ($error === 'cannot_delete_booked') {
            $count = isset($_GET['count']) ? (int) $_GET['count'] : 0;
            $schedules_link = admin_url('admin.php?page=classflow-pro-schedules');
            echo '<div class="notice notice-error is-dismissible"><p>'
                . sprintf(esc_html__('Cannot delete this class: %d upcoming booking(s) exist. Please cancel or reschedule them first.', 'classflow-pro'), max(0, $count))
                . ' <a href="' + esc_url($schedules_link) + '">' . esc_html__('Open Schedules', 'classflow-pro') . '</a>'
                . '</p></div>';
        }
        $map = [
            'created' => __('Class created successfully.', 'classflow-pro'),
            'updated' => __('Class updated successfully.', 'classflow-pro'),
            'deleted' => __('Class deleted successfully.', 'classflow-pro'),
        ];
        if (isset($map[$message])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($map[$message]) . '</p></div>';
        }
    }
}

