<?php
// Function to add services to the menu upon post creation
function add_service_to_menu($new_status, $old_status, $post) {
    // Verify the post type is 'services'
    if ($post->post_type != 'page2') {
        return;
    }
    // Ensure the post is transitioning to "publish" from another status
    if ($new_status != 'publish' || $old_status == 'publish') {
        return;
    }
    // Get the post title and permalink
    $post_title = get_the_title($post->ID);
    $post_url = get_permalink($post->ID);
    // Define the menus to update (Main and Main Menu Toggle menus)
    $menu_names_with_parent = ['Main Menu', 'Main Menu Toggle'];
    foreach ($menu_names_with_parent as $menu_name) {
        // Get the menu object
        $menu = wp_get_nav_menu_object($menu_name);
        if (!$menu) {
            continue;
        }
        $menu_items = wp_get_nav_menu_items($menu->term_id);
        $parent_item_id = null;
        $exists = false;
        // Find the "Services" parent item and check for duplicates
        foreach ($menu_items as $item) {
            if ($item->title == 'Services') {
                $parent_item_id = $item->ID;
            }
            if ($item->title == $post_title && $item->url == $post_url) {
                $exists = true;
                break;
            }
        }
        // Skip adding the item if it already exists
        if ($exists) {
            continue;
        }
        if ($parent_item_id) {
            // Add the new service item under the "Services" parent
            wp_update_nav_menu_item($menu->term_id, 0, array(
                'menu-item-object-id' => $post->ID,
                'menu-item-object' => 'page2', // Explicitly set to 'page2' post type
                'menu-item-type' => 'post_type',
                'menu-item-title' => $post_title,
                'menu-item-url' => $post_url,
                'menu-item-status' => 'publish',
                'menu-item-parent-id' => $parent_item_id,
                'menu-item-position' => 1, // Add it at the top of the list
            ));
        }
    }
    // Add the new service item to the bottom of the "Services" menu without a parent
    $services_menu_name = 'Services'; // Assuming "Services" is the correct name of the Services menu
    $menu = wp_get_nav_menu_object($services_menu_name);
    if ($menu) {
        $menu_items = wp_get_nav_menu_items($menu->term_id);
        $max_position = 0;
        $exists = false;
        // Check if the item already exists in the "Services" menu
        foreach ($menu_items as $item) {
            if ($item->title == $post_title && $item->url == $post_url) {
                $exists = true;
                break;
            }
            $max_position = max($max_position, $item->menu_order);
        }
        // Skip adding the item if it already exists
        if (!$exists) {
            wp_update_nav_menu_item($menu->term_id, 0, array(
                'menu-item-object-id' => $post->ID,
                'menu-item-object' => 'page2', // Explicitly set to 'page2' post type
                'menu-item-type' => 'post_type',
                'menu-item-title' => $post_title,
                'menu-item-url' => $post_url,
                'menu-item-status' => 'publish',
                'menu-item-position' => $max_position + 1, // Add it at the bottom of the list with no parent
            ));
        }
    }
}
add_action('transition_post_status', 'add_service_to_menu', 10, 3);
