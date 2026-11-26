# TaisarTownHub — Backup & Restore

(Consolidated from the former root `BACKUP_GUIDE.md`; content preserved. Helper scripts: `scripts/backup.sh`, `scripts/restore-drill.ps1`.)

Backup = **database** + **application storage** (private documents, quarantine and public media). Restoring only the database leaves file rows pointing at missing bytes; restoring only storage leaves files without their database ownership and audit records. Redis queues/sessions are ephemeral and are not included: after a restore, restart workers and require users to sign in again.

## 1. What must be backed up

| Asset | Path | Why |
|---|---|---|
| Database | MySQL data dir (or logical dump) | All tenants, RBAC, finance, audit |
| Private storage | `backend/storage/app/private` | Documents, versions, encrypted uploads, quarantine and any future private disks |
| Public storage | `backend/storage/app/public` | Public map images and media assets |
| Quarantine volume | `private/quarantine` inside the shared private volume | Rejected uploads retained for rescan and audit |
| Environment | repository-root `.env` for Docker Compose (or `backend/.env` for host-only Laravel) | Keys/credentials — never in the same bucket as the data |
| Laravel secrets | `APP_KEY` | Without it the encrypted columns (CNIC, MFA secrets) are unreadable |

## 2. Creating a backup

Run the repository backup helper from the repository root while the production Docker Compose `backend` and `mysql` services are healthy. It reads the root Compose `.env` first (falling back to `backend/.env` for host-only runs), dumps MySQL from the live MySQL container, and archives the actual named volumes through the running backend container. It never treats the host's shadowed `backend/storage` directory as the Docker volume. DB credentials are written to a mode-`0600` temporary option file; the database and storage artefacts are verified, checksummed and retained under the daily/weekly/monthly policy.

```bash
cd /var/www/taisartownhub
export TTH_BACKUP_DIR=/secure/backups/taisartownhub
bash scripts/backup.sh "$TTH_BACKUP_DIR"
```

The storage archive preserves paths relative to `backend/storage/app`: the complete `private` directory (including the shared documents and quarantine volumes) and `public` when present. The file payloads are encrypted at rest by the application; the backup destination must still be an encrypted volume or encrypted bucket because the database dump contains other sensitive operational data. Do not stop the backend or MySQL services during the backup.


## 3. Verification (mandatory — a backup that was never restored is not a backup)

```bash
zcat "$TTH_BACKUP_DIR/tth_db_${STAMP}.sql.gz" | head -n 5
zcat "$TTH_BACKUP_DIR/tth_db_${STAMP}.sql.gz" | grep -c "CREATE TABLE"
tar -tzf "$TTH_BACKUP_DIR/tth_vault_${STAMP}.tar.gz" | head
sha256sum -c "$TTH_BACKUP_DIR/manifest-${STAMP}.sha256"
```

Record dump size and table count in the change log. A 0-byte dump or 0 table count means the backup failed — do **not** proceed to a migration.

## 4. Restoring

Restore into an isolated recovery environment first. Keep the same `APP_KEY` that encrypted the source data. The database dump and application-storage archive must use the same timestamp.

```bash
# 0. Stop application writers (keep the database available for restore).
docker compose stop backend worker scheduler

# 1. Create a protected MySQL option file from the root Compose .env.
TMP_MYSQL_CNF="$(mktemp)"
TMP_DB_META="$(mktemp)"
php scripts/write-mysql-defaults.php "$PWD" "$PWD/backend" "$TMP_MYSQL_CNF" "$TMP_DB_META"
DB_NAME="$(sed -n '2p' "$TMP_DB_META")"

# 2. Restore the database without exposing the password in process arguments.
gunzip -c "$TTH_BACKUP_DIR/tth_db_<STAMP>.sql.gz" | mysql --defaults-extra-file="$TMP_MYSQL_CNF" "$DB_NAME"

# 3. Restore the complete application storage archive to storage/app.
tar -xzf "$TTH_BACKUP_DIR/tth_vault_<STAMP>.tar.gz" -C backend/storage/app

# 4. Remove temporary credentials and restore ownership/permissions.
rm -f "$TMP_MYSQL_CNF" "$TMP_DB_META"
docker compose run --rm --no-deps --entrypoint sh backend -c 'chown -R www-data:www-data storage/app/private storage/app/public && chmod -R 750 storage/app/private'

# 5. Start services and verify.
docker compose up -d backend worker scheduler
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan app:production-check --strict
```

Review `failed_jobs` before retrying anything. Do not blindly run `queue:retry all`: jobs that are not idempotent can repeat external side effects. Confirm the `APP_KEY` matches the source environment; changing it makes encrypted columns unreadable.

### Restore drill checklist

- [ ] `GET /api/health` → `200 healthy`
- [ ] `GET /api/catalog` → 200 with listings
- [ ] A signed-in user can open **Security & Roles** (RBAC rows intact)
- [ ] The document vault lists its folders (files present, not just rows)
- [ ] `personal_access_tokens` still valid → force re-login if the `APP_KEY` changed
- [ ] Audit trail shows the restore window

## 5. Encryption-at-rest policy

| Layer | Control | Notes |
|---|---|---|
| Application fields | Laravel `encrypted` casts (CNIC, MFA secret, recovery codes) | Protects the column, not the file or the dump |
| Database at rest | Encrypted volume (LUKS / cloud disk encryption) + restricted DB credentials | The dump still contains the ciphertext — safe only with the right `APP_KEY` |
| Backups | Encrypted container/bucket, separate credentials, retention policy | Never store a dump and the `APP_KEY` in the same place |
| Document vault | Private disk outside the web root, non-servable, 750 perms, encrypted volume | Files stream through authenticated endpoints only |
| Log files | Never log tokens, CNICs, MFA codes or document bodies | `CorrelationId` + audit rows carry identifiers, not secrets |

> **Explicitly stated limitation:** Laravel field encryption alone is *not* full disk encryption. Production MUST use encrypted volumes (or an encrypted managed database) plus encrypted backup storage. This is a deployment obligation, not something the application can enforce.

## 6. Retention & monitoring

| Asset | Retention | Automation |
|---|---|---|
| DB dumps | daily 7 / weekly 4 / monthly 6 | `scripts/prune-backups.php` + `sha256sum` manifest |
| Application storage archives | daily 7 / weekly 4 / monthly 6 | `scripts/prune-backups.php` (invoked by `backup.sh`) |
| `failed_jobs` | 7 days (auto-pruned by the scheduler) | `queue:prune-failed --hours=168` |
| Sanctum tokens | 24 h expiry (auto-pruned) | `sanctum:prune-expired --hours=48` |

Alert on: missing dump for > 26 h, dump size deviating > 50 % from the 7-day median, failed restore drill, `failed_jobs` > 0 for more than 15 minutes.
