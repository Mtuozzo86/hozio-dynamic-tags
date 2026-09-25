# Behaviour tests

Command-line harnesses that exercise Hozio Pro's logic against small WordPress stubs and an in-memory fake `wpdb` (`lib/wp-stubs.php`). No WordPress install, database or network is needed.

| File | Covers |
|---|---|
| `test-logger.php` | `includes/hozio-logger.php`: audit/debug log tables, caps, legacy-file migration, redaction |
| `test-holds.php` | `includes/update-holds.php` and `includes/wp-cli.php`: holds, freeze, validation on read, `wp hozio` output and redaction |
| `test-crash-guard.php` | `includes/plugin-auto-updates.php`: crash guard, maintenance-mode handling, freeze wiring, heartbeat patch status |
| `test-hub-deny.php` | `includes/hub-command-executor.php`: `update_option` deny list, hold/freeze commands, holds vs `update_plugin`, rollback holds, temporary support login |
| `test-sitemap-redirects.php` | `includes/sitemap-redirects.php`: which sitemap links the Redirection plugin redirects, query count, malformed and slow regex rules |

## Run

```powershell
powershell -ExecutionPolicy Bypass -File tests/run.ps1                        # php on the PATH
powershell -ExecutionPolicy Bypass -File tests/run.ps1 -Php 'C:\path\to\php.exe'
pwsh tests/run.ps1 -Verbose                                                   # every check
php -n tests/test-holds.php                                                   # one harness
```

`run.ps1` runs each harness with `php -n` (no php.ini), prints a line per harness and the totals, and exits 1 if any check fails or any harness dies. Run it before every release. Each harness also takes an optional plugin folder as its first argument (default: this repo), so the same tests can be pointed at another checkout.

The stub error handler turns every PHP warning, notice or deprecation in the code under test into a failure, unless the code suppressed it on purpose. A new warning is a failing test.

## These files never ship

- The release ZIP is built from an allow-list: `hozio-dynamic-tags.php`, `CHANGELOG.txt`, `README.md`, `includes/` and `assets/` (CLAUDE.md, "Release steps"). `tests/` is not on it.
- `.gitattributes` marks `/tests` `export-ignore`, so GitHub's auto-generated source archives leave it out too. That matters because the updater falls back to the source zipball when a release has no ZIP asset.
- Every PHP file here exits at once unless it runs from the command line, so a copy that somehow reached a web server would do nothing.

## Writing tests

Keep test data generic: reserved example domains (`example.test`, `example.com`), documentation IP ranges (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`), invented plugin and rule names. Never a client domain, site name, site ID or real rule pattern, and never a real key or token (the repo is public).
