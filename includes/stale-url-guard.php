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
 * Every hostname in this text that belongs to a build environment.
 *
 * Matches a dotted hostname anywhere, with or WITHOUT a scheme in front of it.
 * An earlier version only looked behind "//", which meant a bare hostname was
 * never discovered, and a site carrying a second build hostname had most of its
 * rows silently ignored.
 */
function hozio_sug_find_hosts($text) {
    $found = array();
    if (!is_string($text) || '' === $text) {
        return $found;
    }

    // Hostnames appear with escaped slashes inside JSON, so normalise first.
    $text = str_replace(chr(92) . '/', '/', $text);

    $re = '#' . hozio_sug_host_start() . '((?:[A-Za-z0-9\-]+\.)+[A-Za-z0-9\-]+)(?![A-Za-z0-9\-])#';
    if (preg_match_all($re, $text, $matches)) {
        foreach ($matches[1] as $host) {
            $host = rtrim(strtolower($host), '.');
            if (hozio_sug_is_dev_host($host)) {
                $found[$host] = true;
            }
        }
    }

    return array_keys($found);
}

/**
 * Is this dotted name actually a build hostname?
 *
 * The pattern has to be a WHOLE label of the name, not a fragment inside one.
 * Without that rule an uploaded file called
 * "screencapture-2-hoziodev-2024-11-13.jpg" reads as a hostname - labels of
 * letters, digits and hyphens ending in ".jpg" - and the repair would rewrite
 * the filename into a domain, breaking the attachment it belongs to.
 *
 * A real build host always carries the pattern as its own label:
 * 18.hoziodev.com, lisacaputorealtor.mystagingwebsite.com.
 */
function hozio_sug_is_dev_host($host) {
    $host = strtolower(trim((string) $host, '.'));
    if ('' === $host || false === strpos($host, '.')) {
        return false;
    }

    $labels = explode('.', $host);

    foreach (hozio_sug_patterns() as $pattern) {
        $pattern = strtolower(trim((string) $pattern, '.'));
        if ('' === $pattern) {
            continue;
        }

        if (false !== strpos($pattern, '.')) {
            // A pattern that is itself dotted matches the name or its suffix.
            if ($host === $pattern || substr($host, -strlen('.' . $pattern)) === '.' . $pattern) {
                return true;
            }
            continue;
        }

        if (in_array($pattern, $labels, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Where a hostname is allowed to BEGIN, as a regex fragment.
 *
 * An escape sequence ends in letters and digits, which are also what hostnames
 * are made of. Elementor stores every dynamic tag's settings URL-encoded, so a
 * link inside one reads "https%3A%2F%2Fsite.mystagingwebsite.com": the "2F" of
 * the last %2F runs straight into the hostname. Treating only non-hostname
 * characters as a boundary made "2fsite.mystagingwebsite.com" the hostname, and
 * the repair then replaced the "2F" along with it. What was left - "%5C%www..."
 * - is not valid encoding, Elementor could no longer decode the tag, and it
 * silently dropped the tag's settings: ACF fields came unhooked from their
 * widgets.
 *
 * So a hostname may start where the previous character cannot be part of one,
 * and never directly after a bare % or backslash (that is the middle of an
 * escape). It MAY start right after a complete escape: %2F, %252F (encoded
 * twice), \u003e, \x2F, and the JSON escapes \n, \r and \t.
 */
function hozio_sug_host_start() {
    return '(?:'
        . '(?<![A-Za-z0-9.%\\\\\-])'
        . '|(?<=%[0-9A-Fa-f]{2})(?!(?<=%25)[0-9A-Fa-f]{2})'
        . '|(?<=%25[0-9A-Fa-f]{2})'
        . '|(?<=\\\\u[0-9A-Fa-f]{4})'
        . '|(?<=\\\\x[0-9A-Fa-f]{2})'
        . '|(?<=\\\\[nrt])'
        . ')';
}

/**
 * Tidy a hostname recorded by an earlier version.
 *
 * Reports and undo records written before 4.20.6 can hold "2fsite.mystagingwebsite.com"
 * - an escape fragment read as part of the name. Putting that back on undo would
 * write a hostname that never existed. The prefix is only removed when what is
 * left is itself a build hostname.
 */
function hozio_sug_clean_host($host) {
    $host = strtolower(trim((string) $host, " \t."));
    if (preg_match('/^(?:25)*2f(.+)$/', $host, $m) && hozio_sug_is_dev_host($m[1])) {
        return $m[1];
    }

    return $host;
}

/**
 * The live hostname with and without "www.", or nothing on a build site.
 */
function hozio_sug_live_bare_host() {
    $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    $bare = preg_replace('/^www\./', '', $host);
    if ('' === $bare || hozio_sug_is_dev_host($bare)) {
        return '';
    }

    return $bare;
}

/**
 * Search strings that find rows an earlier version damaged: the live hostname
 * glued straight onto a bare %. Valid encoding never produces that.
 */
function hozio_sug_damage_needles() {
    $bare = hozio_sug_live_bare_host();
    if ('' === $bare) {
        return array();
    }

    return array('%' . $bare, '%www.' . $bare);
}

/**
 * Regex for the damage versions up to 4.20.5 left behind, or '' when there is
 * no live hostname to look for.
 *
 * The old repair turned "%2F%2Fdev-host" into "%2F%live-host" and
 * "%5C%2F%5C%2Fdev-host" into "%5C%2F%5C%live-host" - it removed exactly the
 * "2F" of the final escape ("252F" when the text was encoded twice). A % that
 * follows an encoded slash or backslash and runs straight into the live
 * hostname can only be that damage: no encoder writes a bare % there. Anything
 * less certain is left alone.
 */
function hozio_sug_damage_pattern() {
    $bare = hozio_sug_live_bare_host();
    if ('' === $bare) {
        return '';
    }

    // The host must end there: "live.com.au" or "live.company" is somebody else.
    return '#%((?:25)?)(2F|5C)%(?=(?:www\.)?' . preg_quote($bare, '#') . '(?![A-Za-z0-9\-]|\.[A-Za-z0-9]))#i';
}

/**
 * Put back the "2F" an earlier version removed. See hozio_sug_damage_pattern().
 */
function hozio_sug_repair_text($text, $pattern, &$changed) {
    if (!is_string($text) || '' === $text || '' === (string) $pattern || false === strpos($text, '%')) {
        return $text;
    }

    $out = preg_replace_callback(
        $pattern,
        function ($m) {
            // Match the case of the escape in front, so the repair reads like
            // the encoder that wrote the rest of the string.
            $last  = substr($m[2], -1);
            $slash = (strtolower($last) === $last) ? '2f' : '2F';
            return $m[0] . $m[1] . $slash;
        },
        $text
    );

    if (null !== $out && $out !== $text) {
        $changed = true;
        return $out;
    }

    return $text;
}

/**
 * Does this text mention a build environment at all?
 *
 * Deliberately tests the PATTERNS, not a list of known hostnames. The SQL that
 * selects rows uses the same patterns, so this cannot disagree with it - which
 * is what let rows be found by the search and then silently ignored by the
 * repair.
 */
function hozio_sug_mentions_dev($text) {
    if (!is_string($text) || '' === $text) {
        return false;
    }
    foreach (hozio_sug_patterns() as $pattern) {
        if (false !== stripos($text, $pattern)) {
            return true;
        }
    }

    return false;
}

/**
 * Rewrite every build hostname in one string.
 *
 * The hostnames are taken from the text itself, not only from the list the scan
 * discovered. Discovery samples a handful of rows, so it can easily miss a
 * second build hostname - and a row holding an undiscovered name would match the
 * search, fail a known-host check, and disappear without being rewritten or
 * reported. Reading each row on its own terms removes that whole class of bug.
 *
 * Matching is case-insensitive to agree with SQL LIKE, and every rule carries
 * boundaries so a longer hostname that merely contains a shorter one is safe.
 */
function hozio_sug_rewrite_text($text, $hosts, $new_host, $scheme, &$changed) {
    if (!is_string($text) || '' === $text) {
        return $text;
    }

    $candidates = array();
    foreach (array_merge((array) $hosts, hozio_sug_find_hosts($text)) as $host) {
        $host = strtolower(trim((string) $host));
        if ('' !== $host && 0 !== strcasecmp($host, $new_host)) {
            $candidates[$host] = true;
        }
    }
    if (empty($candidates)) {
        return $text;
    }

    // Longest first, so a hostname is never partly rewritten by a shorter one
    // that happens to be a substring of it.
    $candidates = array_keys($candidates);
    usort($candidates, function ($a, $b) { return strlen($b) - strlen($a); });

    $bs    = chr(92); // one backslash
    $tail  = '(?![A-Za-z0-9\-])';
    $start = hozio_sug_host_start();

    foreach ($candidates as $old_host) {
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

        // URL-encoded, as Elementor stores dynamic tag settings:
        // https%3A%2F%2Fhost, or https%3A%5C%2F%5C%2Fhost when the value was
        // JSON before it was encoded. Only the scheme and host change; the
        // escapes are kept byte for byte.
        $rules[] = array(
            $start . 'https?(%3A(?:%5C)?%2F(?:%5C)?%2F)' . preg_quote($old_host, '#') . $tail,
            function ($m) use ($scheme, $new_host) { return $scheme . $m[1] . $new_host; },
        );

        // Protocol-relative.
        $rules[] = array(preg_quote($bs . '/' . $bs . '/' . $old_host, '#') . $tail, $bs . '/' . $bs . '/' . $new_host);
        $rules[] = array(preg_quote('//' . $old_host, '#') . $tail, '//' . $new_host);

        // Anything left: the hostname on its own - including after an escape
        // such as %2F, which is where the encoded forms without a scheme land.
        $rules[] = array($start . preg_quote($old_host, '#') . $tail, $new_host);

        foreach ($rules as $rule) {
            list($pattern, $replacement) = $rule;
            // A callback keeps backslashes and dollar signs in $replacement
            // literal, which preg_replace would otherwise interpret.
            $out = preg_replace_callback(
                '#' . $pattern . '#i',
                ($replacement instanceof Closure) ? $replacement : function () use ($replacement) { return $replacement; },
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
        $value = hozio_sug_rewrite_text($value, $ctx['hosts'], $ctx['new'], $ctx['scheme'], $changed);
        if (!empty($ctx['damage'])) {
            $value = hozio_sug_repair_text($value, $ctx['damage'], $changed);
        }
        return $value;
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

    // This guard's own bookkeeping legitimately stores the dev hostname - the
    // scan report records which host it found, and the undo record has to
    // remember what to put back. Rewriting those destroys the undo record, and
    // because every scan rewrites the report, the count could never reach zero:
    // the guard would keep manufacturing rows that match its own search.
    $skip_options = "`option_name` NOT LIKE 'hozio\_sug\_%'"
        . " AND `option_name` NOT LIKE 'hozio\_sig\_%'"
        . " AND `option_name` NOT LIKE 'hozio\_hub\_%'"
        . " AND `option_name` NOT LIKE 'hozio\_auto\_update\_history'"
        . " AND `option_name` NOT LIKE 'hozio\_version\_history'"
        . " AND `option_name` NOT LIKE '\_transient\_hozio\_s%'"
        . " AND `option_name` NOT LIKE '\_transient\_timeout\_hozio\_s%'";

    // 'label' columns identify a row to a human in the preview. A count of
    // leftovers is useless without knowing WHICH option or meta key they are.
    $targets = array(
        $wpdb->posts    => array('pk' => 'ID',         'cols' => array('post_content', 'post_excerpt', 'post_title'), 'label' => array('post_type', 'post_title')),
        $wpdb->postmeta => array('pk' => 'meta_id',    'cols' => array('meta_value'),     'label' => array('post_id', 'meta_key')),
        $wpdb->options  => array('pk' => 'option_id',  'cols' => array('option_value'),   'label' => array('option_name'), 'exclude' => $skip_options),
        $wpdb->termmeta => array('pk' => 'meta_id',    'cols' => array('meta_value'),     'label' => array('term_id', 'meta_key')),
        $wpdb->comments => array('pk' => 'comment_ID', 'cols' => array('comment_content'), 'label' => array('comment_post_ID')),
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
        $labels = isset($spec['label']) ? array_values(array_intersect((array) $spec['label'], $present)) : array();
        $clean[$table] = array(
            'pk'      => $spec['pk'],
            'cols'    => $cols,
            'label'   => $labels,
            'exclude' => isset($spec['exclude']) ? $spec['exclude'] : '',
        );
    }

    return $clean;
}

/**
 * The WHERE fragment matching any stale pattern in any of the given columns.
 */
function hozio_sug_where($cols, $exclude = '') {
    global $wpdb;

    // The damage needles bring back rows an earlier version broke, so the
    // repair can reach them even once no dev hostname is left in the row.
    $needles = array_merge(hozio_sug_patterns(), hozio_sug_damage_needles());

    $bits = array();
    foreach ($cols as $col) {
        foreach ($needles as $pattern) {
            $bits[] = $wpdb->prepare(
                '`' . str_replace('`', '', $col) . '` LIKE %s',
                '%' . $wpdb->esc_like($pattern) . '%'
            );
        }
    }

    if (empty($bits)) {
        return '1=0';
    }

    $where = '(' . implode(' OR ', $bits) . ')';
    if ('' !== $exclude) {
        $where .= ' AND (' . $exclude . ')';
    }

    return $where;
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
             . hozio_sug_where($spec['cols'], isset($spec['exclude']) ? $spec['exclude'] : '') . ' LIMIT 1';
        if ($wpdb->get_var($sql)) {
            return true;
        }
    }

    return false;
}

/**
 * How long one pass may run before it stops and reports itself unfinished.
 *
 * Kept safely under PHP's own limit so a big site never dies mid-request.
 */
function hozio_sug_budget() {
    $max = (int) ini_get('max_execution_time');
    if ($max <= 0) {
        return 25; // no limit (cron / CLI)
    }

    return max(8, min(25, $max - 10));
}

/**
 * Why a row that mentions a dev domain still cannot be rewritten.
 */
function hozio_sug_why_stuck($value) {
    if (false !== stripos((string) $value, 'wp-content/uploads')) {
        return 'the dev name is part of an uploaded file\'s name, not a web address - renaming it would break the file';
    }

    return 'mentions the dev domain, but not as a web address - nothing to rewrite';
}

/**
 * Walk every matching row ONCE: count it, work out whether the repair could
 * rewrite it, and collect the dev hostnames it contains.
 *
 * This used to be three passes over each table - a COUNT, a 200-row sample for
 * hostnames, then a full evaluation walk. On a large site that overran the time
 * budget, and an unfinished pass fell back to assuming every row found was
 * fixable. A site whose only matches were four uploaded FILENAMES containing
 * "hoziodev" was told four rows needed fixing, offered a Fix button that could
 * not do anything, and never went quiet. One pass is both faster and exact.
 *
 * Never call this on a page load.
 */
function hozio_sug_scan($budget = 0) {
    global $wpdb;

    if (get_transient('hozio_sug_lock')) {
        $prev = get_option('hozio_sug_report', array());
        return is_array($prev) ? $prev : array();
    }
    set_transient('hozio_sug_lock', 1, 5 * MINUTE_IN_SECONDS);

    if (0 === $budget) {
        $budget = hozio_sug_budget();
    }

    $new_host = hozio_sug_target_host();
    $scheme   = hozio_sug_target_scheme();
    $needles  = hozio_sug_patterns();
    $damage   = hozio_sug_damage_pattern();
    $ctx      = array('hosts' => array(), 'new' => $new_host, 'scheme' => $scheme, 'damage' => $damage);

    $tables     = array();
    $hosts      = array();
    $leftovers  = array();
    $total      = 0;
    $rewritable = 0;
    $unfixable  = 0;
    $damaged    = 0;
    $finished   = true;
    $started    = microtime(true);

    foreach (hozio_sug_targets() as $table => $spec) {
        if (!$finished) {
            break;
        }

        $safe  = str_replace('`', '', $table);
        $pk    = str_replace('`', '', $spec['pk']);
        $where = hozio_sug_where($spec['cols'], isset($spec['exclude']) ? $spec['exclude'] : '');
        $cols  = array_map(function ($c) { return str_replace('`', '', $c); }, $spec['cols']);
        $lbls  = isset($spec['label']) ? array_map(function ($c) { return str_replace('`', '', $c); }, $spec['label']) : array();
        $fetch = array_values(array_unique(array_merge($cols, $lbls)));

        $seen     = 0;
        $after_id = 0;

        while (true) {
            if ((microtime(true) - $started) > $budget) {
                $finished = false;
                break;
            }

            // Keyset paging: a row we cannot rewrite still matches next time,
            // so the cursor must only ever move forwards.
            $select = '`' . $pk . '`, `' . implode('`, `', $fetch) . '`';
            $rows   = $wpdb->get_results(
                'SELECT ' . $select . ' FROM `' . $safe . '` WHERE ' . $where
                . ' AND `' . $pk . '` > ' . (int) $after_id
                . ' ORDER BY `' . $pk . '` ASC LIMIT 100',
                ARRAY_A
            );
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $id = isset($row[$pk]) ? $row[$pk] : 0;
                if ((int) $id > $after_id) {
                    $after_id = (int) $id;
                }

                $row_fixable  = false;
                $row_damaged  = false;
                $row_relevant = false;
                $eg           = null;

                foreach ($cols as $col) {
                    $value = isset($row[$col]) ? $row[$col] : null;
                    if (!is_string($value) || '' === $value) {
                        continue;
                    }

                    // A damage needle can match text that is not damage at all.
                    // Such a row is neither a dev URL nor broken, so it is not
                    // counted anywhere.
                    $mentions = hozio_sug_mentions_dev($value);
                    $broken   = '' !== $damage && preg_match($damage, $value);
                    if (!$mentions && !$broken) {
                        continue;
                    }
                    $row_relevant = true;
                    if ($broken) {
                        $row_damaged = true;
                    }

                    foreach (hozio_sug_find_hosts($value) as $found_host) {
                        $hosts[$found_host] = isset($hosts[$found_host]) ? $hosts[$found_host] + 1 : 1;
                    }

                    if (hozio_sug_is_unsafe_row($value)) {
                        if (null === $eg) {
                            $eg = array(
                                'col'  => $col,
                                'why'  => 'stored data from a plugin that is no longer installed - cannot be safely rewritten, and nothing reads it',
                                'text' => hozio_sug_snippet($value, $needles),
                            );
                        }
                        continue;
                    }

                    if ($row_fixable) {
                        continue; // already known; still read for hostnames and damage
                    }

                    $changed = false;
                    hozio_sug_replace_deep($value, $ctx, $changed);
                    if ($changed) {
                        $row_fixable = true;
                        continue;
                    }

                    if (null === $eg && $mentions) {
                        $eg = array(
                            'col'  => $col,
                            'why'  => hozio_sug_why_stuck($value),
                            'text' => hozio_sug_snippet($value, $needles),
                        );
                    }
                }

                if (!$row_relevant) {
                    continue;
                }
                $seen++;
                $total++;

                if ($row_fixable) {
                    $rewritable++;
                    if ($row_damaged) {
                        $damaged++;
                    }
                } else {
                    $unfixable++;
                    if (null !== $eg && count($leftovers) < 12) {
                        $leftovers[] = array(
                            'table' => $table,
                            'pk'    => $id,
                            'col'   => $eg['col'],
                            'label' => hozio_sug_row_label($row, $lbls),
                            'why'   => $eg['why'],
                            'text'  => $eg['text'],
                        );
                    }
                }
            }
        }

        if ($seen > 0) {
            $tables[$table] = $seen;
        }
    }

    arsort($hosts);

    $report = array(
        'scanned_at' => time(),
        'total_rows' => $total,
        'rewritable' => $rewritable,
        'unfixable'  => $unfixable,
        'damaged'    => $damaged,
        'leftovers'  => $leftovers,
        'finished'   => $finished,
        'tables'     => $tables,
        'hosts'      => array_keys($hosts),
        'target'     => hozio_sug_target_scheme() . '://' . hozio_sug_target_host(),
    );

    update_option('hozio_sug_report', $report, false);

    // The banner reflects what is KNOWN to need fixing. An unfinished pass is
    // reported in the panel rather than guessed at - assuming unexamined rows
    // are broken is what kept a clean site permanently red.
    update_option('hozio_sug_found', $rewritable > 0 ? '1' : '0', true);

    delete_transient('hozio_sug_lock');

    return $report;
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
    if (!empty($report['rewritable']) && function_exists('hozio_log')) {
        hozio_log(
            'Live site still contains ' . (int) $report['rewritable'] . ' rows with dev URLs (' . implode(', ', (array) $report['hosts']) . ')',
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
function hozio_sug_run($mode = 'dry', $old_host = '', $budget = 0) {
    global $wpdb;

    if (0 === $budget) {
        $budget = hozio_sug_budget();
    }

    if (!hozio_sug_is_live()) {
        return array('ok' => false, 'message' => 'This is a dev or staging site. Dev URLs belong here, so nothing was changed.');
    }

    $report = hozio_sug_report();
    $hosts  = !empty($report['hosts']) ? (array) $report['hosts'] : array();

    if ('' !== $old_host) {
        $hosts = array($old_host);
    }
    // A report written before 4.20.6 can list "2fsite.mystagingwebsite.com".
    $hosts = array_values(array_unique(array_filter(array_map('hozio_sug_clean_host', $hosts))));
    // No bail-out when the scan found no hostname. Each row discovers its own
    // hostnames now, and the rows that hold a dev pattern WITHOUT a hostname
    // still need to be surfaced - refusing to run here would hide exactly the
    // leftovers a site owner needs to see.

    $new_host = hozio_sug_target_host();
    $scheme   = hozio_sug_target_scheme();

    foreach ($hosts as $host) {
        if (strtolower($host) === strtolower($new_host)) {
            return array('ok' => false, 'message' => 'The old and new hostnames are identical. Nothing to do.');
        }
    }

    $damage       = hozio_sug_damage_pattern();
    $ctx          = array('hosts' => $hosts, 'new' => $new_host, 'scheme' => $scheme, 'damage' => $damage);
    $snip_needles = array_merge($hosts, hozio_sug_damage_needles());

    $started   = microtime(true);
    $touched   = array();
    $skipped   = 0;
    $unsafe_eg = array();   // serialised data we refuse to touch
    $stuck     = 0;         // matched the search but nothing to rewrite
    $stuck_eg  = array();
    $rows_hit = 0;
    $samples  = array();
    $finished = true;

    foreach (hozio_sug_targets() as $table => $spec) {
        $safe  = str_replace('`', '', $table);
        $pk    = str_replace('`', '', $spec['pk']);
        $where = hozio_sug_where($spec['cols'], isset($spec['exclude']) ? $spec['exclude'] : '');
        $cols  = array_map(function ($c) { return str_replace('`', '', $c); }, $spec['cols']);
        $lbls  = isset($spec['label']) ? array_map(function ($c) { return str_replace('`', '', $c); }, $spec['label']) : array();
        $fetch = array_values(array_unique(array_merge($cols, $lbls)));

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

            $select = '`' . $pk . '`, `' . implode('`, `', $fetch) . '`';
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
                    // Same test the scan uses: a damage needle alone can match
                    // text that is not damage, and that is none of our business.
                    if (!hozio_sug_mentions_dev($value) && !('' !== $damage && preg_match($damage, $value))) {
                        continue;
                    }
                    if (hozio_sug_is_unsafe_row($value)) {
                        $skipped++;
                        if (count($unsafe_eg) < 5) {
                            $unsafe_eg[] = array(
                                'table' => $table,
                                'pk'    => $id,
                                'col'   => $col,
                                'label' => hozio_sug_row_label($row, $lbls),
                                'text'  => hozio_sug_snippet($value, $hosts),
                            );
                        }
                        continue;
                    }

                    $changed = false;
                    $new     = hozio_sug_replace_deep($value, $ctx, $changed);
                    if (!$changed || $new === $value) {
                        // The row matched the search but produced no rewrite.
                        // Record an example: an invisible mismatch between what
                        // the search finds and what the repair can act on is
                        // exactly how this silently does nothing.
                        if (hozio_sug_mentions_dev($value)) {
                            $stuck++;
                            if (count($stuck_eg) < 5) {
                                $stuck_eg[] = array(
                                    'table' => $table,
                                    'pk'    => $id,
                                    'col'   => $col,
                                    'label' => hozio_sug_row_label($row, $lbls),
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
                            'before' => hozio_sug_snippet($value, $snip_needles),
                            'after'  => hozio_sug_snippet($new, array($new_host)),
                        );
                    }

                    if ('apply' === $mode) {
                        // Remember which hostname THIS row carried. A site can
                        // have more than one build host, and undo has to put
                        // each row back to its own, not to whichever happened
                        // to be first in the list.
                        $row_hosts = hozio_sug_find_hosts($value);
                        $ok = $wpdb->update($table, array($col => $new), array($pk => $id));
                        if (false !== $ok) {
                            $touched[] = array(
                                't' => $table,
                                'p' => $pk,
                                'i' => $id,
                                'c' => $col,
                                'h' => $row_hosts,
                            );
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
        if (!empty($touched)) {
            hozio_sug_clear_builder_cache();
        }
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
        'unsafe_eg' => $unsafe_eg,
        'samples'   => $samples,
        'finished'  => $finished,
        'old_hosts' => $hosts,
        'new_host'  => $new_host,
    );
}

/**
 * Whatever tells a person what this row IS: the option name, the meta key and
 * post it belongs to, and so on.
 */
function hozio_sug_row_label($row, $label_cols) {
    $bits = array();
    foreach ((array) $label_cols as $c) {
        if (isset($row[$c]) && '' !== (string) $row[$c]) {
            $bits[] = $c . ': ' . substr((string) $row[$c], 0, 80);
        }
    }

    return implode(' / ', $bits);
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

    $old_hosts = array_values(array_filter(array_map('hozio_sug_clean_host', (array) $undo['old_host'])));
    $new_host  = (string) $undo['new_host'];
    $scheme    = !empty($undo['scheme']) ? $undo['scheme'] : 'https';

    // The same rewrite, backwards, per row. Only recorded rows are touched.
    $fallback = reset($old_hosts);

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

        // Each row goes back to the hostname it actually had.
        $original = (!empty($row['h']) && is_array($row['h'])) ? hozio_sug_clean_host(reset($row['h'])) : $fallback;
        if ('' === $original) {
            continue;
        }
        $ctx      = array('hosts' => array($new_host), 'new' => $original, 'scheme' => $scheme);

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
    if ($restored > 0) {
        hozio_sug_clear_builder_cache();
    }
    delete_transient('hozio_sug_lock');
    hozio_sug_scan();

    if (function_exists('hozio_log')) {
        hozio_log('Undid stale-URL repair on ' . $restored . ' rows', 'StaleUrlGuard');
    }

    return array(true, 'Restored ' . $restored . ' rows to ' . implode(', ', $old_hosts) . '.');
}

/**
 * Make Elementor rebuild what it generated from the rows just rewritten.
 *
 * Elementor writes each page's CSS to a file and caches rendered elements, both
 * built from _elementor_data. A direct database rewrite does not tell it
 * anything changed, so background images kept loading from the dev domain until
 * someone happened to press "Regenerate CSS & Data". This is that same button.
 */
function hozio_sug_clear_builder_cache() {
    if (!class_exists('\\Elementor\\Plugin') || !isset(\Elementor\Plugin::$instance)) {
        return;
    }
    try {
        $files = \Elementor\Plugin::$instance->files_manager;
        if (is_object($files) && method_exists($files, 'clear_cache')) {
            $files->clear_cache();
        }
    } catch (\Throwable $e) {
        // A cache that could not be cleared is regenerated on the next save.
    }
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
        'Scan finished: ' . (int) $report['rewritable'] . ' rows need fixing.',
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

    $before         = hozio_sug_report();
    $before_damaged = (int) (isset($before['damaged']) ? $before['damaged'] : 0);

    $result = hozio_sug_run('apply');
    if (empty($result['ok'])) {
        hozio_sug_notice(true, array($result['message']));
        hozio_sug_bounce(false);
    }

    $after     = hozio_sug_report();
    $remaining = (int) (isset($after['rewritable']) ? $after['rewritable'] : 0);
    $info      = (int) (isset($after['total_rows']) ? $after['total_rows'] : 0) - $remaining;

    $msg = 'Rewrote ' . (int) $result['applied'] . ' rows to ' . $result['new_host'] . '.';
    if (!empty($before_damaged)) {
        $msg .= ' That includes ' . $before_damaged . ' rows whose links an earlier version had broken - they are repaired.';
    }
    if (!$result['finished']) {
        $msg .= ' There is more to do - press Fix again to continue.';
    }
    $msg .= ' Rows still needing a fix: ' . $remaining . '.';
    if ($info > 0) {
        $msg .= ' (' . $info . ' other rows mention a dev domain but are not links - listed below for information, not counted.)';
    }

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
    $count   = (int) (isset($report['rewritable']) ? $report['rewritable'] : (isset($report['total_rows']) ? $report['total_rows'] : 0));
    $hosts   = !empty($report['hosts']) ? implode(', ', (array) $report['hosts']) : 'a dev domain';
    $damaged = (int) (isset($report['damaged']) ? $report['damaged'] : 0);
    // Sits just BELOW the WordPress toolbar (z-index 99999). The toolbar's
    // drop-down menus are children of it and share its stacking context, so a
    // banner above 99999 covers every menu the moment one opens. High enough to
    // clear ordinary page content, low enough that the toolbar always wins.
    $fixed = ('admin' === $context) ? 'position:relative;' : 'position:fixed;';

    ob_start();
    ?>
    <div id="hozio-sug-bar" style="<?php echo esc_attr($fixed); ?>z-index:99989;background:#b3140f;border-bottom:3px solid #7d0d0a;color:#fff;padding:12px 16px;font:600 13px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;box-sizing:border-box;box-shadow:0 2px 8px rgba(0,0,0,.25);">
        <div style="max-width:1400px;margin:0 auto;display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;">
            <span style="font-size:15px;font-weight:800;letter-spacing:.03em;white-space:nowrap;">LIVE SITE LINKS TO A DEV DOMAIN</span>
            <span style="font-weight:400;flex:1 1 320px;min-width:0;">
                <?php if (empty($report['hosts']) && $damaged > 0) : ?>
                    <?php echo esc_html($damaged); ?> database rows hold links an earlier dev-URL fix broke.
                    Elementor cannot read them, so dynamic content on those pages may be missing.
                <?php else : ?>
                    <?php echo esc_html($count); ?> database rows still point at <?php echo esc_html($hosts); ?>.
                    Search engines are being told your pages live there.
                <?php endif; ?>
            </span>
            <?php if (current_user_can('manage_options')) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=hozio-plugin-settings') . '#hozio-dev-urls'); ?>"
                   style="background:#fff;color:#7d0d0a;padding:7px 18px;border-radius:3px;text-decoration:none;font-weight:700;white-space:nowrap;">
                    Review and fix
                </a>
            <?php endif; ?>
            <button type="button" id="hozio-sug-dismiss" title="Hide until I close the browser"
                    aria-label="Hide this notice until I close the browser"
                    style="background:transparent;border:1px solid rgba(255,255,255,.55);color:#fff;font:700 16px/1 sans-serif;cursor:pointer;padding:6px 10px;border-radius:3px;margin-left:auto;">&times;</button>
        </div>
    </div>
    <script><?php echo hozio_sug_dismiss_js($count); ?></script>
    <?php
    return ob_get_clean();
}

/**
 * The dismiss behaviour: hide this banner for the rest of the browser session.
 *
 * A live site with dev URLs shows this on every single page load, which is a lot
 * of red for something you already know about and intend to fix later.
 *
 * Kept entirely in the browser, in a session cookie that expires when the browser
 * closes. Nothing is stored against the site or the user, and - this is the
 * important part on a cached host - the SERVER never varies its output on that
 * cookie, so no page can be cached in a dismissed state for somebody else. The
 * markup is always sent; the script removes it before the page is painted.
 *
 * The count is part of the cookie, so dismissing what you have seen does not
 * silence a DIFFERENT number later: if a later scan finds more rows, that is news
 * and the banner comes back. The toolbar item stays regardless, so there is
 * always a way back to the panel.
 */
function hozio_sug_dismiss_js($count) {
    $count = (int) $count;

    return "(function(){var K='hozio_sug_hide=',C='" . $count . "',"
        . "b=document.getElementById('hozio-sug-bar');if(!b){return;}"
        . "function g(){var p=document.cookie?document.cookie.split('; '):[];"
        . "for(var i=0;i<p.length;i++){if(p[i].indexOf(K)===0){return p[i].slice(K.length);}}return '';}"
        . "function k(){if(b.parentNode){b.parentNode.removeChild(b);}}"
        . "if(g()===C){k();return;}"
        . "var d=document.getElementById('hozio-sug-dismiss');"
        . "if(d){d.addEventListener('click',function(){"
        . "document.cookie=K+C+'; path=/; SameSite=Lax';k();});}})();";
}

function hozio_sug_render_admin_banner() {
    $report = hozio_sug_report();
    if (empty($report['rewritable'])) {
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
    if (empty($report['rewritable'])) {
        return;
    }

    $GLOBALS['hozio_sug_front_done'] = true;

    // The bar's placement lives here, NOT in the element's inline style. An
    // inline declaration beats any stylesheet rule that is not !important, so
    // an inline "top:0" made every offset below unreachable and the bar sat on
    // top of the WordPress toolbar.
    //
    // The offset is decided in PHP rather than by a body class: a theme that
    // forgets body_class() never gets "admin-bar", and an administrator who has
    // turned the toolbar off in their profile needs no offset at all.
    $bar_offset = is_admin_bar_showing() ? 32 : 0;
    $bar_css    = '#hozio-sug-bar{left:0;right:0;top:' . (int) $bar_offset . 'px}';
    if ($bar_offset) {
        // WordPress makes its own toolbar 46px tall below 782px wide.
        $bar_css .= '@media screen and (max-width:782px){#hozio-sug-bar{top:46px}}';
    }
    echo '<style>' . $bar_css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
    echo hozio_sug_banner_html($report, 'front'); // phpcs:ignore WordPress.Security.EscapeOutput
}

function hozio_sug_admin_bar_node($bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    $report = hozio_sug_report();
    if (empty($report['rewritable'])) {
        return;
    }
    $bar->add_node(array(
        'id'    => 'hozio-sug',
        'title' => '<span style="display:inline-block;background:#b3140f;color:#fff;font-weight:700;padding:0 9px;border-radius:3px;">DEV URLS ON LIVE SITE</span>',
        'href'  => admin_url('admin.php?page=hozio-plugin-settings') . '#hozio-dev-urls',
        'meta'  => array('title' => (int) $report['rewritable'] . ' rows still point at a dev domain'),
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
