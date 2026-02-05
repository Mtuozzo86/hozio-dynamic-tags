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

    $allowed_pages   = [ 'hozio-leads', 'hozio-lead-view', 'hozio-leads-settings' ];
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
    if ( ! hozio_submissions_tables_exist() ) return [ 0, 0, 0 ];

    global $wpdb;
    $subs = $wpdb->prefix . 'e_submissions';

    $total = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*) FROM `{$subs}` WHERE status <> %s", 'trash' )
    );
    $today = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$subs}` WHERE status <> %s AND created_at >= %s",
            'trash', gmdate( 'Y-m-d 00:00:00' )
        )
    );
    $week = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$subs}` WHERE status <> %s AND created_at >= %s",
            'trash', gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days' ) )
        )
    );

    return [ $total, $today, $week ];
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


// ══════════════════════════════════════════════════════════
// 3. ADMIN PAGE: Leads List (CRM Dashboard)
// ══════════════════════════════════════════════════════════
function hozio_leads_list_page() {
    if ( ! hozio_submissions_tables_exist() ) {
        echo '<div class="wrap"><h1>Lead Submissions</h1><p>No submissions table found.</p></div>';
        return;
    }

    // Pagination
    $per_page = 50;
    $current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

    $result = hozio_get_submissions( $per_page, $current_page );
    $submissions = $result['submissions'];
    $total_items = $result['total'];
    $total_pages = (int) ceil( $total_items / $per_page );

    list( $stat_total, $stat_today, $stat_week ) = hozio_get_lead_stats();

    // Nonce for the view links
    $view_nonce = wp_create_nonce( 'hozio_view_lead' );
    ?>
    <div class="hozio-leads-wrap">

      <!-- Header -->
      <div class="hl-header">
        <div>
          <h1 class="hl-title">Lead Submissions</h1>
          <p class="hl-subtitle">Track and manage all your incoming leads</p>
        </div>
      </div>

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
          <h2>No leads yet</h2>
          <p>When someone submits a form on your site, it'll show up here.</p>
        </div>
      <?php else : ?>

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
              <th class="hl-sortable" data-col="0">Date <span class="hl-sort-icon">↕</span></th>
              <th class="hl-sortable" data-col="1">Name <span class="hl-sort-icon">↕</span></th>
              <th class="hl-sortable" data-col="2">Email <span class="hl-sort-icon">↕</span></th>
              <th class="hl-sortable" data-col="3">Phone <span class="hl-sort-icon">↕</span></th>
              <th>Status</th>
              <th>Source</th>
              <th style="width:90px;"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ( $submissions as $s ) :
              $view_url = wp_nonce_url(
                  admin_url( 'admin.php?page=hozio-lead-view&id=' . $s->id ),
                  'hozio_view_lead',
                  '_hlnonce'
              );
              $form_page    = $s->post_id ? get_the_title( $s->post_id ) : '';
              $field_labels = hozio_get_form_field_labels( $s->post_id );
              $badge        = hozio_status_badge( $s->status );
              $relative     = hozio_relative_time( $s->created_at );

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
                  $detail_fields[] = [ 'label' => $label, 'display' => $display ];
              }
          ?>
            <tr class="hl-row <?php echo $s->has_data ? '' : 'hl-row-dim'; ?>"
                data-name="<?php echo esc_attr( strtolower( $s->name ) ); ?>"
                data-email="<?php echo esc_attr( strtolower( $s->email ) ); ?>"
                data-phone="<?php echo esc_attr( $s->phone ); ?>"
                data-status="<?php echo esc_attr( $s->status ); ?>"
                data-date="<?php echo esc_attr( $s->created_at ); ?>"
                data-has-data="<?php echo $s->has_data ? '1' : '0'; ?>"
                data-timestamp="<?php echo esc_attr( strtotime( $s->created_at ) ); ?>">
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
              <td data-label="Source">
                <span class="hl-source"><?php echo esc_html( $form_page ?: '—' ); ?></span>
              </td>
              <td>
                <span class="hl-expand-btn" title="Click to expand details">
                  <span class="hl-expand-label">Details</span>
                  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
              </td>
            </tr>
            <!-- Expandable detail row -->
            <tr class="hl-detail-row" style="display:none;">
              <td colspan="7">
                <div class="hl-detail-panel">
                  <div class="hl-detail-header">
                    <h3>Submission #<?php echo esc_html( $s->id ); ?></h3>
                    <div class="hl-detail-meta">
                      <span><?php echo esc_html( date_i18n( 'F j, Y \a\t g:i A', strtotime( $s->created_at ) ) ); ?></span>
                      <?php if ( $form_page ) : ?>
                        <span class="hl-meta-sep">•</span>
                        <span><?php echo esc_html( $form_page ); ?></span>
                      <?php endif; ?>
                      <?php if ( $s->referer ) : ?>
                        <span class="hl-meta-sep">•</span>
                        <a href="<?php echo esc_url( $s->referer ); ?>" target="_blank" rel="noopener noreferrer" class="hl-referer-link">View source page ↗</a>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php if ( ! empty( $detail_fields ) ) : ?>
                    <div class="hl-detail-grid">
                      <?php foreach ( $detail_fields as $df ) : ?>
                        <div class="hl-detail-field">
                          <span class="hl-detail-label"><?php echo esc_html( $df['label'] ); ?></span>
                          <span class="hl-detail-value"><?php echo $df['display']; ?></span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else : ?>
                    <p class="hl-detail-empty">No field data available for this submission.</p>
                  <?php endif; ?>
                  <div class="hl-detail-actions">
                    <a href="<?php echo esc_url( $view_url ); ?>" class="hl-btn-view">Open Full View →</a>
                  </div>
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
      // Expand/collapse rows — click anywhere on the row
      $('.hl-row').on('click', function(e){
        // Don't trigger if clicking a link, button inside the row
        if ($(e.target).closest('a, button').length && !$(e.target).closest('.hl-expand-btn').length) return;
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

      // Search + filters
      var $search = $('#hl-search'), $date = $('#hl-filter-date'), $data = $('#hl-filter-data');
      var debounceTimer;
      $search.on('input', function(){ clearTimeout(debounceTimer); debounceTimer = setTimeout(filterTable, 150); });
      $date.on('change', filterTable);
      $data.on('change', filterTable);

      function filterTable(){
        var query = $search.val().toLowerCase().trim();
        var dateFilter = $date.val();
        var dataFilter = $data.val();
        var now = Math.floor(Date.now()/1000);
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
          $row.toggle(show);
          if (!show) $detail.hide();
          if (show) visible++;
        });
        $('#hl-visible-count').text(visible);
        $('#hl-no-results').toggle(visible === 0);
        $('#hl-leads-table thead').toggle(visible > 0);
      }

      // Column sorting
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
          if (col===0) {
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

    .hl-detail-row td { padding:0 !important; border-bottom:1px solid #e2e8f0 !important; background:#f8fafc; }
    .hl-detail-panel { padding:20px 24px 24px; border-top:2px solid #818cf8; }
    .hl-detail-header h3 { font-size:15px; font-weight:600; color:#1e293b; margin:0 0 6px; }
    .hl-detail-meta { font-size:12px; color:#94a3b8; margin-bottom:16px; }
    .hl-meta-sep { margin:0 6px; }
    .hl-referer-link { color:#3b82f6; text-decoration:none; }
    .hl-referer-link:hover { text-decoration:underline; }
    .hl-detail-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:16px; margin-bottom:16px; }
    .hl-detail-field { display:flex; flex-direction:column; gap:3px; }
    .hl-detail-label { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:#94a3b8; }
    .hl-detail-value { font-size:14px; color:#1e293b; word-break:break-word; }
    .hl-detail-value a { color:#3b82f6; text-decoration:none; }
    .hl-detail-value a:hover { text-decoration:underline; }
    .hl-detail-empty { color:#94a3b8; font-style:italic; font-size:13px; }
    .hl-detail-actions { padding-top:12px; border-top:1px solid #e2e8f0; }
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
      .hl-detail-grid { grid-template-columns:1fr; }
    }
    @media (max-width:480px) { .hl-stats { grid-template-columns:1fr; } }
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

    if ( ! hozio_submissions_tables_exist() ) {
        echo '<div class="wrap"><h1>Error</h1><p>Submissions table not found.</p></div>';
        return;
    }

    global $wpdb;
    $subs = $wpdb->prefix . 'e_submissions';
    $vals = $wpdb->prefix . 'e_submissions_values';

    $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    if ( ! $id ) {
        echo '<div class="wrap"><h1>Submission Not Found</h1><p>Invalid submission ID.</p></div>';
        return;
    }

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

    $form_page    = $submission->post_id ? get_the_title( $submission->post_id ) : '—';
    $back_url     = admin_url( 'admin.php?page=hozio-leads' );
    $field_labels = hozio_get_form_field_labels( (int) $submission->post_id );
    $badge        = hozio_status_badge( $submission->status );

    $badge_styles = [
        'hl-badge-success' => 'background:#d1fae5;color:#065f46',
        'hl-badge-error'   => 'background:#fee2e2;color:#991b1b',
        'hl-badge-new'     => 'background:#4f46e5;color:#ffffff',
    ];
    $badge_style = $badge_styles[ $badge[1] ] ?? 'background:#ede9fe;color:#5b21b6';
    ?>
    <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:740px;">
        <h1 style="display:flex;align-items:center;gap:8px;">
            <a href="<?php echo esc_url( $back_url ); ?>" style="text-decoration:none;color:#64748b;font-size:14px;display:inline-flex;align-items:center;gap:4px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:8px;">← Back</a>
            <span style="font-size:22px;font-weight:700;">Submission #<?php echo esc_html( $id ); ?></span>
        </h1>

        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px;margin-top:16px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
            <table class="form-table" style="margin:0;">
                <tr>
                    <th style="width:130px;padding:10px 10px 10px 0;color:#64748b;font-size:13px;">Date</th>
                    <td style="padding:10px 0;font-weight:500;"><?php echo esc_html( date_i18n( 'F j, Y \a\t g:i A', strtotime( $submission->created_at ) ) ); ?></td>
                </tr>
                <tr>
                    <th style="padding:10px 10px 10px 0;color:#64748b;font-size:13px;">Status</th>
                    <td style="padding:10px 0;">
                        <span style="display:inline-block;padding:4px 14px;border-radius:100px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.3px;<?php echo esc_attr( $badge_style ); ?>">
                            <?php echo esc_html( $badge[0] ); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th style="padding:10px 10px 10px 0;color:#64748b;font-size:13px;">Form Page</th>
                    <td style="padding:10px 0;"><?php echo esc_html( $form_page ); ?></td>
                </tr>
                <?php if ( ! empty( $submission->referer ) ) : ?>
                <tr>
                    <th style="padding:10px 10px 10px 0;color:#64748b;font-size:13px;">Referrer</th>
                    <td style="padding:10px 0;"><a href="<?php echo esc_url( $submission->referer ); ?>" target="_blank" rel="noopener noreferrer" style="color:#3b82f6;"><?php echo esc_html( $submission->referer ); ?></a></td>
                </tr>
                <?php endif; ?>
            </table>

            <hr style="margin:20px 0;border:none;border-top:1px solid #f1f5f9;">
            <h3 style="margin:0 0 16px;font-size:15px;font-weight:600;color:#1e293b;">Submitted Fields</h3>

            <?php if ( empty( $fields ) ) : ?>
                <p style="color:#94a3b8;font-style:italic;">No field data found.</p>
            <?php else : ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
                <?php foreach ( $fields as $f ) :
                    if ( $f['value'] === '' || $f['value'] === null ) continue;
                    $raw_key = $f['key'];
                    $fl      = $field_labels[ $raw_key ] ?? ucwords( str_replace( [ '_', '-' ], ' ', $raw_key ) );
                    $value   = $f['value'];
                    $display = esc_html( $value );
                    if ( is_email( $value ) ) {
                        $display = '<a href="mailto:' . esc_attr( $value ) . '" style="color:#3b82f6;">' . esc_html( $value ) . '</a>';
                    } elseif ( preg_match( '/^[\+\d\s\-\(\)]{7,}$/', $value ) ) {
                        $display = '<a href="tel:' . esc_attr( preg_replace( '/[^\d\+]/', '', $value ) ) . '" style="color:#3b82f6;">' . esc_html( $value ) . '</a>';
                    }
                ?>
                    <div>
                        <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:#94a3b8;margin-bottom:3px;"><?php echo esc_html( $fl ); ?></div>
                        <div style="font-size:14px;color:#1e293b;word-break:break-word;"><?php echo $display; ?></div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}


// ══════════════════════════════════════════════════════════
// 5. FRONT-END SHORTCODE
// ══════════════════════════════════════════════════════════
add_shortcode( 'leads_digest', function() {
    if ( ! is_user_logged_in() ) {
        return '<div style="text-align:center;">
                    <p><em>You must be <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">logged in</a> to view your leads.</em></p>
                </div>';
    }

    if ( ! hozio_submissions_tables_exist() ) {
        return '<p><em>No submissions table found.</em></p>';
    }

    // Front-end: load all for client-side filtering (capped at 500 for safety)
    $result      = hozio_get_submissions( 500, 1 );
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