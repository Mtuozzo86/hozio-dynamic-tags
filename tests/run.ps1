# Runs every behaviour harness in tests/ and exits non-zero if any of them fails.
#
#   powershell -ExecutionPolicy Bypass -File tests/run.ps1
#   powershell -ExecutionPolicy Bypass -File tests/run.ps1 -Php 'C:\path\to\php.exe'
#   pwsh tests/run.ps1 -Php php -Verbose
#
# -Php     the PHP binary (default: php on the PATH). PHP 7.4 or newer.
# -Plugin  the plugin folder to test (default: this repo).
# -Verbose print every check, not just failures and totals.
[CmdletBinding()]
param(
    [string]$Php = 'php',
    [string]$Plugin = ''
)

$ErrorActionPreference = 'Continue'
if (-not (Get-Command $Php -ErrorAction SilentlyContinue)) {
    Write-Host "PHP not found: $Php (pass -Php with the path to a php binary)"
    exit 1
}
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
if ($Plugin -eq '') {
    $Plugin = Split-Path -Parent $here
}

$tests = Get-ChildItem -Path $here -Filter 'test-*.php' | Sort-Object Name
if (-not $tests) {
    Write-Host "No test-*.php files found in $here"
    exit 1
}

$failed = 0
$passedTotal = 0
$failedTotal = 0

foreach ($t in $tests) {
    # -n: no php.ini, so nothing passes only because a local extension happens to be loaded.
    $out  = & $Php -n $t.FullName $Plugin 2>&1 | ForEach-Object { "$_" }
    $code = $LASTEXITCODE
    $last = ($out | Where-Object { $_ -match '^\d+ passed, \d+ failed$' } | Select-Object -Last 1)

    if ($last -match '^(\d+) passed, (\d+) failed$') {
        $passedTotal += [int]$Matches[1]
        $failedTotal += [int]$Matches[2]
    }

    $ok = ($code -eq 0) -and ($last -match ' 0 failed$')
    if ($ok) {
        Write-Host ("PASS  {0,-28} {1}" -f $t.Name, $last)
        if ($VerbosePreference -eq 'Continue') { $out | ForEach-Object { Write-Host "      $_" } }
    } else {
        $failed++
        Write-Host ("FAIL  {0,-28} exit {1}; {2}" -f $t.Name, $code, $(if ($last) { $last } else { 'no summary line (fatal error?)' }))
        # Show the failing checks and any PHP error; the full output with -Verbose.
        $shown = $out | Where-Object { $_ -match 'FAIL|Fatal|Warning|Notice|Deprecated|Uncaught|Parse error' }
        if ($VerbosePreference -eq 'Continue' -or -not $shown) { $shown = $out }
        $shown | ForEach-Object { Write-Host "      $_" }
    }
}

Write-Host ''
Write-Host ("{0} harnesses, {1} failed; {2} checks passed, {3} failed" -f $tests.Count, $failed, $passedTotal, $failedTotal)
if ($failed -gt 0) {
    exit 1
}
exit 0
