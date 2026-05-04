<?php
/**
 * WordPress native field shortcodes + debug shortcode.
 * These have NO dependency on ACF and always load.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Post Title: [wp_title] ─────────────────────────────────────────────
if ( ! shortcode_exists( 'wp_title' ) ) {
    function cb_wp_title_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts(), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        return esc_html( $post_id ? get_the_title( $post_id ) : get_the_title() );
    }
    add_shortcode( 'wp_title', 'cb_wp_title_shortcode' );
}

// ─── Featured Image: [wp_featured_img alt="" class="" size="full"] ──────
if ( ! shortcode_exists( 'wp_featured_img' ) ) {
    function cb_wp_featured_img_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'alt' => '', 'class' => '', 'size' => 'full' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $post_id = $post_id ? $post_id : get_the_ID();

        $img_id = get_post_thumbnail_id( $post_id );
        if ( ! $img_id ) return '';

        $img_url = wp_get_attachment_image_url( $img_id, $atts['size'] );
        $alt     = $atts['alt'] ? $atts['alt'] : get_post_meta( $img_id, '_wp_attachment_image_alt', true );
        $class   = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';

        return '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $alt ) . '"' . $class . '>';
    }
    add_shortcode( 'wp_featured_img', 'cb_wp_featured_img_shortcode' );
}

// ─── Featured Image URL: [wp_featured_img_url size="full"] ──────────────
if ( ! shortcode_exists( 'wp_featured_img_url' ) ) {
    function cb_wp_featured_img_url_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'size' => 'full' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $post_id = $post_id ? $post_id : get_the_ID();

        $img_url = get_the_post_thumbnail_url( $post_id, $atts['size'] );
        return $img_url ? esc_url( $img_url ) : '';
    }
    add_shortcode( 'wp_featured_img_url', 'cb_wp_featured_img_url_shortcode' );
}

// ─── Permalink: [wp_permalink] ──────────────────────────────────────────
if ( ! shortcode_exists( 'wp_permalink' ) ) {
    function cb_wp_permalink_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts(), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        return esc_url( $post_id ? get_permalink( $post_id ) : get_permalink() );
    }
    add_shortcode( 'wp_permalink', 'cb_wp_permalink_shortcode' );
}

// ─── Debug: [acf_debug] ─────────────────────────────────────────────────
if ( ! shortcode_exists( 'acf_debug' ) ) {
    add_shortcode( 'acf_debug', function () {
        $real      = isset( $GLOBALS['cb_real_post_id'] ) ? $GLOBALS['cb_real_post_id'] : 'NULL';
        $queried   = get_queried_object_id();
        $the_id    = get_the_ID();
        $uri       = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'unknown';
        $is_product = function_exists( 'is_product' ) ? ( is_product() ? 'YES' : 'NO' ) : 'WC not active';

        $real_post    = is_int( $real ) ? get_post( $real ) : null;
        $queried_post = get_post( $queried );

        $fmt = function( $p ) {
            return $p ? '"' . $p->post_title . '" [type=' . $p->post_type . '] slug=' . $p->post_name : 'not found';
        };

        return '<pre style="background:#111;color:#0f0;padding:12px;font-size:11px;z-index:9999;position:relative;line-height:1.8">'
            . 'REQUEST_URI:                     ' . esc_html( $uri ) . "\n\n"
            . 'cb_real_post_id (wp hook):       ' . ( is_int($real) ? $real : $real ) . "\n"
            . '  > ' . $fmt( $real_post ) . "\n\n"
            . 'get_queried_object_id():         ' . $queried . "\n"
            . '  > ' . $fmt( $queried_post ) . "\n\n"
            . 'get_the_ID():                    ' . $the_id . "\n"
            . 'is_product():                    ' . $is_product . "\n"
            . 'wp hook fired:                   ' . ( is_int( $real ) ? 'YES' : 'NO' )
            . '</pre>';
    } );
}
