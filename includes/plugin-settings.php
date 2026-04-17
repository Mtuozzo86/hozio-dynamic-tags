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
            <img src="<?php echo esc_url(plugins_url('../assets/hozio-burst.png', __FILE__)); ?>" alt="Hozio" class="hozio-logo">
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
                <div class="hozio-field" style="margin-top: 20px;">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" name="hozio_auto_updates_enabled" value="1"
                                   <?php checked($auto_updates_enabled, '1'); ?>>
                            <span class="hozio-toggle-slider"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">Enable Automatic Updates</div>
                            <div class="hozio-toggle-description">
                                When enabled, WordPress will automatically install plugin updates.
                                <?php echo $hub_connected ? 'Managed via Hub.' : 'Requires a valid license key.'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Version Lock Toggle -->
                <?php $version_locked = get_option('hozio_version_locked', '0') === '1'; ?>
                <div class="hozio-field" style="margin-top: 16px;">
                    <div class="hozio-toggle-wrapper">
                        <label class="hozio-toggle-switch">
                            <input type="checkbox" id="hozio-version-lock-toggle" value="1"
                                   data-nonce="<?php echo esc_attr(wp_create_nonce('hozio_rollback_nonce')); ?>"
                                   <?php checked($version_locked, true); ?>>
                            <span class="hozio-toggle-slider" style="<?php echo $version_locked ? 'background:#dc2626!important;' : ''; ?>"></span>
                        </label>
                        <div class="hozio-toggle-label">
                            <div class="hozio-toggle-title">
                                Version Lock
                                <?php if ($version_locked): ?>
                                    <span style="display:inline-block;background:#fef2f2;color:#dc2626;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:6px;text-transform:uppercase;letter-spacing:.5px;">Locked</span>
                                <?php endif; ?>
                            </div>
                            <div class="hozio-toggle-description">
                                When locked, all automatic updates are blocked and Hub cannot push updates to this site. Use during active development or client projects.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Update Info & Check Button -->
                <div class="hozio-update-info-box" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
                        <button type="button" class="button button-secondary hozio-check-updates-btn"
                                data-nonce="<?php echo wp_create_nonce('hozio_check_updates_nonce'); ?>">
                            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                            Check for Updates
                        </button>
                        <span class="hozio-update-result"></span>
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

                <?php if ($hub_connected): ?>
                    <!-- Disconnect (subtle, at bottom) -->
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                        <button type="button" class="button hozio-hub-disconnect-btn"
                                data-nonce="<?php echo esc_attr(wp_create_nonce('hozio_hub_disconnect_nonce')); ?>"
                                style="color: #999; border-color: #ddd; font-size: 12px;">
                            Disconnect from Hub
                        </button>
                        <p class="hozio-field-description" style="margin-top: 6px; font-size: 12px;">
                            Disconnecting will revert to manual license key mode.
                            <?php if (empty($license_key)): ?>
                                <strong style="color: #d63638;">No manual key is set — auto-updates will be disabled.</strong>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
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
                            'auto_update' => '#0073aa',
                            'rollback'    => '#7c3aed',
                            'hub_command' => '#059669',
                            'update'      => '#6b7280',
                        ];
                        $method_labels = [
                            'auto_update' => 'Auto-update',
                            'rollback'    => 'Rollback',
                            'hub_command' => 'Hub',
                            'update'      => 'Manual',
                        ];
                        $seen_versions = [];
                        foreach ($history as $i => $entry):
                            // Skip duplicate versions (same version recorded more than once)
                            if (in_array($entry['version'], $seen_versions, true)) continue;
                            $seen_versions[] = $entry['version'];
                            $is_current = ($entry['version'] === $current_ver);
                            $dot_color  = $method_colors[$entry['method']] ?? '#6b7280';
                            $label      = $method_labels[$entry['method']] ?? ucfirst($entry['method']);
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
                    <div class="hozio-browse-releases">
                        <button type="button" class="hozio-load-releases-btn hozio-browse-btn"
                                data-nonce="<?php echo esc_attr($rollback_nonce); ?>">
                            <span class="dashicons dashicons-download"></span>
                            Browse All Releases
                            <span class="hozio-releases-status"></span>
                        </button>

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
    </div><!-- /.wrap -->

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
        object-fit: contain;
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
                        $result.html('<span style="color:#16a34a;">&#10003; Connection verified.</span>').show();
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

        // Hub Disconnect
        $('.hozio-hub-disconnect-btn').on('click', function() {
            if (!confirm('Disconnect from Hub? License validation will revert to manual key mode.')) return;
            var $btn = $(this);
            $btn.prop('disabled', true).text('Disconnecting...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hozio_hub_disconnect',
                    nonce: $btn.data('nonce')
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $btn.prop('disabled', false).text('Disconnect from Hub');
                    }
                },
                error: function() {
                    alert('Network error');
                    $btn.prop('disabled', false).text('Disconnect from Hub');
                }
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

        function hozioRollbackDone($btn, originalText, success, message) {
            $('.hozio-rollback-progress').hide();
            $('[data-version]').prop('disabled', false);
            $btn && $btn.text(originalText);

            if (success) {
                // Update overlay to "Done" state and keep it visible until reload
                $('#hozio-rollback-overlay')
                    .find('.hozio-overlay-icon').removeClass('hozio-spinning dashicons-update').addClass('hozio-overlay-done dashicons-yes-alt').end()
                    .find('.hozio-overlay-title').text('Done! Reloading…').end()
                    .find('.hozio-overlay-sub').text('The page will refresh automatically.').end();
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                $('#hozio-rollback-overlay').hide();
                var color = '#991b1b';
                var bg    = '#fee2e2';
                $('.hozio-rollback-result')
                    .show()
                    .html('<div style="padding:12px 16px;background:' + bg + ';border-radius:8px;color:' + color + ';display:flex;gap:10px;align-items:center;">' +
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
                success: function(r) { hozioRollbackDone($btn, 'Revert to v' + version, r.success, r.success ? r.data.message : r.data); },
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
                success: function(r) { hozioRollbackDone($btn, orig, r.success, r.success ? r.data.message : r.data); },
                error: function()    { hozioRollbackDone($btn, orig, false, 'Network error. Check your server error log.'); }
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

        // Version lock toggle (AJAX — not a form field, takes effect immediately)
        $('#hozio-version-lock-toggle').on('change', function() {
            var $cb    = $(this);
            var locked = $cb.is(':checked');
            var nonce  = $cb.data('nonce');
            var $slider = $cb.next('.hozio-toggle-slider');
            $.post(ajaxurl, { action: 'hozio_set_version_lock', nonce: nonce, locked: locked ? 1 : 0 }, function(r) {
                if (r.success) {
                    $slider.css('background', locked ? '#dc2626' : '');
                } else {
                    $cb.prop('checked', !locked); // revert on failure
                }
            });
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
    });
    </script>
    <?php
}
