<?php
/**
 * Hozio Query Post Types - Styled Settings Page
 * Handles the selection of post types for dynamic queries
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Add inline styles for the Query Post Types page
function hozio_query_post_types_admin_styles() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'hozio-query-post-types') === false) {
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
        
        .hozio-post-types-wrapper {
            background: #f9fafb;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .hozio-post-types-header {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 50%, var(--hozio-orange) 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .hozio-post-types-header::before {
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
        
        .hozio-post-types-header h1 {
            color: white !important;
            font-size: 32px;
            margin: 0 0 10px !important;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            text-shadow: none;
        }
        
        .hozio-post-types-header h1 .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
        }
        
        .hozio-post-types-subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            margin: 0;
        }
        
        .hozio-post-types-content {
            padding: 0 40px 40px;
        }
        
        .hozio-post-types-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            border-left: 4px solid var(--hozio-blue);
        }
        
        .hozio-post-types-card.info-card {
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
        
        .info-card .hozio-card-header .dashicons {
            color: var(--hozio-orange);
        }
        
        .hozio-post-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .hozio-post-type-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .hozio-post-type-item:hover {
            background: #f3f4f6;
            border-color: var(--hozio-blue);
        }
        
        .hozio-post-type-item.selected {
            background: rgba(0, 160, 227, 0.05);
            border-color: var(--hozio-blue);
        }
        
        .hozio-toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
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
            border-radius: 28px;
        }
        
        .hozio-toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .hozio-toggle-slider {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
        }
        
        input:checked + .hozio-toggle-slider:before {
            transform: translateX(22px);
        }
        
        .hozio-post-type-info {
            flex: 1;
        }
        
        .hozio-post-type-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--hozio-gray);
            margin-bottom: 4px;
        }
        
        .hozio-post-type-description {
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
            .hozio-post-types-wrapper {
                margin: 20px 0;
            }
            
            .hozio-post-types-header {
                padding: 30px 20px;
            }
            
            .hozio-post-types-header h1 {
                font-size: 24px;
            }
            
            .hozio-post-types-content {
                padding: 0 20px 20px;
            }
            
            .hozio-post-types-card {
                padding: 20px;
                margin: 20px 0;
            }
            
            .hozio-post-types-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Handle toggle interactions
        $('.hozio-post-type-item').on('click', function(e) {
            if (e.target.type !== 'checkbox') {
                const checkbox = $(this).find('input[type="checkbox"]');
                checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
            }
        });
        
        // Update item appearance when checkbox changes
        $('input[type="checkbox"][name="selected_post_types[]"]').on('change', function() {
            const item = $(this).closest('.hozio-post-type-item');
            if ($(this).is(':checked')) {
                item.addClass('selected');
            } else {
                item.removeClass('selected');
            }
        });
        
        // Initialize selected state
        $('input[type="checkbox"][name="selected_post_types[]"]:checked').each(function() {
            $(this).closest('.hozio-post-type-item').addClass('selected');
        });
        
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
add_action('admin_head', 'hozio_query_post_types_admin_styles');

function hozio_query_post_types_page() {
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['selected_post_types'] ) ) {
        // Save selected post types to options
        update_option( 'hozio_selected_post_types', array_map( 'sanitize_text_field', $_POST['selected_post_types'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>Post types saved successfully!</p></div>';
    }

    // Get all public post types
    $post_types = get_post_types( [ 'public' => true ], 'objects' );

    // Define post types to exclude
    $excluded_post_types = [
        'post',        // Posts
        'page',        // Pages
        'attachment',  // Media
        'landing_pages', // Landing Pages (custom post type)
        'floating_elements', // Floating Elements (custom post type)
        'my_templates', // My Templates (custom post type)
        'template',    // Template (custom post type)
        'widget',      // Widgets (custom post type)
    ];

    // Filter out excluded post types
    $post_types = array_filter( $post_types, function( $post_type ) use ( $excluded_post_types ) {
        return ! in_array( $post_type->name, $excluded_post_types );
    });

    // Get saved post types
    $selected_post_types = get_option( 'hozio_selected_post_types', [] );
    ?>
    <div class="hozio-post-types-wrapper">
        <div class="hozio-post-types-header">
            <div class="hozio-header-content">
                <h1>
                    <span class="dashicons dashicons-admin-post"></span>
                    Dynamic Query Post Types
                </h1>
                <p class="hozio-post-types-subtitle">Select which custom post types can be used in dynamic queries and templates</p>
            </div>
        </div>

        <div class="hozio-post-types-content">
            <form method="POST" action="">
                <?php wp_nonce_field('hozio_post_types_save', 'hozio_post_types_nonce'); ?>
                
                <!-- Settings Card -->
                <div class="hozio-post-types-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <h2>Available Post Types</h2>
                    </div>
                    
                    <?php if (empty($post_types)): ?>
                        <div class="hozio-warning">
                            <div class="hozio-warning-header">
                                <span class="dashicons dashicons-info"></span>
                                No Custom Post Types Found
                            </div>
                            <div class="hozio-warning-text">
                                No eligible custom post types were found on this site. Only public custom post types (excluding Posts, Pages, Media, and internal Hozio types) can be selected here.
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="hozio-post-types-grid">
                            <?php foreach ( $post_types as $post_type ): ?>
                                <div class="hozio-post-type-item">
                                    <label class="hozio-toggle-switch">
                                        <input type="checkbox" 
                                               name="selected_post_types[]"
                                               id="post-type-<?php echo esc_attr( $post_type->name ); ?>"
                                               value="<?php echo esc_attr( $post_type->name ); ?>"
                                               <?php checked( in_array( $post_type->name, $selected_post_types ) ); ?>>
                                        <span class="hozio-toggle-slider"></span>
                                    </label>
                                    <div class="hozio-post-type-info">
                                        <div class="hozio-post-type-name"><?php echo esc_html( $post_type->label ); ?></div>
                                        <div class="hozio-post-type-description">
                                            <?php echo $post_type->description ? esc_html( $post_type->description ) : 'Custom post type: ' . esc_html( $post_type->name ); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" name="submit" class="button hozio-save-btn">
                            <span class="dashicons dashicons-yes"></span>
                            Save Selected Post Types
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Info Card -->
                <div class="hozio-post-types-card info-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-info"></span>
                        <h2>About This Feature</h2>
                    </div>
                    
                    <ul class="hozio-info-list">
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Template Integration:</strong> Selected post types become available for use in ACF fields and dynamic templates
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Query Control:</strong> Enables these post types to be included in dynamic content queries
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Excluded Types:</strong> Posts, Pages, Media, and Hozio internal types are automatically excluded
                            </div>
                        </li>
                        <li>
                            <span class="dashicons dashicons-arrow-right-alt hozio-info-icon"></span>
                            <div>
                                <strong>Safe Selection:</strong> Only public post types are shown to ensure compatibility
                            </div>
                        </li>
                    </ul>
                    
                    <div class="hozio-warning">
                        <div class="hozio-warning-header">
                            <span class="dashicons dashicons-lightbulb"></span>
                            Usage Note
                        </div>
                        <div class="hozio-warning-text">
                            This feature is designed for advanced template customization. Only select post types that you actually need in your dynamic queries to keep the interface clean and organized.
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php
}
