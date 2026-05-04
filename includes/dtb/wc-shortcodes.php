<?php
/**
 * WooCommerce product shortcodes.
 * All shortcodes auto-detect the current product on product pages.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Product Title: [wc_title] ──────────────────────────────────────────
if ( ! shortcode_exists( 'wc_title' ) ) {
    function cb_wc_title_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        return $product ? esc_html( $product->get_name() ) : '';
    }
    add_shortcode( 'wc_title', 'cb_wc_title_shortcode' );
}

// ─── Product Price (formatted): [wc_price] ──────────────────────────────
if ( ! shortcode_exists( 'wc_price' ) ) {
    function cb_wc_price_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        return $product ? $product->get_price_html() : '';
    }
    add_shortcode( 'wc_price', 'cb_wc_price_shortcode' );
}

// ─── Regular Price: [wc_regular_price] ──────────────────────────────────
if ( ! shortcode_exists( 'wc_regular_price' ) ) {
    function cb_wc_regular_price_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        if ( ! $product ) return '';
        $price = $product->get_regular_price();
        return $price ? wc_price( $price ) : '';
    }
    add_shortcode( 'wc_regular_price', 'cb_wc_regular_price_shortcode' );
}

// ─── Sale Price: [wc_sale_price] ────────────────────────────────────────
if ( ! shortcode_exists( 'wc_sale_price' ) ) {
    function cb_wc_sale_price_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        if ( ! $product ) return '';
        $price = $product->get_sale_price();
        return $price ? wc_price( $price ) : '';
    }
    add_shortcode( 'wc_sale_price', 'cb_wc_sale_price_shortcode' );
}

// ─── On Sale conditional: [wc_on_sale]...[/wc_on_sale] ─────────────────
if ( ! shortcode_exists( 'wc_on_sale' ) ) {
    function cb_wc_on_sale_shortcode( $atts, $content = null ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        if ( $product && $product->is_on_sale() ) {
            return do_shortcode( $content );
        }
        return '';
    }
    add_shortcode( 'wc_on_sale', 'cb_wc_on_sale_shortcode' );
}

// ─── SKU: [wc_sku] ─────────────────────────────────────────────────────
if ( ! shortcode_exists( 'wc_sku' ) ) {
    function cb_wc_sku_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        return $product ? esc_html( $product->get_sku() ) : '';
    }
    add_shortcode( 'wc_sku', 'cb_wc_sku_shortcode' );
}

// ─── Stock Status: [wc_stock] ───────────────────────────────────────────
if ( ! shortcode_exists( 'wc_stock' ) ) {
    function cb_wc_stock_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        if ( ! $product ) return '';

        $map = array(
            'instock'     => 'In Stock',
            'outofstock'  => 'Out of Stock',
            'onbackorder' => 'On Backorder',
        );
        $status = $product->get_stock_status();
        return isset( $map[ $status ] ) ? $map[ $status ] : esc_html( $status );
    }
    add_shortcode( 'wc_stock', 'cb_wc_stock_shortcode' );
}

// ─── Stock Status CSS class: [wc_stock_class] ───────────────────────────
if ( ! shortcode_exists( 'wc_stock_class' ) ) {
    function cb_wc_stock_class_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        return $product ? esc_attr( $product->get_stock_status() ) : '';
    }
    add_shortcode( 'wc_stock_class', 'cb_wc_stock_class_shortcode' );
}

// ─── Stock Quantity: [wc_stock_qty] ─────────────────────────────────────
if ( ! shortcode_exists( 'wc_stock_qty' ) ) {
    function cb_wc_stock_qty_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        if ( ! $product ) return '';
        $qty = $product->get_stock_quantity();
        return ( $qty !== null ) ? intval( $qty ) : '';
    }
    add_shortcode( 'wc_stock_qty', 'cb_wc_stock_qty_shortcode' );
}

// ─── Short Description: [wc_short_description] ─────────────────────────
if ( ! shortcode_exists( 'wc_short_description' ) ) {
    function cb_wc_short_desc_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        return $product ? wp_kses_post( $product->get_short_description() ) : '';
    }
    add_shortcode( 'wc_short_description', 'cb_wc_short_desc_shortcode' );
}

// ─── Full Description: [wc_description] ─────────────────────────────────
if ( ! shortcode_exists( 'wc_description' ) ) {
    function cb_wc_description_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        return $product ? wp_kses_post( wpautop( $product->get_description() ) ) : '';
    }
    add_shortcode( 'wc_description', 'cb_wc_description_shortcode' );
}

// ─── Product URL: [wc_url] ──────────────────────────────────────────────
if ( ! shortcode_exists( 'wc_url' ) ) {
    function cb_wc_url_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        return $product ? esc_url( $product->get_permalink() ) : '';
    }
    add_shortcode( 'wc_url', 'cb_wc_url_shortcode' );
}

// ─── Featured Image: [wc_featured_img class="" size="full"] ─────────────
if ( ! shortcode_exists( 'wc_featured_img' ) ) {
    function cb_wc_featured_img_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array( 'class' => '', 'size' => 'full' ) ), $atts );
        $product = cb_wc_get_product( $atts );
        if ( ! $product ) return '';

        $img_id = $product->get_image_id();
        if ( ! $img_id ) return '';

        $img_url = wp_get_attachment_image_url( $img_id, $atts['size'] );
        $alt     = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
        if ( ! $alt ) $alt = $product->get_name();
        $class = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';

        return '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $alt ) . '"' . $class . '>';
    }
    add_shortcode( 'wc_featured_img', 'cb_wc_featured_img_shortcode' );
}

// ─── Featured Image URL: [wc_featured_img_url size="full"] ──────────────
if ( ! shortcode_exists( 'wc_featured_img_url' ) ) {
    function cb_wc_featured_img_url_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array( 'size' => 'full' ) ), $atts );
        $product = cb_wc_get_product( $atts );
        if ( ! $product ) return '';

        $img_id = $product->get_image_id();
        if ( ! $img_id ) return '';

        return esc_url( wp_get_attachment_image_url( $img_id, $atts['size'] ) );
    }
    add_shortcode( 'wc_featured_img_url', 'cb_wc_featured_img_url_shortcode' );
}

// ─── Product Gallery: [wc_gallery class="" size="large" thumb_size="thumbnail"]
if ( ! shortcode_exists( 'wc_gallery' ) ) {
    function cb_wc_gallery_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array(
            'class'      => 'wc-product-gallery',
            'size'       => 'large',
            'thumb_size' => 'thumbnail',
        ) ), $atts );

        $product = cb_wc_get_product( $atts );
        if ( ! $product ) return '';

        $main_id     = $product->get_image_id();
        $gallery_ids = $product->get_gallery_image_ids();

        $all_ids = array();
        if ( $main_id ) $all_ids[] = $main_id;
        $all_ids = array_merge( $all_ids, $gallery_ids );

        if ( empty( $all_ids ) ) {
            return '<div class="' . esc_attr( $atts['class'] ) . '"><p>No images available</p></div>';
        }

        $output = '<div class="' . esc_attr( $atts['class'] ) . '">';

        // Main image
        $main_url  = wp_get_attachment_image_url( $all_ids[0], $atts['size'] );
        $main_full = wp_get_attachment_image_url( $all_ids[0], 'full' );
        $main_alt  = get_post_meta( $all_ids[0], '_wp_attachment_image_alt', true );
        $output .= '<div class="wc-gallery-main">';
        $output .= '<a href="' . esc_url( $main_full ) . '" data-lightbox="product-gallery">';
        $output .= '<img src="' . esc_url( $main_url ) . '" alt="' . esc_attr( $main_alt ) . '" id="wc-gallery-main-img">';
        $output .= '</a></div>';

        // Thumbnails
        if ( count( $all_ids ) > 1 ) {
            $output .= '<div class="wc-gallery-thumbs">';
            foreach ( $all_ids as $index => $img_id ) {
                $thumb_url = wp_get_attachment_image_url( $img_id, $atts['thumb_size'] );
                $large_url = wp_get_attachment_image_url( $img_id, $atts['size'] );
                $full_url  = wp_get_attachment_image_url( $img_id, 'full' );
                $alt       = get_post_meta( $img_id, '_wp_attachment_image_alt', true );
                $active    = ( $index === 0 ) ? ' wc-thumb-active' : '';
                $output .= '<div class="wc-gallery-thumb' . $active . '" data-large="' . esc_url( $large_url ) . '" data-full="' . esc_url( $full_url ) . '">';
                $output .= '<img src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( $alt ) . '">';
                $output .= '</div>';
            }
            $output .= '</div>';
            $output .= '<script>document.addEventListener("DOMContentLoaded",function(){var t=document.querySelectorAll(".wc-gallery-thumb"),m=document.getElementById("wc-gallery-main-img"),l=m?m.parentElement:null;t.forEach(function(e){e.addEventListener("click",function(){t.forEach(function(x){x.classList.remove("wc-thumb-active")});e.classList.add("wc-thumb-active");if(m)m.src=e.getAttribute("data-large");if(l)l.href=e.getAttribute("data-full")})})});</script>';
        }

        $output .= '</div>';
        return $output;
    }
    add_shortcode( 'wc_gallery', 'cb_wc_gallery_shortcode' );
}

// ─── Add to Cart: [wc_add_to_cart class="" text="Add to Cart"] ──────────
if ( ! shortcode_exists( 'wc_add_to_cart' ) ) {
    function cb_wc_add_to_cart_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array( 'class' => '', 'text' => 'Add to Cart' ) ), $atts );
        $product = cb_wc_get_product( $atts );
        if ( ! $product ) return '';

        if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            if ( $product->get_stock_status() === 'outofstock' ) {
                return '<p class="wc-out-of-stock">Out of Stock</p>';
            }
            return '';
        }

        $product_id = $product->get_id();

        // Variable product
        if ( $product->is_type( 'variable' ) ) {
            ob_start();
            global $post;
            $original_post = $post;
            $post = get_post( $product_id );
            setup_postdata( $post );
            wp_enqueue_script( 'wc-add-to-cart-variation' );
            woocommerce_variable_add_to_cart();
            wp_reset_postdata();
            $post = $original_post;
            return ob_get_clean();
        }

        // Grouped product
        if ( $product->is_type( 'grouped' ) ) {
            ob_start();
            global $post;
            $original_post = $post;
            $post = get_post( $product_id );
            setup_postdata( $post );
            woocommerce_grouped_add_to_cart();
            wp_reset_postdata();
            $post = $original_post;
            return ob_get_clean();
        }

        // Simple product
        $btn_class = $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '';
        $output = '<form class="cart wc-add-to-cart-form" method="post" enctype="multipart/form-data">';
        $output .= '<div class="quantity">';
        $output .= '<label class="screen-reader-text" for="quantity_' . $product_id . '">Quantity</label>';
        $output .= '<input type="number" id="quantity_' . $product_id . '" class="input-text qty text" name="quantity" value="1" min="1" step="1">';
        $output .= '</div>';
        $output .= '<button type="submit" name="add-to-cart" value="' . $product_id . '" class="single_add_to_cart_button button alt' . $btn_class . '">' . esc_html( $atts['text'] ) . '</button>';
        $output .= '</form>';

        return $output;
    }
    add_shortcode( 'wc_add_to_cart', 'cb_wc_add_to_cart_shortcode' );
}

// ─── Weight: [wc_weight] ────────────────────────────────────────────────
if ( ! shortcode_exists( 'wc_weight' ) ) {
    function cb_wc_weight_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        if ( ! $product || ! $product->get_weight() ) return '';
        return esc_html( $product->get_weight() . ' ' . get_option( 'woocommerce_weight_unit' ) );
    }
    add_shortcode( 'wc_weight', 'cb_wc_weight_shortcode' );
}

// ─── Dimensions: [wc_dimensions] ────────────────────────────────────────
if ( ! shortcode_exists( 'wc_dimensions' ) ) {
    function cb_wc_dimensions_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        if ( ! $product ) return '';
        $dims = $product->get_dimensions( false );
        if ( ! $dims || ( empty( $dims['length'] ) && empty( $dims['width'] ) && empty( $dims['height'] ) ) ) return '';
        return esc_html( wc_format_dimensions( $dims ) );
    }
    add_shortcode( 'wc_dimensions', 'cb_wc_dimensions_shortcode' );
}

// ─── Categories: [wc_categories separator=", " links="1"] ──────────────
if ( ! shortcode_exists( 'wc_categories' ) ) {
    function cb_wc_categories_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array( 'separator' => ', ', 'links' => '1' ) ), $atts );
        $product = cb_wc_get_product( $atts );
        if ( ! $product ) return '';

        $terms = get_the_terms( $product->get_id(), 'product_cat' );
        if ( ! $terms || is_wp_error( $terms ) ) return '';

        $parts = array();
        foreach ( $terms as $term ) {
            if ( $atts['links'] === '1' ) {
                $parts[] = '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
            } else {
                $parts[] = esc_html( $term->name );
            }
        }
        return implode( $atts['separator'], $parts );
    }
    add_shortcode( 'wc_categories', 'cb_wc_categories_shortcode' );
}

// ─── Tags: [wc_tags separator=", " links="1"] ──────────────────────────
if ( ! shortcode_exists( 'wc_tags' ) ) {
    function cb_wc_tags_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array( 'separator' => ', ', 'links' => '1' ) ), $atts );
        $product = cb_wc_get_product( $atts );
        if ( ! $product ) return '';

        $terms = get_the_terms( $product->get_id(), 'product_tag' );
        if ( ! $terms || is_wp_error( $terms ) ) return '';

        $parts = array();
        foreach ( $terms as $term ) {
            if ( $atts['links'] === '1' ) {
                $parts[] = '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
            } else {
                $parts[] = esc_html( $term->name );
            }
        }
        return implode( $atts['separator'], $parts );
    }
    add_shortcode( 'wc_tags', 'cb_wc_tags_shortcode' );
}

// ─── Attribute: [wc_attribute name="pa_color"] ──────────────────────────
if ( ! shortcode_exists( 'wc_attribute' ) ) {
    function cb_wc_attribute_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array( 'name' => '', 'separator' => ', ' ) ), $atts );
        $product = cb_wc_get_product( $atts );
        if ( ! $product || ! $atts['name'] ) return '';
        $value = $product->get_attribute( $atts['name'] );
        return $value ? esc_html( $value ) : '';
    }
    add_shortcode( 'wc_attribute', 'cb_wc_attribute_shortcode' );
}

// ─── Attributes Table: [wc_attributes_table class=""] ───────────────────
if ( ! shortcode_exists( 'wc_attributes_table' ) ) {
    function cb_wc_attributes_table_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array( 'class' => 'wc-attributes-table' ) ), $atts );
        $product = cb_wc_get_product( $atts );
        if ( ! $product ) return '';

        $attributes = $product->get_attributes();
        if ( empty( $attributes ) ) return '';

        $output = '<table class="' . esc_attr( $atts['class'] ) . '">';
        foreach ( $attributes as $attr ) {
            if ( $attr->get_visible() ) {
                $name  = wc_attribute_label( $attr->get_name() );
                $value = $product->get_attribute( $attr->get_name() );
                if ( $value ) {
                    $output .= '<tr><th>' . esc_html( $name ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
                }
            }
        }
        $output .= '</table>';
        return $output;
    }
    add_shortcode( 'wc_attributes_table', 'cb_wc_attributes_table_shortcode' );
}

// ─── Breadcrumb: [wc_breadcrumb] ────────────────────────────────────────
if ( ! shortcode_exists( 'wc_breadcrumb' ) ) {
    function cb_wc_breadcrumb_shortcode() {
        if ( ! function_exists( 'woocommerce_breadcrumb' ) ) return '';
        ob_start();
        woocommerce_breadcrumb();
        return ob_get_clean();
    }
    add_shortcode( 'wc_breadcrumb', 'cb_wc_breadcrumb_shortcode' );
}

// ─── Product Tabs: [wc_tabs] ────────────────────────────────────────────
if ( ! shortcode_exists( 'wc_tabs' ) ) {
    function cb_wc_tabs_shortcode( $atts ) {
        $product = cb_wc_get_product( shortcode_atts( cb_default_atts(), $atts ) );
        if ( ! $product ) return '';

        global $post;
        $original_post = $post;
        $post = get_post( $product->get_id() );
        setup_postdata( $post );

        ob_start();
        woocommerce_output_product_data_tabs();
        $output = ob_get_clean();

        wp_reset_postdata();
        $post = $original_post;
        return $output;
    }
    add_shortcode( 'wc_tabs', 'cb_wc_tabs_shortcode' );
}

// ─── Related Products: [wc_related limit="4" columns="4"] ───────────────
if ( ! shortcode_exists( 'wc_related' ) ) {
    function cb_wc_related_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array( 'limit' => '4', 'columns' => '4' ) ), $atts );
        $product = cb_wc_get_product( $atts );
        if ( ! $product ) return '';

        global $post;
        $original_post = $post;
        $post = get_post( $product->get_id() );
        setup_postdata( $post );

        ob_start();
        woocommerce_related_products( array(
            'posts_per_page' => intval( $atts['limit'] ),
            'columns'        => intval( $atts['columns'] ),
        ) );
        $output = ob_get_clean();

        wp_reset_postdata();
        $post = $original_post;
        return $output;
    }
    add_shortcode( 'wc_related', 'cb_wc_related_shortcode' );
}

// ─── Upsells: [wc_upsells limit="4" columns="4"] ───────────────────────
if ( ! shortcode_exists( 'wc_upsells' ) ) {
    function cb_wc_upsells_shortcode( $atts ) {
        $atts = shortcode_atts( cb_default_atts( array( 'limit' => '4', 'columns' => '4' ) ), $atts );
        $product = cb_wc_get_product( $atts );
        if ( ! $product ) return '';

        global $post;
        $original_post = $post;
        $post = get_post( $product->get_id() );
        setup_postdata( $post );

        ob_start();
        woocommerce_upsell_display( intval( $atts['limit'] ), intval( $atts['columns'] ) );
        $output = ob_get_clean();

        wp_reset_postdata();
        $post = $original_post;
        return $output;
    }
    add_shortcode( 'wc_upsells', 'cb_wc_upsells_shortcode' );
}

// ─── Cart URLs and counts ───────────────────────────────────────────────
if ( ! shortcode_exists( 'wc_cart_count' ) ) {
    add_shortcode( 'wc_cart_count', function () {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return '0';
        return WC()->cart->get_cart_contents_count();
    } );
}

if ( ! shortcode_exists( 'wc_cart_total' ) ) {
    add_shortcode( 'wc_cart_total', function () {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return '$0.00';
        return WC()->cart->get_cart_total();
    } );
}

if ( ! shortcode_exists( 'wc_cart_url' ) ) {
    add_shortcode( 'wc_cart_url', function () {
        return function_exists( 'wc_get_cart_url' ) ? esc_url( wc_get_cart_url() ) : '';
    } );
}

if ( ! shortcode_exists( 'wc_checkout_url' ) ) {
    add_shortcode( 'wc_checkout_url', function () {
        return function_exists( 'wc_get_checkout_url' ) ? esc_url( wc_get_checkout_url() ) : '';
    } );
}

if ( ! shortcode_exists( 'wc_myaccount_url' ) ) {
    add_shortcode( 'wc_myaccount_url', function () {
        return function_exists( 'wc_get_page_permalink' ) ? esc_url( wc_get_page_permalink( 'myaccount' ) ) : '';
    } );
}
