<?php
// Command line only. tests/ never ships in the release ZIP (see tests/README.md); if a
// copy ever reached a web server, this keeps it inert.
if (PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Hub command tests: update_option deny list, hold/freeze commands, update_plugin vs holds,
 * rollback hold, temporary support login (4.21.0).
 *
 * Run: php -n tests/test-hub-deny.php [path-to-plugin]   (default: this repo)
 *   or all of them: tests/run.ps1
 */

require __DIR__ . '/lib/wp-stubs.php';

$plugin = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__);

$content = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hozio-hub-test-' . getmypid();
rrmdir($content);
mkdir($content, 0777, true);
define('WP_CONTENT_DIR', $content);
define('HOZIO_VERSION', '4.21.0');
define('HOZIO_PLUGIN_FILE', 'x');

// ── users ──
$GLOBALS['__users'] = array();
$GLOBALS['__usermeta'] = array();
$GLOBALS['__next_uid'] = 10;
function get_users($args) { return array((object) array('ID' => 1)); }
function wp_set_current_user($id) { return true; }
function get_user_by($field, $v) { foreach ($GLOBALS['__users'] as $u) { if ($u->user_login === $v) return $u; } return false; }
function wp_insert_user($d) { $id = $GLOBALS['__next_uid']++; $GLOBALS['__users'][$id] = (object) array('ID' => $id, 'user_login' => $d['user_login'], 'user_email' => $d['user_email']); return $id; }
function wp_set_password($p, $id) { $GLOBALS['__pw'][$id] = $p; }
function update_user_meta($id, $k, $v) { $GLOBALS['__usermeta'][$id][$k] = $v; }
function get_user_meta($id, $k, $single) { return isset($GLOBALS['__usermeta'][$id][$k]) ? $GLOBALS['__usermeta'][$id][$k] : ''; }
function wp_generate_password($n) { return str_repeat('p', $n); }
function wp_login_url() { return 'https://example.test/wp-login.php'; }
function wp_delete_user($id, $reassign) { unset($GLOBALS['__users'][$id]); return true; }

// ── update machinery stand-ins ──
$GLOBALS['__runs'] = 0;
$GLOBALS['__rollback'] = null;
$GLOBALS['__version'] = '4.21.0';
function hozio_run_plugin_auto_updates_now($max) { $GLOBALS['__runs']++; return array('updated' => 0, 'failed' => 0, 'messages' => array()); }
function hozio_update_run_refusal() { $f = hozio_update_freeze_read(); return $f['active'] ? 'Updates are frozen' : ''; }
function hozio_auto_update_blocked_reason() { return ''; }
function hozio_auto_update_all_enabled() { return true; }
function hozio_plugin_managed_by_git_updater($f) { return false; }
function hozio_get_plugin_version() { return $GLOBALS['__version']; }
function hozio_perform_rollback($v, $hub) { $GLOBALS['__rollback'] = $v; return array('success' => true, 'message' => 'ok', 'version' => $v); }

$GLOBALS['wpdb'] = new Fake_WPDB();
require $plugin . '/includes/hozio-logger.php';
require $plugin . '/includes/update-holds.php';
require $plugin . '/includes/hub-command-executor.php';
hozio_log_install();

$GLOBALS['__plugins'] = array(
    'akismet/akismet.php'                       => array('Name' => 'Akismet', 'Version' => '5.7.2'),
    'hozio-dynamic-tags/hozio-dynamic-tags.php' => array('Name' => 'Hozio Pro', 'Version' => '4.21.0'),
);
function hub($type, $payload) { return Hozio_Command_Executor::execute($type, $payload); }
function last_audit_msg() { global $wpdb; $r = $wpdb->rows('wp_hozio_audit_log'); return $r ? end($r)['message'] : ''; }

// ─────────────────────────────────────────────────────────────────────────────
section('update_option deny list');
foreach (array('hozio_log_db_version', 'hozio_log_legacy_state', 'hozio_update_holds', 'hozio_update_freeze',
               'hozio_Update_Holds', 'hozio_update_holds ', 'hozio_HUB_site_token', 'hozio_hub_site_token', 'HOZIO_update_holds',
               "hozio_update_holds\t", 'hozio_update_holds%', "hozio_update_holds\n", "hozio_hub_site_token\n",
               "hozio_auto_update_all_plugins\n") as $opt) {
    $r = hub('update_option', array('option_name' => $opt, 'option_value' => 'x'));
    check($r['success'] === false && !array_key_exists($opt, $GLOBALS['__options']), 'refused: ' . json_encode($opt));
}
$r = hub('update_option', array('option_name' => array('hozio_x'), 'option_value' => 'x'));
check($r['success'] === false, 'refused: non-string name');
$r = hub('update_option', array('option_name' => 'hozio_auto_update_all_plugins', 'option_value' => '0'));
check($r['success'] === true && $GLOBALS['__options']['hozio_auto_update_all_plugins'] === '0', 'ordinary hozio_ option still allowed');
$r = hub('update_option', array('option_name' => 'hozio_trustindex-slider', 'option_value' => 'x'));
check($r['success'] === true, 'hyphenated hozio_ option still allowed');
$r = hub('update_option', array('option_name' => 'blogname', 'option_value' => 'x'));
check($r['success'] === false, 'non-hozio option still refused');

// ─────────────────────────────────────────────────────────────────────────────
section('hold / release / freeze / unfreeze commands');
$r = hub('hold_plugin_updates', array('plugin' => 'akismet/akismet.php', 'reason' => 'Broken 5.8', 'until' => '+10d', 'source' => 'orchestrator', 'ref' => 'hub-1'));
check($r['success'] === true && $r['data']['action'] === 'hold', 'hold placed');
$h = hozio_update_hold_get_active('akismet/akismet.php');
check($h['source'] === 'hub', 'source is always hub, whatever the payload says');
$r = hub('hold_plugin_updates', array('plugin' => '../../wp-config.php', 'reason' => 'x'));
check($r['success'] === false && $r['code'] === 'invalid_plugin', 'path-like plugin refused');
$r = hub('hold_plugin_updates', array('plugin' => 'akismet/akismet.php', 'reason' => array('x')));
check($r['success'] === false && $r['code'] === 'reason_required', 'non-string reason refused');
$r = hub('freeze_updates', array('until' => '+30d', 'reason' => 'x'));
check($r['success'] === false && $r['code'] === 'until_too_far', 'freeze over 7 days refused');

// ─────────────────────────────────────────────────────────────────────────────
section('update_plugin respects holds, even with force');
$GLOBALS['__site_transients']['update_plugins'] = (object) array('response' => array('akismet/akismet.php' => (object) array('new_version' => '5.8')));
$GLOBALS['__runs'] = 0;
$r = hub('update_plugin', array('plugin' => 'akismet/akismet.php', 'force' => true));
check($r['success'] === false && strpos($r['error'], 'is held until') !== false && $GLOBALS['__runs'] === 0, 'held plugin refused with force; no run');
check(strpos(last_audit_msg(), 'Refused a Hub update of akismet/akismet.php (forced)') === 0, 'refusal audit-logged');
$r = hub('release_plugin_updates', array('plugin' => 'akismet/akismet.php', 'reason' => 'fixed'));
check($r['success'] === true && $r['data']['released'] === true, 'release_plugin_updates lifts it');
$r = hub('update_plugin', array('plugin' => 'akismet/akismet.php', 'force' => true));
check($GLOBALS['__runs'] === 1, 'released plugin: the run goes ahead');

hozio_update_hold_place('akismet/akismet.php', array('reason' => 'skip bad', 'mode' => 'skip', 'versions' => '5.9'));
$GLOBALS['__runs'] = 0;
hub('update_plugin', array('plugin' => 'akismet/akismet.php'));
check($GLOBALS['__runs'] === 1, 'skip hold on another version: the run goes ahead (5.8 is not skipped)');
hozio_update_hold_release('akismet/akismet.php', array('reason' => 'x'));

$r = hub('freeze_updates', array('until' => '+2h', 'reason' => 'Repair'));
check($r['success'] === true && hozio_updates_frozen(), 'freeze_updates');
$GLOBALS['__runs'] = 0;
$r1 = hub('update_plugin', array('plugin' => 'akismet/akismet.php', 'force' => true));
$r2 = hub('run_plugin_updates', array());
check($r1['success'] === false && $r2['success'] === false && $GLOBALS['__runs'] === 0, 'frozen: update_plugin (forced) and run_plugin_updates refused');
$r = hub('rollback_plugin', array('version' => '4.20.9'));
check($r['success'] === false && $GLOBALS['__rollback'] === null, 'frozen: rollback_plugin refused');
$r = hub('unfreeze_updates', array('reason' => 'done'));
check($r['success'] === true && !hozio_updates_frozen(), 'unfreeze_updates');

// ─────────────────────────────────────────────────────────────────────────────
section('refusal messages sent back to the Hub are redacted');
hozio_update_hold_place('akismet/akismet.php', array('reason' => 'Broke for 203.0.113.9', 'source' => 'admin:owner@example.com', 'ref' => '198.51.100.7'));
$GLOBALS['__runs'] = 0;
$r = hub('update_plugin', array('plugin' => 'akismet/akismet.php', 'force' => true));
check($r['success'] === false && $GLOBALS['__runs'] === 0, 'held (admin source): refused');
check(strpos($r['error'], 'owner@example.com') === false && strpos($r['error'], '203.0.113.9') === false
    && strpos($r['error'], 'admin:[email]') !== false, 'hold refusal: login email and IP redacted');
hozio_update_hold_release('akismet/akismet.php', array('reason' => 'x'));
// The freeze refusal text comes from hozio_update_run_refusal(), stubbed in this file; the
// real one's redaction is tested in test-crash-guard.php.

// ─────────────────────────────────────────────────────────────────────────────
section('Hub rollback of Hozio Pro: downgrade places a hold');
delete_option('hozio_auto_updates_enabled');
$GLOBALS['__version'] = '4.21.0';
$r = hub('rollback_plugin', array('version' => '4.20.9'));
$h = hozio_update_hold_get_active('hozio-dynamic-tags/hozio-dynamic-tags.php');
check($r['success'] === true && $h && $h['mode'] === 'pin' && $h['source'] === 'hub' && $h['versions'] === array('4.21.0'), 'pin hold on Hozio Pro, recording the version that was rolled back');
check(abs($h['expires_at'] - (time() + 30 * 86400)) < 5, 'default 30-day expiry');
check(get_option('hozio_auto_updates_enabled', 'unset') === 'unset', 'auto-updates NOT forced back on');
check(isset($r['data']['hold']['ok']) && $r['data']['hold']['ok'] === true, 'hold reported in the result');
check(!array_key_exists('hold_released', $r['data']), 'a downgrade does not report hold_released');

// ─────────────────────────────────────────────────────────────────────────────
section('Hub install of the same or a newer version releases the Hub\'s hold (4.21.1)');
$hz = 'hozio-dynamic-tags/hozio-dynamic-tags.php';
$GLOBALS['__version'] = '4.20.9';
$r = hub('rollback_plugin', array('version' => '4.21.0'));
check($r['success'] === true && $r['data']['hold_released'] === true && hozio_update_hold_get_active($hz) === null, 'downgrade hold (source hub) released by the upgrade; hold_released true');
check(get_option('hozio_auto_updates_enabled') === '1', 'auto-updates on, as before');
check(strpos(last_audit_msg(), 'Hold released on hozio-dynamic-tags/hozio-dynamic-tags.php by hub: Hub installed Hozio Pro 4.21.0 (was 4.20.9)') === 0, 'release audit-logged');
$r = hub('rollback_plugin', array('version' => '4.21.0'));
check($r['success'] === true && $r['data']['hold_released'] === false, 'nothing to release: hold_released false');

hozio_update_hold_place($hz, array('reason' => 'Repair in progress', 'source' => 'orchestrator', 'ref' => 'fix-1'));
$GLOBALS['__version'] = '4.21.0';
$r = hub('rollback_plugin', array('version' => '4.21.0'));
$h = hozio_update_hold_get_active($hz);
check($r['success'] === true && $r['data']['hold_released'] === false && $h && $h['source'] === 'orchestrator', 'an orchestrator hold survives a Hub install of the same version');
$r = hub('rollback_plugin', array('version' => '4.22.0'));
$h = hozio_update_hold_get_active($hz);
check($r['data']['hold_released'] === false && $h && $h['source'] === 'orchestrator', 'and a Hub upgrade');
hozio_update_hold_place($hz, array('reason' => 'Pinned by hand', 'source' => 'admin:someone'));
$r = hub('rollback_plugin', array('version' => '4.22.0'));
check($r['data']['hold_released'] === false && hozio_update_hold_get_active($hz)['source'] === 'admin:someone', 'a wp-admin hold survives too');
hozio_update_hold_release($hz, array('reason' => 'x'));

// ─────────────────────────────────────────────────────────────────────────────
section('first run of a newer release releases an outgrown Hub pin (4.21.1)');
$GLOBALS['__plugins'][$hz]['Version'] = '4.20.9';   // on disk after the downgrade
$GLOBALS['__version'] = '4.21.0';
hub('rollback_plugin', array('version' => '4.20.9'));
$h = hozio_update_hold_get_active($hz);
check($h && $h['source'] === 'hub' && $h['held_at'] === '4.20.9' && $h['versions'] === array('4.21.0'), 'setup: Hub pin at 4.20.9, naming 4.21.0 as broken');
check(hozio_update_hold_release_stale_hub_pin('4.20.9') === false && hozio_update_hold_get_active($hz), 'same version as the pin: kept');
check(hozio_update_hold_release_stale_hub_pin('4.21.0') === false && hozio_update_hold_get_active($hz), 'the release it names as broken: kept');
check(hozio_update_hold_release_stale_hub_pin('4.20.1') === false && hozio_update_hold_get_active($hz), 'an older release: kept');
check(hozio_update_hold_release_stale_hub_pin('not a version!') === false && hozio_update_hold_get_active($hz), 'junk version: kept');
check(hozio_update_hold_release_stale_hub_pin('4.21.1') === true && hozio_update_hold_get_active($hz) === null, 'a newer release not named as broken: released');
check(strpos(last_audit_msg(), 'Hold released on hozio-dynamic-tags/hozio-dynamic-tags.php by hub: Released automatically: Hozio Pro 4.21.1 is running, newer than the 4.20.9 this hold pinned') === 0, 'automatic release audit-logged');
hozio_update_hold_place($hz, array('reason' => 'Repair in progress', 'source' => 'orchestrator'));
check(hozio_update_hold_release_stale_hub_pin('4.21.1') === false && hozio_update_hold_get_active($hz)['source'] === 'orchestrator', 'an orchestrator hold is never released on a version bump');
hozio_update_hold_release($hz, array('reason' => 'x'));
check(hozio_update_hold_release_stale_hub_pin('4.21.1') === false, 'no hold: nothing to do');
$GLOBALS['__plugins'][$hz]['Version'] = '4.21.0';

// ─────────────────────────────────────────────────────────────────────────────
section('temporary support login never touches hoziowpadmin');
$GLOBALS['__users'][2] = (object) array('ID' => 2, 'user_login' => 'hoziowpadmin', 'user_email' => 'ops@example.test');
$GLOBALS['__pw'] = array();
$r = hub('create_admin_login', array('username' => 'hoziowpadmin'));
check($r['success'] === true && $r['data']['username'] === 'hozio-hub-support' && $r['data']['user_id'] !== 2, 'creates hozio-hub-support (payload username ignored)');
check(!isset($GLOBALS['__pw'][2]), 'hoziowpadmin password untouched');
check(strpos(last_audit_msg(), 'Hub created the temporary support login hozio-hub-support') === 0 && strpos(last_audit_msg(), 'pppp') === false, 'audit-logged, no password in the log');
$id = $r['data']['user_id'];
$r = hub('create_admin_login', array());
check($r['success'] === true && $r['data']['user_id'] === $id && isset($GLOBALS['__pw'][$id]), 'second call resets its own account');
$r = hub('remove_admin_login', array());
check($r['success'] === true && !isset($GLOBALS['__users'][$id]) && isset($GLOBALS['__users'][2]), 'removes hozio-hub-support, hoziowpadmin still there');
$r = hub('remove_admin_login', array());
check($r['success'] === false, 'nothing to remove: fails, hoziowpadmin untouched');
check(isset($GLOBALS['__users'][2]), 'hoziowpadmin survives');
$GLOBALS['__users'][30] = (object) array('ID' => 30, 'user_login' => 'hozio-hub-support', 'user_email' => 'someone@example.test');
$r1 = hub('create_admin_login', array());
$r2 = hub('remove_admin_login', array());
check($r1['success'] === false && $r2['success'] === false && isset($GLOBALS['__users'][30]) && !isset($GLOBALS['__pw'][30]), 'an unrelated account with that name is left alone');

rrmdir($content);
finish();
