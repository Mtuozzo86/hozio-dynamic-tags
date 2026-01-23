<?php
// Enqueue custom admin styles and scripts with INLINE styles as backup
function hozio_dynamic_tags_admin_assets($hook) {
    // Check if we're on the right page
    if (strpos($hook, 'hozio_dynamic_tags') === false) {
        return;
    }
    
    // Enqueue WordPress color picker
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    
    // Try to enqueue external files
    $plugin_dir = plugin_dir_url(__FILE__);
    
    // Enqueue styles
    wp_enqueue_style('hozio-admin-styles', $plugin_dir . 'assets/admin-styles.css', [], time());
    
    // Enqueue scripts
    wp_enqueue_script('hozio-admin-script', $plugin_dir . 'assets/admin-script.js', ['jquery', 'wp-color-picker'], time(), true);
    
    // BACKUP: Add inline styles if external CSS fails to load
    add_action('admin_head', 'hozio_dynamic_tags_inline_styles');
    
    // Add color picker initialization
    add_action('admin_footer', 'hozio_color_picker_init');
}
add_action('admin_enqueue_scripts', 'hozio_dynamic_tags_admin_assets', 999);

// Initialize color picker
function hozio_color_picker_init() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.hozio-color-picker').wpColorPicker();
    });
    </script>
    <?php
}

// Inline styles as backup (ensures styling always works)
function hozio_dynamic_tags_inline_styles() {
    ?>
    <style>
        /* Critical Hozio Styles - Inline Backup */
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
        
        .hozio-logo-wrapper {
            margin-bottom: 20px;
        }
        
        .hozio-logo-wrapper img {
            height: 50px;
            width: auto;
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
        
        .hozio-section:nth-child(2) {
            border-left-color: var(--hozio-green);
        }
        
        .hozio-section:nth-child(3) {
            border-left-color: var(--hozio-orange);
        }
        
        .hozio-section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .hozio-section-header h2 {
            margin: 0 !important;
            font-size: 20px;
            color: #1f2937;
        }
        
        .hozio-section-header .dashicons {
            color: var(--hozio-blue);
            font-size: 24px;
        }
        
        .hozio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .hozio-grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
        
        .hozio-field {
            display: flex;
            flex-direction: column;
        }
        
        .hozio-field label {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .hozio-input-group {
            position: relative;
        }
        
        .hozio-input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--hozio-blue);
            display: flex;
            align-items: center;
        }
        
        .hozio-input,
        .hozio-textarea,
        .hozio-input-number {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }
        
        .hozio-input-group .hozio-input {
            padding-left: 40px;
        }
        
        .hozio-input-number {
            padding-left: 12px;
        }
        
        .hozio-textarea {
            padding-left: 12px;
            min-height: 100px;
            resize: vertical;
        }
        
        .hozio-input:focus,
        .hozio-textarea:focus,
        .hozio-input-number:focus {
            outline: none;
            border-color: var(--hozio-blue);
            box-shadow: 0 0 0 3px rgba(0, 160, 227, 0.1);
        }
        
        .hozio-color-picker-wrapper {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .hozio-color-picker {
            flex: 1;
            padding: 10px 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .hozio-color-input {
            width: 60px;
            height: 44px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .hozio-calculated-value {
            margin-top: 12px;
            padding: 12px 16px;
            background: linear-gradient(135deg, rgba(0, 160, 227, 0.1) 0%, rgba(141, 198, 63, 0.1) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hozio-calculated-value .highlight {
            color: var(--hozio-orange);
            font-weight: 600;
            font-size: 16px;
        }
        
        .hozio-field-description {
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
            font-style: italic;
        }
        
        .hozio-submit-wrapper {
            display: flex;
            gap: 12px;
            padding: 24px 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        .hozio-submit-btn {
            background: linear-gradient(135deg, var(--hozio-blue) 0%, var(--hozio-green) 100%) !important;
            border: none !important;
            color: white !important;
            padding: 12px 32px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            height: auto !important;
            text-shadow: none !important;
            box-shadow: 0 4px 6px rgba(0, 160, 227, 0.3) !important;
        }
        
        .hozio-submit-btn:hover {
            transform: translateY(-2px);
        }
        
        .hozio-reset-btn {
            padding: 12px 24px !important;
            border: 2px solid var(--hozio-orange) !important;
            background: white !important;
            color: var(--hozio-orange) !important;
            border-radius: 8px !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            height: auto !important;
        }
        
        .hozio-reset-btn:hover {
            background: var(--hozio-orange) !important;
            color: white !important;
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
            font-weight: 500;
            color: #1f2937;
        }
        
        /* HTML Sitemap Subsection Styles */
        .hozio-subsection {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        
        .hozio-subsection-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 16px 0;
        }
        
        .hozio-subsection-title .dashicons {
            color: var(--hozio-blue);
            font-size: 20px;
        }
        
        .hozio-info-text {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #6b7280;
            margin: 0 0 24px 0;
            font-size: 14px;
            padding: 12px 16px;
            background: #f9fafb;
            border-radius: 6px;
            border-left: 3px solid var(--hozio-blue);
            line-height: 1.6;
        }
        
        .hozio-info-text .dashicons {
            color: var(--hozio-blue);
            font-size: 18px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        /* Color Picker Field Styles */
        .hozio-color-field {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .hozio-color-input-wrapper {
            flex: 0 0 auto;
        }
        
        .hozio-field-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .hozio-color-info {
            flex: 1;
        }
        
        .hozio-color-info .hozio-field-description {
            margin-top: 0;
            margin-left: 0;
        }
        
        .wp-picker-container {
            margin-top: 0;
        }
        
        @media (max-width: 782px) {
            .hozio-grid,
            .hozio-grid-3 {
                grid-template-columns: 1fr;
            }
            .hozio-header {
                padding: 30px 20px;
            }
            .hozio-content {
                padding: 0 20px 20px;
            }
            .hozio-color-field {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
    <?php
}

// Register settings for Hozio Dynamic Tags
function hozio_dynamic_tags_register_settings() {
    $fields = [
        'hozio_company_phone_1',
        'hozio_company_phone_2',
        'hozio_google_ads_phone',
        'hozio_sms_phone',
        'hozio_company_email',
        'hozio_company_address',
        'hozio_business_hours',
        'hozio_yelp_url',
        'hozio_youtube_url',
        'hozio_angies_list_url',
        'hozio_home_advisor_url',
        'hozio_bbb_url',
        'hozio_facebook_url',
        'hozio_instagram_url',
        'hozio_twitter_url',
        'hozio_tiktok_url',
        'hozio_linkedin_url',
        'hozio_gmb_link',
        'hozio_to_email_contact_form',
        'hozio_nav_text_color',
        'hozio_start_year',
    ];

    foreach ($fields as $field) {
        register_setting('hozio_dynamic_tags_options', $field);
    }
    
    $custom_tags = get_option('hozio_custom_tags', []);
    if (is_array($custom_tags)) {
        foreach ($custom_tags as $tag) {
            register_setting('hozio_dynamic_tags_options', 'hozio_' . $tag['value']);
        }
    }
}
add_action('admin_init', 'hozio_dynamic_tags_register_settings');

// Render input fields with enhanced styling
function hozio_dynamic_tags_render_input($args) {
    $option = get_option($args['label_for'], '');
    $field_id = $args['label_for'];
    
    // Check if this is a custom tag
    $is_custom_tag = false;
    if (strpos($field_id, 'hozio_') === 0) {
        $custom_tags = get_option('hozio_custom_tags', []);
        if (is_array($custom_tags)) {
            foreach ($custom_tags as $tag) {
                if ('hozio_' . $tag['value'] === $field_id) {
                    $is_custom_tag = true;
                    break;
                }
            }
        }
    }
    
    echo '<div class="hozio-field-wrapper">';
    
    if ($field_id === 'hozio_start_year') {
        $stored_start_year = get_option('hozio_start_year', '');
        $current_year = (int) date('Y');
        $years_of_experience = ($stored_start_year) ? $current_year - (int) $stored_start_year : 0;
        
        printf(
            '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="hozio-input-number" min="1900" max="%3$s" />
            <div class="hozio-calculated-value">
                <span class="dashicons dashicons-calendar-alt"></span>
                <strong>Years of Experience:</strong> <span class="highlight">%4$s years</span>
            </div>',
            esc_attr($field_id),
            esc_attr($stored_start_year),
            esc_attr($current_year),
            esc_html($years_of_experience)
        );
    } elseif ($field_id === 'hozio_nav_text_color') {
        printf(
            '<div class="hozio-color-picker-wrapper">
                <input type="text" id="%1$s" name="%1$s" value="%2$s" class="hozio-color-picker" />
                <input type="color" class="hozio-color-input" value="%2$s" />
            </div>',
            esc_attr($field_id),
            esc_attr($option)
        );
    } elseif ($field_id === 'hozio_company_address' || $field_id === 'hozio_business_hours' || $is_custom_tag) {
        // Render textarea for fields that may contain HTML (including ALL custom tags)
        printf(
            '<textarea id="%1$s" name="%1$s" class="hozio-textarea" rows="4" placeholder="Enter content...">%2$s</textarea>
            <p class="hozio-field-description">HTML tags are allowed in this field</p>',
            esc_attr($field_id),
            esc_textarea($option)
        );
    } else {
        $icon = hozio_get_field_icon($field_id);
        $placeholder = hozio_get_field_placeholder($field_id);
        
        printf(
            '<div class="hozio-input-group">
                <span class="hozio-input-icon">%1$s</span>
                <input type="text" id="%2$s" name="%2$s" value="%3$s" class="hozio-input" placeholder="%4$s" />
            </div>',
            $icon,
            esc_attr($field_id),
            esc_attr($option),
            esc_attr($placeholder)
        );
    }
    
    echo '</div>';
}

// Helper function to get icon for each field
function hozio_get_field_icon($field_id) {
    $icons = [
        'hozio_company_phone_1' => '<span class="dashicons dashicons-phone"></span>',
        'hozio_company_phone_2' => '<span class="dashicons dashicons-phone"></span>',
        'hozio_google_ads_phone' => '<span class="dashicons dashicons-phone"></span>',
        'hozio_sms_phone' => '<span class="dashicons dashicons-smartphone"></span>',
        'hozio_company_email' => '<span class="dashicons dashicons-email"></span>',
        'hozio_yelp_url' => '<span class="dashicons dashicons-star-filled"></span>',
        'hozio_youtube_url' => '<span class="dashicons dashicons-video-alt3"></span>',
        'hozio_facebook_url' => '<span class="dashicons dashicons-facebook"></span>',
        'hozio_instagram_url' => '<span class="dashicons dashicons-instagram"></span>',
        'hozio_twitter_url' => '<span class="dashicons dashicons-twitter"></span>',
        'hozio_linkedin_url' => '<span class="dashicons dashicons-linkedin"></span>',
        'hozio_gmb_link' => '<span class="dashicons dashicons-location"></span>',
    ];
    
    // Check if it's a custom tag
    if (strpos($field_id, 'hozio_') === 0) {
        $custom_tags = get_option('hozio_custom_tags', []);
        if (is_array($custom_tags)) {
            foreach ($custom_tags as $tag) {
                if ('hozio_' . $tag['value'] === $field_id) {
                    // Return icon based on tag type
                    if ($tag['type'] === 'url') {
                        return '<span class="dashicons dashicons-admin-links"></span>';
                    } else {
                        return '<span class="dashicons dashicons-editor-alignleft"></span>';
                    }
                }
            }
        }
    }
    
    return isset($icons[$field_id]) ? $icons[$field_id] : '<span class="dashicons dashicons-admin-links"></span>';
}

// Helper function to get placeholder text
function hozio_get_field_placeholder($field_id) {
    $placeholders = [
        'hozio_company_phone_1' => '123-456-7890',
        'hozio_company_phone_2' => '123-456-7890',
        'hozio_google_ads_phone' => '123-456-7890',
        'hozio_sms_phone' => '123-456-7890',
        'hozio_company_email' => 'info@company.com',
        'hozio_to_email_contact_form' => 'contact@company.com, sales@company.com',
    ];
    
    if (strpos($field_id, '_url') !== false) {
        return 'https://...';
    }
    
    return isset($placeholders[$field_id]) ? $placeholders[$field_id] : '';
}

// Display the enhanced settings page
function hozio_dynamic_tags_settings_page() {
    ?>
    <div class="hozio-settings-wrapper">
        <div class="hozio-header">
            <div class="hozio-header-content">
                <h1>
                    <span class="dashicons dashicons-tag"></span>
                    <?php esc_html_e('Dynamic Tags Settings', 'hozio-dynamic-tags'); ?>
                </h1>
                <p class="hozio-subtitle">Configure your dynamic tags and contact information</p>
            </div>
        </div>

        <div class="hozio-content">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hozio-form">
                <?php wp_nonce_field('hozio_save_settings_nonce', 'hozio_save_settings_nonce_field'); ?>
                <input type="hidden" name="action" value="hozio_save_settings" />

                <!-- Contact Information Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-phone"></span>
                        <h2>Contact Information</h2>
                    </div>
                    <div class="hozio-grid">
                        <?php
                        $contact_fields = [
                            'hozio_company_phone_1' => 'Company Phone 1',
                            'hozio_company_phone_2' => 'Company Phone 2',
                            'hozio_google_ads_phone' => 'Google Ads Phone Number',
                            'hozio_sms_phone' => 'SMS Phone Number',
                            'hozio_company_email' => 'Company Email',
                            'hozio_to_email_contact_form' => 'Contact Form Email(s)',
                        ];
                        
                        foreach ($contact_fields as $key => $label) {
                            echo '<div class="hozio-field"><label>' . esc_html($label) . '</label>';
                            hozio_dynamic_tags_render_input(['label_for' => $key]);
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Business Details Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-building"></span>
                        <h2>Business Details</h2>
                    </div>
                    <div class="hozio-grid">
                        <?php
                        $business_fields = [
                            'hozio_company_address' => 'Company Address',
                            'hozio_business_hours' => 'Business Hours',
                            'hozio_start_year' => 'Start Year',
                            'hozio_nav_text_color' => 'Navigation Text Color',
                        ];
                        
                        foreach ($business_fields as $key => $label) {
                            echo '<div class="hozio-field"><label>' . esc_html($label) . '</label>';
                            hozio_dynamic_tags_render_input(['label_for' => $key]);
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Social Media & Review Sites Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-share"></span>
                        <h2>Social Media & Review Sites</h2>
                    </div>
                    <div class="hozio-grid hozio-grid-3">
                        <?php
                        $social_fields = [
                            'hozio_facebook_url' => 'Facebook',
                            'hozio_instagram_url' => 'Instagram',
                            'hozio_twitter_url' => 'Twitter',
                            'hozio_tiktok_url' => 'TikTok',
                            'hozio_linkedin_url' => 'LinkedIn',
                            'hozio_youtube_url' => 'YouTube',
                            'hozio_yelp_url' => 'Yelp',
                            'hozio_angies_list_url' => "Angi's List",
                            'hozio_home_advisor_url' => 'Home Advisor',
                            'hozio_bbb_url' => 'BBB',
                            'hozio_gmb_link' => 'Google My Business',
                        ];
                        
                        foreach ($social_fields as $key => $label) {
                            echo '<div class="hozio-field"><label>' . esc_html($label) . '</label>';
                            hozio_dynamic_tags_render_input(['label_for' => $key]);
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>


                <!-- HTML Sitemap Settings Section -->
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <h2>HTML Sitemap Settings</h2>
                    </div>
                    
                    <!-- Dark Mode Toggle -->
                    <div class="hozio-field">
                        <div class="hozio-toggle-wrapper">
                            <label class="hozio-toggle-switch">
                                <input type="checkbox" name="hozio_sitemap_dark_mode" value="1" <?php checked(get_option('hozio_sitemap_dark_mode'), '1'); ?> />
                                <span class="hozio-toggle-slider"></span>
                            </label>
                            <span class="hozio-toggle-label">Enable Dark Mode for HTML Sitemap</span>
                        </div>
                        <p class="hozio-field-description">When enabled, the HTML sitemap will display with black backgrounds and white text. Links will remain visible for accessibility.</p>
                    </div>

                    <!-- Link Colors Subsection -->
                    <div class="hozio-subsection">
                        <h3 class="hozio-subsection-title">
                            <span class="dashicons dashicons-admin-links"></span>
                            Link Colors
                        </h3>
                        <p class="hozio-info-text">
                            <span class="dashicons dashicons-info"></span>
                            By default, links inherit colors from your Elementor global styles. Set custom colors below to override the global settings for the sitemap only.
                        </p>

                        <!-- Link Color Field -->
                        <div class="hozio-color-field">
                            <div class="hozio-color-input-wrapper">
                                <label class="hozio-field-label">Link Color</label>
                                <input type="text" name="hozio_sitemap_link_color" value="<?php echo esc_attr(get_option('hozio_sitemap_link_color', '')); ?>" class="hozio-color-picker" />
                            </div>
                            <div class="hozio-color-info">
                                <p class="hozio-field-description">
                                    Set the default color for all links in the sitemap. Leave empty to use Elementor global link color.
                                </p>
                            </div>
                        </div>

                        <!-- Link Hover Color Field -->
                        <div class="hozio-color-field">
                            <div class="hozio-color-input-wrapper">
                                <label class="hozio-field-label">Link Hover Color</label>
                                <input type="text" name="hozio_sitemap_link_hover_color" value="<?php echo esc_attr(get_option('hozio_sitemap_link_hover_color', '')); ?>" class="hozio-color-picker" />
                            </div>
                            <div class="hozio-color-info">
                                <p class="hozio-field-description">
                                    Set the color for links when hovering over them. Leave empty to use Elementor global hover color.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Custom Tags Section -->
                <?php
                $custom_tags = get_option('hozio_custom_tags', []);
                if (!empty($custom_tags) && is_array($custom_tags)) :
                ?>
                <div class="hozio-section">
                    <div class="hozio-section-header">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <h2>Custom Dynamic Tags</h2>
                    </div>
                    <div class="hozio-grid">
                        <?php
                        foreach ($custom_tags as $tag) {
                            echo '<div class="hozio-field"><label>' . esc_html($tag['title']) . '</label>';
                            hozio_dynamic_tags_render_input(['label_for' => 'hozio_' . $tag['value']]);
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="hozio-submit-wrapper">
                    <?php submit_button(__('Save All Settings', 'hozio-dynamic-tags'), 'primary hozio-submit-btn', 'submit', false); ?>
                    <button type="button" class="button hozio-reset-btn">
                        <span class="dashicons dashicons-image-rotate"></span>
                        Reset to Default
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// Handle the settings save functionality
function hozio_dynamic_tags_save_settings() {
    if (!isset($_POST['hozio_save_settings_nonce_field']) || !wp_verify_nonce($_POST['hozio_save_settings_nonce_field'], 'hozio_save_settings_nonce')) {
        wp_die('Nonce verification failed');
    }

    $fields = [
        'hozio_company_phone_1',
        'hozio_company_phone_2',
        'hozio_google_ads_phone',
        'hozio_sms_phone',
        'hozio_company_email',
        'hozio_company_address',
        'hozio_business_hours',
        'hozio_yelp_url',
        'hozio_youtube_url',
        'hozio_angies_list_url',
        'hozio_home_advisor_url',
        'hozio_bbb_url',
        'hozio_facebook_url',
        'hozio_instagram_url',
        'hozio_twitter_url',
        'hozio_tiktok_url',
        'hozio_linkedin_url',
        'hozio_gmb_link',
        'hozio_to_email_contact_form',
        'hozio_nav_text_color',
        'hozio_start_year',
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            if ($field === 'hozio_company_address' || $field === 'hozio_business_hours') {
                update_option($field, wp_kses_post($_POST[$field]));
            } else {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    // Save dark mode setting
    update_option('hozio_sitemap_dark_mode', isset($_POST['hozio_sitemap_dark_mode']) ? '1' : '0');

    // Save link color settings
    $link_color = isset($_POST['hozio_sitemap_link_color']) ? sanitize_hex_color($_POST['hozio_sitemap_link_color']) : '';
    update_option('hozio_sitemap_link_color', $link_color);
    
    $link_hover_color = isset($_POST['hozio_sitemap_link_hover_color']) ? sanitize_hex_color($_POST['hozio_sitemap_link_hover_color']) : '';
    update_option('hozio_sitemap_link_hover_color', $link_hover_color);

    // FIXED: Use wp_kses_post() for custom tags to allow HTML
	$custom_tags = get_option('hozio_custom_tags', []);
	if (is_array($custom_tags)) {
		foreach ($custom_tags as $tag) {
			if (isset($_POST['hozio_' . $tag['value']])) {
				$value = wp_unslash($_POST['hozio_' . $tag['value']]);
				
				// If it contains <script> tag, store as-is (only for admin users)
				if (strpos($value, '<script') !== false && current_user_can('manage_options')) {
					update_option('hozio_' . $tag['value'], $value);
				} else {
					// For other HTML, use normal sanitization
					update_option('hozio_' . $tag['value'], wp_kses_post($value));
				}
			}
		}
	}

    wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=hozio_dynamic_tags')));
    exit;
}
add_action('admin_post_hozio_save_settings', 'hozio_dynamic_tags_save_settings');
?>
