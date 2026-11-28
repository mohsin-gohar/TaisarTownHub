# TaisarTownHub - automated MySQL backup for Windows hosts (dev + staging).
#
# Usage:  powershell -ExecutionPolicy Bypass -File scripts\backup.ps1 -Destination <dir>
#
# Produces: <dest>\tth_db_<stamp>.sql.gz, optional <dest>\tth_vault_<stamp>.tar.gz
#           and a sha256 manifest. Nothing on the live system is ever deleted.
# MySQL 8 / MariaDB is the only supported database engine.

param(
    [string]$Destination = "$env:LOCALAPPDATA\Temp\opencode\tth-backups",
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
    $input = [System.IO.File]::OpenRead($source)
    $out = [System.IO.File]::Create($target)
    try {
        $gz = New-Object System.IO.Compression.GZipStream($out, [System.IO.Compression.CompressionMode]::Compress)
        try { $input.CopyTo($gz) } finally { $gz.Dispose() }
    } finally { $input.Dispose(); $out.Dispose() }
}

$driver = Read-DotEnvValue 'DB_CONNECTION' 'mysql'
if ($driver -ne 'mysql') {
    throw "[backup] FATAL: only DB_CONNECTION=mysql is supported (found '$driver')"
}

$name = Read-DotEnvValue 'DB_DATABASE'
$user = Read-DotEnvValue 'DB_USERNAME'
$hostName = Read-DotEnvValue 'DB_HOST' '127.0.0.1'
$port = Read-DotEnvValue 'DB_PORT' '3306'
$password = Read-DotEnvValue 'DB_PASSWORD'

$dumpTool = if ($env:MYSQLDUMP) { $env:MYSQLDUMP } else { 'mysqldump' }
if (-not $env:MYSQLDUMP -and -not (Get-Command mysqldump -ErrorAction SilentlyContinue)) {
    $xamppDump = 'F:\Xampp\mysql\bin\mysqldump.exe'
    if (Test-Path $xamppDump) { $dumpTool = $xamppDump }
}

Write-Host "[backup] MySQL dump of database '$name' from $hostName`:$port"
$sql = Join-Path $Destination "tth_db_$stamp.sql"
$env:MYSQL_PWD = $password
& $dumpTool --single-transaction --routines --triggers --events --hex-blob -h $hostName -P $port -u $user $name | Out-File -FilePath $sql -Encoding utf8
$dumpExit = $LASTEXITCODE
Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
if ($dumpExit -ne 0) { throw "[backup] FATAL: mysqldump failed with exit code $LASTEXITCODE" }

$tableCount = (Select-String -Path $sql -Pattern 'CREATE TABLE' -AllMatches).Count
if ($tableCount -le 0 -or -not (Select-String -Path $sql -Pattern 'Dump completed' -Quiet)) {
    throw "[backup] FATAL: dump contains no tables - aborting"
}
Write-Host "[backup] verification: dump contains $tableCount tables"

$dbArtefact = "$sql.gz"
Compress-ToGzip $sql $dbArtefact
Remove-Item $sql -Force

$vaultArtefact = $null
$vault = Join-Path $backend 'storage\app\private\documents'
    if (-not (Test-Path -LiteralPath $vault -PathType Container)) { throw "Private vault is missing: $vault; database-only backup is forbidden." }
    if (Test-Path $vault) {
        $vaultArtefact = Join-Path $Destination "tth_vault_$stamp.tar.gz"
        tar -czf $vaultArtefact -C (Split-Path -Parent $vault) (Split-Path -Leaf $vault)
        if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $vaultArtefact) -or (Get-Item -LiteralPath $vaultArtefact).Length -eq 0) { throw "Private vault archive creation failed." }
        $vaultEntries = @(tar -tzf $vaultArtefact)
        if ($LASTEXITCODE -ne 0 -or $vaultEntries.Count -eq 0) { throw "Private vault archive integrity check failed." }
        Write-Host "[backup] vault archived: $vaultArtefact"
} else { throw "Private vault archive creation failed." }

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
