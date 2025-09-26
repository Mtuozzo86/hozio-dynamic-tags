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








// ========================
// 6) CONNECT TOWN TAXONOMIES - TAXONOMY SELECTION APPROACH
// ========================

// Add custom button to the pages admin screen
function add_connect_town_taxonomies_button() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'edit-page') {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.tablenav.top .alignleft.actions.bulkactions').first().after(
                '<a href="<?php echo admin_url('admin.php?page=connect-town-taxonomies'); ?>" class="button" style="margin-left: 10px;">Connect Town Taxonomies</a>'
            );
        });
        </script>
        <?php
    }
}
add_action('admin_footer', 'add_connect_town_taxonomies_button');

// Register the admin page
function register_connect_town_taxonomies_page() {
    add_submenu_page(
        null, // No menu item - accessed via button only
        'Connect Town Taxonomies',
        'Connect Town Taxonomies',
        'edit_pages',
        'connect-town-taxonomies',
        'render_connect_town_taxonomies_page'
    );
}
add_action('admin_menu', 'register_connect_town_taxonomies_page');

// Render the taxonomy selection page
function render_connect_town_taxonomies_page() {
    // Handle form submission
    if (isset($_POST['submit_connect_taxonomies']) && check_admin_referer('connect_town_taxonomies_action')) {
        process_taxonomy_based_connection();
        return;
    }

    // Get all page taxonomies
    $all_taxonomies = get_terms(array(
        'taxonomy' => 'parent_pages',
        'hide_empty' => false,
    ));

    ?>
    <div class="wrap">
        <h1>Connect Town Taxonomies</h1>
        
        <div class="notice notice-info">
            <p><strong>How this works:</strong> Select the Page Taxonomies below. The system will find all pages with those taxonomies and automatically create Town Taxonomies based on each page's slug. Parent pages will be skipped automatically.</p>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('connect_town_taxonomies_action'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Select Page Taxonomies</th>
                    <td>
                        <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; background: #fff;">
                            <label style="display: block; margin-bottom: 15px; font-weight: bold;">
                                <input type="checkbox" id="select-all-taxonomies" style="margin-right: 8px;">
                                Select All
                            </label>
                            <hr style="margin: 10px 0;">
                            
                            <?php if (empty($all_taxonomies)): ?>
                                <p>No Page Taxonomies found.</p>
                            <?php else: ?>
                                <?php foreach ($all_taxonomies as $term): ?>
                                    <label style="display: block; padding: 10px; margin: 5px 0; background: #f6f7f7; border-radius: 4px;">
                                        <input type="checkbox" name="selected_page_taxonomies[]" value="<?php echo esc_attr($term->term_id); ?>" class="taxonomy-checkbox" style="margin-right: 8px;">
                                        <strong><?php echo esc_html($term->name); ?></strong> <span style="color: #666;">(<?php echo $term->count; ?> pages)</span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <p class="description">Pages with these taxonomies will have Town Taxonomies created based on their slugs.</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit_connect_taxonomies" class="button button-primary" value="Connect Town Taxonomies">
                <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button">Cancel</a>
            </p>
        </form>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#select-all-taxonomies').on('change', function() {
            $('.taxonomy-checkbox').prop('checked', this.checked);
        });
    });
    </script>

    <style>
        .taxonomy-checkbox:checked + strong { color: #2271b1; }
    </style>
    <?php
}

// Process the connection based on selected taxonomies
function process_taxonomy_based_connection() {
    if (empty($_POST['selected_page_taxonomies']) || !is_array($_POST['selected_page_taxonomies'])) {
        ?>
        <div class="wrap">
            <h1>Connect Town Taxonomies</h1>
            <div class="notice notice-error">
                <p><strong>Error:</strong> Please select at least one Page Taxonomy.</p>
            </div>
            <p><a href="<?php echo admin_url('admin.php?page=connect-town-taxonomies'); ?>" class="button">Go Back</a></p>
        </div>
        <?php
        return;
    }

    $selected_taxonomy_ids = array_map('intval', $_POST['selected_page_taxonomies']);
    $processed = 0;
    $skipped = 0;
    $created_terms = array();

    // Get all pages that have any of the selected taxonomies
    $args = array(
        'post_type' => 'page',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'parent_pages',
                'field' => 'term_id',
                'terms' => $selected_taxonomy_ids,
                'operator' => 'IN'
            )
        )
    );

    $pages = get_posts($args);

    foreach ($pages as $post) {
        // Skip if this is a parent page (has no parent itself, or has child pages)
        $is_parent_page = ($post->post_parent == 0);
        $children = get_children(array(
            'post_parent' => $post->ID,
            'post_type'   => 'page',
            'numberposts' => 1
        ));
        $has_children = !empty($children);
        
        if ($is_parent_page || $has_children) {
            $skipped++;
            continue;
        }

        $slug = $post->post_name;

        // Check if town taxonomy term already exists with this slug
        $term = term_exists($slug, 'town_taxonomies');
        
        if (!$term) {
            // Create new town taxonomy term with the page slug
            $term = wp_insert_term(
                $slug,
                'town_taxonomies',
                array('slug' => $slug)
            );
            
            if (!is_wp_error($term)) {
                $created_terms[] = $slug;
            }
        }

        // If term creation/retrieval was successful, assign it to the page
        if (!is_wp_error($term)) {
            $term_id = is_array($term) ? $term['term_id'] : $term;
            wp_set_post_terms($post->ID, array($term_id), 'town_taxonomies', false);
            $processed++;
        }
    }

    // Display results
    ?>
    <div class="wrap">
        <h1>Connection Complete</h1>
        
        <div class="notice notice-success">
            <p><strong>Success!</strong> Town taxonomies have been connected.</p>
        </div>

        <table class="widefat" style="margin-top: 20px; max-width: 800px;">
            <thead>
                <tr>
                    <th>Result</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Pages Processed</strong></td>
                    <td><?php echo $processed; ?></td>
                </tr>
                <tr>
                    <td><strong>Pages Skipped</strong> (parent pages)</td>
                    <td><?php echo $skipped; ?></td>
                </tr>
                <tr>
                    <td><strong>New Town Taxonomies Created</strong></td>
                    <td><?php echo count($created_terms); ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($created_terms)): ?>
            <div style="margin-top: 20px; max-width: 800px;">
                <h3>New Town Taxonomies Created:</h3>
                <div style="background: #f6f7f7; padding: 15px; border-radius: 4px;">
                    <?php echo implode(', ', array_map('esc_html', $created_terms)); ?>
                </div>
            </div>
        <?php endif; ?>

        <p style="margin-top: 20px;">
            <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="button button-primary">Back to Pages</a>
            <a href="<?php echo admin_url('admin.php?page=connect-town-taxonomies'); ?>" class="button">Run Again</a>
        </p>
    </div>
    <?php
}
