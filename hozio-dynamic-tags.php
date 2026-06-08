<?php
/*
Plugin Name:     Hozio Pro
Description:     Next-generation tools to power your website's performance and unlock new levels of speed, efficiency, and impact.
Version:         4.12.9
Author:          Hozio Web Dev
Author URI:      https://hozio.com
License:         GPL2
Text Domain:     hozio-dynamic-tags
GitHub Plugin URI: https://github.com/Mtuozzo86/hozio-dynamic-tags
GitHub Branch:   main
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define('HOZIO_VERSION', '4.12.9');
define('HOZIO_PLUGIN_FILE', __FILE__);
define('HOZIO_HUB_URL', 'https://www.hozio.com');

// Load custom logger first (enables HOZIO_DEBUG logging without WP_DEBUG)
require_once plugin_dir_path( __FILE__ ) . 'includes/hozio-logger.php';

// Load plugin settings page (debug toggles, feature toggles, system info)
require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-settings.php';

// Load self-hosted plugin updater (checks GitHub Releases for updates)
require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-updater.php';

// Rollback system — must load after updater so hozio_get_plugin_version() is available
require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-rollback.php';

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
require_once plugin_dir_path( __FILE__ ) . 'includes/service-towns-shortcode.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/support-page.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/sitemap-layout.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/image-sitemap.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/faq-schema.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/acf-shortcodes.php';

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
    $current_user = wp_get_current_user();
    $user_info = $current_user->ID ? $current_user->user_login . ' (ID: ' . $current_user->ID . ')' : 'unknown/CLI';
    hozio_audit_log("Hozio Pro ACTIVATED by {$user_info}", 'Lifecycle');

    if (get_option('hozio_hub_url') && !wp_next_scheduled('hozio_hub_heartbeat')) {
        wp_schedule_event(time() + 3600, 'hourly', 'hozio_hub_heartbeat');
    }
});

// Hub heartbeat cron: deactivation hook clears cron events + audit log
register_deactivation_hook(__FILE__, function() {
    // Log who deactivated the plugin (catches manual deactivation from WP admin)
    $current_user = wp_get_current_user();
    $user_info = $current_user->ID ? $current_user->user_login . ' (ID: ' . $current_user->ID . ')' : 'unknown/CLI';
    hozio_audit_log("Hozio Pro DEACTIVATED by {$user_info}", 'Lifecycle');

    wp_clear_scheduled_hook('hozio_hub_heartbeat');
    wp_clear_scheduled_hook('hozio_hub_heartbeat_login');
});

// Fallback cron check: if hub is configured but cron event is missing, reschedule
add_action('init', function() {
    if (get_option('hozio_hub_url') && !wp_next_scheduled('hozio_hub_heartbeat')) {
        wp_schedule_event(time() + 3600, 'hourly', 'hozio_hub_heartbeat');
    }
});

// Flush rewrite rules once after a version upgrade (taxonomy changes need this)
add_action('init', function() {
    $last_version = get_option('hozio_last_flushed_version', '0');
    if (version_compare($last_version, HOZIO_VERSION, '<')) {
        flush_rewrite_rules(true);
        update_option('hozio_last_flushed_version', HOZIO_VERSION);
    }
}, 99);


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
 * Default schema for the structured (Classic) Business Hours.
 * Mon–Fri default to 9 AM – 5 PM, weekends closed.
 * Times stored in 24-hour HH:MM form.
 */
function hozio_default_business_hours_classic() {
    $weekday = array( 'status' => 'open',   'open' => '09:00', 'close' => '17:00' );
    $weekend = array( 'status' => 'closed', 'open' => '09:00', 'close' => '17:00' );
    return array(
        'always_open' => false,
        'days' => array(
            'monday'    => $weekday,
            'tuesday'   => $weekday,
            'wednesday' => $weekday,
            'thursday'  => $weekday,
            'friday'    => $weekday,
            'saturday'  => $weekend,
            'sunday'    => $weekend,
        ),
    );
}

/**
 * Convert "HH:MM" 24-hour to "h:MM AM/PM" 12-hour for display.
 */
function hozio_format_time_12h( $time24 ) {
    if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', $time24, $m ) ) {
        return '';
    }
    $h  = (int) $m[1];
    $mm = $m[2];
    $period = ( $h >= 12 ) ? 'PM' : 'AM';
    $h12 = ( $h % 12 === 0 ) ? 12 : ( $h % 12 );
    return $h12 . ':' . $mm . ' ' . $period;
}

/**
 * Format the structured Business Hours array as <br>-separated display string.
 */
function hozio_format_business_hours_classic( $data ) {
    if ( ! is_array( $data ) ) {
        $data = hozio_default_business_hours_classic();
    }
    if ( ! empty( $data['always_open'] ) ) {
        return 'Open 24/7';
    }
    $labels = array(
        'monday'    => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
        'thursday'  => 'Thu', 'friday'  => 'Fri', 'saturday'  => 'Sat',
        'sunday'    => 'Sun',
    );
    $lines = array();
    foreach ( $labels as $key => $label ) {
        $day = isset( $data['days'][ $key ] ) ? $data['days'][ $key ] : null;
        if ( ! $day ) { continue; }
        if ( ( $day['status'] ?? 'closed' ) === 'closed' ) {
            $lines[] = $label . ': Closed';
        } else {
            $open  = hozio_format_time_12h( $day['open']  ?? '' );
            $close = hozio_format_time_12h( $day['close'] ?? '' );
            $lines[] = $label . ': ' . $open . ' &ndash; ' . $close;
        }
    }
    return implode( '<br>', $lines );
}

/**
 * Returns the Business Hours value to render on the frontend.
 * Switches between HTML mode (raw textarea) and Classic mode (structured).
 */
function hozio_get_business_hours_output() {
    $mode = get_option( 'hozio_business_hours_mode', 'html' );
    if ( $mode === 'classic' ) {
        $data = get_option( 'hozio_business_hours_classic', hozio_default_business_hours_classic() );
        return hozio_format_business_hours_classic( $data );
    }
    return get_option( 'hozio_business_hours', '' );
}

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
        $noswap_attr = '';
        if ( $tag === 'sms-icon-box' && get_option( 'hozio_sms_calltrk_noswap', '0' ) === '1' ) {
            $noswap_attr = ' data-calltrk-noswap';
        }
        return '<a href="' . esc_url( $icon_box_tags[ $tag ]['prefix'] . $val ) . '"' . $noswap_attr . '>' . esc_html( $val ) . '</a>';
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
        if ( $format === 'url' ) {
            return esc_url( 'sms:' . esc_attr( $val ) );
        }
        $noswap = get_option( 'hozio_sms_calltrk_noswap', '0' ) === '1';
        return $noswap ? '<span data-calltrk-noswap>' . esc_html( $val ) . '</span>' : esc_html( $val );
    }

    // --- Email tag ---
    if ( $tag === 'company-email' ) {
        $val = get_option( 'hozio_company_email', '' );
        return $format === 'url' ? esc_url( 'mailto:' . esc_attr( $val ) ) : esc_html( $val );
    }

    // --- Individual address part tags ---
    $addr_tags = array(
        'company-address-street' => 'hozio_address_street',
        'company-address-town'   => 'hozio_address_town',
        'company-address-state'  => 'hozio_address_state',
        'company-address-zip'    => 'hozio_address_zip',
    );
    if ( isset( $addr_tags[ $tag ] ) ) {
        return esc_html( get_option( $addr_tags[ $tag ], '' ) );
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
        if ( $tag === 'business-hours' ) {
            return wp_kses( hozio_get_business_hours_output(), $allowed );
        }
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
    if ( ! current_user_can('manage_options') || ! check_admin_referer('hozio_add_tag_nonce') ) {
        wp_die('Unauthorized request');
    }

    $tag_title = sanitize_text_field( wp_unslash( $_POST['tag_title'] ?? '' ) );
    $tag_type  = sanitize_text_field( wp_unslash( $_POST['tag_type']  ?? '' ) );
    $tag_value = sanitize_title( $tag_title );

    if ( empty( $tag_title ) || empty( $tag_type ) ) {
        wp_redirect( add_query_arg( 'tag-error', 'missing', admin_url('admin.php?page=hozio-add-remove-tags') ) );
        exit;
    }

    $custom_tags = get_option( 'hozio_custom_tags', [] );
    if ( ! is_array( $custom_tags ) ) $custom_tags = [];

    foreach ( $custom_tags as $tag ) {
        if ( $tag['value'] === $tag_value ) {
            wp_redirect( add_query_arg( 'tag-error', 'duplicate', admin_url('admin.php?page=hozio-add-remove-tags') ) );
            exit;
        }
    }

    $custom_tags[] = [ 'title' => $tag_title, 'value' => $tag_value, 'type' => $tag_type ];
    update_option( 'hozio_custom_tags', $custom_tags );

    // Bust object cache so the updated list is visible immediately
    wp_cache_delete( 'hozio_custom_tags', 'options' );
    wp_cache_delete( 'alloptions', 'options' );

    wp_redirect( add_query_arg( 'tag-added', '1', admin_url('admin.php?page=hozio-add-remove-tags') ) );
    exit;
}

// Handle tag removal — POST form to avoid GET-nonce issues
add_action('admin_post_hozio_remove_tag', 'hozio_remove_dynamic_tag');
function hozio_remove_dynamic_tag() {
    // Accept both POST (form) and GET (legacy link) submissions
    $tag_value = sanitize_text_field( wp_unslash( $_POST['tag'] ?? $_GET['tag'] ?? '' ) );
    $nonce     = wp_unslash( $_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? '' );

    if ( ! current_user_can('manage_options') || ! wp_verify_nonce( $nonce, 'hozio_remove_tag_' . $tag_value ) ) {
        wp_die('Unauthorized request');
    }

    $custom_tags = get_option( 'hozio_custom_tags', [] );
    if ( ! is_array( $custom_tags ) ) $custom_tags = [];

    foreach ( $custom_tags as $key => $tag ) {
        if ( $tag['value'] === $tag_value ) {
            unset( $custom_tags[$key] );
            break;
        }
    }

    update_option( 'hozio_custom_tags', array_values( $custom_tags ) );

    // Bust object cache so the updated list is visible immediately
    wp_cache_delete( 'hozio_custom_tags', 'options' );
    wp_cache_delete( 'alloptions', 'options' );

    wp_redirect( add_query_arg( 'tag-removed', '1', admin_url('admin.php?page=hozio-add-remove-tags') ) );
    exit;
}

// Dynamic tag classes and registration are handled entirely by includes/dynamic-tags.php
// (loaded on line 31) — classes are defined inside the Elementor callback where the
// parent class is guaranteed to exist.

// Universal HTML output fixer — single ob_start pass that handles:
//   • Duplicate id="cta-text-color" (Elementor Loop widget renders same template N times)
//   • Inline <script> CDATA wrapping (validators flag bare & / < / > in JS blocks)
//   • Nav menu <ul>/<li> line normalisation (validators lose nesting on single-line output)
//   • Elementor lightbox attribute entity cleanup (&quot; etc. in data attrs)
// Priority 0 so this outer buffer wraps everything; skipped on admin/AJAX/REST/feed.
add_action( 'template_redirect', 'hozio_html_fix_start_buffer', 0 );

function hozio_html_fix_start_buffer() {
    if (
        is_admin()
        || wp_doing_ajax()
        || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
        || ( defined( 'WP_CLI' ) && WP_CLI )
        || is_feed()
    ) {
        return;
    }
    ob_start( 'hozio_html_fix_process' );
}

function hozio_html_fix_process( $html ) {
    if ( empty( $html ) || stripos( $html, '<html' ) === false ) {
        return $html;
    }

    // ── Fix A: deduplicate id="cta-text-color" ──────────────────────────────
    if ( substr_count( $html, 'id="cta-text-color"' ) > 1 ) {
        $found = 0;
        $html  = preg_replace_callback(
            '/\bid="cta-text-color"/',
            function ( $m ) use ( &$found ) {
                $found++;
                return $found === 1 ? $m[0] : 'class="cta-text-color"';
            },
            $html
        );
    }

    // ── Fix B: wrap inline <script> content with CDATA markers ──────────────
    // Validators flag bare & / < / > inside script blocks. CDATA markers are
    // JS line-comments, so browsers ignore them entirely.
    // IMPORTANT: excludes application/ld+json and other non-JS types — wrapping
    // those with // comments breaks JSON syntax and corrupts structured data.
    if ( strpos( $html, '<script' ) !== false ) {
        $html = preg_replace_callback(
            '/<script(\b[^>]*)>([\s\S]*?)<\/script>/i',
            function ( $m ) {
                $attrs   = $m[1];
                $content = $m[2];

                // Skip: external (src=), non-JS types, empty, already wrapped
                if (
                    preg_match( '/\bsrc\s*=/i', $attrs )
                    || preg_match( '/\btype\s*=\s*["\']?\s*application\//i', $attrs )
                    || preg_match( '/\btype\s*=\s*["\']?\s*text\/(?!javascript)[a-z]/i', $attrs )
                    || trim( $content ) === ''
                    || strpos( $content, '//<![CDATA[' ) !== false
                    || strpos( $content, '<![CDATA[' )   !== false
                ) {
                    return $m[0];
                }

                if ( ! preg_match( '/[&<>]/', $content ) ) {
                    return $m[0];
                }

                return '<script' . $attrs . '>' . "\n//<![CDATA[\n" . $content . "\n//]]>\n" . '</script>';
            },
            $html
        );
    }

    // ── Fix C: normalise nav menu <ul>/<li> line structure ──────────────────
    // Elementor sometimes concatenates list items onto one line; validators
    // lose the parent <ul> context and report errors. Newlines restore nesting.
    if ( strpos( $html, 'elementor-nav-menu' ) !== false ) {
        $html = preg_replace_callback(
            '/<ul([^>]*class="[^"]*elementor-nav-menu[^"]*"[^>]*)>([\s\S]*?)<\/ul>/i',
            function ( $m ) {
                $content = preg_replace( '/(<\/?li[\s>])/i', "\n$1", $m[2] );
                $content = preg_replace( '/(<\/?ul[\s>])/i', "\n$1", $content );
                return '<ul' . $m[1] . '>' . $content . "\n</ul>";
            },
            $html
        );
    }

    // ── Fix D: clean HTML entities from Elementor lightbox attributes ────────
    // Captions with special chars get double-encoded (&quot; etc.) inside
    // data-elementor-lightbox-title / -description. Decode then re-encode cleanly.
    if ( strpos( $html, 'data-elementor-lightbox-' ) !== false ) {
        $html = preg_replace_callback(
            '/\b(data-elementor-lightbox-(?:title|description))="([^"]*)"/i',
            function ( $m ) {
                if ( strpos( $m[2], '&' ) === false ) {
                    return $m[0];
                }
                $decoded = html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                $clean   = htmlspecialchars( $decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
                return $m[1] . '="' . $clean . '"';
            },
            $html
        );
    }

    // ── Fix E: remove <link> tags with empty or missing href ─────────────────
    // W3C: "The attribute href of the tag link must have a value."
    // Some plugins output <link href=""> or bare <link href> in wp_head.
    // Handled here (full-page buffer) rather than a separate wp_head ob_start.
    if ( strpos( $html, '<link' ) !== false ) {
        // href="" or href=''  (empty quoted value)
        $html = preg_replace(
            '/<link(?=[^>]*\bhref\s*=\s*["\']["\'])[^>]*>\s*/i',
            '',
            $html
        );
        // bare href with no = at all
        $html = preg_replace(
            '/<link(?=[^>]*\bhref\b(?!\s*=))[^>]*>\s*/i',
            '',
            $html
        );
    }

    // ── Fix F: add data-calltrk-noswap to every <a href="sms:..."> link ────────
    // The Elementor URL-type dynamic tag outputs a bare URL string into the href,
    // so there is no hook to add the attribute from within the tag itself.
    // Stamping it server-side in the full-page buffer ensures CallRail sees the
    // attribute before its JS swap runs, protecting both href and link text.
    if ( get_option( 'hozio_sms_calltrk_noswap', '0' ) === '1'
         && strpos( $html, 'href=' ) !== false
         && stripos( $html, 'sms:' ) !== false ) {
        $html = preg_replace_callback(
            '/<a(\s[^>]*?)>/i',
            function ( $m ) {
                $tag = $m[0];
                // Only target anchors whose href begins with sms:
                if ( ! preg_match( '/\bhref\s*=\s*["\']sms:/i', $tag ) ) {
                    return $tag;
                }
                // Skip if already marked
                if ( stripos( $tag, 'data-calltrk-noswap' ) !== false ) {
                    return $tag;
                }
                // Insert attribute before the closing >
                return '<a' . $m[1] . ' data-calltrk-noswap>';
            },
            $html
        );
    }

    return $html;
}

// Strip raw <u> / </u> tags typed into Elementor Icon List text fields.
// Validators flag them as "tag must be paired" / "start tag match failed".
// CSS underline applied via selectors is unaffected — only inline HTML tags removed.
add_filter( 'elementor/widget/render_content', 'hozio_icon_list_strip_u_tags', 10, 2 );
function hozio_icon_list_strip_u_tags( $content, $widget ) {
    if ( 'icon-list' === $widget->get_name() ) {
        $content = preg_replace( '/<\/?u>/i', '', $content );
    }
    return $content;
}

// Convert genuinely stray < and > in post content to HTML entities.
// Only targets bare angle brackets NOT part of a valid tag, shortcode,
// script, or style block. Conservative by design — the > regex excludes
// chars preceded by spaces/alphanumerics to avoid false positives on
// normal closing tags.
add_filter( 'the_content', 'hozio_escape_stray_angle_brackets', 20 );
function hozio_escape_stray_angle_brackets( $content ) {
    $content = preg_replace( '/(?<![="\'\w])<(?![\/a-zA-Z!?\-])/', '&lt;', $content );
    $content = preg_replace( '/(?<![a-zA-Z0-9"\'\/\s])>(?!\s*<)/', '&gt;', $content );
    return $content;
}

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
        /* Supports both #cta-text-color (CSS ID, single element) and
           .cta-text-color (CSS class, safe to use inside Loop widgets) */
        #cta-text-color .elementor-cta__button,
        .cta-text-color .elementor-cta__button,
        #cta-text-color .elementor-ribbon-inner,
        .cta-text-color .elementor-ribbon-inner {
            color: <?php echo $text_color; ?> !important;
        }

        #cta-text-color .elementor-cta__button:hover,
        .cta-text-color .elementor-cta__button:hover,
        #cta-text-color .elementor-ribbon-inner:hover,
        .cta-text-color .elementor-ribbon-inner:hover {
            color: auto !important;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var textColor = '<?php echo esc_js($text_color); ?>';

            // Elementor Loop widgets render the same template multiple times, which
            // duplicates any CSS ID set in the template (e.g. id="cta-text-color").
            // Deduplicate here: keep the first as an ID, convert the rest to a class
            // so the browser DOM has no duplicate IDs while still applying the color.
            var allById = document.querySelectorAll('[id="cta-text-color"]');
            allById.forEach(function (el, idx) {
                if (idx > 0) {
                    el.removeAttribute('id');
                    el.classList.add('cta-text-color');
                }
            });

            // Now target both the original ID (single element) and the class (loop copies)
            var elements = document.querySelectorAll(
                '#cta-text-color .elementor-cta__button, .cta-text-color .elementor-cta__button, ' +
                '#cta-text-color .elementor-ribbon-inner, .cta-text-color .elementor-ribbon-inner'
            );

            elements.forEach(function (element) {
                element.style.color = textColor;

                element.addEventListener('mouseenter', function () {
                    element.style.color = '';
                });

                element.addEventListener('mouseleave', function () {
                    element.style.color = textColor;
                });
            });
        });
    </script>
    <?php
}

add_action('admin_footer', 'hozio_set_icon');
function hozio_set_icon() {
    global $pagenow;
    if (!in_array($pagenow, ['plugins.php', 'update-core.php'], true)) return;

    $svg_url = plugins_url('assets/hozio-burst.svg', __FILE__);
    ?>
    <script>
    (function() {
        var svgUrl = <?php echo wp_json_encode($svg_url); ?>;
        document.querySelectorAll('td').forEach(function(cell) {
            var strong = cell.querySelector('strong');
            if (!strong || strong.textContent.trim() !== 'Hozio Pro') return;
            var img = cell.querySelector('img');
            if (!img) return;
            img.src = svgUrl;
            img.style.width = '64px';
            img.style.height = '64px';
        });
    })();
    </script>
    <?php
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

    // Strip the XML processing instruction we injected before loadHTML.
    // PHP's saveHTML() is supposed to drop it but doesn't reliably with the
    // LIBXML_HTML_NOIMPLIED flag, and when it leaks into rendered HTML it
    // causes browser warnings and validator errors. Regex tolerates spacing.
    $output = $dom->saveHTML();
    $output = preg_replace( '/^\s*<\?xml[^>]*\?>\s*/i', '', (string) $output );
    return $output;
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

// Inject meta tags into <head> for the HTML sitemap template
add_action('wp_head', function() {
    if (!is_page()) return;
    if (get_page_template_slug(get_queried_object_id()) !== 'html-sitemap-template.php') return;

    echo '<meta name="robots" content="noindex">' . "\n";

    // Only add meta description if one doesn't already exist (e.g. from Yoast)
    if (!defined('WPSEO_VERSION')) {
        echo '<meta name="description" content="Complete HTML sitemap showing all pages, posts, categories, and archives for easy navigation and search engine indexing.">' . "\n";
    }
}, 1);

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

add_action( 'init', function() {
    $yoast_keys = [
        '_yoast_wpseo_title',
        '_yoast_wpseo_metadesc',
    ];
    foreach ( $yoast_keys as $key ) {
        foreach ( [ 'post', 'page' ] as $post_type ) {
            register_post_meta( $post_type, $key, [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback'     => function() {
                    return current_user_can( 'edit_posts' );
                },
            ] );
        }
    }
} );
