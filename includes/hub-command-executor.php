<?php
/**
 * Hozio Command Executor
 * Dispatches Tier 1 quick commands and Tier 2 REST API proxy requests
 * Self-protection applied to ALL tiers
 */

if (!defined('ABSPATH')) exit;

class Hozio_Command_Executor {


    /**
     * Execute a command
     *
     * @param string $command_type    Command identifier
     * @param array  $command_payload Command parameters
     * @return array { success: bool, data?: mixed, error?: string }
     */
    public static function execute($command_type, $command_payload = []) {
        try {
            // Set admin context for operations
            self::set_admin_context();

            switch ($command_type) {
                // Tier 1: Quick Commands
                case 'trash_page':
                    return self::cmd_trash_page($command_payload);
                case 'delete_page':
                    return self::cmd_delete_page($command_payload);
                case 'restore_page':
                    return self::cmd_restore_page($command_payload);
                case 'change_page_status':
                    return self::cmd_change_page_status($command_payload);
                case 'deactivate_plugin':
                    return self::cmd_deactivate_plugin($command_payload);
                case 'activate_plugin':
                    return self::cmd_activate_plugin($command_payload);
                case 'uninstall_plugin':
                    return self::cmd_uninstall_plugin($command_payload);
                case 'update_option':
                    return self::cmd_update_option($command_payload);
                case 'create_admin_login':
                    return self::cmd_create_admin_login($command_payload);
                case 'remove_admin_login':
                    return self::cmd_remove_admin_login($command_payload);

                // Plugin patching
                case 'run_plugin_updates':
                    return self::cmd_run_plugin_updates($command_payload);
                case 'update_plugin':
                    return self::cmd_update_plugin($command_payload);

                // Update holds and the site-wide freeze (includes/update-holds.php)
                case 'hold_plugin_updates':
                    return self::cmd_hold_plugin_updates($command_payload);
                case 'release_plugin_updates':
                    return self::cmd_release_plugin_updates($command_payload);
                case 'freeze_updates':
                    return self::cmd_freeze_updates($command_payload);
                case 'unfreeze_updates':
                    return self::cmd_unfreeze_updates($command_payload);

                // Rollback
                case 'rollback_plugin':
                    return self::cmd_rollback_plugin($command_payload);

                // Tier 2: REST API Proxy
                case 'rest_api_proxy':
                    return self::cmd_rest_api_proxy($command_payload);

                default:
                    return [
                        'success' => false,
                        'error'   => 'Unknown command type: ' . $command_type,
                    ];
            }
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Set the current user to an administrator for command execution
     */
    private static function set_admin_context() {
        $admins = get_users([
            'role'    => 'administrator',
            'number'  => 1,
            'orderby' => 'ID',
        ]);

        if (!empty($admins)) {
            wp_set_current_user($admins[0]->ID);
        }
    }


    /**
     * Self-protection for REST API routes
     *
     * @param string $endpoint REST API endpoint
     * @return bool True if the route targets Hozio Pro
     */
    private static function is_protected_route($endpoint) {
        return strpos($endpoint, 'hozio-dynamic-tags') !== false
            || strpos($endpoint, 'hozio-pro') !== false;
    }

    /**
     * Check if a plugin file refers to Hozio Pro (self-protection)
     */
    private static function is_hozio_plugin($plugin_file) {
        $plugin_file = strtolower(trim($plugin_file));
        return strpos($plugin_file, 'hozio-dynamic-tags') !== false
            || strpos($plugin_file, 'hozio-pro') !== false;
    }

    // ─── Tier 1: Quick Commands ──────────────────────────────────────

    /**
     * Trash a page
     */
    private static function cmd_trash_page($payload) {
        $page_id = (int) ($payload['page_id'] ?? 0);
        if (!$page_id) {
            return ['success' => false, 'error' => 'page_id is required.'];
        }

        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'error' => 'Page not found.'];
        }

        if ($post->post_type !== 'page') {
            return ['success' => false, 'error' => 'Post is not a page (type: ' . $post->post_type . ').'];
        }

        $result = wp_trash_post($page_id);
        if (!$result) {
            return ['success' => false, 'error' => 'Failed to trash page.'];
        }

        return ['success' => true, 'data' => ['page_id' => $page_id, 'status' => 'trashed']];
    }

    /**
     * Permanently delete a page
     */
    private static function cmd_delete_page($payload) {
        $page_id = (int) ($payload['page_id'] ?? 0);
        if (!$page_id) {
            return ['success' => false, 'error' => 'page_id is required.'];
        }

        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'error' => 'Page not found.'];
        }

        if ($post->post_type !== 'page') {
            return ['success' => false, 'error' => 'Post is not a page (type: ' . $post->post_type . ').'];
        }

        $result = wp_delete_post($page_id, true);
        if (!$result) {
            return ['success' => false, 'error' => 'Failed to delete page.'];
        }

        return ['success' => true, 'data' => ['page_id' => $page_id, 'status' => 'deleted']];
    }

    /**
     * Restore a trashed page
     */
    private static function cmd_restore_page($payload) {
        $page_id = (int) ($payload['page_id'] ?? 0);
        if (!$page_id) {
            return ['success' => false, 'error' => 'page_id is required.'];
        }

        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'error' => 'Page not found.'];
        }

        if ($post->post_type !== 'page') {
            return ['success' => false, 'error' => 'Post is not a page.'];
        }

        $result = wp_untrash_post($page_id);
        if (!$result) {
            return ['success' => false, 'error' => 'Failed to restore page.'];
        }

        return ['success' => true, 'data' => ['page_id' => $page_id, 'status' => 'restored']];
    }

    /**
     * Change a page's status (publish, draft, pending, private)
     */
    private static function cmd_change_page_status($payload) {
        $page_id    = (int) ($payload['page_id'] ?? 0);
        $new_status = $payload['status'] ?? '';

        if (!$page_id) {
            return ['success' => false, 'error' => 'page_id is required.'];
        }

        $allowed = ['publish', 'draft', 'pending', 'private'];
        if (!in_array($new_status, $allowed, true)) {
            return ['success' => false, 'error' => 'Invalid status. Allowed: ' . implode(', ', $allowed)];
        }

        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'error' => 'Page not found.'];
        }

        if ($post->post_type !== 'page') {
            return ['success' => false, 'error' => 'Post is not a page (type: ' . $post->post_type . ').'];
        }

        $result = wp_update_post([
            'ID'          => $page_id,
            'post_status' => $new_status,
        ], true);

        if (is_wp_error($result)) {
            return ['success' => false, 'error' => $result->get_error_message()];
        }

        return ['success' => true, 'data' => ['page_id' => $page_id, 'status' => $new_status]];
    }

    /**
     * Deactivate a plugin
     */
    private static function cmd_deactivate_plugin($payload) {
        $plugin_file = $payload['plugin_file'] ?? '';
        if (empty($plugin_file)) {
            return ['success' => false, 'error' => 'plugin_file is required.'];
        }

        // Self-protection: never allow Hozio Pro to deactivate itself
        if (self::is_hozio_plugin($plugin_file)) {
            hozio_audit_log("BLOCKED deactivation attempt on Hozio Pro (plugin_file: {$plugin_file})", 'SelfProtect');
            return ['success' => false, 'error' => 'Cannot deactivate Hozio Pro — self-protection enabled.'];
        }

        hozio_audit_log("Deactivating plugin: {$plugin_file}", 'PluginCmd');

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins($plugin_file);

        // Verify it actually deactivated
        if (is_plugin_active($plugin_file)) {
            hozio_audit_log("Failed to deactivate plugin: {$plugin_file}", 'PluginCmd');
            return ['success' => false, 'error' => 'Plugin could not be deactivated (may be required by another plugin).'];
        }

        hozio_audit_log("Successfully deactivated plugin: {$plugin_file}", 'PluginCmd');
        return ['success' => true, 'data' => ['plugin' => $plugin_file, 'status' => 'deactivated']];
    }

    /**
     * Activate a plugin
     */
    private static function cmd_activate_plugin($payload) {
        $plugin_file = $payload['plugin_file'] ?? '';
        if (empty($plugin_file)) {
            return ['success' => false, 'error' => 'plugin_file is required.'];
        }

        hozio_audit_log("Activating plugin: {$plugin_file}", 'PluginCmd');

        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            hozio_audit_log("Failed to activate plugin: {$plugin_file} — " . $result->get_error_message(), 'PluginCmd');
            return ['success' => false, 'error' => $result->get_error_message()];
        }

        hozio_audit_log("Successfully activated plugin: {$plugin_file}", 'PluginCmd');
        return ['success' => true, 'data' => ['plugin' => $plugin_file, 'status' => 'activated']];
    }

    /**
     * Uninstall (deactivate + delete) a plugin
     */
    private static function cmd_uninstall_plugin($payload) {
        $plugin_file = $payload['plugin_file'] ?? '';
        if (empty($plugin_file)) {
            return ['success' => false, 'error' => 'plugin_file is required.'];
        }

        // Self-protection: never allow Hozio Pro to uninstall itself
        if (self::is_hozio_plugin($plugin_file)) {
            hozio_audit_log("BLOCKED uninstall attempt on Hozio Pro (plugin_file: {$plugin_file})", 'SelfProtect');
            return ['success' => false, 'error' => 'Cannot uninstall Hozio Pro — self-protection enabled.'];
        }

        hozio_audit_log("Uninstalling plugin: {$plugin_file}", 'PluginCmd');

        if (!function_exists('deactivate_plugins') || !function_exists('delete_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Check filesystem method
        if (get_filesystem_method() !== 'direct') {
            hozio_audit_log("Cannot uninstall {$plugin_file} — FTP credentials required", 'PluginCmd');
            return ['success' => false, 'error' => 'Cannot delete plugins — FTP credentials required. Direct filesystem access is not available.'];
        }

        // Deactivate first
        deactivate_plugins($plugin_file);

        // Delete
        $result = delete_plugins([$plugin_file]);
        if (is_wp_error($result)) {
            hozio_audit_log("Failed to delete plugin: {$plugin_file} — " . $result->get_error_message(), 'PluginCmd');
            return ['success' => false, 'error' => $result->get_error_message()];
        }

        hozio_audit_log("Successfully uninstalled plugin: {$plugin_file}", 'PluginCmd');
        return ['success' => true, 'data' => ['plugin' => $plugin_file, 'status' => 'uninstalled']];
    }

    /**
     * Update a WordPress option (restricted to hozio_ prefix)
     */
    private static function cmd_update_option($payload) {
        $option_name  = $payload['option_name'] ?? '';
        $option_value = $payload['option_value'] ?? null;

        if (empty($option_name)) {
            return ['success' => false, 'error' => 'option_name is required.'];
        }

        // Restrict to hozio_ prefixed options only
        if (!is_string($option_name) || strpos($option_name, 'hozio_') !== 0) {
            return ['success' => false, 'error' => 'Only hozio_ prefixed options can be updated remotely.'];
        }

        // Lower case letters, digits, _ and - only. MySQL compares option names without
        // regard to case or trailing spaces, so "hozio_Hub_site_token" or a name with a
        // trailing space would pass the checks below as a PHP string yet overwrite the
        // protected row in the database. \z, not $: $ also matches before a final newline.
        if (!preg_match('/^hozio_[a-z0-9_\-]+\z/', $option_name)) {
            return ['success' => false, 'error' => 'Option names may only contain lower-case letters, digits, _ and -.'];
        }

        // Block hub connection options from remote modification
        if (strpos($option_name, 'hozio_hub_') === 0) {
            return ['success' => false, 'error' => 'Hub connection options cannot be modified via remote commands.'];
        }

        // Block the private log's bookkeeping (hozio_log_db_version, hozio_log_legacy_state).
        // Written remotely, they could stop the log tables being recreated or stop an old
        // public log file from being deleted.
        if (strpos($option_name, 'hozio_log_') === 0) {
            return ['success' => false, 'error' => 'Log storage options cannot be modified via remote commands.'];
        }

        // Block update holds and the freeze. They have their own commands, which validate
        // and audit-log every change; a raw write here would do neither.
        if (in_array($option_name, ['hozio_update_holds', 'hozio_update_freeze'], true)) {
            return ['success' => false, 'error' => 'Update holds and the freeze cannot be written directly. Use hold_plugin_updates, release_plugin_updates, freeze_updates or unfreeze_updates.'];
        }

        update_option($option_name, $option_value);

        return ['success' => true, 'data' => ['option' => $option_name, 'value' => $option_value]];
    }

    /**
     * The Hub's temporary support login.
     *
     * Deliberately NOT "hoziowpadmin". That account belongs to the Hozio Studio Orchestrator
     * on every site: its shared password is the orchestrator's login, and its application
     * passwords are the orchestrator's REST keys. When these commands used that name,
     * create_admin_login reset its password and remove_admin_login deleted it, application
     * passwords and all. The Hub now gets an account of its own, and neither command will
     * ever touch hoziowpadmin.
     */
    const HUB_SUPPORT_LOGIN  = 'hozio-hub-support';
    const HUB_SUPPORT_EMAIL  = 'hozio-hub-support@localhost.invalid';
    const HUB_SUPPORT_MARKER = '_hozio_hub_support_account';

    /**
     * Accounts these commands must never create, reset or delete.
     */
    private static function is_reserved_login($username) {
        return in_array(strtolower((string) $username), ['hoziowpadmin'], true);
    }

    /**
     * Was this account created by create_admin_login? Only then may the Hub reset or
     * delete it — an unrelated person who happens to use the name is left alone.
     */
    private static function is_hub_support_account($user) {
        if (!$user || self::is_reserved_login($user->user_login)) {
            return false;
        }
        return get_user_meta($user->ID, self::HUB_SUPPORT_MARKER, true) === '1'
            || strtolower((string) $user->user_email) === self::HUB_SUPPORT_EMAIL;
    }

    /**
     * Create a temporary admin login (or reset password if exists)
     */
    private static function cmd_create_admin_login($payload) {
        $username = self::HUB_SUPPORT_LOGIN;
        if (self::is_reserved_login($username)) {
            return ['success' => false, 'error' => 'Refusing to touch a reserved account.'];
        }

        // Generate a strong, random one-time password. It is returned ONCE in this
        // command result so the operator can use it, and is never stored in an option
        // or written to a log. Each create/reset issues a fresh password.
        $password = wp_generate_password(20, true, false);
        $email    = self::HUB_SUPPORT_EMAIL;

        $existing = get_user_by('login', $username);

        if ($existing) {
            if (!self::is_hub_support_account($existing)) {
                return ['success' => false, 'error' => 'An account named ' . $username . ' exists that the Hub did not create; it was left alone.'];
            }
            // Reset password on existing account
            wp_set_password($password, $existing->ID);
            hozio_audit_log(sprintf('Hub reset the password of the temporary support login %s (user ID %d)', $username, $existing->ID), 'HubLogin');
            return [
                'success' => true,
                'data'    => [
                    'user_id'   => $existing->ID,
                    'username'  => $username,
                    'password'  => $password,
                    'login_url' => wp_login_url(),
                    'note'      => 'Existing account — password reset.',
                ],
            ];
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass'  => $password,
            'user_email' => $email,
            'role'       => 'administrator',
            'display_name' => 'Hozio Support',
        ]);

        if (is_wp_error($user_id)) {
            return ['success' => false, 'error' => $user_id->get_error_message()];
        }

        update_user_meta($user_id, self::HUB_SUPPORT_MARKER, '1');
        hozio_audit_log(sprintf('Hub created the temporary support login %s (user ID %d)', $username, $user_id), 'HubLogin');

        return [
            'success' => true,
            'data'    => [
                'user_id'   => $user_id,
                'username'  => $username,
                'password'  => $password,
                'login_url' => wp_login_url(),
            ],
        ];
    }

    /**
     * Remove the temporary admin login
     */
    private static function cmd_remove_admin_login($payload) {
        $username = self::HUB_SUPPORT_LOGIN;
        $user = get_user_by('login', $username);

        if (!$user) {
            return ['success' => false, 'error' => 'Login account not found.'];
        }

        if (!self::is_hub_support_account($user)) {
            return ['success' => false, 'error' => 'The account named ' . $username . ' was not created by the Hub; it was left alone.'];
        }

        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        // Reassign content to the first real admin
        $admins = get_users([
            'role'    => 'administrator',
            'number'  => 1,
            'orderby' => 'ID',
            'exclude' => [$user->ID],
        ]);
        $reassign_to = !empty($admins) ? $admins[0]->ID : null;

        wp_delete_user($user->ID, $reassign_to);
        hozio_audit_log(sprintf('Hub removed the temporary support login %s (user ID %d)', $username, $user->ID), 'HubLogin');

        return [
            'success' => true,
            'data'    => [
                'user_id'  => $user->ID,
                'username' => $username,
            ],
        ];
    }

    // ─── Plugin patching ─────────────────────────────────────────────

    /**
     * Run this site's normal plugin update process now, instead of waiting for the
     * twice-daily schedule.
     *
     * Delegates to hozio_run_plugin_auto_updates_now(), which is the same code path the
     * settings-page button uses. That matters: it declares cron context (so core does NOT
     * silently deactivate plugins mid-upgrade), arms the crash guard, releases a stale
     * auto-updater lock, and restricts the run to plugins. Reimplementing any of that here
     * would reintroduce bugs already fixed there.
     *
     * Payload: { max?: int }  — optional tighter cap for this run only.
     */
    private static function cmd_run_plugin_updates($payload) {
        if (!function_exists('hozio_run_plugin_auto_updates_now')) {
            return ['success' => false, 'error' => 'Auto-update module not available on this site.'];
        }

        // A freeze (or maintenance mode someone switched on) beats a Hub-triggered run.
        if (function_exists('hozio_update_run_refusal')) {
            $refusal = hozio_update_run_refusal();
            if ($refusal !== '') {
                return ['success' => false, 'error' => $refusal];
            }
        }

        // A site that physically cannot write updates should say so, rather than
        // reporting "0 updated" and looking healthy.
        if (function_exists('hozio_auto_update_blocked_reason')) {
            $blocked = hozio_auto_update_blocked_reason();
            if (!empty($blocked)) {
                return ['success' => false, 'error' => 'Updates are blocked on this site: ' . $blocked];
            }
        }

        if (function_exists('hozio_auto_update_all_enabled') && !hozio_auto_update_all_enabled()) {
            return ['success' => false, 'error' => 'Auto-Update All Plugins is switched off on this site.'];
        }

        $max = isset($payload['max']) ? (int) $payload['max'] : 0;

        hozio_audit_log('Hub triggered a plugin update run', 'AutoUpdate');

        $result = hozio_run_plugin_auto_updates_now($max);

        // Report failure honestly. hub-client.php derives the Hub-visible command status
        // straight from this flag, so returning true with failures would mark a site
        // "completed" when an update actually did not install.
        return [
            'success' => empty($result['failed']),
            'data'    => $result,
            'error'   => empty($result['failed'])
                ? null
                : sprintf('%d plugin update(s) failed: %s', (int) $result['failed'], implode(' · ', (array) $result['messages'])),
        ];
    }

    /**
     * Update ONE named plugin, leaving everything else alone.
     *
     * The surgical option: push a single security release fleet-wide, or bring one site
     * back in line, without touching any other plugin.
     *
     * Implemented by constraining the normal update run rather than calling
     * Plugin_Upgrader directly. Driving the upgrader by hand would re-enter the bug fixed
     * in 4.15.3 — outside cron context core hooks deactivate_plugin_before_upgrade() and
     * switches the plugin off before swapping its files, with nothing turning it back on.
     * Constraining the hardened path keeps the crash guard, lock handling, maintenance
     * mode and cron semantics intact.
     *
     * Payload: { plugin: "folder/file.php", force?: bool }
     *   force = override this site's exclusion list, the major-version guard, AND the
     *   site's "Auto-Update All Plugins" switch. That last one overrides a decision the
     *   site owner made deliberately, so it is audit-logged as such — intended for an
     *   emergency security patch, not routine use.
     */
    private static function cmd_update_plugin($payload) {
        $plugin_file = isset($payload['plugin']) ? trim((string) $payload['plugin']) : '';
        $force       = !empty($payload['force']);

        if ($plugin_file === '') {
            return ['success' => false, 'error' => 'A plugin file is required.'];
        }

        if (!function_exists('hozio_run_plugin_auto_updates_now')) {
            return ['success' => false, 'error' => 'Auto-update module not available on this site.'];
        }

        // Self-protection, consistent with deactivate_plugin / uninstall_plugin. Changing
        // the Hozio Pro version goes through rollback_plugin, which carries the license,
        // version-lock and rollback-pause guards this path does not.
        if (self::is_hozio_plugin($plugin_file)) {
            return ['success' => false, 'error' => 'Use rollback_plugin to change the Hozio Pro version.'];
        }

        if (function_exists('hozio_auto_update_blocked_reason')) {
            $blocked = hozio_auto_update_blocked_reason();
            if (!empty($blocked)) {
                return ['success' => false, 'error' => 'Updates are blocked on this site: ' . $blocked];
            }
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed = get_plugins();
        if (!isset($installed[$plugin_file])) {
            return ['success' => false, 'error' => 'That plugin is not installed on this site.'];
        }

        // A freeze beats this command, force or not.
        if (function_exists('hozio_update_run_refusal')) {
            $refusal = hozio_update_run_refusal();
            if ($refusal !== '') {
                return ['success' => false, 'error' => $refusal];
            }
        }

        // So does a hold. The $only filter below sits at PHP_INT_MAX and runs after the hold
        // filter, so with force it would otherwise override the hold. Only
        // release_plugin_updates lifts a hold.
        if (function_exists('hozio_update_hold_get_active')) {
            $hold = hozio_update_hold_get_active($plugin_file);
            if ($hold) {
                $pending = get_site_transient('update_plugins');
                $target  = ($pending && isset($pending->response[$plugin_file]->new_version)) ? $pending->response[$plugin_file]->new_version : '';
                // A pin refuses outright. A skip hold refuses when the pending version is one
                // it names; if nothing is pending yet, the filter checks during the run.
                if ($hold['mode'] === 'pin' || ($target !== '' && hozio_update_hold_blocks($plugin_file, $target))) {
                    hozio_audit_log(sprintf('Refused a Hub update of %s%s: it is held until %s (%s)', $plugin_file, $force ? ' (forced)' : '', hozio_hold_iso($hold['expires_at']), $hold['reason']), 'AutoUpdate');
                    return ['success' => false, 'error' => sprintf('%s is held until %s (%s, by %s). Send release_plugin_updates first.', $plugin_file, hozio_hold_iso($hold['expires_at']), hozio_hold_public_text($hold['reason'], true), hozio_hold_public_text($hold['source'], true))];
                }
            }
        }

        // Leave plugins owned by another updater alone unless explicitly forced —
        // two systems writing the same directory is how directories get corrupted.
        if (!$force
            && function_exists('hozio_plugin_managed_by_git_updater')
            && hozio_plugin_managed_by_git_updater($plugin_file)) {
            return ['success' => false, 'error' => 'That plugin is managed by Git Updater on this site.'];
        }

        // Respect the site's master switch. Without this, force silently patched sites
        // whose owner had deliberately turned fleet auto-updating off.
        if (function_exists('hozio_auto_update_all_enabled') && !hozio_auto_update_all_enabled()) {
            if (!$force) {
                return ['success' => false, 'error' => 'Auto-Update All Plugins is switched off on this site. Send force to override.'];
            }
            hozio_audit_log(
                sprintf('Hub FORCED an update of %s despite auto-updates being switched off on this site', $plugin_file),
                'AutoUpdate'
            );
        }

        // Constrain the run to this one plugin. Highest priority so it is the last word,
        // and it runs AFTER the normal filter: returning $update for the target preserves
        // this site's exclusions and major-version guard, while force overrides both.
        $only = function ($update, $item) use ($plugin_file, $force) {
            $file = isset($item->plugin) ? (string) $item->plugin : '';
            if ($file !== $plugin_file) {
                return false;
            }
            // Checked again here in case the update the run finds is a version a skip hold
            // names, which the check above could not see before the run looked.
            if (function_exists('hozio_update_hold_blocks')
                && hozio_update_hold_blocks($file, isset($item->new_version) ? $item->new_version : '')) {
                return false;
            }
            return $force ? true : $update;
        };
        add_filter('auto_update_plugin', $only, PHP_INT_MAX, 2);

        // Lift the per-run cap for this run ONLY.
        //
        // Not an optimisation — without it a targeted update can silently do nothing.
        // The normal filter counts every plugin IT approves, and it runs before the
        // constraint above. With three other updates pending, those three consume the
        // cap first and the target is then refused for being over the limit, even
        // though none of them would actually have installed. Lifting the cap is safe
        // precisely because $only guarantees exactly one plugin can pass.
        $nocap = function () { return PHP_INT_MAX; };
        add_filter('hozio_max_plugin_auto_updates_per_run', $nocap, PHP_INT_MAX);

        hozio_audit_log(
            sprintf('Hub triggered a targeted update of %s%s', $plugin_file, $force ? ' (forced)' : ''),
            'AutoUpdate'
        );

        // try/finally so a throw inside the run cannot leave these filters registered.
        // A heartbeat can deliver several commands in one request; a leaked $only would
        // block every plugin in whatever ran next.
        try {
            // 0 = don't add a second cap filter; $nocap above already governs this run.
            $result = hozio_run_plugin_auto_updates_now(0);
        } finally {
            remove_filter('auto_update_plugin', $only, PHP_INT_MAX);
            remove_filter('hozio_max_plugin_auto_updates_per_run', $nocap, PHP_INT_MAX);
        }

        // Nothing happened: say why, rather than reporting a hollow success.
        if (empty($result['updated']) && empty($result['failed'])) {
            $current = isset($installed[$plugin_file]['Version']) ? $installed[$plugin_file]['Version'] : '?';
            return [
                'success' => true,
                'data'    => array_merge($result, [
                    'note' => sprintf(
                        'No update applied for %s (installed %s). It is already current, excluded on this site, or the available release is a major version — send force to override.',
                        $plugin_file,
                        $current
                    ),
                ]),
            ];
        }

        // Same honesty as above: a failed install must not report as completed.
        return [
            'success' => empty($result['failed']),
            'data'    => $result,
            'error'   => empty($result['failed'])
                ? null
                : sprintf('Update of %s failed: %s', $plugin_file, implode(' · ', (array) $result['messages'])),
        ];
    }

    // ─── Rollback ────────────────────────────────────────────────────

    private static function cmd_rollback_plugin($payload) {
        $version = isset($payload['version']) && is_string($payload['version']) ? ltrim(sanitize_text_field($payload['version']), 'vV') : '';
        if ($version === '') {
            return ['success' => false, 'error' => 'version is required.'];
        }

        if (!function_exists('hozio_perform_rollback')) {
            return ['success' => false, 'error' => 'Rollback system not loaded.'];
        }

        // A freeze means nothing changes on this site until it is lifted, Hozio Pro included.
        if (function_exists('hozio_updates_frozen') && hozio_updates_frozen()) {
            return ['success' => false, 'error' => 'Updates are frozen on this site. Send unfreeze_updates first.'];
        }

        $before = hozio_get_plugin_version();

        hozio_audit_log("Hub-triggered rollback to v{$version}", 'Rollback');

        $result = hozio_perform_rollback($version, true);

        if ($result['success']) {
            if (version_compare($version, $before, '<') && function_exists('hozio_update_hold_place')) {
                // A DOWNGRADE. Previously this switched auto-updates back on, and the only
                // thing then stopping WordPress reinstalling the release just rolled back
                // was a one-hour pause. Hold Hozio Pro instead, for the default 30 days,
                // until someone releases it.
                $hold = hozio_update_hold_place(plugin_basename(HOZIO_PLUGIN_FILE), [
                    'mode'     => 'pin',
                    'versions' => [$before],
                    'reason'   => sprintf('Hub rolled Hozio Pro back from %s to %s', $before, $version),
                    'source'   => 'hub',
                ]);
                $result['hold'] = is_wp_error($hold) ? ['ok' => false, 'error' => $hold->get_error_message()] : $hold;
            } else {
                update_option('hozio_auto_updates_enabled', '1');
                // Same version or newer: lift the hold an earlier Hub downgrade placed, or
                // Hozio Pro would stop self-updating for up to 30 days. Only a hold the Hub
                // placed; an orchestrator, wp-admin or WP-CLI hold stays until its owner
                // releases it.
                $result['hold_released'] = function_exists('hozio_update_hold_release_hub_self')
                    && hozio_update_hold_release_hub_self(sprintf('Hub installed Hozio Pro %s (was %s)', $version, $before));
            }
        }

        return [
            'success' => $result['success'],
            'data'    => $result,
        ];
    }

    // ─── Update holds and freeze ─────────────────────────────────────
    //
    // The same functions `wp hozio updates ...` calls (includes/update-holds.php), with the
    // same validation and audit logging. The source is always "hub", whatever the payload says.

    /**
     * Turn an update-holds result into a command result.
     */
    private static function hold_result($result) {
        if (is_wp_error($result)) {
            return ['success' => false, 'error' => $result->get_error_message(), 'code' => $result->get_error_code()];
        }
        return ['success' => true, 'data' => $result];
    }

    /**
     * Payload value as a string, or null when it isn't one (so validation rejects it).
     */
    private static function payload_string($payload, $key) {
        if (!isset($payload[$key])) {
            return null;
        }
        return (is_string($payload[$key]) || is_int($payload[$key])) ? (string) $payload[$key] : null;
    }

    /**
     * Payload: { plugin, reason, mode?: pin|skip, versions?: [..] | "a,b", until?: "+30d" | "YYYY-MM-DD", ref? }
     */
    private static function cmd_hold_plugin_updates($payload) {
        if (!function_exists('hozio_update_hold_place')) {
            return ['success' => false, 'error' => 'Update holds are not available on this site.'];
        }
        return self::hold_result(hozio_update_hold_place(self::payload_string($payload, 'plugin'), [
            'reason'   => self::payload_string($payload, 'reason'),
            'mode'     => self::payload_string($payload, 'mode'),
            'versions' => isset($payload['versions']) ? $payload['versions'] : null,
            'until'    => self::payload_string($payload, 'until'),
            'ref'      => self::payload_string($payload, 'ref'),
            'source'   => 'hub',
        ]));
    }

    /**
     * Payload: { plugin, reason }
     */
    private static function cmd_release_plugin_updates($payload) {
        if (!function_exists('hozio_update_hold_release')) {
            return ['success' => false, 'error' => 'Update holds are not available on this site.'];
        }
        return self::hold_result(hozio_update_hold_release(self::payload_string($payload, 'plugin'), [
            'reason' => self::payload_string($payload, 'reason'),
            'source' => 'hub',
        ]));
    }

    /**
     * Payload: { until: "+1d" | "YYYY-MM-DD" (at most 7 days), reason, ref? }
     */
    private static function cmd_freeze_updates($payload) {
        if (!function_exists('hozio_updates_freeze')) {
            return ['success' => false, 'error' => 'Update freeze is not available on this site.'];
        }
        return self::hold_result(hozio_updates_freeze([
            'until'  => self::payload_string($payload, 'until'),
            'reason' => self::payload_string($payload, 'reason'),
            'ref'    => self::payload_string($payload, 'ref'),
            'source' => 'hub',
        ]));
    }

    /**
     * Payload: { reason }
     */
    private static function cmd_unfreeze_updates($payload) {
        if (!function_exists('hozio_updates_unfreeze')) {
            return ['success' => false, 'error' => 'Update freeze is not available on this site.'];
        }
        return self::hold_result(hozio_updates_unfreeze([
            'reason' => self::payload_string($payload, 'reason'),
            'source' => 'hub',
        ]));
    }

    // ─── Tier 2: REST API Proxy ──────────────────────────────────────

    /**
     * Execute an internal WordPress REST API request
     */
    private static function cmd_rest_api_proxy($payload) {
        $method   = strtoupper($payload['method'] ?? 'GET');
        $endpoint = $payload['endpoint'] ?? '';
        $body     = $payload['body'] ?? null;

        if (empty($endpoint)) {
            return ['success' => false, 'error' => 'endpoint is required.'];
        }

        // Validate method
        $allowed_methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array($method, $allowed_methods)) {
            return ['success' => false, 'error' => 'Invalid HTTP method: ' . $method];
        }

        // Self-protection: block routes targeting Hozio Pro
        if (self::is_protected_route($endpoint)) {
            return ['success' => false, 'error' => 'Cannot execute REST API operations targeting Hozio Pro — self-protection enabled.'];
        }

        // Build internal REST request
        $request = new WP_REST_Request($method, $endpoint);

        if ($body && is_array($body)) {
            foreach ($body as $key => $value) {
                $request->set_param($key, $value);
            }

            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $request->set_header('Content-Type', 'application/json');
                $request->set_body(wp_json_encode($body));
            }
        }

        // Execute internally (no HTTP round-trip)
        $response = rest_do_request($request);
        $server   = rest_get_server();
        $data     = $server->response_to_data($response, false);

        return [
            'success'     => !$response->is_error(),
            'data'        => $data,
            'status_code' => $response->get_status(),
        ];
    }
}
