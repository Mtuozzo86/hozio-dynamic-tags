<?php
/**
 * DTB content path resolver.
 *
 * Per-client website content (hozio-config.php, templates/, assets/) lives in
 * wp-content/uploads/dtb-site/ and is uploaded via the dtb/v1/content-update
 * REST endpoint. The engine plugin reads from there. If the uploads dir is
 * empty (engine installed but no content deployed yet), shortcodes return
 * safe defaults so the site doesn't crash.
 *
 * Filters:
 *   hozio_dtb_content_path  Override absolute path to the dtb-site directory.
 *   hozio_dtb_content_url   Override URL to the dtb-site directory.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'hozio_dtb_content_path' ) ) {
    function hozio_dtb_content_path() {
        $upload  = wp_get_upload_dir();
        $default = trailingslashit( $upload['basedir'] ) . 'dtb-site/';
        return apply_filters( 'hozio_dtb_content_path', $default );
    }
}

if ( ! function_exists( 'hozio_dtb_content_url' ) ) {
    function hozio_dtb_content_url() {
        $upload  = wp_get_upload_dir();
        $default = trailingslashit( $upload['baseurl'] ) . 'dtb-site/';
        return apply_filters( 'hozio_dtb_content_url', $default );
    }
}

if ( ! function_exists( 'hozio_dtb_content_installed' ) ) {
    function hozio_dtb_content_installed() {
        return file_exists( hozio_dtb_content_path() . 'hozio-config.php' );
    }
}

/**
 * Read the hozio-config.php tag registry from uploaded content.
 * Cached per-request. Returns empty array if no content has been deployed.
 */
if ( ! function_exists( 'hozio_dtb_get_tags' ) ) {
    function hozio_dtb_get_tags() {
        static $tags = null;
        if ( $tags === null ) {
            $file = hozio_dtb_content_path() . 'hozio-config.php';
            $tags = file_exists( $file ) ? (array) include $file : array();
        }
        return $tags;
    }
}

/**
 * Read a single tag value from hozio-config.php.
 * Returns null if the key is not in the config (so callers can distinguish
 * "not set in file" from "set to empty string").
 */
if ( ! function_exists( 'hozio_dtb_get_tag' ) ) {
    function hozio_dtb_get_tag( $key ) {
        $tags = hozio_dtb_get_tags();
        return array_key_exists( $key, $tags ) ? $tags[ $key ] : null;
    }
}

/**
 * Read installed content version from manifest.json (if present).
 * Falls back to mtime of hozio-config.php so cache-busters still work.
 */
if ( ! function_exists( 'hozio_dtb_content_version' ) ) {
    function hozio_dtb_content_version() {
        $manifest = hozio_dtb_content_path() . 'manifest.json';
        if ( file_exists( $manifest ) ) {
            $data = json_decode( (string) file_get_contents( $manifest ), true );
            if ( is_array( $data ) && isset( $data['version'] ) ) {
                return (string) $data['version'];
            }
        }
        $config = hozio_dtb_content_path() . 'hozio-config.php';
        return file_exists( $config ) ? (string) filemtime( $config ) : '';
    }
}

/**
 * Cache-bust helper for content-side assets (CSS/JS in uploads/dtb-site/).
 * Returns mtime of the file or content version as a fallback.
 */
if ( ! function_exists( 'hozio_dtb_content_asset_ver' ) ) {
    function hozio_dtb_content_asset_ver( $relative_path ) {
        $abs = hozio_dtb_content_path() . ltrim( $relative_path, '/' );
        if ( file_exists( $abs ) ) {
            return (string) filemtime( $abs );
        }
        return hozio_dtb_content_version();
    }
}
