<?php
/**
 * Reviews CPT loop shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'get_field' ) ) return;

// ─── Reviews Loop: [reviews_loop limit="6" orderby="rand"]...[/reviews_loop]
if ( ! shortcode_exists( 'reviews_loop' ) ) {
    function cb_reviews_loop_shortcode( $atts, $content = null ) {
        $atts = shortcode_atts( array(
            'limit'   => '6',
            'orderby' => 'date',
            'order'   => 'DESC',
        ), $atts );

        $reviews = get_posts( array(
            'post_type'      => 'review',
            'post_status'    => 'publish',
            'posts_per_page' => intval( $atts['limit'] ),
            'orderby'        => $atts['orderby'],
            'order'          => $atts['order'],
        ) );

        if ( empty( $reviews ) ) return '';

        $original_real_id = isset( $GLOBALS['cb_real_post_id'] ) ? $GLOBALS['cb_real_post_id'] : null;

        $output = '';
        foreach ( $reviews as $review ) {
            $GLOBALS['cb_real_post_id'] = $review->ID;
            $output .= do_shortcode( $content );
        }

        $GLOBALS['cb_real_post_id'] = $original_real_id;
        return $output;
    }
    add_shortcode( 'reviews_loop', 'cb_reviews_loop_shortcode' );
}
