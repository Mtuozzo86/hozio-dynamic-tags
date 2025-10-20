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

// Add inline styles for the Connect Town Taxonomies page
function hozio_town_taxonomies_admin_styles() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'connect-town-taxonomies') === false) {
        return;
    }
    ?>
    <style>
        :root {
            --hozio-blue: #00A0E3;
            --hozio-blue-dark: #0081B8;
            --hozio-green: #8DC63F;
            --hozio-green-dark: #6FA92E;
            --hozio-orange: #F7941D;
            --hozio-orange-dark: #E67E00;
            --hozio-gray: #6D6E71;
        }
        
        .hozio-taxonomies-wrapper {
            background: #f9fafb;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .hozio-taxonomies-header {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 50%, var(--hozio-orange) 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .hozio-taxonomies-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .hozio-taxonomies-header h1 {
            color: white !important;
            font-size: 32px;
            margin: 0 0 10px !important;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            text-shadow: none;
        }
        
        .hozio-taxonomies-header h1 .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
        }
        
        .hozio-taxonomies-subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            margin: 0;
        }
        
        .hozio-taxonomies-content {
            padding: 0 40px 40px;
        }
        
        .hozio-taxonomies-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            border-left: 4px solid var(--hozio-blue);
        }
        
        .hozio-taxonomies-card.info-card {
            border-left-color: var(--hozio-orange);
        }
        
        .hozio-taxonomies-card.selection-card {
            border-left-color: var(--hozio-green);
        }
        
        .hozio-taxonomies-card.results-card {
            border-left-color: var(--hozio-blue);
        }
        
        .hozio-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .hozio-card-header h2 {
            margin: 0 !important;
            font-size: 20px !important;
            color: var(--hozio-gray);
            font-weight: 600;
        }
        
        .hozio-card-header .dashicons {
            color: var(--hozio-blue);
            font-size: 24px;
            width: 24px;
            height: 24px;
        }
        
        .info-card .hozio-card-header .dashicons {
            color: var(--hozio-orange);
        }
        
        .selection-card .hozio-card-header .dashicons {
            color: var(--hozio-green);
        }
        
        .results-card .hozio-card-header .dashicons {
            color: var(--hozio-blue);
        }
        
        .hozio-info-notice {
            background: linear-gradient(135deg, rgba(0, 160, 227, 0.1) 0%, rgba(0, 160, 227, 0.05) 100%);
            border: 1px solid rgba(0, 160, 227, 0.2);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .hozio-info-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--hozio-blue-dark);
            margin-bottom: 8px;
        }
        
        .hozio-info-text {
            color: var(--hozio-blue-dark);
            font-size: 14px;
            line-height: 1.5;
        }
        
        .hozio-taxonomies-selection {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        

        
        .hozio-taxonomy-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            margin: 8px 0;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .hozio-taxonomy-item:hover {
            background: #f3f4f6;
            border-color: var(--hozio-green);
            transform: translateX(4px);
        }
        
        .hozio-taxonomy-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--hozio-green);
        }
        
        .hozio-taxonomy-info {
            flex: 1;
        }
        
        .hozio-taxonomy-name {
            font-weight: 600;
            color: var(--hozio-gray);
            margin-bottom: 4px;
        }
        
        .hozio-taxonomy-count {
            font-size: 13px;
            color: #6b7280;
        }
        
        .hozio-taxonomy-item:has(input:checked) {
            background: linear-gradient(135deg, rgba(141, 198, 63, 0.1) 0%, rgba(141, 198, 63, 0.05) 100%);
            border-color: var(--hozio-green);
        }
        
        .hozio-taxonomy-item:has(input:checked) .hozio-taxonomy-name {
            color: var(--hozio-green-dark);
        }
        
        .hozio-button-group {
            display: flex;
            gap: 16px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        
        .hozio-btn-primary {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%) !important;
            border: none !important;
            color: white !important;
            padding: 12px 32px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            text-shadow: none !important;
            box-shadow: 0 4px 6px rgba(0, 160, 227, 0.3) !important;
            height: auto !important;
            line-height: normal !important;
            text-decoration: none !important;
        }
        
        .hozio-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 160, 227, 0.4) !important;
        }
        
        .hozio-btn-secondary {
            background: white !important;
            border: 2px solid #e5e7eb !important;
            color: var(--hozio-gray) !important;
            padding: 10px 24px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            text-decoration: none !important;
            height: auto !important;
            line-height: normal !important;
        }
        
        .hozio-btn-secondary:hover {
            border-color: var(--hozio-blue);
            color: var(--hozio-blue) !important;
            transform: translateY(-1px);
        }
        
        .hozio-results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .hozio-results-table th {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
            color: white;
            padding: 16px;
            text-align: left;
            font-weight: 600;
        }
        
        .hozio-results-table td {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .hozio-results-table tr:last-child td {
            border-bottom: none;
        }
        
        .hozio-results-table tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .hozio-created-terms {
            background: linear-gradient(135deg, rgba(141, 198, 63, 0.1) 0%, rgba(141, 198, 63, 0.05) 100%);
            border: 1px solid rgba(141, 198, 63, 0.2);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .hozio-created-terms h3 {
            color: var(--hozio-green-dark);
            margin: 0 0 15px 0 !important;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hozio-terms-list {
            background: white;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
            color: var(--hozio-green-dark);
            line-height: 1.6;
        }
        
        .hozio-success-notice {
            background: linear-gradient(135deg, rgba(141, 198, 63, 0.1) 0%, rgba(141, 198, 63, 0.05) 100%);
            border: 1px solid rgba(141, 198, 63, 0.2);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .hozio-success-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--hozio-green-dark);
            margin-bottom: 8px;
        }
        
        .hozio-error-notice {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .hozio-error-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #dc2626;
            margin-bottom: 8px;
        }
        
        .hozio-error-text {
            color: #dc2626;
            font-size: 14px;
        }
        
        @media (max-width: 782px) {
            .hozio-taxonomies-wrapper {
                margin: 20px 0;
            }
            
            .hozio-taxonomies-header {
                padding: 30px 20px;
            }
            
            .hozio-taxonomies-header h1 {
                font-size: 24px;
            }
            
            .hozio-taxonomies-content {
                padding: 0 20px 20px;
            }
            
            .hozio-taxonomies-card {
                padding: 20px;
                margin: 20px 0;
            }
            
            .hozio-button-group {
                flex-direction: column;
            }
            
            .hozio-btn-primary,
            .hozio-btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Form submission with loading state
        $('form').on('submit', function() {
            const $btn = $('.hozio-btn-primary');
            const originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span> Processing...');
            $btn.prop('disabled', true);
            
            // Re-enable after a delay (in case of redirect)
            setTimeout(function() {
                $btn.html(originalText);
                $btn.prop('disabled', false);
            }, 5000);
        });
    });
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    </script>
    <?php
}
add_action('admin_head', 'hozio_town_taxonomies_admin_styles');

// Render the taxonomy selection page with Hozio styling
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
    <div class="hozio-taxonomies-wrapper">
        <div class="hozio-taxonomies-header">
            <div class="hozio-header-content">
                <h1>
                    <span class="dashicons dashicons-networking"></span>
                    Connect Town Taxonomies
                </h1>
                <p class="hozio-taxonomies-subtitle">Automatically create and assign town taxonomies to your pages</p>
            </div>
        </div>

        <div class="hozio-taxonomies-content">
            <!-- Info Card -->
            <div class="hozio-taxonomies-card info-card">
                <div class="hozio-card-header">
                    <span class="dashicons dashicons-info"></span>
                    <h2>How This Works</h2>
                </div>
                
                <div class="hozio-info-notice">
                    <div class="hozio-info-header">
                        <span class="dashicons dashicons-lightbulb"></span>
                        Process Overview
                    </div>
                    <div class="hozio-info-text">
                        Select the Page Taxonomies below and the system will find all pages with those taxonomies, then automatically create Town Taxonomies based on each page's slug. Parent pages will be automatically skipped to ensure optimal organization.
                    </div>
                </div>
            </div>

            <!-- Selection Form -->
            <form method="post" action="">
                <?php wp_nonce_field('connect_town_taxonomies_action'); ?>
                
                <div class="hozio-taxonomies-card selection-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-category"></span>
                        <h2>Select Page Taxonomies</h2>
                    </div>
                    
                    <div class="hozio-taxonomies-selection">
                        <?php if (empty($all_taxonomies)): ?>
                            <div style="text-align: center; padding: 40px; color: #6b7280;">
                                <span class="dashicons dashicons-warning" style="font-size: 48px; margin-bottom: 16px; display: block;"></span>
                                <p style="margin: 0; font-size: 16px;">No Page Taxonomies found.</p>
                                <p style="margin: 8px 0 0; font-size: 14px;">Create some page taxonomies first to use this feature.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($all_taxonomies as $term): ?>
                                <label class="hozio-taxonomy-item">
                                    <input type="checkbox" name="selected_page_taxonomies[]" value="<?php echo esc_attr($term->term_id); ?>" class="taxonomy-checkbox">
                                    <div class="hozio-taxonomy-info">
                                        <div class="hozio-taxonomy-name"><?php echo esc_html($term->name); ?></div>
                                        <div class="hozio-taxonomy-count"><?php echo $term->count; ?> pages</div>
                                    </div>
                                    <span class="dashicons dashicons-arrow-right-alt" style="color: var(--hozio-green); opacity: 0.6;"></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hozio-button-group">
                        <button type="submit" name="submit_connect_taxonomies" class="hozio-btn-primary">
                            <span class="dashicons dashicons-networking"></span>
                            Connect Town Taxonomies
                        </button>
                        <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="hozio-btn-secondary">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            Back to Pages
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// Process the connection based on selected taxonomies with Hozio styling
function process_taxonomy_based_connection() {
    if (empty($_POST['selected_page_taxonomies']) || !is_array($_POST['selected_page_taxonomies'])) {
        ?>
        <div class="hozio-taxonomies-wrapper">
            <div class="hozio-taxonomies-header">
                <div class="hozio-header-content">
                    <h1>
                        <span class="dashicons dashicons-warning"></span>
                        Connection Error
                    </h1>
                    <p class="hozio-taxonomies-subtitle">Please review the issue below and try again</p>
                </div>
            </div>

            <div class="hozio-taxonomies-content">
                <div class="hozio-taxonomies-card">
                    <div class="hozio-error-notice">
                        <div class="hozio-error-header">
                            <span class="dashicons dashicons-dismiss"></span>
                            Selection Required
                        </div>
                        <div class="hozio-error-text">
                            Please select at least one Page Taxonomy to proceed with the connection process.
                        </div>
                    </div>
                    
                    <div class="hozio-button-group">
                        <a href="<?php echo admin_url('admin.php?page=connect-town-taxonomies'); ?>" class="hozio-btn-primary">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            Go Back & Select
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return;
    }

    $selected_taxonomy_ids = array_map('intval', $_POST['selected_page_taxonomies']);
    $processed = 0;
    $skipped = 0;
    $created_terms = array();
    $taxonomy_breakdown = array(); // Track which taxonomies processed which pages
    
    // Get selected taxonomy names for display
    $selected_taxonomies = get_terms(array(
        'taxonomy' => 'parent_pages',
        'include' => $selected_taxonomy_ids,
        'hide_empty' => false,
    ));
    
    // Initialize breakdown array
    foreach ($selected_taxonomies as $taxonomy) {
        $taxonomy_breakdown[$taxonomy->term_id] = array(
            'name' => $taxonomy->name,
            'count' => 0,
            'created_terms' => array()
        );
    }

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

        // Get page taxonomies for this specific page
        $page_taxonomies = wp_get_post_terms($post->ID, 'parent_pages', array('fields' => 'ids'));
        
        $slug = $post->post_name;
        $term_created = false;

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
                $term_created = true;
            }
        }

        // If term creation/retrieval was successful, assign it to the page
        if (!is_wp_error($term)) {
            $term_id = is_array($term) ? $term['term_id'] : $term;
            wp_set_post_terms($post->ID, array($term_id), 'town_taxonomies', false);
            $processed++;
            
            // Track which page taxonomies this applies to
            foreach ($page_taxonomies as $page_tax_id) {
                if (in_array($page_tax_id, $selected_taxonomy_ids)) {
                    $taxonomy_breakdown[$page_tax_id]['count']++;
                    if ($term_created && !in_array($slug, $taxonomy_breakdown[$page_tax_id]['created_terms'])) {
                        $taxonomy_breakdown[$page_tax_id]['created_terms'][] = $slug;
                    }
                }
            }
        }
    }

    // Display results with Hozio styling
    ?>
    <div class="hozio-taxonomies-wrapper">
        <div class="hozio-taxonomies-header">
            <div class="hozio-header-content">
                <h1>
                    <span class="dashicons dashicons-yes"></span>
                    Connection Complete
                </h1>
                <p class="hozio-taxonomies-subtitle">Town taxonomies have been successfully connected to your pages</p>
            </div>
        </div>

        <div class="hozio-taxonomies-content">
            <div class="hozio-taxonomies-card results-card">
                <div class="hozio-card-header">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <h2>Processing Results</h2>
                </div>
                
                <div class="hozio-success-notice">
                    <div class="hozio-success-header">
                        <span class="dashicons dashicons-yes-alt"></span>
                        Operation Successful
                    </div>
                    <div style="color: var(--hozio-green-dark); font-size: 14px;">
                        The town taxonomy connection process has been completed successfully.
                    </div>
                </div>

                <table class="hozio-results-table">
                    <thead>
                        <tr>
                            <th><span class="dashicons dashicons-info" style="margin-right: 8px;"></span>Result Type</th>
                            <th><span class="dashicons dashicons-chart-pie" style="margin-right: 8px;"></span>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Pages Processed</strong></td>
                            <td><span style="color: var(--hozio-blue); font-weight: 600;"><?php echo $processed; ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Pages Skipped</strong> (parent pages)</td>
                            <td><span style="color: var(--hozio-orange); font-weight: 600;"><?php echo $skipped; ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>New Town Taxonomies Created</strong></td>
                            <td><span style="color: var(--hozio-green); font-weight: 600;"><?php echo count($created_terms); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Breakdown by Page Taxonomy -->
            <div class="hozio-taxonomies-card info-card">
                <div class="hozio-card-header">
                    <span class="dashicons dashicons-category"></span>
                    <h2>Breakdown by Page Taxonomy</h2>
                </div>
                
                <table class="hozio-results-table">
                    <thead>
                        <tr>
                            <th><span class="dashicons dashicons-tag" style="margin-right: 8px;"></span>Page Taxonomy</th>
                            <th><span class="dashicons dashicons-networking" style="margin-right: 8px;"></span>Town Taxonomies Connected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($taxonomy_breakdown as $breakdown): ?>
                            <tr>
                                <td><strong><?php echo esc_html($breakdown['name']); ?></strong></td>
                                <td><span style="color: var(--hozio-green); font-weight: 600;"><?php echo $breakdown['count']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($created_terms)): ?>
                <!-- Created Terms List -->
                <div class="hozio-taxonomies-card selection-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-tag"></span>
                        <h2>New Town Taxonomies Created</h2>
                    </div>
                    
                    <div class="hozio-created-terms">
                        <div style="background: linear-gradient(135deg, rgba(141, 198, 63, 0.1) 0%, rgba(141, 198, 63, 0.05) 100%); border: 1px solid rgba(141, 198, 63, 0.2); border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: var(--hozio-green-dark); margin-bottom: 8px;">
                                <span class="dashicons dashicons-info"></span>
                                Summary
                            </div>
                            <div style="color: var(--hozio-green-dark); font-size: 14px;">
                                Created <strong><?php echo count($created_terms); ?></strong> new town taxonomies based on page slugs. These town taxonomies are now connected to their respective pages and can be used for organization and filtering.
                            </div>
                        </div>
                        
                        <div class="hozio-terms-list">
                            <?php echo implode(', ', array_map('esc_html', $created_terms)); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="hozio-button-group" style="margin-top: 30px;">
                <a href="<?php echo admin_url('edit.php?post_type=page'); ?>" class="hozio-btn-primary">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    Back to Pages
                </a>
                <a href="<?php echo admin_url('admin.php?page=connect-town-taxonomies'); ?>" class="hozio-btn-secondary">
                    <span class="dashicons dashicons-controls-repeat"></span>
                    Run Again
                </a>
            </div>
        </div>
    </div>
    <?php
}


class Elementor_ACF_Loop_Filter {
    
    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('elementor/element/loop-grid/section_query/before_section_end', [$this, 'add_filter_toggle'], 999, 2);
        add_action('elementor/element/loop-carousel/section_query/before_section_end', [$this, 'add_filter_toggle'], 999, 2);
        add_filter('elementor/query/query_args', [$this, 'apply_term_filter'], 10, 2);
    }
    
    /**
     * Add toggle control to Elementor widgets
     */
    public function add_filter_toggle($element, $args) {
        $element->add_control(
            'enable_acf_term_filter',
            [
                'label' => __('🔗 Enable ACF Term Filter', 'hozio'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'hozio'),
                'label_off' => __('No', 'hozio'),
                'return_value' => 'yes',
                'default' => '',
                'separator' => 'before',
                'description' => __('Filter this loop by the ACF "loop_item_filter" field set on this page.', 'hozio'),
            ]
        );
    }
    
    /**
     * Apply ACF term filter to query
     */
    public function apply_term_filter($query_args, $widget) {
        $settings = $widget->get_settings();
        
        // Only run if toggle is enabled
        if (empty($settings['enable_acf_term_filter']) || $settings['enable_acf_term_filter'] !== 'yes') {
            return $query_args;
        }
        
        // Don't run in editor
        if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return $query_args;
        }
        
        $page_id = get_the_ID();
        $acf_terms = get_field('loop_item_filter', $page_id);
        
        if (!empty($acf_terms)) {
            $term_ids = $this->extract_term_ids($acf_terms);
            
            if (!empty($term_ids)) {
                $taxonomy = $this->get_taxonomy($term_ids[0]);
                
                if ($taxonomy) {
                    if (!isset($query_args['tax_query'])) {
                        $query_args['tax_query'] = [];
                    }
                    
                    $query_args['tax_query'][] = [
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => $term_ids,
                        'operator' => 'IN',
                    ];
                }
            }
        }
        
        return $query_args;
    }
    
    /**
     * Extract term IDs from ACF field value
     */
    private function extract_term_ids($acf_terms) {
        $term_ids = [];
        
        if (is_numeric($acf_terms)) {
            $term_ids = [(int) $acf_terms];
        } 
        elseif (is_array($acf_terms)) {
            foreach ($acf_terms as $term) {
                if (is_numeric($term)) {
                    $term_ids[] = (int) $term;
                } elseif (is_object($term) && isset($term->term_id)) {
                    $term_ids[] = (int) $term->term_id;
                }
            }
        } 
        elseif (is_object($acf_terms) && isset($acf_terms->term_id)) {
            $term_ids = [(int) $acf_terms->term_id];
        }
        
        return $term_ids;
    }
    
    /**
     * Get taxonomy from term ID
     */
    private function get_taxonomy($term_id) {
        $term = get_term($term_id);
        return ($term && !is_wp_error($term)) ? $term->taxonomy : null;
    }
}

// Initialize the Elementor ACF Loop Filter
new Elementor_ACF_Loop_Filter();
?>
