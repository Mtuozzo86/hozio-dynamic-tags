<?php


if (!defined('ABSPATH')) exit;


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
                <strong>💡 Tip:</strong> This applies to all loop grids and carousels on this page.
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
// INTERCEPT LOOP QUERIES - FIXED
// ========================================
add_action('elementor/query/query_args', 'hozio_intercept_loop_widget_query', 10, 2);

function hozio_intercept_loop_widget_query($query_args, $widget) {
    // Only target loop widgets
    $widget_name = $widget->get_name();
    if (!in_array($widget_name, ['loop-grid', 'loop-carousel'])) {
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

function hozio_apply_loop_configuration($config_name, $query_args, $current_page_id) {
    $configs = get_option('hozio_loop_configurations', array());
    
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
    
    $taxonomy = isset($selected_config['taxonomy']) ? $selected_config['taxonomy'] : '';
    $term_ids = isset($selected_config['terms']) ? $selected_config['terms'] : array();
    $excluded_pages = isset($selected_config['exclude']) ? $selected_config['exclude'] : array();
    
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
    
    // Debug logging (remove in production)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Hozio Loop Config Applied:');
        error_log('Config: ' . $config_name);
        error_log('Taxonomy: ' . $taxonomy);
        error_log('Terms: ' . implode(', ', $term_ids));
        error_log('Excluded: ' . implode(', ', $post__not_in));
    }
    
    return $query_args;
}

// ========================================
// RENDER CONFIGURATIONS PAGE
// ========================================
function hozio_loop_configs_render_page() {
    // Handle save
    if (isset($_POST['hozio_save_configs']) && check_admin_referer('hozio_configs_save', 'hozio_configs_nonce')) {
        if (isset($_POST['hozio_configs']) && is_array($_POST['hozio_configs'])) {
            $configs = array();
            
            foreach ($_POST['hozio_configs'] as $index => $config) {
                if (!empty($config['name'])) {
                    $configs[] = array(
                        'name' => sanitize_text_field($config['name']),
                        'taxonomy' => sanitize_text_field($config['taxonomy']),
                        'terms' => isset($config['terms']) ? array_map('intval', $config['terms']) : array(),
                        'exclude' => isset($config['exclude']) ? array_map('intval', $config['exclude']) : array(),
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
            gap: 24px;
            margin-bottom: 24px;
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
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
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
            font-size: 18px;
            color: #1e293b;
        }
        
        .hozio-remove-config {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            font-size: 16px;
            font-weight: 600;
        }
        
        .hozio-remove-config:hover {
            background: #dc2626;
            color: white;
            transform: scale(1.05);
        }
        
        .hozio-remove-config .dashicons {
            width: 18px;
            height: 18px;
            font-size: 18px;
        }
        
        .hozio-config-body {
            padding: 32px;
            background: #ffffff;
        }
        
        /* Step-by-step sections */
        .hozio-config-step {
            margin-bottom: 32px;
            padding-bottom: 32px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .hozio-config-step:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .hozio-step-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .hozio-step-badge {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        .hozio-step-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .hozio-step-description {
            color: #64748b;
            font-size: 14px;
            margin: 0 0 16px 44px;
        }
        
        .hozio-step-content {
            margin-left: 44px;
        }
        
        /* Input fields */
        .hozio-input-group {
            margin-bottom: 16px;
        }
        
        .hozio-input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 13px;
            color: #475569;
        }
        
        .hozio-input-group input[type="text"],
        .hozio-input-group select {
            width: 100%;
            max-width: 500px;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
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
        
        /* Selected items showcase */
        .hozio-selected-showcase {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border: 2px solid #93c5fd;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            min-height: 80px;
        }
        
        .hozio-showcase-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        
        .hozio-showcase-content {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .hozio-showcase-empty {
            color: #64748b;
            font-size: 14px;
            font-style: italic;
        }
        
        .hozio-selected-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: #1e40af;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid #3b82f6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        
        .hozio-selected-tag:hover {
            background: #f0f9ff;
            border-color: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .hozio-tag-remove {
            cursor: pointer;
            color: #ef4444;
            font-weight: bold;
            font-size: 18px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            transition: all 0.2s;
        }
        
        .hozio-tag-remove:hover {
            background: #fee2e2;
            color: #dc2626;
            transform: scale(1.1);
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
            padding: 16px;
            background: white;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .hozio-search-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .hozio-search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .hozio-checkbox-list {
            max-height: 250px;
            overflow-y: auto;
            padding: 12px;
            background: white;
        }
        
        .hozio-checkbox-item {
            padding: 12px 16px;
            margin-bottom: 6px;
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
            border: 2px solid transparent;
        }
        
        .hozio-checkbox-item:hover {
            background: #f0f9ff;
            border-color: #bfdbfe;
        }
        
        .hozio-checkbox-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            cursor: pointer;
            accent-color: #3b82f6;
        }
        
        .hozio-checkbox-item label {
            cursor: pointer;
            margin: 0;
            font-weight: 500;
            font-size: 15px;
            color: #334155;
            flex: 1;
        }
        
        .hozio-checkbox-item.checked {
            background: #dbeafe;
            border-color: #3b82f6;
        }
        
        .hozio-checkbox-item.checked label {
            color: #1e40af;
            font-weight: 600;
        }
        
        .hozio-no-results {
            padding: 48px;
            text-align: center;
            color: #94a3b8;
            font-size: 15px;
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
                        </div>
                        <button type="button" class="hozio-remove-config">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div class="hozio-config-body">
                        <div class="hozio-config-row">
                            <div class="hozio-config-field">
                                <label>Configuration Name</label>
                                <input type="text" 
                                       name="hozio_configs[${configIndex}][name]" 
                                       value="${configName}" 
                                       class="hozio-config-name-input"
                                       placeholder="Enter configuration name...">
                            </div>
                            <div class="hozio-config-field">
                                <label>Taxonomy</label>
                                <div class="hozio-taxonomy-wrapper">
                                    <select name="hozio_configs[${configIndex}][taxonomy]" class="hozio-taxonomy-select">
                                        <option value="">Select a taxonomy...</option>
                                        <option value="parent_pages">Page Taxonomies</option>
                                        <option value="town_taxonomies">Town Taxonomies</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="hozio-config-row hozio-terms-field" style="display:none;">
                            <div class="hozio-config-field">
                                <div class="hozio-accordion-section">
                                    <div class="hozio-accordion-header">
                                        <span class="hozio-accordion-title">
                                            <span class="dashicons dashicons-arrow-down-alt2 hozio-accordion-arrow"></span>
                                            Select Terms
                                        </span>
                                    </div>
                                    <div class="hozio-accordion-body">
                                        <div class="hozio-searchable-list">
                                            <div class="hozio-selected-items hozio-selected-terms-display">
                                                <div class="hozio-selected-items-empty">No terms selected</div>
                                            </div>
                                            <div class="hozio-search-box">
                                                <input type="text" class="hozio-search-input hozio-terms-search" placeholder="🔍 Search terms...">
                                            </div>
                                            <div class="hozio-checkbox-list hozio-terms-list"></div>
                                            <div class="hozio-selected-count hozio-terms-count">0 selected</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="hozio-config-field hozio-exclude-field" style="display:none;">
                                <div class="hozio-accordion-section">
                                    <div class="hozio-accordion-header">
                                        <span class="hozio-accordion-title">
                                            <span class="dashicons dashicons-arrow-down-alt2 hozio-accordion-arrow"></span>
                                            Exclude Pages
                                        </span>
                                    </div>
                                    <div class="hozio-accordion-body">
                                        <div class="hozio-searchable-list">
                                            <div class="hozio-selected-items hozio-selected-exclude-display">
                                                <div class="hozio-selected-items-empty">No pages excluded</div>
                                            </div>
                                            <div class="hozio-search-box">
                                                <input type="text" class="hozio-search-input hozio-exclude-search" placeholder="🔍 Search pages...">
                                            </div>
                                            <div class="hozio-checkbox-list hozio-exclude-list"></div>
                                            <div class="hozio-selected-count hozio-exclude-count">0 excluded</div>
                                        </div>
                                    </div>
                                </div>
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
        $(document).on('input', '.hozio-terms-search, .hozio-exclude-search', function() {
            var searchText = $(this).val().toLowerCase();
            var $list = $(this).closest('.hozio-searchable-list').find('.hozio-checkbox-list');
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
            var $termsDisplay = $card.find('.hozio-selected-terms-display');
            var $selectedTerms = $card.find('.hozio-term-checkbox:checked');
            
            if ($selectedTerms.length === 0) {
                $termsDisplay.html('<div class="hozio-selected-items-empty">No terms selected</div>');
            } else {
                var html = '';
                $selectedTerms.each(function() {
                    var termName = $(this).data('name');
                    var termId = $(this).val();
                    html += `<span class="hozio-selected-tag" data-checkbox-id="${$(this).attr('id')}">
                        ${termName}
                        <span class="hozio-tag-remove" data-target="${$(this).attr('id')}">×</span>
                    </span>`;
                });
                $termsDisplay.html(html);
            }
            
            // Update exclude display
            var $excludeDisplay = $card.find('.hozio-selected-exclude-display');
            var $selectedExclude = $card.find('.hozio-exclude-checkbox:checked');
            
            if ($selectedExclude.length === 0) {
                $excludeDisplay.html('<div class="hozio-selected-items-empty">No pages excluded</div>');
            } else {
                var html = '';
                $selectedExclude.each(function() {
                    var pageName = $(this).data('name');
                    var pageId = $(this).val();
                    html += `<span class="hozio-selected-tag" data-checkbox-id="${$(this).attr('id')}">
                        ${pageName}
                        <span class="hozio-tag-remove" data-target="${$(this).attr('id')}">×</span>
                    </span>`;
                });
                $excludeDisplay.html(html);
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
            updateSelectedDisplay($card);
        });
    });
    </script>
    <?php
}

function hozio_loop_configs_render_card($index, $config) {
    $name = isset($config['name']) ? $config['name'] : 'Configuration ' . ($index + 1);
    $taxonomy = isset($config['taxonomy']) ? $config['taxonomy'] : '';
    $terms = isset($config['terms']) ? $config['terms'] : array();
    $exclude = isset($config['exclude']) ? $config['exclude'] : array();
    
    ?>
    <div class="hozio-config-card" data-index="<?php echo $index; ?>">
        <div class="hozio-config-header">
            <div class="hozio-config-header-left">
                <span class="dashicons dashicons-arrow-down-alt2 hozio-accordion-icon"></span>
                <span class="hozio-config-title-display"><?php echo esc_html($name); ?></span>
            </div>
            <button type="button" class="hozio-remove-config">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
        <div class="hozio-config-body">
            <!-- STEP 1: Basic Info -->
            <div class="hozio-config-step">
                <div class="hozio-step-header">
                    <div class="hozio-step-badge">1</div>
                    <div class="hozio-step-title">Configuration Details</div>
                </div>
                <p class="hozio-step-description">Give your configuration a unique name and select the taxonomy to filter by.</p>
                
                <div class="hozio-step-content">
                    <div class="hozio-input-group">
                        <label>Configuration Name</label>
                        <input type="text" 
                               name="hozio_configs[<?php echo $index; ?>][name]" 
                               value="<?php echo esc_attr($name); ?>" 
                               class="hozio-config-name-input"
                               placeholder="e.g., Airport Services, Town Pages, etc.">
                    </div>
                    
                    <div class="hozio-input-group">
                        <label>Taxonomy</label>
                        <div class="hozio-taxonomy-wrapper">
                            <select name="hozio_configs[<?php echo $index; ?>][taxonomy]" class="hozio-taxonomy-select">
                                <option value="">Select a taxonomy...</option>
                                <option value="parent_pages" <?php selected($taxonomy, 'parent_pages'); ?>>Page Taxonomies</option>
                                <option value="town_taxonomies" <?php selected($taxonomy, 'town_taxonomies'); ?>>Town Taxonomies</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- STEP 2: Select Terms -->
            <div class="hozio-config-step hozio-terms-field" style="<?php echo empty($taxonomy) ? 'display:none;' : ''; ?>">
                <div class="hozio-step-header">
                    <div class="hozio-step-badge">2</div>
                    <div class="hozio-step-title">Select Terms to Include</div>
                </div>
                <p class="hozio-step-description">Choose which taxonomy terms should be included in your loop query.</p>
                
                <div class="hozio-step-content">
                    <!-- Selected terms showcase -->
                    <div class="hozio-selected-showcase hozio-selected-terms-display">
                        <div class="hozio-showcase-title">✓ Selected Terms</div>
                        <div class="hozio-showcase-content">
                            <div class="hozio-showcase-empty">No terms selected yet</div>
                        </div>
                    </div>
                    
                    <!-- Terms selection -->
                    <div class="hozio-selection-area">
                        <div class="hozio-search-box">
                            <input type="text" class="hozio-search-input hozio-terms-search" placeholder="🔍 Search terms...">
                        </div>
                        <div class="hozio-checkbox-list hozio-terms-list">
                            <?php
                            if (!empty($taxonomy)) {
                                $all_terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
                                foreach ($all_terms as $term) {
                                    $checked = in_array($term->term_id, $terms) ? 'checked' : '';
                                    $class = $checked ? 'hozio-checkbox-item checked' : 'hozio-checkbox-item';
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
                        <div class="hozio-selection-footer hozio-terms-count">0 selected</div>
                    </div>
                </div>
            </div>
            
            <!-- STEP 3: Exclude Pages -->
            <div class="hozio-config-step hozio-exclude-field" style="<?php echo (empty($taxonomy) || empty($terms)) ? 'display:none;' : ''; ?>">
                <div class="hozio-step-header">
                    <div class="hozio-step-badge">3</div>
                    <div class="hozio-step-title">Exclude Specific Pages (Optional)</div>
                </div>
                <p class="hozio-step-description">Optionally exclude specific pages from appearing in the loop results.</p>
                
                <div class="hozio-step-content">
                    <!-- Excluded pages showcase -->
                    <div class="hozio-selected-showcase hozio-selected-exclude-display">
                        <div class="hozio-showcase-title">⊘ Excluded Pages</div>
                        <div class="hozio-showcase-content">
                            <div class="hozio-showcase-empty">No pages excluded</div>
                        </div>
                    </div>
                    
                    <!-- Pages selection -->
                    <div class="hozio-selection-area">
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
                                    'post_type' => 'page',
                                    'posts_per_page' => 500,
                                    'tax_query' => $tax_query,
                                    'orderby' => 'title',
                                    'order' => 'ASC',
                                ));
                                
                                foreach ($pages as $page) {
                                    $checked = in_array($page->ID, $exclude) ? 'checked' : '';
                                    $class = $checked ? 'hozio-checkbox-item checked' : 'hozio-checkbox-item';
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
                        <div class="hozio-selection-footer hozio-exclude-count">0 excluded</div>
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
