# Phase 3.14 — Backup, Restore and Tenant Integrity Audit

Date: 2026-11-28
Branch: `audit/phase-3.14-backup-restore-20261128`
Base: `audit/phase-3.13-seeders-demo-data-20261128`

## Completion criteria

1. A recovery point must contain a database dump and its matching private document vault archive.
2. A restore drill must never write into or drop the application database.
3. Restored database schema, row counts, indexes, foreign keys and table health must match the dump.
4. Every non-null `agency_id` must reference an existing restored agency.
5. Finance child records must have the same agency as their parent transaction.
6. Private agency data must remain behind the existing authenticated, agency-scoped API/vault access controls; backup files themselves are platform-wide sensitive data.

## Files reviewed and changed

- `scripts/backup.sh`
- `scripts/backup.ps1`
- `scripts/restore-drill.ps1`
- `.github/workflows/docker-ci.yml`
- `docs/BACKUP_RESTORE.md`

## Findings fixed

### 1. Incomplete backup could previously be reported as successful

The Linux helper previously warned and continued when the private document vault directory was absent. The Windows helper made vault inclusion optional. A database-only archive is not a valid TaisarTownHub recovery point because document metadata can reference private file bytes.

**Fixes**
- Both backup helpers now require the private document vault.
- Both verify the SQL dump is non-empty and has a mysqldump completion marker.
- Linux verifies gzip integrity, table count, tar integrity and non-empty archive listing.
- Windows verifies table count, completion marker, archive creation and archive listing.
- SHA-256 manifest is written for both database and vault artefacts.
- Credentials are passed using `MYSQL_PWD`, not a visible `-pPASSWORD` command-line argument.
- Linux temporary work directories are cleaned on success and failure.
- Linux local staging cleanup ceiling changed from 30 to 180 days to avoid deleting monthly recovery points prematurely.

### 2. Restore drill previously allowed database-only restore

**Fixes**
- Restore drill now rejects a backup stamp without its matching vault archive before creating a scratch database.
- It already verifies the manifest checksums, SQL completion marker, exact table set, per-table row counts, indexes, foreign keys, CHECK TABLE status, FK orphans, vault archive paths and extracted vault entries.
- Scratch database name is unique per invocation; non-loopback hosts are refused unless explicitly allowed.
- Cleanup drops only the scratch database created by that invocation and verifies it is gone.

### 3. Tenant ownership integrity after restore

Added restore-time assertions:
- Every non-null `agency_id` in every table containing that column must resolve to an agency row. This covers legacy tables without a physical FK.
- `transaction_payments`, `payment_schedules` and `commissions` must match the parent transaction's `agency_id`, including NULL/non-NULL mismatches.
- Existing FK orphan scan and exact row-count verification remain active.

The application-level private-document authorization remains enforced by the existing tenant-scoped vault controllers and Phase 3.10 cross-tenant document access regression tests. The restore drill validates persisted tenant ownership; it does not replace a two-account authenticated staging smoke test.

### 4. CI regression coverage

Added a negative integration step to `.github/workflows/docker-ci.yml`: after the genuine restore drill, CI constructs a database-only backup set and asserts the restore drill rejects it before creating scratch storage.

The existing MySQL 8 backup/restore job performs fresh migrations/seeding, creates a private-vault fixture, runs the backup helper, imports into an isolated scratch DB and validates integrity/cleanup.

## Verification status

- GitHub source review and targeted static inspection: completed.
- CI workflow regression step: added, **not executed on this branch in this session**.
- Linux backup helper runtime: not executed against MySQL.
- Windows backup helper runtime: not executed on Windows/PowerShell.
- MySQL 8 isolated restore drill: not executed in this session.
- Production restore: not performed; no production database or vault was accessed.
- Existing Phase 3.10 private-document tenant isolation tests and Phase 3.11 finance isolation tests remain in the branch ancestry, but were not rerun for this phase.

## Operational requirements (not enforceable by repository code alone)

- Encrypt backup volumes/object storage; SHA-256 is integrity verification, not encryption.
- Keep `APP_KEY`, database credentials and backup-storage credentials in a separate secret manager.
- Back up Redis separately if production uses Redis-only sessions or queues.
- Back up public media separately when non-reproducible uploads are stored in `storage/app/public/media`.
- Run the restore drill against a disposable MySQL 8 instance and perform authenticated two-agency document access checks in staging before production sign-off.

## Outcome

Backup and restore scripts now fail closed on missing vault archives, and the restore drill checks agency-reference and finance parent/child tenant integrity. **Code-level remediation is committed; the completion condition is not runtime-certified until the updated MySQL 8 CI restore drill and two-agency staging access test pass.**
