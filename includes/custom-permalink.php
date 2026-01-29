<?php
/**
 * Hozio Custom Permalinks - Styled Settings Page with Category Toggle
 * Allows independent control of /blog/ and /category/ in URLs
 * FORCEFULLY OVERRIDES WordPress default permalink behavior
 * WITH REAL-TIME PREVIEW UPDATES
 */

// Add inline styles for this page
function hozio_permalink_admin_styles() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'hozio-permalink-settings') === false) {
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
        
        .hozio-permalink-wrapper {
            background: #f9fafb;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .hozio-permalink-header {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 50%, var(--hozio-orange) 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .hozio-permalink-header::before {
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
        
        .hozio-permalink-header h1 {
            color: white !important;
            font-size: 32px;
            margin: 0 0 10px !important;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            text-shadow: none;
        }
        
        .hozio-permalink-header h1 .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
        }
        
        .hozio-permalink-subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            margin: 0;
        }
        
        .hozio-permalink-content {
            padding: 0 40px 40px;
        }
        
        .hozio-permalink-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            border-left: 4px solid var(--hozio-blue);
        }
        
        .hozio-permalink-card.preview-card {
            border-left-color: var(--hozio-green);
        }
        
        .hozio-permalink-card.info-card {
            border-left-color: var(--hozio-orange);
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
        
        .preview-card .hozio-card-header .dashicons {
            color: var(--hozio-green);
        }
        
        .info-card .hozio-card-header .dashicons {
            color: var(--hozio-orange);
        }
        
        .hozio-toggle-wrapper {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-bottom: 16px;
        }
        
        .hozio-toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
            flex-shrink: 0;
        }
        
        .hozio-toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .hozio-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .hozio-toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .hozio-toggle-slider {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
        }
        
        input:checked + .hozio-toggle-slider:before {
            transform: translateX(26px);
        }
        
        .hozio-toggle-label {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .hozio-toggle-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--hozio-gray);
        }
        
        .hozio-toggle-description {
            font-size: 14px;
            color: #6b7280;
        }
        
        .hozio-save-btn {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%) !important;
            border: none !important;
            color: white !important;
            padding: 12px 32px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            text-shadow: none !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin-top: 16px !important;
        }
        
        .hozio-save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
        }
        
        .hozio-save-btn .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        
        .hozio-flush-btn {
            background: white !important;
            border: 2px solid var(--hozio-orange) !important;
            color: var(--hozio-orange) !important;
            padding: 12px 32px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            text-shadow: none !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin-left: 12px !important;
            margin-top: 15px !important;
            height: 56px !important;
        }
        
        .hozio-flush-btn:hover {
            background: var(--hozio-orange) !important;
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
            border-color: var(--hozio-orange) !important;
        }
        
        .hozio-flush-btn .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        
        .hozio-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
        }
        
        .hozio-warning-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #856404;
            margin-bottom: 8px;
        }
        
        .hozio-warning-header .dashicons {
            color: #ffc107;
        }
        
        .hozio-warning-text {
            color: #856404;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .hozio-preview-examples {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .hozio-example {
            padding: 16px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .hozio-example.hozio-example-current {
            border-color: var(--hozio-orange);
            background: #fff5e6;
        }
        
        .hozio-example.hozio-example-preview {
            border-color: var(--hozio-green);
            background: #f0f9e8;
        }
        
        .hozio-example-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--hozio-gray);
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .hozio-example-label .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        
        .hozio-example-url {
            font-family: 'Courier New', monospace;
            font-size: 15px;
            color: var(--hozio-blue);
            padding: 8px 12px;
            background: white;
            border-radius: 4px;
            word-break: break-all;
        }
        
        .hozio-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .hozio-info-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .hozio-info-list li:last-child {
            border-bottom: none;
        }
        
        .hozio-info-icon {
            color: var(--hozio-orange) !important;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .hozio-info-list strong {
            color: var(--hozio-gray);
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Update preview when toggles change
        function updatePreview() {
            const blogEnabled = $('#hozio_blog_prefix_enabled').is(':checked');
            const categoryEnabled = $('#hozio_category_prefix_enabled').is(':checked');
            
            let previewUrl = '<?php echo home_url('/'); ?>';
            
            if (blogEnabled) {
                previewUrl += 'blog/';
            }
            
            if (categoryEnabled) {
                previewUrl += 'category-name/';
            }
            
            previewUrl += 'sample-blog-post/';
            
            // Update the preview URL
            $('.hozio-example-preview .hozio-example-url').text(previewUrl);
        }
        
        // Initial update
        setTimeout(updatePreview, 100);
        
        // Update preview when checkbox state changes
        $(document).on('change', '#hozio_blog_prefix_enabled, #hozio_category_prefix_enabled', function() {
            updatePreview();
        });
        
        // Also catch clicks on the toggle wrapper
        $('.hozio-toggle-wrapper').on('click', function() {
            setTimeout(updatePreview, 10);
        });
        
        // Animate save button
        $('form').on('submit', function() {
            const $btn = $('.hozio-save-btn');
            const originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span> Saving...');
            $btn.prop('disabled', true);
            
            // Re-enable after a delay (in case of redirect)
            setTimeout(function() {
                $btn.html(originalText);
                $btn.prop('disabled', false);
            }, 3000);
        });
    });
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    </script>
    <?php
}
add_action('admin_head', 'hozio_permalink_admin_styles');
// Register the settings to save the enable/disable options
if (!function_exists('hozio_custom_permalink_register_setting')) {
    add_action('admin_init', 'hozio_custom_permalink_register_setting');
    function hozio_custom_permalink_register_setting() {
        register_setting('hozio_permalink_settings', 'hozio_blog_prefix_enabled');
        register_setting('hozio_permalink_settings', 'hozio_category_prefix_enabled');
    }
}

// Flush rewrite rules when custom permalink settings are updated
if (!function_exists('hozio_flush_rewrite_rules_on_save')) {
    add_action('update_option_hozio_blog_prefix_enabled', 'hozio_flush_rewrite_rules_on_save');
    add_action('update_option_hozio_category_prefix_enabled', 'hozio_flush_rewrite_rules_on_save');
    function hozio_flush_rewrite_rules_on_save() {
        flush_rewrite_rules(true); // Hard flush
        
        // Also clear any caching
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
}

// Add custom rewrite rules with HIGHEST priority
if (!function_exists('hozio_add_custom_rewrite_rules')) {
    add_action('init', 'hozio_add_custom_rewrite_rules', 1);
    function hozio_add_custom_rewrite_rules() {
        $blog_enabled = get_option('hozio_blog_prefix_enabled');
        $category_enabled = get_option('hozio_category_prefix_enabled');
        
        // Only add rules if at least one is enabled
        if ($blog_enabled || $category_enabled) {
            if ($blog_enabled && $category_enabled) {
                // Both enabled: /blog/category/postname
                // We only need to match the post name, category is just for pretty URLs
                add_rewrite_rule(
                    '^blog/([^/]+)/([^/]+)/?$',
                    'index.php?name=$matches[2]',
                    'top'
                );
            } elseif ($blog_enabled) {
                // Only blog: /blog/postname
                add_rewrite_rule(
                    '^blog/([^/]+)/?$',
                    'index.php?name=$matches[1]',
                    'top'
                );
            } elseif ($category_enabled) {
                // Only category: /category/postname
                // We only need to match the post name, category is just for pretty URLs
                add_rewrite_rule(
                    '^([^/]+)/([^/]+)/?$',
                    'index.php?name=$matches[2]',
                    'top'
                );
            }
        }
    }
}

// Helper function to get category slug for a post
if (!function_exists('hozio_get_post_category_slug')) {
    function hozio_get_post_category_slug($post_id) {
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            // Get the first category
            return $categories[0]->slug;
        }
        return 'uncategorized'; // Fallback
    }
}

// PERFORMANCE: Cached permalink settings to avoid repeated get_option() calls
if (!function_exists('hozio_get_permalink_settings')) {
    function hozio_get_permalink_settings() {
        static $settings = null;
        if ($settings === null) {
            $settings = array(
                'blog_enabled' => get_option('hozio_blog_prefix_enabled'),
                'category_enabled' => get_option('hozio_category_prefix_enabled'),
            );
        }
        return $settings;
    }
}

// FORCEFULLY override the permalink - PRIMARY METHOD
if (!function_exists('hozio_custom_post_link_override')) {
    add_filter('post_link', 'hozio_custom_post_link_override', 9999, 3);
    add_filter('post_type_link', 'hozio_custom_post_link_override', 9999, 3);
    function hozio_custom_post_link_override($permalink, $post, $leavename = false) {
        // EARLY EXIT: Only apply to posts (blog entries)
        if (!is_object($post) || $post->post_type !== 'post') {
            return $permalink;
        }

        // Use cached settings to avoid repeated DB calls
        $settings = hozio_get_permalink_settings();

        // EARLY EXIT: Nothing enabled, skip processing
        if (!$settings['blog_enabled'] && !$settings['category_enabled']) {
            return $permalink;
        }

        $url_parts = [];

        // Add /blog/ if enabled
        if ($settings['blog_enabled']) {
            $url_parts[] = 'blog';
        }

        // Add /category/ if enabled
        if ($settings['category_enabled']) {
            $cat_slug = hozio_get_post_category_slug($post->ID);
            if ($cat_slug) {
                $url_parts[] = $cat_slug;
            }
        }

        // Add post name
        if ($leavename) {
            $url_parts[] = '%postname%';
        } else {
            $url_parts[] = $post->post_name;
        }

        // Build the permalink - FORCE override
        if (!empty($url_parts)) {
            return home_url('/' . implode('/', $url_parts) . '/');
        }

        return $permalink;
    }
}

// Override the_permalink output - SECONDARY METHOD
if (!function_exists('hozio_override_the_permalink')) {
    add_filter('the_permalink', 'hozio_override_the_permalink', 9999, 2);
    function hozio_override_the_permalink($permalink, $post) {
        // EARLY EXIT: Only apply to posts
        if (!is_object($post) || $post->post_type !== 'post') {
            return $permalink;
        }

        // Use cached settings to avoid repeated DB calls
        $settings = hozio_get_permalink_settings();

        // EARLY EXIT: Nothing enabled
        if (!$settings['blog_enabled'] && !$settings['category_enabled']) {
            return $permalink;
        }

        $url_parts = [];

        if ($settings['blog_enabled']) {
            $url_parts[] = 'blog';
        }

        if ($settings['category_enabled']) {
            $cat_slug = hozio_get_post_category_slug($post->ID);
            if ($cat_slug) {
                $url_parts[] = $cat_slug;
            }
        }

        $url_parts[] = $post->post_name;

        return home_url('/' . implode('/', $url_parts) . '/');
    }
}

// Override get_permalink - TERTIARY METHOD
if (!function_exists('hozio_override_get_permalink')) {
    add_filter('pre_post_link', 'hozio_override_get_permalink', 9999, 3);
    function hozio_override_get_permalink($permalink, $post, $leavename) {
        // EARLY EXIT: Only apply to posts
        if (!is_object($post) || $post->post_type !== 'post') {
            return $permalink;
        }

        // Use cached settings to avoid repeated DB calls
        $settings = hozio_get_permalink_settings();

        // If nothing is enabled, pass through original permalink structure
        if (!$settings['blog_enabled'] && !$settings['category_enabled']) {
            return $permalink;
        }

        $url_parts = [];

        if ($settings['blog_enabled']) {
            $url_parts[] = 'blog';
        }

        if ($settings['category_enabled']) {
            $cat_slug = hozio_get_post_category_slug($post->ID);
            if ($cat_slug) {
                $url_parts[] = $cat_slug;
            }
        }

        if ($leavename) {
            $url_parts[] = '%postname%';
        } else {
            $url_parts[] = $post->post_name;
        }

        return home_url('/' . implode('/', $url_parts) . '/');
    }
}

// Force flush on plugin activation or when visiting the settings page
if (!function_exists('hozio_force_flush_on_load')) {
    add_action('admin_init', 'hozio_force_flush_on_load');
    function hozio_force_flush_on_load() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'hozio-permalink-settings') !== false) {
            // Force flush when viewing the settings page
            flush_rewrite_rules(true);
        }
    }
}

// Display the styled settings page
function hozio_custom_permalink_settings_page() {
    // Handle manual flush
    if (isset($_POST['flush_rules']) && wp_verify_nonce($_POST['hozio_permalink_nonce'], 'hozio_permalink_save')) {
        flush_rewrite_rules(true);
        delete_option('rewrite_rules'); // Nuclear option - delete cached rules
        
        // Clear all caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        echo '<div class="notice notice-success is-dismissible"><p><strong>Rewrite rules flushed!</strong> Please test your URLs now.</p></div>';
    }
    
    // Handle form submission
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['hozio_permalink_nonce'], 'hozio_permalink_save')) {
        $blog_enabled = isset($_POST['hozio_blog_prefix_enabled']) ? 1 : 0;
        $category_enabled = isset($_POST['hozio_category_prefix_enabled']) ? 1 : 0;
        
        update_option('hozio_blog_prefix_enabled', $blog_enabled);
        update_option('hozio_category_prefix_enabled', $category_enabled);
        
        // Force hard flush
        flush_rewrite_rules(true);
        delete_option('rewrite_rules'); // Nuclear option
        
        // Clear all caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Show success message
        echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved successfully!</strong> Rewrite rules have been flushed. Clear any page caching if changes don\'t appear immediately.</p></div>';
    }
    
    $blog_setting = get_option('hozio_blog_prefix_enabled', 0);
    $category_setting = get_option('hozio_category_prefix_enabled', 0);
    
    // Build current URL example
    $current_url = home_url('/');
    if ($blog_setting) {
        $current_url .= 'blog/';
    }
    if ($category_setting) {
        $current_url .= 'category-name/';
    }
    $current_url .= 'sample-blog-post/';
    ?>
    <div class="hozio-permalink-wrapper">
        <div class="hozio-permalink-header">
            <div class="hozio-header-content">
                <h1>
                    <span class="dashicons dashicons-admin-links"></span>
                    Custom Permalinks
                </h1>
                <p class="hozio-permalink-subtitle">Configure custom URL structure for your blog posts</p>
            </div>
        </div>

        <div class="hozio-permalink-content">
            <form method="post" action="">
                <?php wp_nonce_field('hozio_permalink_save', 'hozio_permalink_nonce'); ?>
                
                <!-- Settings Card -->
                <div class="hozio-permalink-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <h2>Permalink Settings</h2>
                    </div>
                    
                    <!-- Blog Prefix Toggle -->
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" 
                                   id="hozio_blog_prefix_enabled" 
                                   name="hozio_blog_prefix_enabled" 
                                   value="1" 
                                   <?php checked($blog_setting, 1); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Enable /blog/ Prefix</div>
                            <div class="hozio-toggle-description">
                                Add "/blog/" at the beginning of all blog post URLs
                            </div>
                        </div>
                    </div>
                    
                    <!-- Category Prefix Toggle -->
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" 
                                   id="hozio_category_prefix_enabled" 
                                   name="hozio_category_prefix_enabled" 
                                   value="1" 
                                   <?php checked($category_setting, 1); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Include Category in URL</div>
                            <div class="hozio-toggle-description">
                                Add category slug between /blog/ and post name (e.g., /blog/<strong>category-name</strong>/post-name/)
                            </div>
                        </div>
                    </div>
                    
                    <div class="hozio-warning">
                        <div class="hozio-warning-header">
                            <span class="dashicons dashicons-warning"></span>
                            Important Notice
                        </div>
                        <div class="hozio-warning-text">
                            Changing URL structure may affect SEO and existing links. Consider setting up redirects for existing URLs to prevent broken links. <strong>After saving, clear any page caching plugins (SiteGround Optimizer, WP Rocket, etc.) for changes to take effect.</strong>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit" class="button hozio-save-btn">
                        <span class="dashicons dashicons-yes"></span>
                        Save Settings
                    </button>
                    
                    <button type="submit" name="flush_rules" class="button hozio-flush-btn">
                        <span class="dashicons dashicons-update"></span>
                        Flush Rewrite Rules
                    </button>
                </div>
                
                <!-- Preview Card -->
                <div class="hozio-permalink-card preview-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-visibility"></span>
                        <h2>URL Preview</h2>
                    </div>
                    
                    <div class="hozio-preview-examples">
                        <div class="hozio-example hozio-example-current">
                            <div class="hozio-example-label">
                                <span class="dashicons dashicons-admin-links"></span>
                                Current Structure:
                            </div>
                            <div class="hozio-example-url">
                                <?php echo $current_url; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Info Card -->
                <div class="hozio-permalink-card info-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-info"></span>
                        <h2>URL Structure Options</h2>
                    </div>
                    
                    <ul class="hozio-info-list">
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Both Enabled:</strong> /blog/category-name/post-name/
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Only Blog Enabled:</strong> /blog/post-name/
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Only Category Enabled:</strong> /category-name/post-name/
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Both Disabled:</strong> /post-name/ (default WordPress structure)
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Category Used:</strong> The first category assigned to each post is used in the URL
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>SEO Friendly:</strong> All structures are search engine optimized
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Only Affects Posts:</strong> Pages and other content types remain unchanged
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Override Priority:</strong> This plugin uses maximum priority (9999) to override all other permalink settings
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Getting 404 Errors?</strong> Click the "Flush Rewrite Rules" button to force WordPress to recognize the new URL structure
                            </div>
                        </li>
                    </ul>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>
