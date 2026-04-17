<?php
/**
 * Hozio Pro Plugin Rollback System
 * Install any previous GitHub release version, record history, support Hub commands.
 */

if (!defined('ABSPATH')) exit;

// ─── Version History ─────────────────────────────────────────────────────────

/**
 * Push a version snapshot onto the front of the history ring-buffer.
 */
function hozio_record_version_snapshot($version, $method = 'update', $previous = '') {
    $history = get_option('hozio_version_history', []);

    // Skip if most recent entry is already this version (prevents duplicates from manual file edits)
    if (!empty($history) && $history[0]['version'] === $version) {
        return;
    }

    array_unshift($history, [
        'version'      => $version,
        'installed_at' => time(),
        'method'       => $method, // auto_update | rollback | hub_command
        'previous'     => $previous,
    ]);

    update_option('hozio_version_history', array_slice($history, 0, 20));
}

/**
 * Keep version history in sync with the actual installed version.
 * Catches manual file updates that bypass the WP upgrader hook.
 */
add_action('plugins_loaded', function() {
    $history  = get_option('hozio_version_history', []);
    $current  = hozio_get_plugin_version();
    $previous = !empty($history) ? $history[0]['version'] : '';
    // hozio_record_version_snapshot is a no-op if history[0] already matches $current
    hozio_record_version_snapshot($current, 'update', $previous);
}, 20);

/**
 * Save current version right before an upgrade so upgrader_process_complete
 * can record the transition accurately.
 */
add_filter('pre_set_site_transient_update_plugins', function($transient) {
    if (empty($transient->response)) return $transient;

    $plugin_slug = basename(dirname(plugin_dir_path(__FILE__)));
    $plugin_file = $plugin_slug . '/hozio-dynamic-tags.php';

    if (isset($transient->response[$plugin_file])) {
        update_option('hozio_pre_upgrade_version', hozio_get_plugin_version());
    }

    return $transient;
}, 20);

/**
 * Record version after any plugin upgrade that touches Hozio Pro.
 */
add_action('upgrader_process_complete', function($upgrader, $hook_extra) {
    if (($hook_extra['action'] ?? '') !== 'update') return;
    if (($hook_extra['type'] ?? '')   !== 'plugin') return;

    $plugin_slug = basename(dirname(plugin_dir_path(__FILE__)));
    $plugin_file = $plugin_slug . '/hozio-dynamic-tags.php';

    if (!in_array($plugin_file, (array)($hook_extra['plugins'] ?? []), true)) return;

    $previous = get_option('hozio_pre_upgrade_version', '');
    hozio_record_version_snapshot(hozio_get_plugin_version(), 'auto_update', $previous);
    delete_option('hozio_pre_upgrade_version');
}, 10, 2);

// ─── GitHub Releases Fetcher ─────────────────────────────────────────────────

/**
 * Return all GitHub releases as normalised arrays, cached 12 hrs.
 *
 * @param bool $force Skip cache and re-fetch.
 * @return array
 */
function hozio_fetch_github_releases($force = false) {
    $cache_key = 'hozio_all_releases_cache';

    if (!$force) {
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
    }

    $response = wp_remote_get('https://api.github.com/repos/Mtuozzo86/hozio-dynamic-tags/releases', [
        'headers' => [
            'Accept'     => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return [];
    }

    $raw = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($raw)) return [];

    $releases = [];
    foreach ($raw as $r) {
        $zip_url = '';
        foreach ((array)($r['assets'] ?? []) as $asset) {
            if (strpos($asset['name'] ?? '', '.zip') !== false) {
                $zip_url = $asset['browser_download_url'] ?? '';
                break;
            }
        }
        if (empty($zip_url)) $zip_url = $r['zipball_url'] ?? '';

        $releases[] = [
            'version'     => ltrim($r['tag_name'] ?? '', 'vV'),
            'tag'         => $r['tag_name'] ?? '',
            'released_at' => $r['published_at'] ?? '',
            'release_url' => $r['html_url'] ?? '',
            'zip_url'     => $zip_url,
            'changelog'   => $r['body'] ?? '',
            'prerelease'  => (bool)($r['prerelease'] ?? false),
        ];
    }

    set_transient($cache_key, $releases, 12 * HOUR_IN_SECONDS);
    return $releases;
}

/**
 * Find one release by version string (e.g. "4.11.3").
 */
function hozio_get_release_by_version($version) {
    $version = ltrim($version, 'vV');
    foreach (hozio_fetch_github_releases() as $r) {
        if ($r['version'] === $version) return $r;
    }
    // Cache miss — try a forced refresh once
    foreach (hozio_fetch_github_releases(true) as $r) {
        if ($r['version'] === $version) return $r;
    }
    return null;
}

// ─── Core Rollback Installer ─────────────────────────────────────────────────

/**
 * Download and install any released version of Hozio Pro.
 *
 * @param string $target_version e.g. "4.11.3"
 * @return array { success: bool, message: string, version?: string }
 */
function hozio_perform_rollback($target_version) {
    $target_version = ltrim(sanitize_text_field($target_version), 'vV');

    if (empty($target_version)) {
        return ['success' => false, 'message' => 'Target version is required.'];
    }

    $release = hozio_get_release_by_version($target_version);
    if (!$release || empty($release['zip_url'])) {
        return ['success' => false, 'message' => "Version {$target_version} not found in GitHub releases."];
    }

    // Load WP upgrader classes first — get_filesystem_method() lives in file.php
    foreach ([
        ABSPATH . 'wp-admin/includes/file.php',
        ABSPATH . 'wp-admin/includes/class-wp-upgrader.php',
        ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php',
        ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php',
        ABSPATH . 'wp-admin/includes/plugin.php',
    ] as $f) {
        if (file_exists($f)) require_once $f;
    }

    if (get_filesystem_method() !== 'direct') {
        return ['success' => false, 'message' => 'Direct filesystem access is required. Cannot install without FTP credentials.'];
    }

    $plugin_slug     = basename(dirname(plugin_dir_path(__FILE__)));
    $plugin_file     = $plugin_slug . '/hozio-dynamic-tags.php';
    $current_version = hozio_get_plugin_version();

    update_option('hozio_pre_upgrade_version', $current_version);

    // Inject target version into the update_plugins transient so
    // Plugin_Upgrader::upgrade() can resolve the package URL.
    $transient = get_site_transient('update_plugins') ?: (object)['response' => [], 'checked' => []];
    $transient->response[$plugin_file] = (object)[
        'slug'        => $plugin_slug,
        'plugin'      => $plugin_file,
        'new_version' => $target_version,
        'package'     => $release['zip_url'],
        'url'         => $release['release_url'],
    ];
    set_site_transient('update_plugins', $transient);

    $skin     = new WP_Ajax_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);
    $result   = $upgrader->upgrade($plugin_file);

    // Clean up injected entry whether it worked or not
    $transient = get_site_transient('update_plugins');
    if ($transient && isset($transient->response[$plugin_file])) {
        unset($transient->response[$plugin_file]);
        set_site_transient('update_plugins', $transient);
    }

    // Clear caches so next check reflects the installed version
    delete_transient('hozio_all_releases_cache');
    delete_transient('hozio_plugin_update_cache');

    if (is_wp_error($result)) {
        delete_option('hozio_pre_upgrade_version');
        return ['success' => false, 'message' => $result->get_error_message()];
    }

    if ($result === false) {
        $errors = $skin->get_errors();
        $msg = is_wp_error($errors) ? $errors->get_error_message() : 'Installation failed — check file permissions.';
        delete_option('hozio_pre_upgrade_version');
        return ['success' => false, 'message' => $msg];
    }

    // Record in history and reactivate
    hozio_record_version_snapshot($target_version, 'rollback', $current_version);
    delete_option('hozio_pre_upgrade_version');

    if (function_exists('activate_plugin')) {
        activate_plugin($plugin_file);
    }

    hozio_audit_log("Rollback complete: {$current_version} → {$target_version}", 'Rollback');

    return [
        'success' => true,
        'message' => "Successfully installed v{$target_version} (was v{$current_version}).",
        'version' => $target_version,
    ];
}

// ─── AJAX Handlers ───────────────────────────────────────────────────────────

function hozio_ajax_fetch_releases() {
    check_ajax_referer('hozio_rollback_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

    $releases = hozio_fetch_github_releases(!empty($_POST['force']));
    wp_send_json_success([
        'releases'        => $releases,
        'current_version' => hozio_get_plugin_version(),
    ]);
}
add_action('wp_ajax_hozio_fetch_releases', 'hozio_ajax_fetch_releases');

function hozio_ajax_rollback_to_version() {
    check_ajax_referer('hozio_rollback_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

    $version = sanitize_text_field($_POST['version'] ?? '');
    if (empty($version)) wp_send_json_error('Version is required.');

    @set_time_limit(300);

    $result = hozio_perform_rollback($version);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_hozio_rollback_to_version', 'hozio_ajax_rollback_to_version');

function hozio_ajax_get_version_history() {
    check_ajax_referer('hozio_rollback_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

    wp_send_json_success(['history' => get_option('hozio_version_history', [])]);
}
add_action('wp_ajax_hozio_get_version_history', 'hozio_ajax_get_version_history');

function hozio_ajax_clear_version_history() {
    check_ajax_referer('hozio_rollback_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

    delete_option('hozio_version_history');
    hozio_audit_log('Version history cleared by admin', 'Rollback');
    wp_send_json_success();
}
add_action('wp_ajax_hozio_clear_version_history', 'hozio_ajax_clear_version_history');

// ─── Version Lock ─────────────────────────────────────────────────────────────

function hozio_is_version_locked() {
    return get_option('hozio_version_locked', '0') === '1';
}

function hozio_ajax_set_version_lock() {
    check_ajax_referer('hozio_rollback_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

    $locked = !empty($_POST['locked']) ? '1' : '0';
    update_option('hozio_version_locked', $locked);
    hozio_audit_log('Version lock ' . ($locked === '1' ? 'enabled' : 'disabled') . ' by admin', 'Rollback');
    wp_send_json_success(['locked' => $locked === '1']);
}
add_action('wp_ajax_hozio_set_version_lock', 'hozio_ajax_set_version_lock');
