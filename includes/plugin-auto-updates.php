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
 * Is a Git-based update manager running this site?
 *
 * Git Updater (and its predecessor GitHub Updater) serves plugin updates straight from
 * GitHub/GitLab/Bitbucket/Gitea. Where it is in charge, WordPress must not be: the two
 * would be writing the same plugin directories, and Git Updater is often deliberately
 * tracking a BRANCH rather than a tagged release, so letting the generic auto-updater
 * install over it can replace a developer's working code with something else entirely.
 *
 * We stand down for the plugins it manages rather than switching it off — see
 * hozio_plugin_managed_by_git_updater().
 *
 * @return bool
 */
function hozio_git_update_manager_active() {
    static $active = null;
    if ( $active !== null ) {
        return $active;
    }

    foreach ( array( 'Fragen\\Git_Updater\\Bootstrap', 'Fragen\\GitHub_Updater\\Bootstrap', 'GitHub_Updater' ) as $class ) {
        if ( class_exists( $class ) ) {
            return $active = true;
        }
    }

    if ( defined( 'GIT_UPDATER_DIR' ) || defined( 'GITHUB_UPDATER_DIR' ) ) {
        return $active = true;
    }

    // Class detection depends on load order; the active-plugins list does not.
    foreach ( (array) get_option( 'active_plugins', array() ) as $plugin_file ) {
        $folder = strtok( strtolower( (string) $plugin_file ), '/' );
        if ( $folder === 'git-updater' || $folder === 'github-updater' ) {
            return $active = true;
        }
    }

    return $active = false;
}

/**
 * Does this plugin carry the headers a Git-based update manager claims ownership by?
 *
 * These headers are inert on their own — Hozio Pro itself shipped one for years with no
 * effect. They only mean anything when Git Updater is actually installed, so always
 * check hozio_git_update_manager_active() first; otherwise we would decline to patch
 * plugins that nothing else is updating.
 *
 * @param string $plugin_file Plugin file relative to the plugins directory.
 * @return bool
 */
function hozio_plugin_managed_by_git_updater( $plugin_file ) {
    $path = WP_PLUGIN_DIR . '/' . $plugin_file;
    if ( ! file_exists( $path ) ) {
        return false;
    }

    $headers = get_file_data( $path, array(
        'github'    => 'GitHub Plugin URI',
        'gitlab'    => 'GitLab Plugin URI',
        'bitbucket' => 'Bitbucket Plugin URI',
        'gitea'     => 'Gitea Plugin URI',
        'gist'      => 'Gist Plugin URI',
    ) );

    foreach ( $headers as $value ) {
        if ( trim( (string) $value ) !== '' ) {
            return true;
        }
    }

    return false;
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

    // ── Another updater owns this plugin ─────────────────────────────────────
    // If Git Updater is running the site, leave the plugins it manages alone. Two
    // updaters writing the same directory is how you get a half-installed plugin, and
    // Git Updater is frequently pointed at a branch rather than a release — installing
    // "the newest version" over that can throw away exactly what the site is meant to
    // be running. Everything Git Updater does NOT manage still gets patched normally,
    // which is the whole point of doing this per-plugin instead of standing down
    // across the site.
    if ( $file !== '' && hozio_git_update_manager_active() && hozio_plugin_managed_by_git_updater( $item->plugin ) ) {
        if ( function_exists( 'hozio_audit_log' ) ) {
            hozio_audit_log(
                sprintf( 'Skipped %s — Git Updater manages this plugin', $slug !== '' ? $slug : $file ),
                'AutoUpdate'
            );
        }
        return false;
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
        // Flags a site where some plugins are deliberately left to another updater, so
        // the Hub doesn't report them as permanently behind.
        'git_updater'         => hozio_git_update_manager_active(),
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
 * Arm the crash guard for an update run.
 *
 * WordPress puts the site into maintenance mode (a .maintenance file in the web root)
 * for the duration of an update and removes it when finished. If PHP is killed
 * mid-update — max_execution_time on a host that ignores set_time_limit(), a memory
 * limit, a 502 — that file is left behind and THE SITE STAYS OFFLINE showing "Briefly
 * unavailable for scheduled maintenance". Core self-expires it after 10 minutes, but
 * ten minutes of downtime on a client site is not acceptable, and the stranded
 * auto_updater lock then blocks every further attempt for a full hour.
 *
 * A shutdown handler runs even on timeout and fatal error, so the cleanup lives there.
 * Armed for the cron run as well as the button: unattended is the case where nobody is
 * watching the site to notice it went down.
 */
function hozio_arm_update_crash_guard() {
    // Snapshot what is switched on right now, so anything the run turns off can be
    // turned back on. See hozio_restore_deactivated_plugins() for why that happens.
    $GLOBALS['hozio_active_plugins_before'] = (array) get_option( 'active_plugins', array() );

    if ( isset( $GLOBALS['hozio_update_crash_guard_armed'] ) ) {
        $GLOBALS['hozio_update_run_active'] = true; // Already registered; just re-arm.
        return;
    }
    $GLOBALS['hozio_update_crash_guard_armed'] = true;
    $GLOBALS['hozio_update_run_active']        = true;

    register_shutdown_function( function () {
        if ( empty( $GLOBALS['hozio_update_run_active'] ) ) {
            return; // Finished normally; nothing to undo.
        }

        // Get the site back online first — this is the visitor-facing symptom.
        $maintenance = ABSPATH . '.maintenance';
        if ( file_exists( $maintenance ) ) {
            @unlink( $maintenance );
        }

        if ( class_exists( 'WP_Upgrader' ) && method_exists( 'WP_Upgrader', 'release_lock' ) ) {
            WP_Upgrader::release_lock( 'auto_updater' );
        } else {
            delete_option( 'auto_updater.lock' );
        }

        if ( function_exists( 'hozio_audit_log' ) ) {
            $err = error_get_last();
            hozio_audit_log(
                'Update run ended unexpectedly (timeout or fatal error) — cleared maintenance mode and the updater lock so the site stays online'
                    . ( $err && ! empty( $err['message'] ) ? '. Last error: ' . $err['message'] : '' ),
                'AutoUpdate'
            );
        }

        // A run killed partway through can leave a plugin deactivated with its files
        // already swapped. Put it back.
        hozio_restore_deactivated_plugins();
    } );
}

/**
 * Stand the crash guard down after a run that reached its own end.
 *
 * Also clears maintenance mode defensively: whatever happened during the run, a site
 * that is finished updating must never be left showing the maintenance page.
 */
function hozio_disarm_update_crash_guard() {
    $GLOBALS['hozio_update_run_active'] = false;

    if ( file_exists( ABSPATH . '.maintenance' ) ) {
        @unlink( ABSPATH . '.maintenance' );
    }

    hozio_restore_deactivated_plugins();
}

/**
 * Switch back on any plugin that was active before the run and isn't now.
 *
 * The safety net for the deactivation problem, kept even though the run now declares
 * cron context (which stops core deactivating anything in the first place). Two cases
 * it still covers: a run killed after core deactivated a plugin but before the files
 * finished swapping, and any other code on the site that deactivates during an upgrade.
 *
 * Reactivation is SILENT — no activation hooks. The deactivation was silent too, so the
 * plugin never ran its deactivation routine and its stored state is untouched; firing
 * activation hooks on a plugin that never really deactivated could re-run installers.
 *
 * Safe against reactivating something genuinely broken: activate_plugin() validates the
 * plugin first and returns WP_Error if its files are missing or unreadable. WordPress's
 * own fatal-error protection works through wp_paused_plugins, not active_plugins, so
 * this cannot fight it or resurrect a plugin core has paused for crashing.
 *
 * @return int How many plugins were switched back on.
 */
function hozio_restore_deactivated_plugins() {
    if ( empty( $GLOBALS['hozio_active_plugins_before'] ) ) {
        return 0;
    }

    $before = (array) $GLOBALS['hozio_active_plugins_before'];
    $now    = (array) get_option( 'active_plugins', array() );
    $lost   = array_diff( $before, $now );

    if ( empty( $lost ) ) {
        return 0;
    }

    if ( ! function_exists( 'activate_plugin' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $restored = 0;
    foreach ( $lost as $plugin_file ) {
        if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
            if ( function_exists( 'hozio_audit_log' ) ) {
                hozio_audit_log(
                    sprintf( 'Cannot reactivate %s — its files are missing after the update. Reinstall it.', $plugin_file ),
                    'AutoUpdate'
                );
            }
            continue;
        }

        $result = activate_plugin( $plugin_file, '', false, true );

        if ( function_exists( 'hozio_audit_log' ) ) {
            if ( is_wp_error( $result ) ) {
                hozio_audit_log(
                    sprintf( 'Failed to reactivate %s after the update: %s', $plugin_file, $result->get_error_message() ),
                    'AutoUpdate'
                );
            } else {
                hozio_audit_log(
                    sprintf( 'Reactivated %s — WordPress deactivated it during the update', $plugin_file ),
                    'AutoUpdate'
                );
            }
        }

        if ( ! is_wp_error( $result ) ) {
            $restored++;
        }
    }

    // Don't repeat the work if this runs twice (normal path, then shutdown).
    $GLOBALS['hozio_active_plugins_before'] = (array) get_option( 'active_plugins', array() );

    return $restored;
}

// Wrap the scheduled run too. Core registers wp_maybe_auto_update() on this action at
// the default priority, so arming before it and disarming after brackets the whole run.
add_action( 'wp_maybe_auto_update', 'hozio_arm_update_crash_guard', 1 );
add_action( 'wp_maybe_auto_update', 'hozio_disarm_update_crash_guard', PHP_INT_MAX );

/**
 * Break down what WordPress currently has pending for this site.
 *
 * "Pending" is whatever core's own update check found. "Eligible" is the part this
 * plugin would actually install — majors and exclusions are declined by policy, so
 * counting them as outstanding work makes a fully-patched site look behind forever.
 *
 * @return array{pending:int,eligible:int,skipped_major:int,excluded:int}
 */
function hozio_count_pending_updates() {
    $out = array( 'pending' => 0, 'eligible' => 0, 'skipped_major' => 0, 'excluded' => 0 );

    $transient = get_site_transient( 'update_plugins' );
    if ( ! $transient || empty( $transient->response ) || ! is_array( $transient->response ) ) {
        return $out;
    }

    if ( ! function_exists( 'get_plugins' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $installed  = get_plugins();
    $exclusions = hozio_auto_update_exclusions();

    foreach ( $transient->response as $file => $info ) {
        $out['pending']++;

        $folder = strpos( $file, '/' ) !== false ? strtok( $file, '/' ) : '';
        if ( isset( $exclusions[ strtolower( $file ) ] ) || ( $folder && isset( $exclusions[ strtolower( $folder ) ] ) ) ) {
            $out['excluded']++;
            continue;
        }

        $current = isset( $installed[ $file ]['Version'] ) ? $installed[ $file ]['Version'] : '';
        if ( $current !== '' && isset( $info->new_version ) ) {
            if ( (int) strtok( ltrim( (string) $info->new_version, 'vV' ), '.' ) > (int) strtok( ltrim( $current, 'vV' ), '.' ) ) {
                $out['skipped_major']++;
                continue;
            }
        }

        $out['eligible']++;
    }

    return $out;
}

/**
 * Run the plugin auto-updater immediately, instead of waiting for the twice-daily
 * cron. Same code path WordPress uses on its own schedule — this just stops waiting.
 *
 * Exists because WP-cron is visitor-triggered and can drift on quiet sites, and
 * because not every host has WP-CLI available for a manual nudge.
 *
 * @param int $max Cap for THIS run only (0 = use the normal per-run cap). The
 *                 settings-page button passes 1 and repeats, so a host with a tight
 *                 max_execution_time never has to finish several updates in one request.
 * @return array{updated:int,failed:int,messages:string[]}
 */
function hozio_run_plugin_auto_updates_now( $max = 0 ) {
    if ( ! function_exists( 'wp_maybe_auto_update' ) ) {
        require_once ABSPATH . 'wp-admin/includes/update.php';
    }
    if ( ! function_exists( 'wp_clean_plugins_cache' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    @set_time_limit( 300 );

    hozio_arm_update_crash_guard();

    // ── Run as though this were the scheduled cron job ───────────────────────
    // THIS IS WHAT WAS DEACTIVATING ELEMENTOR.
    //
    // Core's Plugin_Upgrader::upgrade() hooks deactivate_plugin_before_upgrade() onto
    // upgrader_pre_install, which SILENTLY DEACTIVATES a plugin before replacing its
    // files — and nothing in core ever switches it back on. Core's own comment says it
    // all: "When in cron (background updates) don't deactivate the plugin, as we require
    // a browser to reactivate it." The browser update screens do that reactivation
    // themselves; the automatic path is exempted from the deactivation entirely.
    //
    // This button is an AJAX request, so wp_doing_cron() was false and core took the
    // deactivate branch — switching off every plugin it updated. A big plugin like
    // Elementor is simply where it got noticed first.
    //
    // Declaring cron context puts us on exactly the same path as the twice-daily run,
    // which is what this button has always claimed to be. It also lets core manage
    // maintenance mode around the file swap properly (active_before/active_after are
    // likewise cron-only). Uses the wp_doing_cron filter rather than defining DOING_CRON
    // so it can be lifted again afterwards instead of poisoning the rest of the request.
    $as_cron = function () { return true; };
    add_filter( 'wp_doing_cron', $as_cron, PHP_INT_MAX );

    // Keep this run to plugins only. wp_maybe_auto_update() would otherwise also
    // attempt core, theme, and translation updates — a core update is a large
    // download that makes a timeout far more likely, and the button says plugins.
    $deny = function () { return false; };
    add_filter( 'auto_update_core', $deny, PHP_INT_MAX );
    add_filter( 'auto_update_theme', $deny, PHP_INT_MAX );
    add_filter( 'auto_update_translation', $deny, PHP_INT_MAX );

    // Tighter cap for this run only, when the caller asked for one.
    $cap = null;
    $max = (int) $max;
    if ( $max > 0 ) {
        $cap = function () use ( $max ) { return $max; };
        add_filter( 'hozio_max_plugin_auto_updates_per_run', $cap, PHP_INT_MAX );
    }

    // Release a stale auto-updater lock.
    //
    // WP_Automatic_Updater::run() calls WP_Upgrader::create_lock('auto_updater') and
    // returns SILENTLY if a lock is already held — no error, no results, nothing logged.
    // The lock is only cleared at the end of a successful run, so any run that timed out,
    // fatalled, or was interrupted strands it for a full hour, during which every further
    // attempt does nothing and looks like "there is simply nothing to update".
    //
    // This is the manual, admin-initiated path: the operator is explicitly asking for a
    // run now, so clearing a leftover lock is the expected behaviour. The scheduled cron
    // path is untouched and still honours the lock, which is what stops concurrent runs.
    if ( class_exists( 'WP_Upgrader' ) && method_exists( 'WP_Upgrader', 'release_lock' ) ) {
        WP_Upgrader::release_lock( 'auto_updater' );
    } else {
        delete_option( 'auto_updater.lock' );
    }

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
    remove_filter( 'wp_doing_cron', $as_cron, PHP_INT_MAX );
    remove_filter( 'auto_update_core', $deny, PHP_INT_MAX );
    remove_filter( 'auto_update_theme', $deny, PHP_INT_MAX );
    remove_filter( 'auto_update_translation', $deny, PHP_INT_MAX );
    if ( $cap ) {
        remove_filter( 'hozio_max_plugin_auto_updates_per_run', $cap, PHP_INT_MAX );
    }
    unset( $GLOBALS['hozio_running_updates_now'] );

    hozio_disarm_update_crash_guard();

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

    // ONE plugin per request, and the browser calls back for the next one.
    //
    // Installing several in a single request is what took a site down: shared hosts cap
    // max_execution_time and ignore set_time_limit(), so a batch gets killed partway
    // through with the .maintenance file still in place. One update per request finishes
    // inside any sane limit, and a kill can only ever cost one plugin instead of the batch.
    $batch  = max( 1, (int) apply_filters( 'hozio_manual_update_batch_size', 1 ) );
    $result = hozio_run_plugin_auto_updates_now( $batch );

    $counts    = hozio_count_pending_updates();
    $remaining = (int) $counts['eligible'];

    if ( $result['updated'] === 0 && $result['failed'] === 0 ) {
        // Nothing installed. Separate "there was nothing to do" from "something stopped
        // the run", because those need completely different responses from the operator.
        if ( $counts['pending'] > 0 ) {
            if ( $remaining <= 0 ) {
                wp_send_json_success( array(
                    'summary'   => sprintf(
                        'Nothing eligible to install: of %d pending, %d are major-version updates (skipped by design) and %d are excluded. Install majors manually alongside any paid add-on.',
                        $counts['pending'], $counts['skipped_major'], $counts['excluded']
                    ),
                    'remaining' => 0,
                    'installed' => 0,
                ) );
            }

            wp_send_json_error( sprintf(
                '%d update(s) pending, %d eligible, but none installed. WordPress declined them — most often multisite (only the main site auto-updates), a stale lock, or a permissions problem in wp-content/plugins. Check the audit log.',
                $counts['pending'], $remaining
            ) );
        }

        wp_send_json_success( array(
            'summary'   => 'Nothing to update — all plugins are already current.',
            'remaining' => 0,
            'installed' => 0,
        ) );
    }

    wp_send_json_success( array(
        'summary'   => implode( ' · ', $result['messages'] ),
        'installed' => (int) $result['updated'],
        'failed'    => (int) $result['failed'],
        'remaining' => $remaining,
    ) );
} );
