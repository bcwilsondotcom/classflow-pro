<?php
namespace ClassFlowPro\PostTypes;

class ClassType
{
    public static function register(): void
    {
        register_post_type('cfp_class', [
            'labels' => [
                'name' => \__('Classes', 'classflow-pro'),
                'singular_name' => \__('Class', 'classflow-pro'),
                'menu_name' => \__('Classes', 'classflow-pro'),
                'name_admin_bar' => \__('Class', 'classflow-pro'),
                'add_new' => \__('Add New Class', 'classflow-pro'),
                'add_new_item' => \__('Add New Class', 'classflow-pro'),
                'edit_item' => \__('Edit Class', 'classflow-pro'),
                'new_item' => \__('New Class', 'classflow-pro'),
                'view_item' => \__('View Class', 'classflow-pro'),
                'all_items' => \__('All Classes', 'classflow-pro'),
                'search_items' => \__('Search Classes', 'classflow-pro'),
                'not_found' => \__('No classes found.', 'classflow-pro'),
                'not_found_in_trash' => \__('No classes found in Trash.', 'classflow-pro'),
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-groups',
            'show_in_menu' => 'classflow-pro',
            'supports' => ['title', 'editor', 'thumbnail'],
        ]);

        // Meta fields: duration mins, default capacity, default price cents, currency
        register_post_meta('cfp_class', '_cfp_duration_mins', [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_class', '_cfp_capacity', [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_class', '_cfp_price_cents', [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_class', '_cfp_currency', [
            'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);
        register_post_meta('cfp_class', '_cfp_default_location_id', [
            'type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => '__return_true'
        ]);

        // Metabox for Class details
        add_action('add_meta_boxes_cfp_class', [self::class, 'add_meta_boxes']);
        add_action('save_post_cfp_class', [self::class, 'save_meta'], 10, 2);

        // Admin list columns and sorting
        add_filter('manage_edit-cfp_class_columns', [self::class, 'columns']);
        add_action('manage_cfp_class_posts_custom_column', [self::class, 'column_content'], 10, 2);
        add_filter('manage_edit-cfp_class_sortable_columns', [self::class, 'sortable_columns']);
        add_action('pre_get_posts', [self::class, 'handle_sorting']);
        add_action('quick_edit_custom_box', [self::class, 'quick_edit'], 10, 2);
        add_action('save_post_cfp_class', [self::class, 'save_quick_edit'], 10, 2);
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box('cfp_class_details', \__('Class Details', 'classflow-pro'), [self::class, 'render_meta_box'], 'cfp_class', 'side', 'default');
    }

    public static function render_meta_box($post): void
    {
        $duration = (int)get_post_meta($post->ID, '_cfp_duration_mins', true);
        $capacity = (int)get_post_meta($post->ID, '_cfp_capacity', true);
        $price = (int)get_post_meta($post->ID, '_cfp_price_cents', true);
        $currency = get_post_meta($post->ID, '_cfp_currency', true);
        $default_location_id = (int)get_post_meta($post->ID, '_cfp_default_location_id', true);
        if (!$currency) {
            $currency = \ClassFlowPro\Admin\Settings::get('currency', 'usd');
        }
        wp_nonce_field('cfp_class_meta_' . $post->ID, 'cfp_class_meta_nonce');
        echo '<p><label>' . \esc_html__('Duration (minutes)', 'classflow-pro') . '<br/>';
        echo '<input type="number" name="cfp_duration_mins" value="' . esc_attr($duration ?: 60) . '" min="1" class="small-text"></label></p>';

        echo '<p><label>' . \esc_html__('Default Capacity', 'classflow-pro') . '<br/>';
        echo '<input type="number" name="cfp_capacity" value="' . esc_attr($capacity ?: 8) . '" min="1" class="small-text"></label></p>';

        echo '<p><label>' . \esc_html__('Default Price (cents)', 'classflow-pro') . '<br/>';
        echo '<input type="number" name="cfp_price_cents" value="' . esc_attr($price ?: 3000) . '" min="0" class="regular-text"></label></p>';

        echo '<p><label>' . \esc_html__('Currency', 'classflow-pro') . '<br/>';
        echo '<input type="text" name="cfp_currency" value="' . esc_attr($currency) . '" class="small-text"></label></p>';

        // Default Location selector
        $locations = get_posts(['post_type' => 'cfp_location', 'numberposts' => -1, 'post_status' => 'publish']);
        echo '<p><label>' . \esc_html__('Default Location', 'classflow-pro') . '<br/>';
        echo '<select name="cfp_default_location_id" class="regular-text">';
        echo '<option value="">' . \esc_html__('— None —', 'classflow-pro') . '</option>';
        foreach ($locations as $loc) {
            $sel = \selected($default_location_id, $loc->ID, false);
            echo '<option value="' . \esc_attr($loc->ID) . '"' . $sel . '>' . \esc_html($loc->post_title) . '</option>';
        }
        echo '</select></label></p>';
    }

    public static function save_meta(int $post_id, $post): void
    {
        if (!isset($_POST['cfp_class_meta_nonce']) || !wp_verify_nonce($_POST['cfp_class_meta_nonce'], 'cfp_class_meta_' . $post_id)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $duration = isset($_POST['cfp_duration_mins']) ? max(1, (int)$_POST['cfp_duration_mins']) : null;
        $capacity = isset($_POST['cfp_capacity']) ? max(1, (int)$_POST['cfp_capacity']) : null;
        $price = isset($_POST['cfp_price_cents']) ? max(0, (int)$_POST['cfp_price_cents']) : null;
        $currency = isset($_POST['cfp_currency']) ? sanitize_text_field($_POST['cfp_currency']) : null;
        $default_location_id = isset($_POST['cfp_default_location_id']) ? (int)$_POST['cfp_default_location_id'] : null;

        if ($duration !== null) update_post_meta($post_id, '_cfp_duration_mins', $duration);
        if ($capacity !== null) update_post_meta($post_id, '_cfp_capacity', $capacity);
        if ($price !== null) update_post_meta($post_id, '_cfp_price_cents', $price);
        if ($currency !== null) update_post_meta($post_id, '_cfp_currency', $currency);
        if ($default_location_id !== null) update_post_meta($post_id, '_cfp_default_location_id', $default_location_id);
    }

    // Admin columns
    public static function columns(array $columns): array
    {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['cfp_duration'] = \__('Duration', 'classflow-pro');
                $new['cfp_capacity'] = \__('Capacity', 'classflow-pro');
                $new['cfp_price'] = \__('Price', 'classflow-pro');
                $new['cfp_currency'] = \__('Currency', 'classflow-pro');
            }
        }
        return $new;
    }

    public static function column_content(string $column, int $post_id): void
    {
        switch ($column) {
            case 'cfp_duration':
                $v = (int)get_post_meta($post_id, '_cfp_duration_mins', true);
                echo esc_html($v ?: 0);
                break;
            case 'cfp_capacity':
                $v = (int)get_post_meta($post_id, '_cfp_capacity', true);
                echo esc_html($v ?: 0);
                break;
            case 'cfp_price':
                $v = (int)get_post_meta($post_id, '_cfp_price_cents', true);
                $currency = get_post_meta($post_id, '_cfp_currency', true) ?: \ClassFlowPro\Admin\Settings::get('currency', 'usd');
                $amount = number_format_i18n($v / 100, 2);
                echo esc_html($amount . ' ' . strtoupper($currency));
                break;
            case 'cfp_currency':
                $v = get_post_meta($post_id, '_cfp_currency', true) ?: \ClassFlowPro\Admin\Settings::get('currency', 'usd');
                echo esc_html(strtoupper($v));
                // Hidden payload for quick edit
                $d = (int)get_post_meta($post_id, '_cfp_duration_mins', true);
                $c = (int)get_post_meta($post_id, '_cfp_capacity', true);
                $p = (int)get_post_meta($post_id, '_cfp_price_cents', true);
                echo '<span class="cfp-inline" style="display:none" data-duration="' . esc_attr((string)$d) . '" data-capacity="' . esc_attr((string)$c) . '" data-price="' . esc_attr((string)$p) . '" data-currency="' . esc_attr($v) . '"></span>';
                break;
        }
    }

    public static function sortable_columns(array $columns): array
    {
        $columns['cfp_duration'] = 'cfp_duration';
        $columns['cfp_capacity'] = 'cfp_capacity';
        $columns['cfp_price'] = 'cfp_price';
        $columns['cfp_currency'] = 'cfp_currency';
        return $columns;
    }

    public static function handle_sorting($query): void
    {
        if (!is_admin() || !$query->is_main_query()) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type !== 'cfp_class') return;
        $orderby = $query->get('orderby');
        switch ($orderby) {
            case 'cfp_duration':
                $query->set('meta_key', '_cfp_duration_mins');
                $query->set('orderby', 'meta_value_num');
                break;
            case 'cfp_capacity':
                $query->set('meta_key', '_cfp_capacity');
                $query->set('orderby', 'meta_value_num');
                break;
            case 'cfp_price':
                $query->set('meta_key', '_cfp_price_cents');
                $query->set('orderby', 'meta_value_num');
                break;
            case 'cfp_currency':
                $query->set('meta_key', '_cfp_currency');
                $query->set('orderby', 'meta_value');
                break;
        }
    }

    public static function quick_edit(string $column, string $post_type): void
    {
        if ($post_type !== 'cfp_class') return;
        if ($column !== 'title') return;
        echo '<fieldset class="inline-edit-col-left"><div class="inline-edit-col">';
        echo '<div class="inline-edit-group">';
        echo '<label><span class="title">' . esc_html__('Duration (min)', 'classflow-pro') . '</span><span class="input-text-wrap"><input type="number" min="1" name="cfp_duration_mins" value=""></span></label>';
        echo '<label><span class="title">' . esc_html__('Capacity', 'classflow-pro') . '</span><span class="input-text-wrap"><input type="number" min="1" name="cfp_capacity" value=""></span></label>';
        echo '<label><span class="title">' . esc_html__('Price (cents)', 'classflow-pro') . '</span><span class="input-text-wrap"><input type="number" min="0" name="cfp_price_cents" value=""></span></label>';
        echo '<label><span class="title">' . esc_html__('Currency', 'classflow-pro') . '</span><span class="input-text-wrap"><input type="text" name="cfp_currency" value=""></span></label>';
        echo '</div></div></fieldset>';
    }

    public static function save_quick_edit(int $post_id, $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!isset($_POST['_inline_edit']) || !wp_verify_nonce($_POST['_inline_edit'], 'inlineeditnonce')) return;
        if (isset($_POST['cfp_duration_mins'])) update_post_meta($post_id, '_cfp_duration_mins', max(1, (int)$_POST['cfp_duration_mins']));
        if (isset($_POST['cfp_capacity'])) update_post_meta($post_id, '_cfp_capacity', max(1, (int)$_POST['cfp_capacity']));
        if (isset($_POST['cfp_price_cents'])) update_post_meta($post_id, '_cfp_price_cents', max(0, (int)$_POST['cfp_price_cents']));
        if (isset($_POST['cfp_currency'])) update_post_meta($post_id, '_cfp_currency', sanitize_text_field($_POST['cfp_currency']));
    }
}
