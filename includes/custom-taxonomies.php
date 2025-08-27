<?php
// ========================
// 1) PAGE TAXONOMY
// ========================
function create_parent_pages_taxonomy() {
    $args = array(
        'hierarchical'      => true,
        'labels'            => array(
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
        'show_admin_column' => false, // <-- disable WP's auto column (we add our own below)
        'query_var'         => true,
        'rewrite'           => array('slug' => 'parent-pages'),
    );

    register_taxonomy('parent_pages', 'page', $args);
}
add_action('init', 'create_parent_pages_taxonomy');

// ========================
// 2) TOWN TAXONOMY
// ========================
function create_town_taxonomies_taxonomy() {
    $args = array(
        'hierarchical'      => true,
        'labels'            => array(
            'name'              => 'Town Taxonomies',
            'singular_name'     => 'Town Taxonomy',
            'search_items'      => 'Search Towns',
            'all_items'         => 'All Town Taxonomies',
            'parent_item'       => 'Parent Town',
            'parent_item_colon' => 'Parent Town:',
            'edit_item'         => 'Edit Town Taxonomy',
            'update_item'       => 'Update Town Taxonomy',
            'add_new_item'      => 'Add New Town',
            'new_item_name'     => 'New Town Name',
            'menu_name'         => 'Town Taxonomies',
        ),
        'show_ui'           => true,
        'show_admin_column' => false, // <-- disable WP's auto column
        'query_var'         => true,
        'rewrite'           => array('slug' => 'town'),
    );

    register_taxonomy('town_taxonomies', array('page'), $args);
}
add_action('init', 'create_town_taxonomies_taxonomy');

// ========================
// 3) SEARCH BARS ON PAGES LIST
// ========================
function add_custom_taxonomy_search_bar() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'edit-page') {
        ?>
        <form method="get" id="custom-taxonomy-search-form" action="">
            <input type="hidden" name="post_type" value="page">
            <input type="text" name="search_parent_pages" placeholder="Search Parent Pages (Taxonomy)">
            <input type="submit" value="Search">
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('custom-taxonomy-search-form');
            const target = document.querySelector('.tablenav.top .actions.bulkactions');
            if (target && form) target.appendChild(form);
        });
        </script>
        <style>
            #custom-taxonomy-search-form{display:inline-block;margin-left:10px}
            #custom-taxonomy-search-form input[type="text"]{margin-right:5px}
        </style>
        <?php
    }
}
add_action('admin_footer', 'add_custom_taxonomy_search_bar');

function add_custom_town_taxonomy_search_bar() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'edit-page') {
        ?>
        <form method="get" id="custom-town-taxonomy-search-form" action="">
            <input type="hidden" name="post_type" value="page">
            <input type="text" name="search_town_taxonomies" placeholder="Search Town Taxonomies">
            <input type="submit" value="Search">
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('custom-town-taxonomy-search-form');
            const target = document.querySelector('.tablenav.top .actions.bulkactions');
            if (target && form) target.appendChild(form);
        });
        </script>
        <style>
            #custom-town-taxonomy-search-form{display:inline-block;margin-left:10px}
            #custom-town-taxonomy-search-form input[type="text"]{margin-right:5px;width:220px}
        </style>
        <?php
    }
}
add_action('admin_footer', 'add_custom_town_taxonomy_search_bar');

// ========================
// 4) APPLY PARTIAL TERM FILTERS
// ========================
function filter_pages_by_partial_taxonomy($query) {
    if (!is_admin() || !$query->is_main_query()) return;

    global $wpdb;

    $apply = function($param_key, $taxonomy) use ($wpdb, $query) {
        if (empty($_GET[$param_key])) return;

        $searched = sanitize_text_field(wp_unslash($_GET[$param_key]));
        $matching = $wpdb->get_col($wpdb->prepare(
            "SELECT t.term_id
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE tt.taxonomy = %s AND t.name LIKE %s",
            $taxonomy,
            '%' . $wpdb->esc_like($searched) . '%'
        ));

        if (!empty($matching)) {
            $tax_query = (array) $query->get('tax_query');
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $matching,
                'operator' => 'IN',
            );
            $query->set('tax_query', $tax_query);
        } else {
            $query->set('post__in', array(0));
        }
    };

    if (isset($_GET['search_parent_pages']))   $apply('search_parent_pages', 'parent_pages');
    if (isset($_GET['search_town_taxonomies'])) $apply('search_town_taxonomies', 'town_taxonomies');
}
add_action('pre_get_posts', 'filter_pages_by_partial_taxonomy');

// ========================
// 5) MANUAL COLUMNS ON PAGES LIST (single set, no dupes)
// ========================
function pages_add_taxonomy_columns($columns){
    $out = array();
    foreach($columns as $key => $label){
        $out[$key] = $label;
        if ($key === 'title'){
            $out['parent_pages']    = 'Page Taxonomies';
            $out['town_taxonomies'] = 'Town Taxonomies';
        }
    }
    return $out;
}
add_filter('manage_pages_columns','pages_add_taxonomy_columns');

function pages_populate_taxonomy_columns($column,$post_id){
    if ($column === 'parent_pages' || $column === 'town_taxonomies'){
        $terms = wp_get_post_terms($post_id, $column, array('fields'=>'names'));
        echo (!is_wp_error($terms) && !empty($terms)) ? esc_html(implode(', ', $terms)) : '—';
    }
}
add_action('manage_pages_custom_column','pages_populate_taxonomy_columns',10,2);

