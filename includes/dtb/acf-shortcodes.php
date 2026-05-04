<?php
/**
 * ACF Shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Only register ACF shortcodes if ACF is active
if ( ! function_exists( 'get_field' ) ) {
    return;
}

// ─── Text: [acf_text field="field_name"] ─────────────────────────────────
if ( ! shortcode_exists( 'acf_text' ) ) {
    function cb_acf_text_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $value   = ( $post_id !== null ) ? get_field( $atts['field'], $post_id ) : get_field( $atts['field'] );

        if ( is_array( $value ) ) return '';
        return $value ? wp_kses_post( $value ) : '';
    }
    add_shortcode( 'acf_text', 'cb_acf_text_shortcode' );
}

// ─── Textarea with line breaks: [acf_textarea field="field_name"] ────────
if ( ! shortcode_exists( 'acf_textarea' ) ) {
    function cb_acf_textarea_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $value   = ( $post_id !== null ) ? get_field( $atts['field'], $post_id ) : get_field( $atts['field'] );

        if ( is_array( $value ) || ! $value ) return '';
        return wp_kses_post( nl2br( $value ) );
    }
    add_shortcode( 'acf_textarea', 'cb_acf_textarea_shortcode' );
}

// ─── Image tag: [acf_img field="field_name" alt="" class=""] ─────────────
if ( ! shortcode_exists( 'acf_img' ) ) {
    function cb_acf_img_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '', 'alt' => '', 'class' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $value   = ( $post_id !== null ) ? get_field( $atts['field'], $post_id ) : get_field( $atts['field'] );

        if ( ! $value ) return '';

        if ( is_array( $value ) ) {
            $url = isset( $value['url'] ) ? $value['url'] : '';
            $alt = $atts['alt'] ? $atts['alt'] : ( isset( $value['alt'] ) ? $value['alt'] : '' );
        } else {
            $url = $value;
            $alt = $atts['alt'];
        }

        if ( ! $url ) return '';

        $class_attr = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
        return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . $class_attr . '>';
    }
    add_shortcode( 'acf_img', 'cb_acf_img_shortcode' );
}

// ─── Image URL only: [acf_img_url field="field_name"] ───────────────────
if ( ! shortcode_exists( 'acf_img_url' ) ) {
    function cb_acf_img_url_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $value   = ( $post_id !== null ) ? get_field( $atts['field'], $post_id ) : get_field( $atts['field'] );

        if ( ! $value ) return '';

        if ( is_array( $value ) ) {
            $url = isset( $value['url'] ) ? $value['url'] : '';
        } else {
            $url = $value;
        }

        return $url ? esc_url( $url ) : '';
    }
    add_shortcode( 'acf_img_url', 'cb_acf_img_url_shortcode' );
}

// ─── Raw HTML (unescaped): [acf_raw field="field_name"] ─────────────────
if ( ! shortcode_exists( 'acf_raw' ) ) {
    function cb_acf_raw_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $value   = ( $post_id !== null ) ? get_field( $atts['field'], $post_id ) : get_field( $atts['field'] );

        if ( is_array( $value ) ) return '';
        return $value ? $value : '';
    }
    add_shortcode( 'acf_raw', 'cb_acf_raw_shortcode' );
}

// ─── Color Picker: [acf_color field="bg_color"] ─────────────────────────
if ( ! shortcode_exists( 'acf_color' ) ) {
    function cb_acf_color_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $value   = ( $post_id !== null ) ? get_field( $atts['field'], $post_id ) : get_field( $atts['field'] );

        return $value ? esc_attr( $value ) : '';
    }
    add_shortcode( 'acf_color', 'cb_acf_color_shortcode' );
}

// ─── Checkbox: [acf_checkbox field="use_county_pages" value="yes"] ──────
if ( ! shortcode_exists( 'acf_checkbox' ) ) {
    function cb_acf_checkbox_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '', 'value' => '', 'show' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $value   = ( $post_id !== null ) ? get_field( $atts['field'], $post_id ) : get_field( $atts['field'] );

        if ( $atts['show'] ) {
            if ( is_array( $value ) ) return esc_html( implode( ', ', $value ) );
            return $value ? esc_html( $value ) : '';
        }

        if ( $atts['value'] ) {
            if ( is_array( $value ) && in_array( $atts['value'], $value ) ) return 'true';
            return 'false';
        }

        return ( $value && ! empty( $value ) ) ? 'true' : 'false';
    }
    add_shortcode( 'acf_checkbox', 'cb_acf_checkbox_shortcode' );
}

// ─── Taxonomy: [acf_taxonomy field="loop_item_filter" format="links"] ───
if ( ! shortcode_exists( 'acf_taxonomy' ) ) {
    function cb_acf_taxonomy_shortcode( $atts ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '', 'format' => 'text', 'separator' => ', ' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $terms   = ( $post_id !== null ) ? get_field( $atts['field'], $post_id ) : get_field( $atts['field'] );

        if ( ! $terms ) return '';

        // Single term
        if ( is_object( $terms ) && isset( $terms->name ) ) {
            if ( $atts['format'] === 'links' ) {
                return '<a href="' . esc_url( get_term_link( $terms ) ) . '">' . esc_html( $terms->name ) . '</a>';
            }
            return esc_html( $terms->name );
        }

        if ( ! is_array( $terms ) ) return '';

        $output = array();
        foreach ( $terms as $term ) {
            if ( is_object( $term ) && isset( $term->name ) ) {
                if ( $atts['format'] === 'links' ) {
                    $output[] = '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
                } else {
                    $output[] = esc_html( $term->name );
                }
            }
        }

        return implode( $atts['separator'], $output );
    }
    add_shortcode( 'acf_taxonomy', 'cb_acf_taxonomy_shortcode' );
}

// ─── Conditional: [acf_if field="sale_badge"]...[/acf_if] ───────────────
if ( ! shortcode_exists( 'acf_if' ) ) {
    function cb_acf_if_shortcode( $atts, $content = null ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '', 'value' => '', 'not' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $field_value = ( $post_id !== null ) ? get_field( $atts['field'], $post_id ) : get_field( $atts['field'] );

        $show = false;

        if ( $atts['value'] !== '' ) {
            if ( is_array( $field_value ) ) {
                $show = in_array( $atts['value'], $field_value );
            } else {
                $show = ( (string) $field_value === (string) $atts['value'] );
            }
        } else {
            $show = ! empty( $field_value );
        }

        if ( $atts['not'] ) {
            $show = ! $show;
        }

        return $show ? do_shortcode( $content ) : '';
    }
    add_shortcode( 'acf_if', 'cb_acf_if_shortcode' );
}

// ─── Repeater: [acf_repeater field="specs"]...[/acf_repeater] ───────────
if ( ! shortcode_exists( 'acf_repeater' ) ) {
    function cb_acf_repeater_shortcode( $atts, $content = null ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $check_id = ( $post_id !== null ) ? $post_id : get_the_ID();

        if ( ! have_rows( $atts['field'], $check_id ) ) return '';

        $output = '';
        while ( have_rows( $atts['field'], $check_id ) ) {
            the_row();
            $output .= do_shortcode( $content );
        }

        return $output;
    }
    add_shortcode( 'acf_repeater', 'cb_acf_repeater_shortcode' );
}

// ─── Repeater sub-field: [acf_sub field="spec_label"] ───────────────────
if ( ! shortcode_exists( 'acf_sub' ) ) {
    function cb_acf_sub_shortcode( $atts ) {
        $atts  = shortcode_atts( array( 'field' => '' ), $atts );
        $value = get_sub_field( $atts['field'] );

        if ( is_array( $value ) ) {
            if ( isset( $value['url'] ) ) return esc_url( $value['url'] );
            return '';
        }

        return $value ? wp_kses_post( $value ) : '';
    }
    add_shortcode( 'acf_sub', 'cb_acf_sub_shortcode' );
}

// ─── Repeater sub-field image: [acf_sub_img field="icon" alt="" class=""]
if ( ! shortcode_exists( 'acf_sub_img' ) ) {
    function cb_acf_sub_img_shortcode( $atts ) {
        $atts  = shortcode_atts( array( 'field' => '', 'alt' => '', 'class' => '' ), $atts );
        $value = get_sub_field( $atts['field'] );

        if ( ! $value ) return '';

        if ( is_array( $value ) ) {
            $url = isset( $value['url'] ) ? $value['url'] : '';
            $alt = $atts['alt'] ? $atts['alt'] : ( isset( $value['alt'] ) ? $value['alt'] : '' );
        } else {
            $url = $value;
            $alt = $atts['alt'];
        }

        if ( ! $url ) return '';

        $class_attr = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
        return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . $class_attr . '>';
    }
    add_shortcode( 'acf_sub_img', 'cb_acf_sub_img_shortcode' );
}

// ─── Group: [acf_group field="group_name"]...[/acf_group] ───────────────
if ( ! shortcode_exists( 'acf_group' ) ) {
    function cb_acf_group_shortcode( $atts, $content = null ) {
        $atts    = shortcode_atts( cb_default_atts( array( 'field' => '' ) ), $atts );
        $post_id = cb_acf_resolve_post_id( $atts );
        $check_id = ( $post_id !== null ) ? $post_id : get_the_ID();

        if ( ! have_rows( $atts['field'], $check_id ) ) return '';

        $output = '';
        while ( have_rows( $atts['field'], $check_id ) ) {
            the_row();
            $output .= do_shortcode( $content );
        }

        return $output;
    }
    add_shortcode( 'acf_group', 'cb_acf_group_shortcode' );
}
