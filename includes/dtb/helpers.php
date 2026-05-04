<?php
/**
 * Core helper functions used by all shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$GLOBALS['cb_real_post_id'] = null;

add_action( 'wp', function () {
    if ( is_singular() ) {
        $GLOBALS['cb_real_post_id'] = get_queried_object_id();
    }
} );

/**
 * Resolve the correct post ID, accounting for Elementor's query overrides.
 */
function cb_acf_resolve_post_id( $atts ) {
    if ( ! empty( $atts['post_id'] ) ) {
        return intval( $atts['post_id'] );
    }

    if ( ! empty( $atts['slug'] ) ) {
        $post_type = ! empty( $atts['post_type'] ) ? sanitize_text_field( $atts['post_type'] ) : 'any';
        $found = get_posts( array(
            'name'        => sanitize_title( $atts['slug'] ),
            'post_type'   => $post_type,
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields'      => 'ids',
        ) );
        if ( $found ) {
            return $found[0];
        }
    }

    return isset( $GLOBALS['cb_real_post_id'] ) ? $GLOBALS['cb_real_post_id'] : null;
}

/**
 * Get the WooCommerce product object for the current context.
 */
function cb_wc_get_product( $atts = array() ) {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return null;
    }

    // If explicit post_id or slug was passed, use that directly.
    if ( ! empty( $atts['post_id'] ) || ! empty( $atts['slug'] ) ) {
        $post_id = cb_acf_resolve_post_id( $atts );
        if ( $post_id ) {
            return wc_get_product( $post_id );
        }
    }

    // Check global $product first (set by wc_cards loop).
    global $product;
    if ( is_a( $product, 'WC_Product' ) ) {
        return $product;
    }

    // Fall back to current page context.
    $post_id = cb_acf_resolve_post_id( $atts );
    if ( $post_id ) {
        return wc_get_product( $post_id );
    }

    return null;
}

/**
 * Standard attribute defaults shared across all shortcodes.
 */
function cb_default_atts( $extra = array() ) {
    return array_merge( array(
        'post_id'   => '',
        'slug'      => '',
        'post_type' => '',
    ), $extra );
}
