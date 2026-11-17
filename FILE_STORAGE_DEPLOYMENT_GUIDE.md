# File Storage Deployment Guide (Secure Vault)

Ops runbook for the Secure Document & Media Vault as shipped in commit `8c56b76`.
Companion: `SECURE_FILE_VAULT_ARCHITECTURE.md`, `FILE_STORAGE_THREAT_MODEL.md`, `documents/15_DEPLOYMENT_CICD.md`.

> ⚠️ **Nothing in this guide is "verified production-ready" until the referenced infrastructure actually exists.** Items marked BLOCKED below require real ClamAV, real backups and real SMTP to be provisioned and exercised.

---

## 1. Environment variables

```bash
# Storage (MySQL 8 primary DB; see 15_DEPLOYMENT_CICD.md for DB_* vars)
FILESYSTEM_DISK=local

# Vault at-rest encryption (AES-256-GCM; key derived from APP_KEY)
FILE_VAULT_ENCRYPTION=true

# Quarantine retention for rejected/suspicious uploads (days)
VAULT_QUARANTINE_RETENTION_DAYS=30

# Malware scanning — PRODUCTION REQUIRED
MALWARE_SCAN_ENABLED=true
MALWARE_SCANNER=clamav
CLAMD_SOCKET=127.0.0.1:3310        # clamd sidecar (TCP) — preferred
# CLAMSCAN_COMMAND=clamscan        # CLI fallback if no daemon
MALWARE_SCAN_FAIL_CLOSED=true      # REQUIRED in production
MALWARE_SCAN_LOG_SKIPPED=true
```

**Never** ship with `MALWARE_SCAN_FAIL_CLOSED=false` in production: it is the switch that lets an upload land unscanned (fail-open holds stay undownloadable until rescan, but the default posture should be reject).

## 2. Key management (APP_KEY)

1. Generate once per environment: `php artisan key:generate`; store in the secret manager (env/CI secrets). **Never commit it.**
2. The vault derives its data key as SHA-256(APP_KEY ‖ "taisartownhub-file-vault-v1") — rotating APP_KEY rotates the vault key.
3. **Rotation procedure:**
   1. Deploy the new APP_KEY (env).
   2. Immediately run `php artisan vault:encrypt` — re-encrypts every file still decryptable under the OLD key; files encrypted under an *unknown* key are reported (`DECRYPT FAILED`) and left untouched, never corrupted.
   3. Keep the previous APP_KEY available until step 2 reports `errors: 0`, then destroy it.
4. `vault:encrypt` is idempotent and safe to run live (reads are transparent; encrypted files are skipped after a decrypt verification).
5. First deployment over an existing (pre-encryption) vault: run `php artisan vault:encrypt --dry-run` to see the count, then run it for real. Until then legacy files read transparently as plaintext (documented behaviour).

## 3. ClamAV (BLOCKED on infrastructure — required before go-live)

- Run clamd as a **sidecar** (the API image intentionally contains no daemon):
  `clamd` listening on TCP `127.0.0.1:3310` (container-internal) reachable via `CLAMD_SOCKET`.
- Protocol integration is already implemented and wire-verified (INSTREAM, length-prefixed chunks, path never transmitted) — see `ClamAVScanner` and `MalwareScanningTest` (11 tests against a real fake-clamd process).
- Enable signature updates (freshclam) on a schedule and monitor daemon health; an unreachable daemon + `FAIL_CLOSED=true` ⇒ uploads rejected with 422 until it recovers (correct, safe behaviour).
- **Acceptance before production use:** upload the EICAR test string through the real API against real clamd → expect 422 + a `document_quarantines` row with `reason=infected`; upload a clean file → 201 + `scan_status=scanned_clean`. (Not yet executed — no clamd on this machine.)

## 4. Scheduler & queues

`routes/console.php` schedules (require the scheduler to run — cron `* * * * * php artisan schedule:run` or container equivalent):

| Command | Cadence | Purpose |
|---|---|---|
| `vault:purge-quarantine` | daily | Erase rejected/never-approved uploads older than the retention window; each purge audited |
| `sanctum:prune-expired` | daily | Bound token table |
| `queue:prune-failed` | weekly | Bound failed jobs |
| `saved-searches:send-alerts` | hourly | Marketplace alerts |

## 5. Storage layout & web-server rules

```
storage/app/private/documents/   ← vault + media bytes (encrypted at rest)
storage/app/private/quarantine/  ← rejected uploads (encrypted at rest)
```

- **Never** create a web-server alias for `storage/app/private/**`; never run `storage:link` targeting the private disks (the only configured link is the public disk, unused by the vault).
- nginx `client_max_body_size 30m;` in sync with `storage.max_upload_mb` system setting (25 MB default).
- Filesystem-level hardening (recommended): run the app as a dedicated user; ensure only that user + root can read `storage/app/private`.

## 6. Backups (BLOCKED until a real drill is run)

`scripts/backup.sh` (Linux) and `scripts/backup.ps1` (Windows) already tar the database + `storage/app/private/documents` + manifest; `scripts/restore-drill.ps1` automates the restore rehearsal.

Targets (documented, **not yet measured** — measure them in the first real drill):

| Metric | Target |
|---|---|
| RPO | ≤ 24h (daily encrypted off-site copy; schedule nightly + pre-change snapshots) |
| RTO | ≤ 4h (DB restore + vault untar + `vault:encrypt` verification pass) |

Drill checklist (first execution):
1. Restore DB + vault into an isolated environment with the **same APP_KEY**.
2. `php artisan vault:encrypt --dry-run` → must report `already encrypted: N, errors: 0` (checksums match plaintext, decryption under the restored key succeeds).
3. Download a sample document through the API → byte-identical (`sha256sum` vs stored checksum).
4. Record measured RPO/RTO in this file.

Vault bytes are AES-256-GCM encrypted **and** backups are encrypted-in-tar per script — a stolen backup without APP_KEY does not yield file contents.

## 7. Go-live checklist (vault-specific)

- [ ] MySQL 8 reachable; `migrate` includes `2026_11_17_000001_add_vault_scan_lifecycle`
- [ ] `APP_KEY` set from secret store; `vault:encrypt` run over any pre-existing files (`errors: 0`)
- [ ] `MALWARE_SCAN_ENABLED=true`, `FAIL_CLOSED=true`, clamd reachable (EICAR + clean-file acceptance done)
- [ ] Scheduler running (`vault:purge-quarantine` active)
- [ ] `storage/app/private/{documents,quarantine}` outside web root; no alias/symlink; permissions locked down
- [ ] `php artisan about` / health endpoint green; `/api/agency/documents/storage-usage` returns counters
- [ ] Smoke test: upload → download → share link (expiry + cap) → soft delete → permanent delete; verify `document_access_logs` rows
- [ ] Cross-agency smoke: user of agency A requests agency B's document id → 404
- [ ] Backup job scheduled + first restore drill completed (record RPO/RTO)
- [ ] Redis/SMTP/notifications configured as per `15_DEPLOYMENT_CICD.md`

## 8. Incident runbook (vault)

| Event | Response |
|---|---|
| Malware detected post-upload (rescan) | Bytes auto-move to quarantine disk; investigate `document_quarantines` row + uploader; keep for forensics until retention purge |
| Suspected mass download | Pull `document_access_logs`/`audit_logs` (actions, ips, UA) for the agency; revoke tokens (`document_shares`); rotate user tokens; MFA check |
| APP_KEY leaked | Rotate immediately + `vault:encrypt`; treat exposed copies as compromised; re-verify with dry-run `errors: 0` |
| Scanner outage | Fail-closed keeps uploads rejected; restore clamd; no data loss (nothing was accepted unscanned) |
| Quarantine growth | Check `document_quarantines` reasons; tune extension allowlist (`document.allowed_extensions`) |
