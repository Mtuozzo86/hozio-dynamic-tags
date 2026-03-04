<?php
/*
Template Name: HTML Sitemap
*/

// Check if dark mode is enabled
$dark_mode_enabled = get_option('hozio_sitemap_dark_mode', '0') === '1';

// Set color variables based on dark mode setting
if ($dark_mode_enabled) {
    $bg_color = '#000000';
    $text_color = '#ffffff';
    $border_color = '#333333';
    $border_light = '#555555';
    $desc_color = '#cccccc';
    $accordion_bg = '#1a1a1a';
    $accordion_hover = '#2a2a2a';
    $accordion_active = '#2a2a2a';
    $link_color = '#ffffff';
} else {
    $bg_color = '#ffffff';
    $text_color = '#000000';
    $border_color = '#e0e0e0';
    $border_light = '#000000';
    $desc_color = '#666666';
    $accordion_bg = '#f9f9f9';
    $accordion_hover = '#f0f0f0';
    $accordion_active = '#e8e8e8';
    $link_color = '#000000';
}

// Get custom link colors (these override Elementor global styles if set)
$custom_link_color = get_option('hozio_sitemap_link_color', '');
$custom_link_hover_color = get_option('hozio_sitemap_link_hover_color', '');

get_header(); 
?>

<!-- SEO Meta Description -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!document.querySelector('meta[name="description"]')) {
        const metaDescription = document.createElement('meta');
        metaDescription.name = 'description';
        metaDescription.content = 'Complete HTML sitemap of our website showing all pages, posts, categories, and archives for easy navigation and search engine indexing.';
        document.head.appendChild(metaDescription);
    }
});
</script>

<div class="sitemap-wrapper">
    <main class="sitemap-main">
        <article class="sitemap-article">
            
            <!-- Page Header -->
            <header class="sitemap-header">
            <meta name="robots" content="noindex">
                <?php
                // Display page content - Required for Elementor
                if (have_posts()) :
                    while (have_posts()) : the_post();
                ?>
                <div class="sitemap-description">
                    <?php the_content(); ?>
                </div>
                <?php
                    endwhile;
                endif;
                ?>
            </header>

            <!-- Sitemap Content Grid -->
            <div class="sitemap-grid">
                
                <!-- Pages Section -->
                <section class="sitemap-section sitemap-pages">
                    <h2 class="section-title">
                        <svg class="section-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                        </svg>
                        Pages
                    </h2>

                    <?php
                    // Get all pages
                    $all_pages = get_pages(array(
                        'sort_column' => 'menu_order',
                        'sort_order' => 'ASC',
                        'post_status' => 'publish',
                        'exclude' => get_the_ID()
                    ));

                    // ========================================
                    // PERFORMANCE: Batch-prime caches (replaces ~800+ individual queries with 2-3)
                    // ========================================
                    $all_page_ids = wp_list_pluck($all_pages, 'ID');
                    if (!empty($all_page_ids)) {
                        update_meta_cache('post', $all_page_ids);
                        update_object_term_cache($all_page_ids, 'page');
                    }

                    // Build noindex set once (all meta is now cached, so these are free lookups)
                    $noindex_ids = array();
                    foreach ($all_pages as $page) {
                        $yoast_noindex = get_post_meta($page->ID, '_yoast_wpseo_meta-robots-noindex', true);
                        if ($yoast_noindex === '1') {
                            $noindex_ids[$page->ID] = true;
                        }
                    }

                    // Filter out thank you page and noindex pages
                    $all_pages = array_filter($all_pages, function($page) use ($noindex_ids) {
                        if ($page->post_name === 'thank-you') {
                            return false;
                        }
                        return !isset($noindex_ids[$page->ID]);
                    });

                    // Build children-by-parent lookup (replaces ~57 get_children queries with 0)
                    $children_by_parent = array();
                    foreach ($all_pages as $page) {
                        if ($page->post_parent) {
                            $children_by_parent[$page->post_parent][] = $page;
                        }
                    }
                    // Sort each parent's children by title
                    foreach ($children_by_parent as &$children) {
                        usort($children, function($a, $b) {
                            return strcmp($a->post_title, $b->post_title);
                        });
                    }
                    unset($children);

                    // ========================================
                    // HELPER: Get children from in-memory lookup (0 queries)
                    // ========================================
                    if (!function_exists('hozio_sitemap_get_children')) {
                        function hozio_sitemap_get_children($parent_id) {
                            // Uses the pre-built lookup passed via global
                            global $hozio_sitemap_children_lookup;
                            return isset($hozio_sitemap_children_lookup[$parent_id])
                                ? $hozio_sitemap_children_lookup[$parent_id]
                                : array();
                        }
                    }
                    // Set global for the helper function
                    $GLOBALS['hozio_sitemap_children_lookup'] = $children_by_parent;

                    // ========================================
                    // HELPER: Get taxonomy terms for a page (reads from primed cache, 0 queries)
                    // ========================================
                    if (!function_exists('hozio_sitemap_get_tax_names')) {
                        function hozio_sitemap_get_tax_names($page_id) {
                            $terms = wp_get_post_terms($page_id, 'parent_pages', array('fields' => 'names'));
                            return is_wp_error($terms) ? array() : $terms;
                        }
                    }

                    // ========================================
                    // HELPER: Build accordion title with parent prefix (meta is cached, 0 queries)
                    // ========================================
                    if (!function_exists('hozio_sitemap_accordion_title')) {
                        function hozio_sitemap_accordion_title($page) {
                            $title = $page->post_title ? $page->post_title : 'Untitled';
                            $filter_by_parent = get_post_meta($page->ID, 'hozio_filter_by_parent_page', true);
                            if ($filter_by_parent) {
                                $wp_parent_id = $page->post_parent;
                                if ($wp_parent_id) {
                                    $parent_title = get_the_title($wp_parent_id);
                                    if ($parent_title) {
                                        $title = $parent_title . ' ' . $title;
                                    }
                                }
                            }
                            return $title;
                        }
                    }

                    // ========================================
                    // HELPER: Build manual override accordions from saved layout
                    // ========================================
                    if (!function_exists('hozio_sitemap_build_overrides')) {
                        function hozio_sitemap_build_overrides($all_pages, $noindex_ids) {
                            $overrides = get_option('hozio_sitemap_layout_overrides', array());

                            $empty_result = array(
                                'accordions'   => array(),
                                'consumed_ids' => array(),
                                'excluded_ids' => array(),
                                'mode'         => 'override_first',
                            );

                            if (empty($overrides) || empty($overrides['enabled']) || empty($overrides['accordions'])) {
                                return $empty_result;
                            }

                            // Build pages lookup
                            $pages_by_id = array();
                            foreach ($all_pages as $page) {
                                $pages_by_id[$page->ID] = $page;
                            }

                            $consumed_ids = array();
                            $excluded_ids = isset($overrides['exclude_ids']) ? array_map('intval', $overrides['exclude_ids']) : array();

                            $built_accordions = array();
                            foreach ($overrides['accordions'] as $acc_data) {
                                $acc = hozio_sitemap_build_override_accordion($acc_data, $pages_by_id, $consumed_ids, $noindex_ids);
                                if ($acc) {
                                    $built_accordions[] = $acc;
                                }
                            }

                            usort($built_accordions, function($a, $b) {
                                return ($a['order'] ?? 0) - ($b['order'] ?? 0);
                            });

                            return array(
                                'accordions'   => $built_accordions,
                                'consumed_ids' => $consumed_ids,
                                'excluded_ids' => $excluded_ids,
                                'mode'         => isset($overrides['mode']) ? $overrides['mode'] : 'override_first',
                            );
                        }
                    }

                    if (!function_exists('hozio_sitemap_build_override_accordion')) {
                        function hozio_sitemap_build_override_accordion($data, &$pages_by_id, &$consumed_ids, $noindex_ids) {
                            $page_id = intval($data['page_id']);
                            if (!isset($pages_by_id[$page_id]) || isset($noindex_ids[$page_id])) {
                                return null;
                            }

                            $consumed_ids[] = $page_id;
                            $page = $pages_by_id[$page_id];

                            $sub_accordions = array();
                            $child_pages = array();

                            if (!empty($data['children'])) {
                                foreach ($data['children'] as $child_data) {
                                    $child_id = intval($child_data['page_id']);
                                    if (!isset($pages_by_id[$child_id]) || isset($noindex_ids[$child_id])) {
                                        continue;
                                    }

                                    if (!empty($child_data['children'])) {
                                        $sub = hozio_sitemap_build_override_accordion($child_data, $pages_by_id, $consumed_ids, $noindex_ids);
                                        if ($sub) {
                                            $sub_accordions[] = $sub;
                                        }
                                    } else {
                                        $consumed_ids[] = $child_id;
                                        $child_pages[] = $pages_by_id[$child_id];
                                    }
                                }
                            }

                            return array(
                                'page'           => $page,
                                'sub_accordions' => $sub_accordions,
                                'child_pages'    => $child_pages,
                                'order'          => isset($data['order']) ? intval($data['order']) : 0,
                            );
                        }
                    }

                    if (!function_exists('hozio_sitemap_render_override_accordion')) {
                        function hozio_sitemap_render_override_accordion($accordion, $level) {
                            $page = $accordion['page'];
                            $title = hozio_sitemap_accordion_title($page);
                            $sub_accordions = $accordion['sub_accordions'];
                            $child_pages = $accordion['child_pages'];
                            $total_count = count($child_pages) + 1;
                            $sub_count = count($sub_accordions);
                            foreach ($sub_accordions as $sub) {
                                $total_count += count($sub['child_pages']) + 1;
                                if (!empty($sub['sub_accordions'])) {
                                    foreach ($sub['sub_accordions'] as $subsub) {
                                        $total_count += count($subsub['child_pages']) + 1;
                                    }
                                    $total_count += count($sub['sub_accordions']);
                                }
                            }
                            $level_class = 'sitemap-accordion-level-' . min($level, 3);

                            // If no children and no sub-accordions, render as plain link
                            if (empty($child_pages) && empty($sub_accordions)) {
                                ?>
                                <ul class="sitemap-list accordion-child-list"><li class="sitemap-item">
                                    <a href="<?php echo get_permalink($page->ID); ?>" class="sitemap-link">
                                        <?php echo esc_html($title); ?>
                                    </a>
                                </li></ul>
                                <?php
                                return;
                            }
                            ?>
                            <div class="sitemap-accordion <?php echo $level_class; ?>">
                                <div class="sitemap-accordion-header" role="button" tabindex="0" aria-expanded="false">
                                    <span class="accordion-title">
                                        <?php echo esc_html($title); ?>
                                        <span class="accordion-count">(<?php echo $total_count; ?>)</span>
                                        <?php if ($sub_count > 0): ?>
                                            <span class="accordion-nested-badge">
                                                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="9" height="9" rx="1.5"/><rect x="6" y="6" width="9" height="9" rx="1.5"/></svg>
                                                <?php echo $sub_count; ?> sub-section<?php echo $sub_count > 1 ? 's' : ''; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <svg class="accordion-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </div>
                                <div class="sitemap-accordion-content">
                                    <!-- Pages (visible by default, toggle at top) -->
                                    <?php $page_count = count($child_pages) + 1; ?>
                                    <?php if (!empty($sub_accordions)): ?>
                                        <div class="pages-drawer-divider" role="button" tabindex="0" aria-expanded="true" data-page-count="<?php echo $page_count; ?>">
                                            <span class="pages-drawer-line"></span>
                                            <span class="pages-drawer-label">
                                                <span class="pages-drawer-text">Hide <?php echo $page_count; ?> page<?php echo $page_count > 1 ? 's' : ''; ?></span>
                                                <svg class="pages-drawer-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 15 12 9 18 15"></polyline></svg>
                                            </span>
                                            <span class="pages-drawer-line"></span>
                                        </div>
                                        <div class="pages-drawer-content">
                                    <?php endif; ?>
                                    <ul class="sitemap-list accordion-child-list">
                                        <li class="sitemap-item">
                                            <a href="<?php echo get_permalink($page->ID); ?>" class="sitemap-link">
                                                <?php echo esc_html($title); ?>
                                            </a>
                                        </li>
                                        <?php foreach ($child_pages as $child): ?>
                                            <li class="sitemap-item">
                                                <a href="<?php echo get_permalink($child->ID); ?>" class="sitemap-link">
                                                    <?php echo esc_html($child->post_title ? $child->post_title : 'Untitled'); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (!empty($sub_accordions)): ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Sub-accordions -->
                                    <?php foreach ($sub_accordions as $sub): ?>
                                        <?php hozio_sitemap_render_override_accordion($sub, $level + 1); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php
                        }
                    }

                    // ========================================
                    // PHASE 0: Manual Override Check
                    // ========================================
                    $override_result = hozio_sitemap_build_overrides($all_pages, $noindex_ids);
                    $override_accordions = $override_result['accordions'];
                    $override_consumed_ids = $override_result['consumed_ids'];
                    $override_excluded_ids = $override_result['excluded_ids'];
                    $run_auto_detection = ($override_result['mode'] !== 'manual_only');

                    // ========================================
                    // CLASSIFICATION: Build nested data structure
                    // ========================================
                    $services_accordion = null;     // The Services page + its nested hubs
                    $standalone_accordions = array(); // SPLI pages NOT under a Service Hub
                    $regular_pages = array();        // Everything else
                    $consumed_ids = array_merge($override_consumed_ids, $override_excluded_ids);

                    // First pass: index pages by ID and find the Services page
                    $pages_by_id = array();
                    $services_page = null;
                    foreach ($all_pages as $page) {
                        $pages_by_id[$page->ID] = $page;
                        if ($page->post_name === 'services') {
                            $services_page = $page;
                        }
                    }

                    // Auto-detection: taxonomy-based classification (skipped in manual_only mode)
                    if ($run_auto_detection):

                    // Build lookup: which pages are Service Hubs and which are SPLIs
                    // (term cache is primed, so wp_get_post_terms reads from cache — 0 queries)
                    $service_hub_ids = array();
                    $spli_ids = array();
                    foreach ($all_pages as $page) {
                        // Skip the Services page itself
                        if ($services_page && $page->ID === $services_page->ID) continue;

                        $tax_names = hozio_sitemap_get_tax_names($page->ID);
                        $is_hub = in_array('Service Hub', $tax_names);
                        $is_spli = in_array('Service Pages Loop Item', $tax_names);

                        // Service Hub takes priority over SPLI
                        if ($is_hub) {
                            $service_hub_ids[$page->ID] = $page;
                        } elseif ($is_spli) {
                            $spli_ids[$page->ID] = $page;
                        }
                    }

                    // Build the Services accordion structure (skip if already handled by manual overrides)
                    if ($services_page && !in_array($services_page->ID, $consumed_ids)) {
                        $consumed_ids[] = $services_page->ID;

                        $services_children = hozio_sitemap_get_children($services_page->ID);
                        $hubs = array();
                        $services_other_children = array();

                        foreach ($services_children as $child) {
                            if (isset($service_hub_ids[$child->ID])) {
                                // This child is a Service Hub — build its sub-structure
                                $consumed_ids[] = $child->ID;
                                $hub_children = hozio_sitemap_get_children($child->ID);
                                $hub_services = array();
                                $hub_other_children = array();

                                foreach ($hub_children as $hub_child) {
                                    if (isset($spli_ids[$hub_child->ID])) {
                                        // SPLI under a Service Hub — build its town pages
                                        $consumed_ids[] = $hub_child->ID;
                                        $town_pages = hozio_sitemap_get_children($hub_child->ID);
                                        foreach ($town_pages as $town) {
                                            $consumed_ids[] = $town->ID;
                                        }
                                        $hub_services[] = array(
                                            'page'     => $hub_child,
                                            'children' => $town_pages
                                        );
                                    } else {
                                        // Non-SPLI child of the hub — plain link
                                        $consumed_ids[] = $hub_child->ID;
                                        $hub_other_children[] = $hub_child;
                                    }
                                }

                                $hubs[] = array(
                                    'page'           => $child,
                                    'services'       => $hub_services,
                                    'other_children' => $hub_other_children
                                );
                            } elseif (isset($spli_ids[$child->ID])) {
                                // SPLI directly under Services (no hub) — treat as hub-level item
                                $consumed_ids[] = $child->ID;
                                $town_pages = hozio_sitemap_get_children($child->ID);
                                foreach ($town_pages as $town) {
                                    $consumed_ids[] = $town->ID;
                                }
                                // Add as a "hub" with no sub-hubs, just direct children
                                $hubs[] = array(
                                    'page'           => $child,
                                    'services'       => array(),
                                    'other_children' => $town_pages
                                );
                            } else {
                                // Regular child of Services page — plain link
                                $consumed_ids[] = $child->ID;
                                $services_other_children[] = $child;
                            }
                        }

                        $services_accordion = array(
                            'page'           => $services_page,
                            'hubs'           => $hubs,
                            'other_children' => $services_other_children
                        );
                    }

                    // Standalone SPLI pages (not under a Service Hub or Services page)
                    foreach ($spli_ids as $spli_id => $spli_page) {
                        if (in_array($spli_id, $consumed_ids)) continue;

                        $children = hozio_sitemap_get_children($spli_id);
                        $consumed_ids[] = $spli_id;

                        if (!empty($children)) {
                            foreach ($children as $child) {
                                $consumed_ids[] = $child->ID;
                            }
                            $standalone_accordions[] = array(
                                'page'     => $spli_page,
                                'children' => $children
                            );
                        }
                        // If no children, it will fall through to regular pages
                    }

                    // Service Hub pages not under Services (standalone hubs)
                    foreach ($service_hub_ids as $hub_id => $hub_page) {
                        if (in_array($hub_id, $consumed_ids)) continue;

                        $consumed_ids[] = $hub_id;
                        $hub_children = hozio_sitemap_get_children($hub_id);
                        $hub_services = array();
                        $hub_other_children = array();

                        foreach ($hub_children as $hub_child) {
                            $consumed_ids[] = $hub_child->ID;
                            if (isset($spli_ids[$hub_child->ID])) {
                                $town_pages = hozio_sitemap_get_children($hub_child->ID);
                                foreach ($town_pages as $town) {
                                    $consumed_ids[] = $town->ID;
                                }
                                $hub_services[] = array(
                                    'page'     => $hub_child,
                                    'children' => $town_pages
                                );
                            } else {
                                $hub_other_children[] = $hub_child;
                            }
                        }

                        if (!empty($hub_services) || !empty($hub_other_children)) {
                            $standalone_accordions[] = array(
                                'page'           => $hub_page,
                                'services'       => $hub_services,
                                'other_children' => $hub_other_children
                            );
                        }
                    }

                    // Implicit hubs: regular pages (no Service Hub taxonomy) that have SPLI children
                    // This handles sites where SPLI pages live under normal parent pages
                    foreach ($all_pages as $page) {
                        if (in_array($page->ID, $consumed_ids)) continue;
                        if (isset($service_hub_ids[$page->ID]) || isset($spli_ids[$page->ID])) continue;

                        // Check if this page has any SPLI children that haven't been consumed yet
                        $page_children = hozio_sitemap_get_children($page->ID);
                        $implicit_services = array();
                        $implicit_other_children = array();

                        foreach ($page_children as $child) {
                            if (in_array($child->ID, $consumed_ids)) continue;

                            if (isset($spli_ids[$child->ID])) {
                                // SPLI child — build its town pages
                                $consumed_ids[] = $child->ID;
                                $town_pages = hozio_sitemap_get_children($child->ID);
                                foreach ($town_pages as $town) {
                                    $consumed_ids[] = $town->ID;
                                }
                                $implicit_services[] = array(
                                    'page'     => $child,
                                    'children' => $town_pages
                                );
                            } else {
                                // Non-SPLI child — will be a plain link inside the accordion
                                $implicit_other_children[] = $child;
                            }
                        }

                        // If this page has SPLI children, treat it as an implicit hub
                        if (!empty($implicit_services)) {
                            $consumed_ids[] = $page->ID;
                            foreach ($implicit_other_children as $child) {
                                $consumed_ids[] = $child->ID;
                            }
                            $standalone_accordions[] = array(
                                'page'           => $page,
                                'services'       => $implicit_services,
                                'other_children' => $implicit_other_children
                            );
                        }
                    }

                    endif; // $run_auto_detection

                    // Pages with WordPress children (not caught by taxonomy detection)
                    // If a page has child pages that haven't been consumed, treat it as an accordion
                    foreach ($all_pages as $page) {
                        if (in_array($page->ID, $consumed_ids)) continue;

                        $wp_children = hozio_sitemap_get_children($page->ID);
                        if (empty($wp_children)) continue;

                        // Filter out already-consumed children
                        $available_children = array();
                        foreach ($wp_children as $child) {
                            if (!in_array($child->ID, $consumed_ids)) {
                                $available_children[] = $child;
                            }
                        }
                        if (empty($available_children)) continue;

                        // This page has unconsumed WP children — make it an accordion
                        $consumed_ids[] = $page->ID;
                        foreach ($available_children as $child) {
                            $consumed_ids[] = $child->ID;
                        }
                        $standalone_accordions[] = array(
                            'page'     => $page,
                            'children' => $available_children
                        );
                    }

                    // Regular pages: everything not consumed
                    foreach ($all_pages as $page) {
                        if (!in_array($page->ID, $consumed_ids)) {
                            $regular_pages[] = $page;
                        }
                    }

                    usort($regular_pages, function($a, $b) {
                        return strcmp($a->post_title, $b->post_title);
                    });
                    ?>

                    <!-- Regular Pages (displayed first, above accordions) -->
                    <?php if (!empty($regular_pages)): ?>
                        <ul class="sitemap-list">
                            <?php foreach ($regular_pages as $page): ?>
                                <li class="sitemap-item">
                                    <a href="<?php echo get_permalink($page->ID); ?>" class="sitemap-link">
                                        <?php echo esc_html($page->post_title ? $page->post_title : 'Untitled'); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <!-- Manual Override Accordions -->
                    <?php if (!empty($override_accordions)): ?>
                        <div class="sitemap-accordions">
                            <?php foreach ($override_accordions as $accordion): ?>
                                <?php hozio_sitemap_render_override_accordion($accordion, 1); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Services Accordion (nested: Services → Hubs → SPLIs → Towns) -->
                    <?php if ($services_accordion): ?>
                        <?php
                        // Calculate total pages inside Services accordion
                        $services_total = 1 + count($services_accordion['other_children']);
                        $services_sub_sections = 0;
                        foreach ($services_accordion['hubs'] as $h) {
                            $services_total += 1 + count($h['other_children']);
                            if (!empty($h['services']) || !empty($h['other_children'])) {
                                $services_sub_sections++;
                            }
                            foreach ($h['services'] as $s) {
                                $services_total += 1 + count($s['children']);
                            }
                        }
                        ?>
                        <div class="sitemap-accordions">
                            <div class="sitemap-accordion sitemap-accordion-level-1">
                                <div class="sitemap-accordion-header" role="button" tabindex="0" aria-expanded="false">
                                    <span class="accordion-title">
                                        <?php echo esc_html($services_accordion['page']->post_title ? $services_accordion['page']->post_title : 'Services'); ?>
                                        <span class="accordion-count">(<?php echo $services_total; ?>)</span>
                                        <?php if ($services_sub_sections > 0): ?>
                                            <span class="accordion-nested-badge">
                                                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="9" height="9" rx="1.5"/><rect x="6" y="6" width="9" height="9" rx="1.5"/></svg>
                                                <?php echo $services_sub_sections; ?> sub-section<?php echo $services_sub_sections > 1 ? 's' : ''; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <svg class="accordion-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </div>
                                <div class="sitemap-accordion-content">
                                    <!-- Services page link + plain children (visible by default, toggle at top) -->
                                    <?php $services_page_count = 1 + count($services_accordion['other_children']); ?>
                                    <?php if (!empty($services_accordion['hubs'])): ?>
                                        <div class="pages-drawer-divider" role="button" tabindex="0" aria-expanded="true" data-page-count="<?php echo $services_page_count; ?>">
                                            <span class="pages-drawer-line"></span>
                                            <span class="pages-drawer-label">
                                                <span class="pages-drawer-text">Hide <?php echo $services_page_count; ?> page<?php echo $services_page_count > 1 ? 's' : ''; ?></span>
                                                <svg class="pages-drawer-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 15 12 9 18 15"></polyline></svg>
                                            </span>
                                            <span class="pages-drawer-line"></span>
                                        </div>
                                        <div class="pages-drawer-content">
                                    <?php endif; ?>
                                    <ul class="sitemap-list accordion-child-list">
                                        <li class="sitemap-item">
                                            <a href="<?php echo get_permalink($services_accordion['page']->ID); ?>" class="sitemap-link">
                                                <?php echo esc_html($services_accordion['page']->post_title ? $services_accordion['page']->post_title : 'Services'); ?>
                                            </a>
                                        </li>
                                        <?php foreach ($services_accordion['other_children'] as $child): ?>
                                            <li class="sitemap-item">
                                                <a href="<?php echo get_permalink($child->ID); ?>" class="sitemap-link">
                                                    <?php echo esc_html($child->post_title ? $child->post_title : 'Untitled'); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (!empty($services_accordion['hubs'])): ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Service Hub sub-accordions -->
                                    <?php foreach ($services_accordion['hubs'] as $hub): ?>
                                        <?php if (!empty($hub['services'])): ?>
                                            <!-- Hub has SPLI children — render as nested accordion -->
                                            <?php
                                            // Calculate total pages inside this hub
                                            $hub_count = 1 + count($hub['other_children']);
                                            $hub_sub_sections = 0;
                                            $hub_plain_pages = 1 + count($hub['other_children']); // hub page + other children
                                            foreach ($hub['services'] as $s) {
                                                $hub_count += 1 + count($s['children']);
                                                if (!empty($s['children'])) {
                                                    $hub_sub_sections++;
                                                } else {
                                                    $hub_plain_pages++; // childless SPLIs show as plain links
                                                }
                                            }
                                            ?>
                                            <div class="sitemap-accordion sitemap-accordion-level-2">
                                                <div class="sitemap-accordion-header" role="button" tabindex="0" aria-expanded="false">
                                                    <span class="accordion-title">
                                                        <?php echo esc_html($hub['page']->post_title ? $hub['page']->post_title : 'Untitled'); ?>
                                                        <span class="accordion-count">(<?php echo $hub_count; ?>)</span>
                                                        <?php if ($hub_sub_sections > 0): ?>
                                                            <span class="accordion-nested-badge">
                                                                <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="9" height="9" rx="1.5"/><rect x="6" y="6" width="9" height="9" rx="1.5"/></svg>
                                                                <?php echo $hub_sub_sections; ?> sub-section<?php echo $hub_sub_sections > 1 ? 's' : ''; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($hub_sub_sections > 0 && $hub_plain_pages > 0): ?>
                                                            <span class="accordion-pages-badge">
                                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
                                                                + <?php echo $hub_plain_pages; ?> page<?php echo $hub_plain_pages > 1 ? 's' : ''; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <svg class="accordion-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="6 9 12 15 18 9"></polyline>
                                                    </svg>
                                                </div>
                                                <div class="sitemap-accordion-content">
                                                    <!-- Pages (visible by default, toggle at top) -->
                                                    <?php if ($hub_sub_sections > 0): ?>
                                                        <div class="pages-drawer-divider" role="button" tabindex="0" aria-expanded="true" data-page-count="<?php echo $hub_plain_pages; ?>">
                                                            <span class="pages-drawer-line"></span>
                                                            <span class="pages-drawer-label">
                                                                <span class="pages-drawer-text">Hide <?php echo $hub_plain_pages; ?> page<?php echo $hub_plain_pages > 1 ? 's' : ''; ?></span>
                                                                <svg class="pages-drawer-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 15 12 9 18 15"></polyline></svg>
                                                            </span>
                                                            <span class="pages-drawer-line"></span>
                                                        </div>
                                                        <div class="pages-drawer-content">
                                                    <?php endif; ?>
                                                    <ul class="sitemap-list accordion-child-list">
                                                        <li class="sitemap-item">
                                                            <a href="<?php echo get_permalink($hub['page']->ID); ?>" class="sitemap-link">
                                                                <?php echo esc_html($hub['page']->post_title ? $hub['page']->post_title : 'Untitled'); ?>
                                                            </a>
                                                        </li>
                                                        <?php // Childless SPLIs first (e.g., Roof Installation, Roof Maintenance) ?>
                                                        <?php foreach ($hub['services'] as $service): ?>
                                                            <?php if (empty($service['children'])): ?>
                                                                <li class="sitemap-item">
                                                                    <a href="<?php echo get_permalink($service['page']->ID); ?>" class="sitemap-link">
                                                                        <?php echo esc_html($service['page']->post_title ? $service['page']->post_title : 'Untitled'); ?>
                                                                    </a>
                                                                </li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                        <?php // Then other children (town pages) ?>
                                                        <?php foreach ($hub['other_children'] as $child): ?>
                                                            <li class="sitemap-item">
                                                                <a href="<?php echo get_permalink($child->ID); ?>" class="sitemap-link">
                                                                    <?php echo esc_html($child->post_title ? $child->post_title : 'Untitled'); ?>
                                                                </a>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                    <?php if ($hub_sub_sections > 0): ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- SPLI sub-accordions -->
                                                    <?php foreach ($hub['services'] as $service): ?>
                                                        <?php if (!empty($service['children'])): ?>
                                                            <?php $spli_count = 1 + count($service['children']); ?>
                                                            <div class="sitemap-accordion sitemap-accordion-level-3">
                                                                <div class="sitemap-accordion-header" role="button" tabindex="0" aria-expanded="false">
                                                                    <span class="accordion-title">
                                                                        <?php echo esc_html(hozio_sitemap_accordion_title($service['page'])); ?>
                                                                        <span class="accordion-count">(<?php echo $spli_count; ?>)</span>
                                                                    </span>
                                                                    <svg class="accordion-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <polyline points="6 9 12 15 18 9"></polyline>
                                                                    </svg>
                                                                </div>
                                                                <div class="sitemap-accordion-content">
                                                                    <ul class="sitemap-list accordion-child-list">
                                                                        <li class="sitemap-item">
                                                                            <a href="<?php echo get_permalink($service['page']->ID); ?>" class="sitemap-link">
                                                                                <?php echo esc_html($service['page']->post_title ? $service['page']->post_title : 'Untitled'); ?>
                                                                            </a>
                                                                        </li>
                                                                        <?php foreach ($service['children'] as $town): ?>
                                                                            <li class="sitemap-item">
                                                                                <a href="<?php echo get_permalink($town->ID); ?>" class="sitemap-link">
                                                                                    <?php echo esc_html($town->post_title ? $town->post_title : 'Untitled'); ?>
                                                                                </a>
                                                                            </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Hub has no SPLI children — render as simple accordion with plain links -->
                                            <?php if (!empty($hub['other_children'])): ?>
                                                <?php $hub_count = 1 + count($hub['other_children']); ?>
                                                <div class="sitemap-accordion sitemap-accordion-level-2">
                                                    <div class="sitemap-accordion-header" role="button" tabindex="0" aria-expanded="false">
                                                        <span class="accordion-title">
                                                            <?php echo esc_html($hub['page']->post_title ? $hub['page']->post_title : 'Untitled'); ?>
                                                            <span class="accordion-count">(<?php echo $hub_count; ?>)</span>
                                                        </span>
                                                        <svg class="accordion-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="6 9 12 15 18 9"></polyline>
                                                        </svg>
                                                    </div>
                                                    <div class="sitemap-accordion-content">
                                                        <ul class="sitemap-list accordion-child-list">
                                                            <li class="sitemap-item">
                                                                <a href="<?php echo get_permalink($hub['page']->ID); ?>" class="sitemap-link">
                                                                    <?php echo esc_html($hub['page']->post_title ? $hub['page']->post_title : 'Untitled'); ?>
                                                                </a>
                                                            </li>
                                                            <?php foreach ($hub['other_children'] as $child): ?>
                                                                <li class="sitemap-item">
                                                                    <a href="<?php echo get_permalink($child->ID); ?>" class="sitemap-link">
                                                                        <?php echo esc_html($child->post_title ? $child->post_title : 'Untitled'); ?>
                                                                    </a>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Standalone Accordions (SPLI or Hub pages not under Services) -->
                    <?php if (!empty($standalone_accordions)): ?>
                        <div class="sitemap-accordions">
                            <?php foreach ($standalone_accordions as $accordion): ?>
                                <?php if (isset($accordion['services']) && !empty($accordion['services'])): ?>
                                    <!-- Standalone Hub with SPLI children -->
                                    <?php
                                    // Calculate total pages inside this standalone hub
                                    $sa_hub_count = 1 + count($accordion['other_children'] ?? []);
                                    $sa_hub_sub_sections = 0;
                                    $sa_hub_plain_pages = 1 + count($accordion['other_children'] ?? []);
                                    foreach ($accordion['services'] as $s) {
                                        $sa_hub_count += 1 + count($s['children']);
                                        if (!empty($s['children'])) {
                                            $sa_hub_sub_sections++;
                                        } else {
                                            $sa_hub_plain_pages++;
                                        }
                                    }
                                    ?>
                                    <div class="sitemap-accordion sitemap-accordion-level-1">
                                        <div class="sitemap-accordion-header" role="button" tabindex="0" aria-expanded="false">
                                            <span class="accordion-title">
                                                <?php echo esc_html($accordion['page']->post_title ? $accordion['page']->post_title : 'Untitled'); ?>
                                                <span class="accordion-count">(<?php echo $sa_hub_count; ?>)</span>
                                                <?php if ($sa_hub_sub_sections > 0): ?>
                                                    <span class="accordion-nested-badge">
                                                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="9" height="9" rx="1.5"/><rect x="6" y="6" width="9" height="9" rx="1.5"/></svg>
                                                        <?php echo $sa_hub_sub_sections; ?> sub-section<?php echo $sa_hub_sub_sections > 1 ? 's' : ''; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($sa_hub_sub_sections > 0 && $sa_hub_plain_pages > 0): ?>
                                                    <span class="accordion-pages-badge">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
                                                        + <?php echo $sa_hub_plain_pages; ?> page<?php echo $sa_hub_plain_pages > 1 ? 's' : ''; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                            <svg class="accordion-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </div>
                                        <div class="sitemap-accordion-content">
                                            <!-- Pages (visible by default, toggle at top) -->
                                            <?php if ($sa_hub_sub_sections > 0): ?>
                                                <div class="pages-drawer-divider" role="button" tabindex="0" aria-expanded="true" data-page-count="<?php echo $sa_hub_plain_pages; ?>">
                                                    <span class="pages-drawer-line"></span>
                                                    <span class="pages-drawer-label">
                                                        <span class="pages-drawer-text">Hide <?php echo $sa_hub_plain_pages; ?> page<?php echo $sa_hub_plain_pages > 1 ? 's' : ''; ?></span>
                                                        <svg class="pages-drawer-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 15 12 9 18 15"></polyline></svg>
                                                    </span>
                                                    <span class="pages-drawer-line"></span>
                                                </div>
                                                <div class="pages-drawer-content">
                                            <?php endif; ?>
                                            <ul class="sitemap-list accordion-child-list">
                                                <li class="sitemap-item">
                                                    <a href="<?php echo get_permalink($accordion['page']->ID); ?>" class="sitemap-link">
                                                        <?php echo esc_html($accordion['page']->post_title ? $accordion['page']->post_title : 'Untitled'); ?>
                                                    </a>
                                                </li>
                                                <?php // Childless SPLIs first ?>
                                                <?php foreach ($accordion['services'] as $service): ?>
                                                    <?php if (empty($service['children'])): ?>
                                                        <li class="sitemap-item">
                                                            <a href="<?php echo get_permalink($service['page']->ID); ?>" class="sitemap-link">
                                                                <?php echo esc_html($service['page']->post_title ? $service['page']->post_title : 'Untitled'); ?>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <?php // Then other children (town pages) ?>
                                                <?php if (isset($accordion['other_children'])): ?>
                                                    <?php foreach (($accordion['other_children'] ?? []) as $child): ?>
                                                        <li class="sitemap-item">
                                                            <a href="<?php echo get_permalink($child->ID); ?>" class="sitemap-link">
                                                                <?php echo esc_html($child->post_title ? $child->post_title : 'Untitled'); ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </ul>
                                            <?php if ($sa_hub_sub_sections > 0): ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php // SPLI sub-accordions ?>
                                            <?php foreach ($accordion['services'] as $service): ?>
                                                <?php if (!empty($service['children'])): ?>
                                                    <?php $sa_spli_count = 1 + count($service['children']); ?>
                                                    <div class="sitemap-accordion sitemap-accordion-level-2">
                                                        <div class="sitemap-accordion-header" role="button" tabindex="0" aria-expanded="false">
                                                            <span class="accordion-title">
                                                                <?php echo esc_html(hozio_sitemap_accordion_title($service['page'])); ?>
                                                                <span class="accordion-count">(<?php echo $sa_spli_count; ?>)</span>
                                                            </span>
                                                            <svg class="accordion-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <polyline points="6 9 12 15 18 9"></polyline>
                                                            </svg>
                                                        </div>
                                                        <div class="sitemap-accordion-content">
                                                            <ul class="sitemap-list accordion-child-list">
                                                                <li class="sitemap-item">
                                                                    <a href="<?php echo get_permalink($service['page']->ID); ?>" class="sitemap-link">
                                                                        <?php echo esc_html($service['page']->post_title ? $service['page']->post_title : 'Untitled'); ?>
                                                                    </a>
                                                                </li>
                                                                <?php foreach ($service['children'] as $town): ?>
                                                                    <li class="sitemap-item">
                                                                        <a href="<?php echo get_permalink($town->ID); ?>" class="sitemap-link">
                                                                            <?php echo esc_html($town->post_title ? $town->post_title : 'Untitled'); ?>
                                                                        </a>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Standalone SPLI accordion (backward compat) -->
                                    <?php $sa_simple_count = 1 + count($accordion['children'] ?? []); ?>
                                    <div class="sitemap-accordion">
                                        <div class="sitemap-accordion-header" role="button" tabindex="0" aria-expanded="false">
                                            <span class="accordion-title">
                                                <?php echo esc_html(hozio_sitemap_accordion_title($accordion['page'])); ?>
                                                <span class="accordion-count">(<?php echo $sa_simple_count; ?>)</span>
                                            </span>
                                            <svg class="accordion-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </div>
                                        <div class="sitemap-accordion-content">
                                            <ul class="sitemap-list accordion-child-list">
                                                <li class="sitemap-item">
                                                    <a href="<?php echo get_permalink($accordion['page']->ID); ?>" class="sitemap-link">
                                                        <?php echo esc_html($accordion['page']->post_title ? $accordion['page']->post_title : 'Untitled'); ?>
                                                    </a>
                                                </li>
                                                <?php foreach (($accordion['children'] ?? []) as $child): ?>
                                                    <li class="sitemap-item">
                                                        <a href="<?php echo get_permalink($child->ID); ?>" class="sitemap-link">
                                                            <?php echo esc_html($child->post_title ? $child->post_title : 'Untitled'); ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Posts Section - ENTIRE SECTION AS ACCORDION -->
                <?php
                $recent_posts = get_posts(array(
                    'numberposts' => 20,
                    'post_status' => 'publish',
                    'orderby' => 'date',
                    'order' => 'DESC'
                ));
                
                $recent_posts = array_filter($recent_posts, function($post) {
                    $yoast_noindex = get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true);
                    return $yoast_noindex !== '1';
                });
                
                if (!empty($recent_posts)):
                ?>
                <section class="sitemap-section sitemap-posts sitemap-section-accordion">
                    <h2 class="section-title section-accordion-header" role="button" tabindex="0" aria-expanded="false">
                        <div class="section-title-content">
                            <svg class="section-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9"/>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                            </svg>
                            <span>Recent Posts <span class="post-count-small">(<?php echo count($recent_posts); ?>)</span></span>
                        </div>
                        <div class="section-accordion-trigger">
                            <span class="accordion-helper-text">View All</span>
                            <svg class="section-accordion-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                    </h2>
                    
                    <div class="section-accordion-content">
                        <ul class="sitemap-list">
                            <?php
                            foreach ($recent_posts as $post) {
                                setup_postdata($post);
                                
                                echo '<li class="sitemap-item">';
                                echo '<a href="' . get_permalink($post->ID) . '" class="sitemap-link">';
                                echo esc_html($post->post_title);
                                echo '</a>';
                                echo '<span class="post-date">' . get_the_date('M j, Y', $post->ID) . '</span>';
                                echo '</li>';
                            }
                            wp_reset_postdata();
                            ?>
                        </ul>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Custom Post Types Section -->
                <?php
                // Get the selected post types from ACF field on THIS page
                $included_post_types = get_field('post_types_to_show_in_sitemap');

                // If post types are selected, display them
                if (!empty($included_post_types)) {
                    
                    foreach ($included_post_types as $post_type_slug) {
                        
                        // Get the post type object for labels
                        $post_type_obj = get_post_type_object($post_type_slug);
                        
                        if (!$post_type_obj) continue; // Skip if post type doesn't exist
                        
                        // Query posts for this custom post type
                        $cpt_posts = get_posts(array(
                            'post_type' => $post_type_slug,
                            'numberposts' => -1,
                            'post_status' => 'publish',
                            'orderby' => 'title',
                            'order' => 'ASC'
                        ));
                        
                        // Filter out Yoast noindex posts
                        $cpt_posts = array_filter($cpt_posts, function($post) {
                            $yoast_noindex = get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true);
                            return $yoast_noindex !== '1';
                        });
                        
                        // Only show section if posts exist
                        if (!empty($cpt_posts)):
                ?>
                <section class="sitemap-section sitemap-<?php echo esc_attr($post_type_slug); ?> sitemap-section-accordion">
                    <h2 class="section-title section-accordion-header" role="button" tabindex="0" aria-expanded="false">
                        <div class="section-title-content">
                            <svg class="section-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                            <span><?php echo esc_html($post_type_obj->labels->name); ?> <span class="post-count-small">(<?php echo count($cpt_posts); ?>)</span></span>
                        </div>
                        <div class="section-accordion-trigger">
                            <span class="accordion-helper-text">View All</span>
                            <svg class="section-accordion-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                    </h2>
                    
                    <div class="section-accordion-content">
                        <ul class="sitemap-list">
                            <?php foreach ($cpt_posts as $cpt_post): ?>
                                <li class="sitemap-item">
                                    <a href="<?php echo get_permalink($cpt_post->ID); ?>" class="sitemap-link">
                                        <?php echo esc_html($cpt_post->post_title ? $cpt_post->post_title : 'Untitled'); ?>
                                    </a>
                                    <span class="post-date"><?php echo get_the_date('M j, Y', $cpt_post->ID); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>
                <?php 
                        endif; // end if posts exist
                    } // end foreach
                } // end if included_post_types
                ?>

                <!-- Categories Section -->
                <section class="sitemap-section sitemap-categories">
                    <h3 class="section-title">
                        <svg class="section-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                        Categories
                    </h3>
                    <ul class="sitemap-list">
                        <?php
                        $categories = get_categories(array(
                            'orderby' => 'name',
                            'order' => 'ASC',
                            'hide_empty' => true
                        ));
                        
                        foreach ($categories as $category) {
                            echo '<li class="sitemap-item">';
                            echo '<a href="' . get_category_link($category->term_id) . '" class="sitemap-link">';
                            echo esc_html($category->name);
                            echo '</a>';
                            echo '<span class="post-count">(' . $category->count . ')</span>';
                            echo '</li>';
                        }
                        ?>
                    </ul>
                </section>

                <!-- Tags Section -->
                <section class="sitemap-section sitemap-tags">
                    <h3 class="section-title">
                        <svg class="section-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                            <line x1="7" y1="7" x2="7.01" y2="7"/>
                        </svg>
                        Tags
                    </h3>
                    <div class="tag-cloud">
                        <?php
                        $tags = get_tags(array(
                            'orderby' => 'count',
                            'order' => 'DESC',
                            'hide_empty' => true,
                            'number' => 30
                        ));
                        
                        foreach ($tags as $tag) {
                            echo '<a href="' . get_tag_link($tag->term_id) . '" class="tag-link">';
                            echo esc_html($tag->name);
                            echo '</a>';
                        }
                        ?>
                    </div>
                </section>

            </div>
        </article>
    </main>
</div>

<?php
// Output styles with PHP variables
echo '<style>';
?>
.sitemap-wrapper * {
    box-sizing: border-box;
}

.sitemap-wrapper {
    width: 100%;
    margin: 0;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    line-height: 1.6;
    overflow-x: hidden;
}

.sitemap-main {
    background: <?php echo $bg_color; ?>;
    border-radius: 0;
    box-shadow: none;
}

.sitemap-header {
    background: <?php echo $bg_color; ?>;
    color: <?php echo $text_color; ?>;
    padding: 0;
    text-align: center;
    width: 100%;
    margin: 0;
}

.sitemap-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 1rem 0;
    text-shadow: none;
    color: <?php echo $text_color; ?>;
}

.sitemap-description {
    font-size: 1.1rem;
    opacity: 1;
    max-width: none;
    margin: 0;
    color: <?php echo $desc_color; ?>;
    width: 100%;
}

.sitemap-description p {
    margin: 0;
}

.sitemap-grid {
    display: block;
    padding: 2rem 1rem;
    max-width: 1200px;
    margin: 0 auto;
}

.sitemap-section {
    background: <?php echo $bg_color; ?>;
    border-radius: 15px;
    padding: 1.5rem;
    border: 1px solid <?php echo $border_color; ?>;
    transition: none;
    margin-bottom: 2rem;
    box-shadow: none;
}

.sitemap-section:hover {
    transform: none;
    box-shadow: none;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: <?php echo $text_color; ?>;
    margin: 0 0 1.5rem 0;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid <?php echo $border_light; ?>;
}

.section-icon {
    color: <?php echo $text_color; ?>;
    flex-shrink: 0;
}

/* Ensure all headings use correct color */
.sitemap-section h2,
.sitemap-section h3,
h2.section-title,
h3.section-title {
    color: <?php echo $text_color; ?> !important;
}

/* Section Accordion Styles for Posts */
.sitemap-section-accordion {
    overflow: hidden;
    cursor: pointer;
    transition: background 0.2s ease;
}

.sitemap-section-accordion:hover {
    background: rgba(0, 0, 0, 0.03);
}

.section-accordion-header {
    cursor: pointer;
    user-select: none;
    justify-content: space-between;
    margin-bottom: -0.75rem !important;
    padding-bottom: 0.75rem !important;
    transition: all 0.2s ease;
    position: relative;
    border-bottom: none !important;
}

.section-accordion-header.active {
    border-bottom: 1px solid <?php echo $border_light; ?> !important;
    margin-bottom: 0 !important;
}

.section-title-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.post-count-small {
    font-size: 0.875rem;
    font-weight: 400;
    opacity: 0.7;
    margin-left: 0.25rem;
}

.section-accordion-trigger {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.accordion-helper-text {
    font-size: 1rem;
    font-weight: 500;
    color: <?php echo $text_color; ?>;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.section-accordion-icon {
    flex-shrink: 0;
    transition: transform 0.3s ease;
    color: <?php echo $text_color; ?>;
    stroke: <?php echo $text_color; ?>;
    fill: none;
    width: 24px;
    height: 24px;
}

.sitemap-section-accordion:hover .section-accordion-icon {
    transform: translateY(2px);
}

.section-accordion-header.active .section-accordion-icon {
    transform: rotate(180deg);
}

.sitemap-section-accordion:hover .section-accordion-header.active .section-accordion-icon {
    transform: rotate(180deg) translateY(2px);
}

.section-accordion-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    padding-top: 0;
}

.section-accordion-content.active {
    max-height: 3000px;
    padding-top: 1.5rem;
}

/* Pages Accordion Styles */
.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordions {
    margin-top: 40px;
    margin-bottom: 1.5rem;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion {
    margin-bottom: 1rem;
    border: none;
    border-radius: 8px;
    overflow: hidden;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header {
    width: 100% !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    padding: 1rem 1.25rem !important;
    background: <?php echo $accordion_bg; ?> !important;
    background-color: <?php echo $accordion_bg; ?> !important;
    border: none !important;
    border-radius: 8px !important;
    cursor: pointer !important;
    font-weight: 600 !important;
    color: <?php echo $text_color; ?> !important;
    text-align: left !important;
    transition: background-color 0.2s ease !important;
    box-shadow: none !important;
    text-shadow: none !important;
    text-decoration: none !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif !important;
    line-height: normal !important;
    letter-spacing: normal !important;
    text-transform: none !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header:hover {
    background: <?php echo $accordion_hover; ?> !important;
    background-color: <?php echo $accordion_hover; ?> !important;
    color: <?php echo $text_color; ?> !important;
    box-shadow: none !important;
    transform: none !important;
    border: none !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header.active {
    background: <?php echo $accordion_active; ?> !important;
    background-color: <?php echo $accordion_active; ?> !important;
    color: <?php echo $text_color; ?> !important;
    box-shadow: none !important;
    border: none !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header:focus {
    outline: none !important;
    box-shadow: none !important;
    background: <?php echo $accordion_hover; ?> !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-title.accordion-title {
    flex-grow: 1 !important;
    color: <?php echo $text_color; ?> !important;
    text-shadow: none !important;
    font-weight: 600 !important;
    font-size: 18px !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-icon.accordion-icon {
    flex-shrink: 0 !important;
    transition: transform 0.3s ease !important;
    color: <?php echo $text_color; ?> !important;
    stroke: <?php echo $text_color; ?> !important;
    fill: none !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.active .accordion-icon {
    transform: rotate(180deg) !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-content.sitemap-accordion-content {
    max-height: 0 !important;
    overflow: hidden !important;
    transition: max-height 0.3s ease !important;
    background: <?php echo $bg_color; ?> !important;
    background-color: <?php echo $bg_color; ?> !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-content.active {
    max-height: 100% !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-child-list.accordion-child-list {
    padding: 40px 1.25rem !important;
    margin: 0 !important;
    background: <?php echo $bg_color; ?> !important;
    background-color: <?php echo $bg_color; ?> !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-child-list .sitemap-link.sitemap-link {
    font-size: 16px !important;
    text-decoration: none !important;
    font-weight: 500 !important;
}

/* Accordion child lists keep the same 3-column grid as regular lists */

/* Accordion count badge */
.sitemap-wrapper .accordion-count {
    font-size: 13px !important;
    font-weight: 400 !important;
    opacity: 0.6;
    margin-left: 0.5rem;
}

/* Nested sub-sections indicator badge */
.sitemap-wrapper .accordion-nested-badge {
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    background: <?php echo $dark_mode_enabled ? 'rgba(100,160,255,0.15)' : 'rgba(0,90,200,0.08)'; ?> !important;
    color: <?php echo $dark_mode_enabled ? '#8bb8ff' : '#0059c7'; ?> !important;
    padding: 3px 12px !important;
    border-radius: 12px !important;
    margin-left: 0.75rem !important;
    white-space: nowrap !important;
    vertical-align: middle !important;
}

.sitemap-wrapper .accordion-nested-badge svg {
    flex-shrink: 0 !important;
    opacity: 0.85 !important;
}

/* Pages indicator badge (shown when hub has both sub-accordions AND plain pages) */
.sitemap-wrapper .accordion-pages-badge {
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    background: <?php echo $dark_mode_enabled ? 'rgba(160,160,160,0.15)' : 'rgba(0,0,0,0.05)'; ?> !important;
    color: <?php echo $dark_mode_enabled ? '#aaaaaa' : '#555555'; ?> !important;
    padding: 3px 12px !important;
    border-radius: 12px !important;
    margin-left: 0.5rem !important;
    white-space: nowrap !important;
    vertical-align: middle !important;
}

.sitemap-wrapper .accordion-pages-badge svg {
    flex-shrink: 0 !important;
    opacity: 0.7 !important;
}

/* Pages Drawer — collapsible page list with centered divider toggle */
.sitemap-wrapper .pages-drawer-content {
    overflow: hidden !important;
    transition: max-height 0.4s ease !important;
}

.sitemap-wrapper .pages-drawer-divider {
    display: flex !important;
    align-items: center !important;
    gap: 16px !important;
    padding: 8px 4px !important;
    margin: 4px 0 8px 0 !important;
    cursor: pointer !important;
    user-select: none !important;
    border: none !important;
    background: none !important;
}
.sitemap-wrapper .pages-drawer-divider:hover .pages-drawer-label {
    color: <?php echo $link_color; ?> !important;
}
.sitemap-wrapper .pages-drawer-divider:hover .pages-drawer-line {
    border-color: <?php echo $dark_mode_enabled ? '#555555' : '#bbb'; ?> !important;
}
.sitemap-wrapper .pages-drawer-divider:hover .pages-drawer-chevron {
    color: <?php echo $link_color; ?> !important;
}

.sitemap-wrapper .pages-drawer-line {
    flex: 1 !important;
    height: 0 !important;
    border: none !important;
    border-top: 1px dashed <?php echo $dark_mode_enabled ? '#444444' : '#d0d0d0'; ?> !important;
    transition: border-color 0.2s !important;
}

.sitemap-wrapper .pages-drawer-label {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    color: <?php echo $dark_mode_enabled ? '#888888' : '#999999'; ?> !important;
    white-space: nowrap !important;
    transition: color 0.2s !important;
    letter-spacing: 0.3px !important;
}

.sitemap-wrapper .pages-drawer-text {
    font-size: 13px !important;
    font-weight: 500 !important;
}

.sitemap-wrapper .pages-drawer-chevron {
    flex-shrink: 0 !important;
    color: <?php echo $dark_mode_enabled ? '#888888' : '#999999'; ?> !important;
    transition: transform 0.3s ease, color 0.2s !important;
}
.sitemap-wrapper .pages-drawer-divider.collapsed .pages-drawer-chevron {
    transform: rotate(180deg) !important;
}

/* Nested Accordion Styles — Width reduction per nesting level */
/* Each level gets progressively narrower and centered */
.sitemap-wrapper .sitemap-accordion .sitemap-accordion {
    border: none !important;
    box-shadow: none !important;
    overflow: hidden !important;
}

/* Spacing between sibling sub-accordions */
.sitemap-wrapper .sitemap-accordion .sitemap-accordion-level-2 + .sitemap-accordion-level-2 {
    margin-top: 14px !important;
}

.sitemap-wrapper .sitemap-accordion .sitemap-accordion-level-3 + .sitemap-accordion-level-3 {
    margin-top: 10px !important;
}

/* Top padding for first sub-accordion inside content (no child-list before it) */
.sitemap-wrapper .sitemap-accordion .sitemap-accordion-content > .sitemap-accordion:first-child {
    margin-top: 12px !important;
}

/* Spacing after sub-accordions before the child list */
.sitemap-wrapper .sitemap-accordion .sitemap-accordion-level-2 + .accordion-child-list,
.sitemap-wrapper .sitemap-accordion .sitemap-accordion-level-3 + .accordion-child-list {
    margin-top: 10px !important;
}

/* Spacing before the first sub-accordion when it follows a child list */
.sitemap-wrapper .sitemap-accordion .accordion-child-list + .sitemap-accordion-level-2,
.sitemap-wrapper .sitemap-accordion .accordion-child-list + .sitemap-accordion-level-3 {
    margin-top: 10px !important;
}

/* Level 2 — e.g., Gutter Services inside Services */
.sitemap-wrapper .sitemap-accordion .sitemap-accordion-level-2 {
    margin: 0 2rem !important;
    border-radius: 6px !important;
}

.sitemap-wrapper .sitemap-accordion-level-2 > .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header {
    padding: 0.85rem 1.25rem !important;
    background: <?php echo $dark_mode_enabled ? '#222222' : '#f0f0f0'; ?> !important;
    border-radius: 6px !important;
    border: none !important;
    box-shadow: none !important;
}

.sitemap-wrapper .sitemap-accordion-level-2 > .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header:hover {
    background: <?php echo $dark_mode_enabled ? '#2a2a2a' : '#e8e8e8'; ?> !important;
}

.sitemap-wrapper .sitemap-accordion-level-2 > .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header.active {
    background: <?php echo $dark_mode_enabled ? '#2a2a2a' : '#e5e5e5'; ?> !important;
    border-radius: 6px 6px 0 0 !important;
}

.sitemap-wrapper .sitemap-accordion-level-2 > .sitemap-accordion-header .accordion-title {
    font-size: 16px !important;
    font-weight: 600 !important;
}

/* Level 3 — e.g., Gutter Installation inside Gutter Services */
.sitemap-wrapper .sitemap-accordion .sitemap-accordion .sitemap-accordion-level-3 {
    margin: 0 1.5rem !important;
    border-radius: 5px !important;
}

.sitemap-wrapper .sitemap-accordion-level-3 > .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header {
    padding: 0.7rem 1.25rem !important;
    background: <?php echo $dark_mode_enabled ? '#2a2a2a' : '#e8e8e8'; ?> !important;
    border-radius: 5px !important;
    border: none !important;
    box-shadow: none !important;
}

.sitemap-wrapper .sitemap-accordion-level-3 > .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header:hover {
    background: <?php echo $dark_mode_enabled ? '#333333' : '#dedede'; ?> !important;
}

.sitemap-wrapper .sitemap-accordion-level-3 > .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header.active {
    background: <?php echo $dark_mode_enabled ? '#333333' : '#d8d8d8'; ?> !important;
    border-radius: 5px 5px 0 0 !important;
}

.sitemap-wrapper .sitemap-accordion-level-3 > .sitemap-accordion-header .accordion-title {
    font-size: 14px !important;
    font-weight: 500 !important;
}

/* Nested accordion content */
.sitemap-wrapper .sitemap-accordion .sitemap-accordion .sitemap-accordion-content {
    background: <?php echo $bg_color; ?> !important;
    border-radius: 0 0 6px 6px !important;
}

.sitemap-wrapper .sitemap-accordion .sitemap-accordion .accordion-child-list {
    padding: 20px 1.25rem !important;
}

/* List Styles */
.sitemap-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem 1rem;
    align-items: stretch;
}

.sitemap-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    margin-bottom: 0;
    background: none;
    border-radius: 0;
    box-shadow: none;
    border: none;
    border-bottom: 1px solid <?php echo $border_color; ?>;
    box-sizing: border-box;
}

.sitemap-item:last-child {
    margin-bottom: 0;
}

.sitemap-link {
    color: <?php echo $link_color; ?>;
    text-decoration: none;
    font-weight: 500;
    transition: none;
    flex-grow: 1;
    font-size: 16px !important;
}

.sitemap-link:hover {
    color: <?php echo $link_color; ?>;
    text-decoration: underline;
}

.post-date,
.post-count {
    font-size: 0.875rem;
    color: <?php echo $desc_color; ?>;
    font-weight: 400;
    flex-shrink: 0;
    margin-left: 1rem;
}

.tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.tag-link {
    display: inline-block;
    padding: 0.375rem 0.75rem;
    background: <?php echo $bg_color; ?>;
    color: <?php echo $text_color; ?>;
    text-decoration: none;
    border-radius: 0;
    font-size: 0.875rem;
    font-weight: 500;
    transition: none;
    border: 1px solid <?php echo $text_color; ?>;
    margin: 0.25rem;
}

.tag-link:hover {
    background: <?php echo $bg_color; ?>;
    transform: none;
    box-shadow: none;
    text-decoration: underline;
}

@media (max-width: 768px) {
    .sitemap-wrapper {
        margin: 0 auto;
        padding: 0 20px;
    }

    .sitemap-grid {
        display: block;
        padding: 1rem 0;
    }

    .sitemap-header {
        padding: 1rem 0 0.5rem;
    }

    .sitemap-title {
        font-size: 2rem;
    }

    .sitemap-section {
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .sitemap-list {
        grid-template-columns: repeat(2, 1fr);
    }

    .sitemap-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .post-date,
    .post-count {
        margin-left: 0;
        font-size: 0.8rem;
    }

    .tag-cloud {
        gap: 0.375rem;
    }

    .tag-link {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }

    /* Reduce nested accordion indentation on tablet */
    .sitemap-wrapper .sitemap-accordion .sitemap-accordion-level-2 {
        margin: 0 0.75rem !important;
    }
    .sitemap-wrapper .sitemap-accordion .sitemap-accordion .sitemap-accordion-level-3 {
        margin: 0 0.5rem !important;
    }

    /* Reduce accordion content padding on tablet */
    .sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-child-list.accordion-child-list {
        padding: 20px 0.75rem !important;
    }
    .sitemap-wrapper .sitemap-accordion .sitemap-accordion .accordion-child-list {
        padding: 15px 0.75rem !important;
    }

    /* Tighter accordion header padding */
    .sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header {
        padding: 0.75rem 1rem !important;
    }
}

@media (max-width: 480px) {
    .sitemap-wrapper {
        margin: 0 auto;
        padding: 0 20px;
    }

    .sitemap-grid {
        display: block;
        padding: 0.5rem 0;
    }

    .sitemap-section {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .sitemap-list {
        grid-template-columns: 1fr;
    }

    .sitemap-title {
        font-size: 1.75rem;
    }

    .section-title {
        font-size: 1.125rem;
    }

    .sitemap-link {
        font-size: 0.9rem;
    }

    /* Minimal nested accordion indentation on small mobile */
    .sitemap-wrapper .sitemap-accordion .sitemap-accordion-level-2 {
        margin: 0 0.35rem !important;
    }
    .sitemap-wrapper .sitemap-accordion .sitemap-accordion .sitemap-accordion-level-3 {
        margin: 0 0.25rem !important;
    }

    /* Tighter accordion padding on small mobile */
    .sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-child-list.accordion-child-list {
        padding: 15px 0.5rem !important;
    }
    .sitemap-wrapper .sitemap-accordion .sitemap-accordion .accordion-child-list {
        padding: 10px 0.5rem !important;
    }
    .sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header {
        padding: 0.65rem 0.75rem !important;
    }
    .sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordions {
        margin-top: 20px;
    }
}

.sitemap-wrapper,
.sitemap-main,
.sitemap-section {
    background: <?php echo $bg_color; ?> !important;
    color: <?php echo $text_color; ?> !important;
}

@media print {
    .sitemap-wrapper {
        box-shadow: none;
        margin: 0;
        padding: 0;
    }
    
    .sitemap-header {
        background: <?php echo $bg_color; ?>;
        color: <?php echo $text_color; ?>;
        text-shadow: none;
    }
    
    .sitemap-section {
        background: <?php echo $bg_color; ?>;
        border: 1px solid <?php echo $text_color; ?>;
        break-inside: avoid;
    }
    
    .tag-link {
        background: <?php echo $bg_color; ?>;
        color: <?php echo $text_color; ?>;
        border: 1px solid <?php echo $text_color; ?>;
    }
    
    .sitemap-accordion-content,
    .section-accordion-content {
        max-height: none !important;
    }
}

<?php
// Add custom link color overrides if set (overrides Elementor global styles)
if (!empty($custom_link_color)) {
    echo '
/* Custom Link Color Override */
.sitemap-wrapper .sitemap-link,
.sitemap-wrapper .sitemap-link:visited,
.sitemap-wrapper .tag-link,
.sitemap-wrapper .tag-link:visited,
.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-child-list .sitemap-link.sitemap-link {
    color: ' . esc_attr($custom_link_color) . ' !important;
}
    ';
}

if (!empty($custom_link_hover_color)) {
    echo '
/* Custom Link Hover Color Override */
.sitemap-wrapper .sitemap-link:hover,
.sitemap-wrapper .sitemap-link:focus,
.sitemap-wrapper .tag-link:hover,
.sitemap-wrapper .tag-link:focus,
.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-child-list .sitemap-link.sitemap-link:hover {
    color: ' . esc_attr($custom_link_hover_color) . ' !important;
}
    ';
}
?>
<?php
echo '</style>';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle page accordions (supports nested accordions)
    const accordionHeaders = document.querySelectorAll('.sitemap-accordion-header');

    accordionHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            // Stop propagation so clicking a nested accordion doesn't toggle parent
            e.stopPropagation();

            const content = this.nextElementSibling;
            const isActive = this.classList.contains('active');

            this.classList.toggle('active');
            content.classList.toggle('active');

            this.setAttribute('aria-expanded', !isActive);

            if (!isActive) {
                content.style.setProperty('max-height', content.scrollHeight + 'px', 'important');
                // Update parent accordion maxHeight to account for newly expanded child
                let parentContent = this.closest('.sitemap-accordion-content');
                while (parentContent) {
                    parentContent.style.setProperty('max-height', (parentContent.scrollHeight + content.scrollHeight) + 'px', 'important');
                    parentContent = parentContent.parentElement.closest('.sitemap-accordion-content');
                }
            } else {
                content.style.setProperty('max-height', '0', 'important');
            }
        });

        header.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
    
    // Handle section accordion (Posts section)
    const sectionAccordionHeaders = document.querySelectorAll('.section-accordion-header');
    
    sectionAccordionHeaders.forEach(header => {
        // Get the parent section
        const section = header.closest('.sitemap-section-accordion');
        
        const clickHandler = function() {
            const content = header.nextElementSibling;
            const isActive = header.classList.contains('active');
            
            header.classList.toggle('active');
            content.classList.toggle('active');
            
            header.setAttribute('aria-expanded', !isActive);
            
            if (!isActive) {
                content.style.setProperty('max-height', content.scrollHeight + 'px', 'important');
            } else {
                content.style.setProperty('max-height', '0', 'important');
            }
        };
        
        // Add click handler to entire section
        if (section) {
            section.addEventListener('click', clickHandler);
        }
        
        // Keyboard accessibility
        header.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                clickHandler();
            }
        });
    });

    // Handle pages drawer dividers (collapsible page lists within accordions)
    // Pages start OPEN — divider collapses/expands the content ABOVE it
    const drawerDividers = document.querySelectorAll('.pages-drawer-divider');

    drawerDividers.forEach(divider => {
        divider.addEventListener('click', function(e) {
            e.stopPropagation();

            const content = this.nextElementSibling; // content is AFTER the divider
            const isCollapsed = this.classList.contains('collapsed');
            const textEl = this.querySelector('.pages-drawer-text');
            const pageCount = this.getAttribute('data-page-count');
            const plural = parseInt(pageCount) !== 1 ? 's' : '';

            if (!isCollapsed) {
                // Collapsing — capture current height, then animate to 0
                this.classList.add('collapsed');
                this.setAttribute('aria-expanded', 'false');
                content.style.setProperty('max-height', content.scrollHeight + 'px', 'important');
                content.offsetHeight; // force reflow for transition
                content.style.setProperty('max-height', '0px', 'important');
                if (textEl) {
                    textEl.textContent = 'Show ' + pageCount + ' page' + plural;
                }
            } else {
                // Expanding
                this.classList.remove('collapsed');
                this.setAttribute('aria-expanded', 'true');
                content.style.setProperty('max-height', content.scrollHeight + 'px', 'important');
                if (textEl) {
                    textEl.textContent = 'Hide ' + pageCount + ' page' + plural;
                }
                // Update parent accordion content heights to accommodate expanded content
                let parentContent = this.closest('.sitemap-accordion-content');
                while (parentContent) {
                    parentContent.style.setProperty('max-height', (parentContent.scrollHeight + content.scrollHeight) + 'px', 'important');
                    parentContent = parentContent.parentElement ? parentContent.parentElement.closest('.sitemap-accordion-content') : null;
                }
            }
        });

        divider.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
});
</script>

<?php get_footer(); ?>
