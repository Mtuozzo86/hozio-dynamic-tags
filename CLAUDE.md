# Hozio Pro (hozio-dynamic-tags) — read this first

Hozio Pro is one WordPress plugin installed on roughly 200 client sites. **Every site updates itself from this repo's GitHub releases.** A release is therefore a fleet-wide deploy, and a bad one breaks client sites. Safety and security come before features.

`FEATURES.md` is the map of the codebase: every module, option, REST route, WP-CLI command, cron event and gotcha. Read the parts you are about to touch. When it disagrees with the code, the code is right; fix `FEATURES.md` in the same change.

## Non-negotiable rules

1. **Pre-release first, always.** The self-updater (`includes/plugin-updater.php`) reads `/releases/latest`, which skips pre-releases, so a pre-release reaches no client site. Every release goes:
   1. `gh release create vX.Y.Z <zip> --prerelease` at the release commit;
   2. install on the sandbox, then on two or three low-risk client sites through Hozio Pro's own rollback installer, and run the change's tests;
   3. watch for 24 hours;
   4. only then promote to a full (latest) release.
   Never create a non-prerelease directly, and never push to `main` or force-push it without being asked.
2. **The repo is public, and changelogs are shown to every client admin** (the release browser displays release notes). Never put a client domain, site name, site ID or incident detail in a CHANGELOG entry, commit message, release note, code comment, this file or `FEATURES.md`. Describe a problem generically ("on hosts that serve wp-content files directly…").
3. **No public files.** Nothing operational (logs, state, exports) may be written anywhere web-servable. wp-content is public on Nginx hosts, where `.htaccess` does nothing. Use the database. The audit and debug logs are tables for exactly this reason (4.20.9), and they must never fall back to a file.
4. **No new unauthenticated surface.** No `permission_callback => '__return_true'` (the one existing exception is the Hub endpoint, which checks a bearer token itself), no `wp_ajax_nopriv_`, no public query-string triggers. Admin actions check a capability **and** a nonce.
5. **Never output secrets.** No command, log line, heartbeat field, JSON output or admin screen may print `hozio_hub_site_token`, the hub token, the licence key or any password. WP-CLI output can land in the host's activity log, so free text printed by `wp hozio` goes through `hozio_log_redact()`.
6. **Validate stored data when you read it,** not only when you write it. The Hub's `update_option` command can write most `hozio_*` options. Every new option must be (a) refused in `cmd_update_option()` in `includes/hub-command-executor.php` and (b) validated entry by entry on read, with invalid entries dropped and counted, never trusted.
7. **Audit-log every state change** through `hozio_audit_log()`, saying who did it (`wp-cli`, `hub`, `orchestrator`, `admin:<login>`), why, and what it touched.
8. **`hoziowpadmin` belongs to the Hozio Studio Orchestrator** on every site (its shared login, and its application passwords are the orchestrator's REST keys). Nothing in this plugin may reset, delete or reuse that account. The Hub's temporary login is `hozio-hub-support`.
9. **Humans stay in control.** Holds and the freeze stop automatic updates only. Never block a person clicking "Update now".
10. **Backward compatible, and the PHP the plugin already uses.** Sites without the Hub, with Auto-Update All off, or still on an older release must keep working. Don't introduce newer language features (no `match`, enums, readonly, nullsafe `?->`, `str_contains` and the like). Don't depend on optional extensions such as mbstring.

## Lint (required before every commit)

Run `php -n -l` on every PHP file. The `-n` matters: it runs without php.ini, so it catches code that only works because an optional extension happens to be loaded locally.

```powershell
$php = 'php'   # or the full path to a local php.exe
$files = @('hozio-dynamic-tags.php') + (Get-ChildItem includes -Recurse -Filter *.php | % FullName)
$bad = 0; foreach ($f in $files) { & $php -n -l $f | Out-Null; if ($LASTEXITCODE) { $bad++; & $php -n -l $f } }; "$($files.Count) files, $bad failures"
```

Behaviour tests (harnesses with WordPress stubs) live outside the plugin folder and never ship.

## Release steps (summary of RELEASE.md, which is kept locally and not committed)

1. Bump **both** version strings in `hozio-dynamic-tags.php`: the `Version:` header and `define('HOZIO_VERSION', ...)`.
2. Add a CHANGELOG entry at the top of `CHANGELOG.txt`, in its existing voice (`= Security =`, `= Fixes =`, `= Added =`, `= Changed =`, `= Notes =`), with no client identifiers.
3. Commit, and push the branch.
4. Build the ZIP with `System.IO.Compression.ZipFile` and **forward slashes**. The top folder must be `hozio-dynamic-tags/`. Include only `hozio-dynamic-tags.php`, `CHANGELOG.txt`, `README.md`, `includes/` and `assets/`. **Never use `Compress-Archive`**: its backslash paths break installs on Linux.
5. Verify the ZIP: every entry is `hozio-dynamic-tags/...`, no backslashes, no stray files (this file, tests, `.git`), and `php -n -l` passes on the extracted PHP.
6. Create the **pre-release** (rule 1), test, and promote later.

Updater safety: `after_install()` must never move or delete directories. `fix_source_directory()` renames the folder before install. `after_install()` only reactivates and clears the cache.

## The orchestrator contract (`wp hozio ...`)

The Hozio Studio Orchestrator manages these sites through the host's WP-CLI API. It detects Hozio Pro with `wp cli has-command hozio`, then reads `wp hozio info --format=json`. Code lives in `includes/wp-cli.php` (a thin layer) and `includes/update-holds.php` (the logic, shared with the Hub and the settings UI).

- Every command prints **one line** of compact JSON, `--format=json` only (and the default). On failure it prints `{"ok":false,"error":"<code>","message":"..."}` and exits 1. Nothing prompts, nothing is slow, and nothing prints a secret.
- The orchestrator reads about the first **500 characters** of a successful command. Keep `updates hold/release/freeze/unfreeze` output short. `info` must keep `contract` and `capabilities` as its **first keys**, and `info --compact` must stay under 500 characters.
- Commands and their keys:
  - `info [--compact]`: `contract`, `plugin_version`, `capabilities`, `license_status`, `hub_connected`, `freeze`, `holds_active`, `holds_expired`, `holds_invalid`; the full form adds `self_update`, `auto_update_all`, `holds`, `pending`, `recent`.
  - `updates hold <plugin-file> --reason= [--mode=pin|skip] [--versions=] [--until=] [--source=] [--ref=]`: `ok`, `action`, `plugin`, `mode`, `versions`, `held_at`, `expires_at`, `updated`.
  - `updates release <plugin-file> --reason= [--source=]`: `ok`, `action`, `plugin`, `released`.
  - `updates holds`: `ok`, `holds`, `holds_invalid`, `freeze`.
  - `updates freeze --until= --reason= [--ref=] [--source=]`: `ok`, `action`, `until`, `replaced`.
  - `updates unfreeze --reason= [--source=]`: `ok`, `action`, `unfrozen`.
  - `updates history`: `ok`, `history`, `invalid`.
  - `log [--lines=] [--since=] [--category=]`: `ok`, `count`, `entries` (`id`, `at`, `category`, `message`).
- **`contract`** is `HOZIO_ORCHESTRATOR_CONTRACT` in `includes/update-holds.php`, currently **1**. Bump it **only** for a breaking change: a key removed or renamed, a type or meaning changed, a command removed. Adding keys, commands or `capabilities` entries is not breaking. Current capabilities: `holds`, `freeze`, `log`, `status`, `history`.
- **Limits:**
  - reason: at most 500 characters, sanitized;
  - ref: `[A-Za-z0-9_.:-]`, at most 100 characters;
  - hold: 30 days by default, at most 180;
  - freeze: at most 7 days;
  - `--until` accepts `YYYY-MM-DD`, `YYYY-MM-DDTHH:MMZ` or `+N` followed by `m`, `h` or `d`;
  - plugin files are `folder/file.php` and must be installed to be held;
  - times are ISO 8601 UTC.
- Hub parity: the commands `hold_plugin_updates`, `release_plugin_updates`, `freeze_updates` and `unfreeze_updates` call the same functions, with `source = hub`. The heartbeat's `patch_status` carries `holds`, `holds_invalid` and `freeze`.
- Change the contract deliberately. The orchestrator is built against these exact names.
