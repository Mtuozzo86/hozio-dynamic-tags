<?php
/*
Plugin Name:     Hozio Pro
Plugin URI:      https://github.com/Mtuozzo86/hozio-dynamic-tags
Description:     Next-generation tools to power your website's performance and unlock new levels of speed, efficiency, and impact.
Version:         3.96
Author:          Hozio Web Dev
Author URI:      https://hozio.com
License:         GPL2
Text Domain:     hozio-dynamic-tags
GitHub Plugin URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
GitHub Branch:   main
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('HOZIO_VERSION', '3.96');
define('HOZIO_PLUGIN_FILE', __FILE__);
define('HOZIO_HUB_URL', 'https://www.hozio.com');

// Load custom logger first (enables HOZIO_DEBUG logging without WP_DEBUG)
require_once plugin_dir_path( __FILE__ ) . 'includes/hozio-logger.php';

// Load plugin settings page (debug toggles, feature toggles, system info)
require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-settings.php';

// Load self-hosted plugin updater (checks GitHub Releases for updates)
require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-updater.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/dynamic-tags.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/service-menu-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/custom-permalink.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/custom-taxonomies.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/custom-parent-pages-queries.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/acf-filters.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/rss-feed-override.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/leads-digest.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-media-replace-endpoint.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/query-post-types.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/sitemap-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/taxonomy-archive-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/loop-configurations.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/parent-page-filtering.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/support-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/sitemap-layout.php';

// Hub integration files (defensive loading — file_exists prevents fatal errors on partial updates)
$hozio_hub_includes = [
    'includes/hub-command-executor.php',  // Must load before hub-client.php and hub-direct-endpoint.php
    'includes/hub-client.php',
    'includes/hub-direct-endpoint.php',
];
foreach ($hozio_hub_includes as $hozio_hub_file) {
    $hozio_hub_path = plugin_dir_path(__FILE__) . $hozio_hub_file;
    if (file_exists($hozio_hub_path)) {
        require_once $hozio_hub_path;
    }
}

// Hub heartbeat cron: activation hook MUST be in main plugin file
register_activation_hook(__FILE__, function() {
    if (get_option('hozio_hub_url') && !wp_next_scheduled('hozio_hub_heartbeat')) {
        wp_schedule_event(time() + 3600, 'hourly', 'hozio_hub_heartbeat');
    }
});

// Hub heartbeat cron: deactivation hook clears cron events
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('hozio_hub_heartbeat');
    wp_clear_scheduled_hook('hozio_hub_heartbeat_login');
});

// Fallback cron check: if hub is configured but cron event is missing, reschedule
add_action('init', function() {
    if (get_option('hozio_hub_url') && !wp_next_scheduled('hozio_hub_heartbeat')) {
        wp_schedule_event(time() + 3600, 'hourly', 'hozio_hub_heartbeat');
    }
});


add_action( 'init', function() {
    // only run on sites that have a page whose slug is exactly "leads-page"
    $leads_page = get_page_by_path( 'leads-page' );
    if ( ! $leads_page ) {
        return;
    }

    // bring in the file that itself calls add_shortcode('leads_digest', …)
    require_once plugin_dir_path( __FILE__ ) . 'includes/leads-digest.php';

    // force HTML mails
    add_action( 'phpmailer_init', function( $phpmailer ) {
        $phpmailer->isHTML( true );
    } );

    // swap the placeholder link in outgoing emails
    add_filter( 'wp_mail', function( $args ) {
        if ( empty( $args['message'] ) ) {
            return $args;
        }
        $pattern = '/<a\s+href="\[site_url\]\/leads-page"([^>]*)>(.*?)<\/a>/is';
        $args['message'] = preg_replace_callback( $pattern, function( $matches ) {
            return '<a href="' . esc_url( home_url( '/leads-page' ) ) . '"'
                   . $matches[1]
                   . '>View All Leads →</a>';
        }, $args['message'] );
        return $args;
    }, 20, 1 );
} );

function hozio_current_year() {
  return date('Y');
}
add_shortcode('hozio_current_year','hozio_current_year');

/**
 * Universal [hozio] shortcode — use any dynamic tag value in HTML widgets.
 *
 * Usage:
 *   [hozio tag="company-phone-1-name"]           → display phone number
 *   [hozio tag="company-phone-1" format="url"]    → tel:5551234567
 *   [hozio tag="company-email" format="url"]      → mailto:info@example.com
 *   [hozio tag="company-address"]                 → HTML address
 *   [hozio tag="years-of-experience"]             → calculated years
 *   [hozio tag="facebook"]                        → URL
 *   [hozio tag="my-custom-tag"]                   → custom tag value
 */
function hozio_shortcode_handler( $atts ) {
    $atts   = shortcode_atts( array( 'tag' => '', 'format' => '' ), $atts, 'hozio' );
    $tag    = sanitize_text_field( $atts['tag'] );
    $format = sanitize_text_field( $atts['format'] );

    if ( empty( $tag ) ) {
        return '';
    }

    // --- Calculated: years-of-experience ---
    if ( $tag === 'years-of-experience' ) {
        $start = (int) get_option( 'hozio_start_year', 0 );
        return $start > 0 ? (string) ( (int) date( 'Y' ) - $start ) : '0';
    }

    // --- Sitemap XML ---
    if ( $tag === 'sitemap-xml' ) {
        return esc_url( get_option( 'sitemap_url', home_url( '/sitemap.xml' ) ) );
    }

    // --- Icon-box tags (return full <a> element) ---
    $icon_box_tags = array(
        'phone-number-icon-box' => array( 'option' => 'hozio_company_phone_1', 'prefix' => 'tel:' ),
        'sms-icon-box'          => array( 'option' => 'hozio_sms_phone',       'prefix' => 'sms:' ),
        'email-icon-box'        => array( 'option' => 'hozio_company_email',   'prefix' => 'mailto:' ),
    );
    if ( isset( $icon_box_tags[ $tag ] ) ) {
        $val = esc_attr( get_option( $icon_box_tags[ $tag ]['option'], '' ) );
        return '<a href="' . esc_url( $icon_box_tags[ $tag ]['prefix'] . $val ) . '">' . esc_html( $val ) . '</a>';
    }

    // --- Phone tags ---
    $phone_tags = array(
        'company-phone-1' => 'hozio_company_phone_1',
        'company-phone-2' => 'hozio_company_phone_2',
        'google-ads-phone' => 'hozio_google_ads_phone',
    );
    if ( isset( $phone_tags[ $tag ] ) ) {
        $val = get_option( $phone_tags[ $tag ], '' );
        return $format === 'url' ? esc_url( 'tel:' . esc_attr( $val ) ) : esc_html( $val );
    }

    // --- SMS tags ---
    if ( $tag === 'sms-phone' ) {
        $val = get_option( 'hozio_sms_phone', '' );
        return $format === 'url' ? esc_url( 'sms:' . esc_attr( $val ) ) : esc_html( $val );
    }

    // --- Email tag ---
    if ( $tag === 'company-email' ) {
        $val = get_option( 'hozio_company_email', '' );
        return $format === 'url' ? esc_url( 'mailto:' . esc_attr( $val ) ) : esc_html( $val );
    }

    // --- Name display tags (return formatted display value) ---
    $name_tags = array(
        'company-phone-1-name' => 'hozio_company_phone_1',
        'company-phone-2-name' => 'hozio_company_phone_2',
        'sms-phone-name'       => 'hozio_sms_phone',
    );
    if ( isset( $name_tags[ $tag ] ) ) {
        return esc_html( get_option( $name_tags[ $tag ], '' ) );
    }

    // --- HTML-allowed tags ---
    if ( $tag === 'company-address' || $tag === 'business-hours' ) {
        $allowed = array(
            'br' => array(), 'a' => array( 'href' => array(), 'title' => array() ),
            'b' => array(), 'i' => array(), 'p' => array(),
            'ul' => array(), 'ol' => array(), 'li' => array(),
        );
        return wp_kses( get_option( 'hozio_' . str_replace( '-', '_', $tag ), '' ), $allowed );
    }

    // --- URL / social link tags ---
    $url_map = array(
        'gmb-link'     => 'hozio_gmb_link',
        'facebook'     => 'hozio_facebook_url',
        'instagram'    => 'hozio_instagram_url',
        'twitter'      => 'hozio_twitter_url',
        'tiktok'       => 'hozio_tiktok_url',
        'linkedin'     => 'hozio_linkedin_url',
        'bbb'          => 'hozio_bbb_url',
        'yelp'         => 'hozio_yelp_url',
        'youtube'      => 'hozio_youtube_url',
        'angies-list'  => 'hozio_angies_list_url',
        'home-advisor' => 'hozio_home_advisor_url',
    );
    if ( isset( $url_map[ $tag ] ) ) {
        return esc_url( get_option( $url_map[ $tag ], '' ) );
    }

    // --- Text tags ---
    if ( $tag === 'to-email-contact-form' ) {
        return esc_html( get_option( 'hozio_to_email_contact_form', '' ) );
    }

    // --- Fallback: custom tags (hozio_{tag-slug} option) ---
    $option_key = 'hozio_' . str_replace( '-', '_', $tag );
    $value = get_option( $option_key, '' );

    if ( $value === '' ) {
        // Also try with hyphens (some custom tags use hyphens in the option key)
        $value = get_option( 'hozio_' . $tag, '' );
    }

    if ( strpos( $value, '<script' ) !== false ) {
        return ''; // Never output scripts via shortcode
    }

    return esc_html( $value );
}
add_shortcode( 'hozio', 'hozio_shortcode_handler' );


// Hide all third-party admin notices on Hozio plugin pages
// Our own notices are rendered inline in page templates, so they're unaffected
function hozio_hide_third_party_notices() {
    $screen = get_current_screen();
    if (!$screen) return;

    // Check if we're on any Hozio plugin page
    if (strpos($screen->id, 'hozio') === false && strpos($screen->id, 'hozio_dynamic_tags') === false) return;

    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
}
add_action('in_admin_header', 'hozio_hide_third_party_notices', 999);

// Add the custom admin menu
function hozio_dynamic_tags_menu() {
    add_menu_page(
        'Hozio Dynamic Tags Settings',
        'Hozio Pro',
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
        'Blog Permalink & RSS Feed',
        'manage_options',
        'hozio-permalink-settings',
        'hozio_custom_permalink_settings_page'
    );
        // Add the new submenu for post type configuration
    add_submenu_page(
        'hozio_dynamic_tags',
        'Dynamic Query Post Types',
        'Query Post Types',
        'manage_options',
        'hozio-query-post-types',
        'hozio_query_post_types_page'
    );
    add_submenu_page(
    'hozio_dynamic_tags',
    'Taxonomy Archive Settings',
    'Archive Settings',
    'manage_options',
    'hozio-taxonomy-archives',
    'hozio_taxonomy_archive_settings_page'
    );
    add_submenu_page(
    'hozio_dynamic_tags', // ✅ CORRECT - has underscore
    'Loop Configurations',
    'Loop Configurations',
    'manage_options',
    'hozio-loop-configurations',
    'hozio_loop_configs_render_page'
    );

    // Plugin Settings (debug, feature toggles, system info)
    add_submenu_page(
        'hozio_dynamic_tags',
        'Hozio Pro Settings',
        'Hozio Pro Settings',
        'manage_options',
        'hozio-plugin-settings',
        'hozio_plugin_settings_page'
    );

    // Support & Help documentation page
    add_submenu_page(
        'hozio_dynamic_tags',
        'Hozio Pro Support',
        'Support & Help',
        'manage_options',
        'hozio-support',
        'hozio_support_page'
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

// Add a Dynamic Tags link under the plugin details
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'hozio_dynamic_tags_action_links');
function hozio_dynamic_tags_action_links($links) {
    $dynamic_tags_link = '<a href="admin.php?page=hozio_dynamic_tags">Dynamic Tags</a>';
    array_unshift($links, $dynamic_tags_link);
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
    $tag_value = isset($_GET['tag']) ? sanitize_text_field($_GET['tag']) : '';

    if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'hozio_remove_tag_' . $tag_value)) {
        wp_die('Unauthorized request');
    }
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

// Dynamic tag classes and registration are handled entirely by includes/dynamic-tags.php
// (loaded on line 31) — classes are defined inside the Elementor callback where the
// parent class is guaranteed to exist.

add_action('wp_footer', 'hozio_dynamic_nav_menu_inline_styles');
function hozio_dynamic_nav_menu_inline_styles() {
    // Skip on admin pages and AJAX requests - these styles are only needed on frontend
    if ( is_admin() || wp_doing_ajax() ) {
        return;
    }

    $text_color = esc_attr(get_option('hozio_nav_text_color', 'black')); // Dynamically retrieve text color
    ?>
    <style type="text/css">
        /* Style for the last menu item */
        #toggle-menu li:last-of-type > .elementor-item {
            background-color: var(--e-global-color-secondary, #FFFFFF) !important;
            color: <?php echo $text_color; ?> !important;
            padding: 25px 24px;
            font-weight: 600;
            font-size: 17px;
            text-align: center;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        #toggle-menu li:last-of-type > .elementor-item:hover {
            background-color: var(--e-global-color-secondary, #FFFFFF) !important;
            color: <?php echo $text_color; ?> !important;
        }
    </style>

    <style type="text/css">
        /* Apply the dynamic text color to the default state */
        #cta-text-color .elementor-cta__button,
        #cta-text-color .elementor-ribbon-inner {
            color: <?php echo $text_color; ?> !important; /* Apply dynamic color to the default state */
        }

        /* Completely avoid overriding hover styles */
        #cta-text-color .elementor-cta__button:hover,
        #cta-text-color .elementor-ribbon-inner:hover {
            color: auto !important; /* Allow Elementor's hover settings to take full effect */
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const textColor = '<?php echo esc_js($text_color); ?>';
            const elements = document.querySelectorAll('#cta-text-color .elementor-cta__button, #cta-text-color .elementor-ribbon-inner');

            elements.forEach(function (element) {
                // Set default color dynamically
                element.style.color = textColor;

                // Let Elementor handle hover styles completely
                element.addEventListener('mouseenter', function () {
                    element.style.color = ''; // Clear dynamic styles on hover
                });

                element.addEventListener('mouseleave', function () {
                    element.style.color = textColor; // Reapply dynamic color after hover
                });
            });
        });
    </script>
    <?php
}

add_action('admin_head', 'hozio_set_icon');
function hozio_set_icon() {
    $icon_url = plugins_url('assets/hozio-logo.gif', __FILE__); // Update with correct path
    echo '<style>
        .plugin-title img[src*="geopattern-icon"] {
            content: url("' . esc_url($icon_url) . '") !important;
            width: 64px !important;
            height: 64px !important;
        }
    </style>';
}

// Modify the query for child pages of "Services" when the query ID is "services_children"
add_action('elementor/query/services_children', function ($query) {
    // Get the ID of the "Services" page by its slug
    $parent_page_id = get_page_by_path('services')->ID; // Replace 'services' with your parent slug

    // Set the query to only include pages
    $query->set('post_type', 'page');

    // Set the query to only include child pages of the "Services" page
    $query->set('post_parent', $parent_page_id);
});




// Shortcode to display ACF fields from a specific page (by page ID)
function show_final_cta_from_page( $atts ) {
    $atts = shortcode_atts( array(
        'field' => '',
        'page_id' => '', // You'll add the page ID here
    ), $atts, 'final_cta' );

    if( empty($atts['page_id']) ) return '';

    // Get field value from the specific page ID
    $field_value = get_field( $atts['field'], (int)$atts['page_id'] );

    if( $field_value ) {
        return wp_kses_post( $field_value );
    }

    return '';
}
add_shortcode( 'final_cta', 'show_final_cta_from_page' );




//Hides Useful Links on HOG Template if ACF Value is empty
add_filter( 'the_content', function( $content ) {
    if ( is_admin() ) {
        return $content;
    }

    // FEATURE TOGGLE: Check if DOM parsing is enabled in Settings
    if ( function_exists( 'hozio_dom_parsing_enabled' ) && ! hozio_dom_parsing_enabled() ) {
        return $content;
    }

    // EARLY EXIT: Skip expensive DOM parsing if target classes don't exist in content
    if ( strpos( $content, 'hide-if-empty-acf' ) === false &&
         strpos( $content, 'hide-if-no-wiki' ) === false ) {
        return $content;
    }

    // Fallbacks for Icon Lists
    $icon_fallbacks = [
        '', 'Google Map Link', 'USPS Link',
        'Pharmacy Link', 'Weather Link', 'County & State Wiki Link',
    ];

    // **Corrected** fallback for Wiki container
    $wiki_fallbacks = [
        '',                            // truly empty
        'County & State Wiki Link',    // your ACF dynamic-tag fallback
    ];

    // Load into DOM
    libxml_use_internal_errors( true );
    $dom   = new DOMDocument();
    $dom->loadHTML( '<?xml encoding="utf-8"?>' . $content,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    $xpath = new DOMXPath( $dom );

    //
    // PART 1: Icon List cleanup
    //
    $widgets = $xpath->query(
        "//div[contains(@class,'elementor-widget-icon-list') and contains(@class,'hide-if-empty-acf')]"
    );
    foreach ( $widgets as $wrapper ) {
        $items     = $xpath->query( ".//li[contains(@class,'elementor-icon-list-item')]", $wrapper );
        $realCount = 0;
        foreach ( $items as $li ) {
            $text = trim( $li->textContent );
            if ( '' === $text || in_array( $text, $icon_fallbacks, true ) ) {
                $li->parentNode->removeChild( $li );
            } else {
                $realCount++;
            }
        }
        if ( 0 === $realCount ) {
            $wrapper->parentNode->removeChild( $wrapper );
        }
    }

    //
    // PART 2: Wiki container removal
    //
    $containers = $xpath->query(
        "//div[contains(concat(' ',normalize-space(@class),' '),' hide-if-no-wiki ')]"
    );
    foreach ( $containers as $wrapper ) {
        // Grab the first Text Editor inside
        $textEditor = $xpath->query(
            ".//div[contains(concat(' ',normalize-space(@class),' '),' elementor-widget-text-editor ')]",
            $wrapper
        );
        $text = '';
        if ( $textEditor->length ) {
            $text = trim( $textEditor->item(0)->textContent );
        }
        // Hide if empty or exactly the fallback
        if ( '' === $text || in_array( $text, $wiki_fallbacks, true ) ) {
            $wrapper->parentNode->removeChild( $wrapper );
        }
    }

    return $dom->saveHTML();
}, 20 );



// Register custom page templates
add_filter('theme_page_templates', function($templates) {
    $templates['html-sitemap-template.php'] = 'HTML Sitemap';
    return $templates;
});

// Load the template from the plugin includes folder
add_filter('template_include', function($template) {
    if (is_page()) {
        $current_template = get_page_template_slug(get_queried_object_id());
        if ($current_template === 'html-sitemap-template.php') {
            return plugin_dir_path(__FILE__) . 'includes/templates/html-sitemap-template.php';
        }
    }
    return $template;
});

class Your_Plugin_ACF_Maps {
    
    public function __construct() {
        // Register shortcode
        add_shortcode('gmb_map', array($this, 'output_gmb_map'));
        
        // Allow iframes for administrators
        add_filter('content_save_pre', array($this, 'allow_iframes'));
    }
    
    /**
     * Output GMB map shortcode
     */
    public function output_gmb_map($atts) {
        $atts = shortcode_atts(array(
            'field' => 'gmb_map',
            'post_id' => get_the_ID()
        ), $atts);
        
        $map_code = get_field($atts['field'], $atts['post_id'], false);
        
        if (!empty($map_code)) {
            return $map_code;
        }
        
        return '';
    }
    
    /**
     * Allow iframes in content
     */
    public function allow_iframes($content) {
        global $allowedposttags;
        
        $allowedposttags['iframe'] = array(
            'src' => true,
            'width' => true,
            'height' => true,
            'frameborder' => true,
            'allowfullscreen' => true,
            'style' => true,
            'loading' => true,
            'referrerpolicy' => true,
            'allow' => true,
        );
        
        return $content;
    }
}

// Initialize the ACF Maps functionality only if ACF is active
add_action('plugins_loaded', function() {
    if (function_exists('get_field')) {
        new Your_Plugin_ACF_Maps();
    }
});


// Force all ACF field groups to show in REST API
add_filter('acf/get_field_group', 'force_acf_rest_api_on');
function force_acf_rest_api_on($field_group) {
    $field_group['show_in_rest'] = 1;
    return $field_group;
}



// ========================
// ACF FIELD GROUPS REST API
// ========================

/**
 * Register REST API endpoint for ACF field groups and fields
 */
function hozio_register_acf_fields_endpoint() {
    register_rest_route('wp/v2', '/acf-fields', array(
        'methods'             => 'GET',
        'callback'            => 'hozio_get_acf_field_groups',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
    
    register_rest_route('wp/v1', '/acf-fields/(?P<group_key>[a-zA-Z0-9_-]+)', array(
        'methods'             => 'GET',
        'callback'            => 'hozio_get_acf_field_group_details',
        'permission_callback' => function() {
            return current_user_can('edit_posts');
        }
    ));
}
add_action('rest_api_init', 'hozio_register_acf_fields_endpoint');

/**
 * Get all ACF field groups with their fields
 */
function hozio_get_acf_field_groups($request) {
    if (!function_exists('acf_get_field_groups')) {
        return new WP_Error('acf_not_active', 'Advanced Custom Fields is not active', array('status' => 404));
    }
    
    $field_groups = acf_get_field_groups();
    $result = array();
    
    foreach ($field_groups as $group) {
        $fields = acf_get_fields($group['key']);
        
        $group_data = array(
            'key'       => $group['key'],
            'title'     => $group['title'],
            'active'    => $group['active'],
            'location'  => $group['location'],
            'fields'    => array()
        );
        
        if ($fields) {
            foreach ($fields as $field) {
                $group_data['fields'][] = hozio_format_acf_field($field);
            }
        }
        
        $result[] = $group_data;
    }
    
    return rest_ensure_response($result);
}

/**
 * Get a specific field group by key
 */
function hozio_get_acf_field_group_details($request) {
    if (!function_exists('acf_get_field_groups')) {
        return new WP_Error('acf_not_active', 'Advanced Custom Fields is not active', array('status' => 404));
    }
    
    $group_key = $request->get_param('group_key');
    $group = acf_get_field_group($group_key);
    
    if (!$group) {
        return new WP_Error('group_not_found', 'Field group not found', array('status' => 404));
    }
    
    $fields = acf_get_fields($group_key);
    
    $result = array(
        'key'       => $group['key'],
        'title'     => $group['title'],
        'active'    => $group['active'],
        'location'  => $group['location'],
        'fields'    => array()
    );
    
    if ($fields) {
        foreach ($fields as $field) {
            $result['fields'][] = hozio_format_acf_field($field);
        }
    }
    
    return rest_ensure_response($result);
}

/**
 * Format a single ACF field (handles nested fields like repeaters/groups)
 */
function hozio_format_acf_field($field) {
    $formatted = array(
        'key'           => $field['key'],
        'name'          => $field['name'],
        'label'         => $field['label'],
        'type'          => $field['type'],
        'required'      => !empty($field['required']),
        'instructions'  => $field['instructions'] ?? '',
    );
    
    if (in_array($field['type'], array('select', 'checkbox', 'radio', 'button_group'))) {
        $formatted['choices'] = $field['choices'] ?? array();
    }
    
    if (!empty($field['default_value'])) {
        $formatted['default_value'] = $field['default_value'];
    }
    
    if (!empty($field['sub_fields'])) {
        $formatted['sub_fields'] = array();
        foreach ($field['sub_fields'] as $sub_field) {
            $formatted['sub_fields'][] = hozio_format_acf_field($sub_field);
        }
    }
    
    if (!empty($field['layouts'])) {
        $formatted['layouts'] = array();
        foreach ($field['layouts'] as $layout) {
            $layout_data = array(
                'key'   => $layout['key'],
                'name'  => $layout['name'],
                'label' => $layout['label'],
                'sub_fields' => array()
            );
            if (!empty($layout['sub_fields'])) {
                foreach ($layout['sub_fields'] as $sub_field) {
                    $layout_data['sub_fields'][] = hozio_format_acf_field($sub_field);
                }
            }
            $formatted['layouts'][] = $layout_data;
        }
    }
    
    return $formatted;
}


add_action('init', 'hozio_ensure_taxonomy_rest_support', 99);
function hozio_ensure_taxonomy_rest_support() {
    global $wp_taxonomies;
    
    if (isset($wp_taxonomies['parent_pages'])) {
        $wp_taxonomies['parent_pages']->show_in_rest = true;
        $wp_taxonomies['parent_pages']->rest_base = 'parent_pages';
    }
    
    if (isset($wp_taxonomies['town_taxonomies'])) {
        $wp_taxonomies['town_taxonomies']->show_in_rest = true;
        $wp_taxonomies['town_taxonomies']->rest_base = 'town_taxonomies';
    }
}


