<?php
/**
 * Update holds and the site-wide update freeze.
 *
 * A HOLD is a stored instruction: "do not auto-update this plugin." It is placed after a
 * rollback, so the automatic updater cannot put back the version that just broke the site.
 * A FREEZE stops every automatic update on the site for a while: the Hozio Studio
 * Orchestrator freezes a site while it repairs it, so nothing changes underneath the repair.
 *
 * Why this exists: "Auto-Update All Plugins" (plugin-auto-updates.php) approves every plugin
 * on the auto_update_plugin filter, and WordPress only stores which plugins have auto-updates
 * ENABLED, never which were disabled. So after a plugin was rolled back, the next run simply
 * reinstalled the version that broke the site, and nothing short of the free-text exclusion
 * list could say "not this one".
 *
 * One implementation, four front doors: WP-CLI (includes/wp-cli.php), the Hub
 * (hub-command-executor.php), the settings page and the Plugins screen. All of them call the
 * functions in this file, so validation and audit logging happen exactly once.
 *
 * HUMANS STAY IN CONTROL. Nothing here blocks a person clicking "Update now" in wp-admin.
 * Holds and the freeze stop AUTOMATIC updates only; the Plugins screen says so and warns.
 *
 * STORAGE: two non-autoloaded options, hozio_update_holds and hozio_update_freeze. The Hub's
 * generic update_option command can write most hozio_* options, so both are refused there,
 * and both are validated entry by entry every time they are read. An entry that doesn't pass
 * is dropped and counted in the status output, never trusted.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// A hold lasts 30 days unless the caller says otherwise, and never more than 180.
define( 'HOZIO_HOLD_DEFAULT_DAYS', 30 );
define( 'HOZIO_HOLD_MAX_DAYS', 180 );

// A freeze is for the length of a repair: at most a week.
define( 'HOZIO_FREEZE_MAX_DAYS', 7 );

// Expired holds and freezes stop blocking at once but stay visible this long.
define( 'HOZIO_HOLD_EXPIRED_VISIBLE_DAYS', 7 );

// ─── Validation ──────────────────────────────────────────────────────────────

/**
 * "folder/file.php" or a single-file "file.php". Nothing path-like: no "..", no leading
 * dot, no backslash, no absolute path.
 *
 * @param mixed $file
 * @return bool
 */
function hozio_hold_valid_plugin_file( $file ) {
    if ( ! is_string( $file ) || $file === '' || strlen( $file ) > 200 || strpos( $file, '..' ) !== false ) {
        return false;
    }
    return (bool) preg_match( '#^[A-Za-z0-9_\-][A-Za-z0-9_.\-]*(?:/[A-Za-z0-9_\-][A-Za-z0-9_.\-]*)?\.php\z#', $file );
}

/**
 * @param mixed $v
 * @return bool
 */
function hozio_hold_valid_version( $v ) {
    return is_string( $v ) && (bool) preg_match( '/^[0-9A-Za-z][0-9A-Za-z.+_\-]{0,39}\z/', $v );
}

/**
 * Who placed a hold: orchestrator, hub, wp-cli, or admin:<login>.
 *
 * @param mixed $s
 * @return bool
 */
function hozio_hold_valid_source( $s ) {
    return is_string( $s ) && (bool) preg_match( '/^(?:orchestrator|hub|wp-cli|admin:[A-Za-z0-9_.@\-]{1,60})\z/', $s );
}

/**
 * Optional caller reference, e.g. "fix-<session id>".
 *
 * @param mixed $r
 * @return bool
 */
function hozio_hold_valid_ref( $r ) {
    return is_string( $r ) && ( $r === '' || (bool) preg_match( '/^[A-Za-z0-9_.:\-]{1,100}\z/', $r ) );
}

/**
 * Characters, not bytes, without depending on mbstring.
 *
 * @param string $s
 * @return int
 */
function hozio_hold_char_count( $s ) {
    $n = preg_match_all( '/./us', (string) $s );
    return ( $n === false ) ? strlen( (string) $s ) : (int) $n;
}

/**
 * A stored reason is valid only if it is already in its sanitized form.
 *
 * @param mixed $r
 * @return bool
 */
function hozio_hold_valid_reason( $r ) {
    return is_string( $r ) && $r !== '' && hozio_hold_char_count( $r ) <= 500 && sanitize_text_field( $r ) === $r;
}

/**
 * Reason from a caller: required, at most 500 characters, sanitized.
 *
 * Over-long input is rejected rather than cut, so a caller never gets a hold whose
 * reason says something other than what it sent.
 *
 * @param mixed $raw
 * @return string|WP_Error
 */
function hozio_hold_clean_reason( $raw ) {
    if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
        return new WP_Error( 'reason_required', 'A reason is required.' );
    }
    if ( strlen( $raw ) > 2000 || hozio_hold_char_count( $raw ) > 500 ) {
        return new WP_Error( 'reason_too_long', 'The reason must be 500 characters or fewer.' );
    }
    $clean = sanitize_text_field( $raw );
    if ( $clean === '' ) {
        return new WP_Error( 'reason_required', 'A reason is required.' );
    }
    return $clean;
}

/**
 * @param mixed  $raw
 * @param string $default
 * @return string|WP_Error
 */
function hozio_hold_clean_source( $raw, $default ) {
    $source = ( $raw === null || $raw === '' ) ? $default : $raw;
    if ( ! hozio_hold_valid_source( $source ) ) {
        return new WP_Error( 'invalid_source', 'Source must be orchestrator, hub, wp-cli or admin:<login>.' );
    }
    return $source;
}

/**
 * @param mixed $raw
 * @return string|WP_Error
 */
function hozio_hold_clean_ref( $raw ) {
    $ref = ( $raw === null ) ? '' : $raw;
    if ( ! hozio_hold_valid_ref( $ref ) ) {
        return new WP_Error( 'invalid_ref', 'The reference may only use letters, digits and _ . : - (100 characters at most).' );
    }
    return $ref;
}

/**
 * Versions from a caller: an array or a comma-separated string.
 *
 * @param mixed $raw
 * @return string[]|WP_Error
 */
function hozio_hold_clean_versions( $raw ) {
    if ( $raw === null || $raw === '' ) {
        return array();
    }
    if ( is_string( $raw ) ) {
        $raw = explode( ',', $raw );
    }
    if ( ! is_array( $raw ) ) {
        return new WP_Error( 'invalid_versions', 'Versions must be a list such as 3.5.1,3.5.2.' );
    }

    $out = array();
    foreach ( $raw as $v ) {
        if ( ! is_string( $v ) && ! is_int( $v ) ) {
            return new WP_Error( 'invalid_versions', 'Versions must be a list such as 3.5.1,3.5.2.' );
        }
        $v = ltrim( trim( (string) $v ), 'vV' );
        if ( $v === '' ) {
            continue;
        }
        if ( ! hozio_hold_valid_version( $v ) ) {
            return new WP_Error( 'invalid_versions', 'Not a version number: ' . substr( sanitize_text_field( $v ), 0, 40 ) );
        }
        if ( ! in_array( $v, $out, true ) ) {
            $out[] = $v;
        }
    }

    if ( count( $out ) > 20 ) {
        return new WP_Error( 'invalid_versions', 'At most 20 versions.' );
    }
    return $out;
}

/**
 * When a hold or freeze ends.
 *
 * Accepts +N followed by m, h or d (+30d, +12h, +15m), a date (YYYY-MM-DD, meaning the
 * end of that day in UTC) or a UTC time (YYYY-MM-DDTHH:MMZ). Must be in the future and
 * within $max_days.
 *
 * @param mixed $raw
 * @param int   $max_days
 * @return int|WP_Error Unix timestamp.
 */
function hozio_hold_parse_until( $raw, $max_days ) {
    $now = time();

    if ( ! is_string( $raw ) && ! is_int( $raw ) ) {
        return new WP_Error( 'bad_until', 'Use YYYY-MM-DD, YYYY-MM-DDTHH:MMZ or +N followed by m, h or d (for example +30d).' );
    }
    $raw = trim( (string) $raw );

    if ( preg_match( '/^\+(\d{1,6})([mhd])\z/', $raw, $m ) ) {
        $unit = array( 'm' => MINUTE_IN_SECONDS, 'h' => HOUR_IN_SECONDS, 'd' => DAY_IN_SECONDS );
        $ts   = $now + (int) $m[1] * $unit[ $m[2] ];
    } elseif ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2})(?::(\d{2}))?Z)?\z/', $raw, $m ) ) {
        if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
            return new WP_Error( 'bad_until', 'That date does not exist.' );
        }
        if ( isset( $m[4] ) && $m[4] !== '' ) {
            $h = (int) $m[4];
            $i = (int) $m[5];
            $s = isset( $m[6] ) && $m[6] !== '' ? (int) $m[6] : 0;
            if ( $h > 23 || $i > 59 || $s > 59 ) {
                return new WP_Error( 'bad_until', 'That time does not exist.' );
            }
        } else {
            $h = 23;
            $i = 59;
            $s = 59;
        }
        $ts = gmmktime( $h, $i, $s, (int) $m[2], (int) $m[3], (int) $m[1] );
    } else {
        return new WP_Error( 'bad_until', 'Use YYYY-MM-DD, YYYY-MM-DDTHH:MMZ or +N followed by m, h or d (for example +30d).' );
    }

    if ( $ts <= $now ) {
        return new WP_Error( 'until_in_past', 'The end time must be in the future.' );
    }
    if ( $ts > $now + (int) $max_days * DAY_IN_SECONDS ) {
        return new WP_Error( 'until_too_far', sprintf( 'At most %d days from now.', (int) $max_days ) );
    }
    return (int) $ts;
}

/**
 * UTC timestamp in the format every front door prints.
 *
 * @param int $ts
 * @return string
 */
function hozio_hold_iso( $ts ) {
    return gmdate( 'Y-m-d\TH:i:s\Z', (int) $ts );
}

/**
 * A timestamp as a date (and optionally time) in the site's own timezone, for screens.
 *
 * @param int  $ts
 * @param bool $with_time
 * @return string
 */
function hozio_hold_local_date( $ts, $with_time = false ) {
    $format = get_option( 'date_format' ) . ( $with_time ? ' ' . get_option( 'time_format' ) : '' );
    return function_exists( 'wp_date' ) ? (string) wp_date( $format, (int) $ts ) : date_i18n( $format, (int) $ts + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
}

/**
 * The source label for whoever is logged in to wp-admin.
 *
 * @return string
 */
function hozio_hold_admin_source() {
    $user  = wp_get_current_user();
    $login = ( $user && $user->exists() ) ? preg_replace( '/[^A-Za-z0-9_.@\-]/', '_', (string) $user->user_login ) : '';
    $login = substr( (string) $login, 0, 60 );
    return 'admin:' . ( $login !== '' ? $login : 'unknown' );
}

/**
 * Integer-like value from storage (ints, or numeric strings from `wp option update`).
 *
 * @param mixed $v
 * @return int|null
 */
function hozio_hold_int( $v ) {
    if ( is_int( $v ) ) {
        return $v;
    }
    if ( is_string( $v ) && preg_match( '/^\d{1,12}\z/', $v ) ) {
        return (int) $v;
    }
    return null;
}

// ─── Holds: storage ──────────────────────────────────────────────────────────

/**
 * Validate one stored hold. Returns the normalized hold, or null when anything is off.
 *
 * @param mixed $file
 * @param mixed $h
 * @return array|null
 */
function hozio_update_hold_validate( $file, $h ) {
    if ( ! hozio_hold_valid_plugin_file( $file ) || ! is_array( $h ) ) {
        return null;
    }

    $mode = isset( $h['mode'] ) ? $h['mode'] : '';
    if ( $mode !== 'pin' && $mode !== 'skip' ) {
        return null;
    }

    $versions = isset( $h['versions'] ) ? $h['versions'] : array();
    if ( ! is_array( $versions ) || count( $versions ) > 20 ) {
        return null;
    }
    foreach ( $versions as $v ) {
        if ( ! hozio_hold_valid_version( $v ) ) {
            return null;
        }
    }
    $versions = array_values( $versions );
    if ( $mode === 'skip' && empty( $versions ) ) {
        return null; // A skip hold with nothing to skip blocks nothing; it was not written by us.
    }

    $held_at = isset( $h['held_at'] ) ? $h['held_at'] : '';
    if ( $held_at !== '' && ! hozio_hold_valid_version( $held_at ) ) {
        return null;
    }

    $reason = isset( $h['reason'] ) ? $h['reason'] : '';
    $source = isset( $h['source'] ) ? $h['source'] : '';
    $ref    = isset( $h['ref'] ) ? $h['ref'] : '';
    if ( ! hozio_hold_valid_reason( $reason ) || ! hozio_hold_valid_source( $source ) || ! hozio_hold_valid_ref( $ref ) ) {
        return null;
    }

    $now     = time();
    $created = isset( $h['created_at'] ) ? hozio_hold_int( $h['created_at'] ) : null;
    $expires = isset( $h['expires_at'] ) ? hozio_hold_int( $h['expires_at'] ) : null;
    $updated = isset( $h['updated_at'] ) ? hozio_hold_int( $h['updated_at'] ) : $created;
    if ( ! $created || ! $expires || $updated === null || $updated < $created ) {
        return null;
    }
    // Nothing we write is dated in the future, and no hold outlives its maximum length.
    if ( $created > $now + 300 || $updated > $now + 300 || $expires <= $created
        || $expires > max( $created, $updated ) + HOZIO_HOLD_MAX_DAYS * DAY_IN_SECONDS + 60 ) {
        return null;
    }

    return array(
        'mode'       => $mode,
        'versions'   => $versions,
        'held_at'    => $held_at,
        'reason'     => $reason,
        'source'     => $source,
        'ref'        => $ref,
        'created_at' => $created,
        'updated_at' => $updated,
        'expires_at' => $expires,
    );
}

/**
 * Every hold worth showing, validated.
 *
 * Holds that expired more than HOZIO_HOLD_EXPIRED_VISIBLE_DAYS ago are left out (and are
 * removed for good on the next write). Invalid entries are counted, never used.
 *
 * @return array{holds: array<string,array>, invalid: int}
 */
function hozio_update_holds_read() {
    $raw = get_option( 'hozio_update_holds', array() );
    $out = array( 'holds' => array(), 'invalid' => 0 );

    if ( empty( $raw ) ) {
        return $out;
    }
    if ( ! is_array( $raw ) ) {
        $out['invalid'] = 1;
        return $out;
    }

    $now = time();
    foreach ( $raw as $file => $h ) {
        $clean = hozio_update_hold_validate( $file, $h );
        if ( ! $clean ) {
            $out['invalid']++;
            continue;
        }
        if ( $clean['expires_at'] + HOZIO_HOLD_EXPIRED_VISIBLE_DAYS * DAY_IN_SECONDS <= $now ) {
            continue;
        }
        $clean['expired']      = ( $clean['expires_at'] <= $now );
        $out['holds'][ $file ] = $clean;
    }

    ksort( $out['holds'] );
    return $out;
}

/**
 * Store holds. Only validated entries are ever written back, so this also clears out junk.
 *
 * @param array $holds     From hozio_update_holds_read()['holds'], edited.
 * @param int   $discarded Invalid entries being dropped by this write.
 * @return void
 */
function hozio_update_holds_save( $holds, $discarded ) {
    $store = array();
    foreach ( $holds as $file => $h ) {
        unset( $h['expired'] );
        $store[ $file ] = $h;
    }

    if ( empty( $store ) ) {
        delete_option( 'hozio_update_holds' );
    } else {
        update_option( 'hozio_update_holds', $store, false );
    }

    if ( $discarded > 0 ) {
        hozio_audit_log( sprintf( 'Discarded %d invalid entr%s found in hozio_update_holds', $discarded, $discarded === 1 ? 'y' : 'ies' ), 'Holds' );
    }
}

/**
 * The live (not expired) hold on a plugin, if any.
 *
 * @param string $plugin_file
 * @return array|null
 */
function hozio_update_hold_get_active( $plugin_file ) {
    $state = hozio_update_holds_read();
    $file  = (string) $plugin_file;
    if ( isset( $state['holds'][ $file ] ) && ! $state['holds'][ $file ]['expired'] ) {
        return $state['holds'][ $file ];
    }
    return null;
}

/**
 * Does a hold stop this plugin updating to this version?
 *
 * A pin blocks every version. A skip blocks only the listed ones; when the target version
 * isn't known it blocks too, because guessing wrong would install the version that broke
 * the site.
 *
 * @param string $plugin_file
 * @param string $new_version
 * @return bool
 */
function hozio_update_hold_blocks( $plugin_file, $new_version = '' ) {
    $h = hozio_update_hold_get_active( $plugin_file );
    if ( ! $h ) {
        return false;
    }
    if ( $h['mode'] === 'pin' ) {
        return true;
    }
    $v = ltrim( trim( (string) $new_version ), 'vV' );
    return $v === '' || in_array( $v, $h['versions'], true );
}

// ─── Holds: place and release ────────────────────────────────────────────────

/**
 * Place (or update) a hold on one plugin.
 *
 * Placing a hold on an already-held plugin replaces it; it never creates a second one.
 *
 * @param string $plugin_file "folder/file.php", as get_plugins() keys it.
 * @param array  $args {
 *     @type string       $reason   Required, at most 500 characters.
 *     @type string       $mode     'pin' (default: no updates at all) or 'skip' (only $versions).
 *     @type string|array $versions Versions to block; required for 'skip'.
 *     @type string       $until    See hozio_hold_parse_until(). Default +30d, at most 180 days.
 *     @type string       $source   orchestrator | hub | wp-cli | admin:<login>. Default wp-cli.
 *     @type string       $ref      Optional caller reference.
 * }
 * @return array|WP_Error Compact result for output.
 */
function hozio_update_hold_place( $plugin_file, $args ) {
    $args        = is_array( $args ) ? $args : array();
    $plugin_file = is_string( $plugin_file ) ? trim( $plugin_file ) : '';

    if ( ! hozio_hold_valid_plugin_file( $plugin_file ) ) {
        return new WP_Error( 'invalid_plugin', 'Give the plugin as folder/file.php, the way wp plugin list --field=file shows it.' );
    }

    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $installed = get_plugins();
    if ( ! isset( $installed[ $plugin_file ] ) ) {
        return new WP_Error( 'plugin_not_installed', 'That plugin is not installed on this site.' );
    }

    $reason = hozio_hold_clean_reason( isset( $args['reason'] ) ? $args['reason'] : null );
    if ( is_wp_error( $reason ) ) {
        return $reason;
    }

    $mode = isset( $args['mode'] ) && $args['mode'] !== '' ? $args['mode'] : 'pin';
    if ( $mode !== 'pin' && $mode !== 'skip' ) {
        return new WP_Error( 'invalid_mode', 'Mode must be pin or skip.' );
    }

    $versions = hozio_hold_clean_versions( isset( $args['versions'] ) ? $args['versions'] : null );
    if ( is_wp_error( $versions ) ) {
        return $versions;
    }
    if ( $mode === 'skip' && empty( $versions ) ) {
        return new WP_Error( 'versions_required', 'A skip hold needs the versions to skip.' );
    }

    $until = ( isset( $args['until'] ) && $args['until'] !== '' )
        ? hozio_hold_parse_until( $args['until'], HOZIO_HOLD_MAX_DAYS )
        : time() + HOZIO_HOLD_DEFAULT_DAYS * DAY_IN_SECONDS;
    if ( is_wp_error( $until ) ) {
        return $until;
    }

    $source = hozio_hold_clean_source( isset( $args['source'] ) ? $args['source'] : null, 'wp-cli' );
    if ( is_wp_error( $source ) ) {
        return $source;
    }
    $ref = hozio_hold_clean_ref( isset( $args['ref'] ) ? $args['ref'] : null );
    if ( is_wp_error( $ref ) ) {
        return $ref;
    }

    $held_at = isset( $installed[ $plugin_file ]['Version'] ) ? ltrim( trim( (string) $installed[ $plugin_file ]['Version'] ), 'vV' ) : '';
    if ( ! hozio_hold_valid_version( $held_at ) ) {
        $held_at = '';
    }

    $state    = hozio_update_holds_read();
    $existing = isset( $state['holds'][ $plugin_file ] ) ? $state['holds'][ $plugin_file ] : null;
    $now      = time();

    $state['holds'][ $plugin_file ] = array(
        'mode'       => $mode,
        'versions'   => $versions,
        'held_at'    => $held_at,
        'reason'     => $reason,
        'source'     => $source,
        'ref'        => $ref,
        'created_at' => $existing ? $existing['created_at'] : $now,
        'updated_at' => $now,
        'expires_at' => $until,
    );
    hozio_update_holds_save( $state['holds'], $state['invalid'] );

    hozio_audit_log( sprintf(
        'Hold %s on %s (%s%s, until %s) by %s: %s%s',
        $existing ? 'updated' : 'placed',
        $plugin_file,
        $mode,
        $versions ? ' ' . implode( ',', $versions ) : '',
        hozio_hold_iso( $until ),
        $source,
        $reason,
        $ref !== '' ? ' [ref ' . $ref . ']' : ''
    ), 'Holds' );

    return array(
        'ok'         => true,
        'action'     => 'hold',
        'plugin'     => $plugin_file,
        'mode'       => $mode,
        'versions'   => $versions,
        'held_at'    => $held_at,
        'expires_at' => hozio_hold_iso( $until ),
        'updated'    => (bool) $existing,
    );
}

/**
 * Lift a hold. The only thing that lifts one before it expires.
 *
 * Releasing a plugin that isn't held succeeds with released=false, so a caller can release
 * without checking first. The plugin need not still be installed.
 *
 * @param string $plugin_file
 * @param array  $args { reason (required), source (default wp-cli) }
 * @return array|WP_Error
 */
function hozio_update_hold_release( $plugin_file, $args ) {
    $args        = is_array( $args ) ? $args : array();
    $plugin_file = is_string( $plugin_file ) ? trim( $plugin_file ) : '';

    if ( ! hozio_hold_valid_plugin_file( $plugin_file ) ) {
        return new WP_Error( 'invalid_plugin', 'Give the plugin as folder/file.php, the way wp plugin list --field=file shows it.' );
    }
    $reason = hozio_hold_clean_reason( isset( $args['reason'] ) ? $args['reason'] : null );
    if ( is_wp_error( $reason ) ) {
        return $reason;
    }
    $source = hozio_hold_clean_source( isset( $args['source'] ) ? $args['source'] : null, 'wp-cli' );
    if ( is_wp_error( $source ) ) {
        return $source;
    }

    $state = hozio_update_holds_read();
    if ( ! isset( $state['holds'][ $plugin_file ] ) ) {
        return array( 'ok' => true, 'action' => 'release', 'plugin' => $plugin_file, 'released' => false );
    }

    $old = $state['holds'][ $plugin_file ];
    unset( $state['holds'][ $plugin_file ] );
    hozio_update_holds_save( $state['holds'], $state['invalid'] );

    hozio_audit_log( sprintf(
        'Hold released on %s by %s: %s (was %s, placed by %s: %s)',
        $plugin_file,
        $source,
        $reason,
        $old['expired'] ? 'already expired' : $old['mode'] . ' until ' . hozio_hold_iso( $old['expires_at'] ),
        $old['source'],
        $old['reason']
    ), 'Holds' );

    return array( 'ok' => true, 'action' => 'release', 'plugin' => $plugin_file, 'released' => true );
}

/**
 * A free-text field on its way out of wp-admin: email and IP addresses removed.
 *
 * Reasons, sources and refs are free text. A source can be admin:<login>, and a login is
 * often an email address; a ref can look like an IP address. WP-CLI output lands in the
 * host's activity log, readable by every collaborator on the site. If the redactor is
 * somehow missing, nothing is shown rather than the raw text.
 *
 * @param mixed $s
 * @param bool  $redact
 * @return string
 */
function hozio_hold_public_text( $s, $redact ) {
    $s = (string) $s;
    if ( ! $redact ) {
        return $s;
    }
    return function_exists( 'hozio_log_redact' ) ? hozio_log_redact( $s ) : '';
}

/**
 * Holds as the status output shows them.
 *
 * Redacted by default: this feeds `wp hozio updates holds`, `wp hozio info` and the Hub
 * heartbeat. Only the wp-admin settings panel (administrators only) passes false.
 *
 * @param bool $redact Remove email and IP addresses from reason, source and ref.
 * @return array{holds: array[], invalid: int, active: int, expired: int}
 */
function hozio_update_holds_status( $redact = true ) {
    $state = hozio_update_holds_read();
    $list  = array();
    $live  = 0;

    foreach ( $state['holds'] as $file => $h ) {
        $list[] = array(
            'plugin'     => $file,
            'mode'       => $h['mode'],
            'versions'   => $h['versions'],
            'held_at'    => $h['held_at'],
            'reason'     => hozio_hold_public_text( $h['reason'], $redact ),
            'source'     => hozio_hold_public_text( $h['source'], $redact ),
            'ref'        => hozio_hold_public_text( $h['ref'], $redact ),
            'created_at' => hozio_hold_iso( $h['created_at'] ),
            'expires_at' => hozio_hold_iso( $h['expires_at'] ),
            'expired'    => $h['expired'],
        );
        if ( ! $h['expired'] ) {
            $live++;
        }
    }

    return array(
        'holds'   => $list,
        'invalid' => $state['invalid'],
        'active'  => $live,
        'expired' => count( $list ) - $live,
    );
}

// ─── Freeze ──────────────────────────────────────────────────────────────────

/**
 * @param mixed $f
 * @return array|null
 */
function hozio_update_freeze_validate( $f ) {
    if ( ! is_array( $f ) ) {
        return null;
    }

    $until   = isset( $f['until'] ) ? hozio_hold_int( $f['until'] ) : null;
    $created = isset( $f['created_at'] ) ? hozio_hold_int( $f['created_at'] ) : null;
    if ( ! $until || ! $created || $created > time() + 300 || $until <= $created
        || $until > $created + HOZIO_FREEZE_MAX_DAYS * DAY_IN_SECONDS + 60 ) {
        return null;
    }

    $reason = isset( $f['reason'] ) ? $f['reason'] : '';
    $source = isset( $f['source'] ) ? $f['source'] : '';
    $ref    = isset( $f['ref'] ) ? $f['ref'] : '';
    if ( ! hozio_hold_valid_reason( $reason ) || ! hozio_hold_valid_source( $source ) || ! hozio_hold_valid_ref( $ref ) ) {
        return null;
    }

    return array(
        'until'      => $until,
        'reason'     => $reason,
        'source'     => $source,
        'ref'        => $ref,
        'created_at' => $created,
    );
}

/**
 * The stored freeze, validated. An invalid record never freezes anything.
 *
 * @return array{active: bool, expired: bool, invalid: int, freeze: array|null}
 */
function hozio_update_freeze_read() {
    $raw = get_option( 'hozio_update_freeze', null );
    $out = array( 'active' => false, 'expired' => false, 'invalid' => 0, 'freeze' => null );

    if ( empty( $raw ) ) {
        return $out;
    }

    $f = hozio_update_freeze_validate( $raw );
    if ( ! $f ) {
        $out['invalid'] = 1;
        return $out;
    }

    $now = time();
    if ( $f['until'] > $now ) {
        $out['active'] = true;
        $out['freeze'] = $f;
    } elseif ( $f['until'] + HOZIO_HOLD_EXPIRED_VISIBLE_DAYS * DAY_IN_SECONDS > $now ) {
        $out['expired'] = true;
        $out['freeze']  = $f;
    }
    return $out;
}

/**
 * Is a site-wide update freeze in force right now?
 *
 * @return bool
 */
function hozio_updates_frozen() {
    // hozio_auto_update_blocked_reason() asks WordPress whether updates are switched off
    // for ENVIRONMENTAL reasons; our own freeze must not answer that question for it.
    if ( ! empty( $GLOBALS['hozio_freeze_ignored'] ) ) {
        return false;
    }
    $f = hozio_update_freeze_read();
    return $f['active'];
}

/**
 * Freeze every automatic update on the site until a given time (at most 7 days).
 *
 * @param array $args { until (required), reason (required), source (default wp-cli), ref }
 * @return array|WP_Error
 */
function hozio_updates_freeze( $args ) {
    $args = is_array( $args ) ? $args : array();

    if ( ! isset( $args['until'] ) || $args['until'] === '' ) {
        return new WP_Error( 'until_required', 'Say when the freeze ends, for example --until=+1d.' );
    }
    $until = hozio_hold_parse_until( $args['until'], HOZIO_FREEZE_MAX_DAYS );
    if ( is_wp_error( $until ) ) {
        return $until;
    }
    $reason = hozio_hold_clean_reason( isset( $args['reason'] ) ? $args['reason'] : null );
    if ( is_wp_error( $reason ) ) {
        return $reason;
    }
    $source = hozio_hold_clean_source( isset( $args['source'] ) ? $args['source'] : null, 'wp-cli' );
    if ( is_wp_error( $source ) ) {
        return $source;
    }
    $ref = hozio_hold_clean_ref( isset( $args['ref'] ) ? $args['ref'] : null );
    if ( is_wp_error( $ref ) ) {
        return $ref;
    }

    $before = hozio_update_freeze_read();

    update_option( 'hozio_update_freeze', array(
        'until'      => $until,
        'reason'     => $reason,
        'source'     => $source,
        'ref'        => $ref,
        'created_at' => time(),
    ), false );

    hozio_audit_log( sprintf(
        'Updates %s until %s by %s: %s%s',
        $before['active'] ? 'freeze extended' : 'FROZEN',
        hozio_hold_iso( $until ),
        $source,
        $reason,
        $ref !== '' ? ' [ref ' . $ref . ']' : ''
    ), 'Freeze' );

    return array(
        'ok'       => true,
        'action'   => 'freeze',
        'until'    => hozio_hold_iso( $until ),
        'replaced' => $before['active'],
    );
}

/**
 * Lift the freeze. Also clears an expired or invalid freeze record.
 *
 * @param array $args { reason (required), source (default wp-cli) }
 * @return array|WP_Error
 */
function hozio_updates_unfreeze( $args ) {
    $args = is_array( $args ) ? $args : array();

    $reason = hozio_hold_clean_reason( isset( $args['reason'] ) ? $args['reason'] : null );
    if ( is_wp_error( $reason ) ) {
        return $reason;
    }
    $source = hozio_hold_clean_source( isset( $args['source'] ) ? $args['source'] : null, 'wp-cli' );
    if ( is_wp_error( $source ) ) {
        return $source;
    }

    $before = hozio_update_freeze_read();
    $stored = get_option( 'hozio_update_freeze', null );
    if ( empty( $stored ) ) {
        return array( 'ok' => true, 'action' => 'unfreeze', 'unfrozen' => false );
    }

    delete_option( 'hozio_update_freeze' );

    if ( $before['active'] ) {
        $was = 'was frozen until ' . hozio_hold_iso( $before['freeze']['until'] ) . ' by ' . $before['freeze']['source'];
    } elseif ( $before['invalid'] ) {
        $was = 'cleared an invalid freeze record';
    } else {
        $was = 'cleared an expired freeze';
    }
    hozio_audit_log( sprintf( 'Updates UNFROZEN by %s: %s (%s)', $source, $reason, $was ), 'Freeze' );

    return array( 'ok' => true, 'action' => 'unfreeze', 'unfrozen' => $before['active'] );
}

/**
 * The freeze as the status output shows it.
 *
 * Redacted by default, like hozio_update_holds_status(); only the wp-admin settings panel
 * passes false.
 *
 * @param bool $redact Remove email and IP addresses from reason, source and ref.
 * @return array
 */
function hozio_update_freeze_status( $redact = true ) {
    $f   = hozio_update_freeze_read();
    $out = array(
        'active'  => $f['active'],
        'until'   => null,
        'reason'  => '',
        'source'  => '',
        'ref'     => '',
        'expired' => $f['expired'],
        'invalid' => $f['invalid'],
    );
    if ( $f['freeze'] ) {
        $out['until']  = hozio_hold_iso( $f['freeze']['until'] );
        $out['reason'] = hozio_hold_public_text( $f['freeze']['reason'], $redact );
        $out['source'] = hozio_hold_public_text( $f['freeze']['source'], $redact );
        $out['ref']    = hozio_hold_public_text( $f['freeze']['ref'], $redact );
    }
    return $out;
}

// ─── Enforcement ─────────────────────────────────────────────────────────────

/**
 * The last word on auto_update_plugin: a held plugin is not auto-updated, whoever approved
 * it — Hozio Pro's Auto-Update All, WordPress's own per-plugin setting, Jetpack, anything.
 *
 * Deliberately separate from hozio_auto_update_all_plugins_filter(), so holds still apply
 * on a site with Auto-Update All switched off. Also covers Hozio Pro itself.
 *
 * Code that adds its own PHP_INT_MAX filter AFTER this one (the Hub's targeted update does)
 * must check holds itself, and does.
 *
 * @param bool|null $update
 * @param object    $item
 * @return bool|null
 */
function hozio_update_holds_filter( $update, $item ) {
    $file = ( is_object( $item ) && isset( $item->plugin ) ) ? (string) $item->plugin : '';
    if ( $file === '' ) {
        return $update;
    }

    if ( hozio_updates_frozen() ) {
        return false;
    }

    $new = isset( $item->new_version ) ? (string) $item->new_version : '';
    if ( ! hozio_update_hold_blocks( $file, $new ) ) {
        return $update;
    }

    // Say so in the audit log during a real run, once per plugin per request. The Plugins
    // screen calls this same filter for every row, so it must stay quiet there.
    static $logged = array();
    $in_run = ( defined( 'DOING_CRON' ) && DOING_CRON ) || ! empty( $GLOBALS['hozio_running_updates_now'] );
    if ( $in_run && ! isset( $logged[ $file ] ) ) {
        $logged[ $file ] = true;
        $h = hozio_update_hold_get_active( $file );
        hozio_audit_log( sprintf(
            'Skipped %s%s: held until %s (%s)',
            $file,
            $new !== '' ? ' ' . $new : '',
            $h ? hozio_hold_iso( $h['expires_at'] ) : '?',
            $h ? $h['reason'] : ''
        ), 'AutoUpdate' );
    }
    return false;
}
add_filter( 'auto_update_plugin', 'hozio_update_holds_filter', PHP_INT_MAX, 2 );

/**
 * While frozen, themes, core and translations are declined too.
 *
 * @param bool|null $update
 * @return bool|null
 */
function hozio_update_freeze_deny( $update ) {
    return hozio_updates_frozen() ? false : $update;
}
add_filter( 'auto_update_theme', 'hozio_update_freeze_deny', PHP_INT_MAX );
add_filter( 'auto_update_core', 'hozio_update_freeze_deny', PHP_INT_MAX );
add_filter( 'auto_update_translation', 'hozio_update_freeze_deny', PHP_INT_MAX );

/**
 * While frozen, WordPress's automatic updater does not run at all.
 *
 * The "Override and force updates on" option also works through this filter at
 * PHP_INT_MAX; it checks the freeze itself, so whichever runs last the freeze wins.
 *
 * @param bool $disabled
 * @return bool
 */
function hozio_update_freeze_disable_updater( $disabled ) {
    return hozio_updates_frozen() ? true : $disabled;
}
add_filter( 'automatic_updater_disabled', 'hozio_update_freeze_disable_updater', PHP_INT_MAX );

// ─── Status for the orchestrator (wp hozio info) ─────────────────────────────

// The interface version the Hozio Studio Orchestrator reads from `wp hozio info`. Bump it
// ONLY for a breaking change: a key removed or renamed, a meaning changed. Adding a key or
// a capability is not breaking and does not bump it.
define( 'HOZIO_ORCHESTRATOR_CONTRACT', 1 );

/**
 * Everything the orchestrator needs to decide whether a repair is safe, in one array.
 *
 * Key order is part of the contract: contract and capabilities come first so they survive
 * when a caller only sees the first few hundred characters. Never includes a URL, token,
 * key or password.
 *
 * @param bool $compact Only the headline fields (well under 500 characters as JSON).
 * @return array
 */
function hozio_orchestrator_info( $compact = false ) {
    // Both redacted (reason, source, ref): this output ends up in the host's activity log.
    $holds   = hozio_update_holds_status( true );
    $freeze  = hozio_update_freeze_status( true );
    $license = function_exists( 'hozio_get_license_status' ) ? hozio_get_license_status() : array();

    $out = array(
        'contract'       => HOZIO_ORCHESTRATOR_CONTRACT,
        'plugin_version' => defined( 'HOZIO_VERSION' ) ? HOZIO_VERSION : '',
        'capabilities'   => array( 'holds', 'freeze', 'log', 'status', 'history' ),
        'license_status' => isset( $license['status'] ) ? (string) $license['status'] : 'unknown',
        'hub_connected'  => class_exists( 'Hozio_Hub_Client' ) && Hozio_Hub_Client::is_connected(),
        'freeze'         => $compact
            ? array( 'active' => $freeze['active'], 'until' => $freeze['until'] )
            : $freeze,
        'holds_active'   => $holds['active'],
        'holds_expired'  => $holds['expired'],
        'holds_invalid'  => $holds['invalid'],
    );
    if ( $compact ) {
        return $out;
    }

    $patch  = function_exists( 'hozio_get_patch_status' ) ? hozio_get_patch_status() : array();
    $paused = (int) get_option( 'hozio_auto_updates_paused_until', 0 );
    $next   = isset( $patch['next_run'] ) ? (int) $patch['next_run'] : 0;

    $out['self_update'] = array(
        'enabled'        => get_option( 'hozio_auto_updates_enabled', '1' ) === '1',
        'version_locked' => get_option( 'hozio_version_locked', '0' ) === '1',
        'paused_until'   => $paused > time() ? hozio_hold_iso( $paused ) : null,
        'held'           => defined( 'HOZIO_PLUGIN_FILE' ) && hozio_update_hold_get_active( plugin_basename( HOZIO_PLUGIN_FILE ) ) !== null,
    );

    $out['auto_update_all'] = array(
        'enabled'        => function_exists( 'hozio_auto_update_all_enabled' ) && hozio_auto_update_all_enabled(),
        'force'          => function_exists( 'hozio_auto_update_force_enabled' ) && hozio_auto_update_force_enabled(),
        'excluded'       => isset( $patch['excluded'] ) ? array_values( $patch['excluded'] ) : array(),
        'max_per_run'    => isset( $patch['max_per_run'] ) ? (int) $patch['max_per_run'] : 0,
        'blocked_reason' => isset( $patch['blocked_reason'] ) ? (string) $patch['blocked_reason'] : '',
        'next_run'       => $next > 0 ? hozio_hold_iso( $next ) : null,
        'git_updater'    => ! empty( $patch['git_updater'] ),
    );

    $out['holds'] = $holds['holds'];

    // An object even when empty, so the type never changes between sites.
    $out['pending'] = (object) ( isset( $patch['pending'] ) ? $patch['pending'] : array() );

    $recent = array();
    foreach ( ( isset( $patch['recent'] ) ? $patch['recent'] : array() ) as $r ) {
        $r['error'] = hozio_log_redact( $r['error'] );
        $recent[]   = $r;
    }
    $out['recent'] = $recent;

    return $out;
}

// ─── Plugins screen ──────────────────────────────────────────────────────────

/**
 * Link that lifts a hold (update_plugins users only; nonce-protected).
 *
 * @param string $plugin_file
 * @param string $back 'plugins' or 'settings'
 * @return string
 */
function hozio_release_hold_url( $plugin_file, $back = 'plugins' ) {
    return wp_nonce_url(
        add_query_arg(
            array(
                'action' => 'hozio_release_hold',
                'plugin' => rawurlencode( $plugin_file ),
                'back'   => $back === 'settings' ? 'settings' : 'plugins',
            ),
            admin_url( 'admin-post.php' )
        ),
        'hozio_release_hold_' . $plugin_file
    );
}

/**
 * Link that lifts the freeze.
 *
 * @return string
 */
function hozio_unfreeze_url() {
    return wp_nonce_url(
        add_query_arg( array( 'action' => 'hozio_unfreeze_updates' ), admin_url( 'admin-post.php' ) ),
        'hozio_unfreeze_updates'
    );
}

/**
 * Where to go after a release/unfreeze, with the outcome for the notice.
 *
 * @param string $back
 * @param string $msg
 * @return string
 */
function hozio_update_holds_back_url( $back, $msg ) {
    $url = ( $back === 'settings' )
        ? admin_url( 'admin.php?page=hozio-plugin-settings' )
        : admin_url( 'plugins.php' );
    $url = add_query_arg( 'hozio_holds', $msg, $url );
    return $back === 'settings' ? $url . '#hozio-update-holds' : $url;
}

/**
 * The Auto-updates column: say that Hozio Pro manages it, and show a hold.
 *
 * WordPress offers a per-plugin toggle here, but a filter forcing auto-updates on makes that
 * toggle meaningless (the row shows it as unavailable). Say what is really in charge instead.
 *
 * @param string $html
 * @param string $plugin_file
 * @return string
 */
function hozio_update_holds_setting_html( $html, $plugin_file ) {
    $h = hozio_update_hold_get_active( $plugin_file );
    if ( $h ) {
        $out = '<span class="hozio-hold-note" style="color:#b45309;">'
             . esc_html( sprintf(
                 'Held until %s: %s (%s)',
                 hozio_hold_local_date( $h['expires_at'] ),
                 $h['reason'],
                 $h['source']
             ) )
             . '</span>';
        if ( current_user_can( 'update_plugins' ) ) {
            $out .= '<br><a href="' . esc_url( hozio_release_hold_url( $plugin_file, 'plugins' ) ) . '"'
                  . ' onclick="return confirm(\'Release this hold? Automatic updates for this plugin start again on the next run.\');">'
                  . 'Release hold</a>';
        }
        return $out;
    }

    $freeze = hozio_update_freeze_read();
    if ( $freeze['active'] ) {
        return esc_html( sprintf(
            'All automatic updates frozen until %s',
            hozio_hold_local_date( $freeze['freeze']['until'], true )
        ) );
    }

    if ( function_exists( 'hozio_auto_update_all_enabled' ) && hozio_auto_update_all_enabled() ) {
        return esc_html( 'Auto-updates managed by Hozio Pro' );
    }

    return $html;
}
add_filter( 'plugin_auto_update_setting_html', 'hozio_update_holds_setting_html', 20, 2 );

/**
 * Warn in the "update available" row of a held plugin. The update is not blocked; a
 * person may know better. They should just know what the hold was for.
 *
 * @return void
 */
function hozio_update_holds_row_warnings() {
    $state = hozio_update_holds_read();
    foreach ( $state['holds'] as $file => $h ) {
        if ( $h['expired'] ) {
            continue;
        }
        add_action( 'in_plugin_update_message-' . $file, function () use ( $h ) {
            echo '<br><strong style="color:#b45309;">Hozio Pro hold:</strong> '
               . esc_html( sprintf(
                   '%s (%s, until %s). Updating by hand may bring back the version that broke the site.',
                   $h['reason'],
                   $h['source'],
                   hozio_hold_local_date( $h['expires_at'] )
               ) );
        } );
    }
}
add_action( 'load-plugins.php', 'hozio_update_holds_row_warnings' );
add_action( 'load-update-core.php', 'hozio_update_holds_row_warnings' );

/**
 * admin-post.php?action=hozio_release_hold — Release hold link (Plugins screen, settings).
 *
 * @return void
 */
function hozio_update_holds_admin_release() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_die( 'Permission denied', 403 );
    }
    $file = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';
    check_admin_referer( 'hozio_release_hold_' . $file );

    $back   = ( isset( $_GET['back'] ) && $_GET['back'] === 'settings' ) ? 'settings' : 'plugins';
    $result = hozio_update_hold_release( $file, array(
        'reason' => 'Released by hand in wp-admin',
        'source' => hozio_hold_admin_source(),
    ) );

    if ( is_wp_error( $result ) ) {
        $msg = 'error';
    } else {
        $msg = $result['released'] ? 'released' : 'none';
    }
    wp_safe_redirect( hozio_update_holds_back_url( $back, $msg ) );
    exit;
}
add_action( 'admin_post_hozio_release_hold', 'hozio_update_holds_admin_release' );

/**
 * admin-post.php?action=hozio_unfreeze_updates — Unfreeze button (settings).
 *
 * @return void
 */
function hozio_update_holds_admin_unfreeze() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_die( 'Permission denied', 403 );
    }
    check_admin_referer( 'hozio_unfreeze_updates' );

    $result = hozio_updates_unfreeze( array(
        'reason' => 'Unfrozen by hand in wp-admin',
        'source' => hozio_hold_admin_source(),
    ) );

    wp_safe_redirect( hozio_update_holds_back_url( 'settings', is_wp_error( $result ) ? 'error' : 'unfrozen' ) );
    exit;
}
add_action( 'admin_post_hozio_unfreeze_updates', 'hozio_update_holds_admin_unfreeze' );

/**
 * Outcome message for the notice after a release/unfreeze.
 *
 * @return string '' when there is nothing to say.
 */
function hozio_update_holds_notice_text() {
    $code = isset( $_GET['hozio_holds'] ) ? sanitize_key( wp_unslash( $_GET['hozio_holds'] ) ) : '';
    $map  = array(
        'released' => 'Hold released. Automatic updates for that plugin resume on the next run.',
        'none'     => 'That plugin was not held.',
        'unfrozen' => 'Updates unfrozen. Automatic updates resume on the next run.',
        'error'    => 'That did not work. Check the audit log.',
    );
    return isset( $map[ $code ] ) ? $map[ $code ] : '';
}

/**
 * Show the outcome on the Plugins screen. (The Hozio settings page hides third-party
 * notices, so it prints its own inside the holds panel.)
 *
 * @return void
 */
function hozio_update_holds_admin_notice() {
    global $pagenow;
    if ( $pagenow !== 'plugins.php' ) {
        return;
    }
    $text = hozio_update_holds_notice_text();
    if ( $text !== '' ) {
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
    }
}
add_action( 'admin_notices', 'hozio_update_holds_admin_notice' );
