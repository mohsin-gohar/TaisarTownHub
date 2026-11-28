# Phase 8.1 — Super-Admin Dashboard Audit

Status: **VERIFIED**

## Files reviewed (full read)

- `backend/app/Http\Controllers\Api\AdminDashboardController.php` — `reports()` (lines 32-94), `index()` (lines 96-200), `timeseries()` (lines 203-238)
- `backend/routes/api_definitions.php` lines 301-302
- `frontend/src/App.tsx` admin view gating (lines 135-147, 314-339)

## Findings

| Severity | Finding | Evidence | Disposition |
|----------|---------|----------|-------------|
| — | `reports()` requires `admin.view_reports` (platform-only) | `AdminDashboardController.php:34` | VERIFIED |
| — | `index()` scopes listings/leads/transactions by `agency_id` when present; platform totals only when `$isPlatform && hasPermission('admin.view_reports')` | lines 114-118, 176-187 | VERIFIED |
| — | Finance figures only returned when `canSeeFinance` (`finance.view`/`view_reports`/`admin.view_reports`) | lines 140-158 | VERIFIED |
| — | Date-range inputs validated (`from`, `to` with `after_or_equal:from`) | lines 36-39 | VERIFIED |
| — | Time-series uses sargable range bounds, agency-scoped | lines 203-238 | VERIFIED |
| P2 | `/api/admin/dashboard` reachable by any authenticated user holding `properties.view`; returns only agency-scoped data, platform block empty | line 98 | By design — not a platform data leak |

## Regression test

`Phase8SuperAdminAuthorizationTest::test_platform_reports_require_admin_view_reports_and_validate_dates` — invalid dates → 422, valid → 200 with correct period. **PASS** (6 tests total in file, 38 assertions).

## Tenant privacy

`Phase8SuperAdminAuthorizationTest::test_agency_owner_cannot_access_platform_only_surfaces` — owner gets 403/404 on `/api/admin/reports`. **PASS**.

## Browser verification

NOT TESTED — no live frontend+backend server session with Playwright interaction. Static route gating inspected only.
