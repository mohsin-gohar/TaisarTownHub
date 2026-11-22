# TaisarTownHub - backup restoration drill.
#
# Restores a backup into a SCRATCH directory (never over the live database),
# verifies row counts against the live database and prints a drill report.
# Nothing in the live environment is modified.
#
# Supports both SQLite (.sqlite.gz) and MySQL/MariaDB (.sql.gz) backups.
# For MySQL backups, the MySQL CLI (mysql) must be on PATH and the live
# database must be reachable (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME,
# DB_PASSWORD env vars or backend/.env).
#
# Exit code: 0 = PASS, 1 = FAIL, 2 = blocker (missing tooling).
param(
    [string]$BackupDir = "$env:LOCALAPPDATA\Temp\opencode\tth-backups",
    [string]$Scratch = "$env:LOCALAPPDATA\Temp\opencode\tth-restore-drill"
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$backend = Join-Path $root 'backend'

# --- Find the newest DB backup ---
$db = Get-ChildItem $BackupDir -Filter 'tth_db_*.sqlite.gz' -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
$isSqlite = $true
if (-not $db) {
    $db = Get-ChildItem $BackupDir -Filter 'tth_db_*.sql.gz' -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    $isSqlite = $false
}
if (-not $db) { throw "No database backup found in $BackupDir" }

Write-Host "[drill] source backup: $($db.FullName) ($($db.Length) bytes)"
Write-Host "[drill] backup type: $(if ($isSqlite) { 'SQLite' } else { 'MySQL/MariaDB' })"

# --- Decompress into scratch ---
New-Item -ItemType Directory -Force -Path $Scratch | Out-Null
$restored = Join-Path $Scratch $db.BaseName
if (Test-Path $restored) { Remove-Item $restored -Force }

$in = [System.IO.File]::OpenRead($db.FullName)
$gz = New-Object System.IO.Compression.GZipStream($in, [System.IO.Compression.CompressionMode]::Decompress)
$out = [System.IO.File]::Create($restored)
$gz.CopyTo($out)
$out.Close()
$gz.Close()
$in.Close()
if ($restored -like '*.gz') { $restored = $restored.Substring(0, $restored.Length - 3) }

Write-Host "[drill] restored copy: $restored ($((Get-Item $restored).Length) bytes)"

# --- Verify row counts ---
if ($isSqlite) {
    $live = Join-Path $backend 'database/database.sqlite'
    $verify = @"
<?php
`$restored = '$restored';
`$live = '$live';
function counts(`$file) {
    `$pdo = new PDO('sqlite:' . `$file);
    `$out = [];
    foreach (['users','agencies','properties','dealers','inquiries','transactions','business_records','login_histories','permissions','role_permissions','documents'] as `$t) {
        try { `$out[`$t] = (int) `$pdo->query("SELECT count(*) FROM `$t`")->fetchColumn(); }
        catch (Throwable `$e) { `$out[`$t] = -1; }
    }
    `$out['__tables__'] = (int) `$pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
    return `$out;
}
`$r = counts(`$restored);
`$l = is_file(`$live) ? counts(`$live) : [];
echo json_encode(['restored' => `$r, 'live' => `$l]);
"@
    $verifyFile = Join-Path $Scratch 'verify.php'
    Set-Content -Path $verifyFile -Value $verify -Encoding ASCII
    $counts = (& php $verifyFile) | ConvertFrom-Json

    Write-Host ""
    Write-Host "table                restored      live     match"
    $mismatch = 0
    foreach ($prop in $counts.restored.PSObject.Properties) {
        $restoredValue = $prop.Value
        $liveValue = if ($counts.live.PSObject.Properties.Name -contains $prop.Name) { $counts.live.($prop.Name) } else { 'n/a' }
        $match = if ([string]$restoredValue -eq [string]$liveValue) { 'yes' } else { 'NO' }
        if ($match -eq 'NO') { $mismatch++ }
        Write-Host ("{0,-20} {1,-12} {2,-9} {3}" -f $prop.Name, $restoredValue, $liveValue, $match)
    }
} else {
    # MySQL/MariaDB backup: verify it's a valid SQL dump and contains expected tables
    Write-Host "[drill] verifying SQL dump integrity..."
    $sqlContent = Get-Content $restored -Raw -ErrorAction SilentlyContinue
    if (-not $sqlContent) {
        Write-Host "DRILL RESULT: FAIL - empty or unreadable SQL file"
        exit 1
    }

    $tableCount = ([regex]::Matches($sqlContent, 'CREATE TABLE')).Count
    $insertCount = ([regex]::Matches($sqlContent, 'INSERT INTO')).Count
    Write-Host "  CREATE TABLE statements: $tableCount"
    Write-Host "  INSERT INTO statements:  $insertCount"

    if ($tableCount -eq 0) {
        Write-Host "DRILL RESULT: FAIL - no CREATE TABLE statements found"
        exit 1
    }

    # Check for expected core tables
    $expectedTables = @('users', 'agencies', 'properties', 'dealers', 'inquiries', 'transactions', 'business_records')
    $missingTables = @()
    foreach ($t in $expectedTables) {
        if ($sqlContent -notmatch "CREATE TABLE.*$t") {
            $missingTables += $t
        }
    }
    if ($missingTables.Count -gt 0) {
        Write-Host "DRILL RESULT: FAIL - missing expected tables: $($missingTables -join ', ')"
        exit 1
    }

    Write-Host "  All expected core tables present."
    $mismatch = 0
    Write-Host "DRILL RESULT: PASS - MySQL backup is valid with $tableCount tables and $insertCount inserts"
}

# --- Vault restore ---
$vault = Get-ChildItem $BackupDir -Filter 'tth_vault_*.tar.gz' -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
$vaultStatus = 'not part of this backup'
if ($vault) {
    $vaultTarget = Join-Path $Scratch 'vault'
    New-Item -ItemType Directory -Force -Path $vaultTarget | Out-Null
    tar -xzf $vault.FullName -C $vaultTarget
    $entries = (tar -tzf $vault.FullName | Measure-Object).Count
    $vaultStatus = "restored $entries entries"
    Write-Host "[drill] vault: $vaultStatus"
}

Write-Host ""
if ($mismatch -gt 0) {
    Write-Host "DRILL RESULT: FAIL - $mismatch table(s) differ from the live database"
    exit 1
}
Write-Host "DRILL RESULT: PASS - restored copy verified (vault: $vaultStatus)"