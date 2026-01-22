<?php
// ========================================
// QUERY 1: Original dynamic parent pages query
// ⭐ NOW EXCLUDES pages with "county" term
// ========================================
add_action('elementor/query/dynamic_parent_pages_query', function($query) {
    // Get the current page ID
    $current_page_id = get_the_ID();

    // ✅ Check if the "use_county_pages" custom field is enabled
    $use_county_pages = get_post_meta($current_page_id, 'use_county_pages', true);

    if ($use_county_pages) {
        // 🔄 COUNTY PAGES LOGIC
        hozio_log('Using County Pages logic.', 'ParentPagesQuery');

        // Get the current page slug
        $current_page_slug = basename(get_permalink($current_page_id));
        hozio_log('Current page slug: ' . $current_page_slug, 'ParentPagesQuery');

        // Fetch the terms assigned to the current page in the parent_pages taxonomy
        $terms = get_the_terms($current_page_id, 'parent_pages');
        hozio_log('Fetched terms: ' . print_r($terms, true), 'ParentPagesQuery');

        // Handle WP_Error
        if (is_wp_error($terms) || empty($terms)) {
            hozio_log('No valid terms found.', 'ParentPagesQuery');
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

            // ⭐ UPDATED: Exclude pages with "county" term
            $query->set('tax_query', [
                'relation' => 'AND',
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'term_id',
                    'terms'    => $matching_term->term_id,
                    'operator' => 'IN',
                ],
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'slug',
                    'terms'    => 'county',
                    'operator' => 'NOT IN', // ⭐ Exclude county pages
                ],
            ]);

            $query->set('post__not_in', [$current_page_id]); // Exclude the current page
            hozio_log('Tax query set with term ID: ' . $matching_term->term_id . ' (excluding county pages)', 'ParentPagesQuery');
        }

    } else {
        // ✅ OLD LOGIC
        hozio_log('Using old term-matching logic.', 'ParentPagesQuery');

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

            // ⭐ UPDATED: Exclude pages with "county" term
            $query->set('tax_query', [
                'relation' => 'AND',
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'slug',
                    'terms'    => $parent_term->slug,
                    'operator' => 'IN',
                ],
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'slug',
                    'terms'    => 'county',
                    'operator' => 'NOT IN', // ⭐ Exclude county pages
                ],
            ]);

            $query->set('meta_query', [
                [
                    'key'     => 'location',
                    'value'   => '',
                    'compare' => '!=',
                ],
            ]);

            $query->set('post__not_in', [$current_page_id]); // Exclude the current page
        }
    }
});

// ========================================
// QUERY 2: Dynamic town pages query
// ========================================
add_action('elementor/query/dynamic_town_pages_query', function( $query ) {
    // Current page ID
    $current_id = get_queried_object_id();
    if ( ! $current_id ) {
        hozio_log('No queried object ID.', 'TownQuery');
        return;
    }

    // Get last URL segment (page slug)
    $permalink = get_permalink( $current_id );
    if ( ! $permalink ) {
        hozio_log('No permalink for current page.', 'TownQuery');
        return;
    }

    $last_segment = basename( untrailingslashit( $permalink ) ); // e.g. mike-t
    if ( empty( $last_segment ) ) {
        hozio_log('Empty last segment.', 'TownQuery');
        return;
    }

    // Find a matching term in town_taxonomies by slug
    $taxonomy = 'town_taxonomies';
    $term = get_term_by( 'slug', $last_segment, $taxonomy );

    if ( ! $term || is_wp_error( $term ) ) {
        // No matching town term, do nothing so Elementor falls back gracefully
        hozio_log('No matching term for slug ' . $last_segment, 'TownQuery');
        return;
    }

    // Build the query: pages that have this town term, excluding the current page
    $query->set( 'post_type', 'page' );
    $query->set( 'post__not_in', array( $current_id ) );
    $query->set( 'tax_query', array(
        array(
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => array( $last_segment ),
            'operator' => 'IN',
        ),
    ) );

    // Optional: order newest first (tweak to taste)
    if ( empty( $query->get( 'orderby' ) ) ) {
        $query->set( 'orderby', 'date' );
        $query->set( 'order', 'DESC' );
    }

    hozio_log('Querying pages with town term slug ' . $last_segment . ' (term_id ' . $term->term_id . ')', 'TownQuery');
});

// ========================================
// QUERY 3: NEW - Dynamic county pages query
// Only shows pages that have BOTH the matching term AND "county" term
// ========================================
add_action('elementor/query/dynamic_county_pages_query', function($query) {
    // Get the current page ID
    $current_page_id = get_the_ID();

    // Get the current page slug
    $current_page_slug = basename(get_permalink($current_page_id));

    // Fetch the terms assigned to the current page in the parent_pages taxonomy
    $terms = get_the_terms($current_page_id, 'parent_pages');

    // ⭐ Collect debug info for logging
    $debug_info = [
        'query_id' => 'dynamic_county_pages_query',
        'current_page_id' => $current_page_id,
        'current_page_slug' => $current_page_slug,
        'current_page_url' => get_permalink($current_page_id),
    ];

    // Handle WP_Error
    if (is_wp_error($terms) || empty($terms)) {
        $debug_info['error'] = 'No valid terms found';
        hozio_log($debug_info, 'CountyQuery');
        hozio_console_log($debug_info, 'County Query Debug');
        return;
    }

    // ✅ Check if the "use_county_pages" custom field is enabled
    $use_county_pages = get_post_meta($current_page_id, 'use_county_pages', true);
    $debug_info['use_county_pages'] = $use_county_pages;

    if ($use_county_pages) {
        // 🔄 COUNTY PAGES LOGIC
        $debug_info['logic_type'] = 'County Pages Logic';

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

            // ⭐ Both "sprinter-service" AND "county" are terms in the SAME parent_pages taxonomy
            $tax_query = [
                'relation' => 'AND',
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'term_id',
                    'terms'    => $matching_term->term_id,
                    'operator' => 'IN',
                ],
                [
                    'taxonomy' => 'parent_pages', // ⭐ SAME taxonomy!
                    'field'    => 'slug',
                    'terms'    => 'county', // ⭐ The "county" term
                    'operator' => 'IN',
                ],
            ];

            $query->set('tax_query', $tax_query);
            $debug_info['tax_query_set'] = $tax_query;
            $query->set('post__not_in', [$current_page_id]);
        }

    } else {
        // ✅ OLD LOGIC - Looking for pages with matching term AND "county" term
        $debug_info['logic_type'] = 'Old Term-Matching Logic';
        $debug_info['looking_for_term_slug'] = $current_page_slug;

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

        $debug_info['found_parent_term'] = $parent_term ? $parent_term->slug : 'NOT FOUND';

        if ($parent_term) {
            $query->set('post_type', 'page');

            // ⭐ Both terms are in the same parent_pages taxonomy
            $tax_query = [
                'relation' => 'AND',
                [
                    'taxonomy' => 'parent_pages',
                    'field'    => 'slug',
                    'terms'    => $parent_term->slug, // e.g., "sprinter-service"
                    'operator' => 'IN',
                ],
                [
                    'taxonomy' => 'parent_pages', // ⭐ SAME taxonomy!
                    'field'    => 'slug',
                    'terms'    => 'county', // ⭐ The "county" term
                    'operator' => 'IN',
                ],
            ];

            $query->set('tax_query', $tax_query);
            $debug_info['tax_query_set'] = $tax_query;

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

    // Log debug info using HOZIO_DEBUG (no frontend output unless explicitly enabled)
    hozio_log($debug_info, 'CountyQuery');
    hozio_console_log($debug_info, 'County Query Debug');

    // Log found pages when debug is enabled
    if (hozio_debug_enabled()) {
        add_action('wp_footer', function() use ($query) {
            $found_pages = [];
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $page_id = get_the_ID();
                    $found_pages[] = [
                        'id' => $page_id,
                        'title' => get_the_title(),
                        'url' => get_permalink(),
                        'parent_pages_terms' => wp_get_post_terms($page_id, 'parent_pages', ['fields' => 'names']),
                    ];
                }
                wp_reset_postdata();
            }
            hozio_log(['county_pages_found' => $found_pages], 'CountyQuery');
            hozio_console_log($found_pages, 'County Pages Found');
        }, 999);
    }
});

