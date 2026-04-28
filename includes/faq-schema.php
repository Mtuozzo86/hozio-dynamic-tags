<?php
/**
 * FAQ Schema Output
 * Collects Q&A pairs from all enabled sources, outputs one combined FAQPage block.
 * Reads from WP's in-memory post meta cache — no extra DB queries.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', 'hozio_output_faq_schema', 10);

function hozio_output_faq_schema() {
    if (!is_singular()) return;
    if (get_option('hozio_faq_schema_enabled', '1') !== '1') return;

    $post_id    = get_the_ID();
    $all_pairs = [];
    $has_acf   = function_exists('get_field');

    // ── Group 1: FAQ Page (ACF fields) ───────────────────────────────────────────
    if ($has_acf && get_option('hozio_faq_schema_general_enabled', '1') === '1') {
        for ($i = 1; $i <= 6; $i++) {
            $q = get_field("general_questions__question_{$i}", $post_id);
            $a = get_field("general_questions__answer_{$i}", $post_id);
            if (!empty($q) && !empty($a)) {
                $all_pairs[] = ['q' => $q, 'a' => $a];
            }
        }
    }

    // ── Group 2: Service Page FAQs (ACF) ──────────────────────────────────────
    if ($has_acf && get_option('hozio_faq_schema_service_enabled', '1') === '1') {
        $exclude = hozio_faq_parse_exclude(get_option('hozio_faq_schema_service_exclude', ''));
        if (!in_array($post_id, $exclude, true)) {
            $first_q = get_field('faq_question_1', $post_id);
            if (!empty($first_q)) {
                $all_pairs[] = ['q' => $first_q, 'a' => get_field('faq_answer_1', $post_id)];
                for ($i = 2; $i <= 6; $i++) {
                    $q = get_field("faq_question_{$i}", $post_id);
                    $a = get_field("faq_answer_{$i}", $post_id);
                    if (!empty($q) && !empty($a)) {
                        $all_pairs[] = ['q' => $q, 'a' => $a];
                    }
                }
            }
        }
    }

    // ── Group 3: Town / HOG Page FAQs (ACF) ──────────────────────────────────
    if ($has_acf && get_option('hozio_faq_schema_town_enabled', '1') === '1') {
        $exclude = hozio_faq_parse_exclude(get_option('hozio_faq_schema_town_exclude', ''));
        if (!in_array($post_id, $exclude, true)) {
            $first_q = get_field('new_hog_faq_question_1', $post_id);
            if (!empty($first_q)) {
                $all_pairs[] = ['q' => $first_q, 'a' => get_field('new_hog_faq_answer_1', $post_id)];
                for ($i = 2; $i <= 6; $i++) {
                    $q = get_field("new_hog_faq_question_{$i}", $post_id);
                    $a = get_field("new_hog_faq_answer_{$i}", $post_id);
                    if (!empty($q) && !empty($a)) {
                        $all_pairs[] = ['q' => $q, 'a' => $a];
                    }
                }
            }
        }
    }

    if (!empty($all_pairs)) {
        hozio_print_faq_schema($all_pairs);
    }
}

/**
 * Echo a single FAQPage JSON-LD block.
 *
 * @param array $pairs Array of ['q' => string, 'a' => string]
 */
function hozio_print_faq_schema(array $pairs) {
    $entities = [];
    foreach ($pairs as $pair) {
        $q = wp_strip_all_tags($pair['q']);
        $a = wp_strip_all_tags($pair['a']);
        if (empty($q) || empty($a)) continue;
        $entities[] = [
            '@type'          => 'Question',
            'name'           => $q,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $a,
            ],
        ];
    }

    if (empty($entities)) return;

    $schema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $entities,
    ];

    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}

/**
 * Parse a comma-separated exclude string into an array of integer post IDs.
 *
 * @param string $value
 * @return int[]
 */
function hozio_faq_parse_exclude($value) {
    if (empty($value)) return [];
    return array_map('intval', array_filter(array_map('trim', explode(',', $value))));
}
