# Phase 7 — Private Document Vault Audit

Branch: `audit/opencode-phase-7-agency-erp-workflows`
Date: 2026-11-28

## Findings

| # | Severity | File / Method | Finding | Fix | Regression test | Result |
|---|----------|---------------|---------|-----|-----------------|--------|
| 1 | — | `DocumentController::findScoped()` | Every document access scoped by `where('agency_id', $agencyId)`. Cross-tenant access → 404. | N/A | `DocumentVaultTest` | PASS |
| 2 | — | `DocumentController::store()` | MIME + signature + extension + size validation → malware scan → encrypted store (`SecureFileStore`) → audit log. | N/A | `DocumentVaultTest` | PASS |
| 3 | P1 | `SecureFileStore::storeUpload()/putBytes()/move()/scanStored()` | Fixed in critical repairs: all storage write results checked, destination verified before source deletion, failed temp-file writes handled. | Added write-result checks + existence verification. | `SecureFileStoreTest` | PASS |
| 4 | P1 | `ClamAVScanner` | Fixed in critical repairs: `fread()` failures return null (fail-closed), incomplete stream never accepted as clean. | Added `=== false` checks on both file and socket reads. | `MalwareScanningTest` (11 tests) | PASS |
| 5 | — | `DocumentController::sharesStore()/sharedDownload()` | Token-based share links, expiry enforced, revocable, download counted, transaction-wrapped. | N/A | `DocumentSharingTest` | PASS |
| 6 | — | `DocumentController::quarantineIndex()/rescan()` | Infected files isolated, never exposed as clean, rescan available. | N/A | `MalwareScanningTest` | PASS |
| 7 | — | `DocumentController::versions()/storeVersion()` | Document versioning with checksums in `document_versions` table. | N/A | `DocumentVersionTest` | PASS |
| 8 | — | `DocumentController::logAccess()` | Every preview/download/share logged with user ID + IP. | N/A | `DocumentAccessLogTest` | PASS |
| 9 | — | `DocumentController::usage()` | Storage quota reporting vs plan limit. | N/A | `DocumentQuotaTest` | PASS |

## Security invariants verified

- Agency A cannot access Agency B's documents — VERIFIED
- Unauthorized members cannot preview/download — VERIFIED (permission gate + agency scope)
- Shared links must be valid, unexpired, non-revoked — VERIFIED
- Quarantined files never exposed as clean — VERIFIED
- Malware scanning fail-closed — VERIFIED (fake-clamd tests)

## Verdict

No unresolved P0/P1 defects. ClamAV live scanning remains an external blocker (no daemon) — documented, not claimed verified.
