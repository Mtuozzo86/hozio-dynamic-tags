<?php
// Sync pages based on taxonomy assignment to menus
function sync_service_taxonomy_to_menus( $post_id ) {
    // Avoid running on autosaves, revisions, or non-page post types
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }
    if ( 'page' !== get_post_type( $post_id ) ) {
        return;
    }
    
    // Define the menus to update
    $menu_names = array( 'Main Menu', 'Main Menu Toggle', 'Services' );
    
    // Get post details
    $post_title = get_the_title( $post_id );
    $post_url   = get_permalink( $post_id );
    
    // Check if the page has the taxonomy term "service-pages-loop-item"
    $terms = get_the_terms( $post_id, 'parent_pages' );
    $has_service_term = false;
    if ( $terms && ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            if ( 'service-pages-loop-item' === $term->slug ) {
                $has_service_term = true;
                break;
            }
        }
    }
    
    // Loop through each menu to add or remove the page accordingly
    foreach ( $menu_names as $menu_name ) {
        $menu = wp_get_nav_menu_object( $menu_name );
        if ( ! $menu ) {
            continue;
        }
        $menu_items = wp_get_nav_menu_items( $menu->term_id );
        $exists = false;
        $parent_item_id = null;
        $special_item_position = null;
        
        if ( $menu_items ) {
            foreach ( $menu_items as $item ) {
                // For Main Menu and Main Menu Toggle, use "Services" as the parent
                if ( 'Services' !== $menu_name && 'Services' === $item->title ) {
                    $parent_item_id = $item->ID;
                }
                // For the Services menu, determine the position using "See All Services"
                if ( 'Services' === $menu_name && strpos( $item->title, 'See All Services' ) !== false ) {
                    $special_item_position = $item->menu_order;
                }
                // Check if the menu item already exists
                if ( $item->title === $post_title && $item->url === $post_url ) {
                    $exists = true;
                }
            }
        }
        
        if ( $has_service_term ) {
            // If the page has the term and isn't already in the menu, add it.
            if ( ! $exists ) {
                if ( 'Services' !== $menu_name ) {
                    wp_update_nav_menu_item( $menu->term_id, 0, array(
                        'menu-item-object-id' => $post_id,
                        'menu-item-object'    => 'page',
                        'menu-item-type'      => 'post_type',
                        'menu-item-title'     => $post_title,
                        'menu-item-url'       => $post_url,
                        'menu-item-status'    => 'publish',
                        'menu-item-parent-id' => $parent_item_id,
                        'menu-item-position'  => $special_item_position ? $special_item_position - 1 : 1,
                    ) );
                } else {
                    wp_update_nav_menu_item( $menu->term_id, 0, array(
                        'menu-item-object-id' => $post_id,
                        'menu-item-object'    => 'page',
                        'menu-item-type'      => 'post_type',
                        'menu-item-title'     => $post_title,
                        'menu-item-url'       => $post_url,
                        'menu-item-status'    => 'publish',
                        'menu-item-position'  => 1,
                    ) );
                }
            }
        } else {
            // If the page no longer has the taxonomy term, remove it from the menu.
            if ( $exists ) {
                foreach ( $menu_items as $item ) {
                    if ( $item->title === $post_title && $item->url === $post_url ) {
                        wp_delete_post( $item->ID, true );
                        break;
                    }
                }
            }
        }
    }
}

// Ensure all existing pages with the taxonomy term are synced to the menus
function ensure_existing_service_taxonomy_pages_in_menus() {
    $args = array(
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'tax_query'      => array(
            array(
                'taxonomy' => 'parent_pages',
                'field'    => 'slug',
                'terms'    => 'service-pages-loop-item',
            ),
        ),
    );
    $query = new WP_Query( $args );
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            sync_service_taxonomy_to_menus( get_the_ID() );
        }
        wp_reset_postdata();
    }
}

// Hook into the save_post action so the function runs whenever a page is saved
add_action( 'save_post', 'sync_service_taxonomy_to_menus' );

// Run on plugin activation to ensure existing pages with the term are added to the menus
register_activation_hook( __FILE__, 'ensure_existing_service_taxonomy_pages_in_menus' );
?>
