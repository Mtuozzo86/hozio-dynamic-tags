<?php
// Command line only. tests/ never ships in the release ZIP (see tests/README.md); if a
// copy ever reached a web server, this keeps it inert.
if (PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Behaviour tests for the update crash guard and freeze wiring in
 * includes/plugin-auto-updates.php (4.21.0).
 *
 * Run: php -n tests/test-crash-guard.php [path-to-plugin]   (default: this repo)
 *   or all of them: tests/run.ps1
 */

require __DIR__ . '/lib/wp-stubs.php';

$plugin = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__);

$content = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hozio-guard-test-' . getmypid();
rrmdir($content);
mkdir($content, 0777, true);
define('WP_CONTENT_DIR', $content);
define('HOZIO_VERSION', '4.21.0');
define('HOZIO_PLUGIN_FILE', 'x');
@mkdir(ABSPATH, 0777, true);
rrmdir(WP_PLUGIN_DIR);
@mkdir(WP_PLUGIN_DIR, 0777, true);
foreach (array('a', 'b', 'c', 'd') as $p) {
    @mkdir(WP_PLUGIN_DIR . "/$p", 0777, true);
    file_put_contents(WP_PLUGIN_DIR . "/$p/$p.php", '<?php');
}

$GLOBALS['__activated'] = array();
$GLOBALS['__scheduled'] = array();
$GLOBALS['__next_scheduled'] = false;
function activate_plugin($f, $r = '', $net = false, $silent = false) {
    $GLOBALS['__activated'][] = array($f, $net, $silent);
    $a = (array) get_option('active_plugins', array());
    $a[] = $f;
    update_option('active_plugins', $a);
    return null;
}
function is_plugin_active_for_network($f) { return false; }
function wp_next_scheduled($h) { return $GLOBALS['__next_scheduled']; }
function wp_schedule_event($t, $r, $h) { $GLOBALS['__scheduled'][] = $h; return true; }
function get_filesystem_method() { return 'direct'; }
class WP_Automatic_Updater {
    public function is_disabled() { return (bool) apply_filters('automatic_updater_disabled', false); }
    public function is_vcs_checkout($p) { return false; }
}

$GLOBALS['wpdb'] = new Fake_WPDB();
global $wpdb;
require $plugin . '/includes/hozio-logger.php';
require $plugin . '/includes/update-holds.php';
require $plugin . '/includes/plugin-auto-updates.php';
hozio_log_install();

$maint = ABSPATH . '.maintenance';
function last_audit_msg() { global $wpdb; $r = $wpdb->rows('wp_hozio_audit_log'); return $r ? end($r)['message'] : ''; }
function reset_run() {
    unset($GLOBALS['hozio_update_crash_guard_armed']);
    $GLOBALS['hozio_update_run_active'] = false;
    $GLOBALS['hozio_deactivated_during_run'] = array();
    $GLOBALS['__activated'] = array();
    remove_filter('automatic_updater_disabled', 'hozio_updater_off_for_this_run', PHP_INT_MAX);
}

// ─────────────────────────────────────────────────────────────────────────────
section('maintenance mode detection');
@unlink($maint);
check(hozio_maintenance_timestamp() === 0 && !hozio_maintenance_mode_active(), 'no file: not in maintenance');
file_put_contents($maint, '<?php $upgrading = ' . time() . '; ?>');
check(hozio_maintenance_mode_active(), 'fresh file (wp maintenance-mode activate): active');
file_put_contents($maint, '<?php $upgrading = ' . (time() - 11 * 60) . '; ?>');
check(!hozio_maintenance_mode_active(), 'older than 10 minutes: over, as WordPress itself treats it');
file_put_contents($maint, '<?php echo "no timestamp"; ?>');
check(!hozio_maintenance_mode_active(), 'no $upgrading: not active (WordPress agrees)');
@unlink($maint);

// ─────────────────────────────────────────────────────────────────────────────
section('refusal: freeze and deliberate maintenance');
check(hozio_update_run_refusal() === '', 'nothing stops a run');
hozio_updates_freeze(array('until' => '+1h', 'reason' => 'Repair', 'source' => 'orchestrator'));
check(strpos(hozio_update_run_refusal(), 'Updates are frozen until') === 0, 'freeze refuses');
$r = hozio_run_plugin_auto_updates_now(1);
check(isset($r['refused']) && $r['updated'] === 0, 'the run itself refuses while frozen (backstop)');
hozio_updates_unfreeze(array('reason' => 'done'));
file_put_contents($maint, '<?php $upgrading = ' . time() . '; ?>');
check(strpos(hozio_update_run_refusal(), 'maintenance mode') !== false, 'deliberate maintenance refuses');
$r = hozio_run_plugin_auto_updates_now(1);
check(isset($r['refused']) && file_exists($maint), 'run refused, maintenance file untouched');
@unlink($maint);

// ─────────────────────────────────────────────────────────────────────────────
section('scheduled run with maintenance already on');
reset_run();
file_put_contents($maint, '<?php $upgrading = ' . (time() - 5) . '; ?>');
hozio_arm_update_crash_guard();
check(has_filter_cb('automatic_updater_disabled', 'hozio_updater_off_for_this_run'), 'WordPress updater switched off for this request');
check((new WP_Automatic_Updater())->is_disabled() === true, 'core sees the updater as disabled');
check(strpos(last_audit_msg(), 'Skipped the scheduled update run: the site was already in maintenance mode') === 0, 'skip audit-logged');
hozio_disarm_update_crash_guard();
check(file_exists($maint), 'deliberate maintenance file kept after the run');
check(!has_filter_cb('automatic_updater_disabled', 'hozio_updater_off_for_this_run'), 'switch-off removed at the end of the run');
@unlink($maint);

// ─────────────────────────────────────────────────────────────────────────────
section('a run only deletes its own maintenance file');
reset_run();
hozio_arm_update_crash_guard();
$started = $GLOBALS['hozio_update_run_started'];
file_put_contents($maint, '<?php $upgrading = ' . ($started - 100) . '; ?>');
check(hozio_clear_own_maintenance() === false && file_exists($maint), '$upgrading before the run: kept');
file_put_contents($maint, '<?php $upgrading = ' . $started . '; ?>');
check(hozio_clear_own_maintenance() === true && !file_exists($maint), '$upgrading at the run start: deleted');
file_put_contents($maint, '<?php $upgrading = ' . ($started + 3) . '; ?>');
hozio_disarm_update_crash_guard();
check(!file_exists($maint), 'disarm deletes a file written during the run');
reset_run();
hozio_arm_update_crash_guard();
file_put_contents($maint, 'no stamp');
touch($maint, time() - 3600);
clearstatcache();
check(hozio_clear_own_maintenance() === false && file_exists($maint), 'no stamp, modified before the run: kept');
touch($maint, time() + 1);
clearstatcache();
check(hozio_clear_own_maintenance() === true, 'no stamp, modified during the run: deleted');
hozio_disarm_update_crash_guard();
reset_run();
file_put_contents($maint, '<?php $upgrading = ' . time() . '; ?>');
unset($GLOBALS['hozio_update_run_started']);
check(hozio_clear_own_maintenance() === false && file_exists($maint), 'no run started: never deletes');
@unlink($maint);

section('maintenance switched on in the same second the run starts');
reset_run();
file_put_contents($maint, '<?php $upgrading = ' . time() . '; ?>');
hozio_arm_update_crash_guard();
$GLOBALS['hozio_update_run_started'] = hozio_maintenance_timestamp(); // force the same second
hozio_disarm_update_crash_guard();
check(file_exists($maint), 'a file that was there before the run and is unchanged is kept, even with the same timestamp');
reset_run();
file_put_contents($maint, '<?php $upgrading = ' . (time() - 5) . '; ?>');
hozio_arm_update_crash_guard();
file_put_contents($maint, '<?php $upgrading = ' . ($GLOBALS['hozio_update_run_started'] + 1) . '; ?>'); // rewritten during the run
check(hozio_clear_own_maintenance() === true, 'a file rewritten during the run counts as the run\'s own');
hozio_disarm_update_crash_guard();
@unlink($maint);

// ─────────────────────────────────────────────────────────────────────────────
section('only plugins switched off in THIS request during the run come back');
reset_run();
update_option('active_plugins', array('a/a.php', 'b/b.php', 'c/c.php', 'd/d.php'));
hozio_arm_update_crash_guard();
// This request's run switches b off (as core's silent pre-upgrade deactivation would).
update_option('active_plugins', array('a/a.php', 'c/c.php', 'd/d.php'));
do_action('update_option_active_plugins', array('a/a.php', 'b/b.php', 'c/c.php', 'd/d.php'), array('a/a.php', 'c/c.php', 'd/d.php'));
// Another request (the orchestrator, a second shell) switches d off. That write never
// passes through this request's hooks; this request only sees the result.
update_option('active_plugins', array('a/a.php', 'c/c.php'));
hozio_disarm_update_crash_guard();
$acts = array_map(function ($x) { return $x[0]; }, $GLOBALS['__activated']);
check($acts === array('b/b.php'), 'b (this run) reactivated; d (someone else) left off');
check($GLOBALS['__activated'][0][2] === true, 'reactivation is silent');
check(!in_array('d/d.php', get_option('active_plugins'), true), 'd stays deactivated');
check(strpos(last_audit_msg(), 'Reactivated b/b.php') === 0, 'reactivation audit-logged');
$GLOBALS['__activated'] = array();
hozio_restore_deactivated_plugins();
check($GLOBALS['__activated'] === array(), 'restore does not repeat (disarm then shutdown)');

section('held and recovery-paused plugins are never switched back on');
reset_run();
$GLOBALS['__plugins'] = array('a/a.php' => array('Version' => '1'), 'b/b.php' => array('Version' => '1'), 'c/c.php' => array('Version' => '1'));
hozio_update_hold_place('a/a.php', array('reason' => 'crashes', 'source' => 'orchestrator'));
$GLOBALS['_paused_plugins'] = array('b' => array('type' => 1));
update_option('active_plugins', array('a/a.php', 'b/b.php', 'c/c.php'));
hozio_arm_update_crash_guard();
update_option('active_plugins', array());
do_action('update_option_active_plugins', array('a/a.php', 'b/b.php', 'c/c.php'), array());
hozio_disarm_update_crash_guard();
$acts = array_map(function ($x) { return $x[0]; }, $GLOBALS['__activated']);
check($acts === array('c/c.php'), 'only c reactivated');
global $wpdb;
$msgs = array_map(function ($r) { return $r['message']; }, $wpdb->rows('wp_hozio_audit_log'));
check(count(preg_grep('/^Left a\/a\.php switched off after the update: it is held/', $msgs)) === 1, 'held plugin left off, logged');
check(count(preg_grep('/^Left b\/b\.php switched off after the update: WordPress recovery mode paused it/', $msgs)) === 1, 'paused plugin left off, logged');
unset($GLOBALS['_paused_plugins']);

section('deactivations outside a run are not tracked');
reset_run();
hozio_arm_update_crash_guard();
hozio_disarm_update_crash_guard();
do_action('update_option_active_plugins', array('c/c.php'), array());
check(empty($GLOBALS['hozio_deactivated_during_run']), 'hook ignores changes after the run ended');

// ─────────────────────────────────────────────────────────────────────────────
section('freeze wiring');
$init = null;
foreach ($GLOBALS['__actions']['init'] as $e) { if ($e[1] === 20 && $e[0] instanceof Closure) { $init = $e[0]; } }
$force = null;
foreach ($GLOBALS['__actions']['automatic_updater_disabled'] as $e) { if ($e[0] instanceof Closure) { $force = $e[0]; } }
check($init !== null && $force !== null, 'found the reschedule hook and the force filter');
$GLOBALS['__scheduled'] = array();
$GLOBALS['__next_scheduled'] = false;
$init();
check($GLOBALS['__scheduled'] === array('wp_maybe_auto_update'), 'not frozen: missing schedule restored');
hozio_updates_freeze(array('until' => '+1h', 'reason' => 'Repair', 'source' => 'orchestrator'));
$GLOBALS['__scheduled'] = array();
$init();
check($GLOBALS['__scheduled'] === array(), 'frozen: the reschedule hook does nothing');
update_option('hozio_auto_update_force', '1');
check($force(true) === true, 'frozen: "force updates on" cannot switch the updater back on');
check((new WP_Automatic_Updater())->is_disabled() === true, 'frozen: updater disabled whatever order the filters run in');
check(hozio_auto_update_blocked_reason() === '', 'a freeze is not reported as an environment blocker');
hozio_updates_unfreeze(array('reason' => 'done'));
check($force(true) === false, 'not frozen: force works as before');
update_option('hozio_auto_update_force', '0');

// ─────────────────────────────────────────────────────────────────────────────
section('pending counts and patch status');
$GLOBALS['__plugins'] = array('a/a.php' => array('Name' => 'A', 'Version' => '1.0'), 'b/b.php' => array('Name' => 'B', 'Version' => '1.0'));
$GLOBALS['__site_transients']['update_plugins'] = (object) array('response' => array(
    'a/a.php' => (object) array('new_version' => '1.1'),
    'b/b.php' => (object) array('new_version' => '1.1'),
));
delete_option('hozio_update_holds');
hozio_update_hold_place('a/a.php', array('reason' => 'x'));
$c = hozio_count_pending_updates();
check($c['pending'] === 2 && $c['held'] === 1 && $c['eligible'] === 1, 'held update counted as held, not eligible');
$ps = hozio_get_patch_status();
check($ps['pending']['a/a.php']['held'] === true && $ps['pending']['b/b.php']['held'] === false, 'pending entries marked held');
check(count($ps['holds']) === 1 && $ps['holds_invalid'] === 0 && $ps['freeze']['active'] === false, 'heartbeat carries holds, holds_invalid, freeze');
hozio_update_hold_place('a/a.php', array('reason' => 'Broke for 203.0.113.9', 'source' => 'admin:owner@example.com', 'ref' => '198.51.100.7'));
hozio_updates_freeze(array('until' => '+1h', 'reason' => 'Repair', 'source' => 'admin:owner@example.com', 'ref' => '198.51.100.7'));
$ps  = hozio_get_patch_status();
$psj = json_encode($ps, JSON_UNESCAPED_SLASHES);
check(strpos($psj, 'owner@example.com') === false && strpos($psj, '198.51.100.7') === false && strpos($psj, '203.0.113.9') === false,
    'heartbeat: login email and IP-like ref redacted in holds and freeze');
check($ps['holds'][0]['source'] === 'admin:[email]' && $ps['holds'][0]['ref'] === '[ip]'
    && $ps['freeze']['source'] === 'admin:[email]' && $ps['freeze']['ref'] === '[ip]', 'heartbeat: redacted values in place');
$ref = hozio_update_run_refusal();
check(strpos($ref, 'Updates are frozen') === 0 && strpos($ref, 'owner@example.com') === false, 'run refusal message redacted');
hozio_updates_unfreeze(array('reason' => 'done'));
delete_option('hozio_update_holds');
hozio_update_hold_place('a/a.php', array('reason' => 'x'));
update_option('hozio_auto_update_history', array(array('plugin' => 'a/a.php', 'name' => 'a', 'version' => '1', 'ok' => true, 'error' => '', 'at' => 'x'), 'junk', array('plugin' => array())));
$inv = 0;
$h = hozio_auto_update_history_read($inv);
check(count($h) === 1 && $inv === 2, 'history validated on read (2 junk entries dropped)');

// ─────────────────────────────────────────────────────────────────────────────
section('per-run cap is not used up by a held plugin');
delete_option('hozio_update_holds');
delete_option('hozio_auto_update_exclude');
hozio_update_hold_place('a/a.php', array('reason' => 'held'));
$GLOBALS['hozio_running_updates_now'] = true;
$GLOBALS['hozio_update_run_token'] = 'run-cap-test';
add_filter('hozio_max_plugin_auto_updates_per_run', function () { return 1; }, PHP_INT_MAX);
$ia = (object) array('plugin' => 'a/a.php', 'slug' => 'a', 'new_version' => '1.1');
$ib = (object) array('plugin' => 'b/b.php', 'slug' => 'b', 'new_version' => '1.1');
$ra = apply_filters('auto_update_plugin', null, $ia);
$rb = apply_filters('auto_update_plugin', null, $ib);
check($ra === false, 'held plugin declined');
check($rb === true, 'the next plugin still gets the run\'s one slot');
unset($GLOBALS['hozio_running_updates_now'], $GLOBALS['hozio_update_run_token']);

rrmdir($content);
rrmdir(WP_PLUGIN_DIR);
finish();
