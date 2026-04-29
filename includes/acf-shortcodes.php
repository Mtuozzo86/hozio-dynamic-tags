<?php
/**
 * ACF Field Shortcodes
 * One shortcode per ACF field name. Text fields return the raw value.
 * Image fields return a full <img> tag. Textarea fields return raw HTML.
 * Requires ACF (get_field) to be active — silently returns empty if not.
 */

if (!defined('ABSPATH')) exit;

// ── Helpers ───────────────────────────────────────────────────────────────────

function hozio_sc_text($field_name) {
    if (!function_exists('get_field')) return '';
    return (string) get_field($field_name);
}

function hozio_sc_img($field_name) {
    if (!function_exists('get_field')) return '';
    $img = get_field($field_name);
    if (empty($img) || !is_array($img)) return '';
    return '<img src="' . esc_url($img['url']) . '" alt="' . esc_attr($img['alt']) . '" loading="lazy">';
}

function hozio_sc_raw($field_name) {
    if (!function_exists('get_field')) return '';
    return (string) get_field($field_name);
}

// ── Register shortcodes ───────────────────────────────────────────────────────

// Map: shortcode_tag => acf_field_name
// (tags and field names differ only where ACF has a typo, e.g. trailing colon)

$hozio_text_fields = [

    // ── Town / HOG Page ───────────────────────────────────────────────────────

    // Hero
    'hog_hero_seo_heading'                          => 'hog_hero_seo_heading',
    'hog_hero_section_headline'                     => 'hog_hero_section_headline:', // trailing colon in ACF — fix field name if possible
    'hog_hero_body'                                 => 'hog_hero_body',

    // Outcomes
    'hog_outcomes_seo_heading'                      => 'hog_outcomes_seo_heading',
    'hog_outcomes_section_headline'                 => 'hog_outcomes_section_headline',
    'hog_outcomes_body'                             => 'hog_outcomes_body',

    // About Us
    'hog_about_us_seo_heading'                      => 'hog_about_us_seo_heading',
    'hog_about_us_section_headline'                 => 'hog_about_us_section_headline',
    'hog_about_us_body'                             => 'hog_about_us_body',

    // How It Works
    'hog_how_it_works_seo_heading'                  => 'hog_how_it_works_seo_heading',
    'hog_how_it_works_section_headline'             => 'hog_how_it_works_section_headline',
    'hog_how_it_works_body'                         => 'hog_how_it_works_body',

    // Service-Related Information
    'hog_service-related_information_seo_heading'       => 'hog_service-related_information_seo_heading',
    'hog_service-related_information_section_headline'  => 'hog_service-related_information_section_headline',
    'hog_service-related_information_body'              => 'hog_service-related_information_body',

    // FAQ Questions & Answers
    'new_hog_faq_question_1'                        => 'new_hog_faq_question_1',
    'new_hog_faq_answer_1'                          => 'new_hog_faq_answer_1',
    'new_hog_faq_question_2'                        => 'new_hog_faq_question_2',
    'new_hog_faq_answer_2'                          => 'new_hog_faq_answer_2',
    'new_hog_faq_question_3'                        => 'new_hog_faq_question_3',
    'new_hog_faq_answer_3'                          => 'new_hog_faq_answer_3',
    'new_hog_faq_question_4'                        => 'new_hog_faq_question_4',
    'new_hog_faq_answer_4'                          => 'new_hog_faq_answer_4',
    'new_hog_faq_question_5'                        => 'new_hog_faq_question_5',
    'new_hog_faq_answer_5'                          => 'new_hog_faq_answer_5',
    'new_hog_faq_question_6'                        => 'new_hog_faq_question_6',
    'new_hog_faq_answer_6'                          => 'new_hog_faq_answer_6',

    // Links
    'hog_google_maps_link'                          => 'hog_google_maps_link',
    'hog_usps_link'                                 => 'hog_usps_link',
    'hog_pharmacy_link'                             => 'hog_pharmacy_link',
    'hog_weather_link'                              => 'hog_weather_link',
    'hog_county_and_state_wiki_link'                => 'hog_county_and_state_wiki_link',

    // Misc
    'hog_location'                                  => 'location',
];

foreach ($hozio_text_fields as $tag => $field) {
    add_shortcode($tag, function($atts, $content, $shortcode_tag) use ($field) {
        return hozio_sc_text($field);
    });
}

// ── Image fields ──────────────────────────────────────────────────────────────

$hozio_img_fields = [
    'hog_hero_image'                => 'hog_hero_image',
    'hog_outcomes_image'            => 'hog_outcomes_image',
    'hog_about_us_image'            => 'hog_about_us_image',
    'hog_how_it_works_image'        => 'hog_how_it_works_image',
    'hog_service_related_info_image' => 'hog_service_related_info_image',
];

foreach ($hozio_img_fields as $tag => $field) {
    add_shortcode($tag, function($atts, $content, $shortcode_tag) use ($field) {
        return hozio_sc_img($field);
    });
}

// ── Raw HTML fields (textarea — e.g. iframe embeds) ───────────────────────────

$hozio_raw_fields = [
    'hog_gmb_map' => 'gmb_map',
];

foreach ($hozio_raw_fields as $tag => $field) {
    add_shortcode($tag, function($atts, $content, $shortcode_tag) use ($field) {
        return hozio_sc_raw($field);
    });
}

// ── Service Page ─────────────────────────────────────────────────────────────

$hozio_service_text_fields = [

    // Misc
    'hog_areas'                                         => 'hog_areas',
    'service_loop_item_description'                     => 'service_loop_item_description',
    'faq_service_page_section'                          => 'faq_service_page_section',

    // Hero
    'hero__seo_heading'                                 => 'hero__seo_heading',
    'hero__section_headline'                            => 'hero__section_headline',
    'hero__body'                                        => 'hero__body',

    // Trust Symbols
    'trust_symbols__section_headline'                   => 'trust_symbols__section_headline',
    'trust_symbols__title_1'                            => 'trust_symbols__title_1',
    'trust_symbols__description_1'                      => 'trust_symbols__description_1',
    'trust_symbols__title_2'                            => 'trust_symbols__title_2',
    'trust_symbols__description_2'                      => 'trust_symbols__description_2',
    'trust_symbols__title_3'                            => 'trust_symbols__title_3',
    'trust_symbols__description_3'                      => 'trust_symbols__description_3',
    'trust_symbols__title_4'                            => 'trust_symbols__title_4',
    'trust_symbols__description_4'                      => 'trust_symbols__description_4',

    // Service Intro
    'service_intro__seo_heading'                        => 'service_intro__seo_heading',
    'service_intro__section_headline'                   => 'service_intro__section_headline',

    // Benefits
    'benefits__seo_heading'                             => 'benefits__seo_heading',
    'benefits__section_headline'                        => 'benefits__section_headline',
    'benefits__subheadline'                             => 'benefits__subheadline',
    'benefits__bullet_text_1'                           => 'benefits__bullet_text_1',
    'benefits__bullet_text_2'                           => 'benefits__bullet_text_2',
    'benefits__bullet_text_3'                           => 'benefits__bullet_text_3',
    'benefits__bullet_text_4'                           => 'benefits__bullet_text_4',
    'benefits__bullet_text_5'                           => 'benefits__bullet_text_5',
    'benefits__bullet_text_6'                           => 'benefits__bullet_text_6',

    // Service-Related Information 1
    'service-related_information_1__seo_heading'        => 'service-related_information_1__seo_heading',
    'service-related_information_1__section_headline'   => 'service-related_information_1__section_headline',

    // Service-Related Information 2
    'service-related_information_2__seo_heading'        => 'service-related_information_2__seo_heading',
    'service-related_information_2__section_headline'   => 'service-related_information_2__section_headline',

    // FAQs
    'faq_question_1'                                    => 'faq_question_1',
    'faq_answer_1'                                      => 'faq_answer_1',
    'faq_question_2'                                    => 'faq_question_2',
    'faq_answer_2'                                      => 'faq_answer_2',
    'faq_question_3'                                    => 'faq_question_3',
    'faq_answer_3'                                      => 'faq_answer_3',
    'faq_question_4'                                    => 'faq_question_4',
    'faq_answer_4'                                      => 'faq_answer_4',
    'faq_question_5'                                    => 'faq_question_5',
    'faq_answer_5'                                      => 'faq_answer_5',
    'faq_question_6'                                    => 'faq_question_6',
    'faq_answer_6'                                      => 'faq_answer_6',

    // How it Works
    'how_it_works__seo_heading'                         => 'how_it_works__seo_heading',
    'how_it_works__section_headline'                    => 'how_it_works__section_headline',
    'how_it_works__icon_title_1'                        => 'how_it_works__icon_title_1',
    'how_it_works__icon_description_1'                  => 'how_it_works__icon_description_1',
    'how_it_works__icon_title_2'                        => 'how_it_works__icon_title_2',
    'how_it_works__icon_description_2'                  => 'how_it_works__icon_description_2',
    'how_it_works__icon_title_3'                        => 'how_it_works__icon_title_3',
    'how_it_works__icon_description_3'                  => 'how_it_works__icon_description_3',
];

foreach ($hozio_service_text_fields as $tag => $field) {
    add_shortcode($tag, function($atts, $content, $shortcode_tag) use ($field) {
        return hozio_sc_text($field);
    });
}

$hozio_service_img_fields = [
    'service_loop_item_background'          => 'service_loop_item_background',
    'hero__banner_image'                    => 'hero__banner_image',
    'service_intro__image'                  => 'service_intro__image',
    'service-related_information_1__image'  => 'service-related_information_1__image',
    'service-related_information_2__image'  => 'service-related_information_2__image',
];

foreach ($hozio_service_img_fields as $tag => $field) {
    add_shortcode($tag, function($atts, $content, $shortcode_tag) use ($field) {
        return hozio_sc_img($field);
    });
}

$hozio_service_raw_fields = [
    'service_intro__body'                   => 'service_intro__body',
    'service-related_information_1__body'   => 'service-related_information_1__body',
    'service-related_information_2__body'   => 'service-related_information_2__body',
];

foreach ($hozio_service_raw_fields as $tag => $field) {
    add_shortcode($tag, function($atts, $content, $shortcode_tag) use ($field) {
        return hozio_sc_raw($field);
    });
}

// ── Generic shortcodes: [acf], [acf_img], [acf_raw] ──────────────────────────
// These replace the ACF Pro built-in [acf] shortcode which is disabled by default.

$hozio_acf_text_handler = function($atts) {
    if (!function_exists('get_field')) return '';
    $atts = shortcode_atts(['field' => ''], $atts);
    if (empty($atts['field'])) return '';
    $value = get_field($atts['field']);
    if (is_array($value)) return '';
    return $value ? wp_kses_post((string) $value) : '';
};
add_shortcode('acf',      $hozio_acf_text_handler);
add_shortcode('acf_text', $hozio_acf_text_handler);

add_shortcode('acf_img', function($atts) {
    if (!function_exists('get_field')) return '';
    $atts = shortcode_atts(['field' => '', 'alt' => ''], $atts);
    if (empty($atts['field'])) return '';
    $img = get_field($atts['field']);
    if (empty($img)) return '';
    // ACF image fields can return an array (return format: array) or a URL string
    if (is_array($img)) {
        $url = $img['url'] ?? '';
        $alt = !empty($atts['alt']) ? $atts['alt'] : ($img['alt'] ?? '');
    } else {
        $url = (string) $img;
        $alt = $atts['alt'];
    }
    if (empty($url)) return '';
    return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy">';
});

add_shortcode('acf_raw', function($atts) {
    if (!function_exists('get_field')) return '';
    $atts = shortcode_atts(['field' => ''], $atts);
    if (empty($atts['field'])) return '';
    return (string) get_field($atts['field']);
});
