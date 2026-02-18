<?php
/**
 * Sitemap Layout Editor
 * Manual override system for the HTML Sitemap accordion layout
 */

if (!defined('ABSPATH')) exit;

// No separate menu — accessed via Sitemap Settings > Layout Editor tab

// Enqueue assets when on the Layout Editor tab
function hozio_sitemap_layout_admin_assets($hook) {
    if (strpos($hook, 'hozio-sitemap-settings') === false) {
        return;
    }
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'appearance';
    if ($tab !== 'layout') {
        return;
    }
    wp_enqueue_script('jquery-ui-sortable');
    add_action('admin_head', 'hozio_sitemap_layout_inline_styles');
}
add_action('admin_enqueue_scripts', 'hozio_sitemap_layout_admin_assets', 999);

// ========================================
// AJAX ENDPOINTS
// ========================================

// 1. Search pages by title
add_action('wp_ajax_hozio_sitemap_search_pages', function() {
    check_ajax_referer('hozio_sitemap_layout_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $exclude_ids = isset($_POST['exclude_ids']) && is_array($_POST['exclude_ids']) ? array_map('intval', $_POST['exclude_ids']) : array();

    if (strlen($search) < 2) {
        wp_send_json_success(array());
    }

    $args = array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        's'              => $search,
        'posts_per_page' => 20,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );
    if (!empty($exclude_ids)) {
        $args['post__not_in'] = $exclude_ids;
    }

    $pages = get_posts($args);
    $results = array();
    foreach ($pages as $p) {
        $parent_title = '';
        if ($p->post_parent) {
            $parent_title = get_the_title($p->post_parent);
        }
        $results[] = array(
            'id'           => $p->ID,
            'title'        => $p->post_title ? $p->post_title : '(Untitled)',
            'parent_title' => $parent_title,
            'permalink'    => get_permalink($p->ID),
        );
    }
    wp_send_json_success($results);
});

// 2. Get WordPress children of a page
add_action('wp_ajax_hozio_sitemap_get_page_children', function() {
    check_ajax_referer('hozio_sitemap_layout_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    if (!$parent_id) wp_send_json_error('No parent ID');

    $children = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'post_parent'    => $parent_id,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    $results = array();
    foreach ($children as $c) {
        $results[] = array(
            'id'    => $c->ID,
            'title' => $c->post_title ? $c->post_title : '(Untitled)',
        );
    }
    wp_send_json_success($results);
});

// 3. Get all pages (paginated)
add_action('wp_ajax_hozio_sitemap_get_all_pages', function() {
    check_ajax_referer('hozio_sitemap_layout_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $paged    = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $search   = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $per_page = 50;

    $args = array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );
    if ($search) {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);
    $results = array();
    foreach ($query->posts as $p) {
        $parent_title = '';
        if ($p->post_parent) {
            $parent_title = get_the_title($p->post_parent);
        }
        $results[] = array(
            'id'           => $p->ID,
            'title'        => $p->post_title ? $p->post_title : '(Untitled)',
            'parent_id'    => $p->post_parent,
            'parent_title' => $parent_title,
        );
    }

    wp_send_json_success(array(
        'pages'     => $results,
        'total'     => $query->found_posts,
        'max_pages' => $query->max_num_pages,
    ));
});

// 4. Import current auto-detection as overrides
add_action('wp_ajax_hozio_sitemap_import_auto', function() {
    check_ajax_referer('hozio_sitemap_layout_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    // Get all published pages
    $all_pages = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));

    if (empty($all_pages)) {
        wp_send_json_success(array('accordions' => array()));
    }

    // Build children lookup
    $children_by_parent = array();
    foreach ($all_pages as $page) {
        if ($page->post_parent) {
            $children_by_parent[$page->post_parent][] = $page;
        }
    }

    // Build taxonomy lookups
    $page_ids = wp_list_pluck($all_pages, 'ID');
    update_object_term_cache($page_ids, 'page');

    $service_hub_ids = array();
    $spli_ids = array();
    $services_page = null;

    foreach ($all_pages as $page) {
        if ($page->post_name === 'services') {
            $services_page = $page;
        }
        $terms = wp_get_post_terms($page->ID, 'parent_pages', array('fields' => 'names'));
        if (is_wp_error($terms)) $terms = array();

        if ($services_page && $page->ID === $services_page->ID) continue;

        if (in_array('Service Hub', $terms)) {
            $service_hub_ids[$page->ID] = $page;
        } elseif (in_array('Service Pages Loop Item', $terms)) {
            $spli_ids[$page->ID] = $page;
        }
    }

    $accordions = array();
    $consumed_ids = array();
    $order = 0;

    // Helper to get children
    $get_children = function($parent_id) use ($children_by_parent) {
        return isset($children_by_parent[$parent_id]) ? $children_by_parent[$parent_id] : array();
    };

    // Build Services accordion
    if ($services_page) {
        $consumed_ids[] = $services_page->ID;
        $services_children_data = array();
        $child_order = 0;
        $services_children = $get_children($services_page->ID);

        foreach ($services_children as $child) {
            if (isset($service_hub_ids[$child->ID])) {
                // Hub under Services
                $consumed_ids[] = $child->ID;
                $hub_children_data = array();
                $hub_child_order = 0;
                $hub_children = $get_children($child->ID);

                foreach ($hub_children as $hub_child) {
                    if (isset($spli_ids[$hub_child->ID])) {
                        $consumed_ids[] = $hub_child->ID;
                        $spli_children_data = array();
                        $spli_child_order = 0;
                        $town_pages = $get_children($hub_child->ID);
                        foreach ($town_pages as $town) {
                            $consumed_ids[] = $town->ID;
                            $spli_children_data[] = array('page_id' => $town->ID, 'order' => $spli_child_order++, 'children' => array());
                        }
                        $hub_children_data[] = array('page_id' => $hub_child->ID, 'order' => $hub_child_order++, 'children' => $spli_children_data);
                    } else {
                        $consumed_ids[] = $hub_child->ID;
                        $hub_children_data[] = array('page_id' => $hub_child->ID, 'order' => $hub_child_order++, 'children' => array());
                    }
                }
                $services_children_data[] = array('page_id' => $child->ID, 'order' => $child_order++, 'children' => $hub_children_data);
            } else {
                $consumed_ids[] = $child->ID;
                $services_children_data[] = array('page_id' => $child->ID, 'order' => $child_order++, 'children' => array());
            }
        }
        $accordions[] = array('page_id' => $services_page->ID, 'order' => $order++, 'children' => $services_children_data);
    }

    // Build standalone SPLI accordions
    foreach ($spli_ids as $spli_id => $spli_page) {
        if (in_array($spli_id, $consumed_ids)) continue;
        $consumed_ids[] = $spli_id;
        $children_data = array();
        $child_order = 0;
        $spli_children = $get_children($spli_id);
        foreach ($spli_children as $child) {
            $consumed_ids[] = $child->ID;
            $children_data[] = array('page_id' => $child->ID, 'order' => $child_order++, 'children' => array());
        }
        if (!empty($children_data)) {
            $accordions[] = array('page_id' => $spli_id, 'order' => $order++, 'children' => $children_data);
        }
    }

    // Build standalone Hub accordions (not under Services)
    foreach ($service_hub_ids as $hub_id => $hub_page) {
        if (in_array($hub_id, $consumed_ids)) continue;
        $consumed_ids[] = $hub_id;
        $hub_children_data = array();
        $child_order = 0;
        $hub_children = $get_children($hub_id);
        foreach ($hub_children as $hub_child) {
            if (isset($spli_ids[$hub_child->ID]) && !in_array($hub_child->ID, $consumed_ids)) {
                $consumed_ids[] = $hub_child->ID;
                $spli_children_data = array();
                $spli_child_order = 0;
                $town_pages = $get_children($hub_child->ID);
                foreach ($town_pages as $town) {
                    $consumed_ids[] = $town->ID;
                    $spli_children_data[] = array('page_id' => $town->ID, 'order' => $spli_child_order++, 'children' => array());
                }
                $hub_children_data[] = array('page_id' => $hub_child->ID, 'order' => $child_order++, 'children' => $spli_children_data);
            } else {
                $consumed_ids[] = $hub_child->ID;
                $hub_children_data[] = array('page_id' => $hub_child->ID, 'order' => $child_order++, 'children' => array());
            }
        }
        if (!empty($hub_children_data)) {
            $accordions[] = array('page_id' => $hub_id, 'order' => $order++, 'children' => $hub_children_data);
        }
    }

    wp_send_json_success(array('accordions' => $accordions));
});

// ========================================
// SAVE HANDLER
// ========================================
function hozio_save_sitemap_layout() {
    if (!isset($_POST['hozio_sitemap_layout_nonce_field']) ||
        !wp_verify_nonce($_POST['hozio_sitemap_layout_nonce_field'], 'hozio_sitemap_layout_nonce')) {
        wp_die('Security check failed');
    }
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized user');
    }

    $enabled = isset($_POST['hozio_layout_enabled']) ? true : false;
    $mode = isset($_POST['hozio_layout_mode']) && $_POST['hozio_layout_mode'] === 'manual_only' ? 'manual_only' : 'override_first';

    // Parse accordions from JSON
    $accordions_json = isset($_POST['hozio_layout_accordions']) ? wp_unslash($_POST['hozio_layout_accordions']) : '[]';
    $accordions_raw = json_decode($accordions_json, true);
    if (!is_array($accordions_raw)) $accordions_raw = array();

    // Recursive sanitization
    $accordions = hozio_sanitize_accordion_array($accordions_raw);

    // Parse excluded IDs
    $exclude_json = isset($_POST['hozio_layout_exclude_ids']) ? wp_unslash($_POST['hozio_layout_exclude_ids']) : '[]';
    $exclude_raw = json_decode($exclude_json, true);
    $exclude_ids = is_array($exclude_raw) ? array_map('intval', $exclude_raw) : array();

    $data = array(
        'enabled'     => $enabled,
        'mode'        => $mode,
        'accordions'  => $accordions,
        'exclude_ids' => $exclude_ids,
    );

    update_option('hozio_sitemap_layout_overrides', $data);

    wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=hozio-sitemap-settings&tab=layout')));
    exit;
}
add_action('admin_post_hozio_save_sitemap_layout', 'hozio_save_sitemap_layout');

function hozio_sanitize_accordion_array($items) {
    $sanitized = array();
    if (!is_array($items)) return $sanitized;

    foreach ($items as $index => $item) {
        if (!isset($item['page_id']) || !intval($item['page_id'])) continue;
        $sanitized[] = array(
            'page_id'  => intval($item['page_id']),
            'order'    => isset($item['order']) ? intval($item['order']) : $index,
            'children' => isset($item['children']) && is_array($item['children'])
                ? hozio_sanitize_accordion_array($item['children'])
                : array(),
        );
    }
    return $sanitized;
}

// ========================================
// ADMIN PAGE RENDER
// ========================================
function hozio_sitemap_layout_page() {
    $overrides = get_option('hozio_sitemap_layout_overrides', array());
    $enabled = !empty($overrides['enabled']);
    $mode = isset($overrides['mode']) ? $overrides['mode'] : 'override_first';
    $accordions = isset($overrides['accordions']) ? $overrides['accordions'] : array();
    $exclude_ids = isset($overrides['exclude_ids']) ? $overrides['exclude_ids'] : array();

    // Pre-load page titles for all referenced page IDs
    $all_page_ids = array();
    hozio_collect_page_ids_from_accordions($accordions, $all_page_ids);
    $all_page_ids = array_merge($all_page_ids, $exclude_ids);
    $all_page_ids = array_unique(array_filter($all_page_ids));

    $page_titles = array();
    if (!empty($all_page_ids)) {
        $pages = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'any',
            'post__in'       => $all_page_ids,
            'posts_per_page' => -1,
        ));
        foreach ($pages as $p) {
            $parent_title = '';
            if ($p->post_parent) {
                $parent_title = get_the_title($p->post_parent);
            }
            $page_titles[$p->ID] = array(
                'title'        => $p->post_title ? $p->post_title : '(Untitled)',
                'parent_title' => $parent_title,
                'status'       => $p->post_status,
            );
        }
    }

    // Mark missing pages
    foreach ($all_page_ids as $pid) {
        if (!isset($page_titles[$pid])) {
            $page_titles[$pid] = array(
                'title'        => '(Deleted Page #' . $pid . ')',
                'parent_title' => '',
                'status'       => 'deleted',
            );
        }
    }

    // Pre-load all published pages for the Unassigned Pages section
    $all_site_pages = array();
    $all_published = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));
    foreach ($all_published as $p) {
        $parent_title = $p->post_parent ? get_the_title($p->post_parent) : '';
        $all_site_pages[] = array(
            'id'           => $p->ID,
            'title'        => $p->post_title ? $p->post_title : '(Untitled)',
            'parent_title' => $parent_title,
        );
    }
    ?>

    <div class="wrap">
        <div class="hozio-settings-wrapper">
            <div class="hozio-header">
                <div class="hozio-header-content">
                    <h1>
                        <span class="dashicons dashicons-admin-site-alt3" style="font-size: 32px; width: 32px; height: 32px;"></span>
                        Sitemap Settings
                    </h1>
                    <p class="hozio-subtitle">Configure your HTML sitemap appearance and layout</p>
                </div>
            </div>

            <div class="hozio-tab-bar">
                <a href="<?php echo esc_url(admin_url('admin.php?page=hozio-sitemap-settings&tab=appearance')); ?>" class="hozio-tab">
                    <span class="dashicons dashicons-admin-appearance"></span> Appearance
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hozio-sitemap-settings&tab=layout')); ?>" class="hozio-tab active">
                    <span class="dashicons dashicons-layout"></span> Layout Editor
                </a>
            </div>

            <div class="hozio-content">
                <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
                    <div class="notice notice-success is-dismissible" style="margin: 0 0 20px 0; border-radius: 6px;">
                        <p><strong>Layout saved successfully!</strong> Your sitemap layout overrides have been updated.</p>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="hozio-layout-form">
                    <?php wp_nonce_field('hozio_sitemap_layout_nonce', 'hozio_sitemap_layout_nonce_field'); ?>
                    <input type="hidden" name="action" value="hozio_save_sitemap_layout">
                    <input type="hidden" name="hozio_layout_accordions" id="hozio-layout-accordions-data" value="<?php echo esc_attr(json_encode($accordions)); ?>">
                    <input type="hidden" name="hozio_layout_exclude_ids" id="hozio-layout-exclude-data" value="<?php echo esc_attr(json_encode($exclude_ids)); ?>">

                    <!-- Section 1: Override Mode -->
                    <div class="hozio-section" style="border-left-color: var(--hozio-blue);">
                        <div class="hozio-section-header">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <h2>Override Mode</h2>
                        </div>

                        <div class="hozio-field">
                            <div class="hozio-toggle-wrapper">
                                <label class="hozio-toggle-switch">
                                    <input type="checkbox" name="hozio_layout_enabled" id="hozio-layout-enabled" value="1" <?php checked($enabled); ?>>
                                    <span class="hozio-toggle-slider"></span>
                                </label>
                                <span class="hozio-toggle-label">Enable Manual Overrides</span>
                            </div>
                            <p class="hozio-field-description">When enabled, your manually defined accordions will appear in the sitemap. When disabled, the sitemap uses automatic detection only.</p>
                        </div>

                        <div class="hozio-field" id="hozio-mode-field" style="<?php echo $enabled ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">
                            <label class="hozio-field-label">Detection Mode</label>
                            <div class="hozio-radio-group">
                                <label class="hozio-radio-option <?php echo $mode === 'override_first' ? 'active' : ''; ?>">
                                    <input type="radio" name="hozio_layout_mode" value="override_first" <?php checked($mode, 'override_first'); ?>>
                                    <span class="radio-dot"></span>
                                    <div>
                                        <strong>Override + Auto-fill</strong>
                                        <span class="radio-desc">Your manual accordions render first, then remaining pages are auto-detected using taxonomy rules.</span>
                                    </div>
                                </label>
                                <label class="hozio-radio-option <?php echo $mode === 'manual_only' ? 'active' : ''; ?>">
                                    <input type="radio" name="hozio_layout_mode" value="manual_only" <?php checked($mode, 'manual_only'); ?>>
                                    <span class="radio-dot"></span>
                                    <div>
                                        <strong>Manual Only</strong>
                                        <span class="radio-desc">Only your manually defined accordions appear. No automatic detection. Remaining pages show as plain links.</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Accordion Builder -->
                    <div class="hozio-section" style="border-left-color: var(--hozio-green);">
                        <div class="hozio-section-header">
                            <span class="dashicons dashicons-list-view" style="color: var(--hozio-green);"></span>
                            <h2>Accordion Builder</h2>
                        </div>

                        <div class="hozio-builder-actions">
                            <button type="button" class="button button-primary" id="hozio-add-accordion">
                                <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span> Add Accordion
                            </button>
                            <button type="button" class="button" id="hozio-import-auto">
                                <span class="dashicons dashicons-download" style="margin-top: 3px;"></span> Import Current Auto-Detection
                            </button>
                        </div>

                        <div id="hozio-accordions-container">
                            <!-- Accordion cards rendered by JS -->
                        </div>

                        <div id="hozio-no-accordions" class="hozio-empty-state" style="<?php echo empty($accordions) ? '' : 'display: none;'; ?>">
                            <span class="dashicons dashicons-layout" style="font-size: 48px; width: 48px; height: 48px; color: #d1d5db;"></span>
                            <p>No accordions defined yet.</p>
                            <p class="hozio-field-description">Click "Add Accordion" to create your first accordion, or "Import Current Auto-Detection" to start from the current layout.</p>
                        </div>
                    </div>

                    <!-- Section 3: Excluded Pages -->
                    <div class="hozio-section" style="border-left-color: var(--hozio-orange);">
                        <div class="hozio-section-header">
                            <span class="dashicons dashicons-hidden" style="color: var(--hozio-orange);"></span>
                            <h2>Excluded Pages</h2>
                        </div>
                        <p class="hozio-field-description" style="margin-bottom: 16px;">
                            Pages listed here will be completely hidden from the sitemap, regardless of other settings.
                        </p>

                        <div class="hozio-exclude-search-wrapper">
                            <input type="text" id="hozio-exclude-search" class="hozio-search-input" placeholder="Search pages to exclude...">
                        </div>

                        <div id="hozio-exclude-tags" class="hozio-tag-list">
                            <!-- Tags rendered by JS -->
                        </div>

                        <div id="hozio-exclude-results" class="hozio-search-results" style="display: none;">
                            <!-- Search results rendered by JS -->
                        </div>
                    </div>

                    <!-- Section 4: Unassigned Pages -->
                    <div class="hozio-section" style="border-left-color: #8b5cf6;">
                        <div class="hozio-section-header">
                            <span class="dashicons dashicons-editor-ul" style="color: #8b5cf6;"></span>
                            <h2>Unassigned Pages</h2>
                            <span id="hozio-unassigned-count" class="unassigned-count-badge">0</span>
                        </div>
                        <p class="hozio-field-description" style="margin-bottom: 16px;">
                            These pages are not in any accordion and not excluded. They will appear as plain links above the accordions in the sitemap.
                        </p>

                        <div id="hozio-unassigned-search-wrapper" style="margin-bottom: 12px; display: none;">
                            <input type="text" id="hozio-unassigned-filter" class="hozio-search-input" placeholder="Filter unassigned pages...">
                        </div>

                        <div id="hozio-unassigned-list" class="unassigned-pages-list">
                            <div class="unassigned-loading">Loading pages...</div>
                        </div>

                        <button type="button" class="button button-small" id="hozio-toggle-unassigned" style="margin-top: 12px;">
                            <span class="dashicons dashicons-arrow-down-alt2" style="margin-top: 2px; font-size: 14px;"></span> Show All
                        </button>
                    </div>

                    <!-- Save bar -->
                    <div class="hozio-submit-wrapper">
                        <?php submit_button('Save Layout', 'primary hozio-submit-btn', 'submit', false); ?>
                        <button type="button" class="button hozio-reset-btn" id="hozio-reset-layout">
                            <span class="dashicons dashicons-undo" style="margin-top: 3px;"></span> Reset to Auto-Detection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var nonce = '<?php echo wp_create_nonce('hozio_sitemap_layout_nonce'); ?>';
        var pageTitles = <?php echo json_encode($page_titles); ?>;
        var accordions = <?php echo json_encode($accordions); ?>;
        var excludeIds = <?php echo json_encode($exclude_ids); ?>;
        var allSitePages = <?php echo json_encode($all_site_pages); ?>;
        var searchTimeout = null;
        var unassignedExpanded = false;
        var unassignedFilter = '';

        // ========================================
        // PATH-BASED TREE NAVIGATION
        // Every element uses a "path" string like "0" (top-level index 0),
        // "0.2" (accordion 0, child 2), "0.2.1" (accordion 0, child 2, sub-child 1)
        // This lets us navigate to any depth.
        // ========================================
        function getNodeByPath(path) {
            var parts = path.split('.').map(Number);
            if (parts.length === 0) return null;
            var node = accordions[parts[0]];
            for (var i = 1; i < parts.length; i++) {
                if (!node || !node.children || !node.children[parts[i]]) return null;
                node = node.children[parts[i]];
            }
            return node;
        }

        function getParentAndIndex(path) {
            var parts = path.split('.').map(Number);
            if (parts.length === 1) {
                return { parent: null, index: parts[0], isTopLevel: true };
            }
            var parentPath = parts.slice(0, -1).join('.');
            return { parent: getNodeByPath(parentPath), index: parts[parts.length - 1], isTopLevel: false };
        }

        // ========================================
        // UTILITY FUNCTIONS
        // ========================================
        function getPageTitle(pageId) {
            pageId = parseInt(pageId);
            if (pageTitles[pageId]) {
                var info = pageTitles[pageId];
                return info.title + (info.parent_title ? ' (under: ' + info.parent_title + ')' : '');
            }
            return 'Page #' + pageId;
        }

        function getPageTitleShort(pageId) {
            pageId = parseInt(pageId);
            if (pageTitles[pageId]) return pageTitles[pageId].title;
            return 'Page #' + pageId;
        }

        function getAllAssignedIds() {
            var ids = [];
            function collect(items) {
                if (!items) return;
                for (var i = 0; i < items.length; i++) {
                    ids.push(parseInt(items[i].page_id));
                    if (items[i].children) collect(items[i].children);
                }
            }
            collect(accordions);
            return ids;
        }

        function countChildren(acc) {
            var count = 0;
            if (acc.children) {
                count += acc.children.length;
                for (var i = 0; i < acc.children.length; i++) {
                    count += countChildren(acc.children[i]);
                }
            }
            return count;
        }

        function syncFormData() {
            for (var i = 0; i < accordions.length; i++) {
                accordions[i].order = i;
            }
            $('#hozio-layout-accordions-data').val(JSON.stringify(accordions));
            $('#hozio-layout-exclude-data').val(JSON.stringify(excludeIds));
            // Update unassigned pages whenever data changes
            if (typeof renderUnassignedPages === 'function') {
                renderUnassignedPages();
            }
        }

        function escHtml(str) {
            if (!str) return '';
            return $('<div>').text(str).html();
        }

        // ========================================
        // RENDER ACCORDIONS
        // ========================================
        function renderAccordions() {
            var $container = $('#hozio-accordions-container');
            $container.empty();

            if (accordions.length === 0) {
                $('#hozio-no-accordions').show();
                syncFormData();
                return;
            }
            $('#hozio-no-accordions').hide();

            for (var i = 0; i < accordions.length; i++) {
                $container.append(renderAccordionCard(String(i), accordions[i], 1));
            }

            // Init sortable on the top-level container
            $container.sortable({
                handle: '> .accordion-card-header > .accordion-card-drag',
                items: '> .accordion-card',
                placeholder: 'accordion-card-placeholder',
                tolerance: 'pointer',
                update: function() {
                    var newOrder = [];
                    $container.children('.accordion-card').each(function() {
                        var path = $(this).data('path');
                        var idx = parseInt(path);
                        newOrder.push(accordions[idx]);
                    });
                    accordions = newOrder;
                    renderAccordions();
                }
            });

            syncFormData();
        }

        function renderAccordionCard(path, acc, level) {
            var childCount = countChildren(acc);
            var title = getPageTitleShort(acc.page_id);
            var isCollapsed = acc._collapsed !== false;
            var isSubAccordion = acc._is_accordion || (acc.children && acc.children.length > 0);

            var levelLabel = level === 1 ? 'Accordion' : (level === 2 ? 'Sub-Accordion' : 'Nested');
            var levelColor = level === 1 ? 'var(--hozio-green)' : (level === 2 ? 'var(--hozio-blue)' : 'var(--hozio-orange)');

            var html = '<div class="accordion-card" data-path="' + path + '" data-level="' + level + '" style="border-left-color: ' + levelColor + ';">';
            html += '<div class="accordion-card-header">';
            html += '<span class="accordion-card-drag dashicons dashicons-move" title="Drag to reorder"></span>';
            html += '<span class="accordion-card-level-badge" style="background: ' + levelColor + ';">' + levelLabel + '</span>';
            html += '<span class="accordion-card-title">' + escHtml(title) + '</span>';
            html += '<span class="accordion-card-count">' + childCount + ' page' + (childCount !== 1 ? 's' : '') + '</span>';
            html += '<button type="button" class="accordion-card-toggle" title="' + (isCollapsed ? 'Expand' : 'Collapse') + '">';
            html += '<span class="dashicons dashicons-arrow-' + (isCollapsed ? 'down' : 'up') + '-alt2"></span></button>';
            html += '<button type="button" class="accordion-card-delete" data-path="' + path + '" title="Remove">';
            html += '<span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';

            html += '<div class="accordion-card-body" style="' + (isCollapsed ? 'display:none;' : '') + '">';

            // Parent page selector
            html += '<div class="accordion-card-field">';
            html += '<label class="hozio-field-label">Parent Page</label>';
            html += '<div class="hozio-page-selector">';
            if (acc.page_id) {
                html += '<div class="selected-page-tag">';
                html += '<span>' + escHtml(getPageTitle(acc.page_id)) + '</span>';
                html += '<button type="button" class="tag-remove change-parent" data-path="' + path + '" title="Change">';
                html += '<span class="dashicons dashicons-edit"></span></button>';
                html += '</div>';
            }
            html += '<div class="page-search-wrapper" style="' + (acc.page_id ? 'display:none;' : '') + '">';
            html += '<input type="text" class="hozio-search-input parent-search" data-path="' + path + '" placeholder="Search for a page...">';
            html += '<div class="page-search-results" style="display:none;"></div>';
            html += '</div>';
            html += '</div></div>';

            // Children section
            html += '<div class="accordion-card-field">';
            html += '<label class="hozio-field-label">Children</label>';
            html += '<div class="children-actions">';
            html += '<button type="button" class="button button-small add-child-btn" data-path="' + path + '">';
            html += '<span class="dashicons dashicons-plus-alt2" style="margin-top: 2px; font-size: 14px;"></span> Add Pages</button>';
            if (acc.page_id) {
                html += '<button type="button" class="button button-small import-wp-children-btn" data-path="' + path + '" data-parent-id="' + acc.page_id + '">';
                html += '<span class="dashicons dashicons-download" style="margin-top: 2px; font-size: 14px;"></span> Import WP Children</button>';
            }
            html += '</div>';

            // Children list
            html += '<div class="children-list" data-path="' + path + '">';
            if (acc.children && acc.children.length > 0) {
                for (var c = 0; c < acc.children.length; c++) {
                    var child = acc.children[c];
                    var childPath = path + '.' + c;
                    var isChildAccordion = child._is_accordion || (child.children && child.children.length > 0);

                    html += '<div class="child-item ' + (isChildAccordion ? 'is-accordion' : '') + '" data-child-path="' + childPath + '">';
                    html += '<span class="child-drag dashicons dashicons-move" title="Drag to reorder"></span>';
                    html += '<span class="child-title">' + escHtml(getPageTitleShort(child.page_id)) + '</span>';

                    if (level < 3) {
                        if (isChildAccordion) {
                            html += '<button type="button" class="acc-toggle-btn is-active" data-path="' + childPath + '" data-action="remove" title="Convert back to a plain page link">';
                            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>';
                            html += ' Accordion</button>';
                        } else {
                            html += '<button type="button" class="acc-toggle-btn" data-path="' + childPath + '" data-action="make" title="Turn this into a nested accordion with its own children">';
                            html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>';
                            html += ' Accordion</button>';
                        }
                    }

                    html += '<button type="button" class="child-remove" data-path="' + childPath + '" title="Remove from this accordion">';
                    html += '<span class="dashicons dashicons-no-alt"></span></button>';
                    html += '</div>';

                    // Render nested sub-accordion card if this child is an accordion
                    if (isChildAccordion && level < 3) {
                        html += renderAccordionCard(childPath, child, level + 1);
                    }
                }
            } else {
                html += '<div class="children-empty">No children added yet.</div>';
            }
            html += '</div>';

            // Child search panel (hidden by default)
            html += '<div class="child-search-panel" data-path="' + path + '" style="display:none;">';
            html += '<input type="text" class="hozio-search-input child-search-input" placeholder="Search pages to add...">';
            html += '<div class="child-search-results"></div>';
            html += '<button type="button" class="button button-small close-child-search">Done</button>';
            html += '</div>';

            html += '</div>'; // end field
            html += '</div>'; // end body
            html += '</div>'; // end card

            return html;
        }

        // ========================================
        // EVENT HANDLERS
        // ========================================

        // Toggle enabled state
        $('#hozio-layout-enabled').on('change', function() {
            var enabled = $(this).is(':checked');
            $('#hozio-mode-field').css({
                'opacity': enabled ? 1 : 0.5,
                'pointer-events': enabled ? 'auto' : 'none'
            });
        });

        // Radio option styling
        $(document).on('change', '.hozio-radio-option input', function() {
            $('.hozio-radio-option').removeClass('active');
            $(this).closest('.hozio-radio-option').addClass('active');
        });

        // Add new top-level accordion
        $('#hozio-add-accordion').on('click', function() {
            accordions.push({
                page_id: 0,
                order: accordions.length,
                children: [],
                _collapsed: false
            });
            renderAccordions();
            var $newCard = $('#hozio-accordions-container > .accordion-card:last');
            $newCard.find('.page-search-wrapper').show();
            $newCard.find('.parent-search').focus();
            $('html, body').animate({ scrollTop: $newCard.offset().top - 100 }, 300);
        });

        // Toggle accordion card expand/collapse
        $(document).on('click', '.accordion-card-toggle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $card = $(this).closest('.accordion-card');
            var $body = $card.children('.accordion-card-body');
            var $icon = $(this).find('.dashicons');
            var path = $card.data('path');
            var node = getNodeByPath(String(path));

            if ($body.is(':visible')) {
                $body.slideUp(200);
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                if (node) node._collapsed = true;
            } else {
                $body.slideDown(200);
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                if (node) node._collapsed = false;
            }
        });

        // Delete accordion card (works at any level)
        $(document).on('click', '.accordion-card-delete', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var path = String($(this).data('path'));
            var node = getNodeByPath(path);
            var title = node ? getPageTitleShort(node.page_id) : '';

            if (!confirm('Remove this accordion' + (title ? ' (' + title + ')' : '') + ' and all its children?')) return;

            var info = getParentAndIndex(path);
            if (info.isTopLevel) {
                accordions.splice(info.index, 1);
            } else if (info.parent && info.parent.children) {
                // Remove this child from its parent, converting it to nothing
                info.parent.children.splice(info.index, 1);
            }
            renderAccordions();
        });

        // Change parent page (works at any level)
        $(document).on('click', '.change-parent', function(e) {
            e.preventDefault();
            var $selector = $(this).closest('.hozio-page-selector');
            $selector.find('.selected-page-tag').hide();
            $selector.find('.page-search-wrapper').show();
            $selector.find('.parent-search').val('').focus();
        });

        // Parent page search
        $(document).on('input', '.parent-search', function() {
            var $input = $(this);
            var $results = $input.siblings('.page-search-results');
            var query = $input.val();

            clearTimeout(searchTimeout);
            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            searchTimeout = setTimeout(function() {
                $.post(ajaxurl, {
                    action: 'hozio_sitemap_search_pages',
                    nonce: nonce,
                    search: query,
                    exclude_ids: getAllAssignedIds()
                }, function(response) {
                    if (response.success && response.data.length > 0) {
                        var html = '';
                        for (var i = 0; i < response.data.length; i++) {
                            var p = response.data[i];
                            html += '<div class="search-result-item parent-result" data-page-id="' + p.id + '">';
                            html += '<strong>' + escHtml(p.title) + '</strong>';
                            if (p.parent_title) html += '<span class="result-meta"> (under: ' + escHtml(p.parent_title) + ')</span>';
                            html += '<span class="result-meta result-id">ID: ' + p.id + '</span>';
                            html += '</div>';
                        }
                        $results.html(html).show();
                        for (var i = 0; i < response.data.length; i++) {
                            var p = response.data[i];
                            pageTitles[p.id] = { title: p.title, parent_title: p.parent_title, status: 'publish' };
                        }
                    } else {
                        $results.html('<div class="search-no-results">No pages found</div>').show();
                    }
                });
            }, 300);
        });

        // Select parent page from search results (works at any level)
        $(document).on('click', '.parent-result', function() {
            var pageId = parseInt($(this).data('page-id'));
            var $card = $(this).closest('.accordion-card');
            var path = String($card.data('path'));
            var node = getNodeByPath(path);
            if (node) node.page_id = pageId;
            renderAccordions();
        });

        // ========================================
        // MAKE ACCORDION / REMOVE ACCORDION
        // ========================================
        $(document).on('click', '.acc-toggle-btn', function(e) {
            e.preventDefault();
            var path = String($(this).data('path'));
            var action = $(this).data('action');
            var node = getNodeByPath(path);
            if (!node) return;

            if (action === 'make') {
                node._is_accordion = true;
                node._collapsed = false;
                if (!node.children) node.children = [];
            } else {
                var hasChildren = node.children && node.children.length > 0;
                if (hasChildren && !confirm('This will remove all nested children from this accordion. Continue?')) return;
                node._is_accordion = false;
                node.children = [];
            }
            renderAccordions();
        });

        // ========================================
        // ADD CHILD PAGES (works at any level)
        // ========================================
        $(document).on('click', '.add-child-btn', function(e) {
            e.preventDefault();
            var $panel = $(this).closest('.accordion-card-field').find('> .child-search-panel');
            $panel.show();
            $panel.find('.child-search-input').val('').focus();
        });

        $(document).on('click', '.close-child-search', function(e) {
            e.preventDefault();
            $(this).closest('.child-search-panel').hide();
            renderAccordions();
        });

        // Child page search
        $(document).on('input', '.child-search-input', function() {
            var $input = $(this);
            var $results = $input.siblings('.child-search-results');
            var query = $input.val();
            var path = String($input.closest('.child-search-panel').data('path'));

            clearTimeout(searchTimeout);
            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            searchTimeout = setTimeout(function() {
                $.post(ajaxurl, {
                    action: 'hozio_sitemap_search_pages',
                    nonce: nonce,
                    search: query,
                    exclude_ids: getAllAssignedIds()
                }, function(response) {
                    if (response.success && response.data.length > 0) {
                        var html = '';
                        var assigned = getAllAssignedIds();
                        for (var i = 0; i < response.data.length; i++) {
                            var p = response.data[i];
                            var isAssigned = assigned.indexOf(p.id) !== -1;
                            html += '<div class="search-result-item child-result ' + (isAssigned ? 'disabled' : '') + '" data-page-id="' + p.id + '" data-target-path="' + path + '">';
                            html += '<span class="dashicons dashicons-' + (isAssigned ? 'lock' : 'plus-alt2') + '"></span> ';
                            html += escHtml(p.title);
                            if (p.parent_title) html += '<span class="result-meta"> (under: ' + escHtml(p.parent_title) + ')</span>';
                            if (isAssigned) html += '<span class="result-meta assigned-label">Already assigned</span>';
                            html += '</div>';
                        }
                        $results.html(html).show();
                        for (var i = 0; i < response.data.length; i++) {
                            var p = response.data[i];
                            pageTitles[p.id] = { title: p.title, parent_title: p.parent_title, status: 'publish' };
                        }
                    } else {
                        $results.html('<div class="search-no-results">No pages found</div>').show();
                    }
                });
            }, 300);
        });

        // Select child page from results (works at any level via path)
        $(document).on('click', '.child-result:not(.disabled)', function() {
            var pageId = parseInt($(this).data('page-id'));
            var targetPath = String($(this).data('target-path'));
            var target = getNodeByPath(targetPath);

            if (target) {
                if (!target.children) target.children = [];
                target.children.push({
                    page_id: pageId,
                    order: target.children.length,
                    children: []
                });
            }

            $(this).addClass('disabled');
            $(this).find('.dashicons').removeClass('dashicons-plus-alt2').addClass('dashicons-lock');
            $(this).find('.result-meta.assigned-label').remove();
            $(this).append('<span class="result-meta assigned-label">Just added</span>');
            syncFormData();
        });

        // Import WordPress children (works at any level via path)
        $(document).on('click', '.import-wp-children-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var parentId = parseInt($btn.data('parent-id'));
            var path = String($btn.data('path'));

            $btn.prop('disabled', true).text('Loading...');

            $.post(ajaxurl, {
                action: 'hozio_sitemap_get_page_children',
                nonce: nonce,
                parent_id: parentId
            }, function(response) {
                if (response.success && response.data.length > 0) {
                    var target = getNodeByPath(path);
                    if (!target) { $btn.prop('disabled', false); return; }
                    if (!target.children) target.children = [];

                    var assigned = getAllAssignedIds();
                    var added = 0;
                    for (var i = 0; i < response.data.length; i++) {
                        var child = response.data[i];
                        if (assigned.indexOf(child.id) === -1) {
                            pageTitles[child.id] = { title: child.title, parent_title: '', status: 'publish' };
                            target.children.push({
                                page_id: child.id,
                                order: target.children.length,
                                children: []
                            });
                            added++;
                        }
                    }
                    renderAccordions();
                    alert('Imported ' + added + ' child page(s).');
                } else {
                    alert('No child pages found for this page.');
                }
                $btn.prop('disabled', false);
            }).fail(function() {
                $btn.prop('disabled', false);
                alert('Failed to load children. Please try again.');
            });
        });

        // Remove child (works at any level via path)
        $(document).on('click', '.child-remove', function(e) {
            e.preventDefault();
            var path = String($(this).data('path'));
            var info = getParentAndIndex(path);
            if (info.isTopLevel) {
                accordions.splice(info.index, 1);
            } else if (info.parent && info.parent.children) {
                info.parent.children.splice(info.index, 1);
            }
            renderAccordions();
        });

        // ========================================
        // IMPORT AUTO-DETECTION
        // ========================================
        $('#hozio-import-auto').on('click', function() {
            if (accordions.length > 0 && !confirm('This will replace your current accordion layout with the auto-detected one. Continue?')) return;

            var $btn = $(this);
            $btn.prop('disabled', true).text('Importing...');

            $.post(ajaxurl, {
                action: 'hozio_sitemap_import_auto',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    accordions = response.data.accordions || [];
                    var ids = [];
                    function collectIds(items) {
                        for (var i = 0; i < items.length; i++) {
                            ids.push(items[i].page_id);
                            if (items[i].children) collectIds(items[i].children);
                        }
                    }
                    collectIds(accordions);

                    if (ids.length > 0) {
                        loadPageTitles(ids, function() {
                            renderAccordions();
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top: 3px;"></span> Import Current Auto-Detection');
                        });
                    } else {
                        renderAccordions();
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top: 3px;"></span> Import Current Auto-Detection');
                        alert('No accordions detected. Your sitemap may not have any pages with the required taxonomy terms.');
                    }
                } else {
                    alert('Import failed. Please try again.');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top: 3px;"></span> Import Current Auto-Detection');
                }
            }).fail(function() {
                alert('Import failed. Please try again.');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="margin-top: 3px;"></span> Import Current Auto-Detection');
            });
        });

        function loadPageTitles(ids, callback) {
            var remaining = [];
            for (var i = 0; i < ids.length; i++) {
                if (!pageTitles[ids[i]]) remaining.push(ids[i]);
            }
            if (remaining.length === 0) { callback(); return; }

            $.post(ajaxurl, {
                action: 'hozio_sitemap_load_titles',
                nonce: nonce,
                ids: remaining
            }, function(response) {
                if (response.success) {
                    for (var id in response.data) {
                        pageTitles[id] = response.data[id];
                    }
                }
                callback();
            }).fail(callback);
        }

        // ========================================
        // EXCLUDE PAGES
        // ========================================
        function renderExcludeTags() {
            var $tags = $('#hozio-exclude-tags');
            $tags.empty();
            for (var i = 0; i < excludeIds.length; i++) {
                var id = excludeIds[i];
                $tags.append(
                    '<span class="hozio-tag">' + escHtml(getPageTitleShort(id)) +
                    ' <button type="button" class="tag-remove exclude-remove" data-id="' + id + '">&times;</button></span>'
                );
            }
            syncFormData();
        }

        $(document).on('click', '.exclude-remove', function() {
            var id = parseInt($(this).data('id'));
            excludeIds = excludeIds.filter(function(eid) { return eid !== id; });
            renderExcludeTags();
        });

        var excludeSearchTimeout = null;
        $('#hozio-exclude-search').on('input', function() {
            var $input = $(this);
            var query = $input.val();
            var $results = $('#hozio-exclude-results');

            clearTimeout(excludeSearchTimeout);
            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            excludeSearchTimeout = setTimeout(function() {
                $.post(ajaxurl, {
                    action: 'hozio_sitemap_search_pages',
                    nonce: nonce,
                    search: query
                }, function(response) {
                    if (response.success && response.data.length > 0) {
                        var html = '';
                        for (var i = 0; i < response.data.length; i++) {
                            var p = response.data[i];
                            var isExcluded = excludeIds.indexOf(p.id) !== -1;
                            html += '<div class="search-result-item exclude-result ' + (isExcluded ? 'disabled' : '') + '" data-page-id="' + p.id + '">';
                            html += escHtml(p.title);
                            if (p.parent_title) html += '<span class="result-meta"> (under: ' + escHtml(p.parent_title) + ')</span>';
                            if (isExcluded) html += '<span class="result-meta assigned-label">Already excluded</span>';
                            html += '</div>';
                        }
                        $results.html(html).show();
                        for (var i = 0; i < response.data.length; i++) {
                            pageTitles[response.data[i].id] = { title: response.data[i].title, parent_title: response.data[i].parent_title, status: 'publish' };
                        }
                    } else {
                        $results.html('<div class="search-no-results">No pages found</div>').show();
                    }
                });
            }, 300);
        });

        $(document).on('click', '.exclude-result:not(.disabled)', function() {
            var pageId = parseInt($(this).data('page-id'));
            excludeIds.push(pageId);
            $(this).addClass('disabled').append('<span class="result-meta assigned-label">Excluded</span>');
            renderExcludeTags();
        });

        // ========================================
        // RESET
        // ========================================
        $('#hozio-reset-layout').on('click', function() {
            if (!confirm('This will clear all your manual accordions and excluded pages. The sitemap will revert to automatic detection. Continue?')) return;
            accordions = [];
            excludeIds = [];
            $('#hozio-layout-enabled').prop('checked', false).trigger('change');
            renderAccordions();
            renderExcludeTags();
        });

        // ========================================
        // INIT SORTABLE ON CHILDREN LISTS
        // ========================================
        $(document).on('mouseenter', '.children-list', function() {
            var $list = $(this);
            if (!$list.data('sortable-init')) {
                $list.sortable({
                    handle: '.child-drag',
                    items: '> .child-item',
                    placeholder: 'child-item-placeholder',
                    tolerance: 'pointer',
                    update: function() {
                        var path = String($list.data('path'));
                        var target = getNodeByPath(path);
                        if (target && target.children) {
                            var newOrder = [];
                            $list.children('.child-item').each(function() {
                                var childPath = String($(this).data('child-path'));
                                var childIdx = parseInt(childPath.split('.').pop());
                                newOrder.push(target.children[childIdx]);
                            });
                            target.children = newOrder;
                            renderAccordions();
                        }
                    }
                });
                $list.data('sortable-init', true);
            }
        });

        // ========================================
        // UNASSIGNED PAGES
        // ========================================
        function getUnassignedPages() {
            var assigned = getAllAssignedIds();
            var unassigned = [];
            for (var i = 0; i < allSitePages.length; i++) {
                var p = allSitePages[i];
                if (assigned.indexOf(p.id) === -1 && excludeIds.indexOf(p.id) === -1) {
                    unassigned.push(p);
                }
            }
            return unassigned;
        }

        function renderUnassignedPages() {
            var pages = getUnassignedPages();
            var $list = $('#hozio-unassigned-list');
            var $count = $('#hozio-unassigned-count');
            var $toggle = $('#hozio-toggle-unassigned');
            var $searchWrap = $('#hozio-unassigned-search-wrapper');

            // Filter by search
            var filtered = pages;
            if (unassignedFilter.length >= 2) {
                var q = unassignedFilter.toLowerCase();
                filtered = pages.filter(function(p) {
                    return p.title.toLowerCase().indexOf(q) !== -1 ||
                           (p.parent_title && p.parent_title.toLowerCase().indexOf(q) !== -1);
                });
            }

            $count.text(pages.length);

            if (pages.length === 0) {
                $list.html('<div class="unassigned-empty">All pages are assigned to accordions or excluded.</div>');
                $toggle.hide();
                $searchWrap.hide();
                return;
            }

            // Show search if more than 10 pages
            if (pages.length > 10) {
                $searchWrap.show();
            } else {
                $searchWrap.hide();
            }

            var visibleCount = unassignedExpanded ? filtered.length : Math.min(filtered.length, 10);
            var html = '';
            for (var i = 0; i < visibleCount; i++) {
                var p = filtered[i];
                html += '<div class="unassigned-page-item">';
                html += '<span class="dashicons dashicons-media-default" style="color: #9ca3af; font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>';
                html += '<span class="unassigned-page-title">' + escHtml(p.title) + '</span>';
                if (p.parent_title) {
                    html += '<span class="unassigned-page-parent">(under: ' + escHtml(p.parent_title) + ')</span>';
                }
                html += '</div>';
            }

            $list.html(html);

            if (filtered.length > 10) {
                $toggle.show();
                if (unassignedExpanded) {
                    $toggle.html('<span class="dashicons dashicons-arrow-up-alt2" style="margin-top: 2px; font-size: 14px;"></span> Show Less');
                } else {
                    $toggle.html('<span class="dashicons dashicons-arrow-down-alt2" style="margin-top: 2px; font-size: 14px;"></span> Show All (' + filtered.length + ')');
                }
            } else {
                $toggle.hide();
            }
        }

        $('#hozio-toggle-unassigned').on('click', function() {
            unassignedExpanded = !unassignedExpanded;
            renderUnassignedPages();
        });

        $('#hozio-unassigned-filter').on('input', function() {
            unassignedFilter = $(this).val();
            renderUnassignedPages();
        });

        // ========================================
        // INITIAL RENDER
        // ========================================
        renderAccordions();
        renderExcludeTags();
        renderUnassignedPages();
    });
    </script>

    <?php
}

// Helper to collect all page IDs from accordions array
function hozio_collect_page_ids_from_accordions($items, &$ids) {
    if (!is_array($items)) return;
    foreach ($items as $item) {
        if (isset($item['page_id'])) {
            $ids[] = intval($item['page_id']);
        }
        if (isset($item['children']) && is_array($item['children'])) {
            hozio_collect_page_ids_from_accordions($item['children'], $ids);
        }
    }
}

// ========================================
// AJAX: Load page titles by IDs
// ========================================
add_action('wp_ajax_hozio_sitemap_load_titles', function() {
    check_ajax_referer('hozio_sitemap_layout_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : array();
    if (empty($ids)) wp_send_json_success(array());

    $pages = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'any',
        'post__in'       => $ids,
        'posts_per_page' => -1,
    ));

    $results = array();
    foreach ($pages as $p) {
        $parent_title = '';
        if ($p->post_parent) {
            $parent_title = get_the_title($p->post_parent);
        }
        $results[$p->ID] = array(
            'title'        => $p->post_title ? $p->post_title : '(Untitled)',
            'parent_title' => $parent_title,
            'status'       => $p->post_status,
        );
    }

    wp_send_json_success($results);
});

// ========================================
// INLINE STYLES
// ========================================
function hozio_sitemap_layout_inline_styles() {
    ?>
    <style>
        :root {
            --hozio-blue: #00A0E3;
            --hozio-blue-dark: #0081B8;
            --hozio-green: #8DC63F;
            --hozio-orange: #F7941D;
        }

        .hozio-settings-wrapper {
            background: #f9fafb;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
        }

        .hozio-header {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 50%, var(--hozio-orange) 100%);
            color: white;
            padding: 40px;
            border-radius: 8px 8px 0 0;
        }

        .hozio-header h1 {
            color: white !important;
            font-size: 32px;
            margin: 0 0 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .hozio-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            margin: 0;
        }

        .hozio-content {
            padding: 0 40px 40px;
            margin-top: -30px;
        }

        .hozio-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            border-left: 4px solid var(--hozio-blue);
        }

        .hozio-section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f3f4f6;
        }

        .hozio-section-header .dashicons {
            color: var(--hozio-blue);
            font-size: 24px;
            width: 24px;
            height: 24px;
        }

        .hozio-section-header h2 {
            margin: 0;
            font-size: 20px;
            color: #1f2937;
            font-weight: 600;
        }

        .hozio-field { margin-bottom: 20px; }
        .hozio-field-label {
            font-weight: 600;
            font-size: 15px;
            color: #1f2937;
            margin-bottom: 8px;
            display: block;
        }
        .hozio-field-description {
            color: #6b7280;
            font-size: 14px;
            margin: 8px 0 0 0;
            line-height: 1.6;
        }

        /* Toggle */
        .hozio-toggle-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .hozio-toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 32px;
        }
        .hozio-toggle-switch input { opacity: 0; width: 0; height: 0; }
        .hozio-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 32px;
        }
        .hozio-toggle-slider:before {
            position: absolute;
            content: "";
            height: 24px; width: 24px;
            left: 4px; bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .hozio-toggle-slider { background-color: var(--hozio-blue); }
        input:checked + .hozio-toggle-slider:before { transform: translateX(28px); }
        .hozio-toggle-label { font-weight: 600; font-size: 16px; color: #1f2937; }

        /* Radio group */
        .hozio-radio-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 12px;
        }
        .hozio-radio-option {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .hozio-radio-option:hover { border-color: var(--hozio-blue); background: #f0f9ff; }
        .hozio-radio-option.active { border-color: var(--hozio-blue); background: #f0f9ff; }
        .hozio-radio-option input { display: none; }
        .radio-dot {
            width: 20px; height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 2px;
            position: relative;
        }
        .hozio-radio-option.active .radio-dot { border-color: var(--hozio-blue); }
        .hozio-radio-option.active .radio-dot::after {
            content: '';
            position: absolute;
            top: 3px; left: 3px;
            width: 10px; height: 10px;
            background: var(--hozio-blue);
            border-radius: 50%;
        }
        .radio-desc { color: #6b7280; font-size: 13px; margin-top: 4px; display: block; }

        /* Builder actions */
        .hozio-builder-actions {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .hozio-builder-actions .button { display: flex; align-items: center; gap: 4px; }

        /* Accordion cards */
        .accordion-card {
            border: 1px solid #e5e7eb;
            border-left: 4px solid var(--hozio-green);
            border-radius: 8px;
            margin-bottom: 12px;
            background: white;
            transition: box-shadow 0.2s;
        }
        .accordion-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }

        .accordion-card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: #fafbfc;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid #e5e7eb;
            cursor: default;
        }

        .accordion-card-drag {
            color: #9ca3af;
            cursor: grab;
            font-size: 16px;
        }
        .accordion-card-drag:active { cursor: grabbing; }

        .accordion-card-level-badge {
            padding: 2px 8px;
            border-radius: 4px;
            color: white;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .accordion-card-title {
            font-weight: 600;
            font-size: 15px;
            color: #1f2937;
            flex: 1;
        }

        .accordion-card-count {
            color: #6b7280;
            font-size: 13px;
        }

        .accordion-card-toggle,
        .accordion-card-delete {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            color: #6b7280;
            transition: all 0.2s;
        }
        .accordion-card-toggle:hover { background: #e5e7eb; color: #1f2937; }
        .accordion-card-delete:hover { background: #fee2e2; color: #dc2626; }

        .accordion-card-body { padding: 20px; }

        .accordion-card-field { margin-bottom: 20px; }
        .accordion-card-field:last-child { margin-bottom: 0; }

        /* Page selector */
        .selected-page-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            font-size: 14px;
            color: #1e40af;
        }
        .selected-page-tag .tag-remove {
            background: none;
            border: none;
            cursor: pointer;
            padding: 2px;
            color: #60a5fa;
            border-radius: 3px;
        }
        .selected-page-tag .tag-remove:hover { background: #dbeafe; color: #1e40af; }

        /* Search inputs */
        .hozio-search-input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .hozio-search-input:focus {
            outline: none;
            border-color: var(--hozio-blue);
            box-shadow: 0 0 0 3px rgba(0, 160, 227, 0.1);
        }

        /* Search results */
        .page-search-results,
        .child-search-results,
        .hozio-search-results {
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-top: 4px;
            background: white;
        }

        .search-result-item {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
            transition: background 0.15s;
        }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: #f0f9ff; }
        .search-result-item.disabled { opacity: 0.5; cursor: not-allowed; background: #f9fafb; }
        .search-result-item.disabled:hover { background: #f9fafb; }

        .result-meta { color: #9ca3af; font-size: 12px; margin-left: 6px; }
        .result-id { float: right; }
        .assigned-label { color: #f59e0b; font-weight: 500; }
        .search-no-results { padding: 16px; text-align: center; color: #9ca3af; font-size: 14px; }

        /* Children actions */
        .children-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .children-actions .button { display: flex; align-items: center; gap: 2px; }

        /* Children list */
        .children-list { min-height: 10px; }

        .child-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .child-item:last-child { margin-bottom: 0; }

        .child-drag {
            color: #9ca3af;
            cursor: grab;
            font-size: 14px;
        }
        .child-drag:active { cursor: grabbing; }

        .child-title { flex: 1; color: #374151; font-weight: 500; }

        /* Accordion toggle button */
        .acc-toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            line-height: 1.4;
        }
        .acc-toggle-btn:hover {
            color: var(--hozio-blue);
            border-color: var(--hozio-blue);
            background: #eff6ff;
        }
        .acc-toggle-btn.is-active {
            color: white;
            background: var(--hozio-blue);
            border-color: var(--hozio-blue);
        }
        .acc-toggle-btn.is-active:hover {
            background: #dc2626;
            border-color: #dc2626;
        }
        .acc-toggle-btn svg { flex-shrink: 0; }

        .child-remove {
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            padding: 2px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .child-remove:hover { background: #fee2e2; color: #dc2626; }

        .children-empty { padding: 16px; text-align: center; color: #9ca3af; font-size: 14px; }

        /* Child search panel */
        .child-search-panel {
            margin-top: 12px;
            padding: 16px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .child-search-panel .child-search-results { margin-top: 8px; }
        .child-search-panel .close-child-search { margin-top: 8px; }

        /* Empty state */
        .hozio-empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        .hozio-empty-state p { margin: 8px 0; }

        /* Exclude search wrapper */
        .hozio-exclude-search-wrapper { margin-bottom: 12px; }

        /* Tags */
        .hozio-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .hozio-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 6px;
            font-size: 13px;
            color: #92400e;
        }
        .hozio-tag .tag-remove {
            background: none;
            border: none;
            cursor: pointer;
            color: #b45309;
            font-size: 16px;
            line-height: 1;
            padding: 0 2px;
            border-radius: 3px;
        }
        .hozio-tag .tag-remove:hover { background: #fde68a; }

        /* Submit wrapper */
        .hozio-submit-wrapper {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 100;
        }

        .hozio-submit-btn {
            background: linear-gradient(135deg, var(--hozio-blue), var(--hozio-green)) !important;
            border: none !important;
            color: white !important;
            padding: 12px 30px !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }
        .hozio-submit-btn:hover { opacity: 0.9; transform: translateY(-1px); }

        .hozio-reset-btn {
            display: flex;
            align-items: center;
            gap: 4px;
            color: #dc2626 !important;
            border-color: #fecaca !important;
        }
        .hozio-reset-btn:hover {
            background: #fee2e2 !important;
            border-color: #dc2626 !important;
        }

        /* Sortable placeholders */
        .accordion-card-placeholder {
            border: 2px dashed #93c5fd;
            border-radius: 8px;
            margin-bottom: 12px;
            height: 50px;
            background: #eff6ff;
        }
        .child-item-placeholder {
            border: 2px dashed #93c5fd;
            border-radius: 6px;
            margin-bottom: 6px;
            height: 40px;
            background: #eff6ff;
        }

        /* Nested accordion cards indentation */
        .accordion-card .accordion-card {
            margin-left: 20px;
            margin-top: 8px;
        }

        /* Tab bar */
        .hozio-tab-bar {
            display: flex;
            gap: 0;
            background: white;
            padding: 0 40px;
            margin-top: -30px;
            border-bottom: 2px solid #e5e7eb;
            position: relative;
            z-index: 1;
        }
        .hozio-tab-bar + .hozio-content {
            margin-top: 0;
            padding-top: 24px;
        }
        .hozio-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 16px 24px;
            font-size: 15px;
            font-weight: 500;
            color: #6b7280;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .hozio-tab:hover {
            color: var(--hozio-blue);
            background: #f0f9ff;
            text-decoration: none;
        }
        .hozio-tab:focus {
            outline: none;
            box-shadow: none;
            text-decoration: none;
        }
        .hozio-tab.active {
            color: var(--hozio-blue);
            border-bottom-color: var(--hozio-blue);
            font-weight: 600;
        }
        .hozio-tab .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        /* Unassigned pages section */
        .unassigned-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 8px;
            background: #8b5cf6;
            color: white;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
        }

        .unassigned-pages-list {
            max-height: 420px;
            overflow-y: auto;
        }

        .unassigned-page-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .unassigned-page-item:last-child {
            border-bottom: none;
        }

        .unassigned-page-title {
            color: #374151;
            font-weight: 500;
        }

        .unassigned-page-parent {
            color: #9ca3af;
            font-size: 12px;
        }

        .unassigned-empty {
            padding: 20px;
            text-align: center;
            color: #9ca3af;
            font-size: 14px;
        }

        .unassigned-loading {
            padding: 20px;
            text-align: center;
            color: #9ca3af;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .hozio-tab-bar {
                padding: 0 20px;
            }
            .hozio-tab {
                padding: 12px 16px;
                font-size: 14px;
            }
        }
    </style>
    <?php
}
