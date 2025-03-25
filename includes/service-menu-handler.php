<?php
/**
 * Sync pages based on the "service-pages-loop-item" taxonomy assignment to menus.
 * Only pages that have (or once had) the "service-pages-loop-item" term in the "parent_pages"
 * taxonomy will be auto-added or removed from the specified menus.
 */

function sync_service_taxonomy_to_menus( $post_id ) {
    // Avoid running on autosaves, revisions, or non-page post types.
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }
    if ( 'page' !== get_post_type( $post_id ) ) {
        return;
    }

    // Define the menus to update.
    $menu_names = array( 'Main Menu', 'Main Menu Toggle', 'Services' );
    
    // Get post details.
    $post_title = get_the_title( $post_id );
    $post_url   = get_permalink( $post_id );
    
    // Check if the page has the "service-pages-loop-item" term (in taxonomy "parent_pages").
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
    
    // Loop through each menu to add or remove the page accordingly.
    foreach ( $menu_names as $menu_name ) {
        $menu = wp_get_nav_menu_object( $menu_name );
        if ( ! $menu ) {
            continue;
        }
        $menu_items = wp_get_nav_menu_items( $menu->term_id );
        $found_item = false;
        $auto_item   = null; // Only auto‑added items (marked with our meta flag).
        $parent_item_id = null;
        $special_item_position = null;
        
        if ( $menu_items ) {
            foreach ( $menu_items as $item ) {
                // For Main Menu and Main Menu Toggle, use the item titled "Services" as the parent.
                if ( 'Services' !== $menu_name && 'Services' === $item->title ) {
                    $parent_item_id = $item->ID;
                }
                // For the Services menu, determine the position using an item titled "See All Services".
                if ( 'Services' === $menu_name && strpos( $item->title, 'See All Services' ) !== false ) {
                    $special_item_position = $item->menu_order;
                }
                // Check if an item matching this page already exists.
                if ( $item->title === $post_title && $item->url === $post_url ) {
                    $found_item = true;
                    // Only consider items that we auto‑added.
                    if ( get_post_meta( $item->ID, '_auto_service_sync', true ) === '1' ) {
                        $auto_item = $item;
                    }
                }
            }
        }
        
        if ( $has_service_term ) {
            // If the page currently has the term and the menu item doesn't exist, add it.
            if ( ! $found_item ) {
                if ( 'Services' !== $menu_name ) {
                    $new_item_id = wp_update_nav_menu_item( $menu->term_id, 0, array(
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
                    $new_item_id = wp_update_nav_menu_item( $menu->term_id, 0, array(
                        'menu-item-object-id' => $post_id,
                        'menu-item-object'    => 'page',
                        'menu-item-type'      => 'post_type',
                        'menu-item-title'     => $post_title,
                        'menu-item-url'       => $post_url,
                        'menu-item-status'    => 'publish',
                        'menu-item-position'  => 1,
                    ) );
                }
                // Mark this menu item as auto‑added.
                if ( $new_item_id && ! is_wp_error( $new_item_id ) ) {
                    update_post_meta( $new_item_id, '_auto_service_sync', '1' );
                }
            }
        } else {
            // If the page does NOT have the term, then only remove it if it was auto‑added.
            if ( $found_item && $auto_item ) {
                wp_delete_post( $auto_item->ID, true );
            }
        }
    }
}

/**
 * When taxonomy terms are set/changed on a page, update its menu sync.
 *
 * This hook fires when terms are updated for a given taxonomy.
 */
function sync_service_taxonomy_on_term_update( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
    if ( 'parent_pages' !== $taxonomy ) {
        return;
    }
    sync_service_taxonomy_to_menus( $object_id );
}
add_action( 'set_object_terms', 'sync_service_taxonomy_on_term_update', 10, 6 );

/**
 * Also hook into wp_after_insert_post to catch updates after the post (and its taxonomy data) are saved.
 */
add_action( 'wp_after_insert_post', function( $post_id, $post, $update ) {
    if ( 'page' === get_post_type( $post_id ) ) {
        sync_service_taxonomy_to_menus( $post_id );
    }
}, 10, 3 );

/**
 * On plugin activation, sync all existing pages that have the service-pages-loop-item term.
 */
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
register_activation_hook( __FILE__, 'ensure_existing_service_taxonomy_pages_in_menus' );
?>
