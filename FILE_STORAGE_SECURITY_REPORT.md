# File Storage Security Report

**Date:** 2026-11-17 · **Commit:** `8c56b76` · **Baseline:** 243 tests / 1,974 assertions → **Now:** 260 tests / 2,095 assertions (SQLite **and** MySQL 8.4.9)

---

## 1. Control matrix (with test evidence)

| # | Control | Implementation | Verified by |
|---|---|---|---|
| 1 | Private storage outside web root | `documents` + `quarantine` disks under `storage/app/private/`, `serve=false`, never symlinked | `FileVaultSecurityTest::test_vault_disks_stay_configured_outside_the_web_root`; on-disk check: `backend/public/storage` does not exist |
| 2 | Server-side multi-tenant isolation (no IDOR) | `AgencyContext` middleware resolves agency from own memberships; all queries scoped; cross-agency → 404 | `StorageSecurityTest` (rival agency 404), `CrossAgencyCapabilitiesTest`, `DealerFilesAuthorizationTest` (X-Agency-Id spoof) |
| 3 | Files inaccessible until scan completes | `scan_status` lifecycle; download 423 + share mint 422 + media delivery gate until `scanned_clean` | `FileVaultSecurityTest::test_fail_open_hold_is_not_downloadable_until_rescan_clears_it` |
| 4 | Real MIME / magic bytes | Server sniffing (`mime_content_type`), dangerous-MIME blocklist, magic bytes at offset 0 | `test_executable_content_disguised_as_pdf_is_rejected_at_offset_zero`, `test_media_upload_rejects_executable_content`, `test_served_content_type_is_the_sniffed_type_not_the_client_declaration` |
| 5 | Malware scanning (ClamAV protocol) | clamscan CLI + clamd INSTREAM (length-prefixed chunks, path never sent); verdict never "clean" unless a real scan ran; fail-closed config | `MalwareScanningTest` (11 tests, real fake-clamd process byte-exact INSTREAM framing), `MalwareUploadEnforcementTest` (5 end-to-end), quarantine tests |
| 6 | Quarantine isolation + retention | `quarantine` disk, `document_quarantines` table, visibility endpoint, rescan, daily purge after 30-day window | `test_infected_upload_is_rejected_and_isolated_on_the_quarantine_disk`, `test_quarantine_purge_removes_only_expired_rows` |
| 7 | Encryption at rest | AES-256-GCM `FileVaultCrypto`, APP_KEY-derived key (never stored beside files), transparent legacy reads, `vault:encrypt` backfill/rotation | `test_vault_bytes_are_encrypted_at_rest_and_stream_transparently`, `test_legacy_plaintext_files_still_stream_and_vault_encrypt_rewrites_them`, `test_public_media_bytes_are_encrypted_too` |
| 8 | Auth-checked downloads | sanctum + MFA middleware, `documents.download`, sensitive clearance, active status, scanned-clean gate, then `SecureFileStore::response` (nosniff, safe disposition, no-store) | `StorageSecurityTest` download/anonymous tests, `test_unauthorized_and_sensitive_download_attempts_are_audited`, `test_hostile_original_filenames_cannot_inject_headers` |
| 9 | Granular permissions (documents.*) | 8-key catalog seeded to roles, enforced per endpoint, denials audited | `RolePermissionsTest`, audit assertions in `FileVaultSecurityTest` |
| 10 | Sharing disabled by default / expiring capability tokens | SHA-256-token-hash only, expiry, revocation, atomic download cap, sensitive clearance required to mint, scan-clearance gate | `DocumentSharesTest` (6 tests) + new lifecycle gates in `FileVaultSecurityTest` |
| 11 | Audit trail (view/download/replace/delete) | `audit_logs` enterprise columns + `document_access_logs` forensic rows; access-log reader endpoints; purge actions audited | access-log assertions in `StorageSecurityTest`, `FileVaultSecurityTest`; `AdminSystemController::auditLogs` scoping |
| 12 | Quotas + rate limits | Storage = documents (incl. trash) + versions + media; 402 `plan_limit_reached`; uploads `throttle:20,1` | `test_storage_quota_counts_trashed_documents_versions_and_media`, `test_upload_endpoints_get_a_dedicated_rate_limit`, `PlanLimitEnforcementTest` |
| 13 | Public exposure | No `asset('storage/…')`, no `Storage::url()`; public endpoints only: public-visibility media (guarded) and hash-gated share tokens; external URL redirects HTTPS-public-only, re-checked per delivery | `test_media_external_urls_must_be_https_public_hosts`, bundle guard, grep-verified absence of storage URL generation |
| 14 | Integrity | SHA-256 checksum of plaintext stored per document/version; recomputed at rescan promotion | `DocumentController` checksum flows, rescan test path |

## 2. Real MySQL verification

- Full backend suite ran on a disposable **MySQL 8.4.9** instance (`migrate:fresh` including new migration `2026_11_17_000001_add_vault_scan_lifecycle` → `260 passed / 2,095 assertions`, 133.46s).
- SQLite suite: `260 passed / 2,095 assertions` (31.91s).
- CI additionally runs the suite on MySQL 8 (integration job with driver guard — no silent SQLite fallback; `backend/scripts/assert-db-driver.php`).
- Note: the quota/concurrency-relevant SQL (`withTrashed()` sums, `lockForUpdate` share counters) runs against MySQL in CI, so the quota accounting claims are engine-verified, not SQLite-only.

## 3. Vulnerabilities found and fixed (summary)

V-1 magic bytes offset bug (HIGH) · V-2 version-upload bypass (HIGH) · V-3 media pipeline missing (HIGH) · V-4 quota escape via trash/versions/media (MEDIUM) · V-5 open redirect / arbitrary URLs (MEDIUM) · V-6 denied downloads unaudited (MEDIUM) · V-7 unscanned bytes deliverable (HIGH) · V-8 no at-rest encryption (HIGH) · V-9 no upload throttle (LOW) · V-10 header injection via filenames (LOW) · V-11 unbounded quarantine (LOW).

Details with file references and per-finding regression tests: `SECURE_FILE_VAULT_AUDIT.md`.

## 4. Honest limitations

1. **No production ClamAV exercised.** The wire protocol is verified against a real fake-clamd process implementing INSTREAM byte-for-byte, but production clamd + signature DB behaviour is BLOCKED on infrastructure. Fail-closed production config is safe by construction.
2. **No real backup restore drill.** Backup scripts exist (DB + vault tar + manifest) and `restore-drill.ps1` exists, but RPO/RTO figures are documented targets, not measured results (BLOCKED on infrastructure).
3. **KMS/HSM not integrated** — key material is APP_KEY from env/secret store; rotation procedure is documented and tooling provided (`vault:encrypt`), verified in tests.
4. **Legacy external URLs** registered before the HTTPS guard: delivery now refuses them (410); no data backfill migration included.
5. **Browser E2E / Lighthouse pass not run this increment** (tab automation unavailable); frontend evidence is the verify chain (typecheck/tests/prerender/bundle guard), all green.
