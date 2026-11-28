# Phase 8.6 — CMS & System Settings Audit

Status: **PARTIALLY VERIFIED**

## Files reviewed

- `backend/app/Http/Controllers/Api/AdminSystemController.php` — `settings()`, `updateSetting()`
- `backend/app/Http/Controllers/Api/WorkspaceController.php` — `PUBLIC_CMS_KEYS` (lines 126-134), `canRead()`/`canWrite()` gates (lines 947-1006), CMS persist (lines 350-356)
- `frontend/src/components/admin/AdminCMS.tsx` — full read
- `frontend/src/context/AppContext.tsx` — `updateCMSConfig` (line 2035), workspace autosave (line 684)
- `backend/database/seeders/EnterpriseSeeder.php` — system settings catalog (lines 127-151)

## Findings

| Severity | Finding | Evidence | Disposition |
|----------|---------|----------|-------------|
| — | `settings()` requires `admin.manage_platform_settings` OR `admin.manage_agencies` | `AdminSystemController.php:81` | VERIFIED |
| — | `updateSetting()` requires `admin.manage_platform_settings`, validates key/value, writes audit with old/new | lines 99-124 | VERIFIED |
| — | No secrets in seeded `system_settings` catalog (all flags/limits/contact config) | `EnterpriseSeeder.php:127-151` | VERIFIED |
| — | CMS persisted via workspace `BusinessRecord::put('cms','default',...)` with `edit_cms` write gate and allowlisted keys | `WorkspaceController.php:126-134,350-356,999` | VERIFIED |
| — | CMS read gate: `edit_cms`/`view_reports`/`view_properties` | line 968 | VERIFIED |
| P2 | CMS has no dedicated CRUD API/table; it is a single workspace business-record blob. Versioning/audit of individual CMS fields is not available. | `AdminCMS.tsx` uses `updateCMSConfig` → generic workspace save | DOCUMENTED — architecture limitation, not a defect |
| P2 | `updateSetting()` audit log stores `old`/`new` values verbatim; if an operator stores a secret as a setting it would appear in audit log. Current catalog has no such setting. | line 123 | DOCUMENTED — low risk, no fix applied to avoid changing audit contract |

## Maps/location data

`MapController` admin endpoints (lines 326-334) are present; permission gates exist. Not individually exercised in new tests — see `PHASE_8_TEST_EVIDENCE.md` NOT TESTED section.

## Authorization test

`Phase8SuperAdminAuthorizationTest::test_agency_manager_cannot_approve_agency_or_moderate_listings` includes PATCH `/api/admin/settings` → 403. **PASS**.

## Browser verification

NOT TESTED (no live Playwright session). Static review only.
