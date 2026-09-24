<?php
/**
 * Hozio Pro Logger
 *
 * Two logs, both kept in the site's database:
 *
 *   AUDIT log  ({prefix}hozio_audit_log) — lifecycle, security, Hub and update events.
 *              Always on. Written with hozio_audit_log().
 *   DEBUG log  ({prefix}hozio_debug_log) — troubleshooting detail, written with
 *              hozio_log() and only while debug logging is switched on.
 *
 * To enable debug logging, add this to wp-config.php:
 *     define('HOZIO_DEBUG', true);
 * or switch it on in Hozio Pro Settings → Debug & Logging.
 *
 * To disable logging (default):
 *     define('HOZIO_DEBUG', false);
 *     or simply don't define it
 *
 * WHY THE DATABASE AND NOT A FILE (4.20.9)
 *
 * Up to 4.20.8 both logs were plain files in wp-content/ — hozio-audit.log and
 * hozio-debug.log. wp-content is web-servable, and on Nginx hosts (Pressable included)
 * nothing stopped a stranger downloading them: the audit log held admin usernames and
 * user IDs, the full plugin list with versions, failed updates with their error messages,
 * and Hub activity. That is exactly the reconnaissance an attacker uses to pick a target.
 * An .htaccess deny does nothing on Nginx. The database is never web-servable on any
 * host, so the logs live there now, one small table each.
 *
 * THE ONE RULE: if the table is missing or a write fails, the entry is DROPPED. It never
 * falls back to a file. A lost log line is an inconvenience; a public one is a leak.
 *
 * Both tables keep the newest HOZIO_LOG_MAX_ROWS entries (the database equivalent of the
 * old 500 KB file cap) and each message is capped at HOZIO_LOG_MAX_MESSAGE bytes.
 * Times are stored in UTC.
 */

if (!defined('ABSPATH')) exit;

// Bump when the table definition changes — the next request re-runs dbDelta().
define('HOZIO_LOG_SCHEMA', '1');

// Rows kept per log. Oldest are pruned.
define('HOZIO_LOG_MAX_ROWS', 5000);

// Bytes kept per message. print_r() dumps in the debug log are the only thing near it.
define('HOZIO_LOG_MAX_MESSAGE', 4000);

/**
 * Check if Hozio debug logging is enabled
 *
 * @return bool
 */
function hozio_debug_enabled() {
    // Check wp-config.php constant first (takes priority)
    if (defined('HOZIO_DEBUG')) {
        return HOZIO_DEBUG === true;
    }
    // Fall back to database option (from Settings page)
    return get_option('hozio_debug_enabled', '0') === '1';
}

/**
 * Log a message to the Hozio debug log
 *
 * No-op unless debug logging is enabled.
 *
 * @param mixed $message String message or data to log
 * @param string $context Optional context label (e.g., 'CountyQuery', 'Permalink')
 * @return void
 */
function hozio_log($message, $context = '') {
    // Only log if HOZIO_DEBUG is enabled
    if (!hozio_debug_enabled()) {
        return;
    }

    hozio_log_write('debug', $context, $message);
}

/**
 * Log a critical security/lifecycle event (always logs, regardless of debug mode)
 *
 * Records plugin deactivation attempts, update results, Hub commands, rollbacks and
 * similar events. Kept in the private audit table; the newest HOZIO_LOG_MAX_ROWS
 * entries are retained.
 *
 * @param string $message Description of the event
 * @param string $context Category label (e.g., 'SelfProtect', 'Updater')
 * @return void
 */
function hozio_audit_log($message, $context = 'Audit') {
    hozio_log_write('audit', $context, $message);
}

/**
 * Log a message to the browser console (only when HOZIO_DEBUG is enabled)
 * This queues the data to be output in wp_footer
 *
 * @param mixed $data Data to output to console
 * @param string $label Optional label for the console output
 * @return void
 */
function hozio_console_log($data, $label = 'Hozio Debug') {
    // Only output if HOZIO_DEBUG is enabled
    if (!hozio_debug_enabled()) {
        return;
    }

    add_action('wp_footer', function() use ($data, $label) {
        echo '<script>';
        echo 'console.log("=== ' . esc_js($label) . ' ===");';
        echo 'console.log(' . json_encode($data, JSON_PRETTY_PRINT) . ');';
        echo '</script>';
    }, 9999);
}

/**
 * Clear the Hozio debug log
 *
 * Only the debug log. The audit log has no clear action by design.
 *
 * @return bool True on success, false on failure
 */
function hozio_clear_log() {
    return hozio_log_clear('debug');
}

/**
 * Where the debug log is kept.
 *
 * Up to 4.20.8 this returned the path of wp-content/hozio-debug.log. The log is a
 * database table now, so this returns the table's name. Kept under its old name so
 * older callers keep working; nothing may treat the result as a file path.
 *
 * @return string
 */
function hozio_get_log_path() {
    return hozio_log_table('debug');
}

/**
 * How big the debug log is, for display.
 *
 * @return string e.g. "0 entries", "1 entry", "1,204 entries"
 */
function hozio_get_log_size() {
    return hozio_log_count_label(hozio_log_count('debug'));
}

/**
 * "1 entry" / "1,204 entries".
 *
 * @param int $count
 * @return string
 */
function hozio_log_count_label($count) {
    $count = (int) $count;
    return $count === 1 ? '1 entry' : number_format_i18n($count) . ' entries';
}

// ─── Storage ─────────────────────────────────────────────────────────────────

/**
 * Table name for a log.
 *
 * @param string $channel 'audit' or 'debug'
 * @return string
 */
function hozio_log_table($channel = 'audit') {
    global $wpdb;
    return $wpdb->prefix . ($channel === 'debug' ? 'hozio_debug_log' : 'hozio_audit_log');
}

/**
 * Does the table for this log exist?
 *
 * Checked once per request per table (the answer is cached), because the debug log can
 * be written many times in one page view.
 *
 * @param string $channel 'audit' or 'debug'
 * @param bool   $refresh Ignore the cached answer (used straight after creating tables).
 * @return bool
 */
function hozio_log_table_ready($channel = 'audit', $refresh = false) {
    static $ready = array();
    global $wpdb;

    if (!isset($wpdb) || !is_object($wpdb)) {
        return false;
    }

    $table = hozio_log_table($channel);
    if ($refresh) {
        unset($ready[$table]);
    }

    if (!isset($ready[$table])) {
        $suppress = $wpdb->suppress_errors(true);
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        $wpdb->suppress_errors($suppress);
        // Case-insensitive: hosts with lower_case_table_names report the name in lower case.
        $ready[$table] = is_string($found) && strtolower($found) === strtolower($table);
    }

    return $ready[$table];
}

/**
 * Create (or bring up to date) both log tables.
 *
 * Same approach as the wp_hozio_leads table: dbDelta, then verify the table really exists
 * rather than trusting dbDelta's return value.
 *
 * @return bool True when both tables exist afterwards.
 */
function hozio_log_install() {
    global $wpdb;

    if (!function_exists('dbDelta')) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $charset  = $wpdb->get_charset_collate();
    $suppress = $wpdb->suppress_errors(true);

    foreach (array('audit', 'debug') as $channel) {
        $table = hozio_log_table($channel);
        // dbDelta is fussy: one column per line, two spaces after PRIMARY KEY.
        dbDelta("CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  context varchar(64) NOT NULL DEFAULT '',
  message text NOT NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at)
) {$charset};");
    }

    $wpdb->suppress_errors($suppress);

    $audit_ok = hozio_log_table_ready('audit', true);
    $debug_ok = hozio_log_table_ready('debug', true);

    return $audit_ok && $debug_ok;
}

/**
 * Create the tables when needed and clear away the old public log files.
 *
 * Runs early on every request (init, priority 1) but is cheap once done: one autoloaded
 * option comparison and two file_exists() checks.
 *
 * It keeps its own schema-version option instead of riding on hozio_last_flushed_version
 * for two reasons. The version routine runs at init:99, and the rest of the plugin starts
 * logging well before then. And the file sweep must NOT be once-per-version: a site that is
 * rolled back to 4.20.8 or earlier starts writing the public files again, and when it comes
 * forward to this version the version number alone would say "already done".
 *
 * A failed table creation is retried at most once an hour, so a database user without
 * CREATE rights doesn't pay for dbDelta on every page view.
 *
 * @return void
 */
function hozio_log_maybe_upgrade() {
    if (function_exists('wp_installing') && wp_installing()) {
        return;
    }

    $state = (string) get_option('hozio_log_db_version', '');
    if ($state !== HOZIO_LOG_SCHEMA) {
        $retry_at = 0;
        if (strpos($state, 'failed:') === 0) {
            $retry_at = (int) substr($state, 7) + HOUR_IN_SECONDS;
        }
        if (time() >= $retry_at) {
            $ok = hozio_log_install();
            update_option('hozio_log_db_version', $ok ? HOZIO_LOG_SCHEMA : 'failed:' . time(), true);
        }
    }

    hozio_log_sweep_legacy_files();
}
add_action('init', 'hozio_log_maybe_upgrade', 1);

/**
 * The table disappeared after it was created (a partial migration, a restored backup
 * without it). Clear the "installed" marker so the next request recreates it.
 *
 * @return void
 */
function hozio_log_note_missing_table() {
    static $noted = false;
    if ($noted) {
        return;
    }
    $noted = true;

    if (get_option('hozio_log_db_version', '') === HOZIO_LOG_SCHEMA) {
        update_option('hozio_log_db_version', 'failed:0', true);
    }
}

/**
 * Write one entry. Never throws, never falls back to a file.
 *
 * @param string $channel 'audit' or 'debug'
 * @param string $context Category label
 * @param mixed  $message String, or an array/object (logged with print_r)
 * @return bool True when the row was stored.
 */
function hozio_log_write($channel, $context, $message) {
    global $wpdb;

    if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'insert')) {
        return false;
    }

    $channel = ($channel === 'debug') ? 'debug' : 'audit';

    // A log call usually lands in the middle of someone else's database work — a hook
    // fired between their INSERT and their read of $wpdb->insert_id, say. Put wpdb's
    // "last query" state back afterwards so logging is invisible to the code around it,
    // the way the old file write was.
    $saved   = hozio_log_save_db_state();
    $written = false;

    if (hozio_log_table_ready($channel)) {
        $suppress = $wpdb->suppress_errors(true);

        $written = (bool) $wpdb->insert(
            hozio_log_table($channel),
            array(
                'created_at' => gmdate('Y-m-d H:i:s'),
                'context'    => hozio_log_prepare_context($context),
                'message'    => hozio_log_prepare_message($message),
            ),
            array('%s', '%s', '%s')
        );

        // Prune now and then rather than on every write. Random rather than "every Nth
        // id": on clustered databases ids step by more than one and may never land on
        // a multiple of N.
        if ($written && mt_rand(1, 50) === 1) {
            hozio_log_prune($channel);
        }

        $wpdb->suppress_errors($suppress);
    } else {
        hozio_log_note_missing_table();
    }

    hozio_log_restore_db_state($saved);

    return $written;
}

/**
 * Snapshot wpdb's per-query public state.
 *
 * get_object_vars() from outside the class returns only public properties, so this is
 * safe with db.php drop-ins that declare some of these differently.
 *
 * @return array
 */
function hozio_log_save_db_state() {
    global $wpdb;

    $vars  = get_object_vars($wpdb);
    $state = array();
    foreach (array('insert_id', 'last_error', 'last_query', 'last_result', 'rows_affected', 'num_rows') as $prop) {
        if (array_key_exists($prop, $vars)) {
            $state[$prop] = $vars[$prop];
        }
    }
    return $state;
}

/**
 * @param array $state From hozio_log_save_db_state().
 * @return void
 */
function hozio_log_restore_db_state($state) {
    global $wpdb;

    foreach ((array) $state as $prop => $value) {
        $wpdb->$prop = $value;
    }
}

/**
 * Category label: letters, digits and a little punctuation, 64 characters at most
 * (the column width — wpdb refuses the whole row if a value is too long).
 *
 * @param string $context
 * @return string
 */
function hozio_log_prepare_context($context) {
    $context = preg_replace('/[^A-Za-z0-9_.:\- ]/', '', (string) $context);
    return substr((string) $context, 0, 64);
}

/**
 * Message text, made safe to store.
 *
 * @param mixed $message
 * @return string
 */
function hozio_log_prepare_message($message) {
    // Convert arrays/objects to string
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }

    $message = str_replace("\0", '', (string) $message);

    if (strlen($message) > HOZIO_LOG_MAX_MESSAGE) {
        $message = substr($message, 0, HOZIO_LOG_MAX_MESSAGE);
        // Byte-based cut (no mbstring dependency): drop a multibyte character the cut
        // may have split. Harmlessly drops a whole one when the cut was clean.
        $trimmed = preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/', '', $message);
        $message = (is_string($trimmed) ? $trimmed : $message) . ' [truncated]';
    }

    return hozio_log_text_for_db($message);
}

/**
 * Make text acceptable to the table's character set.
 *
 * wpdb refuses the WHOLE row when a value contains anything the column's charset can't
 * hold, so an error message with one stray byte in it would otherwise vanish.
 *
 * @param string $text
 * @return string
 */
function hozio_log_text_for_db($text) {
    global $wpdb;

    $text = (string) $text;

    if ($text !== '' && !preg_match('//u', $text)) {
        // Not valid UTF-8: keep the entry, lose the unreadable bytes.
        $clean = preg_replace('/[\x80-\xFF]/', '?', $text);
        $text  = is_string($clean) ? $clean : '';
    }

    // A utf8 (3-byte) table can't store 4-byte characters such as emoji.
    if (isset($wpdb->charset) && $wpdb->charset !== 'utf8mb4') {
        $clean = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '?', $text);
        $text  = is_string($clean) ? $clean : $text;
    }

    return $text;
}

/**
 * Keep only the newest HOZIO_LOG_MAX_ROWS rows.
 *
 * @param string $channel
 * @return void
 */
function hozio_log_prune($channel) {
    global $wpdb;

    $table = hozio_log_table($channel);
    $cut   = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$table}` ORDER BY id DESC LIMIT 1 OFFSET %d",
        HOZIO_LOG_MAX_ROWS - 1
    ));

    if ($cut) {
        $wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE id < %d", (int) $cut));
    }
}

/**
 * Number of entries in a log.
 *
 * @param string $channel
 * @return int
 */
function hozio_log_count($channel = 'audit') {
    global $wpdb;

    if (!hozio_log_table_ready($channel)) {
        return 0;
    }

    $table = hozio_log_table($channel);
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
}

/**
 * Read entries, oldest first (like reading the tail of the old file).
 *
 * @param string $channel 'audit' or 'debug'
 * @param array  $args {
 *     @type int    $limit   Newest N entries (1–HOZIO_LOG_MAX_ROWS, default 200).
 *     @type int    $since   Unix timestamp; only entries at or after it. 0 = no limit.
 *     @type string $context Only this category. '' = all.
 * }
 * @return array[] Each: id (int), created_at (UTC, Y-m-d H:i:s), context, message.
 */
function hozio_log_get_entries($channel = 'audit', $args = array()) {
    global $wpdb;

    if (!hozio_log_table_ready($channel)) {
        return array();
    }

    $args = wp_parse_args($args, array('limit' => 200, 'since' => 0, 'context' => ''));

    $limit = (int) $args['limit'];
    if ($limit < 1) {
        $limit = 1;
    } elseif ($limit > HOZIO_LOG_MAX_ROWS) {
        $limit = HOZIO_LOG_MAX_ROWS;
    }

    $table  = hozio_log_table($channel);
    $where  = array();
    $params = array();

    $since = (int) $args['since'];
    if ($since > 0) {
        $where[]  = 'created_at >= %s';
        $params[] = gmdate('Y-m-d H:i:s', $since);
    }

    $context = hozio_log_prepare_context($args['context']);
    if ($context !== '') {
        $where[]  = 'context = %s';
        $params[] = $context;
    }

    $sql = "SELECT id, created_at, context, message FROM `{$table}`"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY id DESC LIMIT %d';
    $params[] = $limit;

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    if (!is_array($rows)) {
        return array();
    }

    $out = array();
    foreach (array_reverse($rows) as $row) {
        $out[] = array(
            'id'         => (int) $row['id'],
            'created_at' => (string) $row['created_at'],
            'context'    => (string) $row['context'],
            'message'    => (string) $row['message'],
        );
    }
    return $out;
}

/**
 * Delete every entry in a log.
 *
 * @param string $channel
 * @return bool
 */
function hozio_log_clear($channel) {
    global $wpdb;

    if (!hozio_log_table_ready($channel)) {
        return true; // Nothing stored, nothing to clear.
    }

    $table = hozio_log_table($channel);
    return $wpdb->query("DELETE FROM `{$table}`") !== false;
}

// ─── Old public log files (4.20.8 and earlier) ──────────────────────────────

/**
 * Paths the logs used to be written to, by channel.
 *
 * @return array
 */
function hozio_log_legacy_files() {
    return array(
        'audit' => WP_CONTENT_DIR . '/hozio-audit.log',
        'debug' => WP_CONTENT_DIR . '/hozio-debug.log',
    );
}

/**
 * Move the newest entries of any old log file into the tables, then delete the file.
 *
 * Deleting is not conditional on the move: if the table can't be created, the file is
 * still removed. Its contents were already public; leaving it would keep them public.
 *
 * If a file can't be deleted (wrong owner, say) it is emptied instead, and an unchanged
 * leftover is retried at most once an hour so it can't cost every request a lock.
 *
 * @return void
 */
function hozio_log_sweep_legacy_files() {
    $todo = array();
    foreach (hozio_log_legacy_files() as $channel => $path) {
        if (@file_exists($path)) {
            $todo[$channel] = $path;
        }
    }
    if (empty($todo)) {
        return;
    }

    // Only reached when an old file is actually present, so this option read is rare.
    $state   = get_option('hozio_log_legacy_state', array());
    $state   = is_array($state) ? $state : array();
    $changed = false;

    foreach ($todo as $channel => $path) {
        $sig  = hozio_log_file_signature($path);
        $seen = isset($state[$path]) && is_array($state[$path]) ? $state[$path] : null;
        // Validated on read: a retry time more than an hour out was never written by this
        // code, so it is ignored rather than allowed to shelter a public file.
        $retry_at = ($seen && isset($seen['retry_at'])) ? (int) $seen['retry_at'] : 0;
        if ($seen && isset($seen['sig']) && $seen['sig'] === $sig
            && $retry_at > time() && $retry_at <= time() + HOUR_IN_SECONDS) {
            unset($todo[$channel]); // Known leftover, unchanged; wait for the retry window.
        }
    }
    if (empty($todo)) {
        return;
    }

    if (!hozio_log_lock()) {
        return; // Another request is doing it right now.
    }

    try {
        foreach ($todo as $channel => $path) {
            $sig  = hozio_log_file_signature($path);
            $seen = isset($state[$path]) && is_array($state[$path]) ? $state[$path] : null;

            // Don't import the same content twice if the file survived an earlier attempt.
            $moved = ($seen && isset($seen['sig']) && $seen['sig'] === $sig)
                ? 0
                : hozio_log_import_legacy_file($path, $channel);

            $name = 'wp-content/' . basename($path);

            if (@unlink($path)) {
                unset($state[$path]);
                $changed = true;
                hozio_audit_log(sprintf(
                    'Moved %s from the old public file %s into the private database log and deleted the file',
                    hozio_log_count_label($moved),
                    $name
                ), 'Logger');
                continue;
            }

            // Could not delete it. Empty it, so at least nothing in it can be read.
            $emptied = (@file_put_contents($path, '') !== false);
            clearstatcache(true, $path);

            $state[$path] = array(
                'sig'      => hozio_log_file_signature($path),
                'retry_at' => time() + HOUR_IN_SECONDS,
            );
            $changed = true;

            // Already reported and nothing new was moved: retry quietly.
            if ($seen && $moved === 0) {
                continue;
            }

            hozio_audit_log(sprintf(
                'Moved %s from the old public file %s but could not delete it; %s',
                hozio_log_count_label($moved),
                $name,
                $emptied
                    ? 'emptied it instead. Delete the empty file by hand.'
                    : 'could not empty it either. Delete it by hand: anyone can download it.'
            ), 'Logger');
        }
    } finally {
        hozio_log_unlock();
    }

    if ($changed) {
        if (empty($state)) {
            delete_option('hozio_log_legacy_state');
        } else {
            update_option('hozio_log_legacy_state', $state, false);
        }
    }
}

/**
 * Cheap "has this file changed" fingerprint.
 *
 * @param string $path
 * @return string
 */
function hozio_log_file_signature($path) {
    clearstatcache(true, $path);
    return (int) @filesize($path) . ':' . (int) @filemtime($path);
}

/**
 * Copy the newest entries of an old log file into a table.
 *
 * Reads at most the last 1 MB (the old debug log had no size cap at all), parses the
 * "[Y-m-d H:i:s] [Context] message" lines, converts their site-local timestamps to UTC
 * and inserts them oldest first, in chunks.
 *
 * @param string $path
 * @param string $channel
 * @return int Entries stored.
 */
function hozio_log_import_legacy_file($path, $channel) {
    global $wpdb;

    if (!hozio_log_table_ready($channel)) {
        return 0;
    }

    $size = (int) @filesize($path);
    if ($size <= 0) {
        return 0;
    }

    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return 0;
    }

    $max     = 1048576;
    $partial = false;
    if ($size > $max) {
        fseek($fh, -$max, SEEK_END);
        $partial = true;
    }
    $raw = stream_get_contents($fh);
    fclose($fh);

    if (!is_string($raw) || $raw === '') {
        return 0;
    }

    // Started mid-file: the first line is almost certainly cut off.
    if ($partial) {
        $nl  = strpos($raw, "\n");
        $raw = ($nl === false) ? '' : substr($raw, $nl + 1);
    }

    $entries = hozio_log_parse_legacy($raw);
    if (count($entries) > HOZIO_LOG_MAX_ROWS) {
        $entries = array_slice($entries, -HOZIO_LOG_MAX_ROWS);
    }
    if (empty($entries)) {
        return 0;
    }

    $table    = hozio_log_table($channel);
    $stored   = 0;
    $saved    = hozio_log_save_db_state();
    $suppress = $wpdb->suppress_errors(true);

    foreach (array_chunk($entries, 100) as $chunk) {
        $rows   = array();
        $params = array();
        foreach ($chunk as $entry) {
            $rows[]   = '(%s, %s, %s)';
            $params[] = $entry['created_at'];
            $params[] = hozio_log_prepare_context($entry['context']);
            $params[] = hozio_log_prepare_message($entry['message']);
        }
        $done = $wpdb->query($wpdb->prepare(
            "INSERT INTO `{$table}` (created_at, context, message) VALUES " . implode(', ', $rows),
            $params
        ));
        if ($done) {
            $stored += (int) $done;
        }
    }

    hozio_log_prune($channel);

    $wpdb->suppress_errors($suppress);
    hozio_log_restore_db_state($saved);

    return $stored;
}

/**
 * Split old log text into entries.
 *
 * A line that doesn't start with a timestamp belongs to the entry above it — print_r()
 * output in the debug log spans many lines. The rotation marker the old audit log wrote
 * ("--- Log trimmed at ... ---") is skipped.
 *
 * @param string $raw
 * @return array[] Each: created_at (UTC), context, message.
 */
function hozio_log_parse_legacy($raw) {
    $entries = array();
    $lines   = preg_split('/\r\n|\n|\r/', (string) $raw);
    if (!is_array($lines)) {
        return $entries;
    }

    foreach ($lines as $line) {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (?:\[([A-Za-z0-9_.:\- ]{1,64})\] )?(.*)$/', $line, $m)) {
            $entries[] = array(
                'created_at' => $m[1],
                'context'    => $m[2],
                'message'    => $m[3],
            );
        } elseif ($line === '' || strpos($line, '--- Log trimmed at ') === 0) {
            continue;
        } elseif (!empty($entries)) {
            $entries[count($entries) - 1]['message'] .= "\n" . $line;
        }
        // A continuation line with no entry above it (the file tail began mid-entry) is dropped.
    }

    // The old files stamped site-local time (current_time()); the tables hold UTC.
    foreach ($entries as $i => $entry) {
        $utc = get_gmt_from_date($entry['created_at'], 'Y-m-d H:i:s');
        $entries[$i]['created_at'] = is_string($utc) && $utc !== '' ? $utc : $entry['created_at'];
    }

    return $entries;
}

/**
 * Short-lived lock so two simultaneous requests don't both import the same file.
 *
 * Same technique as WP_Upgrader::create_lock(): INSERT IGNORE is atomic, so exactly one
 * request gets the row. A lock older than five minutes is treated as abandoned.
 *
 * @return bool True when this request holds the lock.
 */
function hozio_log_lock() {
    global $wpdb;

    $name     = 'hozio_log_migration_lock';
    $suppress = $wpdb->suppress_errors(true);

    $got = $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, 'no')",
        $name,
        time()
    ));

    if (!$got) {
        $since = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s",
            $name
        ));
        if ($since > 0 && $since > time() - 300) {
            $wpdb->suppress_errors($suppress);
            return false;
        }
        // Abandoned. Take it over — only one request can win this UPDATE.
        $got = $wpdb->query($wpdb->prepare(
            "UPDATE `{$wpdb->options}` SET option_value = %s WHERE option_name = %s AND option_value = %s",
            time(),
            $name,
            (string) $since
        ));
    }

    $wpdb->suppress_errors($suppress);
    return (bool) $got;
}

/**
 * @return void
 */
function hozio_log_unlock() {
    global $wpdb;

    $suppress = $wpdb->suppress_errors(true);
    $wpdb->query($wpdb->prepare("DELETE FROM `{$wpdb->options}` WHERE option_name = %s", 'hozio_log_migration_lock'));
    $wpdb->suppress_errors($suppress);
    wp_cache_delete('hozio_log_migration_lock', 'options');
}

/**
 * One entry as a line of text, in site time — the same shape the old files had.
 *
 * @param array $entry From hozio_log_get_entries().
 * @return string
 */
function hozio_log_format_line($entry) {
    $when = get_date_from_gmt($entry['created_at'], 'Y-m-d H:i:s');
    $ctx  = $entry['context'] !== '' ? '[' . $entry['context'] . '] ' : '';
    return '[' . $when . '] ' . $ctx . $entry['message'];
}
