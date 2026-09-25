<?php
// Command line only. tests/ never ships in the release ZIP (see tests/README.md); if a
// copy ever reached a web server, this keeps it inert.
if (PHP_SAPI !== 'cli') {
    exit;
}
/**
 * HTML sitemap redirect filter (includes/sitemap-redirects.php, 4.21.1).
 *
 * Run: php -n tests/test-sitemap-redirects.php [path-to-plugin]   (default: this repo)
 *   or all of them: tests/run.ps1
 */

require __DIR__ . '/lib/wp-stubs.php';

$plugin = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__);

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
}
$GLOBALS['__debug_log'] = array();
function hozio_log($message, $context = '') { $GLOBALS['__debug_log'][] = $context . ': ' . $message; }

/**
 * A fake wpdb that understands the three query shapes sitemap-redirects.php issues.
 */
class Redirect_WPDB {
    public $prefix = 'wp_';
    public $has_table = false;
    public $items = array();   // rows: url, match_url, regex, status, match_type, action_type, position
    public $queries = array();
    private $suppress = false;

    public function suppress_errors($s = true) { $old = $this->suppress; $this->suppress = (bool) $s; return $old; }
    public function esc_like($t) { return addcslashes($t, '_%\\'); }
    public function prepare($query, ...$args) {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        $i = 0;
        return preg_replace_callback('/%[sd]/', function ($m) use (&$i, $args) {
            $v = $args[$i++];
            return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'";
        }, $query);
    }
    private static function literals($sql) {
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/s", $sql, $m);
        return array_map('stripslashes', $m[1]);
    }
    private function live($regex) {
        $out = array();
        foreach ($this->items as $r) {
            if ((int) $r['regex'] === $regex && $r['status'] === 'enabled' && $r['match_type'] === 'url'
                && in_array($r['action_type'], array('url', 'random', 'error'), true)) {
                $out[] = $r;
            }
        }
        return $out;
    }
    public function get_var($sql) {
        $this->queries[] = $sql;
        if (strpos($sql, 'SHOW TABLES LIKE') === 0) {
            // LIKE pattern with esc_like()'s backslashes; MySQL returns the real table name.
            $l    = self::literals($sql);
            $name = str_replace('\\', '', $l[0]);
            return ($this->has_table && $name === $this->prefix . 'redirection_items') ? $name : null;
        }
        return null;
    }
    public function get_col($sql) {
        $this->queries[] = $sql;
        if (!$this->has_table) {
            return array();
        }
        if (strpos($sql, 'regex = 1') !== false) {
            $rows = $this->live(1);
            usort($rows, function ($a, $b) { return $a['position'] - $b['position']; });
            preg_match('/LIMIT (\d+)/', $sql, $m);
            return array_map(function ($r) { return $r['url']; }, array_slice($rows, 0, (int) $m[1]));
        }
        if (preg_match('/match_url IN \((.*?)\) AND/s', $sql, $m)) {
            $want = self::literals($m[1]);
            $out  = array();
            foreach ($this->live(0) as $r) {
                if (in_array($r['match_url'], $want, true)) {
                    $out[$r['match_url']] = true;
                }
            }
            return array_keys($out);
        }
        return array();
    }
    public function add($url, $regex = 0, $extra = array()) {
        $row = array_merge(array(
            'url' => $url,
            'match_url' => $regex ? 'regex' : hozio_sitemap_redirect_match_url($url),
            'regex' => $regex, 'status' => 'enabled', 'match_type' => 'url', 'action_type' => 'url',
            'position' => count($this->items),
        ), $extra);
        $this->items[] = $row;
    }
    public function reset_queries() { $this->queries = array(); }
}

$GLOBALS['wpdb'] = new Redirect_WPDB();
global $wpdb;
require $plugin . '/includes/sitemap-redirects.php';

function reset_cache() {
    hozio_sitemap_redirection_table(true);
    hozio_sitemap_redirect_regex_rules('', true);
    $GLOBALS['wpdb']->reset_queries();
}

$site = 'https://example.test';
$links = array(
    $site . '/category/news/',
    $site . '/category/old-service/',
    $site . '/old-service-roofing/',
    $site . '/category/legacy-gutters/',
    $site . '/tag/keep-me/',
);

// ─────────────────────────────────────────────────────────────────────────────
section('match_url normalisation (as Redirection stores it)');
check(hozio_sitemap_redirect_match_url('/Foo/Bar/?x=1') === '/foo/bar', 'query dropped, trailing slash dropped, lower case');
check(hozio_sitemap_redirect_match_url('/') === '/', 'root stays /');
check(hozio_sitemap_redirect_match_url('/caf%C3%A9/') === '/caf%c3%a9', 'non-ASCII encoded the way Redirection encodes it');
check(hozio_sitemap_redirect_match_url('/a b/(c)/') === '/a%20b/(c)', 'space encoded, ( ) kept');

// ─────────────────────────────────────────────────────────────────────────────
section('no Redirection: no query at all');
reset_cache();
$wpdb->has_table = true;
$wpdb->add('/category/old-service/');
$h = hozio_sitemap_redirected_links($links);
check($h === array() && count($wpdb->queries) === 0, 'plugin not loaded: nothing hidden, 0 queries (table present or not)');

define('REDIRECTION_VERSION', '5.10.1');

// ─────────────────────────────────────────────────────────────────────────────
section('Redirection loaded, table missing');
reset_cache();
$wpdb->has_table = false;
$h = hozio_sitemap_redirected_links($links);
check($h === array() && count($wpdb->queries) === 1 && strpos($wpdb->queries[0], 'SHOW TABLES') === 0, 'one table check, nothing else, nothing hidden');
hozio_sitemap_redirected_links($links);
check(count($wpdb->queries) === 1, 'table check cached for the request');

// ─────────────────────────────────────────────────────────────────────────────
section('exact rules');
reset_cache();
$wpdb->has_table = true;
$wpdb->items = array();
$wpdb->add('/category/old-service/');
$wpdb->add('/category/news/', 0, array('status' => 'disabled'));
$wpdb->add('/tag/keep-me/', 0, array('action_type' => 'pass'));
$wpdb->add('/tag/keep-me', 0, array('action_type' => 'nothing'));
$wpdb->add('/category/news', 0, array('match_type' => 'login'));
$h = hozio_sitemap_redirected_links($links);
check(isset($h[$site . '/category/old-service/']), 'enabled exact rule: link left out');
check(!isset($h[$site . '/category/news/']), 'disabled rule and login-conditional rule: link kept');
check(!isset($h[$site . '/tag/keep-me/']), 'pass-through and do-nothing rules: link kept');
check(count($h) === 1, 'only that one link');
$shapes = array_map(function ($q) { return strpos($q, 'SHOW') === 0 ? 'show' : (strpos($q, 'regex = 1') !== false ? 'regex' : 'in'); }, $wpdb->queries);
check($shapes === array('show', 'in', 'regex'), 'three queries for the whole call: table, IN lookup, regex rules');
$wpdb->reset_queries();
hozio_sitemap_redirected_links(array($site . '/category/other/'));
check(count($wpdb->queries) === 1 && strpos($wpdb->queries[0], 'match_url IN') !== false, 'second call: only the IN lookup (table and regex rules cached)');
$wpdb->add('/category/Error-Page/', 0, array('action_type' => 'error'));
$h = hozio_sitemap_redirected_links(array($site . '/category/error-page/'));
check(isset($h[$site . '/category/error-page/']), 'a rule that answers 404/410 also leaves the link out (case-insensitive)');

// ─────────────────────────────────────────────────────────────────────────────
section('regex rules');
reset_cache();
$wpdb->items = array();
$wpdb->add('^/category/legacy-(.*)/', 1);
$wpdb->add('^old-service-(.*)/', 1);
$wpdb->add('^/broken-(.*', 1);          // does not compile
$wpdb->add('^/a@b/', 1);                // "@" is the delimiter; must be escaped
$wpdb->add('^/TAG/SHOUTY/$', 1);
$wpdb->add('^/tag/keep', 1, array('match_type' => 'referrer'));
$more = array_merge($links, array($site . '/a@b/', $site . '/tag/shouty/'));
$h = hozio_sitemap_redirected_links($more);   // the stub error handler throws on any unsuppressed warning
check(isset($h[$site . '/category/legacy-gutters/']), 'regex with the leading slash: left out');
check(isset($h[$site . '/old-service-roofing/']), 'regex written without the leading slash: left out too');
check(isset($h[$site . '/a@b/']), 'an "@" in a rule works');
check(isset($h[$site . '/tag/shouty/']), 'regex rules match case-insensitively');
check(!isset($h[$site . '/tag/keep-me/']) && !isset($h[$site . '/category/news/']), 'conditional regex rule and unmatched links: kept');
check(count(hozio_sitemap_redirect_regex_rules(hozio_sitemap_redirection_table())) === 4, 'the malformed rule is dropped silently (4 of 5 unconditional rules kept)');
check(hozio_sitemap_redirect_compile('(unclosed') === '' && hozio_sitemap_redirect_compile('^/ok/$') !== '', 'compile: bad pattern gives "", good one a pattern');
check(hozio_sitemap_redirect_compile('foo\\') === '', 'a pattern ending in a backslash is rejected without a warning');

// Catastrophic backtracking: preg_match fails quietly; the link just stays listed.
reset_cache();
$wpdb->items = array();
$wpdb->add('^/(a+)+$', 1);
$old_bt  = ini_get('pcre.backtrack_limit');
$old_jit = ini_get('pcre.jit');
ini_set('pcre.backtrack_limit', '1000');
ini_set('pcre.jit', '0');
$h = hozio_sitemap_redirected_links(array($site . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaab'));
ini_set('pcre.backtrack_limit', $old_bt);
ini_set('pcre.jit', $old_jit);
check($h === array(), 'backtrack-limit failure: no warning, link kept');

// ─────────────────────────────────────────────────────────────────────────────
section('large sites');
reset_cache();
$wpdb->items = array();
for ($i = 0; $i < 5000; $i++) {
    $wpdb->add('/old-exact-' . $i . '/');
}
$many = array();
for ($i = 0; $i < 2500; $i++) {
    $many[] = $site . '/category/c-' . $i . '/';
}
$many[] = $site . '/old-exact-4999/';
$h = hozio_sitemap_redirected_links($many);
$in = count(array_filter($wpdb->queries, function ($q) { return strpos($q, 'match_url IN') !== false; }));
check(count($h) === 1 && isset($h[$site . '/old-exact-4999/']), '5,000 exact rules: the one matching link found');
check($in === 3, '2,501 links: 3 IN lookups (chunks of 1,000), never one per link (' . $in . ')');

reset_cache();
$wpdb->items = array();
for ($i = 0; $i < 300; $i++) {
    $wpdb->add('^/never-' . $i . '/$', 1);
}
$h = hozio_sitemap_redirected_links(array($site . '/x/'));
check(count(hozio_sitemap_redirect_regex_rules(hozio_sitemap_redirection_table())) === HOZIO_SITEMAP_REDIRECT_MAX_REGEX, 'regex rules capped at ' . HOZIO_SITEMAP_REDIRECT_MAX_REGEX);

reset_cache();
$wpdb->items = array();
for ($i = 0; $i < 199; $i++) {
    $wpdb->add('^/never-' . $i . '/$', 1);
}
$wpdb->add('^/last-link/$', 1);
$lots = array();
for ($i = 0; $i < 400; $i++) {
    $lots[] = $site . '/category/p-' . $i . '/';
}
$lots[] = $site . '/last-link/';
$GLOBALS['__debug_log'] = array();
$t0 = microtime(true);
$h  = hozio_sitemap_redirected_links($lots);
$ms = (microtime(true) - $t0) * 1000;
check(!isset($h[$site . '/last-link/']), 'test budget reached: links not yet tested stay listed');
check(count($GLOBALS['__debug_log']) === 1 && strpos($GLOBALS['__debug_log'][0], 'stopped after') !== false, 'budget stop noted in the debug log');
check($ms < 2000, sprintf('budgeted run finishes quickly (%.0f ms)', $ms));

finish();
