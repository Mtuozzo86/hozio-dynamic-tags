<?php
/*
Plugin Name: Hozio Dynamic Tags
Plugin URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
Description: Adds custom dynamic tags for Elementor to manage Hozio's contact information.
Version: 1.0
Author: Hozio Web Dev
Author URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
License: GPL2
Text Domain: hozio-dynamic-tags
GitHub Plugin URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
GitHub Branch: main
*/


// Ensure WordPress is calling the file
if (!defined('ABSPATH')) {
    exit;
}

// Include the settings page and dynamic tags file
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';  
require_once plugin_dir_path(__FILE__) . 'includes/dynamic-tags.php';    

// Function to display the contact info page
function hozio_dynamic_tags_contact_info() {
    hozio_dynamic_tags_settings_page();
}

// Add a settings link under the plugin details
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'hozio_dynamic_tags_action_links');
function hozio_dynamic_tags_action_links($links) {
    $settings_link = '<a href="admin.php?page=hozio_dynamic_tags_contact_info">Contact Info</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Add the custom admin menu
add_action('admin_menu', 'hozio_dynamic_tags_menu');
function hozio_dynamic_tags_menu() {
    $icon_url = plugins_url('assets/hozio-logo.png', __FILE__);

    add_menu_page(
        'Hozio Dynamic Tags',              
        'Hozio Dynamic Tags',              
        'manage_options',                  
        'hozio-dynamic-tags',              
        'hozio_dynamic_tags_contact_info', 
        $icon_url,                         
        25                                 
    );
}

// Register custom dynamic tags
add_action('elementor/dynamic_tags/register', function($dynamic_tags) {
    // URL-based tags
    $url_tags = [
        ['company-phone-1', 'Company Phone Number 1', 'hozio_company_phone_1', 'tel'],
        ['company-phone-2', 'Company Phone Number 2', 'hozio_company_phone_2', 'tel'],
        ['sms-phone', 'SMS Phone Number', 'hozio_sms_phone', 'sms'],
        ['company-email', 'Company Email', 'hozio_company_email', 'mailto'],
        ['gmb-link', 'GMB Link', 'hozio_gmb_link', 'url'],
        ['facebook', 'Facebook', 'hozio_facebook_url', 'url'],
        ['instagram', 'Instagram', 'hozio_instagram_url', 'url'],
        ['twitter', 'Twitter', 'hozio_twitter_url', 'url'],
        ['tiktok', 'TikTok', 'hozio_tiktok_url', 'url'],
        ['linkedin', 'LinkedIn', 'hozio_linkedin_url', 'url'],
        ['bbb', 'BBB', 'hozio_bbb_url', 'url'],
    ];

    // Text-based tags
    $text_tags = [
        ['company-phone-1-name', 'Company Phone #1 Name', 'hozio_company_phone_1'],
        ['company-phone-2-name', 'Company Phone #2 Name', 'hozio_company_phone_2'],
        ['sms-phone-name', 'SMS Phone # Name', 'hozio_sms_phone'],
        ['company-address', 'Company Address', 'hozio_company_address'],
        ['business-hours', 'Business Hours', 'hozio_business_hours'],
        ['yelp', 'Yelp', 'hozio_yelp_url'],
        ['youtube', 'YouTube', 'hozio_youtube_url'],
        ['angies-list', "Angi's List", 'hozio_angies_list_url'],
        ['home-advisor', 'Home Advisor', 'hozio_home_advisor_url'],
        ['sitemap-xml', 'sitemap.xml', ''],
    ];

    // Register URL-based dynamic tags
    foreach ($url_tags as $tag) {
        $class_name = 'My_' . str_replace('-', '_', ucwords($tag[0], '-')) . '_Tag';
        if (!class_exists($class_name)) {
            eval("
                class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                    public function get_name() { return '" . esc_attr($tag[0]) . "'; }
                    public function get_title() { return __('" . esc_attr($tag[1]) . "', 'plugin-name'); }
                    public function get_group() { return 'site'; }
                    public function get_categories() { return [\\Elementor\\Modules\\DynamicTags\\Module::URL_CATEGORY]; }
                    protected function register_controls() {}
                    public function render() {
                        // Check for tel, sms, and mailto to apply correct prefix
                        if ('tel' === '" . esc_attr($tag[3]) . "') {
                            echo esc_url('tel:' . esc_attr(get_option('" . esc_attr($tag[2]) . "')));
                        } elseif ('sms' === '" . esc_attr($tag[3]) . "') {
                            echo esc_url('sms:' . esc_attr(get_option('" . esc_attr($tag[2]) . "')));
                        } elseif ('mailto' === '" . esc_attr($tag[3]) . "') {
                            echo esc_url('mailto:' . esc_attr(get_option('" . esc_attr($tag[2]) . "')));
                        } else {
                            echo esc_url(get_option('" . esc_attr($tag[2]) . "'));
                        }
                    }
                }
            ");
            $dynamic_tags->register(new $class_name());
        }
    }

    // Register text-based dynamic tags
    foreach ($text_tags as $tag) {
        $class_name = 'My_' . str_replace('-', '_', ucwords($tag[0], '-')) . '_Tag';
        if (!class_exists($class_name)) {
            eval("
                class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                    public function get_name() { return '" . esc_attr($tag[0]) . "'; }
                    public function get_title() { return __('" . esc_attr($tag[1]) . "', 'plugin-name'); }
                    public function get_group() { return 'site'; }
                    public function get_categories() { return [\\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY]; }
                    protected function register_controls() {}
                    public function render() {
                        echo esc_html(get_option('" . esc_attr($tag[2]) . "'));
                    }
                }
            ");
            $dynamic_tags->register(new $class_name());
        }
    }
});
?>
