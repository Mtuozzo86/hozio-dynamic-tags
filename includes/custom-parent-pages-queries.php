<?php
add_action('elementor/query/dynamic_parent_pages_query', function($query) {
    // Get the current page ID
    $current_page_id = get_the_ID();
    error_log('Current page ID: ' . $current_page_id);

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

    // Log the matching term, or log if no match was found
    if ($matching_term) {
        error_log('Matching term found: ' . print_r($matching_term, true));
    } else {
        error_log('No matching term found for expected slug: ' . $expected_slug);
        return;
    }

    // If a matching term is found, modify the query to get only pages assigned to that exact term
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

        // Exclude the parent page from the query results
        $query->set('post__not_in', [$current_page_id]);
        error_log('Excluding current page ID from query results: ' . $current_page_id);
    }
});
