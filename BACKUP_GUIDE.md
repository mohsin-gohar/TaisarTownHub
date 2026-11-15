# BACKUP_GUIDE.md — TaisarTownHub

Backup = **database** + **private document vault** + **queue/session state**. Restoring only the
database leaves the vault (and therefore the agency's private documents) empty, and restoring only
the vault leaves the database referencing missing files. Always take and restore both.

> The dev workspace's verified backup from the transformation phase is
> `C:\Users\mohsi\AppData\Local\Temp\opencode\backup_taisartownhub_20261110_101116.sql`
> (250 KB, 20 tables, users + business_records confirmed inside the dump).

---

## 1. What must be backed up

| Asset | Path | Why |
|---|---|---|
| Database | MySQL/MariaDB data dir (or a logical dump) | All tenants, RBAC, finance, audit |
| Document vault | `backend/storage/app/private/documents` | Agency documents, versions, shares |
| Media assets | `backend/storage/app/public/media` (if used) | Public media served by the API |
| Environment | `backend/.env` (stored separately, encrypted) | Keys/credentials — **never in the same bucket as the data** |
| Laravel secrets | `APP_KEY` | Without it the encrypted columns (CNIC, MFA secrets) are unreadable |

---

## 2. Creating a backup

### 2.1 Logical dump (MariaDB/MySQL)

```bash
cd /var/www/taisartownhub/backend
mkdir -p storage/app/backups
STAMP=$(date +%Y%m%d_%H%M%S)
mysqldump --single-transaction --routines --triggers \
  -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip > "storage/app/backups/tth_db_${STAMP}.sql.gz"
sha256sum "storage/app/backups/tth_db_${STAMP}.sql.gz" > "storage/app/backups/tth_db_${STAMP}.sql.gz.sha256"
```

`--single-transaction` keeps the dump consistent without locking InnoDB tables.

### 2.2 Document vault

```bash
tar -czf "storage/app/backups/tth_vault_${STAMP}.tar.gz" \
  -C storage/app/private documents
sha256sum "storage/app/backups/tth_vault_${STAMP}.tar.gz" > "storage/app/backups/tth_vault_${STAMP}.tar.gz.sha256"
```

### 2.3 Verification (mandatory)

A backup that was never restored is not a backup.

```bash
zcat storage/app/backups/tth_db_${STAMP}.sql.gz | head -n 5            # header readable
zcat storage/app/backups/tth_db_${STAMP}.sql.gz | grep -c "CREATE TABLE"  # table count > 0
tar -tzf storage/app/backups/tth_vault_${STAMP}.tar.gz | head           # vault listing readable
```

Record the dump size and the table count in the change log. A dump of 0 bytes or a table count of 0
means the backup failed — do **not** proceed to a migration.

---

## 3. Restoring

```bash
# 0. stop writers so nothing lands mid-restore
sudo supervisorctl stop tth-worker
sudo systemctl stop nginx || true

# 1. restore the database
gunzip -c storage/app/backups/tth_db_<STAMP>.sql.gz | \
  mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"

# 2. restore the vault
tar -xzf storage/app/backups/tth_vault_<STAMP>.tar.gz -C storage/app/private

# 3. permissions
chown -R www-data:www-data storage/app/private/documents
chmod -R 750 storage/app/private/documents

# 4. verify
php artisan migrate:status           # expect: nothing pending
php artisan app:production-check --strict
php artisan queue:retry all           # requeue jobs that failed during the outage
sudo supervisorctl start tth-worker
sudo systemctl start nginx
```

### Restore drill checklist

- [ ] `GET /api/health` → `200 healthy`
- [ ] `GET /api/catalog` → 200 with listings
- [ ] A signed-in user can open **Security & Roles** (RBAC rows intact)
- [ ] The document vault lists its folders (files present, not just rows)
- [ ] `personal_access_tokens` still valid → force re-login if the `APP_KEY` changed
- [ ] Audit trail shows the restore window

---

## 4. Encryption-at-rest policy (what is *not* covered by field encryption)

| Layer | Control | Notes |
|---|---|---|
| Application fields | Laravel `encrypted` casts (CNIC, MFA secret, recovery codes) | Protects the **column**, not the file or the dump |
| Database at rest | Encrypted volume (LUKS / cloud disk encryption) + restricted DB credentials | The dump still contains the ciphertext — safe only with the right `APP_KEY` |
| Backups | Encrypted container/bucket, separate credentials, retention policy | Never store a dump and the `APP_KEY` in the same place |
| Document vault | Private disk outside the web root, non-servable, 750 perms, encrypted volume | Files are streamed through authenticated endpoints only |
| Backups of the vault | Encrypted archive + separate storage location | A vault backup contains the most sensitive data in the system |
| Log files | Never log tokens, CNICs, MFA codes or document bodies | `CorrelationId` + audit rows carry identifiers, not secrets |

> **Explicitly stated limitation:** Laravel field encryption alone is *not* full disk encryption.
> Production MUST use encrypted volumes (or an encrypted managed database) plus encrypted backup
> storage. This is a deployment obligation, not something the application can enforce.

---

## 5. Retention & monitoring

| Asset | Retention | Automation |
|---|---|---|
| DB dumps | daily 7 / weekly 4 / monthly 6 | cron + `sha256sum` manifest |
| Vault archives | daily 7 / weekly 4 | cron |
| `failed_jobs` | 7 days (auto-pruned by the scheduler) | `queue:prune-failed --hours=168` |
| Sanctum tokens | 24 h expiry (auto-pruned) | `sanctum:prune-expired --hours=48` |

Alert on: missing dump for > 26 h, dump size deviating > 50 % from the 7-day median, failed restore
drill, `failed_jobs` > 0 for more than 15 minutes.
