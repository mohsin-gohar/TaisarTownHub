# TaisarTownHub — GENUINE MySQL backup restoration drill (safety-first).
#
# This is NOT a "count CREATE TABLE statements" check. It:
#   1. finds the newest tth_db_*.sql.gz backup and its sha256 manifest,
#   2. verifies every artefact checksum and the gzip stream,
#   3. parses the dump itself for the EXPECTED schema (tables, named indexes,
#      foreign keys) and EXPECTED row counts (extended INSERT tuples),
#   4. allocates a UNIQUE scratch database (timestamp + random suffix), never
#      drops or reuses a database it did not create in this exact run,
#   5. imports the COMPLETE dump through the mysql client's stdin (space-safe),
#   6. verifies exact table set equality, every expected index, every foreign
#      key (orphan scan), CHECK TABLE health and exact per-table row counts
#      derived from the backup,
#   7. extracts the private-vault archive into scratch storage, rejects path
#      traversal and confirms every archived entry was restored,
#   8. drops ONLY the scratch database this run created, deletes ONLY this
#      run's scratch directory, then VERIFIES both are gone (success & failure).
#
# Safety:
#   * refuses non-loopback hosts unless -AllowRemoteHost is passed explicitly,
#   * never connects to the application database, never touches production,
#   * never deletes a pre-existing database and never "cleans" the backup dir,
#   * credentials are read from backend/.env and never printed.
#
# MySQL 8 (CI) and MySQL-protocol MariaDB (local XAMPP) are supported.
# Exit code: 0 = PASS (including verified cleanup), 1 = FAIL.
param(
    [string]$BackupDir = "$env:LOCALAPPDATA\Temp\opencode\tth-backups",
    [string]$Scratch = "$env:LOCALAPPDATA\Temp\opencode\tth-restore-drill",
    [switch]$AllowRemoteHost
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$backend = Join-Path $root 'backend'
$temporaryDatabase = $null
$createdDatabase = $false
$work = $null
$scratchExisted = $false
$client = $null
$hostName = $null
$port = $null
$user = $null

function Read-DotEnvValue([string]$key, [string]$fallback = '') {
    $envFile = Join-Path $backend '.env'
    if (-not (Test-Path $envFile)) { return $fallback }
    $line = Get-Content $envFile | Where-Object { $_ -like "$key=*" } | Select-Object -First 1
    if (-not $line) { return $fallback }
    return ($line -split '=', 2)[1].Trim().Trim('"')
}

function Invoke-Sql([string]$database, [string]$query) {
    $sqlArgs = @('-h', $hostName, '-P', "$port", '-u', $user, '-N', '-B')
    if ($database) { $sqlArgs += $database }
    $sqlArgs += @('-e', $query)
    $result = & $client @sqlArgs 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "SQL failed (exit $LASTEXITCODE): $query => $($result -join ' ')"
    }
    return @($result)
}

function Invoke-SqlScalar([string]$database, [string]$query) {
    # @() is required: PowerShell unrolls a single-element array returned by a
    # function into a scalar string, and indexing that string would yield its
    # first CHARACTER (e.g. "11" -> "1"). That produced false row-count drift.
    $rows = @(Invoke-Sql $database $query)
    if ($rows.Count -eq 0) { return $null }
    return "$($rows[0])".Trim()
}

function Invoke-MysqlImport([string]$database, [string]$file) {
    # File redirection through stdin avoids every shell/SOURCE path-quoting
    # problem (spaces, parentheses, non-ASCII) on Windows and Linux.
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $client
    $psi.Arguments = ('-h "{0}" -P {1} -u "{2}" --binary-mode --default-character-set=utf8mb4 "{3}"' -f $hostName, $port, $user, $database)
    $psi.UseShellExecute = $false
    $psi.RedirectStandardInput = $true
    $psi.RedirectStandardError = $true
    $psi.CreateNoWindow = $true
    $proc = New-Object System.Diagnostics.Process
    $proc.StartInfo = $psi
    if (-not $proc.Start()) { throw "could not start mysql client '$client'" }
    $errTask = $proc.StandardError.ReadToEndAsync()
    $stream = [System.IO.File]::OpenRead($file)
    try {
        $stream.CopyTo($proc.StandardInput.BaseStream)
    } finally {
        $stream.Close()
        $proc.StandardInput.Close()
    }
    $proc.WaitForExit()
    $errText = $errTask.Result
    if ($proc.ExitCode -ne 0) {
        throw "mysql import of '$file' failed (exit $($proc.ExitCode)): $($errText.Trim())"
    }
    if (-not [string]::IsNullOrWhiteSpace($errText)) {
        Write-Host "[drill] mysql import notes: $($errText.Trim())"
    }
}

# Quoting-aware counter for mysqldump extended INSERT statements. Counts the
# top-level value tuples per table so restored row counts can be compared to
# expectations derived from the backup itself.
function Get-InsertRowCounts([string]$sql) {
    $counts = @{}
    $pattern = [regex]'(?m)^INSERT INTO `([^`]+)` VALUES '
    foreach ($match in $pattern.Matches($sql)) {
        $table = $match.Groups[1].Value
        $i = $match.Index + $match.Length
        $len = $sql.Length
        $inQuote = $false
        $depth = 0
        $rows = 0
        while ($i -lt $len) {
            $c = $sql[$i]
            if ($inQuote) {
                if ($c -eq '\') { $i += 2; continue }
                if ($c -eq "'") {
                    if ($i + 1 -lt $len -and $sql[$i + 1] -eq "'") { $i += 2; continue }
                    $inQuote = $false
                }
                $i++
                continue
            }
            if ($c -eq "'") { $inQuote = $true; $i++; continue }
            if ($c -eq '(') { $depth++; $i++; continue }
            if ($c -eq ')') {
                $depth--
                if ($depth -eq 0) { $rows++ }
                $i++
                continue
            }
            if ($c -eq ';' -and $depth -eq 0) { $i++; break }
            $i++
        }
        if ($counts.ContainsKey($table)) { $counts[$table] += $rows } else { $counts[$table] = $rows }
    }
    return $counts
}

function Get-DumpExpectations([string]$sql) {
    $tables = @{}   # table -> list of index names (including PRIMARY)
    $foreignKeys = New-Object System.Collections.Generic.List[object]
    $current = $null
    foreach ($line in ($sql -split "`r?`n")) {
        if ($line -match '^CREATE TABLE `([^`]+)` \(') {
            $current = $Matches[1]
            $tables[$current] = New-Object System.Collections.Generic.List[string]
            continue
        }
        if (-not $current) { continue }
        if ($line -match '^\) ENGINE') { $current = $null; continue }
        if ($line -match '^\s+PRIMARY KEY \(') {
            $tables[$current].Add('PRIMARY')
        } elseif ($line -match '^\s+(?:UNIQUE |FULLTEXT |SPATIAL )?KEY `([^`]+)`') {
            $tables[$current].Add($Matches[1])
        }
        if ($line -match 'FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)') {
            $foreignKeys.Add([pscustomobject]@{
                Table        = $current
                Column       = $Matches[1]
                Parent       = $Matches[2]
                ParentColumn = $Matches[3]
            })
        }
    }
    return [pscustomobject]@{
        Tables      = $tables
        ForeignKeys = $foreignKeys
        RowCounts   = (Get-InsertRowCounts $sql)
    }
}

function Quote-Identifier([string]$name) {
    return $name.Replace('`', '``')
}

function Quote-Literal([string]$value) {
    return $value.Replace("'", "''")
}

$exitCode = 0
$cleanupErrors = New-Object System.Collections.Generic.List[string]

try {
    # ---------------------------------------------------------------- locate
    $dbBackup = Get-ChildItem -LiteralPath $BackupDir -Filter 'tth_db_*.sql.gz' -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $dbBackup) { throw "no tth_db_*.sql.gz backup in '$BackupDir'" }

    $stamp = ($dbBackup.BaseName -replace '^tth_db_', '') -replace '\.sql$', ''
    $manifest = Get-ChildItem -LiteralPath $BackupDir -Filter 'manifest-*.sha256' -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like "*$stamp*" } | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if (-not $manifest) { throw "no sha256 manifest for backup stamp '$stamp'" }

    $vault = Get-ChildItem -LiteralPath $BackupDir -Filter 'tth_vault_*.tar.gz' -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like "*$stamp*" } | Select-Object -First 1
    if (-not $vault) { throw "private vault archive is missing for backup stamp '$stamp'; database-only restore is forbidden" }

    Write-Host "[drill] backup  : $($dbBackup.FullName)"
    Write-Host "[drill] manifest: $($manifest.FullName)"

    # ---------------------------------------------------------------- checksums
    $expected = @{}
    foreach ($line in (Get-Content -LiteralPath $manifest.FullName)) {
        # Accept both "<hash>  <file>" and "<hash> *<file>" (some sha256sum
        # builds mark binary files with a leading asterisk).
        if ($line -match '^([0-9a-fA-F]{64})\s+\*?(.+)$') { $expected[$Matches[2].Trim()] = $Matches[1].ToLower() }
    }
    foreach ($artefact in @($dbBackup, $vault)) {
        if (-not $artefact) { continue }
        if (-not $expected.ContainsKey($artefact.Name)) { throw "manifest has no checksum for '$($artefact.Name)'" }
        $actualHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $artefact.FullName).Hash.ToLower()
        if ($actualHash -ne $expected[$artefact.Name]) { throw "checksum mismatch for '$($artefact.Name)'" }
    }
    Write-Host '[drill] sha256 verified for database backup and vault archive'

    # ---------------------------------------------------------------- gzip
    $scratchExisted = Test-Path -LiteralPath $Scratch
    if (-not $scratchExisted) { New-Item -ItemType Directory -Force -Path $Scratch | Out-Null }
    $work = Join-Path $Scratch ("work-{0}-{1}" -f $stamp, ([guid]::NewGuid().ToString('N').Substring(0, 8)))
    New-Item -ItemType Directory -Force -Path $work | Out-Null
    $sqlPath = Join-Path $work 'restored.sql'
    try {
        $inputStream = [System.IO.File]::OpenRead($dbBackup.FullName)
        try {
            $gzip = New-Object System.IO.Compression.GZipStream($inputStream, [System.IO.Compression.CompressionMode]::Decompress)
            try {
                $output = [System.IO.File]::Create($sqlPath)
                try { $gzip.CopyTo($output) } finally { $output.Close() }
            } finally { $gzip.Close() }
        } finally { $inputStream.Close() }
    } catch {
        throw "gzip decompression failed: $($_.Exception.Message)"
    }
    if ((Get-Item -LiteralPath $sqlPath).Length -eq 0) { throw 'decompressed dump is empty' }
    $sqlText = [System.IO.File]::ReadAllText($sqlPath)
    if ($sqlText -notmatch 'Dump completed') {
        throw 'dump is incomplete: the "Dump completed" marker is missing (interrupted export)'
    }

    $expect = Get-DumpExpectations $sqlText
    if ($expect.Tables.Count -eq 0) { throw 'dump contains no CREATE TABLE statements' }
    Write-Host "[drill] parsed backup: $($expect.Tables.Count) tables, $($expect.ForeignKeys.Count) foreign keys, $($expect.RowCounts.Count) tables with data"

    # ---------------------------------------------------------------- connect
    $hostName = Read-DotEnvValue 'DB_HOST' '127.0.0.1'
    $port = Read-DotEnvValue 'DB_PORT' '3306'
    $user = Read-DotEnvValue 'DB_USERNAME' 'root'
    $password = Read-DotEnvValue 'DB_PASSWORD' ''
    if ($hostName -notin @('127.0.0.1', 'localhost', '::1') -and -not $AllowRemoteHost) {
        throw "refusing to run against non-loopback host '$hostName' without -AllowRemoteHost (production safety)"
    }
    $client = if ($env:MYSQL_CLIENT) { $env:MYSQL_CLIENT } else { 'mysql' }
    # Resolve to an absolute path: ProcessStartInfo does not reliably search
    # PATH on every platform, and the import uses it directly.
    $resolvedClient = @(Get-Command $client -ErrorAction SilentlyContinue) | Select-Object -First 1
    if ($resolvedClient) {
        $client = $resolvedClient.Source
    } elseif (-not $env:MYSQL_CLIENT) {
        $xampp = 'F:\Xampp\mysql\bin\mysql.exe'
        if (Test-Path -LiteralPath $xampp) { $client = $xampp }
    }
    if (-not (Get-Command $client -ErrorAction SilentlyContinue)) {
        throw "mysql client not found ('$client'); set MYSQL_CLIENT to its full path"
    }
    $env:MYSQL_PWD = $password   # only via environment; never on the command line

    # ---------------------------------------------------------------- scratch DB
    # Unique name per run. A pre-existing database is NEVER dropped or reused.
    for ($attempt = 0; $attempt -lt 5; $attempt++) {
        $candidate = 'tth_restore_drill_{0}_{1}' -f (Get-Date -Format 'yyyyMMdd_HHmmss'), ([guid]::NewGuid().ToString('N').Substring(0, 8))
        $exists = Invoke-SqlScalar '' "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$candidate'"
        if ($exists -eq '0') { $temporaryDatabase = $candidate; break }
    }
    if (-not $temporaryDatabase) { throw 'could not allocate a unique scratch database name' }

    Invoke-Sql '' "CREATE DATABASE ``$temporaryDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" | Out-Null
    $createdDatabase = $true
    Write-Host "[drill] created isolated scratch database $temporaryDatabase"

    # ---------------------------------------------------------------- import
    Invoke-MysqlImport $temporaryDatabase $sqlPath
    Write-Host '[drill] complete SQL dump imported successfully'

    # ---------------------------------------------------------------- schema/rows
    $actualTables = @(Invoke-Sql $temporaryDatabase "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$temporaryDatabase' AND TABLE_TYPE='BASE TABLE'" |
        Where-Object { $_ -and "$_".Trim() } | ForEach-Object { "$_".Trim() })
    $expectedTables = @($expect.Tables.Keys | Sort-Object)
    $missingTables = @($expectedTables | Where-Object { $actualTables -notcontains $_ })
    $extraTables = @($actualTables | Where-Object { $expectedTables -notcontains $_ })
    if ($missingTables.Count -gt 0) { throw "restored schema is missing tables: $($missingTables -join ', ')" }
    if ($extraTables.Count -gt 0) { throw "restored schema has unexpected tables: $($extraTables -join ', ')" }
    Write-Host "[drill] table set matches the backup exactly ($($actualTables.Count) tables)"

    foreach ($table in $expectedTables) {
        $expectedRows = if ($expect.RowCounts.ContainsKey($table)) { [int]$expect.RowCounts[$table] } else { 0 }
        $query = 'SELECT COUNT(*) FROM `{0}`' -f (Quote-Identifier $table)
        $actualRows = [int](Invoke-SqlScalar $temporaryDatabase $query)
        if ($actualRows -ne $expectedRows) {
            throw "row-count mismatch for '$table': backup has $expectedRows row(s), restored database has $actualRows"
        }
    }
    Write-Host '[drill] every table row count matches the count derived from the backup'

    $checkedIndexes = 0
    foreach ($table in $expectedTables) {
        foreach ($index in $expect.Tables[$table]) {
            $q = "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$temporaryDatabase' AND TABLE_NAME='$(Quote-Literal $table)' AND INDEX_NAME='$(Quote-Literal $index)'"
            if ([int](Invoke-SqlScalar $temporaryDatabase $q) -lt 1) {
                throw "index '$index' is missing on table '$table' after restore"
            }
            $checkedIndexes++
        }
    }
    Write-Host "[drill] all $checkedIndexes expected indexes are present"

    $checkTargets = ($actualTables | ForEach-Object { '`{0}`' -f (Quote-Identifier $_) }) -join ', '
    $checkFailures = @(Invoke-Sql $temporaryDatabase "CHECK TABLE $checkTargets" | Where-Object { $_ -and ($_ -notmatch "`tOK$") })
    if ($checkFailures.Count -gt 0) { throw "CHECK TABLE reported problems: $($checkFailures -join ' | ')" }
    Write-Host "[drill] CHECK TABLE reports OK for all $($actualTables.Count) tables"

    foreach ($fk in $expect.ForeignKeys) {
        $q = 'SELECT COUNT(*) FROM `{0}` c LEFT JOIN `{1}` p ON c.`{2}` = p.`{3}` WHERE c.`{2}` IS NOT NULL AND p.`{3}` IS NULL' -f `
            (Quote-Identifier $fk.Table), (Quote-Identifier $fk.Parent), (Quote-Identifier $fk.Column), (Quote-Identifier $fk.ParentColumn)
        $orphans = [int](Invoke-SqlScalar $temporaryDatabase $q)
        if ($orphans -ne 0) {
            throw "foreign-key integrity violation: $($fk.Table).$($fk.Column) -> $($fk.Parent).$($fk.ParentColumn) has $orphans orphaned row(s)"
        }
    }
    Write-Host "[drill] all $($expect.ForeignKeys.Count) foreign-key relationships are free of orphaned rows"

    # Every non-null tenant key must reference a restored agency, including
    # legacy tables that do not have a physical agency_id foreign key.
    if ($expect.Tables.ContainsKey('agencies')) {
        $agencyTables = @(Invoke-Sql $temporaryDatabase "SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$temporaryDatabase' AND COLUMN_NAME='agency_id' ORDER BY TABLE_NAME")
        foreach ($tableName in $agencyTables) {
            $tableName = "$tableName".Trim()
            if (-not $tableName) { continue }
            $q = 'SELECT COUNT(*) FROM `{0}` r LEFT JOIN agencies a ON a.id = r.agency_id WHERE r.agency_id IS NOT NULL AND a.id IS NULL' -f (Quote-Identifier $tableName)
            $orphans = [int](Invoke-SqlScalar $temporaryDatabase $q)
            if ($orphans -ne 0) { throw "tenant ownership integrity violation: $tableName has $orphans row(s) referencing a missing agency" }
        }
        Write-Host "[drill] all non-null agency_id values reference restored agency rows"
    }

    # Financial child rows must carry the same tenant as their parent deal.
    foreach ($childTable in @('transaction_payments', 'payment_schedules', 'commissions')) {
        if (-not $expect.Tables.ContainsKey($childTable) -or -not $expect.Tables.ContainsKey('transactions')) { continue }
        $q = 'SELECT COUNT(*) FROM `{0}` c INNER JOIN transactions t ON t.id = c.transaction_id WHERE NOT (c.agency_id <=> t.agency_id)' -f (Quote-Identifier $childTable)
        $mismatches = [int](Invoke-SqlScalar $temporaryDatabase $q)
        if ($mismatches -ne 0) { throw "cross-tenant finance integrity violation: $childTable has $mismatches mismatched row(s)" }
    }
    Write-Host '[drill] finance child/parent agency ownership matches for payments, schedules and commissions'

    # Meaningful application query against the restored copy.
    if ($expect.Tables.ContainsKey('properties')) {
        $saleListings = Invoke-SqlScalar $temporaryDatabase "SELECT COUNT(*) FROM properties WHERE listing_purpose='sale'"
        $otherListings = Invoke-SqlScalar $temporaryDatabase "SELECT COUNT(*) FROM properties WHERE listing_purpose IS NOT NULL AND listing_purpose <> 'sale'"
        Write-Host "[drill] restored catalog: $saleListings sale listing(s), $otherListings non-sale listing(s)"
    }
    if ($expect.Tables.ContainsKey('migrations')) {
        $migrationCount = Invoke-SqlScalar $temporaryDatabase 'SELECT COUNT(*) FROM migrations'
        Write-Host "[drill] restored migration history: $migrationCount applied migration(s)"
        if ([int]$migrationCount -lt 1) { throw 'restored migrations table is empty' }
    }

    # ---------------------------------------------------------------- vault
    if ($vault) {
        Write-Host "[drill] verifying private vault archive $($vault.Name)"
        $entries = @(& tar -tzf $vault.FullName 2>&1)
        if ($LASTEXITCODE -ne 0) { throw "vault archive failed tar integrity check: $($entries -join ' ')" }
        if ($entries.Count -eq 0) { throw 'vault archive contains no entries' }
        foreach ($entry in $entries) {
            $normalized = "$entry".Replace('\', '/')
            if ($normalized -match '^([A-Za-z]:|//|/)' -or ($normalized -split '/') -contains '..') {
                throw "vault archive contains an unsafe path: $entry"
            }
        }
        $vaultDir = Join-Path $work 'vault'
        New-Item -ItemType Directory -Force -Path $vaultDir | Out-Null
        & tar -xzf $vault.FullName -C $vaultDir 2>&1 | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'vault extraction into scratch storage failed' }

        # Build the extracted inventory with -Force so hidden files (for
        # example a vault's .gitignore) are included on every platform. A
        # single listing is also immune to per-entry Get-Item quirks under
        # PowerShell 7 on Linux.
        $vaultRoot = [System.IO.Path]::GetFullPath($vaultDir)
        $allExtracted = @(Get-ChildItem -LiteralPath $vaultDir -Force -Recurse -ErrorAction Stop)
        $actualEntries = @($allExtracted | ForEach-Object {
            $full = [System.IO.Path]::GetFullPath($_.FullName)
            $relativeName = $full.Substring($vaultRoot.Length).TrimStart([System.IO.Path]::DirectorySeparatorChar)
            $relativeName.Replace('\', '/')
        })
        foreach ($entry in $entries) {
            $relativeName = "$entry".Replace('\', '/').Trim().TrimEnd('/')
            if (-not $relativeName -or $relativeName -eq '.') { continue }
            if ($actualEntries -notcontains $relativeName) {
                throw "vault entry missing after extraction: $entry"
            }
        }
        $restoredFiles = @($allExtracted | Where-Object { -not $_.PSIsContainer })
        $restoredBytes = [long](($restoredFiles | Measure-Object -Property Length -Sum).Sum)
        Write-Host "[drill] vault extracted into scratch storage: $($restoredFiles.Count) file(s), $restoredBytes byte(s)"
    }
} catch {
    Write-Host "RESTORE DRILL: FAIL - $($_.Exception.Message)"
    $exitCode = 1
} finally {
    # Cleanup ONLY resources created by THIS execution, then verify cleanup.
    if ($createdDatabase -and $temporaryDatabase) {
        try {
            Invoke-Sql '' "DROP DATABASE ``$temporaryDatabase``" | Out-Null
        } catch {
            $cleanupErrors.Add("could not drop scratch database '$temporaryDatabase': $($_.Exception.Message)")
        }
        try {
            $stillThere = Invoke-SqlScalar '' "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$temporaryDatabase'"
            if ($stillThere -ne '0') { $cleanupErrors.Add("scratch database '$temporaryDatabase' still exists after cleanup") }
        } catch {
            $cleanupErrors.Add("could not verify removal of scratch database '$temporaryDatabase': $($_.Exception.Message)")
        }
    }
    if ($work -and (Test-Path -LiteralPath $work)) {
        try { Remove-Item -LiteralPath $work -Recurse -Force } catch { }
        if (Test-Path -LiteralPath $work) { $cleanupErrors.Add("scratch directory still exists after cleanup: $work") }
    }
    # If this run created the scratch root, remove it too (only when empty).
    if (-not $scratchExisted -and (Test-Path -LiteralPath $Scratch)) {
        try { Remove-Item -LiteralPath $Scratch -Force } catch { }
        if (Test-Path -LiteralPath $Scratch) {
            $cleanupErrors.Add("scratch root directory still exists after cleanup: $Scratch")
        }
    }
    Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue

    if ($cleanupErrors.Count -gt 0) {
        foreach ($cleanupError in $cleanupErrors) { Write-Host "[drill] CLEANUP FAILURE: $cleanupError" }
        Write-Host 'RESTORE DRILL: FAIL - cleanup verification failed'
        $exitCode = 1
    } elseif ($temporaryDatabase) {
        Write-Host "[drill] cleanup verified: scratch database dropped, scratch directory removed"
    }
}

if ($exitCode -eq 0) {
    Write-Host 'RESTORE DRILL: PASS - isolated MySQL restore, data integrity and cleanup verified'
}
exit $exitCode
