<?php
/**
 * Hozio RSS Feed Override
 * Replaces default RSS feed content with ACF field content for Elementor-templated blog posts.
 * Only active when enabled via Blog Permalink Settings toggle.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$hozio_rss_override = get_option( 'hozio_rss_override_enabled', 0 );

if ( $hozio_rss_override ) {

    add_filter( 'the_content_feed', 'hozio_rss_content_override', 10, 2 );

    function hozio_rss_content_override( $content, $feed_type = 'rss2' ) {
        global $post;

        if ( ! $post || $post->post_type !== 'post' || ! function_exists( 'get_field' ) ) {
            return $content;
        }

        $custom_content = '';
        $post_id = $post->ID;

        // Introduction
        $introduction = get_field( 'introduction', $post_id );
        if ( $introduction ) {
            $custom_content .= '<p>' . wp_kses_post( nl2br( $introduction ) ) . '</p>';
        }

        // Section 1 (H2)
        $s1_heading = get_field( 'section_1_heading_h2', $post_id );
        $s1_body = get_field( 'section_1_body_text', $post_id );
        if ( $s1_heading ) {
            $custom_content .= '<h2>' . esc_html( $s1_heading ) . '</h2>';
        }
        if ( $s1_body ) {
            $custom_content .= '<p>' . wp_kses_post( nl2br( $s1_body ) ) . '</p>';
        }

        // Section 2 (H3)
        $s2_heading = get_field( 'section_2_heading_h3', $post_id );
        $s2_body = get_field( 'section_2__body_text', $post_id );
        if ( $s2_heading ) {
            $custom_content .= '<h3>' . esc_html( $s2_heading ) . '</h3>';
        }
        if ( $s2_body ) {
            $custom_content .= '<p>' . wp_kses_post( nl2br( $s2_body ) ) . '</p>';
        }

        // Section 3 (H3)
        $s3_heading = get_field( 'section_3__heading_h3', $post_id );
        $s3_body = get_field( 'section_3__body_text', $post_id );
        if ( $s3_heading ) {
            $custom_content .= '<h3>' . esc_html( $s3_heading ) . '</h3>';
        }
        if ( $s3_body ) {
            $custom_content .= '<p>' . wp_kses_post( nl2br( $s3_body ) ) . '</p>';
        }

        // Section 4 (H2)
        $s4_heading = get_field( 'section_4__heading_h2', $post_id );
        $s4_body = get_field( 'section_4__body_text', $post_id );
        if ( $s4_heading ) {
            $custom_content .= '<h2>' . esc_html( $s4_heading ) . '</h2>';
        }
        if ( $s4_body ) {
            $custom_content .= '<p>' . wp_kses_post( nl2br( $s4_body ) ) . '</p>';
        }

        // Section 5 (H3)
        $s5_heading = get_field( 'section_5__heading_h3', $post_id );
        $s5_body = get_field( 'section_5__body_text', $post_id );
        if ( $s5_heading ) {
            $custom_content .= '<h3>' . esc_html( $s5_heading ) . '</h3>';
        }
        if ( $s5_body ) {
            $custom_content .= '<p>' . wp_kses_post( nl2br( $s5_body ) ) . '</p>';
        }

        // Section 6 (H3)
        $s6_heading = get_field( 'section_6__heading_h3', $post_id );
        $s6_body = get_field( 'section_6__body_text', $post_id );
        if ( $s6_heading ) {
            $custom_content .= '<h3>' . esc_html( $s6_heading ) . '</h3>';
        }
        if ( $s6_body ) {
            $custom_content .= '<p>' . wp_kses_post( nl2br( $s6_body ) ) . '</p>';
        }

        // Section 7 (H2)
        $s7_heading = get_field( 'section_7__heading_h2', $post_id );
        $s7_body = get_field( 'section_7__body_text', $post_id );
        if ( $s7_heading ) {
            $custom_content .= '<h2>' . esc_html( $s7_heading ) . '</h2>';
        }
        if ( $s7_body ) {
            $custom_content .= '<p>' . wp_kses_post( nl2br( $s7_body ) ) . '</p>';
        }

        return $custom_content ?: $content;
    }

    add_filter( 'the_excerpt_rss', 'hozio_rss_excerpt_override' );

    function hozio_rss_excerpt_override( $excerpt ) {
        global $post;

        if ( ! $post || $post->post_type !== 'post' || ! function_exists( 'get_field' ) ) {
            return $excerpt;
        }

        $custom_excerpt = get_field( 'excerpt', $post->ID );

        if ( ! $custom_excerpt ) {
            $custom_excerpt = get_field( 'summary', $post->ID );
        }

        if ( ! $custom_excerpt ) {
            $custom_excerpt = get_field( 'introduction', $post->ID );
        }

        if ( $custom_excerpt ) {
            return wp_trim_words( strip_tags( $custom_excerpt ), 55 );
        }

        return $excerpt;
    }

    add_action( 'rss2_item', 'hozio_rss_featured_image_enclosure' );

    function hozio_rss_featured_image_enclosure() {
        global $post;

        if ( ! $post || $post->post_type !== 'post' ) {
            return;
        }

        // Get the featured image ID
        $thumbnail_id = get_post_thumbnail_id( $post->ID );

        if ( ! $thumbnail_id ) {
            return;
        }

        // Get the image URL (full size)
        $thumbnail_url = wp_get_attachment_url( $thumbnail_id );

        if ( ! $thumbnail_url ) {
            return;
        }

        // Get the image file path to determine file size
        $thumbnail_path = get_attached_file( $thumbnail_id );
        $file_size = file_exists( $thumbnail_path ) ? filesize( $thumbnail_path ) : 0;

        // Get the MIME type
        $mime_type = get_post_mime_type( $thumbnail_id );
        if ( ! $mime_type ) {
            $mime_type = 'image/jpeg'; // Default fallback
        }

        // Output the enclosure tag
        echo '<enclosure url="' . esc_url( $thumbnail_url ) . '" length="' . absint( $file_size ) . '" type="' . esc_attr( $mime_type ) . '" />';
    }

}
