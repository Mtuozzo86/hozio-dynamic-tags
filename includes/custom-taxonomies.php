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
    error_log( 'Debug: Elementor query hook triggered for page_tax_query' );

    // Get the current post ID
    $current_post_id = get_the_ID();
    error_log( 'Debug: Current Post ID: ' . print_r( $current_post_id, true ) );

    // Fetch the ACF field value
    $acf_taxonomy_value = get_field( 'page_taxonomy', $current_post_id ); // Correct ACF field name
    error_log( 'Debug: ACF Field "page_taxonomy" Value (raw): ' . print_r( $acf_taxonomy_value, true ) );

    // Ensure the ACF field value exists
    if ( ! empty( $acf_taxonomy_value ) ) {
        // Split the field value into an array if it contains multiple slugs separated by commas
        $taxonomy_terms = array_map( 'trim', explode( ',', $acf_taxonomy_value ) );
        error_log( 'Debug: ACF Field "page_taxonomy" Value (as array): ' . print_r( $taxonomy_terms, true ) );

        $tax_query = [
            [
                'taxonomy' => 'parent_pages',
                'field'    => 'slug', // Adjust 'slug' if needed (e.g., to 'name' or 'term_id')
                'terms'    => $taxonomy_terms,
                'operator' => 'IN', // Ensures that any of the slugs match
            ],
        ];
        error_log( 'Debug: Tax Query Array: ' . print_r( $tax_query, true ) );

        $query->set( 'tax_query', $tax_query );
        error_log( 'Debug: Query modified with tax_query' );
    } else {
        $query->set( 'post__in', [0] );
        error_log( 'Debug: No ACF Field "page_taxonomy" Value found, query set to return no results' );
    }

    error_log( 'Debug: Final Query Vars: ' . print_r( $query->query_vars, true ) );
});
