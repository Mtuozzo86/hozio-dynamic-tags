<?php
/*
Plugin Name: Hozio Dynamic Tags
Plugin URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
Description: Adds custom dynamic tags for Elementor to manage Hozio's contact information.
Version: 3.13.5
Author: Hozio Web Dev
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

// Add the custom admin menu
function hozio_dynamic_tags_menu() {
    add_menu_page(
        'Hozio Dynamic Tags Settings',
        'Hozio Dynamic Tags',
        'manage_options',
        'hozio_dynamic_tags',
        'hozio_dynamic_tags_contact_info',
        plugins_url('assets/hozio-logo.png', __FILE__),
        25
    );

    add_submenu_page(
        'hozio_dynamic_tags',
        'Add / Remove Dynamic Tags',
        'Add / Remove',
        'manage_options',
        'hozio-add-remove-tags',
        'hozio_add_remove_tags_page'
    );

    add_submenu_page(
        'hozio_dynamic_tags',
        'Custom Permalink Settings',
        'Blog Permalink Settings',
        'manage_options',
        'hozio-permalink-settings',
        'hozio_permalink_settings_html'
    );
}

add_action('admin_menu', 'hozio_dynamic_tags_menu');

// Add custom CSS for the plugin's settings page
function hozio_dynamic_tags_custom_styles() {
    ?>
    <style type="text/css">
        /* Adjust the width of the textarea fields */
        #hozio_company_address, #hozio_business_hours {
            width: 100%;
            max-width: 350px;
            min-width: 300px;
        }
    </style>
    <?php
}

add_action('admin_head', 'hozio_dynamic_tags_custom_styles');

// Add a settings link under the plugin details
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'hozio_dynamic_tags_action_links');
function hozio_dynamic_tags_action_links($links) {
    $settings_link = '<a href="admin.php?page=hozio_dynamic_tags">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Function to display the settings page
function hozio_dynamic_tags_contact_info() {
    hozio_dynamic_tags_settings_page();
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
    $tag_value = sanitize_title($tag_title); // Ensure the tag's value is sanitized for use as a key

    // Fetch the existing tags from the options table
    $custom_tags = get_option('hozio_custom_tags', []);
    
    // Check if tag already exists to prevent duplicates
    foreach ($custom_tags as $tag) {
        if ($tag['value'] === $tag_value) {
            wp_die('This tag already exists.');
        }
    }

    // Add the new tag to the array
    $custom_tags[] = [
        'title' => $tag_title,
        'value' => $tag_value,
        'type' => $tag_type
    ];

    // Update the tags in the options table
    update_option('hozio_custom_tags', $custom_tags);

    // Redirect back to the add/remove tags page
    wp_redirect(admin_url('admin.php?page=hozio-add-remove-tags'));
    exit;
}

// Handle tag removal
add_action('admin_post_hozio_remove_tag', 'hozio_remove_dynamic_tag');
function hozio_remove_dynamic_tag() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized request');
    }

    // Get the tag value to be removed
    $tag_value = sanitize_text_field($_GET['tag']);
    $custom_tags = get_option('hozio_custom_tags', []);

    // Loop through tags and remove the one that matches
    foreach ($custom_tags as $key => $tag) {
        if ($tag['value'] === $tag_value) {
            unset($custom_tags[$key]);
            break;
        }
    }

    // Update the tags in the options table after removal
    update_option('hozio_custom_tags', array_values($custom_tags));

    // Redirect back to the add/remove tags page
    wp_redirect(admin_url('admin.php?page=hozio-add-remove-tags'));
    exit;
}

// Register the setting to save the enable/disable option for custom permalinks
add_action('admin_init', 'hozio_custom_permalink_register_setting');
function hozio_custom_permalink_register_setting() {
    register_setting('hozio_permalink_settings', 'hozio_custom_permalink_enabled');
}

// Render the settings page HTML for custom permalinks
function hozio_permalink_settings_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Custom Permalink Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('hozio_permalink_settings');
            do_settings_sections('hozio_permalink_settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable/Disable Custom Blog Permalink</th>
                    <td>
                        <input type="checkbox" name="hozio_custom_permalink_enabled" value="1" <?php checked(1, get_option('hozio_custom_permalink_enabled'), true); ?> />
                    </td>
                </tr>
            </table>
            <p>Enabling this plugin will add the slug "blog" to all posts.</p>
            <p><strong>Example if enabled:</strong> domain.com/blog/category/post-name</p>
            <p><strong>Example if disabled:</strong> domain.com/category/post-name</p>
            <p><em>To remove the category, go to <strong>Settings > Permalinks</strong> and remove "%category%" from the custom structure.</em></p>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Hook to modify the permalink structure
add_filter('post_link', 'hozio_custom_post_link', 10, 2);
function hozio_custom_post_link($permalink, $post) {
    $is_enabled = get_option('hozio_custom_permalink_enabled');

    if (!$is_enabled || $post->post_type !== 'post') {
        return $permalink;
    }

    $categories = get_the_category($post->ID);
    if (!empty($categories)) {
        $category = $categories[0]->slug;
        $permalink = home_url('/blog/' . $category . '/' . $post->post_name . '/');
    }
    return $permalink;
}

// Register custom dynamic tags
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
                eval("
                    class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                        public function get_name() {
                            return '" . esc_attr($tag[0]) . "';
                        }

                        public function get_title() {
                            return __('" . esc_attr($tag[1]) . "', 'plugin-name');
                        }

                        public function get_group() {
                            return 'site';
                        }

                        public function get_categories() {
                            return [\\Elementor\\Modules\\DynamicTags\\Module::URL_CATEGORY];
                        }

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
                    }
                ");
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
        ['years-of-experience', 'Years of Experience', 'hozio_start_year'],
    ];

    foreach ($text_tags as $tag) {
        if (isset($tag[2])) {
            $class_name = 'My_' . str_replace('-', '_', ucwords($tag[0], '-')) . '_Tag';

            if (!class_exists($class_name)) {
                eval("
                    class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                        public function get_name() {
                            return '" . esc_attr($tag[0]) . "';
                        }

                        public function get_title() {
                            return __('" . esc_attr($tag[1]) . "', 'plugin-name');
                        }

                        public function get_group() {
                            return 'site';
                        }

                        public function get_categories() {
                            return [\\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY];
                        }

                        protected function register_controls() {}

                        public function render() {
                            if ('years-of-experience' === '" . esc_attr($tag[0]) . "') {
                                \$start_year = get_option('hozio_start_year', 0);
                                \$current_year = (int) date('Y');
                                \$years_of_experience = (\$start_year > 0) ? \$current_year - (int) \$start_year : 0;
                                echo esc_html(\$years_of_experience);
                            } elseif ('company-address' === '" . esc_attr($tag[0]) . "') {
                                echo wp_kses_post(get_option('hozio_company_address'));
                            } elseif ('business-hours' === '" . esc_attr($tag[0]) . "') {
                                echo wp_kses_post(get_option('hozio_business_hours'));
                            } else {
                                echo esc_html(get_option('" . esc_attr($tag[2]) . "'));
                            }
                        }
                    }
                ");
                $dynamic_tags->register(new $class_name());
            }
        }
    }
});

// Output custom inline CSS for the last menu item
add_action('wp_footer', 'hozio_dynamic_nav_menu_inline_css');
function hozio_dynamic_nav_menu_inline_css() {
    ?>
    <style type="text/css">
        #toggle-menu li:last-of-type > .elementor-item {
            background-color: var(--e-global-color-secondary, #FFFFFF) !important;
            color: <?php echo esc_attr(get_option('hozio_nav_text_color', 'black')); ?> !important;
            padding: 25px 24px;
            font-weight: 600;
            font-size: 17px;
            text-align: center;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        #toggle-menu li:last-of-type > .elementor-item:hover {
            background-color: var(--e-global-color-secondary, #FFFFFF) !important;
            color: <?php echo esc_attr(get_option('hozio_nav_text_color', 'black')); ?> !important;
        }
    </style>
    <?php
}
