<?php
namespace ClassFlowPro\PostTypes;

class ResourceType
{
    public static function register(): void
    {
        register_post_type('cfp_resource', [
            'labels' => [
                'name' => __('Resources', 'classflow-pro'),
                'singular_name' => __('Resource', 'classflow-pro'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-hammer',
            'show_in_menu' => 'classflow-pro',
            'supports' => ['title', 'editor'],
        ]);
        register_post_meta('cfp_resource', '_cfp_capacity', [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_resource', '_cfp_location_id', [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);

        // Admin columns
        add_filter('manage_edit-cfp_resource_columns', [self::class, 'columns']);
        add_action('manage_cfp_resource_posts_custom_column', [self::class, 'column_content'], 10, 2);
        add_filter('manage_edit-cfp_resource_sortable_columns', [self::class, 'sortable_columns']);
        add_action('pre_get_posts', [self::class, 'handle_sorting']);
        add_action('quick_edit_custom_box', [self::class, 'quick_edit'], 10, 2);
        add_action('save_post_cfp_resource', [self::class, 'save_quick_edit'], 10, 2);
    }

    public static function columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['cfp_capacity'] = __('Capacity', 'classflow-pro');
                $new['cfp_location'] = __('Location', 'classflow-pro');
            }
        }
        return $new;
    }

    public static function column_content(string $column, int $post_id): void
    {
        if ($column === 'cfp_capacity') {
            $v = (int)get_post_meta($post_id, '_cfp_capacity', true);
            echo esc_html($v ?: 0);
            echo '<span class="cfp-inline" style="display:none" data-capacity="' . esc_attr((string)$v) . '"></span>';
        } elseif ($column === 'cfp_location') {
            $loc = (int)get_post_meta($post_id, '_cfp_location_id', true);
            echo $loc ? esc_html(get_the_title($loc)) : '—';
        }
    }

    public static function sortable_columns(array $columns): array
    {
        $columns['cfp_capacity'] = 'cfp_capacity';
        $columns['cfp_location'] = 'cfp_location';
        return $columns;
    }

    public static function handle_sorting($query): void
    {
        if (!is_admin() || !$query->is_main_query()) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type !== 'cfp_resource') return;
        $orderby = $query->get('orderby');
        if ($orderby === 'cfp_capacity') {
            $query->set('meta_key', '_cfp_capacity');
            $query->set('orderby', 'meta_value_num');
        }
    }

    public static function quick_edit(string $column, string $post_type): void
    {
        if ($post_type !== 'cfp_resource') return;
        if ($column !== 'title') return;
        echo '<fieldset class="inline-edit-col-left"><div class="inline-edit-col">';
        echo '<div class="inline-edit-group">';
        echo '<label><span class="title">' . esc_html__('Capacity', 'classflow-pro') . '</span><span class="input-text-wrap"><input type="number" min="0" name="cfp_capacity" value=""></span></label>';
        echo '</div></div></fieldset>';
    }

    public static function save_quick_edit(int $post_id, $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!isset($_POST['_inline_edit']) || !wp_verify_nonce($_POST['_inline_edit'], 'inlineeditnonce')) return;
        if (isset($_POST['cfp_capacity'])) {
            update_post_meta($post_id, '_cfp_capacity', (int)$_POST['cfp_capacity']);
        }
    }
}
