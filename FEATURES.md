# Hozio Pro — Complete Feature Inventory & Architecture Guide

A complete, organized inventory of every feature in the Hozio Pro plugin (v4.12.12), with an explanation of how the modules connect and how the whole system works. Covers all 30 PHP modules (~37,000 lines) plus the JS/CSS assets — every feature, shortcode, hook, REST endpoint, cron job, admin page, and `wp_option`.

Organized as:
1. What Hozio Pro is
2. How it all fits together (architecture + the data backbone)
3. The 10 feature groups, module by module, with every feature
4. Cross-cutting reference tables (shortcodes, REST endpoints, cron, admin pages, the option backbone, shared helpers)
5. The 9 end-to-end data flows
6. Notable gotchas & security notes

---

## 1. What Hozio Pro Is

Hozio Pro is a single monolithic WordPress plugin that acts as an **all-in-one SEO / operations / site-management toolkit for Elementor + ACF local-service-business websites** (plumbers, roofers, HVAC, etc.). It centralizes company contact data into editable "dynamic tags," builds service/town page hierarchies on two custom taxonomies, generates HTML and image/video XML sitemaps, captures leads from many form platforms, injects structured data, self-updates from GitHub, and is remotely manageable from a central **Hozio Hub** control plane at hozio.com. Everything is license-gated.

---

## 2. How It All Fits Together

### The orchestrator
`hozio-dynamic-tags.php` (main file) is the bootstrap. It defines three constants (`HOZIO_VERSION` `4.12.12`, `HOZIO_PLUGIN_FILE`, `HOZIO_HUB_URL`), then `require_once`-loads every module **in dependency order**: logger first (so logging works without `WP_DEBUG`), then settings, updater, rollback (after updater so `hozio_get_plugin_version()` exists), then all feature modules, ending with three Hub files loaded defensively via `file_exists()` (command-executor must load before client and direct-endpoint). It also owns the top-level **Hozio Pro** admin menu + 7 submenus, the plugin lifecycle hooks, Hub heartbeat cron scheduling, a version-gated rewrite-rules flush, the universal `[hozio]` shortcode, and a full-page HTML "output fixer."

### The data backbone
The whole plugin revolves around the `wp_options` table. **`admin-settings.php`** (the "Dynamic Tags Settings" page) is the single source of truth where the owner enters all contact info, address, hours, social URLs, branding colors, and styling. It writes ~45 `hozio_*` option keys. Every editable value is **base64-encoded in the DB** (`b64:` / `b64arr:` prefixes) so site-migration search/replace tools can't mangle URLs/phones/addresses — values decode transparently on read.

Two **pure consumers** read those same options at render time without ever writing them:
- **`dynamic-tags.php`** registers ~32 Elementor dynamic tags + a user-extensible custom-tag system.
- The **`[hozio]` shortcode** (in the main file) resolves the identical option set for HTML widgets.

Both output paths share the `hozio_sms_calltrk_noswap` option so CallRail number-swap suppression applies no matter how an SMS link is rendered.

### The control center
**`plugin-settings.php`** ("Hozio Pro Settings") is the operator console and the registry/owner of all feature-flag and licensing options. It defines three load-bearing helpers consumed elsewhere: `hozio_get_plugin_version()`, `hozio_dom_parsing_enabled()`, and `hozio_service_menu_sync_enabled()`. Its toggles drive other modules (FAQ schema, canonical redirects, the lead webhook, DOM parsing, service-menu sync).

### The governance cluster (updates / license / rollback / Hub)
`plugin-updater.php` polls GitHub Releases and injects updates into WordPress's native transient, **gated behind a license**. The license authority is the **Hozio Hub** when connected (status `active`), falling back to a legacy MD5 key check. `plugin-rollback.php` installs any release (up/down) and pauses auto-updates after a downgrade. The three `hub-*.php` files register the site with the Hub, send hourly heartbeats, and accept remote commands (both pull-via-heartbeat and push-via-REST), funneling all of it through a self-protecting command executor.

### The content/relational engine
Two custom taxonomies (`parent_pages`, `town_taxonomies`) on the `page` post type are the relational fabric. Query modules rewrite Elementor loops to list related child/sibling/county pages; the service-towns shortcode renders county accordions; the service-menu handler auto-syncs service pages into nav menus; the sitemap modules render the page tree.

---

## 3. Feature Inventory by Group

### GROUP 1 — Bootstrap & Orchestration
**Files:** `hozio-dynamic-tags.php`, `hozio-logger.php`

**`hozio-dynamic-tags.php` (main orchestrator):**
- Module loader / bootstrap (constants + ordered `require_once` of ~25 includes; defensive `file_exists` loading of the 3 Hub files).
- Plugin lifecycle: activation handler (audit-logs activator, schedules Hub heartbeat cron) and deactivation handler (audit-logs, clears heartbeat crons).
- Hub heartbeat cron self-heal on `init`; version-gated `flush_rewrite_rules` once per upgrade (`hozio_last_flushed_version`).
- Leads-page email integration: on sites with a `leads-page` page, forces HTML email and rewrites the `[site_url]/leads-page` placeholder link in outgoing mail.
- **Universal `[hozio]` shortcode** — resolves any dynamic-tag value (phones, SMS, email, address parts, social URLs, business hours, years-of-experience, sitemap URL, icon-box anchors, custom tags) from options; `format="url"` yields `tel:`/`sms:`/`mailto:`; HTML tags pass through `wp_kses`; blocks `<script` in custom values.
- `[hozio_current_year]`, `[final_cta field= page_id=]` (cross-page ACF), `[gmb_map]` (raw ACF map embed + whitelists `<iframe>`).
- Business-hours engine: default classic schema, 24h→12h formatter, classic formatter, and `hozio_get_business_hours_output()` resolver (HTML vs Classic mode) — the canonical hours renderer shared by the shortcode and the Business Hours dynamic tag.
- **Universal HTML output fixer** (full-page `template_redirect` buffer, priority 0) applying six fixes for W3C validation + CallRail: (A) dedupe `id="cta-text-color"`, (B) wrap inline `<script>` in CDATA (excludes JSON-LD), (C) normalize Elementor nav-menu `<ul>/<li>`, (D) clean lightbox attribute entities, (E) strip empty `<link href>`, (F) stamp `data-calltrk-noswap` on `sms:` anchors.
- `the_content` filters: escape stray angle brackets; **DOM-parse hide-empty** (`hide-if-empty-acf` icon lists + `hide-if-no-wiki` containers, gated by `hozio_dom_parsing_enabled()`).
- Strip `<u>` tags from Elementor icon-list widgets; inject nav/CTA inline styles + CTA dedupe JS (`hozio_nav_text_color`); swap the plugin-row icon to the burst SVG; hide third-party admin notices on Hozio screens; add custom tags via `admin_post` handlers (with object-cache busting).
- Elementor `services_children` custom query (child pages of `services`).
- **ACF→REST**: forces all ACF field groups `show_in_rest`; registers `GET wp/v2/acf-fields` and `GET wp/v1/acf-fields/{key}`; forces `parent_pages`/`town_taxonomies` into REST; registers Yoast `_yoast_wpseo_title`/`_metadesc` post-meta for REST.
- HTML Sitemap page-template registration + `<head>` noindex/meta injection.
- Add/Remove custom tag form handlers (`admin_post_hozio_add_tag` / `_remove_tag`).

**`hozio-logger.php` (shared logging API):**
- `hozio_debug_enabled()` gate (`HOZIO_DEBUG` constant overrides DB option).
- `hozio_log()` file debug logger → `wp-content/hozio-debug.log` (no-op unless debug on).
- `hozio_console_log()` browser-console logger (footer `<script>`, debug-gated).
- `hozio_clear_log()`, `hozio_get_log_path()`, `hozio_get_log_size()`.
- **`hozio_audit_log()`** — always-on, self-rotating (500 KB cap) audit log → `wp-content/hozio-audit.log`, used for lifecycle/security/Hub/update events.

### GROUP 2 — Settings & Data Backbone
**Files:** `admin-settings.php`, `plugin-settings.php`, `add-remove-tags.php`, `query-post-types.php`, `taxonomy-archive-settings.php`

**`admin-settings.php` (Dynamic Tags Settings — the contact-info source of truth):**
- **Base64 storage protection** (encode on `pre_update_option_*`, decode on `option_*`/`default_option_*`) for all string + array options, plus runtime registration for each custom tag; one-time encode migration flag `_hozio_settings_encoded_v2`.
- Field renderer with per-type controls (icon-prefixed text, number+years badge, color picker, HTML textarea) + copy-shortcode chips.
- **Live address builder** (auto-composes Company Address from Street/Town/State/ZIP), **custom searchable US-state combobox** (abbr/full toggle), and **Photon/OpenStreetMap street autocomplete** (keyless, US-only).
- **Business Hours editor**: HTML vs Classic mode toggle, 24/7 master switch, per-day Open/Closed pills + 15-min time selects, "Apply Monday to Tue–Fri."
- Live years-of-experience calc; client + server email-list validation (invalid emails not saved, error banner via transient).
- WP color pickers with live swatches: Nav Text Color, sitemap link/hover colors, **12 Service Towns colors** (with per-field + global reset) and county display-order select.
- CallRail SMS no-swap toggle; custom-tags section render+save (raw `<script>` allowed only for `manage_options`); object-cache busting; inline backup CSS.

**`plugin-settings.php` (Hozio Pro Settings — operator control center):**
- Registers/saves 12+ options via Settings API; nonce + capability-checked save.
- **License & Updates** section (Hub-managed vs manual key, auto-updates toggle with Version-Lock awareness, update-info panel + "Check for Updates" AJAX).
- **Debug & Logging** (toggle + Test/Clear/View log, surfaces `HOZIO_DEBUG` constant override).
- **Feature Toggles**: ACF Field Hiding (DOM parsing), Service Menu Auto-Sync, WP Canonical Redirects, Block Pages at Wrong URLs, Staging Index Guard, Dev URL Guard, Live robots.txt Template, Lead Webhook Endpoint.
- **FAQ Schema** master + 3 group toggles + 2 per-group exclude lists + ACF auto-detection.
- **Cache Management** (clear `hozio_*` transients except `hozio_hub_*`).
- **Version Control / Rollback** sidebar (installed version, Quick Revert, install-history timeline, "Browse All Releases" with client-side markdown changelog renderer, full-page rollback overlay, post-downgrade auto-update-pause modal).
- **System Info** card; **Shortcode Reference** tab (hard-coded Town/Service chips + dynamic "All ACF Groups" panel via `acf_get_field_groups`).
- Hub AJAX: connect / check-connection / disconnect. Defines `hozio_get_plugin_version()`, `hozio_dom_parsing_enabled()`, `hozio_service_menu_sync_enabled()`.

**`add-remove-tags.php`** — view template for creating/removing custom dynamic tags (title + Text/URL type), stored in `hozio_custom_tags`; per-tag remove forms with per-tag nonces; success/error notices; cache-bypass read.

**`query-post-types.php`** — "Query Post Types" page: toggle which public CPTs are allowed in dynamic queries → `hozio_selected_post_types` (excludes core + Elementor-library types). *(Note: nonce rendered but not verified — CSRF gap; data-producer whose consumer is the dynamic-query runtime.)*

**`taxonomy-archive-settings.php`** — "Archive Settings" page: two toggles controlling whether `parent_pages` / `town_taxonomies` archives are public (`hozio_parent_pages_archive_enabled` / `hozio_town_taxonomies_archive_enabled`), flushes rewrite rules on save; Quick Stats; per-term archive-URL accordion with copy buttons, status badges, assigned-pages lists, edit/view links.

### GROUP 3 — Dynamic Tags & ACF Shortcode Output
**Files:** `dynamic-tags.php`, `acf-shortcodes.php`, `acf-filters.php` (+ main-file shortcodes)

**`dynamic-tags.php`:**
- Single `elementor/dynamic_tags/register` (priority 50) entry point + three base classes (URL, Text, Icon Box).
- ~32 built-in tags: Company Phone 1/2, Google Ads Phone, SMS Phone, Company Email, GMB, Facebook, Instagram, Twitter, TikTok, LinkedIn, BBB, Yelp, YouTube, Angi, HomeAdvisor, Sitemap XML; phone/SMS/email "name" text tags; address part tags (street/town/state/zip); full Company Address (HTML); Business Hours (helper-backed); To-Email; Years of Experience (computed); Phone/SMS/Email **Icon-Box anchor** tags.
- **User custom tags** generated into a cached PHP class file (`uploads/hozio-cache/custom-tag-classes.php`) with version+content-hash invalidation, atomic write, web-access guard.
- **Services Children** fixed-value query-ID tag and **Composite tag** (concatenate two tags + before/between/after text).
- Admin-script passthrough (text option containing `<script` renders raw); CallRail no-swap on SMS outputs.

**`acf-shortcodes.php`** — ~120 **named ACF field shortcodes** for Town/HOG pages and Service pages (`hog_*`, `hero__*`, `trust_symbols__*`, `benefits__*`, `service-related_information_*`, `faq_question/answer_1..6`, `new_hog_faq_*`, `how_it_works__*`, plus image renderers and raw HTML embeds) + four generic replacements for ACF Pro's disabled `[acf]`: `[acf]`/`[acf_text]` (sanitized), `[acf_img]` (array or URL), `[acf_raw]` (unescaped). *(Escaping is intentionally per-category: text raw, images escaped, raw fields unescaped.)*

**`acf-filters.php`** — populates an ACF `allowed_post_types` select's choices with all public post types.

### GROUP 4 — Page Hierarchy, Taxonomies & Queries
**Files:** `custom-taxonomies.php`, `custom-parent-pages-queries.php`, `parent-page-filtering.php`, `service-menu-handler.php`, `service-towns-shortcode.php`, `loop-configurations.php`

**`custom-taxonomies.php`:**
- Registers `parent_pages` ("Page Taxonomies") and `town_taxonomies` ("Town Taxonomies") on `page`, both REST-exposed; public archive registration gated on the archive-enabled options.
- Optional disable of WP canonical redirects; **ghost-page guard** (301 or hard-404 for URL/permalink mismatch, with `?hozio_ghost_debug` headers); **`hozio_has_valid_parent_chain()`** helper; **Yoast XML sitemap ghost-page filter**; 404 for disabled taxonomy archives.
- Pages-list admin tooling: Parent/Town partial-term search bars + query filtering, taxonomy columns, **Connect Town Taxonomies** hidden tool (auto-creates town terms from page slugs) with styled results.
- Force noindex on `leads-page` (X-Robots-Tag header + `wp_robots` meta).

**`custom-parent-pages-queries.php` + `parent-page-filtering.php`:**
- Three Elementor custom queries — `dynamic_parent_pages_query` (related pages excluding `county`), `dynamic_town_pages_query` (pages sharing the town term), `dynamic_county_pages_query` (pages with both service + `county` terms, honoring an ACF `visible_counties` whitelist).
- County vs legacy code paths keyed on `use_county_pages` page meta; self-removing ghost-page `posts_results` filter; sibling-only filtering via the `hozio_filter_by_parent_page` meta box (parent-page-filtering.php).

**`service-menu-handler.php`** — auto-syncs pages tagged `service-pages-loop-item` into three nav menus (Main Menu, Main Menu Toggle, Services) on term change / save / WP All Import / cron retry; flags auto-added items with `_auto_service_sync` so removal never deletes manual items; gated by `hozio_service_menu_sync_enabled()`.

**`service-towns-shortcode.php`** — `[hozio_service_towns]`: searchable county-accordion (or flat "All Cities" fallback) of service-area towns for the current service page; town labels from ACF `location`; live client-side search; 12 color options + county-order setting from admin settings; scoped CSS printed once.

**`loop-configurations.php`** — reusable named **Loop Configurations** filtering Elementor Loop Grid/Carousel widgets and HTML carousels by taxonomy terms or explicit page IDs (with excludes). Per-page meta-box assignment; theme-builder context guard; admin builder UI (accordion cards, term/page pickers, search, chips); **public AJAX `hozio_get_loop_results`** for HTML widgets (+ `window.hozioPageId`); **Claude setup-prompt generator** for building HTML carousels; admin AJAX for terms/pages.

### GROUP 5 — Sitemaps (HTML, Layout, Image/Video)
**Files:** `sitemap-settings.php`, `sitemap-layout.php`, `image-sitemap.php`, `templates/html-sitemap-template.php`

**`sitemap-settings.php`** — tabbed "Sitemap Settings" shell (Appearance / Layout Editor / Image Sitemap). Appearance tab: background mode (light/dark/custom hex with luminance-aware text), custom color picker with live preview, link/hover/border color overrides, legacy `dark_mode` shim. Writes the `hozio_sitemap_*` options.

**`sitemap-layout.php` (≈7,800 lines — the largest module):**
- **Manual accordion Layout Editor** (drag-and-drop tree up to 3 levels, move-in/out, convert child↔sub-accordion, bulk select/delete) → `hozio_sitemap_layout_overrides`; Override Mode + Detection Mode (`override_first` vs `manual_only`); Excluded Pages; Import Current Auto-Detection / Import WP Children / Import by Taxonomy; in-tree duplicate detector.
- **Duplicate Page Detector** — scans for WP `-2`/`-3` auto-suffixed slugs, classifies true-duplicate vs orphaned, with fix/trash/draft/promote-to-primary/ignore actions (single, smart per-group, and bulk).
- **Town Page ACF Content Checker** — scans town pages for ~32 empty ACF content fields, deep-links to the editor with `?hozio_highlight=` to pulse-highlight missing fields.
- Admin-wide dismissible warning banners (duplicates + town-ACF); twice-daily background cron scan; auto-place/auto-remove pages in the saved tree on publish/trash/delete; stale-page cleanup; bounded permalink preload; cache invalidation on save/delete. ~18 nonce-checked AJAX endpoints.

**`image-sitemap.php` (IVXS singleton):**
- **Image** and **Video** Google-format XML sitemaps at `/{filename}.xml`, listing only media actually referenced on published content (classic inline, galleries, featured images, Elementor data, Gutenberg blocks); raw physical file URLs (avoids attachment-page 301s); Elementor screenshot exclusion; empty-sitemap 404 guard.
- **Yoast sitemap-index injection**; rewrite rules + query var + trailing-slash redirect disable; auto-enable video sitemap on first video upload; admin dashboard tab; Yoast dependency notices; **auto-deactivates and blocks the old standalone IVXS plugin**.

**`templates/html-sitemap-template.php`** — public "HTML Sitemap" page template: light/dark/custom theming + color overrides, Yoast-noindex filtering, trashed-ancestor (ghost-URL) guard, zero-query children lookup, **manual layout overrides** + **taxonomy auto-classification** (Services → Hubs → SPLI → towns, standalone/implicit hubs, generic WP-children), flat pages list, Recent Posts + selected CPT accordions, Categories + Tags sections, collapsible drawers, responsive + print styles, ARIA/keyboard support.

### GROUP 6 — Updates, Licensing & Rollback
**Files:** `plugin-updater.php`, `plugin-rollback.php`

**`plugin-updater.php`** — GitHub-Releases update injection into the WP transient (12h cache), download-URL resolution (asset zip preferred over zipball), **license gate** (Hub `active` or legacy MD5), license-status reporting for the UI, auto-update opt-in filter (honors license + version-lock + rollback-pause + toggle), "View details" popup metadata/changelog, **source-directory rename** (GitHub zipball fix), post-install reactivation + Hub heartbeat, plugins-page action links, dynamic slug detection, force-update-check + status helpers (`hozio_is_license_valid`, `hozio_get_license_status`, `hozio_auto_updates_enabled`).

**`plugin-rollback.php`** — install **any** GitHub release (up/down) via WP Upgrader; 20-entry version-history ring buffer (`hozio_version_history`); self-healing history sync on load; pre/post-upgrade capture; **on downgrade pauses auto-updates** (`hozio_auto_updates_paused_until`) and strips the update transient; AJAX for fetch-releases / rollback (license-gated) / pause / history / clear-history / version-lock; `hozio_is_license_active()`, `hozio_is_version_locked()`, `hozio_perform_rollback()` (called with `from_hub=true` by the Hub).

### GROUP 7 — Hozio Hub Remote Management
**Files:** `hub-command-executor.php`, `hub-client.php`, `hub-direct-endpoint.php`

**`hub-command-executor.php`** — static `Hozio_Command_Executor::execute()` dispatching 12 commands in **elevated admin context**: page lifecycle (trash/delete/restore/change-status), plugin lifecycle (activate/deactivate/uninstall), `update_option` (only `hozio_*`, not `hozio_hub_*`), temp admin login create/remove (`hoziowpadmin`), `rollback_plugin`, and a Tier-2 **internal REST API proxy**. Self-protection guards refuse to disable/uninstall/rollback-target Hozio Pro itself or touch `hozio_hub_*`. Exception-safe; audit-logged.

**`hub-client.php`** — static `Hozio_Hub_Client`: site registration (exchanges a registration key for a persistent `hozio_hub_site_token` + sha256 `hozio_hub_token_hash`), hourly **heartbeat** (reports version/plugins/lock, refreshes license, returns prior command results, receives commands), command processing with **nonce idempotency ring buffer**, multi-tier license-status caching with graceful degradation (transient → last-known → 72h grace), admin-login heartbeat trigger, disconnect (keeps the token hash for inbound auth). The Hub is the sole license authority.

**`hub-direct-endpoint.php`** — **inbound** `POST /wp-json/hozio-pro/v1/hub-request` (registered only when paired), bearer-token authenticated via constant-time `hash_equals` against the stored hash. Actions: `ping`, `get_info`, `get_plugins`, `get_pages` (search/paginate/tax filter), `get_taxonomies`, `get_features`, `execute_command` (with idempotency), `refresh_license` (immediate license push, no heartbeat round-trip).

### GROUP 8 — Content & SEO Output (RSS, FAQ, Permalinks)
**Files:** `custom-permalink.php`, `rss-feed-override.php`, `faq-schema.php`

**`custom-permalink.php`** — "Blog Permalink & RSS Feed" page: `/blog/` prefix toggle + category-in-URL toggle (priority-9999 `post_link`/`the_permalink`/`pre_post_link` overrides + matching rewrite rules), manual + auto rewrite flushing, live preview, and **owns the `hozio_rss_override_enabled` toggle** consumed by the RSS module.

**`rss-feed-override.php`** — replaces default RSS feed bodies with structured content assembled from ACF section fields (so Elementor-built posts produce real feed content); gated by `hozio_rss_override_enabled`.

**`faq-schema.php`** — emits **one consolidated FAQPage JSON-LD** block in `<head>` on singular pages, harvesting up to 18 Q&A pairs from three ACF sources (generic `general_questions__*`, service `faq_*`, town `new_hog_faq_*`), with master + 3 group toggles and 2 per-group post-ID exclude lists; strips HTML from Q&A text.

### GROUP 9 — Leads CRM & Media
**Files:** `leads-digest.php`, `class-media-replace-endpoint.php`

**`leads-digest.php` (self-contained lead CRM):**
- Custom `wp_hozio_leads` table; per-site webhook secret; Cloudflare-aware client-IP detection; blocked-attempt ring-buffer log.
- **Native auto-capture** from Elementor (native `e_submissions` tables), Divi, Contact Form 7, WPForms, Gravity Forms; **secret-protected REST webhook** `POST /wp-json/hozio/v1/lead` (honeypot + per-IP rate limit + optional CleanTalk, off by default).
- Unified merge of all sources → paginated/searchable admin **Lead Submissions** dashboard + hidden detail view; **rule-based spam/test scoring engine**; trash/restore/permanent-delete (single + bulk); 30-day auto-purge (page-load driven); CSV export; Display Settings color customizer; Webhook Settings page; **restricted "leads-only" admin experience** for non-admin client users (menu lockdown + redirect guards + login redirect); public **`[leads_digest]`** shortcode.

**`class-media-replace-endpoint.php`** — two media REST endpoints: `POST hozio/v1/replace-media` (replace-in-place or rename) and `POST hozio/v1/add-media` (new upload), both **preserving EXIF/ICC profiles across resized variants** (forces Imagick), with 8MB/type validation; replace-media also **extracts GPS to `gps_lat`/`gps_lng`** post meta.

### GROUP 10 — Support, Logging & Frontend Assets
**Files:** `support-page.php`, `hozio-logger.php` (also Group 1), assets

**`support-page.php`** — license-gated in-product **Support & Help** documentation center: search, 6 category tabs, 27 feature guides in an expandable detail panel. Purely presentational (documents other modules).

**Assets** — `admin-script.js`/`admin-styles.css` (branded settings UI: color pickers, live calcs, validation, toasts, counters) and `image-sitemap.js`/`image-sitemap.css` (iOS-style toggle panels for the image-sitemap tab). All persistence is server-side.

---

## 4. Cross-Cutting Reference

### Shortcodes
| Shortcode | Source | Purpose |
|---|---|---|
| `[hozio tag= format=]` | main | universal dynamic-tag resolver |
| `[hozio_current_year]` | main | current year |
| `[final_cta field= page_id=]` | main | ACF field from a specific page |
| `[gmb_map]` | main | raw ACF map embed |
| `[hozio_service_towns]` | service-towns | county-accordion town list |
| `[leads_digest]` | leads-digest | front-end leads dashboard |
| `[acf]`, `[acf_text]`, `[acf_img]`, `[acf_raw]` | acf-shortcodes | generic ACF output |
| ~120 named field shortcodes | acf-shortcodes | Town/HOG + Service page fields |

### Elementor integration points
- **Dynamic tags:** ~32 built-in + custom + `services_children` + composite (`elementor/dynamic_tags/register`).
- **Custom queries:** `dynamic_parent_pages_query`, `dynamic_town_pages_query`, `dynamic_county_pages_query`, `services_children`.
- **Loop interception:** `elementor/query/query_args` for `loop-grid`/`loop-carousel`.
- **Render filters:** strip `<u>` from icon-list; HTML output fixer targets nav-menu/CTA/lightbox markup.

### REST endpoints
| Route | Method | Auth |
|---|---|---|
| `wp/v2/acf-fields`, `wp/v1/acf-fields/{key}` | GET | `edit_posts` |
| `hozio/v1/lead` | POST | `X-Hozio-Secret` + rate limit (off by default) |
| `hozio/v1/replace-media`, `hozio/v1/add-media` | POST | `upload_files` |
| `hozio-pro/v1/hub-request` | POST | Bearer hub token (paired sites only) |
| `wp/v2/parent_pages`, `wp/v2/town_taxonomies` | core | core term caps |
| `admin-ajax.php?action=hozio_get_loop_results` | POST | public (no nonce) |

### Cron events
| Event | Schedule | Purpose |
|---|---|---|
| `hozio_hub_heartbeat` | hourly | Hub telemetry/license/commands |
| `hozio_hub_heartbeat_login` | single | heartbeat on admin login |
| `hozio_hub_heartbeat_post_update` | single (+5s) | notify Hub of new version |
| `sync_service_taxonomy_delayed` | single (+5s) | service-menu sync retry |
| `hozio_town_acf_background_scan` | twicedaily | town-ACF missing-content count |
| `wp_update_plugins` | core | triggers GitHub update check |

### Admin pages (top-level **Hozio Pro** + others)
Dynamic Tags Settings · Add/Remove · Blog Permalink & RSS Feed · Query Post Types · Archive Settings · Loop Configurations · Hozio Pro Settings · Support & Help · Sitemap Settings (Appearance/Layout/Image tabs) · **Lead Submissions** (top-level: + View, Display Settings, Webhook) · Connect Town Taxonomies (hidden) · meta boxes: Parent Pages Query Options, Loop Configuration.

### The shared option backbone (multi-module keys)
- Contact/branding: `hozio_company_phone_1/2`, `hozio_google_ads_phone`, `hozio_sms_phone`, `hozio_sms_calltrk_noswap`, `hozio_company_email`, `hozio_address_*`, `hozio_company_address`, `hozio_business_hours*`, `hozio_start_year`, all social URLs, `hozio_to_email_contact_form`, `hozio_nav_text_color` → written by admin-settings, read by dynamic-tags + `[hozio]`.
- `hozio_custom_tags` → written by Add/Remove + main handlers, consumed by dynamic-tags (class generation) + admin-settings (field render).
- Feature flags: `hozio_dom_parsing_enabled`, `hozio_service_menu_sync_enabled`, `hozio_canonical_redirect_enabled`, `hozio_webhook_enabled`, 6× `hozio_faq_schema_*` → owned by plugin-settings, consumed by main/service-menu/custom-taxonomies/leads/faq.
- Sitemap: `hozio_sitemap_bg_mode`/`dark_mode`/`custom_bg_color`/`link_color`/`link_hover_color`/`border_color`, `hozio_sitemap_layout_overrides` → settings/layout → template.
- Licensing/updates/Hub: `hozio_license_key`, `hozio_auto_updates_enabled`, `hozio_auto_updates_paused_until`, `hozio_version_locked`, `hozio_version_history`, `hozio_hub_url`, `hozio_hub_site_token`, `hozio_hub_token_hash`, `hozio_hub_license_status`, `hozio_hub_last_known_status`, `hozio_hub_executed_commands`, `hozio_hub_pending_results`.
- Service Towns: `hozio_hst_*` (12 colors + order).
- Per-page meta: `hozio_filter_by_parent_page`, `use_county_pages`, `hozio_selected_loop_config`.

### Shared helper functions
- `hozio_audit_log()` / `hozio_log()` / `hozio_debug_enabled()` — logger.php (used plugin-wide).
- `hozio_get_plugin_version()`, `hozio_dom_parsing_enabled()`, `hozio_service_menu_sync_enabled()` — plugin-settings.php.
- `hozio_is_license_valid()`, `hozio_get_license_status()`, `hozio_auto_updates_enabled()` — updater.php.
- `hozio_is_license_active()`, `hozio_is_version_locked()`, `hozio_perform_rollback()` — rollback.php.
- `hozio_get_business_hours_output()` (+ formatters) — main.
- `hozio_has_valid_parent_chain()` — custom-taxonomies.php (used by query module + sitemap template).
- `Hozio_Hub_Client::*` and `Hozio_Command_Executor::execute()` — Hub modules.
- `hozio_insert_lead_record()` + lead helpers — leads-digest.php.

### External integrations
Elementor/Elementor Pro · ACF/ACF Pro · Yoast SEO · CallRail · Google (Ads/GMB/sitemap schemas/Rich Results) · **Hozio Hub** · GitHub Releases · WordPress core · lead-form platforms (Elementor/Divi/CF7/WPForms/Gravity) + Zapier/Make + CleanTalk + Cloudflare · Imagick/EXIF · Photon/OpenStreetMap · site-migration search/replace tools (base64 defends against them).

---

## 5. End-to-End Data Flows

1. **Contact edit → tag/shortcode render (CallRail-aware):** admin saves (base64) → dynamic-tags + `[hozio]` read at render → SMS gets `data-calltrk-noswap` → HTML fixer Fix F stamps any remaining `sms:` anchors.
2. **Custom tag lifecycle:** Add/Remove → `hozio_custom_tags` → dynamic-tags regenerates the cached class file → admin-settings renders an editable field → `[hozio]` resolves it.
3. **Service page → loops + nav + town accordion:** taxonomy terms (`service-pages-loop-item`, `Service Hub`, `county`) drive service-menu sync, the three Elementor queries (+ `visible_counties` + `hozio_filter_by_parent_page`), and `[hozio_service_towns]`.
4. **Sitemap config → public render:** Appearance + Layout Editor write options → "HTML Sitemap" template renders with Yoast-noindex + ghost-ancestor filtering.
5. **Image/video sitemap → Yoast index:** IVXS collects referenced media → injects into Yoast `sitemap_index` → crawlers fetch the `.xml`.
6. **Update/rollback governance under Hub license:** heartbeat caches license → updater injects only if licensed/unlocked/unpaused → rollback records history + pauses on downgrade → post-update heartbeat re-syncs the Hub.
7. **Hub remote command (push or pull):** heartbeat response or inbound endpoint → nonce idempotency → `Command_Executor::execute()` in admin context with self-protection → results queued back to the Hub + audit-logged.
8. **Multi-platform lead capture → dashboard/webhook:** form hooks or webhook → `hozio_insert_lead_record()` / `wp_hozio_leads` → merged with Elementor `e_submissions` → scored → admin dashboard + `[leads_digest]`.
9. **ACF FAQ → FAQPage JSON-LD:** editors fill ACF FAQ fields → settings toggles/excludes → faq-schema harvests up to 18 pairs → one JSON-LD block for Google.

---

## 6. Notable Gotchas & Security Notes
- `HOZIO_VERSION` is hardcoded and must match the header `Version:` — bump both.
- Base64 protection means a domain-migration find/replace will **not** update stored URLs — re-save the settings page once after migration.
- `[hozio]` text tags and several ACF `[acf_raw]`/`*__body` shortcodes intentionally output unescaped HTML (admin-trusted content); the `<script` passthrough in text dynamic tags is by design for tracking snippets.
- `query-post-types.php` renders a nonce but doesn't verify it (CSRF gap); `loop-configurations` public AJAX has no nonce (intentional for front-end widgets).
- Hub command executor creates a hardcoded `hoziowpadmin` / `TempLogin123!` admin and elevates every command to admin — all authorization is delegated to Hub transport auth.
- `service-menu-handler` `register_activation_hook(__FILE__)` lives in an include, so the activation backfill likely never fires from the main plugin file.
- Leads 30-day trash auto-purge is page-load driven (not cron); the webhook route is 404 until explicitly enabled.
