# Secure Document & Media Vault — Architecture

**Status:** implemented in commit `8c56b76`; verified 260 tests / 2,095 assertions on SQLite and MySQL 8.4.9.
**Companion docs:** `SECURE_FILE_VAULT_AUDIT.md` (findings), `FILE_STORAGE_SECURITY_REPORT.md` (evidence), `FILE_STORAGE_THREAT_MODEL.md` (threats), `FILE_STORAGE_DEPLOYMENT_GUIDE.md` (ops).

---

## 1. Tenancy and storage topology

```
backend/storage/app/
├── public/                 ← public disk (only symlinkable disk; unused by vault)
└── private/                ← NOTHING here is ever web-served or symlinked
    ├── documents/          ← `documents` disk: vault + media bytes
    │   ├── {agency_id}/2026/11/doc_{16hex}.pdf
    │   └── media/{agency_id}/2026/11/med_{16hex}.jpg
    └── quarantine/         ← `quarantine` disk: rejected/suspicious uploads
        └── {agency_id}/2026/11/qtn_{16hex}.{ext}
```

- **Both disks are `serve => false`, `visibility => private`, outside the web root, and never referenced by `filesystems.links`** (pinned by `test_vault_disks_stay_configured_outside_the_web_root`).
- Physical paths are stored in the DB (`disk_path`, `storage_disk`) and **hidden from every API response** (`Document`, `DocumentVersion`, `MediaFile`, `DocumentQuarantine` all declare `$hidden`).
- Docker pre-creates both mount points with www-data ownership (`backend/Dockerfile`).

## 2. Upload lifecycle (fail-closed core)

```
POST /api/agency/documents (throttle:20,1)
  │
  ├─ AgencyContext → agency_id (from own memberships only)
  ├─ requirePermission('documents.upload') [+ view_sensitive for sensitive categories]
  ├─ PlanLimits::storageBlock()          → 402 plan_limit_reached
  ├─ UploadSecurity::evaluate()          → 422 on:
  │    1. extension allowlist miss (SystemSetting document.allowed_extensions)
  │    2. sniffed MIME in blocklist (browser type ignored)
  │    3. magic bytes @ offset 0 (<?php, <?, \x7fELF, MZ, #!)
  ├─ MalwareScanner::scan()              → verdict
  │
  ├─ clean ─────────────► SecureFileStore (AES-256-GCM) → documents disk
  │                       Document: status=active, scan_status=scanned_clean
  │                       → downloadable / shareable
  ├─ infected ──────────► SecureFileStore → quarantine disk + document_quarantines
  │                       → 422 "File failed malware scan and was rejected."
  ├─ unavailable + fail-closed → same as infected (reason=scan_unavailable) → 422
  └─ unavailable + fail-open  → stored on documents disk, BUT
                                status=quarantined, scan_status=skipped
                                → download 423, share mint 422, until rescan
```

The same pipeline runs for **document versions** (`storeVersion`) and **media uploads** (`MediaController::store`) — there is no validation bypass path.

### Rescan (administrator)

`POST /api/agency/documents/{id}/rescan` (`documents.manage_permissions`):
- clean → promote (`status=active`, bytes move off the quarantine disk if needed, checksum recomputed, version row synced);
- infected → move bytes onto the quarantine disk, record threats, stays 423;
- scanner still unavailable → stays held (fail-closed), 422.

Visibility: `GET /api/agency/documents-quarantine` lists held vault rows + rejected uploads (never disk paths). Retention: `vault:purge-quarantine` (scheduled daily, `VAULT_QUARANTINE_RETENTION_DAYS=30`) erases expired rejected rows and never-approved holds, each audited as `document.quarantine_purged`.

## 3. Encryption at rest

`App\Services\FileVaultCrypto`:
- **AES-256-GCM** via PHP's standard OpenSSL integration (no custom cryptography);
- **key derivation:** SHA-256(APP_KEY ‖ "taisartownhub-file-vault-v1") — the vault data key is never persisted beside the files and differs from other APP_KEY uses;
- **envelope:** `TTHENC1\n base64(iv) \n base64(tag) \n ciphertext` (single write, random 96-bit IV per file);
- **transparent reads:** any payload without the envelope marker is treated as legacy plaintext and returned as-is, so pre-encryption files keep streaming with zero downtime;
- **APP_KEY rotation:** re-run `php artisan vault:encrypt` — idempotent; files still encrypted under the old key are re-written, files that fail decryption under the current key are **reported and left untouched** (fail closed, never corrupted);
- `FILE_VAULT_ENCRYPTION=true` by default (`.env.example`); `vault.encryption.enabled` config.

All writes and reads go through `App\Services\SecureFileStore` (`storeUpload`, `putBytes`, `move`, `get`, `checksum`, `scanStored`, `response`). Integrity checksums in the DB are of the **plaintext** content, so byte-level integrity is verifiable after decryption.

## 4. Delivery path

`SecureFileStore::response(disk, path, name, mime, disposition)`:
- caller must already have: authenticated user, agency scope check (`findScoped` → 404 for cross-agency), `documents.download`, `documents.view_sensitive` for sensitive docs, `status=active`, `scan_status=scanned_clean`;
- decrypts on the fly; **Content-Type is the server-sniffed type stored at upload** (never the browser-declared one);
- `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Pragma: no-cache`;
- `Content-Disposition` built by Laravel's `makeDisposition` from a sanitised name (CR/LF/quotes stripped) — attachment for downloads, inline for previews/public media;
- missing bytes → 410 JSON (never a stack trace).

Public share downloads (`GET /api/shares/{token}/download`) follow the identical path, additionally gated by token hash lookup, expiry, revocation and the atomic download cap.

## 5. RBAC (documents.*)

Catalog (`App\Support\Permissions`): `documents.{view, upload, update, download, delete, restore, manage_permissions, view_sensitive}` — seeded to roles by `EnterpriseSeeder`, enforced by `AuthorizesApi::requirePermission()` in every controller action. Sensitive categories (`cnic, registry, ownership, sale_agreement, transfer_letter, allotment_letter`) additionally require `documents.view_sensitive` for upload, list, download and share minting. Denials are audited (`document.download_denied` + forensic `download_denied` access-log row).

## 6. Audit trail

| Layer | Content |
|---|---|
| `audit_logs` | actor, role, action (`document.upload/download/preview/delete/restore/permanent_delete/share_created/share_revoked/share_download/version/rescan/quarantined/quarantine_purged`), agency_id, entity, metadata JSON, ip |
| `document_access_logs` | per-document forensic: action, user, ip (45), user-agent (≤500 chars), timestamp — every upload/preview/download/block/deny |
| Response parity | `GET /agency/documents/{id}/access-logs` and `/agency/documents-activity` expose agency-scoped history; platform users need `admin.view_audit_logs` |

Logs never contain file contents, raw share tokens (hash-only) or physical paths.

## 7. Quotas and rate limits

- `PlanLimits::usage()` = listings + members + **storage_bytes (documents incl. trashed + all archived versions + media)** + leads; enforcement at upload → 402 `plan_limit_reached`; `GET /agency/documents/storage-usage` and `/agency/subscription` read the exact same source (no UI/enforcement drift).
- `POST /agency/documents`, `/agency/documents/{id}/versions`, `/agency/media` → dedicated `throttle:20,1` on top of the global 60/min `api` limiter; public media 300/min by IP; share downloads 30/min.

## 8. Frontend integration

- `frontend/src/services/api.ts`: multipart upload, authenticated blob reads (`downloadAgencyDocument` / `previewAgencyDocument` / `fetchAgencyMediaBlob` — **private bytes are never public URLs**), plus new `getAgencyDocumentQuarantine`, `rescanAgencyDocument`, `getAgencyDocumentStorageUsage`.
- `AdminDocumentVault.tsx`: server-backed vault table + quarantine card (held items with Rescan, rejected items marked Blocked; panel renders only for `documents.manage_permissions` holders — a 403 simply hides it).
- `check:bundle` guard keeps private tokens/credentials out of public chunks (verified green).

## 9. Deployment shape

PHP 8.3 / Laravel 12, MySQL 8 primary. ClamAV runs as a sidecar (clamd TCP `CLAMD_SOCKET` or `clamscan` CLI). See `FILE_STORAGE_DEPLOYMENT_GUIDE.md` for env, keys, backups (RPO/RTO), retention and the production go-live checklist.
