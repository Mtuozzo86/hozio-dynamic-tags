<?php
/**
 * Hozio Pro Plugin Settings Page
 * Provides centralized control for plugin features and debugging
 */

if (!defined('ABSPATH')) exit;

/**
 * Register plugin settings
 */
function hozio_plugin_settings_register() {
    register_setting('hozio_plugin_settings', 'hozio_debug_enabled', [
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);

    register_setting('hozio_plugin_settings', 'hozio_dom_parsing_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);

    register_setting('hozio_plugin_settings', 'hozio_service_menu_sync_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);

    register_setting('hozio_plugin_settings', 'hozio_license_key', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field'
    ]);

    register_setting('hozio_plugin_settings', 'hozio_auto_updates_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);

    register_setting('hozio_plugin_settings', 'hozio_canonical_redirect_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);

    register_setting('hozio_plugin_settings', 'hozio_sug_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
    register_setting('hozio_plugin_settings', 'hozio_sug_www_mode', [
        'type' => 'string',
        'default' => 'home',
        'sanitize_callback' => 'sanitize_key'
    ]);

    register_setting('hozio_plugin_settings', 'hozio_staging_guard_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
    register_setting('hozio_plugin_settings', 'hozio_staging_banner_public', [
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
    register_setting('hozio_plugin_settings', 'hozio_staging_guard_all_red', [
        'type' => 'boolean',
        'default' => false,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
    register_setting('hozio_plugin_settings', 'hozio_staging_guard_environment', [
        'type' => 'string',
        'default' => 'auto',
        'sanitize_callback' => 'sanitize_key'
    ]);

    register_setting('hozio_plugin_settings', 'hozio_block_wrong_urls_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);

    register_setting('hozio_plugin_settings', 'hozio_faq_schema_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
    register_setting('hozio_plugin_settings', 'hozio_faq_schema_general_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
    register_setting('hozio_plugin_settings', 'hozio_faq_schema_service_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
    register_setting('hozio_plugin_settings', 'hozio_faq_schema_town_enabled', [
        'type' => 'boolean',
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ]);
    register_setting('hozio_plugin_settings', 'hozio_faq_schema_service_exclude', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
    register_setting('hozio_plugin_settings', 'hozio_faq_schema_town_exclude', [
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field'
    ]);
}
add_action('admin_init', 'hozio_plugin_settings_register');

/**
 * Handle AJAX action to clear debug log
 */
function hozio_ajax_clear_debug_log() {
    check_ajax_referer('hozio_clear_log_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $result = hozio_clear_log();

    if ($result) {
        wp_send_json_success(['message' => 'Debug log cleared successfully']);
    } else {
        wp_send_json_error('Failed to clear debug log');
    }
}
add_action('wp_ajax_hozio_clear_debug_log', 'hozio_ajax_clear_debug_log');

/**
 * Handle AJAX action to test debug logging
 */
function hozio_ajax_test_debug_log() {
    check_ajax_referer('hozio_test_log_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    // Check if debug logging is enabled
    if (!hozio_debug_enabled()) {
        wp_send_json_error('Debug logging is not enabled. Please enable it first and save settings.');
    }

    // Write a test entry to the log
    $test_message = 'Test log entry created from Settings page by user: ' . wp_get_current_user()->user_login;
    hozio_log($test_message, 'SettingsTest');

    // Also log some system info
    hozio_log('WordPress Version: ' . get_bloginfo('version'), 'SettingsTest');
    hozio_log('PHP Version: ' . PHP_VERSION, 'SettingsTest');
    hozio_log('Plugin Version: ' . hozio_get_plugin_version(), 'SettingsTest');

    // Check if the log file was actually created
    $log_path = hozio_get_log_path();
    if (file_exists($log_path)) {
        $log_size = hozio_get_log_size();
        wp_send_json_success([
            'message' => 'Test log entry written successfully!',
            'log_size' => $log_size,
            'log_path' => $log_path
        ]);
    } else {
        wp_send_json_error('Log file was not created. Check file permissions on wp-content directory.');
    }
}
add_action('wp_ajax_hozio_test_debug_log', 'hozio_ajax_test_debug_log');

/**
 * Handle AJAX action to clear plugin caches
 */
function hozio_ajax_clear_plugin_caches() {
    check_ajax_referer('hozio_clear_caches_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    global $wpdb;

    // Clear all hozio-related transients EXCEPT hub transients (license status, rate limit)
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hozio_%' AND option_name NOT LIKE '_transient_hozio_hub_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hozio_%' AND option_name NOT LIKE '_transient_timeout_hozio_hub_%'");

    wp_send_json_success(['message' => 'Plugin caches cleared successfully']);
}
add_action('wp_ajax_hozio_clear_plugin_caches', 'hozio_ajax_clear_plugin_caches');

/**
 * Handle AJAX action to connect to Hub
 */
function hozio_ajax_hub_connect() {
    check_ajax_referer('hozio_hub_connect_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $hub_url = defined('HOZIO_HUB_URL') ? HOZIO_HUB_URL : '';
    $registration_key = sanitize_text_field(trim($_POST['registration_key'] ?? ''));

    if (empty($hub_url)) {
        wp_send_json_error('Hub URL is not configured.');
    }

    if (empty($registration_key)) {
        wp_send_json_error('Registration Key is required.');
    }

    if (!class_exists('Hozio_Hub_Client')) {
        wp_send_json_error('Hub client module not available. Please update the plugin.');
    }

    $result = Hozio_Hub_Client::register($hub_url, $registration_key);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success([
        'message'        => 'Successfully connected to Hub!',
        'license_status' => $result['license_status'] ?? 'active',
    ]);
}
add_action('wp_ajax_hozio_hub_connect', 'hozio_ajax_hub_connect');

/**
 * Handle AJAX action to verify live Hub connection
 */
function hozio_ajax_check_hub_connection() {
    check_ajax_referer('hozio_check_connection_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['type' => 'permission', 'message' => 'Permission denied']);
    }

    if (!class_exists('Hozio_Hub_Client') || !Hozio_Hub_Client::is_connected()) {
        wp_send_json_error(['type' => 'not_connected', 'message' => 'Not connected to Hub']);
    }

    $hub_url    = get_option('hozio_hub_url');
    $site_token = get_option('hozio_hub_site_token');
    $endpoint   = $hub_url . '/wp-json/hozio-hub/v1/heartbeat';

    $response = wp_remote_post($endpoint, [
        'timeout'   => 15,
        'sslverify' => true,
        'headers'   => [
            'Authorization' => 'Bearer ' . $site_token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
            'site_url'       => home_url(),
            'plugin_version' => defined('HOZIO_VERSION') ? HOZIO_VERSION : '0.0.0',
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => phpversion(),
        ]),
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['type' => 'network', 'message' => 'Could not reach Hub: ' . $response->get_error_message()]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code === 401 || $code === 403) {
        wp_send_json_error(['type' => 'auth', 'message' => 'Authentication failed — this site was deleted from the Hub. Please disconnect and re-register.']);
    }

    if ($code === 404) {
        wp_send_json_error(['type' => 'not_found', 'message' => 'Hub endpoint not found. Is the Hub plugin active at ' . $hub_url . '?']);
    }

    if ($code === 429) {
        wp_send_json_success([
            'type'           => 'ok',
            'license_status' => get_option('hozio_hub_last_known_status', 'active'),
            'note'           => 'rate_limited',
        ]);
    }

    if ($code !== 200 || empty($body)) {
        wp_send_json_error(['type' => 'bad_response', 'message' => 'Hub returned unexpected status ' . $code]);
    }

    $license_status = $body['license_status'] ?? get_option('hozio_hub_last_known_status', 'active');
    set_transient('hozio_hub_license_status', $license_status, DAY_IN_SECONDS);
    update_option('hozio_hub_last_known_status', $license_status);

    wp_send_json_success(['type' => 'ok', 'license_status' => $license_status]);
}
add_action('wp_ajax_hozio_check_hub_connection', 'hozio_ajax_check_hub_connection');

/**
 * Handle AJAX action to disconnect from Hub
 */
function hozio_ajax_hub_disconnect() {
    check_ajax_referer('hozio_hub_disconnect_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    if (!class_exists('Hozio_Hub_Client')) {
        wp_send_json_error('Hub client module not available.');
    }

    Hozio_Hub_Client::disconnect();

    wp_send_json_success(['message' => 'Disconnected from Hub. License validation reverted to manual key.']);
}
add_action('wp_ajax_hozio_hub_disconnect', 'hozio_ajax_hub_disconnect');

/**
 * Handle AJAX action to check for plugin updates
 */
function hozio_ajax_check_for_updates() {
    check_ajax_referer('hozio_check_updates_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    // Check if license is valid
    if (function_exists('hozio_is_license_valid') && !hozio_is_license_valid()) {
        wp_send_json_error('Please enter a valid license key first.');
    }

    // Clear update cache and force WordPress to check for updates
    delete_transient('hozio_plugin_update_cache');
    delete_site_transient('update_plugins');

    // Store check timestamp
    update_option('hozio_last_update_check', time());

    // Force WordPress to check for plugin updates
    wp_update_plugins();

    // Get fresh update data
    $update_plugins = get_site_transient('update_plugins');
    $plugin_file = 'hozio-dynamic-tags/hozio-dynamic-tags.php';

    // Get timing info
    $auto_updates_enabled = get_option('hozio_auto_updates_enabled', '1') === '1';
    $auto_update_status = function_exists('hozio_get_auto_update_status') ? hozio_get_auto_update_status() : 'Unknown';

    if (isset($update_plugins->response[$plugin_file])) {
        $update = $update_plugins->response[$plugin_file];

        // Direct update URL that triggers the update immediately
        $update_url = wp_nonce_url(
            admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($plugin_file)),
            'upgrade-plugin_' . $plugin_file
        );

        wp_send_json_success([
            'has_update' => true,
            'message' => 'Update available! Version ' . $update->new_version . ' is ready.',
            'new_version' => $update->new_version,
            'update_url' => $update_url,
            'last_checked' => 'Just now',
            'next_check' => function_exists('hozio_get_next_update_check') ? hozio_get_next_update_check() : 'Unknown',
            'auto_update_status' => $auto_update_status,
            'auto_updates_enabled' => $auto_updates_enabled
        ]);
    } else {
        wp_send_json_success([
            'has_update' => false,
            'message' => 'You are running the latest version.',
            'current_version' => hozio_get_plugin_version(),
            'last_checked' => 'Just now',
            'next_check' => function_exists('hozio_get_next_update_check') ? hozio_get_next_update_check() : 'Unknown',
            'auto_update_status' => $auto_update_status,
            'auto_updates_enabled' => $auto_updates_enabled
        ]);
    }
}
add_action('wp_ajax_hozio_check_for_updates', 'hozio_ajax_check_for_updates');

/**
 * Save plugin settings
 */
function hozio_plugin_settings_save() {
    if (!isset($_POST['hozio_plugin_settings_nonce']) ||
        !wp_verify_nonce($_POST['hozio_plugin_settings_nonce'], 'hozio_plugin_settings_save')) {
        wp_die('Security check failed');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    // Save debug enabled setting
    $debug_enabled = isset($_POST['hozio_debug_enabled']) ? '1' : '0';
    update_option('hozio_debug_enabled', $debug_enabled);

    // Save DOM parsing enabled setting
    $dom_parsing_enabled = isset($_POST['hozio_dom_parsing_enabled']) ? '1' : '0';
    update_option('hozio_dom_parsing_enabled', $dom_parsing_enabled);

    // Save service menu sync enabled setting
    $service_menu_sync_enabled = isset($_POST['hozio_service_menu_sync_enabled']) ? '1' : '0';
    update_option('hozio_service_menu_sync_enabled', $service_menu_sync_enabled);

    // Save license key — never overwrite an existing key with an empty submission
    if (isset($_POST['hozio_license_key'])) {
        $submitted_key = sanitize_text_field(trim($_POST['hozio_license_key']));
        $existing_key  = get_option('hozio_license_key', '');
        if (!empty($submitted_key) || empty($existing_key)) {
            update_option('hozio_license_key', $submitted_key);
        }
    }

    // Save auto-updates enabled setting
    $auto_updates_enabled = isset($_POST['hozio_auto_updates_enabled']) ? '1' : '0';
    update_option('hozio_auto_updates_enabled', $auto_updates_enabled);

    // Save canonical redirect enabled setting
    $canonical_redirect_enabled = isset($_POST['hozio_canonical_redirect_enabled']) ? '1' : '0';
    update_option('hozio_canonical_redirect_enabled', $canonical_redirect_enabled);

    // Save wrong-URL (ghost page) guard setting
    $block_wrong_urls_enabled = isset($_POST['hozio_block_wrong_urls_enabled']) ? '1' : '0';
    update_option('hozio_block_wrong_urls_enabled', $block_wrong_urls_enabled);

    // Save Staging Index Guard settings
    update_option('hozio_staging_guard_enabled', isset($_POST['hozio_staging_guard_enabled']) ? '1' : '0');
    update_option('hozio_staging_banner_public', isset($_POST['hozio_staging_banner_public']) ? '1' : '0');
    update_option('hozio_staging_guard_all_red', isset($_POST['hozio_staging_guard_all_red']) ? '1' : '0');
    $staging_env = isset($_POST['hozio_staging_guard_environment'])
        ? sanitize_key(wp_unslash($_POST['hozio_staging_guard_environment']))
        : 'auto';
    if (!in_array($staging_env, ['auto', 'staging', 'production'], true)) {
        $staging_env = 'auto';
    }
    update_option('hozio_staging_guard_environment', $staging_env);
    if (function_exists('hozio_sig_flush_status')) {
        hozio_sig_flush_status();
    }

    // Save Dev URL Guard settings
    update_option('hozio_sug_enabled', isset($_POST['hozio_sug_enabled']) ? '1' : '0');
    $sug_mode = isset($_POST['hozio_sug_www_mode'])
        ? sanitize_key(wp_unslash($_POST['hozio_sug_www_mode']))
        : 'home';
    if (!in_array($sug_mode, ['home', 'www', 'nowww'], true)) {
        $sug_mode = 'home';
    }
    update_option('hozio_sug_www_mode', $sug_mode);

    // Save fleet plugin auto-update settings
    $auto_update_all = isset($_POST['hozio_auto_update_all_plugins']) ? '1' : '0';
    update_option('hozio_auto_update_all_plugins', $auto_update_all);
    update_option('hozio_auto_update_force', isset($_POST['hozio_auto_update_force']) ? '1' : '0');
    update_option(
        'hozio_auto_update_exclude',
        sanitize_textarea_field($_POST['hozio_auto_update_exclude'] ?? '')
    );


    // Save FAQ schema settings
    update_option('hozio_faq_schema_enabled',         isset($_POST['hozio_faq_schema_enabled'])         ? '1' : '0');
    update_option('hozio_faq_schema_general_enabled',  isset($_POST['hozio_faq_schema_general_enabled'])  ? '1' : '0');
    update_option('hozio_faq_schema_service_enabled',  isset($_POST['hozio_faq_schema_service_enabled'])  ? '1' : '0');
    update_option('hozio_faq_schema_town_enabled',     isset($_POST['hozio_faq_schema_town_enabled'])     ? '1' : '0');
    update_option('hozio_faq_schema_service_exclude',  sanitize_text_field($_POST['hozio_faq_schema_service_exclude'] ?? ''));
    update_option('hozio_faq_schema_town_exclude',           sanitize_text_field($_POST['hozio_faq_schema_town_exclude']           ?? ''));
    wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=hozio-plugin-settings')));
    exit;
}
add_action('admin_post_hozio_plugin_settings_save', 'hozio_plugin_settings_save');

/**
 * Get plugin version from main file header
 */
function hozio_get_plugin_version() {
    $plugin_data = get_file_data(
        dirname(__FILE__) . '/../hozio-dynamic-tags.php',
        ['Version' => 'Version']
    );
    return $plugin_data['Version'] ?? 'Unknown';
}

/**
 * Check if DOM parsing feature is enabled
 */
function hozio_dom_parsing_enabled() {
    // Default to true if option doesn't exist
    $enabled = get_option('hozio_dom_parsing_enabled', '1');
    return $enabled === '1' || $enabled === true;
}

/**
 * Check if service menu sync feature is enabled
 */
function hozio_service_menu_sync_enabled() {
    // Default to true if option doesn't exist
    $enabled = get_option('hozio_service_menu_sync_enabled', '1');
    return $enabled === '1' || $enabled === true;
}

/**
 * Classify an ACF field type into one of our shortcode badge buckets.
 * Returns: ['badge' => 'hozio-badge-...', 'label' => '...', 'sc_template' => '[acf|acf_img|acf_raw field="%s"]']
 */
function hozio_sc_classify_acf_field_type( $type ) {
    $img_types  = array( 'image', 'gallery', 'file' );
    $raw_types  = array( 'wysiwyg', 'html', 'code_editor', 'oembed' );
    $misc_types = array(
        'select', 'checkbox', 'radio', 'true_false', 'button_group',
        'date_picker', 'date_time_picker', 'time_picker', 'color_picker',
        'taxonomy', 'user', 'page_link', 'post_object', 'relationship', 'link',
        'range', 'number', 'email', 'url', 'password',
    );
    if ( in_array( $type, $img_types, true ) ) {
        return array( 'badge' => 'hozio-badge-img',  'label' => 'image', 'sc' => 'acf_img' );
    }
    if ( in_array( $type, $raw_types, true ) ) {
        return array( 'badge' => 'hozio-badge-raw',  'label' => 'raw',   'sc' => 'acf_raw' );
    }
    if ( in_array( $type, $misc_types, true ) ) {
        return array( 'badge' => 'hozio-badge-misc', 'label' => 'misc',  'sc' => 'acf' );
    }
    return array(    'badge' => 'hozio-badge-text', 'label' => 'text',  'sc' => 'acf' );
}

/**
 * Build the best shortcode string for a given ACF field. If a dedicated
 * shortcode tag exists for this field name (registered in acf-shortcodes.php),
 * use [field_name]; otherwise fall back to the generic [acf|acf_img|acf_raw field="..."].
 */
function hozio_sc_build_shortcode( $field_name, $sc_handler ) {
    if ( shortcode_exists( $field_name ) ) {
        return '[' . $field_name . ']';
    }
    return '[' . $sc_handler . ' field="' . $field_name . '"]';
}

/**
 * Recursively render ACF fields as shortcode chips inside an accordion section.
 * Sub-fields of repeater/group/flexible_content get a prefixed name (parent_child).
 */
function hozio_sc_render_acf_fields( $fields, $prefix = '' ) {
    if ( empty( $fields ) || ! is_array( $fields ) ) return;

    $complex_types = array( 'repeater', 'group', 'flexible_content', 'clone' );
    $buckets = array(
        'text' => array(), 'image' => array(), 'raw' => array(), 'misc' => array(),
    );
    $complex_fields = array();

    foreach ( $fields as $field ) {
        if ( ! is_array( $field ) || empty( $field['name'] ) ) continue;
        if ( in_array( $field['type'], $complex_types, true ) ) {
            $complex_fields[] = $field;
            continue;
        }
        $cls  = hozio_sc_classify_acf_field_type( $field['type'] );
        $name = $prefix . $field['name'];
        $sc   = hozio_sc_build_shortcode( $name, $cls['sc'] );
        $buckets[ $cls['label'] ][] = array(
            'sc'    => $sc,
            'label' => $field['label'] ?? $name,
            'type'  => $field['type'],
        );
    }

    foreach ( $buckets as $label => $items ) {
        if ( empty( $items ) ) continue;
        $cls = hozio_sc_classify_acf_field_type(
            $label === 'image' ? 'image' : ( $label === 'raw' ? 'wysiwyg' : ( $label === 'misc' ? 'select' : 'text' ) )
        );
        echo '<div class="hozio-sc-sub">';
        echo '<span class="hozio-sc-type-badge ' . esc_attr( $cls['badge'] ) . '">' . esc_html( $label ) . '</span>';
        echo '<div class="hozio-sc-grid">';
        foreach ( $items as $it ) {
            echo '<code class="hozio-sc-chip" title="' . esc_attr( $it['label'] . ' (' . $it['type'] . ')' ) . '">' . esc_html( $it['sc'] ) . '</code>';
        }
        echo '</div></div>';
    }

    // Render complex fields (repeater, group, etc.) with their sub-fields recursively
    foreach ( $complex_fields as $field ) {
        $name        = $prefix . $field['name'];
        $type_label  = $field['type'];
        $field_label = $field['label'] ?? $name;

        echo '<div class="hozio-sc-sub" style="margin-top:10px;padding:10px 12px;background:#fafbfc;border:1px solid #e5e7eb;border-radius:6px;">';
        echo '<div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">';
        echo esc_html( $field_label );
        echo ' <span style="font-weight:400;color:#9ca3af;font-size:11px;">(' . esc_html( $type_label ) . ')</span>';
        echo '</div>';

        if ( ! empty( $field['sub_fields'] ) ) {
            hozio_sc_render_acf_fields( $field['sub_fields'], $name . '_' );
        } elseif ( ! empty( $field['layouts'] ) ) {
            foreach ( $field['layouts'] as $layout ) {
                echo '<div style="margin-top:6px;font-size:11px;color:#6b7280;font-style:italic;">Layout: <strong>' . esc_html( $layout['name'] ?? '' ) . '</strong></div>';
                if ( ! empty( $layout['sub_fields'] ) ) {
                    hozio_sc_render_acf_fields( $layout['sub_fields'], $name . '_' );
                }
            }
        }
        echo '</div>';
    }
}

/**
 * Render the plugin settings page
 */
function hozio_plugin_settings_page() {
    // Get current settings
    $debug_enabled = get_option('hozio_debug_enabled', '0');
    $dom_parsing_enabled = get_option('hozio_dom_parsing_enabled', '1');
    $service_menu_sync_enabled = get_option('hozio_service_menu_sync_enabled', '1');
    $license_key = get_option('hozio_license_key', '');
    $auto_updates_enabled = get_option('hozio_auto_updates_enabled', '1');
    $canonical_redirect_enabled = get_option('hozio_canonical_redirect_enabled', '1');
    $block_wrong_urls_enabled   = get_option('hozio_block_wrong_urls_enabled', '1');
    $staging_guard_enabled      = get_option('hozio_staging_guard_enabled', '1');
    $staging_banner_public      = get_option('hozio_staging_banner_public', '0');
    $staging_guard_all_red      = get_option('hozio_staging_guard_all_red', '0');
    $staging_guard_environment  = get_option('hozio_staging_guard_environment', 'auto');
    $sug_enabled                = get_option('hozio_sug_enabled', '1');
    $sug_www_mode               = get_option('hozio_sug_www_mode', 'home');
    $auto_update_all_plugins    = get_option('hozio_auto_update_all_plugins', '1');
    $auto_update_force          = get_option('hozio_auto_update_force', '0');
    $auto_update_exclude        = get_option('hozio_auto_update_exclude', '');
    $faq_schema_enabled         = get_option('hozio_faq_schema_enabled', '1');
    $faq_schema_general_enabled = get_option('hozio_faq_schema_general_enabled', '1');
    $faq_schema_service_enabled = get_option('hozio_faq_schema_service_enabled', '1');
    $faq_schema_town_enabled    = get_option('hozio_faq_schema_town_enabled', '1');
    $faq_schema_service_exclude    = get_option('hozio_faq_schema_service_exclude', '');
    $faq_schema_town_exclude          = get_option('hozio_faq_schema_town_exclude', '');
    // Auto-detect which page has the general_questions ACF fields populated
    $faq_detected_pages = get_posts([
        'post_type'      => 'any',
        'posts_per_page' => 3,
        'meta_query'     => [[
            'key'     => 'general_questions__question_1',
            'compare' => '!=',
            'value'   => '',
        ]],
        'fields' => 'ids',
    ]);
    $faq_detected_label = '';
    if (!empty($faq_detected_pages)) {
        $labels = [];
        foreach ($faq_detected_pages as $pid) {
            $labels[] = get_the_title($pid) . ' (ID: ' . $pid . ')';
        }
        $faq_detected_label = implode(', ', $labels);
    }
    $faq_acf_found = !empty($faq_detected_pages);

    // Get update timing info
    $last_update_check = function_exists('hozio_get_last_update_check') ? hozio_get_last_update_check() : 'Unknown';
    $next_update_check = function_exists('hozio_get_next_update_check') ? hozio_get_next_update_check() : 'Unknown';
    $auto_update_status = function_exists('hozio_get_auto_update_status') ? hozio_get_auto_update_status() : 'Unknown';

    // Get license status (if updater is loaded)
    $license_status = function_exists('hozio_get_license_status') ? hozio_get_license_status() : [
        'status' => 'unknown',
        'message' => 'Updater not loaded',
        'class' => 'hozio-license-empty'
    ];

    // Get log info
    $log_path = hozio_get_log_path();
    $log_size = hozio_get_log_size();
    $log_exists = file_exists($log_path);

    // Check if wp-config.php constant is set
    $constant_defined = defined('HOZIO_DEBUG');
    $constant_value = $constant_defined ? (HOZIO_DEBUG ? 'true' : 'false') : null;

    ?>
    <div class="wrap hozio-settings-wrap">
        <div class="hozio-header">
            <div class="hozio-logo"><?php echo file_get_contents(plugin_dir_path(HOZIO_PLUGIN_FILE) . 'assets/hozio-burst.svg'); ?></div>
            <div class="hozio-header-text">
                <h1>Plugin Settings</h1>
                <p class="hozio-subtitle">Configure plugin features and debugging options</p>
            </div>
        </div>

        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
        <div class="notice notice-success is-dismissible">
            <p>Settings saved successfully!</p>
        </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="hozio-tab-nav">
            <button type="button" class="hozio-tab-btn hozio-tab-active" data-target="hozio-tab-settings">
                <span class="dashicons dashicons-admin-settings"></span> Settings
            </button>
            <button type="button" class="hozio-tab-btn" data-target="hozio-tab-shortcodes">
                <span class="dashicons dashicons-shortcode"></span> Shortcode Reference
            </button>
        </div>

        <div id="hozio-tab-settings" class="hozio-tab-panel">
        <div class="hozio-settings-layout">
        <!-- ── LEFT: main settings form ─────────────────────────────── -->
        <div class="hozio-main-col">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="hozio_plugin_settings_save">
            <?php wp_nonce_field('hozio_plugin_settings_save', 'hozio_plugin_settings_nonce'); ?>

            <!-- License & Updates Section -->
            <div class="hozio-section hozio-license-section">
                <h2 class="hozio-section-title">License & Updates</h2>

                <?php
                $hub_connected = class_exists('Hozio_Hub_Client') && Hozio_Hub_Client::is_connected();
                $hub_url = get_option('hozio_hub_url', '');
                $hub_license = '';
                if ($hub_connected && class_exists('Hozio_Hub_Client')) {
                    $hub_license = Hozio_Hub_Client::get_license_status();
                }
                ?>

                <?php if ($hub_connected): ?>
                    <!-- Hub-managed license -->
                    <div class="hozio-hub-status-box" id="hozio-hub-status-box">
                        <div class="hozio-hub-status-header">
                            <span class="dashicons dashicons-yes-alt" style="color: #16a34a; font-size: 20px;" id="hozio-hub-status-icon"></span>
                            <strong id="hozio-hub-status-label">Connected to Hub</strong>
                        </div>
                        <table class="hozio-hub-status-table">
                            <tr><td>Hub URL:</td><td><?php echo esc_html($hub_url); ?></td></tr>
                            <tr>
                                <td>License Status:</td>
                                <td>
                                    <strong id="hozio-hub-license-display" style="color: <?php echo $hub_license === 'active' ? '#16a34a' : '#d63638'; ?>;">
                                        <?php echo esc_html(ucfirst($hub_license ?: 'unknown')); ?>
                                    </strong>
                                </td>
                            </tr>
                        </table>
                        <div id="hozio-check-connection-result" style="margin-top:8px;font-size:13px;display:none;"></div>
                        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="button" id="hozio-check-connection-btn"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce('hozio_check_connection_nonce')); ?>">
                                <span class="dashicons dashicons-update" style="margin-top:3px;"></span>
                                Check Connection
                            </button>
                            <button type="button" class="button" id="hozio-hub-disconnect-btn"
                                    data-nonce="<?php echo esc_attr(wp_create_nonce('hozio_hub_disconnect_nonce')); ?>"
                                    style="color:#d63638;border-color:#d63638;">
                                Disconnect
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Manual license key -->
                    <p class="hozio-section-description">Enter your license key or connect to the Hozio Hub to enable automatic updates.</p>
                    <div class="hozio-field">
                        <div class="hozio-license-wrapper">
                            <label for="hozio_license_key" class="hozio-license-label">License Key</label>
                            <div class="hozio-license-input-wrapper">
                                <?php if ($license_status['status'] === 'valid'): ?>
                                <input type="password" id="hozio_license_key" name="hozio_license_key"
                                       value="<?php echo esc_attr($license_key); ?>"
                                       class="regular-text hozio-license-input"
                                       placeholder="Enter your license key">
                                <?php else: ?>
                                <input type="text" id="hozio_license_key" name="hozio_license_key"
                                       value="<?php echo esc_attr($license_key); ?>"
                                       class="regular-text hozio-license-input"
                                       placeholder="Enter your license key">
                                <?php endif; ?>
                                <span class="hozio-license-status <?php echo esc_attr($license_status['class']); ?>">
                                    <?php if ($license_status['status'] === 'valid'): ?>
                                        <span class="dashicons dashicons-yes-alt"></span> Licensed
                                    <?php elseif ($license_status['status'] === 'invalid'): ?>
                                        <span class="dashicons dashicons-warning"></span> Invalid Key
                                    <?php else: ?>
                                        <span class="dashicons dashicons-minus"></span> Not Licensed
                                    <?php endif; ?>
                                </span>
                            </div>
                            <p class="hozio-field-description">
                                <?php echo esc_html($license_status['message']); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Hub Connect (inline) -->
                    <div class="hozio-hub-connect-inline">
                        <div class="hozio-hub-connect-divider"><span>or connect via Hub</span></div>
                        <div class="hozio-hub-connect-form">
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <input type="text" id="hozio_hub_reg_key_input" class="regular-text" placeholder="Paste registration key" style="min-width: 280px;">
                                <button type="button" class="button button-primary hozio-hub-connect-btn"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('hozio_hub_connect_nonce')); ?>">
                                    Connect to Hub
                                </button>
                            </div>
                            <span class="hozio-hub-connect-result" style="display: block; margin-top: 8px;"></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Auto-Updates Toggle -->
                <?php $version_locked = get_option('hozio_version_locked', '0') === '1'; ?>
                <div class="hozio-field" style="margin-top: 20px;">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch" <?php echo $version_locked ? 'style="opacity:0.45;cursor:not-allowed;"' : ''; ?>>
                            <input type="checkbox" name="hozio_auto_updates_enabled" value="1"
                                   <?php checked(!$version_locked && $auto_updates_enabled === '1', true); ?>
                                   <?php echo $version_locked ? 'disabled' : ''; ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">
                                Enable Automatic Updates
                                <?php if ($version_locked): ?>
                                    <span style="display:inline-block;background:#fef2f2;color:#dc2626;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:6px;text-transform:uppercase;letter-spacing:.5px;">Version Lock</span>
                                <?php endif; ?>
                            </div>
                            <div class="hozio-toggle-description">
                                <?php if ($version_locked): ?>
                                    Automatic updates are disabled because <strong>Version Lock</strong> is active on this site. To re-enable updates, turn off Version Lock from Hozio Hub.
                                <?php else: ?>
                                    When enabled, WordPress will automatically install plugin updates.
                                    <?php echo $hub_connected ? 'Managed via Hub.' : 'Requires a valid license key.'; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Update Info & Check Button -->
                <div class="hozio-update-info-box" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
                        <button type="button" class="button button-secondary hozio-check-updates-btn"
                                data-nonce="<?php echo wp_create_nonce('hozio_check_updates_nonce'); ?>"
                                <?php echo $version_locked ? 'disabled title="Version Lock is active — updates are blocked"' : ''; ?>>
                            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                            Check for Updates
                        </button>
                        <?php if ($version_locked): ?>
                            <span style="color:#dc2626;font-size:12px;">Version Lock is active — updates are blocked from Hozio Hub.</span>
                        <?php else: ?>
                        <span class="hozio-update-result"></span>
                        <?php endif; ?>
                    </div>
                    <table class="hozio-update-info-table" style="width: 100%; font-size: 13px;">
                        <tr>
                            <td style="padding: 5px 15px 5px 0; color: #666;">Last Checked:</td>
                            <td class="hozio-last-checked" style="padding: 5px 0;"><?php echo esc_html($last_update_check); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 15px 5px 0; color: #666;">Next Check:</td>
                            <td class="hozio-next-check" style="padding: 5px 0;"><?php echo esc_html($next_update_check); ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 15px 5px 0; color: #666;">Auto-Update Status:</td>
                            <td class="hozio-auto-update-status" style="padding: 5px 0;">
                                <?php echo esc_html($auto_update_status); ?>
                            </td>
                        </tr>
                    </table>
                </div>

            </div>

            <!-- Search Engine Visibility Section -->
            <div class="hozio-section">
                <h2 class="hozio-section-title">Search Engine Visibility</h2>
                <p class="hozio-section-description">
                    Watches whether this site can be crawled and indexed. On a <strong>staging</strong> site it warns when
                    search engines are still allowed in. On a <strong>live</strong> site it warns when the site has
                    accidentally been hidden from Google. The warning banner appears in the admin and on the front end.
                </p>

                <?php
                $sig = function_exists('hozio_sig_status') ? hozio_sig_status(true) : null;
                if ($sig) :
                    $sig_notice_key = 'hozio_sig_notice_' . get_current_user_id();
                    $sig_notice = get_transient($sig_notice_key);
                    if ($sig_notice) {
                        delete_transient($sig_notice_key);
                    }
                    $sig_remote = get_transient('hozio_sig_remote_result');

                    $sig_palette = array(
                        'green' => array('#116329', '#dafbe1', '#aceebb'),
                        'amber' => array('#7d4a00', '#fff4e0', '#f5d8a8'),
                        'red'   => array('#7d0d0a', '#ffe9e7', '#f5b8b3'),
                    );
                    $sig_c = isset($sig_palette[$sig['level']]) ? $sig_palette[$sig['level']] : $sig_palette['green'];
                ?>

                <?php if ($sig_notice && !empty($sig_notice['messages'])) : ?>
                    <div style="margin:0 0 16px;padding:12px 14px;border-radius:4px;border:1px solid <?php echo empty($sig_notice['failed']) ? '#aceebb' : '#f5b8b3'; ?>;background:<?php echo empty($sig_notice['failed']) ? '#dafbe1' : '#ffe9e7'; ?>;color:<?php echo empty($sig_notice['failed']) ? '#116329' : '#7d0d0a'; ?>;">
                        <strong><?php echo empty($sig_notice['failed']) ? 'Done:' : 'Could not finish:'; ?></strong>
                        <?php foreach ($sig_notice['messages'] as $sig_msg) : ?>
                            <div><?php echo esc_html($sig_msg); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="border:1px solid <?php echo esc_attr($sig_c[2]); ?>;background:<?php echo esc_attr($sig_c[1]); ?>;border-radius:5px;padding:14px 16px;margin-bottom:18px;">
                    <div style="font-weight:700;color:<?php echo esc_attr($sig_c[0]); ?>;margin-bottom:10px;font-size:14px;">
                        <?php
                        if ('green' === $sig['level']) {
                            echo 'staging' === $sig['environment']
                                ? 'Staging site is correctly blocked from search engines.'
                                : 'Live site is visible to search engines.';
                        } elseif ('production' === $sig['environment']) {
                            echo 'This LIVE site is hidden from search engines.';
                        } else {
                            echo 'red' === $sig['level']
                                ? 'This staging site can be indexed by Google.'
                                : 'This staging site can be crawled by Google.';
                        }
                        ?>
                    </div>

                    <table style="border-collapse:collapse;font-size:13px;color:#1f2328;">
                        <tr>
                            <td style="padding:3px 18px 3px 0;color:#57606a;">Environment</td>
                            <td style="padding:3px 0;font-weight:600;">
                                <?php echo 'staging' === $sig['environment'] ? 'Staging' : 'Production (live)'; ?>
                                <?php if ('auto' !== get_option('hozio_staging_guard_environment', 'auto')) : ?>
                                    <span style="color:#7d4a00;">(manually overridden)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:3px 18px 3px 0;color:#57606a;">Discourage search engines</td>
                            <td style="padding:3px 0;font-weight:600;"><?php echo $sig['discourage_on'] ? 'On (noindex tag is being sent)' : 'Off (pages are indexable)'; ?></td>
                        </tr>
                        <tr>
                            <td style="padding:3px 18px 3px 0;color:#57606a;">robots.txt</td>
                            <td style="padding:3px 0;font-weight:600;">
                                <?php
                                if ('live' === $sig['robots_source']) {
                                    echo 'Fetched from ' . esc_html(home_url('/robots.txt'));
                                } elseif ('file' === $sig['robots_source']) {
                                    echo 'File at ' . esc_html($sig['robots_path']);
                                } else {
                                    echo 'Not checked yet';
                                }
                                echo ' &mdash; ';
                                if (null === $sig['robots_blocks_all']) {
                                    echo '<span style="color:#7d4a00;">unknown</span>';
                                } elseif ($sig['robots_blocks_all']) {
                                    echo 'blocks all crawlers';
                                } else {
                                    echo '<span style="color:#7d0d0a;">does NOT block crawlers</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:3px 18px 3px 0;color:#57606a;vertical-align:top;">Looked for it in</td>
                            <td style="padding:3px 0;font-weight:400;font-size:12px;color:#57606a;">
                                <?php
                                $sig_roots = !empty($sig['root_candidates']) ? $sig['root_candidates'] : array();
                                echo $sig_roots ? esc_html(implode(', ', $sig_roots)) : 'no candidate web root found';
                                ?>
                            </td>
                        </tr>
                        <?php if (is_array($sig_remote) && !empty($sig_remote['ok'])) : ?>
                        <tr>
                            <td style="padding:3px 18px 3px 0;color:#57606a;">Live check</td>
                            <td style="padding:3px 0;font-weight:600;">
                                HTTP <?php echo (int) $sig_remote['status']; ?>,
                                <?php echo empty($sig_remote['blocks_all']) ? 'does NOT block crawlers' : 'blocks all crawlers'; ?>
                                <?php if (!empty($sig_remote['served_static'])) : ?>
                                    <span style="color:#57606a;font-weight:400;">(served as a real file by the web server, so WordPress cannot override it)</span>
                                <?php endif; ?>
                                <span style="color:#57606a;font-weight:400;">&mdash; <?php echo esc_html(human_time_diff($sig_remote['checked_at'])); ?> ago</span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <?php if (!empty($sig['issues'])) : ?>
                        <ul style="margin:12px 0 0;padding-left:18px;font-size:13px;color:#1f2328;">
                            <?php foreach ($sig['issues'] as $sig_issue) : ?>
                                <li style="margin-bottom:4px;"><?php echo esc_html($sig_issue); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <?php if ('staging' === $sig['environment'] && 'green' !== $sig['level']) : ?>
                            <a href="<?php echo esc_url(hozio_sig_fix_url('both')); ?>" class="button button-primary">Fix both now</a>
                            <?php if (!$sig['discourage_on']) : ?>
                                <a href="<?php echo esc_url(hozio_sig_fix_url('discourage')); ?>" class="button">Only discourage search engines</a>
                            <?php endif; ?>
                            <?php if (!$sig['robots_blocks_all'] && $sig['robots_managed']) : ?>
                                <a href="<?php echo esc_url(hozio_sig_fix_url('robots')); ?>" class="button">Only fix robots.txt</a>
                            <?php endif; ?>
                        <?php elseif ('production' === $sig['environment'] && 'green' !== $sig['level']) : ?>
                            <?php if ($sig['discourage_on']) : ?>
                                <a href="<?php echo esc_url(admin_url('options-reading.php')); ?>" class="button button-primary">Open Reading settings</a>
                            <?php endif; ?>
                            <?php if (!empty($sig['robots_is_ours']) && true === $sig['robots_blocks_all']) : ?>
                                <a href="<?php echo esc_url(hozio_sig_restore_url()); ?>" class="button button-primary">Restore previous robots.txt</a>
                            <?php endif; ?>
                            <span style="font-size:12px;color:#57606a;">Hozio Pro never changes search visibility automatically on a live site.</span>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(hozio_sig_recheck_url()); ?>" class="button">Re-check now</a>
                    </div>

                    <?php if ('staging' === $sig['environment'] && (!$sig['robots_managed'] || ($sig['robots_exists'] && !is_writable($sig['robots_path'])))) : ?>
                        <div style="margin-top:14px;">
                            <p style="font-size:13px;margin:0 0 6px;"><strong>robots.txt cannot be written automatically here.</strong> Paste this in by hand:</p>
                            <pre style="background:#fff;border:1px solid #d0d7de;border-radius:4px;padding:10px;margin:0;font-size:12px;">User-agent: *
Disallow: /</pre>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_staging_guard_enabled" value="1"
                                   <?php checked($staging_guard_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Staging Index Guard</div>
                            <div class="hozio-toggle-description">
                                Watches search-engine visibility and shows the warning banner. Turning this off disables
                                the banner, the daily check and the Fix buttons on this site.
                                <strong>Enabled by default.</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_staging_banner_public" value="1"
                                   <?php checked($staging_banner_public, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Show the banner to logged-out visitors</div>
                            <div class="hozio-toggle-description">
                                By default only logged-in administrators see the front-end banner, which keeps it out of
                                any cached page. Turn this on to show it to everyone who visits the staging site.
                                Never applies to a live site. <strong>Off by default.</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_staging_guard_all_red" value="1"
                                   <?php checked($staging_guard_all_red, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Treat every warning as critical</div>
                            <div class="hozio-toggle-description">
                                Normally a staging site that is protected by the noindex tag but still has a permissive
                                robots.txt shows an orange banner, and only a genuinely indexable site shows red.
                                Turn this on to make every warning red. <strong>Off by default.</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-field">
                    <label for="hozio_staging_guard_environment" style="display:block;font-weight:600;margin-bottom:6px;">Environment detection</label>
                    <select name="hozio_staging_guard_environment" id="hozio_staging_guard_environment">
                        <option value="auto" <?php selected($staging_guard_environment, 'auto'); ?>>Automatic (detect from the site URL)</option>
                        <option value="staging" <?php selected($staging_guard_environment, 'staging'); ?>>Force: this is a staging site</option>
                        <option value="production" <?php selected($staging_guard_environment, 'production'); ?>>Force: this is a live site</option>
                    </select>
                    <p class="description">
                        Automatic treats any site whose URL contains <code>mystagingwebsite.com</code> as staging. Only
                        override this for a staging site on a custom domain, or a live site that kept a staging URL.
                    </p>
                </div>

            </div>

            <!-- Dev URLs on a Live Site -->
            <div class="hozio-section">
                <h2 class="hozio-section-title">Dev URLs on a Live Site</h2>
                <p class="hozio-section-description">
                    After a site goes live it should not contain links pointing back at the dev or staging domain it
                    was built on. This finds them and can rewrite them to the live address. It does nothing at all on a
                    dev or staging site, where those links are correct.
                </p>

                <?php
                $sug_live   = function_exists('hozio_sug_is_live') ? hozio_sug_is_live() : false;
                $sug_report = function_exists('hozio_sug_report') ? hozio_sug_report() : array();
                $sug_rows   = isset($sug_report['total_rows']) ? (int) $sug_report['total_rows'] : 0;
                $sug_seen   = !empty($sug_report['scanned_at']);

                $sug_notice_key = 'hozio_sug_notice_' . get_current_user_id();
                $sug_notice = get_transient($sug_notice_key);
                if ($sug_notice) { delete_transient($sug_notice_key); }

                $sug_prev_key = 'hozio_sug_preview_' . get_current_user_id();
                $sug_preview = get_transient($sug_prev_key);
                if ($sug_preview) { delete_transient($sug_prev_key); }

                $sug_undo = get_option('hozio_sug_undo', array());
                ?>

                <?php if ($sug_notice && !empty($sug_notice['messages'])) : ?>
                    <div style="margin:0 0 16px;padding:12px 14px;border-radius:4px;border:1px solid <?php echo empty($sug_notice['failed']) ? '#aceebb' : '#f5b8b3'; ?>;background:<?php echo empty($sug_notice['failed']) ? '#dafbe1' : '#ffe9e7'; ?>;color:<?php echo empty($sug_notice['failed']) ? '#116329' : '#7d0d0a'; ?>;">
                        <?php foreach ($sug_notice['messages'] as $sug_msg) : ?>
                            <div><?php echo esc_html($sug_msg); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$sug_live) : ?>
                    <div style="border:1px solid #d0d7de;background:#f6f8fa;border-radius:5px;padding:14px 16px;margin-bottom:18px;font-size:13px;">
                        <strong>This is a dev or staging site.</strong> Dev URLs belong here, so this check is switched
                        off and the repair will refuse to run.
                    </div>
                <?php else : ?>

                    <div style="border:1px solid <?php echo $sug_rows > 0 ? '#f5b8b3' : ($sug_seen ? '#aceebb' : '#d0d7de'); ?>;background:<?php echo $sug_rows > 0 ? '#ffe9e7' : ($sug_seen ? '#dafbe1' : '#f6f8fa'); ?>;border-radius:5px;padding:14px 16px;margin-bottom:18px;">
                        <div style="font-weight:700;font-size:14px;margin-bottom:10px;color:<?php echo $sug_rows > 0 ? '#7d0d0a' : ($sug_seen ? '#116329' : '#57606a'); ?>;">
                            <?php
                            if (!$sug_seen) {
                                echo 'Not scanned yet.';
                            } elseif ($sug_rows > 0) {
                                echo esc_html($sug_rows) . ' database rows still point at a dev domain.';
                            } else {
                                echo 'No dev URLs found on this site.';
                            }
                            ?>
                        </div>

                        <table style="border-collapse:collapse;font-size:13px;color:#1f2328;">
                            <tr>
                                <td style="padding:3px 18px 3px 0;color:#57606a;">Live address</td>
                                <td style="padding:3px 0;font-weight:600;"><?php echo esc_html(home_url()); ?></td>
                            </tr>
                            <tr>
                                <td style="padding:3px 18px 3px 0;color:#57606a;">Links will be rewritten to</td>
                                <td style="padding:3px 0;font-weight:600;"><?php echo esc_html(hozio_sug_target_scheme() . '://' . hozio_sug_target_host()); ?></td>
                            </tr>
                            <?php if ($sug_seen) : ?>
                                <tr>
                                    <td style="padding:3px 18px 3px 0;color:#57606a;">Last scan</td>
                                    <td style="padding:3px 0;font-weight:600;"><?php echo esc_html(human_time_diff($sug_report['scanned_at'])); ?> ago</td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($sug_report['hosts'])) : ?>
                                <tr>
                                    <td style="padding:3px 18px 3px 0;color:#57606a;">Dev domains found</td>
                                    <td style="padding:3px 0;font-weight:600;"><?php echo esc_html(implode(', ', (array) $sug_report['hosts'])); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($sug_report['tables'])) : ?>
                                <tr>
                                    <td style="padding:3px 18px 3px 0;color:#57606a;vertical-align:top;">Where they are</td>
                                    <td style="padding:3px 0;font-weight:600;">
                                        <?php foreach ((array) $sug_report['tables'] as $sug_t => $sug_c) : ?>
                                            <div><?php echo esc_html($sug_t); ?>: <?php echo esc_html($sug_c); ?> rows</div>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>

                        <?php
                        $sug_leftovers = array();
                        if (!empty($sug_preview['stuck_eg'])) {
                            foreach ((array) $sug_preview['stuck_eg'] as $sug_x) {
                                $sug_x['why'] = 'mentions the dev domain but holds no rewritable URL';
                                $sug_leftovers[] = $sug_x;
                            }
                        }
                        if (!empty($sug_preview['unsafe_eg'])) {
                            foreach ((array) $sug_preview['unsafe_eg'] as $sug_x) {
                                $sug_x['why'] = 'stored data could not be safely rewritten';
                                $sug_leftovers[] = $sug_x;
                            }
                        }
                        ?>
                        <?php if (!empty($sug_leftovers)) : ?>
                            <div style="margin-top:14px;">
                                <div style="font-weight:700;font-size:13px;margin-bottom:6px;">
                                    Rows the repair will leave alone, and why. If a row is <em>supposed</em> to hold the old
                                    address &mdash; a log, a history, a record of where the site came from &mdash; ignore it and
                                    it stops being counted.
                                </div>
                                <?php foreach ($sug_leftovers as $sug_x) : ?>
                                    <div style="background:#fff;border:1px solid #f5d8a8;border-radius:4px;padding:8px;margin-bottom:6px;font-size:11px;font-family:Menlo,Consolas,monospace;word-break:break-all;">
                                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                                            <div style="color:#1f2328;font-weight:700;">
                                                <?php echo esc_html($sug_x['table']); ?> #<?php echo esc_html($sug_x['pk']); ?>
                                                <?php if (!empty($sug_x['label'])) : ?>
                                                    &mdash; <?php echo esc_html($sug_x['label']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?php echo esc_url(hozio_sug_ignore_url($sug_x['table'], $sug_x['pk'])); ?>"
                                               style="white-space:nowrap;font-family:inherit;"
                                               onclick="return confirm('Stop counting this row as a problem?');">Ignore this row</a>
                                        </div>
                                        <div style="color:#7d4a00;margin:2px 0;"><?php echo esc_html($sug_x['why']); ?></div>
                                        <div style="color:#57606a;"><?php echo esc_html($sug_x['text']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($sug_preview['samples'])) : ?>
                            <div style="margin-top:14px;">
                                <div style="font-weight:700;font-size:13px;margin-bottom:6px;">Preview &mdash; nothing has been changed:</div>
                                <?php foreach ($sug_preview['samples'] as $sug_s) : ?>
                                    <div style="background:#fff;border:1px solid #d0d7de;border-radius:4px;padding:8px;margin-bottom:6px;font-size:11px;font-family:Menlo,Consolas,monospace;word-break:break-all;">
                                        <div style="color:#57606a;"><?php echo esc_html($sug_s['table'] . ' &middot; ' . $sug_s['col']); ?></div>
                                        <div style="color:#7d0d0a;">- <?php echo esc_html($sug_s['before']); ?></div>
                                        <div style="color:#116329;">+ <?php echo esc_html($sug_s['after']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                            <a href="<?php echo esc_url(hozio_sug_url('scan')); ?>" class="button">Scan now</a>
                            <?php if ($sug_rows > 0) : ?>
                                <a href="<?php echo esc_url(hozio_sug_url('preview')); ?>" class="button">Preview changes</a>
                                <a href="<?php echo esc_url(hozio_sug_url('fix')); ?>" class="button button-primary"
                                   onclick="return confirm('This rewrites <?php echo esc_js($sug_rows); ?> database rows. It can be undone from this screen. Continue?');">
                                    Fix now
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($sug_undo['rows'])) : ?>
                                <a href="<?php echo esc_url(hozio_sug_url('undo')); ?>" class="button"
                                   onclick="return confirm('This puts the previous dev URLs back. Continue?');">
                                    Undo last fix (<?php echo esc_html(count($sug_undo['rows'])); ?> rows)
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php
                        $sug_ign = function_exists('hozio_sug_ignored') ? hozio_sug_ignored() : array();
                        $sug_ign_n = 0;
                        foreach ($sug_ign as $sug_ign_rows) { $sug_ign_n += count((array) $sug_ign_rows); }
                        ?>
                        <?php if ($sug_ign_n > 0) : ?>
                            <p style="font-size:12px;color:#57606a;margin:12px 0 0;">
                                <?php echo esc_html($sug_ign_n); ?> row<?php echo 1 === $sug_ign_n ? '' : 's'; ?> deliberately ignored.
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=hozio_sug_unignore'), 'hozio_sug_unignore', 'hozio_sug_nonce')); ?>">Stop ignoring them</a>
                            </p>
                        <?php endif; ?>

                        <p style="font-size:12px;color:#57606a;margin:12px 0 0;">
                            Scanning reads every row of several database tables, so it runs only when you press a button
                            or once a day in the background &mdash; never while somebody is loading a page.
                        </p>
                    </div>

                <?php endif; ?>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_sug_enabled" value="1"
                                   <?php checked($sug_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Dev URL Guard</div>
                            <div class="hozio-toggle-description">
                                Watches a live site for links back to the dev or staging domain it was built on, and
                                shows a warning banner in the admin and to logged-in administrators on the front end.
                                <strong>Enabled by default.</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-field">
                    <label for="hozio_sug_www_mode" style="display:block;font-weight:600;margin-bottom:6px;">Rewrite links to</label>
                    <select name="hozio_sug_www_mode" id="hozio_sug_www_mode">
                        <option value="home" <?php selected($sug_www_mode, 'home'); ?>>Match the site address (recommended)</option>
                        <option value="www" <?php selected($sug_www_mode, 'www'); ?>>Force www.</option>
                        <option value="nowww" <?php selected($sug_www_mode, 'nowww'); ?>>Force no www.</option>
                    </select>
                    <p class="description">
                        Recommended matches WordPress' own Site Address exactly, currently
                        <code><?php echo esc_html(hozio_sug_target_scheme() . '://' . (string) wp_parse_url(home_url(), PHP_URL_HOST)); ?></code>.
                        Choosing a different form only makes sense if the Site Address itself is wrong &mdash; otherwise
                        every internal link picks up a redirect, which is the problem you are trying to remove.
                    </p>
                </div>

            </div>

            <!-- Debug & Logging Section -->
            <div class="hozio-section">
                <h2 class="hozio-section-title">Debug & Logging</h2>

                <?php if ($constant_defined): ?>
                <div class="hozio-notice hozio-notice-info">
                    <strong>Note:</strong> HOZIO_DEBUG constant is defined in wp-config.php (value: <?php echo esc_html($constant_value); ?>).
                    This takes priority over the setting below.
                </div>
                <?php endif; ?>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_debug_enabled" value="1"
                                   <?php checked($debug_enabled, '1'); ?>
                                   <?php echo $constant_defined ? 'disabled' : ''; ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Enable Debug Logging</div>
                            <div class="hozio-toggle-description">
                                Write debug information to a log file. Useful for troubleshooting issues.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-log-info">
                    <div class="hozio-log-details">
                        <span class="hozio-log-label">Log File:</span>
                        <code><?php echo esc_html(basename($log_path)); ?></code>
                        <span class="hozio-log-size">(<?php echo esc_html($log_size); ?>)</span>
                    </div>
                    <div class="hozio-log-actions">
                        <button type="button" class="button button-primary hozio-test-log-btn"
                                data-nonce="<?php echo esc_attr(wp_create_nonce('hozio_test_log_nonce')); ?>">
                            Test Logging
                        </button>
                        <button type="button" class="button hozio-clear-log-btn"
                                data-nonce="<?php echo esc_attr(wp_create_nonce('hozio_clear_log_nonce')); ?>"
                                <?php echo !$log_exists ? 'disabled' : ''; ?>>
                            Clear Log
                        </button>
                        <?php if ($log_exists): ?>
                        <a href="<?php echo esc_url(content_url('hozio-debug.log')); ?>"
                           target="_blank" class="button">View Log</a>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="hozio-field-description" style="margin-top: 10px;">
                    Click "Test Logging" to write a test entry to the debug log. Make sure debug logging is enabled and saved first.
                </p>
            </div>

            <!-- Feature Toggles Section -->
            <div class="hozio-section">
                <h2 class="hozio-section-title">Feature Toggles</h2>
                <p class="hozio-section-description">Enable or disable specific plugin features. Disabling unused features can improve performance.</p>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_dom_parsing_enabled" value="1"
                                   <?php checked($dom_parsing_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">ACF Field Hiding (DOM Parsing)</div>
                            <div class="hozio-toggle-description">
                                Enable the <code>hide-if-empty-acf</code> and <code>hide-if-no-wiki</code> CSS class functionality.
                                Disable if your site doesn't use these features.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_service_menu_sync_enabled" value="1"
                                   <?php checked($service_menu_sync_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Service Menu Auto-Sync</div>
                            <div class="hozio-toggle-description">
                                Automatically add/remove service pages to navigation menus based on the
                                <code>service-pages-loop-item</code> taxonomy term.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_canonical_redirect_enabled" value="1"
                                   <?php checked($canonical_redirect_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">WordPress Canonical Redirects</div>
                            <div class="hozio-toggle-description">
                                WordPress automatically redirects mistyped or guessed URLs to the closest matching page.
                                Disable this if canonical redirects are causing unwanted redirects on your site.
                                <strong>Enabled by default.</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_block_wrong_urls_enabled" value="1"
                                   <?php checked($block_wrong_urls_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Block Pages at Wrong URLs</div>
                            <div class="hozio-toggle-description">
                                Blocks &ldquo;ghost&rdquo; URLs &mdash; child pages that WordPress will also serve at a
                                root-level URL (e.g. <code>/bathroom-remodeling/</code> serving
                                <code>/services/bathroom-remodeling/</code>). Registered rewrite endpoints
                                (WooCommerce My&nbsp;Account, order&#8209;received, and similar) are always allowed
                                through. Disable this if a legitimate URL on your site is being redirected or 404&rsquo;d.
                                <strong>Enabled by default.</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_auto_update_all_plugins" value="1"
                                   <?php checked($auto_update_all_plugins, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Auto-Update All Plugins</div>
                            <div class="hozio-toggle-description">
                                Lets WordPress automatically install updates for <strong>every</strong> plugin on this site,
                                not just Hozio Pro. WordPress already checks for plugin updates twice a day &mdash; this
                                grants permission to install what it finds, and on WordPress&nbsp;6.3+ it automatically
                                restores the previous version if an update causes a fatal error.
                                Premium plugins without an active license are skipped automatically (no update is offered),
                                so those still need updating by hand.
                                Every update is written to the audit log.
                                <strong>Enabled by default.</strong>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top:12px;padding:12px 16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;">
                        <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;color:#7c2d12;">
                            <input type="checkbox" name="hozio_auto_update_force" value="1"
                                   <?php checked($auto_update_force, '1'); ?> style="margin:2px 0 0;">
                            <span>
                                <strong>Override and force updates on.</strong>
                                Use this only if the message above says automatic updates are switched off.
                                It re-enables WordPress's updater even when another plugin has disabled it, and
                                restores the update schedule if it was removed.
                                <br>
                                <span style="color:#9a3412;">
                                    Leave this off if ManageWP, MainWP, or a similar tool is intentionally handling
                                    updates for you &mdash; forcing it on means two systems updating the same plugins.
                                    This cannot override a host-level lock (<code>DISALLOW_FILE_MODS</code>) or missing
                                    write access; those are reported separately and need fixing on the server.
                                </span>
                            </span>
                        </label>
                    </div>

                    <?php if ( function_exists( 'hozio_git_update_manager_active' ) && hozio_git_update_manager_active() ) : ?>
                        <div style="margin-top:10px;padding:10px 12px;border-left:3px solid #2563eb;background:#eff6ff;font-size:12px;color:#1e3a8a;line-height:1.6;">
                            <strong>Git Updater is running on this site.</strong>
                            Any plugin that declares a GitHub, GitLab, Bitbucket, Gitea or Gist URI is left to Git Updater
                            and will be skipped here &mdash; two updaters writing the same plugin folder is how you end up
                            with a half-installed plugin, and Git Updater is often tracking a branch rather than a release.
                            Every other plugin on the site is still auto-updated as normal. Skips are named in the audit log.
                            Git Updater is never deactivated automatically.
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:10px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <button type="button" id="hozio-run-updates-btn" class="button"
                                data-nonce="<?php echo esc_attr( wp_create_nonce('hozio_run_plugin_updates') ); ?>">
                            <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                            Run Plugin Updates Now
                        </button>
                        <span id="hozio-run-updates-status" style="font-size:12px;color:#6b7280;"></span>
                    </div>
                    <?php
                    $hozio_next_auto_run = wp_next_scheduled( 'wp_maybe_auto_update' );
                    if ( $hozio_next_auto_run ) {
                        $hozio_next_label = $hozio_next_auto_run <= time()
                            ? 'due now (runs on the next visit to the site)'
                            : 'in ' . human_time_diff( time(), $hozio_next_auto_run );
                    } else {
                        $hozio_next_label = 'not scheduled — WordPress will reschedule it automatically';
                    }
                    ?>
                    <p style="margin:8px 0 0;font-size:12px;color:#9ca3af;">
                        Updates install on their own twice a day &mdash; <strong>next automatic run: <?php echo esc_html( $hozio_next_label ); ?></strong>.
                        Installing or updating a plugin does not trigger a run; only the schedule does. This button runs
                        that same process immediately, which is useful for testing or when you don't want to wait.
                        The button installs <strong>one plugin per request</strong> and keeps going until the backlog is
                        clear, so keep this page open while it works. Doing them one at a time is deliberate: shared hosts
                        cap how long a single request may run, and a run killed halfway through can leave the site stuck on
                        the &ldquo;scheduled maintenance&rdquo; page. The twice-daily automatic run installs up to
                        <strong>3 per run</strong> and works through a backlog over a few days.
                    </p>
                </div>

                <div class="hozio-field" style="margin-top:-4px;">
                    <label for="hozio_auto_update_exclude" style="display:block;font-weight:600;font-size:13px;margin-bottom:6px;">
                        Never auto-update these plugins
                    </label>
                    <textarea name="hozio_auto_update_exclude" id="hozio_auto_update_exclude" rows="4"
                              class="hozio-input" style="width:100%;font-family:monospace;font-size:12px;"
                              placeholder="one per line, e.g.&#10;advanced-custom-fields-pro&#10;elementor-pro/elementor-pro.php"><?php echo esc_textarea($auto_update_exclude); ?></textarea>
                    <p style="margin:6px 0 0;font-size:12px;color:#9ca3af;">
                        One per line. Use either the folder name (<code>elementor-pro</code>) or the full plugin file
                        (<code>elementor-pro/elementor-pro.php</code>). Use this for premium or heavily customized
                        plugins you want to update manually. Hozio Pro is always excluded automatically.
                    </p>
                </div>

            </div>

            <!-- FAQ Schema Section -->
            <div class="hozio-section">
                <h2 class="hozio-section-title">FAQ Schema</h2>
                <p class="hozio-section-description">Output JSON-LD FAQPage schema to <code>&lt;head&gt;</code> using ACF fields. The FAQ page is auto-detected. Use the exclude fields to skip specific pages for service or town groups.</p>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_faq_schema_enabled" value="1"
                                   <?php checked($faq_schema_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">FAQ Schema Output</div>
                            <div class="hozio-toggle-description">
                                Master switch. Disabling this suppresses all FAQ schema output sitewide.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_faq_schema_general_enabled" value="1"
                                   <?php checked($faq_schema_general_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">FAQ Page Schema</div>
                            <div class="hozio-toggle-description">
                                Uses <code>general_questions__question/answer_N</code> ACF fields. Schema only outputs on pages where those fields are populated.
                                <?php if ($faq_acf_found): ?>
                                    <br><strong style="color:#16a34a;">&#10003; ACF detected: <?php echo esc_html($faq_detected_label); ?></strong>
                                <?php else: ?>
                                    <br><span style="color:#b91c1c;">&#10007; No ACF fields detected &mdash; schema will not output for this group.</span>
                                    <br><span style="color:#6b7280;font-size:12px;">If your FAQ page has JSON-LD in an Elementor HTML widget, Google can still see it in the page body. View source &rarr; Ctrl+F &ldquo;FAQPage&rdquo; to verify.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_faq_schema_service_enabled" value="1"
                                   <?php checked($faq_schema_service_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Service Page FAQ Schema</div>
                            <div class="hozio-toggle-description">
                                Outputs FAQ schema using <code>faq_question/answer_N</code> fields on any page where those fields are filled.
                            </div>
                        </div>
                    </div>
                    <div style="margin-top:10px;padding-left:62px;">
                        <label style="font-size:13px;color:#444;display:block;margin-bottom:4px;">Exclude Post IDs</label>
                        <input type="text" name="hozio_faq_schema_service_exclude"
                               value="<?php echo esc_attr($faq_schema_service_exclude); ?>"
                               placeholder="e.g. 45, 102, 387" style="width:260px;">
                        <p class="description" style="margin-top:4px;">Comma-separated post IDs to skip for this schema group.</p>
                    </div>
                </div>

                <div class="hozio-field">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_faq_schema_town_enabled" value="1"
                                   <?php checked($faq_schema_town_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Town Page FAQ Schema (HOG)</div>
                            <div class="hozio-toggle-description">
                                Outputs FAQ schema using <code>new_hog_faq_question/answer_N</code> fields on any page where those fields are filled.
                            </div>
                        </div>
                    </div>
                    <div style="margin-top:10px;padding-left:62px;">
                        <label style="font-size:13px;color:#444;display:block;margin-bottom:4px;">Exclude Post IDs</label>
                        <input type="text" name="hozio_faq_schema_town_exclude"
                               value="<?php echo esc_attr($faq_schema_town_exclude); ?>"
                               placeholder="e.g. 45, 102, 387" style="width:260px;">
                        <p class="description" style="margin-top:4px;">Comma-separated post IDs to skip for this schema group.</p>
                    </div>
                </div>

            </div>

            <!-- Cache Management Section -->
            <div class="hozio-section">
                <h2 class="hozio-section-title">Cache Management</h2>

                <div class="hozio-cache-actions">
                    <button type="button" class="button hozio-clear-caches-btn"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('hozio_clear_caches_nonce')); ?>">
                        Clear Plugin Caches
                    </button>
                    <p class="hozio-field-description">
                        Clears all transient caches created by this plugin. Use this after making taxonomy changes or if you're experiencing caching issues.
                    </p>
                </div>
            </div>

            <div class="hozio-submit-wrapper">
                <button type="submit" class="button button-primary button-large">Save Settings</button>
            </div>
        </form>
        </div><!-- /.hozio-main-col -->

        <!-- ── RIGHT: sidebar ───────────────────────────────────────── -->
        <div class="hozio-sidebar-col">

            <?php
            $history        = get_option('hozio_version_history', []);
            $prev_entry     = !empty($history) ? $history[0] : null;
            $quick_revert_raw = $prev_entry && !empty($prev_entry['previous']) ? $prev_entry['previous'] : '';
            $quick_revert     = ($quick_revert_raw !== $current_ver) ? $quick_revert_raw : '';
            $rollback_nonce = wp_create_nonce('hozio_rollback_nonce');
            $current_ver    = hozio_get_plugin_version();
            ?>

            <!-- Version Control Card -->
            <div class="hozio-sidebar-card hozio-rollback-card">

                <!-- Accordion header -->
                <button type="button" class="hozio-accordion-trigger" aria-expanded="true">
                    <div class="hozio-accordion-title">
                        <span class="dashicons dashicons-backup"></span>
                        <span>Version Control</span>
                    </div>
                    <div class="hozio-accordion-meta">
                        <span class="hozio-version-pill">v<?php echo esc_html($current_ver); ?></span>
                        <span class="dashicons dashicons-arrow-up-alt2 hozio-accordion-chevron"></span>
                    </div>
                </button>

                <div class="hozio-accordion-body">

                    <!-- Current version banner -->
                    <div class="hozio-current-version-banner">
                        <div class="hozio-cv-label">Currently Installed</div>
                        <div class="hozio-cv-version">v<?php echo esc_html($current_ver); ?></div>
                    </div>

                    <!-- Quick Revert -->
                    <?php if ($quick_revert): ?>
                    <div class="hozio-revert-strip">
                        <div class="hozio-revert-strip-label">
                            <span class="dashicons dashicons-undo"></span>
                            Previous version: <strong>v<?php echo esc_html($quick_revert); ?></strong>
                        </div>
                        <button type="button" class="hozio-revert-btn hozio-quick-revert-btn"
                                data-version="<?php echo esc_attr($quick_revert); ?>"
                                data-nonce="<?php echo esc_attr($rollback_nonce); ?>">
                            Revert Now
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Install History accordion (default closed) -->
                    <div class="hozio-history-block">
                        <div class="hozio-history-header">
                            <button type="button" class="hozio-history-toggle" aria-expanded="false">
                                <span class="dashicons dashicons-arrow-right-alt2 hozio-history-chevron"></span>
                                Install History
                                <?php
                                $unique_count = count(array_unique(array_column($history, 'version')));
                                if ($unique_count > 0): ?>
                                    <span class="hozio-history-count"><?php echo $unique_count; ?></span>
                                <?php endif; ?>
                            </button>
                            <?php if (!empty($history)): ?>
                            <button type="button" class="hozio-history-clear-btn"
                                    data-nonce="<?php echo esc_attr($rollback_nonce); ?>"
                                    title="Clear history">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <div class="hozio-history-body" style="display:none;">
                        <?php if (!empty($history)): ?>
                        <ul class="hozio-timeline">
                        <?php
                        $method_colors = [
                            'auto_update'  => '#0073aa',
                            'update'       => '#0073aa',
                            'rollback'     => '#7c3aed',
                            'hub_update'   => '#0073aa',
                            'hub_rollback' => '#7c3aed',
                            'hub_command'  => '#059669',
                        ];
                        $method_labels = [
                            'auto_update'  => 'Auto-update',
                            'update'       => 'Update',
                            'rollback'     => 'Rollback',
                            'hub_update'   => 'Update',
                            'hub_rollback' => 'Rollback',
                            'hub_command'  => 'Hub',
                        ];
                        $hub_methods = ['hub_update', 'hub_rollback', 'hub_command'];
                        $seen_versions = [];
                        foreach ($history as $i => $entry):
                            // Skip duplicate versions (same version recorded more than once)
                            if (in_array($entry['version'], $seen_versions, true)) continue;
                            $seen_versions[] = $entry['version'];
                            $is_current  = ($entry['version'] === $current_ver);
                            $dot_color   = $method_colors[$entry['method']] ?? '#6b7280';
                            $label       = $method_labels[$entry['method']] ?? ucfirst($entry['method']);
                            $is_hub      = in_array($entry['method'], $hub_methods, true);
                        ?>
                        <li class="hozio-timeline-item">
                            <span class="hozio-timeline-dot" style="background:<?php echo esc_attr($dot_color); ?>;"></span>
                            <div class="hozio-timeline-content">
                                <span class="hozio-timeline-ver">v<?php echo esc_html($entry['version']); ?></span>
                                <?php if ($is_current): ?>
                                    <span class="hozio-badge hozio-badge-current">current</span>
                                <?php endif; ?>
                                <span class="hozio-timeline-meta">
                                    <?php echo esc_html(date_i18n('M j', $entry['installed_at'])); ?>
                                    &middot; <span style="color:<?php echo esc_attr($dot_color); ?>;"><?php echo esc_html($label); ?></span>
                                    <?php if ($is_hub): ?>
                                        <span style="display:inline-block;margin-left:4px;padding:0 5px;background:#059669;color:#fff;border-radius:3px;font-size:10px;font-weight:600;vertical-align:middle;line-height:16px;">Hub</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p style="margin:8px 12px;font-size:12px;color:#9ca3af;">No history recorded yet.</p>
                        <?php endif; ?>
                        </div>
                    </div>

                    <!-- Browse All Releases -->
                    <?php $license_active = function_exists('hozio_is_license_active') && hozio_is_license_active(); ?>
                    <div class="hozio-browse-releases">
                        <button type="button" class="hozio-load-releases-btn hozio-browse-btn"
                                data-nonce="<?php echo esc_attr($rollback_nonce); ?>"
                                <?php echo !$license_active ? 'disabled title="A valid license is required to install versions"' : ''; ?>>
                            <span class="dashicons dashicons-download"></span>
                            Browse All Releases
                            <span class="hozio-releases-status"></span>
                        </button>
                        <?php if (!$license_active): ?>
                            <p style="margin:8px 0 0 0;font-size:12px;color:#d63638;">
                                <span class="dashicons dashicons-warning" style="font-size:13px;vertical-align:middle;"></span>
                                A valid active license is required to install or rollback versions. Check your license status above.
                            </p>
                        <?php endif; ?>

                        <div class="hozio-releases-list" style="display:none;margin-top:12px;">
                            <table class="widefat striped hozio-releases-table">
                                <thead>
                                    <tr>
                                        <th>Version</th>
                                        <th>Date</th>
                                        <th>Notes</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody class="hozio-releases-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Progress & result -->
                    <div class="hozio-rollback-progress" style="display:none;">
                        <span class="dashicons dashicons-update spin"></span>
                        <div>
                            <strong class="hozio-rollback-progress-title">Installing…</strong>
                            <p class="hozio-rollback-progress-msg">Do not close this page.</p>
                        </div>
                    </div>
                    <div class="hozio-rollback-result" style="display:none;"></div>

                </div><!-- /.hozio-accordion-body -->
            </div><!-- /.hozio-rollback-card -->

            <!-- System Info Card -->
            <div class="hozio-sidebar-card hozio-sysinfo-card">
                <button type="button" class="hozio-accordion-trigger" aria-expanded="true">
                    <div class="hozio-accordion-title">
                        <span class="dashicons dashicons-info-outline"></span>
                        <span>System Info</span>
                    </div>
                    <span class="dashicons dashicons-arrow-up-alt2 hozio-accordion-chevron"></span>
                </button>
                <div class="hozio-accordion-body">
                    <table class="hozio-sysinfo-table">
                        <tr><th>Plugin</th><td>v<?php echo esc_html($current_ver); ?></td></tr>
                        <tr><th>WordPress</th><td><?php echo esc_html(get_bloginfo('version')); ?></td></tr>
                        <tr><th>PHP</th><td><?php echo esc_html(PHP_VERSION); ?></td></tr>
                        <tr>
                            <th>License</th>
                            <td><span class="hozio-status hozio-status-<?php echo $license_status['status'] === 'valid' ? 'on' : 'off'; ?>"><?php echo esc_html($license_status['status'] === 'valid' ? 'Valid' : 'Invalid'); ?></span></td>
                        </tr>
                        <tr>
                            <th>Debug</th>
                            <td><span class="hozio-status hozio-status-<?php echo ($debug_enabled === '1' || ($constant_defined && HOZIO_DEBUG)) ? 'on' : 'off'; ?>"><?php echo ($debug_enabled === '1' || ($constant_defined && HOZIO_DEBUG)) ? 'On' : 'Off'; ?></span></td>
                        </tr>
                        <tr><th>Log File</th><td><code style="font-size:11px;"><?php echo esc_html(basename($log_path)); ?></code> <span style="color:#999;">(<?php echo esc_html($log_size); ?>)</span></td></tr>
                    </table>
                </div>
            </div>

        </div><!-- /.hozio-sidebar-col -->
        </div><!-- /.hozio-settings-layout -->
        </div><!-- /#hozio-tab-settings -->

        <div id="hozio-tab-shortcodes" class="hozio-tab-panel" style="display:none;">

            <!-- ── Sub-tab navigation ───────────────────────────────────── -->
            <div class="hozio-sc-subnav">
                <button type="button" class="hozio-sc-subbtn hozio-sc-subbtn-active" data-panel="hozio-sc-town">Town / HOG Page</button>
                <button type="button" class="hozio-sc-subbtn" data-panel="hozio-sc-service">Service Page</button>
                <button type="button" class="hozio-sc-subbtn" data-panel="hozio-sc-all">All ACF Groups</button>
                <button type="button" class="hozio-sc-subbtn" data-panel="hozio-sc-generic">Generic</button>
            </div>

            <!-- ══ PANEL: Town / HOG Page ═══════════════════════════════════ -->
            <div id="hozio-sc-town" class="hozio-sc-panel">
                <div class="hozio-sc-panel-header">
                    <span style="font-size:13px;color:#6b7280;">Click any shortcode to copy it.</span>
                    <button type="button" class="hozio-copy-all-btn button button-secondary" data-scope="#hozio-sc-town">
                        <span class="dashicons dashicons-clipboard"></span> Copy All
                    </button>
                </div>

                <details class="hozio-sc-accordion" open><summary>Hero</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['hog_hero_seo_heading','hog_hero_section_headline','hog_hero_body'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[hog_hero_image]</code></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>Outcomes</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['hog_outcomes_seo_heading','hog_outcomes_section_headline','hog_outcomes_body'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[hog_outcomes_image]</code></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>About Us</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['hog_about_us_seo_heading','hog_about_us_section_headline','hog_about_us_body'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[hog_about_us_image]</code></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>How It Works</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['hog_how_it_works_seo_heading','hog_how_it_works_section_headline','hog_how_it_works_body'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[hog_how_it_works_image]</code></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>Service-Related Information</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['hog_service-related_information_seo_heading','hog_service-related_information_section_headline','hog_service-related_information_body'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[hog_service_related_info_image]</code></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>FAQ Questions &amp; Answers</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php for ($i=1;$i<=6;$i++): ?><code class="hozio-sc-chip" title="Click to copy">[new_hog_faq_question_<?php echo $i; ?>]</code><code class="hozio-sc-chip" title="Click to copy">[new_hog_faq_answer_<?php echo $i; ?>]</code><?php endfor; ?></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>Links</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['hog_google_maps_link','hog_usps_link','hog_pharmacy_link','hog_weather_link','hog_county_and_state_wiki_link','hog_location'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>GMB Map</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-raw">raw/embed</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[hog_gmb_map]</code></div></div>
                </details>
            </div><!-- /#hozio-sc-town -->

            <!-- ══ PANEL: Service Page ════════════════════════════════════════ -->
            <div id="hozio-sc-service" class="hozio-sc-panel" style="display:none;">
                <div class="hozio-sc-panel-header">
                    <span style="font-size:13px;color:#6b7280;">Click any shortcode to copy it.</span>
                    <button type="button" class="hozio-copy-all-btn button button-secondary" data-scope="#hozio-sc-service">
                        <span class="dashicons dashicons-clipboard"></span> Copy All
                    </button>
                </div>

                <details class="hozio-sc-accordion" open><summary>Hero</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['hero__seo_heading','hero__section_headline','hero__body'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[hero__banner_image]</code></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>Trust Symbols</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['trust_symbols__section_headline','trust_symbols__title_1','trust_symbols__description_1','trust_symbols__title_2','trust_symbols__description_2','trust_symbols__title_3','trust_symbols__description_3','trust_symbols__title_4','trust_symbols__description_4'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>Service Intro</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['service_intro__seo_heading','service_intro__section_headline'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[service_intro__image]</code></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-raw">raw/embed</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[service_intro__body]</code></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>Benefits</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['benefits__seo_heading','benefits__section_headline','benefits__subheadline','benefits__bullet_text_1','benefits__bullet_text_2','benefits__bullet_text_3','benefits__bullet_text_4','benefits__bullet_text_5','benefits__bullet_text_6'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>Service-Related Info 1</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['service-related_information_1__seo_heading','service-related_information_1__section_headline'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[service-related_information_1__image]</code></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-raw">raw/embed</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[service-related_information_1__body]</code></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>Service-Related Info 2</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['service-related_information_2__seo_heading','service-related_information_2__section_headline'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[service-related_information_2__image]</code></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-raw">raw/embed</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[service-related_information_2__body]</code></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>FAQ Questions &amp; Answers</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php for ($i=1;$i<=6;$i++): ?><code class="hozio-sc-chip" title="Click to copy">[faq_question_<?php echo $i; ?>]</code><code class="hozio-sc-chip" title="Click to copy">[faq_answer_<?php echo $i; ?>]</code><?php endfor; ?></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>How It Works</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-text">text</span><div class="hozio-sc-grid"><?php foreach (['how_it_works__seo_heading','how_it_works__section_headline','how_it_works__icon_title_1','how_it_works__icon_description_1','how_it_works__icon_title_2','how_it_works__icon_description_2','how_it_works__icon_title_3','how_it_works__icon_description_3'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                </details>

                <details class="hozio-sc-accordion"><summary>Miscellaneous</summary>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-misc">misc</span><div class="hozio-sc-grid"><?php foreach (['hog_areas','faq_service_page_section','service_loop_item_description'] as $sc): ?><code class="hozio-sc-chip" title="Click to copy">[<?php echo esc_html($sc); ?>]</code><?php endforeach; ?></div></div>
                    <div class="hozio-sc-sub"><span class="hozio-sc-type-badge hozio-badge-img">image</span><div class="hozio-sc-grid"><code class="hozio-sc-chip" title="Click to copy">[service_loop_item_background]</code></div></div>
                </details>
            </div><!-- /#hozio-sc-service -->

            <!-- ══ PANEL: All ACF Groups (dynamic) ═══════════════════════════ -->
            <div id="hozio-sc-all" class="hozio-sc-panel" style="display:none;">
                <div class="hozio-sc-panel-header">
                    <span style="font-size:13px;color:#6b7280;">All ACF field groups detected on this site. Click any shortcode to copy.</span>
                    <button type="button" class="hozio-copy-all-btn button button-secondary" data-scope="#hozio-sc-all">
                        <span class="dashicons dashicons-clipboard"></span> Copy All
                    </button>
                </div>
                <?php
                if ( ! function_exists( 'acf_get_field_groups' ) ) {
                    echo '<p style="padding:16px;color:#6b7280;font-style:italic;">Advanced Custom Fields (ACF) is not active on this site, so there are no field groups to list.</p>';
                } else {
                    $hozio_all_acf_groups = acf_get_field_groups();
                    if ( empty( $hozio_all_acf_groups ) ) {
                        echo '<p style="padding:16px;color:#6b7280;font-style:italic;">No ACF field groups have been registered on this site yet.</p>';
                    } else {
                        // Sort by title for predictable ordering
                        usort( $hozio_all_acf_groups, function( $a, $b ) {
                            return strcasecmp( $a['title'] ?? '', $b['title'] ?? '' );
                        } );
                        foreach ( $hozio_all_acf_groups as $hozio_group ) {
                            $hozio_group_fields = function_exists( 'acf_get_fields' ) ? acf_get_fields( $hozio_group['key'] ) : array();
                            if ( empty( $hozio_group_fields ) ) continue;
                            $hozio_field_count  = count( $hozio_group_fields );
                            $hozio_group_status = ! empty( $hozio_group['active'] ) ? '' : ' <span style="font-size:10px;color:#dc2626;font-weight:600;">(inactive)</span>';
                            $hozio_group_dom_id = 'hozio-acf-acc-' . sanitize_html_class( $hozio_group['key'] ?? uniqid() );
                            ?>
                            <details class="hozio-sc-accordion" id="<?php echo esc_attr( $hozio_group_dom_id ); ?>">
                                <summary>
                                    <?php echo esc_html( $hozio_group['title'] ?? 'Untitled Group' ); ?>
                                    <span style="font-size:11px;color:#9ca3af;font-weight:400;margin-left:8px;">(<?php echo (int) $hozio_field_count; ?> field<?php echo $hozio_field_count === 1 ? '' : 's'; ?>)</span>
                                    <?php echo $hozio_group_status; ?>
                                </summary>
                                <div class="hozio-sc-group-toolbar">
                                    <button type="button" class="hozio-copy-all-btn button button-small" data-scope="#<?php echo esc_attr( $hozio_group_dom_id ); ?>">
                                        <span class="dashicons dashicons-clipboard"></span> Copy All in <?php echo esc_html( $hozio_group['title'] ?? 'Group' ); ?>
                                    </button>
                                </div>
                                <?php hozio_sc_render_acf_fields( $hozio_group_fields ); ?>
                            </details>
                            <?php
                        }
                    }
                }
                ?>
            </div><!-- /#hozio-sc-all -->

            <!-- ══ PANEL: Generic ════════════════════════════════════════════ -->
            <div id="hozio-sc-generic" class="hozio-sc-panel" style="display:none;">
                <div class="hozio-sc-panel-header">
                    <span style="font-size:13px;color:#6b7280;">Use these when your field isn't in the lists above.</span>
                    <button type="button" class="hozio-copy-all-btn button button-secondary" data-scope="#hozio-sc-generic">
                        <span class="dashicons dashicons-clipboard"></span> Copy All
                    </button>
                </div>
                <div class="hozio-sc-grid" style="padding:16px 0;">
                    <code class="hozio-sc-chip" title="Click to copy">[acf field="field_name"]</code>
                    <code class="hozio-sc-chip" title="Click to copy">[acf_text field="field_name"]</code>
                    <code class="hozio-sc-chip" title="Click to copy">[acf_img field="field_name" alt="description"]</code>
                    <code class="hozio-sc-chip" title="Click to copy">[acf_raw field="field_name"]</code>
                </div>
            </div><!-- /#hozio-sc-generic -->

        </div><!-- /#hozio-tab-shortcodes -->

    </div><!-- /.wrap -->

    <!-- Auto-update pause modal (shown after a successful downgrade) -->
    <div id="hozio-autoupdate-modal" style="display:none;position:fixed;inset:0;z-index:100001;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:440px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.22);">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                <span class="dashicons dashicons-shield" style="color:#059669;font-size:22px;"></span>
                <strong style="font-size:16px;">Rollback complete — auto-updates paused</strong>
            </div>
            <p style="margin:0 0 18px;color:#555;font-size:13px;">Auto-updates are paused to protect this rollback. How long would you like to keep them paused?</p>

            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;" id="hozio-pause-option-wrap">
                    <input type="radio" name="hozio_au_choice" value="pause" checked style="margin:0;">
                    <div>
                        <div style="display:flex;align-items:center;gap:6px;font-weight:600;font-size:13px;">
                            Keep paused for…
                            <span style="font-size:10px;font-weight:700;background:#d1fae5;color:#065f46;padding:1px 7px;border-radius:20px;letter-spacing:.3px;">Recommended</span>
                        </div>
                        <select id="hozio-pause-hours" style="margin-top:4px;font-size:12px;">
                            <option value="1" selected>1 hour</option>
                            <option value="6">6 hours</option>
                            <option value="24">24 hours</option>
                            <option value="168">1 week</option>
                        </select>
                        <div style="font-size:11px;color:#9ca3af;margin-top:2px;">Auto-updates resume automatically when the time expires.</div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 14px;border:2px solid #e5e7eb;border-radius:8px;">
                    <input type="radio" name="hozio_au_choice" value="keep" style="margin:0;">
                    <div>
                        <div style="font-weight:600;font-size:13px;">Resume auto-updates now</div>
                        <div style="font-size:11px;color:#9ca3af;">Cancels the pause immediately. Auto-updates will be active again.</div>
                    </div>
                </label>
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:10px 14px;border:2px solid #fecaca;border-radius:8px;background:#fff7f7;">
                    <input type="radio" name="hozio_au_choice" value="disable" style="margin:0;">
                    <div>
                        <div style="font-weight:600;font-size:13px;color:#b91c1c;">Turn off completely</div>
                        <div style="font-size:11px;color:#9ca3af;">Disables auto-updates until you manually re-enable them in Settings.</div>
                    </div>
                </label>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" id="hozio-autoupdate-confirm" style="background:#7c3aed;color:#fff;border:none;padding:9px 22px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">Confirm &amp; Continue</button>
            </div>
        </div>
    </div>

    <!-- Full-page rollback overlay -->
    <div id="hozio-rollback-overlay" style="display:none;">
        <div class="hozio-overlay-box">
            <span class="hozio-overlay-icon dashicons dashicons-update hozio-spinning"></span>
            <div class="hozio-overlay-title">Installing…</div>
            <div class="hozio-overlay-sub">Do not close this tab.</div>
        </div>
    </div>

    <style>
    /* ── Layout ─────────────────────────────────────────────────────── */
    .hozio-settings-wrap { max-width: none; }

    .hozio-settings-layout {
        display: grid;
        grid-template-columns: 55% 1fr;
        gap: 24px;
        align-items: start;
    }

    @keyframes hozio-spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }
    .hozio-spinning {
        display: inline-block;
        animation: hozio-spin 0.7s linear infinite;
    }

    .hozio-sidebar-col {
        position: sticky;
        top: 32px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* ── Sidebar cards ───────────────────────────────────────────────── */
    .hozio-sidebar-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,.08);
        overflow: hidden;
    }

    .hozio-accordion-trigger {
        width: 100%;
        background: none;
        border: none;
        cursor: pointer;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        font-size: 14px;
        font-weight: 600;
        color: #1f2937;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
        transition: background .15s;
    }

    .hozio-accordion-trigger:hover { background: #f9fafb; }

    .hozio-rollback-card .hozio-accordion-trigger {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        color: #fff;
        border-bottom-color: rgba(255,255,255,.15);
    }

    .hozio-rollback-card .hozio-accordion-trigger:hover { filter: brightness(1.06); }

    .hozio-accordion-title {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .hozio-accordion-meta {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .hozio-version-pill {
        background: rgba(255,255,255,.2);
        color: #fff;
        border-radius: 20px;
        padding: 2px 10px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .3px;
    }

    .hozio-accordion-chevron {
        transition: transform .2s;
        opacity: .7;
    }

    .hozio-accordion-trigger[aria-expanded="false"] .hozio-accordion-chevron {
        transform: rotate(180deg);
    }

    .hozio-accordion-body { padding: 0; }
    .hozio-accordion-body.is-collapsed { display: none; }

    /* ── Current version banner ─────────────────────────────────────── */
    .hozio-current-version-banner {
        padding: 18px;
        background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
        border-bottom: 1px solid #ddd6fe;
        text-align: center;
    }

    .hozio-cv-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: #7c3aed;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .hozio-cv-version {
        font-size: 28px;
        font-weight: 800;
        color: #4f46e5;
        line-height: 1;
    }

    /* ── Quick revert strip ─────────────────────────────────────────── */
    .hozio-revert-strip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 18px;
        background: #fffbeb;
        border-bottom: 1px solid #fde68a;
        font-size: 13px;
    }

    .hozio-revert-strip-label {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #92400e;
    }

    .hozio-revert-strip-label .dashicons { font-size: 16px; width: 16px; height: 16px; }

    .hozio-revert-btn {
        background: #7c3aed !important;
        border-color: #7c3aed !important;
        color: #fff !important;
        border-radius: 6px !important;
        padding: 4px 12px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        white-space: nowrap;
        cursor: pointer;
        transition: background .15s !important;
    }

    .hozio-revert-btn:hover { background: #6d28d9 !important; border-color: #6d28d9 !important; }
    .hozio-revert-btn:disabled { opacity: .6; cursor: not-allowed; }

    /* ── History accordion ──────────────────────────────────────────── */
    .hozio-history-block {
        border-bottom: 1px solid #f3f4f6;
    }

    .hozio-history-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 18px;
    }

    .hozio-history-toggle {
        display: flex;
        align-items: center;
        gap: 6px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: #6b7280;
        font-weight: 600;
        padding: 0;
    }

    .hozio-history-toggle:hover { color: #374151; }

    .hozio-history-chevron {
        font-size: 14px !important;
        width: 14px !important;
        height: 14px !important;
        transition: transform .2s;
    }

    .hozio-history-toggle[aria-expanded="true"] .hozio-history-chevron {
        transform: rotate(90deg);
    }

    .hozio-history-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #e5e7eb;
        color: #4b5563;
        font-size: 10px;
        font-weight: 700;
        border-radius: 10px;
        padding: 1px 6px;
        min-width: 18px;
    }

    .hozio-history-clear-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: #d1d5db;
        padding: 0;
        line-height: 1;
        transition: color .15s;
    }

    .hozio-history-clear-btn:hover { color: #ef4444; }
    .hozio-history-clear-btn .dashicons { font-size: 15px !important; width: 15px !important; height: 15px !important; }

    .hozio-history-body { padding: 0 18px 12px; }

    /* ── Rollback overlay ───────────────────────────────────────────── */
    #hozio-rollback-overlay {
        position: fixed;
        inset: 0;
        background: rgba(17,24,39,.75);
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .hozio-overlay-box {
        background: #fff;
        border-radius: 16px;
        padding: 40px 50px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0,0,0,.3);
        min-width: 280px;
    }

    .hozio-overlay-icon {
        font-size: 48px !important;
        width: 48px !important;
        height: 48px !important;
        color: #7c3aed;
        margin-bottom: 16px;
        display: block;
        margin-left: auto;
        margin-right: auto;
    }

    .hozio-overlay-icon.hozio-overlay-done {
        color: #16a34a;
        animation: none;
    }

    .hozio-overlay-title {
        font-size: 20px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 6px;
    }

    .hozio-overlay-sub {
        font-size: 13px;
        color: #6b7280;
    }

    /* ── Timeline ───────────────────────────────────────────────────── */

    .hozio-timeline {
        list-style: none;
        margin: 0;
        padding: 0;
        position: relative;
    }

    .hozio-timeline::before {
        content: '';
        position: absolute;
        left: 6px;
        top: 6px;
        bottom: 6px;
        width: 2px;
        background: #e5e7eb;
    }

    .hozio-timeline-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 5px 0;
        position: relative;
    }

    .hozio-timeline-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 2px;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px rgba(0,0,0,.1);
        position: relative;
        z-index: 1;
    }

    .hozio-timeline-content { flex: 1; min-width: 0; }

    .hozio-timeline-ver {
        font-weight: 700;
        font-size: 13px;
        color: #111827;
        margin-right: 4px;
    }

    .hozio-timeline-meta {
        display: block;
        font-size: 11px;
        color: #9ca3af;
        margin-top: 1px;
    }

    /* ── Browse releases ────────────────────────────────────────────── */
    .hozio-browse-releases { padding: 14px 18px; }

    .hozio-browse-btn {
        width: 100%;
        display: flex !important;
        align-items: center;
        gap: 6px;
        justify-content: center;
        border: 2px dashed #d1d5db !important;
        border-radius: 8px !important;
        background: #f9fafb !important;
        color: #374151 !important;
        padding: 8px !important;
        font-size: 13px !important;
        cursor: pointer;
        transition: all .15s !important;
    }

    .hozio-browse-btn:hover {
        border-color: #7c3aed !important;
        color: #7c3aed !important;
        background: #faf5ff !important;
    }

    .hozio-releases-status { font-size: 11px; color: #9ca3af; margin-left: 4px; }

    .hozio-releases-table { font-size: 12px !important; }
    .hozio-releases-table th,
    .hozio-releases-table td { padding: 6px 8px !important; }

    /* ── Progress / result ──────────────────────────────────────────── */
    .hozio-rollback-progress {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        background: #fffbeb;
        border-top: 1px solid #fde68a;
        font-size: 13px;
    }

    .hozio-rollback-progress .dashicons { color: #d97706; font-size: 20px; }

    .hozio-rollback-progress p { margin: 2px 0 0; font-size: 12px; color: #92400e; }

    .hozio-rollback-result { padding: 12px 18px; font-size: 13px; }

    /* ── System info card ───────────────────────────────────────────── */
    .hozio-sysinfo-card .hozio-accordion-trigger {
        background: #f8fafc;
        color: #374151;
    }

    .hozio-sysinfo-table {
        width: 100%;
        font-size: 13px;
        border-collapse: collapse;
        margin: 0;
        padding: 8px 0;
    }

    .hozio-sysinfo-table tr { border: none; }

    .hozio-sysinfo-table th,
    .hozio-sysinfo-table td {
        padding: 7px 18px;
        border: none;
        vertical-align: middle;
    }

    .hozio-sysinfo-table th {
        width: 90px;
        color: #6b7280;
        font-weight: 500;
        font-size: 12px;
    }

    .hozio-sysinfo-table tr:nth-child(even) { background: #f9fafb; }

    .hozio-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
        padding: 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .hozio-header .hozio-logo {
        width: 52px;
        height: 52px;
        flex-shrink: 0;
    }

    .hozio-header .hozio-logo svg {
        width: 52px;
        height: 52px;
        display: block;
    }

    .hozio-logo .swoosh-blue,
    .hozio-logo .swoosh-green,
    .hozio-logo .swoosh-orange {
        transform-box: fill-box;
        transform-origin: center;
    }

    .hozio-logo .swoosh-blue   { animation: hozio-swoosh-in 0.65s cubic-bezier(0.34,1.56,0.64,1) 0.05s both; }
    .hozio-logo .swoosh-green  { animation: hozio-swoosh-in 0.65s cubic-bezier(0.34,1.56,0.64,1) 0.20s both; }
    .hozio-logo .swoosh-orange { animation: hozio-swoosh-in 0.65s cubic-bezier(0.34,1.56,0.64,1) 0.35s both; }

    @keyframes hozio-swoosh-in {
        0%   { opacity: 0; transform: scale(0.25) rotate(-25deg); }
        100% { opacity: 1; transform: scale(1)    rotate(0deg); }
    }

    .hozio-header h1 {
        margin: 0;
        padding: 0;
        font-size: 24px;
    }

    .hozio-subtitle {
        margin: 5px 0 0;
        color: #666;
    }

    .hozio-section {
        background: white;
        border-radius: 12px;
        padding: 25px 30px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border: 1px solid #e5e7eb;
        border-left: 4px solid #0073aa;
    }

    .hozio-section-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0 0 8px;
        padding: 0;
    }

    .hozio-section-description {
        color: #666;
        margin: 0 0 20px;
    }

    .hozio-field {
        margin-bottom: 20px;
    }

    .hozio-field:last-child {
        margin-bottom: 0;
    }

    .hozio-toggle-wrapper {
        display: flex;
        align-items: flex-start;
        gap: 15px;
    }

    .hozio-toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
        flex-shrink: 0;
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
        transition: .3s;
        border-radius: 26px;
    }

    .hozio-toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }

    input:checked + .hozio-toggle-slider {
        background-color: #0073aa;
    }

    input:checked + .hozio-toggle-slider:before {
        transform: translateX(24px);
    }

    input:disabled + .hozio-toggle-slider {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .hozio-toggle-label {
        flex: 1;
    }

    .hozio-toggle-title {
        font-weight: 600;
        margin-bottom: 4px;
    }

    .hozio-toggle-description {
        color: #666;
        font-size: 13px;
        line-height: 1.5;
    }

    .hozio-toggle-description code {
        background: #f0f0f1;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 12px;
    }

    .hozio-notice {
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 20px;
    }

    .hozio-notice-info {
        background: #e7f3ff;
        border: 1px solid #72aee6;
        color: #1d4ed8;
    }

    .hozio-log-info {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-top: 15px;
    }

    .hozio-log-details {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .hozio-log-label {
        font-weight: 500;
    }

    .hozio-log-size {
        color: #666;
    }

    .hozio-log-actions {
        display: flex;
        gap: 10px;
    }

    .hozio-cache-actions {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .hozio-cache-actions .hozio-field-description {
        margin: 0;
        color: #666;
        font-size: 13px;
    }

    .hozio-system-info {
        width: 100%;
        border-collapse: collapse;
    }

    .hozio-system-info th,
    .hozio-system-info td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    .hozio-system-info th {
        width: 200px;
        font-weight: 500;
        color: #374151;
    }

    .hozio-system-info td code {
        background: #f0f0f1;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }

    .hozio-system-info tr:last-child th,
    .hozio-system-info tr:last-child td {
        border-bottom: none;
    }

    .hozio-status {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .hozio-status-on {
        background: #d1fae5;
        color: #065f46;
    }

    .hozio-status-off {
        background: #fee2e2;
        color: #991b1b;
    }

    .hozio-submit-wrapper {
        margin-top: 20px;
    }

    .hozio-submit-wrapper .button-primary {
        padding: 8px 24px;
        height: auto;
    }

    /* License Key Styles */
    .hozio-license-section {
        border-left-color: #2271b1;
    }

    .hozio-license-wrapper {
        max-width: 500px;
    }

    .hozio-license-label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .hozio-license-input-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .hozio-license-input {
        flex: 1;
        min-width: 250px;
        padding: 8px 12px !important;
        font-size: 14px !important;
    }

    .hozio-license-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
    }

    .hozio-license-valid {
        background: #d1fae5;
        color: #065f46;
    }

    .hozio-license-invalid {
        background: #fee2e2;
        color: #991b1b;
    }

    .hozio-license-empty {
        background: #f3f4f6;
        color: #6b7280;
    }

    .hozio-license-status .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
    }

    .hozio-field-description {
        color: #666;
        font-size: 13px;
        margin-top: 8px;
    }

    /* Spinning animation for update check */
    @keyframes hozio-spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .hozio-check-updates-btn .dashicons.spin {
        animation: hozio-spin 1s linear infinite;
    }

    .hozio-update-result {
        display: inline-block;
        vertical-align: middle;
    }

    .hozio-update-result .dashicons {
        font-size: 16px;
        width: 16px;
        height: 16px;
        vertical-align: text-bottom;
    }

    /* Hub status box (connected state) */
    .hozio-hub-status-box {
        padding: 15px;
        background: #f0fdf4;
        border: 1px solid #86efac;
        border-radius: 8px;
        margin-bottom: 5px;
    }

    .hozio-hub-status-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .hozio-hub-status-table {
        font-size: 13px;
    }

    .hozio-hub-status-table td {
        padding: 3px 15px 3px 0;
    }

    .hozio-hub-status-table td:first-child {
        color: #666;
    }

    .hozio-badge {
        display: inline-block;
        padding: 1px 7px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        vertical-align: middle;
        margin-left: 4px;
    }

    .hozio-badge-current { background: #d1fae5; color: #065f46; }

    /* Hub connect divider */
    .hozio-hub-connect-inline {
        margin-top: 20px;
    }

    .hozio-hub-connect-divider {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        color: #999;
        font-size: 13px;
    }

    .hozio-hub-connect-divider::before,
    .hozio-hub-connect-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e5e7eb;
    }

    .hozio-hub-connect-divider span {
        padding: 0 12px;
    }

    /* ── Tab Navigation ─────────────────────────────────────────────── */
    .hozio-tab-nav {
        display: flex;
        gap: 4px;
        margin-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 0;
    }
    .hozio-tab-btn {
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        padding: 10px 18px;
        font-size: 14px;
        font-weight: 500;
        color: #6b7280;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        border-radius: 6px 6px 0 0;
        transition: color 0.15s, border-color 0.15s;
    }
    .hozio-tab-btn:hover { color: #374151; }
    .hozio-tab-btn.hozio-tab-active {
        color: #7c3aed;
        border-bottom-color: #7c3aed;
        background: #fff;
    }
    .hozio-tab-btn .dashicons { font-size: 16px; width: 16px; height: 16px; margin-top: 1px; }

    /* ── Shortcode Reference Tab ────────────────────────────────────── */
    .hozio-sc-page {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .hozio-sc-group {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px 24px;
    }
    .hozio-sc-group-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    .hozio-sc-group-title {
        font-size: 16px;
        font-weight: 700;
        color: #111827;
        margin: 0;
        padding: 0;
        border: none;
    }
    .hozio-sc-group-desc {
        font-size: 13px;
        color: #6b7280;
        margin: -8px 0 16px;
    }
    .hozio-sc-sub { margin-bottom: 14px; }
    .hozio-sc-sub:last-child { margin-bottom: 0; }
    .hozio-sc-type-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 2px 8px;
        border-radius: 4px;
        margin-bottom: 8px;
    }
    .hozio-badge-text  { background: #eff6ff; color: #1d4ed8; }
    .hozio-badge-img   { background: #f0fdf4; color: #166534; }
    .hozio-badge-raw   { background: #fff7ed; color: #c2410c; }
    .hozio-badge-misc  { background: #f5f3ff; color: #6d28d9; }
    .hozio-sc-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .hozio-sc-chip {
        display: inline-block;
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 12px;
        font-family: 'Courier New', Courier, monospace;
        color: #374151;
        cursor: pointer;
        transition: background 0.1s, border-color 0.1s, color 0.1s;
        user-select: none;
    }
    .hozio-sc-chip:hover {
        background: #e0e7ff;
        border-color: #a5b4fc;
        color: #3730a3;
    }
    .hozio-sc-chip.hozio-copied {
        background: #d1fae5;
        border-color: #6ee7b7;
        color: #065f46;
    }
    .hozio-copy-all-btn .dashicons { margin-top: 3px; }

    /* ── Shortcode sub-tab navigation ─────────────────────────────── */
    .hozio-sc-subnav {
        display: flex;
        gap: 4px;
        margin-bottom: 16px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 0;
    }
    .hozio-sc-subbtn {
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 500;
        color: #6b7280;
        cursor: pointer;
        border-radius: 6px 6px 0 0;
        transition: color 0.15s, border-color 0.15s;
    }
    .hozio-sc-subbtn:hover { color: #374151; }
    .hozio-sc-subbtn-active {
        color: #7c3aed;
        border-bottom-color: #7c3aed;
        background: #faf5ff;
    }
    .hozio-sc-panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .hozio-sc-accordion {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-bottom: 8px;
        overflow: hidden;
    }
    .hozio-sc-group-toolbar {
        display: flex;
        justify-content: flex-end;
        padding: 10px 14px 6px;
        margin-bottom: -4px;
    }
    .hozio-sc-group-toolbar .hozio-copy-all-btn {
        font-size: 11px;
        height: auto;
        line-height: 1.5;
        padding: 4px 10px;
    }
    .hozio-sc-group-toolbar .hozio-copy-all-btn .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
        margin-top: 1px;
    }
    .hozio-sc-accordion summary {
        padding: 10px 14px;
        font-weight: 600;
        font-size: 13px;
        color: #374151;
        cursor: pointer;
        background: #f9fafb;
        user-select: none;
        list-style: none;
    }
    .hozio-sc-accordion summary::-webkit-details-marker { display: none; }
    .hozio-sc-accordion summary::before {
        content: '▶';
        display: inline-block;
        font-size: 10px;
        margin-right: 8px;
        color: #9ca3af;
        transition: transform 0.2s;
    }
    .hozio-sc-accordion[open] > summary::before { transform: rotate(90deg); }
    .hozio-sc-accordion[open] > summary { border-bottom: 1px solid #e5e7eb; }
    .hozio-sc-accordion .hozio-sc-sub { padding: 12px 14px 0; }
    .hozio-sc-accordion .hozio-sc-sub:last-child { padding-bottom: 12px; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Test debug logging
        $('.hozio-test-log-btn').on('click', function() {
            var $btn = $(this);
            var nonce = $btn.data('nonce');
            var originalText = $btn.text();

            $btn.prop('disabled', true).text('Testing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hozio_test_debug_log',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Success!').addClass('button-success');
                        $('.hozio-log-size').text('(' + response.data.log_size + ')');
                        // Enable the Clear Log and View Log buttons since log now exists
                        $('.hozio-clear-log-btn').prop('disabled', false);
                        // Show alert with success message
                        alert(response.data.message + '\n\nLog file: ' + response.data.log_path);
                        setTimeout(function() {
                            $btn.text(originalText).prop('disabled', false).removeClass('button-success');
                        }, 2000);
                    } else {
                        alert('Test failed: ' + response.data);
                        $btn.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred while testing the log');
                    $btn.text(originalText).prop('disabled', false);
                }
            });
        });

        // Clear debug log
        $('.hozio-clear-log-btn').on('click', function() {
            var $btn = $(this);
            var nonce = $btn.data('nonce');

            $btn.prop('disabled', true).text('Clearing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hozio_clear_debug_log',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Cleared!');
                        $('.hozio-log-size').text('(0 bytes)');
                        setTimeout(function() {
                            $btn.text('Clear Log').prop('disabled', false);
                        }, 2000);
                    } else {
                        alert('Failed to clear log: ' + response.data);
                        $btn.text('Clear Log').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred while clearing the log');
                    $btn.text('Clear Log').prop('disabled', false);
                }
            });
        });

        // Clear plugin caches
        $('.hozio-clear-caches-btn').on('click', function() {
            var $btn = $(this);
            var nonce = $btn.data('nonce');

            $btn.prop('disabled', true).text('Clearing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hozio_clear_plugin_caches',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Caches Cleared!');
                        setTimeout(function() {
                            $btn.text('Clear Plugin Caches').prop('disabled', false);
                        }, 2000);
                    } else {
                        alert('Failed to clear caches: ' + response.data);
                        $btn.text('Clear Plugin Caches').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred while clearing caches');
                    $btn.text('Clear Plugin Caches').prop('disabled', false);
                }
            });
        });

        // Run plugin auto-updates now (same process the twice-daily cron runs).
        //
        // The server installs ONE plugin per request and reports how many are left, so
        // this loops until it's done. Shared hosts cap how long a PHP request may run and
        // ignore set_time_limit(), so asking for a whole backlog in one request is what
        // gets the process killed mid-install — which strands the site in maintenance mode.
        $('#hozio-run-updates-btn').on('click', function() {
            var $btn    = $(this);
            var $status = $('#hozio-run-updates-status');
            var label   = $btn.html();

            var done  = [];   // Plugins installed across the whole loop.
            var round = 0;    // Backstop so a mis-reported "remaining" can't spin forever.

            $btn.prop('disabled', true).text('Updating…');

            function finish(color, message) {
                $status.css('color', color).text(message);
                $btn.prop('disabled', false).html(label);
            }

            function progress(extra) {
                $status.css('color', '#6b7280').text(
                    (done.length ? done.length + ' installed so far. ' : '') +
                    (extra || '') + ' Do not leave the page.'
                );
            }

            function runOne() {
                round++;
                if (round > 60) {
                    finish('#15803d', done.join(' · ') + ' — stopped after 60 rounds. Click again to continue.');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    timeout: 180000,
                    data: {
                        action: 'hozio_run_plugin_updates',
                        nonce: $btn.data('nonce')
                    },
                    success: function(response) {
                        if (!response.success) {
                            finish('#dc2626', response.data || 'Update run failed.');
                            return;
                        }

                        var d = response.data || {};
                        if (d.summary) { done.push(d.summary); }

                        // Only keep going while we're actually making progress. A plugin
                        // that fails stays in the pending list, so looping on "remaining"
                        // alone would retry the same failure until the round cap.
                        if (d.installed > 0 && d.remaining > 0) {
                            progress(d.remaining + ' left.');
                            runOne();
                            return;
                        }

                        finish(d.failed > 0 ? '#dc2626' : '#15803d', done.join(' · '));
                    },
                    error: function() {
                        finish('#dc2626',
                            (done.length ? done.join(' · ') + ' — then: ' : '') +
                            'a request failed or timed out. Check the audit log, then click again to carry on.');
                    }
                });
            }

            progress('Installing one plugin at a time.');
            runOne();
        });

        // Hub Connect
        $('.hozio-hub-connect-btn').on('click', function() {
            var $btn = $(this);
            var $result = $('.hozio-hub-connect-result');
            var regKey = $('#hozio_hub_reg_key_input').val();

            if (!regKey) {
                $result.html('<span style="color: #d63638;">Please enter a registration key.</span>');
                return;
            }

            $btn.prop('disabled', true).text('Connecting...');
            $result.html('<span style="color: #666;">Registering with Hub...</span>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hozio_hub_connect',
                    nonce: $btn.data('nonce'),
                    registration_key: regKey
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: #00a32a;"><span class="dashicons dashicons-yes"></span> ' + response.data.message + '</span>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $btn.prop('disabled', false).text('Connect to Hub');
                        $result.html('<span style="color: #d63638;">' + response.data + '</span>');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Connect to Hub');
                    $result.html('<span style="color: #d63638;">Network error. Check the Hub URL.</span>');
                }
            });
        });

        // Hub Check Connection
        $('#hozio-check-connection-btn').on('click', function() {
            var $btn    = $(this);
            var $result = $('#hozio-check-connection-result');
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top:3px;"></span> Checking…');
            $result.hide();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'hozio_check_hub_connection', nonce: $btn.data('nonce') },
                success: function(response) {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top:3px;"></span> Check Connection');
                    if (response.success) {
                        var status = response.data.license_status || 'active';
                        var color  = status === 'active' ? '#16a34a' : '#d63638';
                        $('#hozio-hub-status-icon').removeClass().addClass('dashicons dashicons-yes-alt').css('color', '#16a34a');
                        $('#hozio-hub-status-label').text('Connected to Hub');
                        $('#hozio-hub-license-display').css('color', color).text(status.charAt(0).toUpperCase() + status.slice(1));
                        if (response.data.note === 'rate_limited') {
                            $result.html('<span style="color:#996800;">&#9432; Connection is active — Hub is temporarily rate-limiting heartbeat requests. This is normal and will resolve automatically.</span>').show();
                        } else {
                            $result.html('<span style="color:#16a34a;">&#10003; Connection verified.</span>').show();
                        }
                    } else {
                        var isAuth = response.data && response.data.type === 'auth';
                        $('#hozio-hub-status-icon').removeClass().addClass('dashicons dashicons-warning').css('color', '#d63638');
                        $('#hozio-hub-status-label').text('Connection Lost');
                        var msg = (response.data && response.data.message) ? response.data.message : 'Connection check failed.';
                        $result.html('<span style="color:#d63638;">&#9888; ' + msg + '</span>' +
                            (isAuth ? '<br><small>Click <strong>Disconnect</strong> below, then re-register with a new key from the Hub.</small>' : '')).show();
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="margin-top:3px;"></span> Check Connection');
                    $result.html('<span style="color:#d63638;">Network error. Please try again.</span>').show();
                }
            });
        });

        // Hub Disconnect (by ID for the connected-state button)
        $('#hozio-hub-disconnect-btn').on('click', function() {
            if (!confirm('Disconnect from Hub? The site will no longer receive commands or license updates from the Hub.')) return;
            var $btn = $(this);
            $btn.prop('disabled', true).text('Disconnecting…');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'hozio_hub_disconnect', nonce: $btn.data('nonce') },
                success: function(response) {
                    if (response.success) { location.reload(); }
                    else { alert('Error: ' + response.data); $btn.prop('disabled', false).text('Disconnect'); }
                },
                error: function() { alert('Network error'); $btn.prop('disabled', false).text('Disconnect'); }
            });
        });

        // ── Accordion ────────────────────────────────────────────────
        $('.hozio-accordion-trigger').on('click', function() {
            var $btn  = $(this);
            var $body = $btn.closest('.hozio-sidebar-card').find('.hozio-accordion-body');
            var open  = $btn.attr('aria-expanded') === 'true';
            $btn.attr('aria-expanded', open ? 'false' : 'true');
            $body.toggleClass('is-collapsed', open);
        });

        // ── Rollback ─────────────────────────────────────────────────

        function hozioFormatDate(iso) {
            if (!iso) return '—';
            var d = new Date(iso);
            return d.toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
        }

        function hozioRollbackStart(version) {
            $('#hozio-rollback-overlay')
                .find('.hozio-overlay-icon').removeClass('hozio-overlay-done dashicons-yes-alt').addClass('hozio-spinning dashicons-update').end()
                .find('.hozio-overlay-title').text('Installing v' + version + '…').end()
                .find('.hozio-overlay-sub').text('Do not close this tab.').end()
                .show();
            $('.hozio-rollback-progress').show();
            $('.hozio-rollback-result').hide();
            $('[data-version]').prop('disabled', true);
        }

        function hozioRollbackDone($btn, originalText, success, responseData) {
            $('.hozio-rollback-progress').hide();
            $('[data-version]').prop('disabled', false);
            $btn && $btn.text(originalText);

            if (success) {
                var isDowngrade   = responseData && responseData.is_downgrade;
                var autoUpdatesOn = responseData && responseData.auto_updates_enabled;

                if (isDowngrade && autoUpdatesOn) {
                    // Hide the rollback overlay and show the auto-update choice modal
                    $('#hozio-rollback-overlay').hide();
                    $('#hozio-autoupdate-modal').css('display', 'flex');
                } else {
                    // Update overlay to "Done" state then reload
                    $('#hozio-rollback-overlay')
                        .find('.hozio-overlay-icon').removeClass('hozio-spinning dashicons-update').addClass('hozio-overlay-done dashicons-yes-alt').end()
                        .find('.hozio-overlay-title').text('Done! Reloading…').end()
                        .find('.hozio-overlay-sub').text('The page will refresh automatically.').end();
                    setTimeout(function() { location.reload(); }, 2000);
                }
            } else {
                var message = (typeof responseData === 'string') ? responseData : (responseData && responseData.message ? responseData.message : 'Unknown error.');
                $('#hozio-rollback-overlay').hide();
                $('.hozio-rollback-result')
                    .show()
                    .html('<div style="padding:12px 16px;background:#fee2e2;border-radius:8px;color:#991b1b;display:flex;gap:10px;align-items:center;">' +
                          '<span class="dashicons dashicons-warning" style="font-size:20px;"></span>' +
                          '<div>' + message + '</div>' +
                          '</div>');
            }
        }

        // History accordion toggle
        $(document).on('click', '.hozio-history-toggle', function() {
            var $btn  = $(this);
            var $body = $btn.closest('.hozio-history-block').find('.hozio-history-body');
            var open  = $btn.attr('aria-expanded') === 'true';
            $btn.attr('aria-expanded', open ? 'false' : 'true');
            $body.slideToggle(200);
        });

        // Clear history
        $(document).on('click', '.hozio-history-clear-btn', function() {
            if (!confirm('Clear all install history? This cannot be undone.')) return;
            var $btn   = $(this);
            var nonce  = $btn.data('nonce');
            $.post(ajaxurl, { action: 'hozio_clear_version_history', nonce: nonce }, function(r) {
                if (r.success) {
                    $btn.closest('.hozio-history-block').find('.hozio-history-body').html('<p style="margin:8px 0;font-size:12px;color:#9ca3af;">No history recorded yet.</p>');
                    $btn.closest('.hozio-history-block').find('.hozio-history-count').remove();
                    $btn.remove();
                }
            });
        });

        // Quick Revert button
        $('.hozio-quick-revert-btn').on('click', function() {
            var $btn    = $(this);
            var version = $btn.data('version');
            var nonce   = $btn.data('nonce');

            if (!confirm('Revert to v' + version + '? The page will reload automatically when done.')) return;

            hozioRollbackStart(version);
            $btn.text('Installing…').prop('disabled', true);

            $.ajax({
                url: ajaxurl, type: 'POST',
                data: { action: 'hozio_rollback_to_version', nonce: nonce, version: version },
                timeout: 300000,
                success: function(r) { hozioRollbackDone($btn, 'Revert to v' + version, r.success, r.success ? r.data : r.data); },
                error: function()    { hozioRollbackDone($btn, 'Revert to v' + version, false, 'Network error. Check your server error log.'); }
            });
        });

        // Load releases from GitHub
        $('.hozio-load-releases-btn').on('click', function() {
            var $btn    = $(this);
            var nonce   = $btn.data('nonce');
            var $status = $('.hozio-releases-status');

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update hozio-spinning" style="margin-top:3px;"></span> Loading…');
            $status.text('');

            $.ajax({
                url: ajaxurl, type: 'POST',
                data: { action: 'hozio_fetch_releases', nonce: nonce },
                timeout: 30000,
                success: function(r) {
                    $btn.prop('disabled', false);
                    if (!r.success) {
                        $btn.html('<span class="dashicons dashicons-warning" style="margin-top:3px;color:#d63638;"></span> Error');
                        $status.text(r.data);
                        setTimeout(function() { $btn.html('<span class="dashicons dashicons-update" style="margin-top:3px;"></span> Refresh'); }, 3000);
                        return;
                    }

                    var releases = r.data.releases;
                    var current  = r.data.current_version;
                    $btn.html('<span class="dashicons dashicons-yes-alt" style="margin-top:3px;color:#16a34a;"></span> Done — ' + releases.length + ' releases');
                    setTimeout(function() { $btn.html('<span class="dashicons dashicons-update" style="margin-top:3px;"></span> Refresh'); }, 2500);
                    var html = '';
                    $.each(releases, function(i, rel) {
                        var isCurrent = rel.version === current;
                        var rowStyle  = isCurrent ? 'background:#f5f3ff;' : (i % 2 === 0 ? '' : 'background:#fafafa;');
                        var badge     = isCurrent ? '<span class="hozio-badge hozio-badge-current">current</span>' : '';
                        var safeVer   = hozioEsc(rel.version.replace(/\./g, '-'));
                        var rawNotes  = rel.changelog ? rel.changelog.trim() : '';

                        var noteHtml = rawNotes
                            ? '<a href="#" class="hozio-notes-toggle" data-ver="' + safeVer + '" style="font-size:11px;color:#7c3aed;text-decoration:none;white-space:nowrap;">▼ Release notes</a>'
                            : '<span style="color:#9ca3af;font-size:11px;">No notes</span>';

                        var btnHtml = isCurrent
                            ? '<span style="color:#9ca3af;font-size:11px;">Installed</span>'
                            : '<button type="button" class="button button-small hozio-install-version-btn" ' +
                              'data-version="' + hozioEsc(rel.version) + '" data-nonce="' + hozioEsc(nonce) + '" ' +
                              'style="border-color:#7c3aed;color:#7c3aed;white-space:nowrap;">' +
                              'Install v' + hozioEsc(rel.version) + '</button>';

                        html += '<tr style="' + rowStyle + '">' +
                            '<td style="white-space:nowrap;padding:8px 6px;"><strong>v' + hozioEsc(rel.version) + '</strong>' + badge + '</td>' +
                            '<td style="white-space:nowrap;color:#6b7280;padding:8px 6px;">' + hozioFormatDate(rel.released_at) + '</td>' +
                            '<td style="padding:8px 6px;">' + noteHtml + '</td>' +
                            '<td style="text-align:right;padding:8px 6px;">' + btnHtml + '</td>' +
                            '</tr>';

                        if (rawNotes) {
                            html += '<tr class="hozio-notes-expand" id="hozio-notes-' + safeVer + '" style="display:none;">' +
                                '<td colspan="4" style="padding:0 8px 12px 24px;background:' + (isCurrent ? '#f5f3ff' : '#fafafa') + ';">' +
                                '<div style="border:1px solid #e9d5ff;border-radius:6px;padding:14px 18px;background:#fff;max-height:400px;overflow-y:auto;">' +
                                hozioMarkdown(rawNotes) +
                                '</div></td></tr>';
                        }
                    });

                    $('.hozio-releases-tbody').html(html);
                    $('.hozio-releases-list').show();
                },
                error: function() {
                    $btn.prop('disabled', false);
                    $btn.html('<span class="dashicons dashicons-warning" style="margin-top:3px;color:#d63638;"></span> Network error');
                    setTimeout(function() { $btn.html('<span class="dashicons dashicons-update" style="margin-top:3px;"></span> Refresh'); }, 3000);
                }
            });
        });

        // Install a specific version from the releases table
        $(document).on('click', '.hozio-install-version-btn', function() {
            var $btn    = $(this);
            var version = $btn.data('version');
            var nonce   = $btn.data('nonce');
            var orig    = $btn.text();

            if (!confirm('Install v' + version + '? This will replace the current plugin files. The page will reload when done.')) return;

            hozioRollbackStart(version);
            $btn.text('Installing…').prop('disabled', true);

            $.ajax({
                url: ajaxurl, type: 'POST',
                data: { action: 'hozio_rollback_to_version', nonce: nonce, version: version },
                timeout: 300000,
                success: function(r) { hozioRollbackDone($btn, orig, r.success, r.success ? r.data : r.data); },
                error: function()    { hozioRollbackDone($btn, orig, false, 'Network error. Check your server error log.'); }
            });
        });

        // Auto-update pause modal — confirm button
        $('#hozio-autoupdate-confirm').on('click', function() {
            var $btn   = $(this);
            var choice = $('input[name="hozio_au_choice"]:checked').val();
            var hours  = parseInt($('#hozio-pause-hours').val(), 10);
            var nonce  = '<?php echo esc_js(wp_create_nonce("hozio_rollback_nonce")); ?>';

            $btn.prop('disabled', true).text('Saving…');

            $.post(ajaxurl, {
                action: 'hozio_set_auto_update_pause',
                nonce:  nonce,
                choice: choice,
                hours:  hours
            }, function() {
                location.reload();
            }).fail(function() {
                // Reload anyway so the page reflects the installed version
                location.reload();
            });
        });

        // Simple HTML-entity escape helper
        function hozioEsc(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function hozioMarkdown(md) {
            var lines  = md.split('\n');
            var html   = '';
            var inList = false;
            function esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
            function inline(s) { return esc(s).replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>').replace(/`(.+?)`/g,'<code style="background:#f3f4f6;padding:1px 4px;border-radius:3px;font-size:11px;">$1</code>'); }
            lines.forEach(function(line) {
                var m;
                if ((m = line.match(/^### (.+)$/))) {
                    if (inList) { html += '</ul>'; inList = false; }
                    html += '<h5 style="margin:8px 0 3px;font-size:12px;font-weight:700;color:#374151;">' + inline(m[1]) + '</h5>';
                } else if ((m = line.match(/^## (.+)$/))) {
                    if (inList) { html += '</ul>'; inList = false; }
                    html += '<h4 style="margin:12px 0 4px;font-size:13px;font-weight:700;color:#111827;border-bottom:1px solid #e5e7eb;padding-bottom:4px;">' + inline(m[1]) + '</h4>';
                } else if ((m = line.match(/^# (.+)$/))) {
                    if (inList) { html += '</ul>'; inList = false; }
                    html += '<h3 style="margin:12px 0 5px;font-size:14px;font-weight:700;color:#111827;">' + inline(m[1]) + '</h3>';
                } else if ((m = line.match(/^[-*] (.+)$/))) {
                    if (!inList) { html += '<ul style="margin:4px 0 6px;padding-left:18px;">'; inList = true; }
                    html += '<li style="margin:2px 0;font-size:12px;color:#374151;">' + inline(m[1]) + '</li>';
                } else if (line.trim() === '') {
                    if (inList) { html += '</ul>'; inList = false; }
                } else {
                    if (inList) { html += '</ul>'; inList = false; }
                    var p = inline(line);
                    if (p.trim()) html += '<p style="margin:4px 0;font-size:12px;color:#374151;">' + p + '</p>';
                }
            });
            if (inList) html += '</ul>';
            return html;
        }

        // Toggle release notes expanded row
        $(document).on('click', '.hozio-notes-toggle', function(e) {
            e.preventDefault();
            var ver  = $(this).data('ver');
            var $row = $('#hozio-notes-' + ver);
            var open = $row.is(':visible');
            $row.toggle(!open);
            $(this).text(open ? '▼ Release notes' : '▲ Release notes');
        });

        // ── End Rollback ─────────────────────────────────────────────

        // Check for updates
        $('.hozio-check-updates-btn').on('click', function() {
            var $btn = $(this);
            var $result = $('.hozio-update-result');
            var nonce = $btn.data('nonce');

            $btn.prop('disabled', true);
            $btn.find('.dashicons').addClass('spin');
            $result.html('<span style="color: #666;">Checking...</span>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hozio_check_for_updates',
                    nonce: nonce
                },
                success: function(response) {
                    $btn.find('.dashicons').removeClass('spin');
                    $btn.prop('disabled', false);

                    if (response.success) {
                        // Update timing info
                        if (response.data.last_checked) {
                            $('.hozio-last-checked').text(response.data.last_checked);
                        }
                        if (response.data.next_check) {
                            $('.hozio-next-check').text(response.data.next_check);
                        }
                        if (response.data.auto_update_status) {
                            $('.hozio-auto-update-status').text(response.data.auto_update_status);
                        }

                        if (response.data.has_update) {
                            $result.html('<span style="color: #00a32a;"><span class="dashicons dashicons-yes"></span> ' + response.data.message + ' <a href="' + response.data.update_url + '">Update Now</a></span>');
                        } else {
                            $result.html('<span style="color: #00a32a;"><span class="dashicons dashicons-yes"></span> ' + response.data.message + '</span>');
                        }
                    } else {
                        $result.html('<span style="color: #d63638;"><span class="dashicons dashicons-warning"></span> ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $btn.find('.dashicons').removeClass('spin');
                    $btn.prop('disabled', false);
                    $result.html('<span style="color: #d63638;">Error checking for updates</span>');
                }
            });
        });
        // FAQ Schema master toggle — dims/disables sub-fields when master is off
        var $faqMaster   = $('input[name="hozio_faq_schema_enabled"]');
        var $faqSection  = $faqMaster.closest('.hozio-section');
        var $faqSubFields = $faqSection.find('.hozio-field').not($faqMaster.closest('.hozio-field'));

        function updateFaqToggles() {
            var on = $faqMaster.is(':checked');
            $faqSubFields.css('opacity', on ? '' : '0.4').css('pointer-events', on ? '' : 'none');
            $faqSubFields.find('input, select, textarea').prop('disabled', !on);
        }
        $faqMaster.on('change', updateFaqToggles);
        updateFaqToggles();

        // ── Tab switching ────────────────────────────────────────────
        $('.hozio-tab-btn').on('click', function() {
            var target = $(this).data('target');
            $('.hozio-tab-btn').removeClass('hozio-tab-active');
            $(this).addClass('hozio-tab-active');
            $('.hozio-tab-panel').hide();
            $('#' + target).show();
        });

        // ── Shortcode chip: click to copy ────────────────────────────
        $(document).on('click', '.hozio-sc-chip', function() {
            var text = $(this).text();
            var $chip = $(this);
            navigator.clipboard.writeText(text).then(function() {
                $chip.addClass('hozio-copied');
                setTimeout(function() { $chip.removeClass('hozio-copied'); }, 1200);
            });
        });

        // ── Shortcode sub-tab switching ──────────────────────────────
        $(document).on('click', '.hozio-sc-subbtn', function() {
            var panelId = $(this).data('panel');
            $('.hozio-sc-subbtn').removeClass('hozio-sc-subbtn-active');
            $(this).addClass('hozio-sc-subbtn-active');
            $('.hozio-sc-panel').hide();
            $('#' + panelId).show();
        });

        // ── Copy All button ──────────────────────────────────────────
        $(document).on('click', '.hozio-copy-all-btn', function() {
            var $btn   = $(this);
            var scope  = $btn.data('scope');
            var $panel = scope ? $(scope) : $btn.closest('.hozio-sc-panel, .hozio-sc-group');
            var codes  = $panel.find('.hozio-sc-chip').map(function() {
                return $(this).text();
            }).get();
            navigator.clipboard.writeText(codes.join('\n')).then(function() {
                var orig = $btn.html();
                $btn.html('<span class="dashicons dashicons-yes"></span> Copied!');
                setTimeout(function() { $btn.html(orig); }, 1500);
            });
        });
    });
    </script>
    <?php
}
