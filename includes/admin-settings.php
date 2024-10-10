<?php
// Register settings for Hozio Dynamic Tags
function hozio_dynamic_tags_register_settings() {
    // Register a new setting for each of the fields in the settings page
    register_setting('hozio_dynamic_tags_options', 'hozio_company_phone_1');
    register_setting('hozio_dynamic_tags_options', 'hozio_company_phone_2');
    register_setting('hozio_dynamic_tags_options', 'hozio_sms_phone');
    register_setting('hozio_dynamic_tags_options', 'hozio_company_email');
    register_setting('hozio_dynamic_tags_options', 'hozio_company_address');
    register_setting('hozio_dynamic_tags_options', 'hozio_business_hours');
    register_setting('hozio_dynamic_tags_options', 'hozio_yelp_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_youtube_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_angies_list_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_home_advisor_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_bbb_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_facebook_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_instagram_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_twitter_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_tiktok_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_linkedin_url');
    register_setting('hozio_dynamic_tags_options', 'hozio_gmb_link');
    register_setting('hozio_dynamic_tags_options', 'hozio_to_email_contact_form');
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
    add_settings_field('hozio_company_phone_1', 'Company Phone 1', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_company_phone_1']);
    add_settings_field('hozio_company_phone_2', 'Company Phone 2', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_company_phone_2']);
    add_settings_field('hozio_sms_phone', 'SMS Phone Number', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_sms_phone']);
    add_settings_field('hozio_company_email', 'Company Email', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_company_email']);
    add_settings_field('hozio_company_address', 'Company Address', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_company_address']);
    add_settings_field('hozio_business_hours', 'Business Hours', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_business_hours']);
    add_settings_field('hozio_yelp_url', 'Yelp URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_yelp_url']);
    add_settings_field('hozio_youtube_url', 'YouTube URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_youtube_url']);
    add_settings_field('hozio_angies_list_url', 'Angi\'s List URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_angies_list_url']);
    add_settings_field('hozio_home_advisor_url', 'Home Advisor URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_home_advisor_url']);
    add_settings_field('hozio_bbb_url', 'BBB URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_bbb_url']);
    add_settings_field('hozio_facebook_url', 'Facebook URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_facebook_url']);
    add_settings_field('hozio_instagram_url', 'Instagram URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_instagram_url']);
    add_settings_field('hozio_twitter_url', 'Twitter URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_twitter_url']);
    add_settings_field('hozio_tiktok_url', 'TikTok URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_tiktok_url']);
    add_settings_field('hozio_linkedin_url', 'LinkedIn URL', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_linkedin_url']);
    add_settings_field('hozio_gmb_link', 'GMB Link', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_gmb_link']);
    add_settings_field('to-email-contact-form', 'To Email(s) Contact Form', 'hozio_dynamic_tags_render_input', 'hozio_dynamic_tags', 'hozio_dynamic_tags_section', ['label_for' => 'hozio_to_email_contact_form']);
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


add_action('admin_init', function() {
    error_log('Admin init called');
});

add_action('admin_init', 'hozio_dynamic_tags_register_settings');
add_action('admin_init', 'hozio_dynamic_tags_settings_init');
