<?php
if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// IMAGE & VIDEO SITEMAP — Hozio Pro Integration
// ============================================
// Integrated from the standalone "Hozio Image & Video XML Sitemap" plugin.
// Settings tab lives at: Sitemap Settings > Image Sitemap
// Options: ivxs_enable_image_sitemap, ivxs_image_sitemap_filename,
//          ivxs_enable_video_sitemap, ivxs_video_sitemap_filename

class Hozio_ImageVideoSitemap {

    private static $instance = null;
    private $cached_media_ids = null; // populated once per request by ivxs_collect_published_media_ids()

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct(){
        add_action('admin_enqueue_scripts', [$this, 'ivxs_enqueue_scripts']);
        add_action('admin_post_save_ivxs_settings', [$this, 'ivxs_save_settings']);
        add_action('wpseo_sitemap_index', [$this, 'ivxs_add_index_sitemap']);
        add_action('init', [$this, 'ivxs_add_sitemap_rewrites']);
        add_filter('query_vars', [$this, 'ivxs_add_query_vars']);
        add_action('template_redirect', [$this, 'ivxs_generate_custom_sitemap']);
        add_filter('redirect_canonical', [$this, 'ivxs_disable_trailing_slash_redirect'], 10, 2);
        add_action('add_attachment', [$this, 'ivxs_maybe_enable_video_sitemap']);
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    public function ivxs_enqueue_scripts($hook) {
        if (strpos($hook, 'hozio-sitemap-settings') === false) {
            return;
        }
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'image-sitemap') {
            return;
        }

        $plugin_assets_url = plugins_url('assets/', dirname(__FILE__));

        wp_enqueue_style(
            'hozio-image-sitemap-style',
            $plugin_assets_url . 'css/image-sitemap.css',
            [],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/image-sitemap.css')
        );

        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'hozio-image-sitemap-script',
            $plugin_assets_url . 'js/image-sitemap.js',
            ['jquery'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/image-sitemap.js'),
            true
        );
        wp_localize_script('hozio-image-sitemap-script', 'ivxs_ajax_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ivxs_nonce'),
        ]);
    }

    public function ivxs_dashboard() {

        if (isset($_GET['error']) && $_GET['error'] === 'invalid_sitemap_name') {
            echo '<div class="notice notice-error"><p><strong>Error:</strong> The filename cannot contain the word "sitemap" due to possible conflicts with SEO plugins.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1 class="ivxs-title">Image &amp; Video XML Sitemap</h1>
            <div class="ivxs-row">
                <div class="ivxs-col-8">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="save_ivxs_settings">
                        <?php wp_nonce_field('ivxs_save_settings', 'ivxs_nonce'); ?>
                        <div class="ivxs-global-options">
                            <h3>XML Sitemaps</h3>
                            <p>Enable the Image &amp; Video XML sitemaps. These sitemaps are specialized files that list your website's essential media content, helping search engines discover and crawl them effectively.</p>

                            <!-- Image Sitemap Toggle -->
                            <div class="ivxs-d-flex">
                                <div>
                                    <h4>Image Sitemap</h4>
                                    <p>When you upload an image, WordPress automatically creates an image page (attachment URL) for it.</p>
                                </div>
                                <label class="switch" for="image-sitemap-toggle">
                                    <input type="checkbox" id="image-sitemap-toggle" name="ivxs_enable_image_sitemap" value="1" <?php checked( get_option( 'ivxs_enable_image_sitemap', 0 ), 1 ); ?> />
                                    <div class="slider round"></div>
                                </label>
                            </div>
                            <div id="image-sitemap-options" style="display: <?php echo get_option( 'ivxs_enable_image_sitemap', 0 ) ? 'block' : 'none'; ?>;">
                                <p>
                                    <label for="image-sitemap-filename">Image Sitemap File Name:</label>
                                    <input type="text" id="image-sitemap-filename" name="ivxs_image_sitemap_filename" value="<?php echo esc_attr(get_option( 'ivxs_image_sitemap_filename', '' )); ?>" class="regular-text" />
                                    <span>.xml</span>
                                </p>
                                <?php $img_file = get_option('ivxs_image_sitemap_filename', ''); if ($img_file) : ?>
                                <p>
                                    <span class="dashicons dashicons-external" style="color:#a61e69;vertical-align:middle;margin-right:4px;"></span>
                                    <a href="<?php echo esc_url(site_url('/' . $img_file . '.xml')); ?>" target="_blank" style="font-weight:600;"><?php echo esc_html(site_url('/' . $img_file . '.xml')); ?></a>
                                </p>
                                <?php endif; ?>
                            </div>

                            <hr/>

                            <!-- Video Sitemap Toggle -->
                            <div class="ivxs-d-flex">
                                <div>
                                    <h4>Video Sitemap</h4>
                                    <p>When you upload a video, WordPress automatically creates a video page (attachment URL) for it.</p>
                                </div>
                                <label class="switch" for="video-sitemap-toggle">
                                    <input type="checkbox" id="video-sitemap-toggle" name="ivxs_enable_video_sitemap" value="1" <?php checked( get_option( 'ivxs_enable_video_sitemap', 0 ), 1 ); ?> />
                                    <div class="slider round"></div>
                                </label>
                            </div>
                            <div id="video-sitemap-options" style="display: <?php echo get_option( 'ivxs_enable_video_sitemap', 0 ) ? 'block' : 'none'; ?>;">
                                <p>
                                    <label for="video-sitemap-filename">Video Sitemap File Name:</label>
                                    <input type="text" id="video-sitemap-filename" name="ivxs_video_sitemap_filename" value="<?php echo esc_attr( get_option( 'ivxs_video_sitemap_filename', '' ) ); ?>" class="regular-text" />
                                    <span>.xml</span>
                                </p>
                                <?php $vid_file = get_option('ivxs_video_sitemap_filename', ''); if ($vid_file) : ?>
                                <p>
                                    <span class="dashicons dashicons-external" style="color:#a61e69;vertical-align:middle;margin-right:4px;"></span>
                                    <a href="<?php echo esc_url(site_url('/' . $vid_file . '.xml')); ?>" target="_blank" style="font-weight:600;"><?php echo esc_html(site_url('/' . $vid_file . '.xml')); ?></a>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php submit_button(); ?>
                    </form>
                    <p><em><strong>Note</strong>: Please visit Settings &rarr; Permalinks in your WordPress dashboard and simply click 'Save Changes' if the image or video XML sitemap URL is not working.</em></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function ivxs_save_settings() {
        if (
            !is_admin() ||
            !current_user_can('manage_options') ||
            !check_admin_referer('ivxs_save_settings', 'ivxs_nonce')
        ) {
            wp_die(esc_html('You are not allowed to perform this action.'));
        }

        $enable_image_sitemap = isset($_POST['ivxs_enable_image_sitemap']) ? 1 : 0;
        $enable_video_sitemap = isset($_POST['ivxs_enable_video_sitemap']) ? 1 : 0;

        update_option('ivxs_enable_image_sitemap', $enable_image_sitemap);
        update_option('ivxs_enable_video_sitemap', $enable_video_sitemap);

        $image_sitemap_filename = isset($_POST['ivxs_image_sitemap_filename']) ? sanitize_file_name(wp_unslash($_POST['ivxs_image_sitemap_filename'])) : '';
        $video_sitemap_filename = isset($_POST['ivxs_video_sitemap_filename']) ? sanitize_file_name(wp_unslash($_POST['ivxs_video_sitemap_filename'])) : '';

        $image_sitemap_filename = str_replace('.xml', '', $image_sitemap_filename);
        $video_sitemap_filename = str_replace('.xml', '', $video_sitemap_filename);

        if (stripos($image_sitemap_filename, 'sitemap') !== false || stripos($video_sitemap_filename, 'sitemap') !== false) {
            wp_redirect(admin_url('admin.php?page=hozio-sitemap-settings&tab=image-sitemap&error=invalid_sitemap_name'));
            exit;
        }

        if ($enable_image_sitemap && empty($image_sitemap_filename)) {
            $image_sitemap_filename = 'image';
        }
        if ($enable_video_sitemap && empty($video_sitemap_filename)) {
            $video_sitemap_filename = 'video';
        }

        update_option('ivxs_image_sitemap_filename', $enable_image_sitemap ? $image_sitemap_filename : '');
        update_option('ivxs_video_sitemap_filename', $enable_video_sitemap ? $video_sitemap_filename : '');

        flush_rewrite_rules();

        wp_redirect(admin_url('admin.php?page=hozio-sitemap-settings&tab=image-sitemap&settings-updated=true'));
        exit;
    }

    public function ivxs_add_index_sitemap($sitemaps){

        $image_sitemap_filename = get_option('ivxs_image_sitemap_filename', '');
        $video_sitemap_filename = get_option('ivxs_video_sitemap_filename', '');
        $enable_image_sitemap = get_option('ivxs_enable_image_sitemap');
        $enable_video_sitemap = get_option('ivxs_enable_video_sitemap');

        $last_mod_date_image = $this->ivxs_get_last_uploaded_media_date('image');
        $last_mod_date_video = $this->ivxs_get_last_uploaded_media_date('video');

        if(!empty($enable_image_sitemap) && !empty($image_sitemap_filename)){
            $sitemaps .= '
            <sitemap>
                <loc>' . esc_url( site_url( '/'.$image_sitemap_filename.'.xml' ) ) . '</loc>
                <lastmod>' . esc_html( $last_mod_date_image ) . '</lastmod>
            </sitemap> ';
        }
        else {
            delete_option('ivxs_image_sitemap_filename');
        }

        if(!empty($enable_video_sitemap) && !empty($video_sitemap_filename)
            && $this->ivxs_has_content_media('video') ){
            $sitemaps .= '
                <sitemap>
                    <loc>' . esc_url( site_url( '/'.$video_sitemap_filename.'.xml' ) ) . '</loc>
                    <lastmod>' . esc_html( $last_mod_date_video ) . '</lastmod>
                </sitemap>
            ';
        }
        else {
            delete_option('ivxs_video_sitemap_filename');
        }

        return $sitemaps;
    }

    public function ivxs_get_last_uploaded_media_date( $media_type ) {
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => $media_type,
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            $query->the_post();
            $formatted_date = $this->ivxs_get_post_datetime_string( get_the_ID() );
            wp_reset_postdata();
            return $formatted_date;
        }

        return current_time( 'Y-m-d\TH:i:s+00:00' );
    }

    public function ivxs_get_post_datetime_string( $post_id ) {
        $modified_date = get_the_modified_date( 'Y-m-d H:i:s', $post_id );
        $wp_timezone = wp_timezone();

        try {
            $datetime = new DateTime( $modified_date, $wp_timezone );
            $datetime->setTimezone( new DateTimeZone( 'UTC' ) );
            return $datetime->format( 'Y-m-d\TH:i:s+00:00' );
        } catch ( Exception $e ) {
            return gmdate( 'Y-m-d\TH:i:s+00:00' );
        }
    }

    public function ivxs_add_sitemap_rewrites() {
        $image_sitemap_filename = get_option('ivxs_image_sitemap_filename', 'image');
        $video_sitemap_filename = get_option('ivxs_video_sitemap_filename', 'video');

        if ($image_sitemap_filename) {
            add_rewrite_rule(
                '^' . $image_sitemap_filename . '\.xml$',
                'index.php?ivxs_custom_var=' . $image_sitemap_filename,
                'top'
            );
        }

        if ($video_sitemap_filename) {
            add_rewrite_rule(
                '^' . $video_sitemap_filename . '\.xml$',
                'index.php?ivxs_custom_var=' . $video_sitemap_filename,
                'top'
            );
        }
    }

    public function ivxs_add_query_vars($qvars) {
        $qvars[] = 'ivxs_custom_var';
        return $qvars;
    }

    public function ivxs_generate_custom_sitemap() {

        if (!is_main_query() || is_admin()) {
            return;
        }

        $sitemap_name = get_query_var('ivxs_custom_var');

        $image_sitemap_filename = get_option('ivxs_image_sitemap_filename', 'image');
        $video_sitemap_filename = get_option('ivxs_video_sitemap_filename', 'video');

        if ($sitemap_name && !is_404()) {
            if ($sitemap_name === $image_sitemap_filename) {
                $this->ivxs_generate_media_sitemap('image');
            } elseif ($sitemap_name === $video_sitemap_filename) {
                $this->ivxs_generate_media_sitemap('video');
            }
        }
    }

    /**
     * Auto-enable the video sitemap the first time a video is uploaded to the media library.
     * The content-based check in ivxs_add_index_sitemap() still controls whether video.xml
     * actually appears in the sitemap index — it won't until the video is placed on a page.
     */
    public function ivxs_maybe_enable_video_sitemap( $attachment_id ) {
        $mime = get_post_mime_type( $attachment_id );
        if ( ! $mime || strpos( $mime, 'video/' ) !== 0 ) {
            return;
        }
        if ( get_option( 'ivxs_enable_video_sitemap' ) !== '1' ) {
            update_option( 'ivxs_enable_video_sitemap', '1' );
        }
        if ( ! get_option( 'ivxs_video_sitemap_filename' ) ) {
            update_option( 'ivxs_video_sitemap_filename', 'video' );
        }
    }

    /**
     * Returns true if any attachment of the given type (image|video) is referenced
     * in published post content. Used to gate sitemap index entries.
     */
    private function ivxs_has_content_media( $type ) {
        global $wpdb;
        $allowed_ids = $this->ivxs_collect_published_media_ids(); // already intval-cast
        if ( empty( $allowed_ids ) ) {
            return false;
        }
        $mime_types = $type === 'image'
            ? [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ]
            : [ 'video/mp4', 'video/avi', 'video/mov', 'video/webm' ];
        $id_placeholders   = implode( ',', array_fill( 0, count( $allowed_ids ), '%d' ) );
        $mime_placeholders = implode( ',', array_fill( 0, count( $mime_types ), '%s' ) );
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'attachment'
                 AND post_status = 'inherit'
                 AND post_mime_type IN ($mime_placeholders)
                 AND ID IN ($id_placeholders)",
                array_merge( $mime_types, $allowed_ids )
            )
        );
        return $count > 0;
    }

    /**
     * Check if an attachment is an Elementor screenshot
     */
    private function ivxs_is_elementor_screenshot( $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        $filename = basename( $file_path );

        if (
            strpos( $filename, '-screenshot' ) !== false ||
            strpos( $filename, '_screenshot' ) !== false ||
            strpos( $filename, 'elementor-screenshot' ) !== false
        ) {
            return true;
        }

        $meta = get_post_meta( $attachment_id, '_elementor_source', true );
        if ( ! empty( $meta ) ) {
            return true;
        }

        return false;
    }

    public function ivxs_generate_media_sitemap( $media_type ) {
        global $wpdb, $wp_query; // $wp_query needed for set_404()

        // Pre-check: see if there are any entries before sending headers.
        // This lets us 404 cleanly instead of serving an empty XML file.
        $allowed_ids = $this->ivxs_collect_published_media_ids();

        if ( ! empty( $allowed_ids ) ) {
            $id_list    = implode( ',', $allowed_ids );
            $mime_types = $media_type === 'image'
                ? "'image/jpeg','image/png','image/gif','image/webp'"
                : "'video/mp4','video/avi','video/mov','video/webm'";
            $has_entries = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts}
                 WHERE post_type = 'attachment'
                 AND post_status = 'inherit'
                 AND post_mime_type IN ($mime_types)
                 AND ID IN ($id_list)"
            );
        } else {
            $has_entries = 0;
        }

        if ( ! $has_entries ) {
            $wp_query->set_404();
            return; // Let WordPress + Elementor Pro load the 404 template naturally
        }

        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
        }

        $namespace = $media_type === 'image'
            ? 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"'
            : 'xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"';

        $xsl_url = plugins_url('assets/css/image-sitemap.xsl', dirname(__FILE__));

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<?xml-stylesheet type="text/xsl" href="' . esc_url($xsl_url) . '"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" ' . $namespace . '>';

        if ( $media_type === 'image' ) {
            $this->ivxs_output_image_sitemap_entries();
        } else {
            $this->ivxs_output_video_sitemap_entries();
        }

        echo '</urlset>';
        wp_reset_postdata();
        exit;
    }

    /**
     * Build the set of attachment IDs that are referenced on at least one published post/page.
     * Checks: published post_parent, featured images, Elementor widget data, Gutenberg blocks.
     * Returns an array of integer attachment IDs (may include non-image/non-video IDs —
     * the final query filters by mime type).
     */
    private function ivxs_collect_published_media_ids() {
        if ( $this->cached_media_ids !== null ) {
            return $this->cached_media_ids;
        }

        global $wpdb;
        $ids = [];

        // 1. Classic editor inline images (wp-image-{id} class) and gallery shortcodes.
        //    Intentionally NOT using post_parent — WordPress sets post_parent when an image is
        //    uploaded via the post editor even if it was never inserted into the content, so
        //    post_parent alone would include orphaned media library uploads.
        $classic_rows = $wpdb->get_col(
            "SELECT post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ('post','page')
             AND (post_content LIKE '%wp-image-%' OR post_content LIKE '%[gallery%')"
        );
        foreach ( $classic_rows as $content ) {
            if ( preg_match_all( '/\bwp-image-(\d+)\b/', $content, $m ) ) {
                $ids = array_merge( $ids, $m[1] );
            }
            if ( preg_match_all( '/\[gallery[^\]]*ids="([^"]+)"/', $content, $m ) ) {
                foreach ( $m[1] as $csv ) {
                    $ids = array_merge( $ids, array_map( 'trim', explode( ',', $csv ) ) );
                }
            }
        }

        // 2. Featured images (_thumbnail_id) of published posts
        $rows = $wpdb->get_col(
            "SELECT DISTINCT CAST(pm.meta_value AS UNSIGNED)
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_thumbnail_id'
             AND p.post_status = 'publish'
             AND pm.meta_value > 0"
        );
        if ( $rows ) $ids = array_merge( $ids, $rows );

        // 3. Parse Elementor JSON to extract attachment IDs
        // Elementor stores image/video IDs as {"id":123} inside _elementor_data meta.
        $elementor_rows = $wpdb->get_col(
            "SELECT pm.meta_value
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_elementor_data'
             AND p.post_status = 'publish'
             AND pm.meta_value != '' AND pm.meta_value != '[]'"
        );
        foreach ( $elementor_rows as $json ) {
            if ( preg_match_all( '/"id"\s*:\s*(\d+)/', $json, $m ) ) {
                $ids = array_merge( $ids, $m[1] );
            }
        }

        // 4. Gutenberg blocks — extract IDs from <!-- wp:image {"id":123} --> comments
        $gutenberg_rows = $wpdb->get_col(
            "SELECT post_content FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ('post', 'page')
             AND post_content LIKE '%wp:image%'"
        );
        foreach ( $gutenberg_rows as $content ) {
            if ( preg_match_all( '/wp:(?:image|video|media-text)[^{]*\{[^}]*"id"\s*:\s*(\d+)/', $content, $m ) ) {
                $ids = array_merge( $ids, $m[1] );
            }
        }

        $this->cached_media_ids = array_unique( array_filter( array_map( 'intval', $ids ) ) );
        return $this->cached_media_ids;
    }

    /**
     * Output image sitemap entries.
     * Includes any image attachment referenced on at least one published post/page.
     */
    private function ivxs_output_image_sitemap_entries() {
        global $wpdb;

        $allowed_ids = $this->ivxs_collect_published_media_ids();
        if ( empty( $allowed_ids ) ) {
            return;
        }

        $id_list = implode( ',', $allowed_ids ); // safe — all cast to int above
        $attachments = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_status = 'inherit'
             AND post_mime_type IN ('image/jpeg', 'image/png', 'image/gif', 'image/webp')
             AND ID IN ($id_list)
             ORDER BY post_date DESC"
        );

        if ( empty( $attachments ) ) {
            return;
        }

        foreach ( $attachments as $attachment ) {
            $attachment_id = (int) $attachment->ID;

            if ( $this->ivxs_is_elementor_screenshot( $attachment_id ) ) {
                continue;
            }

            $direct_url = $this->ivxs_get_raw_file_url( $attachment_id );

            if ( empty( $direct_url ) ) {
                continue;
            }

            $lastmod = $this->ivxs_get_post_datetime_string( $attachment_id );

            echo '<url>';
            echo '<loc>' . esc_url( $direct_url ) . '</loc>';
            if ( $lastmod ) {
                echo '<lastmod>' . esc_html( $lastmod ) . '</lastmod>';
            }
            echo '<image:image>';
            echo '<image:loc>' . esc_url( $direct_url ) . '</image:loc>';
            echo '</image:image>';
            echo '</url>';
        }
    }

    /**
     * Return the raw physical file URL for an attachment, bypassing any
     * wp_get_attachment_url filters that might rewrite it to the attachment
     * page URL (which 301-redirects on WP 6.4+ and causes Ahrefs sitemap errors).
     */
    private function ivxs_get_raw_file_url( $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        if ( empty( $file_path ) ) {
            return '';
        }

        $upload_dir = wp_get_upload_dir();
        if ( empty( $upload_dir['basedir'] ) || empty( $upload_dir['baseurl'] ) ) {
            return '';
        }

        if ( strpos( $file_path, $upload_dir['basedir'] ) === 0 ) {
            return str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $file_path );
        }

        // Fallback — file lives outside standard uploads dir (rare). Use filtered URL
        // but strip anything that doesn't look like a file (no extension) to avoid
        // emitting the attachment page URL into the sitemap.
        $filtered = wp_get_attachment_url( $attachment_id );
        return ( $filtered && preg_match( '/\.[a-z0-9]{2,5}(\?.*)?$/i', $filtered ) ) ? $filtered : '';
    }

    /**
     * Output video sitemap entries.
     * Includes any video attachment referenced on at least one published post/page.
     */
    private function ivxs_output_video_sitemap_entries() {
        global $wpdb;

        $allowed_ids = $this->ivxs_collect_published_media_ids();
        if ( empty( $allowed_ids ) ) {
            return;
        }

        $id_list = implode( ',', $allowed_ids ); // safe — all cast to int above
        $attachments = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'attachment'
             AND post_status = 'inherit'
             AND post_mime_type IN ('video/mp4', 'video/avi', 'video/mov', 'video/webm')
             AND ID IN ($id_list)
             ORDER BY post_date DESC"
        );

        if ( empty( $attachments ) ) {
            return;
        }

        foreach ( $attachments as $attachment ) {
            $attachment_id = (int) $attachment->ID;

            $elementor_source = get_post_meta( $attachment_id, '_elementor_source', true );
            if ( ! empty( $elementor_source ) ) {
                continue;
            }

            $direct_url = $this->ivxs_get_raw_file_url( $attachment_id );

            if ( empty( $direct_url ) ) {
                continue;
            }

            $lastmod = $this->ivxs_get_post_datetime_string( $attachment_id );

            $video_page_url = $this->ivxs_find_video_usage( $attachment_id );

            $thumbnail = $this->ivxs_get_raw_file_url( get_post_thumbnail_id( $attachment_id ) );

            $title = get_the_title( $attachment_id );
            if ( empty( $title ) ) {
                $title = get_bloginfo( 'name' );
            }

            $description = wp_get_attachment_caption( $attachment_id );
            if ( empty( $description ) ) {
                $description = get_post_field( 'post_content', $attachment_id );
            }
            if ( empty( $description ) ) {
                $description = get_bloginfo( 'description' );
            }
            if ( empty( $description ) ) {
                $description = get_bloginfo( 'name' );
            }

            echo '<url>';
            echo '<loc>' . esc_url( $direct_url ) . '</loc>';
            if ( $lastmod ) {
                echo '<lastmod>' . esc_html( $lastmod ) . '</lastmod>';
            }
            echo '<video:video>';
            echo '<video:thumbnail_loc>' . esc_url( $thumbnail ) . '</video:thumbnail_loc>';
            echo '<video:title>' . esc_html( $title ) . '</video:title>';
            echo '<video:description>' . esc_html( $description ) . '</video:description>';

            if ( $video_page_url ) {
                echo '<video:content_loc>' . esc_url( $video_page_url ) . '</video:content_loc>';
            }

            echo '</video:video>';
            echo '</url>';
        }
    }

    /**
     * Find if a video attachment is used/embedded on any post or page
     */
    private function ivxs_find_video_usage( $attachment_id ) {
        global $wpdb;

        $attachment_url = wp_get_attachment_url( $attachment_id );
        $filename = basename( $attachment_url );

        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_type FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ('post', 'page')
             AND (post_content LIKE %s OR post_content LIKE %s)
             LIMIT 1",
            '%' . $wpdb->esc_like( $attachment_url ) . '%',
            '%' . $wpdb->esc_like( $filename ) . '%'
        ) );

        if ( empty( $posts ) ) {
            $posts = $wpdb->get_results( $wpdb->prepare(
                "SELECT p.ID, p.post_type FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 WHERE p.post_status = 'publish'
                 AND p.post_type IN ('post', 'page')
                 AND pm.meta_key = '_thumbnail_id'
                 AND pm.meta_value = %d
                 LIMIT 1",
                $attachment_id
            ) );
        }

        if ( ! empty( $posts ) ) {
            return get_permalink( $posts[0]->ID );
        }

        return false;
    }

    public function ivxs_disable_trailing_slash_redirect($redirect_url, $requested_url) {
        $image_sitemap_filename = get_option('ivxs_image_sitemap_filename', 'image');
        $video_sitemap_filename = get_option('ivxs_video_sitemap_filename', 'video');

        if (preg_match('/(' . preg_quote($image_sitemap_filename, '/') . '|' . preg_quote($video_sitemap_filename, '/') . ')\.xml$/', $requested_url)) {
            return false;
        }
        return $redirect_url;
    }

}

// ============================================
// INITIALIZATION & TAB RENDER FUNCTION
// ============================================

// Boot the singleton (hook priority 5 to run before init priority 10 rewrite rules)
add_action('plugins_loaded', function() {
    Hozio_ImageVideoSitemap::get_instance();
}, 5);

// Render function called by the Hozio Pro tab dispatcher
if (!function_exists('hozio_image_sitemap_page')) {
    function hozio_image_sitemap_page() {
        // Show settings-updated notice
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        }
        Hozio_ImageVideoSitemap::get_instance()->ivxs_dashboard();
    }
}

// ============================================
// YOAST SEO DEPENDENCY CHECK
// ============================================

// Use anonymous functions to avoid naming conflicts with the standalone plugin
// (which declares identically-named functions without function_exists guards).
// This ensures both files can be loaded on the same request without a fatal error
// while the plugins_loaded hook (above) deactivates the standalone for all future requests.
add_action('admin_init', function() {
    if (!is_admin() || !current_user_can('activate_plugins')) {
        return;
    }
    if (!function_exists('is_plugin_active')) {
        return;
    }
    if (!is_plugin_active('wordpress-seo/wp-seo.php')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><strong>Hozio Pro &mdash; Image &amp; Video XML Sitemap:</strong> Requires Yoast SEO to be installed and activated.</p>
            </div>
            <?php
        });
    } elseif (class_exists('WPSEO_Options') && empty(WPSEO_Options::get('enable_xml_sitemap'))) {
        add_action('admin_notices', function() {
            $current_screen = get_current_screen();
            if ($current_screen && strpos($current_screen->id, 'hozio-sitemap-settings') !== false) {
                ?>
                <div class="notice notice-error">
                    <p><strong>Hozio Pro &mdash; Image &amp; Video XML Sitemap:</strong> Please enable the <a href="<?php echo esc_url(admin_url('admin.php?page=wpseo_page_settings#/site-features')); ?>">Yoast SEO XML Sitemap</a> feature to use this.</p>
                </div>
                <?php
            }
        });
    }
});

// ============================================
// STANDALONE PLUGIN CONFLICT GUARD
// ============================================

define('HOZIO_IVXS_STANDALONE', 'image-video-xml-sitemap/image-video-xml-sitemap.php');

// Auto-deactivate the standalone plugin if it somehow ends up active alongside Hozio Pro.
// This handles the case where Hozio Pro is updated on a site that still has the old plugin installed.
// Runs on plugins_loaded so it fires as early as possible — before any admin_init hooks.
add_action('plugins_loaded', function() {
    if (!function_exists('is_plugin_active') || !function_exists('deactivate_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (is_plugin_active(HOZIO_IVXS_STANDALONE)) {
        deactivate_plugins(HOZIO_IVXS_STANDALONE);
        set_transient('hozio_ivxs_auto_deactivated', true, 60);
    }
}, 1); // Priority 1 — fires as early as possible

// Show a notice after auto-deactivation
add_action('admin_notices', function() {
    if (!get_transient('hozio_ivxs_auto_deactivated')) {
        return;
    }
    delete_transient('hozio_ivxs_auto_deactivated');
    $url = admin_url('admin.php?page=hozio-sitemap-settings&tab=image-sitemap');
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><strong>Hozio Pro:</strong> The standalone "Image &amp; Video XML Sitemap" plugin has been automatically deactivated &mdash; this feature is now built into Hozio Pro. <a href="<?php echo esc_url($url); ?>">Configure it here &rarr;</a></p>
    </div>
    <?php
});

// Block manual re-activation of the standalone plugin
add_action('activate_' . HOZIO_IVXS_STANDALONE, function() {
    deactivate_plugins(HOZIO_IVXS_STANDALONE);
    set_transient('hozio_ivxs_activation_blocked', true, 60);
});

// Show a notice if someone tried to activate it
add_action('admin_notices', function() {
    if (!get_transient('hozio_ivxs_activation_blocked')) {
        return;
    }
    delete_transient('hozio_ivxs_activation_blocked');
    $url = admin_url('admin.php?page=hozio-sitemap-settings&tab=image-sitemap');
    ?>
    <div class="notice notice-error is-dismissible">
        <p><strong>Hozio Pro:</strong> The "Image &amp; Video XML Sitemap" plugin cannot be activated because this feature is already built into Hozio Pro. <a href="<?php echo esc_url($url); ?>">Configure it under Sitemap Settings &rarr; Image Sitemap</a>.</p>
    </div>
    <?php
});

// Disable the "Activate" link on the plugins page when Hozio Pro is active
add_filter('plugin_action_links_' . HOZIO_IVXS_STANDALONE, function($actions) {
    unset($actions['activate']);
    $url = admin_url('admin.php?page=hozio-sitemap-settings&tab=image-sitemap');
    $actions['hozio_built_in'] = '<span style="color:#a61e69;font-weight:600;">Built into Hozio Pro &mdash; <a href="' . esc_url($url) . '">Configure</a></span>';
    return $actions;
});
