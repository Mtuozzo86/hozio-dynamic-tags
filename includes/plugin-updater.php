<?php
/**
 * Hozio Pro Self-Hosted Plugin Updater
 * Checks GitHub Releases for updates - no third-party plugin required
 *
 * @package Hozio_Pro
 * @since 3.74
 */

if (!defined('ABSPATH')) exit;

class Hozio_Plugin_Updater {

    /**
     * Plugin configuration
     */
    private $plugin_slug = 'hozio-dynamic-tags';
    private $plugin_file = 'hozio-dynamic-tags/hozio-dynamic-tags.php';
    private $plugin_name = 'Hozio Pro';

    /**
     * GitHub configuration
     */
    private $github_username = 'Mtuozzo86';
    private $github_repo = 'hozio-dynamic-tags';

    /**
     * License key (single key for all sites)
     */
    private $valid_license_key = 'HOZIO-PRO-2026';

    /**
     * Cache settings
     */
    private $cache_key = 'hozio_plugin_update_cache';
    private $cache_expiry = 43200; // 12 hours in seconds

    /**
     * Current plugin version
     */
    private $current_version;

    /**
     * GitHub release data
     */
    private $github_response;

    /**
     * Constructor - hook into WordPress update system
     */
    public function __construct() {
        $this->current_version = $this->get_current_version();

        // Only run in admin
        if (!is_admin()) {
            return;
        }

        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);

        // Enable auto-updates for this plugin (when license valid and setting enabled)
        add_filter('auto_update_plugin', [$this, 'enable_auto_updates'], 10, 2);

        // Add action link on plugins page
        add_filter('plugin_action_links_' . $this->plugin_file, [$this, 'add_action_links']);
    }

    /**
     * Get the current installed plugin version
     */
    private function get_current_version() {
        // Method 1: Try to get version from the main plugin file relative to this file
        $main_plugin_file = dirname(__DIR__) . '/hozio-dynamic-tags.php';

        if (file_exists($main_plugin_file)) {
            $plugin_data = get_file_data($main_plugin_file, ['Version' => 'Version']);
            if (!empty($plugin_data['Version'])) {
                return $plugin_data['Version'];
            }
        }

        // Method 2: Try standard WordPress plugin path
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = WP_PLUGIN_DIR . '/' . $this->plugin_file;

        if (file_exists($plugin_file)) {
            $plugin_data = get_plugin_data($plugin_file);
            if (!empty($plugin_data['Version'])) {
                return $plugin_data['Version'];
            }
        }

        // Method 3: Fallback - return a version that will trigger update check
        return '0.0.0';
    }

    /**
     * Check if the license key is valid
     */
    public function is_license_valid() {
        $entered_key = get_option('hozio_license_key', '');
        return trim($entered_key) === $this->valid_license_key;
    }

    /**
     * Get the license status for display
     */
    public function get_license_status() {
        $auto_updates_enabled = get_option('hozio_auto_updates_enabled', '1') === '1';

        if ($this->is_license_valid()) {
            if ($auto_updates_enabled) {
                return [
                    'status' => 'valid',
                    'message' => 'Licensed - Auto-updates enabled',
                    'class' => 'hozio-license-valid'
                ];
            } else {
                return [
                    'status' => 'valid',
                    'message' => 'Licensed - Auto-updates disabled (manual only)',
                    'class' => 'hozio-license-valid'
                ];
            }
        }

        $entered_key = get_option('hozio_license_key', '');
        if (empty($entered_key)) {
            return [
                'status' => 'empty',
                'message' => 'No license key entered - Updates disabled',
                'class' => 'hozio-license-empty'
            ];
        }

        return [
            'status' => 'invalid',
            'message' => 'Invalid license key - Updates disabled',
            'class' => 'hozio-license-invalid'
        ];
    }

    /**
     * Allow WordPress to auto-update this plugin
     */
    public function enable_auto_updates($update, $item) {
        // Only affect our plugin
        if (!isset($item->slug) || $item->slug !== $this->plugin_slug) {
            return $update;
        }

        // Check if license is valid
        if (!$this->is_license_valid()) {
            return false;
        }

        // Check if auto-updates are enabled in settings
        if (get_option('hozio_auto_updates_enabled', '1') !== '1') {
            return false;
        }

        return true;
    }

    /**
     * Fetch release data from GitHub API
     */
    private function get_github_release() {
        // Check cache first
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Build API URL
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->github_username,
            $this->github_repo
        );

        // Make request
        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            ],
            'timeout' => 10
        ]);

        // Check for errors
        if (is_wp_error($response)) {
            hozio_log('GitHub API error: ' . $response->get_error_message(), 'Updater');
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            hozio_log('GitHub API returned status ' . $response_code, 'Updater');
            return false;
        }

        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data) || !isset($data->tag_name)) {
            hozio_log('Invalid GitHub API response', 'Updater');
            return false;
        }

        // Cache the response
        set_transient($this->cache_key, $data, $this->cache_expiry);

        // Track when we last checked for updates
        update_option('hozio_last_update_check', time());

        return $data;
    }

    /**
     * Get the download URL from GitHub release
     */
    private function get_download_url($release) {
        // First, look for a ZIP file in assets (preferred - your uploaded ZIP)
        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    return $asset->browser_download_url;
                }
            }
        }

        // Fallback to GitHub's auto-generated zipball
        if (!empty($release->zipball_url)) {
            return $release->zipball_url;
        }

        return false;
    }

    /**
     * Parse version from GitHub tag (removes 'v' prefix if present)
     */
    private function parse_version($tag) {
        return ltrim($tag, 'vV');
    }

    /**
     * Check for plugin updates
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Only check if license is valid
        if (!$this->is_license_valid()) {
            return $transient;
        }

        // Get GitHub release data
        $release = $this->get_github_release();
        if (!$release) {
            return $transient;
        }

        // Compare versions
        $remote_version = $this->parse_version($release->tag_name);

        if (version_compare($this->current_version, $remote_version, '<')) {
            // Get download URL
            $download_url = $this->get_download_url($release);

            if ($download_url) {
                $transient->response[$this->plugin_file] = (object) [
                    'slug' => $this->plugin_slug,
                    'plugin' => $this->plugin_file,
                    'new_version' => $remote_version,
                    'url' => $release->html_url,
                    'package' => $download_url,
                    'icons' => [],
                    'banners' => [],
                    'banners_rtl' => [],
                    'tested' => '',
                    'requires_php' => '7.4',
                    'compatibility' => new stdClass()
                ];

                hozio_log('Update available: ' . $this->current_version . ' → ' . $remote_version, 'Updater');
            }
        }

        return $transient;
    }

    /**
     * Provide plugin information for the "View details" popup
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        // Get GitHub release data
        $release = $this->get_github_release();
        if (!$release) {
            return $result;
        }

        $remote_version = $this->parse_version($release->tag_name);
        $download_url = $this->get_download_url($release);

        // Build plugin info object
        $plugin_info = new stdClass();
        $plugin_info->name = $this->plugin_name;
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->version = $remote_version;
        $plugin_info->author = '<a href="https://hozio.com">Hozio, Inc.</a>';
        $plugin_info->homepage = 'https://github.com/' . $this->github_username . '/' . $this->github_repo;
        $plugin_info->requires = '5.0';
        $plugin_info->tested = '6.4';
        $plugin_info->requires_php = '7.4';
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = $release->published_at;
        $plugin_info->download_link = $download_url;

        // Convert markdown changelog to HTML (basic)
        $changelog = isset($release->body) ? $release->body : 'No changelog available.';
        $plugin_info->sections = [
            'description' => '<p>Hozio Pro - Dynamic tags, custom permalinks, loop configurations, and more for WordPress + Elementor sites.</p>',
            'changelog' => '<pre>' . esc_html($changelog) . '</pre>',
            'installation' => '<p>Upload the plugin ZIP file via WordPress admin or FTP.</p>'
        ];

        return $plugin_info;
    }

    /**
     * Handle post-installation tasks (fix folder name if needed)
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Only process our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_file) {
            return $response;
        }

        // Get the installed folder name
        $install_directory = $result['destination'];
        $plugin_directory = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

        // If GitHub's zipball was used, folder will have a weird name like "Mtuozzo86-hozio-dynamic-tags-abc123"
        // We need to rename it to "hozio-dynamic-tags"
        if ($install_directory !== $plugin_directory) {
            $wp_filesystem->move($install_directory, $plugin_directory);
            $result['destination'] = $plugin_directory;

            hozio_log('Renamed plugin folder after update', 'Updater');
        }

        // Reactivate the plugin
        activate_plugin($this->plugin_file);

        // Clear update cache
        delete_transient($this->cache_key);

        return $response;
    }

    /**
     * Add action links on the plugins page
     */
    public function add_action_links($links) {
        $license_status = $this->get_license_status();

        // Always add Settings link
        $settings_link = '<a href="' . admin_url('admin.php?page=hozio-plugin-settings') . '">Settings</a>';
        array_unshift($links, $settings_link);

        // Add warning if not licensed
        if ($license_status['status'] !== 'valid') {
            $license_link = '<a href="' . admin_url('admin.php?page=hozio-plugin-settings') . '" style="color: #d63638;">Enter License Key</a>';
            array_unshift($links, $license_link);
        }

        return $links;
    }

    /**
     * Force check for updates (clears cache)
     */
    public function force_update_check() {
        delete_transient($this->cache_key);
        delete_site_transient('update_plugins');
        wp_update_plugins();
    }
}

// Initialize the updater
function hozio_init_plugin_updater() {
    global $hozio_updater;
    $hozio_updater = new Hozio_Plugin_Updater();
}
add_action('init', 'hozio_init_plugin_updater');

/**
 * Helper function to get license status
 */
function hozio_get_license_status() {
    global $hozio_updater;
    if ($hozio_updater) {
        return $hozio_updater->get_license_status();
    }
    return [
        'status' => 'unknown',
        'message' => 'Updater not initialized',
        'class' => 'hozio-license-empty'
    ];
}

/**
 * Helper function to check if license is valid
 */
function hozio_is_license_valid() {
    global $hozio_updater;
    if ($hozio_updater) {
        return $hozio_updater->is_license_valid();
    }
    return false;
}

/**
 * Auto-set license key on plugin activation/update
 * This ensures all sites updating to v3.74+ automatically have the license key
 */
function hozio_auto_set_license_key() {
    $license_key = 'HOZIO-PRO-2026';
    $current_key = get_option('hozio_license_key', '');

    // Only set if not already set or if different
    if ($current_key !== $license_key) {
        update_option('hozio_license_key', $license_key);
        hozio_log('License key auto-configured on plugin activation/update', 'Updater');
    }
}

// Run on plugin activation
register_activation_hook(dirname(__DIR__) . '/hozio-dynamic-tags.php', 'hozio_auto_set_license_key');

// Also run on every admin load to catch updates (runs once then stops)
function hozio_check_license_on_update() {
    // Only run in admin
    if (!is_admin()) {
        return;
    }

    // Check if we've already auto-set for this version
    $version_licensed = get_option('hozio_license_version', '');
    $current_version = '3.81';

    if ($version_licensed !== $current_version) {
        hozio_auto_set_license_key();
        update_option('hozio_license_version', $current_version);
    }
}
add_action('admin_init', 'hozio_check_license_on_update');

/**
 * Get human-readable time since last update check
 */
function hozio_get_last_update_check() {
    $timestamp = get_option('hozio_last_update_check', 0);
    if (!$timestamp) {
        return 'Never';
    }
    return human_time_diff($timestamp, time()) . ' ago';
}

/**
 * Get time until next WordPress plugin update check
 */
function hozio_get_next_update_check() {
    $next_check = wp_next_scheduled('wp_update_plugins');
    if (!$next_check) {
        return 'Not scheduled';
    }
    if ($next_check <= time()) {
        return 'Soon';
    }
    return 'in ' . human_time_diff(time(), $next_check);
}

/**
 * Get auto-update status message
 * Shows whether auto-updates are configured, not when they'll run
 * (WordPress cron detection is unreliable across different hosts)
 */
function hozio_get_auto_update_status() {
    // Check if license is valid
    if (!function_exists('hozio_is_license_valid') || !hozio_is_license_valid()) {
        return 'Disabled (no valid license)';
    }

    // Check if auto-updates toggle is enabled
    if (get_option('hozio_auto_updates_enabled', '1') !== '1') {
        return 'Disabled (turned off in settings)';
    }

    // Check if there's an update available
    $update_plugins = get_site_transient('update_plugins');
    $plugin_file = 'hozio-dynamic-tags/hozio-dynamic-tags.php';

    if (isset($update_plugins->response[$plugin_file])) {
        // Update is available and auto-updates are enabled
        return 'Enabled - will auto-update when WordPress runs background updates';
    }

    // No update available, but auto-updates are ready
    return 'Enabled - ready for future updates';
}

/**
 * Check if auto-updates are enabled for this plugin
 */
function hozio_auto_updates_enabled() {
    // Must have valid license AND auto-updates setting enabled
    if (!function_exists('hozio_is_license_valid') || !hozio_is_license_valid()) {
        return false;
    }
    return get_option('hozio_auto_updates_enabled', '1') === '1';
}
