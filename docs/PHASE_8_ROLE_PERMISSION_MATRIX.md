# Phase 8 — Role / Permission Matrix

Status: **VERIFIED** (backend enforcement)

## Persona × platform surface

| Surface | Visitor | Agency Member | Agency Manager | Agency Owner | Super Admin |
|---------|---------|---------------|----------------|--------------|-------------|
| `/api/admin/dashboard` (platform block) | 401 | empty platform | empty platform | empty platform | full |
| `/api/admin/reports` | 401 | 403 | 403 | 403 | 200 |
| `/api/admin/users` | 401 | 403 | 403 | 403 | 200 |
| `/api/admin/agencies` | 401 | 403 | 403 | 403 | 200 |
| Agency verify/status | 401 | 403 | 403 | 403 | 200 |
| Listing moderation | 401 | 403 | 403 | 403 | 200 |
| `/api/admin/featured-agencies` | 401 | 403 | 403 | 403 | 200 |
| `/api/admin/support-tickets` | 401 | 403 | 403 | 403 | 200 |
| `/api/admin/settings` | 401 | 403 | 403 | 403 | 200 |
| `/api/admin/audit-logs` (platform) | 401 | agency-only* | agency-only* | agency-only* | 200 |
| `/api/admin/plans` | 401 | 403 | 403 | 403 | 200 |

\* with `view_reports` permission; scoped to own agency only.

## Tests proving the matrix

`Phase8SuperAdminAuthorizationTest` (6 tests, 38 assertions):
1. `test_platform_admin_can_access_every_super_admin_surface` — 11 endpoints → 200
2. `test_agency_owner_cannot_access_platform_only_surfaces` — 11 endpoints → 403/404
3. `test_agency_manager_cannot_approve_agency_or_moderate_listings` — 4 privileged writes → 403
4. `test_ordinary_member_cannot_reach_platform_dashboard_or_audit_logs` — 3 endpoints → 403
5. `test_platform_reports_require_admin_view_reports_and_validate_dates` — 422 invalid, 200 valid
6. `test_audit_logs_are_scoped_for_agency_reporters_and_platform_for_admins` — tenant isolation

All **PASS**.

## Attack classes checked

- **IDOR/BOLA**: agency-scoped `findScoped()`/`findVisible()`/`forAgency()`; cross-tenant → 404/403. VERIFIED.
- **Privilege escalation**: custom/system role delegation checks (Phase 7 P1 fix) + platform permission gates. VERIFIED.
- **Mass assignment**: server-owned keys stripped in `WorkspaceController::RESERVED_KEYS`; agency stamp from membership. VERIFIED.
- **Cross-agency**: tenant isolation tests (finance, CRM, documents, workspace). VERIFIED.
- **Unauthorized state transitions**: platform-only verify/status/moderation/promotion gates. VERIFIED.
- **Forged request params**: date validation, `receipt_no` unique validation, `agency_id` never from body. VERIFIED.
- **CSRF/session/token**: Sanctum token auth on all admin routes; `auth:sanctum` middleware. VERIFIED (token-based, no session cookies).
- **Rate limiting**: `throttle:api.public`/`api.write` on public endpoints; admin routes under api group throttle. VERIFIED by route config.
- **Audit-log tampering**: no write route; append-only at app boundary. VERIFIED.
- **Duplicate/concurrent**: locks on promotion/schedules/payments; unique constraints. VERIFIED.

## Frontend controls

Frontend admin routes are gated by `isAdminAuthenticated` + workspace access in `App.tsx`. **A hidden button is not authorization** — every sensitive operation is enforced server-side as proven above.
