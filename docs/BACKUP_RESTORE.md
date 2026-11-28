# TaisarTownHub — Backup & Restore

A valid recovery point is the **database plus the private document vault** from the same timestamp. A database-only or vault-only set is incomplete and must never be treated as restorable.

## Recovery assets

| Asset | Source | Requirement |
|---|---|---|
| MySQL | Logical mysqldump | Schema, rows, indexes, foreign keys, routines, triggers and events |
| Private vault | `backend/storage/app/private/documents` | Required archive; document bytes must accompany metadata |
| Public media | `backend/storage/app/public/media` (if used) | Back up separately when user uploads are stored there |
| APP_KEY and environment | Secret manager / separately encrypted store | Required for encrypted CNIC, MFA and vault files; never store beside backup |
| Redis | Redis persistence / managed backup when Redis-only queues or sessions are enabled | Not included in the MySQL dump |

Backups contain all agencies' private data. Treat them as platform-wide sensitive data: encrypted storage, separate credentials, operator-only access, and never expose the backup directory through the web server.

## Create a backup

Linux: `./scripts/backup.sh [destination-dir]`

Windows: `powershell -ExecutionPolicy Bypass -File scripts/backup.ps1 -Destination <directory>`

Both scripts fail closed if the MySQL dump or private vault is missing, empty or corrupt. They publish a SHA-256 manifest only after both archives pass integrity checks. Database credentials are passed via `MYSQL_PWD`, not a visible `-pPASSWORD` process argument. SHA-256 detects corruption; it does not encrypt data.

## Isolated restore drill (non-production)

Run using PowerShell 7 and a disposable local MySQL 8 / MariaDB instance:

```powershell
pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/restore-drill.ps1 -BackupDir <backup-directory> -Scratch <scratch-directory>
```

The drill requires a matching database dump, private vault archive and checksum manifest; rejects truncated SQL dumps; imports into a unique scratch database; compares exact table sets, per-table row counts, indexes, foreign keys, CHECK TABLE health and FK orphans; checks all non-null agency IDs reference a restored agency; verifies payments, schedules and commissions match their parent transaction agency; extracts and verifies every private-vault archive entry in scratch storage; and drops only resources created by that run.

It refuses non-loopback DB hosts unless `-AllowRemoteHost` is explicit. It never imports into or drops the application database. This validates restoration and structural tenant ownership, not live authorization; verify two separate agency accounts against a separately restored staging environment before signing off disaster recovery.

## Controlled production restore

1. Confirm target host, backup timestamp, manifest, matching vault archive, `APP_KEY`, encrypted storage and approved maintenance window.
2. Stop API writers, queue workers, scheduler and every process writing to the vault.
3. Restore DB and matching vault archive. Never mix timestamps.
4. Restore exact `APP_KEY` and environment/secrets from the secret manager.
5. Restore public media separately if used.
6. Run `php artisan migrate:status`, `php artisan app:production-check --strict` and tenant/finance integrity checks. Do not auto-run migrations as part of restore.
7. Reconcile document metadata against vault files; test private access with two agency accounts.
8. Record operator, timestamp and backup checksum in the audit trail. Restart writers only after checks pass.

If any integrity or ownership check fails, keep the application offline and use a known-good recovery point.

## Encryption and access

- Encrypt backup storage (encrypted volume or encrypted object-storage bucket); SHA-256 is not encryption.
- Store `APP_KEY`, DB and backup-storage credentials separately.
- Restrict backup files to designated platform backup operators. Agency users must never receive direct backup-file access.
- Laravel field encryption is not full disk encryption. Production requires encrypted database/storage volumes and encrypted backup storage.

## Retention and monitoring

The Linux helper applies a 180-day local staging ceiling. Off-site storage should implement a lifecycle such as 7 daily, 4 weekly and 6 monthly recovery points. Do not independently delete DB, vault or manifest files.

Alert on missing backup (>26h), checksum mismatch, incomplete DB/vault pair, unexpected size drift, failed restore drill/cleanup, and non-zero tenant or finance integrity violations.
