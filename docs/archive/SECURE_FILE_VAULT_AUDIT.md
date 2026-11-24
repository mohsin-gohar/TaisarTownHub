# Secure File Vault — Audit Report

**Date:** 2026-11-17
**Scope:** Complete read-only audit of the existing file upload / storage / document-vault system in TaisarTownHub (backend `app/Http/Controllers/Api/{DocumentController,MediaController,DealerFilesController}.php`, `config/filesystems.php`, models, migrations, routes, RBAC, frontend vault UI), followed by remediation of every verified vulnerability.
**Remediation commit:** `8c56b76` (`feat(vault): enterprise secure document & media vault hardening`).

---

## 1. What already existed (verified strong)

The codebase already had a serious vault foundation — this audit confirms it, and the enterprise build hardened rather than replaced it:

| Control | Status before | Where |
|---|---|---|
| Private storage outside web root | ✅ `documents` disk → `storage/app/private/documents`, `serve => false`, never symlinked, `public/storage` did not exist on disk | `backend/config/filesystems.php` (links array contains only `public`), verified `Test-Path backend/public/storage → False` |
| Server-side tenancy scoping | ✅ Every vault query scoped by `agency_id` from `AgencyContext` middleware (resolved only from the user's own memberships); cross-agency ids → 404 (not 403, no probing oracle) | `DocumentController::findScoped()`, `backend/app/Http/Middleware/AgencyContext.php` |
| Permission gates | ✅ `documents.{view,upload,update,download,delete,restore,manage_permissions,view_sensitive}` enforced via `AuthorizesApi::requirePermission()` on every endpoint | `backend/app/Support/Permissions.php` (lines 40–47), `routes/api_definitions.php` |
| Sensitive-doc clearance | ✅ CNIC/registry categories require `documents.view_sensitive` for upload, list, download and share minting | `DocumentController` (sensitive check in `index`, `stream`, `sharesStore`) |
| Malware scanning interface | ✅ Provider-agnostic `MalwareScanner` with real ClamAV transports (clamscan CLI + clamd INSTREAM protocol with length-prefixed chunks, path never transmitted); result never claims clean unless a real scan ran | `backend/app/Services/ClamAVScanner.php`, `backend/app/Support/MalwareScanResult.php` |
| Fail-closed option | ✅ `MALWARE_SCAN_FAIL_CLOSED` rejected uploads when the scanner was unreachable | `backend/config/malware.php` |
| Share links | ✅ SHA-256-token-hash only (plain token shown once), expiry, download cap, atomic counter under `lockForUpdate`, revocation | `DocumentShare::issue()`, `DocumentController::sharedDownload()` |
| Audit trail | ✅ `AuditLog` (enterprise columns incl. `agency_id`, `entity_type`, `metadata`) + dedicated `document_access_logs` forensic table (ip, user-agent, action) for every upload/preview/download/delete | `backend/app/Http/Controllers/Api/AuthorizesApi.php::writeAudit()`, `DocumentController::logAccess()` |
| Filename randomization | ✅ `doc_` + 16 hex bytes under `{agency}/{Y/m}/`, server-side names; `disk_path`/`stored_name`/`checksum` hidden from all API serialization | `Document::$hidden`, `MediaFile::$hidden` |
| Storage quota | ✅ Plan-based `storage_mb` enforced server-side at upload (402 `plan_limit_reached`) | `App\Support\PlanLimits::storageBlock()` |
| Security headers | ✅ Global `nosniff`, `X-Frame-Options: DENY`, CSP sandbox on every response; CORP `cross-origin` for public media | `backend/app/Http/Middleware/SecurityHeaders.php` |
| Git hygiene | ✅ `storage/app/private/documents/.gitignore` blocks all vault bytes from version control | verified ignore chain |

---

## 2. Verified vulnerabilities (all fixed in `8c56b76`)

Each finding was reproduced and pinned by a regression test before the fix landed. Full suites: **261 passed / 2,104 assertions on SQLite** (final, incl. the path-traversal invariance test); MySQL 8 verified locally on 8.4.9 (260 / 2,095 at the 17-test stage) and in CI on the final commit — **Backend suite on MySQL 8 (integration) job green, run `44099075001`** (baseline was 243 / 1,974).

### V-1 — Magic-byte check inspected the wrong bytes (HIGH) — FIXED
`DocumentController::magicBytesMatch()` read `file_get_contents($path, false, null, 32)` — PHP's 4th argument is the **offset**, so the first 32 bytes were never examined. A `<?php` script, `MZ` Windows PE or ELF binary wearing an allowed extension (`.pdf`, `.jpg`) passed the "magic bytes" gate.
**Fix:** `App\Support\UploadSecurity::magicBytesLookDangerous()` reads offset **0**, length 32, and rejects `<?php`, `<?`, `\x7fELF`, `MZ`, `#!` prefixes.
**Test:** `FileVaultSecurityTest::test_executable_content_disguised_as_pdf_is_rejected_at_offset_zero` (PE and PHP payloads both 422; nothing reaches the vault or quarantine).

### V-2 — Version uploads bypassed every content check (HIGH) — FIXED
`DocumentController::storeVersion()` validated only `file|file|max:`. No extension allowlist, no MIME sniffing, no magic bytes, no malware scan — a hostile payload could be planted as "version 2" of an approved document.
**Fix:** versions now run the identical pipeline as first uploads; a rejected version leaves the live document fully intact (pointer/version/bytes unchanged, verified by the test); a clean version still works end-to-end and stays downloadable.
**Test:** `test_document_version_upload_cannot_bypass_content_checks`.

### V-3 — Media uploads skipped MIME sniffing, magic bytes and malware scan (HIGH) — FIXED
`MediaController::store()` checked only the extension allowlist, stored the **client-declared** MIME, and served it later — public-visibility media was delivered anonymously with an attacker-influenced `Content-Type`.
**Fix:** the full `UploadSecurity::evaluate()` pipeline + malware scan now run for media; the stored mime is the **sniffed** type; the served `Content-Type` is the stored sniffed value (pinned by test); public delivery re-uses `SecureFileStore` (nosniff, safe disposition).
**Tests:** `test_media_upload_rejects_executable_content`, `test_served_content_type_is_the_sniffed_type_not_the_client_declaration`, `test_public_media_bytes_are_encrypted_too`.

### V-4 — Storage quota escaped via trash, versions and media (MEDIUM) — FIXED
`PlanLimits::usage()` counted only `documents` with `status='active'`. Trashed documents (bytes retained for audit), archived `document_versions`, and all `media_files` bytes were invisible to the quota, letting an agency bypass its plan limit indefinitely. `SubscriptionController::current()` mirrored the same undercount, so the usage panel disagreed with enforcement.
**Fix:** usage now sums `Document::withTrashed()` + all `DocumentVersion` bytes + `MediaFile::withTrashed()` bytes; the subscription endpoint reads the exact same `PlanLimits::usage()`.
**Test:** `test_storage_quota_counts_trashed_documents_versions_and_media` (trash does not free quota; media shares the vault quota → 402).

### V-5 — Unrestricted external redirect + arbitrary client URLs (MEDIUM) — FIXED
`MediaController::deliver()` did `redirect()->away($media->url)` for any stored value (`'url' => 'url'` validation only), and `DealerFilesController`/media accepted arbitrary client URLs rendered later by the frontend — an open-redirect / SSRF-adjacent surface.
**Fix:** `UploadSecurity::isAllowedExternalUrl()` enforces HTTPS + public host + no embedded credentials (IP literals must be public, `localhost`/`.localhost` blocked); enforced at registration **and** re-checked on every delivery (legacy rows carrying disallowed URLs return 410 instead of redirecting).
**Tests:** `test_media_external_urls_must_be_https_public_hosts` (http, private IP, localhost, credential URLs → 422; legacy bad URL → 410 no redirect).

### V-6 — Denied download attempts were not audited (MEDIUM) — FIXED
A user without `documents.download` (or without `documents.view_sensitive` clearance) received 403 with **no** audit or access-log entry — repeated probing was invisible to administrators.
**Fix:** `stream()` audits `document.download_denied` before aborting (permission case), and writes a `download_denied` forensic access-log row + audit when the sensitive clearance is missing (the document id is verified within the agency at that point).
**Test:** `test_unauthorized_and_sensitive_download_attempts_are_audited`.

### V-7 — Unscanned bytes could be served (share minting, public media) (HIGH) — FIXED
Under fail-open operation a document stored without a scan verdict was `status='active'` and fully downloadable; share links could be minted for it; media with `scan_status='skipped'` was deliverable publicly. This violated the core rule "files are inaccessible until scan completes".
**Fix (scan lifecycle):** uploads now follow `UPLOADING → QUARANTINED/SCANNED`:
- clean → `scan_status='scanned_clean'`, active, downloadable;
- infected → **rejected**, isolated copy on the new `quarantine` disk + `document_quarantines` row (never in the vault);
- scanner unavailable + fail-closed → rejected (isolated copy kept for administrators);
- scanner unavailable + fail-open → stored but `status='quarantined'`, `scan_status='skipped'` — download **and** share minting blocked with 423/422 until a rescan clears it.
`sharedDownload()` gates on `scan_status`; media delivery gates on non-legacy `scan_status`.
**Tests:** `test_fail_open_hold_is_not_downloadable_until_rescan_clears_it` (423 download, 422 share, rescan promote, then 200), `test_infected_upload_is_rejected_and_isolated_on_the_quarantine_disk`, `test_fail_closed_unavailable_scanner_rejects_and_quarantines`.

### V-8 — At-rest encryption absent for file bytes (HIGH for the stated priorities) — FIXED
Vault bytes were plaintext on the private disk (only `dealers.cnic` / MFA fields used Laravel `encrypted` casts).
**Fix:** `App\Services\FileVaultCrypto` — AES-256-GCM (standard OpenSSL, **no custom crypto**), key = SHA-256 subkey of `APP_KEY` with a vault domain separator (key never stored next to files), envelope format `TTHENC1\n{iv}\n{tag}\n{ciphertext}`, transparent legacy-plaintext reads, and `php artisan vault:encrypt` for idempotent backfill / APP_KEY-rotation re-encryption. Every vault and media write/read now flows through `App\Services\SecureFileStore`.
**Tests:** `test_vault_bytes_are_encrypted_at_rest_and_stream_transparently` (plaintext secret absent from raw disk bytes; authorized stream decrypts), `test_legacy_plaintext_files_still_stream_and_vault_encrypt_rewrites_them` (incl. idempotent second run), `test_public_media_bytes_are_encrypted_too`.

### V-9 — No upload-specific rate limiting (LOW) — FIXED
Uploads rode the general 60/min `api` limiter.
**Fix:** `POST /agency/documents`, `/versions`, `/agency/media` now carry a dedicated `throttle:20,1`.
**Test:** `test_upload_endpoints_get_a_dedicated_rate_limit` (20 × 201, 21st → 429).

### V-10 — Hostile filenames could shape the Content-Disposition header (LOW) — FIXED
The download path interpolated `addslashes()`-escaped client filenames into the header.
**Fix:** `SecureFileStore::safeDispositionName()` strips CR/LF/control/quote/backslash characters; Laravel's `makeDisposition` builds the header; upload-time sanitization (`UploadSecurity::sanitizeOriginalName()`) neutralizes stored names.
**Test:** `test_hostile_original_filenames_cannot_inject_headers`.

### V-11 — Quarantine retention unbounded (LOW) — FIXED
Rejected/infected material now has a retention policy: `VAULT_QUARANTINE_RETENTION_DAYS` (default 30) with scheduled `vault:purge-quarantine` (daily, `routes/console.php`) that erases expired rejected rows **and** never-approved holds, auditing each purge — and touching nothing live.
**Test:** `test_quarantine_purge_removes_only_expired_rows` (expired rejected row + expired hold removed incl. bytes; live approved document untouched and downloadable).

---

## 3. Configuration guardrails (pinned by test)

`test_vault_disks_stay_configured_outside_the_web_root` asserts: both `documents` and `quarantine` disks live under `storage/app` (never `public/`), `serve => false` on both, `filesystems.links` never references the private disks, and vault at-rest encryption defaults to **enabled** (`FILE_VAULT_ENCRYPTION=true` in `.env.example`).

## 4. Cross-agency isolation re-verified

Existing suites continue to pin tenancy: `StorageSecurityTest` (rival agency → 404 on private media; foreign document ids → 404), `CrossAgencyCapabilitiesTest`, `DealerFilesAuthorizationTest` (`X-Agency-Id` spoof rejected; cross-agency sales report 403). No new endpoint breaks these — the full suite (260 tests) is green on both engines.

## 5. Remaining gaps / risks (honest, not fixed by this commit)

| # | Gap | Severity | Note |
|---|---|---|---|
| R-1 | **Real ClamAV not available on this machine** — scanning verified against a real fake-clamd process (`tests/Support/fake_clamd.php`) exercising the actual INSTREAM wire protocol, and via stub verdicts; a production clamd deployment has never been exercised end-to-end. | BLOCKED (infra) | Production must run `MALWARE_SCAN_ENABLED=true` + `MALWARE_SCAN_FAIL_CLOSED=true` with a reachable clamd; until then fail-closed rejects uploads (correct behaviour). |
| R-2 | **Encryption keys from APP_KEY** — acceptable managed mechanism (env/KMS-injected APP_KEY), but no external KMS/HSM integration exists. APP_KEY rotation requires running `vault:encrypt` afterwards; files written under an unknown key fail closed on read (never silently decrypted) and `vault:encrypt` reports them. | MEDIUM | Documented in `FILE_STORAGE_DEPLOYMENT_GUIDE.md`. |
| R-3 | **Backups** — `scripts/backup.sh` / `restore-drill.ps1` tar the vault directory + DB; an RPO/RTO has been **documented** but no real backup restore drill was executed against production infrastructure. | BLOCKED (infra) | Vault bytes are encrypted; backups need the same APP_KEY. |
| R-4 | **Legacy `dealer_files.url` / `media_files.url` rows** created before the HTTPS guard may still hold non-HTTPS values in production data; delivery now refuses them (410), but a data-migration backfill for legacy URLs is not included. | LOW | Fail-closed at delivery; backfill recommended during ops window. |
| R-5 | **User's untracked bookings migration (`2026_11_16_144458_create_bookings_table.php`) is absent from the working tree** — it was never committed, no git history exists, and nothing in the code references it (suite passes without it). Not recreated to avoid fabricating a wrong schema. | ACTION (user) | Needs user confirmation/restore from their local copy. |
| R-6 | `documents` disk on S3 would require `league/flysystem-aws-s3-v3` (currently absent; the `s3` disk config would fail at runtime). | LOW | Documented; add the adapter before enabling S3. |
| R-7 | Docker image installs no ClamAV daemon; compose orchestration does not exist in-repo. clamd must run as a sidecar reachable via `CLAMD_SOCKET`. | BLOCKED (Docker CLI unavailable) | Documented in deployment guide. |

## 6. Deliverables produced by this increment

- `SECURE_FILE_VAULT_AUDIT.md` (this document)
- `SECURE_FILE_VAULT_ARCHITECTURE.md` — system architecture
- `FILE_STORAGE_SECURITY_REPORT.md` — control-by-control report with test evidence
- `FILE_STORAGE_THREAT_MODEL.md` — STRIDE-style threat model
- `FILE_STORAGE_DEPLOYMENT_GUIDE.md` — ops runbook (ClamAV, keys, backups, retention)
- `docs/openapi.yaml` — new endpoints documented (`documents-quarantine`, `rescan`, `storage-usage`, 423 semantics)
- `backend/tests/Feature/FileVaultSecurityTest.php` — 18 regression tests (130 assertions)
