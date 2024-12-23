<?php

// Keep the existing taxonomy creation code
function create_parent_pages_taxonomy() {
    $args = array(
        'hierarchical' => true,
        'labels' => array(
            'name'              => 'Parent Pages (Loop Items)',
            'singular_name'     => 'Parent Page',
            'search_items'      => 'Search Parent Pages',
            'all_items'         => 'All Parent Pages',
            'parent_item'       => 'Parent Page',
            'parent_item_colon' => 'Parent Page:',
            'edit_item'         => 'Edit Parent Page',
            'update_item'       => 'Update Parent Page',
            'add_new_item'      => 'Add New Parent Page',
            'new_item_name'     => 'New Parent Page Name',
            'menu_name'         => 'Parent Pages',
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
