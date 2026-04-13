<?php


if (!defined('ABSPATH')) exit;

// Output current page ID for front-end JS (used by HTML carousel widgets)
add_action('wp_head', function() {
    if (!is_singular()) return;
    echo '<script>var hozioPageId = ' . intval(get_queried_object_id()) . ';</script>' . "\n";
}, 1);

add_action('add_meta_boxes', 'hozio_add_page_config_meta_box');
function hozio_add_page_config_meta_box() {
    add_meta_box(
        'hozio_page_loop_config',
        '🎯 Loop Configuration',
        'hozio_page_config_meta_box_callback',
        'page',
        'side',  // Keep in sidebar but will appear after page attributes
        'low'    // Low priority = appears lower
    );
}

function hozio_page_config_meta_box_callback($post) {
    wp_nonce_field('hozio_page_config_nonce', 'hozio_page_config_nonce');
    
    $selected_config = get_post_meta($post->ID, 'hozio_selected_loop_config', true);
    $configs = get_option('hozio_loop_configurations', array());
    
    ?>
    <div style="padding: 10px 0;">
        <p style="margin-bottom: 12px; color: #666; font-size: 13px;">
            Choose which loop configuration this page should use for its loop grids/carousels.
        </p>
        
        <label for="hozio_selected_loop_config" style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px;">
            Configuration:
        </label>
        
        <select name="hozio_selected_loop_config" id="hozio_selected_loop_config" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; font-size: 14px;">
            <option value="">-- No Configuration (Default Query) --</option>
            <?php
            if (!empty($configs) && is_array($configs)) {
                foreach ($configs as $config) {
                    if (!empty($config['name'])) {
                        $selected = ($selected_config === $config['name']) ? 'selected' : '';
                        echo '<option value="' . esc_attr($config['name']) . '" ' . $selected . '>' . esc_html($config['name']) . '</option>';
                    }
                }
            }
            ?>
        </select>
        
        <?php if (!empty($selected_config)): ?>
            <div style="margin-top: 12px; padding: 10px; background: #d4edda; border-left: 3px solid #28a745; font-size: 12px;">
                <strong>✓ Active:</strong> <?php echo esc_html($selected_config); ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($configs)): ?>
            <p style="margin-top: 12px; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107; font-size: 12px;">
                <strong>No configurations found.</strong><br>
                <a href="<?php echo admin_url('admin.php?page=hozio-loop-configurations'); ?>" target="_blank">Create configurations →</a>
            </p>
        <?php else: ?>
            <p style="margin-top: 12px; padding: 10px; background: #d1ecf1; border-left: 3px solid #0c5460; font-size: 12px;">
                <strong>💡 Tip:</strong> This applies to all loop grids and carousels on this page (excludes header/footer).
                <a href="<?php echo admin_url('admin.php?page=hozio-loop-configurations'); ?>" target="_blank" style="display: block; margin-top: 6px;">Manage configurations →</a>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

// Save page configuration
add_action('save_post', 'hozio_save_page_config');
function hozio_save_page_config($post_id) {
    if (!isset($_POST['hozio_page_config_nonce']) || !wp_verify_nonce($_POST['hozio_page_config_nonce'], 'hozio_page_config_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['hozio_selected_loop_config'])) {
        $config_name = sanitize_text_field($_POST['hozio_selected_loop_config']);
        if (!empty($config_name)) {
            update_post_meta($post_id, 'hozio_selected_loop_config', $config_name);
        } else {
            delete_post_meta($post_id, 'hozio_selected_loop_config');
        }
    }
}

// ========================================
// INTERCEPT LOOP QUERIES - WITH HEADER/FOOTER EXCLUSION
// ========================================
add_action('elementor/query/query_args', 'hozio_intercept_loop_widget_query', 10, 2);

function hozio_intercept_loop_widget_query($query_args, $widget) {
    // Only target loop widgets
    $widget_name = $widget->get_name();
    if (!in_array($widget_name, ['loop-grid', 'loop-carousel'])) {
        return $query_args;
    }
    
    // Skip if widget is inside a header, footer, or other theme builder template
    if (hozio_is_theme_builder_context()) {
        return $query_args;
    }
    
    // Get current page ID - try multiple methods
    $current_page_id = null;
    
    // Method 1: Check if we're in Elementor preview
    if (isset($_GET['elementor-preview'])) {
        $current_page_id = absint($_GET['elementor-preview']);
    }
    
    // Method 2: Get from global post
    if (!$current_page_id) {
        global $post;
        if ($post && isset($post->ID)) {
            $current_page_id = $post->ID;
        }
    }
    
    // Method 3: get_the_ID()
    if (!$current_page_id) {
        $current_page_id = get_the_ID();
    }
    
    // Method 4: Check queried object
    if (!$current_page_id) {
        $queried_object = get_queried_object();
        if ($queried_object && isset($queried_object->ID)) {
            $current_page_id = $queried_object->ID;
        }
    }
    
    if (!$current_page_id) {
        return $query_args;
    }
    
    // Get configuration from page meta
    $config_name = get_post_meta($current_page_id, 'hozio_selected_loop_config', true);
    
    if (empty($config_name)) {
        return $query_args;
    }
    
    // Apply configuration
    $query_args = hozio_apply_loop_configuration($config_name, $query_args, $current_page_id);
    
    return $query_args;
}

/**
 * Check if we're currently rendering inside a theme builder template (header/footer/etc)
 */
function hozio_is_theme_builder_context() {
    // Check if Elementor Pro is active
    if (!class_exists('\ElementorPro\Plugin')) {
        return false;
    }
    
    // Method 1: Check Elementor's document stack for theme builder templates
    if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->documents) {
        $current_doc = \Elementor\Plugin::$instance->documents->get_current();
        
        if ($current_doc) {
            // Get the document's post ID
            $doc_post_id = $current_doc->get_main_id();
            
            // Check if this is an elementor_library (theme builder template)
            if (get_post_type($doc_post_id) === 'elementor_library') {
                $template_type = get_post_meta($doc_post_id, '_elementor_template_type', true);
                
                // Skip for headers, footers, and other theme parts
                $skip_types = ['header', 'footer', 'section', 'popup'];
                if (in_array($template_type, $skip_types)) {
                    return true;
                }
            }
        }
    }
    
    // Method 2: Check the theme builder locations module
    if (class_exists('\ElementorPro\Modules\ThemeBuilder\Module')) {
        $theme_builder = \ElementorPro\Modules\ThemeBuilder\Module::instance();
        $locations = $theme_builder->get_locations_manager();
        
        if ($locations) {
            $current_location = $locations->get_current_location();
            
            // Skip if we're in a header or footer location
            if (in_array($current_location, ['header', 'footer'])) {
                return true;
            }
        }
    }
    
    return false;
}

// PERFORMANCE: Cached loop configurations to avoid repeated get_option() calls
function hozio_get_loop_configurations() {
    static $configs = null;
    if ($configs === null) {
        $configs = get_option('hozio_loop_configurations', array());
    }
    return $configs;
}

function hozio_apply_loop_configuration($config_name, $query_args, $current_page_id) {
    // Use cached configurations to avoid repeated DB calls
    $configs = hozio_get_loop_configurations();

    if (empty($configs)) {
        return $query_args;
    }
    
    // Find the selected configuration
    $selected_config = null;
    foreach ($configs as $config) {
        if (isset($config['name']) && $config['name'] === $config_name) {
            $selected_config = $config;
            break;
        }
    }
    
    if (!$selected_config) {
        return $query_args;
    }
    
    $filter_mode    = isset($selected_config['filter_mode']) ? $selected_config['filter_mode'] : 'taxonomy';
    $taxonomy       = isset($selected_config['taxonomy']) ? $selected_config['taxonomy'] : '';
    $term_ids       = isset($selected_config['terms']) ? $selected_config['terms'] : array();
    $excluded_pages = isset($selected_config['exclude']) ? $selected_config['exclude'] : array();

    // Pages mode: show only explicitly selected pages
    if ( $filter_mode === 'pages' ) {
        $page_ids = isset($selected_config['page_ids']) ? array_map('intval', $selected_config['page_ids']) : array();
        $page_ids = array_values( array_diff( $page_ids, array( $current_page_id ) ) );
        if ( empty($page_ids) ) return $query_args;
        $query_args['post_type'] = 'page';
        $query_args['post__in']  = $page_ids;
        unset( $query_args['post__not_in'], $query_args['tax_query'] );
        hozio_log( 'Loop Config Applied (pages mode): ' . $config_name, 'LoopConfig' );
        return $query_args;
    }

    if (empty($taxonomy) || empty($term_ids)) {
        return $query_args;
    }
    
    // Build exclusion list
    $post__not_in = isset($query_args['post__not_in']) ? $query_args['post__not_in'] : array();
    $post__not_in[] = $current_page_id; // Always exclude current page
    
    if (!empty($excluded_pages)) {
        $post__not_in = array_merge($post__not_in, array_map('intval', $excluded_pages));
    }
    
    $post__not_in = array_unique(array_filter($post__not_in));
    
    // Override query args
    $query_args['post_type'] = 'page';
    $query_args['post__not_in'] = $post__not_in;
    
    // Build tax query
    if (count($term_ids) > 1) {
        $query_args['tax_query'] = array(
            'relation' => 'OR'
        );
        foreach ($term_ids as $term_id) {
            $query_args['tax_query'][] = array(
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => intval($term_id),
                'operator' => 'IN',
            );
        }
    } else {
        $query_args['tax_query'] = array(
            array(
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => intval($term_ids[0]),
                'operator' => 'IN',
            ),
        );
    }
    
    // Debug logging (uses HOZIO_DEBUG, not WP_DEBUG)
    hozio_log('Loop Config Applied: ' . $config_name, 'LoopConfig');
    hozio_log('Taxonomy: ' . $taxonomy . ' | Terms: ' . implode(', ', $term_ids) . ' | Excluded: ' . implode(', ', $post__not_in), 'LoopConfig');
    
    return $query_args;
}

// ========================================
// ========================================
// CLAUDE PROMPT HELPERS
// ========================================
function hozio_get_config_sample_pages($config) {
    $filter_mode = isset($config['filter_mode']) ? $config['filter_mode'] : 'taxonomy';
    if ( $filter_mode === 'pages' ) {
        $page_ids = !empty($config['page_ids']) ? array_map('intval', $config['page_ids']) : array();
        if ( empty($page_ids) ) return array();
        $posts = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'post__in'       => $page_ids,
            'orderby'        => 'post__in',
        ));
        $pages = array();
        foreach ($posts as $post) {
            $pages[] = array(
                'title'     => $post->post_title,
                'url'       => get_permalink($post->ID),
                'thumbnail' => get_the_post_thumbnail_url($post->ID, 'medium') ?: 'none',
            );
        }
        return $pages;
    }
    if (empty($config['terms']) || empty($config['taxonomy'])) return array();
    $tax_query = array('relation' => 'OR');
    foreach ($config['terms'] as $term_id) {
        $tax_query[] = array('taxonomy' => $config['taxonomy'], 'field' => 'term_id', 'terms' => intval($term_id));
    }
    $args = array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        'tax_query'      => $tax_query,
    );
    if (!empty($config['exclude'])) {
        $args['post__not_in'] = array_map('intval', $config['exclude']);
    }
    $posts = get_posts($args);
    $pages = array();
    foreach ($posts as $post) {
        $pages[] = array(
            'title'     => $post->post_title,
            'url'       => get_permalink($post->ID),
            'thumbnail' => get_the_post_thumbnail_url($post->ID, 'medium') ?: 'none',
        );
    }
    return $pages;
}

function hozio_build_claude_prompt_single($config) {
    $site_url  = get_site_url();
    $ajax_url  = admin_url('admin-ajax.php');
    $name      = $config['name'] ?? 'Unnamed';
    $taxonomy  = $config['taxonomy'] ?? '';
    $tax_label = $taxonomy === 'town_taxonomies' ? 'Town Taxonomies'
               : ($taxonomy === 'parent_pages'   ? 'Page Taxonomies' : $taxonomy);

    $term_names = array();
    foreach (($config['terms'] ?? array()) as $term_id) {
        $term = get_term(intval($term_id), $taxonomy);
        $term_names[] = ($term && !is_wp_error($term)) ? $term->name : "Term #$term_id";
    }
    $exclude_titles = array();
    foreach (($config['exclude'] ?? array()) as $page_id) {
        $page = get_post(intval($page_id));
        $exclude_titles[] = $page ? $page->post_title : "Page #$page_id";
    }
    $sample_pages = hozio_get_config_sample_pages($config);

    $lines = array(
        '=== HOZIO PRO — LOOP CONFIGURATION SETUP ===',
        '',
        'You are building an HTML carousel widget for a WordPress site running the Hozio Pro plugin.',
        'This prompt gives you everything you need to build it and use it in an Elementor HTML widget.',
        '',
        'HOW IT WORKS:',
        '• Each WordPress page can have a Loop Configuration assigned to it in the page editor sidebar',
        '• When your HTML widget loads, it fetches the matching pages from a WordPress AJAX endpoint',
        '• The current page ID is always available as window.hozioPageId (output by the plugin on every page)',
        '',
        'SITE INFO:',
        "• Site URL: $site_url",
        "• AJAX Endpoint: $ajax_url",
        '',
        'FETCH CALL:',
        "fetch('$ajax_url', {",
        "    method: 'POST',",
        "    headers: {'Content-Type': 'application/x-www-form-urlencoded'},",
        "    body: 'action=hozio_get_loop_results&page_id=' + window.hozioPageId",
        '})',
        '.then(r => r.json())',
        '.then(data => {',
        '    if (data.success) {',
        '        const pages = data.data;',
        '        // each page: { id, title, url, excerpt, thumbnail }',
        '    }',
        '});',
        '',
        "CONFIGURATION: \"$name\"",
        '• Taxonomy: ' . $tax_label,
        '• Terms included: ' . (empty($term_names)     ? 'None' : implode(', ', $term_names)),
        '• Excluded pages: ' . (empty($exclude_titles) ? 'None' : implode(', ', $exclude_titles)),
        '',
    );
    if (!empty($sample_pages)) {
        $lines[] = 'SAMPLE PAGES IN THIS CONFIG:';
        foreach ($sample_pages as $p) {
            $lines[] = "  • {$p['title']} | {$p['url']} | thumbnail: {$p['thumbnail']}";
        }
    } else {
        $lines[] = 'SAMPLE PAGES: (none found — assign terms and save to see results)';
    }
    $lines = array_merge($lines, array(
        '',
        'IMPLEMENTATION NOTES:',
        '• Use window.hozioPageId — it is already output by the plugin on every front-end page',
        '• The widget goes in an Elementor HTML widget — no PHP available, use JavaScript only',
        '• jQuery is available (WordPress loads it), or you can use native fetch',
        '• The endpoint returns an empty success array if no config is assigned to the page',
        '',
        'BUILD ME:',
        'A responsive HTML carousel that:',
        '1. On load, calls the AJAX endpoint with window.hozioPageId',
        '2. Renders matching pages as cards with: featured image, title, excerpt, and a "Learn More" link',
        '3. Has previous/next navigation buttons',
        '4. Shows a loading state while fetching',
        '5. Gracefully handles empty results',
        '6. Is fully self-contained for use in an Elementor HTML widget',
        '7. Works on mobile (responsive, ideally swipeable)',
    ));
    return implode("\n", $lines);
}

function hozio_build_claude_prompt_all($configs) {
    $site_url = get_site_url();
    $ajax_url = admin_url('admin-ajax.php');
    $count    = count($configs);

    $lines = array(
        '=== HOZIO PRO — ALL LOOP CONFIGURATIONS SETUP ===',
        '',
        'You are building an HTML carousel widget for a WordPress site running the Hozio Pro plugin.',
        'This prompt gives you everything you need to build it and use it in an Elementor HTML widget.',
        '',
        'HOW IT WORKS:',
        '• Each WordPress page can have a Loop Configuration assigned to it in the page editor sidebar',
        '• When your HTML widget loads, it fetches the matching pages from a WordPress AJAX endpoint',
        '• The same HTML template works on every page — it automatically shows the right pages for each',
        '• The current page ID is always available as window.hozioPageId (output by the plugin on every page)',
        '',
        'SITE INFO:',
        "• Site URL: $site_url",
        "• AJAX Endpoint: $ajax_url",
        '',
        'FETCH CALL:',
        "fetch('$ajax_url', {",
        "    method: 'POST',",
        "    headers: {'Content-Type': 'application/x-www-form-urlencoded'},",
        "    body: 'action=hozio_get_loop_results&page_id=' + window.hozioPageId",
        '})',
        '.then(r => r.json())',
        '.then(data => {',
        '    if (data.success) {',
        '        const pages = data.data;',
        '        // each page: { id, title, url, excerpt, thumbnail }',
        '    }',
        '});',
        '',
        "AVAILABLE CONFIGURATIONS ($count total):",
        '',
    );

    foreach ($configs as $i => $config) {
        $name      = $config['name'] ?? 'Unnamed';
        $taxonomy  = $config['taxonomy'] ?? '';
        $tax_label = $taxonomy === 'town_taxonomies' ? 'Town Taxonomies'
                   : ($taxonomy === 'parent_pages'   ? 'Page Taxonomies' : $taxonomy);

        $term_names = array();
        foreach (($config['terms'] ?? array()) as $term_id) {
            $term = get_term(intval($term_id), $taxonomy);
            $term_names[] = ($term && !is_wp_error($term)) ? $term->name : "Term #$term_id";
        }
        $exclude_titles = array();
        foreach (($config['exclude'] ?? array()) as $page_id) {
            $page = get_post(intval($page_id));
            $exclude_titles[] = $page ? $page->post_title : "Page #$page_id";
        }
        $sample_pages = hozio_get_config_sample_pages($config);

        $lines[] = '--- Config ' . ($i + 1) . ': "' . $name . '" ---';
        $lines[] = '• Taxonomy: ' . $tax_label;
        $lines[] = '• Terms included: ' . (empty($term_names)     ? 'None' : implode(', ', $term_names));
        $lines[] = '• Excluded pages: ' . (empty($exclude_titles) ? 'None' : implode(', ', $exclude_titles));
        if (!empty($sample_pages)) {
            $lines[] = '• Sample pages:';
            foreach ($sample_pages as $p) {
                $lines[] = "    - {$p['title']} | {$p['url']} | thumbnail: {$p['thumbnail']}";
            }
        } else {
            $lines[] = '• Sample pages: (none found)';
        }
        $lines[] = '';
    }

    $lines = array_merge($lines, array(
        'IMPLEMENTATION NOTES:',
        '• Use window.hozioPageId — it is already output by the plugin on every front-end page',
        '• The widget goes in an Elementor HTML widget — no PHP available, use JavaScript only',
        '• jQuery is available (WordPress loads it), or you can use native fetch',
        '• The endpoint returns an empty success array if no config is assigned to the page',
        '• One HTML template works across ALL pages — the correct config is auto-applied per page',
        '',
        'BUILD ME:',
        'A responsive HTML carousel that:',
        '1. On load, calls the AJAX endpoint with window.hozioPageId',
        '2. Renders matching pages as cards with: featured image, title, excerpt, and a "Learn More" link',
        '3. Has previous/next navigation buttons',
        '4. Shows a loading state while fetching',
        '5. Gracefully handles empty results',
        '6. Is fully self-contained for use in an Elementor HTML widget',
        '7. Works on mobile (responsive, ideally swipeable)',
    ));
    return implode("\n", $lines);
}

// RENDER CONFIGURATIONS PAGE
// ========================================
function hozio_loop_configs_render_page() {
    // Handle save
    if (isset($_POST['hozio_save_configs']) && check_admin_referer('hozio_configs_save', 'hozio_configs_nonce')) {
        if (isset($_POST['hozio_configs']) && is_array($_POST['hozio_configs'])) {
            $configs = array();
            
            foreach ($_POST['hozio_configs'] as $index => $config) {
                if (!empty($config['name'])) {
                    $fm = $config['filter_mode'] ?? 'taxonomy';
                    $configs[] = array(
                        'name'        => sanitize_text_field($config['name']),
                        'filter_mode' => in_array($fm, array('taxonomy', 'pages')) ? sanitize_key($fm) : 'taxonomy',
                        'taxonomy'    => sanitize_text_field($config['taxonomy'] ?? ''),
                        'terms'       => isset($config['terms'])    ? array_map('intval', $config['terms'])    : array(),
                        'page_ids'    => isset($config['page_ids']) ? array_map('intval', $config['page_ids']) : array(),
                        'exclude'     => isset($config['exclude'])  ? array_map('intval', $config['exclude'])  : array(),
                    );
                }
            }
            
            update_option('hozio_loop_configurations', $configs);
            echo '<div class="notice notice-success is-dismissible"><p><strong>✓ Saved!</strong> Your configurations have been updated.</p></div>';
        }
    }
    
    $configs = get_option('hozio_loop_configurations', array());
    
    if (empty($configs)) {
        $configs = array(
            array(
                'name' => 'My First Configuration',
                'taxonomy' => '',
                'terms' => array(),
                'exclude' => array(),
            ),
        );
    }
    
    ?>
    <div class="wrap hozio-loop-configs-wrap">
        <div class="hozio-header">
            <div class="hozio-header-content">
                <h1 class="hozio-title">
                    <span class="hozio-icon">🎯</span>
                    Loop Configurations
                </h1>
                <p class="hozio-subtitle">Create reusable filter configurations for Elementor loop widgets</p>
            </div>
        </div>
        
        <div class="hozio-info-card">
            <div class="hozio-info-header">
                <span class="dashicons dashicons-info-outline"></span>
                <strong>Quick Start Guide</strong>
            </div>
            <div class="hozio-info-steps">
                <div class="hozio-step">
                    <span class="step-number">1</span>
                    <span>Create configurations with unique names</span>
                </div>
                <div class="hozio-step">
                    <span class="step-number">2</span>
                    <span>Select taxonomy and filter terms</span>
                </div>
                <div class="hozio-step">
                    <span class="step-number">3</span>
                    <span>Save and assign to pages in page editor</span>
                </div>
                <div class="hozio-step">
                    <span class="step-number">4</span>
                    <span>Loop widgets will automatically use the configuration</span>
                </div>
            </div>
            <div style="margin-top: 16px; padding: 12px; background: #fef3c7; border-radius: 6px; font-size: 13px; color: #92400e;">
                <strong>📌 Note:</strong> Configurations only apply to page content. Loop widgets in headers, footers, and popups are not affected.
            </div>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('hozio_configs_save', 'hozio_configs_nonce'); ?>
            
            <div id="hozio-configs-container">
                <?php foreach ($configs as $index => $config): ?>
                    <?php hozio_loop_configs_render_card($index, $config); ?>
                <?php endforeach; ?>
            </div>
            
            <div class="hozio-actions-bar">
                <button type="button" id="hozio-add-config" class="button button-secondary hozio-btn-add">
                    <span class="dashicons dashicons-plus-alt2"></span> Add Configuration
                </button>

                <button type="submit" name="hozio_save_configs" class="button button-primary hozio-btn-save">
                    <span class="dashicons dashicons-saved"></span> Save All Configurations
                </button>

                <button type="button" id="hozio-copy-all-claude" class="button hozio-btn-copy-claude">
                    <span class="dashicons dashicons-clipboard"></span> Copy All Setups
                </button>
                
                <span class="hozio-count-badge"><?php echo count($configs); ?> configuration<?php echo count($configs) !== 1 ? 's' : ''; ?></span>
            </div>
        </form>
    </div>
    


    <style>
        .hozio-loop-configs-wrap {
            max-width: 1200px;
            margin: 20px auto 40px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .hozio-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .hozio-title {
            margin: 0 0 8px 0;
            font-size: 32px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hozio-icon {
            font-size: 36px;
        }
        
        .hozio-subtitle {
            margin: 0;
            font-size: 16px;
            opacity: 0.95;
            font-weight: 400;
        }
        
        .hozio-info-card {
            background: #f0f9ff;
            border: 2px solid #bae6fd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .hozio-info-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            color: #0369a1;
            margin-bottom: 16px;
        }
        
        .hozio-info-header .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        
        .hozio-info-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        
        .hozio-step {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #0c4a6e;
            font-size: 14px;
        }
        
        .step-number {
            background: #0ea5e9;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }
        
        #hozio-configs-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .hozio-config-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .hozio-config-card:hover {
            border-color: #00a0e3;
            box-shadow: 0 8px 24px rgba(0, 160, 227, 0.15);
        }
        
        .hozio-config-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
        }
        
        .hozio-config-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .hozio-accordion-icon {
            color: #64748b;
            font-size: 22px;
            transition: transform 0.2s;
        }
        
        .hozio-config-card.collapsed .hozio-accordion-icon {
            transform: rotate(-90deg);
        }
        
        .hozio-config-card.collapsed .hozio-config-body {
            display: none;
        }
        
        .hozio-config-title-display {
            font-weight: 700;
            font-size: 14px;
            color: #1e293b;
        }
        
        .hozio-remove-config {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 4px 7px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
        }

        .hozio-remove-config:hover {
            background: #dc2626;
            color: white;
        }

        .hozio-remove-config .dashicons {
            width: 16px;
            height: 16px;
            font-size: 16px;
        }
        
        .hozio-config-body {
            padding: 14px 16px;
            background: #ffffff;
        }

        /* Name + taxonomy side by side */
        .hozio-fields-row {
            display: flex;
            gap: 16px;
            margin-bottom: 4px;
            align-items: flex-end;
        }
        .hozio-fields-row .hozio-input-group {
            flex: 1;
            min-width: 0;
        }

        /* Compact section label */
        .hozio-step-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 10px;
        }
        .hozio-step-label em {
            font-weight: 400;
            text-transform: none;
            letter-spacing: 0;
        }
        .hozio-step-label .hozio-terms-count,
        .hozio-step-label .hozio-exclude-count {
            font-size: 11px;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 10px;
            color: #475569;
            font-weight: 600;
            text-transform: none;
            letter-spacing: 0;
        }

        /* Header summary badges */
        .hozio-config-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: 8px;
        }
        .hozio-card-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            white-space: nowrap;
        }
        .hozio-badge-tax   { background: #ede9fe; color: #6d28d9; }
        .hozio-badge-terms { background: #d1fae5; color: #065f46; }
        .hozio-badge-exclude { background: #fee2e2; color: #991b1b; }
        .hozio-badge-pages { background: #dbeafe; color: #1e40af; }

        /* Sections */
        .hozio-config-step {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
        }

        /* Input fields */
        .hozio-input-group {
            margin-bottom: 0;
        }
        
        .hozio-input-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
            font-size: 12px;
            color: #64748b;
        }
        
        .hozio-input-group input[type="text"],
        .hozio-input-group select {
            width: 100%;
            max-width: none;
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            transition: all 0.2s;
        }
        
        .hozio-input-group input[type="text"]:hover,
        .hozio-input-group select:hover {
            border-color: #cbd5e1;
        }
        
        .hozio-input-group input[type="text"]:focus,
        .hozio-input-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .hozio-taxonomy-wrapper {
            position: relative;
            max-width: 500px;
        }
        
        .hozio-taxonomy-wrapper::after {
            content: '\f140';
            font-family: dashicons;
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
            font-size: 20px;
        }
        
        .hozio-taxonomy-select {
            appearance: none;
            padding-right: 40px !important;
            cursor: pointer;
        }
        
        /* Selected items showcase — compact chip strip */
        .hozio-selected-showcase {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            align-items: center;
            margin-bottom: 6px;
            min-height: 0;
        }

        .hozio-showcase-content {
            display: contents;
        }

        .hozio-showcase-empty {
            color: #94a3b8;
            font-size: 12px;
            font-style: italic;
        }

        .hozio-selected-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #eff6ff;
            color: #1e40af;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #bfdbfe;
            transition: background 0.15s;
        }

        .hozio-selected-tag:hover {
            background: #dbeafe;
        }

        .hozio-tag-remove {
            cursor: pointer;
            color: #93c5fd;
            font-weight: bold;
            font-size: 14px;
            line-height: 1;
            transition: color 0.15s;
        }

        .hozio-tag-remove:hover {
            color: #dc2626;
        }
        
        /* Selection area */
        .hozio-selection-area {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 16px;
        }
        
        .hozio-search-box {
            padding: 6px 0;
        }

        .hozio-search-input {
            width: 100%;
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
            transition: border-color 0.2s;
        }

        .hozio-search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .hozio-checkbox-list {
            max-height: 160px;
            overflow-y: auto;
            padding: 4px 0;
            background: white;
        }

        .hozio-checkbox-item {
            padding: 5px 8px;
            margin-bottom: 1px;
            border-radius: 5px;
            transition: background 0.15s;
            cursor: pointer;
            display: flex;
            align-items: center;
            border: 1px solid transparent;
        }

        .hozio-checkbox-item:hover {
            background: #f0f9ff;
            border-color: #bfdbfe;
        }

        .hozio-checkbox-item input[type="checkbox"] {
            width: 14px;
            height: 14px;
            margin-right: 8px;
            cursor: pointer;
            accent-color: #3b82f6;
            flex-shrink: 0;
        }

        .hozio-checkbox-item label {
            cursor: pointer;
            margin: 0;
            font-weight: 500;
            font-size: 13px;
            color: #334155;
            flex: 1;
        }

        .hozio-checkbox-item.checked {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .hozio-checkbox-item.checked label {
            color: #1e40af;
            font-weight: 600;
        }

        .hozio-no-results {
            padding: 16px;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
        }
        
        .hozio-selection-footer {
            padding: 12px 16px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-top: 2px solid #bae6fd;
            font-size: 13px;
            color: #0369a1;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Actions bar */
        .hozio-actions-bar {
            background: white;
            padding: 24px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            display: flex;
            gap: 16px;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .hozio-btn-add {
            height: 48px;
            padding: 0 24px;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            transition: all 0.2s;
            background: white;
        }
        
        .hozio-btn-add:hover {
            border-color: #3b82f6;
            color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }
        
        .hozio-btn-save {
            height: 48px;
            padding: 0 32px;
            font-size: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            border-radius: 10px;
            transition: all 0.2s;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .hozio-btn-save:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }
        
        .hozio-count-badge {
            margin-left: auto;
            background: #f3f4f6;
            color: #6b7280;
            padding: 10px 20px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 700;
        }

        .hozio-btn-copy-claude {
            height: 48px;
            padding: 0 24px;
            font-size: 15px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
        }
        .hozio-btn-copy-claude:hover {
            background: linear-gradient(135deg, #6d28d9 0%, #4c1d95 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(124, 58, 237, 0.4);
        }
        .hozio-btn-copy-claude.hozio-copied {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }

        .hozio-config-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hozio-copy-config-claude {
            background: #ede9fe;
            color: #7c3aed;
            border: none;
            padding: 4px 7px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
        }
        .hozio-copy-config-claude:hover {
            background: #7c3aed;
            color: white;
        }
        .hozio-copy-config-claude.hozio-copied {
            background: #059669;
            color: white;
        }
        .hozio-copy-config-claude .dashicons {
            width: 16px;
            height: 16px;
            font-size: 16px;
        }

        .hozio-checkbox-list::-webkit-scrollbar {
            width: 10px;
        }
        
        .hozio-checkbox-list::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        .hozio-checkbox-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 5px;
        }
        
        .hozio-checkbox-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        @media (max-width: 768px) {
            .hozio-config-body {
                padding: 20px;
            }
            
            .hozio-step-content {
                margin-left: 0;
            }
            
            .hozio-step-description {
                margin-left: 0;
            }
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        var configIndex = <?php echo count($configs); ?>;
        
        // Add new configuration
        $('#hozio-add-config').on('click', function() {
            var configName = 'Configuration ' + (configIndex + 1);
            var newConfig = `
                <div class="hozio-config-card" data-index="${configIndex}">
                    <div class="hozio-config-header">
                        <div class="hozio-config-header-left">
                            <span class="dashicons dashicons-arrow-down-alt2 hozio-accordion-icon"></span>
                            <span class="hozio-config-title-display">${configName}</span>
                            <span class="hozio-config-meta"></span>
                        </div>
                        <div class="hozio-config-header-actions">
                            <button type="button" class="hozio-copy-config-claude" title="Copy Setup Prompt">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                            <button type="button" class="hozio-remove-config">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <div class="hozio-config-body">
                        <div class="hozio-fields-row">
                            <div class="hozio-input-group">
                                <label>Configuration Name</label>
                                <input type="text"
                                       name="hozio_configs[${configIndex}][name]"
                                       value="${configName}"
                                       class="hozio-config-name-input"
                                       placeholder="e.g., Airport Services, Town Pages, etc.">
                            </div>
                            <div class="hozio-input-group">
                                <label>Filter Type</label>
                                <select name="hozio_configs[${configIndex}][filter_mode]" class="hozio-filter-mode-select">
                                    <option value="taxonomy">By Taxonomy</option>
                                    <option value="pages">By Specific Pages</option>
                                </select>
                            </div>
                            <div class="hozio-input-group hozio-taxonomy-col">
                                <label>Taxonomy</label>
                                <select name="hozio_configs[${configIndex}][taxonomy]" class="hozio-taxonomy-select">
                                    <option value="">Select a taxonomy...</option>
                                    <option value="parent_pages">Page Taxonomies</option>
                                    <option value="town_taxonomies">Town Taxonomies</option>
                                </select>
                            </div>
                        </div>
                        <div class="hozio-taxonomy-section">
                            <div class="hozio-config-step hozio-terms-field" style="display:none;">
                                <div class="hozio-step-label">
                                    <span>Terms to Include</span>
                                    <span class="hozio-terms-count">0 selected</span>
                                </div>
                                <div class="hozio-selected-showcase hozio-selected-terms-display">
                                    <div class="hozio-showcase-content">
                                        <div class="hozio-showcase-empty">No terms selected yet</div>
                                    </div>
                                </div>
                                <div class="hozio-search-box">
                                    <input type="text" class="hozio-search-input hozio-terms-search" placeholder="🔍 Search terms...">
                                </div>
                                <div class="hozio-checkbox-list hozio-terms-list"></div>
                            </div>
                            <div class="hozio-config-step hozio-exclude-field" style="display:none;">
                                <div class="hozio-step-label">
                                    <span>Exclude Pages <em>(optional)</em></span>
                                    <span class="hozio-exclude-count">0 excluded</span>
                                </div>
                                <div class="hozio-selected-showcase hozio-selected-exclude-display">
                                    <div class="hozio-showcase-content">
                                        <div class="hozio-showcase-empty">No pages excluded</div>
                                    </div>
                                </div>
                                <div class="hozio-search-box">
                                    <input type="text" class="hozio-search-input hozio-exclude-search" placeholder="🔍 Search pages...">
                                </div>
                                <div class="hozio-checkbox-list hozio-exclude-list"></div>
                            </div>
                        </div>
                        <div class="hozio-pages-section" style="display:none;">
                            <div class="hozio-config-step" style="margin-top:10px;padding-top:10px;border-top:1px solid #f1f5f9;">
                                <div class="hozio-step-label">
                                    <span>Select Pages to Include</span>
                                    <span class="hozio-pages-count">0 selected</span>
                                </div>
                                <div class="hozio-selected-showcase hozio-selected-pages-display">
                                    <div class="hozio-showcase-content">
                                        <div class="hozio-showcase-empty">No pages selected yet</div>
                                    </div>
                                </div>
                                <div class="hozio-search-box">
                                    <input type="text" class="hozio-search-input hozio-pages-search" placeholder="🔍 Search pages...">
                                </div>
                                <div class="hozio-checkbox-list hozio-pages-list"><div class="hozio-no-results">Loading...</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('#hozio-configs-container').append(newConfig);
            configIndex++;
        });
        
        // Update title display when name input changes
        $(document).on('input', '.hozio-config-name-input', function() {
            var newName = $(this).val() || 'Untitled Configuration';
            $(this).closest('.hozio-config-card').find('.hozio-config-title-display').text(newName);
        });

        // Filter mode change handler
        $(document).on('change', '.hozio-filter-mode-select', function() {
            var $card        = $(this).closest('.hozio-config-card');
            var $meta        = $card.find('.hozio-config-meta');
            var $taxSection  = $card.find('.hozio-taxonomy-section');
            var $pagesSection = $card.find('.hozio-pages-section');
            var mode         = $(this).val();

            if (mode === 'pages') {
                $taxSection.hide();
                $pagesSection.show();
                $card.find('.hozio-taxonomy-col').hide();
                // Remove taxonomy badges
                $meta.find('.hozio-badge-tax, .hozio-badge-terms, .hozio-badge-exclude').remove();
                // Load all pages if not yet loaded
                var $pagesList = $card.find('.hozio-pages-list');
                if ($pagesList.find('.hozio-checkbox-item').length === 0) {
                    var index = $card.data('index');
                    $pagesList.html('<div class="hozio-no-results">Loading...</div>');
                    $.post(ajaxurl, {
                        action: 'hozio_get_all_pages',
                        nonce: '<?php echo wp_create_nonce('hozio_get_pages'); ?>'
                    }, function(response) {
                        if (response.success) {
                            var html = '';
                            response.data.forEach(function(page) {
                                html += `<div class="hozio-checkbox-item" data-search="${page.title.toLowerCase()}">
                                    <input type="checkbox"
                                           name="hozio_configs[${index}][page_ids][]"
                                           value="${page.id}"
                                           id="page_${index}_${page.id}"
                                           class="hozio-page-checkbox"
                                           data-name="${page.title}">
                                    <label for="page_${index}_${page.id}">${page.title}</label>
                                </div>`;
                            });
                            $pagesList.html(html || '<div class="hozio-no-results">No pages found</div>');
                            updateSelectedDisplay($card);
                        }
                    });
                }
            } else {
                $taxSection.show();
                $pagesSection.hide();
                $card.find('.hozio-taxonomy-col').show();
                // Remove pages badge
                $meta.find('.hozio-badge-pages').remove();
            }
        });

        // Update header badges when taxonomy changes
        $(document).on('change', '.hozio-taxonomy-select', function() {
            var $card = $(this).closest('.hozio-config-card');
            var $meta = $card.find('.hozio-config-meta');
            var taxVal = $(this).val();
            var taxLabel = taxVal === 'town_taxonomies' ? 'Town Taxonomies'
                         : (taxVal === 'parent_pages'   ? 'Page Taxonomies' : '');
            var $taxBadge = $meta.find('.hozio-badge-tax');
            if (taxLabel) {
                if ($taxBadge.length) { $taxBadge.text(taxLabel); }
                else { $meta.prepend('<span class="hozio-card-badge hozio-badge-tax">' + taxLabel + '</span>'); }
            } else {
                $taxBadge.remove();
                $meta.find('.hozio-badge-terms, .hozio-badge-exclude').remove();
            }
        });

        // Update terms badge in header when term checkboxes change
        $(document).on('change', '.hozio-term-checkbox', function() {
            var $card = $(this).closest('.hozio-config-card');
            var count = $card.find('.hozio-term-checkbox:checked').length;
            var $meta = $card.find('.hozio-config-meta');
            var $badge = $meta.find('.hozio-badge-terms');
            if (count > 0) {
                var label = count + ' term' + (count !== 1 ? 's' : '');
                if ($badge.length) { $badge.text(label); }
                else { $meta.append('<span class="hozio-card-badge hozio-badge-terms">' + label + '</span>'); }
            } else {
                $badge.remove();
            }
        });
        
        // Toggle config accordion
        $(document).on('click', '.hozio-config-header', function(e) {
            if ($(e.target).is('button, .dashicons-trash')) return;
            $(this).closest('.hozio-config-card').toggleClass('collapsed');
        });
        
        // Toggle accordion sections
        $(document).on('click', '.hozio-accordion-header', function() {
            $(this).closest('.hozio-accordion-section').toggleClass('collapsed');
        });
        
        // Remove configuration
        $(document).on('click', '.hozio-remove-config', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if ($('.hozio-config-card').length <= 1) {
                alert('You must have at least one configuration.');
                return;
            }
            
            if (confirm('Remove this configuration?')) {
                $(this).closest('.hozio-config-card').fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });
        
        // Tag removal handler
        $(document).on('click', '.hozio-tag-remove', function(e) {
            e.stopPropagation();
            var targetId = $(this).data('target');
            var $checkbox = $('#' + targetId);
            
            if ($checkbox.length) {
                $checkbox.prop('checked', false).trigger('change');
            }
        });
        
        // Taxonomy change handler
        $(document).on('change', '.hozio-taxonomy-select', function() {
            var $card = $(this).closest('.hozio-config-card');
            var taxonomy = $(this).val();
            var index = $card.data('index');
            var $termsField = $card.find('.hozio-terms-field');
            var $termsList = $card.find('.hozio-terms-list');
            var $excludeField = $card.find('.hozio-exclude-field');
            
            if (!taxonomy) {
                $termsField.hide();
                $excludeField.hide();
                return;
            }
            
            $termsField.show();
            $termsList.html('<div class="hozio-no-results">Loading...</div>');
            $excludeField.hide();
            
            $.post(ajaxurl, {
                action: 'hozio_get_taxonomy_terms',
                taxonomy: taxonomy,
                nonce: '<?php echo wp_create_nonce('hozio_get_terms'); ?>'
            }, function(response) {
                if (response.success) {
                    var html = '';
                    response.data.forEach(function(term) {
                        html += `
                            <div class="hozio-checkbox-item" data-search="${term.name.toLowerCase()}">
                                <input type="checkbox" 
                                       name="hozio_configs[${index}][terms][]" 
                                       value="${term.term_id}" 
                                       id="term_${index}_${term.term_id}"
                                       class="hozio-term-checkbox"
                                       data-name="${term.name}">
                                <label for="term_${index}_${term.term_id}">${term.name}</label>
                            </div>
                        `;
                    });
                    $termsList.html(html);
                    updateSelectedCount($card.find('.hozio-terms-count'), $card.find('.hozio-term-checkbox'));
                    updateSelectedDisplay($card);
                }
            });
        });
        
        // Term checkbox change handler
        $(document).on('change', '.hozio-term-checkbox', function() {
            var $card = $(this).closest('.hozio-config-card');
            var $item = $(this).closest('.hozio-checkbox-item');
            var index = $card.data('index');
            var taxonomy = $card.find('.hozio-taxonomy-select').val();
            var selectedTerms = [];
            
            if ($(this).is(':checked')) {
                $item.addClass('checked');
            } else {
                $item.removeClass('checked');
            }
            
            updateSelectedCount($card.find('.hozio-terms-count'), $card.find('.hozio-term-checkbox'));
            updateSelectedDisplay($card);
            
            $card.find('.hozio-term-checkbox:checked').each(function() {
                selectedTerms.push($(this).val());
            });
            
            if (selectedTerms.length === 0) {
                $card.find('.hozio-exclude-field').hide();
                return;
            }
            
            var $excludeField = $card.find('.hozio-exclude-field');
            var $excludeList = $card.find('.hozio-exclude-list');
            
            $excludeField.show();
            $excludeList.html('<div class="hozio-no-results">Loading...</div>');
            
            $.post(ajaxurl, {
                action: 'hozio_get_pages_by_terms',
                taxonomy: taxonomy,
                terms: selectedTerms,
                nonce: '<?php echo wp_create_nonce('hozio_get_pages'); ?>'
            }, function(response) {
                if (response.success) {
                    var html = '';
                    response.data.forEach(function(page) {
                        html += `
                            <div class="hozio-checkbox-item" data-search="${page.title.toLowerCase()}">
                                <input type="checkbox" 
                                       name="hozio_configs[${index}][exclude][]" 
                                       value="${page.id}" 
                                       id="exclude_${index}_${page.id}"
                                       class="hozio-exclude-checkbox"
                                       data-name="${page.title}">
                                <label for="exclude_${index}_${page.id}">${page.title}</label>
                            </div>
                        `;
                    });
                    $excludeList.html(html || '<div class="hozio-no-results">No pages found</div>');
                    updateSelectedCount($card.find('.hozio-exclude-count'), $card.find('.hozio-exclude-checkbox'), 'excluded');
                    updateSelectedDisplay($card);
                }
            });
        });
        
        // Page checkbox change handler (pages mode)
        $(document).on('change', '.hozio-page-checkbox', function() {
            var $item = $(this).closest('.hozio-checkbox-item');
            var $card = $(this).closest('.hozio-config-card');
            var $meta = $card.find('.hozio-config-meta');

            if ($(this).is(':checked')) { $item.addClass('checked'); }
            else { $item.removeClass('checked'); }

            var count = $card.find('.hozio-page-checkbox:checked').length;
            $card.find('.hozio-pages-count').text(count + ' selected');

            var $badge = $meta.find('.hozio-badge-pages');
            var label  = count + ' page' + (count !== 1 ? 's' : '');
            if (count > 0) {
                if ($badge.length) { $badge.text(label); }
                else { $meta.append('<span class="hozio-card-badge hozio-badge-pages">' + label + '</span>'); }
            } else {
                $badge.remove();
            }

            updateSelectedDisplay($card);
        });

        // Exclude checkbox change handler
        $(document).on('change', '.hozio-exclude-checkbox', function() {
            var $item = $(this).closest('.hozio-checkbox-item');
            var $card = $(this).closest('.hozio-config-card');
            
            if ($(this).is(':checked')) {
                $item.addClass('checked');
            } else {
                $item.removeClass('checked');
            }
            
            updateSelectedCount($card.find('.hozio-exclude-count'), $card.find('.hozio-exclude-checkbox'), 'excluded');
            updateSelectedDisplay($card);
        });
        
        // Search handlers
        $(document).on('input', '.hozio-terms-search, .hozio-exclude-search, .hozio-pages-search', function() {
            var searchText = $(this).val().toLowerCase();
            var $list = $(this).closest('.hozio-config-step').find('.hozio-checkbox-list');
            var $items = $list.find('.hozio-checkbox-item');
            
            $items.each(function() {
                var itemText = $(this).data('search');
                if (itemText.indexOf(searchText) !== -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // Update selected count
        function updateSelectedCount($countEl, $checkboxes, label = 'selected') {
            var count = $checkboxes.filter(':checked').length;
            $countEl.text(count + ' ' + label);
        }
        
        // Update selected items display
        function updateSelectedDisplay($card) {
            // Update terms display
            var $termsDisplay = $card.find('.hozio-selected-terms-display .hozio-showcase-content');
            var $selectedTerms = $card.find('.hozio-term-checkbox:checked');

            if ($selectedTerms.length === 0) {
                $termsDisplay.html('<div class="hozio-showcase-empty">No terms selected yet</div>');
            } else {
                var html = '';
                $selectedTerms.each(function() {
                    var termName = $(this).data('name');
                    html += `<span class="hozio-selected-tag" data-checkbox-id="${$(this).attr('id')}">
                        ${termName}
                        <span class="hozio-tag-remove" data-target="${$(this).attr('id')}">×</span>
                    </span>`;
                });
                $termsDisplay.html(html);
            }

            // Update exclude display
            var $excludeDisplay = $card.find('.hozio-selected-exclude-display .hozio-showcase-content');
            var $selectedExclude = $card.find('.hozio-exclude-checkbox:checked');

            if ($selectedExclude.length === 0) {
                $excludeDisplay.html('<div class="hozio-showcase-empty">No pages excluded</div>');
            } else {
                var html = '';
                $selectedExclude.each(function() {
                    var pageName = $(this).data('name');
                    html += `<span class="hozio-selected-tag" data-checkbox-id="${$(this).attr('id')}">
                        ${pageName}
                        <span class="hozio-tag-remove" data-target="${$(this).attr('id')}">×</span>
                    </span>`;
                });
                $excludeDisplay.html(html);
            }

            // Update pages display (pages mode)
            var $pagesDisplay = $card.find('.hozio-selected-pages-display .hozio-showcase-content');
            var $selectedPages = $card.find('.hozio-page-checkbox:checked');

            if ($selectedPages.length === 0) {
                $pagesDisplay.html('<div class="hozio-showcase-empty">No pages selected yet</div>');
            } else {
                var html = '';
                $selectedPages.each(function() {
                    var pageName = $(this).data('name');
                    html += `<span class="hozio-selected-tag" data-checkbox-id="${$(this).attr('id')}">
                        ${pageName}
                        <span class="hozio-tag-remove" data-target="${$(this).attr('id')}">×</span>
                    </span>`;
                });
                $pagesDisplay.html(html);
            }
        }
        
        // Initialize
        $('.hozio-checkbox-item input[type="checkbox"]:checked').each(function() {
            $(this).closest('.hozio-checkbox-item').addClass('checked');
        });

        $('.hozio-config-card').each(function() {
            var $card = $(this);
            updateSelectedCount($card.find('.hozio-terms-count'), $card.find('.hozio-term-checkbox'));
            updateSelectedCount($card.find('.hozio-exclude-count'), $card.find('.hozio-exclude-checkbox'), 'excluded');
            var pageCount = $card.find('.hozio-page-checkbox:checked').length;
            $card.find('.hozio-pages-count').text(pageCount + ' selected');
            updateSelectedDisplay($card);
        });

        // ---- Claude Copy Prompts ----
        var hozioClaudeAllPrompt = <?php echo json_encode(hozio_build_claude_prompt_all($configs)); ?>;
        var hozioClaudePrompts   = {
            <?php foreach ($configs as $idx => $config): ?><?php echo $idx; ?>: <?php echo json_encode(hozio_build_claude_prompt_single($config)); ?>,
            <?php endforeach; ?>
        };

        function hozioCopyToClipboard(text, $btn, defaultHtml) {
            var fallback = function() {
                var $ta = $('<textarea>').val(text).css({position:'fixed',top:0,left:0,opacity:0}).appendTo('body');
                $ta[0].select();
                document.execCommand('copy');
                $ta.remove();
            };
            var done = function() {
                $btn.addClass('hozio-copied').html('<span class="dashicons dashicons-yes"></span> Copied!');
                setTimeout(function() { $btn.removeClass('hozio-copied').html(defaultHtml); }, 2500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done).catch(function() { fallback(); done(); });
            } else {
                fallback(); done();
            }
        }

        $('#hozio-copy-all-claude').on('click', function() {
            hozioCopyToClipboard(hozioClaudeAllPrompt, $(this),
                '<span class="dashicons dashicons-clipboard"></span> Copy All Setups');
        });

        $(document).on('click', '.hozio-copy-config-claude', function(e) {
            e.stopPropagation();
            var idx    = $(this).closest('.hozio-config-card').data('index');
            var prompt = hozioClaudePrompts[idx];
            if (!prompt) {
                alert('Save your configurations first to generate the setup prompt.');
                return;
            }
            hozioCopyToClipboard(prompt, $(this), '<span class="dashicons dashicons-clipboard"></span>');
        });

    });
    </script>
    <?php
}

function hozio_loop_configs_render_card($index, $config) {
    $name        = isset($config['name'])        ? $config['name']        : 'Configuration ' . ($index + 1);
    $filter_mode = isset($config['filter_mode']) ? $config['filter_mode'] : 'taxonomy';
    $taxonomy    = isset($config['taxonomy'])    ? $config['taxonomy']    : '';
    $terms       = isset($config['terms'])       ? $config['terms']       : array();
    $page_ids    = isset($config['page_ids'])    ? $config['page_ids']    : array();
    $exclude     = isset($config['exclude'])     ? $config['exclude']     : array();
    $tax_label   = $taxonomy === 'town_taxonomies' ? 'Town Taxonomies'
                 : ($taxonomy === 'parent_pages'   ? 'Page Taxonomies' : '');
    $terms_count    = count($terms);
    $page_ids_count = count($page_ids);
    $exclude_count  = count($exclude);
    ?>
    <div class="hozio-config-card collapsed" data-index="<?php echo $index; ?>">
        <div class="hozio-config-header">
            <div class="hozio-config-header-left">
                <span class="dashicons dashicons-arrow-down-alt2 hozio-accordion-icon"></span>
                <span class="hozio-config-title-display"><?php echo esc_html($name); ?></span>
                <span class="hozio-config-meta">
                    <?php if ($filter_mode === 'pages'): ?>
                        <span class="hozio-card-badge hozio-badge-pages"><?php echo $page_ids_count; ?> page<?php echo $page_ids_count !== 1 ? 's' : ''; ?></span>
                    <?php else: ?>
                        <?php if ($tax_label): ?>
                            <span class="hozio-card-badge hozio-badge-tax"><?php echo esc_html($tax_label); ?></span>
                        <?php endif; ?>
                        <?php if ($terms_count > 0): ?>
                            <span class="hozio-card-badge hozio-badge-terms"><?php echo $terms_count; ?> term<?php echo $terms_count !== 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        <?php if ($exclude_count > 0): ?>
                            <span class="hozio-card-badge hozio-badge-exclude"><?php echo $exclude_count; ?> excluded</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="hozio-config-header-actions">
                <button type="button" class="hozio-copy-config-claude" title="Copy Setup Prompt">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
                <button type="button" class="hozio-remove-config">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
        <div class="hozio-config-body">
            <div class="hozio-fields-row">
                <div class="hozio-input-group">
                    <label>Configuration Name</label>
                    <input type="text"
                           name="hozio_configs[<?php echo $index; ?>][name]"
                           value="<?php echo esc_attr($name); ?>"
                           class="hozio-config-name-input"
                           placeholder="e.g., Airport Services, Town Pages, etc.">
                </div>
                <div class="hozio-input-group">
                    <label>Filter Type</label>
                    <select name="hozio_configs[<?php echo $index; ?>][filter_mode]" class="hozio-filter-mode-select">
                        <option value="taxonomy" <?php selected($filter_mode, 'taxonomy'); ?>>By Taxonomy</option>
                        <option value="pages" <?php selected($filter_mode, 'pages'); ?>>By Specific Pages</option>
                    </select>
                </div>
                <div class="hozio-input-group hozio-taxonomy-col" style="<?php echo $filter_mode === 'pages' ? 'display:none;' : ''; ?>">
                    <label>Taxonomy</label>
                    <select name="hozio_configs[<?php echo $index; ?>][taxonomy]" class="hozio-taxonomy-select">
                        <option value="">Select a taxonomy...</option>
                        <option value="parent_pages" <?php selected($taxonomy, 'parent_pages'); ?>>Page Taxonomies</option>
                        <option value="town_taxonomies" <?php selected($taxonomy, 'town_taxonomies'); ?>>Town Taxonomies</option>
                    </select>
                </div>
            </div>

            <!-- Taxonomy section -->
            <div class="hozio-taxonomy-section" style="<?php echo $filter_mode === 'pages' ? 'display:none;' : ''; ?>">
                <div class="hozio-config-step hozio-terms-field" style="<?php echo empty($taxonomy) ? 'display:none;' : ''; ?>">
                    <div class="hozio-step-label">
                        <span>Terms to Include</span>
                        <span class="hozio-terms-count"><?php echo $terms_count; ?> selected</span>
                    </div>
                    <div class="hozio-selected-showcase hozio-selected-terms-display">
                        <div class="hozio-showcase-content">
                            <div class="hozio-showcase-empty">No terms selected yet</div>
                        </div>
                    </div>
                    <div class="hozio-search-box">
                        <input type="text" class="hozio-search-input hozio-terms-search" placeholder="🔍 Search terms...">
                    </div>
                    <div class="hozio-checkbox-list hozio-terms-list">
                        <?php
                        if (!empty($taxonomy)) {
                            $all_terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
                            foreach ($all_terms as $term) {
                                $checked = in_array($term->term_id, $terms) ? 'checked' : '';
                                $class   = $checked ? 'hozio-checkbox-item checked' : 'hozio-checkbox-item';
                                ?>
                                <div class="<?php echo $class; ?>" data-search="<?php echo esc_attr(strtolower($term->name)); ?>">
                                    <input type="checkbox"
                                           name="hozio_configs[<?php echo $index; ?>][terms][]"
                                           value="<?php echo $term->term_id; ?>"
                                           id="term_<?php echo $index; ?>_<?php echo $term->term_id; ?>"
                                           class="hozio-term-checkbox"
                                           data-name="<?php echo esc_attr($term->name); ?>"
                                           <?php echo $checked; ?>>
                                    <label for="term_<?php echo $index; ?>_<?php echo $term->term_id; ?>"><?php echo esc_html($term->name); ?></label>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>

                <div class="hozio-config-step hozio-exclude-field" style="<?php echo (empty($taxonomy) || empty($terms)) ? 'display:none;' : ''; ?>">
                    <div class="hozio-step-label">
                        <span>Exclude Pages <em>(optional)</em></span>
                        <span class="hozio-exclude-count"><?php echo $exclude_count; ?> excluded</span>
                    </div>
                    <div class="hozio-selected-showcase hozio-selected-exclude-display">
                        <div class="hozio-showcase-content">
                            <div class="hozio-showcase-empty">No pages excluded</div>
                        </div>
                    </div>
                    <div class="hozio-search-box">
                        <input type="text" class="hozio-search-input hozio-exclude-search" placeholder="🔍 Search pages...">
                    </div>
                    <div class="hozio-checkbox-list hozio-exclude-list">
                        <?php
                        if (!empty($taxonomy) && !empty($terms)) {
                            $tax_query = array('relation' => 'OR');
                            foreach ($terms as $term_id) {
                                $tax_query[] = array('taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id);
                            }
                            $pages = get_posts(array(
                                'post_type'      => 'page',
                                'posts_per_page' => 500,
                                'tax_query'      => $tax_query,
                                'orderby'        => 'title',
                                'order'          => 'ASC',
                            ));
                            foreach ($pages as $page) {
                                $checked = in_array($page->ID, $exclude) ? 'checked' : '';
                                $class   = $checked ? 'hozio-checkbox-item checked' : 'hozio-checkbox-item';
                                ?>
                                <div class="<?php echo $class; ?>" data-search="<?php echo esc_attr(strtolower($page->post_title)); ?>">
                                    <input type="checkbox"
                                           name="hozio_configs[<?php echo $index; ?>][exclude][]"
                                           value="<?php echo $page->ID; ?>"
                                           id="exclude_<?php echo $index; ?>_<?php echo $page->ID; ?>"
                                           class="hozio-exclude-checkbox"
                                           data-name="<?php echo esc_attr($page->post_title); ?>"
                                           <?php echo $checked; ?>>
                                    <label for="exclude_<?php echo $index; ?>_<?php echo $page->ID; ?>"><?php echo esc_html($page->post_title); ?></label>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Pages section -->
            <div class="hozio-pages-section" style="<?php echo $filter_mode !== 'pages' ? 'display:none;' : ''; ?>">
                <div class="hozio-config-step" style="margin-top:10px;padding-top:10px;border-top:1px solid #f1f5f9;">
                    <div class="hozio-step-label">
                        <span>Select Pages to Include</span>
                        <span class="hozio-pages-count"><?php echo $page_ids_count; ?> selected</span>
                    </div>
                    <div class="hozio-selected-showcase hozio-selected-pages-display">
                        <div class="hozio-showcase-content">
                            <?php if (empty($page_ids)): ?>
                                <div class="hozio-showcase-empty">No pages selected yet</div>
                            <?php else: ?>
                                <?php
                                foreach ($page_ids as $pid) {
                                    $p = get_post(intval($pid));
                                    if (!$p) continue;
                                    ?>
                                    <span class="hozio-selected-tag" data-checkbox-id="page_<?php echo $index; ?>_<?php echo $pid; ?>">
                                        <?php echo esc_html($p->post_title); ?>
                                        <span class="hozio-tag-remove" data-target="page_<?php echo $index; ?>_<?php echo $pid; ?>">×</span>
                                    </span>
                                    <?php
                                }
                                ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="hozio-search-box">
                        <input type="text" class="hozio-search-input hozio-pages-search" placeholder="🔍 Search pages...">
                    </div>
                    <div class="hozio-checkbox-list hozio-pages-list">
                        <?php
                        $all_pages = get_posts(array(
                            'post_type'      => 'page',
                            'post_status'    => 'publish',
                            'posts_per_page' => 500,
                            'orderby'        => 'title',
                            'order'          => 'ASC',
                        ));
                        foreach ($all_pages as $page) {
                            $checked = in_array($page->ID, $page_ids) ? 'checked' : '';
                            $class   = $checked ? 'hozio-checkbox-item checked' : 'hozio-checkbox-item';
                            ?>
                            <div class="<?php echo $class; ?>" data-search="<?php echo esc_attr(strtolower($page->post_title)); ?>">
                                <input type="checkbox"
                                       name="hozio_configs[<?php echo $index; ?>][page_ids][]"
                                       value="<?php echo $page->ID; ?>"
                                       id="page_<?php echo $index; ?>_<?php echo $page->ID; ?>"
                                       class="hozio-page-checkbox"
                                       data-name="<?php echo esc_attr($page->post_title); ?>"
                                       <?php echo $checked; ?>>
                                <label for="page_<?php echo $index; ?>_<?php echo $page->ID; ?>"><?php echo esc_html($page->post_title); ?></label>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ========================================
// AJAX HANDLERS
// ========================================
add_action('wp_ajax_hozio_get_taxonomy_terms', 'hozio_ajax_get_taxonomy_terms');
function hozio_ajax_get_taxonomy_terms() {
    check_ajax_referer('hozio_get_terms', 'nonce');
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
    $term_data = array();
    foreach ($terms as $term) {
        $term_data[] = array('term_id' => $term->term_id, 'name' => $term->name);
    }
    wp_send_json_success($term_data);
}

add_action('wp_ajax_hozio_get_pages_by_terms', 'hozio_ajax_get_pages_by_terms');
function hozio_ajax_get_pages_by_terms() {
    check_ajax_referer('hozio_get_pages', 'nonce');
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    $terms = isset($_POST['terms']) ? array_map('intval', $_POST['terms']) : array();

    if (empty($taxonomy) || empty($terms)) {
        wp_send_json_success(array());
        return;
    }

    $tax_query = array('relation' => 'OR');
    foreach ($terms as $term_id) {
        $tax_query[] = array('taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term_id);
    }

    $pages = get_posts(array(
        'post_type' => 'page',
        'posts_per_page' => 500,
        'tax_query' => $tax_query,
        'orderby' => 'title',
        'order' => 'ASC',
    ));

    $page_data = array();
    foreach ($pages as $page) {
        $page_data[] = array('id' => $page->ID, 'title' => $page->post_title);
    }
    wp_send_json_success($page_data);
}

// ========================================
// PUBLIC AJAX — LOOP RESULTS FOR HTML WIDGETS
// ========================================
add_action('wp_ajax_hozio_get_loop_results',        'hozio_ajax_get_loop_results');
add_action('wp_ajax_nopriv_hozio_get_loop_results', 'hozio_ajax_get_loop_results');
function hozio_ajax_get_loop_results() {
    $page_id = intval($_POST['page_id'] ?? 0);
    if (!$page_id) {
        wp_send_json_error('No page ID provided');
    }

    $config_name = get_post_meta($page_id, 'hozio_selected_loop_config', true);
    if (!$config_name) {
        wp_send_json_success(array());
    }

    $configs = hozio_get_loop_configurations();
    $config  = null;
    foreach ($configs as $c) {
        if (isset($c['name']) && $c['name'] === $config_name) {
            $config = $c;
            break;
        }
    }
    if (!$config) {
        wp_send_json_success(array());
    }

    $filter_mode = $config['filter_mode'] ?? 'taxonomy';

    if ( $filter_mode === 'pages' ) {
        $page_ids = !empty($config['page_ids']) ? array_map('intval', $config['page_ids']) : array();
        $page_ids = array_values( array_diff( $page_ids, array($page_id) ) );
        if ( empty($page_ids) ) {
            wp_send_json_success(array());
            return;
        }
        $query = new WP_Query(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'post__in'       => $page_ids,
            'orderby'        => 'post__in',
        ));
        $pages = array();
        foreach ($query->posts as $post) {
            $pages[] = array(
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'url'       => get_permalink($post->ID),
                'excerpt'   => wp_trim_words(get_the_excerpt($post), 20, '...'),
                'thumbnail' => get_the_post_thumbnail_url($post->ID, 'large') ?: '',
            );
        }
        wp_send_json_success($pages);
        return;
    }

    $exclude = array_merge(
        array($page_id),
        !empty($config['exclude']) ? array_map('intval', $config['exclude']) : array()
    );

    $args = array(
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'posts_per_page' => -1,
        'post__not_in' => $exclude,
    );

    if (!empty($config['terms']) && !empty($config['taxonomy'])) {
        $tax_query = array('relation' => 'OR');
        foreach ($config['terms'] as $term_id) {
            $tax_query[] = array(
                'taxonomy' => sanitize_key($config['taxonomy']),
                'field'    => 'term_id',
                'terms'    => intval($term_id),
            );
        }
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query($args);
    $pages = array();
    foreach ($query->posts as $post) {
        $pages[] = array(
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'url'       => get_permalink($post->ID),
            'excerpt'   => wp_trim_words(get_the_excerpt($post), 20, '...'),
            'thumbnail' => get_the_post_thumbnail_url($post->ID, 'large') ?: '',
        );
    }
    wp_send_json_success($pages);
}

add_action('wp_ajax_hozio_get_all_pages', 'hozio_ajax_get_all_pages');
function hozio_ajax_get_all_pages() {
    check_ajax_referer('hozio_get_pages', 'nonce');
    $posts = get_posts(array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 500,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));
    $page_data = array();
    foreach ($posts as $post) {
        $page_data[] = array('id' => $post->ID, 'title' => $post->post_title);
    }
    wp_send_json_success($page_data);
}
