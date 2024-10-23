<?php
// Register settings for Hozio Dynamic Tags
function hozio_dynamic_tags_register_settings() {
    // Register a new setting for each of the fields in the settings page
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
    ];

    // Register each field
    foreach ($fields as $field) {
        register_setting('hozio_dynamic_tags_options', $field);
    }

    // Dynamically add custom tag settings
    $custom_tags = get_option('hozio_custom_tags', []);
    foreach ($custom_tags as $tag) {
        // Register each custom tag as an option
        $option_name = 'hozio_' . $tag['value'];
        register_setting('hozio_dynamic_tags_options', $option_name);
        add_settings_field(
            $option_name,
            $tag['title'],
            'hozio_dynamic_tags_render_input',
            'hozio_dynamic_tags',
            'hozio_dynamic_tags_section',
            ['label_for' => $option_name]
        );
    }
}

// Add settings sections and fields
function hozio_dynamic_tags_settings_init() {
    add_settings_section(
        'hozio_dynamic_tags_section',                // Section ID
        'Hozio Dynamic Tags Settings',               // Section title
        null,                                        // Callback function (optional)
        'hozio_dynamic_tags'                         // Page slug where the section appears
    );

    // Add fields for each option in the settings
    $fields = [
        'hozio_company_phone_1' => 'Company Phone 1',
        'hozio_company_phone_2' => 'Company Phone 2',
        'hozio_sms_phone' => 'SMS Phone Number',
        'hozio_company_email' => 'Company Email',
        'hozio_company_address' => 'Company Address',
        'hozio_business_hours' => 'Business Hours',
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

    // Loop through fields to add settings
    foreach ($fields as $key => $label) {
        add_settings_field($key, $label, 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => $key]);
    }
}

// Render input fields for the settings
function hozio_dynamic_tags_render_input($args) {
    $option = get_option($args['label_for']);
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
        <form method="post" action="options.php">
            <?php
            // Output security fields for the registered setting
            settings_fields('hozio_dynamic_tags_options');

            // Output the settings sections and their fields
            do_settings_sections('hozio_dynamic_tags');

            // Output the save settings button
            submit_button(__('Save Settings', 'hozio-dynamic-tags'));
            ?>
        </form>
    </div>
    <?php
}

// Register the settings and initialize the fields
add_action('admin_init', 'hozio_dynamic_tags_register_settings');
add_action('admin_init', 'hozio_dynamic_tags_settings_init');
?>
