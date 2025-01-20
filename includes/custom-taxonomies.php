<?php

// Keep the existing taxonomy creation code
function create_parent_pages_taxonomy() {
    $args = array(
        'hierarchical' => true,
        'labels' => array(
            'name'              => 'Page Taxonomies',
            'singular_name'     => 'Page Taxonomy',
            'search_items'      => 'Search Taxonomies',
            'all_items'         => 'All Page Taxonomies',
            'parent_item'       => 'Parent Taxonomy',
            'parent_item_colon' => 'Parent Taxonomy:',
            'edit_item'         => 'Edit Page Taxonomy',
            'update_item'       => 'Update Page Taxonomy',
            'add_new_item'      => 'Add New Page Taxonomy',
            'new_item_name'     => 'New Page Taxonomy Name',
            'menu_name'         => 'Page Taxonomies',
        ),
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'parent-pages'),
    );

    register_taxonomy('parent_pages', 'page', $args);
}
add_action('init', 'create_parent_pages_taxonomy');

// Add custom taxonomy search bar to Pages admin
function add_custom_taxonomy_search_bar() {
    $screen = get_current_screen();

    if ($screen->id === 'edit-page') {
        ?>
        <form method="get" id="custom-taxonomy-search-form" action="">
            <input type="hidden" name="post_type" value="page">
            <input type="text" name="search_parent_pages" placeholder="Search Parent Pages (Taxonomy)">
            <input type="submit" value="Search">
        </form>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchForm = document.getElementById('custom-taxonomy-search-form');
                const filterContainer = document.querySelector('.tablenav.top .actions.bulkactions');
                if (filterContainer) {
                    filterContainer.appendChild(searchForm);
                }
            });
        </script>
        <style>
            #custom-taxonomy-search-form {
                display: inline-block;
                margin-left: 10px;
            }
            #custom-taxonomy-search-form input[type="text"] {
                margin-right: 5px;
            }
        </style>
        <?php
    }
}
add_action('admin_footer', 'add_custom_taxonomy_search_bar');

// Filter query to show only pages with the searched taxonomy term (partial matches allowed)
function filter_pages_by_partial_taxonomy($query) {
    if (is_admin() && $query->is_main_query() && isset($_GET['search_parent_pages']) && !empty($_GET['search_parent_pages'])) {
        global $wpdb;

        $searched_term = sanitize_text_field($_GET['search_parent_pages']);
        $taxonomy = 'parent_pages'; // Your taxonomy name

        // Get taxonomy term IDs that partially match the search term
        $matching_terms = $wpdb->get_col($wpdb->prepare("
            SELECT term_id 
            FROM {$wpdb->terms} 
            WHERE name LIKE %s
        ", '%' . $wpdb->esc_like($searched_term) . '%'));

        if (!empty($matching_terms)) {
            // Modify the query to filter by matching term IDs
            $query->set('tax_query', array(
                array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $matching_terms,
                    'operator' => 'IN',
                ),
            ));
        } else {
            // If no matching terms are found, show no results
            $query->set('post__in', array(0));
        }
    }
}
add_action('pre_get_posts', 'filter_pages_by_partial_taxonomy');

add_action( 'elementor/query/page_tax_query', function( $query ) {
    // Get allowed post types from options
    $allowed_post_types = get_option( 'hozio_selected_post_types', [] );

    if ( ! empty( $allowed_post_types ) ) {
        $query->set( 'post_type', $allowed_post_types );
        error_log( 'Debug: Allowed Post Types: ' . print_r( $allowed_post_types, true ) );
    } else {
        error_log( 'Debug: No allowed post types found in options.' );
    }

    // Get the current post ID
    $current_post_id = get_the_ID();
    if ( ! $current_post_id ) {
        error_log( 'Debug: No current post ID found.' );
        return;
    }

    // Fetch the ACF field value
    $acf_taxonomy_value = get_field( 'acf_taxonomy', $current_post_id );

    if ( empty( $acf_taxonomy_value ) ) {
        error_log( 'Debug: No value retrieved from ACF field.' );
        return;
    }

    // Process the ACF value (split, trim, and sanitize)
    $taxonomy_terms = array_map( function( $term ) {
        // Replace unwanted characters and sanitize
        $term = trim( $term ); // Trim leading/trailing spaces
        $term = str_replace( '.', '-', $term ); // Replace periods with dashes
        return sanitize_title( $term ); // Convert to slug format
    }, explode( ',', $acf_taxonomy_value ) );

    // Debug: Log the sanitized taxonomy terms
    error_log( 'Debug: Tax Query Array (sanitized): ' . print_r( $taxonomy_terms, true ) );

    // Ensure terms exist before adding to query
    if ( ! empty( $taxonomy_terms ) ) {
        $query->set( 'tax_query', [
            [
                'taxonomy' => 'acf-taxonomy', // Replace with your taxonomy name
                'field'    => 'slug',
                'terms'    => $taxonomy_terms,
                'operator' => 'IN',
            ],
        ]);
        error_log( 'Debug: Tax Query added to query_vars.' );
    } else {
        error_log( 'Debug: No valid taxonomy terms found after sanitization.' );
    }
});



// Add a custom column to display taxonomies
function add_taxonomy_column( $columns ) {
    $columns['acf_taxonomy'] = 'ACF Taxonomy'; // Add a new column with a label
    return $columns;
}
add_filter( 'manage_product_posts_columns', 'add_taxonomy_column' ); // Replace 'product' with your post type

// Populate the custom taxonomy column
function populate_taxonomy_column( $column, $post_id ) {
    if ( 'acf_taxonomy' === $column ) {
        // Fetch terms associated with the taxonomy
        $terms = get_the_terms( $post_id, 'acf-taxonomy' ); // Replace 'acf-taxonomy' with your taxonomy name

        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            // Display taxonomy terms as a comma-separated list
            $term_list = join( ', ', wp_list_pluck( $terms, 'name' ) );
            echo esc_html( $term_list );
        } else {
            echo '—'; // Display a dash if no terms are assigned
        }
    }
}
add_action( 'manage_product_posts_custom_column', 'populate_taxonomy_column', 10, 2 ); // Replace 'product' with your post type


