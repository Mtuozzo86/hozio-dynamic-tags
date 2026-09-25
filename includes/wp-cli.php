<?php
/**
 * WP-CLI interface: `wp hozio ...`
 *
 * The Hozio Studio Orchestrator's way into Hozio Pro. It reaches sites through the host's
 * WP-CLI API, so these commands are written for a machine reading the output back later:
 *
 *   - every command prints ONE line of compact JSON with stable keys, and --format=json is
 *     the only format (it is also the default);
 *   - failure prints {"ok":false,"error":"<code>","message":"..."} and exits 1;
 *   - nothing prompts, nothing is slow;
 *   - nothing prints a URL, token, key or password, and free text (log lines, reasons)
 *     has email and IP addresses redacted, because command output is kept in the host's
 *     activity log where every collaborator on the site can read it;
 *   - the orchestrator reads the first ~500 characters of a successful command, so
 *     `updates hold` / `release` / `freeze` / `unfreeze` print a short result, and `info`
 *     puts contract and capabilities first (`info --compact` stays well under 500).
 *
 * No logic lives here. Every command calls the same functions the settings page and the
 * Hub use (update-holds.php, hozio-logger.php), so nothing is implemented twice.
 *
 * Only someone with SSH or host-API access to the site can run these, and either of those
 * already means full control of the site — so this adds nothing an attacker can reach.
 * That is why the orchestrator's interface is WP-CLI and not a REST route.
 *
 * The contract number (HOZIO_ORCHESTRATOR_CONTRACT, update-holds.php) is reported by
 * `wp hozio info` and bumps only on a breaking change.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Output helpers shared by every `wp hozio` command.
 */
class Hozio_CLI_Output {

    /**
     * Print one line of JSON.
     *
     * @param mixed $data
     * @return void
     */
    public static function emit( $data ) {
        $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $json ) ) {
            $json = '{"ok":false,"error":"encode_failed","message":"The result could not be encoded as JSON."}';
        }
        WP_CLI::line( $json );
    }

    /**
     * Print a failure and exit 1.
     *
     * @param string $code
     * @param string $message
     * @return void
     */
    public static function fail( $code, $message ) {
        self::emit( array( 'ok' => false, 'error' => (string) $code, 'message' => hozio_log_redact( (string) $message ) ) );
        WP_CLI::halt( 1 );
    }

    /**
     * Print a result from update-holds.php, or its error.
     *
     * @param array|WP_Error $result
     * @return void
     */
    public static function result( $result ) {
        if ( is_wp_error( $result ) ) {
            self::fail( $result->get_error_code(), $result->get_error_message() );
            return;
        }
        self::emit( $result );
    }

    /**
     * Only --format=json exists.
     *
     * @param array $assoc_args
     * @return void
     */
    public static function require_json( $assoc_args ) {
        if ( isset( $assoc_args['format'] ) && $assoc_args['format'] !== 'json' ) {
            self::fail( 'unsupported_format', 'Only --format=json is supported.' );
        }
    }

    /**
     * A --flag=value as a string, or null when absent. A bare --flag (no value) reaches
     * the validators as an empty string, which they reject where a value is required.
     *
     * @param array  $assoc_args
     * @param string $key
     * @return string|null
     */
    public static function arg( $assoc_args, $key ) {
        if ( ! isset( $assoc_args[ $key ] ) ) {
            return null;
        }
        return is_string( $assoc_args[ $key ] ) ? $assoc_args[ $key ] : '';
    }
}

/**
 * Hozio Pro status and private audit log.
 */
class Hozio_CLI_Command {

    /**
     * Report Hozio Pro's state: contract, capabilities, holds, freeze, updates.
     *
     * contract and capabilities are always the first keys.
     *
     * ## OPTIONS
     *
     * [--compact]
     * : Only contract, plugin_version, capabilities, license_status, hub_connected, the
     * freeze and hold counts. Always well under 500 characters.
     *
     * [--format=<format>]
     * : Output format. Only json.
     *
     * ## EXAMPLES
     *
     *     wp hozio info --format=json
     *     wp hozio info --compact
     */
    public function info( $args, $assoc_args ) {
        Hozio_CLI_Output::require_json( $assoc_args );
        Hozio_CLI_Output::emit( hozio_orchestrator_info( ! empty( $assoc_args['compact'] ) ) );
    }

    /**
     * Read the private audit log (newest last). Email and IP addresses are redacted.
     *
     * ## OPTIONS
     *
     * [--lines=<n>]
     * : How many of the newest entries, 1 to 1000. Default 200.
     *
     * [--since=<iso>]
     * : Only entries at or after this time, e.g. 2026-09-24T10:00:00Z.
     *
     * [--category=<category>]
     * : Only this category, e.g. AutoUpdate, Holds, Freeze, Rollback.
     *
     * [--format=<format>]
     * : Output format. Only json.
     *
     * ## EXAMPLES
     *
     *     wp hozio log --lines=50 --category=Holds
     */
    public function log( $args, $assoc_args ) {
        Hozio_CLI_Output::require_json( $assoc_args );

        $lines = Hozio_CLI_Output::arg( $assoc_args, 'lines' );
        if ( $lines === null ) {
            $lines = '200';
        }
        if ( ! preg_match( '/^\d{1,4}$/', $lines ) || (int) $lines < 1 || (int) $lines > 1000 ) {
            Hozio_CLI_Output::fail( 'bad_lines', '--lines must be a number from 1 to 1000.' );
        }

        $since = 0;
        $raw   = Hozio_CLI_Output::arg( $assoc_args, 'since' );
        if ( $raw !== null ) {
            $since = preg_match( '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?(?:Z|[+\-]\d{2}:?\d{2})?)?$/', $raw ) ? strtotime( $raw ) : false;
            if ( ! $since ) {
                Hozio_CLI_Output::fail( 'bad_since', '--since must be an ISO date or time, e.g. 2026-09-24T10:00:00Z.' );
            }
        }

        $category = Hozio_CLI_Output::arg( $assoc_args, 'category' );
        if ( $category !== null && ! preg_match( '/^[A-Za-z0-9_.:\-]{1,64}$/', $category ) ) {
            Hozio_CLI_Output::fail( 'bad_category', '--category may only use letters, digits and _ . : -' );
        }

        $entries = array();
        foreach ( hozio_log_get_entries( 'audit', array(
            'limit'   => (int) $lines,
            'since'   => (int) $since,
            'context' => $category === null ? '' : $category,
        ) ) as $e ) {
            $entries[] = array(
                'id'       => $e['id'],
                'at'       => str_replace( ' ', 'T', $e['created_at'] ) . 'Z',
                'category' => $e['context'],
                'message'  => hozio_log_redact( $e['message'] ),
            );
        }

        Hozio_CLI_Output::emit( array(
            'ok'      => true,
            'count'   => count( $entries ),
            'entries' => $entries,
        ) );
    }
}

/**
 * Update holds and the site-wide update freeze.
 */
class Hozio_CLI_Updates_Command {

    /**
     * Hold a plugin: stop automatic updates to it. Running it again updates the hold.
     *
     * ## OPTIONS
     *
     * [<plugin-file>]
     * : The plugin as folder/file.php (see `wp plugin list --field=file`).
     *
     * [--reason=<text>]
     * : Why. Required. At most 500 characters.
     *
     * [--mode=<mode>]
     * : pin (default) blocks every update; skip blocks only --versions.
     *
     * [--versions=<list>]
     * : Comma-separated versions. Required for skip; for pin, a record of what broke.
     *
     * [--until=<when>]
     * : YYYY-MM-DD, YYYY-MM-DDTHH:MMZ, or +N followed by m, h or d. Default +30d, at most 180 days.
     *
     * [--source=<source>]
     * : orchestrator, hub, wp-cli (default) or admin:<login>.
     *
     * [--ref=<ref>]
     * : Caller reference: letters, digits and _ . : - (100 characters at most).
     *
     * [--format=<format>]
     * : Output format. Only json.
     *
     * ## EXAMPLES
     *
     *     wp hozio updates hold elementor/elementor.php --reason="3.5.1 fatals in wp-admin" --until=+30d --source=orchestrator --ref=fix-123
     */
    public function hold( $args, $assoc_args ) {
        Hozio_CLI_Output::require_json( $assoc_args );
        Hozio_CLI_Output::result( hozio_update_hold_place( isset( $args[0] ) ? $args[0] : '', array(
            'reason'   => Hozio_CLI_Output::arg( $assoc_args, 'reason' ),
            'mode'     => Hozio_CLI_Output::arg( $assoc_args, 'mode' ),
            'versions' => Hozio_CLI_Output::arg( $assoc_args, 'versions' ),
            'until'    => Hozio_CLI_Output::arg( $assoc_args, 'until' ),
            'source'   => Hozio_CLI_Output::arg( $assoc_args, 'source' ),
            'ref'      => Hozio_CLI_Output::arg( $assoc_args, 'ref' ),
        ) ) );
    }

    /**
     * Release a hold. Releasing a plugin that isn't held succeeds with "released":false.
     *
     * ## OPTIONS
     *
     * [<plugin-file>]
     * : The plugin as folder/file.php.
     *
     * [--reason=<text>]
     * : Why. Required.
     *
     * [--source=<source>]
     * : orchestrator, hub, wp-cli (default) or admin:<login>.
     *
     * [--format=<format>]
     * : Output format. Only json.
     *
     * ## EXAMPLES
     *
     *     wp hozio updates release elementor/elementor.php --reason="Fixed in 3.5.2" --source=orchestrator
     */
    public function release( $args, $assoc_args ) {
        Hozio_CLI_Output::require_json( $assoc_args );
        Hozio_CLI_Output::result( hozio_update_hold_release( isset( $args[0] ) ? $args[0] : '', array(
            'reason' => Hozio_CLI_Output::arg( $assoc_args, 'reason' ),
            'source' => Hozio_CLI_Output::arg( $assoc_args, 'source' ),
        ) ) );
    }

    /**
     * List holds (active, and expired within the last 7 days), the invalid count and the freeze.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Only json.
     */
    public function holds( $args, $assoc_args ) {
        Hozio_CLI_Output::require_json( $assoc_args );

        $status = hozio_update_holds_status();
        $list   = array();
        foreach ( $status['holds'] as $h ) {
            $h['reason'] = hozio_log_redact( $h['reason'] );
            $list[]      = $h;
        }
        $freeze           = hozio_update_freeze_status();
        $freeze['reason'] = hozio_log_redact( $freeze['reason'] );

        Hozio_CLI_Output::emit( array(
            'ok'            => true,
            'holds'         => $list,
            'holds_invalid' => $status['invalid'],
            'freeze'        => $freeze,
        ) );
    }

    /**
     * Freeze every automatic update on the site (plugins, themes, core, Hozio Pro) until a
     * time at most 7 days away.
     *
     * ## OPTIONS
     *
     * [--until=<when>]
     * : Required. YYYY-MM-DD, YYYY-MM-DDTHH:MMZ, or +N followed by m, h or d. At most 7 days.
     *
     * [--reason=<text>]
     * : Why. Required.
     *
     * [--ref=<ref>]
     * : Caller reference.
     *
     * [--source=<source>]
     * : orchestrator, hub, wp-cli (default) or admin:<login>.
     *
     * [--format=<format>]
     * : Output format. Only json.
     *
     * ## EXAMPLES
     *
     *     wp hozio updates freeze --until=+4h --reason="Repair in progress" --source=orchestrator --ref=fix-123
     */
    public function freeze( $args, $assoc_args ) {
        Hozio_CLI_Output::require_json( $assoc_args );
        Hozio_CLI_Output::result( hozio_updates_freeze( array(
            'until'  => Hozio_CLI_Output::arg( $assoc_args, 'until' ),
            'reason' => Hozio_CLI_Output::arg( $assoc_args, 'reason' ),
            'ref'    => Hozio_CLI_Output::arg( $assoc_args, 'ref' ),
            'source' => Hozio_CLI_Output::arg( $assoc_args, 'source' ),
        ) ) );
    }

    /**
     * Lift the freeze.
     *
     * ## OPTIONS
     *
     * [--reason=<text>]
     * : Why. Required.
     *
     * [--source=<source>]
     * : orchestrator, hub, wp-cli (default) or admin:<login>.
     *
     * [--format=<format>]
     * : Output format. Only json.
     */
    public function unfreeze( $args, $assoc_args ) {
        Hozio_CLI_Output::require_json( $assoc_args );
        Hozio_CLI_Output::result( hozio_updates_unfreeze( array(
            'reason' => Hozio_CLI_Output::arg( $assoc_args, 'reason' ),
            'source' => Hozio_CLI_Output::arg( $assoc_args, 'source' ),
        ) ) );
    }

    /**
     * The last 20 automatic plugin update results (newest first).
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Only json.
     */
    public function history( $args, $assoc_args ) {
        Hozio_CLI_Output::require_json( $assoc_args );

        $invalid = 0;
        $history = array();
        foreach ( hozio_auto_update_history_read( $invalid ) as $r ) {
            $r['error'] = hozio_log_redact( $r['error'] );
            $history[]  = $r;
        }

        Hozio_CLI_Output::emit( array(
            'ok'      => true,
            'history' => $history,
            'invalid' => $invalid,
        ) );
    }
}

WP_CLI::add_command( 'hozio', 'Hozio_CLI_Command' );
WP_CLI::add_command( 'hozio updates', 'Hozio_CLI_Updates_Command' );
