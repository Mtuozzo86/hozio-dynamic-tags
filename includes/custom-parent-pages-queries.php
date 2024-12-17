<?php
add_action('elementor/query/dynamic_parent_pages_query', function($query) {
    // Get the current page ID (Service Page One)
    $current_page_id = get_the_ID();

    // Get the current page slug (title used for the taxonomy term)
    $current_page_slug = basename(get_permalink($current_page_id));

    // Debugging: Log the current page slug
    error_log('Current page slug: ' . $current_page_slug);

    // Fetch the terms assigned to the current page (parent_pages taxonomy)
    $terms = get_the_terms($current_page_id, 'parent_pages'); // Get terms for the current page

    // Debugging: Log the fetched terms
    if ($terms) {
        error_log('Fetched terms: ' . implode(', ', wp_list_pluck($terms, 'slug')));
    } else {
        error_log('No terms found for the current page.');
    }

    // Find the term that matches the current page's slug
    $parent_term = null;
    foreach ($terms as $term) {
        if ($term->slug === $current_page_slug) {
            $parent_term = $term;
            break; // Exit loop once the matching term is found
        }
    }

    // If the correct term is found, modify the query to get the child pages
    if ($parent_term) {
        // Debugging: Log the parent term slug
        error_log('Parent term slug: ' . $parent_term->slug);

        // Fetch child pages assigned to the current taxonomy term
        $query->set('post_type', 'page'); // Ensure only pages are queried
        $query->set('tax_query', [
            [
                'taxonomy' => 'parent_pages', // The taxonomy to filter by
                'field'    => 'slug',
                'terms'    => $parent_term->slug, // Use the slug of the current term to find matching pages
                'operator' => 'IN',
            ],
        ]);

        // Add meta_query to ensure 'location' field is not empty
        $query->set('meta_query', [
            [
                'key'     => 'location',
                'value'   => '',
                'compare' => '!=',
            ],
        ]);

        // Exclude the parent page from the query results (we don't want to display the parent page in the loop)
        $query->set('post__not_in', [$current_page_id]); // Exclude the current page (parent) from the query
    }
});
