<?php
/**
 * Live robots.txt Template
 *
 * Every live Hozio site should serve the standard robots.txt, with the
 * Sitemap line pointing at its own domain in the form the site actually uses
 * (www or not - taken from the WordPress Site Address, never guessed).
 *
 * This watches whether the live file matches, warns when it does not, and
 * provides an editor plus an "apply the standard template" button in the
 * Hozio Pro settings. NOTHING here writes a file on its own: the daily cron
 * only evaluates, and every write happens from a button an administrator
 * clicked. The warning banner carries no fix action - it links to the
 * settings section where the file can be reviewed and edited.
 *
 * Division of labour with the Staging Index Guard (staging-index-guard.php),
 * whose helpers this file reuses:
 *   - staging sites: SIG enforces "Disallow: /" - this file does nothing there.
 *   - a LIVE site that BLOCKS crawlers: SIG's red banner and restore flow own
 *     that emergency; this check stays silent so two banners never argue.
 *   - a live site serving the wrong/permissive/foreign robots.txt: this file.
 *
 * COST: page loads read one autoloaded option. Evaluation reuses the daily
 * HTTP fetch SIG already makes - no request of its own is ever added.
 *
 * @package Hozio Pro
 * @since 4.20.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * The template and what "expected" means
 * ---------------------------------------------------------------------- */

function hozio_rt_enabled() {
    return '1' === get_option('hozio_rt_enabled', '1');
}

/**
 * The standard live-site robots.txt, with this site's own domain in the
 * Sitemap line. The host comes from the Site Address verbatim, so a www site
 * gets www and a bare-domain site does not - never guessed.
 */
function hozio_rt_template() {
    $host   = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    $scheme = ('http' === wp_parse_url(home_url(), PHP_URL_SCHEME)) ? 'http' : 'https';

    $lines = array(
        'User-agent: *',
        'Allow: /',
        'Allow: /wp-content/plugins/*.js',
        'Allow: /wp-content/plugins/*.css',
        'Allow: /wp-content/plugins/*.jpg',
        'Allow: /wp-content/plugins/*.jpeg',
        'Allow: /wp-content/plugins/*.png',
        'Allow: /wp-content/plugins/*.gif',
        'Allow: /wp-content/plugins/*.woff',
        'Allow: /wp-content/plugins/*.ttf',
        'Disallow: /wp-admin',
        'Disallow: /wp-content/plugins',
        'Disallow: /thank-you/',
        'Disallow: /cdn-cgi/',
        '',
        'Sitemap: ' . $scheme . '://' . $host . '/sitemap_index.xml',
    );

    return apply_filters('hozio_live_robots_template', implode("\n", $lines) . "\n");
}

/**
 * What this site's robots.txt is SUPPOSED to say. The standard template by
 * default; a deliberate customisation saved through the editor replaces it,
 * so an edited site is compared against its own choice and never nagged for
 * differing from the template.
 */
function hozio_rt_expected() {
    $custom = get_option('hozio_rt_expected', '');
    if (is_string($custom) && '' !== trim($custom)) {
        return $custom;
    }

    return hozio_rt_template();
}

/* -------------------------------------------------------------------------
 * Comparing what is served with what is expected
 * ---------------------------------------------------------------------- */

/**
 * A robots.txt reduced to what crawlers act on: comments and blank lines
 * dropped, directive names lowercased, values trimmed. Two files that differ
 * only in whitespace, line endings, comments or directive case are the same
 * file, and must never raise a warning.
 */
function hozio_rt_normalize($text) {
    $out = array();
    if (!is_string($text) || '' === $text) {
        return $out;
    }

    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ('' === $line || 0 === strpos($line, '#')) {
            continue;
        }
        if (preg_match('/^([A-Za-z\-]+)\s*:\s*(.*)$/', $line, $m)) {
            $out[] = strtolower($m[1]) . ': ' . trim($m[2]);
        } else {
            $out[] = $line;
        }
    }

    return $out;
}

function hozio_rt_matches($served, $expected) {
    return hozio_rt_normalize($served) === hozio_rt_normalize($expected);
}

/**
 * Anything wrong with the Sitemap line(s) in the served file, as a
 * plain-English string - '' when they are fine.
 *
 * The site's own form is the authority: a www site whose sitemap says the bare
 * domain is wrong, a bare-domain site whose sitemap says www is wrong, and a
 * sitemap pointing at some other domain entirely (the file a clone carries in)
 * is worst of all.
 */
function hozio_rt_sitemap_issue($served) {
    $own = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));

    if (!is_string($served) || !preg_match_all('/^\s*sitemap\s*:\s*(\S+)/im', $served, $m)) {
        return 'robots.txt has no Sitemap line, so crawlers are not told where the sitemap lives.';
    }

    foreach ($m[1] as $url) {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ('' === $host) {
            return 'The Sitemap line (' . $url . ') is not a full web address.';
        }
        if ($host === $own) {
            continue;
        }
        // The www-mismatch cases get called out by name; anything else is a
        // foreign domain.
        if ('www.' . $own === $host) {
            return 'The Sitemap line uses www (' . $host . ') but this site\'s address does not (' . $own . ').';
        }
        if ($own === 'www.' . $host) {
            return 'This site\'s address uses www (' . $own . ') but the Sitemap line does not (' . $host . ').';
        }
        return 'The Sitemap line points at a different domain entirely (' . $host . ') - it was carried over from another site.';
    }

    return '';
}

/* -------------------------------------------------------------------------
 * Evaluation - reads the fetch SIG already made, writes a verdict, never HTTP
 * ---------------------------------------------------------------------- */

/**
 * Turn the latest fetched robots.txt into a stored verdict.
 *
 * Verdicts: 'ok', 'mismatch' (served differs from expected), 'sitemap'
 * (content matches but the Sitemap line is wrong - only possible with a
 * custom expected), 'unknown' (no successful fetch yet). Empty string means
 * not evaluated. When SIG has flagged the live site as BLOCKED, this stays
 * silent: that emergency belongs to SIG's banner and restore flow.
 */
function hozio_rt_evaluate() {
    if (!hozio_rt_enabled() || (function_exists('hozio_sig_is_staging') && hozio_sig_is_staging())) {
        return '';
    }

    // SIG's territory: a live site serving "Disallow: /".
    if ('1' === get_option('hozio_sig_robots_blocked', '0')) {
        update_option('hozio_rt_status', '', true);
        return '';
    }

    $remote = get_transient('hozio_sig_remote_result');
    if (!is_array($remote) || empty($remote['ok'])) {
        update_option('hozio_rt_status', 'unknown', true);
        return 'unknown';
    }

    $served  = isset($remote['body']) ? (string) $remote['body'] : '';
    $sitemap = hozio_rt_sitemap_issue($served);

    if (!hozio_rt_matches($served, hozio_rt_expected())) {
        $status = 'mismatch';
        $detail = 'The robots.txt being served does not match what it should say.';
        if ('' !== $sitemap) {
            $detail .= ' ' . $sitemap;
        }
    } elseif ('' !== $sitemap) {
        $status = 'sitemap';
        $detail = $sitemap;
    } else {
        $status = 'ok';
        $detail = '';
    }

    update_option('hozio_rt_status', $status, true);
    update_option('hozio_rt_detail', array(
        'checked_at' => time(),
        'message'    => $detail,
        'served'     => $served,
    ), false);

    return $status;
}

/**
 * Piggybacks on SIG's daily check, which has just fetched robots.txt.
 * Evaluation only - this never writes the file.
 */
function hozio_rt_daily() {
    $status = hozio_rt_evaluate();
    if (in_array($status, array('mismatch', 'sitemap'), true) && function_exists('hozio_log')) {
        $detail = get_option('hozio_rt_detail', array());
        hozio_log('Live robots.txt needs attention: ' . (isset($detail['message']) ? $detail['message'] : $status), 'RobotsTemplate');
    }
}
add_action('hozio_sig_daily_check', 'hozio_rt_daily', 20);

/* -------------------------------------------------------------------------
 * Writing - ONLY ever from a button an administrator clicked
 * ---------------------------------------------------------------------- */

/**
 * Write robots.txt with the same care the staging fix earned the hard way:
 * back up whatever is there, verify the write on disk, then prove it over
 * HTTP with cache-busted retries before claiming success.
 */
function hozio_rt_write($content) {
    $path = function_exists('hozio_sig_robots_path') ? hozio_sig_robots_path() : '';
    if ('' === $path) {
        return array(false, 'No usable web root was found, so robots.txt could not be written. Set it by hand.');
    }

    $dir = trailingslashit(dirname($path));

    if (file_exists($path)) {
        if (!is_writable($path)) {
            return array(false, 'robots.txt at ' . $path . ' is not writable by PHP. Fix the permissions, or paste the content in by hand.');
        }
        $backup = $dir . 'robots.txt.hozio-backup-' . gmdate('Ymd-His');
        if (!copy($path, $backup)) {
            return array(false, 'Could not back up the existing robots.txt, so nothing was changed.');
        }
        $note = ' The old file was saved as ' . basename($backup) . '.';
    } elseif (!is_writable($dir)) {
        return array(false, $dir . ' is not writable by PHP, so robots.txt could not be created. Paste the content in by hand.');
    } else {
        $note = '';
    }

    if (false === file_put_contents($path, $content)) {
        return array(false, 'Writing ' . $path . ' failed. Nothing was changed.');
    }
    clearstatcache(true, $path);
    if (trim((string) file_get_contents($path)) !== trim($content)) {
        return array(false, 'robots.txt was written but read back different. Check ' . $path . ' by hand.');
    }

    // Prove it on the URL - a cache one step behind hands back the old file.
    $attempts  = (int) apply_filters('hozio_staging_verify_attempts', 3);
    $delay     = (int) apply_filters('hozio_staging_verify_delay', 2);
    $confirmed = false;
    $fetched   = false;

    for ($attempt = 1; $attempt <= max(1, $attempts); $attempt++) {
        if ($attempt > 1 && $delay > 0) {
            sleep($delay);
        }
        delete_transient('hozio_sig_remote_result');
        $after = function_exists('hozio_sig_remote_check') ? hozio_sig_remote_check(true, true) : array();
        if (is_array($after) && !empty($after['ok'])) {
            $fetched = true;
            if (hozio_rt_matches(isset($after['body']) ? (string) $after['body'] : '', $content)) {
                $confirmed = true;
                break;
            }
        }
    }

    hozio_rt_evaluate();

    if ($confirmed) {
        return array(true, 'robots.txt updated and confirmed by fetching ' . home_url('/robots.txt') . '.' . $note);
    }
    if ($fetched) {
        return array(false, 'Wrote ' . $path . ' and confirmed it on disk, but ' . home_url('/robots.txt') . ' was still serving the old content after ' . max(1, $attempts) . ' checks. That is usually a cache that has not caught up - open the URL in a minute and use "Re-check now".' . $note);
    }

    return array(true, 'Wrote ' . $path . ', but the HTTP check could not run. Open ' . home_url('/robots.txt') . ' to confirm.' . $note);
}

/**
 * Shared guard for the three handlers: capability, nonce, and live-site-only.
 */
function hozio_rt_guard($nonce_action) {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.', 'Hozio Pro', array('response' => 403));
    }
    check_admin_referer($nonce_action, 'hozio_rt_nonce');

    if (function_exists('hozio_sig_is_staging') && hozio_sig_is_staging()) {
        wp_die(
            'Refused: this is a staging site. The live robots.txt template must not be applied to staging - staging blocks all crawlers.',
            'Hozio Pro',
            array('response' => 400)
        );
    }
}

function hozio_rt_notice($failed, $message) {
    set_transient('hozio_rt_notice_' . get_current_user_id(), array(
        'failed'  => (bool) $failed,
        'message' => (string) $message,
    ), 5 * MINUTE_IN_SECONDS);
}

function hozio_rt_bounce($ok) {
    $back = wp_get_referer();
    if (!$back) {
        $back = admin_url('admin.php?page=hozio-plugin-settings');
    }
    // Land on the robots section, not the top of the page.
    $back = preg_replace('/#.*$/', '', $back) . '#hozio-robots-editor';
    wp_safe_redirect(add_query_arg('hozio_rt', $ok ? 'ok' : 'fail', $back));
    exit;
}

/**
 * "Apply standard template": writes the generated template and clears any
 * custom expectation, so the site is measured against the standard again.
 */
function hozio_rt_handle_apply() {
    hozio_rt_guard('hozio_rt_apply');

    delete_option('hozio_rt_expected');
    list($ok, $msg) = hozio_rt_write(hozio_rt_template());

    if (function_exists('hozio_log')) {
        hozio_log('Applied standard robots.txt template: ' . $msg, 'RobotsTemplate');
    }
    hozio_rt_notice(!$ok, $msg);
    hozio_rt_bounce($ok);
}

/**
 * "Save robots.txt": writes exactly what is in the editor and makes that the
 * expected content, so a deliberate customisation is never nagged about.
 */
function hozio_rt_handle_save() {
    hozio_rt_guard('hozio_rt_save');

    $content = isset($_POST['hozio_rt_content']) ? (string) wp_unslash($_POST['hozio_rt_content']) : '';
    $content = str_replace(array("\r\n", "\r", chr(0)), array("\n", "\n", ''), $content);

    if ('' === trim($content)) {
        hozio_rt_notice(true, 'The editor was empty, so nothing was saved.');
        hozio_rt_bounce(false);
    }
    if (strlen($content) > 65536) {
        hozio_rt_notice(true, 'That content is too large for a robots.txt (64KB limit). Nothing was saved.');
        hozio_rt_bounce(false);
    }
    if ("\n" !== substr($content, -1)) {
        $content .= "\n";
    }

    // A customisation identical to the template is not a customisation:
    // store nothing, so the site keeps tracking the standard.
    if (hozio_rt_matches($content, hozio_rt_template())) {
        delete_option('hozio_rt_expected');
    } else {
        update_option('hozio_rt_expected', $content, false);
    }

    list($ok, $msg) = hozio_rt_write($content);

    if (function_exists('hozio_log')) {
        hozio_log('robots.txt saved from the editor: ' . $msg, 'RobotsTemplate');
    }
    hozio_rt_notice(!$ok, $msg);
    hozio_rt_bounce($ok);
}

function hozio_rt_handle_recheck() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to do this.', 'Hozio Pro', array('response' => 403));
    }
    check_admin_referer('hozio_rt_recheck', 'hozio_rt_nonce');

    delete_transient('hozio_sig_remote_result');
    if (function_exists('hozio_sig_remote_check')) {
        hozio_sig_remote_check(true, true);
    }
    $status = hozio_rt_evaluate();

    hozio_rt_notice(false, 'ok' === $status
        ? 'Checked: the live robots.txt matches.'
        : 'Checked: see the status below.');
    hozio_rt_bounce(true);
}

function hozio_rt_action_url($action) {
    return wp_nonce_url(
        admin_url('admin-post.php?action=hozio_rt_' . $action),
        'hozio_rt_' . $action,
        'hozio_rt_nonce'
    );
}

/* -------------------------------------------------------------------------
 * Banner - warns, links to the settings section, fixes nothing itself
 * ---------------------------------------------------------------------- */

function hozio_rt_settings_url() {
    return admin_url('admin.php?page=hozio-plugin-settings') . '#hozio-robots-editor';
}

function hozio_rt_banner_html($context) {
    $detail  = get_option('hozio_rt_detail', array());
    $message = !empty($detail['message']) ? (string) $detail['message'] : 'The robots.txt being served does not match what it should say.';
    $fixed   = ('admin' === $context) ? 'position:relative;' : 'position:fixed;top:0;left:0;right:0;';

    ob_start();
    ?>
    <div id="hozio-rt-bar" style="<?php echo esc_attr($fixed); ?>z-index:2147483638;background:#b3140f;border-bottom:3px solid #7d0d0a;color:#fff;padding:12px 16px;font:600 13px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;box-sizing:border-box;box-shadow:0 2px 8px rgba(0,0,0,.25);">
        <div style="max-width:1400px;margin:0 auto;display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center;">
            <span style="font-size:15px;font-weight:800;letter-spacing:.03em;white-space:nowrap;">ROBOTS.TXT NEEDS ATTENTION</span>
            <span style="font-weight:400;flex:1 1 320px;min-width:0;"><?php echo esc_html($message); ?></span>
            <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" rel="noopener"
               style="color:#fff;text-decoration:underline;white-space:nowrap;font-weight:400;">View live file</a>
            <a href="<?php echo esc_url(hozio_rt_settings_url()); ?>"
               style="background:#fff;color:#7d0d0a;padding:7px 18px;border-radius:3px;text-decoration:none;font-weight:700;white-space:nowrap;">
                Review in settings
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function hozio_rt_render_admin_banner() {
    if (!in_array(get_option('hozio_rt_status', ''), array('mismatch', 'sitemap'), true)) {
        return;
    }
    echo hozio_rt_banner_html('admin'); // phpcs:ignore WordPress.Security.EscapeOutput
}

function hozio_rt_render_front_banner() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!empty($GLOBALS['hozio_rt_front_done'])) {
        return;
    }
    // The other guards' banners take precedence - never stack fixed bars.
    if (!empty($GLOBALS['hozio_sig_front_done']) || !empty($GLOBALS['hozio_sug_front_done'])) {
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
    if (!in_array(get_option('hozio_rt_status', ''), array('mismatch', 'sitemap'), true)) {
        return;
    }

    $GLOBALS['hozio_rt_front_done'] = true;

    echo '<style>#hozio-rt-bar{top:0}.admin-bar #hozio-rt-bar{top:32px}@media screen and (max-width:782px){.admin-bar #hozio-rt-bar{top:46px}}</style>';
    echo hozio_rt_banner_html('front'); // phpcs:ignore WordPress.Security.EscapeOutput
}

function hozio_rt_admin_bar_node($bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!in_array(get_option('hozio_rt_status', ''), array('mismatch', 'sitemap'), true)) {
        return;
    }
    $bar->add_node(array(
        'id'    => 'hozio-rt',
        'title' => '<span style="display:inline-block;background:#b3140f;color:#fff;font-weight:700;padding:0 9px;border-radius:3px;">ROBOTS.TXT</span>',
        'href'  => hozio_rt_settings_url(),
        'meta'  => array('title' => 'The live robots.txt does not match what it should say'),
    ));
}

/* -------------------------------------------------------------------------
 * Bootstrap
 * ---------------------------------------------------------------------- */

function hozio_rt_init() {
    // Handlers are admin-only and each re-checks capability, nonce and
    // environment for itself.
    if (is_admin()) {
        add_action('admin_post_hozio_rt_apply', 'hozio_rt_handle_apply');
        add_action('admin_post_hozio_rt_save', 'hozio_rt_handle_save');
        add_action('admin_post_hozio_rt_recheck', 'hozio_rt_handle_recheck');
    }

    if (!hozio_rt_enabled()) {
        return;
    }

    // Staging sites are SIG's business; this guard does nothing there.
    if (function_exists('hozio_sig_is_staging') && hozio_sig_is_staging()) {
        return;
    }

    // One autoloaded option read decides whether any banner hook exists.
    if (!in_array(get_option('hozio_rt_status', ''), array('mismatch', 'sitemap'), true)) {
        return;
    }

    add_action('in_admin_header', 'hozio_rt_render_admin_banner', 1002);
    add_action('admin_bar_menu', 'hozio_rt_admin_bar_node', 1001);
    add_action('wp_body_open', 'hozio_rt_render_front_banner', 3);
    add_action('wp_footer', 'hozio_rt_render_front_banner', 3);
}
add_action('init', 'hozio_rt_init', 22);
