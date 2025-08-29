<?php
/*
Template Name: HTML Sitemap
*/

get_header(); ?>

<!-- Add meta description for sitemap -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add meta description if not already present
    if (!document.querySelector('meta[name="description"]')) {
        var metaDescription = document.createElement('meta');
        metaDescription.name = 'description';
        metaDescription.content = 'Complete HTML sitemap of our website showing all pages, posts, categories, and archives for easy navigation and search engine indexing.';
        document.head.appendChild(metaDescription);
    }
});
</script>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
            </header>

            <div class="entry-content">
                <div class="sitemap-container">
                    <?php
                    // Display page content if any
                    if (have_posts()) :
                        while (have_posts()) : the_post();
                            the_content();
                        endwhile;
                    endif;
                    ?>
                    <!-- Pages Section -->
                    <div class="sitemap-section">
                        <h2>Pages</h2>
                        <ul class="sitemap-list">
                            <?php
                            $pages = get_pages(array(
                                'sort_column' => 'menu_order',
                                'sort_order' => 'ASC',
                                'post_status' => 'publish',
                                'exclude' => get_the_ID() // Exclude current sitemap page
                            ));
                            
                            // Filter out thank you page by slug
                            $pages = array_filter($pages, function($page) {
                                return $page->post_name !== 'thank-you';
                            });
                            
                            foreach ($pages as $page) {
                                $page_title = $page->post_title ? $page->post_title : 'Untitled';
                                echo '<li><a href="' . get_permalink($page->ID) . '">' . esc_html($page_title) . '</a></li>';
                            }
                            ?>
                        </ul>
                    </div>

                    <!-- Posts Section -->
                    <div class="sitemap-section">
                        <h2>All Posts</h2>
                        <ul class="sitemap-list">
                            <?php
                            $all_posts = get_posts(array(
                                'numberposts' => -1, // Get all posts
                                'post_status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ));
                            
                            foreach ($all_posts as $post) {
                                setup_postdata($post);
                                echo '<li><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a> - ' . get_the_date('F j, Y', $post->ID) . '</li>';
                            }
                            wp_reset_postdata();
                            ?>
                        </ul>
                    </div>

                    <!-- Categories Section -->
                    <div class="sitemap-section">
                        <h3>Categories</h3>
                        <ul class="sitemap-list">
                            <?php
                            $categories = get_categories(array(
                                'orderby' => 'name',
                                'order' => 'ASC',
                                'hide_empty' => true
                            ));
                            
                            foreach ($categories as $category) {
                                echo '<li><a href="' . get_category_link($category->term_id) . '">' . esc_html($category->name) . '</a> (' . $category->count . ' posts)</li>';
                            }
                            ?>
                        </ul>
                    </div>

                    <!-- Tags Section -->
                    <div class="sitemap-section">
                        <h3>Tags</h3>
                        <div class="tag-cloud">
                            <?php
                            $tags = get_tags(array(
                                'orderby' => 'name',
                                'order' => 'ASC',
                                'hide_empty' => true
                            ));
                            
                            foreach ($tags as $tag) {
                                echo '<a href="' . get_tag_link($tag->term_id) . '" class="tag-link">' . esc_html($tag->name) . '</a> ';
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Archives Section -->
                    <div class="sitemap-section">
                        <h3>Archives</h3>
                        <ul class="sitemap-list">
                            <?php
                            wp_get_archives(array(
                                'type' => 'monthly',
                                'limit' => 12,
                                'format' => 'html',
                                'show_post_count' => true
                            ));
                            ?>
                        </ul>
                    </div>
                </div>
            </div>
        </article>
    </main>
</div>

<style>
.sitemap-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 20px 20px 20px; /* Reduced top padding since header has more space now */
    margin-top: 0; /* Removed extra margin to bring closer to header */
    width: 100% !important;
    float: none !important;
    clear: both !important;
    position: relative !important;
}

.sitemap-section {
    margin-bottom: 40px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.sitemap-section h2 {
    color: #333;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
    margin-bottom: 20px;
    font-size: 1.5em;
}

.sitemap-section h3 {
    color: #333;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
    margin-bottom: 20px;
    font-size: 1.3em;
}

.sitemap-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 15px 30px;
}

.sitemap-list li {
    margin-bottom: 8px;
    padding: 5px 0;
    flex: 0 1 calc(33.333% - 20px); /* 3 columns on desktop */
    break-inside: avoid;
}

/* Responsive column adjustments */
@media (max-width: 1200px) {
    .sitemap-list li {
        flex: 0 1 calc(50% - 15px); /* 2 columns on tablet */
    }
}

@media (max-width: 768px) {
    .sitemap-list li {
        flex: 0 1 100%; /* 1 column on mobile */
    }
}

.sitemap-list a {
    color: #0073aa;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.sitemap-list a:hover {
    color: #005a87;
    text-decoration: underline;
}

.tag-cloud {
    line-height: 2;
}

.tag-link {
    display: inline-block;
    margin: 2px 5px 2px 0;
    padding: 4px 8px;
    background: #0073aa;
    color: white !important;
    text-decoration: none;
    border-radius: 3px;
    font-size: 0.9em;
    transition: background 0.3s ease;
}

.tag-link:hover {
    background: #005a87;
    text-decoration: none;
}

/* Header overlap prevention */
.entry-header {
    padding-top: 120px; /* Increased padding to push H1 further down */
    margin-bottom: 30px; /* More space between header and sitemap */
    width: 100% !important;
    float: none !important;
    clear: both !important;
    text-align: center !important;
    position: relative !important;
}

.entry-title {
    margin-top: 0 !important;
    margin-bottom: 20px !important;
    width: 100% !important;
    max-width: 1200px !important; /* Match sitemap container width */
    float: none !important;
    clear: both !important;
    text-align: center !important;
    position: relative !important;
    z-index: 1 !important;
    /* Clean white text on dark background */
    color: #ffffff !important; /* White text */
    background: rgba(0,0,0,0.85) !important; /* Dark background */
    padding: 15px 40px !important; /* Increased padding for rectangle shape */
    border-radius: 12px !important; /* Rounded edges */
    display: block !important; /* Changed from inline-block to block for full width */
    box-shadow: 0 2px 10px rgba(0,0,0,0.3) !important;
    margin-left: auto !important;
    margin-right: auto !important;
    text-shadow: none !important; /* Remove text shadow since we have dark background */
}

/* Consistent styling for both light and dark themes */
@media (prefers-color-scheme: dark) {
    .entry-title {
        color: #ffffff !important; /* Keep white text for dark themes too */
        background: rgba(0,0,0,0.9) !important; /* Slightly darker for dark themes */
    }
}

/* Alternative approach - adjust if needed */
body.page-template-page-sitemap {
    padding-top: 100px; /* Fallback for themes with body padding */
}

/* Force proper layout flow */
.entry-content {
    width: 100% !important;
    float: none !important;
    clear: both !important;
    position: relative !important;
}

.entry-content::before {
    content: "";
    display: table;
    clear: both;
}

/* Responsive adjustments for mobile headers */
/* Responsive Design */
@media (max-width: 768px) {
    .sitemap-container {
        padding-top: 100px; /* Mobile headers are often shorter */
    }
    
    .entry-header {
        padding-top: 60px;
    }
}
    .sitemap-list {
        columns: 1;
    }
    
    .sitemap-section {
        padding: 15px;
    }

</style>

<?php get_footer(); ?>