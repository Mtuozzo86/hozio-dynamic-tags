<?php
// Command line only. tests/ never ships in the release ZIP (see tests/README.md); if a
// copy ever reached a web server, this keeps it inert.
if (PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Behaviour tests for includes/update-holds.php and includes/wp-cli.php (4.21.0).
 *
 * Run: php -n tests/test-holds.php [path-to-plugin]   (default: this repo)
 *   or all of them: tests/run.ps1
 */

require __DIR__ . '/lib/wp-stubs.php';

$plugin = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__);

$content = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hozio-holds-test-' . getmypid();
rrmdir($content);
mkdir($content, 0777, true);
define('WP_CONTENT_DIR', $content);
define('HOZIO_VERSION', '4.21.0');
define('HOZIO_PLUGIN_FILE', '/srv/htdocs/wp-content/plugins/hozio-dynamic-tags/hozio-dynamic-tags.php');
define('WP_CLI', true);

class WP_CLI_Halt extends Exception {}
class WP_CLI {
    public static $lines = array();
    public static $commands = array();
    public static function line($s) { self::$lines[] = $s; }
    public static function halt($code) { throw new WP_CLI_Halt((string) $code, (int) $code); }
    public static function add_command($name, $class) { self::$commands[$name] = $class; }
}

$GLOBALS['wpdb'] = new Fake_WPDB();
global $wpdb;

require $plugin . '/includes/hozio-logger.php';
require $plugin . '/includes/update-holds.php';
require $plugin . '/includes/wp-cli.php';

hozio_log_install();

$GLOBALS['__plugins'] = array(
    'akismet/akismet.php'                       => array('Name' => 'Akismet', 'Version' => '5.7.2'),
    'hello.php'                                 => array('Name' => 'Hello Dolly', 'Version' => '1.6'),
    'elementor/elementor.php'                   => array('Name' => 'Elementor', 'Version' => '4.3.0'),
    'odd/odd.php'                               => array('Name' => 'Odd', 'Version' => '1.0 beta'),
    'hozio-dynamic-tags/hozio-dynamic-tags.php' => array('Name' => 'Hozio Pro', 'Version' => '4.21.0'),
);

function audit_messages() { global $wpdb; return array_map(function ($r) { return $r['context'] . ': ' . $r['message']; }, $wpdb->rows('wp_hozio_audit_log')); }
function last_audit_msg() { $m = audit_messages(); return $m ? end($m) : ''; }
function reset_holds() { delete_option('hozio_update_holds'); delete_option('hozio_update_freeze'); }
function cli($class, $method, $args, $assoc) {
    WP_CLI::$lines = array();
    $code = 0;
    try { (new $class())->$method($args, $assoc); } catch (WP_CLI_Halt $h) { $code = $h->getCode(); }
    return array($code, count(WP_CLI::$lines) === 1 ? WP_CLI::$lines[0] : implode("\n", WP_CLI::$lines));
}
function item($file, $ver) { return (object) array('plugin' => $file, 'slug' => strtok($file, '/'), 'new_version' => $ver); }

// ─────────────────────────────────────────────────────────────────────────────
section('plugin file validation');
foreach (array('akismet/akismet.php', 'hello.php', 'a-b_c.d/x-y.php') as $ok) {
    check(hozio_hold_valid_plugin_file($ok), "accepts $ok");
}
foreach (array('../x.php', 'a/../b.php', '/abs/x.php', 'a\\b.php', 'a/b/c.php', '.hidden/x.php', 'x.txt', '', 'a/b.php ', "a/b.php\0") as $bad) {
    check(!hozio_hold_valid_plugin_file($bad), 'rejects ' . json_encode($bad));
}
check(!hozio_hold_valid_plugin_file(array('a/b.php')), 'rejects an array');

// ─────────────────────────────────────────────────────────────────────────────
section('until parsing');
$now = time();
$t = hozio_hold_parse_until('+30d', 180);
check(is_int($t) && abs($t - ($now + 30 * 86400)) <= 2, '+30d');
check(is_int(hozio_hold_parse_until('+15m', 7)), '+15m');
check(is_int(hozio_hold_parse_until('+180d', 180)), '+180d is the maximum');
$e = hozio_hold_parse_until('+181d', 180);
check(is_wp_error($e) && $e->get_error_code() === 'until_too_far', '+181d refused');
$e = hozio_hold_parse_until('+8d', 7);
check(is_wp_error($e) && $e->get_error_code() === 'until_too_far', 'freeze: +8d refused');
$e = hozio_hold_parse_until('+0d', 180);
check(is_wp_error($e) && $e->get_error_code() === 'until_in_past', '+0d refused');
$e = hozio_hold_parse_until('2020-01-01', 180);
check(is_wp_error($e) && $e->get_error_code() === 'until_in_past', 'past date refused');
$e = hozio_hold_parse_until(gmdate('Y') . '-02-30', 180);
check(is_wp_error($e) && $e->get_error_code() === 'bad_until', 'impossible date refused');
$d = gmdate('Y-m-d', $now + 10 * 86400);
check(hozio_hold_parse_until($d, 180) === gmmktime(23, 59, 59, (int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4)), 'date means end of that day UTC');
$iso = gmdate('Y-m-d\TH:i\Z', $now + 3600);
check(is_int(hozio_hold_parse_until($iso, 7)), 'ISO time accepted');
foreach (array('tomorrow', '+30', '30d', '+3w', '../etc', '+30d; rm', array('+1d')) as $bad) {
    $e = hozio_hold_parse_until($bad, 180);
    check(is_wp_error($e) && $e->get_error_code() === 'bad_until', 'garbage refused: ' . json_encode($bad));
}

// ─────────────────────────────────────────────────────────────────────────────
section('placing holds: validation');
reset_holds();
$r = hozio_update_hold_place('nope/nope.php', array('reason' => 'x'));
check(is_wp_error($r) && $r->get_error_code() === 'plugin_not_installed', 'unknown plugin file refused');
$r = hozio_update_hold_place('../akismet/akismet.php', array('reason' => 'x'));
check(is_wp_error($r) && $r->get_error_code() === 'invalid_plugin', 'path-like input refused');
$r = hozio_update_hold_place('akismet/akismet.php', array());
check(is_wp_error($r) && $r->get_error_code() === 'reason_required', 'missing reason refused');
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => '   '));
check(is_wp_error($r) && $r->get_error_code() === 'reason_required', 'blank reason refused');
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => str_repeat('a', 501)));
check(is_wp_error($r) && $r->get_error_code() === 'reason_too_long', '501-character reason refused');
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => str_repeat("\xC3\xA9", 500)));
check(!is_wp_error($r), '500 two-byte characters accepted (counted as characters)');
reset_holds();
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => 'x', 'mode' => 'freeze'));
check(is_wp_error($r) && $r->get_error_code() === 'invalid_mode', 'bad mode refused');
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => 'x', 'mode' => 'skip'));
check(is_wp_error($r) && $r->get_error_code() === 'versions_required', 'skip without versions refused');
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => 'x', 'mode' => 'skip', 'versions' => '5.8; drop'));
check(is_wp_error($r) && $r->get_error_code() === 'invalid_versions', 'junk version refused');
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => 'x', 'source' => 'root'));
check(is_wp_error($r) && $r->get_error_code() === 'invalid_source', 'unknown source refused');
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => 'x', 'ref' => 'has space'));
check(is_wp_error($r) && $r->get_error_code() === 'invalid_ref', 'ref with a space refused');
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => 'x', 'ref' => str_repeat('a', 101)));
check(is_wp_error($r) && $r->get_error_code() === 'invalid_ref', '101-character ref refused');
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => 'x', 'until' => '2020-01-01'));
check(is_wp_error($r) && $r->get_error_code() === 'until_in_past', 'past --until refused');
check(get_option('hozio_update_holds', 'none') === 'none', 'nothing stored by any refused call');

// ─────────────────────────────────────────────────────────────────────────────
section('placing, updating, releasing');
reset_holds();
$uuid = 'fix-3f2a9c1e-7b4d-4e8a-9c0f-1a2b3c4d5e6f';
$r = hozio_update_hold_place('akismet/akismet.php', array(
    'reason' => 'Rolled back: <b>5.8</b> fatals in wp-admin', 'until' => '+30d', 'source' => 'orchestrator', 'ref' => $uuid, 'versions' => 'v5.8,5.8',
));
check(!is_wp_error($r) && $r['ok'] && $r['mode'] === 'pin' && $r['held_at'] === '5.7.2' && $r['versions'] === array('5.8') && $r['updated'] === false, 'pin placed (UUID ref accepted, versions de-duplicated)');
$json = json_encode($r, JSON_UNESCAPED_SLASHES);
check(strlen($json) < 250, 'result is compact (' . strlen($json) . ' chars)');
$stored = get_option('hozio_update_holds');
check($stored['akismet/akismet.php']['reason'] === 'Rolled back: 5.8 fatals in wp-admin', 'reason sanitized (tags stripped)');
check(strpos(last_audit_msg(), 'Holds: Hold placed on akismet/akismet.php (pin 5.8, until ') === 0 && strpos(last_audit_msg(), 'by orchestrator') !== false && strpos(last_audit_msg(), $uuid) !== false, 'audit-logged with source and ref');
$created = $stored['akismet/akismet.php']['created_at'];
sleep(1);
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => 'Still broken', 'until' => '+10d', 'source' => 'orchestrator'));
$stored = get_option('hozio_update_holds');
check($r['updated'] === true && count($stored) === 1 && $stored['akismet/akismet.php']['reason'] === 'Still broken', 'running it again updates the one hold');
check($stored['akismet/akismet.php']['created_at'] === $created && $stored['akismet/akismet.php']['updated_at'] > $created, 'created_at kept, updated_at moved');
$r = hozio_update_hold_place('odd/odd.php', array('reason' => 'x'));
check(!is_wp_error($r) && $r['held_at'] === '' && hozio_update_hold_get_active('odd/odd.php') !== null, 'odd installed version stored as empty held_at, hold still valid');

$r = hozio_update_hold_release('hello.php', array('reason' => 'nothing to do'));
check(!is_wp_error($r) && $r['released'] === false, 'releasing an unheld plugin succeeds with released=false');
$r = hozio_update_hold_release('akismet/akismet.php', array());
check(is_wp_error($r) && $r->get_error_code() === 'reason_required', 'release needs a reason');
$r = hozio_update_hold_release('../x.php', array('reason' => 'x'));
check(is_wp_error($r) && $r->get_error_code() === 'invalid_plugin', 'release refuses path-like input');
$r = hozio_update_hold_release('akismet/akismet.php', array('reason' => 'Fixed upstream', 'source' => 'orchestrator'));
check($r['released'] === true && hozio_update_hold_get_active('akismet/akismet.php') === null, 'release lifts the hold');
check(strpos(last_audit_msg(), 'Holds: Hold released on akismet/akismet.php by orchestrator: Fixed upstream') === 0, 'release audit-logged');

// ─────────────────────────────────────────────────────────────────────────────
section('enforcement: pin, skip, expiry');
reset_holds();
hozio_update_hold_place('akismet/akismet.php', array('reason' => 'pin it'));
hozio_update_hold_place('elementor/elementor.php', array('reason' => 'skip bad', 'mode' => 'skip', 'versions' => '4.3.1'));
check(hozio_update_holds_filter(true, item('akismet/akismet.php', '5.8')) === false, 'pin blocks an update Auto-Update All approved');
check(hozio_update_holds_filter(null, item('akismet/akismet.php', '5.8')) === false, 'pin blocks even when nothing approved it');
check(hozio_update_holds_filter(true, item('elementor/elementor.php', '4.3.1')) === false, 'skip blocks a listed version');
check(hozio_update_holds_filter(true, item('elementor/elementor.php', '4.3.2')) === true, 'skip lets another version through');
check(hozio_update_holds_filter(true, item('hello.php', '1.7')) === true, 'unheld plugin passes through');
check(hozio_update_holds_filter(false, item('hello.php', '1.7')) === false, 'unheld plugin keeps a false');
check(hozio_update_hold_blocks('elementor/elementor.php', '') === true, 'skip with an unknown target version blocks (fail safe)');

$h = get_option('hozio_update_holds');
$h['akismet/akismet.php']['created_at'] = time() - 3 * 86400;
$h['akismet/akismet.php']['updated_at'] = time() - 3 * 86400;
$h['akismet/akismet.php']['expires_at'] = time() - 60;
update_option('hozio_update_holds', $h);
check(hozio_update_holds_filter(true, item('akismet/akismet.php', '5.8')) === true, 'expired hold no longer blocks');
$st = hozio_update_holds_status();
$ak = array_values(array_filter($st['holds'], function ($x) { return $x['plugin'] === 'akismet/akismet.php'; }));
check(count($ak) === 1 && $ak[0]['expired'] === true && $st['expired'] === 1, 'expired hold still visible, marked expired');
$h['akismet/akismet.php']['created_at'] = time() - 20 * 86400;
$h['akismet/akismet.php']['updated_at'] = time() - 20 * 86400;
$h['akismet/akismet.php']['expires_at'] = time() - 8 * 86400;
update_option('hozio_update_holds', $h);
$st = hozio_update_holds_status();
check(count($st['holds']) === 1 && $st['holds'][0]['plugin'] === 'elementor/elementor.php' && $st['invalid'] === 0, 'expired more than 7 days: gone from status, not counted invalid');
hozio_update_hold_place('hello.php', array('reason' => 'x'));
check(!array_key_exists('akismet/akismet.php', get_option('hozio_update_holds')), 'next write prunes it for good');

// in a real run, a skip is audit-logged once
$GLOBALS['hozio_running_updates_now'] = true;
$before = count(audit_messages());
hozio_update_holds_filter(true, item('hello.php', '1.7'));
hozio_update_holds_filter(true, item('hello.php', '1.7'));
check(count(audit_messages()) === $before + 1 && strpos(last_audit_msg(), 'AutoUpdate: Skipped hello.php 1.7: held until') === 0, 'skip audit-logged once per run');
unset($GLOBALS['hozio_running_updates_now']);
$before = count(audit_messages());
hozio_update_holds_filter(true, item('elementor/elementor.php', '4.3.1'));
check(count(audit_messages()) === $before, 'Plugins-screen calls (no run) do not log');

// ─────────────────────────────────────────────────────────────────────────────
section('stored junk is ignored and counted');
reset_holds();
update_option('hozio_update_holds', 'junk');
$st = hozio_update_holds_status();
check($st['invalid'] === 1 && $st['holds'] === array(), 'non-array option: 1 invalid, no holds');
check(hozio_update_holds_filter(true, item('akismet/akismet.php', '5.8')) === true, 'junk blocks nothing');
$valid = array('mode' => 'pin', 'versions' => array(), 'held_at' => '5.7.2', 'reason' => 'ok', 'source' => 'wp-cli', 'ref' => '', 'created_at' => time(), 'expires_at' => time() + 86400);
$junk = array(
    'akismet/akismet.php'   => $valid,
    '../evil.php'           => $valid,
    'hello.php'             => array_merge($valid, array('mode' => 'block')),
    'elementor/elementor.php' => array_merge($valid, array('expires_at' => time() + 400 * 86400)),
    'a/a.php'               => array_merge($valid, array('reason' => '<script>x</script>')),
    'b/b.php'               => array_merge($valid, array('created_at' => time() + 86400, 'expires_at' => time() + 2 * 86400)),
    'c/c.php'               => array_merge($valid, array('mode' => 'skip', 'versions' => array())),
    'd/d.php'               => array_merge($valid, array('source' => 'attacker')),
    'e/e.php'               => 'string',
    'f/f.php'               => array_merge($valid, array('versions' => array('1.0; drop'))),
    'g/g.php'               => array_merge($valid, array('created_at' => (string) time(), 'expires_at' => (string) (time() + 3600))),
);
update_option('hozio_update_holds', $junk);
$st = hozio_update_holds_status();
$names = array_map(function ($x) { return $x['plugin']; }, $st['holds']);
check($names === array('akismet/akismet.php', 'g/g.php') && $st['invalid'] === 9, 'only valid entries kept (numeric strings accepted), 9 counted invalid');
check(hozio_update_holds_filter(true, item('elementor/elementor.php', '9.9')) === true, 'a 400-day hold written directly is not trusted');
hozio_update_hold_place('hello.php', array('reason' => 'x'));
$msgs = audit_messages();
check(count(get_option('hozio_update_holds')) === 3 && strpos($msgs[count($msgs) - 2], 'Holds: Discarded 9 invalid entries') === 0, 'next write drops the junk and says so');

// ─────────────────────────────────────────────────────────────────────────────
section('freeze');
reset_holds();
$r = hozio_updates_freeze(array('reason' => 'x'));
check(is_wp_error($r) && $r->get_error_code() === 'until_required', 'until required');
$r = hozio_updates_freeze(array('until' => '+8d', 'reason' => 'x'));
check(is_wp_error($r) && $r->get_error_code() === 'until_too_far', 'more than 7 days refused');
$r = hozio_updates_freeze(array('until' => '+1d'));
check(is_wp_error($r) && $r->get_error_code() === 'reason_required', 'reason required');
check(!hozio_updates_frozen(), 'not frozen after refused calls');
$r = hozio_updates_freeze(array('until' => '+2h', 'reason' => 'Repair in progress', 'source' => 'orchestrator', 'ref' => $uuid));
check(!is_wp_error($r) && $r['ok'] && $r['replaced'] === false && hozio_updates_frozen(), 'frozen');
check(strlen(json_encode($r)) < 200, 'freeze result compact');
check(strpos(last_audit_msg(), 'Freeze: Updates FROZEN until ') === 0, 'freeze audit-logged');
check(hozio_update_holds_filter(true, item('hello.php', '1.7')) === false, 'frozen: every plugin declined');
check(hozio_update_freeze_deny(true) === false, 'frozen: themes/core/translations declined');
check(hozio_update_freeze_disable_updater(false) === true, 'frozen: WordPress updater disabled');
$GLOBALS['hozio_freeze_ignored'] = true;
check(hozio_updates_frozen() === false, 'blocked-reason check can set the freeze aside');
unset($GLOBALS['hozio_freeze_ignored']);
$r = hozio_updates_freeze(array('until' => '+3h', 'reason' => 'longer', 'source' => 'orchestrator'));
check($r['replaced'] === true && strpos(last_audit_msg(), 'Freeze: Updates freeze extended until') === 0, 'freezing again replaces and says so');
$r = hozio_updates_unfreeze(array());
check(is_wp_error($r), 'unfreeze needs a reason');
$r = hozio_updates_unfreeze(array('reason' => 'done', 'source' => 'orchestrator'));
check($r['unfrozen'] === true && !hozio_updates_frozen() && strpos(last_audit_msg(), 'Freeze: Updates UNFROZEN by orchestrator: done (was frozen until') === 0, 'unfrozen and audit-logged');
$r = hozio_updates_unfreeze(array('reason' => 'again'));
check($r['unfrozen'] === false, 'unfreezing when not frozen succeeds with unfrozen=false');
update_option('hozio_update_freeze', array('until' => time() + 30 * 86400, 'created_at' => time(), 'reason' => 'x', 'source' => 'hub', 'ref' => ''));
$fs = hozio_update_freeze_status();
check(!hozio_updates_frozen() && $fs['invalid'] === 1, 'a 30-day freeze written directly is invalid and freezes nothing');
update_option('hozio_update_freeze', array('until' => time() - 60, 'created_at' => time() - 3600, 'reason' => 'x', 'source' => 'hub', 'ref' => ''));
$fs = hozio_update_freeze_status();
check(!hozio_updates_frozen() && $fs['expired'] === true && $fs['active'] === false, 'ended freeze shown as expired');

// ─────────────────────────────────────────────────────────────────────────────
section('redaction');
check(hozio_log_redact('mail admin@client-site.test now') === 'mail [email] now', 'email');
check(hozio_log_redact('from 203.0.113.9 and 10.0.0.1') === 'from [ip] and [ip]', 'IPv4');
check(hozio_log_redact('v6 2001:db8:85a3:0:0:8a2e:370:7334 x') === 'v6 [ip] x', 'IPv6 full');
check(hozio_log_redact('lo ::1 and fe80::1') === 'lo [ip] and [ip]', 'IPv6 compressed');
check(hozio_log_redact('at 12:34:56 in Foo::bar and 4.3.0') === 'at 12:34:56 in Foo::bar and 4.3.0', 'times, Class::method and 3-part versions untouched');
check(hozio_log_redact('') === '', 'empty string');

// ─────────────────────────────────────────────────────────────────────────────
section('wp hozio info');
reset_holds();
update_option('hozio_license_key', 'LicKeyAbCdEf1234567890SECRET');
update_option('hozio_hub_site_token', 'TokQwErTy9876543210zzSECRET');
hozio_update_hold_place('akismet/akismet.php', array('reason' => 'Rolled back by ops@agency.test', 'source' => 'orchestrator'));
$info = hozio_orchestrator_info(true);
$json = json_encode($info, JSON_UNESCAPED_SLASHES);
$keys = array_keys($info);
check($keys[0] === 'contract' && $keys[1] === 'plugin_version' && $keys[2] === 'capabilities', 'contract, plugin_version, capabilities come first');
check($info['contract'] === 1 && in_array('holds', $info['capabilities'], true) && in_array('freeze', $info['capabilities'], true), 'contract 1, holds and freeze capabilities');
check(strlen($json) < 500, 'compact info under 500 characters (' . strlen($json) . ')');
check(strpos(substr($json, 0, 120), '"capabilities":[') !== false, 'capabilities inside the first 120 characters');
check(strpos($json, 'LicKeyAb') === false && strpos($json, 'TokQwErT') === false, 'no licence key or token');
check($info['holds_active'] === 1 && $info['freeze']['active'] === false, 'counts');

// full info needs the patch-status helpers; stand in for them.
function hozio_get_patch_status() {
    return array('excluded' => array(), 'max_per_run' => 3, 'blocked_reason' => '', 'next_run' => time() + 3600, 'git_updater' => false,
        'pending' => array('akismet/akismet.php' => array('name' => 'Akismet', 'from' => '5.7.2', 'to' => '5.8', 'held' => true)),
        'recent' => array(array('plugin' => 'x/x.php', 'name' => 'x', 'version' => '1', 'ok' => false, 'error' => 'mail admin@site.test', 'at' => '2026-09-24 10:00:00')));
}
function hozio_auto_update_all_enabled() { return true; }
function hozio_auto_update_force_enabled() { return false; }
$full = hozio_orchestrator_info(false);
$fj = json_encode($full, JSON_UNESCAPED_SLASHES);
check(array_slice(array_keys($full), 0, 3) === array('contract', 'plugin_version', 'capabilities'), 'full info keeps the same first keys');
foreach (array('self_update', 'auto_update_all', 'freeze', 'holds', 'holds_invalid', 'pending', 'recent', 'license_status', 'hub_connected') as $k) {
    check(array_key_exists($k, $full), "full info has $k");
}
check(strpos($fj, 'LicKeyAb') === false && strpos($fj, 'TokQwErT') === false, 'full info: no licence key or token');
check(strpos($fj, 'ops@agency.test') === false && strpos($fj, 'admin@site.test') === false, 'full info: emails redacted in reasons and errors');
check(json_encode((object) array()) === '{}' && strpos(json_encode(hozio_orchestrator_info(false)['pending']), '{') === 0, 'pending is always a JSON object');

// ─────────────────────────────────────────────────────────────────────────────
section('source and ref redacted in info, holds and status output');
reset_holds();
$r = hozio_update_hold_place('akismet/akismet.php', array('reason' => 'Broke for 203.0.113.9', 'source' => 'admin:owner@example.com', 'ref' => '198.51.100.7'));
check(!is_wp_error($r) && $r['ok'], 'hold with an admin:<email> source and an IP-like ref is accepted');
$r = hozio_updates_freeze(array('until' => '+2h', 'reason' => 'Repair', 'source' => 'admin:owner@example.com', 'ref' => '198.51.100.7'));
check(!is_wp_error($r) && $r['ok'], 'freeze with the same source and ref is accepted');
$leak = function ($s) { return strpos($s, 'owner@example.com') !== false || strpos($s, '198.51.100.7') !== false || strpos($s, '203.0.113.9') !== false; };
$full = hozio_orchestrator_info(false);
$fj   = json_encode($full, JSON_UNESCAPED_SLASHES);
check(!$leak($fj), 'info (full): no login email, no IP-like ref or reason');
check($full['holds'][0]['source'] === 'admin:[email]' && $full['holds'][0]['ref'] === '[ip]', 'info (full): hold source and ref redacted');
check($full['freeze']['source'] === 'admin:[email]' && $full['freeze']['ref'] === '[ip]', 'info (full): freeze source and ref redacted');
check(!$leak(json_encode(hozio_orchestrator_info(true), JSON_UNESCAPED_SLASHES)), 'info --compact: nothing to leak');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'holds', array(), array());
$o = json_decode($out, true);
check($code === 0 && !$leak($out), 'wp hozio updates holds: no login email, no IP-like ref');
check($o['holds'][0]['source'] === 'admin:[email]' && $o['holds'][0]['ref'] === '[ip]' && $o['freeze']['source'] === 'admin:[email]' && $o['freeze']['ref'] === '[ip]', 'wp hozio updates holds: redacted values in place');
list($code, $out) = cli('Hozio_CLI_Command', 'info', array(), array());
check($code === 0 && !$leak($out), 'wp hozio info: no login email, no IP-like ref');
$raw  = hozio_update_holds_status(false);
$rawf = hozio_update_freeze_status(false);
check($raw['holds'][0]['source'] === 'admin:owner@example.com' && $raw['holds'][0]['ref'] === '198.51.100.7' && $rawf['source'] === 'admin:owner@example.com', 'settings panel (redact=false) still sees the real values');
check(hozio_update_holds_status()['holds'][0]['source'] === 'admin:[email]' && hozio_update_freeze_status()['source'] === 'admin:[email]', 'status functions redact by default');
$stored = get_option('hozio_update_holds');
check($stored['akismet/akismet.php']['source'] === 'admin:owner@example.com', 'stored record unchanged (redaction is output-only)');
hozio_updates_unfreeze(array('reason' => 'x'));
reset_holds();

// ─────────────────────────────────────────────────────────────────────────────
section('a trailing newline never passes validation');
check(!hozio_hold_valid_plugin_file("akismet/akismet.php\n"), 'plugin file + newline refused');
check(!hozio_hold_valid_version("1.0\n"), 'version + newline refused');
check(!hozio_hold_valid_source("orchestrator\n") && !hozio_hold_valid_source("admin:x\n"), 'source + newline refused');
check(!hozio_hold_valid_ref("fix-1\n"), 'ref + newline refused');
check(!is_wp_error(hozio_hold_parse_until("+1d\n", 30)), 'until parser trims surrounding whitespace before matching');
check(hozio_hold_int("123\n") === null, 'stored integer + newline refused');
foreach (array(array('lines' => "5\n"), array('category' => "Holds\n"), array('since' => "2026-09-24\n")) as $bad) {
    list($code, $out) = cli('Hozio_CLI_Command', 'log', array(), $bad);
    check($code === 1 && json_decode($out, true)['ok'] === false, 'log refuses ' . json_encode($bad));
}

// ─────────────────────────────────────────────────────────────────────────────
section('wp hozio commands');
reset_holds();
check(WP_CLI::$commands === array('hozio' => 'Hozio_CLI_Command', 'hozio updates' => 'Hozio_CLI_Updates_Command'), 'commands registered');

list($code, $out) = cli('Hozio_CLI_Updates_Command', 'hold', array('akismet/akismet.php'), array('reason' => 'Rolled back: 5.8 fatals', 'until' => '+30d', 'source' => 'orchestrator', 'ref' => $uuid));
$o = json_decode($out, true);
check($code === 0 && $o['ok'] === true && $o['action'] === 'hold' && strpos($out, "\n") === false, 'hold: one line of JSON, exit 0');
check(strlen($out) < 300, 'hold output short (' . strlen($out) . ' chars)');

list($code, $out) = cli('Hozio_CLI_Updates_Command', 'hold', array('nope/nope.php'), array('reason' => 'x'));
$o = json_decode($out, true);
check($code === 1 && $o['ok'] === false && $o['error'] === 'plugin_not_installed', 'unknown plugin: JSON error, exit 1');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'hold', array('akismet/akismet.php'), array('reason' => str_repeat('r', 501)));
check($code === 1 && json_decode($out, true)['error'] === 'reason_too_long', 'reason over 500: exit 1');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'hold', array('akismet/akismet.php'), array('reason' => 'x', 'until' => '2020-01-01'));
check($code === 1 && json_decode($out, true)['error'] === 'until_in_past', 'past --until: exit 1');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'hold', array('../../wp-config.php'), array('reason' => 'x'));
check($code === 1 && json_decode($out, true)['error'] === 'invalid_plugin', 'path-like plugin: exit 1');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'hold', array(), array('reason' => 'x'));
check($code === 1 && json_decode($out, true)['error'] === 'invalid_plugin', 'missing plugin: JSON error, not a prompt');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'hold', array('akismet/akismet.php'), array('reason' => true));
check($code === 1 && json_decode($out, true)['error'] === 'reason_required', 'bare --reason: exit 1');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'holds', array(), array('format' => 'table'));
check($code === 1 && json_decode($out, true)['error'] === 'unsupported_format', 'non-json format refused');

list($code, $out) = cli('Hozio_CLI_Updates_Command', 'holds', array(), array());
$o = json_decode($out, true);
check($code === 0 && count($o['holds']) === 1 && $o['holds_invalid'] === 0 && isset($o['freeze']['active']), 'holds lists holds, invalid count and freeze');

list($code, $out) = cli('Hozio_CLI_Updates_Command', 'freeze', array(), array('until' => '+4h', 'reason' => 'Repair', 'source' => 'orchestrator', 'ref' => $uuid));
check($code === 0 && json_decode($out, true)['action'] === 'freeze' && hozio_updates_frozen(), 'freeze via CLI');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'freeze', array(), array('until' => '+8d', 'reason' => 'x'));
check($code === 1 && json_decode($out, true)['error'] === 'until_too_far', 'freeze over 7 days: exit 1');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'unfreeze', array(), array('reason' => 'done', 'source' => 'orchestrator'));
check($code === 0 && json_decode($out, true)['unfrozen'] === true, 'unfreeze via CLI');
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'release', array('akismet/akismet.php'), array('reason' => 'fixed', 'source' => 'orchestrator'));
check($code === 0 && json_decode($out, true)['released'] === true && strlen($out) < 200, 'release via CLI, short output');

list($code, $out) = cli('Hozio_CLI_Command', 'info', array(), array('compact' => true));
check($code === 0 && strpos($out, '{"contract":1,"plugin_version":"4.21.0","capabilities":[') === 0 && strlen($out) < 500, 'info --compact starts with contract and capabilities');

hozio_audit_log('Contact admin@client.test from 198.51.100.7', 'Test');
list($code, $out) = cli('Hozio_CLI_Command', 'log', array(), array('lines' => '5', 'category' => 'Test'));
$o = json_decode($out, true);
check($code === 0 && $o['count'] === 1 && $o['entries'][0]['message'] === 'Contact [email] from [ip]' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $o['entries'][0]['at']), 'log: filtered by category, redacted, ISO time');
foreach (array(array('lines' => '0'), array('lines' => '5000'), array('lines' => 'abc'), array('since' => 'yesterday'), array('category' => '../x'), array('category' => 'a b')) as $bad) {
    list($code, $out) = cli('Hozio_CLI_Command', 'log', array(), $bad);
    check($code === 1 && json_decode($out, true)['ok'] === false, 'log refuses ' . json_encode($bad));
}
list($code, $out) = cli('Hozio_CLI_Command', 'log', array(), array('since' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600)));
check($code === 0 && json_decode($out, true)['count'] === 0, 'log --since in the future: none');

function hozio_auto_update_history_read(&$invalid = null) { $invalid = 2; return array(array('plugin' => 'a/a.php', 'name' => 'a', 'version' => '1.0', 'ok' => false, 'error' => 'from 203.0.113.9', 'at' => 'x')); }
list($code, $out) = cli('Hozio_CLI_Updates_Command', 'history', array(), array());
$o = json_decode($out, true);
check($code === 0 && $o['invalid'] === 2 && $o['history'][0]['error'] === 'from [ip]', 'history: redacted, invalid counted');

// ─────────────────────────────────────────────────────────────────────────────
section('logger: unknown channel is a no-op');
$n = count($wpdb->rows('wp_hozio_audit_log'));
check(hozio_log_write('audti', 'X', 'typo') === false && count($wpdb->rows('wp_hozio_audit_log')) === $n, 'unknown channel writes nothing');
check(hozio_log_table('nope') === '' && hozio_log_count('nope') === 0 && hozio_log_get_entries('nope') === array(), 'unknown channel has no table');
check(hozio_log_clear('nope') === true && count($wpdb->rows('wp_hozio_audit_log')) === $n, 'clearing an unknown channel leaves the audit log alone');

rrmdir($content);
finish();
