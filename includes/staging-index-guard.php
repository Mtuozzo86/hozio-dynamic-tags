<?php
/**
 * Staging Index Guard
 *
 * Catches staging sites that search engines are still allowed to crawl or index,
 * and shows a non-dismissible banner in wp-admin AND on the front end with a
 * one-click fix.
 *
 * Also runs the mirror guard: a LIVE site set to "Discourage search engines" is
 * the more expensive version of this failure (usually a staging database pushed
 * to production), so that gets its own red banner.
 *
 * PRODUCTION SAFETY
 * -----------------
 * hozio_sig_init() returns before registering a single hook on any site that is
 * not staging and is not mis-set to noindex. On a normal live site the only code
 * that ever runs is the environment check and one autoloaded option read.
 *
 * Nothing here performs an HTTP request during a page load. The cheap check is
 * local only (one option + one file read). The authoritative remote check runs
 * on a button click or a daily cron, never on the front end.
 *
 * @package Hozio Pro
 * @since 4.17.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * Environment detection
 * ---------------------------------------------------------------------- */

/**
 * Host fragments that identify a staging site. Filterable so a future host can
 * be added without a code change.
 */
function hozio_sig_staging_patterns() {
    return apply_filters('hozio_staging_host_patterns', array('mystagingwebsite.com', 'hoziodev'));
}

/**
 * 'staging' or 'production'.
 *
 * home_url()/site_url() come from the database and are the honest signal.
 * HTTP_HOST is checked too, but only to WIDEN detection - it is client-supplied
 * and can be spoofed, so it may turn the guard ON for a request, never off.
 */
function hozio_sig_environment() {
    static $env = null;
    if (null !== $env) {
        return $env;
    }

    $override = get_option('hozio_staging_guard_environment', 'auto');
    if ('staging' === $override || 'production' === $override) {
        $env = $override;
        return $env;
    }

    $patterns = hozio_sig_staging_patterns();

    $hosts = array(
        wp_parse_url(home_url(), PHP_URL_HOST),
        wp_parse_url(site_url(), PHP_URL_HOST),
    );
    if (!empty($_SERVER['HTTP_HOST'])) {
        $hosts[] = wp_unslash($_SERVER['HTTP_HOST']);
    }

    foreach ($hosts as $host) {
        if (!is_string($host) || '' === $host) {
            continue;
        }
        foreach ($patterns as $pattern) {
            if (false !== stripos($host, $pattern)) {
                $env = 'staging';
                return $env;
            }
        }
    }

    // WordPress' own environment type, when the host or wp-config sets it.
    if (function_exists('wp_get_environment_type')) {
        $type = wp_get_environment_type();
        if (in_array($type, array('staging', 'development', 'local'), true)) {
            $env = 'staging';
            return $env;
        }
    }

    $env = 'production';
    return $env;
}

function hozio_sig_is_staging() {
    return 'staging' === hozio_sig_environment();
}

/* -------------------------------------------------------------------------
 * robots.txt helpers
 * ---------------------------------------------------------------------- */

/**
 * Directories that could be the web root, best guess first.
 *
 * ABSPATH is NOT reliable: on Pressable (and other hosts that keep WordPress
 * core outside the docroot) robots.txt lives somewhere ABSPATH never points at,
 * which made an earlier version of this guard miss the file completely.
 */
function hozio_sig_root_candidates() {
    $roots = array();

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $roots[] = wp_unslash($_SERVER['DOCUMENT_ROOT']);
    }
    $roots[] = ABSPATH;
    $roots[] = dirname(untrailingslashit(ABSPATH));

    $clean = array();
    foreach ($roots as $root) {
        if (!is_string($root) || '' === trim($root)) {
            continue;
        }
        $root = trailingslashit(wp_normalize_path($root));
        if (!in_array($root, $clean, true) && is_dir($root)) {
            $clean[] = $root;
        }
    }

    return apply_filters('hozio_staging_root_candidates', $clean);
}

/**
 * Where robots.txt actually is. An existing file wins; otherwise the first
 * writable candidate is where we would create one. '' when nothing is usable.
 */
function hozio_sig_robots_path() {
    $candidates = hozio_sig_root_candidates();

    foreach ($candidates as $root) {
        if (file_exists($root . 'robots.txt')) {
            return $root . 'robots.txt';
        }
    }
    foreach ($candidates as $root) {
        if (is_writable($root)) {
            return $root . 'robots.txt';
        }
    }

    return empty($candidates) ? '' : $candidates[0] . 'robots.txt';
}

/**
 * The file we write. The two directives come first and byte-for-byte match what
 * a staging site needs; the provenance comment sits underneath where no crawler
 * cares about it.
 */
function hozio_sig_marker() {
    return '# Written by Hozio Pro (Staging Index Guard)';
}

function hozio_sig_expected_robots() {
    $content  = "User-agent: *\n";
    $content .= "Disallow: /\n";
    $content .= "\n";
    $content .= hozio_sig_marker() . " on " . gmdate('Y-m-d H:i') . " UTC." . "\n";
    $content .= "# This is a staging site and must not be crawled or indexed.\n";

    return apply_filters('hozio_staging_robots_content', $content);
}

/**
 * Does this robots.txt block every crawler from the whole site?
 *
 * True only when the "User-agent: *" group carries a bare "Disallow: /" and no
 * bare "Allow: /" that would override it.
 */
function hozio_sig_robots_blocks_all($content) {
    if (!is_string($content) || '' === trim($content)) {
        return false;
    }

    $in_star_group = false;
    $disallow_all  = false;
    $allow_all     = false;

    foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
        $line = trim($line);
        if ('' === $line || 0 === strpos($line, '#')) {
            continue;
        }
        if (preg_match('/^user-agent\s*:\s*(.+)$/i', $line, $m)) {
            $in_star_group = ('*' === trim($m[1]));
            continue;
        }
        if (!$in_star_group) {
            continue;
        }
        if (preg_match('/^disallow\s*:\s*\/\s*$/i', $line)) {
            $disallow_all = true;
        }
        if (preg_match('/^allow\s*:\s*\/\s*$/i', $line)) {
            $allow_all = true;
        }
    }

    return $disallow_all && !$allow_all;
}

/**
 * Sitemap: lines pointing at a different host than this site - a staging
 * robots.txt advertising the production sitemap, which is what a clone leaves
 * behind.
 */
function hozio_sig_foreign_sitemaps($content) {
    $found = array();
    if (!is_string($content) || '' === $content) {
        return $found;
    }

    $own = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));

    if (preg_match_all('/^\s*sitemap\s*:\s*(\S+)/im', $content, $matches)) {
        foreach ($matches[1] as $url) {
            $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
            if ('' !== $host && $host !== $own) {
                $found[] = $url;
            }
        }
    }

    return array_values(array_unique($found));
}

/* -------------------------------------------------------------------------
 * Status (cheap, local only - safe to call on every page load)
 * ---------------------------------------------------------------------- */

/**
 * One option read plus one small file read, cached for 5 minutes.
 * Never makes an HTTP request. Never calls do_robots().
 */
function hozio_sig_status($force = false) {
    if (!$force) {
        $cached = get_transient('hozio_sig_status');
        if (is_array($cached)) {
            return $cached;
        }
    }

    $environment   = hozio_sig_environment();
    $discourage_on = ('0' === (string) get_option('blog_public', '1'));

    $robots_path    = hozio_sig_robots_path();
    $robots_managed = ('' !== $robots_path);
    $robots_exists  = $robots_managed && file_exists($robots_path);

    // What the world actually gets is the only authority here. A file served by
    // the web server never reaches PHP, and it does not have to live anywhere
    // this install can see, so the filesystem is a fallback and nothing more.
    $remote = get_transient('hozio_sig_remote_result');
    if ((!is_array($remote) || empty($remote['ok'])) && is_admin() && !wp_doing_ajax()) {
        $remote = hozio_sig_remote_check();
    }

    $robots_body       = '';
    $robots_blocks_all = null;   // null = not established yet
    $robots_source     = 'unknown';

    if (is_array($remote) && !empty($remote['ok'])) {
        $robots_source     = 'live';
        $robots_body       = isset($remote['body']) ? (string) $remote['body'] : '';
        $robots_blocks_all = !empty($remote['blocks_all']);
    } elseif ($robots_exists && is_readable($robots_path)) {
        $robots_source     = 'file';
        $robots_body       = (string) file_get_contents($robots_path);
        $robots_blocks_all = hozio_sig_robots_blocks_all($robots_body);
    }

    $foreign_sitemaps = hozio_sig_foreign_sitemaps($robots_body);
    $robots_is_ours   = ('' !== $robots_body) && (false !== strpos($robots_body, hozio_sig_marker()));

    $issues = array();
    $level  = 'green';

    if ('production' === $environment) {
        // Mirror guard. Every check is inverted on a live site: being blocked is
        // the emergency, not being crawlable.
        if ($discourage_on) {
            $level    = 'red';
            $issues[] = 'This LIVE site is set to discourage search engines, so every page carries a noindex tag. Google will drop this site from results.';
        }
        if (true === $robots_blocks_all) {
            $level    = 'red';
            $issues[] = $robots_is_ours
                ? 'robots.txt on this LIVE site blocks every search engine from the whole site. It is the staging file, carried over when this site went live.'
                : 'robots.txt on this LIVE site blocks every search engine from the whole site.';
        }
    } else {
        if (!$discourage_on) {
            $level    = 'red';
            $issues[] = 'Search engines are NOT discouraged. Pages are being served without a noindex tag and can be indexed.';
        }
        if (false === $robots_blocks_all) {
            $level    = ('red' === $level) ? 'red' : 'amber';
            $issues[] = 'live' === $robots_source
                ? 'robots.txt is being served to crawlers and does not block them.'
                : 'robots.txt exists on disk and does not block crawlers.';
        }
        if (!empty($foreign_sitemaps)) {
            $level    = ('red' === $level) ? 'red' : 'amber';
            $issues[] = 'robots.txt points at a sitemap on another domain (' . implode(', ', $foreign_sitemaps) . ') - it was copied from production.';
        }
        if (null === $robots_blocks_all) {
            $issues[] = 'robots.txt has not been checked yet. Use "Re-check now" on the Hozio Pro settings screen.';
        }
    }

    if ('amber' === $level && '1' === get_option('hozio_staging_guard_all_red', '0')) {
        $level = 'red';
    }

    $status = array(
        'environment'       => $environment,
        'discourage_on'     => $discourage_on,
        'robots_managed'    => $robots_managed,
        'robots_path'       => $robots_path,
        'robots_exists'     => $robots_exists,
        'robots_body'       => $robots_body,
        'robots_blocks_all' => $robots_blocks_all,
        'robots_source'     => $robots_source,
        'robots_is_ours'    => $robots_is_ours,
        'root_candidates'   => hozio_sig_root_candidates(),
        'foreign_sitemaps'  => $foreign_sitemaps,
        'issues'            => $issues,
        'level'             => $level,
    );

    set_transient('hozio_sig_status', $status, 5 * MINUTE_IN_SECONDS);

    return $status;
}

function hozio_sig_flush_status() {
    delete_transient('hozio_sig_status');
}
add_action('update_option_blog_public', 'hozio_sig_flush_status');

/* -------------------------------------------------------------------------
 * Remote verify (Tier 2) - button click or daily cron ONLY
 * ---------------------------------------------------------------------- */

/**
 * Fetches the site's own robots.txt over HTTP to see what the world actually
 * gets, which is the only way to catch a file served above WordPress.
 *
 * Guarded three ways so it can never storm the site: refuses to run outside
 * admin/cron/WP-CLI, holds a 60-second lock, and caches the result.
 */
function hozio_sig_remote_check($force = false, $bust = false) {
    $allowed = is_admin() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI);
    if (!$allowed) {
        $cached = get_transient('hozio_sig_remote_result');
        return is_array($cached) ? $cached : array();
    }

    if (!$force) {
        $cached = get_transient('hozio_sig_remote_result');
        if (is_array($cached)) {
            return $cached;
        }
    }

    if (get_transient('hozio_sig_remote_lock')) {
        $cached = get_transient('hozio_sig_remote_result');
        return is_array($cached) ? $cached : array();
    }
    set_transient('hozio_sig_remote_lock', 1, MINUTE_IN_SECONDS);

    $url = home_url('/robots.txt');

    // A cache one step behind will happily hand back the file we just replaced.
    // Static servers ignore the query string but caches key on it, so this is
    // what makes a verification fetch actually see the current file.
    $request_url = $bust
        ? add_query_arg('hozio-verify', time() . '-' . wp_rand(1000, 9999), $url)
        : $url;

    $response = wp_remote_get($request_url, array(
        'timeout'     => 8,
        'redirection' => 2,
        'sslverify'   => false, // staging certs are frequently self-signed
        'user-agent'  => 'HozioPro-StagingGuard/' . (defined('HOZIO_VERSION') ? HOZIO_VERSION : '1.0'),
        'headers'     => array('Cache-Control' => 'no-cache', 'Pragma' => 'no-cache'),
    ));

    delete_transient('hozio_sig_remote_lock');

    if (is_wp_error($response)) {
        $result = array(
            'checked_at' => time(),
            'ok'         => false,
            'error'      => $response->get_error_message(),
        );
        set_transient('hozio_sig_remote_result', $result, HOUR_IN_SECONDS);
        return $result;
    }

    $body    = (string) wp_remote_retrieve_body($response);
    $code    = (int) wp_remote_retrieve_response_code($response);
    $headers = wp_remote_retrieve_headers($response);

    // A static file served by the web server carries ETag/Last-Modified and
    // never reaches PHP. That tells us whether a filter could help at all.
    $etag          = is_object($headers) || is_array($headers) ? (string) ($headers['etag'] ?? '') : '';
    $last_modified = is_object($headers) || is_array($headers) ? (string) ($headers['last-modified'] ?? '') : '';
    $served_static = ('' !== $etag || '' !== $last_modified);

    $result = array(
        'checked_at'    => time(),
        'ok'            => true,
        'status'        => $code,
        'body'          => $body,
        'blocks_all'    => hozio_sig_robots_blocks_all($body),
        'served_static' => $served_static,
        'url'           => $url,
    );

    set_transient('hozio_sig_remote_result', $result, 12 * HOUR_IN_SECONDS);
    update_option('hozio_sig_last_remote_check', time(), false);

    // Autoloaded, so the production guard can read it on any page load without
    // a query of its own and without ever making an HTTP request there.
    update_option('hozio_sig_robots_blocked', !empty($result['blocks_all']) ? '1' : '0', true);

    return $result;
}

function hozio_sig_daily_check() {
    hozio_sig_flush_status();
    hozio_sig_remote_check(true, true);

    $status = hozio_sig_status(true);
    if ('green' !== $status['level'] && function_exists('hozio_log')) {
        $label = ('production' === $status['environment'])
            ? 'LIVE site is hidden from search engines: '
            : 'Staging site is still crawlable: ';
        hozio_log($label . implode(' | ', $status['issues']), 'StagingGuard');
    }
}
add_action('hozio_sig_daily_check', 'hozio_sig_daily_check');

/* -------------------------------------------------------------------------
 * The fix
 * ---------------------------------------------------------------------- */

/**
 * Turns on "Discourage search engines".
 */
function hozio_sig_fix_discourage() {
    if ('0' === (string) get_option('blog_public', '1')) {
        return array(true, 'Search engines were already discouraged.');
    }
    update_option('blog_public', '0');
    return array(true, 'Search engines are now discouraged (noindex tag added site-wide).');
}

/**
 * Writes the blocking robots.txt, backing up whatever was there first.
 *
 * Backs up with copy() rather than rename() so the original is still in place if
 * the write fails. Verifies by reading the file back - it never reports success
 * on a write it did not confirm.
 */
function hozio_sig_fix_robots() {
    $path = hozio_sig_robots_path();

    if ('' === $path) {
        return array(false, 'No usable web root was found, so robots.txt could not be written. Set it by hand.');
    }

    $dir     = trailingslashit(dirname($path));
    $content = hozio_sig_expected_robots();
    $backup  = '';

    // "Already correct" has to mean correct on the URL, not correct on disk.
    $before = hozio_sig_remote_check(true);
    if (is_array($before) && !empty($before['ok']) && !empty($before['blocks_all'])) {
        $before_body = isset($before['body']) ? (string) $before['body'] : '';
        if (!hozio_sig_foreign_sitemaps($before_body)) {
            return array(true, 'robots.txt already blocks all crawlers.');
        }
    }

    if (file_exists($path)) {
        if (!is_writable($path)) {
            return array(false, 'robots.txt at ' . $path . ' is not writable by PHP. Fix the permissions, or paste the text below in by hand.');
        }
        $backup = $dir . 'robots.txt.hozio-backup-' . gmdate('Ymd-His');
        if (!copy($path, $backup)) {
            return array(false, 'Could not back up the existing robots.txt at ' . $path . ', so nothing was changed.');
        }
    } elseif (!is_writable($dir)) {
        return array(false, $dir . ' is not writable by PHP, so robots.txt could not be created. Paste the text below in by hand.');
    }

    if (false === file_put_contents($path, $content)) {
        return array(false, 'Writing ' . $path . ' failed. Nothing was changed.');
    }

    clearstatcache(true, $path);
    if (trim((string) file_get_contents($path)) !== trim($content)) {
        return array(false, 'robots.txt was written but read back different. Check ' . $path . ' by hand.');
    }

    $note = ('' !== $backup) ? ' The old file was saved as ' . basename($backup) . '.' : '';

    if ('' !== $backup) {
        update_option('hozio_sig_last_backup', $backup, false);
    }

    // Writing the right bytes into the wrong directory looks identical on disk,
    // so the URL is the only thing that proves the fix landed. Give the cache a
    // few seconds to catch up before believing a negative - a single immediate
    // fetch reports failure on a fix that actually worked.
    $attempts = (int) apply_filters('hozio_staging_verify_attempts', 3);
    $delay    = (int) apply_filters('hozio_staging_verify_delay', 2);
    $after    = array();

    for ($attempt = 1; $attempt <= max(1, $attempts); $attempt++) {
        if ($attempt > 1 && $delay > 0) {
            sleep($delay);
        }
        delete_transient('hozio_sig_remote_result');
        $after = hozio_sig_remote_check(true, true);
        if (is_array($after) && !empty($after['ok']) && !empty($after['blocks_all'])) {
            break;
        }
    }

    if (is_array($after) && !empty($after['ok']) && !empty($after['blocks_all'])) {
        return array(true, 'robots.txt now blocks all crawlers, confirmed by fetching ' . home_url('/robots.txt') . '.' . $note);
    }

    if (is_array($after) && !empty($after['ok'])) {
        return array(false, 'Wrote ' . $path . ' and confirmed it on disk, but ' . home_url('/robots.txt') . ' was still serving the old file after ' . max(1, $attempts) . ' checks. That is usually just a cache that has not caught up - open the URL yourself in a minute, then use "Re-check now". The status below always reflects the live file. If it still shows the old one, the web root is somewhere else.' . $note);
    }

    return array(true, 'Wrote ' . $path . ', but the HTTP check could not run. Open ' . home_url('/robots.txt') . ' to confirm.' . $note);
}

/**
 * Backups this guard has left next to robots.txt, oldest first.
 */
function hozio_sig_find_backups() {
    $path = hozio_sig_robots_path();
    if ('' === $path) {
        return array();
    }
    $found = glob(trailingslashit(dirname($path)) . 'robots.txt.hozio-backup-*');
    if (!is_array($found)) {
        return array();
    }
    sort($found);

    return $found;
}

/**
 * Put back the robots.txt this guard replaced.
 *
 * This is the undo for a staging robots.txt that rode into production. It only
 * touches a file carrying our own marker, so a hand-written robots.txt is never
 * overwritten by it.
 */
function hozio_sig_restore_robots() {
    $path = hozio_sig_robots_path();

    if ('' === $path || !file_exists($path)) {
        return array(false, 'There is no robots.txt here to restore over.');
    }

    $body = is_readable($path) ? (string) file_get_contents($path) : '';
    if (false === strpos($body, hozio_sig_marker())) {
        return array(false, 'This robots.txt was not written by Hozio Pro, so it will not be replaced. Edit ' . $path . ' by hand.');
    }

    $backups = hozio_sig_find_backups();
    if (empty($backups)) {
        return array(false, 'No Hozio Pro backup of robots.txt was found next to ' . $path . '. Edit it by hand.');
    }

    if (!is_writable($path)) {
        return array(false, $path . ' is not writable by PHP. Restore it by hand.');
    }

    $backup = end($backups);
    if (!copy($backup, $path)) {
        return array(false, 'Restoring ' . basename($backup) . ' failed. Nothing was changed.');
    }

    clearstatcache(true, $path);
    delete_transient('hozio_sig_remote_result');
    hozio_sig_flush_status();
    hozio_sig_remote_check(true, true);

    return array(true, 'robots.txt restored from ' . basename($backup) . '. Check ' . home_url('/robots.txt') . ' to confirm.');
}

/**
 * admin-post handler for the Restore button.
 */
function hozio_sig_handle_restore() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.', 'Hozio Pro', array('response' => 403));
    }
    check_admin_referer('hozio_sig_restore', 'hozio_sig_nonce');

    list($ok, $msg) = hozio_sig_restore_robots();

    if (function_exists('hozio_log')) {
        hozio_log('robots.txt restore: ' . $msg, 'StagingGuard');
    }

    set_transient('hozio_sig_notice_' . get_current_user_id(), array(
        'failed'   => !$ok,
        'messages' => array($msg),
    ), 5 * MINUTE_IN_SECONDS);

    $back = wp_get_referer();
    if (!$back) {
        $back = admin_url('admin.php?page=hozio-plugin-settings');
    }
    wp_safe_redirect(add_query_arg('hozio_sig', $ok ? 'ok' : 'fail', $back));
    exit;
}

function hozio_sig_restore_url() {
    return wp_nonce_url(
        admin_url('admin-post.php?action=hozio_sig_restore'),
        'hozio_sig_restore',
        'hozio_sig_nonce'
    );
}

/**
 * admin-post handler for the Fix button.
 *
 * Re-checks the environment INSIDE the handler rather than trusting that the
 * hook only got registered on staging.
 */
function hozio_sig_handle_fix() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.', 'Hozio Pro', array('response' => 403));
    }
    check_admin_referer('hozio_sig_fix', 'hozio_sig_nonce');

    if (!hozio_sig_is_staging()) {
        wp_die(
            'Refused: this is not a staging site. The Staging Index Guard will not add a noindex or a blocking robots.txt to a live site.',
            'Hozio Pro',
            array('response' => 400)
        );
    }

    $what     = isset($_REQUEST['what']) ? sanitize_key(wp_unslash($_REQUEST['what'])) : 'both';
    $messages = array();
    $failed   = false;

    if (in_array($what, array('both', 'discourage'), true)) {
        list($ok, $msg) = hozio_sig_fix_discourage();
        $messages[] = $msg;
        $failed = $failed || !$ok;
    }

    if (in_array($what, array('both', 'robots'), true)) {
        list($ok, $msg) = hozio_sig_fix_robots();
        $messages[] = $msg;
        $failed = $failed || !$ok;
    }

    if (function_exists('hozio_log')) {
        hozio_log('Staging fix (' . $what . '): ' . implode(' ', $messages), 'StagingGuard');
    }

    hozio_sig_flush_status();
    delete_transient('hozio_sig_remote_result');
    set_transient('hozio_sig_notice_' . get_current_user_id(), array(
        'failed'   => $failed,
        'messages' => $messages,
    ), 5 * MINUTE_IN_SECONDS);

    $back = wp_get_referer();
    if (!$back) {
        $back = admin_url('admin.php?page=hozio-plugin-settings');
    }
    wp_safe_redirect(add_query_arg('hozio_sig', $failed ? 'fail' : 'ok', $back));
    exit;
}

/**
 * admin-post handler for "Re-check now" (the remote verify).
 */
function hozio_sig_handle_recheck() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.', 'Hozio Pro', array('response' => 403));
    }
    check_admin_referer('hozio_sig_recheck', 'hozio_sig_nonce');

    hozio_sig_flush_status();
    hozio_sig_remote_check(true);

    $back = wp_get_referer();
    if (!$back) {
        $back = admin_url('admin.php?page=hozio-plugin-settings');
    }
    wp_safe_redirect(add_query_arg('hozio_sig', 'checked', $back));
    exit;
}

function hozio_sig_fix_url($what = 'both') {
    return wp_nonce_url(
        admin_url('admin-post.php?action=hozio_sig_fix&what=' . rawurlencode($what)),
        'hozio_sig_fix',
        'hozio_sig_nonce'
    );
}

function hozio_sig_recheck_url() {
    return wp_nonce_url(
        admin_url('admin-post.php?action=hozio_sig_recheck'),
        'hozio_sig_recheck',
        'hozio_sig_nonce'
    );
}

/* -------------------------------------------------------------------------
 * Banner
 * ---------------------------------------------------------------------- */

function hozio_sig_banner_colors($level) {
    if ('red' === $level) {
        return array('bg' => '#b3140f', 'edge' => '#7d0d0a');
    }
    return array('bg' => '#b26a00', 'edge' => '#7d4a00');
}

/**
 * Shared banner markup. Inline CSS only - no enqueued assets, no JavaScript.
 * Overlays the page; never adds body padding, so nothing on the site shifts.
 */
function hozio_sig_banner_html($status, $context) {
    $level  = $status['level'];
    $colors = hozio_sig_banner_colors($level);
    $can_fix = current_user_can('manage_options') && 'staging' === $status['environment'];

    if ('production' === $status['environment']) {
        $title = 'LIVE SITE IS HIDDEN FROM GOOGLE';
    } elseif ('red' === $level) {
        $title = 'STAGING SITE IS INDEXABLE';
    } else {
        $title = 'STAGING SITE IS CRAWLABLE';
    }

    $fixed = 'admin' === $context ? 'position:relative;' : 'position:fixed;top:0;left:0;right:0;';

    ob_start();
    ?>
    <div id="hozio-sig-bar" style="<?php echo esc_attr($fixed); ?>z-index:2147483640;background:<?php echo esc_attr($colors['bg']); ?>;border-bottom:3px solid <?php echo esc_attr($colors['edge']); ?>;color:#fff;padding:12px 16px;font:600 13px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;box-sizing:border-box;box-shadow:0 2px 8px rgba(0,0,0,.25);">
        <div style="max-width:1400px;margin:0 auto;display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;">
            <span style="font-size:15px;font-weight:800;letter-spacing:.03em;white-space:nowrap;">
                <?php echo esc_html($title); ?>
            </span>
            <span style="font-weight:400;flex:1 1 320px;min-width:0;">
                <?php echo esc_html(implode(' ', $status['issues'])); ?>
            </span>
            <?php if ($can_fix) : ?>
                <a href="<?php echo esc_url(hozio_sig_fix_url('both')); ?>"
                   style="background:#fff;color:<?php echo esc_attr($colors['edge']); ?>;padding:7px 18px;border-radius:3px;text-decoration:none;font-weight:700;white-space:nowrap;">
                    Fix this now
                </a>
            <?php elseif ('production' === $status['environment'] && current_user_can('manage_options')) : ?>
                <?php if (!empty($status['discourage_on'])) : ?>
                    <a href="<?php echo esc_url(admin_url('options-reading.php')); ?>"
                       style="background:#fff;color:<?php echo esc_attr($colors['edge']); ?>;padding:7px 18px;border-radius:3px;text-decoration:none;font-weight:700;white-space:nowrap;">
                        Open Reading settings
                    </a>
                <?php endif; ?>
                <?php if (!empty($status['robots_is_ours']) && true === $status['robots_blocks_all']) : ?>
                    <a href="<?php echo esc_url(hozio_sig_restore_url()); ?>"
                       style="background:#fff;color:<?php echo esc_attr($colors['edge']); ?>;padding:7px 18px;border-radius:3px;text-decoration:none;font-weight:700;white-space:nowrap;">
                        Restore previous robots.txt
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function hozio_sig_render_admin_banner() {
    $status = hozio_sig_status();
    if ('green' === $status['level']) {
        return;
    }
    echo hozio_sig_banner_html($status, 'admin'); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Front-end banner. Suppressed anywhere output would break something.
 */
function hozio_sig_render_front_banner() {
    if (!empty($GLOBALS['hozio_sig_front_done'])) {
        return;
    }

    if (wp_doing_ajax() || wp_doing_cron() || is_feed() || is_robots()
        || (defined('REST_REQUEST') && REST_REQUEST)
        || (defined('IFRAME_REQUEST') && IFRAME_REQUEST)
        || (function_exists('is_customize_preview') && is_customize_preview())) {
        return;
    }

    // Page builder previews and editors - never paint over the canvas.
    if (isset($_GET['elementor-preview']) || isset($_GET['ver_preview'])
        || (isset($_GET['action']) && 'elementor' === $_GET['action'])
        || (isset($_GET['context']) && 'edit' === $_GET['context'])) {
        return;
    }

    $is_admin_user = current_user_can('manage_options');
    $public_ok     = ('1' === get_option('hozio_staging_banner_public', '0'));

    // Cheap exit for the overwhelming majority of front-end requests.
    if (!$is_admin_user && !$public_ok) {
        return;
    }

    $status = hozio_sig_status();
    if ('green' === $status['level']) {
        return;
    }

    // A LIVE site never shows this to a visitor, whatever the settings say.
    // "Show the banner to logged-out visitors" applies to staging only.
    if (!$is_admin_user && 'production' === $status['environment']) {
        return;
    }

    $GLOBALS['hozio_sig_front_done'] = true;

    echo '<style>#hozio-sig-bar{top:0}.admin-bar #hozio-sig-bar{top:32px}@media screen and (max-width:782px){.admin-bar #hozio-sig-bar{top:46px}}</style>';
    echo hozio_sig_banner_html($status, 'front'); // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Admin bar node - this is what makes the warning visible in BOTH the front end
 * and wp-admin for a logged-in admin.
 */
function hozio_sig_admin_bar_node($bar) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $status = hozio_sig_status();
    if ('green' === $status['level']) {
        return;
    }

    $colors = hozio_sig_banner_colors($status['level']);

    if ('production' === $status['environment']) {
        $label = 'LIVE SITE HIDDEN FROM GOOGLE';
    } elseif ('red' === $status['level']) {
        $label = 'STAGING: INDEXABLE';
    } else {
        $label = 'STAGING: CRAWLABLE';
    }

    $bar->add_node(array(
        'id'    => 'hozio-sig',
        'title' => '<span style="display:inline-block;background:' . esc_attr($colors['bg']) . ';color:#fff;font-weight:700;padding:0 9px;border-radius:3px;">' . esc_html($label) . '</span>',
        'href'  => admin_url('admin.php?page=hozio-plugin-settings'),
        'meta'  => array('title' => implode(' ', $status['issues'])),
    ));
}

/* -------------------------------------------------------------------------
 * Bootstrap
 * ---------------------------------------------------------------------- */

/**
 * Drop the daily cron, but only when there is actually something to drop.
 *
 * This runs on every request on a live site, so it must stay a read. Calling
 * wp_clear_scheduled_hook() unconditionally would walk the cron array on every
 * page load of every production site for no reason.
 */
function hozio_sig_unschedule() {
    if (wp_next_scheduled('hozio_sig_daily_check')) {
        wp_clear_scheduled_hook('hozio_sig_daily_check');
    }
}

function hozio_sig_init() {
    // The fix handlers are registered in admin context only, and each one
    // re-checks capability, nonce and environment for itself.
    if (is_admin()) {
        add_action('admin_post_hozio_sig_fix', 'hozio_sig_handle_fix');
        add_action('admin_post_hozio_sig_recheck', 'hozio_sig_handle_recheck');
        add_action('admin_post_hozio_sig_restore', 'hozio_sig_handle_restore');
    }

    if ('1' !== get_option('hozio_staging_guard_enabled', '1')) {
        hozio_sig_unschedule();
        return;
    }

    // The daily verification runs on live sites too now. A production site
    // serving "Disallow: /" is as damaging as a staging site left wide open,
    // and it is exactly what a staging-to-production move leaves behind.
    if (!wp_next_scheduled('hozio_sig_daily_check')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'hozio_sig_daily_check');
    }

    if (!hozio_sig_is_staging()) {
        // Both reads below are autoloaded options, so this costs nothing extra
        // on a page load and never makes an HTTP request here.
        $sig_noindex = ('0' === (string) get_option('blog_public', '1'));
        $sig_blocked = ('1' === get_option('hozio_sig_robots_blocked', '0'));

        if ($sig_noindex || $sig_blocked) {
            add_action('in_admin_header', 'hozio_sig_render_admin_banner', 1000);
            add_action('admin_bar_menu', 'hozio_sig_admin_bar_node', 999);
            add_action('wp_body_open', 'hozio_sig_render_front_banner', 1);
            add_action('wp_footer', 'hozio_sig_render_front_banner', 1);
        }
        return;
    }

    /* --- staging only, below this line --- */

    // Rendered at priority 1000 on in_admin_header, NOT as an admin_notice:
    // hozio_hide_third_party_notices() wipes all admin notices at priority 999
    // on Hozio Pro screens, which is exactly where this needs to be visible.
    add_action('in_admin_header', 'hozio_sig_render_admin_banner', 1000);
    add_action('admin_bar_menu', 'hozio_sig_admin_bar_node', 999);
    add_action('wp_body_open', 'hozio_sig_render_front_banner', 1);
    add_action('wp_footer', 'hozio_sig_render_front_banner', 1);

    // Backstop for the generated robots.txt. Does nothing when a real file is on
    // disk (the web server serves that and PHP is never reached), but it covers
    // a staging site that has no file.
    add_filter('robots_txt', 'hozio_sig_filter_robots', PHP_INT_MAX, 2);
}
add_action('init', 'hozio_sig_init', 20);

function hozio_sig_filter_robots($output, $public) {
    if (!hozio_sig_is_staging()) {
        return $output;
    }
    return "User-agent: *\nDisallow: /\n";
}

/**
 * Deactivation: drop the cron event.
 */
function hozio_sig_deactivate() {
    wp_clear_scheduled_hook('hozio_sig_daily_check');
    delete_transient('hozio_sig_status');
    delete_transient('hozio_sig_remote_result');
    delete_transient('hozio_sig_remote_lock');
}
