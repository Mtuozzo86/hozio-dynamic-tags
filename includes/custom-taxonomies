<?php


function create_parent_pages_taxonomy() {
    $args = array(
        'hierarchical' => true,
        'labels' => array(
            'name'              => 'Parent Pages (Loop Items)',
            'singular_name'     => 'Parent Page',
            'search_items'      => 'Search Parent Pages',
            'all_items'         => 'All Parent Pages',
            'parent_item'       => 'Parent Page',
            'parent_item_colon' => 'Parent Page:',
            'edit_item'         => 'Edit Parent Page',
            'update_item'       => 'Update Parent Page',
            'add_new_item'      => 'Add New Parent Page',
            'new_item_name'     => 'New Parent Page Name',
            'menu_name'         => 'Parent Pages',
        ),
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'parent-pages'),
    );

    register_taxonomy('parent_pages', 'page', $args);
}

add_action('init', 'create_parent_pages_taxonomy');

