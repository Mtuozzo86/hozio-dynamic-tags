<?php
// Register the setting to save the enable/disable option for custom permalinks
if (!function_exists('hozio_custom_permalink_register_setting')) {
    add_action('admin_init', 'hozio_custom_permalink_register_setting');

    function hozio_custom_permalink_register_setting() {
        register_setting('hozio_permalink_settings', 'hozio_custom_permalink_enabled');
    }
}

// Flush rewrite rules when custom permalink setting is updated
if (!function_exists('hozio_flush_rewrite_rules')) {
    add_action('update_option_hozio_custom_permalink_enabled', 'hozio_flush_rewrite_rules');

    function hozio_flush_rewrite_rules() {
        flush_rewrite_rules();
    }
}

// Hook to modify the permalink structure
if (!function_exists('hozio_custom_post_link')) {
    add_filter('post_link', 'hozio_custom_post_link', 10, 2);

    function hozio_custom_post_link($permalink, $post) {
        // Check if the custom permalink feature is enabled
        $is_enabled = get_option('hozio_custom_permalink_enabled');

        if (!$is_enabled) {
            return $permalink; // Return the default permalink if the feature is disabled
        }

        // Only apply to posts (blog entries)
        if ($post->post_type == 'post') {
            // Log to debug
            error_log('Custom permalink function is running.');

            // Get the first category of the post
            $categories = get_the_category($post->ID);
            if (!empty($categories)) {
                $category = $categories[0]->slug; // Use the slug of the first category
                // Modify the permalink to include /blog/category/postname structure
                $permalink = home_url('/blog/' . $category . '/' . $post->post_name . '/');
            }
        }
        return $permalink;
    }
}
