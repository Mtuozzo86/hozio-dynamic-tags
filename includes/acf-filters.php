<?php
// Dynamically populate the "Allowed Post Types" field
add_filter( 'acf/load_field/name=allowed_post_types', function( $field ) {
    // Get all public post types
    $post_types = get_post_types( [ 'public' => true ], 'objects' );

    // Populate choices with post type labels and names
    $field['choices'] = [];
    foreach ( $post_types as $post_type ) {
        $field['choices'][ $post_type->name ] = $post_type->label;
    }

    return $field;
});
