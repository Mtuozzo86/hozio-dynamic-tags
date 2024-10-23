<?php
/*
Plugin Name: Hozio Dynamic Tags
Plugin URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
Description: Adds custom dynamic tags for Elementor to manage Hozio's contact information.
Version: 3.11
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
require_once plugin_dir_path(__FILE__) . 'includes/service-menu-handler.php';

// Hook to add services to menu when status changes
add_action('transition_post_status', 'add_service_to_menu', 10, 3);

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

    // Add the Add/Remove submenu
    add_submenu_page(
        'hozio-dynamic-tags',              
        'Add / Remove Dynamic Tags',       
        'Add / Remove',                    
        'manage_options',                  
        'hozio-add-remove-tags',           
        'hozio_add_remove_tags_page'       
    );
}

// Function for displaying the Add/Remove page content
function hozio_add_remove_tags_page() {
    include plugin_dir_path(__FILE__) . 'includes/add-remove-tags.php';
}

// Handle the add tag form submission
add_action('admin_post_hozio_add_tag', 'hozio_add_dynamic_tag');
function hozio_add_dynamic_tag() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_wpnonce'], 'hozio_add_tag_nonce')) {
        wp_die('Unauthorized request');
    }

    $tag_title = sanitize_text_field($_POST['tag_title']);
    $tag_type = sanitize_text_field($_POST['tag_type']);
    $tag_value = sanitize_title($tag_title); // Generate a slug from the title

    $custom_tags = get_option('hozio_custom_tags', []);
    $custom_tags[] = [
        'title' => $tag_title,
        'value' => $tag_value,
        'type' => $tag_type
    ];

    update_option('hozio_custom_tags', $custom_tags);
    wp_redirect(admin_url('admin.php?page=hozio-add-remove-tags'));
    exit;
}

// Handle tag removal
add_action('admin_post_hozio_remove_tag', 'hozio_remove_dynamic_tag');
function hozio_remove_dynamic_tag() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request');
    }

    $tag_value = sanitize_text_field($_GET['tag']);
    $custom_tags = get_option('hozio_custom_tags', []);

    foreach ($custom_tags as $key => $tag) {
        if ($tag['value'] === $tag_value) {
            unset($custom_tags[$key]);
            break;
        }
    }

    update_option('hozio_custom_tags', array_values($custom_tags));
    wp_redirect(admin_url('admin.php?page=hozio-add-remove-tags'));
    exit;
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
        ['sitemap-xml', 'Sitemap', 'sitemap_url', 'url'], // Sitemap registration
    ];

    // Text-based tags
    $text_tags = [
        ['company-phone-1-name', 'Company Phone #1 Name', 'hozio_company_phone_1'],
        ['company-phone-2-name', 'Company Phone #2 Name', 'hozio_company_phone_2'],
        ['sms-phone-name', 'SMS Phone # Name', 'hozio_sms_phone'],
        ['company-address', 'Company Address', 'hozio_company_address'], // Allow HTML
        ['business-hours', 'Business Hours', 'hozio_business_hours'], // Allow HTML
        ['yelp', 'Yelp', 'hozio_yelp_url'],
        ['youtube', 'YouTube', 'hozio_youtube_url'],
        ['angies-list', "Angi's List", 'hozio_angies_list_url'],
        ['home-advisor', 'Home Advisor', 'hozio_home_advisor_url'],
        ['to-email-contact-form', 'To Email(s) Contact Form', 'hozio_to_email_contact_form'],
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
                        if ('tel' === '" . esc_attr($tag[3]) . "') {
                            echo esc_url('tel:' . esc_attr(get_option('" . esc_attr($tag[2]) . "')));
                        } elseif ('sms' === '" . esc_attr($tag[3]) . "') {
                            echo esc_url('sms:' . esc_attr(get_option('" . esc_attr($tag[2]) . "')));
                        } elseif ('mailto' === '" . esc_attr($tag[3]) . "') {
                            echo esc_url('mailto:' . esc_attr(get_option('" . esc_attr($tag[2]) . "')));
                        } elseif ('url' === '" . esc_attr($tag[3]) . "') {
                            echo esc_url(get_option('" . esc_attr($tag[2]) . "') ?: home_url('/sitemap.xml')); // Default to sitemap URL
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
                        if ('company-address' === '" . esc_attr($tag[0]) . "') {
                            echo wp_kses_post(get_option('hozio_company_address'));  // Allow HTML for company address
                        } elseif ('business-hours' === '" . esc_attr($tag[0]) . "') {
                            echo wp_kses_post(get_option('hozio_business_hours'));  // Allow HTML for business hours
                        } else {
                            echo esc_html(get_option('" . esc_attr($tag[2]) . "'));
                        }
                    }
                }
            ");
            $dynamic_tags->register(new $class_name());
        }
    }

    // Custom tags from settings
    $custom_tags = get_option('hozio_custom_tags', []);
    foreach ($custom_tags as $tag) {
        $class_name = 'My_' . str_replace('-', '_', ucwords($tag['value'], '-')) . '_Tag';
        if (!class_exists($class_name)) {
            eval("
                class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                    public function get_name() { return '" . esc_attr($tag['value']) . "'; }
                    public function get_title() { return __('" . esc_attr($tag['title']) . "', 'plugin-name'); }
                    public function get_group() { return 'site'; }
                    public function get_categories() { return [\\Elementor\\Modules\\DynamicTags\\Module::" . ($tag['type'] === 'url' ? 'URL_CATEGORY' : 'TEXT_CATEGORY') . "]; }
                    protected function register_controls() {}
                    public function render() {
                        echo esc_html(get_option('hozio_" . esc_attr($tag['value']) . "'));
                    }
                }
            ");
            $dynamic_tags->register(new $class_name());
        }
    }
});
