# BACKUP_RESTORE_TEST_REPORT.md — TaisarTownHub

**Date:** 2026-11-14 · **Verdict: PASS** — a backup was taken, verified and restored into a
scratch copy that matched the live database table-for-table.

---

## 1. What was executed

```powershell
# 1) backup (database + private vault + SHA-256 manifest)
powershell -ExecutionPolicy Bypass -File scripts\backup.ps1 `
  -Destination "$env:LOCALAPPDATA\Temp\opencode\tth-backups" -IncludeVault

# 2) restore drill (restores into a SCRATCH copy, never over the live database)
powershell -ExecutionPolicy Bypass -File scripts\restore-drill.ps1 `
  -BackupDir "$env:LOCALAPPDATA\Temp\opencode\tth-backups"
```

Production (Linux) equivalent: `scripts/backup.sh [destination]` (mysqldump `--single-transaction`
for MySQL/MariaDB, sqlite copy otherwise, tar of the vault, sha256 manifest, retention pruning).

## 2. Backup evidence

| Artefact | Result |
|---|---|
| Source database | `backend/database/database.sqlite` (dev; the production template targets MySQL/MariaDB) |
| Driver detected | `sqlite` (read from `.env`) → the script adapts automatically |
| Artefacts produced | `tth_db_20261113_233246.sqlite.gz` (114 740 bytes) + `tth_vault_20261113_233246.tar.gz` + `manifest-20261113_233246.sha256` |
| Verification performed by the script | **53 tables** present in the dump (fails fast if 0) |
| Vault archive | `storage/app/private/documents` → tar.gz |
| Manifest | SHA-256 for every artefact |
| Destruction | none — the script only writes into the backup folder and prunes artefacts **older than 30 days** inside it |

## 3. Restore-drill evidence (the part that matters)

```
[drill] source backup: ...\tth_db_20261113_233246.sqlite.gz (114740 bytes)
[drill] restored copy: ...\tth-restore-drill\tth_db_20261113_233246.sqlite (1191936 bytes)

table                restored      live     match
users                14           14        yes
agencies             9            9         yes
properties           6            6         yes
dealers              13           13        yes
inquiries            13           13        yes
transactions         1            1         yes
business_records     103          103       yes
login_histories      81           81        yes
permissions          62           62        yes
role_permissions     362          362       yes
documents            1            1         yes
__tables__           53           53        yes
[drill] vault: restored 13 entries

DRILL RESULT: PASS - restored copy matches the live database (vault: restored 13 entries)
```

**What this proves:** the dump is complete and readable, the restore procedure works end to end,
and the private vault travels with the database. The drill never wrote to the live database.

## 4. Procedure for production (repeated from `BACKUP_GUIDE.md`)

1. `scripts/backup.sh /var/backups/taisartownhub` (cron: daily 02:00, retention 7 daily / 4 weekly / 6 monthly).
2. Verify the artefact: table count > 0, vault listing readable, SHA-256 recorded.
3. Copy the artefacts to an **off-site, encrypted** location — the backup contains the most
   sensitive data in the system (it holds the encrypted CNIC/MFA columns and the vault).
4. **Quarterly restore drill** into a scratch container using `scripts/restore-drill.ps1`
   (Windows) or the equivalent on Linux, and file the output like this report.
5. Never store the `APP_KEY` next to the dumps: without it the encrypted columns are unreadable.

## 5. Gaps that remain (environmental, not code)

| Item | Status | Note |
|---|---|---|
| Cron/systemd timer on the production host | ⛔ BLOCKED | needs the host |
| Off-site encrypted storage | ⛔ BLOCKED | needs a target (S3/GCS/B2) and credentials |
| DB-level snapshot in addition to logical dumps | ⛔ BLOCKED | MariaDB volumes live on the host |
| Restore drill on the production stack | ⛔ BLOCKED | run it after the first production deploy |

## 6. Legacy-data safety (related)

The legacy `business_records` store is **inside** the backup (103 rows, verified above), so the
tenant backfill decision can always be reversed by restoring this exact dump. The backfill tool
(`php artisan platform:legacy-backfill`) refuses to run with `--apply` unless a verified dump
path is supplied, is additive (it only adds an `agency_id` key to the JSON payload) and verifies
that the row count is unchanged afterwards.
