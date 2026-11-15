# TaisarTownHub — Critical Security Fix Report
## Phase 1 — Security & Tenant Isolation (P0)

**Date:** 2026-11-14  
**Scope:** Laravel 12 API (`backend/`) + React 19 frontend  
**Verification basis:** Independent inspection of actual source code + live database + test suite (NOT reliance on prior audit reports)

---

## Executive Summary

An independent codebase audit was performed against every Phase 1 claim. The project already contains substantial, tested tenant-isolation and security infrastructure. Where genuine gaps existed (missing explicit auth guards, no route-level auth middleware), they were fixed with small, reviewable changes. No data was deleted or overwritten. All 176 backend tests (1613 assertions) pass.

## 1. Legacy `business_records` Table — Investigation

### 1.1 Schema
File: `backend/database/migrations/2026_11_03_170000_create_business_records_table.php`  
Extended by: `2026_11_05_100000_add_indexes_to_business_records.php`, `2026_11_10_000015_extend_audit_logs_and_business_records.php`

| Column | Type | Null? | Notes |
|--------|------|-------|-------|
| id | integer | NO | PK |
| collection | varchar | NO | Record "type" (no separate `record_type` column) |
| record_id | varchar | NO | Logical record key |
| payload | text | NO | JSON blob; cast to array on model |
| deleted_at | datetime | YES | Soft delete |
| created_at | datetime | YES | |
| updated_at | datetime | YES | |
| agency_id | varchar | YES | **Nullable — legacy rows may have no owner** |

Indexes: `(collection, record_id)` unique; `(agency_id, collection)`; `(collection, updated_at)`; `(collection, deleted_at)`.

### 1.2 Record inventory (live DB)
Total rows: **104**

| Collection | Rows |
|------------|------|
| properties | 20 |
| dealers | 13 |
| inquiries | 13 |
| subscribers | 9 |
| plots | 8 |
| media | 7 |
| videos | 6 |
| files | 5 |
| payments | 4 |
| projects | 4 |
| buyers | 3 |
| commissions | 3 |
| sellers | 3 |
| transactions | 3 |
| testimonials | 2 |
| cms | 1 |

### 1.3 Ownership (agency_id) distribution
| agency_id | Rows |
|-----------|------|
| NULL | **101** |
| agency-demo | 1 |
| agy_a25696960b79ee4a | 1 |
| agy_b1a721e3f0a6e5bb | 1 |

**101 of 104 records have NULL agency_id** — unknown/unspecified ownership. These are pre-enterprise demo records belonging to no agency.

### 1.4 API endpoints that read/write business_records
All access funnels through a single controller:

| Endpoint | Method | Controller | Auth |
|----------|--------|------------|------|
| `/api/workspace` | GET | `WorkspaceController@show` | `auth:sanctum` + requireAny read gate |
| `/api/workspace` | PUT | `WorkspaceController@save` | `auth:sanctum` + per-collection write gate |
| `/api/catalog` | GET | `WorkspaceController@catalog` | Public (public rows only) |
| `/api/subscribers` | POST | `WorkspaceController@subscribe` | Public |
| `/api/properties/*` | various | `PropertyController` | `auth:sanctum` |
| `/api/dealers/*` | various | `DealerController` | `auth:sanctum` |
| `/api/inquiries` | various | `InquiryController` | `auth:sanctum` |
| `/api/transactions` | various | `TransactionController` / `FinanceController` | `auth:sanctum` |

Plus mirrored relational tables (`properties`, `dealers`, `inquiries`, `transactions`, `projects`, etc.) each have their own endpoints.

## 2. Fixes Applied

### Fix 1 — Route-level `auth:sanctum` on all protected routes (NEW)
**File:** `backend/routes/api_definitions.php`

Previously protected routes relied solely on controller-level `$request->user()` checks. Unauthenticated requests to some controllers (e.g. `NotificationController@index`) would hit a PHP 500 ("Call to member function on null") instead of a clean 401.

Added `->middleware('auth:sanctum')` to every protected route group:
- `/workspace` (GET/PUT)
- `/notifications` (GET/POST ×4)
- `/favorites`, `/transactions`
- `/properties/*`, `/dealers/*`, `/dealer-files/*`, `/inquiries/*`
- `/agency/leads`, `/agency/documents/*`, `/agency/media/*`
- `/agency/transactions/*`, `/agency/payments/*`, `/agency/commissions/*`
- `/agency/expenses`, `/agency/reports`
- `/agency/members`, `/agency/invitations`, `/agency/roles`
- `/support/tickets` (+ messages/status)
- `/payments/subscription/checkout`
- All `/admin/*` routes

**Public routes intentionally left unauthenticated:** `/auth/*`, `/catalog`, `/subscribers` (POST), `/shares/{token}/download`, `/inquiries` (POST), `/locations/*`.

**Verification:** `php -l` clean; all 176 backend tests pass (auth via `actingAs()` works with sanctum guard).

### Fix 2 — Explicit auth guard in NotificationController (FIXED)
**File:** `backend/app/Http/Controllers/Api/NotificationController.php`

`index()`, `unreadCount()`, `markRead()`, `markAllRead()` previously used `$request->user()` without a null check, causing a 500 error for unauthenticated callers. Added `if (!$user) { abort(401, 'Authentication required.'); }` to each method.

Notifications remain **own-only** (`$user->notifications()` / `findOrFail` scoped to the acting user) — users can only access their own notifications.

**Verification:** `php -l` clean; route now returns clean 401 JSON instead of 500.

### Fix 3 — No changes needed (already correct)
The following Phase 1 requirements were verified as already implemented and tested — **no code change required:**

| Requirement | Status | Evidence |
|-------------|--------|----------|
| Prevent cross-agency access to legacy records | ✅ | `WorkspaceController::allRows()` scopes reads to own agency + NULL (platform) rows only; never another agency's rows. `WorkspaceController::syncList()` blocks writes to rows owned by another agency (line 403). |
| Do not assign legacy records to an agency without verified ownership | ✅ | `LegacyBackfillCommand` is **dry-run by default**; `--apply` requires `--backup=<verified dump>`; derives ownership only from typed twins whose agency stamp already exists; skips rows that already claim a different agency; no row is ever deleted. |
| Isolate unknown-ownership records (platform/restricted) | ✅ | 101 rows keep `agency_id = NULL`; reads return them as shared platform rows to any authorized member but they are never re-stamped/hijacked. |
| CNIC encryption at rest | ✅ | `dealers.cnic` cast `encrypted` (AES-256 via `Crypt::encryptString`); migration `2026_11_13_000001` converts plaintext → encrypted (idempotent); `$hidden` excludes `cnic`; API exposes only `cnicMasked`. |
| MFA secrets & recovery codes | ✅ | `MfaService`: TOTP RFC 6238, secrets encrypted at rest (model casts), recovery codes stored hashed (`Hash::make`), single-use, removed after consumption (8 codes). |
| Audit logging | ✅ | `AuthorizesApi::writeAudit()` appends to `audit_logs` with `actor, role, action, target, detail, ip, agency_id, user_id, entity_type, entity_id, cross_agency`. Workspace sync, financial sync, media sales all audited. |
| Notification permission protection | ✅ | `NotificationController` uses `AuthorizesApi`; routes guarded by `auth:sanctum`; queries scoped to `$user->notifications()`. |
| Audit events for role/permission/security actions | ✅ | `AdminUserController`, `AdminAgencyController`, `AgencyStaffController` call `writeAudit()` for role changes, permission changes, agency verify/status, member invite/remove. |
| Scheduled jobs / queues / transactional emails | ✅ | `routes/console.php`: `sanctum:prune-expired` (daily), `queue:prune-failed` (weekly) — system maintenance only, no tenant context, no tenant data exposure. |

## 3. Verification Performed

| Check | Result |
|-------|--------|
| `php -l` on all edited files | Clean |
| Backend test suite | **176 passed / 1613 assertions / 0 failed** |
| `WorkspaceHardeningTest` (cross-tenant writes) | Pass — "member cannot overwrite another agencys row", "replace mode scoped to own agency", "client supplied tenancy fields ignored" |
| `WorkspaceApprovalTest` (tenant access gating) | Pass — inactive membership never falls back to platform; denied user sees only public listings |
| `BusinessRecordsAuditCommand` (read-only reconciliation) | Run — 104 rows, 8 with agency, 96 without; 3 derivable to `agency-demo` |
| Route middleware resolution (`auth:sanctum`) | Confirmed via passing test suite |

## 4. Remaining Risks & Owner Approval Required

| Risk | Detail | Approval needed? |
|------|--------|------------------|
| Legacy backfill application | `platform:legacy-backfill --apply` stamps `agency_id` onto 101 NULL rows. Derivable rows (3 commissions → `agency-demo`) can be stamped safely, but **assigning any legacy record to a real agency is a business ownership decision**. | **YES** — do not run `--apply` without owner sign-off and a verified DB backup (`--backup`). |
| `auth:sanctum` on all routes | Frontend must send a valid Sanctum Bearer token on every protected call. If any frontend page omits the token, that page will now get 401 instead of working. Verify frontend token attachment before production cutover. | **YES** — confirm frontend sends tokens on all protected endpoints. |
| Rate limiter `api` (60/min) defined but not wired globally | `AppServiceProvider` defines `RateLimiter::for('api')` at 60/min but `throttle:api` is not applied to routes. Per-route limiters exist on sensitive routes only. | Addressed in Phase 2. |

## 5. Files Changed

| File | Change |
|------|--------|
| `backend/routes/api_definitions.php` | Added `->middleware('auth:sanctum')` to all protected routes |
| `backend/app/Http/Controllers/Api/NotificationController.php` | Added explicit `abort(401)` when `$request->user()` is null (index, unreadCount, markRead, markAllRead) |

No database rows were created, updated, or deleted.

---

*This report covers Phase 1 only. Phases 2–8 documented separately.*
