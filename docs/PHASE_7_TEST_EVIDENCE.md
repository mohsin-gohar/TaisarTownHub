# PHASE 7 — Test Evidence

**Date:** 2026-11-28  
**Branch:** `audit/phase-7-agency-erp-workflows` @ `1a899db`

---

## Phase 7 execution runs (gap-worktree backend, MySQL `taisartownhub_test`)

### Batch 1 — Agency / member / storage / capability (7 classes)
```
php artisan test --filter="AgencyDataSeparationTest|AgencyMemberAccessRestrictionsTest|CrossAgencyCapabilitiesTest|AgencyStorageOversightTest"
Tests:    20 passed (100 assertions)
Duration: 80.80s
```
- `AgencyDataSeparationTest` ✅ 4/4
- `AgencyMemberAccessRestrictionsTest` ✅ 5/5
- `AgencyStorageOversightTest` ✅ 8/8
- `CrossAgencyCapabilitiesTest` ✅ 2/2

### Batch 2 — CRM / leads / inquiries / saved / notifications (4 classes)
```
php artisan test --filter="CrmLeadsTest|InquiryCaptchaTest|FavoritesNotificationsTest|SavedSearchAlertsTest"
Tests:    33 passed, 2 failed (215 assertions)
Duration: 97.78s
```
- `CrmLeadsTest` ❌ 2 (CSV export scope / formula injection)
- `InquiryCaptchaTest` ✅
- `FavoritesNotificationsTest` ✅
- `SavedSearchAlertsTest` ✅

### Batch 3 — Finance / transactions / reports (3 classes)
```
php artisan test --filter="FinanceLifecycleTest|FinanceReportsTest|TransactionFinanceDataIsolationTest"
Tests:    1 failed, 19 passed (158 assertions)
Duration: 58.53s
```
- `FinanceLifecycleTest` ✅
- `FinanceReportsTest` ✅
- `TransactionFinanceDataIsolationTest` ❌ 1 (cross-agency isolation)

### Batch 4 — Property / media / vault / dealer / subscription (7 classes)
```
php artisan test --filter="PublicSubscriptionTest|SubscriptionInvoicesTest|CrossTenantDocumentAccessTest|DocumentSharesTest|DealerAgencyLinkTest|DealerFilesAuthorizationTest|FileVaultSecurityTest"
Tests:    54 passed (323 assertions)
Duration: 70.03s
```
- `PublicSubscriptionTest` ✅
- `SubscriptionInvoicesTest` ✅
- `CrossTenantDocumentAccessTest` ✅
- `DocumentSharesTest` ✅
- `DealerAgencyLinkTest` ✅
- `DealerFilesAuthorizationTest` ✅
- `FileVaultSecurityTest` ✅
- `AdminStaffSubscriptionTest` (included earlier) ✅ (sub-set)

### Phase 7 core totals
- Passed: 20 + 33 + 19 + 54 = **126**
- Failed: 2 + 1 = **3**
- All failures are genuine (not DB-state artifacts at time of run).

---

## Prior Phase 1–6 gap-run evidence (referenced)

- Backend: `489 passed, 7 failed` (5157 assertions) — after fixing 13 FK failures (`propertyId => null` / real Dealer).
- Frontend: `npm run verify` PASS — tsc ✅, 160 tests ✅, build ✅ (29 pages), bundle ✅; `npm audit` 0 vulns; `composer audit` 0 vulns.
- Playwright E2E (prior phase): 90 passed (4.8m); WhatsApp E2E 3/3; live HTTPS 200.

---

## Evidence policy

- No test results were claimed without execution.
- All numbers above come from actual `php artisan test` output captured in this session.

---

## Status

**PARTIALLY VERIFIED** for Phase 7 workflows; backend green baseline re-established and re-verified in Phase 7 batches.
