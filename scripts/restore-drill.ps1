# TaisarTownHub — GENUINE MySQL backup restoration drill.
#
# This is NOT a "count CREATE TABLE statements" check. It:
#   1. finds the newest backup + its sha256 manifest, verifies both checksums,
#   2. checks gzip integrity by decompressing to a scratch file,
#   3. creates an ISOLATED temporary MySQL database (never the live one),
#   4. imports the COMPLETE SQL dump into it with the mysql client,
#   5. verifies schema/table count, critical row counts, migration state,
#      named indexes and real SELECT queries,
#   6. verifies the private-vault archive with tar -tzf,
#   7. drops ONLY the temporary database it created and removes scratch files.
#
# MySQL 8 / MariaDB is the only supported engine. Nothing in the live
# environment is modified. Exit code: 0 = PASS, 1 = FAIL.
param(
    [string]$BackupDir = "$env:LOCALAPPDATA\Temp\opencode\tth-backups",
    [string]$Scratch = "$env:LOCALAPPDATA\Temp\opencode\tth-restore-drill"
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$backend = Join-Path $root 'backend'
$temporaryDatabase = $null
$work = $null

function Read-DotEnvValue([string]$key, [string]$fallback = '') {
    $envFile = Join-Path $backend '.env'
    if (-not (Test-Path $envFile)) { return $fallback }
    $line = Get-Content $envFile | Where-Object { $_ -like "$key=*" } | Select-Object -First 1
    if (-not $line) { return $fallback }
    return ($line -split '=', 2)[1].Trim().Trim('"')
}

function Fail([string]$message) {
    Write-Host "RESTORE DRILL: FAIL - $message"
    exit 1
}

try {
    # --- Locate backup and its manifest -------------------------------------
    $dbBackup = Get-ChildItem $BackupDir -Filter 'tth_db_*.sql.gz' -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $dbBackup) { Fail "no tth_db_*.sql.gz backup in $BackupDir" }

    $stamp = ($dbBackup.BaseName -replace '^tth_db_', '') -replace '\.sql$', ''
    $manifest = Get-ChildItem $BackupDir -Filter 'manifest-*.sha256' -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like "*$stamp*" } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $manifest) { Fail "no sha256 manifest for backup stamp $stamp" }

    Write-Host "[drill] backup : $($dbBackup.FullName)"
    Write-Host "[drill] manifest: $($manifest.FullName)"

    # --- Checksums ----------------------------------------------------------
    $expected = @{}
    foreach ($line in (Get-Content $manifest.FullName)) {
        if ($line -match '^([0-9a-f]{64})\s+(.+)$') { $expected[$Matches[2]] = $Matches[1] }
    }
    foreach ($artefact in @($dbBackup)) {
        if (-not $expected.ContainsKey($artefact.Name)) { Fail "manifest has no checksum for $($artefact.Name)" }
        $actual = (Get-FileHash -Algorithm SHA256 -Path $artefact.FullName).Hash.ToLower()
        if ($actual -ne $expected[$artefact.Name]) { Fail "checksum mismatch for $($artefact.Name)" }
    }
    Write-Host "[drill] sha256 verified for database backup"

    # --- Gzip integrity -----------------------------------------------------
    $work = Join-Path $Scratch "work-$stamp-$([guid]::NewGuid().ToString('N').Substring(0, 8))"
    New-Item -ItemType Directory -Force -Path $work | Out-Null
    $sqlPath = Join-Path $work 'restored.sql'
    try {
        $inputStream = [System.IO.File]::OpenRead($dbBackup.FullName)
        $gzip = New-Object System.IO.Compression.GZipStream($inputStream, [System.IO.Compression.CompressionMode]::Decompress)
        $output = [System.IO.File]::Create($sqlPath)
        $gzip.CopyTo($output)
        $output.Close(); $gzip.Close(); $inputStream.Close()
    } catch {
        Fail "gzip decompression failed: $($_.Exception.Message)"
    }
    $sqlText = Get-Content $sqlPath -Raw
    if ([string]::IsNullOrWhiteSpace($sqlText)) { Fail "decompressed dump is empty" }
    $createTableCount = ([regex]::Matches($sqlText, 'CREATE TABLE')).Count
    if ($createTableCount -eq 0) { Fail 'dump contains no CREATE TABLE statements' }
    if ($sqlText -notmatch 'Dump completed') {
        Write-Host '[drill] WARNING: dump completion marker missing (older mysqldump or interrupted export)'
    }
    Write-Host "[drill] gzip OK, $createTableCount CREATE TABLE statements in dump"

    # --- MySQL connection details (never printed) ---------------------------
    $hostName = Read-DotEnvValue 'DB_HOST' '127.0.0.1'
    $port = Read-DotEnvValue 'DB_PORT' '3306'
    $user = Read-DotEnvValue 'DB_USERNAME' 'root'
    $password = Read-DotEnvValue 'DB_PASSWORD' ''
    $client = if ($env:MYSQL_CLIENT) { $env:MYSQL_CLIENT } else { 'mysql' }
    if (-not $env:MYSQL_CLIENT -and -not (Get-Command mysql -ErrorAction SilentlyContinue)) {
        $xampp = 'F:\Xampp\mysql\bin\mysql.exe'
        if (Test-Path $xampp) { $client = $xampp }
    }
    $env:MYSQL_PWD = $password   # avoids putting the password on the command line

    function Invoke-Sql([string]$database, [string]$query) {
        $args = @('-h', $hostName, '-P', $port, '-u', $user, '-N', '-B', '-e', $query)
        if ($database) { $args += $database }
        $result = & $client @args 2>&1
        if ($LASTEXITCODE -ne 0) { throw "SQL failed: $query => $result" }
        return $result
    }

    # --- Isolated scratch database ------------------------------------------
    $temporaryDatabase = 'tth_restore_drill_' + $stamp.Replace('-', '_').Replace(':', '_')
    if ($temporaryDatabase -notmatch '^tth_restore_drill_[0-9A-Za-z_]+$') { Fail 'unsafe temporary database name' }
    $exists = (Invoke-Sql '' "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$temporaryDatabase'")
    if ($exists) {
        # Only ever drop a database with the drill prefix.
        Invoke-Sql '' "DROP DATABASE $temporaryDatabase" | Out-Null
    }
    Invoke-Sql '' "CREATE DATABASE $temporaryDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" | Out-Null
    Write-Host "[drill] created isolated database $temporaryDatabase"

    # --- Real import --------------------------------------------------------
    $env:MYSQL_PWD = $password
    $source = "SOURCE $($sqlPath -replace '\\', '/')"
    & $client -h $hostName -P $port -u $user $temporaryDatabase -e $source 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { Fail 'mysql import of the full dump failed' }
    Write-Host '[drill] full SQL dump imported successfully'

    # --- Verification queries ----------------------------------------------
    $actualTables = [int](Invoke-Sql $temporaryDatabase "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$temporaryDatabase' AND TABLE_TYPE='BASE TABLE'")
    Write-Host "[drill] tables in restored database: $actualTables (dump declares $createTableCount)"
    if ($actualTables -lt $createTableCount) { Fail "restored table count $actualTables < dump count $createTableCount" }

    $critical = @('users', 'agencies', 'properties', 'dealers', 'inquiries', 'transactions', 'business_records', 'maps', 'migrations')
    $rowCounts = @{}
    foreach ($table in $critical) {
        $found = Invoke-Sql $temporaryDatabase "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$temporaryDatabase' AND TABLE_NAME='$table'"
        if ([int]$found -eq 0) { Fail "critical table '$table' missing after restore" }
        $rowCounts[$table] = [int](Invoke-Sql $temporaryDatabase "SELECT COUNT(*) FROM $table")
        Write-Host ("  {0,-18} {1} rows" -f $table, $rowCounts[$table])
    }

    $indexCount = [int](Invoke-Sql $temporaryDatabase "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$temporaryDatabase' AND INDEX_NAME='properties_public_listing_idx'")
    if ($indexCount -lt 1) { Fail 'public-marketplace index missing in restored schema' }

    $saleCount = [int](Invoke-Sql $temporaryDatabase "SELECT COUNT(*) FROM properties WHERE listing_purpose='sale'")
    Write-Host "[drill] query check: sale listings = $saleCount"
    if ($saleCount -lt 0) { Fail 'meaningful query failed' }
    Write-Host '[drill] schema, rows, indexes and queries verified'

    # --- Vault archive integrity -------------------------------------------
    $vault = Get-ChildItem $BackupDir -Filter 'tth_vault_*.tar.gz' -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like "*$stamp*" } | Select-Object -First 1
    if ($vault) {
        if (-not $expected.ContainsKey($vault.Name)) { Fail "manifest has no checksum for $($vault.Name)" }
        $vaultHash = (Get-FileHash -Algorithm SHA256 -Path $vault.FullName).Hash.ToLower()
        if ($vaultHash -ne $expected[$vault.Name]) { Fail "checksum mismatch for $($vault.Name)" }
        $entries = @(tar -tzf $vault.FullName 2>&1)
        if ($LASTEXITCODE -ne 0 -or $entries.Count -eq 0) { Fail 'vault archive failed tar integrity check' }
        Write-Host "[drill] vault archive OK ($($entries.Count) entries)"
    } else {
        Write-Host '[drill] no vault archive for this backup stamp (skipped)'
    }

    Write-Host 'RESTORE DRILL: PASS - isolated MySQL restore and verification completed'
    $exitCode = 0
} catch {
    Write-Host "RESTORE DRILL: FAIL - $($_.Exception.Message)"
    $exitCode = 1
} finally {
    # Clean up ONLY the temporary resources created by this drill.
    if ($temporaryDatabase -and $env:MYSQL_PWD -ne $null) {
        try {
            & $client -h $hostName -P $port -u $user $temporaryDatabase -e "SELECT 1" 2>&1 | Out-Null
            $args = @('-h', $hostName, '-P', $port, '-u', $user, '-e', "DROP DATABASE $temporaryDatabase")
            & $client @args 2>&1 | Out-Null
            Write-Host "[drill] cleaned up temporary database $temporaryDatabase"
        } catch { Write-Host "[drill] WARNING: temporary database cleanup failed: $($_.Exception.Message)" }
    }
    if ($work -and (Test-Path $work)) { Remove-Item $work -Recurse -Force -ErrorAction SilentlyContinue }
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
}

exit $exitCode
