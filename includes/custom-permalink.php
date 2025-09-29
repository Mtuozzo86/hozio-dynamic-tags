<?php
/**
 * Hozio Custom Permalinks - Styled Settings Page
 * Maintains all existing functionality with beautiful Hozio design
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
            margin-bottom: 24px;
        }
        
        .hozio-toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
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
            transition: all 0.2s !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            text-shadow: none !important;
            box-shadow: 0 4px 6px rgba(0, 160, 227, 0.3) !important;
            height: auto !important;
            line-height: normal !important;
        }
        
        .hozio-save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 160, 227, 0.4) !important;
        }
        
        .hozio-save-btn .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        
        .hozio-preview-examples {
            display: grid;
            gap: 16px;
            margin-top: 16px;
        }
        
        .hozio-example {
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .hozio-example-label {
            font-weight: 600;
            color: var(--hozio-gray);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hozio-example-url {
            font-family: monospace;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            font-size: 14px;
            color: var(--hozio-blue);
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
            border-bottom: 1px solid #f3f4f6;
        }
        
        .hozio-info-list li:last-child {
            border-bottom: none;
        }
        
        .hozio-info-icon {
            color: var(--hozio-orange);
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .hozio-warning {
            background: linear-gradient(135deg, rgba(247, 148, 29, 0.1) 0%, rgba(247, 148, 29, 0.05) 100%);
            border: 1px solid rgba(247, 148, 29, 0.2);
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
        }
        
        .hozio-warning-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--hozio-orange-dark);
            margin-bottom: 8px;
        }
        
        .hozio-warning-text {
            color: var(--hozio-orange-dark);
            font-size: 14px;
        }
        
        @media (max-width: 782px) {
            .hozio-permalink-wrapper {
                margin: 20px 0;
            }
            
            .hozio-permalink-header {
                padding: 30px 20px;
            }
            
            .hozio-permalink-header h1 {
                font-size: 24px;
            }
            
            .hozio-permalink-content {
                padding: 0 20px 20px;
            }
            
            .hozio-permalink-card {
                padding: 20px;
                margin: 20px 0;
            }
            
            .hozio-toggle-wrapper {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Update preview examples when toggle changes
        $('#hozio_custom_permalink_enabled').on('change', function() {
            const isEnabled = $(this).is(':checked');
            $('.hozio-example-enabled').toggle(isEnabled);
            $('.hozio-example-disabled').toggle(!isEnabled);
            $('.hozio-warning').toggle(isEnabled);
        });
        
        // Initialize preview state
        const initialState = $('#hozio_custom_permalink_enabled').is(':checked');
        $('.hozio-example-enabled').toggle(initialState);
        $('.hozio-example-disabled').toggle(!initialState);
        $('.hozio-warning').toggle(initialState);
        
        // Form submission with loading state
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

// Register the setting to save the enable/disable option for custom permalinks
if (!function_exists('hozio_custom_permalink_register_setting')) {
    add_action('admin_init', 'hozio_custom_permalink_register_setting');
    function hozio_custom_permalink_register_setting() {
        register_setting('hozio_permalink_settings', 'hozio_custom_permalink_enabled');
    }
}

// Flush rewrite rules when custom permalink setting is updated
if (!function_exists('hozio_flush_rewrite_rules')) {
    add_action('update_option_hozio_custom_permalink_enabled', 'hozio_flush_rewrite_rules');
    function hozio_flush_rewrite_rules() {
        flush_rewrite_rules();
    }
}

// Hook to modify the permalink structure
if (!function_exists('hozio_custom_post_link')) {
    add_filter('post_link', 'hozio_custom_post_link', 10, 2);
    function hozio_custom_post_link($permalink, $post) {
        // Check if the custom permalink feature is enabled
        $is_enabled = get_option('hozio_custom_permalink_enabled');
        if (!$is_enabled) {
            return $permalink; // Return the default permalink if the feature is disabled
        }
        // Only apply to posts (blog entries)
        if ($post->post_type == 'post') {
            // Log to debug
            error_log('Custom permalink function is running.');
            // Get the first category of the post
            $categories = get_the_category($post->ID);
            if (!empty($categories)) {
                $category = $categories[0]->slug; // Use the slug of the first category
                // Modify the permalink to include /blog/category/postname structure
                $permalink = home_url('/blog/' . $category . '/' . $post->post_name . '/');
            }
        }
        return $permalink;
    }
}

// Display the styled settings page
function hozio_custom_permalink_settings_page() {
    // Handle form submission
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['hozio_permalink_nonce'], 'hozio_permalink_save')) {
        $enabled = isset($_POST['hozio_custom_permalink_enabled']) ? 1 : 0;
        update_option('hozio_custom_permalink_enabled', $enabled);
        
        // Show success message
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
    }
    
    $current_setting = get_option('hozio_custom_permalink_enabled', 0);
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
                    
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" 
                                   id="hozio_custom_permalink_enabled" 
                                   name="hozio_custom_permalink_enabled" 
                                   value="1" 
                                   <?php checked($current_setting, 1); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Enable Custom Permalinks</div>
                            <div class="hozio-toggle-description">
                                Add "/blog/" prefix to all blog post URLs: /blog/category/post-name/
                            </div>
                        </div>
                    </div>
                    
                    <div class="hozio-warning">
                        <div class="hozio-warning-header">
                            <span class="dashicons dashicons-warning"></span>
                            Important Notice
                        </div>
                        <div class="hozio-warning-text">
                            Enabling this adds "/blog/" to the beginning of all post URLs. This may affect SEO and existing links.
                            Consider setting up redirects for existing URLs.
                        </div>
                    </div>
                    
                    <button type="submit" name="submit" class="button hozio-save-btn">
                        <span class="dashicons dashicons-yes"></span>
                        Save Settings
                    </button>
                </div>
                
                <!-- Preview Card -->
                <div class="hozio-permalink-card preview-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-visibility"></span>
                        <h2>URL Preview</h2>
                    </div>
                    
                    <div class="hozio-preview-examples">
                        <div class="hozio-example hozio-example-disabled">
                            <div class="hozio-example-label">
                                <span class="dashicons dashicons-dismiss"></span>
                                Current (Default) Structure:
                            </div>
                            <div class="hozio-example-url">
                                <?php echo home_url('/category-name/sample-blog-post/'); ?>
                            </div>
                        </div>
                        
                        <div class="hozio-example hozio-example-enabled">
                            <div class="hozio-example-label">
                                <span class="dashicons dashicons-yes"></span>
                                New Custom Structure:
                            </div>
                            <div class="hozio-example-url">
                                <?php echo home_url('/blog/category-name/sample-blog-post/'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Info Card -->
                <div class="hozio-permalink-card info-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-info"></span>
                        <h2>How It Works</h2>
                    </div>
                    
                    <ul class="hozio-info-list">
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Adds Blog Prefix:</strong> Prepends "/blog/" to all post URLs for better organization
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Includes Categories:</strong> Maintains category structure within the blog section (can be changed in Settings > Permalinks)
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>SEO Friendly:</strong> Creates organized URLs like /blog/category/post-name/
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Instant Updates:</strong> Changes apply immediately to new and existing posts
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Only Affects Posts:</strong> Pages and other content types remain unchanged
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

