<?php
/**
 * Export Static — graceful removal command.
 *
 * Converts a DTB-powered site into a standalone WordPress + Elementor site
 * that works identically without the DTB plugin. Content pages get their
 * shortcodes resolved to static HTML. WooCommerce pages keep their shortcodes
 * dynamic (handlers copied to the child theme). CSS/JS moves to a child theme.
 *
 * Trigger:  POST /dtb/v1/export-static
 * WP-CLI:   wp eval-file includes/export-static.php   (future: wp dtb export-static)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the export-static REST endpoint.
 */
add_action( 'rest_api_init', function () {
    register_rest_route( 'dtb/v1', '/export-static', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_export_static',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );
} );

function dtb_rest_export_static( $request ) {
    $body    = $request->get_json_params() ?: array();
    $dry_run = ! empty( $body['dry_run'] );

    $exporter = new DTB_Export_Static();

    if ( $dry_run ) {
        return $exporter->preview();
    }

    return $exporter->run();
}


class DTB_Export_Static {

    private $log = array();
    private $child_theme_dir;
    private $child_theme_slug = 'hello-elementor-child';

    public function __construct() {
        $this->child_theme_dir = get_theme_root() . '/' . $this->child_theme_slug;
    }

    /**
     * Preview what export-static would do without making changes.
     */
    public function preview() {
        $pages = $this->get_pages_to_process();

        $content_pages = array();
        $wc_pages      = array();
        $skipped       = array();

        foreach ( $pages as $page ) {
            $classification = $this->classify_page( $page );
            switch ( $classification ) {
                case 'content':
                    $content_pages[] = array(
                        'id'    => $page->ID,
                        'title' => $page->post_title,
                        'slug'  => $page->post_name,
                    );
                    break;
                case 'woocommerce':
                    $wc_pages[] = array(
                        'id'    => $page->ID,
                        'title' => $page->post_title,
                        'slug'  => $page->post_name,
                    );
                    break;
                default:
                    $skipped[] = array(
                        'id'     => $page->ID,
                        'title'  => $page->post_title,
                        'reason' => $classification,
                    );
            }
        }

        return array(
            'dry_run'       => true,
            'content_pages' => $content_pages,
            'wc_pages'      => $wc_pages,
            'skipped'       => $skipped,
            'child_theme'   => $this->child_theme_slug,
            'actions'       => array(
                'Content pages will have all shortcodes resolved to static HTML.',
                'WooCommerce pages will keep shortcodes; handlers copied to child theme.',
                'Child theme will be created with CSS, JS, and WC shortcode handlers.',
                'A full database backup should be taken before running.',
            ),
        );
    }

    /**
     * Execute the export.
     */
    public function run() {
        // Step 1: Create backup point
        $this->log( 'Starting export-static' );
        $backup_key = $this->create_backup();
        $this->log( "Backup created: {$backup_key}" );

        // Step 2: Create child theme
        $this->create_child_theme();
        $this->log( 'Child theme created' );

        // Step 3: Process pages
        $pages   = $this->get_pages_to_process();
        $results = array(
            'content_resolved' => 0,
            'wc_preserved'     => 0,
            'skipped'          => 0,
            'errors'           => array(),
        );

        foreach ( $pages as $page ) {
            $classification = $this->classify_page( $page );

            if ( $classification === 'content' ) {
                $success = $this->resolve_page_shortcodes( $page );
                if ( $success ) {
                    $results['content_resolved']++;
                } else {
                    $results['errors'][] = "Failed to resolve page {$page->ID}: {$page->post_title}";
                }
            } elseif ( $classification === 'woocommerce' ) {
                // WC pages: resolve hozio/acf shortcodes but keep wc_* shortcodes
                $this->resolve_page_shortcodes( $page, true );
                $results['wc_preserved']++;
            } else {
                $results['skipped']++;
            }
        }

        // Step 4: Copy WC shortcode handlers to child theme (if WC pages exist)
        if ( $results['wc_preserved'] > 0 ) {
            $this->copy_wc_handlers_to_child_theme();
            $this->log( 'WC shortcode handlers copied to child theme' );
        }

        // Step 5: Activate child theme
        switch_theme( $this->child_theme_slug );
        $this->log( 'Child theme activated' );

        // Step 6: Flush caches
        wp_cache_flush();
        if ( class_exists( '\\Elementor\\Plugin' ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        return array(
            'success'    => true,
            'backup_key' => $backup_key,
            'results'    => $results,
            'child_theme' => $this->child_theme_slug,
            'log'        => $this->log,
            'next_steps' => array(
                'Verify the site renders correctly.',
                'Deactivate the Dynamic Tags Bridge plugin.',
                'The site now runs on the child theme without DTB.',
                "To revert, restore the backup (key: {$backup_key}) and reactivate DTB.",
            ),
        );
    }

    // ── Page processing ────────────────────────────────────────────────

    private function get_pages_to_process() {
        return get_posts( array(
            'post_type'      => array( 'page', 'post', 'elementor_library' ),
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => -1,
        ) );
    }

    private function classify_page( $page ) {
        $elementor_data = get_post_meta( $page->ID, '_elementor_data', true );

        // No Elementor data — skip
        if ( empty( $elementor_data ) ) {
            return 'no_elementor_data';
        }

        $data_str = is_string( $elementor_data ) ? $elementor_data : wp_json_encode( $elementor_data );

        // Check if this page uses WooCommerce shortcodes
        if ( preg_match( '/\[wc_/', $data_str ) ) {
            return 'woocommerce';
        }

        // Check if it's a WC page by slug
        $wc_slugs = array( 'shop', 'cart', 'checkout', 'my-account' );
        if ( in_array( $page->post_name, $wc_slugs, true ) ) {
            return 'woocommerce';
        }

        return 'content';
    }

    private function resolve_page_shortcodes( $page, $keep_wc = false ) {
        $raw = get_post_meta( $page->ID, '_elementor_data', true );
        if ( empty( $raw ) ) {
            return false;
        }

        $data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Set up post context for shortcode resolution
        global $post;
        $original_post = $post;
        $post = $page;
        setup_postdata( $page );

        // Walk the element tree and resolve shortcodes in HTML widgets
        $data = $this->walk_elements( $data, $keep_wc );

        // Restore post context
        wp_reset_postdata();
        $post = $original_post;

        // Save the resolved data back
        $json = wp_json_encode( $data );
        update_post_meta( $page->ID, '_elementor_data', wp_slash( $json ) );

        // Clear Elementor CSS for this page
        delete_post_meta( $page->ID, '_elementor_css' );

        return true;
    }

    private function walk_elements( $elements, $keep_wc = false ) {
        foreach ( $elements as &$element ) {
            // Process HTML widgets
            if (
                isset( $element['widgetType'] ) &&
                $element['widgetType'] === 'html' &&
                ! empty( $element['settings']['html'] )
            ) {
                $html = $element['settings']['html'];

                if ( $keep_wc ) {
                    // Resolve everything EXCEPT [wc_*] and [wp_*] shortcodes
                    $html = $this->resolve_non_wc_shortcodes( $html );
                } else {
                    // Resolve ALL shortcodes
                    $html = do_shortcode( $html );
                }

                $element['settings']['html'] = $html;
            }

            // Recurse into child elements
            if ( ! empty( $element['elements'] ) ) {
                $element['elements'] = $this->walk_elements( $element['elements'], $keep_wc );
            }
        }

        return $elements;
    }

    private function resolve_non_wc_shortcodes( $html ) {
        // Temporarily remove WC and WP shortcodes so they survive do_shortcode()
        $preserved = array();
        $counter   = 0;

        // Preserve [wc_*] shortcodes
        $html = preg_replace_callback(
            '/\[wc_[^\]]*\](?:[^\[]*\[\/wc_\w+\])?/',
            function ( $match ) use ( &$preserved, &$counter ) {
                $key = "<!--DTB_PRESERVE_{$counter}-->";
                $preserved[ $key ] = $match[0];
                $counter++;
                return $key;
            },
            $html
        );

        // Preserve [wp_*] shortcodes
        $html = preg_replace_callback(
            '/\[wp_[^\]]*\]/',
            function ( $match ) use ( &$preserved, &$counter ) {
                $key = "<!--DTB_PRESERVE_{$counter}-->";
                $preserved[ $key ] = $match[0];
                $counter++;
                return $key;
            },
            $html
        );

        // Resolve remaining shortcodes (hozio, acf, template, etc.)
        $html = do_shortcode( $html );

        // Restore preserved shortcodes
        foreach ( $preserved as $key => $original ) {
            $html = str_replace( $key, $original, $html );
        }

        return $html;
    }

    // ── Backup ─────────────────────────────────────────────────────────

    private function create_backup() {
        $key = 'dtb_export_backup_' . date( 'Y-m-d_His' );

        // Save current Elementor data for all pages
        $pages = $this->get_pages_to_process();
        $backup_data = array();

        foreach ( $pages as $page ) {
            $elementor_data = get_post_meta( $page->ID, '_elementor_data', true );
            if ( ! empty( $elementor_data ) ) {
                $backup_data[ $page->ID ] = array(
                    'title'          => $page->post_title,
                    'elementor_data' => $elementor_data,
                );
            }
        }

        // Store as a WordPress option (serialized)
        update_option( $key, $backup_data, false );

        // Also store the current active theme
        update_option( $key . '_theme', get_stylesheet(), false );

        return $key;
    }

    // ── Child theme creation ───────────────────────────────────────────

    private function create_child_theme() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        // Create directory
        if ( ! $wp_filesystem->is_dir( $this->child_theme_dir ) ) {
            wp_mkdir_p( $this->child_theme_dir );
        }

        // style.css (child theme header)
        $style_css = "/*\n"
            . "Theme Name: Hello Elementor Child (DTB Static)\n"
            . "Template: hello-elementor\n"
            . "Description: Static assets from DTB export. CSS and JS for the site.\n"
            . "Version: 1.0.0\n"
            . "Author: Hozio\n"
            . "*/\n";
        $wp_filesystem->put_contents( $this->child_theme_dir . '/style.css', $style_css );

        // Copy CSS files
        // primitives.css ships with the engine plugin (universal design tokens).
        // main.css and woocommerce.css come from the per-client content dir
        // (wp-content/uploads/dtb-site/assets/css/).
        $css_dir       = $this->child_theme_dir . '/assets/css';
        $engine_assets = defined( 'HOZIO_DTB_ENGINE_PATH' ) ? HOZIO_DTB_ENGINE_PATH : plugin_dir_path( HOZIO_PLUGIN_FILE );
        $content_path  = function_exists( 'hozio_dtb_content_path' ) ? hozio_dtb_content_path() : '';
        wp_mkdir_p( $css_dir );

        // primitives.css from engine
        $primitives_src = $engine_assets . 'assets/dtb/css/primitives.css';
        if ( file_exists( $primitives_src ) ) {
            $wp_filesystem->copy( $primitives_src, $css_dir . '/primitives.css' );
        }
        // main.css / woocommerce.css from client content
        foreach ( array( 'main.css', 'woocommerce.css', 'trustindex.css' ) as $css ) {
            $source = $content_path . 'assets/css/' . $css;
            if ( file_exists( $source ) ) {
                $wp_filesystem->copy( $source, $css_dir . '/' . $css );
            }
        }

        // Copy JS files (client main.js bundles hz-* nav)
        $js_dir = $this->child_theme_dir . '/assets/js';
        wp_mkdir_p( $js_dir );
        $js_source = $content_path . 'assets/js/main.js';
        if ( file_exists( $js_source ) ) {
            $wp_filesystem->copy( $js_source, $js_dir . '/main.js' );
        }

        // Copy hozio-config.php (frozen business data)
        $config_source = $content_path . 'hozio-config.php';
        if ( file_exists( $config_source ) ) {
            $wp_filesystem->copy( $config_source, $this->child_theme_dir . '/hozio-config.php' );
        }

        // functions.php — asset enqueues
        $functions_php = $this->build_functions_php();
        $wp_filesystem->put_contents( $this->child_theme_dir . '/functions.php', $functions_php );
    }

    private function build_functions_php() {
        $php = "<?php\n";
        $php .= "/**\n * Hello Elementor Child — static assets from DTB export.\n */\n\n";

        // Asset enqueues
        $php .= "add_action( 'wp_enqueue_scripts', function () {\n";
        $php .= "    \$theme_uri = get_stylesheet_directory_uri();\n";
        $php .= "    \$theme_dir = get_stylesheet_directory();\n\n";
        $php .= "    wp_enqueue_style( 'dtb-primitives', \$theme_uri . '/assets/css/primitives.css', array(), filemtime( \$theme_dir . '/assets/css/primitives.css' ) );\n";
        $php .= "    wp_enqueue_style( 'dtb-main', \$theme_uri . '/assets/css/main.css', array( 'dtb-primitives' ), filemtime( \$theme_dir . '/assets/css/main.css' ) );\n\n";
        $php .= "    if ( file_exists( \$theme_dir . '/assets/css/woocommerce.css' ) && function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() ) ) {\n";
        $php .= "        wp_enqueue_style( 'dtb-wc', \$theme_uri . '/assets/css/woocommerce.css', array( 'dtb-main' ), filemtime( \$theme_dir . '/assets/css/woocommerce.css' ) );\n";
        $php .= "    }\n\n";
        $php .= "    if ( file_exists( \$theme_dir . '/assets/js/main.js' ) ) {\n";
        $php .= "        wp_enqueue_script( 'dtb-main', \$theme_uri . '/assets/js/main.js', array( 'jquery' ), filemtime( \$theme_dir . '/assets/js/main.js' ), true );\n";
        $php .= "    }\n";
        $php .= "}, 999 );\n";

        return $php;
    }

    private function copy_wc_handlers_to_child_theme() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        // Read current functions.php
        $functions_path = $this->child_theme_dir . '/functions.php';
        $php = $wp_filesystem->get_contents( $functions_path );

        // Add WC/WP shortcode handlers
        $php .= "\n\n// ── Shortcode handlers (from DTB export) ──────────────────────────────\n";
        $php .= "// These are thin wrappers around WordPress/WooCommerce PHP functions.\n";
        $php .= "// They allow [wc_*] and [wp_*] shortcodes to work after the engine\n";
        $php .= "// plugin is removed.\n\n";

        // Engine source files live under the engine plugin's includes/dtb/
        $engine_includes = ( defined( 'HOZIO_DTB_ENGINE_PATH' ) ? HOZIO_DTB_ENGINE_PATH : plugin_dir_path( HOZIO_PLUGIN_FILE ) ) . 'includes/dtb/';

        // Copy the helper functions needed by shortcodes
        $helpers_source = $engine_includes . 'helpers.php';
        if ( file_exists( $helpers_source ) ) {
            $helpers = file_get_contents( $helpers_source );
            $helpers = preg_replace( '/^<\?php.*?(?=\$GLOBALS|\n\n|function)/s', '', $helpers );
            $php .= $helpers . "\n";
        }

        // Copy WP shortcodes
        $wp_source = $engine_includes . 'wp-shortcodes.php';
        if ( file_exists( $wp_source ) ) {
            $wp_code = file_get_contents( $wp_source );
            $wp_code = preg_replace( '/^<\?php.*?if\s*\(\s*!\s*defined.*?\n/s', '', $wp_code );
            $php .= "\n// ── WordPress field shortcodes ──\n" . $wp_code . "\n";
        }

        // Copy WC shortcodes
        $wc_source = $engine_includes . 'wc-shortcodes.php';
        if ( file_exists( $wc_source ) ) {
            $wc_code = file_get_contents( $wc_source );
            $wc_code = preg_replace( '/^<\?php.*?if\s*\(\s*!\s*defined.*?\n/s', '', $wc_code );
            $php .= "\n// ── WooCommerce field shortcodes ──\n" . $wc_code . "\n";
        }

        // Copy WC loop shortcodes
        $wc_loop_source = $engine_includes . 'wc-loop-shortcodes.php';
        if ( file_exists( $wc_loop_source ) ) {
            $wc_loop = file_get_contents( $wc_loop_source );
            $wc_loop = preg_replace( '/^<\?php.*?if\s*\(\s*!\s*defined.*?\n/s', '', $wc_loop );
            $php .= "\n// ── WooCommerce loop shortcodes ──\n" . $wc_loop . "\n";
        }

        $wp_filesystem->put_contents( $functions_path, $php );
    }

    // ── Logging ────────────────────────────────────────────────────────

    private function log( $message ) {
        $this->log[] = date( 'H:i:s' ) . ' ' . $message;
    }
}
