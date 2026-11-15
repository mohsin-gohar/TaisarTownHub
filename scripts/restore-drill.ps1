# TaisarTownHub - backup restoration drill.
#
# Restores a backup into a SCRATCH directory (never over the live database),
# verifies row counts against the live database and prints a drill report.
# Nothing in the live environment is modified.
param(
    [string]$BackupDir = "$env:LOCALAPPDATA\Temp\opencode\tth-backups",
    [string]$Scratch = "$env:LOCALAPPDATA\Temp\opencode\tth-restore-drill"
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$backend = Join-Path $root 'backend'

$db = Get-ChildItem $BackupDir -Filter 'tth_db_*.sqlite.gz' -ErrorAction SilentlyContinue |
    Sort-Object LastWriteTime -Descending | Select-Object -First 1
if (-not $db) {
    $db = Get-ChildItem $BackupDir -Filter 'tth_db_*.sql.gz' -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
}
if (-not $db) { throw "No database backup found in $BackupDir" }

Write-Host "[drill] source backup: $($db.FullName) ($($db.Length) bytes)"

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

$verify = @"
<?php
`$restored = '$restored';
`$live = '$backend/database/database.sqlite';
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
Write-Host "DRILL RESULT: PASS - restored copy matches the live database (vault: $vaultStatus)"
