# TaisarTownHub - MySQL backup restoration drill.
#
# Restores a backup into a SCRATCH directory (never over the live database),
# verifies the SQL dump's integrity and prints a drill report.
# Nothing in the live environment is modified.
# MySQL 8 / MariaDB is the only supported database engine.
#
# Exit code: 0 = PASS, 1 = FAIL
param(
    [string]$BackupDir = "$env:LOCALAPPDATA\Temp\opencode\tth-backups",
    [string]$Scratch = "$env:LOCALAPPDATA\Temp\opencode\tth-restore-drill"
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$backend = Join-Path $root 'backend'

# --- Find the newest MySQL database backup ---
$db = Get-ChildItem $BackupDir -Filter 'tth_db_*.sql.gz' -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $db) { throw "No MySQL database backup found in $BackupDir" }

Write-Host "[drill] source backup: $($db.FullName) ($($db.Length) bytes)"
Write-Host "[drill] backup type: MySQL/MariaDB"

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

# --- Verify the SQL dump ---
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
Write-Host "DRILL RESULT: PASS - MySQL backup is valid with $tableCount tables and $insertCount inserts (vault: $vaultStatus)"
