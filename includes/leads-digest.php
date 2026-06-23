<?php
/**
 * File: includes/leads-digest.php
 * Leads Digest — CRM dashboard for Elementor form submissions
 *
 * Performance: Single bulk query for fields, label caching, pagination
 * Security: Nonce verification, input sanitization, capability checks, escaped output
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ══════════════════════════════════════════════════════════
// 0. CUSTOM LEADS TABLE (wp_hozio_leads)
// ══════════════════════════════════════════════════════════
add_action( 'init', function() {
    if ( get_option( 'hozio_leads_db_version' ) !== '4' ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'hozio_leads';
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS `{$table}` (
            id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime            NULL DEFAULT NULL,
            source     varchar(100)        NOT NULL DEFAULT '',
            name       varchar(255)        NOT NULL DEFAULT '',
            email      varchar(255)        NOT NULL DEFAULT '',
            phone      varchar(100)        NOT NULL DEFAULT '',
            referer    varchar(500)        NOT NULL DEFAULT '',
            fields     longtext            NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY idx_created (created_at),
            KEY idx_deleted (deleted_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Safety net: dbDelta can fail to add columns to existing tables.
        // Explicitly add deleted_at if it still isn't there.
        $col_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'deleted_at'" );
        if ( ! $col_exists ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `deleted_at` datetime NULL DEFAULT NULL, ADD KEY `idx_deleted` (`deleted_at`)" );
            // Re-check: only mark complete when column is confirmed present.
            $col_exists = $wpdb->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'deleted_at'" );
        }

        if ( $col_exists ) {
            update_option( 'hozio_leads_db_version', '4' );
        }
    }
} );

// ══════════════════════════════════════════════════════════
// 0b. WEBHOOK SECRET + AUTH HELPERS
// ══════════════════════════════════════════════════════════

// Returns the per-site webhook secret, auto-generating on first call.
// Stored in wp_options — never in HTML source, only visible in WP Admin.
function hozio_get_lead_webhook_secret() {
    $secret = get_option( 'hozio_lead_webhook_secret' );
    if ( empty( $secret ) ) {
        $secret = base64_encode( random_bytes( 48 ) );
        update_option( 'hozio_lead_webhook_secret', $secret, false );
    }
    return $secret;
}

// Ensure secret exists for all existing sites on plugin update.
add_action( 'init', function() {
    if ( ! get_option( 'hozio_lead_webhook_secret' ) ) {
        hozio_get_lead_webhook_secret();
    }
}, 1 );

// Best-effort client IP — Cloudflare-aware, falls back to REMOTE_ADDR.
function hozio_get_client_ip() {
    foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
        if ( ! empty( $_SERVER[ $key ] ) ) {
            $ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) )[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }
    return 'unknown';
}

// Ring-buffer log for blocked webhook attempts (capped at 500 entries).
function hozio_log_lead_blocked( $reason, $ip = '', $ua = '' ) {
    $log = get_option( 'hozio_lead_blocked_log', [] );
    if ( ! is_array( $log ) ) {
        $log = [];
    }
    array_unshift( $log, [
        't' => current_time( 'mysql', true ),
        'i' => $ip ?: hozio_get_client_ip(),
        'u' => substr( $ua, 0, 200 ),
        'r' => $reason,
    ] );
    if ( count( $log ) > 500 ) {
        $log = array_slice( $log, 0, 500 );
    }
    update_option( 'hozio_lead_blocked_log', $log, false );
}

// AJAX: regenerate webhook secret (admin-only, nonce-protected).
add_action( 'wp_ajax_hozio_regenerate_webhook_secret', function() {
    check_ajax_referer( 'hozio_regen_secret', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    $new_secret = base64_encode( random_bytes( 48 ) );
    update_option( 'hozio_lead_webhook_secret', $new_secret, false );
    wp_send_json_success( [ 'secret' => $new_secret ] );
} );

// AJAX: enable webhook from the Webhook Settings page (admin-only, nonce-protected).
add_action( 'wp_ajax_hozio_enable_webhook', function() {
    check_ajax_referer( 'hozio_enable_webhook', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    update_option( 'hozio_webhook_enabled', '1' );
    wp_send_json_success();
} );

// ══════════════════════════════════════════════════════════
// 1. ADMIN MENU SETUP
// ══════════════════════════════════════════════════════════
add_action( 'admin_menu', function() {
    add_menu_page(
        'Lead Submissions',
        'Lead Submissions',
        'read',
        'hozio-leads',
        'hozio_leads_list_page',
        'dashicons-email-alt',
        30
    );
    // Hidden subpage for single submission view
    add_submenu_page(
        null,
        'View Submission',
        'View Submission',
        'read',
        'hozio-lead-view',
        'hozio_lead_view_page'
    );
});

// Hide all admin menus except Lead Submissions + Profile for non-admins
add_action( 'admin_menu', function() {
    if ( current_user_can( 'manage_options' ) ) return;
    global $menu;
    $allowed = [ 'hozio-leads', 'profile.php' ];
    if ( is_array( $menu ) ) {
        foreach ( $menu as $key => $item ) {
            $slug = $item[2] ?? '';
            if ( ! in_array( $slug, $allowed, true ) ) {
                remove_menu_page( $slug );
            }
        }
    }
}, 9999 );

// Redirect non-admins away from disallowed admin pages
add_action( 'admin_init', function() {
    // Skip for admins, AJAX, and Cron
    if ( current_user_can( 'manage_options' ) || wp_doing_ajax() || wp_doing_cron() ) return;

    // Skip if user isn't fully logged in yet (prevents login redirect loop)
    if ( ! is_user_logged_in() ) return;

    // Skip during login/logout process
    $pagenow = $GLOBALS['pagenow'] ?? '';
    if ( in_array( $pagenow, [ 'wp-login.php', 'wp-signup.php' ], true ) ) return;

    $allowed_pages   = [ 'hozio-leads', 'hozio-lead-view' ];
    $allowed_scripts = [ 'profile.php', 'admin-ajax.php', 'wp-login.php', 'admin-post.php' ];

    $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
    $script       = basename( sanitize_file_name( $_SERVER['SCRIPT_NAME'] ?? '' ) );

    if ( in_array( $script, $allowed_scripts, true ) ) return;
    if ( $script === 'admin.php' && in_array( $current_page, $allowed_pages, true ) ) return;

    // Prevent redirect loop: only redirect if not already heading to hozio-leads
    $redirect_url = admin_url( 'admin.php?page=hozio-leads' );
    $current_url  = ( is_ssl() ? 'https' : 'http' ) . '://' . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' );
    if ( strpos( $current_url, 'page=hozio-leads' ) !== false ) return;

    wp_safe_redirect( $redirect_url );
    exit;
});

// Send non-admin users directly to Lead Submissions after login
add_filter( 'login_redirect', function( $redirect_to, $requested_redirect_to, $user ) {
    if ( ! is_wp_error( $user ) && is_a( $user, 'WP_User' ) && ! $user->has_cap( 'manage_options' ) ) {
        return admin_url( 'admin.php?page=hozio-leads' );
    }
    return $redirect_to;
}, 10, 3 );


// ══════════════════════════════════════════════════════════
// 1b. SETTINGS PAGE (admin only)
// ══════════════════════════════════════════════════════════
add_action( 'admin_menu', function() {
    add_submenu_page(
        'hozio-leads',
        'Leads Display Settings',
        'Display Settings',
        'manage_options',
        'hozio-leads-settings',
        'hozio_leads_settings_page'
    );
    add_submenu_page(
        'hozio-leads',
        'Webhook Settings',
        'Webhook',
        'manage_options',
        'hozio-leads-webhook',
        'hozio_leads_webhook_page'
    );
});

add_action( 'admin_init', function() {
    register_setting( 'hozio_leads_style', 'hozio_leads_style', [
        'type'              => 'array',
        'sanitize_callback' => 'hozio_sanitize_style_settings',
        'default'           => hozio_default_style(),
    ] );
});

function hozio_default_style() {
    return [
        'element_bg'    => '#ffffff',   // stat cards, table card, table header, filter dropdown, status badges
        'text_color'    => '#111827',   // stat numbers, names, dates, general cell text
        'secondary'     => '#94a3b8',   // stat labels, column headers, relative time, "Showing X of Y", muted dashes
        'link_color'    => '#3b82f6',   // email links, phone links
        'button_bg'     => '#4f46e5',   // View button background
        'button_text'   => '#ffffff',   // View button text color
        'search_bg'     => '#f8fafc',   // search bar background
        'search_text'   => '#111827',   // search bar text color
        'search_border' => '#e2e8f0',   // search bar border color
        'border_color'  => '#e2e8f0',   // all borders and dividers
    ];
}

function hozio_sanitize_style_settings( $input ) {
    $defaults  = hozio_default_style();
    $sanitized = [];
    foreach ( $defaults as $key => $default ) {
        $val = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
        $sanitized[ $key ] = $val ?: $default;
    }
    return $sanitized;
}

function hozio_get_style() {
    $saved = get_option( 'hozio_leads_style', [] );
    return wp_parse_args( $saved, hozio_default_style() );
}

function hozio_leads_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    $style = hozio_get_style();

    // Fields with labels and descriptions of what they affect
    $fields = [
        'element_bg' => [
            'label' => 'Element Background',
            'hint'  => 'Stat cards, table background, table header, filter dropdown, status badges',
        ],
        'text_color' => [
            'label' => 'Primary Text',
            'hint'  => 'Stat numbers, names, dates, table cell text',
        ],
        'secondary' => [
            'label' => 'Secondary Text',
            'hint'  => 'Stat labels, column headers, relative time, "Showing X of Y", muted values',
        ],
        'link_color' => [
            'label' => 'Link Color',
            'hint'  => 'Email addresses, phone numbers',
        ],
        'button_bg' => [
            'label' => 'Button Background',
            'hint'  => 'View button',
        ],
        'button_text' => [
            'label' => 'Button Text',
            'hint'  => 'View button text color',
        ],
        'search_bg' => [
            'label' => 'Search Bar Background',
            'hint'  => 'Search input field background',
        ],
        'search_text' => [
            'label' => 'Search Bar Text',
            'hint'  => 'Text color when typing in search bar',
        ],
        'search_border' => [
            'label' => 'Search Bar Border',
            'hint'  => 'Border color around the search input',
        ],
        'border_color' => [
            'label' => 'Border Color',
            'hint'  => 'All borders, dividers, and table lines',
        ],
    ];
    ?>
    <div class="wrap" style="max-width:700px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
        <h1 style="font-size:22px;font-weight:700;">Leads Display Settings</h1>
        <p style="color:#64748b;margin-bottom:20px;">Customize the colors of the front-end <code>[leads_digest]</code> shortcode.</p>

        <form method="post" action="options.php">
            <?php settings_fields( 'hozio_leads_style' ); ?>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                <?php $i = 0; foreach ( $fields as $key => $info ) : $i++; ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;<?php echo $i < count($fields) ? 'border-bottom:1px solid #f1f5f9;' : ''; ?>">
                        <div style="flex:1;">
                            <label for="hls_<?php echo esc_attr( $key ); ?>" style="font-weight:600;font-size:14px;color:#1e293b;display:block;">
                                <?php echo esc_html( $info['label'] ); ?>
                            </label>
                            <span style="font-size:12px;color:#94a3b8;margin-top:2px;display:block;"><?php echo esc_html( $info['hint'] ); ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                            <input type="color"
                                   id="hls_<?php echo esc_attr( $key ); ?>"
                                   name="hozio_leads_style[<?php echo esc_attr( $key ); ?>]"
                                   value="<?php echo esc_attr( $style[ $key ] ); ?>"
                                   style="width:44px;height:36px;padding:2px;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;">
                            <code id="hls_hex_<?php echo esc_attr( $key ); ?>" style="font-size:12px;color:#64748b;min-width:62px;"><?php echo esc_html( $style[ $key ] ); ?></code>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:20px;display:flex;gap:12px;align-items:center;">
                <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
                <button type="button" id="hozio-reset-colors" class="button button-secondary">Reset to Defaults</button>
            </div>
        </form>
    </div>

    <script>
    jQuery(function($){
        var defaults = <?php echo wp_json_encode( hozio_default_style() ); ?>;
        $('#hozio-reset-colors').on('click', function(){
            $.each(defaults, function(key, val){
                $('#hls_' + key).val(val);
                $('#hls_hex_' + key).text(val);
            });
        });
        $('input[type="color"]').on('input', function(){
            var key = this.id.replace('hls_','');
            $('#hls_hex_' + key).text($(this).val());
        });
    });
    </script>
    <?php
}

function hozio_leads_webhook_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    $secret             = hozio_get_lead_webhook_secret();
    $webhook_url        = rest_url( 'hozio/v1/lead' );
    $nonce              = wp_create_nonce( 'hozio_regen_secret' );
    $enable_nonce       = wp_create_nonce( 'hozio_enable_webhook' );
    $webhook_is_enabled = get_option( 'hozio_webhook_enabled', '0' ) === '1';

    $log     = get_option( 'hozio_lead_blocked_log', [] );
    $cutoff  = gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS );
    $recent  = array_values( array_filter( is_array( $log ) ? $log : [], function( $e ) use ( $cutoff ) {
        return isset( $e['t'] ) && $e['t'] >= $cutoff;
    } ) );
    $blocked_72h = count( $recent );
    ?>
    <div class="wrap" style="max-width:760px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
        <h1 style="font-size:22px;font-weight:700;margin-bottom:4px;">Webhook Settings</h1>
        <p style="color:#64748b;margin-bottom:24px;">Use these credentials to send leads to this site from any external form plugin or system.</p>

        <?php if ( ! $webhook_is_enabled ) : ?>
        <div id="hozio-wh-disabled-banner" style="background:#fefce8;border:1px solid #fde047;border-radius:10px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:flex-start;gap:14px;">
            <svg width="20" height="20" fill="none" stroke="#ca8a04" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div style="flex:1;">
                <div style="font-weight:700;color:#854d0e;font-size:14px;margin-bottom:4px;">Webhook endpoint is disabled</div>
                <div style="font-size:13px;color:#713f12;margin-bottom:12px;">The <code style="background:#fef9c3;padding:1px 5px;border-radius:3px;">/wp-json/hozio/v1/lead</code> endpoint is currently off. Any service trying to POST leads here will receive a 404. Standard form integrations (Elementor, WPForms, etc.) are unaffected.</div>
                <button type="button" id="hozio-wh-enable-btn"
                    data-nonce="<?php echo esc_attr( $enable_nonce ); ?>"
                    style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#16a34a;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;transition:background 0.15s,transform 0.15s,box-shadow 0.15s;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Enable Webhook Now
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $blocked_72h > 0 ) : ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px;">
            <strong style="color:#dc2626;"><?php echo esc_html( $blocked_72h ); ?> blocked attempt<?php echo $blocked_72h !== 1 ? 's' : ''; ?> in the last 72 hours.</strong>
            See the table below for details.
        </div>
        <?php endif; ?>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:16px;">
            <div style="font-weight:600;font-size:14px;color:#1e293b;margin-bottom:8px;">Webhook URL</div>
            <div style="display:flex;gap:8px;align-items:center;">
                <code id="hozio-wh-url" style="flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;font-size:13px;color:#1e293b;word-break:break-all;"><?php echo esc_url( $webhook_url ); ?></code>
                <button type="button" onclick="hozioClipboard('hozio-wh-url',this)" class="button button-secondary">Copy</button>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:16px;">
            <div style="font-weight:600;font-size:14px;color:#1e293b;margin-bottom:4px;">Webhook Secret</div>
            <p style="font-size:12px;color:#94a3b8;margin:0 0 12px;">Include this as the <code>X-Hozio-Secret</code> header on every request. Keep it private — requests without it are rejected.</p>
            <div style="display:flex;gap:8px;align-items:center;">
                <code id="hozio-wh-secret" style="flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;font-size:13px;color:#1e293b;word-break:break-all;"><?php echo esc_html( $secret ); ?></code>
                <button type="button" onclick="hozioClipboard('hozio-wh-secret',this)" class="button button-secondary">Copy</button>
                <button type="button" id="hozio-regen-btn" class="button button-secondary" style="color:#dc2626;border-color:#fecaca;" data-nonce="<?php echo esc_attr( $nonce ); ?>">Regenerate</button>
            </div>
            <p style="font-size:11px;color:#ef4444;margin:10px 0 0;">Warning: Regenerating breaks all existing integrations until you update them with the new secret.</p>
        </div>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:16px;">
            <div style="font-weight:600;font-size:14px;color:#1e293b;margin-bottom:16px;">Integration Examples</div>

            <div style="margin-bottom:18px;">
                <div style="font-weight:600;font-size:13px;color:#475569;margin-bottom:6px;">Elementor Forms — Webhook Action</div>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;font-size:12px;font-family:monospace;line-height:1.8;color:#1e293b;">
                    URL: <?php echo esc_url( $webhook_url ); ?><br>
                    Method: POST<br>
                    Headers &rarr; Add header:<br>
                    &nbsp;&nbsp;Name: <strong>X-Hozio-Secret</strong><br>
                    &nbsp;&nbsp;Value: <span id="el-secret-val" style="background:#fef9c3;padding:1px 4px;border-radius:3px;"><?php echo esc_html( $secret ); ?></span>
                </div>
            </div>

            <div style="margin-bottom:18px;">
                <div style="font-weight:600;font-size:13px;color:#475569;margin-bottom:6px;">WPForms / Gravity Forms via Zapier or Make</div>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;font-size:12px;font-family:monospace;line-height:1.8;color:#1e293b;">
                    URL: <?php echo esc_url( $webhook_url ); ?><br>
                    Header: X-Hozio-Secret = <span style="background:#fef9c3;padding:1px 4px;border-radius:3px;"><?php echo esc_html( $secret ); ?></span>
                </div>
            </div>

            <div>
                <div style="font-weight:600;font-size:13px;color:#475569;margin-bottom:6px;">cURL — test from terminal</div>
                <code id="hozio-curl-ex" style="display:block;background:#1e293b;color:#e2e8f0;border-radius:8px;padding:14px;font-size:12px;line-height:1.8;white-space:pre-wrap;">curl -X POST "<?php echo esc_url( $webhook_url ); ?>" \
     -H "Content-Type: application/json" \
     -H "X-Hozio-Secret: <?php echo esc_html( $secret ); ?>" \
     -d '{"source":"Test","name":"Jane Smith","email":"jane@example.com","phone":"555-0100"}'</code>
                <button type="button" onclick="hozioClipboard('hozio-curl-ex',this)" class="button button-secondary" style="margin-top:8px;">Copy</button>
            </div>
        </div>

        <?php if ( ! empty( $recent ) ) : ?>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="font-weight:600;font-size:14px;color:#1e293b;">Blocked Attempts — Last 72 Hours</div>
                <div style="font-size:12px;color:#94a3b8;" id="hozio-ba-count"></div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid #e2e8f0;">
                        <th style="text-align:left;padding:6px 10px;color:#94a3b8;font-weight:500;">Time (UTC)</th>
                        <th style="text-align:left;padding:6px 10px;color:#94a3b8;font-weight:500;">IP</th>
                        <th style="text-align:left;padding:6px 10px;color:#94a3b8;font-weight:500;">Reason</th>
                        <th style="text-align:left;padding:6px 10px;color:#94a3b8;font-weight:500;">User Agent</th>
                    </tr>
                </thead>
                <tbody id="hozio-ba-tbody">
                <?php foreach ( $recent as $entry ) :
                    $badge_style = $entry['r'] === 'rate_limit'
                        ? 'background:#fef2f2;color:#dc2626'
                        : 'background:#fef9c3;color:#92400e';
                ?>
                    <tr class="hozio-ba-row" style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:8px 10px;color:#475569;"><?php echo esc_html( $entry['t'] ); ?></td>
                        <td style="padding:8px 10px;font-family:monospace;color:#1e293b;"><?php echo esc_html( $entry['i'] ); ?></td>
                        <td style="padding:8px 10px;">
                            <span style="<?php echo esc_attr( $badge_style ); ?>;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;">
                                <?php echo esc_html( $entry['r'] ); ?>
                            </span>
                        </td>
                        <td style="padding:8px 10px;color:#94a3b8;font-size:11px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $entry['u'] ); ?>"><?php echo esc_html( $entry['u'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div id="hozio-ba-pagination" style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;font-size:13px;"></div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function hozioClipboard( elId, btn ) {
        var text = document.getElementById( elId ).innerText.trim();
        navigator.clipboard.writeText( text ).then( function() {
            var orig = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout( function() { btn.textContent = orig; }, 2000 );
        } );
    }

    document.getElementById( 'hozio-regen-btn' ).addEventListener( 'click', function() {
        if ( ! confirm( 'Regenerate the webhook secret? All existing integrations using the old secret will stop working until updated.' ) ) return;
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Regenerating...';
        fetch( ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=hozio_regenerate_webhook_secret&nonce=' + encodeURIComponent( btn.dataset.nonce )
        } )
        .then( function( r ) { return r.json(); } )
        .then( function( data ) {
            if ( data.success ) {
                var s = data.data.secret;
                document.getElementById( 'hozio-wh-secret' ).textContent = s;
                document.getElementById( 'el-secret-val' ).textContent   = s;
                var curl = document.getElementById( 'hozio-curl-ex' );
                curl.textContent = curl.textContent.replace( /X-Hozio-Secret: \S+/, 'X-Hozio-Secret: ' + s );
            }
            btn.disabled = false;
            btn.textContent = 'Regenerate';
        } );
    } );

    // Enable webhook from disabled banner
    (function() {
        var btn = document.getElementById( 'hozio-wh-enable-btn' );
        if ( ! btn ) return;
        btn.addEventListener( 'mouseover', function() {
            this.style.background = '#15803d';
            this.style.transform  = 'translateY(-1px)';
            this.style.boxShadow  = '0 4px 10px rgba(21,128,61,0.35)';
        } );
        btn.addEventListener( 'mouseout', function() {
            this.style.background = '#16a34a';
            this.style.transform  = '';
            this.style.boxShadow  = '';
        } );
        btn.addEventListener( 'click', function() {
            btn.disabled = true;
            btn.textContent = 'Enabling...';
            fetch( ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=hozio_enable_webhook&nonce=' + encodeURIComponent( btn.dataset.nonce )
            } )
            .then( function( r ) { return r.json(); } )
            .then( function( data ) {
                if ( data.success ) {
                    var banner = document.getElementById( 'hozio-wh-disabled-banner' );
                    if ( banner ) {
                        banner.style.transition = 'opacity 0.3s';
                        banner.style.opacity = '0';
                        setTimeout( function() { banner.remove(); }, 320 );
                    }
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Enable Webhook Now';
                    alert( 'Could not enable — please try again.' );
                }
            } );
        } );
    })();

    // Blocked attempts pagination
    (function() {
        var tbody = document.getElementById( 'hozio-ba-tbody' );
        if ( ! tbody ) return;
        var rows    = Array.from( tbody.querySelectorAll( '.hozio-ba-row' ) );
        var total   = rows.length;
        var perPage = 25;
        var page    = 1;
        var pages   = Math.ceil( total / perPage );
        var countEl = document.getElementById( 'hozio-ba-count' );
        var pagEl   = document.getElementById( 'hozio-ba-pagination' );

        function makeBtn( label, disabled, onClick ) {
            var btn = document.createElement( 'button' );
            btn.textContent = label;
            btn.style.cssText = 'padding:5px 12px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;cursor:pointer;color:#1e293b;font-size:12px;' + ( disabled ? 'opacity:0.4;cursor:default;' : '' );
            if ( ! disabled ) btn.addEventListener( 'click', onClick );
            return btn;
        }

        function render() {
            var start = ( page - 1 ) * perPage;
            var end   = start + perPage;
            rows.forEach( function( r, i ) {
                r.style.display = ( i >= start && i < end ) ? '' : 'none';
            } );
            if ( countEl ) {
                countEl.textContent = 'Showing ' + ( start + 1 ) + '–' + Math.min( end, total ) + ' of ' + total;
            }
            if ( pagEl ) {
                while ( pagEl.firstChild ) pagEl.removeChild( pagEl.firstChild );
                pagEl.appendChild( makeBtn( '← Previous', page <= 1, function() { page--; render(); } ) );
                var info = document.createElement( 'span' );
                info.textContent = 'Page ' + page + ' of ' + pages;
                info.style.cssText = 'color:#64748b;font-size:12px;';
                pagEl.appendChild( info );
                pagEl.appendChild( makeBtn( 'Next →', page >= pages, function() { page++; render(); } ) );
            }
        }

        if ( total > 0 ) render();
    })();
    </script>
    <?php
}


// ══════════════════════════════════════════════════════════
// 2. HELPERS
// ══════════════════════════════════════════════════════════

/**
 * Check if Elementor submission tables exist.
 * Result is cached per request via static variable.
 */
function hozio_submissions_tables_exist() {
    static $exists = null;
    if ( $exists !== null ) return $exists;

    global $wpdb;
    $subs = $wpdb->prefix . 'e_submissions';
    $vals = $wpdb->prefix . 'e_submissions_values';

    $exists = (
        $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $subs ) ) === $subs &&
        $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $vals ) ) === $vals
    );
    return $exists;
}

/**
 * Get Elementor form field labels for a given post.
 * Uses static cache so each post_id is parsed only once per request.
 */
function hozio_get_form_field_labels( $post_id ) {
    static $cache = [];
    $post_id = (int) $post_id;
    if ( ! $post_id ) return [];
    if ( isset( $cache[ $post_id ] ) ) return $cache[ $post_id ];

    $labels = [];
    $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
    if ( empty( $elementor_data ) ) {
        $cache[ $post_id ] = $labels;
        return $labels;
    }

    if ( is_string( $elementor_data ) ) {
        $elementor_data = json_decode( $elementor_data, true );
    }
    if ( ! is_array( $elementor_data ) ) {
        $cache[ $post_id ] = $labels;
        return $labels;
    }

    // Walk the Elementor element tree iteratively
    $stack = $elementor_data;
    while ( ! empty( $stack ) ) {
        $element = array_shift( $stack );

        if ( ! empty( $element['settings']['form_fields'] ) && is_array( $element['settings']['form_fields'] ) ) {
            foreach ( $element['settings']['form_fields'] as $field ) {
                $id    = $field['custom_id'] ?? $field['_id'] ?? '';
                $label = $field['field_label'] ?? '';
                if ( $id && $label ) {
                    $labels[ $id ] = $label;
                }
                if ( ! empty( $field['_id'] ) && ! empty( $field['custom_id'] ) && $field['_id'] !== $field['custom_id'] ) {
                    $labels[ $field['_id'] ] = $label;
                }
            }
        }

        if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
            foreach ( $element['elements'] as $child ) {
                $stack[] = $child;
            }
        }
    }

    $cache[ $post_id ] = $labels;
    return $labels;
}

/**
 * Get submissions with field data in bulk (avoids N+1 queries).
 *
 * @param int $per_page  Number of submissions per page. 0 = all.
 * @param int $page      Current page number (1-indexed).
 * @return array|false   [ 'submissions' => [...], 'total' => int ] or false if tables missing.
 */
function hozio_get_submissions( $per_page = 50, $page = 1 ) {
    if ( ! hozio_submissions_tables_exist() ) return false;

    global $wpdb;
    $subs = $wpdb->prefix . 'e_submissions';
    $vals = $wpdb->prefix . 'e_submissions_values';

    // Get total count
    $total = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*) FROM `{$subs}` WHERE `status` <> %s", 'trash' )
    );

    // Get paginated submission rows
    if ( $per_page > 0 ) {
        $offset = max( 0, ( $page - 1 ) * $per_page );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, created_at, post_id, referer, status
               FROM `{$subs}`
              WHERE `status` <> %s
           ORDER BY created_at DESC
              LIMIT %d OFFSET %d",
            'trash', $per_page, $offset
        ) );
    } else {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, created_at, post_id, referer, status
               FROM `{$subs}`
              WHERE `status` <> %s
           ORDER BY created_at DESC",
            'trash'
        ) );
    }

    if ( empty( $rows ) ) {
        return [ 'submissions' => [], 'total' => $total ];
    }

    // Bulk-fetch ALL field values for these submissions in ONE query
    $ids = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

    // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
    $all_values = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT submission_id, `key`, `value` FROM `{$vals}` WHERE submission_id IN ({$placeholders})",
            ...$ids
        ), ARRAY_A
    );

    // Group values by submission_id
    $values_map = [];
    foreach ( $all_values as $v ) {
        $sid = (int) $v['submission_id'];
        $values_map[ $sid ][ $v['key'] ] = $v['value'];
    }

    // Build submission objects
    $submissions = [];
    foreach ( $rows as $row ) {
        $m     = $values_map[ (int) $row->id ] ?? [];
        $name  = trim( ( $m['fname'] ?? '' ) . ' ' . ( $m['lname'] ?? '' ) );
        $email = $m['email'] ?? '';
        $phone = $m['tel']   ?? '';

        $submissions[] = (object) [
            'id'         => (int) $row->id,
            'created_at' => $row->created_at,
            'post_id'    => (int) $row->post_id,
            'referer'    => $row->referer,
            'status'     => $row->status,
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'fields'     => $m,
            'has_data'   => ( $name !== '' || $email !== '' || $phone !== '' ),
        ];
    }

    return [ 'submissions' => $submissions, 'total' => $total ];
}

/**
 * Get stats (total, today, this week) — separate lightweight queries.
 */
function hozio_get_lead_stats() {
    global $wpdb;
    $total = 0; $today_count = 0; $week_count = 0;
    $today_str = gmdate( 'Y-m-d 00:00:00' );
    $week_str  = gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );

    if ( hozio_submissions_tables_exist() ) {
        $subs        = $wpdb->prefix . 'e_submissions';
        $total       += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$subs}` WHERE status <> %s", 'trash' ) );
        $today_count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$subs}` WHERE status <> %s AND created_at >= %s", 'trash', $today_str ) );
        $week_count  += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$subs}` WHERE status <> %s AND created_at >= %s", 'trash', $week_str ) );
    }

    if ( hozio_custom_leads_table_exists() ) {
        $ht           = $wpdb->prefix . 'hozio_leads';
        $total       += (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$ht}` WHERE deleted_at IS NULL" );
        $today_count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$ht}` WHERE deleted_at IS NULL AND created_at >= %s", $today_str ) );
        $week_count  += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$ht}` WHERE deleted_at IS NULL AND created_at >= %s", $week_str ) );
    }

    return [ $total, $today_count, $week_count ];
}

/**
 * Check if the custom wp_hozio_leads table exists.
 */
function hozio_custom_leads_table_exists() {
    static $exists = null;
    if ( $exists !== null ) return $exists;
    global $wpdb;
    $table  = $wpdb->prefix . 'hozio_leads';
    $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
    return $exists;
}

/**
 * Fetch all leads from wp_hozio_leads, shaped like Elementor submission objects.
 */
function hozio_get_custom_leads_all() {
    if ( ! hozio_custom_leads_table_exists() ) return [];
    global $wpdb;
    $table = $wpdb->prefix . 'hozio_leads';
    $rows  = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE deleted_at IS NULL ORDER BY created_at DESC" );
    $leads = [];
    foreach ( $rows as $row ) {
        $fields = json_decode( $row->fields, true ) ?: [];
        $leads[] = (object) [
            'id'         => (int) $row->id,
            'ltype'      => 'hozio',
            'created_at' => $row->created_at,
            'post_id'    => 0,
            'referer'    => $row->referer,
            'status'     => 'completed',
            'name'       => $row->name,
            'email'      => $row->email,
            'phone'      => $row->phone,
            'fields'     => $fields,
            'has_data'   => ( $row->name !== '' || $row->email !== '' || $row->phone !== '' ),
            'platform'   => $row->source ?: 'Unknown',
        ];
    }
    return $leads;
}

/**
 * Get distinct platform names that have leads in wp_hozio_leads.
 */
function hozio_get_custom_lead_platforms() {
    if ( ! hozio_custom_leads_table_exists() ) return [];
    global $wpdb;
    $table   = $wpdb->prefix . 'hozio_leads';
    $sources = $wpdb->get_col( "SELECT DISTINCT source FROM `{$table}` WHERE source <> '' AND deleted_at IS NULL ORDER BY source ASC" );
    return array_map( 'sanitize_text_field', $sources );
}

/**
 * Merge Elementor + custom leads, sort by date, paginate.
 *
 * @param int $per_page  0 = all
 * @param int $page
 * @return array [ 'submissions' => [...], 'total' => int ]
 */
function hozio_get_all_leads( $per_page = 50, $page = 1 ) {
    $leads = [];

    // Elementor leads
    if ( hozio_submissions_tables_exist() ) {
        $result = hozio_get_submissions( 0, 1 ); // 0 = all records
        if ( $result && ! empty( $result['submissions'] ) ) {
            foreach ( $result['submissions'] as $s ) {
                if ( ! isset( $s->ltype ) ) $s->ltype    = 'elementor';
                if ( ! isset( $s->platform ) ) $s->platform = 'Elementor';
                $leads[] = $s;
            }
        }
    }

    // Custom leads
    foreach ( hozio_get_custom_leads_all() as $s ) {
        $leads[] = $s;
    }

    // Sort by created_at DESC
    usort( $leads, function( $a, $b ) {
        return strtotime( $b->created_at ) - strtotime( $a->created_at );
    } );

    $total = count( $leads );

    if ( $per_page > 0 ) {
        $offset = max( 0, ( $page - 1 ) * $per_page );
        $leads  = array_slice( $leads, $offset, $per_page );
    }

    return [ 'submissions' => $leads, 'total' => $total ];
}

/**
 * Shared helper — insert one lead record into wp_hozio_leads.
 */
function hozio_insert_lead_record( $source, $name, $email, $phone, $referer, $fields ) {
    if ( ! hozio_custom_leads_table_exists() ) return;
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'hozio_leads',
        [
            'source'     => sanitize_text_field( $source ),
            'name'       => sanitize_text_field( $name ),
            'email'      => sanitize_email( $email ),
            'phone'      => sanitize_text_field( $phone ),
            'referer'    => esc_url_raw( $referer ),
            'fields'     => wp_json_encode( $fields ),
            'created_at' => current_time( 'mysql', true ),
        ],
        [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
    );
}

/**
 * Render a relative time string from a datetime.
 */
function hozio_relative_time( $datetime ) {
    $diff = time() - strtotime( $datetime );
    if ( $diff < 60 )       return 'Just now';
    if ( $diff < 3600 )     return floor( $diff / 60 ) . 'm ago';
    if ( $diff < 86400 )    return floor( $diff / 3600 ) . 'h ago';
    if ( $diff < 604800 )   return floor( $diff / 86400 ) . 'd ago';
    return date_i18n( 'M j', strtotime( $datetime ) );
}

/**
 * Get status badge info [ label, css_class ].
 */
function hozio_status_badge( $status ) {
    $map = [
        'completed' => [ 'Success', 'hl-badge-success' ],
        'error'     => [ 'Failed',  'hl-badge-error' ],
        'failed'    => [ 'Failed',  'hl-badge-error' ],
    ];
    return $map[ $status ] ?? [ ucfirst( $status ?: 'Unknown' ), 'hl-badge-new' ];
}


/**
 * Get all trashed leads (Elementor + custom), merged and sorted.
 */
function hozio_get_all_trashed_leads( $per_page = 50, $page = 1 ) {
    $leads = [];

    // Trashed Elementor leads
    if ( hozio_submissions_tables_exist() ) {
        global $wpdb;
        $subs = $wpdb->prefix . 'e_submissions';
        $vals = $wpdb->prefix . 'e_submissions_values';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, created_at, post_id, referer, status FROM `{$subs}` WHERE status = %s ORDER BY created_at DESC",
            'trash'
        ) );
        if ( $rows ) {
            $ids          = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $all_values   = $wpdb->get_results(
                $wpdb->prepare( "SELECT submission_id, `key`, `value` FROM `{$vals}` WHERE submission_id IN ({$placeholders})", ...$ids ),
                ARRAY_A
            );
            $values_map = [];
            foreach ( $all_values as $v ) {
                $values_map[ (int) $v['submission_id'] ][ $v['key'] ] = $v['value'];
            }
            foreach ( $rows as $row ) {
                $m     = $values_map[ (int) $row->id ] ?? [];
                $name  = trim( ( $m['fname'] ?? '' ) . ' ' . ( $m['lname'] ?? '' ) );
                $email = $m['email'] ?? '';
                $phone = $m['tel']   ?? '';
                $leads[] = (object) [
                    'id'         => (int) $row->id,
                    'ltype'      => 'elementor',
                    'created_at' => $row->created_at,
                    'post_id'    => (int) $row->post_id,
                    'referer'    => $row->referer,
                    'status'     => 'trash',
                    'name'       => $name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'fields'     => $m,
                    'has_data'   => ( $name !== '' || $email !== '' || $phone !== '' ),
                    'platform'   => 'Elementor',
                ];
            }
        }
    }

    // Trashed custom leads
    if ( hozio_custom_leads_table_exists() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'hozio_leads';
        $rows  = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC" );
        foreach ( $rows as $row ) {
            $fields  = json_decode( $row->fields, true ) ?: [];
            $leads[] = (object) [
                'id'         => (int) $row->id,
                'ltype'      => 'hozio',
                'created_at' => $row->created_at,
                'post_id'    => 0,
                'referer'    => $row->referer,
                'status'     => 'trash',
                'name'       => $row->name,
                'email'      => $row->email,
                'phone'      => $row->phone,
                'fields'     => $fields,
                'has_data'   => ( $row->name !== '' || $row->email !== '' || $row->phone !== '' ),
                'platform'   => $row->source ?: 'Unknown',
            ];
        }
    }

    usort( $leads, function( $a, $b ) {
        return strtotime( $b->created_at ) - strtotime( $a->created_at );
    } );

    $total = count( $leads );
    if ( $per_page > 0 ) {
        $offset = max( 0, ( $page - 1 ) * $per_page );
        $leads  = array_slice( $leads, $offset, $per_page );
    }
    return [ 'submissions' => $leads, 'total' => $total ];
}

/**
 * Count all trashed leads across sources.
 */
function hozio_get_trash_count() {
    global $wpdb;
    $count = 0;
    if ( hozio_submissions_tables_exist() ) {
        $count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$wpdb->prefix}e_submissions` WHERE status = %s", 'trash' ) );
    }
    if ( hozio_custom_leads_table_exists() ) {
        $count += (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}hozio_leads` WHERE deleted_at IS NOT NULL" );
    }
    return $count;
}

/**
 * Auto-purge custom leads that have been in trash 30+ days.
 */
function hozio_auto_purge_trash() {
    if ( ! hozio_custom_leads_table_exists() ) return;
    global $wpdb;
    $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM `{$wpdb->prefix}hozio_leads` WHERE deleted_at IS NOT NULL AND deleted_at < %s",
        $cutoff
    ) );
}

/**
 * Rule-based spam/test score — no AI required.
 * Returns [ 'score' => 0–100, 'label' => string, 'class' => string, 'flags' => [] ]
 */
/**
 * Rule-based spam/test score — no AI required.
 *
 * Three separate signal pools:
 *   $test_score  — looks like an internal / placeholder test submission
 *   $spam_score  — looks like outbound marketing spam
 *   $real_score  — positive signals that suggest a genuine lead (reduces risk)
 *
 * Returns [ 'score' => 0–100, 'label' => string, 'class' => string, 'flags' => [] ]
 */
function hozio_lead_spam_score( $name, $email, $phone, $fields = [], $referer = '' ) {
    $test_score = 0;
    $spam_score = 0;
    $real_score = 0;
    $flags      = [];

    $name_lc  = strtolower( trim( $name ) );
    $email_lc = strtolower( trim( $email ) );
    $phone_d  = preg_replace( '/\D/', '', $phone );

    // ── Name: test / placeholder signals ──
    if ( $name_lc ) {
        foreach ( [ 'test', 'testing', 'asdf', 'qwerty', 'foo', 'bar', 'baz', 'aaa', 'bbb', 'xxx', '123', 'admin', 'user', 'john doe', 'jane doe', 'lorem ipsum', 'sample', 'demo', 'example', 'placeholder', 'fake', 'temp', 'tester' ] as $kw ) {
            if ( strpos( $name_lc, $kw ) !== false ) { $test_score += 35; $flags[] = 'Test/placeholder name'; break; }
        }
        $stripped = str_replace( ' ', '', $name_lc );
        if ( $stripped && strlen( $stripped ) > 1 && strlen( count_chars( $stripped, 3 ) ) === 1 ) {
            $test_score += 25; $flags[] = 'Repeated characters in name';
        }
        if ( strlen( $stripped ) <= 2 ) { $test_score += 20; $flags[] = 'Very short name'; }
    }

    // ── Email: disposable / test signals ──
    if ( $email_lc && strpos( $email_lc, '@' ) !== false ) {
        $at_pos = strrpos( $email_lc, '@' );
        $local  = substr( $email_lc, 0, $at_pos );
        $edom   = substr( $email_lc, $at_pos + 1 );
        foreach ( [ 'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'yopmail.com', 'throwam.com', 'sharklasers.com', 'trashmail.com', 'maildrop.cc', '10minutemail.com', 'fakeinbox.com', 'spambox.us', 'throwaway.email', 'dispostable.com', 'mailnull.com', 'mailnesia.com', 'spamgourmet.com', 'spam4.me', 'getairmail.com' ] as $d ) {
            if ( $edom === $d || strpos( $edom, $d ) !== false ) { $test_score += 45; $flags[] = 'Disposable email domain'; break; }
        }
        foreach ( [ 'test', 'testing', 'spam', 'fake', 'demo', 'asdf', 'qwerty', 'temp', 'noreply', 'no-reply' ] as $kw ) {
            if ( strpos( $local, $kw ) !== false ) { $test_score += 25; $flags[] = 'Test keyword in email'; break; }
        }
        if ( strlen( $local ) <= 2 ) { $test_score += 15; $flags[] = 'Very short email username'; }
    }

    // ── Phone: fake pattern signals ──
    if ( $phone_d ) {
        if ( strlen( $phone_d ) >= 7 && strlen( count_chars( $phone_d, 3 ) ) === 1 ) {
            $test_score += 35; $flags[] = 'Repeated phone digits';
        }
        if ( in_array( $phone_d, [ '1234567890', '0987654321', '1234567', '0000000000', '9999999999', '5555555555', '1111111111' ], true ) ) {
            $test_score += 40; $flags[] = 'Common fake phone pattern';
        }
        if ( strlen( $phone_d ) < 7 ) { $test_score += 15; $flags[] = 'Phone number too short'; }
    }

    // ── No contact info at all ──
    if ( ! $name && ! $email && ! $phone ) { $test_score += 15; $flags[] = 'No contact info'; }

    // ── Field content analysis ──
    if ( ! empty( $fields ) && is_array( $fields ) ) {
        $all_text  = strtolower( implode( ' ', array_map( 'strval', $fields ) ) );
        $url_count = preg_match_all( '/https?:\/\//i', $all_text, $dummy );

        // "hozio" anywhere → internal test by site staff
        if ( strpos( $all_text, 'hozio' ) !== false ) {
            $test_score += 50; $flags[] = 'Contains "hozio" — likely internal test';
        }

        // "test" as a standalone word in message content
        if ( preg_match( '/\btest\b/', $all_text ) ) {
            $test_score += 20; $flags[] = 'Test keyword in message';
        }

        // Unsubscribe link — dead giveaway for outbound marketing spam
        if ( strpos( $all_text, 'unsubscribe' ) !== false ) {
            $spam_score += 60; $flags[] = 'Contains unsubscribe link';
        }

        // Multiple URLs in message
        if ( $url_count >= 2 ) {
            $spam_score += 30; $flags[] = 'Multiple URLs in message';
        } elseif ( $url_count === 1 ) {
            $spam_score += 15; $flags[] = 'URL in message';
        }

        // Affiliate / referral URL params
        if ( preg_match( '/[?&](refer|ref|aff|affiliate|utm_source)=/i', $all_text ) ) {
            $spam_score += 20; $flags[] = 'Affiliate/referral URL';
        }

        // Marketing spam phrases
        $phrase_pts = 0;
        foreach ( [
            'hidden money' => 25, 'learn more:' => 20, 'click here' => 15,
            'limited time' => 15, 'act now' => 15, 'make money' => 20,
            'earn money' => 20, 'increase your' => 10, 'exponentially' => 15,
            'cashflow' => 15, 'cash flow' => 10, 'trigger points' => 15,
            'best part is' => 10, "it's way easier" => 10,
            'want to find' => 10, 'shares exactly how' => 15,
        ] as $phrase => $pts ) {
            if ( strpos( $all_text, $phrase ) !== false ) $phrase_pts += $pts;
        }
        if ( $phrase_pts > 0 ) {
            $spam_score += min( 40, $phrase_pts ); $flags[] = 'Spam marketing language';
        }

        // Long promotional message (>300 chars) that also contains a URL
        foreach ( $fields as $val ) {
            if ( strlen( strval( $val ) ) > 300 && $url_count >= 1 ) {
                $spam_score += 15; $flags[] = 'Long promotional message with URL'; break;
            }
        }
    }

    // ── Positive / real signals (subtract from risk) ──

    // Valid US phone: 10 digits (or 11 starting with 1), area code + exchange don't start with 0 or 1
    if ( $phone_d ) {
        $us = ( strlen( $phone_d ) === 11 && $phone_d[0] === '1' ) ? substr( $phone_d, 1 ) : ( strlen( $phone_d ) === 10 ? $phone_d : '' );
        if ( strlen( $us ) === 10 && $us[0] >= '2' && $us[3] >= '2' ) {
            $real_score += 25;
        }
    }

    // Email from a well-known consumer provider
    if ( $email_lc && strpos( $email_lc, '@' ) !== false ) {
        $edom2 = substr( $email_lc, strrpos( $email_lc, '@' ) + 1 );
        if ( in_array( $edom2, [ 'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'me.com', 'aol.com', 'live.com', 'msn.com', 'comcast.net', 'att.net', 'verizon.net', 'sbcglobal.net', 'cox.net', 'mac.com', 'yahoo.co.uk', 'btinternet.com' ], true ) ) {
            $real_score += 15;
        }
    }

    // Full name — two+ words made of real letters
    if ( $name_lc ) {
        $parts = array_filter( explode( ' ', trim( $name_lc ) ) );
        if ( count( $parts ) >= 2 ) {
            $all_alpha = true;
            foreach ( $parts as $p ) {
                if ( ! preg_match( "/^[a-z'\-\.]+$/i", $p ) ) { $all_alpha = false; break; }
            }
            if ( $all_alpha ) $real_score += 15;
        }
    }

    // Referer is from the same site (real page on this domain, not an outside bot)
    if ( $referer && function_exists( 'home_url' ) ) {
        $site_host = parse_url( home_url(), PHP_URL_HOST );
        $ref_host  = parse_url( $referer, PHP_URL_HOST );
        if ( $site_host && $ref_host && $ref_host === $site_host ) {
            $real_score += 20;
        }
    }

    // ── Combine and classify ──
    $risk = max( 0, min( 100, ( $test_score + $spam_score ) - $real_score ) );

    if ( $risk === 0 )  return [ 'score' => 0,     'label' => 'Looks Real',  'class' => 'hl-spam-clean',  'flags' => [] ];
    if ( $risk <= 35 )  return [ 'score' => $risk, 'label' => 'Review',      'class' => 'hl-spam-review', 'flags' => $flags ];

    // High risk — use the dominant signal type for the label
    if ( $spam_score > $test_score ) {
        return [ 'score' => $risk, 'label' => 'Likely Spam', 'class' => 'hl-spam-spam', 'flags' => $flags ];
    }
    return [ 'score' => $risk, 'label' => 'Likely Test', 'class' => 'hl-spam-test', 'flags' => $flags ];
}


// ══════════════════════════════════════════════════════════
// 3. ADMIN PAGE: Leads List (CRM Dashboard)
// ══════════════════════════════════════════════════════════
function hozio_leads_list_page() {
    hozio_auto_purge_trash();

    $is_trash_view = isset( $_GET['view'] ) && $_GET['view'] === 'trash';

    // Pagination
    $per_page     = 50;
    $current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

    if ( $is_trash_view ) {
        $result = hozio_get_all_trashed_leads( $per_page, $current_page );
    } else {
        $result = hozio_get_all_leads( $per_page, $current_page );
    }
    $submissions = $result['submissions'];
    $total_items = $result['total'];
    $total_pages = (int) ceil( $total_items / $per_page );

    // View-tab counts
    $all_count   = hozio_get_all_leads( 0, 1 )['total'];
    $trash_count = hozio_get_trash_count();

    // Build platform filter options (main view only)
    $platform_options = [];
    if ( ! $is_trash_view ) {
        if ( hozio_submissions_tables_exist() ) {
            $platform_options[] = 'Elementor';
        }
        foreach ( hozio_get_custom_lead_platforms() as $src ) {
            $platform_options[] = $src;
        }
    }

    list( $stat_total, $stat_today, $stat_week ) = hozio_get_lead_stats();

    // Nonces
    $view_nonce   = wp_create_nonce( 'hozio_view_lead' );
    $action_nonce = wp_create_nonce( 'hozio_lead_action' );
    $export_nonce = wp_create_nonce( 'hozio_export_leads' );
    ?>
    <div class="hozio-leads-wrap">

      <!-- Header -->
      <div class="hl-header">
        <div>
          <h1 class="hl-title"><?php echo $is_trash_view ? 'Trash' : 'Lead Submissions'; ?></h1>
          <p class="hl-subtitle"><?php echo $is_trash_view ? 'Items in trash are automatically deleted after 30 days' : 'Track and manage all your incoming leads'; ?></p>
        </div>
        <?php if ( ! $is_trash_view ) : ?>
        <div class="hl-export-wrap">
          <button type="button" id="hl-export-btn" class="hl-export-btn">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
          </button>
          <div class="hl-export-dropdown" id="hl-export-dropdown">
            <div class="hl-export-dd-title">Select date range</div>
            <a href="#" class="hl-export-option" data-range="all">All leads</a>
            <a href="#" class="hl-export-option" data-range="1year">Last 1 year</a>
            <a href="#" class="hl-export-option" data-range="3months">Last 3 months</a>
            <a href="#" class="hl-export-option" data-range="week">Last week</a>
            <a href="#" class="hl-export-option" data-range="yesterday">Yesterday</a>
            <a href="#" class="hl-export-option" data-range="today">Today</a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- View Tabs -->
      <div class="hl-view-tabs">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=hozio-leads' ) ); ?>" class="hl-view-tab <?php echo ! $is_trash_view ? 'hl-tab-active' : ''; ?>">
          All <span class="hl-tab-count"><?php echo esc_html( $all_count ); ?></span>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=hozio-leads&view=trash' ) ); ?>" class="hl-view-tab <?php echo $is_trash_view ? 'hl-tab-active' : ''; ?>">
          Trash <span class="hl-tab-count"><?php echo esc_html( $trash_count ); ?></span>
        </a>
      </div>

      <?php if ( current_user_can( 'manage_options' ) ) :
        $wh_log      = get_option( 'hozio_lead_blocked_log', [] );
        $wh_cutoff   = gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS );
        $wh_blocked  = count( array_filter( is_array( $wh_log ) ? $wh_log : [], function( $e ) use ( $wh_cutoff ) {
            return isset( $e['t'] ) && $e['t'] >= $wh_cutoff;
        } ) );
        $webhook_settings_url = admin_url( 'admin.php?page=hozio-leads-webhook' );
      ?>
      <!-- Webhook Security Banner -->
      <div class="hl-webhook-banner" style="background:#f0fdf4;border-color:#bbf7d0;">
        <div class="hl-webhook-info">
          <svg width="16" height="16" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span style="color:#15803d;"><strong>Webhook is protected.</strong> An <code>X-Hozio-Secret</code> header is required — unauthenticated requests are blocked automatically.</span>
        </div>
        <div style="display:flex;align-items:center;gap:12px;margin-top:10px;flex-wrap:wrap;">
          <a href="<?php echo esc_url( $webhook_settings_url ); ?>" class="hl-copy-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
            Webhook Settings &amp; Secret
          </a>
          <?php if ( $wh_blocked > 0 ) : ?>
          <span style="font-size:12px;color:#dc2626;font-weight:600;">
            <?php echo esc_html( $wh_blocked ); ?> blocked attempt<?php echo $wh_blocked !== 1 ? 's' : ''; ?> in the last 72h
          </span>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Stat Cards -->
      <div class="hl-stats">
        <div class="hl-stat-card">
          <div class="hl-stat-icon" style="background:#ede9fe;color:#7c3aed;">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div class="hl-stat-content">
            <span class="hl-stat-number"><?php echo esc_html( $stat_total ); ?></span>
            <span class="hl-stat-label">Total Leads</span>
          </div>
        </div>
        <div class="hl-stat-card">
          <div class="hl-stat-icon" style="background:#dbeafe;color:#2563eb;">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div class="hl-stat-content">
            <span class="hl-stat-number"><?php echo esc_html( $stat_today ); ?></span>
            <span class="hl-stat-label">Today</span>
          </div>
        </div>
        <div class="hl-stat-card">
          <div class="hl-stat-icon" style="background:#fef3c7;color:#d97706;">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <div class="hl-stat-content">
            <span class="hl-stat-number"><?php echo esc_html( $stat_week ); ?></span>
            <span class="hl-stat-label">This Week</span>
          </div>
        </div>
      </div>

      <?php if ( empty( $submissions ) && $current_page === 1 ) : ?>
        <div class="hl-empty-state">
          <svg width="64" height="64" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <h2><?php echo $is_trash_view ? 'Trash is empty' : 'No leads yet'; ?></h2>
          <p><?php echo $is_trash_view ? 'Trashed leads appear here.' : "When someone submits a form on your site, it'll show up here."; ?></p>
        </div>
      <?php else : ?>

      <!-- Bulk Action Bar -->
      <div class="hl-bulk-bar" id="hl-bulk-bar">
        <span class="hl-bulk-count" id="hl-bulk-count">0 selected</span>
        <?php if ( ! $is_trash_view ) : ?>
          <button type="button" class="hl-bulk-btn hl-bulk-trash-btn" id="hl-bulk-trash">&#128465; Move to Trash</button>
        <?php else : ?>
          <button type="button" class="hl-bulk-btn hl-bulk-restore-btn" id="hl-bulk-restore">&#8617; Restore</button>
          <button type="button" class="hl-bulk-btn hl-bulk-delete-btn" id="hl-bulk-delete">&#10005; Delete Permanently</button>
        <?php endif; ?>
        <button type="button" class="hl-bulk-btn hl-bulk-cancel-btn" id="hl-bulk-cancel">Cancel</button>
      </div>

      <!-- Search & Filter Bar -->
      <div class="hl-toolbar">
        <div class="hl-search-box">
          <svg width="18" height="18" fill="none" stroke="#9ca3af" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="hl-search" placeholder="Search by name, email, or phone...">
        </div>
        <div class="hl-filters">
          <select id="hl-filter-date">
            <option value="">All Time</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
          </select>
          <select id="hl-filter-data">
            <option value="">All Leads</option>
            <option value="has-data">With Contact Info</option>
            <option value="no-data">Missing Contact Info</option>
          </select>
          <?php if ( ! empty( $platform_options ) ) : ?>
          <select id="hl-filter-platform">
            <option value="">All Platforms</option>
            <?php foreach ( $platform_options as $plat ) : ?>
              <option value="<?php echo esc_attr( strtolower( $plat ) ); ?>"><?php echo esc_html( $plat ); ?></option>
            <?php endforeach; ?>
          </select>
          <?php endif; ?>
        </div>
      </div>

      <!-- Results count -->
      <div class="hl-results-count">
        Showing <strong id="hl-visible-count"><?php echo count( $submissions ); ?></strong> of <?php echo esc_html( $total_items ); ?> leads
      </div>

      <!-- Leads Table -->
      <div class="hl-table-card">
        <table class="hl-table" id="hl-leads-table">
          <thead>
            <tr>
              <th class="hl-col-cb"><input type="checkbox" id="hl-select-all" title="Select all visible"></th>
              <th class="hl-sortable" data-col="1">Date <span class="hl-sort-icon">↕</span></th>
              <th class="hl-sortable" data-col="2">Name <span class="hl-sort-icon">↕</span></th>
              <th class="hl-sortable" data-col="3">Email <span class="hl-sort-icon">↕</span></th>
              <th class="hl-sortable" data-col="4">Phone <span class="hl-sort-icon">↕</span></th>
              <th>Status</th>
              <th>Platform</th>
              <th>Risk</th>
              <th style="width:160px;"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ( $submissions as $s ) :
              $ltype    = $s->ltype ?? 'elementor';
              $platform = $s->platform ?? 'Elementor';
              $view_url = wp_nonce_url(
                  admin_url( 'admin.php?page=hozio-lead-view&id=' . $s->id . ( $ltype === 'hozio' ? '&ltype=hozio' : '' ) ),
                  'hozio_view_lead',
                  '_hlnonce'
              );
              $form_page    = $s->post_id ? get_the_title( $s->post_id ) : '';
              $field_labels = ( $ltype === 'elementor' ) ? hozio_get_form_field_labels( $s->post_id ) : [];
              $badge        = hozio_status_badge( $s->status );
              $relative     = hozio_relative_time( $s->created_at );
              $plat_slug    = strtolower( preg_replace( '/[^a-z0-9]/i', '', $platform ) );
              $spam         = hozio_lead_spam_score( $s->name, $s->email, $s->phone, (array) $s->fields, $s->referer ?? '' );

              // Build expanded detail fields (only non-empty)
              $detail_fields = [];
              foreach ( $s->fields as $key => $value ) {
                  if ( $value === '' || $value === null ) continue;
                  $label = $field_labels[ $key ] ?? ucwords( str_replace( [ '_', '-' ], ' ', $key ) );
                  $display = esc_html( $value );
                  if ( is_email( $value ) ) {
                      $display = '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>';
                  } elseif ( preg_match( '/^[\+\d\s\-\(\)]{7,}$/', $value ) ) {
                      $display = '<a href="tel:' . esc_attr( preg_replace( '/[^\d\+]/', '', $value ) ) . '">' . esc_html( $value ) . '</a>';
                  }
                  $is_long = strlen( (string) $value ) > 150;
                $detail_fields[] = [ 'label' => $label, 'display' => $display, 'raw' => (string) $value, 'is_long' => $is_long ];
              }
          ?>
            <tr class="hl-row <?php echo $s->has_data ? '' : 'hl-row-dim'; ?>"
                data-id="<?php echo esc_attr( $s->id ); ?>"
                data-ltype="<?php echo esc_attr( $ltype ); ?>"
                data-name="<?php echo esc_attr( strtolower( $s->name ) ); ?>"
                data-email="<?php echo esc_attr( strtolower( $s->email ) ); ?>"
                data-phone="<?php echo esc_attr( $s->phone ); ?>"
                data-status="<?php echo esc_attr( $s->status ); ?>"
                data-date="<?php echo esc_attr( $s->created_at ); ?>"
                data-has-data="<?php echo $s->has_data ? '1' : '0'; ?>"
                data-platform="<?php echo esc_attr( strtolower( $platform ) ); ?>"
                data-timestamp="<?php echo esc_attr( strtotime( $s->created_at ) ); ?>">
              <td class="hl-cb-cell" data-label=""><input type="checkbox" class="hl-row-cb" value="<?php echo esc_attr( $s->id ); ?>"></td>
              <td data-label="Date">
                <div class="hl-date-cell">
                  <span class="hl-date-primary"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $s->created_at ) ) ); ?></span>
                  <span class="hl-date-secondary"><?php echo esc_html( $relative ); ?></span>
                </div>
              </td>
              <td data-label="Name">
                <?php echo $s->name !== '' ? '<span class="hl-name">' . esc_html( $s->name ) . '</span>' : '<span class="hl-empty">No name</span>'; ?>
              </td>
              <td data-label="Email">
                <?php echo $s->email !== '' ? '<a href="mailto:' . esc_attr( $s->email ) . '" class="hl-email">' . esc_html( $s->email ) . '</a>' : '<span class="hl-empty">—</span>'; ?>
              </td>
              <td data-label="Phone">
                <?php echo $s->phone !== '' ? '<a href="tel:' . esc_attr( $s->phone ) . '" class="hl-phone">' . esc_html( $s->phone ) . '</a>' : '<span class="hl-empty">—</span>'; ?>
              </td>
              <td data-label="Status">
                <span class="hl-badge <?php echo esc_attr( $badge[1] ); ?>"><?php echo esc_html( $badge[0] ); ?></span>
              </td>
              <td data-label="Platform">
                <div class="hl-platform-cell">
                  <span class="hl-platform-badge hl-plat-<?php echo esc_attr( $plat_slug ); ?>"><?php echo esc_html( $platform ); ?></span>
                  <?php if ( $form_page ) : ?>
                    <span class="hl-platform-page"><?php echo esc_html( $form_page ); ?></span>
                  <?php endif; ?>
                </div>
              </td>
              <td data-label="Risk">
                <span class="hl-spam-badge <?php echo esc_attr( $spam['class'] ); ?>">
                  <?php if ( $spam['score'] > 0 ) : ?>
                    <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  <?php else : ?>
                    <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                  <?php endif; ?>
                  <?php echo esc_html( $spam['label'] ); ?>
                </span>
              </td>
              <td class="hl-action-cell">
                <div class="hl-action-group">
                  <span class="hl-expand-btn" title="Click to expand details">
                    <span class="hl-expand-label">Details</span>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                  </span>
                  <?php if ( ! $is_trash_view ) : ?>
                    <button type="button" class="hl-row-action-btn hl-trash-btn"
                            data-id="<?php echo esc_attr( $s->id ); ?>"
                            data-ltype="<?php echo esc_attr( $ltype ); ?>"
                            title="Move to trash">
                      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                  <?php else : ?>
                    <button type="button" class="hl-row-action-btn hl-restore-btn"
                            data-id="<?php echo esc_attr( $s->id ); ?>"
                            data-ltype="<?php echo esc_attr( $ltype ); ?>"
                            title="Restore">
                      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.95"/></svg>
                    </button>
                    <button type="button" class="hl-row-action-btn hl-delete-btn"
                            data-id="<?php echo esc_attr( $s->id ); ?>"
                            data-ltype="<?php echo esc_attr( $ltype ); ?>"
                            title="Delete permanently">
                      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <!-- Expandable detail row -->
            <tr class="hl-detail-row" style="display:none;">
              <td colspan="9">
                <div class="hl-detail-panel">
                  <div class="hl-dp-head">
                    <div class="hl-dp-head-left">
                      <span class="hl-dp-id">Submission #<?php echo esc_html( $s->id ); ?></span>
                      <span class="hl-spam-badge <?php echo esc_attr( $spam['class'] ); ?>">
                        <?php if ( $spam['score'] > 0 ) : ?>
                          <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?php else : ?>
                          <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php endif; ?>
                        <?php echo esc_html( $spam['label'] ); ?>
                        <?php if ( $spam['score'] > 0 ) : ?><span class="hl-dp-score">(<?php echo esc_html( $spam['score'] ); ?>%)</span><?php endif; ?>
                      </span>
                    </div>
                    <div class="hl-dp-meta">
                      <span class="hl-dp-date">
                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo esc_html( date_i18n( 'M j, Y \a\t g:i A', strtotime( $s->created_at ) ) ); ?>
                      </span>
                      <?php if ( $form_page ) : ?>
                        <span class="hl-dp-dot">·</span>
                        <span><?php echo esc_html( $form_page ); ?></span>
                      <?php endif; ?>
                      <?php if ( $s->referer ) : ?>
                        <span class="hl-dp-dot">·</span>
                        <a href="<?php echo esc_url( $s->referer ); ?>" target="_blank" rel="noopener noreferrer" class="hl-dp-source">
                          <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                          Source page
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php if ( ! empty( $spam['flags'] ) ) : ?>
                    <div class="hl-dp-flags">
                      <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                      <?php echo esc_html( implode( ' · ', $spam['flags'] ) ); ?>
                    </div>
                  <?php endif; ?>
                  <?php if ( ! empty( $detail_fields ) ) : ?>
                    <div class="hl-detail-grid">
                      <?php foreach ( $detail_fields as $df ) : ?>
                        <div class="hl-detail-field<?php echo $df['is_long'] ? ' hl-detail-field-wide' : ''; ?>">
                          <span class="hl-detail-label"><?php echo esc_html( $df['label'] ); ?></span>
                          <span class="hl-detail-value">
                            <?php if ( $df['is_long'] ) : ?>
                              <span class="hl-val-short"><?php echo esc_html( mb_substr( $df['raw'], 0, 150 ) ); ?>…</span>
                              <span class="hl-val-full" style="display:none"><?php echo $df['display']; ?></span>
                              <button type="button" class="hl-readmore-btn">Read more</button>
                            <?php else : ?>
                              <?php echo $df['display']; ?>
                            <?php endif; ?>
                          </span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else : ?>
                    <span class="hl-detail-empty">No field data available for this submission.</span>
                  <?php endif; ?>
                  <?php if ( ! $is_trash_view ) : ?>
                    <div class="hl-dp-footer">
                      <a href="<?php echo esc_url( $view_url ); ?>" class="hl-btn-view">Open Full View →</a>
                    </div>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div id="hl-no-results" style="display:none;padding:40px 20px;text-align:center;">
          <svg width="48" height="48" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="8" x2="14" y2="14"/><line x1="14" y1="8" x2="8" y2="14"/></svg>
          <p style="font-size:15px;font-weight:600;color:#1e293b;margin:0 0 4px;">No results found</p>
          <p style="font-size:13px;color:#94a3b8;margin:0;">Try adjusting your search or filters</p>
        </div>
      </div>

      <!-- Shown dynamically when the last row is removed via JS -->
      <div id="hl-empty-after-remove" style="display:none;" class="hl-empty-state">
        <svg width="64" height="64" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <h2><?php echo $is_trash_view ? 'Trash is empty' : 'No leads yet'; ?></h2>
        <p><?php echo $is_trash_view ? 'Trashed leads appear here.' : "When someone submits a form on your site, it'll show up here."; ?></p>
      </div>

      <?php // Pagination
      if ( $total_pages > 1 ) : ?>
        <div class="hl-pagination">
          <?php
          $base_url = admin_url( 'admin.php?page=hozio-leads' );
          for ( $i = 1; $i <= $total_pages; $i++ ) :
              $url   = $i === 1 ? $base_url : add_query_arg( 'paged', $i, $base_url );
              $class = $i === $current_page ? 'hl-page-btn hl-page-active' : 'hl-page-btn';
          ?>
            <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $i ); ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

      <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script>
    jQuery(function($){
      var actionNonce  = '<?php echo esc_js( $action_nonce ); ?>';
      var exportNonce  = '<?php echo esc_js( $export_nonce ); ?>';
      var exportDomain = '<?php echo esc_js( sanitize_file_name( parse_url( home_url(), PHP_URL_HOST ) ) ); ?>';

      // ── Export CSV dropdown ──
      $('#hl-export-btn').on('click', function(e){
        e.stopPropagation();
        $('#hl-export-dropdown').toggleClass('hl-dd-open');
      });
      $(document).on('click', function(){ $('#hl-export-dropdown').removeClass('hl-dd-open'); });
      $('.hl-export-option').on('click', function(e){
        e.preventDefault();
        var range = $(this).data('range');
        var url = ajaxurl + '?action=hozio_export_leads&range=' + encodeURIComponent(range) + '&_wpnonce=' + encodeURIComponent(exportNonce);
        $('#hl-export-dropdown').removeClass('hl-dd-open');
        fetch(url, { credentials: 'same-origin' })
          .then(function(r){ return r.blob(); })
          .then(function(blob){
            var blobUrl = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = blobUrl;
            a.download = 'leads-' + exportDomain + '-' + range + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function(){ URL.revokeObjectURL(blobUrl); }, 100);
          });
      });

      // ── Expand/collapse rows ──
      $('.hl-row').on('click', function(e){
        if ($(e.target).closest('a, button, input[type=checkbox]').length) return;
        var $row = $(this), $btn = $row.find('.hl-expand-btn'), $detail = $row.next('.hl-detail-row');
        var isOpen = $detail.is(':visible');
        $('.hl-detail-row').slideUp(200);
        $('.hl-expand-btn').removeClass('hl-expanded');
        $('.hl-row').removeClass('hl-row-active');
        if (!isOpen) {
          $detail.slideDown(200);
          $btn.addClass('hl-expanded');
          $row.addClass('hl-row-active');
        }
      });
      $('.hl-expand-btn').on('click', function(e){
        e.stopPropagation();
        $(this).closest('.hl-row').trigger('click');
      });

      // ── Checkbox / bulk selection ──
      function getSelected() {
        var items = [];
        $('.hl-row-cb:checked').each(function(){
          var $row = $(this).closest('.hl-row');
          items.push({ id: $row.data('id'), ltype: $row.data('ltype') });
        });
        return items;
      }
      function updateBulkBar() {
        var count = $('.hl-row-cb:checked').length;
        if (count > 0) {
          $('#hl-bulk-bar').addClass('hl-bulk-bar-active');
          $('#hl-bulk-count').text(count + ' selected');
        } else {
          $('#hl-bulk-bar').removeClass('hl-bulk-bar-active');
        }
        $('#hl-select-all').prop('indeterminate', count > 0 && count < $('.hl-row-cb:visible').length);
        $('#hl-select-all').prop('checked', count > 0 && count === $('.hl-row-cb:visible').length);
      }
      $(document).on('change', '.hl-row-cb', updateBulkBar);
      $('#hl-select-all').on('change', function(){
        $('.hl-row-cb:visible').prop('checked', this.checked);
        updateBulkBar();
      });
      $('#hl-bulk-cancel').on('click', function(){
        $('.hl-row-cb').prop('checked', false);
        $('#hl-select-all').prop('checked', false).prop('indeterminate', false);
        updateBulkBar();
      });

      // ── AJAX lead action helper ──
      function leadAction(action, items, onDone) {
        $.post(ajaxurl, {
          action: 'hozio_lead_action',
          _wpnonce: actionNonce,
          lead_action: action,
          leads: JSON.stringify(items)
        }, function(){ onDone(); });
      }
      // Adjust the All/Trash tab count badges without a page reload
      function adjustTabCounts(action, count) {
        var $counts = $('.hl-view-tab .hl-tab-count');
        var allN   = parseInt($counts.eq(0).text()) || 0;
        var trashN = parseInt($counts.eq(1).text()) || 0;
        if (action === 'trash') {
          $counts.eq(0).text(Math.max(0, allN - count));
          $counts.eq(1).text(trashN + count);
        } else if (action === 'restore') {
          $counts.eq(0).text(allN + count);
          $counts.eq(1).text(Math.max(0, trashN - count));
        } else if (action === 'delete_permanent') {
          $counts.eq(1).text(Math.max(0, trashN - count));
        }
        // Keep visible-count in sync too
        var vis = parseInt($('#hl-visible-count').text()) || 0;
        $('#hl-visible-count').text(Math.max(0, vis - count));
      }

      function removeRows(items, action) {
        var total = items.length, done = 0;
        items.forEach(function(item){
          var $row = $('.hl-row[data-id="'+item.id+'"][data-ltype="'+item.ltype+'"]');
          $row.next('.hl-detail-row').remove();
          $row.fadeOut(200, function(){
            $(this).remove();
            updateBulkBar();
            done++;
            if (done === total && $('.hl-row').length === 0) {
              $('.hl-bulk-bar, .hl-toolbar, .hl-results-count, .hl-table-card').hide();
              $('#hl-empty-after-remove').show();
            }
          });
        });
        if (action) adjustTabCounts(action, items.length);
      }

      // ── Row-level trash button ──
      $(document).on('click', '.hl-trash-btn', function(e){
        e.stopPropagation();
        var $btn = $(this), id = $btn.data('id'), ltype = $btn.data('ltype');
        leadAction('trash', [{id:id,ltype:ltype}], function(){
          removeRows([{id:id,ltype:ltype}], 'trash');
        });
      });

      // ── Row-level restore button ──
      $(document).on('click', '.hl-restore-btn', function(e){
        e.stopPropagation();
        var $btn = $(this), id = $btn.data('id'), ltype = $btn.data('ltype');
        leadAction('restore', [{id:id,ltype:ltype}], function(){
          removeRows([{id:id,ltype:ltype}], 'restore');
        });
      });

      // ── Row-level permanent delete button ──
      $(document).on('click', '.hl-delete-btn', function(e){
        e.stopPropagation();
        if (!confirm('Permanently delete this lead? This cannot be undone.')) return;
        var $btn = $(this), id = $btn.data('id'), ltype = $btn.data('ltype');
        leadAction('delete_permanent', [{id:id,ltype:ltype}], function(){
          removeRows([{id:id,ltype:ltype}], 'delete_permanent');
        });
      });

      // ── Bulk actions ──
      $('#hl-bulk-trash').on('click', function(){
        var items = getSelected();
        if (!items.length) return;
        leadAction('trash', items, function(){ removeRows(items, 'trash'); });
      });
      $('#hl-bulk-restore').on('click', function(){
        var items = getSelected();
        if (!items.length) return;
        leadAction('restore', items, function(){ removeRows(items, 'restore'); });
      });
      $('#hl-bulk-delete').on('click', function(){
        var items = getSelected();
        if (!items.length) return;
        if (!confirm('Permanently delete ' + items.length + ' lead(s)? This cannot be undone.')) return;
        leadAction('delete_permanent', items, function(){ removeRows(items, 'delete_permanent'); });
      });

      // ── Search + filters ──
      var $search = $('#hl-search'), $date = $('#hl-filter-date'), $data = $('#hl-filter-data'), $plat = $('#hl-filter-platform');
      var debounceTimer;
      $search.on('input', function(){ clearTimeout(debounceTimer); debounceTimer = setTimeout(filterTable, 150); });
      $date.on('change', filterTable);
      $data.on('change', filterTable);
      $plat.on('change', filterTable);

      function filterTable(){
        var query = $search.val().toLowerCase().trim();
        var dateFilter = $date.val();
        var dataFilter = $data.val();
        var platFilter = $plat.val();
        var td = new Date(); td.setHours(0,0,0,0);
        var todayTs = Math.floor(td.getTime()/1000);
        var weekTs = todayTs - 604800;
        var monthTs = todayTs - 2592000;
        var visible = 0;

        $('.hl-row').each(function(){
          var $row = $(this), $detail = $row.next('.hl-detail-row'), show = true;
          if (query) {
            var n = ($row.data('name')||'').toString();
            var e = ($row.data('email')||'').toString();
            var p = ($row.data('phone')||'').toString();
            if (n.indexOf(query)===-1 && e.indexOf(query)===-1 && p.indexOf(query)===-1) show = false;
          }
          if (dateFilter && show) {
            var ts = parseInt($row.data('timestamp'))||0;
            if (dateFilter==='today' && ts<todayTs) show=false;
            if (dateFilter==='week' && ts<weekTs) show=false;
            if (dateFilter==='month' && ts<monthTs) show=false;
          }
          if (dataFilter && show) {
            var hd = $row.data('has-data');
            if (dataFilter==='has-data' && !hd) show=false;
            if (dataFilter==='no-data' && hd) show=false;
          }
          if (platFilter && show) {
            var rowPlat = ($row.data('platform')||'').toString().toLowerCase();
            if (rowPlat.indexOf(platFilter) === -1) show=false;
          }
          $row.toggle(show);
          if (!show) $detail.hide();
          if (show) visible++;
        });
        $('#hl-visible-count').text(visible);
        $('#hl-no-results').toggle(visible === 0);
        $('#hl-leads-table thead').toggle(visible > 0);
      }

      // ── Copy webhook URL ──
      $('#hl-copy-webhook').on('click', function(){
        var url = $('#hl-webhook-url').text();
        var $btn = $(this);
        navigator.clipboard.writeText(url).then(function(){
          $btn.text('Copied!');
          setTimeout(function(){ $btn.html('<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy URL'); }, 2000);
        }).catch(function(){
          var ta = document.createElement('textarea'); ta.value = url; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
          $btn.text('Copied!');
          setTimeout(function(){ $btn.html('<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copy URL'); }, 2000);
        });
      });

      // ── Column sorting (col indices shifted +1 for checkbox column) ──
      var sortDir = {};
      $('.hl-sortable').on('click', function(){
        var col = parseInt($(this).data('col'));
        sortDir[col] = !sortDir[col];
        var $tbody = $('#hl-leads-table tbody');
        var pairs = [];
        $('.hl-row').each(function(){
          pairs.push({ main: $(this), detail: $(this).next('.hl-detail-row') });
        });
        pairs.sort(function(a,b){
          var aT, bT;
          if (col===1) {
            aT = parseInt(a.main.data('timestamp'))||0;
            bT = parseInt(b.main.data('timestamp'))||0;
          } else {
            aT = a.main.find('td').eq(col).text().trim().toLowerCase();
            bT = b.main.find('td').eq(col).text().trim().toLowerCase();
          }
          if (aT < bT) return sortDir[col] ? -1 : 1;
          if (aT > bT) return sortDir[col] ? 1 : -1;
          return 0;
        });
        pairs.forEach(function(p){ $tbody.append(p.main); $tbody.append(p.detail); });
        $('.hl-sortable .hl-sort-icon').text('↕');
        $(this).find('.hl-sort-icon').text(sortDir[col] ? '↑' : '↓');
      });

      // ── Read more / show less for long field values ──
      $(document).on('click', '.hl-readmore-btn', function(e){
        e.stopPropagation();
        var $btn   = $(this);
        var $short = $btn.siblings('.hl-val-short');
        var $full  = $btn.siblings('.hl-val-full');
        if ($full.is(':hidden')) {
          $short.hide(); $full.show(); $btn.text('Show less');
        } else {
          $short.show(); $full.hide(); $btn.text('Read more');
        }
      });
    });
    </script>

    <!-- Styles -->
    <style>
    .hozio-leads-wrap {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      max-width: 1400px;
      margin: 0;
      padding: 20px 20px 40px 0;
      color: #1e293b;
    }
    .hozio-leads-wrap *, .hozio-leads-wrap *::before, .hozio-leads-wrap *::after { box-sizing: border-box; }

    .hl-header { margin-bottom: 24px; }
    .hl-title { font-size: 26px; font-weight: 700; color: #0f172a; margin: 0 0 4px; line-height: 1.2; }
    .hl-subtitle { font-size: 14px; color: #64748b; margin: 0; }

    .hl-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
    .hl-stat-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; display:flex; align-items:center; gap:16px; transition:box-shadow 0.2s; }
    .hl-stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .hl-stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .hl-stat-content { display:flex; flex-direction:column; }
    .hl-stat-number { font-size:28px; font-weight:700; line-height:1.1; color:#0f172a; }
    .hl-stat-label { font-size:13px; color:#64748b; font-weight:500; margin-top:2px; }

    .hl-empty-state { background:#fff; border:2px dashed #e2e8f0; border-radius:16px; padding:60px 40px; text-align:center; }
    .hl-empty-state h2 { font-size:20px; color:#475569; margin:16px 0 8px; }
    .hl-empty-state p { color:#94a3b8; font-size:15px; margin:0; }

    .hl-toolbar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
    .hl-search-box { flex:1; min-width:240px; position:relative; }
    .hl-search-box svg { position:absolute; left:16px; top:50%; transform:translateY(-50%); pointer-events:none; }
    .hl-search-box input { width:100%; padding:10px 14px 10px 52px !important; border:1px solid #e2e8f0; border-radius:10px; font-size:14px; background:#fff; color:#1e293b; transition:border-color 0.2s,box-shadow 0.2s; outline:none; box-sizing:border-box !important; }
    .hl-search-box input:focus { border-color:#818cf8; box-shadow:0 0 0 3px rgba(129,140,248,0.15); }
    .hl-search-box input::placeholder { color:#94a3b8; }
    .hl-filters { display:flex; gap:8px; flex-wrap:wrap; }
    .hl-filters select { padding:10px 32px 10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:13px; background:#fff; color:#475569; cursor:pointer; outline:none; appearance:none; background-image:url("data:image/svg+xml,%3Csvg width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' xmlns='http://www.w3.org/2000/svg'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; }
    .hl-filters select:focus { border-color:#818cf8; box-shadow:0 0 0 3px rgba(129,140,248,0.15); }

    .hl-results-count { font-size:13px; color:#94a3b8; margin-bottom:8px; }

    .hl-table-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px 12px 0 0; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
    .hl-table { width:100%; border-collapse:collapse; }
    .hl-table thead th { padding:12px 16px; background:#f8fafc; color:#64748b; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #e2e8f0; text-align:left; white-space:nowrap; user-select:none; }
    .hl-sortable { cursor:pointer; }
    .hl-sortable:hover { color:#334155; }
    .hl-sort-icon { font-size:12px; color:#cbd5e1; margin-left:4px; }
    .hl-table tbody td { padding:14px 16px; border-bottom:1px solid #f1f5f9; font-size:14px; vertical-align:middle; }
    .hl-table tbody tr.hl-row:last-of-type td { border-bottom:none; }

    .hl-row { transition:background 0.15s; cursor:pointer; }
    .hl-row:hover td { background:#f8fafc; }
    .hl-row:hover .hl-expand-btn { background:#f1f5f9; border-color:#cbd5e1; color:#475569; }
    .hl-row-active td { background:#f1f5f9 !important; }
    .hl-row-dim td { opacity:0.5; }
    .hl-row-dim:hover td { opacity:0.8; }

    .hl-date-cell { display:flex; flex-direction:column; }
    .hl-date-primary { font-weight:500; color:#334155; font-size:13px; }
    .hl-date-secondary { font-size:11px; color:#94a3b8; margin-top:1px; }
    .hl-name { font-weight:600; color:#1e293b; }
    .hl-email, .hl-phone { color:#3b82f6; text-decoration:none; font-size:13px; }
    .hl-email:hover, .hl-phone:hover { text-decoration:underline; }
    .hl-empty { color:#cbd5e1; font-size:13px; }
    .hl-source { color:#94a3b8; font-size:12px; }

    .hl-badge { display:inline-block; padding:3px 10px; border-radius:100px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; }
    .hl-badge-success { background:#d1fae5; color:#065f46; }
    .hl-badge-error { background:#fee2e2; color:#991b1b; }
    .hl-badge-new { background:#4f46e5; color:#ffffff; }

    .hl-expand-btn { display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border:1px solid #e2e8f0; border-radius:8px; color:#64748b; font-size:12px; font-weight:500; transition:all 0.2s; cursor:pointer; user-select:none; }
    .hl-expand-btn:hover { background:#f1f5f9; border-color:#cbd5e1; color:#475569; }
    .hl-expand-btn svg { transition:transform 0.2s; flex-shrink:0; }
    .hl-expand-btn.hl-expanded svg { transform:rotate(180deg); }
    .hl-expand-btn.hl-expanded { background:#eef2ff; border-color:#818cf8; color:#4f46e5; }
    .hl-expand-label { white-space:nowrap; }

    /* Detail panel — inline expandable */
    .hl-detail-row td { padding:0 !important; border-bottom:2px solid #e0e7ff !important; background:#fff; }
    .hl-detail-panel { border-top:3px solid #818cf8; }
    .hl-dp-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; padding:12px 20px; background:#f5f3ff; border-bottom:1px solid #e0e7ff; }
    .hl-dp-head-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .hl-dp-id { font-size:13px; font-weight:700; color:#4338ca; }
    .hl-dp-score { font-size:10px; opacity:0.7; margin-left:2px; }
    .hl-dp-meta { display:flex; align-items:center; gap:6px; font-size:12px; color:#6b7280; flex-wrap:wrap; }
    .hl-dp-date { display:inline-flex; align-items:center; gap:4px; }
    .hl-dp-dot { color:#d1d5db; }
    .hl-dp-source { display:inline-flex; align-items:center; gap:3px; color:#4f46e5; text-decoration:none; font-weight:500; font-size:12px; }
    .hl-dp-source:hover { text-decoration:underline; }
    .hl-dp-flags { display:flex; align-items:center; gap:5px; padding:6px 20px; background:#fffbeb; border-bottom:1px solid #fde68a; font-size:11px; color:#92400e; font-style:italic; }
    .hl-detail-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); padding:16px 20px 8px; gap:0; }
    .hl-detail-field { padding:8px 14px 10px 0; border-bottom:1px solid #f1f5f9; }
    .hl-detail-field-wide { grid-column:1 / -1; }
    .hl-detail-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:#94a3b8; display:block; margin-bottom:4px; }
    .hl-detail-value { font-size:13.5px; color:#1e293b; word-break:break-word; line-height:1.5; }
    .hl-detail-value a { color:#3b82f6; text-decoration:none; }
    .hl-detail-value a:hover { text-decoration:underline; }
    .hl-detail-empty { color:#94a3b8; font-style:italic; font-size:13px; display:block; padding:16px 20px; }
    .hl-val-full { display:inline; }
    .hl-readmore-btn { display:inline; background:none; border:none; padding:0 0 0 4px; font-size:12px; color:#4f46e5; font-weight:600; cursor:pointer; text-decoration:underline; vertical-align:baseline; line-height:inherit; }
    .hl-readmore-btn:hover { color:#4338ca; }
    .hl-dp-footer { padding:12px 20px; background:#fafbff; border-top:1px solid #e0e7ff; display:flex; justify-content:flex-end; }
    .hl-btn-view { display:inline-flex; align-items:center; gap:6px; padding:8px 20px; background:#4f46e5; color:#fff !important; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none !important; transition:background 0.2s; }
    .hl-btn-view:hover { background:#4338ca; }

    /* Pagination */
    .hl-pagination { display:flex; gap:6px; margin-top:20px; justify-content:center; }
    .hl-page-btn { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:500; color:#475569; text-decoration:none; transition:all 0.15s; }
    .hl-page-btn:hover { background:#f1f5f9; border-color:#cbd5e1; }
    .hl-page-active { background:#4f46e5 !important; color:#fff !important; border-color:#4f46e5 !important; }

    @media (max-width:1024px) { .hl-stats { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width:768px) {
      .hozio-leads-wrap { padding:12px 12px 30px 0; }
      .hl-stats { grid-template-columns:1fr 1fr; gap:10px; }
      .hl-stat-card { padding:14px; }
      .hl-stat-number { font-size:22px; }
      .hl-toolbar { flex-direction:column; }
      .hl-search-box { min-width:100%; }
      .hl-filters { width:100%; }
      .hl-filters select { flex:1; }
      .hl-table-card { border:none; border-radius:0; box-shadow:none; }
      .hl-table thead { display:none; }
      .hl-table, .hl-table tbody, .hl-table tr { display:block; width:100%; }
      .hl-row { margin-bottom:12px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; overflow:hidden; }
      .hl-table tbody td { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid #f1f5f9; }
      .hl-table tbody td::before { content:attr(data-label); font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:0.3px; flex-shrink:0; margin-right:12px; }
      .hl-table tbody td:last-child { justify-content:center; padding:8px; }
      .hl-table tbody td:last-child::before { display:none; }
      .hl-detail-row { margin-bottom:12px; }
      .hl-detail-row td { border-radius:0 0 12px 12px !important; }
      .hl-dp-head { flex-direction:column; align-items:flex-start; }
      .hl-detail-grid { grid-template-columns:1fr !important; padding:12px 14px 4px; }
    }
    @media (max-width:480px) { .hl-stats { grid-template-columns:1fr; } }

    /* Webhook banner */
    .hl-webhook-banner { background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:14px 18px; margin-bottom:20px; }
    .hl-webhook-info { display:flex; align-items:center; gap:8px; font-size:13px; color:#0c4a6e; margin-bottom:8px; }
    .hl-webhook-url-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; flex-wrap:wrap; }
    .hl-webhook-url-row code { flex:1; background:#fff; border:1px solid #bae6fd; border-radius:6px; padding:7px 12px; font-size:12px; color:#0369a1; word-break:break-all; }
    .hl-copy-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 12px; background:#0ea5e9; color:#fff; border:none; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; transition:background 0.15s, transform 0.15s, box-shadow 0.15s; }
    .hl-copy-btn:hover { background:#0284c7; transform:translateY(-1px); box-shadow:0 4px 10px rgba(2,132,199,0.35); color:#fff; }
    .hl-webhook-hint { font-size:11px; color:#64748b; margin:0; }
    .hl-webhook-hint code { background:#e0f2fe; padding:1px 4px; border-radius:3px; font-size:11px; color:#0369a1; }

    /* Platform badges */
    .hl-platform-cell { display:flex; flex-direction:column; gap:2px; }
    .hl-platform-badge { display:inline-block; padding:2px 8px; border-radius:100px; font-size:11px; font-weight:600; white-space:nowrap; }
    .hl-platform-page { font-size:11px; color:#94a3b8; }
    .hl-plat-elementor { background:#ede9fe; color:#5b21b6; }
    .hl-plat-wpforms { background:#dbeafe; color:#1d4ed8; }
    .hl-plat-gravityforms { background:#d1fae5; color:#065f46; }
    .hl-plat-contactform7, .hl-plat-cf7, .hl-plat-contactform7 { background:#fef3c7; color:#92400e; }
    .hl-plat-fluentforms { background:#fce7f3; color:#9d174d; }
    .hl-plat-divi { background:#fdf4ff; color:#7e22ce; }
    .hl-plat-gravityforms { background:#d1fae5; color:#065f46; }
    .hl-plat-zapier { background:#fff7ed; color:#c2410c; }
    .hl-plat-typeform { background:#f0fdf4; color:#166534; }
    /* All other platforms get a neutral style */
    .hl-platform-badge:not([class*="hl-plat-"]),
    .hl-plat-unknown { background:#f1f5f9; color:#475569; }

    /* View tabs */
    .hl-view-tabs { display:flex; gap:4px; margin-bottom:20px; border-bottom:2px solid #e2e8f0; }
    .hl-view-tab { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; font-size:13px; font-weight:500; color:#64748b; text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px; transition:color 0.15s,border-color 0.15s; }
    .hl-view-tab:hover { color:#334155; }
    .hl-tab-active { color:#4f46e5 !important; border-bottom-color:#4f46e5 !important; }
    .hl-tab-count { display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:18px; padding:0 6px; background:#e2e8f0; color:#64748b; border-radius:100px; font-size:11px; font-weight:600; }
    .hl-tab-active .hl-tab-count { background:#eef2ff; color:#4f46e5; }

    /* Bulk action bar */
    .hl-bulk-bar { display:flex; align-items:center; gap:10px; padding:10px 16px; background:#eef2ff; border:1px solid #c7d2fe; border-radius:10px; margin-bottom:12px; flex-wrap:wrap; opacity:0; max-height:0; overflow:hidden; transition:opacity 0.2s,max-height 0.2s; }
    .hl-bulk-bar.hl-bulk-bar-active { opacity:1; max-height:60px; }
    .hl-bulk-count { font-size:13px; font-weight:600; color:#4f46e5; margin-right:4px; }
    .hl-bulk-btn { padding:6px 14px; border-radius:7px; font-size:12px; font-weight:600; cursor:pointer; border:none; transition:background 0.15s; }
    .hl-bulk-trash-btn { background:#fee2e2; color:#991b1b; }
    .hl-bulk-trash-btn:hover { background:#fecaca; }
    .hl-bulk-restore-btn { background:#d1fae5; color:#065f46; }
    .hl-bulk-restore-btn:hover { background:#a7f3d0; }
    .hl-bulk-delete-btn { background:#fee2e2; color:#991b1b; }
    .hl-bulk-delete-btn:hover { background:#fecaca; }
    .hl-bulk-cancel-btn { background:#f1f5f9; color:#475569; }
    .hl-bulk-cancel-btn:hover { background:#e2e8f0; }

    /* Checkbox column */
    .hl-col-cb { width:36px; padding:12px 8px 12px 16px !important; }
    .hl-cb-cell { padding:14px 8px 14px 16px !important; }
    .hl-col-cb input, .hl-cb-cell input { cursor:pointer; width:15px; height:15px; accent-color:#4f46e5; }

    /* Row action buttons */
    .hl-action-cell { padding:10px 12px !important; }
    .hl-action-group { display:flex; align-items:center; gap:6px; }
    .hl-row-action-btn { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border:1px solid #e2e8f0; border-radius:7px; background:#fff; color:#94a3b8; cursor:pointer; transition:all 0.15s; flex-shrink:0; }
    .hl-trash-btn:hover { border-color:#fca5a5; background:#fee2e2; color:#dc2626; }
    .hl-restore-btn:hover { border-color:#86efac; background:#d1fae5; color:#15803d; }
    .hl-delete-btn:hover { border-color:#fca5a5; background:#fee2e2; color:#dc2626; }

    /* Spam score badge */
    .hl-spam-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:100px; font-size:11px; font-weight:600; }
    .hl-spam-clean { background:#d1fae5; color:#065f46; }
    .hl-spam-review { background:#fef3c7; color:#92400e; }
    .hl-spam-test { background:#fed7aa; color:#c2410c; }
    .hl-spam-spam { background:#fee2e2; color:#991b1b; }
    .hl-spam-flags { font-size:11px; color:#94a3b8; font-style:italic; }

    /* Export button + dropdown */
    .hl-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
    .hl-export-wrap { position:relative; flex-shrink:0; align-self:center; }
    .hl-export-btn { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; background:#4f46e5; color:#fff; border:none; border-radius:9px; font-size:13px; font-weight:600; cursor:pointer; transition:background 0.15s; white-space:nowrap; }
    .hl-export-btn:hover { background:#4338ca; }
    .hl-export-dropdown { display:none; position:absolute; right:0; top:calc(100% + 6px); background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.10); min-width:180px; z-index:999; overflow:hidden; }
    .hl-export-dropdown.hl-dd-open { display:block; }
    .hl-export-dd-title { padding:10px 14px 6px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; }
    .hl-export-option { display:block; padding:9px 14px; font-size:13px; color:#1e293b; text-decoration:none; transition:background 0.12s; }
    .hl-export-option:hover { background:#f1f5f9; color:#1e293b; }
    </style>
    <?php
}


// ══════════════════════════════════════════════════════════
// 4. ADMIN PAGE: Single Submission View
// ══════════════════════════════════════════════════════════
function hozio_lead_view_page() {
    // Verify nonce
    if (
        ! isset( $_GET['_hlnonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_hlnonce'] ) ), 'hozio_view_lead' )
    ) {
        wp_die( 'Security check failed. Please go back and try again.', 'Unauthorized', [ 'back_link' => true ] );
    }

    global $wpdb;
    $id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $ltype = isset( $_GET['ltype'] ) ? sanitize_key( $_GET['ltype'] ) : 'elementor';

    if ( ! $id ) {
        echo '<div class="wrap"><h1>Submission Not Found</h1><p>Invalid submission ID.</p></div>';
        return;
    }

    $back_url = admin_url( 'admin.php?page=hozio-leads' );

    // ── Custom (hozio) lead ──────────────────────────────────
    if ( $ltype === 'hozio' ) {
        if ( ! hozio_custom_leads_table_exists() ) {
            echo '<div class="wrap"><h1>Error</h1><p>Custom leads table not found.</p></div>';
            return;
        }
        $table = $wpdb->prefix . 'hozio_leads';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) );
        if ( ! $row ) {
            echo '<div class="wrap"><h1>Submission Not Found</h1><p>This submission does not exist.</p></div>';
            return;
        }
        $submission = (object) [
            'id'         => (int) $row->id,
            'created_at' => $row->created_at,
            'status'     => 'completed',
            'platform'   => $row->source ?: 'Unknown',
            'referer'    => $row->referer,
        ];
        $raw_fields   = json_decode( $row->fields, true ) ?: [];
        // Normalize to [ ['key'=>..., 'value'=>...], ... ] for the view template
        $fields       = [];
        foreach ( $raw_fields as $k => $v ) {
            $fields[] = [ 'key' => $k, 'value' => (string) $v ];
        }
        $form_page    = $row->source ?: '—';
        $field_labels = [];
        $badge        = [ 'Success', 'hl-badge-success' ];
    } else {
        // ── Elementor lead ──────────────────────────────────────
        if ( ! hozio_submissions_tables_exist() ) {
            echo '<div class="wrap"><h1>Error</h1><p>Submissions table not found.</p></div>';
            return;
        }
        $subs = $wpdb->prefix . 'e_submissions';
        $vals = $wpdb->prefix . 'e_submissions_values';

        $submission = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$subs}` WHERE id = %d AND status <> %s", $id, 'trash' )
        );
        if ( ! $submission ) {
            echo '<div class="wrap"><h1>Submission Not Found</h1><p>This submission does not exist.</p></div>';
            return;
        }

        $fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT `key`, `value` FROM `{$vals}` WHERE submission_id = %d ORDER BY id ASC",
                $id
            ), ARRAY_A
        );

        $submission->platform = 'Elementor';
        $form_page    = $submission->post_id ? get_the_title( $submission->post_id ) : '—';
        $field_labels = hozio_get_form_field_labels( (int) $submission->post_id );
        $badge        = hozio_status_badge( $submission->status );
    }

    // Compute spam score for view page
    $spam_v_fields = [];
    foreach ( $fields as $f ) {
        if ( isset( $f['key'] ) && $f['key'] !== '' ) $spam_v_fields[ $f['key'] ] = $f['value'];
    }
    if ( $ltype === 'hozio' ) {
        $spam_v = hozio_lead_spam_score( $row->name, $row->email, $row->phone, $spam_v_fields, $row->referer ?? '' );
    } else {
        $sv_name  = trim( ( $spam_v_fields['fname'] ?? '' ) . ' ' . ( $spam_v_fields['lname'] ?? '' ) );
        $sv_email = $spam_v_fields['email'] ?? '';
        $sv_phone = $spam_v_fields['tel']   ?? '';
        $spam_v   = hozio_lead_spam_score( $sv_name, $sv_email, $sv_phone, $spam_v_fields, $submission->referer ?? '' );
    }

    $badge_map = [
        'hl-badge-success' => 'hlv-badge-success',
        'hl-badge-error'   => 'hlv-badge-error',
        'hl-badge-new'     => 'hlv-badge-new',
    ];
    $badge_cls = $badge_map[ $badge[1] ] ?? 'hlv-badge-new';
    $plat_slug_v = strtolower( preg_replace( '/[^a-z0-9]/i', '', $submission->platform ) );
    ?>
    <div class="hlv-wrap">

      <!-- Page Header -->
      <div class="hlv-page-head">
        <a href="<?php echo esc_url( $back_url ); ?>" class="hlv-back">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
          Back to Leads
        </a>
        <div class="hlv-title-row">
          <h1 class="hlv-title">Submission #<?php echo esc_html( $id ); ?></h1>
          <div class="hlv-title-badges">
            <span class="hlv-status-badge <?php echo esc_attr( $badge_cls ); ?>"><?php echo esc_html( $badge[0] ); ?></span>
            <span class="hl-spam-badge <?php echo esc_attr( $spam_v['class'] ); ?>">
              <?php if ( $spam_v['score'] > 0 ) : ?>
                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <?php else : ?>
                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <?php endif; ?>
              <?php echo esc_html( $spam_v['label'] ); ?>
              <?php if ( $spam_v['score'] > 0 ) echo '<span style="opacity:.7;margin-left:2px;font-size:10px;">(' . esc_html( $spam_v['score'] ) . '%)</span>'; ?>
            </span>
          </div>
        </div>
        <?php if ( ! empty( $spam_v['flags'] ) ) : ?>
          <p class="hlv-flags">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php echo esc_html( implode( ' · ', $spam_v['flags'] ) ); ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Meta strip -->
      <div class="hlv-meta-strip">
        <div class="hlv-meta-item">
          <span class="hlv-meta-label">Date Submitted</span>
          <span class="hlv-meta-value"><?php echo esc_html( date_i18n( 'F j, Y \a\t g:i A', strtotime( $submission->created_at ) ) ); ?></span>
        </div>
        <div class="hlv-meta-divider"></div>
        <div class="hlv-meta-item">
          <span class="hlv-meta-label">Platform</span>
          <span class="hlv-meta-value">
            <span class="hl-platform-badge hl-plat-<?php echo esc_attr( $plat_slug_v ); ?>"><?php echo esc_html( $submission->platform ); ?></span>
            <?php if ( $form_page && $form_page !== '—' && $form_page !== $submission->platform ) : ?>
              <span class="hlv-meta-sub"><?php echo esc_html( $form_page ); ?></span>
            <?php endif; ?>
          </span>
        </div>
        <?php if ( ! empty( $submission->referer ) ) : ?>
          <div class="hlv-meta-divider"></div>
          <div class="hlv-meta-item">
            <span class="hlv-meta-label">Source Page</span>
            <span class="hlv-meta-value">
              <a href="<?php echo esc_url( $submission->referer ); ?>" target="_blank" rel="noopener noreferrer" class="hlv-link">
                <?php echo esc_html( $submission->referer ); ?>
                <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              </a>
            </span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Fields Card -->
      <div class="hlv-card">
        <div class="hlv-card-head">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Submitted Fields
        </div>
        <?php if ( empty( $fields ) ) : ?>
          <p class="hlv-empty">No field data found.</p>
        <?php else : ?>
          <div class="hlv-fields-grid">
            <?php foreach ( $fields as $f ) :
                if ( $f['value'] === '' || $f['value'] === null ) continue;
                $raw_key  = $f['key'];
                $fl       = $field_labels[ $raw_key ] ?? ucwords( str_replace( [ '_', '-' ], ' ', $raw_key ) );
                $value    = $f['value'];
                $is_long  = strlen( $value ) > 150;
                $display  = esc_html( $value );
                if ( is_email( $value ) ) {
                    $display = '<a href="mailto:' . esc_attr( $value ) . '" class="hlv-link">' . esc_html( $value ) . '</a>';
                } elseif ( preg_match( '/^[\+\d\s\-\(\)]{7,}$/', $value ) ) {
                    $display = '<a href="tel:' . esc_attr( preg_replace( '/[^\d\+]/', '', $value ) ) . '" class="hlv-link">' . esc_html( $value ) . '</a>';
                }
            ?>
              <div class="hlv-field <?php echo $is_long ? 'hlv-field-wide' : ''; ?>">
                <span class="hlv-field-label"><?php echo esc_html( $fl ); ?></span>
                <span class="hlv-field-value"><?php echo $display; ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <style>
    .hlv-wrap { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; max-width:900px; padding:20px 20px 60px 0; color:#1e293b; }
    .hlv-wrap *, .hlv-wrap *::before, .hlv-wrap *::after { box-sizing:border-box; }

    /* Header */
    .hlv-back { display:inline-flex; align-items:center; gap:5px; padding:7px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:500; color:#64748b; text-decoration:none; transition:all 0.15s; background:#fff; margin-bottom:14px; }
    .hlv-back:hover { border-color:#cbd5e1; background:#f8fafc; color:#334155; }
    .hlv-page-head { margin-bottom:16px; }
    .hlv-title-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:6px; }
    .hlv-title { font-size:24px; font-weight:700; color:#0f172a; margin:0; line-height:1.2; }
    .hlv-title-badges { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .hlv-status-badge { display:inline-block; padding:4px 14px; border-radius:100px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; }
    .hlv-badge-success { background:#d1fae5; color:#065f46; }
    .hlv-badge-error { background:#fee2e2; color:#991b1b; }
    .hlv-badge-new { background:#4f46e5; color:#fff; }
    .hlv-flags { display:inline-flex; align-items:center; gap:5px; font-size:12px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:7px; padding:5px 11px; margin:6px 0 0; }

    /* Meta strip */
    .hlv-meta-strip { display:flex; align-items:stretch; background:#fff; border:1px solid #e2e8f0; border-top:3px solid #818cf8; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.04); margin-bottom:16px; flex-wrap:wrap; }
    .hlv-meta-item { padding:16px 20px; display:flex; flex-direction:column; gap:4px; flex:1; min-width:160px; }
    .hlv-meta-divider { width:1px; background:#f1f5f9; margin:12px 0; flex-shrink:0; }
    .hlv-meta-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:#94a3b8; }
    .hlv-meta-value { font-size:14px; font-weight:500; color:#1e293b; display:flex; align-items:center; gap:6px; flex-wrap:wrap; word-break:break-all; }
    .hlv-meta-sub { font-size:12px; color:#94a3b8; font-weight:400; }
    .hlv-link { color:#4f46e5; text-decoration:none; display:inline-flex; align-items:center; gap:3px; word-break:break-all; font-weight:500; }
    .hlv-link:hover { text-decoration:underline; }

    /* Fields card */
    .hlv-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.04); margin-bottom:16px; }
    .hlv-card-head { display:flex; align-items:center; gap:7px; padding:13px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:12px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.5px; }
    .hlv-fields-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); }
    .hlv-field { padding:16px 20px; border-bottom:1px solid #f8fafc; border-right:1px solid #f8fafc; display:flex; flex-direction:column; gap:5px; }
    .hlv-field:last-child { border-bottom:none; }
    .hlv-field-wide { grid-column:1 / -1; }
    .hlv-field-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color:#94a3b8; }
    .hlv-field-value { font-size:14px; color:#1e293b; word-break:break-word; line-height:1.6; }
    .hlv-field-value .hlv-link { font-size:14px; }
    .hlv-empty { color:#94a3b8; font-style:italic; padding:20px; margin:0; font-size:13px; display:block; }

    @media (max-width:640px) {
      .hlv-wrap { padding:12px 12px 40px 0; }
      .hlv-title { font-size:18px; }
      .hlv-meta-strip { flex-direction:column; }
      .hlv-meta-divider { width:auto; height:1px; margin:0 12px; }
      .hlv-fields-grid { grid-template-columns:1fr; }
      .hlv-field-wide { grid-column:auto; }
    }
    </style>
    <?php
}


// ══════════════════════════════════════════════════════════
// 4b. LEAD TRASH / RESTORE / DELETE — AJAX handler
//     Single and bulk. Action: hozio_lead_action
// ══════════════════════════════════════════════════════════
add_action( 'wp_ajax_hozio_lead_action', function() {
    check_ajax_referer( 'hozio_lead_action' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    global $wpdb;
    $lead_action = sanitize_key( $_POST['lead_action'] ?? '' );
    $raw_leads   = isset( $_POST['leads'] ) ? json_decode( stripslashes( $_POST['leads'] ), true ) : null;

    if ( ! is_array( $raw_leads ) ) {
        wp_send_json_error( 'Invalid data' );
    }

    foreach ( $raw_leads as $item ) {
        $id    = absint( $item['id'] ?? 0 );
        $ltype = sanitize_key( $item['ltype'] ?? 'elementor' );
        if ( ! $id ) continue;

        if ( $lead_action === 'trash' ) {
            if ( $ltype === 'hozio' ) {
                $wpdb->update( $wpdb->prefix . 'hozio_leads', [ 'deleted_at' => current_time( 'mysql', true ) ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
            } else {
                $wpdb->update( $wpdb->prefix . 'e_submissions', [ 'status' => 'trash' ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
            }
        } elseif ( $lead_action === 'restore' ) {
            if ( $ltype === 'hozio' ) {
                $wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->prefix}hozio_leads` SET deleted_at = NULL WHERE id = %d", $id ) );
            } else {
                $wpdb->update( $wpdb->prefix . 'e_submissions', [ 'status' => 'completed' ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
            }
        } elseif ( $lead_action === 'delete_permanent' ) {
            if ( $ltype === 'hozio' ) {
                $wpdb->delete( $wpdb->prefix . 'hozio_leads', [ 'id' => $id ], [ '%d' ] );
            } else {
                $wpdb->delete( $wpdb->prefix . 'e_submissions', [ 'id' => $id ], [ '%d' ] );
                $wpdb->delete( $wpdb->prefix . 'e_submissions_values', [ 'submission_id' => $id ], [ '%d' ] );
            }
        }
    }

    wp_send_json_success();
} );


// ══════════════════════════════════════════════════════════
// 4c. CSV EXPORT — AJAX handler
//     Fetched via JS fetch() so the page URL never changes
// ══════════════════════════════════════════════════════════
add_action( 'wp_ajax_hozio_export_leads', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'hozio_export_leads' ) ) {
        wp_die( 'Security check failed.' );
    }

    $range = sanitize_key( $_GET['range'] ?? 'all' );
    $from  = null;
    $to    = null;

    switch ( $range ) {
        case 'today':
            $from = gmdate( 'Y-m-d 00:00:00' );
            break;
        case 'yesterday':
            $from = gmdate( 'Y-m-d 00:00:00', strtotime( '-1 day' ) );
            $to   = gmdate( 'Y-m-d 00:00:00' );
            break;
        case 'week':
            $from = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
            break;
        case '3months':
            $from = gmdate( 'Y-m-d H:i:s', strtotime( '-3 months' ) );
            break;
        case '1year':
            $from = gmdate( 'Y-m-d H:i:s', strtotime( '-1 year' ) );
            break;
    }

    global $wpdb;
    $rows = [];

    // ── Elementor leads ──────────────────────────────────
    if ( hozio_submissions_tables_exist() ) {
        $subs = $wpdb->prefix . 'e_submissions';
        $vals = $wpdb->prefix . 'e_submissions_values';
        $sql  = "SELECT id, created_at, post_id, referer FROM `{$subs}` WHERE status <> 'trash'";
        $args = [];
        if ( $from ) { $sql .= ' AND created_at >= %s'; $args[] = $from; }
        if ( $to )   { $sql .= ' AND created_at < %s';  $args[] = $to; }
        $sql .= ' ORDER BY created_at DESC';

        $e_rows = $args
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        if ( $e_rows ) {
            $ids          = array_map( 'intval', wp_list_pluck( $e_rows, 'id' ) );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $all_values   = $wpdb->get_results(
                $wpdb->prepare( "SELECT submission_id, `key`, `value` FROM `{$vals}` WHERE submission_id IN ({$placeholders})", ...$ids ),
                ARRAY_A
            );
            $vmap = [];
            foreach ( $all_values as $v ) {
                $vmap[ (int) $v['submission_id'] ][ $v['key'] ] = $v['value'];
            }
            foreach ( $e_rows as $row ) {
                $m     = $vmap[ (int) $row->id ] ?? [];
                $name  = trim( ( $m['fname'] ?? '' ) . ' ' . ( $m['lname'] ?? '' ) );
                $email = $m['email'] ?? '';
                $phone = $m['tel']   ?? '';
                unset( $m['fname'], $m['lname'], $m['email'], $m['tel'] );
                $rows[] = [
                    'id'         => $row->id,
                    'created_at' => $row->created_at,
                    'platform'   => 'Elementor',
                    'name'       => $name,
                    'email'      => $email,
                    'phone'      => $phone,
                    'referer'    => $row->referer,
                    'fields'     => $m,
                ];
            }
        }
    }

    // ── Custom (hozio) leads ─────────────────────────────
    if ( hozio_custom_leads_table_exists() ) {
        $ht   = $wpdb->prefix . 'hozio_leads';
        $sql  = "SELECT * FROM `{$ht}` WHERE deleted_at IS NULL";
        $args = [];
        if ( $from ) { $sql .= ' AND created_at >= %s'; $args[] = $from; }
        if ( $to )   { $sql .= ' AND created_at < %s';  $args[] = $to; }
        $sql .= ' ORDER BY created_at DESC';

        $c_rows = $args
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        foreach ( $c_rows as $row ) {
            $fields = json_decode( $row->fields, true ) ?: [];
            $rows[] = [
                'id'         => $row->id,
                'created_at' => $row->created_at,
                'platform'   => $row->source ?: 'Unknown',
                'name'       => $row->name,
                'email'      => $row->email,
                'phone'      => $row->phone,
                'referer'    => $row->referer,
                'fields'     => $fields,
            ];
        }
    }

    // Sort merged results by date DESC
    usort( $rows, function( $a, $b ) {
        return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
    } );

    // Collect all unique field keys (for dynamic columns)
    $all_field_keys = [];
    foreach ( $rows as $r ) {
        foreach ( array_keys( $r['fields'] ) as $k ) {
            if ( ! in_array( $k, $all_field_keys, true ) ) {
                $all_field_keys[] = $k;
            }
        }
    }

    // Output CSV
    $domain   = sanitize_file_name( parse_url( home_url(), PHP_URL_HOST ) );
    $filename = 'leads-' . $domain . '-' . gmdate( 'Y-m-d' ) . ( $range !== 'all' ? '-' . $range : '' ) . '.csv';
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    echo "\xEF\xBB\xBF"; // BOM — ensures Excel opens UTF-8 correctly
    $out = fopen( 'php://output', 'w' );

    // Header row
    $headers = [ 'ID', 'Date', 'Platform', 'Name', 'Email', 'Phone', 'Source URL' ];
    foreach ( $all_field_keys as $k ) {
        $headers[] = ucwords( str_replace( [ '_', '-' ], ' ', $k ) );
    }
    fputcsv( $out, $headers );

    // Data rows
    foreach ( $rows as $r ) {
        $row_data = [
            $r['id'],
            $r['created_at'],
            $r['platform'],
            $r['name'],
            $r['email'],
            $r['phone'],
            $r['referer'],
        ];
        foreach ( $all_field_keys as $k ) {
            $row_data[] = $r['fields'][ $k ] ?? '';
        }
        fputcsv( $out, $row_data );
    }

    fclose( $out );
    exit;
} );


// ══════════════════════════════════════════════════════════
// 4e-cors. Allow X-Hozio-Secret in CORS preflight so browser-based
//          integrations (and cross-origin testing) work alongside
//          server-side webhooks. Server-to-server calls (Elementor,
//          Zapier, etc.) are unaffected — CORS is browser-only.
// ══════════════════════════════════════════════════════════
add_filter( 'rest_allowed_cors_headers', function( $headers ) {
    $headers[] = 'X-Hozio-Secret';
    return $headers;
} );

// ══════════════════════════════════════════════════════════
// 4e. REST API — Receive leads from any form platform
//     POST /wp-json/hozio/v1/lead
//     Requires X-Hozio-Secret header matching the per-site secret
//     (Lead Submissions -> Webhook in WP Admin).
//     Rate limited per IP. Honeypot + field validation + CleanTalk soft check.
// ══════════════════════════════════════════════════════════
add_action( 'rest_api_init', function() {
    if ( get_option( 'hozio_webhook_enabled', '0' ) !== '1' ) {
        return;
    }
    register_rest_route( 'hozio/v1', '/lead', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'hozio_rest_receive_lead',
        'permission_callback' => 'hozio_lead_webhook_auth',
    ] );
} );

function hozio_lead_webhook_auth( WP_REST_Request $request ) {
    $secret   = hozio_get_lead_webhook_secret();
    $provided = (string) ( $request->get_header( 'x-hozio-secret' ) ?? '' );

    if ( ! hash_equals( $secret, $provided ) ) {
        $ip = hozio_get_client_ip();
        $ua = substr( (string) ( $request->get_header( 'user-agent' ) ?? '' ), 0, 200 );
        hozio_log_lead_blocked( 'auth_fail', $ip, $ua );
        return new WP_Error( 'unauthorized', 'Invalid or missing webhook secret.', [ 'status' => 401 ] );
    }

    // Rate limit: max 10 authenticated POSTs per IP per 5 minutes.
    $ip    = hozio_get_client_ip();
    $key   = 'hozio_wh_rl_' . md5( $ip );
    $count = (int) get_transient( $key );
    if ( $count >= 10 ) {
        hozio_log_lead_blocked( 'rate_limit', $ip, '' );
        return new WP_Error( 'too_many_requests', 'Rate limit exceeded. Try again later.', [ 'status' => 429 ] );
    }
    set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );

    return true;
}

function hozio_rest_receive_lead( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'hozio_leads';

    // Accept JSON or form-encoded body
    $params = $request->get_json_params();
    if ( empty( $params ) ) {
        $params = $request->get_body_params();
    }
    if ( empty( $params ) ) {
        return new WP_Error( 'no_data', 'No data received.', [ 'status' => 400 ] );
    }

    // Honeypot: silently discard if bot-bait field is populated
    if ( ! empty( $params['website_url'] ) ) {
        return rest_ensure_response( [ 'success' => true, 'id' => 0 ] );
    }

    $email = sanitize_email( $params['email'] ?? '' );
    $phone = sanitize_text_field( $params['phone'] ?? $params['tel'] ?? $params['phone_number'] ?? '' );

    // Require at least one contact method
    if ( empty( $email ) && empty( $phone ) ) {
        return new WP_Error( 'missing_contact', 'An email address or phone number is required.', [ 'status' => 400 ] );
    }

    // CleanTalk soft check — only runs if CleanTalk plugin is active
    if ( function_exists( 'apbct_base_call' ) && ! empty( $email ) ) {
        $ct = apbct_base_call( [ 'email' => $email, 'sender_ip' => hozio_get_client_ip() ], false );
        if ( isset( $ct['allow'] ) && (int) $ct['allow'] === 0 ) {
            hozio_log_lead_blocked( 'cleantalk', hozio_get_client_ip(), '' );
            return new WP_Error( 'spam_detected', 'Submission flagged as spam.', [ 'status' => 403 ] );
        }
    }

    // Resolve name from common field patterns
    $name = sanitize_text_field(
        $params['name'] ?? trim(
            ( $params['first_name'] ?? $params['fname'] ?? '' ) . ' ' .
            ( $params['last_name']  ?? $params['lname'] ?? '' )
        )
    );
    $source  = sanitize_text_field( $params['source'] ?? 'Webhook' );
    $referer = esc_url_raw( $params['referer'] ?? ( isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '' ) );

    // Exclude routing/internal keys before storing all fields as JSON
    $skip   = [ 'source', 'referer', 'website_url', '_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post' ];
    $fields = array_diff_key( $params, array_flip( $skip ) );

    $inserted = $wpdb->insert( $table, [
        'source'     => $source,
        'name'       => $name,
        'email'      => $email,
        'phone'      => $phone,
        'referer'    => $referer,
        'fields'     => wp_json_encode( $fields ),
        'created_at' => current_time( 'mysql', true ),
    ], [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );

    if ( ! $inserted ) {
        return new WP_Error( 'db_error', 'Failed to save lead.', [ 'status' => 500 ] );
    }

    return rest_ensure_response( [ 'success' => true, 'id' => $wpdb->insert_id ] );
}


// ══════════════════════════════════════════════════════════
// 4f. DIVI CONTACT FORM — native integration
//     Fires automatically on every Divi form submission.
//     No webhook URL or per-form configuration needed.
// ══════════════════════════════════════════════════════════
add_action( 'et_pb_contact_form_submit', 'hozio_capture_divi_lead', 10, 3 );

function hozio_capture_divi_lead( $et_pb_contact_form_fields, $et_contact_email, $et_pb_contact_form_num ) {
    if ( ! hozio_custom_leads_table_exists() ) return;

    global $wpdb;
    $table   = $wpdb->prefix . 'hozio_leads';
    $name    = '';
    $email   = '';
    $phone   = '';
    $fields  = [];

    // Divi passes fields as [ ['label' => '...', 'value' => '...'], ... ]
    if ( is_array( $et_pb_contact_form_fields ) ) {
        foreach ( $et_pb_contact_form_fields as $field ) {
            if ( ! is_array( $field ) || ! isset( $field['label'], $field['value'] ) ) continue;
            $label = sanitize_text_field( $field['label'] );
            $value = sanitize_text_field( $field['value'] );
            if ( $label === '' ) continue;
            $fields[ $label ] = $value;
            $lower = strtolower( $label );
            if ( ! $name  && strpos( $lower, 'name' )  !== false ) $name  = $value;
            if ( ! $email && strpos( $lower, 'email' ) !== false ) $email = $value;
            if ( ! $phone && ( strpos( $lower, 'phone' ) !== false || strpos( $lower, 'tel' ) !== false ) ) $phone = $value;
        }
    }

    // Fallback: read directly from $_POST if Divi didn't pass structured fields
    if ( empty( $fields ) ) {
        $num_suffix = '_' . $et_pb_contact_form_num;
        foreach ( $_POST as $key => $raw_val ) {
            if ( strpos( $key, 'et_pb_contact_' ) !== 0 ) continue;
            $label = ucwords( str_replace( [ 'et_pb_contact_', $num_suffix, '_' ], [ '', '', ' ' ], $key ) );
            $value = sanitize_text_field( $raw_val );
            $fields[ $label ] = $value;
            $lower = strtolower( $key );
            if ( ! $name  && strpos( $lower, 'name' )  !== false ) $name  = $value;
            if ( ! $email && strpos( $lower, 'email' ) !== false ) $email = sanitize_email( $value );
            if ( ! $phone && ( strpos( $lower, 'phone' ) !== false || strpos( $lower, 'tel' ) !== false ) ) $phone = $value;
        }
    }

    $referer = esc_url_raw( $_POST['et_pb_contact_form_url'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) );

    $wpdb->insert( $table, [
        'source'     => 'Divi',
        'name'       => sanitize_text_field( $name ),
        'email'      => sanitize_email( $email ),
        'phone'      => sanitize_text_field( $phone ),
        'referer'    => $referer,
        'fields'     => wp_json_encode( $fields ),
        'created_at' => current_time( 'mysql', true ),
    ], [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ] );
}


// ══════════════════════════════════════════════════════════
// 4g. CONTACT FORM 7 — native integration
//     Fires automatically on every CF7 form submission.
// ══════════════════════════════════════════════════════════
add_action( 'wpcf7_mail_sent', function( $contact_form ) {
    $submission = WPCF7_Submission::get_instance();
    if ( ! $submission ) return;

    $posted = $submission->get_posted_data();
    $name   = ''; $email = ''; $phone = ''; $fields = [];
    $skip   = [ '_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post', '_wpcf7_posted_data_hash', 'g-recaptcha-response', '_wpnonce' ];

    foreach ( $posted as $key => $value ) {
        if ( in_array( $key, $skip, true ) || strpos( $key, '_wpcf7' ) === 0 ) continue;
        if ( is_array( $value ) ) $value = implode( ', ', $value );
        $value = sanitize_text_field( $value );
        $label = ucwords( str_replace( [ '-', '_' ], ' ', $key ) );
        $fields[ $label ] = $value;
        $lower = strtolower( $key );
        if ( ! $name  && strpos( $lower, 'name' )  !== false ) $name  = $value;
        if ( ! $email && strpos( $lower, 'email' ) !== false ) $email = $value;
        if ( ! $phone && ( strpos( $lower, 'phone' ) !== false || strpos( $lower, 'tel' ) !== false ) ) $phone = $value;
    }

    hozio_insert_lead_record(
        'Contact Form 7', $name, $email, $phone,
        isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
        $fields
    );
} );


// ══════════════════════════════════════════════════════════
// 4h. WPFORMS — native integration
//     Fires automatically on every WPForms submission.
// ══════════════════════════════════════════════════════════
add_action( 'wpforms_process_complete', function( $fields, $entry, $form_data, $entry_id ) {
    $name  = ''; $email = ''; $phone = ''; $all_fields = [];

    foreach ( $fields as $field ) {
        $label = sanitize_text_field( $field['name'] ?? '' );
        $value = $field['value'] ?? '';
        if ( is_array( $value ) ) $value = implode( ', ', $value );
        $value = sanitize_text_field( (string) $value );
        if ( $label === '' && $value === '' ) continue;
        $all_fields[ $label ?: 'Field ' . ( $field['id'] ?? '' ) ] = $value;
        $type  = $field['type'] ?? '';
        $lower = strtolower( $label );
        if ( ! $name  && ( $type === 'name'  || strpos( $lower, 'name' )  !== false ) ) $name  = $value;
        if ( ! $email && ( $type === 'email' || strpos( $lower, 'email' ) !== false ) ) $email = $value;
        if ( ! $phone && ( $type === 'phone' || strpos( $lower, 'phone' ) !== false || strpos( $lower, 'tel' ) !== false ) ) $phone = $value;
    }

    hozio_insert_lead_record(
        'WPForms', $name, $email, $phone,
        isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
        $all_fields
    );
}, 10, 4 );


// ══════════════════════════════════════════════════════════
// 4i. GRAVITY FORMS — native integration
//     Fires automatically on every Gravity Forms submission.
// ══════════════════════════════════════════════════════════
add_action( 'gform_after_submission', function( $entry, $form ) {
    $name  = ''; $email = ''; $phone = ''; $all_fields = [];

    foreach ( $form['fields'] as $field ) {
        $field_id = $field->id;
        $label    = sanitize_text_field( $field->label );
        $type     = $field->type;

        // Name fields — GF stores data in sub-keys (.3 = first, .6 = last),
        // NOT in the parent field ID. rgar($entry, $field_id) always returns ''.
        if ( $type === 'name' ) {
            $first  = sanitize_text_field( rgar( $entry, $field_id . '.3' ) );
            $last   = sanitize_text_field( rgar( $entry, $field_id . '.6' ) );
            $middle = sanitize_text_field( rgar( $entry, $field_id . '.4' ) );
            $prefix = sanitize_text_field( rgar( $entry, $field_id . '.2' ) );
            $full   = trim( implode( ' ', array_filter( [ $prefix, $first, $middle, $last ] ) ) );
            if ( $full !== '' ) {
                $all_fields[ $label ] = $full;
                if ( ! $name ) $name = $full;
            }
            continue;
        }

        // Address fields — also stored in sub-keys
        if ( $type === 'address' ) {
            $parts = array_filter( [
                rgar( $entry, $field_id . '.1' ),  // street
                rgar( $entry, $field_id . '.2' ),  // street 2
                rgar( $entry, $field_id . '.3' ),  // city
                rgar( $entry, $field_id . '.4' ),  // state
                rgar( $entry, $field_id . '.5' ),  // zip
                rgar( $entry, $field_id . '.6' ),  // country
            ] );
            if ( ! empty( $parts ) ) {
                $all_fields[ $label ] = sanitize_text_field( implode( ', ', $parts ) );
            }
            continue;
        }

        // All other field types
        $value = rgar( $entry, (string) $field_id );
        if ( $value === '' || $value === null ) continue;
        if ( is_array( $value ) ) $value = implode( ', ', $value );
        $value = sanitize_text_field( (string) $value );
        $all_fields[ $label ] = $value;
        $lower = strtolower( $label );
        if ( ! $name  && strpos( $lower, 'name' )  !== false ) $name  = $value;
        if ( ! $email && ( $type === 'email' || strpos( $lower, 'email' ) !== false ) ) $email = $value;
        if ( ! $phone && ( $type === 'phone' || strpos( $lower, 'phone' ) !== false || strpos( $lower, 'tel' ) !== false ) ) $phone = $value;
    }

    hozio_insert_lead_record(
        'Gravity Forms', $name, $email, $phone,
        rgar( $entry, 'source_url' ) ?: ( isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '' ),
        $all_fields
    );
}, 10, 2 );


// ══════════════════════════════════════════════════════════
// 4j. FLUENT FORMS — native integration
//     Fires automatically on every Fluent Forms submission.
//     Source string 'Fluent Forms' slugs to 'fluentforms', which
//     already has a badge style (.hl-plat-fluentforms) and will
//     auto-appear in the platform filter once the first lead lands.
// ══════════════════════════════════════════════════════════
add_action( 'fluentform/submission_inserted', function( $entry_id, $form_data, $form ) {
    $name = ''; $email = ''; $phone = ''; $all_fields = [];

    // Best-effort: build a field-name => human-label map from the form definition.
    $labels = [];
    if ( is_object( $form ) && ! empty( $form->form_fields ) ) {
        $def = json_decode( $form->form_fields, true );
        if ( ! empty( $def['fields'] ) && is_array( $def['fields'] ) ) {
            $stack = $def['fields'];
            while ( $stack ) {
                $f = array_shift( $stack );
                if ( ! is_array( $f ) ) continue;
                // Walk container layouts (rows/columns) to reach nested fields.
                if ( ! empty( $f['fields'] ) && is_array( $f['fields'] ) ) {
                    foreach ( $f['fields'] as $child ) $stack[] = $child;
                }
                if ( ! empty( $f['columns'] ) && is_array( $f['columns'] ) ) {
                    foreach ( $f['columns'] as $col ) {
                        if ( ! empty( $col['fields'] ) && is_array( $col['fields'] ) ) {
                            foreach ( $col['fields'] as $child ) $stack[] = $child;
                        }
                    }
                }
                $att = $f['attributes']['name'] ?? '';
                $lbl = $f['settings']['label'] ?? '';
                if ( $att !== '' && $lbl !== '' ) $labels[ $att ] = $lbl;
            }
        }
    }

    $skip = [ '__fluent_form_embded_post_id', '_wp_http_referer', 'g-recaptcha-response', 'h-captcha-response', '__stripe_payment_method' ];

    foreach ( (array) $form_data as $key => $value ) {
        if ( in_array( $key, $skip, true ) ) continue;
        if ( strpos( $key, '_fluentform' ) === 0 || strpos( $key, '__fluent' ) === 0 ) continue;

        // Composite fields (name / address) arrive as arrays.
        if ( is_array( $value ) ) {
            $sep   = ( $key === 'names' ) ? ' ' : ', ';   // name reads better with spaces, address with commas
            $value = trim( implode( $sep, array_filter( array_map( 'sanitize_text_field', $value ) ) ) );
        } else {
            $value = sanitize_text_field( (string) $value );
        }
        if ( $value === '' ) continue;

        $label = $labels[ $key ] ?? ucwords( str_replace( [ '-', '_' ], ' ', $key ) );
        $all_fields[ $label ] = $value;

        // Detect by field-name attribute OR by human label (covers generic names like input_email_1).
        $lower = strtolower( $key . ' ' . $label );
        if ( ! $name  && ( $key === 'names' || strpos( $lower, 'name' )  !== false ) ) $name  = $value;
        if ( ! $email && strpos( $lower, 'email' ) !== false ) $email = $value;
        if ( ! $phone && ( strpos( $lower, 'phone' ) !== false || strpos( $lower, 'tel' ) !== false || strpos( $lower, 'mobile' ) !== false ) ) $phone = $value;
    }

    hozio_insert_lead_record(
        'Fluent Forms', $name, $email, $phone,
        isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
        $all_fields
    );
}, 10, 3 );


// ══════════════════════════════════════════════════════════
// 5. FRONT-END SHORTCODE
// ══════════════════════════════════════════════════════════
add_shortcode( 'leads_digest', function() {
    if ( ! is_user_logged_in() ) {
        return '<div style="text-align:center;">
                    <p><em>You must be <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">logged in</a> to view your leads.</em></p>
                </div>';
    }

    // Front-end: load all for client-side filtering (capped at 500 for safety)
    $result      = hozio_get_all_leads( 500, 1 );
    $submissions = $result['submissions'];
    $total_items = $result['total'];

    if ( empty( $submissions ) ) {
        ob_start();
        $img_url = plugin_dir_url( __FILE__ ) . '../assets/no-leads.png';
        ?>
        <div class="no-leads-empty">
          <img src="<?php echo esc_url( $img_url ); ?>" alt="Your first lead is just around the corner — stay tuned">
        </div>
        <style>
          .no-leads-empty img { max-width:600px; height:auto; display:block; margin:0 auto; }
        </style>
        <?php
        return ob_get_clean();
    }

    list( $stat_total, $stat_today, $stat_week ) = hozio_get_lead_stats();

    ob_start();
    $c = hozio_get_style();
    ?>
    <div class="ld-dashboard" style="
      --ld-el-bg:<?php echo esc_attr($c['element_bg']); ?>;
      --ld-text:<?php echo esc_attr($c['text_color']); ?>;
      --ld-sec:<?php echo esc_attr($c['secondary']); ?>;
      --ld-link:<?php echo esc_attr($c['link_color']); ?>;
      --ld-btn:<?php echo esc_attr($c['button_bg']); ?>;
      --ld-btn-text:<?php echo esc_attr($c['button_text']); ?>;
      --ld-search-bg:<?php echo esc_attr($c['search_bg']); ?>;
      --ld-search-text:<?php echo esc_attr($c['search_text']); ?>;
      --ld-search-border:<?php echo esc_attr($c['search_border']); ?>;
      --ld-border:<?php echo esc_attr($c['border_color']); ?>;
    ">

      <!-- Stat Cards -->
      <div class="ld-stats">
        <div class="ld-stat">
          <div class="ld-stat-icon" style="background:#ede9fe;color:#7c3aed;">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div class="ld-stat-info">
            <span class="ld-stat-num"><?php echo esc_html( $stat_total ); ?></span>
            <span class="ld-stat-lbl">Total Leads</span>
          </div>
        </div>
        <div class="ld-stat">
          <div class="ld-stat-icon" style="background:#dbeafe;color:#2563eb;">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div class="ld-stat-info">
            <span class="ld-stat-num"><?php echo esc_html( $stat_today ); ?></span>
            <span class="ld-stat-lbl">Today</span>
          </div>
        </div>
        <div class="ld-stat">
          <div class="ld-stat-icon" style="background:#fef3c7;color:#d97706;">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <div class="ld-stat-info">
            <span class="ld-stat-num"><?php echo esc_html( $stat_week ); ?></span>
            <span class="ld-stat-lbl">This Week</span>
          </div>
        </div>
      </div>

      <!-- Search & Filters -->
      <div class="ld-toolbar">
        <div class="ld-search">
          <svg width="16" height="16" fill="none" stroke="#9ca3af" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="ld-search-input" placeholder="Search leads...">
        </div>
        <div class="ld-filter-group">
          <select id="ld-filter-date">
            <option value="">All Time</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
          </select>
        </div>
      </div>

      <div class="ld-results-count">
        Showing <strong id="ld-visible"><?php echo count( $submissions ); ?></strong> of <?php echo esc_html( $total_items ); ?> leads
      </div>

      <!-- Table -->
      <div class="ld-table-wrap">
        <table class="ld-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ( $submissions as $s ) :
              $admin_url = wp_nonce_url(
                  admin_url( 'admin.php?page=hozio-lead-view&id=' . $s->id ),
                  'hozio_view_lead',
                  '_hlnonce'
              );
              $badge = hozio_status_badge( $s->status );
              $rel   = hozio_relative_time( $s->created_at );
          ?>
            <tr class="ld-row <?php echo $s->has_data ? '' : 'ld-dim'; ?>"
                data-name="<?php echo esc_attr( strtolower( $s->name ) ); ?>"
                data-email="<?php echo esc_attr( strtolower( $s->email ) ); ?>"
                data-phone="<?php echo esc_attr( $s->phone ); ?>"
                data-status="<?php echo esc_attr( $s->status ); ?>"
                data-timestamp="<?php echo esc_attr( strtotime( $s->created_at ) ); ?>">
              <td data-label="Date">
                <span class="ld-date-main"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $s->created_at ) ) ); ?></span>
                <span class="ld-date-rel"><?php echo esc_html( $rel ); ?></span>
              </td>
              <td data-label="Name">
                <?php echo $s->name !== '' ? '<strong>' . esc_html( $s->name ) . '</strong>' : '<span class="ld-muted">—</span>'; ?>
              </td>
              <td data-label="Email">
                <?php echo $s->email !== '' ? '<a href="mailto:' . esc_attr( $s->email ) . '" class="ld-link">' . esc_html( $s->email ) . '</a>' : '<span class="ld-muted">—</span>'; ?>
              </td>
              <td data-label="Phone">
                <?php echo $s->phone !== '' ? '<a href="tel:' . esc_attr( $s->phone ) . '" class="ld-link">' . esc_html( $s->phone ) . '</a>' : '<span class="ld-muted">—</span>'; ?>
              </td>
              <td data-label="Status">
                <span class="ld-badge <?php echo esc_attr( $badge[1] ); ?>"><?php echo esc_html( $badge[0] ); ?></span>
              </td>
              <td data-label="Actions">
                <a href="<?php echo esc_url( $admin_url ); ?>" class="ld-view-btn" target="_blank">
                  <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  View
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div id="ld-no-results" style="display:none;padding:40px 20px;text-align:center;">
          <svg width="48" height="48" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="8" x2="14" y2="14"/><line x1="14" y1="8" x2="8" y2="14"/></svg>
          <p style="font-size:15px;font-weight:600;color:var(--ld-text);margin:0 0 4px;">No results found</p>
          <p style="font-size:13px;color:var(--ld-sec);margin:0;">Try adjusting your search or filters</p>
        </div>
      </div>
    </div>

    <script>
    jQuery(function($){
      var debounceTimer;
      $('#ld-search-input').on('input', function(){ clearTimeout(debounceTimer); debounceTimer = setTimeout(ldFilter, 150); });
      $('#ld-filter-date').on('change', ldFilter);

      function ldFilter(){
        var q = $('#ld-search-input').val().toLowerCase().trim();
        var df = $('#ld-filter-date').val();
        var td = new Date(); td.setHours(0,0,0,0);
        var todayTs = Math.floor(td.getTime()/1000);
        var weekTs = todayTs - 604800;
        var monthTs = todayTs - 2592000;
        var vis = 0;

        $('.ld-row').each(function(){
          var $r = $(this), show = true;
          if (q) {
            var n = ($r.data('name')||'').toString();
            var e = ($r.data('email')||'').toString();
            var p = ($r.data('phone')||'').toString();
            if (n.indexOf(q)===-1 && e.indexOf(q)===-1 && p.indexOf(q)===-1) show = false;
          }
          if (df && show) {
            var ts = parseInt($r.data('timestamp'))||0;
            if (df==='today' && ts<todayTs) show=false;
            if (df==='week' && ts<weekTs) show=false;
            if (df==='month' && ts<monthTs) show=false;
          }
          $r.toggle(show);
          if (show) vis++;
        });
        $('#ld-visible').text(vis);
        $('#ld-no-results').toggle(vis === 0);
        $('.ld-table thead').toggle(vis > 0);
      }
    });
    </script>

    <style>
    /* ── Base ── */
    .ld-dashboard { font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif; max-width:1200px; margin:0 auto; padding:0 12px; }

    /* ── Stat Cards — element_bg + text_color + secondary ── */
    .ld-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
    .ld-stat { background:var(--ld-el-bg); border:1px solid var(--ld-border); border-radius:12px; padding:18px; display:flex; align-items:center; gap:14px; }
    .ld-stat-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .ld-stat-info { display:flex; flex-direction:column; }
    .ld-stat-num { font-size:24px; font-weight:700; color:var(--ld-text); line-height:1.1; }
    .ld-stat-lbl { font-size:12px; color:var(--ld-sec); font-weight:500; margin-top:1px; }

    /* ── Search Bar — search_bg ── */
    .ld-toolbar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px; }
    .ld-search { flex:1; min-width:200px; position:relative; }
    .ld-search svg { position:absolute; left:16px; top:50%; transform:translateY(-50%); pointer-events:none; z-index:1; }
    .ld-search input { width:100%; padding:10px 12px 10px 52px !important; border:1px solid var(--ld-search-border); border-radius:10px; font-size:14px; outline:none; background:var(--ld-search-bg); color:var(--ld-search-text) !important; -webkit-text-fill-color:var(--ld-search-text) !important; box-sizing:border-box !important; }
    .ld-search input::placeholder { color:var(--ld-sec) !important; -webkit-text-fill-color:var(--ld-sec) !important; opacity:1; }
    .ld-search input:focus { border-color:var(--ld-link); box-shadow:0 0 0 3px rgba(59,130,246,0.12); }

    /* ── Filter Dropdown — element_bg ── */
    .ld-filter-group { display:flex; gap:8px; }
    .ld-filter-group select { padding:10px 30px 10px 12px; border:1px solid var(--ld-border); border-radius:10px; font-size:13px; background:var(--ld-el-bg); color:var(--ld-text); cursor:pointer; outline:none; appearance:none; background-image:url("data:image/svg+xml,%3Csvg width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' xmlns='http://www.w3.org/2000/svg'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; }

    /* ── Results Count — secondary ── */
    .ld-results-count { font-size:12px; color:var(--ld-sec); margin-bottom:6px; }

    /* ── Table — element_bg + text_color + secondary ── */
    .ld-table-wrap { background:var(--ld-el-bg); border:1px solid var(--ld-border); border-radius:12px 12px 0 0; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
    .ld-table { width:100%; border-collapse:collapse; }
    .ld-table thead th { padding:11px 16px; background:var(--ld-el-bg); color:var(--ld-sec); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid var(--ld-border); text-align:left; }
    .ld-table tbody td { padding:13px 16px; border-bottom:1px solid var(--ld-border); font-size:14px; vertical-align:middle; color:var(--ld-text) !important; }
    .ld-table tbody tr:last-child td { border-bottom:none; }
    .ld-table tbody td strong { color:var(--ld-text) !important; font-weight:600; }
    .ld-row:hover td { background:rgba(0,0,0,0.02); }
    .ld-dim td { opacity:0.45; }
    .ld-dim:hover td { opacity:0.75; }

    /* ── Date Cells — text_color + secondary ── */
    .ld-date-main { display:block; font-size:13px; font-weight:500; color:var(--ld-text); }
    .ld-date-rel { display:block; font-size:11px; color:var(--ld-sec); }

    /* ── Links — link_color ── */
    .ld-link { color:var(--ld-link) !important; text-decoration:none; font-size:13px; }
    .ld-link:hover { text-decoration:underline; }

    /* ── Muted — secondary ── */
    .ld-muted { color:var(--ld-sec); }

    /* ── Badges — element_bg for container context ── */
    .ld-badge { display:inline-block; padding:3px 10px; border-radius:100px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; }
    .ld-badge-success { background:#d1fae5; color:#065f46; }
    .ld-badge-error { background:#fee2e2; color:#991b1b; }
    .ld-badge-new { background:var(--ld-btn); color:var(--ld-btn-text); }

    /* ── View Button — button_bg ── */
    .ld-view-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 16px; background:var(--ld-btn) !important; color:var(--ld-btn-text) !important; border-radius:8px; font-size:12px; font-weight:600; text-decoration:none !important; transition:opacity 0.2s; }
    .ld-view-btn:hover { opacity:0.85; }

    /* ── Mobile ── */
    @media (max-width:768px) {
      .ld-stats { grid-template-columns:1fr 1fr; }
      .ld-toolbar { flex-direction:column; }
      .ld-table-wrap { border:none; border-radius:0; box-shadow:none; }
      .ld-table thead { display:none; }
      .ld-table, .ld-table tbody, .ld-table tr { display:block; width:100%; }
      .ld-row { margin-bottom:12px; border:1px solid var(--ld-border); border-radius:12px; overflow:hidden; background:var(--ld-el-bg); }
      .ld-table tbody td { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid var(--ld-border); }
      .ld-table tbody td::before { content:attr(data-label); font-size:11px; font-weight:600; color:var(--ld-sec); text-transform:uppercase; flex-shrink:0; margin-right:12px; }
      .ld-table tbody td:last-child { justify-content:center; }
      .ld-table tbody td:last-child::before { display:none; }
      .ld-view-btn { width:100%; justify-content:center; }
    }
    @media (max-width:480px) { .ld-stats { grid-template-columns:1fr; } }
    </style>
    <?php
    return ob_get_clean();
});