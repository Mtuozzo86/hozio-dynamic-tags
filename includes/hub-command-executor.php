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
        if (strpos($option_name, 'hozio_') !== 0) {
            return ['success' => false, 'error' => 'Only hozio_ prefixed options can be updated remotely.'];
        }

        // Block hub connection options from remote modification
        if (strpos($option_name, 'hozio_hub_') === 0) {
            return ['success' => false, 'error' => 'Hub connection options cannot be modified via remote commands.'];
        }

        update_option($option_name, $option_value);

        return ['success' => true, 'data' => ['option' => $option_name, 'value' => $option_value]];
    }

    /**
     * Create a temporary admin login (or reset password if exists)
     */
    private static function cmd_create_admin_login($payload) {
        $username = 'hoziowpadmin';
        $password = 'TempLogin123!';
        $email    = 'hoziowpadmin@localhost.invalid';

        $existing = get_user_by('login', $username);

        if ($existing) {
            // Reset password on existing account
            wp_set_password($password, $existing->ID);
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
        $username = 'hoziowpadmin';
        $user = get_user_by('login', $username);

        if (!$user) {
            return ['success' => false, 'error' => 'Login account not found.'];
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

        return [
            'success' => true,
            'data'    => [
                'user_id'  => $user->ID,
                'username' => $username,
            ],
        ];
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
