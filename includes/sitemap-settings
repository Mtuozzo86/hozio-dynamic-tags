<?php
/**
 * HTML Sitemap Settings Page
 * Separate menu item for HTML Sitemap configuration
 */

// Add the sitemap settings submenu to Hozio Pro menu
function hozio_register_sitemap_settings_menu() {
    add_submenu_page(
        'hozio_dynamic_tags',           // Parent slug (Hozio Pro menu)
        'HTML Sitemap Settings',        // Page title
        'HTML Sitemap Settings',        // Menu title
        'manage_options',               // Capability
        'hozio-sitemap-settings',       // Menu slug
        'hozio_sitemap_settings_page'   // Callback function
    );
}
add_action('admin_menu', 'hozio_register_sitemap_settings_menu', 20);

// Enqueue admin styles for sitemap settings page
function hozio_sitemap_settings_admin_assets($hook) {
    if (strpos($hook, 'hozio-sitemap-settings') === false) {
        return;
    }
    
    // Add inline styles to match Hozio Pro styling
    add_action('admin_head', 'hozio_sitemap_settings_inline_styles');
}
add_action('admin_enqueue_scripts', 'hozio_sitemap_settings_admin_assets', 999);

// Inline styles for sitemap settings page
function hozio_sitemap_settings_inline_styles() {
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
        
        .hozio-header-content h1 {
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
        
        .hozio-field {
            margin-bottom: 20px;
        }
        
        .hozio-field-description {
            color: #6b7280;
            font-size: 14px;
            margin: 12px 0 0 72px;
            line-height: 1.6;
        }
        
        /* Toggle Switch Styles */
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
            border-radius: 32px;
        }
        
        .hozio-toggle-slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .hozio-toggle-slider {
            background-color: var(--hozio-blue);
        }
        
        input:checked + .hozio-toggle-slider:before {
            transform: translateX(28px);
        }
        
        .hozio-toggle-label {
            font-weight: 600;
            font-size: 16px;
            color: #1f2937;
        }
        
        /* Preview Styles */
        .hozio-dark-mode-preview {
            margin-top: 24px;
            padding: 24px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .preview-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .preview-label .dashicons {
            color: var(--hozio-blue);
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .preview-box {
            border-radius: 8px;
            padding: 20px;
            border: 2px solid #e5e7eb;
        }
        
        .preview-box.light-mode {
            background: #ffffff;
            border-color: #e5e7eb;
        }
        
        .preview-box.dark-mode {
            background: #000000;
            border-color: #333333;
        }
        
        .preview-title {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .preview-box.light-mode .preview-title {
            color: #6b7280;
        }
        
        .preview-box.dark-mode .preview-title {
            color: #9ca3af;
        }
        
        .preview-sample {
            padding: 16px;
            border-radius: 6px;
        }
        
        .preview-box.light-mode .preview-sample {
            background: #f9fafb;
        }
        
        .preview-box.dark-mode .preview-sample {
            background: #1a1a1a;
        }
        
        .sample-heading {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .preview-box.light-mode .sample-heading {
            color: #000000;
        }
        
        .preview-box.dark-mode .sample-heading {
            color: #ffffff;
        }
        
        .sample-text {
            font-size: 14px;
            line-height: 1.5;
        }
        
        .preview-box.light-mode .sample-text {
            color: #374151;
        }
        
        .preview-box.dark-mode .sample-text {
            color: #cccccc;
        }
        
        .hozio-info-text {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #6b7280;
            margin: 0;
            font-size: 14px;
        }
        
        .hozio-info-text .dashicons {
            color: var(--hozio-blue);
            font-size: 20px;
        }
        
        .hozio-submit-wrapper {
            margin-top: 30px;
            text-align: left;
        }
        
        .hozio-submit-btn {
            background: var(--hozio-blue) !important;
            border-color: var(--hozio-blue-dark) !important;
            color: white !important;
            padding: 12px 32px !important;
            font-size: 16px !important;
            border-radius: 8px !important;
            box-shadow: 0 2px 4px rgba(0, 160, 227, 0.2) !important;
            transition: all 0.3s ease !important;
            height: auto !important;
            text-shadow: none !important;
        }
        
        .hozio-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 160, 227, 0.3) !important;
        }
        
        .notice {
            margin: 20px 40px 0;
        }
        
        @media (max-width: 768px) {
            .preview-grid {
                grid-template-columns: 1fr;
            }
            .hozio-header {
                padding: 30px 20px;
            }
            .hozio-content {
                padding: 0 20px 20px;
            }
            .hozio-field-description {
                margin-left: 0;
            }
        }
    </style>
    <?php
}

// Display the HTML Sitemap settings page
function hozio_sitemap_settings_page() {
    // Check if settings were saved
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        add_settings_error(
            'hozio_sitemap_settings',
            'hozio_sitemap_settings_updated',
            'Settings saved successfully!',
            'success'
        );
    }
    
    settings_errors('hozio_sitemap_settings');
    ?>
    <div class="hozio-settings-wrapper">
        <div class="hozio-header">
            <div class="hozio-header-content">
                <h1>
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    HTML Sitemap Settings
                </h1>
                <p class="hozio-subtitle">Configure your HTML sitemap appearance and functionality</p>
            </div>
        </div>

        <div class="hozio-content">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hozio-form">
                <?php wp_nonce_field('hozio_save_sitemap_settings_nonce', 'hozio_save_sitemap_settings_nonce_field'); ?>
                <input type="hidden" name="action" value="hozio_save_sitemap_settings" />

                <!-- Dark Mode Settings Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <h2>Display Settings</h2>
                    </div>
                    
                    <div class="hozio-field">
                        <div class="hozio-toggle-wrapper">
                            <label class="hozio-toggle-switch">
                                <input type="checkbox" name="hozio_sitemap_dark_mode" value="1" <?php checked(get_option('hozio_sitemap_dark_mode'), '1'); ?> />
                                <span class="hozio-toggle-slider"></span>
                            </label>
                            <span class="hozio-toggle-label">Enable Dark Mode</span>
                        </div>
                        <p class="hozio-field-description">
                            When enabled, the HTML sitemap will display with a dark theme featuring black backgrounds and white text. 
                            This provides better visibility for dark-themed websites and reduces eye strain in low-light conditions.
                        </p>
                    </div>

                    <div class="hozio-dark-mode-preview">
                        <div class="preview-label">
                            <span class="dashicons dashicons-visibility"></span>
                            Color Preview
                        </div>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <div class="preview-box light-mode">
                                    <div class="preview-title">Light Mode (Default)</div>
                                    <div class="preview-sample">
                                        <div class="sample-heading">Pages Heading</div>
                                        <div class="sample-text">About Us • Contact • Services</div>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-item">
                                <div class="preview-box dark-mode">
                                    <div class="preview-title">Dark Mode</div>
                                    <div class="preview-sample">
                                        <div class="sample-heading">Pages Heading</div>
                                        <div class="sample-text">About Us • Contact • Services</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Settings Section (Future Features) -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <h2>Additional Settings</h2>
                    </div>
                    <p class="hozio-info-text">
                        <span class="dashicons dashicons-info"></span>
                        More sitemap customization options will be available in future updates.
                    </p>
                </div>

                <div class="hozio-submit-wrapper">
                    <?php submit_button('Save Settings', 'primary hozio-submit-btn', 'submit', false); ?>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// Handle the sitemap settings save functionality
function hozio_save_sitemap_settings() {
    // Verify nonce
    if (!isset($_POST['hozio_save_sitemap_settings_nonce_field']) || 
        !wp_verify_nonce($_POST['hozio_save_sitemap_settings_nonce_field'], 'hozio_save_sitemap_settings_nonce')) {
        wp_die('Security check failed');
    }

    // Verify user permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized user');
    }

    // Save dark mode setting
    update_option('hozio_sitemap_dark_mode', isset($_POST['hozio_sitemap_dark_mode']) ? '1' : '0');

    // Redirect back with success message
    wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=hozio-sitemap-settings')));
    exit;
}
add_action('admin_post_hozio_save_sitemap_settings', 'hozio_save_sitemap_settings');
