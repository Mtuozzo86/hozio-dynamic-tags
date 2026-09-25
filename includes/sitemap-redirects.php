<?php
/**
 * HTML sitemap: leave out category and tag links that the Redirection plugin redirects.
 *
 * Loaded only by templates/html-sitemap-template.php, so it costs nothing anywhere else.
 *
 * Cost per sitemap render, never per term:
 *   - Redirection not active: no query at all.
 *   - Redirection active: one SHOW TABLES check. If its table is missing, nothing more.
 *   - Otherwise: one query for the regex rules (at most HOZIO_SITEMAP_REDIRECT_MAX_REGEX)
 *     and one indexed IN() query per HOZIO_SITEMAP_REDIRECT_IN_CHUNK links for the exact
 *     rules. A site with thousands of exact rules loads none of them: the database looks
 *     up only the sitemap's own links, the way Redirection itself looks up a request.
 *   - Regex tests stop after HOZIO_SITEMAP_REDIRECT_MAX_TESTS; links not tested by then
 *     stay listed, as they were before this check existed.
 *
 * Only rules that send EVERY visitor away count: enabled, match type "url", action
 * "url", "random" or "error" (404/410). Conditional rules (login state, referrer, cookie,
 * IP and so on) and "pass" / "do nothing" rules leave the link in place. Disabling a
 * Redirection group disables its rules, so the status check covers groups too.
 *
 * Regex rules are user input. A pattern that does not compile is dropped before use and
 * never raises a warning; a pattern that hits PCRE's backtrack limit simply does not match.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('HOZIO_SITEMAP_REDIRECT_MAX_REGEX')) {
    define('HOZIO_SITEMAP_REDIRECT_MAX_REGEX', 200);
}
if (!defined('HOZIO_SITEMAP_REDIRECT_MAX_TESTS')) {
    define('HOZIO_SITEMAP_REDIRECT_MAX_TESTS', 100000);
}
if (!defined('HOZIO_SITEMAP_REDIRECT_IN_CHUNK')) {
    define('HOZIO_SITEMAP_REDIRECT_IN_CHUNK', 1000);
}

/**
 * Is the Redirection plugin loaded on this request?
 *
 * @return bool
 */
function hozio_sitemap_redirection_active() {
    return defined('REDIRECTION_VERSION') || defined('REDIRECTION_FILE');
}

/**
 * Redirection's rules table, or '' when Redirection is not active or the table is missing.
 * Checked once per request.
 *
 * @param bool $reset Forget the cached answer (tests only).
 * @return string
 */
function hozio_sitemap_redirection_table($reset = false) {
    static $table = null;
    if ($reset) {
        $table = null;
        return '';
    }
    if ($table !== null) {
        return $table;
    }
    $table = '';

    if (!hozio_sitemap_redirection_active()) {
        return $table;
    }

    global $wpdb;
    if (!is_object($wpdb)) {
        return $table;
    }

    $name     = $wpdb->prefix . 'redirection_items';
    $suppress = $wpdb->suppress_errors(true);
    $found    = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($name)));
    $wpdb->suppress_errors($suppress);

    if ($found === $name) {
        $table = $name;
    }
    return $table;
}

/**
 * A path normalised the way Redirection stores it in match_url (Red_Url_Match::get_url()):
 * no query string, decoded, one trailing slash removed, re-encoded except / : [ ] @ ~ , ( ) ;
 * and lower case.
 *
 * @param string $path
 * @return string
 */
function hozio_sitemap_redirect_match_url($path) {
    $path = (string) $path;

    $q = strpos($path, '?');
    if ($q !== false) {
        $path = substr($path, 0, $q);
    }
    $path = urldecode($path);

    if ($path !== '/') {
        $path = (string) preg_replace('@/$@', '', $path);
    }

    $path = rawurlencode($path);
    foreach (array('/', ':', '[', ']', '@', '~', ',', '(', ')', ';') as $char) {
        $path = str_replace(rawurlencode($char), $char, $path);
    }

    $path = function_exists('mb_strtolower') ? mb_strtolower($path, 'UTF-8') : strtolower($path);
    return $path === '' ? '/' : $path;
}

/**
 * Build a PCRE pattern from a Redirection regex rule the way Redirection does
 * (Red_Regex: rawurldecode, "@" delimiter with "@" escaped, "s" flag), always case-insensitive.
 * Returns '' when the pattern does not compile, without raising a warning.
 *
 * @param string $rule
 * @return string
 */
function hozio_sitemap_redirect_compile($rule) {
    $pattern = '@' . str_replace('@', '\\@', rawurldecode((string) $rule)) . '@si';

    set_error_handler('hozio_sitemap_redirect_quiet');
    try {
        $ok = preg_match($pattern, '');
    } catch (Exception $e) {
        $ok = false;
    } catch (Error $e) {
        $ok = false;
    }
    restore_error_handler();

    if ($ok === false || preg_last_error() !== PREG_NO_ERROR) {
        return '';
    }
    return $pattern;
}

/**
 * Error handler that swallows PCRE warnings while user-entered patterns are tried.
 *
 * @return bool
 */
function hozio_sitemap_redirect_quiet() {
    return true;
}

/**
 * The enabled, unconditional regex rules as compiled patterns, loaded once per request.
 *
 * @param string $table
 * @param bool   $reset Forget the cached rules (tests only).
 * @return string[]
 */
function hozio_sitemap_redirect_regex_rules($table, $reset = false) {
    static $cache = array();
    if ($reset) {
        $cache = array();
        return array();
    }
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $cache[$table] = array();

    global $wpdb;
    $suppress = $wpdb->suppress_errors(true);
    $rows     = $wpdb->get_col($wpdb->prepare(
        "SELECT url FROM `{$table}` WHERE status = 'enabled' AND regex = 1 AND match_type = 'url'"
        . " AND action_type IN ('url', 'random', 'error') ORDER BY position ASC, id ASC LIMIT %d",
        HOZIO_SITEMAP_REDIRECT_MAX_REGEX
    ));
    $wpdb->suppress_errors($suppress);

    foreach ((array) $rows as $rule) {
        $pattern = hozio_sitemap_redirect_compile($rule);
        if ($pattern !== '') {
            $cache[$table][] = $pattern;
        }
    }
    return $cache[$table];
}

/**
 * Which of these links does an enabled Redirection rule send elsewhere?
 *
 * Regex rules are tested against the path with its leading slash (what Redirection itself
 * matches) AND without it, because rules are often written without one (^old-service-(.*)/).
 * Such a rule never fires in Redirection, but the link it aims at is still left out.
 *
 * @param string[] $links Absolute or root-relative URLs.
 * @return array<string, true> The redirected links, as keys.
 */
function hozio_sitemap_redirected_links($links) {
    $hidden = array();
    if (!is_array($links) || !$links) {
        return $hidden;
    }

    $table = hozio_sitemap_redirection_table();
    if ($table === '') {
        return $hidden;
    }

    $paths = array();
    foreach ($links as $link) {
        if (!is_string($link) || $link === '') {
            continue;
        }
        $path = wp_parse_url($link, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $paths[$link] = $path;
        }
    }
    if (!$paths) {
        return $hidden;
    }

    global $wpdb;

    // Exact rules: the database looks up only these links (match_url is indexed).
    $by_match = array();
    foreach ($paths as $link => $path) {
        $by_match[hozio_sitemap_redirect_match_url($path)][] = $link;
    }
    $suppress = $wpdb->suppress_errors(true);
    foreach (array_chunk(array_keys($by_match), HOZIO_SITEMAP_REDIRECT_IN_CHUNK) as $chunk) {
        $placeholders = implode(', ', array_fill(0, count($chunk), '%s'));
        $found = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT match_url FROM `{$table}` WHERE match_url IN ({$placeholders})"
            . " AND status = 'enabled' AND regex = 0 AND match_type = 'url'"
            . " AND action_type IN ('url', 'random', 'error')",
            $chunk
        ));
        foreach ((array) $found as $match_url) {
            $key = (string) $match_url;
            if (!isset($by_match[$key])) {
                $key = strtolower($key);
            }
            if (isset($by_match[$key])) {
                foreach ($by_match[$key] as $link) {
                    $hidden[$link] = true;
                }
            }
        }
    }
    $wpdb->suppress_errors($suppress);

    // Regex rules: loaded once, tested within a fixed budget.
    $rules = hozio_sitemap_redirect_regex_rules($table);
    if (!$rules) {
        return $hidden;
    }

    $budget  = HOZIO_SITEMAP_REDIRECT_MAX_TESTS;
    $stopped = false;
    set_error_handler('hozio_sitemap_redirect_quiet');
    try {
        foreach ($paths as $link => $path) {
            if (isset($hidden[$link])) {
                continue;
            }
            $with  = rawurldecode($path);
            $forms = array($with);
            $bare  = ltrim($with, '/');
            if ($bare !== $with && $bare !== '') {
                $forms[] = $bare;
            }
            foreach ($rules as $pattern) {
                foreach ($forms as $form) {
                    if (--$budget < 0) {
                        $stopped = true;
                        break 3;
                    }
                    if (preg_match($pattern, $form) === 1) {
                        $hidden[$link] = true;
                        continue 3;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // A user pattern must never break the sitemap; keep what was found so far.
    } catch (Error $e) {
        // Same.
    }
    restore_error_handler();

    if ($stopped && function_exists('hozio_log')) {
        hozio_log('HTML sitemap: redirect check stopped after ' . HOZIO_SITEMAP_REDIRECT_MAX_TESTS . ' regex tests; the remaining links stay listed.', 'Sitemap');
    }

    return $hidden;
}
