<?php
/**
 * Fleet plugin auto-updates.
 *
 * Lets WordPress's own auto-update system install updates for ALL plugins on
 * the site, not just Hozio Pro.
 *
 * Deliberately thin. WordPress already checks for plugin updates twice daily on
 * every site and already knows how to download, install, and (on WP 6.3+) roll
 * back an update that fatals. It simply isn't permitted to install anything
 * unless something opts each plugin in. This file is that opt-in — no polling,
 * no cron, no remote control channel, no new attack surface.
 *
 * ON by default — a site that never touches the setting auto-updates its plugins.
 * Turn it off per-site in Hozio Pro Settings → Feature Toggles. On Hub-connected
 * sites it can also be flipped remotely with the update_option command
 * (hozio_auto_update_all_plugins = '0' to disable).
 *
 * Hozio Pro itself is deliberately NOT decided here — includes/plugin-updater.php
 * owns that call, because it carries extra guards (valid license, version lock,
 * post-rollback pause) that must not be bypassed.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Is fleet plugin auto-updating switched on for this site?
 */
function hozio_auto_update_all_enabled() {
    return get_option( 'hozio_auto_update_all_plugins', '1' ) === '1';
}

/**
 * Is the operator forcing auto-updates on despite something having disabled them?
 */
function hozio_auto_update_force_enabled() {
    return get_option( 'hozio_auto_update_force', '0' ) === '1';
}

/**
 * Override another plugin's decision to switch WordPress auto-updates off.
 *
 * Update-management plugins (ManageWP Worker, MainWP Child), security plugins, and some
 * hosts set the automatic_updater_disabled filter or the AUTOMATIC_UPDATER_DISABLED
 * constant. Core reads both through this one filter, so a late filter re-enables either.
 *
 * OPT-IN ONLY, and deliberately so: if a tool really is managing updates, forcing WP to
 * update as well means two systems writing the same plugin directories. The operator has
 * to make that call knowingly.
 *
 * Note this cannot override DISALLOW_FILE_MODS or a non-direct filesystem — core checks
 * those before the filter, and rightly so: they mean writes genuinely aren't permitted.
 */
add_filter( 'automatic_updater_disabled', function ( $disabled ) {
    if ( hozio_auto_update_all_enabled() && hozio_auto_update_force_enabled() ) {
        return false;
    }
    return $disabled;
}, PHP_INT_MAX );

/**
 * Re-schedule WordPress's auto-update cron if something removed it.
 *
 * A missing wp_maybe_auto_update event means updates can never install unattended, no
 * matter what else is configured — the "next automatic run: not scheduled" symptom.
 * Restoring it is safe: it is the same event core schedules itself, and WordPress still
 * decides whether to act when it fires.
 */
add_action( 'init', function () {
    if ( ! hozio_auto_update_all_enabled() || wp_installing() ) {
        return;
    }
    if ( ! wp_next_scheduled( 'wp_maybe_auto_update' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'wp_maybe_auto_update' );
    }
}, 20 );

/**
 * Operator's exclusion list as a fast lookup array.
 *
 * Accepts either a plugin file ("akismet/akismet.php") or just the folder slug
 * ("akismet"), one per line or comma separated, so it's forgiving about how the
 * value gets typed in.
 *
 * @return array<string,bool>
 */
function hozio_auto_update_exclusions() {
    $raw = (string) get_option( 'hozio_auto_update_exclude', '' );
    if ( trim( $raw ) === '' ) {
        return array();
    }

    $out = array();
    foreach ( preg_split( '/[\r\n,]+/', $raw ) as $line ) {
        $line = strtolower( trim( $line ) );
        if ( $line !== '' ) {
            $out[ $line ] = true;
        }
    }
    return $out;
}

/**
 * Grant WordPress permission to auto-update third-party plugins.
 *
 * Priority 20 so this runs AFTER plugin-updater.php's own filter (priority 10);
 * for Hozio Pro we hand back whatever that filter decided, untouched.
 *
 * @param bool|null $update Whether WP intends to auto-update this item.
 * @param object    $item   Update object (->slug, ->plugin, ->new_version).
 * @return bool|null
 */
function hozio_auto_update_all_plugins_filter( $update, $item ) {
    if ( ! hozio_auto_update_all_enabled() ) {
        return $update;
    }

    $slug = isset( $item->slug )   ? strtolower( (string) $item->slug )   : '';
    $file = isset( $item->plugin ) ? strtolower( (string) $item->plugin ) : '';

    // Hozio Pro decides its own fate in plugin-updater.php — never override it.
    foreach ( array( 'hozio-dynamic-tags', 'hozio-pro' ) as $self ) {
        if ( ( $slug !== '' && strpos( $slug, $self ) !== false )
          || ( $file !== '' && strpos( $file, $self ) !== false ) ) {
            return $update;
        }
    }

    $exclusions = hozio_auto_update_exclusions();
    if ( ! empty( $exclusions ) ) {
        // Match on the full plugin file, the slug, or the containing folder.
        $folder = ( $file !== '' && strpos( $file, '/' ) !== false ) ? strtok( $file, '/' ) : '';
        foreach ( array( $file, $slug, $folder ) as $candidate ) {
            if ( $candidate !== '' && isset( $exclusions[ $candidate ] ) ) {
                return false;
            }
        }
    }

    // ── Major-version guard ──────────────────────────────────────────────────
    // Never auto-install a major version jump (3.x -> 4.x). Major releases carry
    // breaking changes, and the sharpest case is a free plugin whose PREMIUM twin
    // cannot follow: Elementor 3.x -> 4.x while Elementor Pro (no license, no
    // update offered) stays on 3.x. Pro then fails its compatibility check, fatals,
    // and WordPress deactivates it. Same shape for WooCommerce, ACF, and friends.
    //
    // Security fixes ship in patch/minor releases, so declining majors keeps the
    // patching benefit while removing the breakage. Majors are left for a human to
    // schedule alongside their paid counterpart. WordPress simply keeps offering
    // the update in wp-admin; nothing is lost, it just isn't applied unattended.
    if ( $file !== '' && isset( $item->new_version ) ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed = get_plugins();
        $current   = isset( $installed[ $item->plugin ]['Version'] ) ? $installed[ $item->plugin ]['Version'] : '';

        if ( $current !== '' ) {
            $current_major = (int) strtok( ltrim( $current, 'vV' ), '.' );
            $new_major     = (int) strtok( ltrim( (string) $item->new_version, 'vV' ), '.' );

            if ( $new_major > $current_major && apply_filters( 'hozio_block_major_auto_updates', true, $item ) ) {
                if ( function_exists( 'hozio_audit_log' ) ) {
                    hozio_audit_log(
                        sprintf(
                            'Skipped major update for %s (%s → %s) — install manually alongside any paid add-on',
                            $slug !== '' ? $slug : $file,
                            $current,
                            $item->new_version
                        ),
                        'AutoUpdate'
                    );
                }
                return false;
            }
        }
    }

    // ── Per-run cap ──────────────────────────────────────────────────────────
    // A neglected site can accumulate a long backlog. Installing all of it in one
    // PHP request risks hitting max_execution_time and finishing half-done, and it
    // concentrates disk I/O on servers hosting many sites. Approving a few per run
    // spreads a backlog over a couple of days; steady-state (0-2 pending) is
    // unaffected. WordPress simply re-offers whatever we decline on the next tick.
    //
    // ONLY applied during a real update run. The plugins-list screen calls this
    // same filter once per plugin to render the "Auto-updates enabled" column —
    // counting there would make every plugin past the cap read as disabled.
    $in_update_run = ( defined( 'DOING_CRON' ) && DOING_CRON )
        || ! empty( $GLOBALS['hozio_running_updates_now'] );

    if ( $in_update_run ) {
        static $approved = 0;
        $max = (int) apply_filters( 'hozio_max_plugin_auto_updates_per_run', 3 );
        if ( $max > 0 && $approved >= $max ) {
            return false;
        }
        $approved++;
    }

    return true;
}
add_filter( 'auto_update_plugin', 'hozio_auto_update_all_plugins_filter', 20, 2 );

/**
 * Record every automatic plugin update in the audit log.
 *
 * Fires once per auto-update run with the results for the whole batch. This is
 * the only visibility you get on a site you aren't watching, so it always logs
 * (the audit log is not gated behind debug mode) and records failures too.
 *
 * @param array $results Results keyed by type: core / plugin / theme / translation.
 */
function hozio_log_plugin_auto_updates( $results ) {
    if ( empty( $results['plugin'] ) || ! is_array( $results['plugin'] ) || ! function_exists( 'hozio_audit_log' ) ) {
        return;
    }

    foreach ( $results['plugin'] as $plugin_result ) {
        $item = isset( $plugin_result->item ) ? $plugin_result->item : null;

        $name = '';
        if ( $item ) {
            $name = $item->slug ?? ( $item->plugin ?? '' );
        }
        if ( $name === '' && isset( $plugin_result->name ) ) {
            $name = $plugin_result->name;
        }
        if ( $name === '' ) {
            $name = 'unknown plugin';
        }

        $version = ( $item && isset( $item->new_version ) ) ? $item->new_version : '?';

        $succeeded = isset( $plugin_result->result )
            && ! is_wp_error( $plugin_result->result )
            && $plugin_result->result;

        $reason = '';
        if ( ! $succeeded ) {
            $reason = ( isset( $plugin_result->result ) && is_wp_error( $plugin_result->result ) )
                ? $plugin_result->result->get_error_message()
                : 'unknown error';
        }

        if ( $succeeded ) {
            hozio_audit_log( sprintf( 'Auto-updated %s to %s', $name, $version ), 'AutoUpdate' );
        } else {
            hozio_audit_log( sprintf( 'Auto-update FAILED for %s (%s): %s', $name, $version, $reason ), 'AutoUpdate' );
        }

        // The audit log is a flat text file — fine on one site, unqueryable across a
        // fleet. Mirror each result into a small structured ring buffer so the Hub
        // heartbeat can report it. Deliberately NOT cleared on send: the heartbeat is
        // fire-and-forget, so a fixed-size buffer stays correct across a missed beat.
        hozio_record_auto_update_result( array(
            'plugin'  => $item->plugin ?? '',
            'name'    => $name,
            'version' => $version,
            'ok'      => (bool) $succeeded,
            'error'   => $reason,
            'at'      => gmdate( 'Y-m-d H:i:s' ),
        ) );
    }
}

/**
 * Push one result onto the auto-update history ring buffer (newest first, cap 20).
 */
function hozio_record_auto_update_result( array $entry ) {
    $history = get_option( 'hozio_auto_update_history', array() );
    if ( ! is_array( $history ) ) {
        $history = array();
    }
    array_unshift( $history, $entry );
    if ( count( $history ) > 20 ) {
        $history = array_slice( $history, 0, 20 );
    }
    update_option( 'hozio_auto_update_history', $history, false );
}

/**
 * Patch status for this site — what the Hub needs to answer "did the fleet patch?"
 *
 * Everything here is already computed by WordPress; this only assembles it. Safe to
 * call in cron context. Returns an array shaped per SPEC-hub-fleet-patching.md.
 *
 * @return array
 */
function hozio_get_patch_status() {
    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $installed = array();
    foreach ( get_plugins() as $file => $data ) {
        $installed[ $file ] = array(
            'name'    => isset( $data['Name'] ) ? $data['Name'] : $file,
            'version' => isset( $data['Version'] ) ? $data['Version'] : '',
        );
    }

    // Pending = what WordPress already knows is available, from its own update check.
    $pending   = array();
    $transient = get_site_transient( 'update_plugins' );
    if ( $transient && ! empty( $transient->response ) && is_array( $transient->response ) ) {
        foreach ( $transient->response as $file => $info ) {
            $pending[ $file ] = array(
                'name' => $installed[ $file ]['name'] ?? $file,
                'from' => $installed[ $file ]['version'] ?? '',
                'to'   => isset( $info->new_version ) ? $info->new_version : '',
            );
        }
    }

    return array(
        'auto_update_enabled' => hozio_auto_update_all_enabled(),
        // Empty when updates can run. A non-empty value means this site CANNOT install
        // updates at all (host lockout, no direct filesystem access, VCS checkout) — it
        // would otherwise sit in the fleet looking permanently "current".
        'blocked_reason'      => hozio_auto_update_blocked_reason(),
        'max_per_run'         => (int) apply_filters( 'hozio_max_plugin_auto_updates_per_run', 3 ),
        'excluded'            => array_keys( hozio_auto_update_exclusions() ),
        'next_run'            => (int) wp_next_scheduled( 'wp_maybe_auto_update' ),
        'pending'             => $pending,
        'installed'           => $installed,
        'recent'              => get_option( 'hozio_auto_update_history', array() ),
    );
}
add_action( 'automatic_updates_complete', 'hozio_log_plugin_auto_updates' );

/**
 * Why this site cannot install plugin updates, if it can't.
 *
 * WordPress refuses to auto-update for several environmental reasons, and it does so
 * SILENTLY — wp_maybe_auto_update() simply returns and nothing is logged. That makes a
 * blocked site look identical to an up-to-date one, which is the worst possible failure
 * mode across a fleet. This names the blocker instead.
 *
 * Uses core's own WP_Automatic_Updater checks rather than re-implementing them, so it
 * stays correct as WordPress changes.
 *
 * @return string Empty string when updates can run, otherwise a human-readable reason.
 */
function hozio_auto_update_blocked_reason() {
    // Most specific first: a hard host/wp-config lockout on all file changes.
    if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
        return 'File modifications are disabled on this site (DISALLOW_FILE_MODS is set in wp-config.php or by the host). No plugin can be installed or updated, by any method.';
    }

    if ( ! class_exists( 'WP_Automatic_Updater' ) ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    if ( class_exists( 'WP_Automatic_Updater' ) ) {
        $updater = new WP_Automatic_Updater();

        // Covers AUTOMATIC_UPDATER_DISABLED and the automatic_updater_disabled filter,
        // which hosts and security plugins both commonly set.
        if ( method_exists( $updater, 'is_disabled' ) && $updater->is_disabled() ) {
            return 'WordPress automatic updates are switched off on this site, so nothing can install unattended. '
                 . 'Common causes: an update-management plugin that takes over updates (ManageWP Worker, MainWP Child), '
                 . 'a security plugin, the AUTOMATIC_UPDATER_DISABLED constant in wp-config.php, or a host mu-plugin. '
                 . 'Tick "Override and force updates on" below to have Hozio Pro re-enable them.';
        }

        // WordPress refuses to auto-update a site under version control.
        if ( method_exists( $updater, 'is_vcs_checkout' ) && $updater->is_vcs_checkout( ABSPATH ) ) {
            return 'This site is under version control (a .git or .svn directory was found), so WordPress blocks automatic updates to avoid conflicting with deployments.';
        }
    }

    // Without direct write access WordPress would need FTP credentials, which it cannot
    // prompt for during an unattended run.
    if ( ! function_exists( 'get_filesystem_method' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if ( function_exists( 'get_filesystem_method' ) && get_filesystem_method() !== 'direct' ) {
        return 'WordPress cannot write to the plugins directory directly (it would need FTP/SSH credentials). This is usually a file-ownership or permissions problem on the server.';
    }

    return '';
}

/**
 * Run the plugin auto-updater immediately, instead of waiting for the twice-daily
 * cron. Same code path WordPress uses on its own schedule — this just stops waiting.
 *
 * Exists because WP-cron is visitor-triggered and can drift on quiet sites, and
 * because not every host has WP-CLI available for a manual nudge.
 *
 * @return array{updated:int,failed:int,messages:string[]}
 */
function hozio_run_plugin_auto_updates_now() {
    if ( ! function_exists( 'wp_maybe_auto_update' ) ) {
        require_once ABSPATH . 'wp-admin/includes/update.php';
    }
    if ( ! function_exists( 'wp_clean_plugins_cache' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    @set_time_limit( 300 );

    // Signals the filter that this is a genuine update run, so the per-run cap
    // applies here exactly as it does under cron.
    $GLOBALS['hozio_running_updates_now'] = true;

    // Capture the batch result without disturbing the audit-log listener.
    $captured = array();
    $collect  = function ( $results ) use ( &$captured ) {
        $captured = isset( $results['plugin'] ) && is_array( $results['plugin'] ) ? $results['plugin'] : array();
    };
    add_action( 'automatic_updates_complete', $collect, 99 );

    // Force a fresh look at what's available, then run the updater.
    wp_clean_plugins_cache( true );
    wp_update_plugins();
    wp_maybe_auto_update();

    remove_action( 'automatic_updates_complete', $collect, 99 );
    unset( $GLOBALS['hozio_running_updates_now'] );

    $updated  = 0;
    $failed   = 0;
    $messages = array();

    foreach ( $captured as $r ) {
        $item = isset( $r->item ) ? $r->item : null;
        $name = $item ? ( $item->slug ?? ( $item->plugin ?? 'plugin' ) ) : 'plugin';
        $ver  = ( $item && isset( $item->new_version ) ) ? $item->new_version : '';

        if ( isset( $r->result ) && ! is_wp_error( $r->result ) && $r->result ) {
            $updated++;
            $messages[] = sprintf( '%s → %s', $name, $ver );
        } else {
            $failed++;
            $why = ( isset( $r->result ) && is_wp_error( $r->result ) )
                ? $r->result->get_error_message()
                : 'unknown error';
            $messages[] = sprintf( '%s FAILED: %s', $name, $why );
        }
    }

    return array( 'updated' => $updated, 'failed' => $failed, 'messages' => $messages );
}

/**
 * AJAX: "Run Plugin Updates Now" button on the settings page.
 */
add_action( 'wp_ajax_hozio_run_plugin_updates', function () {
    check_ajax_referer( 'hozio_run_plugin_updates', 'nonce' );
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_send_json_error( 'Unauthorized', 403 );
    }
    if ( ! hozio_auto_update_all_enabled() ) {
        wp_send_json_error( 'Auto-Update All Plugins is turned off for this site.' );
    }

    // Report an environmental blocker plainly rather than letting the run come back
    // empty and read as "everything is current".
    $blocked = hozio_auto_update_blocked_reason();
    if ( $blocked !== '' ) {
        wp_send_json_error( $blocked );
    }

    $result = hozio_run_plugin_auto_updates_now();

    if ( $result['updated'] === 0 && $result['failed'] === 0 ) {
        // Distinguish "already current" from "updates are pending but none installed",
        // which means something stopped the run after the preflight passed.
        $pending = 0;
        $t = get_site_transient( 'update_plugins' );
        if ( $t && ! empty( $t->response ) && is_array( $t->response ) ) {
            $pending = count( $t->response );
        }

        if ( $pending > 0 ) {
            wp_send_json_error( sprintf(
                '%d update(s) are pending but none installed. They may all be major-version updates (skipped by design), excluded, or WordPress declined them. Check the audit log for details.',
                $pending
            ) );
        }

        wp_send_json_success( array( 'summary' => 'Nothing to update — all plugins are already current.' ) );
    }

    wp_send_json_success( array(
        'summary' => sprintf(
            '%d updated, %d failed. %s',
            $result['updated'],
            $result['failed'],
            implode( ' · ', $result['messages'] )
        ),
    ) );
} );
