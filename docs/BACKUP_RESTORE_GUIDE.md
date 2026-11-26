# Backup and Restore Guide

## Backup

- Database: `mysqldump` / `mariadb-dump` of production DB to encrypted storage.
- Files: archive `storage/app/private` and `storage/app/public` volumes.
- Retention: define per organization policy.

## Restore (isolated first)

1. Restore DB dump into a disposable database.
2. Restore file archives to a temp path.
3. Run `php artisan migrate --force` if needed.
4. Verify counts, sample records, and application health.
5. Only then promote to production.

## Verification

- A backup is verified only after a successful isolated restore.
- Never overwrite production data during testing.

## Recovery

- Document RTO/RPO per deployment.
- Keep at least one off-site encrypted copy.
