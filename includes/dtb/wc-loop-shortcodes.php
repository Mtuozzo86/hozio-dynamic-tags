<?php
/**
 * WooCommerce product loop / query shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Product Loop (native WC grid): [wc_product_loop category="" limit="8" columns="4"]
if ( ! shortcode_exists( 'wc_product_loop' ) ) {
    function cb_wc_product_loop_shortcode( $atts ) {
        if ( ! function_exists( 'wc_get_products' ) ) return '';

        $atts = shortcode_atts( array(
            'category' => '',
            'tag'      => '',
            'limit'    => '8',
            'columns'  => '4',
            'orderby'  => 'date',
            'order'    => 'DESC',
            'on_sale'  => '',
            'featured' => '',
            'class'    => 'wc-product-loop',
        ), $atts );

        $sc_atts = array();
        $sc_atts[] = 'limit="' . intval( $atts['limit'] ) . '"';
        $sc_atts[] = 'columns="' . intval( $atts['columns'] ) . '"';
        $sc_atts[] = 'orderby="' . esc_attr( $atts['orderby'] ) . '"';
        $sc_atts[] = 'order="' . esc_attr( $atts['order'] ) . '"';

        if ( $atts['category'] ) $sc_atts[] = 'category="' . esc_attr( $atts['category'] ) . '"';
        if ( $atts['tag'] )      $sc_atts[] = 'tag="' . esc_attr( $atts['tag'] ) . '"';
        if ( $atts['on_sale'] )  $sc_atts[] = 'on_sale="true"';
        if ( $atts['featured'] ) $sc_atts[] = 'visibility="featured"';

        $shortcode = '[products ' . implode( ' ', $sc_atts ) . ']';
        return '<div class="' . esc_attr( $atts['class'] ) . '">' . do_shortcode( $shortcode ) . '</div>';
    }
    add_shortcode( 'wc_product_loop', 'cb_wc_product_loop_shortcode' );
}

// ─── Custom Product Cards: [wc_cards category="" limit="8"]...[/wc_cards]
// Inner content uses [wc_title], [wc_price], [wc_url], etc.
if ( ! shortcode_exists( 'wc_cards' ) ) {
    function cb_wc_cards_shortcode( $atts, $content = null ) {
        if ( ! function_exists( 'wc_get_products' ) ) return '';

        $atts = shortcode_atts( array(
            'category' => '',
            'tag'      => '',
            'ids'      => '',
            'limit'    => '8',
            'orderby'  => 'date',
            'order'    => 'DESC',
            'on_sale'  => '',
            'featured' => '',
        ), $atts );

        $args = array(
            'limit'   => intval( $atts['limit'] ),
            'orderby' => $atts['orderby'],
            'order'   => $atts['order'],
            'status'  => 'publish',
            'return'  => 'objects',
        );

        if ( $atts['category'] ) $args['category'] = array_map( 'trim', explode( ',', $atts['category'] ) );
        if ( $atts['tag'] )      $args['tag']      = array_map( 'trim', explode( ',', $atts['tag'] ) );
        if ( $atts['ids'] )      $args['include']   = array_map( 'intval', explode( ',', $atts['ids'] ) );
        if ( $atts['on_sale'] )  $args['include']   = wc_get_product_ids_on_sale();
        if ( $atts['featured'] ) $args['featured']   = true;

        $products = wc_get_products( $args );
        if ( empty( $products ) ) return '';

        $original_real_id = isset( $GLOBALS['cb_real_post_id'] ) ? $GLOBALS['cb_real_post_id'] : null;
        global $product;
        $original_product = $product;

        $output = '';
        foreach ( $products as $prod ) {
            $GLOBALS['cb_real_post_id'] = $prod->get_id();
            $product = $prod;

            // Replace {{wc_url}} placeholder with the actual product URL.
            // We use {{wc_url}} instead of [wc_url] in href attributes because
            // WordPress's do_shortcodes_in_html_tags() processes shortcodes inside
            // HTML attributes BEFORE enclosing shortcodes set up their loop context.
            $card = str_replace( '{{wc_url}}', esc_url( $prod->get_permalink() ), $content );

            $output .= do_shortcode( $card );
        }

        $GLOBALS['cb_real_post_id'] = $original_real_id;
        $product = $original_product;

        return $output;
    }
    add_shortcode( 'wc_cards', 'cb_wc_cards_shortcode' );
}
