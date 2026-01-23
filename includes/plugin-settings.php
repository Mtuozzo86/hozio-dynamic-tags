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

    // Clear all hozio-related transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hozio_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hozio_%'");

    wp_send_json_success(['message' => 'Plugin caches cleared successfully']);
}
add_action('wp_ajax_hozio_clear_plugin_caches', 'hozio_ajax_clear_plugin_caches');

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

        // Link to plugins page where user can click "update now" for the plugin
        $update_url = admin_url('plugins.php');

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

    // Save license key
    if (isset($_POST['hozio_license_key'])) {
        $license_key = sanitize_text_field(trim($_POST['hozio_license_key']));
        update_option('hozio_license_key', $license_key);
    }

    // Save auto-updates enabled setting
    $auto_updates_enabled = isset($_POST['hozio_auto_updates_enabled']) ? '1' : '0';
    update_option('hozio_auto_updates_enabled', $auto_updates_enabled);

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
            <img src="<?php echo esc_url(plugins_url('../assets/hozio-logo.png', __FILE__)); ?>" alt="Hozio" class="hozio-logo">
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

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="hozio_plugin_settings_save">
            <?php wp_nonce_field('hozio_plugin_settings_save', 'hozio_plugin_settings_nonce'); ?>

            <!-- License Key Section -->
            <div class="hozio-section hozio-license-section">
                <h2 class="hozio-section-title">License & Updates</h2>
                <p class="hozio-section-description">Enter your license key to enable automatic plugin updates.</p>

                <div class="hozio-field">
                    <div class="hozio-license-wrapper">
                        <label for="hozio_license_key" class="hozio-license-label">License Key</label>
                        <div class="hozio-license-input-wrapper">
                            <input type="text" id="hozio_license_key" name="hozio_license_key"
                                   value="<?php echo esc_attr($license_key); ?>"
                                   class="regular-text hozio-license-input"
                                   placeholder="Enter your license key">
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
                                Requires a valid license key.
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

            <!-- System Information Section -->
            <div class="hozio-section">
                <h2 class="hozio-section-title">System Information</h2>

                <table class="hozio-system-info">
                    <tr>
                        <th>Plugin Version</th>
                        <td><?php echo esc_html(hozio_get_plugin_version()); ?></td>
                    </tr>
                    <tr>
                        <th>WordPress Version</th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th>PHP Version</th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th>Debug Log Path</th>
                        <td><code><?php echo esc_html($log_path); ?></code></td>
                    </tr>
                    <tr>
                        <th>Debug Logging</th>
                        <td>
                            <?php if ($constant_defined): ?>
                                <span class="hozio-status hozio-status-<?php echo HOZIO_DEBUG ? 'on' : 'off'; ?>">
                                    <?php echo HOZIO_DEBUG ? 'Enabled (via wp-config.php)' : 'Disabled (via wp-config.php)'; ?>
                                </span>
                            <?php else: ?>
                                <span class="hozio-status hozio-status-<?php echo $debug_enabled === '1' ? 'on' : 'off'; ?>">
                                    <?php echo $debug_enabled === '1' ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>DOM Parsing</th>
                        <td>
                            <span class="hozio-status hozio-status-<?php echo $dom_parsing_enabled === '1' ? 'on' : 'off'; ?>">
                                <?php echo $dom_parsing_enabled === '1' ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Service Menu Sync</th>
                        <td>
                            <span class="hozio-status hozio-status-<?php echo $service_menu_sync_enabled === '1' ? 'on' : 'off'; ?>">
                                <?php echo $service_menu_sync_enabled === '1' ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>License Status</th>
                        <td>
                            <span class="hozio-status hozio-status-<?php echo $license_status['status'] === 'valid' ? 'on' : 'off'; ?>">
                                <?php echo esc_html($license_status['message']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="hozio-submit-wrapper">
                <button type="submit" class="button button-primary button-large">Save Settings</button>
            </div>
        </form>
    </div>

    <style>
    .hozio-settings-wrap {
        max-width: 900px;
    }

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
        width: 60px;
        height: auto;
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
