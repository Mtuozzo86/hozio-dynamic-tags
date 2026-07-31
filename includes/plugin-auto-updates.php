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

        if ( $succeeded ) {
            hozio_audit_log( sprintf( 'Auto-updated %s to %s', $name, $version ), 'AutoUpdate' );
        } else {
            $reason = ( isset( $plugin_result->result ) && is_wp_error( $plugin_result->result ) )
                ? $plugin_result->result->get_error_message()
                : 'unknown error';
            hozio_audit_log( sprintf( 'Auto-update FAILED for %s (%s): %s', $name, $version, $reason ), 'AutoUpdate' );
        }
    }
}
add_action( 'automatic_updates_complete', 'hozio_log_plugin_auto_updates' );

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

    $result = hozio_run_plugin_auto_updates_now();

    if ( $result['updated'] === 0 && $result['failed'] === 0 ) {
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
