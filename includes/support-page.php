<?php
/**
 * Hozio Pro - Support & Help Page
 * Searchable FAQ-style documentation for all plugin features
 */

if (!defined('ABSPATH')) exit;

function hozio_support_page() {
    // Check if license is active
    if (!function_exists('hozio_is_license_valid') || !hozio_is_license_valid()) {
        $settings_url = admin_url('admin.php?page=hozio-plugin-settings');
        ?>
        <div class="hozio-settings-wrapper">
            <div class="hozio-header">
                <div class="hozio-header-content">
                    <h1><span class="dashicons dashicons-book-alt"></span> Support & Help</h1>
                    <p class="hozio-subtitle">Step-by-step guides for all Hozio Pro features</p>
                </div>
            </div>
            <div class="hozio-content">
                <div style="text-align:center; padding:60px 20px;">
                    <span class="dashicons dashicons-lock" style="font-size:48px; width:48px; height:48px; color:#d63638; margin-bottom:16px; display:block; margin-left:auto; margin-right:auto;"></span>
                    <h2 style="margin:0 0 12px; font-size:22px; color:#1f2937;">License Required</h2>
                    <p style="margin:0 0 24px; font-size:15px; color:#6b7280; max-width:450px; margin-left:auto; margin-right:auto;">A valid license key is required to access Support & Help documentation. Please enter your license key in the plugin settings.</p>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary" style="font-size:14px; padding:8px 24px; height:auto;">Enter License Key</a>
                </div>
            </div>
        </div>
        <?php
        return;
    }
    ?>
    <div class="hozio-settings-wrapper">
        <!-- Header -->
        <div class="hozio-header">
            <div class="hozio-header-content">
                <h1><span class="dashicons dashicons-book-alt"></span> Support & Help</h1>
                <p class="hozio-subtitle">Step-by-step guides for all Hozio Pro features</p>
            </div>
        </div>

        <!-- Content -->
        <div class="hozio-content">

            <!-- Search Bar -->
            <div class="hozio-support-search-wrapper">
                <div class="hozio-support-search-inner">
                    <span class="dashicons dashicons-search hozio-support-search-icon"></span>
                    <input type="text" id="hozio-support-search" class="hozio-support-search-input" placeholder="Search documentation... (e.g., taxonomy, county pages, loop, RSS)" autocomplete="off" />
                    <span id="hozio-support-search-clear" class="hozio-support-search-clear" style="display:none;">&times;</span>
                </div>
                <p class="hozio-support-search-hint">Click any section to expand it. Use the search bar to filter by keyword.</p>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 1: Dynamic Tags -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="dynamic-tags">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-tag"></span>
                    <h2>Dynamic Tags</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Dynamic Tags let you store business information (phone numbers, emails, social media links, etc.) in one place and use them across your entire site via Elementor. When you update a value in Hozio Pro settings, it updates everywhere that tag is used.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Navigate to Hozio Pro</strong> in your WordPress admin sidebar. The main settings page shows all your contact information fields.</li>
                            <li><strong>Fill in your business details</strong> &mdash; phone numbers, email, address, business hours, social media URLs, and more.</li>
                            <li><strong>Click Save Settings</strong> at the bottom of the page.</li>
                            <li><strong>In Elementor</strong>, edit any text widget, button, or link field. Click the dynamic tags icon (the stacked database icon).</li>
                            <li><strong>Search for "Hozio"</strong> in the dynamic tag dropdown. You'll see all available tags like "Company Phone 1", "Company Email", "Facebook URL", etc.</li>
                            <li><strong>Select the tag</strong> you want. The widget will now pull its value from your Hozio Pro settings automatically.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Tags are available in both <strong>Text</strong> and <strong>URL</strong> categories in Elementor.</li>
                            <li>Phone tags automatically format with <code>tel:</code> protocol for click-to-call links.</li>
                            <li>SMS tags use <code>sms:</code> protocol and email tags use <code>mailto:</code>.</li>
                            <li>The <strong>Years of Experience</strong> tag auto-calculates from the year you set &mdash; it updates every year automatically.</li>
                            <li>To create <strong>custom tags</strong>, go to <strong>Hozio Pro &rarr; Add / Remove</strong> and add tags with custom names.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 2: Page Taxonomies & Town Taxonomies -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="taxonomies">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-category"></span>
                    <h2>Page Taxonomies & Town Taxonomies</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Hozio Pro adds two custom taxonomies to WordPress pages: <strong>Page Taxonomies</strong> (parent_pages) and <strong>Town Taxonomies</strong> (town_taxonomies). These allow you to group and filter pages by service type, location, or any custom classification &mdash; which powers the dynamic query features.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Go to Pages</strong> in the WordPress admin. You'll see two new columns: "Page Taxonomies" and "Town Taxonomies".</li>
                            <li><strong>Edit any page</strong> and look for the "Page Taxonomies" and "Town Taxonomies" meta boxes in the sidebar (or below the editor, depending on your layout).</li>
                            <li><strong>Assign taxonomy terms</strong> to pages. For example, assign the term "plumbing" to all plumbing service town pages.</li>
                            <li><strong>To bulk-create Town Taxonomy terms</strong>, click the "Connect Town Taxonomies" button on the Pages list. This tool automatically creates town terms based on page slugs and assigns them.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Both taxonomies are <strong>hierarchical</strong> (like categories), so you can create parent/child term structures.</li>
                            <li>The <strong>Connect Town Taxonomies</strong> tool skips parent pages (pages with children) &mdash; it only processes leaf/town pages.</li>
                            <li>Use the <strong>search bars</strong> on the Pages list to filter by taxonomy terms.</li>
                            <li>To enable/disable taxonomy archive pages, go to <strong>Hozio Pro &rarr; Archive Settings</strong>.</li>
                            <li>WordPress does not allow two terms with the same slug in the same taxonomy. If you need separate "repair" terms, see the <strong>Parent Page Filtering</strong> section below.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 3: Parent Pages Query -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="parent-pages-query">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-admin-page"></span>
                    <h2>Parent Pages Query (dynamic_parent_pages_query)</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>This is a custom Elementor query that dynamically fetches related pages based on the current page's taxonomy. When a visitor lands on a town page, this query finds all other pages that share the same Page Taxonomy term &mdash; typically used to show "other locations for this service" or "related service pages".</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Create your page hierarchy</strong> in WordPress. For example: <code>/plumbing/</code> (parent) with child town pages like <code>/plumbing/houston/</code>, <code>/plumbing/dallas/</code>.</li>
                            <li><strong>Create a Page Taxonomy term</strong> that matches the slug of the service (e.g., term slug = "plumbing").</li>
                            <li><strong>Assign this term</strong> to all the town pages under that service.</li>
                            <li><strong>In Elementor</strong>, add a Loop Grid or Loop Carousel widget to your page template.</li>
                            <li><strong>In the widget's Query settings</strong>, set the Query ID to: <code>dynamic_parent_pages_query</code></li>
                            <li>The widget will now automatically display pages that share the same Page Taxonomy term as the current page, excluding the current page itself.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>How the matching works</h3>
                        <ul>
                            <li>The query extracts the <strong>current page's slug</strong> (last segment of the URL).</li>
                            <li>It then looks for a <strong>Page Taxonomy term</strong> assigned to the current page whose slug matches the page slug.</li>
                            <li>All pages with that term are returned (excluding the current page and any pages with the "county" term).</li>
                            <li>Pages must also have a non-empty <code>location</code> ACF field to appear in results.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 4: Parent Page Filtering (NEW) -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="parent-page-filtering">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-filter"></span>
                    <h2>Parent Page Filtering (Same-Slug Pages)</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>When multiple page hierarchies share the same slug (e.g., <code>/gutter/installation/</code> and <code>/roofing/installation/</code>), their child town pages would normally mix together in query results because they share the same Page Taxonomy term ("installation"). <strong>Parent Page Filtering</strong> solves this by restricting the <code>dynamic_parent_pages_query</code> and <code>dynamic_county_pages_query</code> to only return pages that are direct children of the page where the checkbox is enabled.</p>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>The problem this solves</h3>
                        <ul>
                            <li>You have a site with multiple service categories, each with a similar sub-service. For example:
                                <ul>
                                    <li><code>/services/gutter/installation/</code> &mdash; with town pages like <code>/services/gutter/installation/west-newbury-ma/</code></li>
                                    <li><code>/services/roofing/installation/</code> &mdash; with town pages like <code>/services/roofing/installation/nahant-ma/</code></li>
                                </ul>
                            </li>
                            <li>Both sets of town pages are tagged with the same <strong>"installation"</strong> Page Taxonomy term (since WordPress doesn't allow duplicate term slugs in the same taxonomy).</li>
                            <li><strong>Without this feature:</strong> When a visitor is on <code>/services/gutter/installation/</code>, the query shows ALL pages with the "installation" taxonomy &mdash; including roofing town pages that don't belong there.</li>
                            <li><strong>With this feature:</strong> The query is restricted to only return direct children of the page where the checkbox is enabled, so <code>/services/gutter/installation/</code> only shows its own town pages.</li>
                        </ul>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Identify the service pages</strong> that share the same slug across different hierarchies. For example, you might have an "installation" page under <code>/gutter/</code> and another "installation" page under <code>/roofing/</code>.</li>
                            <li><strong>Edit the service page</strong> in WordPress &mdash; this is the page where the Elementor loop widget lives and where the query runs. For example, edit the <code>/services/gutter/installation/</code> page.</li>
                            <li><strong>In the sidebar</strong>, find the <strong>"Parent Pages Query Options"</strong> meta box.</li>
                            <li><strong>Check the box</strong> labeled <strong>"Filter by parent page"</strong>.</li>
                            <li><strong>Save/update</strong> the page.</li>
                            <li><strong>Repeat</strong> for every other service page that shares the same slug. In our example, you would also enable the checkbox on <code>/services/roofing/installation/</code>.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Where to enable the checkbox</h3>
                        <ul>
                            <li><strong>Enable it on the service page itself</strong> &mdash; the page where your Elementor Loop Grid or Loop Carousel widget is placed (e.g., <code>/services/gutter/installation/</code>). This is the most common and recommended approach.</li>
                            <li>The checkbox tells the query: <strong>"Only show pages that are direct children of this page."</strong></li>
                            <li>You do <strong>NOT</strong> need to enable the checkbox on each individual town page. Just the service/parent page is enough.</li>
                            <li><strong>Ancestor walk-up:</strong> If the checkbox is not found on the current page, the system will automatically check parent pages going up the hierarchy (e.g., <code>/gutter/</code>, then <code>/services/</code>). If any ancestor has the checkbox enabled, filtering will activate using that ancestor as the boundary. This means you can also enable the checkbox on a higher-level parent if you want all its descendants to be filtered.</li>
                        </ul>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Detailed example</h3>
                        <ul>
                            <li><strong>Site structure:</strong>
                                <ul>
                                    <li><code>/services/gutter/installation/</code> (service page &mdash; checkbox enabled here)</li>
                                    <li><code>/services/gutter/installation/west-newbury-ma/</code> (town page)</li>
                                    <li><code>/services/gutter/installation/nahant-ma/</code> (town page)</li>
                                    <li><code>/services/roofing/installation/</code> (service page &mdash; checkbox enabled here too)</li>
                                    <li><code>/services/roofing/installation/nahant-ma/</code> (town page)</li>
                                </ul>
                            </li>
                            <li>All town pages are tagged with the <strong>"installation"</strong> Page Taxonomy term.</li>
                            <li><strong>Result:</strong> When a visitor is on <code>/services/gutter/installation/</code>, the loop widget only shows <code>west-newbury-ma</code> and <code>nahant-ma</code> under gutter. The roofing <code>nahant-ma</code> page does NOT appear because it is not a direct child of <code>/services/gutter/installation/</code>.</li>
                        </ul>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Which queries are affected</h3>
                        <ul>
                            <li><strong><code>dynamic_parent_pages_query</code></strong> &mdash; YES, affected. When the checkbox is enabled, results are restricted to children of the page with the checkbox.</li>
                            <li><strong><code>dynamic_county_pages_query</code></strong> &mdash; YES, affected. Same filtering logic applies to county pages.</li>
                            <li><strong><code>dynamic_town_pages_query</code></strong> &mdash; NO, not affected. The town query is designed to show all services available in a given town across all hierarchies. For example, if a visitor is on <code>/gutter/installation/nahant-ma/</code>, the town query shows ALL services in Nahant (gutter, roofing, etc.). This cross-hierarchy behavior is intentional and preserved regardless of the checkbox setting.</li>
                        </ul>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>HTML Sitemap behavior</h3>
                        <ul>
                            <li>The <strong>HTML Sitemap</strong> also respects this checkbox.</li>
                            <li>When enabled, accordion dropdown labels will be <strong>prefixed with the parent page's name</strong> to distinguish same-named sections.</li>
                            <li>For example: instead of two accordion dropdowns both labeled "Installation", you'll see <strong>"Gutter Installation"</strong> and <strong>"Roofing Installation"</strong>.</li>
                        </ul>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li><strong>Only needed for duplicate slugs.</strong> If your service page slugs are already unique across different hierarchies (e.g., "gutter-installation" vs "roofing-repair"), you do not need this feature.</li>
                            <li>This feature works with both the <strong>standard query logic</strong> and the <strong>County Pages logic</strong> (when <code>use_county_pages</code> is enabled).</li>
                            <li>The checkbox is saved as a <strong>per-page setting</strong>. It only needs to be enabled on the specific pages that have slug conflicts.</li>
                            <li>The filtering uses WordPress's built-in <code>post_parent</code> constraint, so it is performant and does not add extra database queries beyond the standard meta check.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 5: County Pages -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="county-pages">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-location-alt"></span>
                    <h2>County Pages</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>County Pages is an advanced query mode that extends the parent pages system. When enabled, it uses a <strong>composite slug</strong> (parent term + page slug) to match taxonomy terms, and adds support for a special "county" term to distinguish county-level pages from regular town pages.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Create your page hierarchy</strong> with county pages as children of service pages. For example: <code>/sprinter-service/travis-county/</code>.</li>
                            <li><strong>Create Page Taxonomy terms</strong> using the composite slug pattern: <code>parentterm-pageslug</code>. For example, if the parent term is "sprinter-service" and the page slug is "travis-county", create a term with slug <code>sprinter-service-travis-county</code>.</li>
                            <li><strong>Create a "county" term</strong> in the Page Taxonomies. This is a special marker term.</li>
                            <li><strong>Assign both terms</strong> to your county pages: the composite term AND the "county" term.</li>
                            <li><strong>Enable the county pages flag</strong> on the page by setting the <code>use_county_pages</code> custom field to <code>true</code> (via ACF or custom fields).</li>
                            <li><strong>Use the query IDs</strong> in Elementor:
                                <ul>
                                    <li><code>dynamic_parent_pages_query</code> &mdash; shows related pages EXCLUDING county pages</li>
                                    <li><code>dynamic_county_pages_query</code> &mdash; shows ONLY county pages</li>
                                </ul>
                            </li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>How the composite slug matching works</h3>
                        <ul>
                            <li>When <code>use_county_pages</code> is enabled, the query looks at the current page's assigned terms.</li>
                            <li>For each term, it checks if the term's slug equals <code>parent-term-slug + "-" + page-slug</code>.</li>
                            <li>Example: Page slug is "austin", parent term slug is "texas" &rarr; looks for a term with slug "texas-austin".</li>
                            <li>The <code>dynamic_parent_pages_query</code> excludes any pages that have the "county" marker term.</li>
                            <li>The <code>dynamic_county_pages_query</code> only includes pages that have BOTH the matching term AND the "county" term.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 6: Loop Configurations -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="loop-configurations">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-grid-view"></span>
                    <h2>Loop Configurations</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Loop Configurations let you create reusable filter presets for Elementor Loop Grid and Loop Carousel widgets. Instead of manually setting up query filters on every page, you create a configuration once and assign it to pages.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Go to Hozio Pro &rarr; Loop Configurations</strong> in the admin menu.</li>
                            <li><strong>Create a new configuration</strong> by giving it a unique name (e.g., "Plumbing Services Loop").</li>
                            <li><strong>Select the taxonomy</strong> to filter by (Page Taxonomies or Town Taxonomies).</li>
                            <li><strong>Choose the terms</strong> to include in the results.</li>
                            <li><strong>Optionally exclude</strong> specific pages from results.</li>
                            <li><strong>Save the configuration.</strong></li>
                            <li><strong>Edit a page</strong> in WordPress. In the sidebar, find the <strong>"Loop Configuration"</strong> meta box.</li>
                            <li><strong>Select your configuration</strong> from the dropdown.</li>
                            <li>All Loop Grid and Loop Carousel widgets on that page will now use this configuration's filters automatically.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Configurations apply to <strong>all loop widgets on the page</strong> (excluding header, footer, and popup contexts).</li>
                            <li>If no configuration is assigned, loop widgets use their default Elementor query settings.</li>
                            <li>You can create as many configurations as you need and reuse them across pages.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 7: Blog Permalink Settings -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="blog-permalink">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-admin-links"></span>
                    <h2>Blog Permalink Settings</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Customizes your blog post URL structure independently from WordPress's default permalink settings. You can add a <code>/blog/</code> prefix and/or include the post's category in the URL.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Go to Hozio Pro &rarr; Blog Permalink Settings</strong>.</li>
                            <li><strong>Toggle "Blog Prefix"</strong> to add <code>/blog/</code> before all post URLs.</li>
                            <li><strong>Toggle "Category Prefix"</strong> to include the post's first category in the URL.</li>
                            <li><strong>Check the preview</strong> at the bottom of the page to see the resulting URL structure.</li>
                            <li><strong>Save the settings.</strong> Rewrite rules are flushed automatically.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>URL structure examples</h3>
                        <ul>
                            <li>Both enabled: <code>/blog/category-name/post-name/</code></li>
                            <li>Blog prefix only: <code>/blog/post-name/</code></li>
                            <li>Category prefix only: <code>/category-name/post-name/</code></li>
                            <li>Both disabled: <code>/post-name/</code> (WordPress default)</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 8: RSS Feed Override -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="rss-feed">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-rss"></span>
                    <h2>RSS Feed Override</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Replaces the default WordPress RSS feed content with structured content pulled from ACF fields. This is useful when your post content is built with Elementor (which doesn't export cleanly to RSS) but your actual text lives in ACF fields.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Go to Hozio Pro &rarr; Blog Permalink Settings</strong>.</li>
                            <li><strong>Toggle "ACF RSS Feed Override"</strong> to enable.</li>
                            <li><strong>Save settings.</strong></li>
                            <li>Your RSS feed will now pull content from ACF fields: Introduction, Section 1-7 (with H2/H3 headings and body text).</li>
                            <li>Posts without ACF fields will fall back to the default WordPress content.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>The feed also includes the <strong>featured image as an enclosure</strong> tag, which is compatible with Zapier and other RSS automation tools.</li>
                            <li>ACF field names must match the expected structure: <code>introduction</code>, <code>section_1_heading</code>, <code>section_1_body</code>, etc.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 9: Service Menu Sync -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="service-menu-sync">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-menu-alt3"></span>
                    <h2>Service Menu Sync</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Automatically adds and removes pages from navigation menus when they are tagged with the <strong>"service-pages-loop-item"</strong> Page Taxonomy term. This keeps your service menus in sync without manual menu management.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Ensure the feature is enabled</strong> in Hozio Pro &rarr; Hozio Pro Settings (the "Service Menu Sync" toggle).</li>
                            <li><strong>Create your navigation menus</strong> in Appearance &rarr; Menus. The sync works with three menus:
                                <ul>
                                    <li><strong>Main Menu</strong> &mdash; pages added as children of a "Services" menu item</li>
                                    <li><strong>Main Menu Toggle</strong> &mdash; same structure</li>
                                    <li><strong>Services Menu</strong> &mdash; pages added at top level</li>
                                </ul>
                            </li>
                            <li><strong>Create a Page Taxonomy term</strong> called "service-pages-loop-item".</li>
                            <li><strong>Assign this term</strong> to any page you want to appear in the service menus.</li>
                            <li>The page will automatically appear in the menus. Remove the term to remove it from menus.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Only <strong>auto-added items</strong> are removed when the term is removed. Manually created menu items are never touched.</li>
                            <li>Compatible with <strong>WP All Import</strong> &mdash; uses delayed sync with retry logic for bulk imports.</li>
                            <li>Can be <strong>disabled globally</strong> via Hozio Pro Settings.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 10: HTML Sitemap -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="html-sitemap">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-networking"></span>
                    <h2>HTML Sitemap</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Provides an HTML sitemap page template that displays a hierarchical list of all your site's content, styled with customizable colors.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Create a new page</strong> in WordPress (e.g., title it "Sitemap").</li>
                            <li><strong>In the Page Attributes</strong> section, select <strong>"HTML Sitemap"</strong> as the page template.</li>
                            <li><strong>Publish the page.</strong></li>
                            <li><strong>Customize colors</strong> by going to Hozio Pro &rarr; HTML Sitemap Settings.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>This is an <strong>HTML sitemap</strong> for visitors, not an XML sitemap for search engines.</li>
                            <li>The template automatically generates the sitemap content &mdash; no shortcodes needed.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 11: Lead Management -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="lead-management">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-email-alt"></span>
                    <h2>Lead Management (CRM Dashboard)</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>A built-in CRM dashboard for managing Elementor form submissions. Displays all leads with search, filtering, pagination, and detailed views. Non-admin users are automatically restricted to only see the Lead Submissions page.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Lead Submissions</strong> appears as its own menu item in the WordPress admin (separate from Hozio Pro).</li>
                            <li><strong>Elementor form submissions</strong> are automatically captured and displayed in the dashboard.</li>
                            <li><strong>Click any lead</strong> to view full submission details.</li>
                            <li><strong>To customize colors</strong>, go to Lead Submissions &rarr; Display Settings (admin only).</li>
                            <li><strong>Non-admin users</strong> are automatically redirected to the Lead Submissions page after login. They only see this page and their profile.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>You can also use the <code>[leads_digest]</code> shortcode to display leads on a frontend page.</li>
                            <li>Display Settings let you customize all colors: backgrounds, text, links, buttons, search bar, and borders.</li>
                            <li>Non-admin access restrictions can be managed by adjusting user roles.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- SECTION 12: Plugin Settings -->
            <!-- ============================================ -->
            <div class="hozio-support-section" data-section="plugin-settings">
                <div class="hozio-support-section-header">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <h2>Plugin Settings & Debug Tools</h2>
                    <span class="hozio-support-toggle dashicons dashicons-arrow-down-alt2"></span>
                </div>
                <div class="hozio-support-section-content">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Centralized settings page for controlling plugin-wide options, enabling debug logging for troubleshooting, and managing feature toggles.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Go to Hozio Pro &rarr; Hozio Pro Settings</strong>.</li>
                            <li><strong>Feature toggles available:</strong>
                                <ul>
                                    <li><strong>DOM Parsing</strong> &mdash; auto-hides empty ACF sections in page content</li>
                                    <li><strong>Service Menu Sync</strong> &mdash; automatic menu management (see Service Menu Sync section)</li>
                                    <li><strong>Auto-Updates</strong> &mdash; checks GitHub for new plugin releases</li>
                                </ul>
                            </li>
                            <li><strong>Enable HOZIO_DEBUG</strong> to start logging. Logs are written to <code>wp-content/hozio-debug.log</code>.</li>
                            <li>Use the <strong>"Test Log Entry"</strong> button to verify logging is working.</li>
                            <li>Use the <strong>"Clear Log"</strong> button to reset the log file.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Debug logging works <strong>independently from WP_DEBUG</strong> &mdash; you don't need to enable WP_DEBUG.</li>
                            <li>Logs are categorized by component: ParentPagesQuery, CountyQuery, TownQuery, LoopConfig, MenuSync, etc.</li>
                            <li>Remember to <strong>disable debug logging</strong> on production sites to avoid unnecessary disk usage.</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div><!-- .hozio-content -->
    </div><!-- .hozio-settings-wrapper -->

    <style>
        /* Support page specific styles */
        .hozio-support-search-wrapper {
            position: sticky;
            top: 32px;
            z-index: 100;
            background: var(--hozio-bg, #f9fafb);
            padding: 20px 0 10px;
            margin-bottom: 10px;
        }

        .hozio-support-search-inner {
            position: relative;
        }

        .hozio-support-search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #00A0E3;
            font-size: 20px !important;
            width: 20px !important;
            height: 20px !important;
            pointer-events: none;
            z-index: 2;
        }

        .hozio-settings-wrapper .hozio-support-search-input,
        .hozio-settings-wrapper .hozio-support-search-input[type="text"] {
            width: 100% !important;
            max-width: 100% !important;
            padding: 16px 44px 16px 50px !important;
            border: 2px solid #e5e7eb !important;
            border-radius: 12px !important;
            font-size: 16px !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            background: #ffffff !important;
            color: #1f2937 !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06) !important;
            box-sizing: border-box !important;
            height: auto !important;
            line-height: 1.4 !important;
            margin: 0 !important;
        }

        .hozio-settings-wrapper .hozio-support-search-input:focus {
            outline: none !important;
            border-color: #00A0E3 !important;
            box-shadow: 0 0 0 3px rgba(0, 160, 227, 0.15) !important;
        }

        .hozio-settings-wrapper .hozio-support-search-input::placeholder {
            color: #9ca3af !important;
            opacity: 1 !important;
        }

        .hozio-support-search-clear {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 22px;
            color: #999;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
            z-index: 2;
        }

        .hozio-support-search-clear:hover {
            color: #333;
        }

        .hozio-support-search-hint {
            margin: 8px 0 0;
            font-size: 13px;
            color: #6b7280;
        }

        /* Section styles */
        .hozio-support-section {
            background: var(--hozio-card-bg, #fff);
            border-radius: 12px;
            margin-bottom: 16px;
            box-shadow: var(--hozio-shadow, 0 1px 3px rgba(0,0,0,0.1));
            border: 1px solid var(--hozio-border, #e5e7eb);
            border-left: 4px solid var(--hozio-blue, #00A0E3);
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }

        .hozio-support-section:nth-child(odd) {
            border-left-color: var(--hozio-green, #8DC63F);
        }

        .hozio-support-section:nth-child(3n) {
            border-left-color: var(--hozio-orange, #F7941D);
        }

        .hozio-support-section:hover {
            box-shadow: var(--hozio-shadow-lg, 0 10px 15px rgba(0,0,0,0.1));
        }

        .hozio-support-section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 24px;
            cursor: pointer;
            user-select: none;
            transition: background 0.15s ease;
        }

        .hozio-support-section-header:hover {
            background: rgba(0, 160, 227, 0.03);
        }

        .hozio-support-section-header .dashicons:first-child {
            color: var(--hozio-blue, #00A0E3);
            font-size: 22px;
            width: 22px;
            height: 22px;
        }

        .hozio-support-section:nth-child(odd) .hozio-support-section-header .dashicons:first-child {
            color: var(--hozio-green, #8DC63F);
        }

        .hozio-support-section:nth-child(3n) .hozio-support-section-header .dashicons:first-child {
            color: var(--hozio-orange, #F7941D);
        }

        .hozio-support-section-header h2 {
            margin: 0;
            font-size: 17px;
            font-weight: 600;
            color: var(--hozio-text, #1f2937);
            flex: 1;
        }

        .hozio-support-toggle {
            color: #999;
            transition: transform 0.3s ease;
        }

        .hozio-support-section.is-open .hozio-support-toggle {
            transform: rotate(180deg);
        }

        /* Content area - collapsed by default */
        .hozio-support-section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
            padding: 0 24px;
        }

        .hozio-support-section.is-open .hozio-support-section-content {
            max-height: 3000px;
            padding: 0 24px 24px;
        }

        /* Inner content styling */
        .hozio-support-what,
        .hozio-support-steps,
        .hozio-support-notes {
            margin-bottom: 20px;
        }

        .hozio-support-section-content h3 {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--hozio-gray, #6D6E71);
            margin: 0 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--hozio-border, #e5e7eb);
        }

        .hozio-support-section-content p {
            font-size: 14px;
            line-height: 1.7;
            color: var(--hozio-text, #1f2937);
            margin: 0 0 8px;
        }

        .hozio-support-section-content ol {
            counter-reset: step-counter;
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .hozio-support-section-content ol > li {
            counter-increment: step-counter;
            position: relative;
            padding-left: 40px;
            margin-bottom: 12px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--hozio-text, #1f2937);
        }

        .hozio-support-section-content ol > li::before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 1px;
            width: 26px;
            height: 26px;
            background: linear-gradient(135deg, var(--hozio-blue, #00A0E3), var(--hozio-green, #8DC63F));
            color: white;
            border-radius: 50%;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .hozio-support-section-content ol > li ul {
            margin-top: 8px;
            margin-bottom: 0;
        }

        .hozio-support-section-content ul {
            padding-left: 20px;
            margin: 0;
        }

        .hozio-support-section-content ul li {
            font-size: 14px;
            line-height: 1.7;
            color: var(--hozio-text, #1f2937);
            margin-bottom: 6px;
        }

        .hozio-support-section-content code {
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 13px;
            color: #e11d48;
            font-family: 'Consolas', 'Monaco', monospace;
        }

        /* No results */
        .hozio-support-no-results {
            text-align: center;
            padding: 60px 20px;
            color: var(--hozio-text-light, #6b7280);
            font-size: 16px;
            display: none;
        }

        .hozio-support-no-results .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #ddd;
            display: block;
            margin: 0 auto 12px;
        }

        /* Highlight matched text during search */
        .hozio-support-section.is-match {
            border-left-width: 6px;
        }

        @media (max-width: 782px) {
            .hozio-support-search-wrapper {
                top: 46px;
            }

            .hozio-support-section-header {
                padding: 16px 18px;
            }

            .hozio-support-section-header h2 {
                font-size: 15px;
            }

            .hozio-support-section-content {
                padding: 0 18px;
            }

            .hozio-support-section.is-open .hozio-support-section-content {
                padding: 0 18px 18px;
            }
        }
    </style>

    <script>
    (function() {
        'use strict';

        // Toggle sections
        document.querySelectorAll('.hozio-support-section-header').forEach(function(header) {
            header.addEventListener('click', function() {
                var section = this.closest('.hozio-support-section');
                section.classList.toggle('is-open');
            });
        });

        // Search functionality
        var searchInput = document.getElementById('hozio-support-search');
        var clearBtn = document.getElementById('hozio-support-search-clear');
        var sections = document.querySelectorAll('.hozio-support-section');
        var debounceTimer;

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    filterSections(searchInput.value.trim().toLowerCase());
                }, 200);

                clearBtn.style.display = searchInput.value ? 'block' : 'none';
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                filterSections('');
                searchInput.focus();
            });
        }

        function filterSections(query) {
            var visibleCount = 0;

            sections.forEach(function(section) {
                if (!query) {
                    // No search - show all, collapse all
                    section.style.display = '';
                    section.classList.remove('is-open', 'is-match');
                    visibleCount++;
                    return;
                }

                var headerText = section.querySelector('h2').textContent.toLowerCase();
                var contentText = section.querySelector('.hozio-support-section-content').textContent.toLowerCase();
                var matches = headerText.indexOf(query) !== -1 || contentText.indexOf(query) !== -1;

                if (matches) {
                    section.style.display = '';
                    section.classList.add('is-open', 'is-match');
                    visibleCount++;
                } else {
                    section.style.display = 'none';
                    section.classList.remove('is-open', 'is-match');
                }
            });

            // Show/hide no results message
            var noResults = document.querySelector('.hozio-support-no-results');
            if (noResults) {
                noResults.style.display = (visibleCount === 0 && query) ? 'block' : 'none';
            }
        }
    })();
    </script>

    <!-- No results message -->
    <div class="hozio-support-no-results">
        <span class="dashicons dashicons-search"></span>
        No matching documentation found. Try a different search term.
    </div>

    <?php
}
