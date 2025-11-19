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
            error_log('Tax query set with term ID: ' . $matching_term->term_id . ' (excluding county pages)');
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
        error_log('TownQuery: No queried object ID.');
        return;
    }

    // Get last URL segment (page slug)
    $permalink = get_permalink( $current_id );
    if ( ! $permalink ) {
        error_log('TownQuery: No permalink for current page.');
        return;
    }

    $last_segment = basename( untrailingslashit( $permalink ) ); // e.g. mike-t
    if ( empty( $last_segment ) ) {
        error_log('TownQuery: Empty last segment.');
        return;
    }

    // Find a matching term in town_taxonomies by slug
    $taxonomy = 'town_taxonomies';
    $term = get_term_by( 'slug', $last_segment, $taxonomy );

    if ( ! $term || is_wp_error( $term ) ) {
        // No matching town term, do nothing so Elementor falls back gracefully
        error_log('TownQuery: No matching term for slug ' . $last_segment);
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

    error_log('TownQuery: Querying pages with town term slug ' . $last_segment . ' (term_id ' . $term->term_id . ')');
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

    // ⭐ Collect debug info for console
    $debug_info = [
        'query_id' => 'dynamic_county_pages_query',
        'current_page_id' => $current_page_id,
        'current_page_slug' => $current_page_slug,
        'current_page_url' => get_permalink($current_page_id),
    ];

    // Handle WP_Error
    if (is_wp_error($terms) || empty($terms)) {
        $debug_info['error'] = 'No valid terms found';
        console_log_debug($debug_info);
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
    
    console_log_debug($debug_info);
    
    // ⭐ AFTER the query runs, log what pages were actually found
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
        
        echo '<script>';
        echo 'console.log("=== COUNTY PAGES FOUND ===");';
        echo 'console.log(' . json_encode($found_pages, JSON_PRETTY_PRINT) . ');';
        echo '</script>';
    }, 999);
});

// ⭐ Helper function to output to browser console
function console_log_debug($data) {
    add_action('wp_footer', function() use ($data) {
        echo '<script>';
        echo 'console.log("=== COUNTY QUERY DEBUG ===");';
        echo 'console.log(' . json_encode($data, JSON_PRETTY_PRINT) . ');';
        echo '</script>';
    });
}

// ========================================
// ADD CUSTOM CONTROLS TO LOOP WIDGETS
// Simple toggle - no dropdown needed
// ========================================
add_action('elementor/element/loop-grid/section_layout/after_section_end', 'add_hozio_query_controls', 10, 2);
add_action('elementor/element/loop-carousel/section_layout/after_section_end', 'add_hozio_query_controls', 10, 2);

function add_hozio_query_controls($element, $args) {
    $element->start_controls_section(
        'hozio_query_settings',
        [
            'label' => __('Hozio Query Settings', 'hozio-dynamic-tags'),
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]
    );

    $element->add_control(
        'hozio_use_custom_query',
        [
            'label' => __('Filter Pages by Taxonomy', 'hozio-dynamic-tags'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('Yes', 'hozio-dynamic-tags'),
            'label_off' => __('No', 'hozio-dynamic-tags'),
            'return_value' => 'yes',
            'default' => '',
            'description' => __('Uses the taxonomy selected in the Loop Item Filter ACF field', 'hozio-dynamic-tags'),
        ]
    );

    $element->add_control(
        'hozio_query_notice',
        [
            'type' => \Elementor\Controls_Manager::RAW_HTML,
            'raw' => __('<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 12px; border-radius: 4px; margin-top: 10px; color: #0c5460; line-height: 1.6;">
                <strong style="color: #0c5460; display: block; margin-bottom: 6px;">📌 How it works:</strong>
                Choose the taxonomy you would like to display using the <strong>Loop Item Filter</strong> ACF field on your page. This will show all pages that have that taxonomy term assigned to them.
            </div>', 'hozio-dynamic-tags'),
            'condition' => [
                'hozio_use_custom_query' => 'yes',
            ],
        ]
    );

    $element->end_controls_section();
}

// ========================================
// INTERCEPT ALL LOOP QUERIES
// Only uses ACF field selection
// ========================================
add_action('elementor/query/query_args', 'hozio_intercept_loop_query', 10, 2);

function hozio_intercept_loop_query($query_args, $widget) {
    // Only apply to loop-grid and loop-carousel widgets
    $widget_name = $widget->get_name();
    if (!in_array($widget_name, ['loop-grid', 'loop-carousel'])) {
        return $query_args;
    }
    
    // Get widget settings
    $settings = $widget->get_settings();
    
    // Check if custom query is enabled
    if (empty($settings['hozio_use_custom_query']) || $settings['hozio_use_custom_query'] !== 'yes') {
        return $query_args;
    }
    
    $current_page_id = get_the_ID();
    
    // Always use ACF filter when toggle is on
    $query_args = get_acf_filter_query_args($current_page_id, $query_args);
    
    return $query_args;
}

// ========================================
// ACF FILTER QUERY ARGS
// Uses the Loop Item Filter ACF field value
// ========================================
function get_acf_filter_query_args($current_page_id, $query_args) {
    // Get the ACF field value (term ID)
    $selected_term_id = get_field('loop_item_filter', $current_page_id);
    
    if (empty($selected_term_id)) {
        error_log('Hozio: No ACF loop_item_filter value found for page ' . $current_page_id);
        return $query_args;
    }
    
    // Get the term object
    $term = get_term($selected_term_id, 'parent_pages');
    
    if (is_wp_error($term) || !$term) {
        error_log('Hozio: Invalid term ID in ACF field: ' . $selected_term_id);
        return $query_args;
    }
    
    error_log('Hozio: Filtering by taxonomy term - ' . $term->name . ' (ID: ' . $term->term_id . ')');
    
    $query_args['post_type'] = 'page';
    $query_args['post__not_in'] = array_merge(
        isset($query_args['post__not_in']) ? $query_args['post__not_in'] : [],
        [$current_page_id]
    );
    
    $query_args['tax_query'] = [
        [
            'taxonomy' => 'parent_pages',
            'field'    => 'term_id',
            'terms'    => $term->term_id,
            'operator' => 'IN',
        ],
    ];
    
    return $query_args;
}
