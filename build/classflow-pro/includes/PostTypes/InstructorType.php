<?php
namespace ClassFlowPro\PostTypes;

class InstructorType
{
    public static function register(): void
    {
        register_post_type('cfp_instructor', [
            'labels' => [
                'name' => __('Instructors', 'classflow-pro'),
                'singular_name' => __('Instructor', 'classflow-pro'),
            ],
            'public' => true,
            'has_archive' => false,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-universal-access',
            'show_in_menu' => 'classflow-pro',
            'supports' => ['title', 'editor', 'thumbnail'],
        ]);

        // Meta: Stripe Connect account id, pay split percent
        register_post_meta('cfp_instructor', '_cfp_stripe_account_id', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_instructor', '_cfp_payout_percent', [
            'type' => 'number', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_instructor', '_cfp_email', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_instructor', '_cfp_availability_weekly', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_instructor', '_cfp_blackout_dates', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);

        add_action('add_meta_boxes_cfp_instructor', [self::class, 'add_meta_boxes']);
        add_action('save_post_cfp_instructor', [self::class, 'save_meta'], 10, 2);

        // Admin columns
        add_filter('manage_edit-cfp_instructor_columns', [self::class, 'columns']);
        add_action('manage_cfp_instructor_posts_custom_column', [self::class, 'column_content'], 10, 2);
        add_filter('manage_edit-cfp_instructor_sortable_columns', [self::class, 'sortable_columns']);
        add_action('pre_get_posts', [self::class, 'handle_sorting']);
        add_action('quick_edit_custom_box', [self::class, 'quick_edit'], 10, 2);
        add_action('save_post_cfp_instructor', [self::class, 'save_quick_edit'], 10, 2);
    }

    public static function columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['cfp_payout'] = __('Payout %', 'classflow-pro');
                $new['cfp_stripe_acct'] = __('Stripe Account', 'classflow-pro');
                $new['cfp_email'] = __('Email', 'classflow-pro');
            }
        }
        return $new;
    }

    public static function column_content(string $column, int $post_id): void
    {
        switch ($column) {
            case 'cfp_payout':
                $v = get_post_meta($post_id, '_cfp_payout_percent', true);
                echo esc_html($v !== '' ? rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.') : '0') . '%';
                break;
            case 'cfp_stripe_acct':
                $v = get_post_meta($post_id, '_cfp_stripe_account_id', true);
                echo $v ? '<code>' . esc_html($v) . '</code>' : '—';
                // Hidden payload for quick edit
                echo '<span class="cfp-inline" style="display:none" data-payout="' . esc_attr((string)get_post_meta($post_id, '_cfp_payout_percent', true)) . '" data-stripe="' . esc_attr((string)$v) . '" data-email="' . esc_attr((string)get_post_meta($post_id, '_cfp_email', true)) . '"></span>';
                break;
            case 'cfp_email':
                $e = get_post_meta($post_id, '_cfp_email', true);
                echo $e ? esc_html($e) : '—';
                break;
        }
    }

    public static function sortable_columns(array $columns): array
    {
        $columns['cfp_payout'] = 'cfp_payout';
        $columns['cfp_stripe_acct'] = 'cfp_stripe_acct';
        $columns['cfp_email'] = 'cfp_email';
        return $columns;
    }

    public static function handle_sorting($query): void
    {
        if (!is_admin() || !$query->is_main_query()) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type !== 'cfp_instructor') return;
        $orderby = $query->get('orderby');
        switch ($orderby) {
            case 'cfp_payout':
                $query->set('meta_key', '_cfp_payout_percent');
                $query->set('orderby', 'meta_value_num');
                break;
            case 'cfp_stripe_acct':
                $query->set('meta_key', '_cfp_stripe_account_id');
                $query->set('orderby', 'meta_value');
                break;
        }
    }

    public static function quick_edit(string $column, string $post_type): void
    {
        if ($post_type !== 'cfp_instructor') return;
        if ($column !== 'title') return; // place fields in quick edit under title section
        echo '<fieldset class="inline-edit-col-left"><div class="inline-edit-col">';
        echo '<div class="inline-edit-group">';
        echo '<label><span class="title">' . esc_html__('Payout %', 'classflow-pro') . '</span><span class="input-text-wrap"><input type="number" step="0.1" min="0" max="100" name="cfp_payout_percent" value=""></span></label>';
        echo '<label><span class="title">' . esc_html__('Stripe Account', 'classflow-pro') . '</span><span class="input-text-wrap"><input type="text" name="cfp_stripe_account_id" value=""></span></label>';
        echo '<label><span class="title">' . esc_html__('Email', 'classflow-pro') . '</span><span class="input-text-wrap"><input type="email" name="cfp_email" value=""></span></label>';
        echo '</div></div></fieldset>';
    }

    public static function save_quick_edit(int $post_id, $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!isset($_POST['_inline_edit']) || !wp_verify_nonce($_POST['_inline_edit'], 'inlineeditnonce')) return;
        if (isset($_POST['cfp_payout_percent'])) {
            update_post_meta($post_id, '_cfp_payout_percent', (float)$_POST['cfp_payout_percent']);
        }
        if (isset($_POST['cfp_stripe_account_id'])) {
            update_post_meta($post_id, '_cfp_stripe_account_id', sanitize_text_field($_POST['cfp_stripe_account_id']));
        }
        if (isset($_POST['cfp_email'])) {
            update_post_meta($post_id, '_cfp_email', sanitize_email($_POST['cfp_email']));
        }
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box('cfp_instructor_availability', __('Availability & Blackouts', 'classflow-pro'), [self::class, 'render_meta_box'], 'cfp_instructor', 'normal', 'default');
    }

    public static function render_meta_box($post): void
    {
        $weekly = get_post_meta($post->ID, '_cfp_availability_weekly', true);
        $blackouts = get_post_meta($post->ID, '_cfp_blackout_dates', true);
        wp_nonce_field('cfp_instructor_meta_' . $post->ID, 'cfp_instructor_meta_nonce');
        echo '<p><label>' . esc_html__('Weekly Availability (e.g., Mon 08:00-12:00; Tue 14:00-18:00)', 'classflow-pro') . '<br/>';
        echo '<textarea name="cfp_availability_weekly" rows="3" class="large-text">' . esc_textarea($weekly) . '</textarea></label></p>';
        echo '<p><label>' . esc_html__('Blackout Dates (YYYY-MM-DD, comma separated)', 'classflow-pro') . '<br/>';
        echo '<input type="text" name="cfp_blackout_dates" value="' . esc_attr($blackouts) . '" class="regular-text"/></label></p>';
    }

    public static function save_meta(int $post_id, $post): void
    {
        if (!isset($_POST['cfp_instructor_meta_nonce']) || !wp_verify_nonce($_POST['cfp_instructor_meta_nonce'], 'cfp_instructor_meta_' . $post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (isset($_POST['cfp_availability_weekly'])) update_post_meta($post_id, '_cfp_availability_weekly', wp_kses_post($_POST['cfp_availability_weekly']));
        if (isset($_POST['cfp_blackout_dates'])) update_post_meta($post_id, '_cfp_blackout_dates', sanitize_text_field($_POST['cfp_blackout_dates']));
    }
}
