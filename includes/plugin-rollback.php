<?php
/**
 * Hozio Pro Plugin Rollback System
 * Install any previous GitHub release version, record history, support Hub commands.
 */

if (!defined('ABSPATH')) exit;

// ─── Rollback Debug Log (v4.12.1+) ────────────────────────────────────────────

/**
 * Append a one-line entry to the rollback debug log (capped at 100 entries).
 * Use this any time something rollback-related happens — call sites, Hub
 * commands, install/uninstall events — so we can trace unwanted rollbacks.
 */
function hozio_rollback_debug_log($message) {
    $log = get_option('hozio_rollback_debug_log', array());
    if (!is_array($log)) { $log = array(); }
    array_unshift($log, array(
        'time'    => time(),
        'message' => (string) $message,
    ));
    update_option('hozio_rollback_debug_log', array_slice($log, 0, 100), false);
}

/**
 * Build a one-line "function@file:line → function@file:line" trace string for
 * the current call stack, skipping the immediate frame (the logger itself).
 */
function hozio_rollback_caller_chain($depth = 6) {
    if (!function_exists('debug_backtrace')) return 'unknown';
    $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 2);
    $out = array();
    foreach (array_slice($frames, 2, $depth) as $f) {
        $where = isset($f['file']) ? basename($f['file']) . ':' . ($f['line'] ?? '?') : '[internal]';
        $what  = (isset($f['class']) ? $f['class'] . ($f['type'] ?? '::') : '') . ($f['function'] ?? '?');
        $out[] = $what . '@' . $where;
    }
    return $out ? implode(' → ', $out) : 'top-level';
}

/**
 * AJAX: clear all rollback-related options/transients.
 * Usage: POST /wp-admin/admin-ajax.php  action=hozio_clear_rollback_data + nonce
 */
add_action('wp_ajax_hozio_clear_rollback_data', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
    }
    check_ajax_referer('hozio_rollback_nonce', 'nonce');

    $cleared = array();
    foreach (array(
        'hozio_version_history',
        'hozio_pre_upgrade_version',
        'hozio_auto_updates_paused_until',
    ) as $opt) {
        if (get_option($opt, '__missing__') !== '__missing__') {
            delete_option($opt);
            $cleared[] = $opt;
        }
    }
    foreach (array(
        'hozio_plugin_update_cache',
        'hozio_all_releases_cache',
    ) as $tr) {
        delete_transient($tr);
        $cleared[] = $tr . ' (transient)';
    }
    delete_site_transient('update_plugins');
    $cleared[] = 'update_plugins (site transient)';

    hozio_rollback_debug_log('Force-clear executed by user_id=' . get_current_user_id() . '; cleared: ' . implode(', ', $cleared));

    wp_send_json_success(['cleared' => $cleared]);
});

/**
 * AJAX: clear the rollback debug log.
 */
add_action('wp_ajax_hozio_clear_rollback_debug_log', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
    }
    check_ajax_referer('hozio_rollback_nonce', 'nonce');
    delete_option('hozio_rollback_debug_log');
    wp_send_json_success();
});

// ─── License Gate ─────────────────────────────────────────────────────────────

/**
 * Returns true only when the site has an active license.
 * Hub-connected: checks Hub license status. Manual: checks that a key is set.
 */
function hozio_is_license_active() {
    if (class_exists('Hozio_Hub_Client') && Hozio_Hub_Client::is_connected()) {
        return Hozio_Hub_Client::get_license_status() === 'active';
    }
    return !empty(get_option('hozio_license_key', ''));
}

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
    $current  = hozio_get_plugin_version();
    hozio_rollback_debug_log("upgrader_process_complete: plugin updated from v{$previous} to v{$current}");
    hozio_record_version_snapshot($current, 'auto_update', $previous);
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
function hozio_perform_rollback($target_version, $from_hub = false) {
    $target_version = ltrim(sanitize_text_field($target_version), 'vV');

    // Debug log — capture WHO called this and WHY (helps diagnose unwanted rollbacks)
    hozio_rollback_debug_log(sprintf(
        'hozio_perform_rollback() called: target=v%s from_hub=%s | caller=%s | request_uri=%s | user=%d',
        $target_version,
        $from_hub ? 'yes' : 'no',
        hozio_rollback_caller_chain(),
        isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : 'cli',
        get_current_user_id()
    ));

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
    $is_upgrade = version_compare($target_version, $current_version, '>');
    if ($from_hub) {
        $snap_method = $is_upgrade ? 'hub_update' : 'hub_rollback';
    } else {
        $snap_method = $is_upgrade ? 'update' : 'rollback';
    }
    hozio_record_version_snapshot($target_version, $snap_method, $current_version);
    delete_option('hozio_pre_upgrade_version');

    if (!$is_upgrade) {
        // Block auto-updates immediately so WP-Cron can't re-install the latest
        // version before the user makes a choice in the modal. Default: 1 hour.
        // The modal lets the user extend, keep, or disable permanently.
        update_option('hozio_auto_updates_paused_until', time() + HOUR_IN_SECONDS);

        // Remove our plugin from WordPress's update_plugins transient so the
        // cron job doesn't see an available update on the very next page load.
        $upd = get_site_transient('update_plugins');
        if ($upd && isset($upd->response[$plugin_file])) {
            unset($upd->response[$plugin_file]);
            set_site_transient('update_plugins', $upd);
        }
    }

    if (function_exists('activate_plugin')) {
        activate_plugin($plugin_file);
    }

    hozio_audit_log("Rollback complete: {$current_version} → {$target_version}", 'Rollback');

    // Notify Hub immediately so it sees the new version
    if (!wp_next_scheduled('hozio_hub_heartbeat_post_update')) {
        wp_schedule_single_event(time() + 5, 'hozio_hub_heartbeat_post_update');
    }

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

    if (!hozio_is_license_active()) {
        wp_send_json_error('A valid active license is required to install plugin versions. Please check your license status in the Hub.');
    }

    $version = sanitize_text_field($_POST['version'] ?? '');
    if (empty($version)) wp_send_json_error('Version is required.');

    @set_time_limit(300);

    $current_before = hozio_get_plugin_version();
    $result = hozio_perform_rollback($version);

    if ($result['success']) {
        $result['is_downgrade'] = version_compare($version, $current_before, '<');
        $result['auto_updates_enabled'] = get_option('hozio_auto_updates_enabled', '1') === '1';
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_hozio_rollback_to_version', 'hozio_ajax_rollback_to_version');

function hozio_ajax_set_auto_update_pause() {
    check_ajax_referer('hozio_rollback_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

    $choice = sanitize_text_field($_POST['choice'] ?? '');
    $hours  = (int) ($_POST['hours'] ?? 0);

    if ($choice === 'pause' && $hours > 0) {
        update_option('hozio_auto_updates_paused_until', time() + ($hours * HOUR_IN_SECONDS));
        wp_send_json_success(['message' => "Auto-updates paused for {$hours} hour(s)."]);
    } elseif ($choice === 'disable') {
        update_option('hozio_auto_updates_enabled', '0');
        delete_option('hozio_auto_updates_paused_until');
        wp_send_json_success(['message' => 'Auto-updates disabled.']);
    } elseif ($choice === 'keep') {
        delete_option('hozio_auto_updates_paused_until');
        wp_send_json_success(['message' => 'Auto-updates kept on.']);
    } else {
        wp_send_json_error('Invalid choice.');
    }
}
add_action('wp_ajax_hozio_set_auto_update_pause', 'hozio_ajax_set_auto_update_pause');

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
