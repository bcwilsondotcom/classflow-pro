<?php
namespace ClassFlowPro\Admin;

use ClassFlowPro\Memberships\Manager as MembershipManager;

class Memberships
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        if ($_SERVER['REQUEST_METHOD']==='POST' && check_admin_referer('cfp_memberships_action')) {
            self::handle_post();
            $action='';
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Membership Plans', 'classflow-pro') . '</h1>';
        if ($action==='new' || ($action==='edit' && !empty($_GET['id']))) {
            self::render_form((int)($_GET['id'] ?? 0));
        } else {
            self::render_list();
        }
        echo '</div>';
    }

    private static function render_list(): void
    {
        $plans = MembershipManager::list_plans();
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=classflow-pro-memberships&action=new')) . '">' . esc_html__('Add Plan', 'classflow-pro') . '</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Name','classflow-pro') . '</th><th>' . esc_html__('Credits/Period','classflow-pro') . '</th><th>' . esc_html__('Period','classflow-pro') . '</th><th>' . esc_html__('Stripe Price','classflow-pro') . '</th><th>' . esc_html__('Active','classflow-pro') . '</th><th>' . esc_html__('Actions','classflow-pro') . '</th></tr></thead><tbody>';
        if (!$plans) echo '<tr><td colspan="6">' . esc_html__('No plans yet. Create one.', 'classflow-pro') . '</td></tr>';
        foreach ($plans as $p) {
            $edit = esc_url(admin_url('admin.php?page=classflow-pro-memberships&action=edit&id=' . (int)$p['id']));
            echo '<tr>';
            echo '<td>' . esc_html($p['name']) . '</td>';
            echo '<td>' . esc_html((string)$p['credits_per_period']) . '</td>';
            echo '<td>' . esc_html($p['period']) . '</td>';
            echo '<td><code>' . esc_html($p['stripe_price_id']) . '</code></td>';
            echo '<td>' . ($p['active'] ? 'Yes' : 'No') . '</td>';
            echo '<td><a class="button" href="' . $edit . '">' . esc_html__('Edit','classflow-pro') . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_form(int $id): void
    {
        global $wpdb; $t=$wpdb->prefix.'cfp_membership_plans';
        $row = $id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id), ARRAY_A) : null;
        $name = $row['name'] ?? '';
        $desc = $row['description'] ?? '';
        $credits = isset($row['credits_per_period']) ? (int)$row['credits_per_period'] : 0;
        $period = $row['period'] ?? 'monthly';
        $price = $row['stripe_price_id'] ?? '';
        $active = isset($row['active']) ? (int)$row['active'] : 1;
        echo '<h2>' . ($id ? esc_html__('Edit Plan','classflow-pro') : esc_html__('New Plan','classflow-pro')) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('cfp_memberships_action');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>' . esc_html__('Name','classflow-pro') . '</th><td><input type="text" name="name" value="' . esc_attr($name) . '" class="regular-text" required /></td></tr>';
        echo '<tr><th>' . esc_html__('Description','classflow-pro') . '</th><td><textarea name="description" rows="3" class="large-text">' . esc_textarea($desc) . '</textarea></td></tr>';
        echo '<tr><th>' . esc_html__('Credits per Period','classflow-pro') . '</th><td><input type="number" name="credits" value="' . esc_attr((string)$credits) . '" min="0" step="1" class="small-text" /></td></tr>';
        echo '<tr><th>' . esc_html__('Period','classflow-pro') . '</th><td><select name="period"><option value="monthly"' . selected($period,'monthly',false) . '>Monthly</option><option value="weekly"' . selected($period,'weekly',false) . '>Weekly</option></select></td></tr>';
        echo '<tr><th>' . esc_html__('Stripe Price ID','classflow-pro') . '</th><td><input type="text" name="stripe_price_id" value="' . esc_attr($price) . '" class="regular-text" required /> <p class="description">e.g., price_...</p></td></tr>';
        echo '<tr><th>' . esc_html__('Active','classflow-pro') . '</th><td><label><input type="checkbox" name="active" value="1"' . checked($active,1,false) . ' /> ' . esc_html__('Enabled','classflow-pro') . '</label></td></tr>';
        echo '</tbody></table>';
        echo '<p><input type="hidden" name="cfp_do" value="save_plan" /><input type="hidden" name="id" value="' . esc_attr((string)$id) . '" />';
        submit_button($id?__('Save Changes','classflow-pro'):__('Create Plan','classflow-pro'), 'primary', '', false); echo '</p>';
        echo '</form>';
    }

    private static function handle_post(): void
    {
        $do = sanitize_text_field($_POST['cfp_do'] ?? '');
        if ($do==='save_plan') {
            global $wpdb; $t=$wpdb->prefix.'cfp_membership_plans';
            $id = (int)($_POST['id'] ?? 0);
            $data = [
                'name' => sanitize_text_field($_POST['name'] ?? ''),
                'description' => wp_kses_post($_POST['description'] ?? ''),
                'credits_per_period' => max(0, (int)($_POST['credits'] ?? 0)),
                'period' => in_array($_POST['period'] ?? 'monthly', ['monthly','weekly'], true) ? $_POST['period'] : 'monthly',
                'stripe_price_id' => sanitize_text_field($_POST['stripe_price_id'] ?? ''),
                'active' => !empty($_POST['active']) ? 1 : 0,
            ];
            if ($id) {
                $wpdb->update($t, $data, ['id'=>$id]);
                echo '<div class="notice notice-success"><p>' . esc_html__('Plan updated.','classflow-pro') . '</p></div>';
            } else {
                $wpdb->insert($t, $data);
                echo '<div class="notice notice-success"><p>' . esc_html__('Plan created.','classflow-pro') . '</p></div>';
            }
        }
    }
}
