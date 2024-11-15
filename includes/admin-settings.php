<?php
// Register settings for Hozio Dynamic Tags
function hozio_dynamic_tags_register_settings() {
    // Register each setting
    $fields = [
        'hozio_company_phone_1',
        'hozio_company_phone_2',
        'hozio_sms_phone',
        'hozio_company_email',
        'hozio_company_address',   // Allow HTML (we'll handle it below)
        'hozio_business_hours',    // Allow HTML (we'll handle it below)
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
        'hozio_nav_text_color_hex'
    ];

    foreach ($fields as $field) {
        register_setting('hozio_dynamic_tags_options', $field);
    }
}

add_action('admin_init', 'hozio_dynamic_tags_register_settings');

// Initialize settings fields and sections
function hozio_dynamic_tags_settings_init() {
    add_settings_section(
        'hozio_dynamic_tags_section',
        'Hozio Dynamic Tags Settings',
        null,
        'hozio_dynamic_tags'
    );

    $fields = [
        'hozio_company_phone_1' => 'Company Phone 1',
        'hozio_company_phone_2' => 'Company Phone 2',
        'hozio_sms_phone' => 'SMS Phone Number',
        'hozio_company_email' => 'Company Email',
        'hozio_company_address' => 'Company Address', // Regular input field
        'hozio_business_hours' => 'Business Hours',    // Regular input field
        'hozio_yelp_url' => 'Yelp URL',
        'hozio_youtube_url' => 'YouTube URL',
        'hozio_angies_list_url' => "Angi's List URL",
        'hozio_home_advisor_url' => 'Home Advisor URL',
        'hozio_bbb_url' => 'BBB URL',
        'hozio_facebook_url' => 'Facebook URL',
        'hozio_instagram_url' => 'Instagram URL',
        'hozio_twitter_url' => 'Twitter URL',
        'hozio_tiktok_url' => 'TikTok URL',
        'hozio_linkedin_url' => 'LinkedIn URL',
        'hozio_gmb_link' => 'GMB Link',
        'hozio_to_email_contact_form' => 'To Email(s) Contact Form',
    ];

    foreach ($fields as $key => $label) {
        add_settings_field(
            $key,
            $label,
            'hozio_dynamic_tags_render_input',
            'hozio_dynamic_tags',
            'hozio_dynamic_tags_section',
            ['label_for' => $key]
        );
    }
}

add_action('admin_init', 'hozio_dynamic_tags_settings_init');

// Render input fields for text settings
function hozio_dynamic_tags_render_input($args) {
    $option = get_option($args['label_for']);
    // Use regular input fields for all fields, including Company Address and Business Hours
    printf(
        '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" />',
        esc_attr($args['label_for']),
        esc_attr($option)
    );
}

// Display the settings page
function hozio_dynamic_tags_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Hozio Dynamic Tags Settings', 'hozio-dynamic-tags'); ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php
            wp_nonce_field('hozio_save_settings_nonce', 'hozio_save_settings_nonce_field');
            settings_fields('hozio_dynamic_tags_options');
            do_settings_sections('hozio_dynamic_tags');
            echo '<input type="hidden" name="action" value="hozio_save_settings" />'; // Ensure the form is submitted correctly
            submit_button(__('Save Settings', 'hozio-dynamic-tags'));
            ?>
        </form>
    </div>
    <?php
}

// Handle the settings save functionality
function hozio_dynamic_tags_save_settings() {
    // Check nonce for security
    if (!isset($_POST['hozio_save_settings_nonce_field']) || !wp_verify_nonce($_POST['hozio_save_settings_nonce_field'], 'hozio_save_settings_nonce')) {
        wp_die('Nonce verification failed');
    }

    $fields = [
        'hozio_company_phone_1',
        'hozio_company_phone_2',
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
        'hozio_nav_text_color_hex'
    ];

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_option($field, sanitize_text_field($_POST[$field]));
        }
    }

    wp_redirect(admin_url('admin.php?page=hozio_dynamic_tags'));
    exit;
}

add_action('admin_post_hozio_save_settings', 'hozio_dynamic_tags_save_settings');
