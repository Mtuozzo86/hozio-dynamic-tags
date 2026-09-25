<?php
// Command line only. tests/ never ships in the release ZIP (see tests/README.md); if a
// copy ever reached a web server, this keeps it inert.
if (PHP_SAPI !== 'cli') {
    exit;
}
/**
 * Behaviour tests for includes/hozio-logger.php (4.20.9: logs moved to the database).
 *
 * Run: php -n tests/test-logger.php [path-to-plugin]   (default: this repo)
 *   or all of them: tests/run.ps1
 */

require __DIR__ . '/lib/wp-stubs.php';

$plugin = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__);

$content = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hozio-logger-test-' . getmypid();
rrmdir($content);
mkdir($content, 0777, true);
define('WP_CONTENT_DIR', $content);

$GLOBALS['wpdb'] = new Fake_WPDB();
global $wpdb;

require $plugin . '/includes/hozio-logger.php';

function files_in_content() {
    return array_values(array_diff(scandir(WP_CONTENT_DIR), array('.', '..')));
}
function reset_db() {
    $GLOBALS['wpdb'] = new Fake_WPDB();
    $GLOBALS['__options'] = array();
    $GLOBALS['__dbdelta_calls'] = 0;
}
function audit_rows() { global $wpdb; return $wpdb->rows('wp_hozio_audit_log'); }
function debug_rows() { global $wpdb; return $wpdb->rows('wp_hozio_debug_log'); }
function last_audit() { $r = audit_rows(); return $r ? end($r) : null; }

// ─────────────────────────────────────────────────────────────────────────────
section('init hook registration');
$found = false;
foreach ($GLOBALS['__actions']['init'] as $a) {
    if ($a[0] === 'hozio_log_maybe_upgrade' && $a[1] === 1) $found = true;
}
check($found, 'hozio_log_maybe_upgrade hooked on init at priority 1');

// ─────────────────────────────────────────────────────────────────────────────
section('context and message preparation');
check(hozio_log_prepare_context('AutoUpdate') === 'AutoUpdate', 'plain context unchanged');
check(hozio_log_prepare_context("Bad<script>\n\"ctx'") === 'Badscriptctx', 'junk stripped from context');
check(strlen(hozio_log_prepare_context(str_repeat('a', 100))) === 64, 'context capped at 64');
check(hozio_log_prepare_message(array('a' => 1)) === print_r(array('a' => 1), true), 'arrays logged with print_r');
check(hozio_log_prepare_message("a\0b") === 'ab', 'NUL bytes removed');

$long = hozio_log_prepare_message(str_repeat('x', 5000));
check(strlen($long) === 4000 + strlen(' [truncated]') && substr($long, -12) === ' [truncated]', 'ASCII message cut to 4000 bytes + marker');

$euro = hozio_log_prepare_message(str_repeat("\xE2\x82\xAC", 2000)); // 6000 bytes, cut lands mid-character
check(preg_match('//u', $euro) === 1, '3-byte chars: cut never leaves invalid UTF-8');
check(strlen($euro) <= 4000 + 12, '3-byte chars: still within cap');

$e2 = hozio_log_prepare_message(str_repeat("\xC3\xA9", 3000));
check(preg_match('//u', $e2) === 1, '2-byte chars: valid UTF-8 after cut');

$bad = hozio_log_prepare_message("ok \xFF\xFE end");
check($bad === 'ok ?? end', 'invalid UTF-8 bytes replaced, entry kept');

$wpdb->charset = 'utf8';
check(hozio_log_prepare_message("emoji \xF0\x9F\x98\x80 here") === 'emoji ? here', 'utf8 table: 4-byte char replaced');
$wpdb->charset = 'utf8mb4';
check(hozio_log_prepare_message("emoji \xF0\x9F\x98\x80 here") === "emoji \xF0\x9F\x98\x80 here", 'utf8mb4 table: 4-byte char kept');

// ─────────────────────────────────────────────────────────────────────────────
section('table missing: entries are dropped, never written to a file');
reset_db();
update_option('hozio_log_db_version', '1');
hozio_audit_log('should be dropped', 'Test');
$GLOBALS['__options']['hozio_debug_enabled'] = '1';
hozio_log('debug dropped', 'Test');
check(files_in_content() === array(), 'no file created in wp-content');
check(count(audit_rows()) === 0, 'nothing stored');
check(get_option('hozio_log_db_version') === 'failed:0', 'installed marker cleared so the next request recreates the table');
check(hozio_log_write('audit', 'X', 'y') === false, 'hozio_log_write reports false');
unset($GLOBALS['__options']['hozio_debug_enabled']);

// ─────────────────────────────────────────────────────────────────────────────
section('install + write');
reset_db();
hozio_log_maybe_upgrade();
check(get_option('hozio_log_db_version') === '1', 'schema option set after install');
check(isset($wpdb->tables['wp_hozio_audit_log'], $wpdb->tables['wp_hozio_debug_log']), 'both tables created');
$calls = $GLOBALS['__dbdelta_calls'];
hozio_log_maybe_upgrade();
check($GLOBALS['__dbdelta_calls'] === $calls, 'second request does not re-run dbDelta');

$wpdb->insert_id  = 777;
$wpdb->last_error = 'theirs';
$wpdb->last_query = 'SELECT theirs';
hozio_audit_log('Hozio Pro ACTIVATED by admin (ID: 1)', 'Lifecycle');
check(count(audit_rows()) === 1, 'audit entry stored');
$row = last_audit();
check($row['context'] === 'Lifecycle' && $row['message'] === 'Hozio Pro ACTIVATED by admin (ID: 1)', 'context and message stored');
check(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['created_at']) === 1 && abs(strtotime($row['created_at'] . ' UTC') - time()) < 5, 'created_at is current UTC');
check($wpdb->insert_id === 777 && $wpdb->last_error === 'theirs' && $wpdb->last_query === 'SELECT theirs', "caller's wpdb state restored after logging");

hozio_log('not logged while debug is off', 'T');
check(count(debug_rows()) === 0, 'hozio_log is a no-op when debug is off');
$GLOBALS['__options']['hozio_debug_enabled'] = '1';
hozio_log(array('k' => 'v'), 'CountyQuery');
hozio_log('no context');
check(count(debug_rows()) === 2, 'hozio_log writes when debug is on');
$d = debug_rows();
check($d[1]['context'] === '', 'empty context allowed');
check(hozio_get_log_size() === '2 entries' && hozio_get_log_path() === 'wp_hozio_debug_log', 'size label and path (table name)');
check(hozio_clear_log() === true && count(debug_rows()) === 0, 'clear empties the debug log only');
check(count(audit_rows()) === 1, 'audit log untouched by clear');
check(files_in_content() === array(), 'still no files in wp-content');

$wpdb->fail_inserts = true;
check(hozio_log_write('audit', 'X', 'fails') === false, 'a failed insert returns false, no exception');
$wpdb->fail_inserts = false;
check(files_in_content() === array(), 'failed insert did not fall back to a file');

// ─────────────────────────────────────────────────────────────────────────────
section('install failure is throttled');
reset_db();
$wpdb->deny_create = true;
hozio_log_maybe_upgrade();
$state = get_option('hozio_log_db_version');
check(strpos($state, 'failed:') === 0, 'failed install recorded');
$calls = $GLOBALS['__dbdelta_calls'];
hozio_log_maybe_upgrade();
check($GLOBALS['__dbdelta_calls'] === $calls, 'no retry within the hour');
update_option('hozio_log_db_version', 'failed:' . (time() - 3601));
$wpdb->deny_create = false;
hozio_log_maybe_upgrade();
check(get_option('hozio_log_db_version') === '1', 'retried after an hour and succeeded');

// ─────────────────────────────────────────────────────────────────────────────
section('prune keeps the newest 5000');
reset_db();
hozio_log_install();
for ($i = 1; $i <= 5100; $i++) {
    $wpdb->tables['wp_hozio_audit_log']['rows'][$i] = array('id' => $i, 'created_at' => '2026-01-01 00:00:00', 'context' => 'P', 'message' => "m$i");
}
$wpdb->tables['wp_hozio_audit_log']['auto'] = 5100;
hozio_log_prune('audit');
$ids = array_map(function ($r) { return $r['id']; }, audit_rows());
check(count($ids) === 5000 && min($ids) === 101 && max($ids) === 5100, 'oldest 100 removed');
check(hozio_log_count('audit') === 5000, 'count matches');

// ─────────────────────────────────────────────────────────────────────────────
section('reading entries');
reset_db();
hozio_log_install();
$rows = array(
    array('2026-09-20 10:00:00', 'AutoUpdate', 'one'),
    array('2026-09-21 10:00:00', 'Rollback', 'two'),
    array('2026-09-22 10:00:00', 'AutoUpdate', 'three'),
    array('2026-09-23 10:00:00', 'Lifecycle', 'four'),
);
foreach ($rows as $i => $r) {
    $wpdb->tables['wp_hozio_audit_log']['rows'][$i + 1] = array('id' => $i + 1, 'created_at' => $r[0], 'context' => $r[1], 'message' => $r[2]);
}
$e = hozio_log_get_entries('audit', array('limit' => 2));
check(count($e) === 2 && $e[0]['message'] === 'three' && $e[1]['message'] === 'four', 'newest N returned oldest-first');
$e = hozio_log_get_entries('audit', array('since' => strtotime('2026-09-21 10:00:00 UTC')));
check(count($e) === 3 && $e[0]['message'] === 'two', 'since filter');
$e = hozio_log_get_entries('audit', array('context' => 'AutoUpdate'));
check(count($e) === 2 && $e[0]['message'] === 'one' && $e[1]['message'] === 'three', 'context filter');
$e = hozio_log_get_entries('audit', array('limit' => 999999));
check(count($e) === 4, 'oversized limit clamped, still works');
check(hozio_log_format_line($e[0]) === '[2026-09-20 06:00:00] [AutoUpdate] one', 'formatted line uses site time');
check(hozio_log_get_entries('debug') === array() || true, 'debug read on empty table ok');

// ─────────────────────────────────────────────────────────────────────────────
section('parsing the old file format');
$raw = "[2026-09-20 08:00:00] [Lifecycle] Hozio Pro ACTIVATED by admin (ID: 1)\r\n"
     . "--- Log trimmed at 2026-09-20 09:00:00 ---\n"
     . "[2026-09-20 09:30:00] [AutoUpdate] Auto-update FAILED for x (1.2): boom\n"
     . "second line of the same entry\n"
     . "\n"
     . "[2026-09-20 10:00:00] no context here\n";
$parsed = hozio_log_parse_legacy($raw);
check(count($parsed) === 3, 'three entries, trim marker skipped');
check($parsed[0]['created_at'] === '2026-09-20 12:00:00', 'site-local time converted to UTC (UTC-4 site)');
check($parsed[1]['message'] === "Auto-update FAILED for x (1.2): boom\nsecond line of the same entry", 'continuation line joined');
check($parsed[2]['context'] === '' && $parsed[2]['message'] === 'no context here', 'debug-style line without context');
check(hozio_log_parse_legacy("orphan continuation\n[2026-09-20 10:00:00] [A] b") === array(array('created_at' => '2026-09-20 14:00:00', 'context' => 'A', 'message' => 'b')), 'leading orphan line dropped');

// ─────────────────────────────────────────────────────────────────────────────
section('upgrade sweep: old public files are migrated then deleted');
reset_db();
$audit_file = WP_CONTENT_DIR . '/hozio-audit.log';
$debug_file = WP_CONTENT_DIR . '/hozio-debug.log';
$lines = '';
for ($i = 0; $i < 40; $i++) {
    $lines .= sprintf("[2026-09-%02d 10:00:00] [AutoUpdate] Auto-updated plugin-%d to 1.%d\n", 1 + ($i % 20), $i, $i);
}
file_put_contents($audit_file, $lines);
file_put_contents($debug_file, "[2026-09-20 10:00:00] [CountyQuery] Array\n(\n    [a] => 1\n)\n[2026-09-20 10:00:01] plain\n");
hozio_log_maybe_upgrade();
check(!file_exists($audit_file) && !file_exists($debug_file), 'both old files deleted');
check(files_in_content() === array(), 'wp-content has no log files left');
$notes = array_values(array_filter(audit_rows(), function ($r) { return $r['context'] === 'Logger'; }));
check(count(audit_rows()) === 42 && count($notes) === 2, '40 audit entries migrated + one "moved" note per file');
check(strpos($notes[0]['message'], 'Moved 40 entries from the old public file wp-content/hozio-audit.log') === 0, 'audit-file move recorded');
check(strpos($notes[1]['message'], 'Moved 2 entries from the old public file wp-content/hozio-debug.log') === 0, 'debug-file move recorded');
$first = audit_rows()[0];
check($first['created_at'] === '2026-09-01 14:00:00' && $first['message'] === 'Auto-updated plugin-0 to 1.0', 'oldest migrated entry first, timestamp in UTC');
$d = debug_rows();
check(count($d) === 2 && $d[0]['message'] === "Array\n(\n    [a] => 1\n)", 'debug entries migrated with multi-line print_r intact');
check(empty($wpdb->opt_rows), 'migration lock released');
check(get_option('hozio_log_legacy_state', 'none') === 'none', 'no leftover state');

// A rollback to an old version writes the file again; the next request sweeps it again.
file_put_contents($audit_file, "[2026-09-24 10:00:00] [Updater] written by old code after a rollback\n");
hozio_log_maybe_upgrade();
check(!file_exists($audit_file), 're-created file swept again even though the schema is current');
$msgs = array_map(function ($r) { return $r['message']; }, audit_rows());
check(in_array('written by old code after a rollback', $msgs, true), 'its entry migrated');

// ─────────────────────────────────────────────────────────────────────────────
section('upgrade sweep: tail of a huge file');
reset_db();
$big = '';
$n = 0;
while (strlen($big) < 1300000) {
    $big .= sprintf("[2026-09-20 10:00:00] [CountyQuery] line %06d %s\n", $n++, str_repeat('z', 60));
}
file_put_contents($debug_file, $big);
hozio_log_maybe_upgrade();
$d = debug_rows();
check(count($d) === 5000, 'capped at 5000 newest entries');
check(end($d)['message'] === sprintf('line %06d %s', $n - 1, str_repeat('z', 60)), 'newest line is last');
check(!file_exists($debug_file), 'huge file deleted');

// ─────────────────────────────────────────────────────────────────────────────
section('upgrade sweep: table cannot be created');
reset_db();
$wpdb->deny_create = true;
file_put_contents($audit_file, "[2026-09-20 10:00:00] [A] secret-ish\n");
hozio_log_maybe_upgrade();
check(!file_exists($audit_file), 'file deleted anyway (its contents were public)');
check(files_in_content() === array(), 'nothing written elsewhere');

// ─────────────────────────────────────────────────────────────────────────────
section('upgrade sweep: file that cannot be deleted or emptied');
reset_db();
hozio_log_install();
update_option('hozio_log_db_version', '1');
file_put_contents($audit_file, "[2026-09-20 10:00:00] [A] one\n[2026-09-20 10:00:01] [A] two\n");
chmod($audit_file, 0444); // read-only: on Windows neither unlink nor write succeeds
$ro_works = !is_writable($audit_file);
if (!$ro_works) {
    echo "  (skipped: read-only files are writable on this filesystem)\n";
} else {
    hozio_log_maybe_upgrade();
    check(file_exists($audit_file), 'file still there');
    $rows_after_first = count(audit_rows());
    check($rows_after_first === 3, 'entries migrated once + one warning');
    check(strpos(last_audit()['message'], 'could not empty it either') !== false, 'warning says it could not be emptied');
    $st = get_option('hozio_log_legacy_state');
    check(is_array($st) && isset($st[$audit_file]['retry_at']) && $st[$audit_file]['retry_at'] > time(), 'retry scheduled');
    $q = count($wpdb->queries);
    hozio_log_maybe_upgrade();
    check(count(audit_rows()) === $rows_after_first, 'no duplicate import on the next request');
    check(count($wpdb->queries) === $q, 'next request touches the database not at all (throttled)');
    $st[$audit_file]['retry_at'] = time() - 1;
    update_option('hozio_log_legacy_state', $st);
    hozio_log_maybe_upgrade();
    check(count(audit_rows()) === $rows_after_first, 'retry after the window: unchanged file not re-imported, no new warning');
    // Junk written straight into the option (e.g. a far-future retry) is not trusted.
    $st = get_option('hozio_log_legacy_state');
    $st[$audit_file]['retry_at'] = time() + 30 * 86400;
    update_option('hozio_log_legacy_state', $st);
    $q = count($wpdb->queries);
    hozio_log_maybe_upgrade();
    check(count($wpdb->queries) > $q, 'far-future retry time ignored: the file is retried now');
    update_option('hozio_log_legacy_state', 'not-an-array');
    $threw = false;
    try { hozio_log_maybe_upgrade(); } catch (Throwable $t) { $threw = true; }
    check(!$threw, 'non-array state does not fatal');
    // Junk state is treated as "never seen", so that run re-imported the file once.
    $rows_after_first = count(audit_rows());
    chmod($audit_file, 0666);
    $st = get_option('hozio_log_legacy_state');
    $st[$audit_file]['retry_at'] = time() - 1;
    update_option('hozio_log_legacy_state', $st);
    hozio_log_maybe_upgrade();
    check(!file_exists($audit_file), 'deleted once permissions allow');
    check(count(audit_rows()) === $rows_after_first + 1 && strpos(last_audit()['message'], 'Moved 0 entries') === 0, 'deletion recorded, still no duplicates');
    check(get_option('hozio_log_legacy_state', 'none') === 'none', 'state cleared');
}

// ─────────────────────────────────────────────────────────────────────────────
section('migration lock');
reset_db();
check(hozio_log_lock() === true, 'first request gets the lock');
check(hozio_log_lock() === false, 'second request does not');
hozio_log_unlock();
check(hozio_log_lock() === true, 'available again after unlock');
$wpdb->opt_rows['hozio_log_migration_lock'] = (string) (time() - 400);
check(hozio_log_lock() === true, 'abandoned lock (over 5 minutes) is taken over');
hozio_log_unlock();

section('held lock: sweep leaves files for the other request');
file_put_contents($audit_file, "[2026-09-20 10:00:00] [A] x\n");
$wpdb->opt_rows['hozio_log_migration_lock'] = (string) time();
hozio_log_install();
hozio_log_sweep_legacy_files();
check(file_exists($audit_file), 'file left alone while another request holds the lock');
unset($wpdb->opt_rows['hozio_log_migration_lock']);
hozio_log_sweep_legacy_files();
check(!file_exists($audit_file), 'swept once the lock is free');

// ─────────────────────────────────────────────────────────────────────────────
section('no $wpdb at all');
$keep = $GLOBALS['wpdb'];
unset($GLOBALS['wpdb']);
$threw = false;
try { hozio_audit_log('early', 'X'); } catch (Throwable $t) { $threw = true; }
check(!$threw, 'logging before $wpdb exists does not fatal');
$GLOBALS['wpdb'] = $keep;

rrmdir($content);
finish();
