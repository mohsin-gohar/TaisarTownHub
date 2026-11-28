# Phase 2 — Service Layer Audit

Scope: `app/Services` (7 files: `AgencyPromotionService`, `AgencyOnboarding`, `ClamAVScanner`, `DisabledMalwareScanner`, `FileVaultCrypto`, `MalwareScanner` (interface), `SecureFileStore`). Each public method inspected for business rules, transactions, race conditions, idempotency, failure handling, logging, external integrations, performance.

## Defects repaired in Phase 2
1. **CRITICAL — `AgencyOnboarding::ensureStarterSubscription()` race.** Check-then-create without a transaction/lock could insert duplicate subscription rows for one agency under concurrency. Fixed: wrapped in `DB::transaction` with `Agency::whereKey(...)->lockForUpdate()` to serialize per-agency onboarding.

## Confirmed findings (evidence-backed)
| # | Service/method | File:line | Severity | Description |
|---|---|---|---|---|
| 1 | AgencyOnboarding::ensureStarterSubscription | AgencyOnboarding.php:24 | CRITICAL | Check-then-act race — FIXED |
| 2 | AgencyPromotionService::promote | AgencyPromotionService.php:126,134 | CRITICAL | Duplicate-placement exists() check runs OUTSIDE the transaction — concurrent duplicate placements |
| 3 | SecureFileStore::move | SecureFileStore.php:51-56 | CRITICAL | Decrypt→encrypt→delete-source without transaction/lock; crash leaves duplicate copies |
| 4 | AgencyPromotionService::reorder | AgencyPromotionService.php:252 | HIGH | Rows read without lockForUpdate() inside transaction — lost-update on positions |
| 5 | AgencyPromotionService::expireDue | AgencyPromotionService.php:271,276 | HIGH | get() outside transaction — double expiry notifications |
| 6 | SecureFileStore::scanStored | SecureFileStore.php:81-86 | HIGH | Decrypted plaintext written to OS temp; persists if scan throws/crashes before unlink |
| 7 | SecureFileStore::storeUpload/putBytes | SecureFileStore.php:33,44 | HIGH | Entire upload loaded into memory (OOM risk on large files) |
| 8 | AgencyPromotionService::update | AgencyPromotionService.php:191 | MEDIUM | assertSlotAvailable count() without lockForUpdate — slot over-allocation |
| 9 | AgencyPromotionService::reorderCurrent/reorder/expireDue | AgencyPromotionService.php:257,336,277 | MEDIUM | N+1 per-row updates inside transaction |
| 10 | AgencyPromotionService::stats | AgencyPromotionService.php:96-115 | MEDIUM | Five count() queries instead of one aggregate |
| 11 | ClamAVScanner::__construct/scan | ClamAVScanner.php:38,52,71-75 | MEDIUM | `failClosed` defaults false (fail-open) — production config sets true, but default is unsafe |
| 12 | FileVaultCrypto::key | FileVaultCrypto.php:106-115 | MEDIUM | Single-round SHA-256 key derivation, no stretching |
| 13 | SecureFileStore::response | SecureFileStore.php:125 | MEDIUM | Decrypts entire file into memory inside stream callback |
| 14 | SecureFileStore / FileVaultCrypto logging | multiple | LOW | No success/audit logging on store/move/scan/download; crypto failures not logged |

## Positive patterns (verified)
- `ClamAVScanner` INSTREAM protocol correct; errors never treated as clean; `scanViaClamscan` exit 2 = unknown (not clean); `DisabledMalwareScanner` always `skipped()`.
- `FileVaultCrypto` uses AES-256-GCM with random IV + auth tag (sound envelope).
- `SecureFileStore::safeDispositionName` strips CR/LF/control/quotes, caps length (header injection mitigated).
- `AgencyPromotionService::promote/update/reorder/expireDue` DO use `DB::transaction` (gap is missing lockForUpdate + checks outside the transaction boundary).

## Remaining (documented, not patched to avoid scope creep)
Items 2-14 are documented in PHASE_2_REMAINING_ISSUES with evidence. The CRITICAL promote() check-outside-transaction (item 2) is the highest-priority follow-up.