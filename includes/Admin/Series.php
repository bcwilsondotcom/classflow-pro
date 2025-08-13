<?php
namespace ClassFlowPro\Admin;

if (!defined('ABSPATH')) { exit; }

class Series
{
    public static function render(): void
    {
        if (!current_user_can('cfp_manage_schedules') && !current_user_can('manage_options')) return;
        $action = isset($_GET['action']) ? sanitize_key((string)$_GET['action']) : 'list';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cfp_action']) && $_POST['cfp_action']==='save') { self::handle_save(); return; }
        if ($action === 'new' || $action === 'edit') self::render_form(); else self::render_list();
    }

    private static function render_list(): void
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_series';
        $page = max(1, (int)($_GET['paged'] ?? 1)); $per=20; $off=($page-1)*$per;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t ORDER BY id DESC LIMIT %d OFFSET %d", $per, $off), ARRAY_A);
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $t");
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Series', 'classflow-pro') . '</h1>';
        echo ' <a href="' . esc_url(admin_url('admin.php?page=classflow-pro-series&action=new')) . '" class="page-title-action">' . esc_html__('Add New', 'classflow-pro') . '</a>';
        echo '<hr class="wp-header-end" />';
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>'
            . '<th>' . esc_html__('Name','classflow-pro') . '</th>'
            . '<th>' . esc_html__('Class','classflow-pro') . '</th>'
            . '<th>' . esc_html__('Dates','classflow-pro') . '</th>'
            . '<th>' . esc_html__('Price','classflow-pro') . '</th>'
            . '<th>' . esc_html__('Status','classflow-pro') . '</th>'
            . '<th>' . esc_html__('Actions','classflow-pro') . '</th>'
            . '</tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="6">' . esc_html__('No series found.','classflow-pro') . '</td></tr>';
        foreach ($rows as $r) {
            $cls = \ClassFlowPro\Utils\Entities::class_name((int)$r['class_id']);
            $price = '$' . number_format_i18n(((int)$r['price_cents'])/100, 2);
            echo '<tr>'
                . '<td><strong><a href="' . esc_url(admin_url('admin.php?page=classflow-pro-series&action=edit&id='.(int)$r['id'])) . '">' . esc_html($r['name']) . '</a></strong></td>'
                . '<td>' . esc_html($cls) . '</td>'
                . '<td>' . esc_html($r['start_date'] . ' → ' . $r['end_date']) . '</td>'
                . '<td>' . esc_html($price) . '</td>'
                . '<td>' . esc_html($r['status']) . '</td>'
                . '<td><a class="button" href="' . esc_url(admin_url('admin.php?page=classflow-pro-series&action=edit&id='.(int)$r['id'])) . '">' . esc_html__('Manage','classflow-pro') . '</a></td>'
                . '</tr>';
        }
        echo '</tbody></table>';
        $pages = (int)ceil($total/$per);
        if ($pages>1) { echo '<div class="tablenav bottom"><div class="tablenav-pages">' . paginate_links(['base'=>add_query_arg('paged','%#%'),'total'=>$pages,'current'=>$page]) . '</div></div>'; }
        echo '</div>';
    }

    private static function render_form(): void
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_series';
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A) : null;
        $name = $row['name'] ?? '';
        $class_id = (int)($row['class_id'] ?? 0);
        $location_id = (int)($row['location_id'] ?? 0);
        $instructor_id = (int)($row['instructor_id'] ?? 0);
        $price_cents = (int)($row['price_cents'] ?? 0);
        $start_date = $row['start_date'] ?? gmdate('Y-m-d');
        $end_date = $row['end_date'] ?? gmdate('Y-m-d', strtotime('+6 weeks'));
        $capacity = (int)($row['capacity'] ?? 0);
        $status = $row['status'] ?? 'active';

        // Simple lookups
        $classes = \ClassFlowPro\Utils\Entities::classes_list();
        $locations = \ClassFlowPro\Utils\Entities::locations_list();
        $instructors = \ClassFlowPro\Utils\Entities::instructors_list();

        echo '<div class="wrap"><h1>' . ($id?esc_html__('Edit Series','classflow-pro'):esc_html__('Add Series','classflow-pro')) . '</h1>';
        echo '<form method="post">'; wp_nonce_field('cfp_save_series'); echo '<input type="hidden" name="cfp_action" value="save" />'; if ($id) echo '<input type="hidden" name="id" value="' . esc_attr((string)$id) . '" />';
        echo '<table class="form-table">';
        echo '<tr><th><label>' . esc_html__('Name','classflow-pro') . '</label></th><td><input name="name" class="regular-text" required value="' . esc_attr($name) . '"/></td></tr>';
        echo '<tr><th><label>' . esc_html__('Class','classflow-pro') . '</label></th><td><select name="class_id" required><option value="">' . esc_html__('Select','classflow-pro') . '</option>'; foreach ($classes as $c) { echo '<option value="' . (int)$c['id'] . '"' . selected($class_id,(int)$c['id'],false) . '>' . esc_html($c['name']) . '</option>'; } echo '</select></td></tr>';
        echo '<tr><th><label>' . esc_html__('Location','classflow-pro') . '</label></th><td><select name="location_id"><option value="0">' . esc_html__('Any','classflow-pro') . '</option>'; foreach ($locations as $l) { echo '<option value="' . (int)$l['id'] . '"' . selected($location_id,(int)$l['id'],false) . '>' . esc_html($l['name']) . '</option>'; } echo '</select></td></tr>';
        echo '<tr><th><label>' . esc_html__('Instructor','classflow-pro') . '</label></th><td><select name="instructor_id"><option value="0">' . esc_html__('Any','classflow-pro') . '</option>'; foreach ($instructors as $i) { echo '<option value="' . (int)$i['id'] . '"' . selected($instructor_id,(int)$i['id'],false) . '>' . esc_html($i['name']) . '</option>'; } echo '</select></td></tr>';
        echo '<tr><th><label>' . esc_html__('Date Range','classflow-pro') . '</label></th><td><input type="date" name="start_date" value="' . esc_attr($start_date) . '"/> → <input type="date" name="end_date" value="' . esc_attr($end_date) . '"/></td></tr>';
        echo '<tr><th><label>' . esc_html__('Series Price (USD cents)','classflow-pro') . '</label></th><td><input type="number" name="price_cents" min="0" step="1" value="' . esc_attr((string)$price_cents) . '"/></td></tr>';
        echo '<tr><th><label>' . esc_html__('Capacity (enrollments)','classflow-pro') . '</label></th><td><input type="number" name="capacity" min="0" step="1" value="' . esc_attr((string)$capacity) . '"/> <span class="description">' . esc_html__('0 = derive from schedules each week','classflow-pro') . '</span></td></tr>';
        echo '<tr><th><label>' . esc_html__('Status','classflow-pro') . '</label></th><td><select name="status"><option value="active"' . selected($status,'active',false) . '>Active</option><option value="archived"' . selected($status,'archived',false) . '>Archived</option></select></td></tr>';
        echo '</table>';
        submit_button($id?__('Update Series','classflow-pro'):__('Create Series','classflow-pro'));
        echo '</form>';

        if ($id) {
            // Show included sessions
            $ss = $wpdb->prefix . 'cfp_series_sessions';
            $schedules = $wpdb->prefix . 'cfp_schedules';
            $rows = $wpdb->get_results($wpdb->prepare("SELECT s.* FROM $schedules s JOIN $ss x ON x.schedule_id=s.id WHERE x.series_id=%d ORDER BY s.start_time ASC", $id), ARRAY_A);
            echo '<h2>' . esc_html__('Sessions in this series','classflow-pro') . '</h2>';
            echo '<table class="widefat striped"><thead><tr><th>#</th><th>' . esc_html__('Start','classflow-pro') . '</th><th>' . esc_html__('Capacity','classflow-pro') . '</th><th>' . esc_html__('Booked','classflow-pro') . '</th></tr></thead><tbody>';
            if (!$rows) echo '<tr><td colspan="4">' . esc_html__('No sessions linked. Save to populate.','classflow-pro') . '</td></tr>';
            foreach ($rows as $r) {
                $booked = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cfp_bookings WHERE schedule_id=%d AND status IN ('pending','confirmed')", (int)$r['id']));
                echo '<tr><td>#' . (int)$r['id'] . '</td><td>' . esc_html(gmdate('Y-m-d H:i', strtotime($r['start_time']))) . ' UTC</td><td>' . (int)$r['capacity'] . '</td><td>' . $booked . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    private static function handle_save(): void
    {
        if (!current_user_can('cfp_manage_schedules') && !current_user_can('manage_options')) wp_die('Forbidden');
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cfp_save_series')) wp_die('Security check failed');
        global $wpdb; $t=$wpdb->prefix.'cfp_series';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = sanitize_text_field($_POST['name'] ?? '');
        $class_id = (int)($_POST['class_id'] ?? 0);
        $location_id = (int)($_POST['location_id'] ?? 0);
        $instructor_id = (int)($_POST['instructor_id'] ?? 0);
        $start_date = preg_replace('/[^0-9\-]/','',$_POST['start_date'] ?? '');
        $end_date = preg_replace('/[^0-9\-]/','',$_POST['end_date'] ?? '');
        $price_cents = max(0, (int)($_POST['price_cents'] ?? 0));
        $capacity = max(0, (int)($_POST['capacity'] ?? 0));
        $status = in_array(($_POST['status'] ?? 'active'), ['active','archived'], true) ? $_POST['status'] : 'active';
        if (!$name || !$class_id || !$start_date || !$end_date) wp_die('Missing required fields');
        $data = [ 'name'=>$name, 'class_id'=>$class_id, 'location_id'=>$location_id?:null, 'instructor_id'=>$instructor_id?:null, 'start_date'=>$start_date, 'end_date'=>$end_date, 'price_cents'=>$price_cents, 'currency'=>'usd', 'capacity'=>$capacity, 'status'=>$status ];
        $fmt = ['%s','%d','%d','%d','%s','%s','%d','%s','%d','%s'];
        if ($id) { $wpdb->update($t, $data, ['id'=>$id], $fmt, ['%d']); } else { $wpdb->insert($t, $data, $fmt); $id = (int)$wpdb->insert_id; }

        // Populate sessions from schedules in date range for this class (and optional filters)
        $schedules = $wpdb->prefix . 'cfp_schedules';
        $where = $wpdb->prepare('WHERE class_id = %d AND start_time BETWEEN %s AND %s', $class_id, $start_date.' 00:00:00', $end_date.' 23:59:59');
        if ($location_id) { $where .= $wpdb->prepare(' AND location_id = %d', $location_id); }
        if ($instructor_id) { $where .= $wpdb->prepare(' AND instructor_id = %d', $instructor_id); }
        $list = $wpdb->get_results("SELECT id FROM $schedules $where ORDER BY start_time ASC", ARRAY_A);
        $ss = $wpdb->prefix . 'cfp_series_sessions';
        // Reset and insert
        $wpdb->query($wpdb->prepare("DELETE FROM $ss WHERE series_id=%d", $id));
        foreach ($list as $row) { $wpdb->insert($ss, ['series_id'=>$id, 'schedule_id'=>(int)$row['id']], ['%d','%d']); }

        wp_redirect(admin_url('admin.php?page=classflow-pro-series&action=edit&id=' . $id));
        exit;
    }
}

