<?php
/**
 * Hozio Pro - Support & Help Page
 * Tabbed category layout with card grid and expandable documentation
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
            </div>

            <!-- Category Tabs -->
            <div class="hozio-tabs" id="hozio-tabs">
                <button class="hozio-tab is-active" data-tab="getting-started">
                    <span class="dashicons dashicons-welcome-learn-more"></span> Getting Started
                </button>
                <button class="hozio-tab" data-tab="pages">
                    <span class="dashicons dashicons-admin-page"></span> Page Management
                </button>
                <button class="hozio-tab" data-tab="elementor">
                    <span class="dashicons dashicons-editor-expand"></span> Elementor Tools
                </button>
                <button class="hozio-tab" data-tab="sitemap">
                    <span class="dashicons dashicons-networking"></span> Sitemap &amp; Content
                </button>
                <button class="hozio-tab" data-tab="settings">
                    <span class="dashicons dashicons-admin-generic"></span> Settings &amp; Admin
                </button>
                <button class="hozio-tab" data-tab="acf-shortcodes">
                    <span class="dashicons dashicons-database-view"></span> ACF Shortcodes
                </button>
            </div>

            <!-- Card Grid -->
            <div class="hozio-card-grid" id="hozio-card-grid">

                <!-- ======== GETTING STARTED ======== -->

                <div class="hozio-card" data-section="dynamic-tags" data-category="getting-started" data-description="Use dynamic content in Elementor widgets">
                    <span class="dashicons dashicons-tag hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Dynamic Tags</h3>
                    <p class="hozio-card-desc">Use dynamic content in Elementor widgets</p>
                </div>

                <div class="hozio-card" data-section="taxonomies" data-category="getting-started" data-description="Organize pages with parent &amp; town taxonomies">
                    <span class="dashicons dashicons-category hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Page &amp; Town Taxonomies</h3>
                    <p class="hozio-card-desc">Organize pages with parent &amp; town taxonomies</p>
                </div>

                <div class="hozio-card" data-section="shortcodes" data-category="getting-started" data-description="Use [hozio] tags in HTML widgets &amp; posts">
                    <span class="dashicons dashicons-editor-code hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Shortcodes</h3>
                    <p class="hozio-card-desc">Use [hozio] tags in HTML widgets &amp; posts</p>
                </div>

                <!-- ======== PAGE MANAGEMENT ======== -->

                <div class="hozio-card" data-section="parent-pages-query" data-category="pages" data-description="Filter Elementor queries by parent page">
                    <span class="dashicons dashicons-admin-page hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Parent Pages Query</h3>
                    <p class="hozio-card-desc">Filter Elementor queries by parent page</p>
                </div>

                <div class="hozio-card" data-section="parent-page-filtering" data-category="pages" data-description="Handle same-slug pages across locations">
                    <span class="dashicons dashicons-filter hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Parent Page Filtering</h3>
                    <p class="hozio-card-desc">Handle same-slug pages across locations</p>
                </div>

                <div class="hozio-card" data-section="county-pages" data-category="pages" data-description="Manage county-level location pages">
                    <span class="dashicons dashicons-location-alt hozio-card-icon"></span>
                    <h3 class="hozio-card-title">County Pages</h3>
                    <p class="hozio-card-desc">Manage county-level location pages</p>
                </div>

                <div class="hozio-card" data-section="connect-town-taxonomies" data-category="pages" data-description="Bulk-assign town terms to pages">
                    <span class="dashicons dashicons-randomize hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Connect Town Taxonomies</h3>
                    <p class="hozio-card-desc">Bulk-assign town terms to pages</p>
                </div>

                <div class="hozio-card" data-section="taxonomy-archive-settings" data-category="pages" data-description="Enable/disable taxonomy archive pages">
                    <span class="dashicons dashicons-archive hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Taxonomy Archive Settings</h3>
                    <p class="hozio-card-desc">Enable/disable taxonomy archive pages</p>
                </div>

                <div class="hozio-card" data-section="ghost-page-protection" data-category="pages" data-description="Block phantom URLs from orphaned child pages and taxonomy terms">
                    <span class="dashicons dashicons-shield hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Ghost Page Protection</h3>
                    <p class="hozio-card-desc">Block phantom URLs from orphaned child pages</p>
                </div>

                <div class="hozio-card" data-section="town-content-checker" data-category="pages" data-description="Scan town pages for missing ACF fields with deep-link editing">
                    <span class="dashicons dashicons-clipboard hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Town Content Checker</h3>
                    <p class="hozio-card-desc">Scan town pages for missing ACF fields</p>
                </div>

                <!-- ======== ELEMENTOR TOOLS ======== -->

                <div class="hozio-card" data-section="loop-configurations" data-category="elementor" data-description="Configure Elementor loop widget queries">
                    <span class="dashicons dashicons-grid-view hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Loop Configurations</h3>
                    <p class="hozio-card-desc">Configure Elementor loop widget queries</p>
                </div>

                <div class="hozio-card" data-section="query-post-types" data-category="elementor" data-description="Add custom post types to Elementor queries">
                    <span class="dashicons dashicons-database hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Query Post Types</h3>
                    <p class="hozio-card-desc">Add custom post types to Elementor queries</p>
                </div>

                <div class="hozio-card" data-section="services-children-query" data-category="elementor" data-description="Query child pages of Services page">
                    <span class="dashicons dashicons-networking hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Services Children Query</h3>
                    <p class="hozio-card-desc">Query child pages of Services page</p>
                </div>

                <div class="hozio-card" data-section="dom-parsing" data-category="elementor" data-description="Auto-hide empty ACF content on frontend">
                    <span class="dashicons dashicons-hidden hozio-card-icon"></span>
                    <h3 class="hozio-card-title">DOM Parsing</h3>
                    <p class="hozio-card-desc">Auto-hide empty ACF content on frontend</p>
                </div>

                <div class="hozio-card" data-section="nav-text-color" data-category="elementor" data-description="Set CTA button color in toggle menu">
                    <span class="dashicons dashicons-art hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Nav Menu Text Color</h3>
                    <p class="hozio-card-desc">Set CTA button color in toggle menu</p>
                </div>

                <!-- ======== SITEMAP & CONTENT ======== -->

                <div class="hozio-card" data-section="html-sitemap" data-category="sitemap" data-description="Auto-generated sitemap with accordions">
                    <span class="dashicons dashicons-networking hozio-card-icon"></span>
                    <h3 class="hozio-card-title">HTML Sitemap</h3>
                    <p class="hozio-card-desc">Auto-generated sitemap with accordions</p>
                </div>

                <div class="hozio-card" data-section="sitemap-layout-editor" data-category="sitemap" data-description="Manually arrange sitemap layout">
                    <span class="dashicons dashicons-layout hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Sitemap Layout Editor</h3>
                    <p class="hozio-card-desc">Manually arrange sitemap layout</p>
                </div>

                <div class="hozio-card" data-section="blog-permalink" data-category="sitemap" data-description="Customize the blog page URL slug">
                    <span class="dashicons dashicons-admin-links hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Blog Permalink Settings</h3>
                    <p class="hozio-card-desc">Customize the blog page URL slug</p>
                </div>

                <div class="hozio-card" data-section="rss-feed" data-category="sitemap" data-description="Redirect RSS feeds to a custom URL">
                    <span class="dashicons dashicons-rss hozio-card-icon"></span>
                    <h3 class="hozio-card-title">RSS Feed Override</h3>
                    <p class="hozio-card-desc">Redirect RSS feeds to a custom URL</p>
                </div>

                <div class="hozio-card" data-section="service-menu-sync" data-category="sitemap" data-description="Sync service pages to nav menu">
                    <span class="dashicons dashicons-menu-alt3 hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Service Menu Sync</h3>
                    <p class="hozio-card-desc">Sync service pages to nav menu</p>
                </div>

                <div class="hozio-card" data-section="duplicate-page-detector" data-category="sitemap" data-description="Find and fix WordPress auto-generated duplicate page slugs (-2, -3)">
                    <span class="dashicons dashicons-warning hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Duplicate Page Detector</h3>
                    <p class="hozio-card-desc">Find and fix duplicate page slugs (-2, -3)</p>
                </div>

                <div class="hozio-card" data-section="image-video-sitemap" data-category="sitemap" data-description="Auto-generate image and video XML sitemap entries for Google Search Console">
                    <span class="dashicons dashicons-format-image hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Image &amp; Video XML Sitemap</h3>
                    <p class="hozio-card-desc">Auto-generate image and video XML sitemaps</p>
                </div>

                <!-- ======== SETTINGS & ADMIN ======== -->

                <div class="hozio-card" data-section="lead-management" data-category="settings" data-description="View and manage form submissions">
                    <span class="dashicons dashicons-email-alt hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Lead Management</h3>
                    <p class="hozio-card-desc">View and manage form submissions</p>
                </div>

                <div class="hozio-card" data-section="lead-webhook" data-category="settings" data-description="Secure REST endpoint for capturing leads from external services — secret key, enable/disable toggle, blocked attempts log">
                    <span class="dashicons dashicons-shield hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Lead Webhook</h3>
                    <p class="hozio-card-desc">Secure endpoint for external lead capture</p>
                </div>

                <div class="hozio-card" data-section="plugin-settings" data-category="settings" data-description="Configure features &amp; debug logging">
                    <span class="dashicons dashicons-admin-generic hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Plugin Settings &amp; Debug</h3>
                    <p class="hozio-card-desc">Configure features &amp; debug logging</p>
                </div>

                <div class="hozio-card" data-section="hub-connectivity" data-category="settings" data-description="Connect to Hozio Hub for remote management">
                    <span class="dashicons dashicons-cloud hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Hub Connectivity</h3>
                    <p class="hozio-card-desc">Connect to Hozio Hub for remote management</p>
                </div>

                <div class="hozio-card" data-section="faq-schema" data-category="settings" data-description="Output FAQPage JSON-LD schema to page head from ACF fields">
                    <span class="dashicons dashicons-editor-help hozio-card-icon"></span>
                    <h3 class="hozio-card-title">FAQ Schema</h3>
                    <p class="hozio-card-desc">Output FAQPage JSON-LD to &lt;head&gt; from ACF fields</p>
                </div>

                <!-- ======== ACF SHORTCODES ======== -->

                <div class="hozio-card" data-section="acf-generic-shortcodes" data-category="acf-shortcodes" data-description="Universal ACF shortcodes: acf_text, acf_img, acf_img_url, acf_raw — output any ACF field anywhere">
                    <span class="dashicons dashicons-shortcode hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Generic ACF Shortcodes</h3>
                    <p class="hozio-card-desc">Output any ACF field value anywhere on the site</p>
                </div>

                <div class="hozio-card" data-section="acf-hog-shortcodes" data-category="acf-shortcodes" data-description="Named shortcodes for all Town and HOG page ACF fields — hero, outcomes, about us, how it works, FAQ, links">
                    <span class="dashicons dashicons-location hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Town / HOG Page Fields</h3>
                    <p class="hozio-card-desc">Named shortcodes for every HOG page ACF field</p>
                </div>

                <div class="hozio-card" data-section="acf-service-shortcodes" data-category="acf-shortcodes" data-description="Named shortcodes for all Service page ACF fields — hero, trust symbols, benefits, how it works, FAQ, service-related info">
                    <span class="dashicons dashicons-admin-tools hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Service Page Fields</h3>
                    <p class="hozio-card-desc">Named shortcodes for every service page ACF field</p>
                </div>

                <div class="hozio-card" data-section="service-towns" data-category="acf-shortcodes" data-description="County accordion shortcode that displays town links grouped by county on service pages — setup, ACF field config, county groups">
                    <span class="dashicons dashicons-location hozio-card-icon"></span>
                    <h3 class="hozio-card-title">Service Towns Shortcode</h3>
                    <p class="hozio-card-desc">County accordion with town links for service pages</p>
                </div>

            </div><!-- .hozio-card-grid -->

            <!-- Expanded Detail Panel -->
            <div class="hozio-detail-panel" id="hozio-detail-panel" style="display:none;">
                <button type="button" class="hozio-detail-close" id="hozio-detail-close" title="Close">&times;</button>
                <div class="hozio-detail-body" id="hozio-detail-body"></div>
            </div>

            <!-- Hidden section content blocks (loaded into detail panel on card click) -->
            <div style="display:none;" id="hozio-section-data">

                <!-- Section: dynamic-tags -->
                <div data-content="dynamic-tags">
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

                <!-- Section: taxonomies -->
                <div data-content="taxonomies">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Hozio Pro adds two custom taxonomies to WordPress pages: <strong>Page Taxonomies</strong> (parent_pages) and <strong>Town Taxonomies</strong> (town_taxonomies). These allow you to group and filter pages by service type, location, or any custom classification &mdash; which powers the dynamic query features.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Go to Pages</strong> in the WordPress admin. You'll see two new columns: "Page Taxonomies" and "Town Taxonomies".</li>
                            <li><strong>Edit any page</strong> and look for the "Page Taxonomies" and "Town Taxonomies" meta boxes in the sidebar.</li>
                            <li><strong>Assign taxonomy terms</strong> to pages. For example, assign the term "plumbing" to all plumbing service town pages.</li>
                            <li><strong>To bulk-create Town Taxonomy terms</strong>, click the "Connect Town Taxonomies" button on the Pages list.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Both taxonomies are <strong>hierarchical</strong> (like categories), so you can create parent/child term structures.</li>
                            <li>The <strong>Connect Town Taxonomies</strong> tool skips parent pages &mdash; it only processes leaf/town pages.</li>
                            <li>To enable/disable taxonomy archive pages, go to <strong>Hozio Pro &rarr; Archive Settings</strong>.</li>
                            <li>WordPress does not allow two terms with the same slug in the same taxonomy. See the <strong>Parent Page Filtering</strong> section for handling duplicate slugs.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: shortcodes -->
                <div data-content="shortcodes">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Hozio Pro provides shortcodes you can use in pages, posts, Elementor text widgets, and <strong>HTML widgets</strong>:</p>
                        <h4 style="margin-top: 16px;"><code>[hozio tag="..."]</code> &mdash; Universal Dynamic Tag Shortcode</h4>
                        <p>Use <strong>any</strong> Hozio dynamic tag value inside HTML widgets or anywhere shortcodes are supported. Just pass the tag slug as the <code>tag</code> attribute:</p>
                        <ul>
                            <li><code>[hozio tag="company-phone-1-name"]</code> &mdash; Display phone number</li>
                            <li><code>[hozio tag="company-address"]</code> &mdash; Company address (HTML supported)</li>
                            <li><code>[hozio tag="business-hours"]</code> &mdash; Business hours (HTML supported)</li>
                            <li><code>[hozio tag="years-of-experience"]</code> &mdash; Calculated years</li>
                            <li><code>[hozio tag="facebook"]</code> &mdash; Facebook URL</li>
                            <li><code>[hozio tag="my-custom-tag"]</code> &mdash; Any custom tag you&rsquo;ve created</li>
                        </ul>
                        <p>For phone, SMS, and email tags, add <code>format="url"</code> to get the prefixed URL version:</p>
                        <ul>
                            <li><code>[hozio tag="company-phone-1" format="url"]</code> &rarr; <code>tel:5551234567</code></li>
                            <li><code>[hozio tag="company-email" format="url"]</code> &rarr; <code>mailto:info@example.com</code></li>
                            <li><code>[hozio tag="sms-phone" format="url"]</code> &rarr; <code>sms:5551234567</code></li>
                        </ul>
                        <p><strong>Example HTML widget code:</strong></p>
                        <pre style="background: #f3f4f6; padding: 12px; border-radius: 6px; font-size: 13px; overflow-x: auto; margin: 8px 0;">&lt;a href="[hozio tag='company-phone-1' format='url']"&gt;
  Call us: [hozio tag='company-phone-1-name']
&lt;/a&gt;
&lt;p&gt;[hozio tag='company-address']&lt;/p&gt;
&lt;p&gt;[hozio tag='years-of-experience'] years of experience&lt;/p&gt;</pre>
                        <h4 style="margin-top: 20px;"><code>[hozio_current_year]</code></h4>
                        <p>Outputs the current 4-digit year (e.g., <strong><?php echo date('Y'); ?></strong>). No parameters needed. Useful for copyright notices.</p>
                        <h4 style="margin-top: 16px;"><code>[gmb_map]</code></h4>
                        <p>Embeds a Google My Business map from an ACF field. Parameters: <code>field</code> (default: <code>gmb_map</code>), <code>post_id</code> (default: current post).</p>
                        <h4 style="margin-top: 16px;"><code>[final_cta]</code></h4>
                        <p>Displays an ACF field value from a specific page. Both <code>field</code> and <code>page_id</code> are <strong>required</strong>.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to use</h3>
                        <ol>
                            <li>Place any shortcode in a page, post, Elementor Text Editor widget, or <strong>Elementor HTML widget</strong>.</li>
                            <li>To quickly copy a shortcode, go to <strong>Hozio Pro &rarr; Dynamic Tags Settings</strong>. Each field has a small <strong>copy button</strong> beneath it &mdash; click it to copy the shortcode to your clipboard.</li>
                            <li>Paste the shortcode into your HTML widget or text editor. For phone/email/SMS links, add <code>format="url"</code> inside the <code>href</code> attribute.</li>
                            <li>For <code>[gmb_map]</code>, ensure the ACF field exists and contains the Google Map embed code.</li>
                            <li>For <code>[final_cta]</code>, find the page ID in the WordPress admin URL when editing a page (e.g., <code>post=42</code>).</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>The <code>[hozio]</code> shortcode works with <strong>all built-in and custom dynamic tags</strong>.</li>
                            <li>Each field on the Dynamic Tags Settings page has a <strong>copy shortcode</strong> button &mdash; no need to remember tag slugs.</li>
                            <li><code>[gmb_map]</code> and <code>[final_cta]</code> require the <strong>ACF</strong> plugin to be active.</li>
                            <li>All shortcode output is properly escaped. Script tags in custom tag values are blocked for security.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: parent-pages-query -->
                <div data-content="parent-pages-query">
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
                            <li>The widget will now automatically display pages that share the same Page Taxonomy term as the current page.</li>
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

                <!-- Section: parent-page-filtering -->
                <div data-content="parent-page-filtering">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>When multiple page hierarchies share the same slug (e.g., <code>/gutter/installation/</code> and <code>/roofing/installation/</code>), their child town pages would normally mix together in query results. <strong>Parent Page Filtering</strong> solves this by restricting queries to only return pages that are direct children of the page where the checkbox is enabled.</p>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>The problem this solves</h3>
                        <ul>
                            <li>You have multiple service categories each with a similar sub-service (e.g., "installation" under both gutter and roofing).</li>
                            <li>Both sets of town pages share the same <strong>"installation"</strong> Page Taxonomy term.</li>
                            <li><strong>Without this feature:</strong> The query shows ALL "installation" pages &mdash; including ones from the wrong service category.</li>
                            <li><strong>With this feature:</strong> The query only returns direct children of the page where the checkbox is enabled.</li>
                        </ul>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Identify the service pages</strong> that share the same slug across different hierarchies.</li>
                            <li><strong>Edit the service page</strong> in WordPress (the page where the Elementor loop widget lives).</li>
                            <li><strong>In the sidebar</strong>, find the <strong>"Parent Pages Query Options"</strong> meta box.</li>
                            <li><strong>Check the box</strong> labeled <strong>"Filter by parent page"</strong>.</li>
                            <li><strong>Save/update</strong> the page. Repeat for every other page that shares the same slug.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li><strong>Only needed for duplicate slugs.</strong> If your service page slugs are already unique, you don't need this feature.</li>
                            <li><strong>Ancestor walk-up:</strong> If the checkbox isn't found on the current page, the system checks parent pages going up the hierarchy.</li>
                            <li>Affects <code>dynamic_parent_pages_query</code> and <code>dynamic_county_pages_query</code>. Does NOT affect <code>dynamic_town_pages_query</code> (which intentionally shows cross-hierarchy results).</li>
                            <li>The HTML Sitemap also respects this checkbox &mdash; accordion labels are prefixed with the parent page's name to distinguish same-named sections.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: county-pages -->
                <div data-content="county-pages">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>County Pages is an advanced query mode that extends the parent pages system. When enabled, it uses a <strong>composite slug</strong> (parent term + page slug) to match taxonomy terms, and adds support for a special "county" term to distinguish county-level pages from regular town pages.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Create your page hierarchy</strong> with county pages as children of service pages.</li>
                            <li><strong>Create Page Taxonomy terms</strong> using the composite slug pattern: <code>parentterm-pageslug</code>.</li>
                            <li><strong>Create a "county" term</strong> in the Page Taxonomies as a special marker.</li>
                            <li><strong>Assign both terms</strong> to your county pages: the composite term AND the "county" term.</li>
                            <li><strong>Enable the county pages flag</strong> by setting the <code>use_county_pages</code> custom field to <code>true</code>.</li>
                            <li><strong>Use the query IDs</strong>: <code>dynamic_parent_pages_query</code> (excludes county) or <code>dynamic_county_pages_query</code> (only county).</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>How composite slug matching works</h3>
                        <ul>
                            <li>When <code>use_county_pages</code> is enabled, the query checks if term slugs equal <code>parent-term-slug + "-" + page-slug</code>.</li>
                            <li>The <code>dynamic_parent_pages_query</code> excludes pages with the "county" marker term.</li>
                            <li>The <code>dynamic_county_pages_query</code> only includes pages with BOTH the matching term AND the "county" term.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: connect-town-taxonomies -->
                <div data-content="connect-town-taxonomies">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>A bulk tool that automatically creates Town Taxonomy terms from your page slugs and assigns them to the corresponding pages. This saves you from manually creating and assigning hundreds of town taxonomy terms one by one.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Go to the <strong>Pages</strong> list in your WordPress admin.</li>
                            <li>Click the <strong>"Connect Town Taxonomies"</strong> button at the top of the page list.</li>
                            <li>Select which <strong>Page Taxonomies</strong> to process.</li>
                            <li>Click <strong>Connect</strong> to run the bulk assignment.</li>
                            <li>Review the results summary.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Only <strong>leaf/town-level pages</strong> are processed &mdash; parent pages are automatically skipped.</li>
                            <li>If a Town Taxonomy term already exists, it will be assigned without creating a duplicate.</li>
                            <li>Designed for initial setup or when adding many new location pages at once.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: taxonomy-archive-settings -->
                <div data-content="taxonomy-archive-settings">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Controls whether public archive pages are enabled for the <strong>Parent Pages</strong> and <strong>Town Taxonomies</strong> custom taxonomies.</p>
                        <ul>
                            <li><strong>Parent Pages archives:</strong> <code>/parent-pages/{term-slug}/</code></li>
                            <li><strong>Town Taxonomies archives:</strong> <code>/town/{term-slug}/</code></li>
                        </ul>
                        <p>When disabled, visiting an archive URL will <strong>301 redirect</strong> to the homepage.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Navigate to <strong>Hozio Pro &rarr; Archive Settings</strong>.</li>
                            <li>Toggle <strong>Archive Pages</strong> on or off for each taxonomy.</li>
                            <li>Click <strong>Save</strong>. Rewrite rules flush automatically.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Archives are <strong>disabled by default</strong>.</li>
                            <li>If you see 404 errors after enabling, visit <strong>Settings &rarr; Permalinks</strong> and click Save to force a rewrite flush.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: loop-configurations -->
                <div data-content="loop-configurations">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Loop Configurations let you create reusable filter presets for Elementor Loop Grid and Loop Carousel widgets. Instead of manually setting up query filters on every page, you create a configuration once and assign it to pages.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Go to Hozio Pro &rarr; Loop Configurations</strong> in the admin menu.</li>
                            <li><strong>Create a new configuration</strong> by giving it a unique name.</li>
                            <li><strong>Select the taxonomy</strong> to filter by and <strong>choose the terms</strong> to include.</li>
                            <li><strong>Save the configuration.</strong></li>
                            <li><strong>Edit a page</strong> and select your configuration from the <strong>"Loop Configuration"</strong> meta box in the sidebar.</li>
                            <li>All Loop Grid and Loop Carousel widgets on that page will now use this configuration's filters automatically.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Filter modes</h3>
                        <ul>
                            <li><strong>By Taxonomy Terms (default):</strong> Filter loop results by selecting a taxonomy and specific terms. All pages matching those terms are shown.</li>
                            <li><strong>By Specific Pages:</strong> Hand-pick an exact list of pages to display using a checkbox picker. Use this when you need precise control over which pages appear rather than relying on taxonomy assignment.</li>
                            <li>Configurations apply to <strong>all loop widgets on the page</strong> (excluding header, footer, and popup contexts).</li>
                            <li>If no configuration is assigned, loop widgets use their default Elementor query settings.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: query-post-types -->
                <div data-content="query-post-types">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Configures which custom post types are available in Elementor&rsquo;s dynamic queries and loop widgets. This setting lets you toggle on any registered custom post type so it appears as a query source option in Elementor.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Navigate to <strong>Hozio Pro &rarr; Query Post Types</strong>.</li>
                            <li>Toggle <strong>on</strong> the post types you want available in Elementor queries.</li>
                            <li>Click <strong>Save</strong>.</li>
                            <li>In Elementor, the selected post types will now appear in Loop/Posts widget query settings.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Core WordPress types and Elementor internal types are <strong>excluded from the list</strong>.</li>
                            <li>Only <strong>public</strong> custom post types are shown.</li>
                            <li>You may need to refresh the Elementor editor after saving.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: services-children-query -->
                <div data-content="services-children-query">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Provides a custom Elementor Query ID called <code>services_children</code> that automatically fetches all child pages of the page with the slug <strong>&ldquo;services&rdquo;</strong>.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to use</h3>
                        <ol>
                            <li>In Elementor, add a <strong>Posts</strong> or <strong>Loop Grid</strong> widget.</li>
                            <li>In <strong>Query</strong> settings, set the <strong>Query ID</strong> to: <code>services_children</code></li>
                            <li>The widget will automatically display all child pages of your "Services" page.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Your site must have a page with the slug <strong>"services"</strong>.</li>
                            <li>Only <strong>direct children</strong> are returned (not grandchildren).</li>
                            <li>Also available as a <strong>Dynamic Tag</strong> in Elementor.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: dom-parsing -->
                <div data-content="dom-parsing">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Automatically removes empty ACF-powered content from the frontend using two special CSS classes:</p>
                        <h4 style="margin-top: 16px;"><code>hide-if-empty-acf</code></h4>
                        <p>Add to an Elementor <strong>Icon List</strong> widget. Empty or fallback items are removed. If all items are empty, the entire widget is hidden.</p>
                        <h4 style="margin-top: 16px;"><code>hide-if-no-wiki</code></h4>
                        <p>Add to a <strong>container div</strong> wrapping a Text Editor widget. If the content is empty or matches fallback text, the container is hidden.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Ensure <strong>DOM Parsing</strong> is enabled in <strong>Hozio Pro &rarr; Settings</strong> (enabled by default).</li>
                            <li>In Elementor, go to the widget's <strong>Advanced &rarr; CSS Classes</strong>.</li>
                            <li>Add <code>hide-if-empty-acf</code> or <code>hide-if-no-wiki</code> as needed.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Uses server-side HTML parsing (DOMDocument) &mdash; no flash of empty content.</li>
                            <li>Recognized fallback values: empty strings, "Google Map Link", "USPS Link", "Pharmacy Link", "Weather Link", "County &amp; State Wiki Link".</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: nav-text-color -->
                <div data-content="nav-text-color">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Sets a custom text color for the last item in your toggle navigation menu (typically a CTA button). Applied via inline CSS targeting <code>#toggle-menu li:last-of-type</code>.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Settings</strong>.</li>
                            <li>Find <strong>Navigation Text Color</strong> under Business Details.</li>
                            <li>Use the color picker to select your color.</li>
                            <li>Click <strong>Save</strong>.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Targets only the <strong>last menu item</strong> in <code>#toggle-menu</code>.</li>
                            <li>Default color is <strong>black</strong> if not set.</li>
                            <li>Uses <code>!important</code> to override theme styles.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: html-sitemap -->
                <div data-content="html-sitemap">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Provides an HTML sitemap page template that displays a hierarchical list of all your site's content, styled with customizable colors.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Create a new page</strong> in WordPress (e.g., title it "Sitemap").</li>
                            <li><strong>In Page Attributes</strong>, select <strong>"HTML Sitemap"</strong> as the page template.</li>
                            <li><strong>Publish the page.</strong></li>
                            <li><strong>Customize appearance</strong> via <strong>Hozio Pro &rarr; Sitemap Settings &rarr; Appearance</strong> tab.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Appearance options</h3>
                        <ul>
                            <li>This is an <strong>HTML sitemap</strong> for visitors, not an XML sitemap for search engines.</li>
                            <li>The template automatically generates the sitemap content &mdash; no shortcodes needed.</li>
                            <li><strong>Color Theme:</strong> Choose <em>Light</em>, <em>Dark</em>, or <em>Custom Color</em> using the pill selector. Custom Color opens a color picker for a fully branded background.</li>
                            <li><strong>Border &amp; Divider Color:</strong> Set the color used for accordion borders and section dividers independently from the background.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: sitemap-layout-editor -->
                <div data-content="sitemap-layout-editor">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>The Layout Editor gives you full control over how your HTML sitemap is organized with a drag-and-drop interface. Two modes:</p>
                        <ul>
                            <li><strong>Override + Auto-fill:</strong> Manual accordions render first, remaining pages auto-detected.</li>
                            <li><strong>Manual Only:</strong> Only your manually defined accordions appear.</li>
                        </ul>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Navigate to <strong>Hozio Pro &rarr; Sitemap Settings &rarr; Layout Editor</strong> tab.</li>
                            <li>Enable <strong>"Enable layout overrides"</strong>.</li>
                            <li>Select your mode: <strong>Override + Auto-fill</strong> or <strong>Manual Only</strong>.</li>
                            <li>Use <strong>"Import Current Auto-Detection"</strong> to import the current layout as a starting point.</li>
                            <li>Use <strong>"Add Accordion"</strong> to create new sections. Drag and drop to reorder.</li>
                            <li>Click <strong>Save Layout</strong> when finished.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>The <strong>Exclude Pages</strong> section lets you hide specific pages from the sitemap entirely.</li>
                            <li>Importing auto-detection <strong>replaces</strong> your current manual layout.</li>
                            <li>Child pages can have nested children (sub-accordions) for multi-level structures.</li>
                            <li><strong>Deleted pages are auto-removed</strong> from the layout on save &mdash; you don&rsquo;t need to manually clean up after trashing pages.</li>
                            <li>A <strong>warning banner</strong> appears at the top of the Layout Editor when town pages have missing ACF content fields. Click the banner to jump to the <strong>Town Content Checker</strong> for details.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: blog-permalink -->
                <div data-content="blog-permalink">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Customizes your blog post URL structure independently from WordPress's default permalink settings. Add a <code>/blog/</code> prefix and/or include the post's category in the URL.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Blog Permalink Settings</strong>.</li>
                            <li>Toggle <strong>"Blog Prefix"</strong> and/or <strong>"Category Prefix"</strong>.</li>
                            <li>Check the preview at the bottom of the page.</li>
                            <li><strong>Save.</strong> Rewrite rules are flushed automatically.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>URL structure examples</h3>
                        <ul>
                            <li>Both enabled: <code>/blog/category-name/post-name/</code></li>
                            <li>Blog prefix only: <code>/blog/post-name/</code></li>
                            <li>Category prefix only: <code>/category-name/post-name/</code></li>
                            <li>Both disabled: <code>/post-name/</code></li>
                        </ul>
                    </div>
                </div>

                <!-- Section: rss-feed -->
                <div data-content="rss-feed">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Replaces the default WordPress RSS feed content with structured content pulled from ACF fields. Useful when your post content is built with Elementor but your actual text lives in ACF fields.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Blog Permalink Settings</strong>.</li>
                            <li>Toggle <strong>"ACF RSS Feed Override"</strong> to enable.</li>
                            <li><strong>Save settings.</strong></li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>The feed includes the <strong>featured image as an enclosure</strong> tag (compatible with Zapier).</li>
                            <li>ACF field names must match the expected structure: <code>introduction</code>, <code>section_1_heading</code>, <code>section_1_body</code>, etc.</li>
                            <li>Posts without ACF fields fall back to default WordPress content.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: service-menu-sync -->
                <div data-content="service-menu-sync">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Automatically adds and removes pages from navigation menus when tagged with the <strong>"service-pages-loop-item"</strong> Page Taxonomy term.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Ensure <strong>Service Menu Sync</strong> is enabled in Hozio Pro Settings.</li>
                            <li>Create your navigation menus in <strong>Appearance &rarr; Menus</strong>. The sync works with: Main Menu, Main Menu Toggle, and Services Menu.</li>
                            <li>Create a Page Taxonomy term called <strong>"service-pages-loop-item"</strong>.</li>
                            <li>Assign this term to any page you want to appear in the service menus.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Only <strong>auto-added items</strong> are removed when the term is removed. Manual menu items are never touched.</li>
                            <li>Compatible with <strong>WP All Import</strong> &mdash; uses delayed sync with retry logic.</li>
                            <li>Can be <strong>disabled globally</strong> via Hozio Pro Settings.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: lead-management -->
                <div data-content="lead-management">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>A built-in CRM dashboard for managing Elementor form submissions. Displays all leads with search, filtering, pagination, and detailed views.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li><strong>Lead Submissions</strong> appears as its own menu item in the WordPress admin.</li>
                            <li>Elementor form submissions are automatically captured and displayed.</li>
                            <li>Click any lead to view full submission details.</li>
                            <li>To customize colors, go to <strong>Lead Submissions &rarr; Display Settings</strong> (admin only).</li>
                            <li>Non-admin users are automatically redirected to the Lead Submissions page after login.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li><strong>Required Form Field IDs:</strong> Submissions will appear blank without these:
                                <ul style="margin-top: 6px; margin-bottom: 6px;">
                                    <li>First Name: <code>fname</code></li>
                                    <li>Last Name: <code>lname</code></li>
                                    <li>Email: <code>email</code></li>
                                    <li>Telephone: <code>tel</code></li>
                                </ul>
                                Set these in Elementor under each form field's <strong>Advanced</strong> tab &rarr; <strong>ID</strong> field.
                            </li>
                            <li>You can also use the <code>[leads_digest]</code> shortcode to display leads on a frontend page.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: lead-webhook -->
                <div data-content="lead-webhook">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>The Lead Webhook is a secure REST API endpoint at <code>/wp-json/hozio/v1/lead</code> that allows external services (Zapier, Make, custom scripts) to POST lead data directly into your Lead Submissions dashboard. It is protected by a per-site secret key — requests without the correct key are rejected and logged automatically.</p>
                        <p><strong>Standard form integrations are not affected by this feature.</strong> Elementor Forms, WPForms, Gravity Forms, CF7, and Divi Forms all capture leads via WordPress hooks — they do not use this endpoint. The webhook is only relevant if you are sending leads from an external source (e.g., a third-party landing page, Zapier workflow, or API integration).</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Enable / disable the webhook</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Hozio Pro Settings</strong>.</li>
                            <li>In the <strong>Feature Toggles</strong> section, find <strong>Lead Webhook Endpoint</strong>.</li>
                            <li>Toggle it <strong>on</strong> (default) to enable, or <strong>off</strong> to completely disable. When disabled, the endpoint returns 404 and is invisible to bots.</li>
                            <li>Click <strong>Save Settings</strong>.</li>
                        </ol>
                        <p style="margin-top:10px;padding:10px 14px;background:#fef3c7;border-left:3px solid #f59e0b;border-radius:4px;font-size:13px;">
                            <strong>Recommendation:</strong> If you are not sending leads from any external service, disable the webhook. There is no benefit to leaving an unused endpoint open, even though it is protected by the secret key.
                        </p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Finding your webhook URL &amp; secret key</h3>
                        <ol>
                            <li>Go to <strong>Lead Submissions &rarr; Webhook Settings</strong> in the WordPress admin.</li>
                            <li>Your <strong>Webhook URL</strong> and <strong>Secret Key</strong> are displayed at the top of the page.</li>
                            <li>Click the <strong>copy icon</strong> next to each value to copy it to your clipboard.</li>
                            <li>If you need to rotate the secret (e.g., if it was accidentally exposed), click <strong>Regenerate Secret</strong>. Update the new secret in all services that use it.</li>
                        </ol>
                        <p style="margin-top:10px;padding:10px 14px;background:#f0f9ff;border-left:3px solid #0ea5e9;border-radius:4px;font-size:13px;">
                            <strong>Security note:</strong> The secret key is only shown inside your WordPress admin. It is never embedded in your site's HTML source. Even if a bot discovers the endpoint URL, every request without the correct key is automatically rejected and added to the blocked attempts log.
                        </p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How to send leads to the webhook</h3>
                        <p>Every request must include the <code>X-Hozio-Secret</code> header set to your secret key. The body must be JSON.</p>

                        <h4 style="margin-top:16px;">Elementor Forms (via webhook action)</h4>
                        <p>In the Elementor form widget &rarr; <strong>Actions After Submit</strong> &rarr; <strong>Webhook</strong>:</p>
                        <ul>
                            <li><strong>URL:</strong> your Webhook URL</li>
                            <li><strong>Advanced Headers:</strong> <code>X-Hozio-Secret: &lt;your-secret&gt;</code></li>
                        </ul>
                        <p style="color:#6b7280;font-size:12px;margin-top:4px;">Note: Elementor also captures leads via the WordPress hook — this webhook action is only needed for external/remote capture scenarios. For on-site Elementor forms, leads are already captured automatically without any webhook configuration.</p>

                        <h4 style="margin-top:16px;">WPForms (via Zapier or webhook addon)</h4>
                        <p>In the Zapier action (HTTP POST):</p>
                        <ul>
                            <li><strong>URL:</strong> your Webhook URL</li>
                            <li><strong>Headers:</strong> <code>Content-Type: application/json</code>, <code>X-Hozio-Secret: &lt;your-secret&gt;</code></li>
                            <li><strong>Body (JSON):</strong> <code>{"fname":"...","lname":"...","email":"...","tel":"..."}</code></li>
                        </ul>

                        <h4 style="margin-top:16px;">Zapier / Make.com (generic HTTP action)</h4>
                        <pre style="background:#f3f4f6;padding:12px;border-radius:6px;font-size:12px;overflow-x:auto;margin:8px 0;">POST https://yoursite.com/wp-json/hozio/v1/lead
Content-Type: application/json
X-Hozio-Secret: &lt;your-secret&gt;

{
  "fname": "Jane",
  "lname": "Smith",
  "email": "jane@example.com",
  "tel": "555-867-5309",
  "message": "I need a quote"
}</pre>

                        <h4 style="margin-top:16px;">cURL (for testing)</h4>
                        <pre style="background:#f3f4f6;padding:12px;border-radius:6px;font-size:12px;overflow-x:auto;margin:8px 0;">curl -X POST https://yoursite.com/wp-json/hozio/v1/lead \
  -H "Content-Type: application/json" \
  -H "X-Hozio-Secret: &lt;your-secret&gt;" \
  -d '{"fname":"Test","lname":"User","email":"test@example.com","tel":"5551234567"}'</pre>
                    </div>

                    <div class="hozio-support-notes">
                        <h3>Required &amp; accepted fields</h3>
                        <ul>
                            <li><strong>Required:</strong> At least one of <code>email</code> or <code>tel</code> must be present and non-empty.</li>
                            <li><strong>Accepted:</strong> <code>fname</code>, <code>lname</code>, <code>email</code>, <code>tel</code>, <code>message</code>, <code>source</code>, and any additional key/value pairs (stored in the lead's extended fields).</li>
                            <li><strong>Do not send:</strong> <code>website_url</code> — this is a honeypot field. Any request that includes it will be silently rejected as a bot submission.</li>
                        </ul>

                        <h3 style="margin-top:16px;">Security model</h3>
                        <ul>
                            <li><strong>Secret key:</strong> 384-bit random secret (base64 encoded). Auto-generated on first plugin load — no manual setup required.</li>
                            <li><strong>Timing-safe comparison:</strong> The key is verified with <code>hash_equals()</code> to prevent timing attacks.</li>
                            <li><strong>Rate limiting:</strong> Max 10 requests per IP per 5 minutes. Excess requests return 429.</li>
                            <li><strong>Honeypot:</strong> Requests that include the <code>website_url</code> field are silently rejected.</li>
                            <li><strong>CleanTalk:</strong> If the CleanTalk plugin is active on the site, submissions are also checked against the CleanTalk spam database.</li>
                            <li><strong>Blocked attempts log:</strong> All rejected requests (wrong key, rate limit) are stored for 72 hours and visible at <strong>Lead Submissions &rarr; Webhook Settings</strong>.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: plugin-settings -->
                <div data-content="plugin-settings">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Centralized settings page for controlling plugin-wide options, enabling debug logging for troubleshooting, and managing feature toggles.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Hozio Pro Settings</strong>.</li>
                            <li><strong>Feature toggles:</strong> DOM Parsing, Service Menu Sync, Ghost Page Protection, Auto-Updates.</li>
                            <li><strong>Enable HOZIO_DEBUG</strong> to start logging. Logs are written to <code>wp-content/hozio-debug.log</code>.</li>
                            <li>Use <strong>"Test Log Entry"</strong> to verify logging and <strong>"Clear Log"</strong> to reset.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Debug logging works <strong>independently from WP_DEBUG</strong>.</li>
                            <li>Logs are categorized by component: ParentPagesQuery, CountyQuery, TownQuery, LoopConfig, MenuSync, GhostPage, etc.</li>
                            <li>Remember to <strong>disable debug logging</strong> on production sites.</li>
                            <li><strong>Audit Log:</strong> All Hub commands, plugin updates, and rollbacks are written to <code>wp-content/hozio-audit.log</code> via <code>hozio_audit_log()</code>. The audit log auto-rotates at 500KB and is separate from the debug log. Useful for diagnosing unexpected changes on live sites.</li>
                            <li><strong>Install History:</strong> A timeline in the Plugin Settings page shows every version installed, labeled as Update or Rollback, with Hub-triggered installs marked with a green Hub badge.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: hub-connectivity -->
                <div data-content="hub-connectivity">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Hub Connectivity allows your site to register with the Hozio Hub &mdash; a central management dashboard. Once connected, the Hub manages your license and can send remote commands to the site in real time.</p>
                        <p>Supported remote operations: page management, plugin management, admin access, option updates, REST API proxy, <strong>plugin version control</strong> (update, rollback, version lock), and version drift detection.</p>
                        <p><strong>Version Control features (Hub-managed):</strong></p>
                        <ul>
                            <li><strong>Update &amp; Rollback</strong> &mdash; The Hub can push any release version to the site. The Install History timeline in Plugin Settings shows every install with a label (Update vs. Rollback) and a green Hub badge for Hub-triggered installs.</li>
                            <li><strong>Version Lock</strong> &mdash; The Hub can lock a site to its current version, preventing updates. When locked, the Auto-Updates toggle shows a "Version Lock" badge and the Check for Updates button is grayed out. Version Lock can only be set from the Hub, not from the site.</li>
                            <li><strong>Quick Revert</strong> &mdash; Rolls back to the previous version in one click from the Hub dashboard.</li>
                            <li><strong>Version Drift Detection</strong> &mdash; On each page load, Hozio checks if the installed version matches what the Hub expects. If drift is detected, the Hub is notified automatically.</li>
                        </ul>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Settings</strong>.</li>
                            <li>In the Hub Connection section, the Hub URL defaults to <code>https://www.hozio.com</code>.</li>
                            <li>Enter your <strong>Registration Key</strong>.</li>
                            <li>Click <strong>Connect</strong>. The site will register and receive its license automatically.</li>
                            <li>A heartbeat runs hourly to keep the Hub in sync. After an update or rollback, an additional heartbeat fires within 5 seconds so the Hub reflects the change immediately.</li>
                            <li>Version Lock and auto-update settings can be pushed from the Hub without any action on the site.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>When connected, the <strong>license is managed remotely</strong> &mdash; no manual key entry needed.</li>
                            <li>To disconnect, click <strong>Disconnect</strong> in the Hub Connection section.</li>
                            <li>Remote commands include self-protection: the Hub cannot deactivate Hozio Pro itself.</li>
                            <li><strong>Rollbacks require a valid license.</strong> If the license is inactive, the rollback AJAX handler will reject the request.</li>
                            <li>The Plugin Settings page shows a <strong>Hub connection status indicator</strong> &mdash; it distinguishes between "reachable but not connected" and "fully connected" states.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: ghost-page-protection -->
                <div data-content="ghost-page-protection">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Ghost pages are orphaned child pages whose URL no longer resolves correctly &mdash; for example, a page at <code>/plumbing/houston/</code> that exists in the database but whose parent page was deleted, renamed, or moved. Without protection, these phantom URLs return 200 OK and can be indexed by Google.</p>
                        <p>Ghost Page Protection intercepts every front-end request, compares the requested URL against the page&rsquo;s true permalink, and either <strong>301-redirects</strong> the visitor to the correct URL or returns a hard <strong>404</strong> depending on your setting. Ghost pages are also excluded from the <strong>Yoast XML sitemap</strong> and filtered out of <strong>Elementor dynamic query results</strong>.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Hozio Pro Settings</strong>.</li>
                            <li>Find <strong>Ghost Page Protection</strong> and toggle it <strong>On</strong>.</li>
                            <li>Choose your response mode:
                                <ul>
                                    <li><strong>Canonical Redirect (default):</strong> Ghost URLs 301-redirect to the page&rsquo;s real permalink.</li>
                                    <li><strong>Hard 404:</strong> Ghost URLs return a 404 error page.</li>
                                </ul>
                            </li>
                            <li>Click <strong>Save Settings</strong>.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Ghost pages also receive an <strong>X-Robots-Tag: noindex</strong> HTTP header to prevent them from being indexed even if a crawler finds them.</li>
                            <li>To debug, append <code>?hozio_ghost_debug</code> to any URL &mdash; Hozio will add <code>X-Ghost-Debug</code> headers showing how the URL was evaluated.</li>
                            <li>WordPress resolves single-segment URLs via the <code>name</code> query var. Ghost Protection accounts for this so valid pages are never accidentally blocked.</li>
                            <li>Ghost pages are automatically excluded from Elementor&rsquo;s <code>dynamic_parent_pages_query</code> and <code>dynamic_county_pages_query</code> results.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: town-content-checker -->
                <div data-content="town-content-checker">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>The Town Content Checker scans all your town pages and reports which ACF fields are empty or missing. It groups missing fields by category (Links, Other) and shows a badge count per category so you can quickly see which pages need attention. Results are paginated and filterable.</p>
                        <p>The <strong>Edit pencil</strong> next to each result deep-links directly to the missing field&rsquo;s ACF metabox on the page edit screen &mdash; the field is scrolled into view and highlighted automatically via the <code>hozio_highlight</code> URL parameter.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to use it</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Sitemap Settings</strong> and look for the <strong>Town Content Checker</strong> tab (or access it from the Sitemap Layout Editor warning banner).</li>
                            <li>The checker loads and scans all town pages automatically.</li>
                            <li>Use the <strong>category filter pills</strong> to show only <em>Links</em> issues or <em>Other</em> field issues. Both are off by default &mdash; toggle them to see those categories.</li>
                            <li>Click the <strong>pencil icon</strong> next to any page to jump directly to the WordPress editor with the missing field highlighted.</li>
                            <li>Click <strong>Reset Filters</strong> to clear all active filters.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>The badge on each page shows a <strong>breakdown by category</strong> (e.g., "3 Links, 2 Other") rather than a single total count.</li>
                            <li>Section labels in the field list are <strong>clickable links</strong> that jump directly to the corresponding ACF metabox anchor on the edit page.</li>
                            <li>The scan uses <code>update_post_meta_cache</code> pre-loading for performance &mdash; safe to run on large sites.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: duplicate-page-detector -->
                <div data-content="duplicate-page-detector">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>When WordPress cannot use the slug you want (because another page with that slug already exists), it automatically appends <code>-2</code>, <code>-3</code>, etc. to the new page&rsquo;s slug. These duplicates often go unnoticed and can cause SEO issues, broken loops, or sitemap pollution.</p>
                        <p>The Duplicate Page Detector scans your entire site for pages with <code>-2</code>, <code>-3</code> (and beyond) suffixes and presents them in a table with one-click actions to fix or remove them.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to use it</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Sitemap Settings &rarr; Duplicate Detector</strong> tab.</li>
                            <li>The scan runs automatically on page load and populates the results table.</li>
                            <li>For each duplicate, you have three actions:
                                <ul>
                                    <li><strong>Fix</strong> &mdash; renames the slug to remove the <code>-2</code>/<code>-3</code> suffix (only safe if the clean slug is now available).</li>
                                    <li><strong>Draft</strong> &mdash; moves the page to Draft status so it&rsquo;s no longer publicly accessible.</li>
                                    <li><strong>Trash</strong> &mdash; moves the page to the Trash.</li>
                                </ul>
                            </li>
                            <li>Use <strong>bulk select</strong> to select multiple pages at once. The <strong>smart pattern bar</strong> lets you select all pages matching a <code>-2</code> or <code>-3</code> pattern in one click.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>The scan uses a <strong>12-hour transient cache</strong> &mdash; results are fast even on large sites. The cache is cleared when you take any action.</li>
                            <li>Each result shows the <strong>full URL</strong> under the page title so you can identify exactly which page is affected.</li>
                            <li>An <strong>admin-wide warning banner</strong> appears at the top of all admin pages when published duplicate pages are detected, so you&rsquo;re alerted even if you&rsquo;re not on this screen.</li>
                            <li>The Promote modal shows all URLs being removed before you confirm a bulk action.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: image-video-sitemap -->
                <div data-content="image-video-sitemap">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Generates an XML sitemap extension that lists all images and videos on your site for Google Search Console. Having an image/video sitemap helps Google discover and index media it might otherwise miss, especially when images are loaded via JavaScript (Elementor) or stored in ACF fields.</p>
                        <p>Hozio detects media through four methods: <strong>post_parent database join</strong> (attached media), <strong>featured image meta</strong>, <strong>Elementor JSON parsing</strong> (<code>_elementor_data</code>), and <strong>Gutenberg block content</strong>.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Sitemap Settings &rarr; Image &amp; Video Sitemap</strong> tab.</li>
                            <li>Toggle <strong>Enable Image Sitemap</strong> and/or <strong>Enable Video Sitemap</strong>.</li>
                            <li>Click <strong>Save</strong>. The sitemap URLs are displayed below the toggles.</li>
                            <li>Submit the sitemap URLs to <strong>Google Search Console</strong> as you would any XML sitemap.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>If you had the standalone <strong>Image Video XML Sitemap</strong> plugin installed, Hozio will automatically deactivate it to prevent duplicate sitemap entries.</li>
                            <li>Image URLs are fetched directly from the database to bypass filter chains (e.g., CDN URL rewrites) that could produce incorrect URLs.</li>
                            <li>The sitemap only includes <strong>published pages</strong> &mdash; drafts, private, and trashed pages are excluded.</li>
                            <li>Sitemap output is cached. If you add new images/videos, allow up to 24 hours for them to appear, or clear the WordPress object cache.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: faq-schema -->
                <div data-content="faq-schema">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Automatically outputs <strong>FAQPage JSON-LD structured data</strong> to the <code>&lt;head&gt;</code> of your pages. This tells Google your page contains FAQ content, which can trigger rich results (expandable Q&amp;A sections) in search results.</p>
                        <p>FAQ data is pulled from ACF field groups. Three groups are supported: <strong>General Questions</strong>, <strong>Service page FAQs</strong>, and <strong>Town/HOG page FAQs</strong>. Each group can be toggled independently.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to set it up</h3>
                        <ol>
                            <li>Go to <strong>Hozio Pro &rarr; Hozio Pro Settings &rarr; FAQ Schema</strong> section.</li>
                            <li>Toggle the <strong>Master Enable</strong> switch to turn FAQ schema on globally.</li>
                            <li>Enable or disable each ACF field group individually:
                                <ul>
                                    <li><strong>General Questions</strong> &mdash; applies to all pages with a General Questions ACF field group.</li>
                                    <li><strong>Service Pages</strong> &mdash; applies to pages identified as service pages.</li>
                                    <li><strong>Town / HOG Pages</strong> &mdash; applies to town-level location pages.</li>
                                </ul>
                            </li>
                            <li>Optionally, add page IDs to the <strong>Exclude List</strong> to suppress schema output on specific pages.</li>
                            <li>Click <strong>Save Settings</strong>.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>JSON-LD is output in the <code>&lt;head&gt;</code> tag &mdash; Google&rsquo;s preferred method for structured data.</li>
                            <li>Pages with no FAQ ACF fields will not output any schema, even if the toggle is enabled.</li>
                            <li>After enabling, use <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener">Google&rsquo;s Rich Results Test</a> to verify the schema is valid and eligible for rich results.</li>
                            <li>Requires the <strong>ACF</strong> plugin to be active and the corresponding field groups to be configured on your pages.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: acf-generic-shortcodes -->
                <div data-content="acf-generic-shortcodes">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>These four universal shortcodes let you output <strong>any ACF field value</strong> anywhere WordPress renders shortcodes &mdash; Elementor HTML widgets, Gutenberg Custom HTML blocks, pages, and posts. They work on the current page by default but also support cross-page lookups by <code>post_id</code> or <code>slug</code>.</p>

                        <h4 style="margin-top:16px;"><code>[acf_text field="field_name"]</code></h4>
                        <p>Outputs a text/textarea ACF field value. HTML is passed through <code>wp_kses_post()</code> (safe but not stripped). Returns empty if the field is empty or is an array type.</p>

                        <h4 style="margin-top:16px;"><code>[acf_img field="field_name" alt="optional"]</code></h4>
                        <p>Outputs a full <code>&lt;img&gt;</code> tag from an ACF image field. Handles both <em>Array</em> and <em>URL</em> return formats. The <code>alt</code> attribute is read from the image field automatically if not provided.</p>

                        <h4 style="margin-top:16px;"><code>[acf_img_url field="field_name"]</code></h4>
                        <p>Returns <strong>only the image URL string</strong> &mdash; no <code>&lt;img&gt;</code> tag. Use this inside inline CSS when you need the URL for a <code>background-image</code>:</p>
                        <pre style="background:#f3f4f6;padding:12px;border-radius:6px;font-size:13px;overflow-x:auto;margin:8px 0;">&lt;div style="background-image:url('[acf_img_url field=&quot;hero__banner_image&quot;]')"&gt;</pre>

                        <h4 style="margin-top:16px;"><code>[acf_raw field="field_name"]</code></h4>
                        <p>Outputs the raw, unescaped field value. Use for <strong>textarea fields that contain HTML</strong> such as iframe embeds, Google Maps embeds, or rich text you want to render as-is.</p>

                        <h4 style="margin-top:16px;">Cross-page lookup attributes</h4>
                        <p>All four shortcodes accept two optional attributes for pulling fields from <em>other</em> pages:</p>
                        <ul>
                            <li><code>post_id="42"</code> &mdash; pull from a specific page/post by ID.</li>
                            <li><code>slug="services"</code> &mdash; look up a published page by its slug, then read the field from that page. Useful for hub/carousel templates that need service data.</li>
                        </ul>
                        <pre style="background:#f3f4f6;padding:12px;border-radius:6px;font-size:13px;overflow-x:auto;margin:8px 0;">[acf_text field="hero__section_headline" slug="plumbing"]
[acf_img field="service_loop_item_background" post_id="88"]</pre>

                        <h4 style="margin-top:16px;"><code>[acf]</code> alias</h4>
                        <p><code>[acf field="field_name"]</code> is an alias for <code>[acf_text]</code>. It replaces the ACF Pro built-in <code>[acf]</code> shortcode which is disabled by default in ACF Pro.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>How to use</h3>
                        <ol>
                            <li>Place any shortcode in an <strong>Elementor HTML widget</strong>, a <strong>Gutenberg Custom HTML block</strong>, or any standard WordPress text area.</li>
                            <li>Set <code>field</code> to the ACF field name (the <em>Field Name</em> shown in ACF, not the label).</li>
                            <li>For images, use <code>[acf_img]</code> for a full <code>&lt;img&gt;</code> tag or <code>[acf_img_url]</code> if you only need the URL.</li>
                            <li>For fields containing raw HTML (iframes, embeds), use <code>[acf_raw]</code>.</li>
                            <li>To pull a field from a different page, add <code>post_id</code> or <code>slug</code>.</li>
                        </ol>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>All shortcodes <strong>silently return empty</strong> if ACF is not active or the field doesn&rsquo;t exist &mdash; no errors shown to visitors.</li>
                            <li>Works inside <strong>Elementor HTML widgets</strong> and <strong>Gutenberg Custom HTML blocks</strong> automatically &mdash; no extra configuration needed.</li>
                            <li>For debugging, place <code>[acf_debug]</code> on a page to see exactly which post ID ACF is resolving to. Remove it before going live.</li>
                            <li><code>[acf_raw]</code> outputs unescaped HTML &mdash; only use with fields you control. Never use with user-submitted content.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: acf-hog-shortcodes -->
                <div data-content="acf-hog-shortcodes">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Hozio Pro registers a <strong>named shortcode for every ACF field</strong> on Town and HOG (Home of the Good) pages. Instead of typing <code>[acf_text field="hog_hero_body"]</code>, you can just write <code>[hog_hero_body]</code>. This makes HTML widget templates cleaner and faster to build.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>Available shortcodes by section</h3>
                        <h4 style="margin-top:12px;">Hero</h4>
                        <ul>
                            <li><code>[hog_hero_seo_heading]</code></li>
                            <li><code>[hog_hero_section_headline]</code></li>
                            <li><code>[hog_hero_body]</code></li>
                            <li><code>[hog_hero_image]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                        </ul>
                        <h4 style="margin-top:12px;">Outcomes</h4>
                        <ul>
                            <li><code>[hog_outcomes_seo_heading]</code></li>
                            <li><code>[hog_outcomes_section_headline]</code></li>
                            <li><code>[hog_outcomes_body]</code></li>
                            <li><code>[hog_outcomes_image]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                        </ul>
                        <h4 style="margin-top:12px;">About Us</h4>
                        <ul>
                            <li><code>[hog_about_us_seo_heading]</code></li>
                            <li><code>[hog_about_us_section_headline]</code></li>
                            <li><code>[hog_about_us_body]</code></li>
                            <li><code>[hog_about_us_image]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                        </ul>
                        <h4 style="margin-top:12px;">How It Works</h4>
                        <ul>
                            <li><code>[hog_how_it_works_seo_heading]</code></li>
                            <li><code>[hog_how_it_works_section_headline]</code></li>
                            <li><code>[hog_how_it_works_body]</code></li>
                            <li><code>[hog_how_it_works_image]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                        </ul>
                        <h4 style="margin-top:12px;">Service-Related Information</h4>
                        <ul>
                            <li><code>[hog_service-related_information_seo_heading]</code></li>
                            <li><code>[hog_service-related_information_section_headline]</code></li>
                            <li><code>[hog_service-related_information_body]</code></li>
                            <li><code>[hog_service_related_info_image]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                        </ul>
                        <h4 style="margin-top:12px;">FAQ (6 Q&amp;A pairs)</h4>
                        <ul>
                            <li><code>[new_hog_faq_question_1]</code> through <code>[new_hog_faq_question_6]</code></li>
                            <li><code>[new_hog_faq_answer_1]</code> through <code>[new_hog_faq_answer_6]</code></li>
                        </ul>
                        <h4 style="margin-top:12px;">Links</h4>
                        <ul>
                            <li><code>[hog_google_maps_link]</code></li>
                            <li><code>[hog_usps_link]</code></li>
                            <li><code>[hog_pharmacy_link]</code></li>
                            <li><code>[hog_weather_link]</code></li>
                            <li><code>[hog_county_and_state_wiki_link]</code></li>
                        </ul>
                        <h4 style="margin-top:12px;">Misc</h4>
                        <ul>
                            <li><code>[hog_location]</code> &mdash; maps to the <code>location</code> ACF field</li>
                            <li><code>[hog_gmb_map]</code> &mdash; raw HTML output (iframe embed)</li>
                        </ul>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li>Image shortcodes output a full <code>&lt;img&gt;</code> tag with <code>loading="lazy"</code> automatically.</li>
                            <li><code>[hog_gmb_map]</code> uses raw output &mdash; make sure the ACF field contains a valid iframe embed.</li>
                            <li>The <code>hog_hero_section_headline</code> ACF field has a trailing colon in its registered name &mdash; the shortcode handles this automatically.</li>
                        </ul>
                    </div>
                </div>

                <!-- Section: acf-service-shortcodes -->
                <div data-content="acf-service-shortcodes">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>Hozio Pro registers a <strong>named shortcode for every ACF field</strong> on Service pages. Use them directly in Elementor HTML widgets or Gutenberg Custom HTML blocks on service page templates without needing to write <code>[acf_text field="..."]</code> each time.</p>
                    </div>
                    <div class="hozio-support-steps">
                        <h3>Available shortcodes by section</h3>
                        <h4 style="margin-top:12px;">Hero</h4>
                        <ul>
                            <li><code>[hero__seo_heading]</code></li>
                            <li><code>[hero__section_headline]</code></li>
                            <li><code>[hero__body]</code></li>
                            <li><code>[hero__banner_image]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                        </ul>
                        <h4 style="margin-top:12px;">Trust Symbols</h4>
                        <ul>
                            <li><code>[trust_symbols__section_headline]</code></li>
                            <li><code>[trust_symbols__title_1]</code> through <code>[trust_symbols__title_4]</code></li>
                            <li><code>[trust_symbols__description_1]</code> through <code>[trust_symbols__description_4]</code></li>
                        </ul>
                        <h4 style="margin-top:12px;">Service Intro</h4>
                        <ul>
                            <li><code>[service_intro__seo_heading]</code></li>
                            <li><code>[service_intro__section_headline]</code></li>
                            <li><code>[service_intro__body]</code> &mdash; raw HTML output</li>
                            <li><code>[service_intro__image]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                        </ul>
                        <h4 style="margin-top:12px;">Benefits</h4>
                        <ul>
                            <li><code>[benefits__seo_heading]</code></li>
                            <li><code>[benefits__section_headline]</code></li>
                            <li><code>[benefits__subheadline]</code></li>
                            <li><code>[benefits__bullet_text_1]</code> through <code>[benefits__bullet_text_6]</code></li>
                        </ul>
                        <h4 style="margin-top:12px;">Service-Related Information (2 sections)</h4>
                        <ul>
                            <li><code>[service-related_information_1__seo_heading]</code></li>
                            <li><code>[service-related_information_1__section_headline]</code></li>
                            <li><code>[service-related_information_1__body]</code> &mdash; raw HTML output</li>
                            <li><code>[service-related_information_1__image]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                            <li><code>[service-related_information_2__seo_heading]</code></li>
                            <li><code>[service-related_information_2__section_headline]</code></li>
                            <li><code>[service-related_information_2__body]</code> &mdash; raw HTML output</li>
                            <li><code>[service-related_information_2__image]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                        </ul>
                        <h4 style="margin-top:12px;">How It Works</h4>
                        <ul>
                            <li><code>[how_it_works__seo_heading]</code></li>
                            <li><code>[how_it_works__section_headline]</code></li>
                            <li><code>[how_it_works__icon_title_1]</code> through <code>[how_it_works__icon_title_3]</code></li>
                            <li><code>[how_it_works__icon_description_1]</code> through <code>[how_it_works__icon_description_3]</code></li>
                        </ul>
                        <h4 style="margin-top:12px;">FAQ (6 Q&amp;A pairs)</h4>
                        <ul>
                            <li><code>[faq_question_1]</code> through <code>[faq_question_6]</code></li>
                            <li><code>[faq_answer_1]</code> through <code>[faq_answer_6]</code></li>
                        </ul>
                        <h4 style="margin-top:12px;">Misc</h4>
                        <ul>
                            <li><code>[hog_areas]</code> &mdash; service areas text</li>
                            <li><code>[service_loop_item_description]</code></li>
                            <li><code>[faq_service_page_section]</code></li>
                            <li><code>[service_loop_item_background]</code> &mdash; outputs <code>&lt;img&gt;</code> tag</li>
                        </ul>
                    </div>
                    <div class="hozio-support-notes">
                        <h3>Important notes</h3>
                        <ul>
                            <li><code>body</code> and <code>_body</code> fields (service intro, service-related info) use <strong>raw output</strong> &mdash; they output HTML as-is for rich text and iframe embeds.</li>
                            <li>Image shortcodes output a full <code>&lt;img&gt;</code> tag with <code>loading="lazy"</code>.</li>
                            <li>If you need the image as a URL only (for CSS backgrounds), use the generic <code>[acf_img_url field="..."]</code> shortcode instead.</li>
                            <li>The AJAX endpoint <code>action=cb_get_acf_images</code> is available for JavaScript-driven carousels that need to batch-fetch ACF image URLs for multiple page IDs at once.</li>
                        </ul>
                    </div>
                </div>

                <div data-content="service-towns">
                    <div class="hozio-support-what">
                        <h3>What it does</h3>
                        <p>The <code>[hozio_service_towns]</code> shortcode renders a county accordion on any service page. Each county appears as a collapsible section containing the town/city links that belong to it. A live search bar filters across all counties simultaneously. If no counties are configured for a page, a single "All Cities" accordion with every service town shows instead.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Shortcode</h3>
                        <p>Place this shortcode in an HTML widget on any service page:</p>
                        <pre><code>[hozio_service_towns]</code></pre>
                        <p>No attributes required — it reads the current page automatically.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>How town pages get matched</h3>
                        <p>The shortcode finds towns using the <strong>Page Taxonomies</strong> (<code>parent_pages</code>) taxonomy. A town page must have <em>both</em> of the following terms assigned to appear under the correct county:</p>
                        <ul>
                            <li><strong>Service term</strong> — the slug of the service page (e.g. <code>at-home-pet-euthanasia</code>). This is what connects the town to this specific service.</li>
                            <li><strong>County term</strong> — the county taxonomy term (e.g. <code>harris-county</code>). This is what places the town inside a county accordion.</li>
                        </ul>
                        <p>If a town page only has the service term but no county term, it will appear in the flat "All Cities" fallback accordion only.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Step 1 — Create county terms in Page Taxonomies</h3>
                        <ol>
                            <li>Go to <strong>Pages → Page Taxonomies</strong> in the WordPress admin sidebar.</li>
                            <li>Add a term for each county (e.g. "Harris County", "Fort Bend County").</li>
                            <li>The term slug (auto-generated from the name) is what the shortcode uses internally — keep slugs clean, e.g. <code>harris-county</code>.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Step 2 — Assign county terms to town pages</h3>
                        <ol>
                            <li>Edit each town page.</li>
                            <li>In the <strong>Page Taxonomies</strong> metabox (right sidebar), check the county term that town belongs to.</li>
                            <li>Make sure the service term is also checked for the service this town should appear under.</li>
                            <li>Save the page.</li>
                        </ol>
                        <p>You can do this quickly via <strong>Pages → Quick Edit</strong> for each town page — the Page Taxonomies checkboxes are visible there without opening the full editor.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Step 3 — Create the ACF "County Groups" field on service pages</h3>
                        <p>Each service page needs an ACF field that lets you choose which counties to show as accordion sections. Without this field, the shortcode falls back to "All Cities" mode.</p>
                        <ol>
                            <li>Go to <strong>Custom Fields → Field Groups</strong> and edit the field group assigned to your service pages.</li>
                            <li>Add a new field with these exact settings:
                                <ul>
                                    <li><strong>Field Label:</strong> County Groups</li>
                                    <li><strong>Field Name:</strong> <code>county_groups</code> (must match exactly — lowercase, underscore)</li>
                                    <li><strong>Field Type:</strong> Taxonomy</li>
                                    <li><strong>Taxonomy:</strong> Page Taxonomies</li>
                                    <li><strong>Appearance:</strong> Checkbox</li>
                                    <li><strong>Return Value:</strong> Term Object</li>
                                    <li><strong>Save Terms:</strong> <strong>OFF</strong> — this is critical. If left ON, ACF will write the selected counties back to the service page's own taxonomy, which breaks the town matching query.</li>
                                </ul>
                            </li>
                            <li>Save the field group.</li>
                        </ol>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Step 4 — Select counties on each service page</h3>
                        <ol>
                            <li>Edit a service page.</li>
                            <li>Find the <strong>County Groups</strong> field (added in Step 3).</li>
                            <li>Check each county that should appear as an accordion section on that service page.</li>
                            <li>Click <strong>Update</strong> to save.</li>
                        </ol>
                        <p>Only counties that have at least one matching town page will render — empty counties are silently skipped.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Town display text</h3>
                        <p>Each town link displays the value of the <code>location</code> ACF field on the town page (the same field used by <code>[hog_location]</code>). If that field is empty, the page title is used as a fallback.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Fallback — no counties configured</h3>
                        <p>If the <code>county_groups</code> ACF field is empty or the field does not exist on a service page, the shortcode automatically shows a single accordion labelled "All Cities" containing every published town page that has the service term assigned — no county grouping. This ensures the shortcode always outputs something useful even on pages not yet configured with county groups.</p>
                    </div>

                    <div class="hozio-support-steps">
                        <h3>Controlling which service pages show the city list</h3>
                        <p>Use the existing <strong>HOG Areas</strong> ACF field (<code>hog_areas</code>) to control whether the city accordion appears on each service page. This field is already wired into the Elementor template — whatever shortcode you place in it gets rendered automatically.</p>
                        <ol>
                            <li>Edit the service page that needs a city list.</li>
                            <li>Find the <strong>HOG Areas</strong> field and type <code>[hozio_service_towns]</code> into it.</li>
                            <li>Save the page — the county accordion will now appear in that section.</li>
                        </ol>
                        <p>On service pages that don't need a city list, leave the <strong>HOG Areas</strong> field empty and nothing will render. No template changes required.</p>
                    </div>
                </div>

            </div><!-- #hozio-section-data -->

            <!-- No Results Message -->
            <div class="hozio-support-no-results" style="display:none;">
                <span class="dashicons dashicons-search"></span>
                <p>No matching documentation found. Try a different search term.</p>
            </div>

        </div><!-- .hozio-content -->
    </div><!-- .hozio-settings-wrapper -->

    <style>
        /* ============================== */
        /* Search Bar                      */
        /* ============================== */
        .hozio-support-search-wrapper {
            position: sticky;
            top: 32px;
            z-index: 100;
            background: var(--hozio-bg, #f9fafb);
            padding: 20px 0 10px;
            margin-bottom: 0;
        }

        .hozio-support-search-inner { position: relative; }

        .hozio-support-search-icon {
            position: absolute; left: 18px; top: 50%; transform: translateY(-50%);
            color: #00A0E3; font-size: 20px !important; width: 20px !important; height: 20px !important;
            pointer-events: none; z-index: 2;
        }

        .hozio-settings-wrapper .hozio-support-search-input,
        .hozio-settings-wrapper .hozio-support-search-input[type="text"] {
            width: 100% !important; max-width: 100% !important;
            padding: 14px 44px 14px 50px !important;
            border: 2px solid #e5e7eb !important; border-radius: 12px !important;
            font-size: 15px !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            background: #ffffff !important; color: #1f2937 !important;
            transition: all 0.2s ease !important;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1) !important;
            box-sizing: border-box !important; height: auto !important;
            line-height: 1.4 !important; margin: 0 !important;
        }

        .hozio-settings-wrapper .hozio-support-search-input:focus {
            outline: none !important; border-color: #00A0E3 !important;
            box-shadow: 0 0 0 3px rgba(0,160,227,0.15) !important;
        }

        .hozio-settings-wrapper .hozio-support-search-input::placeholder {
            color: #9ca3af !important; opacity: 1 !important;
        }

        .hozio-support-search-clear {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            font-size: 22px; color: #999; cursor: pointer; padding: 4px; z-index: 2;
        }
        .hozio-support-search-clear:hover { color: #333; }

        /* ============================== */
        /* Category Tabs                   */
        /* ============================== */
        .hozio-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px 0 20px;
        }

        .hozio-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            color: #6b7280;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .hozio-tab .dashicons {
            font-size: 16px; width: 16px; height: 16px;
        }

        .hozio-tab:hover {
            border-color: #00A0E3;
            color: #00A0E3;
            background: rgba(0,160,227,0.04);
        }

        .hozio-tab.is-active {
            border-color: #00A0E3;
            background: #00A0E3;
            color: #fff;
        }

        .hozio-tab.is-active .dashicons { color: #fff; }

        /* ============================== */
        /* Card Grid                       */
        /* ============================== */
        .hozio-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .hozio-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .hozio-card:hover {
            border-color: #00A0E3;
            box-shadow: 0 4px 12px rgba(0,160,227,0.12);
            transform: translateY(-2px);
        }

        .hozio-card.is-active {
            border-color: #00A0E3;
            background: #f0f9ff;
            box-shadow: 0 0 0 2px rgba(0,160,227,0.2);
        }

        .hozio-card.is-hidden { display: none; }

        .hozio-card-icon {
            font-size: 28px !important;
            width: 28px !important;
            height: 28px !important;
            color: #00A0E3;
            margin-bottom: 10px;
        }

        /* Alternate icon colors by category */
        .hozio-card[data-category="pages"] .hozio-card-icon { color: #8DC63F; }
        .hozio-card[data-category="elementor"] .hozio-card-icon { color: #F7941D; }
        .hozio-card[data-category="sitemap"] .hozio-card-icon { color: #00A0E3; }
        .hozio-card[data-category="settings"] .hozio-card-icon { color: #6D6E71; }
        .hozio-card[data-category="acf-shortcodes"] .hozio-card-icon { color: #e11d48; }

        .hozio-card-title {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
        }

        .hozio-card-desc {
            margin: 0;
            font-size: 12.5px;
            color: #9ca3af;
            line-height: 1.4;
        }

        /* ============================== */
        /* Detail Panel                    */
        /* ============================== */
        .hozio-detail-panel {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-top: 3px solid #00A0E3;
            border-radius: 0 0 12px 12px;
            margin-top: 20px;
            padding: 28px 32px 32px;
            position: relative;
            animation: hozio-slide-down 0.25s ease;
        }

        @keyframes hozio-slide-down {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hozio-detail-close {
            position: absolute;
            top: 12px;
            right: 16px;
            background: none;
            border: none;
            font-size: 24px;
            color: #9ca3af;
            cursor: pointer;
            padding: 4px 8px;
            line-height: 1;
            border-radius: 4px;
        }

        .hozio-detail-close:hover {
            color: #1f2937;
            background: #f3f4f6;
        }

        /* Detail panel content styling */
        .hozio-detail-body h3 {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6D6E71;
            margin: 0 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
        }

        .hozio-detail-body .hozio-support-what,
        .hozio-detail-body .hozio-support-steps,
        .hozio-detail-body .hozio-support-notes {
            margin-bottom: 20px;
        }

        .hozio-detail-body p {
            font-size: 14px;
            line-height: 1.7;
            color: #1f2937;
            margin: 0 0 8px;
        }

        .hozio-detail-body ol {
            counter-reset: step-counter;
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .hozio-detail-body ol > li {
            counter-increment: step-counter;
            position: relative;
            padding-left: 40px;
            margin-bottom: 12px;
            font-size: 14px;
            line-height: 1.7;
            color: #1f2937;
        }

        .hozio-detail-body ol > li::before {
            content: counter(step-counter);
            position: absolute; left: 0; top: 1px;
            width: 26px; height: 26px;
            background: linear-gradient(135deg, #00A0E3, #8DC63F);
            color: white; border-radius: 50%;
            font-size: 13px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }

        .hozio-detail-body ol > li ul { margin-top: 8px; margin-bottom: 0; }

        .hozio-detail-body ul { padding-left: 20px; margin: 0; }

        .hozio-detail-body ul li {
            font-size: 14px; line-height: 1.7; color: #1f2937; margin-bottom: 6px;
        }

        .hozio-detail-body code {
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 13px;
            color: #e11d48;
            font-family: 'Consolas', 'Monaco', monospace;
        }

        /* ============================== */
        /* No Results                      */
        /* ============================== */
        .hozio-support-no-results {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
            font-size: 16px;
        }

        .hozio-support-no-results .dashicons {
            font-size: 48px; width: 48px; height: 48px;
            color: #ddd; display: block; margin: 0 auto 12px;
        }

        /* ============================== */
        /* Responsive                      */
        /* ============================== */
        @media (max-width: 782px) {
            .hozio-support-search-wrapper { top: 46px; }
            .hozio-tabs { gap: 6px; }
            .hozio-tab { padding: 6px 12px; font-size: 12px; }
            .hozio-card-grid { grid-template-columns: 1fr; }
            .hozio-detail-panel { padding: 20px 18px 24px; }
        }

        @media (min-width: 783px) and (max-width: 1100px) {
            .hozio-card-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>

    <script>
    (function() {
        'use strict';

        var tabs = document.querySelectorAll('.hozio-tab');
        var cards = document.querySelectorAll('.hozio-card');
        var searchInput = document.getElementById('hozio-support-search');
        var clearBtn = document.getElementById('hozio-support-search-clear');
        var tabsContainer = document.getElementById('hozio-tabs');
        var detailPanel = document.getElementById('hozio-detail-panel');
        var detailBody = document.getElementById('hozio-detail-body');
        var detailClose = document.getElementById('hozio-detail-close');
        var noResults = document.querySelector('.hozio-support-no-results');
        var activeTab = 'getting-started';
        var activeCard = null;
        var debounceTimer;

        // --- Tab switching ---
        function switchTab(tabName) {
            activeTab = tabName;
            tabs.forEach(function(t) {
                t.classList.toggle('is-active', t.getAttribute('data-tab') === tabName);
            });
            filterCards();
            closeDetail();
        }

        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                switchTab(this.getAttribute('data-tab'));
            });
        });

        // --- Filter cards by active tab ---
        function filterCards() {
            cards.forEach(function(card) {
                var show = card.getAttribute('data-category') === activeTab;
                card.classList.toggle('is-hidden', !show);
            });
        }

        // --- Card click → open detail panel ---
        function openDetail(sectionId) {
            var contentEl = document.querySelector('#hozio-section-data [data-content="' + sectionId + '"]');
            if (!contentEl) return;

            // Toggle same card
            if (activeCard === sectionId) {
                closeDetail();
                return;
            }

            // Deactivate previous card
            cards.forEach(function(c) { c.classList.remove('is-active'); });

            // Activate clicked card
            var clickedCard = document.querySelector('.hozio-card[data-section="' + sectionId + '"]');
            if (clickedCard) clickedCard.classList.add('is-active');

            activeCard = sectionId;
            detailBody.innerHTML = contentEl.innerHTML;
            detailPanel.style.display = 'block';

            // Scroll panel into view
            setTimeout(function() {
                detailPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 50);
        }

        function closeDetail() {
            detailPanel.style.display = 'none';
            detailBody.innerHTML = '';
            activeCard = null;
            cards.forEach(function(c) { c.classList.remove('is-active'); });
        }

        cards.forEach(function(card) {
            card.addEventListener('click', function() {
                openDetail(this.getAttribute('data-section'));
            });
        });

        if (detailClose) {
            detailClose.addEventListener('click', closeDetail);
        }

        // --- Search ---
        function doSearch(query) {
            if (!query) {
                // Clear search: show tabs, restore tab filter
                tabsContainer.style.display = '';
                filterCards();
                closeDetail();
                if (noResults) noResults.style.display = 'none';
                return;
            }

            // Hide tabs during search
            tabsContainer.style.display = 'none';
            var visibleCount = 0;

            cards.forEach(function(card) {
                var title = card.querySelector('.hozio-card-title').textContent.toLowerCase();
                var desc = card.getAttribute('data-description').toLowerCase();

                // Also search section content
                var sectionId = card.getAttribute('data-section');
                var contentEl = document.querySelector('#hozio-section-data [data-content="' + sectionId + '"]');
                var contentText = contentEl ? contentEl.textContent.toLowerCase() : '';

                var matches = title.indexOf(query) !== -1 || desc.indexOf(query) !== -1 || contentText.indexOf(query) !== -1;

                card.classList.toggle('is-hidden', !matches);
                if (matches) visibleCount++;
            });

            if (noResults) {
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }

            // Close detail if the active card was hidden
            if (activeCard) {
                var activeEl = document.querySelector('.hozio-card[data-section="' + activeCard + '"]');
                if (activeEl && activeEl.classList.contains('is-hidden')) {
                    closeDetail();
                }
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                var val = searchInput.value.trim().toLowerCase();
                debounceTimer = setTimeout(function() { doSearch(val); }, 200);
                if (clearBtn) clearBtn.style.display = searchInput.value ? 'block' : 'none';
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                doSearch('');
                searchInput.focus();
            });
        }

        // --- Initial state ---
        filterCards();
    })();
    </script>

<?php
}
