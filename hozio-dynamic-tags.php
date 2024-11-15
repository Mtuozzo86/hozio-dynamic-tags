<?php
/*
Plugin Name: Hozio Dynamic Tags
Plugin URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
Description: Adds custom dynamic tags for Elementor to manage Hozio's contact information.
Version: 3.13.7
Author: Hozio Web Dev
Author URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
License: GPL2
Text Domain: hozio-dynamic-tags
GitHub Plugin URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
GitHub Branch: main
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/dynamic-tags.php';
require_once plugin_dir_path(__FILE__) . 'includes/service-menu-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/custom-permalink.php';

// Register custom dynamic tags for Elementor
add_action('elementor/dynamic_tags/register', function($dynamic_tags) {
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
        ['sitemap-xml', 'Sitemap', 'sitemap_url', 'url'],
        ['yelp', 'Yelp', 'hozio_yelp_url'],
        ['youtube', 'YouTube', 'hozio_youtube_url'],
        ['angies-list', "Angi's List", 'hozio_angies_list_url'],
        ['home-advisor', 'Home Advisor', 'hozio_home_advisor_url'],
    ];

    foreach ($url_tags as $tag) {
        if (isset($tag[3])) {
            $class_name = 'My_' . str_replace('-', '_', ucwords($tag[0], '-')) . '_Tag';
            if (!class_exists($class_name)) {
                eval("class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
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
                            echo esc_url(get_option('" . esc_attr($tag[2]) . "') ?: home_url('/sitemap.xml'));
                        } else {
                            echo esc_url(get_option('" . esc_attr($tag[2]) . "'));
                        }
                    }
                }");
                $dynamic_tags->register(new $class_name());
            }
        }
    }

    $text_tags = [
        ['company-phone-1-name', 'Company Phone #1 Name', 'hozio_company_phone_1'],
        ['company-phone-2-name', 'Company Phone #2 Name', 'hozio_company_phone_2'],
        ['sms-phone-name', 'SMS Phone # Name', 'hozio_sms_phone'],
        ['company-address', 'Company Address', 'hozio_company_address'],
        ['business-hours', 'Business Hours', 'hozio_business_hours'],
        ['to-email-contact-form', 'To Email(s) Contact Form', 'hozio_to_email_contact_form'],
    ];

    foreach ($text_tags as $tag) {
        if (isset($tag[2])) {
            $class_name = 'My_' . str_replace('-', '_', ucwords($tag[0], '-')) . '_Tag';
            if (!class_exists($class_name)) {
                eval("class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                    public function get_name() { return '" . esc_attr($tag[0]) . "'; }
                    public function get_title() { return __('" . esc_attr($tag[1]) . "', 'plugin-name'); }
                    public function get_group() { return 'site'; }
                    public function get_categories() { return [\\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY]; }
                    protected function register_controls() {}
                    public function render() {
                        if ('company-address' === '" . esc_attr($tag[0]) . "') {
                            echo wp_kses(get_option('hozio_company_address'), array(
                                'br' => array(),
                                'a' => array('href' => array(), 'title' => array()),
                                'b' => array(),
                                'i' => array(),
                                'p' => array(),
                                'ul' => array(),
                                'ol' => array(),
                                'li' => array()
                            ));
                        } elseif ('business-hours' === '" . esc_attr($tag[0]) . "') {
                            echo wp_kses(get_option('hozio_business_hours'), array(
                                'br' => array(),
                                'a' => array('href' => array(), 'title' => array()),
                                'b' => array(),
                                'i' => array(),
                                'p' => array(),
                                'ul' => array(),
                                'ol' => array(),
                                'li' => array()
                            ));
                        } else {
                            echo esc_html(get_option('" . esc_attr($tag[2]) . "'));
                        }
                    }
                }");
                $dynamic_tags->register(new $class_name());
            }
        }
    }
});
