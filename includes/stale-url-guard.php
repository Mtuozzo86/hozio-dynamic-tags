<?php
/**
 * Stale Dev URL Guard
 *
 * A live site should not contain links pointing back at the dev or staging
 * domain it was built on. This finds them and offers a reversible repair.
 *
 * PRODUCTION-ONLY BY DESIGN
 * -------------------------
 * This is the mirror of the Staging Index Guard: it runs ONLY on live sites.
 * On a dev or staging site those URLs are correct, so the guard registers
 * nothing at all there.
 *
 * COST
 * ----
 * Nothing here runs during a page load except one autoloaded option read. The
 * database scan is a LIKE across several tables - genuinely expensive - so it
 * runs on a daily cron or a button press, behind a lock, and never on the
 * front end.
 *
 * @package Hozio Pro
 * @since 4.19.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * What counts as a stale host
 * ---------------------------------------------------------------------- */

/**
 * Host fragments that mean "this URL points at a build environment".
 * Shared with the Staging Index Guard so both agree on what dev looks like.
 */
function hozio_sug_patterns() {
    return apply_filters('hozio_stale_url_patterns', array('hoziodev', 'mystagingwebsite'));
}

/**
 * The host this site should be using, honouring the www preference.
 */
function hozio_sug_target_host() {
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    $mode = get_option('hozio_sug_www_mode', 'home');

    if ('www' === $mode && 0 !== stripos($host, 'www.')) {
        $host = 'www.' . $host;
    } elseif ('nowww' === $mode && 0 === stripos($host, 'www.')) {
        $host = substr($host, 4);
    }

    return $host;
}

function hozio_sug_target_scheme() {
    $scheme = (string) wp_parse_url(home_url(), PHP_URL_SCHEME);
    return ('http' === $scheme) ? 'http' : 'https';
}

/**
 * Rewrite every mention of a dev host inside one string.
 *
 * Deliberately regex-based rather than a str_replace/strtr table, for two
 * reasons that a table gets wrong:
 *
 *  1. CASE. The SQL that finds these rows uses LIKE, which MySQL evaluates
 *     case-insensitively. A case-sensitive replacement therefore matches fewer
 *     rows than the search did, and the repair reports "found 718, rewrote 0".
 *  2. BARE HOSTNAMES. The host does not always sit behind "https://" or "//".
 *     It turns up on its own in stored settings, and a table keyed on
 *     "https://host" never sees those.
 *
 * The bare-host rule carries boundaries on both sides so that a longer hostname
 * which merely contains this one is left alone.
 */
function hozio_sug_rewrite_text($text, $hosts, $new_host, $scheme, &$changed) {
    if (!is_string($text) || '' === $text) {
        return $text;
    }

    $bs = chr(92); // one backslash

    foreach ((array) $hosts as $old_host) {
        $old_host = (string) $old_host;
        if ('' === $old_host || 0 === strcasecmp($old_host, $new_host)) {
            continue;
        }

        $tail  = '(?![A-Za-z0-9\-])';
        $rules = array();

        // Scheme-carrying forms first, so http:// is normalised to the live
        // scheme on the way through. The escaped variants are how JSON-encoded
        // builder data stores a URL.
        foreach (array('https', 'http') as $found_scheme) {
            $rules[] = array(
                preg_quote($found_scheme . ':' . $bs . '/' . $bs . '/' . $old_host, '#') . $tail,
                $scheme . ':' . $bs . '/' . $bs . '/' . $new_host,
            );
            $rules[] = array(
                preg_quote($found_scheme . '://' . $old_host, '#') . $tail,
                $scheme . '://' . $new_host,
            );
        }

        // Protocol-relative.
        $rules[] = array(preg_quote($bs . '/' . $bs . '/' . $old_host, '#') . $tail, $bs . '/' . $bs . '/' . $new_host);
        $rules[] = array(preg_quote('//' . $old_host, '#') . $tail, '//' . $new_host);

        // Anything left: the hostname on its own.
        $rules[] = array('(?<![A-Za-z0-9.\-])' . preg_quote($old_host, '#') . $tail, $new_host);

        foreach ($rules as $rule) {
            list($pattern, $replacement) = $rule;
            // A callback keeps backslashes and dollar signs in $replacement
            // literal, which preg_replace would otherwise interpret.
            $out = preg_replace_callback(
                '#' . $pattern . '#i',
                function () use ($replacement) { return $replacement; },
                $text
            );
            if (null !== $out && $out !== $text) {
                $changed = true;
                $text    = $out;
            }
        }
    }

    return $text;
}

/**
 * Does this string mention any of these hosts at all? Case-insensitive, so it
 * agrees with the SQL that found the row.
 */
function hozio_sug_mentions_host($text, $hosts) {
    if (!is_string($text) || '' === $text) {
        return false;
    }
    foreach ((array) $hosts as $host) {
        if ('' !== $host && false !== stripos($text, (string) $host)) {
            return true;
        }
    }

    return false;
}

/* -------------------------------------------------------------------------
 * Serialisation-safe replacement
 * ---------------------------------------------------------------------- */

/**
 * Replace inside a value without destroying it.
 *
 * WordPress stores a great deal of data as PHP-serialised arrays, where every
 * string carries its own byte length: s:25:"https://18.hoziodev.com". A plain
 * str_replace changes the text but not the 25, and the row becomes unreadable -
 * widgets vanish, theme settings reset, builder pages render blank. So anything
 * serialised is unpacked, walked, and repacked.
 *
 * Returns the new value. $changed is set true if anything was actually replaced.
 */
function hozio_sug_replace_deep($value, $ctx, &$changed, $depth = 0) {
    if ($depth > 20) {
        return $value; // pathological nesting - leave it alone
    }

    if (is_string($value)) {
        if (function_exists('is_serialized') && is_serialized($value, false)) {
            $unpacked = @unserialize($value);
            if (false !== $unpacked || 'b:0;' === $value) {
                $walked = hozio_sug_replace_deep($unpacked, $ctx, $changed, $depth + 1);
                return serialize($walked);
            }
        }
        return hozio_sug_rewrite_text($value, $ctx['hosts'], $ctx['new'], $ctx['scheme'], $changed);
    }

    if (is_array($value)) {
        $out = array();
        foreach ($value as $k => $v) {
            $out[$k] = hozio_sug_replace_deep($v, $ctx, $changed, $depth + 1);
        }
        return $out;
    }

    if (is_object($value)) {
        // An object whose class is not loaded cannot be safely re-serialised.
        if ($value instanceof __PHP_Incomplete_Class) {
            return $value;
        }
        $out = clone $value;
        foreach (get_object_vars($value) as $k => $v) {
            $out->$k = hozio_sug_replace_deep($v, $ctx, $changed, $depth + 1);
        }
        return $out;
    }

    return $value;
}

/**
 * True when a serialised string cannot be safely rewritten, because unpacking
 * it produces an object of a class this request has not loaded.
 */
function hozio_sug_is_unsafe_row($value) {
    if (!is_string($value) || !function_exists('is_serialized') || !is_serialized($value, false)) {
        return false;
    }
    $unpacked = @unserialize($value);
    if (false === $unpacked && 'b:0;' !== $value) {
        return true; // claims to be serialised but is not readable
    }
    return false !== strpos((string) $value, 'O:') && hozio_sug_contains_incomplete($unpacked);
}

function hozio_sug_contains_incomplete($data, $depth = 0) {
    if ($depth > 20) {
        return false;
    }
    if ($data instanceof __PHP_Incomplete_Class) {
        return true;
    }
    if (is_array($data) || is_object($data)) {
        foreach ((array) $data as $v) {
            if (hozio_sug_contains_incomplete($v, $depth + 1)) {
                return true;
            }
        }
    }
    return false;
}

/* -------------------------------------------------------------------------
 * Where to look
 * ---------------------------------------------------------------------- */

function hozio_sug_table_exists($table) {
    global $wpdb;
    static $seen = array();
    if (isset($seen[$table])) {
        return $seen[$table];
    }
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    $seen[$table] = ($found === $table);

    return $seen[$table];
}

function hozio_sug_table_columns($table) {
    global $wpdb;
    static $cache = array();
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $cols = $wpdb->get_col('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    $cache[$table] = is_array($cols) ? $cols : array();

    return $cache[$table];
}

/**
 * Tables and text columns worth searching, with the primary key needed to
 * rewrite a row.
 *
 * post GUIDs are deliberately absent: a GUID is a permanent identifier, not a
 * link, and rewriting it breaks feed readers without fixing anything.
 */
function hozio_sug_targets() {
    global $wpdb;

    $targets = array(
        $wpdb->posts    => array('pk' => 'ID',         'cols' => array('post_content', 'post_excerpt', 'post_title')),
        $wpdb->postmeta => array('pk' => 'meta_id',    'cols' => array('meta_value')),
        $wpdb->options  => array('pk' => 'option_id',  'cols' => array('option_value')),
        $wpdb->termmeta => array('pk' => 'meta_id',    'cols' => array('meta_value')),
        $wpdb->comments => array('pk' => 'comment_ID', 'cols' => array('comment_content')),
    );

    // Plugins that keep their own cache of every page's address. These are the
    // rows that make a canonical tag point at the old domain long after the
    // site has moved.
    $extra = array(
        $wpdb->prefix . 'yoast_indexable' => array(
            'pk'   => 'id',
            'cols' => array('permalink', 'canonical', 'open_graph_url', 'open_graph_image', 'twitter_image'),
        ),
    );
    foreach ($extra as $table => $spec) {
        if (hozio_sug_table_exists($table)) {
            $targets[$table] = $spec;
        }
    }

    $targets = apply_filters('hozio_stale_url_targets', $targets);

    // Drop anything that is not really there, so a missing column cannot turn
    // into a SQL error on someone's site.
    $clean = array();
    foreach ($targets as $table => $spec) {
        if (!hozio_sug_table_exists($table)) {
            continue;
        }
        $present = hozio_sug_table_columns($table);
        $cols    = array_values(array_intersect($spec['cols'], $present));
        if (empty($cols) || !in_array($spec['pk'], $present, true)) {
            continue;
        }
        $clean[$table] = array('pk' => $spec['pk'], 'cols' => $cols);
    }

    return $clean;
}

/**
 * The WHERE fragment matching any stale pattern in any of the given columns.
 */
function hozio_sug_where($cols) {
    global $wpdb;

    $bits = array();
    foreach ($cols as $col) {
        foreach (hozio_sug_patterns() as $pattern) {
            $bits[] = $wpdb->prepare(
                '`' . str_replace('`', '', $col) . '` LIKE %s',
                '%' . $wpdb->esc_like($pattern) . '%'
            );
        }
    }

    return empty($bits) ? '1=0' : '(' . implode(' OR ', $bits) . ')';
}

/* -------------------------------------------------------------------------
 * Scanning
 * ---------------------------------------------------------------------- */

/**
 * Cheapest possible question: is there at least one stale URL anywhere?
 * Stops at the first hit in each table.
 */
function hozio_sug_any_hits() {
    global $wpdb;

    foreach (hozio_sug_targets() as $table => $spec) {
        $sql = 'SELECT 1 FROM `' . str_replace('`', '', $table) . '` WHERE '
             . hozio_sug_where($spec['cols']) . ' LIMIT 1';
        if ($wpdb->get_var($sql)) {
            return true;
        }
    }

    return false;
}

/**
 * Full scan: per-table counts and the distinct dev hostnames involved.
 * Never call this on a page load.
 */
function hozio_sug_scan() {
    global $wpdb;

    if (get_transient('hozio_sug_lock')) {
        $prev = get_option('hozio_sug_report', array());
        return is_array($prev) ? $prev : array();
    }
    set_transient('hozio_sug_lock', 1, 5 * MINUTE_IN_SECONDS);

    $tables = array();
    $hosts  = array();
    $total  = 0;

    foreach (hozio_sug_targets() as $table => $spec) {
        $safe  = str_replace('`', '', $table);
        $where = hozio_sug_where($spec['cols']);

        $count = (int) $wpdb->get_var('SELECT COUNT(*) FROM `' . $safe . '` WHERE ' . $where);
        if ($count > 0) {
            $tables[$table] = $count;
            $total += $count;

            // Sample a few rows to learn which dev hostnames are actually in use.
            $cols = '`' . implode('`, `', array_map(function ($c) { return str_replace('`', '', $c); }, $spec['cols'])) . '`';
            $rows = $wpdb->get_results('SELECT ' . $cols . ' FROM `' . $safe . '` WHERE ' . $where . ' LIMIT 25', ARRAY_A);
            foreach ((array) $rows as $row) {
                foreach ((array) $row as $value) {
                    foreach (hozio_sug_find_hosts((string) $value) as $host) {
                        $hosts[$host] = isset($hosts[$host]) ? $hosts[$host] + 1 : 1;
                    }
                }
            }
        }
    }

    arsort($hosts);

    $report = array(
        'scanned_at' => time(),
        'total_rows' => $total,
        'tables'     => $tables,
        'hosts'      => array_keys($hosts),
        'target'     => hozio_sug_target_scheme() . '://' . hozio_sug_target_host(),
    );

    update_option('hozio_sug_report', $report, false);
    update_option('hozio_sug_found', $total > 0 ? '1' : '0', true);
    delete_transient('hozio_sug_lock');

    return $report;
}

/**
 * Pull dev hostnames out of a blob of text.
 */
function hozio_sug_find_hosts($text) {
    $found = array();
    if ('' === $text) {
        return $found;
    }

    // Hostnames may appear with escaped slashes inside JSON, so normalise first.
    $text = str_replace(chr(92) . '/', '/', $text);

    if (preg_match_all('#//([a-z0-9\-\._]+)#i', $text, $matches)) {
        foreach ($matches[1] as $host) {
            $host = rtrim(strtolower($host), '.');
            foreach (hozio_sug_patterns() as $pattern) {
                if (false !== stripos($host, $pattern)) {
                    $found[$host] = true;
                    break;
                }
            }
        }
    }

    return array_keys($found);
}

function hozio_sug_report() {
    $report = get_option('hozio_sug_report', array());
    return is_array($report) ? $report : array();
}

function hozio_sug_daily_scan() {
    if (!hozio_sug_is_live()) {
        return;
    }
    $report = hozio_sug_scan();
    if (!empty($report['total_rows']) && function_exists('hozio_log')) {
        hozio_log(
            'Live site still contains ' . (int) $report['total_rows'] . ' rows with dev URLs (' . implode(', ', (array) $report['hosts']) . ')',
            'StaleUrlGuard'
        );
    }
}
add_action('hozio_sug_daily_scan', 'hozio_sug_daily_scan');

/* -------------------------------------------------------------------------
 * Environment
 * ---------------------------------------------------------------------- */

/**
 * Live means "not a build environment". Reuses the Staging Index Guard's
 * detection so the two can never disagree about what a dev site is.
 */
function hozio_sug_is_live() {
    if (function_exists('hozio_sig_is_staging')) {
        return !hozio_sig_is_staging();
    }

    $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    foreach (hozio_sug_patterns() as $pattern) {
        if (false !== stripos($host, $pattern)) {
            return false;
        }
    }

    return true;
}

/* -------------------------------------------------------------------------
 * The repair
 * ---------------------------------------------------------------------- */

/**
 * Rewrite every stale URL, or report what a rewrite would do.
 *
 * $mode 'dry' changes nothing and returns counts plus a few samples.
 * $mode 'apply' rewrites, recording which rows it touched so the change can be
 * undone. It never stores the old values - the undo simply runs the same
 * replacement backwards over exactly those rows, which keeps content and any
 * credentials sitting in wp_options out of a backup file.
 */
function hozio_sug_run($mode = 'dry', $old_host = '', $budget = 20) {
    global $wpdb;

    if (!hozio_sug_is_live()) {
        return array('ok' => false, 'message' => 'This is a dev or staging site. Dev URLs belong here, so nothing was changed.');
    }

    $report = hozio_sug_report();
    $hosts  = !empty($report['hosts']) ? (array) $report['hosts'] : array();

    if ('' !== $old_host) {
        $hosts = array($old_host);
    }
    if (empty($hosts)) {
        return array('ok' => false, 'message' => 'No dev hostname has been identified yet. Run a scan first.');
    }

    $new_host = hozio_sug_target_host();
    $scheme   = hozio_sug_target_scheme();

    foreach ($hosts as $host) {
        if (strtolower($host) === strtolower($new_host)) {
            return array('ok' => false, 'message' => 'The old and new hostnames are identical. Nothing to do.');
        }
    }

    $ctx = array('hosts' => $hosts, 'new' => $new_host, 'scheme' => $scheme);

    $started  = microtime(true);
    $touched  = array();
    $skipped  = 0;
    $stuck    = 0;          // matched the search but nothing to rewrite
    $stuck_eg = array();
    $rows_hit = 0;
    $samples  = array();
    $finished = true;

    foreach (hozio_sug_targets() as $table => $spec) {
        $safe  = str_replace('`', '', $table);
        $pk    = str_replace('`', '', $spec['pk']);
        $where = hozio_sug_where($spec['cols']);
        $cols  = array_map(function ($c) { return str_replace('`', '', $c); }, $spec['cols']);

        // Keyset pagination, NOT offset. A row that matches the search but that
        // we cannot rewrite - unreadable serialised data, or a hostname in a
        // form the replacement does not cover - still matches on the next pass.
        // With OFFSET (or a fixed window) those rows come back forever and the
        // repair never terminates. Walking the primary key upwards can only
        // move forwards.
        $after_id = 0;
        while (true) {
            if ((microtime(true) - $started) > $budget) {
                $finished = false;
                break 2;
            }

            $select = '`' . $pk . '`, `' . implode('`, `', $cols) . '`';
            // NOT wrapped in $wpdb->prepare(): $where already came from prepare
            // and contains real % characters from the LIKE patterns, which a
            // second prepare would try to read as placeholders. The cursor is
            // cast to int instead, which is what makes it safe to inline.
            $rows = $wpdb->get_results(
                'SELECT ' . $select . ' FROM `' . $safe . '` WHERE ' . $where
                . ' AND `' . $pk . '` > ' . (int) $after_id
                . ' ORDER BY `' . $pk . '` ASC LIMIT 100',
                ARRAY_A
            );
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $id = $row[$pk];
                if ((int) $id > $after_id) {
                    $after_id = (int) $id;
                }
                foreach ($cols as $col) {
                    $value = isset($row[$col]) ? $row[$col] : null;
                    if (!is_string($value) || '' === $value) {
                        continue;
                    }
                    if (hozio_sug_is_unsafe_row($value)) {
                        $skipped++;
                        continue;
                    }

                    $changed = false;
                    $new     = hozio_sug_replace_deep($value, $ctx, $changed);
                    if (!$changed || $new === $value) {
                        // The row matched the search but produced no rewrite.
                        // Record an example: an invisible mismatch between what
                        // the search finds and what the repair can act on is
                        // exactly how this silently does nothing.
                        if (hozio_sug_mentions_host($value, $hosts)) {
                            $stuck++;
                            if (count($stuck_eg) < 5) {
                                $stuck_eg[] = array(
                                    'table' => $table,
                                    'col'   => $col,
                                    'text'  => hozio_sug_snippet($value, $hosts),
                                );
                            }
                        }
                        continue;
                    }

                    $rows_hit++;

                    if (count($samples) < 5) {
                        $samples[] = array(
                            'table' => $table,
                            'col'   => $col,
                            'before' => hozio_sug_snippet($value, $hosts),
                            'after'  => hozio_sug_snippet($new, array($new_host)),
                        );
                    }

                    if ('apply' === $mode) {
                        $ok = $wpdb->update($table, array($col => $new), array($pk => $id));
                        if (false !== $ok) {
                            $touched[] = array('t' => $table, 'p' => $pk, 'i' => $id, 'c' => $col);
                        }
                    }
                }
            }

        }
    }

    if ('apply' === $mode) {
        update_option('hozio_sug_undo', array(
            'at'       => time(),
            'old_host' => $hosts,
            'new_host' => $new_host,
            'scheme'   => $scheme,
            'rows'     => $touched,
        ), false);

        wp_cache_flush();
        delete_transient('hozio_sug_lock');
        hozio_sug_scan();

        if (function_exists('hozio_log')) {
            hozio_log('Rewrote ' . count($touched) . ' rows from ' . implode(', ', $hosts) . ' to ' . $new_host, 'StaleUrlGuard');
        }
    }

    return array(
        'ok'        => true,
        'mode'      => $mode,
        'rows'      => $rows_hit,
        'applied'   => count($touched),
        'skipped'   => $skipped,
        'stuck'     => $stuck,
        'stuck_eg'  => $stuck_eg,
        'samples'   => $samples,
        'finished'  => $finished,
        'old_hosts' => $hosts,
        'new_host'  => $new_host,
    );
}

/**
 * A short window of text around the first stale host, for the preview.
 */
function hozio_sug_snippet($value, $hosts) {
    $value = (string) $value;
    foreach ((array) $hosts as $host) {
        $pos = stripos($value, $host);
        if (false !== $pos) {
            $start = max(0, $pos - 40);
            return trim(substr($value, $start, 120));
        }
    }

    return trim(substr($value, 0, 120));
}

/**
 * Put back exactly the rows the last repair touched.
 */
function hozio_sug_undo() {
    global $wpdb;

    $undo = get_option('hozio_sug_undo', array());
    if (empty($undo['rows']) || empty($undo['old_host'])) {
        return array(false, 'There is no repair on record to undo.');
    }

    $old_hosts = (array) $undo['old_host'];
    $new_host  = (string) $undo['new_host'];
    $scheme    = !empty($undo['scheme']) ? $undo['scheme'] : 'https';

    // The same rewrite, backwards. Only the recorded rows are touched.
    $ctx = array('hosts' => array($new_host), 'new' => reset($old_hosts), 'scheme' => $scheme);

    $restored = 0;
    foreach ((array) $undo['rows'] as $row) {
        if (empty($row['t']) || empty($row['c'])) {
            continue;
        }
        $table = $row['t'];
        $pk    = str_replace('`', '', $row['p']);
        $col   = str_replace('`', '', $row['c']);

        $value = $wpdb->get_var($wpdb->prepare(
            'SELECT `' . $col . '` FROM `' . str_replace('`', '', $table) . '` WHERE `' . $pk . '` = %s',
            $row['i']
        ));
        if (!is_string($value) || '' === $value) {
            continue;
        }

        $changed = false;
        $back    = hozio_sug_replace_deep($value, $ctx, $changed);
        if ($changed && $back !== $value) {
            if (false !== $wpdb->update($table, array($col => $back), array($pk => $row['i']))) {
                $restored++;
            }
        }
    }

    delete_option('hozio_sug_undo');
    wp_cache_flush();
    delete_transient('hozio_sug_lock');
    hozio_sug_scan();

    if (function_exists('hozio_log')) {
        hozio_log('Undid stale-URL repair on ' . $restored . ' rows', 'StaleUrlGuard');
    }

    return array(true, 'Restored ' . $restored . ' rows to ' . implode(', ', $old_hosts) . '.');
}

/* -------------------------------------------------------------------------
 * Admin actions
 * ---------------------------------------------------------------------- */

function hozio_sug_guard_request($nonce_action) {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.', 'Hozio Pro', array('response' => 403));
    }
    check_admin_referer($nonce_action, 'hozio_sug_nonce');
}

function hozio_sug_notice($failed, $messages) {
    set_transient('hozio_sug_notice_' . get_current_user_id(), array(
        'failed'   => (bool) $failed,
        'messages' => (array) $messages,
    ), 5 * MINUTE_IN_SECONDS);
}

function hozio_sug_bounce($ok) {
    $back = wp_get_referer();
    if (!$back) {
        $back = admin_url('admin.php?page=hozio-plugin-settings');
    }
    wp_safe_redirect(add_query_arg('hozio_sug', $ok ? 'ok' : 'fail', $back));
    exit;
}

function hozio_sug_handle_scan() {
    hozio_sug_guard_request('hozio_sug_scan');
    delete_transient('hozio_sug_lock');
    $report = hozio_sug_scan();
    hozio_sug_notice(false, array(
        'Scan finished: ' . (int) $report['total_rows'] . ' rows contain a dev URL.',
    ));
    hozio_sug_bounce(true);
}

function hozio_sug_handle_preview() {
    hozio_sug_guard_request('hozio_sug_preview');
    $result = hozio_sug_run('dry');
    set_transient('hozio_sug_preview_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
    hozio_sug_notice(empty($result['ok']), array(
        !empty($result['ok'])
            ? 'Preview only - nothing was changed. ' . (int) $result['rows'] . ' rows would be rewritten to ' . $result['new_host'] . '.'
            : $result['message'],
    ));
    hozio_sug_bounce(!empty($result['ok']));
}

function hozio_sug_handle_fix() {
    hozio_sug_guard_request('hozio_sug_fix');

    $result = hozio_sug_run('apply');
    if (empty($result['ok'])) {
        hozio_sug_notice(true, array($result['message']));
        hozio_sug_bounce(false);
    }

    $msg = 'Rewrote ' . (int) $result['applied'] . ' rows to ' . $result['new_host'] . '.';
    if (!empty($result['stuck'])) {
        $msg .= ' ' . (int) $result['stuck'] . ' rows mention the dev domain but contain no rewritable URL - use Preview to see them.';
    }
    if (!$result['finished']) {
        $msg .= ' There is more to do - press Fix again to continue.';
    }
    if ($result['skipped'] > 0) {
        $msg .= ' ' . (int) $result['skipped'] . ' rows were skipped because their stored data could not be safely rewritten.';
    }
    $after = hozio_sug_report();
    $msg .= ' Remaining rows with dev URLs: ' . (int) (isset($after['total_rows']) ? $after['total_rows'] : 0) . '.';

    hozio_sug_notice(false, array($msg));
    hozio_sug_bounce(true);
}

function hozio_sug_handle_undo() {
    hozio_sug_guard_request('hozio_sug_undo');
    list($ok, $msg) = hozio_sug_undo();
    hozio_sug_notice(!$ok, array($msg));
    hozio_sug_bounce($ok);
}

function hozio_sug_url($action) {
    return wp_nonce_url(
        admin_url('admin-post.php?action=hozio_sug_' . $action),
        'hozio_sug_' . $action,
        'hozio_sug_nonce'
    );
}

/* -------------------------------------------------------------------------
 * Banner
 * ---------------------------------------------------------------------- */

function hozio_sug_banner_html($report, $context) {
    $count = (int) (isset($report['total_rows']) ? $report['total_rows'] : 0);
    $hosts = !empty($report['hosts']) ? implode(', ', (array) $report['hosts']) : 'a dev domain';
    $fixed = ('admin' === $context) ? 'position:relative;' : 'position:fixed;top:0;left:0;right:0;';

    ob_start();
    ?>
    <div id="hozio-sug-bar" style="<?php echo esc_attr($fixed); ?>z-index:2147483639;background:#b3140f;border-bottom:3px solid #7d0d0a;color:#fff;padding:12px 16px;font:600 13px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;box-sizing:border-box;box-shadow:0 2px 8px rgba(0,0,0,.25);">
        <div style="max-width:1400px;margin:0 auto;display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;">
            <span style="font-size:15px;font-weight:800;letter-spacing:.03em;white-space:nowrap;">LIVE SITE LINKS TO A DEV DOMAIN</span>
            <span style="font-weight:400;flex:1 1 320px;min-width:0;">
                <?php echo esc_html($count); ?> database rows still point at <?php echo esc_html($hosts); ?>.
                Search engines are being told your pages live there.
            </span>
            <?php if (current_user_can('manage_options')) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hozio-plugin-settings')); ?>"
                   style="background:#fff;color:#7d0d0a;padding:7px 18px;border-radius:3px;text-decoration:none;font-weight:700;white-space:nowrap;">
                    Review and fix
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function hozio_sug_render_admin_banner() {
    $report = hozio_sug_report();
    if (empty($report['total_rows'])) {
        return;
    }
    echo hozio_sug_banner_html($report, 'admin'); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Front end, administrators only - never shown to a visitor on a live site.
 */
function hozio_sug_render_front_banner() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!empty($GLOBALS['hozio_sug_front_done'])) {
        return;
    }
    // The Staging Index Guard owns the top of the screen if it is showing.
    if (!empty($GLOBALS['hozio_sig_front_done'])) {
        return;
    }
    if (wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots()
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (defined('IFRAME_REQUEST') && IFRAME_REQUEST)
        || (function_exists('is_customize_preview') && is_customize_preview())) {
        return;
    }
    if (isset($_GET['elementor-preview']) || (isset($_GET['action']) && 'elementor' === $_GET['action'])) {
        return;
    }

    $report = hozio_sug_report();
    if (empty($report['total_rows'])) {
        return;
    }

    $GLOBALS['hozio_sug_front_done'] = true;

    echo '<style>#hozio-sug-bar{top:0}.admin-bar #hozio-sug-bar{top:32px}@media screen and (max-width:782px){.admin-bar #hozio-sug-bar{top:46px}}</style>';
    echo hozio_sug_banner_html($report, 'front'); // phpcs:ignore WordPress.Security.EscapeOutput
}

function hozio_sug_admin_bar_node($bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    $report = hozio_sug_report();
    if (empty($report['total_rows'])) {
        return;
    }
    $bar->add_node(array(
        'id'    => 'hozio-sug',
        'title' => '<span style="display:inline-block;background:#b3140f;color:#fff;font-weight:700;padding:0 9px;border-radius:3px;">DEV URLS ON LIVE SITE</span>',
        'href'  => admin_url('admin.php?page=hozio-plugin-settings'),
        'meta'  => array('title' => (int) $report['total_rows'] . ' rows still point at a dev domain'),
    ));
}

/* -------------------------------------------------------------------------
 * Bootstrap
 * ---------------------------------------------------------------------- */

function hozio_sug_init() {
    if (is_admin()) {
        add_action('admin_post_hozio_sug_scan', 'hozio_sug_handle_scan');
        add_action('admin_post_hozio_sug_preview', 'hozio_sug_handle_preview');
        add_action('admin_post_hozio_sug_fix', 'hozio_sug_handle_fix');
        add_action('admin_post_hozio_sug_undo', 'hozio_sug_handle_undo');
    }

    if ('1' !== get_option('hozio_sug_enabled', '1')) {
        if (wp_next_scheduled('hozio_sug_daily_scan')) {
            wp_clear_scheduled_hook('hozio_sug_daily_scan');
        }
        return;
    }

    // Dev and staging sites are supposed to contain dev URLs. Nothing runs here.
    if (!hozio_sug_is_live()) {
        if (wp_next_scheduled('hozio_sug_daily_scan')) {
            wp_clear_scheduled_hook('hozio_sug_daily_scan');
        }
        return;
    }

    if (!wp_next_scheduled('hozio_sug_daily_scan')) {
        wp_schedule_event(time() + (2 * HOUR_IN_SECONDS), 'daily', 'hozio_sug_daily_scan');
    }

    // One autoloaded option read. No queries, no HTTP, nothing else on a page
    // load - the banner only appears once a scan has already found something.
    if ('1' !== get_option('hozio_sug_found', '0')) {
        return;
    }

    add_action('in_admin_header', 'hozio_sug_render_admin_banner', 1001);
    add_action('admin_bar_menu', 'hozio_sug_admin_bar_node', 1000);
    add_action('wp_body_open', 'hozio_sug_render_front_banner', 2);
    add_action('wp_footer', 'hozio_sug_render_front_banner', 2);
}
add_action('init', 'hozio_sug_init', 21);

function hozio_sug_deactivate() {
    wp_clear_scheduled_hook('hozio_sug_daily_scan');
    delete_transient('hozio_sug_lock');
}
