<?php
/**
 * Template loader: [template name="product/hero"]
 *
 * Resolution order:
 *   1. Per-client content:  wp-content/uploads/dtb-site/templates/{name}.php
 *   2. Engine fallback:     wp-content/plugins/hozio-dynamic-tags/templates/dtb/{name}.php
 *
 * The engine ships universal templates (privacy policy, etc.) that any
 * client site can render unmodified — styling adapts automatically because
 * they use the hz-* design system whose CSS custom properties are set by
 * each client's main.css. A client can override an engine template by
 * shipping their own file at the same name in dtb-site/templates/.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Resolve a template name to a file path. Returns null if not found in
 * either the content dir or the engine fallback dir, or if the resolved
 * path escapes both roots (path-traversal defense in depth).
 */
if ( ! function_exists( 'hozio_dtb_resolve_template' ) ) {
    function hozio_dtb_resolve_template( $name ) {
        $name = str_replace( array( '..', "\0" ), '', $name );
        $name = ltrim( $name, '/' );
        if ( $name === '' ) {
            return null;
        }
        // Strip a trailing .php so callers can use either form.
        $name_no_ext = preg_replace( '/\.php$/i', '', $name );

        $candidates = array();
        // 1. Per-client content
        if ( function_exists( 'hozio_dtb_content_path' ) ) {
            $content_root      = hozio_dtb_content_path() . 'templates';
            $candidates[]      = array( $content_root . '/' . $name_no_ext . '.php', $content_root );
        }
        // 2. Engine fallback (templates that ship with hozio-dynamic-tags)
        if ( defined( 'HOZIO_DTB_ENGINE_PATH' ) ) {
            $engine_root  = rtrim( HOZIO_DTB_ENGINE_PATH, '/\\' ) . '/templates/dtb';
            $candidates[] = array( $engine_root . '/' . $name_no_ext . '.php', $engine_root );
        }

        foreach ( $candidates as list( $path, $root ) ) {
            if ( ! file_exists( $path ) ) {
                continue;
            }
            $real_path = realpath( $path );
            $real_root = realpath( $root );
            if ( $real_path === false || $real_root === false ) {
                continue;
            }
            if ( strpos( $real_path, $real_root ) !== 0 ) {
                continue; // path escaped its root
            }
            return $real_path;
        }
        return null;
    }
}

if ( ! shortcode_exists( 'template' ) ) {
    function cb_template_loader_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'name'      => '',
            'post_id'   => '',
            'slug'      => '',
            'post_type' => '',
        ), $atts );

        if ( empty( $atts['name'] ) ) {
            return '<!-- [template] error: no name specified -->';
        }

        $template_path = hozio_dtb_resolve_template( $atts['name'] );
        if ( $template_path === null ) {
            return '<!-- [template] error: "' . esc_html( $atts['name'] ) . '" not found -->';
        }

        // Override post ID context if specified
        $original_real_id = null;
        if ( ! empty( $atts['post_id'] ) || ! empty( $atts['slug'] ) ) {
            $original_real_id = isset( $GLOBALS['cb_real_post_id'] ) ? $GLOBALS['cb_real_post_id'] : null;
            $resolved_id = function_exists( 'cb_acf_resolve_post_id' )
                ? cb_acf_resolve_post_id( $atts )
                : null;
            if ( $resolved_id ) {
                $GLOBALS['cb_real_post_id'] = $resolved_id;
            }
        }

        // Load the template
        ob_start();
        include $template_path;
        $output = ob_get_clean();

        // Process any shortcodes in the template output
        $output = do_shortcode( $output );

        // Restore post ID context
        if ( $original_real_id !== null ) {
            $GLOBALS['cb_real_post_id'] = $original_real_id;
        }

        return $output;
    }
    add_shortcode( 'template', 'cb_template_loader_shortcode' );
}

/**
 * Template with CSS: [template_with_css name="product/hero"]
 * Same as [template] but also enqueues a matching CSS file from the
 * content dir if one exists.
 *   templates/product/hero.php  ->  assets/css/templates/product/hero.css
 */
if ( ! shortcode_exists( 'template_with_css' ) ) {
    function cb_template_with_css_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'name' => '' ), $atts );

        if ( empty( $atts['name'] ) ) return '';

        $name = str_replace( array( '..', "\0" ), '', $atts['name'] );
        $name = ltrim( $name, '/' );
        $name = preg_replace( '/\.php$/i', '', $name );

        $css_rel = 'assets/css/templates/' . $name . '.css';
        $handle  = 'dtb-tpl-' . sanitize_title( $name );

        // Per-client sidecar CSS first (uploads/dtb-site/assets/css/templates/...)
        $client_css = hozio_dtb_content_path() . $css_rel;
        if ( file_exists( $client_css ) ) {
            wp_enqueue_style(
                $handle,
                hozio_dtb_content_url() . $css_rel,
                array( 'dtb-main' ),
                hozio_dtb_content_asset_ver( $css_rel )
            );
        } elseif ( defined( 'HOZIO_DTB_ENGINE_PATH' ) ) {
            // Engine sidecar CSS (assets/dtb/css/templates/...)
            $engine_css_rel  = 'assets/dtb/css/templates/' . $name . '.css';
            $engine_css_path = HOZIO_DTB_ENGINE_PATH . $engine_css_rel;
            if ( file_exists( $engine_css_path ) ) {
                wp_enqueue_style(
                    $handle,
                    HOZIO_DTB_ENGINE_URL . $engine_css_rel,
                    array( 'dtb-primitives' ),
                    function_exists( 'hozio_dtb_engine_asset_ver' )
                        ? hozio_dtb_engine_asset_ver( $engine_css_rel )
                        : ( defined( 'HOZIO_VERSION' ) ? HOZIO_VERSION : null )
                );
            }
        }

        return cb_template_loader_shortcode( $atts );
    }
    add_shortcode( 'template_with_css', 'cb_template_with_css_shortcode' );
}
