<?php
// Command line only. tests/ never ships in the release ZIP (see tests/README.md); if a
// copy ever reached a web server, this keeps it inert.
if (PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Minimal WordPress stubs + an in-memory fake wpdb for behaviour-testing Hozio Pro's
 * pure logic. The fake understands only the SQL shapes the code under test issues.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(function ($no, $str, $file, $line) {
    // Warnings in code under test are test failures, except the ones @-suppressed.
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

if (!defined('ABSPATH')) define('ABSPATH', sys_get_temp_dir() . '/hozio-wp/');
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');

$GLOBALS['__options']      = array();
$GLOBALS['__actions']      = array();
$GLOBALS['__tz_offset']    = -4; // site is UTC-4 (EDT)
$GLOBALS['__dbdelta_calls'] = 0;

function get_option($name, $default = false) {
    return array_key_exists($name, $GLOBALS['__options']) ? $GLOBALS['__options'][$name] : $default;
}
function update_option($name, $value, $autoload = null) {
    $GLOBALS['__options'][$name] = $value;
    return true;
}
function add_option($name, $value = '', $dep = '', $autoload = 'yes') {
    if (array_key_exists($name, $GLOBALS['__options'])) return false;
    $GLOBALS['__options'][$name] = $value;
    return true;
}
function delete_option($name) {
    unset($GLOBALS['__options'][$name]);
    return true;
}
function add_action($hook, $cb, $prio = 10, $args = 1) {
    $GLOBALS['__actions'][$hook][] = array($cb, $prio);
    return true;
}
function add_filter($hook, $cb, $prio = 10, $args = 1) {
    return add_action($hook, $cb, $prio, $args);
}
function wp_installing() { return false; }
function wp_cache_delete($k, $g = '') { return true; }
function number_format_i18n($n, $d = 0) { return number_format($n, $d); }
function wp_parse_args($args, $defaults = array()) {
    return array_merge($defaults, (array) $args);
}
function esc_js($s) { return addslashes($s); }
function get_gmt_from_date($date, $format = 'Y-m-d H:i:s') {
    $ts = strtotime($date . ' UTC');
    if ($ts === false) return false;
    return gmdate($format, $ts - $GLOBALS['__tz_offset'] * 3600);
}
function get_date_from_gmt($date, $format = 'Y-m-d H:i:s') {
    $ts = strtotime($date . ' UTC');
    return gmdate($format, $ts + $GLOBALS['__tz_offset'] * 3600);
}
function dbDelta($sql) {
    global $wpdb;
    $GLOBALS['__dbdelta_calls']++;
    if (preg_match('/CREATE TABLE\s+`?([A-Za-z0-9_]+)`?\s*\(/', $sql, $m)) {
        if (!$wpdb->deny_create && !isset($wpdb->tables[$m[1]])) {
            $wpdb->tables[$m[1]] = array('rows' => array(), 'auto' => 0);
        }
    }
    return array();
}

/**
 * In-memory fake of the parts of wpdb the logger uses.
 */
class Fake_WPDB {
    public $prefix = 'wp_';
    public $options = 'wp_options';
    public $charset = 'utf8mb4';
    public $insert_id = 0;
    public $last_error = '';
    public $last_query = '';
    public $last_result = array();
    public $rows_affected = 0;
    public $num_rows = 0;

    public $tables = array();       // name => ['rows' => [id => row], 'auto' => int]
    public $opt_rows = array();     // raw options-table rows touched with SQL (the lock)
    public $deny_create = false;
    public $fail_inserts = false;
    public $queries = array();
    private $suppress = false;

    public function suppress_errors($s = true) { $old = $this->suppress; $this->suppress = (bool) $s; return $old; }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET ' . $this->charset; }
    public function esc_like($text) { return addcslashes($text, '_%\\'); }

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

    private function strings($sql) {
        // All single-quoted literals in order, unescaped.
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/s", $sql, $m);
        return array_map('stripslashes', $m[1]);
    }

    private function valid_value($v) {
        if (!preg_match('//u', $v)) return false;
        if ($this->charset !== 'utf8mb4' && preg_match('/[\x{10000}-\x{10FFFF}]/u', $v)) return false;
        return true;
    }

    public function insert($table, $data, $format = null) {
        $this->queries[] = 'INSERT ' . $table;
        if (!isset($this->tables[$table]) || $this->fail_inserts) {
            $this->last_error = 'insert failed';
            return false;
        }
        foreach ($data as $k => $v) {
            if (!$this->valid_value((string) $v)) { $this->last_error = 'bad value'; return false; }
            if ($k === 'context' && strlen($v) > 64) { $this->last_error = 'too long'; return false; }
        }
        $id = ++$this->tables[$table]['auto'];
        $data['id'] = $id;
        $this->tables[$table]['rows'][$id] = $data;
        $this->insert_id = $id;
        $this->rows_affected = 1;
        $this->last_query = 'INSERT INTO ' . $table;
        return 1;
    }

    public function get_var($sql) {
        $this->last_query = $sql;
        $this->queries[] = $sql;
        if (preg_match('/^SHOW TABLES LIKE /', $sql)) {
            $s = $this->strings($sql);
            $name = str_replace(array('\\_', '\\%', '\\\\'), array('_', '%', '\\'), $s[0]);
            return isset($this->tables[$name]) ? $name : null;
        }
        if (preg_match('/^SELECT id FROM `([^`]+)` ORDER BY id DESC LIMIT 1 OFFSET (\d+)$/', $sql, $m)) {
            if (!isset($this->tables[$m[1]])) return null;
            $ids = array_keys($this->tables[$m[1]]['rows']);
            rsort($ids);
            return isset($ids[(int) $m[2]]) ? (string) $ids[(int) $m[2]] : null;
        }
        if (preg_match('/^SELECT COUNT\(\*\) FROM `([^`]+)`$/', $sql, $m)) {
            return isset($this->tables[$m[1]]) ? (string) count($this->tables[$m[1]]['rows']) : null;
        }
        if (preg_match('/^SELECT option_value FROM `wp_options` WHERE option_name = /', $sql)) {
            $s = $this->strings($sql);
            return isset($this->opt_rows[$s[0]]) ? $this->opt_rows[$s[0]] : null;
        }
        throw new Exception('Fake wpdb get_var: unhandled SQL: ' . $sql);
    }

    public function get_results($sql, $output = OBJECT) {
        $this->last_query = $sql;
        $this->queries[] = $sql;
        if (preg_match('/^SELECT id, created_at, context, message FROM `([^`]+)`(?: WHERE (.*))? ORDER BY id DESC LIMIT (\d+)$/s', $sql, $m)) {
            if (!isset($this->tables[$m[1]])) return null;
            $rows = array_values($this->tables[$m[1]]['rows']);
            usort($rows, function ($a, $b) { return $b['id'] - $a['id']; });
            $where = isset($m[2]) ? $m[2] : '';
            if ($where !== '') {
                $s = $this->strings($where);
                $k = 0;
                if (strpos($where, 'created_at >=') !== false) {
                    $since = $s[$k++];
                    $rows = array_values(array_filter($rows, function ($r) use ($since) { return $r['created_at'] >= $since; }));
                }
                if (strpos($where, 'context =') !== false) {
                    $ctx = $s[$k++];
                    $rows = array_values(array_filter($rows, function ($r) use ($ctx) { return strcasecmp($r['context'], $ctx) === 0; }));
                }
            }
            $rows = array_slice($rows, 0, (int) $m[3]);
            $this->last_result = $rows;
            return $rows;
        }
        throw new Exception('Fake wpdb get_results: unhandled SQL: ' . $sql);
    }

    public function query($sql) {
        $this->last_query = $sql;
        $this->queries[] = $sql;
        if (preg_match('/^DELETE FROM `([^`]+)` WHERE id < (\d+)$/', $sql, $m)) {
            $n = 0;
            foreach (array_keys($this->tables[$m[1]]['rows']) as $id) {
                if ($id < (int) $m[2]) { unset($this->tables[$m[1]]['rows'][$id]); $n++; }
            }
            return $n;
        }
        if (preg_match('/^DELETE FROM `([^`]+)`$/', $sql, $m)) {
            $n = count($this->tables[$m[1]]['rows']);
            $this->tables[$m[1]]['rows'] = array();
            return $n;
        }
        if (preg_match('/^INSERT INTO `([^`]+)` \(created_at, context, message\) VALUES /', $sql, $m)) {
            if (!isset($this->tables[$m[1]]) || $this->fail_inserts) return false;
            $s = $this->strings(substr($sql, strpos($sql, 'VALUES')));
            foreach ($s as $v) { if (!$this->valid_value($v)) return false; }
            $n = 0;
            for ($i = 0; $i + 2 < count($s); $i += 3) {
                $id = ++$this->tables[$m[1]]['auto'];
                $this->tables[$m[1]]['rows'][$id] = array('id' => $id, 'created_at' => $s[$i], 'context' => $s[$i + 1], 'message' => $s[$i + 2]);
                $n++;
            }
            $this->insert_id = $id;
            return $n;
        }
        if (preg_match('/^INSERT IGNORE INTO `wp_options`/', $sql)) {
            $s = $this->strings($sql);
            if (isset($this->opt_rows[$s[0]])) return 0;
            $this->opt_rows[$s[0]] = $s[1];
            return 1;
        }
        if (preg_match('/^UPDATE `wp_options` SET option_value = /', $sql)) {
            $s = $this->strings($sql);
            list($val, $name, $old) = $s;
            if (isset($this->opt_rows[$name]) && $this->opt_rows[$name] === $old) {
                $this->opt_rows[$name] = $val;
                return 1;
            }
            return 0;
        }
        if (preg_match('/^DELETE FROM `wp_options` WHERE option_name = /', $sql)) {
            $s = $this->strings($sql);
            unset($this->opt_rows[$s[0]]);
            return 1;
        }
        throw new Exception('Fake wpdb query: unhandled SQL: ' . $sql);
    }

    public function rows($table) {
        return isset($this->tables[$table]) ? array_values($this->tables[$table]['rows']) : array();
    }
}

// ─── More WordPress stand-ins (holds, CLI, Hub tests) ─────────────────────────

if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!defined('WP_PLUGIN_DIR')) define('WP_PLUGIN_DIR', sys_get_temp_dir() . '/hozio-plugins-' . getmypid());

class WP_Error {
    private $code; private $message;
    public function __construct($code = '', $message = '') { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($x) { return $x instanceof WP_Error; }

function sanitize_text_field($s) {
    $s = (string) $s;
    if (!preg_match('//u', $s)) return '';
    $s = strip_tags($s);
    $s = preg_replace('/[\r\n\t ]+/', ' ', $s);
    return trim($s);
}
function sanitize_key($k) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $k)); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return (string) $s; }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function wp_json_encode($d, $o = 0) { return json_encode($d, $o); }
function wp_date($f, $ts) { return gmdate($f, $ts + $GLOBALS['__tz_offset'] * 3600); }
function date_i18n($f, $ts = false) { return gmdate($f, $ts === false ? time() : $ts); }
function current_user_can($cap) { return !empty($GLOBALS['__caps'][$cap]); }
function plugin_basename($f) { return 'hozio-dynamic-tags/hozio-dynamic-tags.php'; }
function get_plugins() { return $GLOBALS['__plugins']; }
function get_site_transient($k) { return isset($GLOBALS['__site_transients'][$k]) ? $GLOBALS['__site_transients'][$k] : false; }
function wp_get_current_user() { return isset($GLOBALS['__user']) ? $GLOBALS['__user'] : new class { public $user_login = ''; public $ID = 0; public function exists() { return false; } }; }
function admin_url($p = '') { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg($args, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); }
function wp_nonce_url($url, $action) { return $url . '&_wpnonce=stub'; }
function apply_filters($hook, $value) {
    $args = func_get_args();
    array_shift($args);
    if (empty($GLOBALS['__actions'][$hook])) return $value;
    $cbs = $GLOBALS['__actions'][$hook];
    usort($cbs, function ($a, $b) { return $a[1] <=> $b[1]; });
    foreach ($cbs as $cb) { $args[0] = call_user_func_array($cb[0], $args); }
    return $args[0];
}
function do_action($hook) {
    $args = func_get_args();
    array_shift($args);
    if (empty($GLOBALS['__actions'][$hook])) return;
    foreach ($GLOBALS['__actions'][$hook] as $cb) { call_user_func_array($cb[0], $args); }
}
function remove_filter($hook, $cb, $prio = 10) {
    if (empty($GLOBALS['__actions'][$hook])) return false;
    foreach ($GLOBALS['__actions'][$hook] as $i => $e) {
        if ($e[0] === $cb && $e[1] === $prio) { unset($GLOBALS['__actions'][$hook][$i]); return true; }
    }
    return false;
}
function has_filter_cb($hook, $cb) {
    foreach ((array) (isset($GLOBALS['__actions'][$hook]) ? $GLOBALS['__actions'][$hook] : array()) as $e) { if ($e[0] === $cb) return true; }
    return false;
}
$GLOBALS['__plugins'] = array();
$GLOBALS['__caps'] = array();
$GLOBALS['__site_transients'] = array();

// ─── Tiny test runner ────────────────────────────────────────────────────────

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;

function check($cond, $label) {
    if ($cond) {
        $GLOBALS['__pass']++;
        echo "  ok   $label\n";
    } else {
        $GLOBALS['__fail']++;
        echo "  FAIL $label\n";
    }
}

function section($name) {
    echo "\n== $name ==\n";
}

function finish() {
    echo "\n{$GLOBALS['__pass']} passed, {$GLOBALS['__fail']} failed\n";
    exit($GLOBALS['__fail'] ? 1 : 0);
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $f;
        if (is_dir($p)) { rrmdir($p); } else { @chmod($p, 0666); @unlink($p); }
    }
    @rmdir($dir);
}
