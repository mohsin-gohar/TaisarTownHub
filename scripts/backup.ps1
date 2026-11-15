# TaisarTownHub - automated backup for Windows hosts (dev + staging).
#
# Usage:  powershell -ExecutionPolicy Bypass -File scripts\backup.ps1 -Destination <dir>
#
# Produces: <dest>\tth_db_<stamp>.sqlite.gz (or .sql.gz), <dest>\tth_vault_<stamp>.tar.gz
#           and a sha256 manifest. Nothing on the live system is ever deleted.
param(
    [string]$Destination = "$env:LOCALAPPDATA\Temp\opencode\tth-backups",
    [switch]$IncludeVault
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$backend = Join-Path $root 'backend'
$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
New-Item -ItemType Directory -Force -Path $Destination | Out-Null

function Read-DotEnvValue([string]$key, [string]$fallback = '') {
    $envFile = Join-Path $backend '.env'
    if (-not (Test-Path $envFile)) { return $fallback }
    $line = Get-Content $envFile | Where-Object { $_ -like "$key=*" } | Select-Object -First 1
    if (-not $line) { return $fallback }
    return ($line -split '=', 2)[1].Trim().Trim('"')
}

function Compress-ToGzip([string]$source, [string]$target) {
    $bytes = [System.IO.File]::ReadAllBytes($source)
    $out = [System.IO.File]::Create($target)
    $gz = New-Object System.IO.Compression.GZipStream($out, [System.IO.Compression.CompressionMode]::Compress)
    $gz.Write($bytes, 0, $bytes.Length)
    $gz.Close()
    $out.Close()
}

function Get-TableCount([string]$file) {
    $probe = @"
<?php
`$pdo = new PDO('sqlite:' . '$file');
echo (int) `$pdo->query("SELECT count(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
"@
    $probeFile = Join-Path $env:TEMP "tth_probe_$([guid]::NewGuid().ToString('N')).php"
    Set-Content -Path $probeFile -Value $probe -Encoding ASCII
    try {
        $result = & php $probeFile
        return [int](($result | Select-Object -First 1))
    } catch {
        return 0
    } finally {
        Remove-Item $probeFile -Force -ErrorAction SilentlyContinue
    }
}

$driver = Read-DotEnvValue 'DB_CONNECTION' 'sqlite'
$dbArtefact = $null
$tableCount = 0

if ($driver -eq 'sqlite') {
    $dbFile = Read-DotEnvValue 'DB_DATABASE' (Join-Path $backend 'database\database.sqlite')
    Write-Host "[backup] sqlite driver - source $dbFile"
    $copy = Join-Path $Destination "tth_db_$stamp.sqlite"
    Copy-Item $dbFile $copy -Force
    $tableCount = Get-TableCount $copy
    $dbArtefact = "$copy.gz"
    Compress-ToGzip $copy $dbArtefact
    Remove-Item $copy -Force
} else {
    $name = Read-DotEnvValue 'DB_DATABASE'
    $user = Read-DotEnvValue 'DB_USERNAME'
    $hostName = Read-DotEnvValue 'DB_HOST' '127.0.0.1'
    $port = Read-DotEnvValue 'DB_PORT' '3306'
    $password = Read-DotEnvValue 'DB_PASSWORD'
    Write-Host "[backup] mysql/mariadb driver - dumping $name"
    $sql = Join-Path $Destination "tth_db_$stamp.sql"
    & mysqldump --single-transaction --routines --triggers -h $hostName -P $port -u $user "-p$password" $name | Out-File -FilePath $sql -Encoding utf8
    $tableCount = (Select-String -Path $sql -Pattern 'CREATE TABLE' -AllMatches).Count
    $dbArtefact = "$sql.gz"
    Compress-ToGzip $sql $dbArtefact
    Remove-Item $sql -Force
}

if (-not $tableCount -or $tableCount -le 0) {
    throw "[backup] FATAL: dump contains no tables - aborting"
}
Write-Host "[backup] verification: dump contains $tableCount tables"

$vaultArtefact = $null
if ($IncludeVault) {
    $vault = Join-Path $backend 'storage\app\private\documents'
    if (Test-Path $vault) {
        $vaultArtefact = Join-Path $Destination "tth_vault_$stamp.tar.gz"
        tar -czf $vaultArtefact -C (Split-Path -Parent $vault) (Split-Path -Leaf $vault)
        Write-Host "[backup] vault archived: $vaultArtefact"
    }
}

$manifest = Join-Path $Destination "manifest-$stamp.sha256"
$lines = @()
foreach ($artefact in @($dbArtefact, $vaultArtefact)) {
    if ($artefact -and (Test-Path $artefact)) {
        $hash = (Get-FileHash -Algorithm SHA256 -Path $artefact).Hash.ToLower()
        $lines += "$hash  $(Split-Path -Leaf $artefact)"
    }
}
$lines | Out-File -FilePath $manifest -Encoding ascii
Write-Host "[backup] manifest: $manifest"
Write-Host "[backup] done"
