<?php
/**
 * Hozio Hub Client
 * Heartbeat, authentication, license status caching, registration flow
 */

if (!defined('ABSPATH')) exit;

class Hozio_Hub_Client {

    /**
     * Initialize Hub client hooks
     */
    public static function init() {
        // Heartbeat cron
        add_action('hozio_hub_heartbeat', [__CLASS__, 'send_heartbeat']);

        // Admin login heartbeat trigger (manage_options only)
        add_action('wp_login', [__CLASS__, 'on_admin_login'], 10, 2);
    }

    /**
     * Check if this site is connected to a Hub
     *
     * @return bool
     */
    public static function is_connected() {
        return !empty(get_option('hozio_hub_url')) && !empty(get_option('hozio_hub_site_token'));
    }

    /**
     * Register this site with the Hub
     *
     * @param string $hub_url          Hub site URL
     * @param string $registration_key One-time registration key
     * @return array|WP_Error Registration response or error
     */
    public static function register($hub_url, $registration_key) {
        $hub_url = untrailingslashit(trim($hub_url));
        $endpoint = $hub_url . '/wp-json/hozio-hub/v1/register';

        $response = wp_remote_post($endpoint, [
            'timeout'   => 30,
            'sslverify' => true,
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => wp_json_encode([
                'site_url'         => home_url(),
                'admin_email'      => get_option('admin_email'),
                'plugin_version'   => defined('HOZIO_VERSION') ? HOZIO_VERSION : '0.0.0',
                'registration_key' => $registration_key,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body)) {
            $message = $body['message'] ?? 'Registration failed with status ' . $code;
            return new WP_Error('registration_failed', $message);
        }

        // Store connection details
        update_option('hozio_hub_url', $hub_url);
        update_option('hozio_hub_site_token', $body['site_token']);
        update_option('hozio_hub_token_hash', hash('sha256', $body['hub_token']));
        update_option('hozio_hub_heartbeat_interval', $body['heartbeat_interval'] ?? 3600);
        update_option('hozio_hub_registration_time', time());

        // Clear legacy license key — Hub is now the sole license authority
        delete_option('hozio_license_key');

        // Cache the initial license status
        $license_status = $body['license_status'] ?? 'active';
        set_transient('hozio_hub_license_status', $license_status, DAY_IN_SECONDS);
        update_option('hozio_hub_last_known_status', $license_status);

        // Schedule heartbeat cron if not already scheduled
        if (!wp_next_scheduled('hozio_hub_heartbeat')) {
            $interval = (int) ($body['heartbeat_interval'] ?? 3600);
            wp_schedule_event(time() + $interval, 'hourly', 'hozio_hub_heartbeat');
        }

        return $body;
    }

    /**
     * Disconnect from the Hub
     */
    public static function disconnect() {
        delete_option('hozio_hub_url');
        delete_option('hozio_hub_site_token');
        // Keep hozio_hub_token_hash — preserves Hub direct query access after disconnect
        delete_option('hozio_hub_heartbeat_interval');
        delete_option('hozio_hub_last_known_status');
        delete_option('hozio_hub_registration_time');
        delete_option('hozio_hub_pending_results');
        delete_option('hozio_hub_executed_commands');

        delete_transient('hozio_hub_license_status');

        wp_clear_scheduled_hook('hozio_hub_heartbeat');
    }

    /**
     * Send heartbeat to Hub
     */
    public static function send_heartbeat() {
        if (!self::is_connected()) {
            return;
        }

        $hub_url    = get_option('hozio_hub_url');
        $site_token = get_option('hozio_hub_site_token');
        $endpoint   = $hub_url . '/wp-json/hozio-hub/v1/heartbeat';

        // Gather pending results
        $pending_results = get_option('hozio_hub_pending_results', []);

        $response = wp_remote_post($endpoint, [
            'timeout'   => 30,
            'sslverify' => true,
            'headers'   => [
                'Authorization' => 'Bearer ' . $site_token,
                'Content-Type'  => 'application/json',
            ],
            'body'      => wp_json_encode([
                'site_url'        => home_url(),
                'plugin_version'  => defined('HOZIO_VERSION') ? HOZIO_VERSION : '0.0.0',
                'wp_version'      => get_bloginfo('version'),
                'php_version'     => phpversion(),
                'active_plugins'  => get_option('active_plugins', []),
                'pending_results' => $pending_results,
            ]),
        ]);

        if (is_wp_error($response)) {
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body)) {
            return;
        }

        // Clear delivered pending results
        if (!empty($pending_results)) {
            update_option('hozio_hub_pending_results', []);
        }

        // Cache license status (preserve last known status on malformed response)
        $license_status = $body['license_status'] ?? get_option('hozio_hub_last_known_status', 'active');
        set_transient('hozio_hub_license_status', $license_status, DAY_IN_SECONDS);
        update_option('hozio_hub_last_known_status', $license_status);

        // Update heartbeat interval if changed
        if (!empty($body['next_heartbeat'])) {
            update_option('hozio_hub_heartbeat_interval', (int) $body['next_heartbeat']);
        }

        // Process incoming commands
        if (!empty($body['commands']) && is_array($body['commands'])) {
            self::process_commands($body['commands']);
        }
    }

    /**
     * Process commands received from Hub
     *
     * @param array $commands Array of command objects
     */
    private static function process_commands($commands) {
        if (!class_exists('Hozio_Command_Executor')) {
            return;
        }

        $executed_nonces = get_option('hozio_hub_executed_commands', []);
        $pending_results = get_option('hozio_hub_pending_results', []);

        foreach ($commands as $command) {
            if (empty($command['nonce']) || empty($command['command_type'])) {
                continue;
            }

            // Idempotency check — don't re-execute
            if (isset($executed_nonces[$command['nonce']])) {
                // Return the previous result
                $pending_results[] = $executed_nonces[$command['nonce']];
                continue;
            }

            // Execute the command
            $result = Hozio_Command_Executor::execute(
                $command['command_type'],
                $command['command_payload'] ?? []
            );

            $result_entry = [
                'command_id'  => $command['command_id'] ?? null,
                'nonce'       => $command['nonce'],
                'status'      => $result['success'] ? 'completed' : 'failed',
                'result'      => $result['data'] ?? null,
                'error'       => $result['error'] ?? null,
                'executed_at' => gmdate('Y-m-d H:i:s'),
            ];

            $pending_results[] = $result_entry;

            // Store in nonce ring buffer
            $executed_nonces[$command['nonce']] = $result_entry;
        }

        // Evict old nonces (keep last 1000, remove entries older than 7 days)
        $executed_nonces = self::evict_old_nonces($executed_nonces);

        // FIFO eviction on pending results (max 100)
        if (count($pending_results) > 100) {
            $pending_results = array_slice($pending_results, -100);
        }

        update_option('hozio_hub_pending_results', $pending_results);
        update_option('hozio_hub_executed_commands', $executed_nonces);
    }

    /**
     * Evict old nonces from the ring buffer
     *
     * @param array $nonces Associative array of nonce => result_entry
     * @return array Cleaned array
     */
    private static function evict_old_nonces($nonces) {
        $cutoff = gmdate('Y-m-d H:i:s', time() - (7 * DAY_IN_SECONDS));

        // Remove entries older than 7 days
        foreach ($nonces as $nonce => $entry) {
            if (isset($entry['executed_at']) && $entry['executed_at'] < $cutoff) {
                unset($nonces[$nonce]);
            }
        }

        // Keep only last 1000
        if (count($nonces) > 1000) {
            $nonces = array_slice($nonces, -1000, null, true);
        }

        return $nonces;
    }

    /**
     * Trigger heartbeat on admin login (manage_options users only)
     *
     * @param string  $user_login Username
     * @param WP_User $user       User object
     */
    public static function on_admin_login($user_login, $user) {
        if (!$user->has_cap('manage_options')) {
            return;
        }

        if (!self::is_connected()) {
            return;
        }

        // Use wp_schedule_single_event to avoid blocking login
        if (!wp_next_scheduled('hozio_hub_heartbeat_login')) {
            wp_schedule_single_event(time(), 'hozio_hub_heartbeat_login');
        }
    }

    /**
     * Get the cached license status from the Hub
     *
     * @return string|false License status or false if not connected
     */
    public static function get_license_status() {
        if (!self::is_connected()) {
            return false;
        }

        // Check transient (24h cache)
        $status = get_transient('hozio_hub_license_status');
        if ($status !== false) {
            return $status;
        }

        // Fall back to last known status
        $last_known = get_option('hozio_hub_last_known_status');
        if ($last_known) {
            return $last_known;
        }

        // Fall back to 72h grace period for fresh registrations
        $registration_time = (int) get_option('hozio_hub_registration_time', 0);
        if ($registration_time && (time() - $registration_time) < (72 * HOUR_IN_SECONDS)) {
            return 'active';
        }

        return false;
    }
}

// Initialize
Hozio_Hub_Client::init();

// Login heartbeat event handler
add_action('hozio_hub_heartbeat_login', ['Hozio_Hub_Client', 'send_heartbeat']);
