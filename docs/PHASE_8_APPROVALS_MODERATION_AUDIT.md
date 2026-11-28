# Phase 8.3 — Approvals & Moderation Audit

Status: **VERIFIED**

## Files reviewed

- `backend/app/Http/Controllers/Api/AdminAgencyController.php::verify()/status()`
- `backend/app/Http/Controllers/Api/PropertyController.php::submit()/approve()/reject()/moderationQueue()/setMarketplaceVisibility()`
- `backend/routes/api_definitions.php` lines 307-308, moderation routes

## Findings

| Severity | Finding | Evidence | Disposition |
|----------|---------|----------|-------------|
| — | Agency approval/rejection requires platform `admin.manage_agencies`; audit logged | `AdminAgencyController.php:80-135,136-168` | VERIFIED |
| — | Listing moderation (`properties.approve`) is platform-only; state transitions via `DB::transaction` + `recordTransition()` | `PropertyController.php:598-752,919` | VERIFIED |
| — | `pending_changes` (pre-approval edits) hidden from ordinary members, visible only to moderators | `serializeForScope()` `canModerate` gate | VERIFIED |
| — | Public visibility requires `approval_status=approved` + `visibility=public` + sale-only | `publiclyVisible()` scope, `PropertyController.php:69` | VERIFIED |
| — | IDOR/BOLA: cross-agency property IDs blocked by `forAgency()` scope | `PropertyController.php:114-118` | VERIFIED |

## Direct API attempts by lower roles

`Phase8SuperAdminAuthorizationTest::test_agency_manager_cannot_approve_agency_or_moderate_listings` — manager PATCH `/api/admin/agencies/{id}/verify` and `/status` → 403. **PASS**.

## Browser verification

NOT TESTED (no live Playwright admin moderation journey). Static controls inspected in `AdminModerationDesk.tsx`, `AdminAgencyApprovals.tsx`.
