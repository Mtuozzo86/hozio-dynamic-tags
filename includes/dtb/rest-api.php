<?php
/**
 * DTB REST API endpoints.
 *
 * Provides the server-side API for REST-based deployment, page content
 * management, cache orchestration, and option configuration. All endpoints
 * require admin-level authentication via Application Password.
 *
 * Endpoints registered under the dtb/v1 namespace:
 *   POST   /dtb/v1/content-update                Upload zip to replace per-client content in wp-content/uploads/dtb-site/
 *   GET    /dtb/v1/content-status                Read installed website-content version and manifest
 *   GET    /dtb/v1/elementor/{id}                Read _elementor_data for a post
 *   POST   /dtb/v1/elementor/{id}                Write _elementor_data for a post
 *   POST   /dtb/v1/flush-cache                   Orchestrated cache flush
 *   POST   /dtb/v1/flush-rewrite                 Flush WP rewrite rules
 *   GET    /dtb/v1/option/{key}                  Read a WordPress option (allowlisted)
 *   POST   /dtb/v1/option/{key}                  Write a WordPress option (allowlisted)
 *   GET    /dtb/v1/kit                           Read Elementor Kit post meta
 *   POST   /dtb/v1/kit                           Merge-update Elementor Kit post meta
 *   POST   /dtb/v1/install-theme                 Install a theme from wordpress.org by slug
 *   POST   /dtb/v1/activate-theme                Switch the active theme via switch_theme()
 *   POST   /dtb/v1/delete-themes-except-active   Remove every installed theme except the active one
 *   POST   /dtb/v1/acf-write                     Write one or many ACF field values on a post
 *   POST   /dtb/v1/delete-default-content        Hard-delete WP / Elementor sample content
 *   POST   /dtb/v1/site-icons                    Set site_logo and site_icon options from media IDs
 *   POST   /dtb/v1/menus                         Reconcile WP menus + items from a manifest
 *   POST   /dtb/v1/page                          Create a page per the §0.5 page-structure standard
 *   DELETE /dtb/v1/page/{id}                     Hard-delete a page (force=true, skip trash)
 *   POST   /dtb/v1/rewire-acf-groups             Rewire Hozio ACF groups bound to source-site IDs
 *   POST   /dtb/v1/yoast-page                    Write Yoast title / description meta for a post
 *   GET    /dtb/v1/status                        Health check / plugin info
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'dtb_register_rest_routes' );

function dtb_register_rest_routes() {

    $ns = 'dtb/v1';

    // ── Website content upload (replaces self-update) ──────────────────────
    // Two auth paths:
    //   1. WP admin via Application Password (manage_options) — for the
    //      legacy CLI tool and manual deploys from an operator's laptop
    //   2. HMAC-signed deploy from GitHub Actions — verified against the
    //      HOZIO_DEPLOY_SECRET wp-config.php constant (no admin session,
    //      no GitHub credentials on the WP side; just a shared secret)
    register_rest_route( $ns, '/content-update', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_content_update',
        'permission_callback' => 'dtb_rest_content_update_permission',
    ) );

    register_rest_route( $ns, '/content-status', array(
        'methods'             => 'GET',
        'callback'            => 'dtb_rest_content_status',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── Elementor data ─────────────────────────────────────────────────────
    register_rest_route( $ns, '/elementor/(?P<id>\d+)', array(
        array(
            'methods'             => 'GET',
            'callback'            => 'dtb_rest_elementor_read',
            'permission_callback' => 'dtb_rest_admin_check',
        ),
        array(
            'methods'             => 'POST',
            'callback'            => 'dtb_rest_elementor_write',
            'permission_callback' => 'dtb_rest_admin_check',
        ),
    ) );

    // ── Cache flush ────────────────────────────────────────────────────────
    register_rest_route( $ns, '/flush-cache', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_flush_cache',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── Rewrite rules flush (after permalink change) ───────────────────────
    register_rest_route( $ns, '/flush-rewrite', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_flush_rewrite',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── Theme install / activate / delete ──────────────────────────────────
    register_rest_route( $ns, '/install-theme', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_install_theme',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    register_rest_route( $ns, '/activate-theme', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_activate_theme',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    register_rest_route( $ns, '/delete-themes-except-active', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_delete_themes_except_active',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── ACF field VALUE writes (works around ACF Pro 6.8 REST limitation) ──
    register_rest_route( $ns, '/acf-write', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_acf_write',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── Default content cleanup ────────────────────────────────────────────
    register_rest_route( $ns, '/delete-default-content', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_delete_default_content',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── Site icons (logo + favicon) ────────────────────────────────────────
    register_rest_route( $ns, '/site-icons', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_site_icons',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── Menus ──────────────────────────────────────────────────────────────
    register_rest_route( $ns, '/menus', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_menus',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── Page CRUD (enforces §0.5 page-structure standard) ──────────────────
    register_rest_route( $ns, '/page', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_page_create',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    register_rest_route( $ns, '/page/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'dtb_rest_page_delete',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── ACF group location-rule rewiring ───────────────────────────────────
    register_rest_route( $ns, '/rewire-acf-groups', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_rewire_acf_groups',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── Yoast per-page title and description ───────────────────────────────
    register_rest_route( $ns, '/yoast-page', array(
        'methods'             => 'POST',
        'callback'            => 'dtb_rest_yoast_page',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );

    // ── WordPress options ──────────────────────────────────────────────────
    register_rest_route( $ns, '/option/(?P<key>[a-zA-Z0-9_-]+)', array(
        array(
            'methods'             => 'GET',
            'callback'            => 'dtb_rest_option_read',
            'permission_callback' => 'dtb_rest_admin_check',
        ),
        array(
            'methods'             => 'POST',
            'callback'            => 'dtb_rest_option_write',
            'permission_callback' => 'dtb_rest_admin_check',
        ),
    ) );

    // ── Elementor Kit settings ─────────────────────────────────────────────
    register_rest_route( $ns, '/kit', array(
        array(
            'methods'             => 'GET',
            'callback'            => 'dtb_rest_kit_read',
            'permission_callback' => 'dtb_rest_admin_check',
        ),
        array(
            'methods'             => 'POST',
            'callback'            => 'dtb_rest_kit_write',
            'permission_callback' => 'dtb_rest_admin_check',
        ),
    ) );

    // ── Health check ───────────────────────────────────────────────────────
    register_rest_route( $ns, '/status', array(
        'methods'             => 'GET',
        'callback'            => 'dtb_rest_status',
        'permission_callback' => 'dtb_rest_admin_check',
    ) );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Permission callback
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_admin_check( $request ) {
    return current_user_can( 'manage_options' );
}

/**
 * Permission callback for /dtb/v1/content-update.
 *
 * Allows either:
 *   1. An authenticated admin (Application Password) — legacy CLI / manual deploys
 *   2. An HMAC-signed request from GitHub Actions — production deploy path
 *
 * The HMAC path is verified against HOZIO_DEPLOY_SECRET, a wp-config.php
 * constant the operator sets once when the site is provisioned. The same
 * secret is added to the GitHub repo's Actions secrets; GitHub Actions
 * computes HMAC-SHA256(zip_body, secret) and sends it as X-Hozio-Signature.
 *
 * Replay protection: X-Hozio-Timestamp must be within 5 minutes of server time.
 *
 * Returns true if either path passes; false otherwise. The handler itself
 * calls dtb_rest_content_update_verify_hmac() to do the actual signature
 * check (deferred so we can read the body, which the permission callback
 * runs before WP exposes).
 */
function dtb_rest_content_update_permission( $request ) {
    // Path 1: admin auth (no HMAC headers present)
    $signature = $request->get_header( 'x-hozio-signature' );
    if ( empty( $signature ) ) {
        return current_user_can( 'manage_options' );
    }

    // Path 2: HMAC. Defer to the handler, which has access to the body.
    // Returning true here just gates "is this request shaped like an
    // HMAC deploy?" — the actual cryptographic verification happens
    // inside dtb_rest_content_update() before the body is acted on.
    if ( ! defined( 'HOZIO_DEPLOY_SECRET' ) || HOZIO_DEPLOY_SECRET === '' ) {
        return false; // HMAC headers but no secret configured = reject
    }

    return true;
}

/**
 * Verify the HMAC + timestamp on an incoming content-update request.
 * Called by dtb_rest_content_update() right after it locates the upload.
 *
 * @param WP_REST_Request $request
 * @param string          $tmp_path Path to the uploaded zip on disk
 * @return true|WP_Error
 */
function dtb_rest_content_update_verify_hmac( $request, $tmp_path ) {
    $signature = $request->get_header( 'x-hozio-signature' );
    $timestamp = $request->get_header( 'x-hozio-timestamp' );

    if ( empty( $signature ) || empty( $timestamp ) ) {
        return new WP_Error( 'missing_hmac_headers', 'Missing X-Hozio-Signature or X-Hozio-Timestamp.', array( 'status' => 401 ) );
    }
    if ( ! defined( 'HOZIO_DEPLOY_SECRET' ) || HOZIO_DEPLOY_SECRET === '' ) {
        return new WP_Error( 'no_deploy_secret', 'HOZIO_DEPLOY_SECRET is not defined in wp-config.php on this site.', array( 'status' => 503 ) );
    }

    // Replay protection: 5-minute window
    $now      = time();
    $req_time = (int) $timestamp;
    if ( $req_time <= 0 || abs( $now - $req_time ) > 5 * MINUTE_IN_SECONDS ) {
        if ( function_exists( 'hozio_audit_log' ) ) {
            hozio_audit_log( "Content-update HMAC rejected: timestamp out of window (req={$req_time}, now={$now})", 'Deploy Auth' );
        }
        return new WP_Error( 'stale_timestamp', 'X-Hozio-Timestamp is outside the 5-minute window.', array( 'status' => 401 ) );
    }

    // Verify HMAC of the uploaded file against HOZIO_DEPLOY_SECRET
    $expected = base64_encode( hash_hmac( 'sha256', file_get_contents( $tmp_path ), HOZIO_DEPLOY_SECRET, true ) );
    if ( ! hash_equals( $expected, (string) $signature ) ) {
        if ( function_exists( 'hozio_audit_log' ) ) {
            $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
            hozio_audit_log( "Content-update HMAC rejected: signature mismatch from {$ip}", 'Deploy Auth' );
        }
        return new WP_Error( 'bad_signature', 'X-Hozio-Signature did not match.', array( 'status' => 401 ) );
    }

    return true;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Website content upload (atomic swap)
//
// Replaces the legacy /self-update endpoint. The plugin engine itself is now
// part of hozio-dynamic-tags and updates via the Hozio plugin updater
// (GitHub releases). This endpoint accepts a zip of per-client website
// CONTENT (templates/, assets/, hozio-config.php) and atomically swaps it
// into wp-content/uploads/dtb-site/. Files removed from the new zip are
// removed from the server (server matches the repo exactly).
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_content_update( $request ) {
    $files = $request->get_file_params();

    if ( empty( $files['content'] ) || empty( $files['content']['tmp_name'] ) ) {
        return new WP_Error( 'no_file', 'No content zip provided. Send as multipart field named "content".', array( 'status' => 400 ) );
    }

    $file = $files['content'];

    // Validate MIME type
    $allowed_types = array( 'application/zip', 'application/x-zip-compressed', 'application/octet-stream' );
    $finfo         = finfo_open( FILEINFO_MIME_TYPE );
    $detected_type = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );

    if ( ! in_array( $detected_type, $allowed_types, true ) ) {
        return new WP_Error( 'invalid_type', 'File must be a zip archive. Detected: ' . $detected_type, array( 'status' => 400 ) );
    }

    // Validate zip magic bytes (PK\x03\x04)
    $handle = fopen( $file['tmp_name'], 'rb' );
    $magic  = fread( $handle, 4 );
    fclose( $handle );
    if ( $magic !== "PK\x03\x04" ) {
        return new WP_Error( 'invalid_zip', 'File does not have valid zip magic bytes.', array( 'status' => 400 ) );
    }

    // Size guard: 50 MB
    if ( $file['size'] > 50 * 1024 * 1024 ) {
        return new WP_Error( 'too_large', 'Content zip exceeds 50MB limit.', array( 'status' => 400 ) );
    }

    // HMAC auth: if the request carries a signature, verify it against the
    // file body BEFORE touching anything. Admin auth requests (no signature
    // header) skip this branch and rely on the permission_callback.
    $is_hmac_request = ! empty( $request->get_header( 'x-hozio-signature' ) );
    $commit_sha      = (string) $request->get_header( 'x-hozio-commit' );
    $repo_full_name  = (string) $request->get_header( 'x-hozio-repo' );
    $run_id          = (string) $request->get_header( 'x-hozio-run-id' );
    $github_actor    = (string) $request->get_header( 'x-hozio-actor' );
    $deploy_actor    = $is_hmac_request ? 'github-actions' : 'admin';
    if ( $is_hmac_request ) {
        $hmac_check = dtb_rest_content_update_verify_hmac( $request, $file['tmp_name'] );
        if ( is_wp_error( $hmac_check ) ) {
            return $hmac_check;
        }
    }

    // Load WP filesystem
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    global $wp_filesystem;

    if ( ! $wp_filesystem ) {
        return new WP_Error( 'filesystem', 'Could not initialize WP_Filesystem.', array( 'status' => 500 ) );
    }

    // Resolve target paths
    $uploads     = wp_get_upload_dir();
    if ( ! empty( $uploads['error'] ) ) {
        return new WP_Error( 'uploads_error', 'wp_get_upload_dir error: ' . $uploads['error'], array( 'status' => 500 ) );
    }
    $base        = trailingslashit( $uploads['basedir'] );
    $content_dir = $base . 'dtb-site';
    $staging_dir = $base . 'dtb-site-staging-' . wp_generate_password( 8, false );
    $backup_dir  = $base . 'dtb-site-old-' . time() . '-' . wp_generate_password( 4, false );

    // Ensure uploads basedir exists
    if ( ! $wp_filesystem->is_dir( $uploads['basedir'] ) ) {
        wp_mkdir_p( $uploads['basedir'] );
    }

    // Extract to staging
    $unzip = unzip_file( $file['tmp_name'], $staging_dir );
    if ( is_wp_error( $unzip ) ) {
        $wp_filesystem->rmdir( $staging_dir, true );
        return new WP_Error( 'unzip_failed', $unzip->get_error_message(), array( 'status' => 500 ) );
    }

    // Validate structure: zip must contain dtb-site/hozio-config.php at the top
    $extracted_root = trailingslashit( $staging_dir ) . 'dtb-site';
    $required_file  = trailingslashit( $extracted_root ) . 'hozio-config.php';
    if ( ! $wp_filesystem->exists( $required_file ) ) {
        $wp_filesystem->rmdir( $staging_dir, true );
        return new WP_Error( 'invalid_structure', 'Zip must contain dtb-site/hozio-config.php at the top level.', array( 'status' => 400 ) );
    }

    // Read manifest if present (for response payload)
    $manifest_version = '';
    $manifest_path    = trailingslashit( $extracted_root ) . 'manifest.json';
    if ( $wp_filesystem->exists( $manifest_path ) ) {
        $manifest_raw  = $wp_filesystem->get_contents( $manifest_path );
        $manifest_data = json_decode( (string) $manifest_raw, true );
        if ( is_array( $manifest_data ) && isset( $manifest_data['version'] ) ) {
            $manifest_version = (string) $manifest_data['version'];
        }
    }

    // Atomic swap:
    //   1. If existing dtb-site/ exists, rename to backup_dir.
    //   2. Rename staging/dtb-site/ -> dtb-site/.
    //   3. On step 2 failure, rename backup back to original (rollback).
    //   4. Cleanup staging dir + schedule backup deletion.
    $had_existing = $wp_filesystem->is_dir( $content_dir );
    if ( $had_existing ) {
        if ( ! @rename( $content_dir, $backup_dir ) ) {
            $wp_filesystem->rmdir( $staging_dir, true );
            return new WP_Error( 'swap_failed', 'Could not move existing dtb-site/ to backup. Check filesystem permissions.', array( 'status' => 500 ) );
        }
    }

    if ( ! @rename( $extracted_root, $content_dir ) ) {
        // Rollback: restore the backup
        if ( $had_existing && $wp_filesystem->is_dir( $backup_dir ) ) {
            @rename( $backup_dir, $content_dir );
        }
        $wp_filesystem->rmdir( $staging_dir, true );
        return new WP_Error( 'install_failed', 'Could not move new content into place; previous version restored.', array( 'status' => 500 ) );
    }

    // Cleanup staging shell (after the inner dir has been moved out)
    $wp_filesystem->rmdir( $staging_dir, true );

    // Defer backup cleanup so this request returns 200 first
    if ( $had_existing && $wp_filesystem->is_dir( $backup_dir ) ) {
        wp_schedule_single_event( time() + 30, 'dtb_cleanup_old_content', array( $backup_dir ) );
    }

    // Notify other code paths
    do_action( 'dtb_content_updated', $manifest_version, array(
        'actor'          => $deploy_actor,
        'github_actor'   => $github_actor,
        'commit_sha'     => $commit_sha,
        'repo_full_name' => $repo_full_name,
        'run_id'         => $run_id,
        'files'          => $files_count,
        'ip'             => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',
    ) );

    // Count files for the response
    $files_count = dtb_count_files_recursive( $content_dir );

    return array(
        'success'     => true,
        'message'     => 'Website content updated successfully.',
        'version'     => $manifest_version,
        'files_count' => $files_count,
        'path'        => $content_dir,
    );
}

/**
 * Cleanup hook for old dtb-site backup directories.
 * Scheduled by content-update with a 30s delay.
 */
add_action( 'dtb_cleanup_old_content', 'dtb_cleanup_old_content_handler' );
function dtb_cleanup_old_content_handler( $path ) {
    if ( empty( $path ) || ! is_string( $path ) ) {
        return;
    }
    $uploads = wp_get_upload_dir();
    $base    = trailingslashit( $uploads['basedir'] );
    // Defence in depth: only allow paths inside uploads basedir whose name
    // matches our backup prefix.
    if ( strpos( $path, $base . 'dtb-site-old-' ) !== 0 ) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    global $wp_filesystem;
    if ( $wp_filesystem && $wp_filesystem->is_dir( $path ) ) {
        $wp_filesystem->rmdir( $path, true );
    }
}

/**
 * Recursively count files in a directory.
 */
function dtb_count_files_recursive( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return 0;
    }
    $count = 0;
    $items = scandir( $dir );
    if ( ! $items ) return 0;

    foreach ( $items as $item ) {
        if ( $item === '.' || $item === '..' ) continue;
        $path = $dir . '/' . $item;
        if ( is_dir( $path ) ) {
            $count += dtb_count_files_recursive( $path );
        } else {
            $count++;
        }
    }
    return $count;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Content status (read installed version + manifest)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_content_status( $request ) {
    $installed = function_exists( 'hozio_dtb_content_installed' ) && hozio_dtb_content_installed();

    $response = array(
        'installed' => $installed,
        'path'      => function_exists( 'hozio_dtb_content_path' ) ? hozio_dtb_content_path() : null,
        'url'       => function_exists( 'hozio_dtb_content_url' ) ? hozio_dtb_content_url() : null,
        'version'   => function_exists( 'hozio_dtb_content_version' ) ? hozio_dtb_content_version() : '',
    );

    if ( $installed && function_exists( 'hozio_dtb_content_path' ) ) {
        $manifest_path = hozio_dtb_content_path() . 'manifest.json';
        if ( file_exists( $manifest_path ) ) {
            $data = json_decode( (string) file_get_contents( $manifest_path ), true );
            if ( is_array( $data ) ) {
                $response['manifest'] = $data;
            }
        }
    }

    return $response;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Elementor data read / write
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_elementor_read( $request ) {
    $post_id = absint( $request['id'] );
    $post    = get_post( $post_id );

    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
    }

    $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
    $edit_mode      = get_post_meta( $post_id, '_elementor_edit_mode', true );
    $template_type  = get_post_meta( $post_id, '_elementor_template_type', true );
    $page_settings  = get_post_meta( $post_id, '_elementor_page_settings', true );

    // Parse the JSON string if it's a string
    $parsed = null;
    if ( is_string( $elementor_data ) && ! empty( $elementor_data ) ) {
        $parsed = json_decode( $elementor_data, true );
    } elseif ( is_array( $elementor_data ) ) {
        $parsed = $elementor_data;
    }

    return array(
        'post_id'       => $post_id,
        'post_title'    => $post->post_title,
        'post_type'     => $post->post_type,
        'edit_mode'     => $edit_mode ?: null,
        'template_type' => $template_type ?: null,
        'page_settings' => $page_settings ?: null,
        'elementor_data' => $parsed,
        'raw_length'    => is_string( $elementor_data ) ? strlen( $elementor_data ) : null,
    );
}

function dtb_rest_elementor_write( $request ) {
    $post_id = absint( $request['id'] );
    $post    = get_post( $post_id );

    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
    }

    $body = $request->get_json_params();

    if ( empty( $body['elementor_data'] ) ) {
        return new WP_Error( 'missing_data', 'Request body must include elementor_data (array).', array( 'status' => 400 ) );
    }

    $elementor_data = $body['elementor_data'];

    // Validate it's an array (Elementor expects a JSON array of elements)
    if ( ! is_array( $elementor_data ) ) {
        return new WP_Error( 'invalid_data', 'elementor_data must be a JSON array.', array( 'status' => 400 ) );
    }

    // Encode to JSON string for storage
    $json = wp_json_encode( $elementor_data );
    if ( $json === false ) {
        return new WP_Error( 'encode_failed', 'Failed to encode elementor_data as JSON.', array( 'status' => 500 ) );
    }

    // Write the Elementor data
    update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );

    // Set required Elementor meta fields so the page renders as an Elementor page
    update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

    // Set template type based on post type
    $template_type = $post->post_type === 'page' ? 'wp-page' : 'wp-post';
    if ( get_post_meta( $post_id, '_elementor_template_type', true ) === '' ) {
        update_post_meta( $post_id, '_elementor_template_type', $template_type );
    }

    // Set Elementor version stamp
    if ( defined( 'ELEMENTOR_VERSION' ) ) {
        update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
    }

    // Clear Elementor CSS cache for this post so it regenerates
    delete_post_meta( $post_id, '_elementor_css' );

    // Also write to post_content for SEO / search (Elementor stores a text fallback)
    if ( ! empty( $body['post_content'] ) ) {
        wp_update_post( array(
            'ID'           => $post_id,
            'post_content' => wp_kses_post( $body['post_content'] ),
        ) );
    }

    // Apply optional page settings
    if ( ! empty( $body['page_settings'] ) && is_array( $body['page_settings'] ) ) {
        $existing = get_post_meta( $post_id, '_elementor_page_settings', true ) ?: array();
        $merged   = array_replace_recursive( $existing, $body['page_settings'] );
        update_post_meta( $post_id, '_elementor_page_settings', $merged );
    }

    return array(
        'success'    => true,
        'post_id'    => $post_id,
        'data_size'  => strlen( $json ),
        'elements'   => count( $elementor_data ),
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Rewrite rules flush
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_flush_rewrite( $request ) {
    flush_rewrite_rules( false );
    return array(
        'success' => true,
        'message' => 'Rewrite rules flushed.',
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Theme install / activate / delete
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_install_theme( $request ) {
    $body = $request->get_json_params();
    $slug = isset( $body['slug'] ) ? sanitize_key( $body['slug'] ) : '';

    if ( ! $slug ) {
        return new WP_Error( 'missing_slug', 'Request body must include "slug" (theme slug on wordpress.org).', array( 'status' => 400 ) );
    }

    require_once ABSPATH . 'wp-admin/includes/theme.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $existing = wp_get_theme( $slug );
    if ( $existing->exists() ) {
        return array(
            'success'    => true,
            'slug'       => $slug,
            'installed'  => true,
            'already'    => true,
            'version'    => $existing->get( 'Version' ),
            'message'    => 'Theme already installed.',
        );
    }

    $api = themes_api( 'theme_information', array(
        'slug'   => $slug,
        'fields' => array( 'sections' => false ),
    ) );

    if ( is_wp_error( $api ) ) {
        return new WP_Error( 'themes_api_failed', $api->get_error_message(), array( 'status' => 502 ) );
    }

    if ( empty( $api->download_link ) ) {
        return new WP_Error( 'no_download', 'wordpress.org returned no download link for that slug.', array( 'status' => 502 ) );
    }

    $skin     = new Automatic_Upgrader_Skin();
    $upgrader = new Theme_Upgrader( $skin );
    $result   = $upgrader->install( $api->download_link );

    if ( is_wp_error( $result ) ) {
        return new WP_Error( 'install_failed', $result->get_error_message(), array( 'status' => 500 ) );
    }
    if ( $result === false ) {
        return new WP_Error( 'install_failed', 'Theme installation returned false.', array( 'status' => 500 ) );
    }

    $installed = wp_get_theme( $slug );

    return array(
        'success'   => true,
        'slug'      => $slug,
        'installed' => $installed->exists(),
        'already'   => false,
        'version'   => $installed->exists() ? $installed->get( 'Version' ) : null,
    );
}

function dtb_rest_activate_theme( $request ) {
    $body       = $request->get_json_params();
    $stylesheet = isset( $body['stylesheet'] ) ? sanitize_key( $body['stylesheet'] ) : '';

    if ( ! $stylesheet ) {
        return new WP_Error( 'missing_stylesheet', 'Request body must include "stylesheet" (theme directory name).', array( 'status' => 400 ) );
    }

    $theme = wp_get_theme( $stylesheet );
    if ( ! $theme->exists() ) {
        return new WP_Error( 'theme_not_installed', 'Theme "' . $stylesheet . '" is not installed.', array( 'status' => 404 ) );
    }

    if ( $theme->errors() ) {
        return new WP_Error( 'theme_errors', $theme->errors()->get_error_message(), array( 'status' => 500 ) );
    }

    switch_theme( $stylesheet );

    $active = wp_get_theme();

    return array(
        'success'     => true,
        'stylesheet'  => $active->get_stylesheet(),
        'template'    => $active->get_template(),
        'name'        => $active->get( 'Name' ),
        'version'     => $active->get( 'Version' ),
    );
}

function dtb_rest_delete_themes_except_active( $request ) {
    require_once ABSPATH . 'wp-admin/includes/theme.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();

    $active     = wp_get_theme();
    $active_dir = $active->get_stylesheet();
    $parent_dir = $active->get_template(); // for child themes, keep the parent too

    $all_themes = wp_get_themes();
    $deleted    = array();
    $kept       = array();
    $errors     = array();

    foreach ( $all_themes as $theme_slug => $theme_obj ) {
        if ( $theme_slug === $active_dir || $theme_slug === $parent_dir ) {
            $kept[] = $theme_slug;
            continue;
        }

        $result = delete_theme( $theme_slug );

        if ( is_wp_error( $result ) ) {
            $errors[] = array( 'slug' => $theme_slug, 'error' => $result->get_error_message() );
        } elseif ( $result === false ) {
            $errors[] = array( 'slug' => $theme_slug, 'error' => 'delete_theme returned false' );
        } else {
            $deleted[] = $theme_slug;
        }
    }

    return array(
        'success'     => empty( $errors ),
        'active'      => $active_dir,
        'parent_kept' => $parent_dir !== $active_dir ? $parent_dir : null,
        'deleted'     => $deleted,
        'kept'        => $kept,
        'errors'      => $errors,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ACF field VALUE writes
// (works around ACF Pro 6.8 not supporting REST `acf` param writes)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_acf_write( $request ) {
    if ( ! function_exists( 'update_field' ) ) {
        return new WP_Error( 'acf_missing', 'ACF is not active.', array( 'status' => 400 ) );
    }

    $body    = $request->get_json_params();
    $post_id = isset( $body['post_id'] ) ? absint( $body['post_id'] ) : 0;
    $fields  = isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : null;

    if ( ! $post_id ) {
        return new WP_Error( 'missing_post_id', 'Request body must include "post_id" (numeric).', array( 'status' => 400 ) );
    }

    if ( ! get_post( $post_id ) ) {
        return new WP_Error( 'post_not_found', 'Post ' . $post_id . ' not found.', array( 'status' => 404 ) );
    }

    if ( $fields === null ) {
        return new WP_Error( 'missing_fields', 'Request body must include "fields" (object of field_name => value pairs).', array( 'status' => 400 ) );
    }

    $written = array();
    $failed  = array();

    foreach ( $fields as $field_name => $value ) {
        $name   = sanitize_key( $field_name );
        $result = update_field( $name, $value, $post_id );

        if ( $result ) {
            $written[] = $name;
        } else {
            // update_field() returns false on failure OR when the new value matches the
            // existing value. Distinguish by reading back.
            $current = get_field( $name, $post_id );
            if ( $current === $value || ( is_array( $current ) && is_array( $value ) && $current == $value ) ) {
                $written[] = $name;
            } else {
                $failed[] = $name;
            }
        }
    }

    return array(
        'success' => empty( $failed ),
        'post_id' => $post_id,
        'written' => $written,
        'failed'  => $failed,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Cache flush
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_flush_cache( $request ) {
    $flushed = array();

    // 1. WordPress object cache
    wp_cache_flush();
    $flushed[] = 'wp_object_cache';

    // 2. Elementor CSS cache
    if ( class_exists( '\\Elementor\\Plugin' ) ) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
        $flushed[] = 'elementor_css';
    }

    // 3. SiteGround Speed Optimizer
    if ( function_exists( 'sg_cachepress_purge_everything' ) ) {
        sg_cachepress_purge_everything();
        $flushed[] = 'siteground';
    }

    // 4. WP Rocket
    if ( function_exists( 'rocket_clean_domain' ) ) {
        rocket_clean_domain();
        $flushed[] = 'wp_rocket';
    }

    // 5. WP Super Cache
    if ( function_exists( 'wp_cache_clear_cache' ) ) {
        wp_cache_clear_cache();
        $flushed[] = 'wp_super_cache';
    }

    // 6. LiteSpeed Cache
    if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
        LiteSpeed_Cache_API::purge_all();
        $flushed[] = 'litespeed';
    }

    // 7. W3 Total Cache
    if ( function_exists( 'w3tc_flush_all' ) ) {
        w3tc_flush_all();
        $flushed[] = 'w3_total_cache';
    }

    return array(
        'success' => true,
        'flushed' => $flushed,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// WordPress option read / write (allowlisted)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

/**
 * Option allowlist. Only these keys can be read/written via the API.
 * Prevents arbitrary option manipulation.
 */
function dtb_allowed_options() {
    return array(
        // Elementor experiments
        'elementor_experiment-container',
        'elementor_experiment-e_flexbox_container',
        'elementor_experiment-e_element_cache',
        'elementor_experiment-e_nested_atomic_repeaters',
        'elementor_experiment-e_optimized_css_loading',
        'elementor_experiment-e_font_icon_svg',
        'elementor_experiment-additional_custom_breakpoints',
        // Elementor settings
        'elementor_active_kit',
        'elementor_disable_color_schemes',
        'elementor_disable_typography_schemes',
        'elementor_cpt_support',
        // WordPress core
        'blogname',
        'blogdescription',
        'siteurl',
        'home',
        'permalink_structure',
        'show_on_front',
        'page_on_front',
        'page_for_posts',
        'wp_page_for_privacy_policy',
        'posts_per_page',
        'default_comment_status',
        'default_ping_status',
        'blog_public',
        'site_icon',
        'site_logo',
        'timezone_string',
        // Reading / writing settings
        'use_smilies',
        'date_format',
        'time_format',
        'start_of_week',
        // Theme
        'template',
        'stylesheet',
        // Media sizes
        'thumbnail_size_w',
        'thumbnail_size_h',
        'medium_size_w',
        'medium_size_h',
        'large_size_w',
        'large_size_h',
        'medium_large_size_w',
        'medium_large_size_h',
        // Comments
        'comment_registration',
        'require_name_email',
        'close_comments_for_old_posts',
        'close_comments_days_old',
        'thread_comments',
        'show_avatars',
        // WooCommerce
        'woocommerce_permalinks',
        // Yoast SEO (read-merge-write semantics enforced by the dtb_deploy yoast subcommand)
        'wpseo',
        'wpseo_titles',
        'wpseo_social',
    );
}

function dtb_rest_option_read( $request ) {
    $key = sanitize_key( $request['key'] );

    if ( ! in_array( $key, dtb_allowed_options(), true ) ) {
        return new WP_Error(
            'option_not_allowed',
            'Option "' . $key . '" is not in the DTB allowlist.',
            array( 'status' => 403 )
        );
    }

    $value = get_option( $key );

    return array(
        'key'    => $key,
        'value'  => $value,
        'exists' => $value !== false,
    );
}

function dtb_rest_option_write( $request ) {
    $key  = sanitize_key( $request['key'] );
    $body = $request->get_json_params();

    if ( ! in_array( $key, dtb_allowed_options(), true ) ) {
        return new WP_Error(
            'option_not_allowed',
            'Option "' . $key . '" is not in the DTB allowlist.',
            array( 'status' => 403 )
        );
    }

    if ( ! isset( $body['value'] ) ) {
        return new WP_Error( 'missing_value', 'Request body must include "value".', array( 'status' => 400 ) );
    }

    $old_value = get_option( $key );
    $result    = update_option( $key, $body['value'] );

    return array(
        'success'   => true,
        'key'       => $key,
        'old_value' => $old_value,
        'new_value' => $body['value'],
        'changed'   => $result,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Elementor Kit settings
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_kit_read( $request ) {
    $kit_id = get_option( 'elementor_active_kit' );

    if ( ! $kit_id ) {
        return new WP_Error( 'no_kit', 'No active Elementor Kit found.', array( 'status' => 404 ) );
    }

    $settings = get_post_meta( $kit_id, '_elementor_page_settings', true );

    return array(
        'kit_id'  => (int) $kit_id,
        'settings' => $settings ?: new stdClass(),
    );
}

function dtb_rest_kit_write( $request ) {
    $kit_id = get_option( 'elementor_active_kit' );

    if ( ! $kit_id ) {
        return new WP_Error( 'no_kit', 'No active Elementor Kit found.', array( 'status' => 404 ) );
    }

    $body = $request->get_json_params();

    if ( empty( $body['settings'] ) || ! is_array( $body['settings'] ) ) {
        return new WP_Error( 'missing_settings', 'Request body must include "settings" (object).', array( 'status' => 400 ) );
    }

    // Merge, don't replace — preserves lightbox settings, breakpoints, etc.
    $existing = get_post_meta( $kit_id, '_elementor_page_settings', true ) ?: array();
    $merged   = array_replace_recursive( $existing, $body['settings'] );
    update_post_meta( $kit_id, '_elementor_page_settings', $merged );

    // Clear Elementor CSS cache for the Kit post
    delete_post_meta( $kit_id, '_elementor_css' );

    return array(
        'success'  => true,
        'kit_id'   => (int) $kit_id,
        'settings' => $merged,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Default content cleanup
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_delete_default_content( $request ) {
    $deleted = array();
    $skipped = array();

    // Posts: "Hello world!" sample post
    $hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
    if ( $hello ) {
        $r = wp_delete_post( $hello->ID, true );
        $r ? $deleted[] = array( 'type' => 'post', 'id' => $hello->ID, 'title' => $hello->post_title )
           : $skipped[] = array( 'type' => 'post', 'id' => $hello->ID, 'reason' => 'wp_delete_post returned false' );
    }

    // Pages: "Sample Page", default Privacy Policy draft, anything titled "Sample Page" by Elementor
    $sample_titles = array( 'sample-page', 'privacy-policy' );
    foreach ( $sample_titles as $slug ) {
        $page = get_page_by_path( $slug );
        if ( ! $page ) continue;

        // Privacy Policy: only delete if it's the default-created auto-draft (status draft, untouched)
        if ( $slug === 'privacy-policy' ) {
            if ( $page->post_status !== 'draft' ) {
                $skipped[] = array( 'type' => 'page', 'id' => $page->ID, 'reason' => 'Privacy Policy is not in draft status; preserving' );
                continue;
            }
        }

        $r = wp_delete_post( $page->ID, true );
        $r ? $deleted[] = array( 'type' => 'page', 'id' => $page->ID, 'title' => $page->post_title )
           : $skipped[] = array( 'type' => 'page', 'id' => $page->ID, 'reason' => 'wp_delete_post returned false' );
    }

    // Elementor seeds a sample page in the elementor_library CPT on first activation.
    // Find by the standard title prefix and delete.
    $elementor_samples = get_posts( array(
        'post_type'      => 'elementor_library',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'title'          => 'Sample Page',
    ) );
    if ( ! is_wp_error( $elementor_samples ) ) {
        foreach ( $elementor_samples as $sample ) {
            $r = wp_delete_post( $sample->ID, true );
            $r ? $deleted[] = array( 'type' => 'elementor_library', 'id' => $sample->ID, 'title' => $sample->post_title )
               : $skipped[] = array( 'type' => 'elementor_library', 'id' => $sample->ID, 'reason' => 'wp_delete_post returned false' );
        }
    }

    // Default comments (mostly the "Hi, this is a comment" sample on Hello world!)
    $comments = get_comments( array(
        'status' => 'all',
        'number' => 100,
    ) );
    foreach ( $comments as $c ) {
        $r = wp_delete_comment( $c->comment_ID, true );
        if ( $r ) {
            $deleted[] = array( 'type' => 'comment', 'id' => (int) $c->comment_ID );
        }
    }

    // Empty the trash globally to enforce hard-delete policy
    if ( function_exists( 'wp_clean_themes_cache' ) ) {
        // (No-op call kept off to avoid theme cache wipe; trash already empty after force=true.)
    }

    return array(
        'success'      => true,
        'deleted'      => $deleted,
        'deleted_count' => count( $deleted ),
        'skipped'      => $skipped,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Site icons (logo + favicon)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_site_icons( $request ) {
    $body = $request->get_json_params();

    $logo_id    = isset( $body['logo_id'] ) ? absint( $body['logo_id'] ) : 0;
    $favicon_id = isset( $body['favicon_id'] ) ? absint( $body['favicon_id'] ) : 0;

    if ( ! $logo_id && ! $favicon_id ) {
        return new WP_Error( 'missing_ids', 'Provide logo_id and/or favicon_id (numeric media IDs).', array( 'status' => 400 ) );
    }

    $written = array();

    if ( $logo_id ) {
        if ( ! get_post( $logo_id ) || get_post_type( $logo_id ) !== 'attachment' ) {
            return new WP_Error( 'logo_not_found', 'logo_id is not a valid attachment.', array( 'status' => 404 ) );
        }
        update_option( 'site_logo', $logo_id );
        $written['site_logo'] = $logo_id;
    }

    if ( $favicon_id ) {
        if ( ! get_post( $favicon_id ) || get_post_type( $favicon_id ) !== 'attachment' ) {
            return new WP_Error( 'favicon_not_found', 'favicon_id is not a valid attachment.', array( 'status' => 404 ) );
        }
        update_option( 'site_icon', $favicon_id );
        $written['site_icon'] = $favicon_id;
    }

    return array(
        'success' => true,
        'written' => $written,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Menus reconciliation
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_menus( $request ) {
    $body  = $request->get_json_params();
    $menus = isset( $body['menus'] ) && is_array( $body['menus'] ) ? $body['menus'] : null;

    if ( $menus === null ) {
        return new WP_Error( 'missing_menus', 'Request body must include "menus" (array).', array( 'status' => 400 ) );
    }

    $results = array();

    foreach ( $menus as $menu_def ) {
        $menu_name = isset( $menu_def['name'] ) ? sanitize_text_field( $menu_def['name'] ) : '';
        $location  = isset( $menu_def['location'] ) ? sanitize_key( $menu_def['location'] ) : '';
        $items     = isset( $menu_def['items'] ) && is_array( $menu_def['items'] ) ? $menu_def['items'] : array();

        if ( ! $menu_name ) {
            $results[] = array( 'status' => 'error', 'error' => 'menu missing "name"' );
            continue;
        }

        // Find or create the menu
        $menu = wp_get_nav_menu_object( $menu_name );
        if ( ! $menu ) {
            $menu_id = wp_create_nav_menu( $menu_name );
            if ( is_wp_error( $menu_id ) ) {
                $results[] = array( 'status' => 'error', 'name' => $menu_name, 'error' => $menu_id->get_error_message() );
                continue;
            }
            $menu = wp_get_nav_menu_object( $menu_id );
        }

        // Wipe existing items (we reconcile, not append-only)
        $existing_items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) ) ?: array();
        foreach ( $existing_items as $existing ) {
            wp_delete_post( $existing->ID, true );
        }

        // Insert items in manifest order, returning the new item IDs by manifest index
        $created = dtb_menus_insert_items( $menu->term_id, $items, 0 );

        // Wire up to a theme location if provided
        if ( $location ) {
            $locations = get_theme_mod( 'nav_menu_locations', array() );
            $locations[ $location ] = $menu->term_id;
            set_theme_mod( 'nav_menu_locations', $locations );
        }

        $results[] = array(
            'status'  => 'ok',
            'name'    => $menu_name,
            'menu_id' => $menu->term_id,
            'items'   => count( $created ),
        );
    }

    return array(
        'success' => true,
        'results' => $results,
    );
}

/**
 * Insert menu items recursively. Items with "dead_link": true become custom
 * items with url="#" (rendered as <span> by the header template).
 */
function dtb_menus_insert_items( $menu_id, $items, $parent_id ) {
    $created = array();

    foreach ( $items as $item ) {
        $title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
        if ( ! $title ) continue;

        $args = array(
            'menu-item-title'     => $title,
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => $parent_id,
        );

        if ( ! empty( $item['dead_link'] ) ) {
            $args['menu-item-type'] = 'custom';
            $args['menu-item-url']  = '#';
        } elseif ( ! empty( $item['page_slug'] ) ) {
            $page = get_page_by_path( sanitize_title( $item['page_slug'] ) );
            if ( ! $page ) {
                continue; // Skip if page doesn't exist; menu reconciliation continues
            }
            $args['menu-item-type']      = 'post_type';
            $args['menu-item-object']    = 'page';
            $args['menu-item-object-id'] = $page->ID;
        } elseif ( ! empty( $item['url'] ) ) {
            $args['menu-item-type'] = 'custom';
            $args['menu-item-url']  = esc_url_raw( $item['url'] );
        } else {
            continue; // Item has no resolvable target
        }

        $item_id = wp_update_nav_menu_item( $menu_id, 0, $args );

        if ( is_wp_error( $item_id ) || ! $item_id ) continue;

        $created[] = $item_id;

        // Recurse into children
        if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
            $created = array_merge(
                $created,
                dtb_menus_insert_items( $menu_id, $item['children'], $item_id )
            );
        }
    }

    return $created;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Page CRUD (enforces §0.5 page-structure standard)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_page_create( $request ) {
    $body = $request->get_json_params();

    $title         = isset( $body['title'] ) ? sanitize_text_field( $body['title'] ) : '';
    $slug          = isset( $body['slug'] ) ? sanitize_title( $body['slug'] ) : '';
    $template_name = isset( $body['template'] ) ? sanitize_text_field( $body['template'] ) : '';
    $post_type     = isset( $body['post_type'] ) ? sanitize_key( $body['post_type'] ) : 'page';

    if ( ! $title ) {
        return new WP_Error( 'missing_title', 'Request body must include "title".', array( 'status' => 400 ) );
    }

    // Avoid silent collisions
    if ( $slug ) {
        $existing = get_page_by_path( $slug, OBJECT, $post_type );
        if ( $existing ) {
            return new WP_Error( 'slug_in_use', 'Slug "' . $slug . '" is already in use by post ID ' . $existing->ID . '. Delete first or pick another slug.', array( 'status' => 409 ) );
        }
    }

    $insert = array(
        'post_title'   => $title,
        'post_name'    => $slug ?: '',
        'post_status'  => 'publish',
        'post_type'    => $post_type,
        'post_content' => '',
    );

    $post_id = wp_insert_post( $insert, true );
    if ( is_wp_error( $post_id ) ) {
        return new WP_Error( 'insert_failed', $post_id->get_error_message(), array( 'status' => 500 ) );
    }

    // §0.5 page-structure standard: only applies to "page" post type.
    // Pages get _wp_page_template = elementor_canvas + a single full-width
    // container with one HTML widget seeded by [template name="..."].
    if ( $post_type === 'page' ) {
        update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );
    }

    if ( $template_name ) {
        $shortcode      = '[template name="' . esc_attr( $template_name ) . '"]';
        $elementor_data = dtb_build_canvas_seed( $shortcode );

        update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        update_post_meta( $post_id, '_elementor_template_type', $post_type === 'page' ? 'wp-page' : 'wp-post' );
        if ( defined( 'ELEMENTOR_VERSION' ) ) {
            update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
        }
        delete_post_meta( $post_id, '_elementor_css' );
    }

    $post = get_post( $post_id );

    return array(
        'success'  => true,
        'id'       => $post_id,
        'slug'     => $post->post_name,
        'link'     => get_permalink( $post_id ),
        'template' => $template_name ?: null,
        'post_type' => $post_type,
    );
}

/**
 * Build the canonical "Elementor Canvas + full-width container + HTML widget"
 * seed structure. Used by /page POST to enforce §0.5 page-structure standard.
 */
function dtb_build_canvas_seed( $html_content ) {
    $widget_id    = wp_generate_password( 8, false );
    $container_id = wp_generate_password( 8, false );

    return array(
        array(
            'id'       => $container_id,
            'elType'   => 'container',
            'isInner'  => false,
            'settings' => array(
                'content_width' => 'full',
                'padding'       => array(
                    'unit'   => 'px',
                    'top'    => '0',
                    'right'  => '0',
                    'bottom' => '0',
                    'left'   => '0',
                ),
                'margin'        => array(
                    'unit'   => 'px',
                    'top'    => '0',
                    'right'  => '0',
                    'bottom' => '0',
                    'left'   => '0',
                ),
            ),
            'elements' => array(
                array(
                    'id'         => $widget_id,
                    'elType'     => 'widget',
                    'widgetType' => 'html',
                    'settings'   => array( 'html' => $html_content ),
                    'elements'   => array(),
                ),
            ),
        ),
    );
}

function dtb_rest_page_delete( $request ) {
    $id   = absint( $request['id'] );
    $post = get_post( $id );

    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Post ' . $id . ' not found.', array( 'status' => 404 ) );
    }

    $result = wp_delete_post( $id, true );

    if ( ! $result ) {
        return new WP_Error( 'delete_failed', 'wp_delete_post returned false for post ' . $id, array( 'status' => 500 ) );
    }

    return array(
        'success' => true,
        'id'      => $id,
        'title'   => $post->post_title,
        'type'    => $post->post_type,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ACF group location-rule rewiring
// (Hozio source-site IDs -> current client's page IDs)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_rewire_acf_groups( $request ) {
    if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_update_field_group' ) ) {
        return new WP_Error( 'acf_missing', 'ACF is not active.', array( 'status' => 400 ) );
    }

    // Group title -> expected page slug. Only groups with `param=page` rules are rewired;
    // groups using post_type or post_taxonomy are left alone.
    $title_to_slug = array(
        'Home'     => 'home',
        'About Us' => 'about',
        'FAQs'     => 'faq',
    );

    $rewired = array();
    $skipped = array();
    $errors  = array();

    foreach ( acf_get_field_groups() as $group ) {
        $title = isset( $group['title'] ) ? $group['title'] : '';
        if ( ! isset( $title_to_slug[ $title ] ) ) {
            $skipped[] = array( 'title' => $title, 'reason' => 'group title not in rewire map' );
            continue;
        }

        $expected_slug = $title_to_slug[ $title ];
        $page = get_page_by_path( $expected_slug );

        if ( ! $page ) {
            $errors[] = array(
                'title'  => $title,
                'reason' => 'expected page with slug "' . $expected_slug . '" not found — create it before rewiring',
            );
            continue;
        }

        $changed = false;
        $location = isset( $group['location'] ) && is_array( $group['location'] ) ? $group['location'] : array();

        foreach ( $location as $i => $rule_group ) {
            if ( ! is_array( $rule_group ) ) continue;
            foreach ( $rule_group as $j => $rule ) {
                if ( ( $rule['param'] ?? '' ) === 'page' ) {
                    $current_value = (string) ( $rule['value'] ?? '' );
                    $new_value     = (string) $page->ID;
                    if ( $current_value !== $new_value ) {
                        $location[ $i ][ $j ]['value'] = $new_value;
                        $changed = true;
                    }
                }
            }
        }

        if ( ! $changed ) {
            $skipped[] = array( 'title' => $title, 'reason' => 'rules already match current page ID ' . $page->ID );
            continue;
        }

        $group['location'] = $location;
        $update_result = acf_update_field_group( $group );

        if ( $update_result ) {
            $rewired[] = array(
                'title'   => $title,
                'page_id' => $page->ID,
                'slug'    => $expected_slug,
            );
        } else {
            $errors[] = array( 'title' => $title, 'reason' => 'acf_update_field_group returned false' );
        }
    }

    return array(
        'success' => empty( $errors ),
        'rewired' => $rewired,
        'skipped' => $skipped,
        'errors'  => $errors,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Yoast per-page title and description
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_yoast_page( $request ) {
    $body        = $request->get_json_params();
    $post_id     = isset( $body['post_id'] ) ? absint( $body['post_id'] ) : 0;
    $title       = array_key_exists( 'title', $body ) ? $body['title'] : null;
    $description = array_key_exists( 'description', $body ) ? $body['description'] : null;

    if ( ! $post_id ) {
        return new WP_Error( 'missing_post_id', 'Request body must include "post_id".', array( 'status' => 400 ) );
    }

    if ( ! get_post( $post_id ) ) {
        return new WP_Error( 'post_not_found', 'Post ' . $post_id . ' not found.', array( 'status' => 404 ) );
    }

    if ( $title === null && $description === null ) {
        return new WP_Error( 'nothing_to_write', 'Provide "title" and/or "description".', array( 'status' => 400 ) );
    }

    $written = array();

    if ( $title !== null ) {
        $title = (string) $title;
        update_post_meta( $post_id, '_yoast_wpseo_title', $title );
        $written['title'] = $title;
    }

    if ( $description !== null ) {
        $description = (string) $description;
        $len = mb_strlen( $description );
        $warnings = array();
        if ( $len >= 155 ) {
            $warnings[] = 'description is ' . $len . ' chars (>=155); Google may truncate at the SERP';
        }
        if ( $len > 160 ) {
            $warnings[] = 'description exceeds Google\'s 160-char effective max';
        }
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $description );
        $written['description']        = $description;
        $written['description_length'] = $len;
        if ( $warnings ) {
            $written['warnings'] = $warnings;
        }
    }

    return array(
        'success' => true,
        'post_id' => $post_id,
        'written' => $written,
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Health check / status
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function dtb_rest_status( $request ) {
    $status = array(
        'plugin'           => 'hozio-dynamic-tags',
        'plugin_version'   => defined( 'HOZIO_VERSION' ) ? HOZIO_VERSION : 'unknown',
        'php'              => PHP_VERSION,
        'wordpress'        => get_bloginfo( 'version' ),
        'site_url'         => get_site_url(),
        'api_ns'           => 'dtb/v1',
    );

    // DTB website-content state
    if ( function_exists( 'hozio_dtb_content_installed' ) ) {
        $status['content_installed'] = (bool) hozio_dtb_content_installed();
        $status['content_version']   = function_exists( 'hozio_dtb_content_version' ) ? hozio_dtb_content_version() : '';
        $status['content_path']      = function_exists( 'hozio_dtb_content_path' ) ? hozio_dtb_content_path() : null;
    }

    // Theme info (active theme)
    $theme = wp_get_theme();
    $status['theme'] = array(
        'name'       => $theme->get( 'Name' ),
        'stylesheet' => $theme->get_stylesheet(),
        'template'   => $theme->get_template(),
        'version'    => $theme->get( 'Version' ),
    );

    // Elementor info
    if ( defined( 'ELEMENTOR_VERSION' ) ) {
        $status['elementor']     = ELEMENTOR_VERSION;
        $status['elementor_pro'] = defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null;
        $status['active_kit']    = (int) get_option( 'elementor_active_kit', 0 );
        $status['container_experiment'] = get_option( 'elementor_experiment-container', 'default' );
        $status['element_cache']        = get_option( 'elementor_experiment-e_element_cache', 'default' );
    }

    // Site icons
    $status['site_logo'] = (int) get_option( 'site_logo', 0 );
    $status['site_icon'] = (int) get_option( 'site_icon', 0 );

    // Default content state — flags whether each artifact is still around
    $hello = get_page_by_path( 'hello-world', OBJECT, 'post' );
    $sample = get_page_by_path( 'sample-page' );
    $privacy = get_page_by_path( 'privacy-policy' );
    $elementor_samples = function_exists( 'get_posts' ) ? get_posts( array(
        'post_type'      => 'elementor_library',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'title'          => 'Sample Page',
        'fields'         => 'ids',
    ) ) : array();

    $status['default_content'] = array(
        'hello_world_post'   => $hello ? array( 'id' => $hello->ID ) : null,
        'sample_page'        => $sample ? array( 'id' => $sample->ID ) : null,
        'privacy_policy_draft' => ( $privacy && $privacy->post_status === 'draft' ) ? array( 'id' => $privacy->ID ) : null,
        'elementor_sample'   => ! empty( $elementor_samples ) ? array( 'id' => (int) $elementor_samples[0] ) : null,
    );

    // Pages summary
    $pages = get_posts( array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
    ) );
    $status['pages'] = array(
        'count'    => count( $pages ),
        'slugs'    => array_map( function( $p ) { return $p->post_name; }, $pages ),
    );

    // Sitemap (Yoast emits sitemap_index.xml; URL only — client checks reachability)
    $status['sitemap_url'] = home_url( '/sitemap_index.xml' );

    // WooCommerce
    if ( defined( 'WC_VERSION' ) ) {
        $status['woocommerce'] = WC_VERSION;
    }

    // ACF
    if ( function_exists( 'acf_get_setting' ) ) {
        $status['acf'] = acf_get_setting( 'version' );
    }

    // Cache plugins detected
    $caches = array();
    if ( function_exists( 'sg_cachepress_purge_everything' ) ) $caches[] = 'siteground';
    if ( function_exists( 'rocket_clean_domain' ) )            $caches[] = 'wp_rocket';
    if ( function_exists( 'wp_cache_clear_cache' ) )           $caches[] = 'wp_super_cache';
    if ( class_exists( 'LiteSpeed_Cache_API' ) )               $caches[] = 'litespeed';
    if ( function_exists( 'w3tc_flush_all' ) )                 $caches[] = 'w3_total_cache';
    $status['cache_plugins'] = $caches;

    return $status;
}
