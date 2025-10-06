<?php
/*
Template Name: HTML Sitemap
*/

get_header(); ?>

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
                    
                    // Filter out thank you page and Yoast SEO excluded pages
                    $all_pages = array_filter($all_pages, function($page) {
                        if ($page->post_name === 'thank-you') {
                            return false;
                        }
                        $yoast_noindex = get_post_meta($page->ID, '_yoast_wpseo_meta-robots-noindex', true);
                        if ($yoast_noindex === '1') {
                            return false;
                        }
                        return true;
                    });

                    // Separate pages into accordion parents and regular pages
                    $accordion_parents = array();
                    $regular_pages = array();
                    $child_page_ids = array();

                    foreach ($all_pages as $page) {
                        $page_taxonomies = wp_get_post_terms($page->ID, 'parent_pages', array('fields' => 'names'));
                        $is_service_loop = in_array('Service Pages Loop Item', $page_taxonomies);
                        $is_services_page = (strtolower($page->post_title) === 'services');
                        
                        if ($is_service_loop) {
                            $children = get_children(array(
                                'post_parent' => $page->ID,
                                'post_type' => 'page',
                                'post_status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ));
                            
                            $children = array_filter($children, function($child) {
                                $yoast_noindex = get_post_meta($child->ID, '_yoast_wpseo_meta-robots-noindex', true);
                                return $yoast_noindex !== '1';
                            });
                            
                            if (!empty($children)) {
                                $accordion_parents[$page->ID] = array(
                                    'page' => $page,
                                    'children' => $children
                                );
                                foreach ($children as $child) {
                                    $child_page_ids[] = $child->ID;
                                }
                            } else {
                                $regular_pages[] = $page;
                            }
                        } elseif ($is_services_page) {
                            $children = get_children(array(
                                'post_parent' => $page->ID,
                                'post_type' => 'page',
                                'post_status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ));
                            
                            $children = array_filter($children, function($child) {
                                $yoast_noindex = get_post_meta($child->ID, '_yoast_wpseo_meta-robots-noindex', true);
                                if ($yoast_noindex === '1') {
                                    return false;
                                }
                                
                                $child_taxonomies = wp_get_post_terms($child->ID, 'parent_pages', array('fields' => 'names'));
                                $has_service_loop = in_array('Service Pages Loop Item', $child_taxonomies);
                                
                                return !$has_service_loop;
                            });
                            
                            if (!empty($children)) {
                                $accordion_parents[$page->ID] = array(
                                    'page' => $page,
                                    'children' => $children
                                );
                                foreach ($children as $child) {
                                    $child_page_ids[] = $child->ID;
                                }
                            } else {
                                $regular_pages[] = $page;
                            }
                        } else {
                            $regular_pages[] = $page;
                        }
                    }

                    $regular_pages = array_filter($regular_pages, function($page) use ($child_page_ids) {
                        return !in_array($page->ID, $child_page_ids);
                    });
                    
                    usort($regular_pages, function($a, $b) {
                        return strcmp($a->post_title, $b->post_title);
                    });
                    ?>

                    <!-- Regular Pages -->
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

                    <!-- Accordion Pages -->
                    <?php if (!empty($accordion_parents)): ?>
                        <div class="sitemap-accordions">
                            <?php foreach ($accordion_parents as $accordion): ?>
                                <div class="sitemap-accordion">
                                    <div class="sitemap-accordion-header" role="button" tabindex="0" aria-expanded="false">
                                        <span class="accordion-title">
                                            <?php echo esc_html($accordion['page']->post_title ? $accordion['page']->post_title : 'Untitled'); ?>
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
                                            <?php foreach ($accordion['children'] as $child): ?>
                                                <li class="sitemap-item">
                                                    <a href="<?php echo get_permalink($child->ID); ?>" class="sitemap-link">
                                                        <?php echo esc_html($child->post_title ? $child->post_title : 'Untitled'); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
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

                <!-- Archives Section -->
                <section class="sitemap-section sitemap-archives">
                    <h3 class="section-title">
                        <svg class="section-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Archives
                    </h3>
                    <ul class="sitemap-list archives-list">
                        <?php
                        wp_get_archives(array(
                            'type' => 'monthly',
                            'limit' => 12,
                            'format' => 'html',
                            'show_post_count' => true
                        ));
                        ?>
                    </ul>
                </section>

            </div>
        </article>
    </main>
</div>

<style>
.sitemap-wrapper * {
    box-sizing: border-box;
}

.sitemap-wrapper {
    width: 100%;
    margin: 0;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    line-height: 1.6;
}

.sitemap-main {
    background: #ffffff;
    border-radius: 0;
    box-shadow: none;
    overflow: hidden;
}

.sitemap-header {
    background: #ffffff;
    color: #000000;
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
    color: #000000;
}

.sitemap-description {
    font-size: 1.1rem;
    opacity: 1;
    max-width: none;
    margin: 0;
    color: #000000;
    width: 100vw;
    position: relative;
    left: 50%;
    right: 50%;
    margin-left: -50vw;
    margin-right: -50vw;
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
    background: #ffffff;
    border-radius: 15px;
    padding: 1.5rem;
    border: 1px solid #e0e0e0;
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
    color: #000000;
    margin: 0 0 1.5rem 0;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #000000;
}

.section-icon {
    color: #000000;
    flex-shrink: 0;
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
    border-bottom: 1px solid #000000 !important;
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
    color: #000000;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.section-accordion-icon {
    flex-shrink: 0;
    transition: transform 0.3s ease;
    color: #000000;
    stroke: #000000;
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
    background: #f9f9f9 !important;
    background-color: #f9f9f9 !important;
    border: none !important;
    border-radius: 8px !important;
    cursor: pointer !important;
    font-weight: 600 !important;
    color: #000000 !important;
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
    background: #f0f0f0 !important;
    background-color: #f0f0f0 !important;
    color: #000000 !important;
    box-shadow: none !important;
    transform: none !important;
    border: none !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header.active {
    background: #e8e8e8 !important;
    background-color: #e8e8e8 !important;
    color: #000000 !important;
    box-shadow: none !important;
    border: none !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.sitemap-accordion-header.sitemap-accordion-header:focus {
    outline: none !important;
    box-shadow: none !important;
    background: #f0f0f0 !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-title.accordion-title {
    flex-grow: 1 !important;
    color: #000000 !important;
    text-shadow: none !important;
    font-weight: 600 !important;
    font-size: 18px !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-icon.accordion-icon {
    flex-shrink: 0 !important;
    transition: transform 0.3s ease !important;
    color: #000000 !important;
    stroke: #000000 !important;
    fill: none !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-header.active .accordion-icon {
    transform: rotate(180deg) !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-content.sitemap-accordion-content {
    max-height: 0 !important;
    overflow: hidden !important;
    transition: max-height 0.3s ease !important;
    background: #ffffff !important;
    background-color: #ffffff !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .sitemap-accordion-content.active {
    max-height: 2000px !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-child-list.accordion-child-list {
    padding: 40px 1.25rem !important;
    margin: 0 !important;
    background: #ffffff !important;
    background-color: #ffffff !important;
}

.sitemap-wrapper .sitemap-section.sitemap-pages .sitemap-accordion .accordion-child-list .sitemap-link.sitemap-link {
    font-size: 16px !important;
    text-decoration: none !important;
    font-weight: 500 !important;
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
    border-bottom: 1px solid #e0e0e0;
    box-sizing: border-box;
}

.sitemap-item:last-child {
    margin-bottom: 0;
}

.sitemap-link {
    color: #000000;
    text-decoration: none;
    font-weight: 500;
    transition: none;
    flex-grow: 1;
    font-size: 16px !important;
}

.sitemap-link:hover {
    color: #000000;
    text-decoration: underline;
}

.post-date,
.post-count {
    font-size: 0.875rem;
    color: #000000;
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
    background: #ffffff;
    color: #000000;
    text-decoration: none;
    border-radius: 0;
    font-size: 0.875rem;
    font-weight: 500;
    transition: none;
    border: 1px solid #000000;
    margin: 0.25rem;
}

.tag-link:hover {
    background: #ffffff;
    transform: none;
    box-shadow: none;
    text-decoration: underline;
}

.archives-list {
    columns: 1;
}

.archives-list li {
    break-inside: avoid;
    margin-bottom: 0.5rem;
}

.archives-list a {
    color: #000000;
    text-decoration: none;
    font-weight: 500;
    transition: none;
}

.archives-list a:hover {
    color: #000000;
    text-decoration: underline;
}

@media (max-width: 768px) {
    .sitemap-wrapper {
        margin: 1rem auto;
        padding: 0 0.5rem;
    }
    
    .sitemap-grid {
        display: block;
        padding: 1.5rem;
    }
    
    .sitemap-header {
        padding: 2rem 1rem 1.5rem;
    }
    
    .sitemap-title {
        font-size: 2rem;
    }
    
    .sitemap-section {
        padding: 1rem;
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
}

@media (max-width: 480px) {
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
}

.sitemap-wrapper,
.sitemap-main,
.sitemap-section {
    background: #ffffff !important;
    color: #000000 !important;
}

@media print {
    .sitemap-wrapper {
        box-shadow: none;
        margin: 0;
        padding: 0;
    }
    
    .sitemap-header {
        background: #ffffff;
        color: #000000;
        text-shadow: none;
    }
    
    .sitemap-section {
        background: #ffffff;
        border: 1px solid #000000;
        break-inside: avoid;
    }
    
    .tag-link {
        background: #ffffff;
        color: #000000;
        border: 1px solid #000000;
    }
    
    .sitemap-accordion-content,
    .section-accordion-content {
        max-height: none !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle page accordions
    const accordionHeaders = document.querySelectorAll('.sitemap-accordion-header');
    
    accordionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const content = this.nextElementSibling;
            const isActive = this.classList.contains('active');
            
            this.classList.toggle('active');
            content.classList.toggle('active');
            
            this.setAttribute('aria-expanded', !isActive);
            
            if (!isActive) {
                content.style.maxHeight = content.scrollHeight + 'px';
            } else {
                content.style.maxHeight = '0';
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
                content.style.maxHeight = content.scrollHeight + 'px';
            } else {
                content.style.maxHeight = '0';
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
});
</script>

<?php get_footer(); ?>
