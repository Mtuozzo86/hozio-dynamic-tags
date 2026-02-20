<?php
/**
 * Hozio Hub Direct Endpoint
 * REST endpoint for Hub → Client direct queries
 * POST /wp-json/hozio-pro/v1/hub-request
 */

if (!defined('ABSPATH')) exit;

class Hozio_Hub_Direct_Endpoint {

    /**
     * Register REST routes
     */
    public static function register_routes() {
        register_rest_route('hozio-pro/v1', '/hub-request', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_request'],
            'permission_callback' => '__return_true', // Auth via hub_token
        ]);
    }

    /**
     * Handle incoming Hub request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_request($request) {
        // Verify hub token
        $auth_result = self::verify_hub_token($request);
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }

        $action  = $request->get_param('action');
        $payload = $request->get_param('payload') ?: [];

        if (empty($action)) {
            return new WP_Error('missing_action', 'Action parameter is required.', ['status' => 400]);
        }

        switch ($action) {
            case 'ping':
                return rest_ensure_response([
                    'success' => true,
                    'data'    => ['status' => 'ok', 'time' => time()],
                ]);

            case 'get_info':
                return rest_ensure_response([
                    'success' => true,
                    'data'    => self::get_site_info(),
                ]);

            case 'get_plugins':
                return rest_ensure_response([
                    'success' => true,
                    'data'    => ['plugins' => self::get_plugins_list()],
                ]);

            case 'get_pages':
                return rest_ensure_response([
                    'success' => true,
                    'data'    => ['pages' => self::get_pages_list($payload)],
                ]);

            case 'get_taxonomies':
                return rest_ensure_response([
                    'success' => true,
                    'data'    => ['taxonomies' => self::get_page_taxonomies()],
                ]);

            case 'get_features':
                return rest_ensure_response([
                    'success' => true,
                    'data'    => ['features' => self::get_hozio_features()],
                ]);

            case 'execute_command':
                return rest_ensure_response(self::execute_command($payload));

            case 'refresh_license':
                $new_status = $payload['status'] ?? '';
                if (empty($new_status)) {
                    return new WP_Error('missing_status', 'License status is required.', ['status' => 400]);
                }
                // Apply license status immediately — no heartbeat round-trip
                set_transient('hozio_hub_license_status', $new_status, DAY_IN_SECONDS);
                update_option('hozio_hub_last_known_status', $new_status);
                return rest_ensure_response([
                    'success' => true,
                    'data'    => ['message' => 'License status set to ' . $new_status],
                ]);

            default:
                return new WP_Error('unknown_action', 'Unknown action: ' . $action, ['status' => 400]);
        }
    }

    /**
     * Verify the hub token from Authorization header
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    private static function verify_hub_token($request) {
        $auth_header = $request->get_header('Authorization');

        if (empty($auth_header) || stripos($auth_header, 'Bearer ') !== 0) {
            return new WP_Error('unauthorized', 'Bearer token required.', ['status' => 401]);
        }

        $token = substr($auth_header, 7);
        if (empty($token)) {
            return new WP_Error('unauthorized', 'Empty bearer token.', ['status' => 401]);
        }

        $stored_hash = get_option('hozio_hub_token_hash');
        if (empty($stored_hash)) {
            return new WP_Error('not_configured', 'Hub token not configured on this site.', ['status' => 401]);
        }

        $token_hash = hash('sha256', $token);
        if (!hash_equals($stored_hash, $token_hash)) {
            return new WP_Error('unauthorized', 'Invalid hub token.', ['status' => 401]);
        }

        return true;
    }

    /**
     * Get comprehensive site info
     *
     * @return array
     */
    private static function get_site_info() {
        return [
            'site_url'       => home_url(),
            'plugin_version' => defined('HOZIO_VERSION') ? HOZIO_VERSION : '0.0.0',
            'wp_version'     => get_bloginfo('version'),
            'php_version'    => phpversion(),
            'active_plugins' => get_option('active_plugins', []),
            'plugins'        => self::get_plugins_list(),
            'page_count'     => wp_count_posts('page'),
        ];
    }

    /**
     * Get full plugin list with status info
     *
     * @return array
     */
    private static function get_plugins_list() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        $result = [];

        foreach ($all_plugins as $file => $data) {
            $result[$file] = [
                'name'    => $data['Name'],
                'version' => $data['Version'],
                'status'  => in_array($file, $active_plugins) ? 'active' : 'inactive',
                'author'  => $data['Author'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * Get pages list with optional search and pagination
     *
     * @param array $params Optional: search, offset, per_page, post_status
     * @return array
     */
    private static function get_pages_list($params = []) {
        $args = [
            'post_type'      => 'page',
            'posts_per_page' => $params['per_page'] ?? 50,
            'offset'         => $params['offset'] ?? 0,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'post_status'    => $params['post_status'] ?? ['publish', 'draft', 'pending', 'private', 'trash'],
        ];

        if (!empty($params['search'])) {
            $args['s'] = sanitize_text_field($params['search']);
        }

        // Taxonomy filtering
        if (!empty($params['taxonomy']) && !empty($params['term_id'])) {
            $args['tax_query'] = [[
                'taxonomy' => sanitize_text_field($params['taxonomy']),
                'field'    => 'term_id',
                'terms'    => (int) $params['term_id'],
            ]];
        }

        $query = new WP_Query($args);
        $pages = [];

        $tax_slugs = ['parent_pages', 'town_taxonomies'];

        foreach ($query->posts as $post) {
            $taxonomies = [];
            foreach ($tax_slugs as $tax) {
                if (taxonomy_exists($tax)) {
                    $terms = wp_get_post_terms($post->ID, $tax, ['fields' => 'id=>name']);
                    if (!is_wp_error($terms) && !empty($terms)) {
                        $term_list = [];
                        foreach ($terms as $id => $name) {
                            $term_list[] = ['id' => $id, 'name' => $name];
                        }
                        $taxonomies[$tax] = $term_list;
                    }
                }
            }

            $pages[] = [
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'status'     => $post->post_status,
                'slug'       => $post->post_name,
                'parent'     => $post->post_parent,
                'modified'   => $post->post_modified,
                'taxonomies' => $taxonomies,
            ];
        }

        return $pages;
    }

    /**
     * Get available page taxonomies and their terms
     *
     * @return array
     */
    private static function get_page_taxonomies() {
        $result = [];
        $tax_slugs = [
            'parent_pages'     => 'Page Taxonomies',
            'town_taxonomies'  => 'Town Taxonomies',
        ];

        foreach ($tax_slugs as $slug => $label) {
            if (!taxonomy_exists($slug)) {
                continue;
            }

            $terms = get_terms([
                'taxonomy'   => $slug,
                'hide_empty' => false,
                'orderby'    => 'name',
            ]);

            if (is_wp_error($terms)) {
                continue;
            }

            $term_list = [];
            foreach ($terms as $term) {
                $term_list[] = [
                    'id'     => $term->term_id,
                    'name'   => $term->name,
                    'slug'   => $term->slug,
                    'count'  => $term->count,
                    'parent' => $term->parent,
                ];
            }

            $result[$slug] = [
                'label' => $label,
                'terms' => $term_list,
            ];
        }

        return $result;
    }

    /**
     * Get Hozio Pro feature toggle states
     *
     * @return array
     */
    private static function get_hozio_features() {
        return [
            'hozio_dom_parsing_enabled'      => (int) get_option('hozio_dom_parsing_enabled', '1'),
            'hozio_service_menu_sync_enabled' => (int) get_option('hozio_service_menu_sync_enabled', '1'),
            'hozio_auto_updates_enabled'      => (int) get_option('hozio_auto_updates_enabled', '1'),
            'hozio_debug_enabled'             => (int) get_option('hozio_debug_enabled', '0'),
        ];
    }

    /**
     * Execute a command via the command executor
     *
     * @param array $payload Must contain command_type and optionally command_payload
     * @return array
     */
    private static function execute_command($payload) {
        if (!class_exists('Hozio_Command_Executor')) {
            return [
                'success' => false,
                'error'   => 'Command executor not available.',
                'code'    => 'executor_missing',
            ];
        }

        $command_type    = $payload['command_type'] ?? '';
        $command_payload = $payload['command_payload'] ?? [];

        if (empty($command_type)) {
            return [
                'success' => false,
                'error'   => 'command_type is required.',
                'code'    => 'missing_command_type',
            ];
        }

        // Idempotency check if nonce provided
        if (!empty($payload['nonce'])) {
            $executed_nonces = get_option('hozio_hub_executed_commands', []);
            if (isset($executed_nonces[$payload['nonce']])) {
                return [
                    'success' => true,
                    'data'    => $executed_nonces[$payload['nonce']],
                ];
            }
        }

        $result = Hozio_Command_Executor::execute($command_type, $command_payload);

        // Store in nonce ring buffer if nonce provided
        if (!empty($payload['nonce'])) {
            $executed_nonces = get_option('hozio_hub_executed_commands', []);
            $executed_nonces[$payload['nonce']] = $result;

            // Evict old entries
            if (count($executed_nonces) > 1000) {
                $executed_nonces = array_slice($executed_nonces, -1000, null, true);
            }
            update_option('hozio_hub_executed_commands', $executed_nonces);
        }

        return $result;
    }

}

// Only register REST routes if this site is connected to a Hub
add_action('rest_api_init', function() {
    if (get_option('hozio_hub_token_hash')) {
        Hozio_Hub_Direct_Endpoint::register_routes();
    }
});
