<?php
/**
 * Parent Page Filtering
 * Adds a per-page option to restrict dynamic parent/county page queries
 * to only return sibling pages (same post_parent).
 *
 * Use case: When multiple page hierarchies share the same slug
 * (e.g., /window/repair/ and /skylight/repair/), enabling this on the
 * parent page prevents their town pages from mixing in query results.
 */

if (!defined('ABSPATH')) exit;

// ========================================
// META BOX REGISTRATION
// ========================================
add_action('add_meta_boxes', 'hozio_add_query_options_meta_box');
function hozio_add_query_options_meta_box() {
    add_meta_box(
        'hozio_query_options',
        'Parent Pages Query Options',
        'hozio_query_options_meta_box_callback',
        'page',
        'side',
        'low'
    );
}

// ========================================
// META BOX RENDER
// ========================================
function hozio_query_options_meta_box_callback($post) {
    wp_nonce_field('hozio_query_options_nonce', 'hozio_query_options_nonce');

    $filter_by_parent = get_post_meta($post->ID, 'hozio_filter_by_parent_page', true);

    ?>
    <div style="padding: 10px 0;">
        <p style="margin-bottom: 12px; color: #666; font-size: 13px;">
            When enabled, the dynamic parent pages query will only return pages that share the same immediate parent page as the current page.
        </p>

        <label for="hozio_filter_by_parent_page" style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
            <input type="checkbox"
                   name="hozio_filter_by_parent_page"
                   id="hozio_filter_by_parent_page"
                   value="1"
                   <?php checked($filter_by_parent, '1'); ?> />
            Filter by parent page
        </label>

        <?php if ($filter_by_parent): ?>
            <div style="margin-top: 12px; padding: 10px; background: #d4edda; border-left: 3px solid #28a745; font-size: 12px;">
                <strong>&#10003; Active:</strong> Child pages using this as their parent will have their queries restricted to siblings only.
            </div>
        <?php endif; ?>

        <p style="margin-top: 12px; padding: 10px; background: #d1ecf1; border-left: 3px solid #0c5460; font-size: 12px;">
            <strong>Tip:</strong> Enable this on parent/service pages when multiple page hierarchies share the same slug (e.g., <code>/window/repair/</code> and <code>/skylight/repair/</code>). This prevents their town pages from mixing in query results.
        </p>
    </div>
    <?php
}

// ========================================
// SAVE HANDLER
// ========================================
add_action('save_post', 'hozio_save_query_options');
function hozio_save_query_options($post_id) {
    if (!isset($_POST['hozio_query_options_nonce']) || !wp_verify_nonce($_POST['hozio_query_options_nonce'], 'hozio_query_options_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['hozio_filter_by_parent_page'])) {
        update_post_meta($post_id, 'hozio_filter_by_parent_page', '1');
    } else {
        delete_post_meta($post_id, 'hozio_filter_by_parent_page');
    }
}
