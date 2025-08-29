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
                    <ul class="sitemap-list">
                        <?php
                        $pages = get_pages(array(
                            'sort_column' => 'menu_order',
                            'sort_order' => 'ASC',
                            'post_status' => 'publish',
                            'exclude' => get_the_ID()
                        ));
                        
                        // Filter out thank you page and Yoast SEO excluded pages
                        $pages = array_filter($pages, function($page) {
                            // Skip thank you page
                            if ($page->post_name === 'thank-you') {
                                return false;
                            }
                            
                            // Check Yoast SEO settings - exclude if noindex is set
                            $yoast_noindex = get_post_meta($page->ID, '_yoast_wpseo_meta-robots-noindex', true);
                            if ($yoast_noindex === '1') {
                                return false;
                            }
                            
                            return true;
                        });
                        
                        foreach ($pages as $page) {
                            $page_title = $page->post_title ? $page->post_title : 'Untitled';
                            echo '<li class="sitemap-item">';
                            echo '<a href="' . get_permalink($page->ID) . '" class="sitemap-link">';
                            echo esc_html($page_title);
                            echo '</a>';
                            echo '</li>';
                        }
                        ?>
                    </ul>
                </section>

                <!-- Posts Section -->
                <section class="sitemap-section sitemap-posts">
                    <h2 class="section-title">
                        <svg class="section-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                        </svg>
                        Recent Posts
                    </h2>
                    <ul class="sitemap-list">
                        <?php
                        $recent_posts = get_posts(array(
                            'numberposts' => 20,
                            'post_status' => 'publish',
                            'orderby' => 'date',
                            'order' => 'DESC'
                        ));
                        
                        foreach ($recent_posts as $post) {
                            setup_postdata($post);
                            
                            // Check Yoast SEO settings - skip if noindex is set
                            $yoast_noindex = get_post_meta($post->ID, '_yoast_wpseo_meta-robots-noindex', true);
                            if ($yoast_noindex === '1') {
                                continue;
                            }
                            
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
                </section>

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
/* Reset and Base Styles */
.sitemap-wrapper * {
    box-sizing: border-box;
}

/* Main Container */
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

/* Header Styles */
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

/* Single Column Layout */
.sitemap-grid {
    display: block;
    padding: 2rem 1rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* Section Styles */
.sitemap-section {
    background: #ffffff;
    border-radius: 15px;
    padding: 1.5rem;
    border: 1px solid #e0e0e0;
    transition: none;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
    height: 4rem;
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

/* Tag Cloud */
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

/* Archives List Special Styling */
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

/* Responsive Design */
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

/* Consistent styling for all themes */
.sitemap-wrapper,
.sitemap-main,
.sitemap-section {
    background: #ffffff !important;
    color: #000000 !important;
}

/* Print Styles */
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
}
</style>

<?php get_footer(); ?>
