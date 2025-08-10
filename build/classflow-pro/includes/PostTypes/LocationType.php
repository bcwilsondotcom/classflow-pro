<?php
namespace ClassFlowPro\PostTypes;

class LocationType
{
    public static function register(): void
    {
        register_post_type('cfp_location', [
            'labels' => [
                'name' => __('Locations', 'classflow-pro'),
                'singular_name' => __('Location', 'classflow-pro'),
                'add_new' => __('Add New Location', 'classflow-pro'),
                'add_new_item' => __('Add New Location', 'classflow-pro'),
                'edit_item' => __('Edit Location', 'classflow-pro'),
                'new_item' => __('New Location', 'classflow-pro'),
                'view_item' => __('View Location', 'classflow-pro'),
                'all_items' => __('All Locations', 'classflow-pro'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-location',
            'show_in_menu' => 'classflow-pro',
            'supports' => ['title', 'editor'],
        ]);

        register_post_meta('cfp_location', '_cfp_address', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_location', '_cfp_timezone', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);

        add_action('add_meta_boxes_cfp_location', [self::class, 'add_meta_boxes']);
        add_action('save_post_cfp_location', [self::class, 'save_meta'], 10, 2);

        add_filter('manage_edit-cfp_location_columns', [self::class, 'columns']);
        add_action('manage_cfp_location_posts_custom_column', [self::class, 'column_content'], 10, 2);
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box('cfp_location_details', __('Location Details', 'classflow-pro'), [self::class, 'render_meta_box'], 'cfp_location', 'normal', 'default');
    }

    public static function render_meta_box($post): void
    {
        $address = get_post_meta($post->ID, '_cfp_address', true);
        $tz = get_post_meta($post->ID, '_cfp_timezone', true) ?: wp_timezone_string();
        wp_nonce_field('cfp_location_meta_' . $post->ID, 'cfp_location_meta_nonce');
        echo '<p><label>' . esc_html__('Address', 'classflow-pro') . '<br/>';
        echo '<textarea name="cfp_address" class="large-text" rows="3">' . esc_textarea($address) . '</textarea></label></p>';
        echo '<p><label>' . esc_html__('Timezone', 'classflow-pro') . '<br/>';
        echo '<input type="text" name="cfp_timezone" value="' . esc_attr($tz) . '" class="regular-text" placeholder="America/Los_Angeles"></label></p>';
    }

    public static function save_meta(int $post_id, $post): void
    {
        if (!isset($_POST['cfp_location_meta_nonce']) || !wp_verify_nonce($_POST['cfp_location_meta_nonce'], 'cfp_location_meta_' . $post_id)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (isset($_POST['cfp_address'])) update_post_meta($post_id, '_cfp_address', wp_kses_post($_POST['cfp_address']));
        if (isset($_POST['cfp_timezone'])) update_post_meta($post_id, '_cfp_timezone', sanitize_text_field($_POST['cfp_timezone']));
    }

    public static function columns(array $cols): array
    {
        $new = [];
        foreach ($cols as $k => $v) {
            $new[$k] = $v;
            if ($k === 'title') {
                $new['cfp_timezone'] = __('Timezone', 'classflow-pro');
                $new['cfp_address'] = __('Address', 'classflow-pro');
            }
        }
        return $new;
    }

    public static function column_content(string $col, int $post_id): void
    {
        if ($col === 'cfp_timezone') {
            echo esc_html(get_post_meta($post_id, '_cfp_timezone', true) ?: '');
        } elseif ($col === 'cfp_address') {
            echo esc_html(wp_strip_all_tags(get_post_meta($post_id, '_cfp_address', true) ?: ''));
        }
    }
}

