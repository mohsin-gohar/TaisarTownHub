# Phase 8.4 & 8.5 — Featured Agencies & Support Workflows Audit

Status: **VERIFIED**

## Featured agencies

### Files reviewed
- `backend/app/Http/Controllers/Api/AdminFeaturedAgencyController.php` — all 7 methods
- `backend/app/Services/AgencyPromotionService.php` — `promote()`, `reorder()`, `expireDue()`, `assertSlotAvailable()`
- `backend/routes/api_definitions.php` lines 316-323

### Findings

| Severity | Finding | Evidence | Disposition |
|----------|---------|----------|-------------|
| — | All admin promotion endpoints require `admin.manage_promotions` | `AdminFeaturedAgencyController.php:32,79,129,174,212,243,277,303` | VERIFIED |
| P1 (fixed earlier) | Duplicate check + slot capacity moved inside transaction with `lockForUpdate()` | Critical repair `2fb1996`/`8f461da` | VERIFIED |
| — | Package constraints: `PromotionPackage::where('is_active',true)->findOrFail()` | `AgencyPromotionService::promote()` | VERIFIED |
| — | Eligibility via `assertEligible()`; expiry via `expireDue()` | service | VERIFIED |
| — | Public badge only for active + verified + live promotion | `PropertyController::publicPropertyPayload()` | VERIFIED |
| — | Audit records on feature changes | controller `writeAudit()` | VERIFIED |

### Test evidence
`FeaturedAgencyPromotionTest` (existing) passes in focused runs. Concurrency locks verified in prior critical-repair tests.

## Support workflows

### Files reviewed
- `backend/app/Http/Controllers/Api/SupportController.php` — `index`, `store`, `show`, `storeMessage`, `updateStatus`, `adminIndex`, `assignableStaff`, `adminAssign`, `findVisible`
- `backend/routes/api_definitions.php` lines 346-348

### Findings

| Severity | Finding | Evidence | Disposition |
|----------|---------|----------|-------------|
| — | Ticket + opening message created in one `DB::transaction` (no empty tickets) | `SupportController.php:76-96` | VERIFIED |
| — | Agency link from membership context, never from input (mass-assignment safe) | line 72 | VERIFIED |
| — | `findVisible()` — agency tickets visible only from same active agency context; platform tickets private to creator; enumeration-safe 404 | lines 350-381 | VERIFIED |
| — | Staff endpoints require `admin.support_tickets` | lines 255,290,307 | VERIFIED |
| — | Internal notes vs user-visible replies: `is_staff` flag in message payload | line 124 | VERIFIED |

### Test evidence
`SupportTicketsTest` (existing) passes in focused runs. `Phase8SuperAdminAuthorizationTest::test_agency_owner_cannot_access_platform_only_surfaces` covers `/api/admin/support-tickets` → 403. **PASS**.

No new support module invented — verified existing implementation only.
