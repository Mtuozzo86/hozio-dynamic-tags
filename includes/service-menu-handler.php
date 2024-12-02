<?php
// Function to handle adding/removing pages with parent "Services" to/from menus
function sync_service_pages_to_menus($post_id, $post_after, $post_before) {
    // Ensure we're working with a "page" post type
    if (get_post_type($post_id) !== 'page') {
        return;
    }

    // Get the "Services" parent page ID using WP_Query
    $services_page = new WP_Query([
        'post_type' => 'page',
        'posts_per_page' => 1,
        'post_title' => 'Services',
    ]);

    if (!$services_page->have_posts()) {
        return; // Exit if the "Services" page doesn't exist
    }

    // Get the Services page ID
    $services_page_id = $services_page->posts[0]->ID;

    // Define the menus to update
    $menu_names = ['Main Menu', 'Main Menu Toggle', 'Services'];

    // Get post details
    $post_title = get_the_title($post_id);
    $post_url = get_permalink($post_id);

    // Check if the parent was changed
    $parent_after = $post_after->post_parent;
    $parent_before = $post_before->post_parent;

    // Handle adding the page to menus
    if ($parent_after === $services_page_id) {
        foreach ($menu_names as $menu_name) {
            $menu = wp_get_nav_menu_object($menu_name);
            if ($menu) {
                $menu_items = wp_get_nav_menu_items($menu->term_id);
                $parent_item_id = null;
                $exists = false;
                $special_item_position = null;

                // Determine positions for the "Services" menu and "Services" parent item
                foreach ($menu_items as $item) {
                    if ($menu_name !== 'Services' && $item->title === 'Services') {
                        $parent_item_id = $item->ID; // Parent for Main Menu and Main Menu Toggle
                    }
                    if ($menu_name === 'Services' && strpos($item->title, 'See All Services') !== false) {
                        $special_item_position = $item->menu_order; // For Services menu
                    }
                    if ($item->title === $post_title && $item->url === $post_url) {
                        $exists = true; // Check for duplicates
                        break;
                    }
                }

                // Skip if the item already exists
                if ($exists) {
                    continue;
                }

                // Add the new service item
                if ($menu_name !== 'Services') {
                    // For Main Menu and Main Menu Toggle: Add above "See All Services"
                    wp_update_nav_menu_item($menu->term_id, 0, array(
                        'menu-item-object-id' => $post_id,
                        'menu-item-object' => 'page',
                        'menu-item-type' => 'post_type',
                        'menu-item-title' => $post_title,
                        'menu-item-url' => $post_url,
                        'menu-item-status' => 'publish',
                        'menu-item-parent-id' => $parent_item_id,
                        'menu-item-position' => $special_item_position ? $special_item_position - 1 : 1, // Above "See All Services"
                    ));
                } else {
                    // For Services Menu: Add above everything
                    wp_update_nav_menu_item($menu->term_id, 0, array(
                        'menu-item-object-id' => $post_id,
                        'menu-item-object' => 'page',
                        'menu-item-type' => 'post_type',
                        'menu-item-title' => $post_title,
                        'menu-item-url' => $post_url,
                        'menu-item-status' => 'publish',
                        'menu-item-position' => 1, // Always at the top
                    ));
                }
            }
        }
    }

    // Handle removing the page from menus if parent is no longer "Services"
    if ($parent_before === $services_page_id && $parent_after !== $services_page_id) {
        foreach ($menu_names as $menu_name) {
            $menu = wp_get_nav_menu_object($menu_name);
            if ($menu) {
                $menu_items = wp_get_nav_menu_items($menu->term_id);
                foreach ($menu_items as $item) {
                    if ($item->title === $post_title && $item->url === $post_url) {
                        wp_delete_post($item->ID, true); // Remove the menu item
                        break;
                    }
                }
            }
        }
    }
}

// Ensure all existing pages with "Services" parent are in menus
function ensure_existing_service_pages_in_menus() {
    $services_page = new WP_Query([
        'post_type' => 'page',
        'posts_per_page' => 1,
        'post_title' => 'Services',
    ]);

    if (!$services_page->have_posts()) {
        return; // Exit if the "Services" page doesn't exist
    }

    $services_page_id = $services_page->posts[0]->ID;
    $menu_names = ['Main Menu', 'Main Menu Toggle', 'Services'];

    // Get all child pages of "Services"
    $child_pages = get_pages(array('child_of' => $services_page_id));
    foreach ($child_pages as $page) {
        $post_id = $page->ID;
        $post_title = $page->post_title;
        $post_url = get_permalink($post_id);

        foreach ($menu_names as $menu_name) {
            $menu = wp_get_nav_menu_object($menu_name);
            if ($menu) {
                $menu_items = wp_get_nav_menu_items($menu->term_id);
                $parent_item_id = null;
                $exists = false;
                $special_item_position = null;

                // Determine positions for the "Services" menu and "Services" parent item
                foreach ($menu_items as $item) {
                    if ($menu_name !== 'Services' && $item->title === 'Services') {
                        $parent_item_id = $item->ID; // Parent for Main Menu and Main Menu Toggle
                    }
                    if ($menu_name === 'Services' && strpos($item->title, 'See All Services') !== false) {
                        $special_item_position = $item->menu_order; // For Services menu
                    }
                    if ($item->title === $post_title && $item->url === $post_url) {
                        $exists = true; // Check for duplicates
                        break;
                    }
                }

                // Skip if the item already exists
                if ($exists) {
                    continue;
                }

                // Add the new service item
                if ($menu_name !== 'Services') {
                    // For Main Menu and Main Menu Toggle: Add above "See All Services"
                    wp_update_nav_menu_item($menu->term_id, 0, array(
                        'menu-item-object-id' => $post_id,
                        'menu-item-object' => 'page',
                        'menu-item-type' => 'post_type',
                        'menu-item-title' => $post_title,
                        'menu-item-url' => $post_url,
                        'menu-item-status' => 'publish',
                        'menu-item-parent-id' => $parent_item_id,
                        'menu-item-position' => $special_item_position ? $special_item_position - 1 : 1, // Above "See All Services"
                    ));
                } else {
                    // For Services Menu: Add above everything
                    wp_update_nav_menu_item($menu->term_id, 0, array(
                        'menu-item-object-id' => $post_id,
                        'menu-item-object' => 'page',
                        'menu-item-type' => 'post_type',
                        'menu-item-title' => $post_title,
                        'menu-item-url' => $post_url,
                        'menu-item-status' => 'publish',
                        'menu-item-position' => 1, // Always at the top
                    ));
                }
            }
        }
    }
}

// Hook into the 'post_updated' action to handle parent changes
add_action('post_updated', 'sync_service_pages_to_menus', 10, 3);

// Run on plugin activation to ensure existing pages are added to menus
register_activation_hook(__FILE__, 'ensure_existing_service_pages_in_menus');
