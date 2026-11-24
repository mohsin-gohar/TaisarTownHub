# Bug Register and Fixes

**Branch:** `fix/complete-audit-20261117` · **Baseline:** `09a15b9` · **Date:** 2026-11-17

| ID | Severity | File / method | Root cause | Fix | Regression test | Result |
|---|---|---|---|---|---|---|
| A | Critical | `AgencyStaffController::acceptInvitation` | Invite role `owner` mapped to global `users.role='admin'` | Base role `dealer`; owner-only invite; locked single-use claim; honest `is_platform` | `AdminStaffSubscriptionTest` ×3 | PASS |
| B | Critical | `TransactionController::index/store` | No tenant scope; NULL-tenant rows; foreign refs; unsafe deal numbers | Agency scope, server-bound ownership, scoped `exists`, locked number allocation | `LegacyTransactionIsolationTest` ×5 | PASS |
| C | High | `DealerFilesController` | No permissions, agency binding, quota or PII protection | `AuthorizesApi` gates, dealer identity binding, `dealer_files`/`storage_mb` quota, PII projection, audit rows | `DealerFilesAuthorizationTest` ×7 | PASS |
| D | High | `EnsureMfaVerified` / `AuthController::mfaVerify` | User-wide cache key, no invalidation | `App\Support\MfaVerification` token-scoped stamps + revocation hooks | `MfaEnforcementTest` ×4 | PASS |
| E | High | `FinanceController::index/show`, `Transaction` | Undefined `businessRecord` relation → 500 with data | Added `hasOne` relation | `test_finance_index_and_show_work_when_the_agency_has_deals` | PASS |
| F | High | `FinanceController::store` | Wrong column names lost property/dealer attribution | `propertyId`/`dealerId` + scoped validation | `test_finance_store_persists_property_and_dealer_references` | PASS |
| G | High | `FinanceController::void` | No permission check | `requireAny(void_transactions, …)` | `test_void_requires_an_explicit_permission` | PASS |
| H | High | `.gitignore` / CI | Missing private docs dir on fresh checkout | Tracked placeholder + parent unignore | `ProductionCheck` via full suite | PASS |
| I | Medium | `FinanceController::update` | Arbitrary `dealerId` | `exists` + membership agency check | `test_finance_update_rejects_a_foreign_agency_dealer` | PASS |
| J | Medium | `.github/workflows/ci.yml` | PyYAML assumed preinstalled | Explicit `pip install pyyaml` | Local config review | PASS (static) |
| K | Medium | `Dockerfile.prod`, compose | Dead backend stage, no scheduler/healthchecks | Documented, not changed | — | BLOCKED (no Docker) |
| L | Medium | `EnsureMfaVerified::mfaRequiredFor` | Global MFA policy key unseeded, role whitelist ignores agency roles | Documented, not changed | — | OPEN (policy decision) |
| M | Low | Legacy vs finance deal visibility | Divergent dealer self-scope | Documented, not changed | — | OPEN (policy decision) |

## Second-round CI repairs (run 44059650335)

| ID | Severity | File / method | Root cause | Fix | Result |
|---|---|---|---|---|---|
| N | High | `.github/workflows/ci.yml` "Wait for MySQL" | `php -r "$pdo = ..."` — shell expands `$pdo` → step exit 255, suite skipped | `backend/scripts/wait-for-db.php` (env credentials, 30 retries) + `assert-db-driver.php` guard | PASS locally (MySQL 8.4.9) |
| O | High | `frontend/package.json` test script | Quoted `"src/**/*.test.ts"` glob not expanded on fresh checkout/CI → `Could not find` | `frontend/scripts/run-tests.mjs` discovers files with `fs` and runs tsx with explicit paths | 133 tests pass on fresh clone |
| P | High | `frontend/src/services/api.ts` public catalog | Empty `cnicMasked: ''` placeholder in public-safe catalog flagged by bundle guard (`cnicMasked` private token) | Removed the unused private ERP field from public catalog | `check:bundle` OK on fresh clone |
| Q | Medium | `.github/workflows/ci.yml` frontend job | Node 20 pinned while Vite 8/rolldown need `^20.19 || >=22.12` | Pinned `node-version: '22'`, renamed job | Local Node 22 verify green |

## Vault hardening round (2026-11-17, commit `8c56b76`)

Full findings detail in `SECURE_FILE_VAULT_AUDIT.md`; threat analysis in `FILE_STORAGE_THREAT_MODEL.md`.

| ID | Severity | File / method | Root cause | Fix | Result |
|---|---|---|---|---|---|
| R | High | `DocumentController::magicBytesMatch` | `file_get_contents($path,false,null,32)` — 32 passed as OFFSET, first 32 bytes never inspected; `MZ`/`<?php`/ELF passed with allowed extensions | `App\Support\UploadSecurity::magicBytesLookDangerous()` reads offset 0 | `test_executable_content_disguised_as_pdf_is_rejected_at_offset_zero` |
| S | High | `DocumentController::storeVersion` | Version uploads validated only size — full bypass of allowlist/MIME/magic/malware | Same pipeline as first uploads; rejected version leaves pointer/bytes intact | `test_document_version_upload_cannot_bypass_content_checks` |
| T | High | `MediaController::store` | Extension check only; client-declared mime stored & served; no scan | Full `UploadSecurity` pipeline + malware scan; stored mime is SNIFFED type | `test_media_upload_rejects_executable_content`, sniffed-Content-Type test |
| U | Medium | `PlanLimits::usage` | Quota counted only active documents — trash/versions/media bytes escaped | Sums `withTrashed()` documents + all versions + media; SubscriptionController mirrors | `test_storage_quota_counts_trashed_documents_versions_and_media` |
| V | Medium | `MediaController::deliver`, `DealerFilesController::store` | Unrestricted `redirect()->away()` + arbitrary client URLs | `UploadSecurity::isAllowedExternalUrl()` (HTTPS public hosts, no creds, no private IPs) at registration AND delivery; legacy bad rows → 410 | `test_media_external_urls_must_be_https_public_hosts` |
| W | Medium | `DocumentController::stream` | Denied download attempts left no audit/access-log trace | `document.download_denied` audit + `download_denied` forensic row | `test_unauthorized_and_sensitive_download_attempts_are_audited` |
| X | High | `sharedDownload`, media delivery, `sharesStore` | Unscanned (fail-open) bytes deliverable / shareable | Scan-lifecycle gates: 423 download, 422 share mint, 423 media until `scanned_clean`; rescan endpoint promotes/holds | `test_fail_open_hold_is_not_downloadable_until_rescan_clears_it` |
| Y | High | whole vault | No at-rest encryption for file bytes | `FileVaultCrypto` AES-256-GCM + `SecureFileStore` gateway + `vault:encrypt` (idempotent, rotation-aware) | encryption tests ×3 (raw bytes unreadable, legacy backfill, public media) |
| Z | Low | upload routes | No upload-specific throttle | `throttle:20,1` on documents/versions/media POST | `test_upload_endpoints_get_a_dedicated_rate_limit` |
| AA | Low | download headers | Client filename interpolated into Content-Disposition | `safeDispositionName` + Laravel `makeDisposition`; upload-time sanitization | `test_hostile_original_filenames_cannot_inject_headers` |
| AB | Low | quarantine | Rejected material unbounded | 30-day retention + scheduled `vault:purge-quarantine`, audited | `test_quarantine_purge_removes_only_expired_rows` |

## Test-count progression

Baseline 216 / 1,866 assertions → 243 / 1,974 after CI+audit fixes → **260 / 2,095 assertions**
after the vault hardening round (both MySQL 8.4.9 and SQLite; MySQL run 133.46s with
`migrate:fresh` including `2026_11_17_000001_add_vault_scan_lifecycle`).
New backend test file: `FileVaultSecurityTest` (17 tests / 121 assertions).

## Test integrity

No test was deleted, skipped or weakened. `BookingConcurrencyTest`'s fixture was extended with the `finance.create_transaction` permission that the new authorization gate legitimately requires; all five of its original assertions remain unchanged and pass. In the vault round, `StorageSecurityTest` and `MalwareUploadEnforcementTest` fixtures gained a deterministic scanner stub / quarantine disk fake so verdict-dependent flows are engine-independent — every original assertion remains unchanged and passes.
