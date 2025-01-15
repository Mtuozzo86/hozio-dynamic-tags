<?php
add_action('elementor/query/dynamic_parent_pages_query', function($query) {
    // Get the current page ID
    $current_page_id = get_the_ID();

    // ✅ Check if the "use_county_pages" custom field is enabled
    $use_county_pages = get_post_meta($current_page_id, 'use_county_pages', true);

    if ($use_county_pages) {
        // 🔄 COUNTY PAGES LOGIC
        error_log('Using County Pages logic.');

        // Get the current page slug
        $current_page_slug = basename(get_permalink($current_page_id));
        error_log('Current page slug: ' . $current_page_slug);

        // Fetch the terms assigned to the current page in the parent_pages taxonomy
        $terms = get_the_terms($current_page_id, 'parent_pages');
        error_log('Fetched terms: ' . print_r($terms, true));

        // Handle WP_Error
        if (is_wp_error($terms) || empty($terms)) {
            error_log('No valid terms found.');
            return;
        }

        // Find the exact matching term based on the parent term + page slug
        $matching_term = null;
        foreach ($terms as $term) {
            $expected_slug = $term->parent ? get_term($term->parent)->slug . '-' . $current_page_slug : '';
            if ($term->slug === $expected_slug) {
                $matching_term = $term;
                break;
            }
        }

        if ($matching_term) {
            $query->set('post_type', 'page');
            $query->set('tax_query', [
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'term_id',
                    'terms'    => $matching_term->term_id,
                    'operator' => 'IN',
                ],
            ]);
            error_log('Tax query set with term ID: ' . $matching_term->term_id);
        }

    } else {
        // ✅ OLD LOGIC
        error_log('Using old term-matching logic.');

        // Get the current page slug
        $current_page_slug = basename(get_permalink($current_page_id));

        // Fetch the terms assigned to the current page (parent_pages taxonomy)
        $terms = get_the_terms($current_page_id, 'parent_pages');

        // Find the term that matches the current page's slug
        $parent_term = null;
        if ($terms) {
            foreach ($terms as $term) {
                if ($term->slug === $current_page_slug) {
                    $parent_term = $term;
                    break;
                }
            }
        }

        if ($parent_term) {
            $query->set('post_type', 'page');
            $query->set('tax_query', [
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'slug',
                    'terms'    => $parent_term->slug,
                    'operator' => 'IN',
                ],
            ]);

            $query->set('meta_query', [
                [
                    'key'     => 'location',
                    'value'   => '',
                    'compare' => '!=',
                ],
            ]);

            $query->set('post__not_in', [$current_page_id]);
        }
    }
});
