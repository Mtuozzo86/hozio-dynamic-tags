<?php
/**
 * Sitemap Settings Page (Tabbed)
 * Combines Appearance settings and Layout Editor under one menu item
 */

// Add the sitemap settings submenu to Hozio Pro menu
function hozio_register_sitemap_settings_menu() {
    add_submenu_page(
        'hozio_dynamic_tags',           // Parent slug (Hozio Pro menu)
        'Sitemap Settings',             // Page title
        'Sitemap Settings',             // Menu title
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

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'appearance';

    // Only load color picker assets on the appearance tab
    if ($tab !== 'layout') {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        add_action('admin_head', 'hozio_sitemap_settings_inline_styles');
        add_action('admin_footer', 'hozio_sitemap_color_picker_script');
    }
}
add_action('admin_enqueue_scripts', 'hozio_sitemap_settings_admin_assets', 999);

// Color picker initialization script
function hozio_sitemap_color_picker_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Initialize standard link color pickers
        $('.hozio-color-picker').wpColorPicker();

        // Luminance helper — determines if a color is light or dark
        function getLuminance(hex) {
            hex = hex.replace('#', '');
            var r = parseInt(hex.substr(0, 2), 16) / 255;
            var g = parseInt(hex.substr(2, 2), 16) / 255;
            var b = parseInt(hex.substr(4, 2), 16) / 255;
            return 0.299 * r + 0.587 * g + 0.114 * b;
        }

        // Lighten or darken a hex color by an amount
        function adjustColor(hex, amount) {
            hex = hex.replace('#', '');
            var r = Math.min(255, Math.max(0, parseInt(hex.substr(0, 2), 16) + amount));
            var g = Math.min(255, Math.max(0, parseInt(hex.substr(2, 2), 16) + amount));
            var b = Math.min(255, Math.max(0, parseInt(hex.substr(4, 2), 16) + amount));
            return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        }

        // Update the custom preview card colors based on chosen background
        function updateCustomPreview(color) {
            if (!color || color.length < 7) return;
            var isDark = getLuminance(color) < 0.5;
            var textColor = isDark ? '#ffffff' : '#000000';
            var subtextColor = isDark ? '#cccccc' : '#374151';
            var titleColor = isDark ? '#9ca3af' : '#6b7280';
            var sampleBg = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)';
            var borderColor = isDark ? adjustColor(color, 40) : adjustColor(color, -30);

            $('#custom-preview-box').css({ 'background': color, 'border-color': borderColor });
            $('#custom-preview-title').css('color', titleColor);
            $('#custom-preview-sample').css('background', sampleBg);
            $('#custom-preview-heading').css('color', textColor);
            $('#custom-preview-text').css('color', subtextColor);
        }

        // Initialize the custom BG color picker with live preview callback
        $('#hozio_sitemap_custom_bg_color').wpColorPicker({
            change: function(event, ui) {
                updateCustomPreview(ui.color.toString());
            },
            clear: function() {
                updateCustomPreview('#f5f5dc');
            }
        });

        // Select a background mode (light, dark, or custom)
        function selectMode(mode) {
            $('#hozio_sitemap_bg_mode').val(mode);

            // Update pill active states
            $('.hozio-bg-pill').removeClass('active');
            $('.hozio-bg-pill[data-mode="' + mode + '"]').addClass('active');

            // Update preview card active states
            $('.preview-box').removeClass('active-selection');
            $('.preview-box[data-mode="' + mode + '"]').addClass('active-selection');

            // Show/hide custom color picker
            if (mode === 'custom') {
                $('#hozio-custom-picker-row').addClass('visible');
            } else {
                $('#hozio-custom-picker-row').removeClass('visible');
            }
        }

        // Pill button clicks
        $('.hozio-bg-pill').on('click', function() {
            selectMode($(this).data('mode'));
        });

        // Preview card clicks (also act as selectors)
        $('.preview-box[data-mode]').on('click', function() {
            selectMode($(this).data('mode'));
        });

        // Set custom preview colors on page load
        var initialColor = $('#hozio_sitemap_custom_bg_color').val() || '#f5f5dc';
        updateCustomPreview(initialColor);
    });
    </script>
    <?php
}

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

        /* Tab bar — overrides content margin when present */
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

        /* Color Picker Field Styles */
        .hozio-color-field {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .hozio-color-input-wrapper {
            flex: 0 0 auto;
        }

        .hozio-color-info {
            flex: 1;
        }

        .wp-picker-container {
            margin-top: 0;
        }

        .wp-picker-input-wrap {
            display: flex;
            align-items: center;
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

        /* Background Mode Pill Selector */
        .hozio-bg-mode-selector {
            display: flex;
            gap: 0;
            background: #f3f4f6;
            border-radius: 12px;
            padding: 4px;
            margin-bottom: 16px;
            width: fit-content;
        }

        .hozio-bg-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            background: transparent;
            border: 2px solid transparent;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            white-space: nowrap;
            line-height: 1;
        }

        .hozio-bg-pill:hover {
            color: #374151;
            background: rgba(255,255,255,0.6);
        }

        .hozio-bg-pill.active {
            background: white;
            color: var(--hozio-blue);
            border-color: var(--hozio-blue);
            box-shadow: 0 2px 8px rgba(0, 160, 227, 0.15);
        }

        .hozio-bg-pill .color-dot {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .hozio-bg-pill .color-dot.dot-light {
            background: #ffffff;
            border: 2px solid #d1d5db;
        }

        .hozio-bg-pill .color-dot.dot-dark {
            background: #000000;
            border: 2px solid #555;
        }

        .hozio-bg-pill .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .hozio-custom-picker-row {
            display: none;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px dashed var(--hozio-blue);
        }

        .hozio-custom-picker-row.visible {
            display: flex;
        }

        .hozio-custom-picker-label {
            font-weight: 600;
            font-size: 14px;
            color: #374151;
            white-space: nowrap;
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
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .preview-box {
            border-radius: 8px;
            padding: 20px;
            border: 2px solid #e5e7eb;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .preview-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .preview-box.active-selection {
            border-color: var(--hozio-blue) !important;
            box-shadow: 0 0 0 3px rgba(0, 160, 227, 0.2);
        }

        .preview-badge {
            display: none;
            position: absolute;
            top: -10px;
            right: 12px;
            background: var(--hozio-blue);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .preview-box.active-selection .preview-badge {
            display: block;
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
            align-items: flex-start;
            gap: 10px;
            color: #6b7280;
            margin: 0;
            font-size: 14px;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 3px solid var(--hozio-blue);
        }

        .hozio-info-text .dashicons {
            color: var(--hozio-blue);
            font-size: 20px;
            flex-shrink: 0;
            margin-top: 2px;
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

        @media (max-width: 960px) {
            .preview-grid {
                grid-template-columns: 1fr;
            }
            .hozio-bg-mode-selector {
                flex-wrap: wrap;
            }
            .hozio-header {
                padding: 30px 20px;
            }
            .hozio-content {
                padding: 0 20px 20px;
            }
            .hozio-color-field {
                flex-direction: column;
            }
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

// Display the settings page (tab dispatcher)
function hozio_sitemap_settings_page() {
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'appearance';

    // Delegate to layout editor if on layout tab
    if ($tab === 'layout') {
        hozio_sitemap_layout_page();
        return;
    }

    // Delegate to image sitemap tab
    if ($tab === 'image-sitemap') {
        hozio_image_sitemap_page();
        return;
    }

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

    // Get current color values
    $link_color = get_option('hozio_sitemap_link_color', '');
    $link_hover_color = get_option('hozio_sitemap_link_hover_color', '');
    $border_color_val = get_option('hozio_sitemap_border_color', '');

    // Get background mode (with backward compat for old dark_mode toggle)
    $bg_mode = get_option('hozio_sitemap_bg_mode', '');
    if ($bg_mode === '') {
        $bg_mode = get_option('hozio_sitemap_dark_mode', '0') === '1' ? 'dark' : 'light';
    }
    $custom_bg_color = get_option('hozio_sitemap_custom_bg_color', '#f5f5dc');
    ?>
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
            <a href="<?php echo esc_url(admin_url('admin.php?page=hozio-sitemap-settings&tab=appearance')); ?>" class="hozio-tab<?php echo ($tab === 'appearance' || $tab === '') ? ' active' : ''; ?>">
                <span class="dashicons dashicons-admin-appearance"></span> Appearance
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=hozio-sitemap-settings&tab=layout')); ?>" class="hozio-tab<?php echo $tab === 'layout' ? ' active' : ''; ?>">
                <span class="dashicons dashicons-layout"></span> Layout Editor
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=hozio-sitemap-settings&tab=image-sitemap')); ?>" class="hozio-tab<?php echo $tab === 'image-sitemap' ? ' active' : ''; ?>">
                <span class="dashicons dashicons-format-image"></span> Image Sitemap
            </a>
        </div>

        <div class="hozio-content">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hozio-form">
                <?php wp_nonce_field('hozio_save_sitemap_settings_nonce', 'hozio_save_sitemap_settings_nonce_field'); ?>
                <input type="hidden" name="action" value="hozio_save_sitemap_settings" />

                <!-- Background Color Settings Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <h2>Display Settings</h2>
                    </div>

                    <input type="hidden" name="hozio_sitemap_bg_mode" id="hozio_sitemap_bg_mode" value="<?php echo esc_attr($bg_mode); ?>" />

                    <div class="hozio-bg-mode-selector">
                        <button type="button" class="hozio-bg-pill<?php echo $bg_mode === 'light' ? ' active' : ''; ?>" data-mode="light">
                            <span class="color-dot dot-light"></span> Light
                        </button>
                        <button type="button" class="hozio-bg-pill<?php echo $bg_mode === 'dark' ? ' active' : ''; ?>" data-mode="dark">
                            <span class="color-dot dot-dark"></span> Dark
                        </button>
                        <button type="button" class="hozio-bg-pill<?php echo $bg_mode === 'custom' ? ' active' : ''; ?>" data-mode="custom">
                            <span class="dashicons dashicons-art"></span> Custom Color
                        </button>
                    </div>

                    <p class="hozio-field-description" style="margin-top: 0; margin-bottom: 20px;">
                        Choose the background color for your HTML sitemap. Text and accent colors automatically adjust for readability.
                    </p>

                    <div class="hozio-custom-picker-row<?php echo $bg_mode === 'custom' ? ' visible' : ''; ?>" id="hozio-custom-picker-row">
                        <span class="hozio-custom-picker-label">Choose your color:</span>
                        <input type="text" name="hozio_sitemap_custom_bg_color" id="hozio_sitemap_custom_bg_color" value="<?php echo esc_attr($custom_bg_color); ?>" class="hozio-bg-color-picker" />
                    </div>

                    <div class="hozio-dark-mode-preview">
                        <div class="preview-label">
                            <span class="dashicons dashicons-visibility"></span>
                            Live Preview
                        </div>
                        <div class="preview-grid">
                            <div class="preview-item">
                                <div class="preview-box light-mode<?php echo $bg_mode === 'light' ? ' active-selection' : ''; ?>" data-mode="light">
                                    <span class="preview-badge">Active</span>
                                    <div class="preview-title">Light</div>
                                    <div class="preview-sample">
                                        <div class="sample-heading">Pages Heading</div>
                                        <div class="sample-text">About Us &bull; Contact &bull; Services</div>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-item">
                                <div class="preview-box dark-mode<?php echo $bg_mode === 'dark' ? ' active-selection' : ''; ?>" data-mode="dark">
                                    <span class="preview-badge">Active</span>
                                    <div class="preview-title">Dark</div>
                                    <div class="preview-sample">
                                        <div class="sample-heading">Pages Heading</div>
                                        <div class="sample-text">About Us &bull; Contact &bull; Services</div>
                                    </div>
                                </div>
                            </div>
                            <div class="preview-item">
                                <div class="preview-box custom-mode<?php echo $bg_mode === 'custom' ? ' active-selection' : ''; ?>" id="custom-preview-box" data-mode="custom" style="background: <?php echo esc_attr($custom_bg_color); ?>;">
                                    <span class="preview-badge">Active</span>
                                    <div class="preview-title" id="custom-preview-title">Custom</div>
                                    <div class="preview-sample" id="custom-preview-sample">
                                        <div class="sample-heading" id="custom-preview-heading">Pages Heading</div>
                                        <div class="sample-text" id="custom-preview-text">About Us &bull; Contact &bull; Services</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Color Customization Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-art"></span>
                        <h2>Color Customization</h2>
                    </div>

                    <div class="hozio-info-text" style="margin-bottom: 24px;">
                        <span class="dashicons dashicons-info"></span>
                        <span>By default, links will inherit colors from your Elementor global styles and borders use the default theme color. Set custom colors below to override for the sitemap only.</span>
                    </div>

                    <!-- Link Color Field -->
                    <div class="hozio-color-field">
                        <div class="hozio-color-input-wrapper">
                            <label class="hozio-field-label">Link Color</label>
                            <input type="text" name="hozio_sitemap_link_color" value="<?php echo esc_attr($link_color); ?>" class="hozio-color-picker" />
                        </div>
                        <div class="hozio-color-info">
                            <p class="hozio-field-description" style="margin-top: 32px;">
                                Set the default color for all links in the sitemap. Leave empty to use Elementor global link color.
                            </p>
                        </div>
                    </div>

                    <!-- Link Hover Color Field -->
                    <div class="hozio-color-field">
                        <div class="hozio-color-input-wrapper">
                            <label class="hozio-field-label">Link Hover Color</label>
                            <input type="text" name="hozio_sitemap_link_hover_color" value="<?php echo esc_attr($link_hover_color); ?>" class="hozio-color-picker" />
                        </div>
                        <div class="hozio-color-info">
                            <p class="hozio-field-description" style="margin-top: 32px;">
                                Set the color for links when hovering over them. Leave empty to use Elementor global hover color.
                            </p>
                        </div>
                    </div>

                    <!-- Border & Divider Color Field -->
                    <div class="hozio-color-field">
                        <div class="hozio-color-input-wrapper">
                            <label class="hozio-field-label">Border & Divider Color</label>
                            <input type="text" name="hozio_sitemap_border_color" value="<?php echo esc_attr($border_color_val); ?>" class="hozio-color-picker" />
                        </div>
                        <div class="hozio-color-info">
                            <p class="hozio-field-description" style="margin-top: 32px;">
                                Set the color for section borders, item dividers, and drawer lines. Leave empty to use the default based on your background mode.
                            </p>
                        </div>
                    </div>
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

    // Save background mode setting
    $bg_mode = isset($_POST['hozio_sitemap_bg_mode']) ? sanitize_key($_POST['hozio_sitemap_bg_mode']) : 'light';
    if (!in_array($bg_mode, array('light', 'dark', 'custom'))) {
        $bg_mode = 'light';
    }
    update_option('hozio_sitemap_bg_mode', $bg_mode);

    // Save custom background color
    $custom_bg_color = isset($_POST['hozio_sitemap_custom_bg_color']) ? sanitize_hex_color($_POST['hozio_sitemap_custom_bg_color']) : '';
    update_option('hozio_sitemap_custom_bg_color', $custom_bg_color ? $custom_bg_color : '#f5f5dc');

    // Keep dark_mode in sync for backward compat
    update_option('hozio_sitemap_dark_mode', $bg_mode === 'dark' ? '1' : '0');

    // Save link color (sanitize as hex color or empty)
    $link_color = isset($_POST['hozio_sitemap_link_color']) ? sanitize_hex_color($_POST['hozio_sitemap_link_color']) : '';
    update_option('hozio_sitemap_link_color', $link_color);

    // Save link hover color (sanitize as hex color or empty)
    $link_hover_color = isset($_POST['hozio_sitemap_link_hover_color']) ? sanitize_hex_color($_POST['hozio_sitemap_link_hover_color']) : '';
    update_option('hozio_sitemap_link_hover_color', $link_hover_color);

    // Save border color (sanitize as hex color or empty)
    $border_color = isset($_POST['hozio_sitemap_border_color']) ? sanitize_hex_color($_POST['hozio_sitemap_border_color']) : '';
    update_option('hozio_sitemap_border_color', $border_color);

    // Redirect back with success message
    wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=hozio-sitemap-settings')));
    exit;
}
add_action('admin_post_hozio_save_sitemap_settings', 'hozio_save_sitemap_settings');
