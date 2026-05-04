<?php
/**
 * Site Connection — read-only status panel.
 *
 * After the deploy architecture rewrite, this tab no longer holds any
 * inputs, credentials, or configuration. All deploy authentication is
 * driven by the HOZIO_DEPLOY_SECRET wp-config.php constant + GitHub
 * Actions (see Deploy Architecture v2 plan). This file just renders
 * status so an authorized operator can confirm the site is set up
 * correctly and see recent deploy activity.
 *
 * Three panels:
 *   1. Deploy auth status — is HOZIO_DEPLOY_SECRET defined? (no value shown)
 *   2. Recent deploys — last 10 successful content-updates with timestamp,
 *      commit SHA, actor, IP, files count
 *   3. Engine health — existing in-process REST status check
 *
 * Access control:
 *   - Tab visibility is gated by the HOZIO_DTB_AUTHORIZED_LOGINS constant
 *     (see plugin-settings.php)
 *   - Every AJAX endpoint enforces the same gate via
 *     hozio_dtb_assert_can_view_ajax()
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const HOZIO_DTB_DEPLOY_HISTORY_OPT = 'hozio_dtb_deploy_history';
const HOZIO_DTB_DEPLOY_HISTORY_MAX = 10;

// ─── Authorization gate ──────────────────────────────────────────────────
//
// HOZIO_DTB_AUTHORIZED_LOGINS (CSV in wp-config.php) restricts the tab
// to specific user_logins. If unset, falls back to "any administrator".

if ( ! function_exists( 'hozio_dtb_user_can_see_site_connection' ) ) {
    function hozio_dtb_user_can_see_site_connection() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        if ( ! defined( 'HOZIO_DTB_AUTHORIZED_LOGINS' ) || HOZIO_DTB_AUTHORIZED_LOGINS === '' ) {
            return true;
        }
        $current = wp_get_current_user();
        if ( ! $current || empty( $current->user_login ) ) {
            return false;
        }
        $allowed = array_filter( array_map( 'trim', explode( ',', (string) HOZIO_DTB_AUTHORIZED_LOGINS ) ) );
        return in_array( $current->user_login, $allowed, true );
    }
}

if ( ! function_exists( 'hozio_dtb_assert_can_view_ajax' ) ) {
    function hozio_dtb_assert_can_view_ajax() {
        if ( ! hozio_dtb_user_can_see_site_connection() ) {
            wp_send_json_error( 'Unauthorized.', 403 );
        }
        check_ajax_referer( 'hozio_dtb_site_connection', 'nonce' );
    }
}

// ─── Deploy history log ──────────────────────────────────────────────────
//
// Hooked to the dtb_content_updated action so EVERY successful deploy
// (admin or HMAC) appends an entry. Capped at HOZIO_DTB_DEPLOY_HISTORY_MAX
// entries via FIFO eviction.

add_action( 'dtb_content_updated', 'hozio_dtb_record_deploy', 10, 2 );
function hozio_dtb_record_deploy( $version, $context = array() ) {
    $entry = array(
        'when'           => time(),
        'version'        => (string) $version,
        'actor'          => isset( $context['actor'] ) ? (string) $context['actor'] : 'unknown',
        'github_actor'   => isset( $context['github_actor'] ) ? (string) $context['github_actor'] : '',
        'commit_sha'     => isset( $context['commit_sha'] ) ? (string) $context['commit_sha'] : '',
        'repo_full_name' => isset( $context['repo_full_name'] ) ? (string) $context['repo_full_name'] : '',
        'run_id'         => isset( $context['run_id'] ) ? (string) $context['run_id'] : '',
        'files'          => isset( $context['files'] ) ? (int) $context['files'] : 0,
        'ip'             => isset( $context['ip'] ) ? (string) $context['ip'] : '',
    );

    $history = get_option( HOZIO_DTB_DEPLOY_HISTORY_OPT, array() );
    if ( ! is_array( $history ) ) {
        $history = array();
    }
    array_unshift( $history, $entry );
    if ( count( $history ) > HOZIO_DTB_DEPLOY_HISTORY_MAX ) {
        $history = array_slice( $history, 0, HOZIO_DTB_DEPLOY_HISTORY_MAX );
    }
    update_option( HOZIO_DTB_DEPLOY_HISTORY_OPT, $history, false );

    // Track the most-recent connected repo so the status panel can show
    // "Connected to repo X" without parsing history every render.
    if ( ! empty( $entry['repo_full_name'] ) ) {
        update_option( 'hozio_dtb_connected_repo', $entry['repo_full_name'], false );
    }
}

// ─── Config → options sync ───────────────────────────────────────────────
//
// After every successful deploy, pull values from the freshly-deployed
// hozio-config.php into wp_options so the Dynamic Tags Settings page
// reflects the repo's contact info without any manual entry.

add_action( 'dtb_content_updated', 'hozio_dtb_sync_config_to_options', 20, 2 );
function hozio_dtb_sync_config_to_options( $version, $context = array() ) {
    // Mapping: hozio-config.php key → wp_options key
    $map = array(
        'company-phone-1'        => 'hozio_company_phone_1',
        'company-phone-2'        => 'hozio_company_phone_2',
        'google-ads-phone'       => 'hozio_google_ads_phone',
        'sms-phone'              => 'hozio_sms_phone',
        'company-email'          => 'hozio_company_email',
        'to-email-contact-form'  => 'hozio_to_email_contact_form',
        'company-address'        => 'hozio_company_address',
        'business-hours'         => 'hozio_business_hours',
        'years-of-experience'    => 'hozio_start_year',
        'gmb-link'               => 'hozio_gmb_link',
        'facebook'               => 'hozio_facebook_url',
        'instagram'              => 'hozio_instagram_url',
        'twitter'                => 'hozio_twitter_url',
        'tiktok'                 => 'hozio_tiktok_url',
        'linkedin'               => 'hozio_linkedin_url',
        'youtube'                => 'hozio_youtube_url',
        'yelp'                   => 'hozio_yelp_url',
        'angies-list'            => 'hozio_angies_list_url',
        'home-advisor'           => 'hozio_home_advisor_url',
        'bbb'                    => 'hozio_bbb_url',
    );

    $tags = hozio_dtb_get_tags();
    if ( empty( $tags ) ) {
        return;
    }

    foreach ( $map as $config_key => $option_key ) {
        if ( array_key_exists( $config_key, $tags ) ) {
            update_option( $option_key, $tags[ $config_key ] );
        }
    }
}

// ─── AJAX: read deploy state ─────────────────────────────────────────────
//
// Returns a consolidated "where does this site stand" payload:
//   - secret_defined: HOZIO_DEPLOY_SECRET present in wp-config.php?
//   - connected_repo: most recent repo we've received deploys from
//   - last_deploy:    summary of the most recent successful deploy
//   - status:         one of ['not_configured', 'awaiting_first_deploy', 'connected']
//   - history:        the last N deploys (compact rows)
add_action( 'wp_ajax_hozio_dtb_deploy_state', 'hozio_dtb_ajax_deploy_state' );
function hozio_dtb_ajax_deploy_state() {
    hozio_dtb_assert_can_view_ajax();

    $secret_defined  = defined( 'HOZIO_DEPLOY_SECRET' ) && (string) HOZIO_DEPLOY_SECRET !== '';
    $connected_repo  = (string) get_option( 'hozio_dtb_connected_repo', '' );
    $history         = get_option( HOZIO_DTB_DEPLOY_HISTORY_OPT, array() );
    if ( ! is_array( $history ) ) {
        $history = array();
    }

    $rendered_history = array_map( function ( $entry ) {
        $when    = isset( $entry['when'] ) ? (int) $entry['when'] : 0;
        $repo    = isset( $entry['repo_full_name'] ) ? $entry['repo_full_name'] : '';
        $commit  = isset( $entry['commit_sha'] ) ? $entry['commit_sha'] : '';
        $run_id  = isset( $entry['run_id'] ) ? $entry['run_id'] : '';
        return array(
            'when'         => $when,
            'when_human'   => $when ? human_time_diff( $when, time() ) . ' ago' : '',
            'when_iso'     => $when ? gmdate( 'Y-m-d H:i:s', $when ) . ' UTC' : '',
            'actor'        => isset( $entry['actor'] ) ? $entry['actor'] : '',
            'github_actor' => isset( $entry['github_actor'] ) ? $entry['github_actor'] : '',
            'commit_sha'   => $commit,
            'commit_short' => $commit ? substr( $commit, 0, 7 ) : '',
            'commit_url'   => ( $repo && $commit ) ? "https://github.com/{$repo}/commit/{$commit}" : '',
            'run_url'      => ( $repo && $run_id ) ? "https://github.com/{$repo}/actions/runs/{$run_id}" : '',
            'repo'         => $repo,
            'files'        => isset( $entry['files'] ) ? (int) $entry['files'] : 0,
        );
    }, $history );

    // Roll up status
    if ( ! $secret_defined ) {
        $status = 'not_configured';
    } elseif ( empty( $rendered_history ) ) {
        $status = 'awaiting_first_deploy';
    } else {
        $status = 'connected';
    }
    $last = ! empty( $rendered_history ) ? $rendered_history[0] : null;

    wp_send_json_success( array(
        'status'             => $status,
        'secret_defined'     => $secret_defined,
        'connected_repo'     => $connected_repo,
        'connected_repo_url' => $connected_repo ? 'https://github.com/' . $connected_repo : '',
        'last_deploy'        => $last,
        'history'            => $rendered_history,
        'site_url'           => untrailingslashit( home_url() ),
    ) );
}

// ─── AJAX: engine self-test ─────────────────────────────────────────────
add_action( 'wp_ajax_hozio_dtb_test_engine', 'hozio_dtb_ajax_test_engine' );
function hozio_dtb_ajax_test_engine() {
    hozio_dtb_assert_can_view_ajax();

    // In-process REST call — works even if loopback is firewalled.
    $request  = new WP_REST_Request( 'GET', '/dtb/v1/status' );
    $response = rest_do_request( $request );

    if ( $response->is_error() ) {
        wp_send_json_error( $response->as_error()->get_error_message() );
    }

    wp_send_json_success( $response->get_data() );
}

/**
 * Render the Site Connection tab. Called from plugin-settings.php inside
 * the tab panel wrapper.
 */
function hozio_dtb_render_connection_tab() {
    $nonce = wp_create_nonce( 'hozio_dtb_site_connection' );
    ?>
    <div class="hozio-dtb-connection" data-nonce="<?php echo esc_attr( $nonce ); ?>">

        <!-- One consolidated card: connection status + last deploy + diagnostics -->
        <div id="hozio-dtb-status-root">Loading…</div>

    </div>

    <style>
    /* Status card */
    .hozio-dtb-status { background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:18px 20px; }
    .hozio-dtb-status.ok    { border-left:4px solid #10b981; }
    .hozio-dtb-status.warn  { border-left:4px solid #f59e0b; }
    .hozio-dtb-status.err   { border-left:4px solid #dc2626; }

    .hozio-dtb-headline { display:flex;align-items:center;gap:10px;font-size:15px;margin:0 0 4px;font-weight:600; }
    .hozio-dtb-headline.ok    { color:#065f46; }
    .hozio-dtb-headline.warn  { color:#92400e; }
    .hozio-dtb-headline.err   { color:#991b1b; }
    .hozio-dtb-headline .dashicons { font-size:20px;width:20px;height:20px; }
    .hozio-dtb-subhead { margin:0;color:#6b7280;font-size:12.5px; }

    .hozio-dtb-snippet {
        background:#0f172a;color:#e2e8f0;padding:10px 14px;border-radius:6px;
        font:12.5px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
        white-space:pre;overflow-x:auto;margin:10px 0;
    }
    .hozio-dtb-actions { margin-top:14px;display:flex;gap:8px;flex-wrap:wrap; }

    /* Last-deploy summary inside the status card */
    .hozio-dtb-last { margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;font-size:12.5px;color:#374151; }
    .hozio-dtb-last code { font-size:11.5px; }

    /* Recent deploys table (smaller, only shows when expanded) */
    .hozio-dtb-history-toggle {
        background:none;border:none;color:#3b82f6;cursor:pointer;font-size:12.5px;
        padding:0;margin-top:14px;text-decoration:underline;
    }
    .hozio-dtb-history-toggle:hover { color:#1d4ed8; }
    .hozio-dtb-history-wrap { margin-top:10px; }
    .hozio-dtb-history-tbl { width:100%;border-collapse:collapse;font-size:12.5px; }
    .hozio-dtb-history-tbl th,
    .hozio-dtb-history-tbl td { text-align:left;padding:6px 9px;border-bottom:1px solid #f1f5f9; }
    .hozio-dtb-history-tbl th { background:#f9fafb;font-weight:600;color:#374151;font-size:11.5px;text-transform:uppercase;letter-spacing:0.3px; }
    .hozio-dtb-history-tbl tr:last-child td { border-bottom:none; }
    .hozio-dtb-history-tbl a { color:#3b82f6;text-decoration:none; }
    .hozio-dtb-history-tbl a:hover { text-decoration:underline; }

    .hozio-dtb-pill { display:inline-block;padding:1px 7px;border-radius:9px;font-size:10px;font-weight:600;letter-spacing:.3px;text-transform:uppercase; }
    .hozio-dtb-pill-actions { background:#dbeafe;color:#1e3a8a; }
    .hozio-dtb-pill-admin { background:#fef3c7;color:#78350f; }
    .hozio-dtb-msg-success { color:#065f46; }
    .hozio-dtb-msg-error { color:#991b1b; }

    /* Diagnostics — collapsed by default */
    .hozio-dtb-diag { margin-top:18px; }
    @keyframes hozio-spin { to { transform:rotate(360deg); } }
    .hozio-spin { animation:hozio-spin .7s linear infinite;display:inline-block; }
    .hozio-dtb-diag-result { margin-top:12px;font-size:12.5px; }
    .hozio-dtb-status-grid { display:grid;grid-template-columns:max-content 1fr;gap:5px 14px;margin-top:8px;font-size:12px; }
    .hozio-dtb-status-grid dt { color:#6b7280;font-weight:500; }
    .hozio-dtb-status-grid dd { margin:0;color:#111827;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; }
    </style>

    <script>
    jQuery(function($) {
        var nonce = $('.hozio-dtb-connection').data('nonce');

        function escHtml(s) {
            return String(s == null ? '' : s)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }

        function refreshDeployState() {
            $.post(ajaxurl, { action: 'hozio_dtb_deploy_state', nonce: nonce })
                .done(function(resp) {
                    if (!resp.success) {
                        $('#hozio-dtb-status-root').html('<span class="hozio-dtb-msg-error">' + escHtml(resp.data) + '</span>');
                        return;
                    }
                    renderStatus(resp.data);
                })
                .fail(function() {
                    $('#hozio-dtb-status-root').html('<span class="hozio-dtb-msg-error">Network error.</span>');
                });
        }

        function renderStatus(d) {
            var $root = $('#hozio-dtb-status-root');

            // ── 3 possible top states ──
            var headlineHtml, subHtml, bodyHtml = '', cardClass;

            if (d.status === 'not_configured') {
                cardClass    = 'err';
                headlineHtml =
                    '<div class="hozio-dtb-headline err">' +
                        '<span class="dashicons dashicons-warning"></span>' +
                        'Not connected' +
                    '</div>';
                subHtml = '<p class="hozio-dtb-subhead">' +
                    'No deploy secret defined. Add this line to <code>wp-config.php</code>:' +
                '</p>';
                bodyHtml =
                    '<div class="hozio-dtb-snippet">' +
                        "define('HOZIO_DEPLOY_SECRET', '&lt;long random value&gt;');" +
                    '</div>' +
                    '<p style="margin:6px 0 0;font-size:12px;color:#6b7280;">' +
                        'Generate with <code>openssl rand -hex 32</code> and use the same value in your GitHub repo\'s <code>HOZIO_DEPLOY_SECRET</code> Actions secret.' +
                    '</p>';
            } else if (d.status === 'awaiting_first_deploy') {
                cardClass    = 'warn';
                headlineHtml =
                    '<div class="hozio-dtb-headline warn">' +
                        '<span class="dashicons dashicons-clock"></span>' +
                        'Configured · awaiting first deploy' +
                    '</div>';
                subHtml = '<p class="hozio-dtb-subhead">' +
                    'Secret is set. Push to the connected GitHub repo to trigger the first deploy. ' +
                    'Make sure the repo has <code>HOZIO_DEPLOY_SECRET</code> (Secret) and <code>HOZIO_DEPLOY_URL</code> (Variable) set with values matching this site.' +
                '</p>';
            } else { // connected
                cardClass    = 'ok';
                var repoLink = d.connected_repo_url
                    ? '<a href="' + escHtml(d.connected_repo_url) + '" target="_blank" rel="noopener"><code>' + escHtml(d.connected_repo) + '</code></a>'
                    : '<code>' + escHtml(d.connected_repo) + '</code>';
                headlineHtml =
                    '<div class="hozio-dtb-headline ok">' +
                        '<span class="dashicons dashicons-yes-alt"></span>' +
                        'Connected to ' + repoLink +
                    '</div>';
                subHtml = '<p class="hozio-dtb-subhead">' +
                    'Receiving signed deploys from this repo. Every <code>git push</code> updates this site.' +
                '</p>';

                // Show last-deploy summary when we have one
                if (d.last_deploy) {
                    var ld = d.last_deploy;
                    var commitHtml = ld.commit_short
                        ? (ld.commit_url
                            ? '<a href="' + escHtml(ld.commit_url) + '" target="_blank" rel="noopener"><code>' + escHtml(ld.commit_short) + '</code></a>'
                            : '<code>' + escHtml(ld.commit_short) + '</code>')
                        : '<span style="color:#9ca3af;">—</span>';
                    var actorHtml = ld.github_actor
                        ? '<strong>' + escHtml(ld.github_actor) + '</strong>'
                        : (ld.actor === 'admin' ? 'admin' : 'github-actions');
                    var runLink = ld.run_url
                        ? ' &middot; <a href="' + escHtml(ld.run_url) + '" target="_blank" rel="noopener">view run</a>'
                        : '';
                    bodyHtml =
                        '<div class="hozio-dtb-last">' +
                            '<strong>Last deploy:</strong> ' + escHtml(ld.when_human) + ' &middot; ' +
                            'commit ' + commitHtml + ' &middot; by ' + actorHtml + ' &middot; ' +
                            escHtml(ld.files) + ' files' + runLink +
                        '</div>';
                }
            }

            // History toggle (only meaningful when there's history)
            var historyHtml = '';
            if (d.history && d.history.length > 1) {
                historyHtml =
                    '<button type="button" class="hozio-dtb-history-toggle" id="hozio-dtb-history-toggle">' +
                        'Show all ' + d.history.length + ' recent deploys ▾' +
                    '</button>' +
                    '<div class="hozio-dtb-history-wrap" id="hozio-dtb-history-wrap" style="display:none;"></div>';
            }

            // Diagnostics — collapsed test button
            var diagHtml =
                '<div class="hozio-dtb-diag">' +
                    '<div class="hozio-dtb-actions">' +
                        '<button type="button" class="button" id="hozio-dtb-refresh-btn">' +
                            '<span class="dashicons dashicons-update" style="margin-top:3px;"></span>' +
                            '<span class="hozio-dtb-refresh-label"> Refresh</span>' +
                        '</button>' +
                        '<button type="button" class="button" id="hozio-dtb-test-btn">' +
                            '<span class="dashicons dashicons-search" style="margin-top:3px;"></span> Run diagnostics' +
                        '</button>' +
                    '</div>' +
                    '<div class="hozio-dtb-diag-result" id="hozio-dtb-test-result"></div>' +
                '</div>';

            $root.html(
                '<div class="hozio-dtb-status ' + cardClass + '">' +
                    headlineHtml +
                    subHtml +
                    bodyHtml +
                    historyHtml +
                    diagHtml +
                '</div>'
            );

            // Lazy-render history rows when toggle is clicked
            $('#hozio-dtb-history-toggle').data('rows', d.history);
        }

        $(document).on('click', '#hozio-dtb-history-toggle', function() {
            var $btn = $(this);
            var $wrap = $('#hozio-dtb-history-wrap');
            if ($wrap.is(':visible')) {
                $wrap.slideUp(140);
                $btn.text($btn.text().replace('▴', '▾'));
                return;
            }
            // Render
            var rows = $btn.data('rows') || [];
            var html = rows.map(function(h) {
                var actorPill = h.actor === 'github-actions'
                    ? '<span class="hozio-dtb-pill hozio-dtb-pill-actions">actions</span>'
                    : '<span class="hozio-dtb-pill hozio-dtb-pill-admin">' + escHtml(h.actor) + '</span>';
                var commit = h.commit_short
                    ? (h.commit_url
                        ? '<a href="' + escHtml(h.commit_url) + '" target="_blank" rel="noopener"><code>' + escHtml(h.commit_short) + '</code></a>'
                        : '<code>' + escHtml(h.commit_short) + '</code>')
                    : '<span style="color:#9ca3af;">—</span>';
                var run = h.run_url
                    ? '<a href="' + escHtml(h.run_url) + '" target="_blank" rel="noopener">run</a>'
                    : '<span style="color:#9ca3af;">—</span>';
                var actor = h.github_actor || h.actor || '';
                return '<tr>' +
                    '<td>' + escHtml(h.when_human) + '</td>' +
                    '<td>' + actorPill + '</td>' +
                    '<td>' + escHtml(actor) + '</td>' +
                    '<td>' + commit + '</td>' +
                    '<td>' + run + '</td>' +
                    '<td>' + escHtml(h.files) + '</td>' +
                '</tr>';
            }).join('');
            $wrap.html(
                '<table class="hozio-dtb-history-tbl">' +
                    '<thead><tr><th>When</th><th>Source</th><th>Actor</th><th>Commit</th><th>Run</th><th>Files</th></tr></thead>' +
                    '<tbody>' + html + '</tbody>' +
                '</table>'
            ).slideDown(140);
            $btn.text($btn.text().replace('▾', '▴'));
        });

        $(document).on('click', '#hozio-dtb-refresh-btn', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).find('.dashicons').addClass('hozio-spin');
            $btn.find('span.hozio-dtb-refresh-label').text(' Refreshing…');
            $.post(ajaxurl, { action: 'hozio_dtb_deploy_state', nonce: nonce })
                .done(function(resp) {
                    if (resp.success) {
                        renderStatus(resp.data);
                    } else {
                        $('#hozio-dtb-status-root').prepend(
                            '<p class="hozio-dtb-msg-error" style="margin:0 0 8px;">' + escHtml(resp.data) + '</p>'
                        );
                    }
                })
                .fail(function() {
                    $('#hozio-dtb-status-root').prepend(
                        '<p class="hozio-dtb-msg-error" style="margin:0 0 8px;">Network error — could not refresh.</p>'
                    );
                })
                .always(function() {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('hozio-spin');
                    $btn.find('span.hozio-dtb-refresh-label').text(' Refresh');
                });
        });

        $(document).on('click', '#hozio-dtb-test-btn', function() {
            var $btn = $(this);
            var $out = $('#hozio-dtb-test-result');
            $btn.prop('disabled', true);
            $out.html('<span style="color:#6b7280;">Checking…</span>');
            $.post(ajaxurl, { action: 'hozio_dtb_test_engine', nonce: nonce })
                .done(function(resp) {
                    if (!resp.success) {
                        $out.html('<span class="hozio-dtb-msg-error">' + escHtml(resp.data) + '</span>');
                        return;
                    }
                    var d = resp.data;
                    $out.html(
                        '<span class="hozio-dtb-msg-success"><span class="dashicons dashicons-yes-alt"></span> Engine OK.</span>' +
                        '<dl class="hozio-dtb-status-grid">' +
                            '<dt>Plugin</dt><dd>' + escHtml(d.plugin) + ' v' + escHtml(d.plugin_version || '') + '</dd>' +
                            '<dt>WordPress</dt><dd>' + escHtml(d.wordpress) + '</dd>' +
                            '<dt>PHP</dt><dd>' + escHtml(d.php) + '</dd>' +
                            '<dt>Content</dt><dd>' + (d.content_installed ? 'installed (v' + escHtml(d.content_version || '') + ')' : 'not deployed yet') + '</dd>' +
                        '</dl>'
                    );
                })
                .fail(function() {
                    $out.html('<span class="hozio-dtb-msg-error">Network error.</span>');
                })
                .always(function() {
                    $btn.prop('disabled', false);
                });
        });

        // Initial load + refresh whenever this tab is reopened
        refreshDeployState();
        $('.hozio-tab-btn[data-target="hozio-tab-connection"]').on('click', refreshDeployState);
    });
    </script>
    <?php
}
