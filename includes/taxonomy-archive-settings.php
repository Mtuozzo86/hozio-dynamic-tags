<?php
if (!defined('ABSPATH')) exit;

// Register settings
add_action('admin_init', 'hozio_taxonomy_archive_register_settings');
function hozio_taxonomy_archive_register_settings() {
    register_setting('hozio_taxonomy_archive_settings', 'hozio_parent_pages_archive_enabled');
    register_setting('hozio_taxonomy_archive_settings', 'hozio_town_taxonomies_archive_enabled');
}

// Render the settings page
function hozio_taxonomy_archive_settings_page() {
    // Handle form submission
    if (isset($_POST['submit']) && check_admin_referer('hozio_taxonomy_archive_settings_action')) {
        update_option('hozio_parent_pages_archive_enabled', isset($_POST['hozio_parent_pages_archive_enabled']) ? 1 : 0);
        update_option('hozio_town_taxonomies_archive_enabled', isset($_POST['hozio_town_taxonomies_archive_enabled']) ? 1 : 0);
        
        // Flush rewrite rules after changing settings
        flush_rewrite_rules();
        
        echo '<div class="hozio-success-notice" style="margin: 20px 0;">
            <div class="hozio-success-header">
                <span class="dashicons dashicons-yes-alt"></span>
                Settings Saved Successfully
            </div>
            <div style="color: var(--hozio-green-dark); font-size: 14px;">
                Taxonomy archive settings have been updated. Rewrite rules have been flushed.
            </div>
        </div>';
    }

    $parent_pages_enabled = get_option('hozio_parent_pages_archive_enabled', 0);
    $town_taxonomies_enabled = get_option('hozio_town_taxonomies_archive_enabled', 0);
    
    // Get sample terms for URL preview
    $parent_pages_sample = get_terms(array(
        'taxonomy' => 'parent_pages',
        'hide_empty' => false,
        'number' => 1
    ));
    
    $town_taxonomies_sample = get_terms(array(
        'taxonomy' => 'town_taxonomies',
        'hide_empty' => false,
        'number' => 1
    ));
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
        
        .hozio-archive-wrapper {
            background: #f9fafb;
            margin: 20px 20px 20px 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .hozio-archive-header {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 50%, var(--hozio-orange) 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .hozio-archive-header::before {
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
        
        .hozio-archive-header h1 {
            color: white !important;
            font-size: 32px;
            margin: 0 0 10px !important;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            text-shadow: none;
        }
        
        .hozio-archive-header h1 .dashicons {
            font-size: 36px;
            width: 36px;
            height: 36px;
        }
        
        .hozio-archive-subtitle {
            color: rgba(255, 255, 255, 0.95);
            font-size: 16px;
            margin: 0;
        }
        
        .hozio-archive-content {
            padding: 0 40px 40px;
        }
        
        .hozio-archive-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            border-left: 4px solid var(--hozio-blue);
        }
        
        .hozio-archive-card.info-card {
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
        
        .hozio-taxonomy-setting {
            background: #f9fafb;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        
        .hozio-taxonomy-setting:hover {
            border-color: var(--hozio-blue);
            box-shadow: 0 2px 4px rgba(0, 160, 227, 0.1);
        }
        
        .hozio-taxonomy-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .hozio-taxonomy-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .hozio-taxonomy-title {
            flex: 1;
        }
        
        .hozio-taxonomy-title h3 {
            margin: 0 0 4px 0 !important;
            color: var(--hozio-gray);
            font-size: 18px !important;
            font-weight: 600;
        }
        
        .hozio-taxonomy-slug {
            color: #6b7280;
            font-size: 13px;
            font-family: monospace;
        }
        
        .hozio-toggle-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hozio-toggle-switch {
            position: relative;
            width: 56px;
            height: 28px;
        }
        
        .hozio-toggle-switch input[type="checkbox"] {
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
            background-color: #cbd5e1;
            transition: 0.4s;
            border-radius: 28px;
        }
        
        .hozio-toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        .hozio-toggle-switch input:checked + .hozio-toggle-slider {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%);
        }
        
        .hozio-toggle-switch input:checked + .hozio-toggle-slider:before {
            transform: translateX(28px);
        }
        
        .hozio-toggle-label {
            font-weight: 600;
            color: var(--hozio-gray);
        }
        
        .hozio-archive-preview {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 16px;
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hozio-archive-preview .dashicons {
            color: var(--hozio-blue);
        }
        
        .hozio-archive-url {
            color: var(--hozio-blue);
            text-decoration: none;
            font-family: monospace;
            font-size: 13px;
            word-break: break-all;
        }
        
        .hozio-archive-url:hover {
            text-decoration: underline;
        }
        
        .hozio-disabled-text {
            color: #9ca3af;
            font-size: 13px;
            font-style: italic;
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
        
        @media (max-width: 782px) {
            .hozio-archive-wrapper {
                margin: 20px 0;
            }
            
            .hozio-archive-header {
                padding: 30px 20px;
            }
            
            .hozio-archive-header h1 {
                font-size: 24px;
            }
            
            .hozio-archive-content {
                padding: 0 20px 20px;
            }
            
            .hozio-archive-card {
                padding: 20px;
                margin: 20px 0;
            }
            
            .hozio-taxonomy-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .hozio-toggle-container {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
    
    <div class="hozio-archive-wrapper">
        <div class="hozio-archive-header">
            <div class="hozio-header-content">
                <h1>
                    <span class="dashicons dashicons-archive"></span>
                    Taxonomy Archive Settings
                </h1>
                <p class="hozio-archive-subtitle">Control whether taxonomy archive pages are publicly accessible</p>
            </div>
        </div>

        <div class="hozio-archive-content">
            <!-- Info Card -->
            <div class="hozio-archive-card info-card">
                <div class="hozio-card-header">
                    <span class="dashicons dashicons-info"></span>
                    <h2>About Archive Pages</h2>
                </div>
                
                <div class="hozio-info-notice">
                    <div class="hozio-info-header">
                        <span class="dashicons dashicons-lightbulb"></span>
                        What Are Archive Pages?
                    </div>
                    <div class="hozio-info-text">
                        Archive pages display all posts/pages associated with a specific taxonomy term. When enabled, visitors can access these pages through URLs like the examples shown below. By default, archives are disabled to prevent unwanted indexing and maintain better control over your site's structure.
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <form method="post" action="">
                <?php wp_nonce_field('hozio_taxonomy_archive_settings_action'); ?>
                
                <div class="hozio-archive-card">
                    <div class="hozio-card-header">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <h2>Configure Archive Accessibility</h2>
                    </div>
                    
                    <!-- Page Taxonomies Setting -->
                    <div class="hozio-taxonomy-setting">
                        <div class="hozio-taxonomy-header">
                            <div class="hozio-taxonomy-icon">
                                <span class="dashicons dashicons-category"></span>
                            </div>
                            <div class="hozio-taxonomy-title">
                                <h3>Page Taxonomies</h3>
                                <div class="hozio-taxonomy-slug">Taxonomy: parent_pages</div>
                            </div>
                            <div class="hozio-toggle-container">
                                <span class="hozio-toggle-label">Archive Pages</span>
                                <label class="hozio-toggle-switch">
                                    <input type="checkbox" name="hozio_parent_pages_archive_enabled" <?php checked($parent_pages_enabled, 1); ?>>
                                    <span class="hozio-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="hozio-archive-preview">
                            <?php if ($parent_pages_enabled && !empty($parent_pages_sample)): ?>
                                <span class="dashicons dashicons-admin-links"></span>
                                <a href="<?php echo esc_url(get_term_link($parent_pages_sample[0])); ?>" target="_blank" class="hozio-archive-url">
                                    <?php echo esc_url(get_term_link($parent_pages_sample[0])); ?>
                                </a>
                            <?php elseif (!$parent_pages_enabled && !empty($parent_pages_sample)): ?>
                                <span class="dashicons dashicons-lock" style="color: #9ca3af;"></span>
                                <span class="hozio-disabled-text">
                                    Example: <?php echo esc_html(home_url('/parent-pages/' . $parent_pages_sample[0]->slug . '/')); ?> (disabled)
                                </span>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #9ca3af;"></span>
                                <span class="hozio-disabled-text">No terms available for preview</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Town Taxonomies Setting -->
                    <div class="hozio-taxonomy-setting">
                        <div class="hozio-taxonomy-header">
                            <div class="hozio-taxonomy-icon">
                                <span class="dashicons dashicons-location"></span>
                            </div>
                            <div class="hozio-taxonomy-title">
                                <h3>Town Taxonomies</h3>
                                <div class="hozio-taxonomy-slug">Taxonomy: town_taxonomies</div>
                            </div>
                            <div class="hozio-toggle-container">
                                <span class="hozio-toggle-label">Archive Pages</span>
                                <label class="hozio-toggle-switch">
                                    <input type="checkbox" name="hozio_town_taxonomies_archive_enabled" <?php checked($town_taxonomies_enabled, 1); ?>>
                                    <span class="hozio-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="hozio-archive-preview">
                            <?php if ($town_taxonomies_enabled && !empty($town_taxonomies_sample)): ?>
                                <span class="dashicons dashicons-admin-links"></span>
                                <a href="<?php echo esc_url(get_term_link($town_taxonomies_sample[0])); ?>" target="_blank" class="hozio-archive-url">
                                    <?php echo esc_url(get_term_link($town_taxonomies_sample[0])); ?>
                                </a>
                            <?php elseif (!$town_taxonomies_enabled && !empty($town_taxonomies_sample)): ?>
                                <span class="dashicons dashicons-lock" style="color: #9ca3af;"></span>
                                <span class="hozio-disabled-text">
                                    Example: <?php echo esc_html(home_url('/town/' . $town_taxonomies_sample[0]->slug . '/')); ?> (disabled)
                                </span>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #9ca3af;"></span>
                                <span class="hozio-disabled-text">No terms available for preview</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="submit" class="hozio-btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        Save Archive Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>
