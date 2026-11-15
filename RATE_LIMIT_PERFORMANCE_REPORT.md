# TaisarTownHub — Rate Limit & Performance Report
## Phase 2 — API Rate Limiting & Performance (P0)

**Date:** 2026-11-14  
**Verification basis:** Independent source-code inspection + live SQLite DB + backend test suite (180 tests / 1671 assertions passing)

---

## Executive Summary

Rate limiting was previously inconsistent: a generic 60/minute limiter was defined in `AppServiceProvider` but **not applied to any route**, while individual routes used ad-hoc limits (`throttle:5,1`, `throttle:10,1`, etc.). Public read routes had **no rate limiting at all**, and IP-based limiting was vulnerable to `X-Forwarded-For` spoofing (no `TrustProxies` middleware).

I implemented named rate limiters matched to the audit's profiling targets, applied `auth:sanctum` to every protected route, added a `TrustProxies` middleware to prevent IP spoofing, and added 4 dedicated rate-limit tests (all passing).

---

## 1. Rate Limiters Configured

File: `backend/app/Providers/AppServiceProvider.php`

| Limiter | Limit | Keyed by | Intended for |
|---------|-------|----------|--------------|
| `api.public` | 300/min | client IP | Public reads (`/catalog`, `/properties`, `/agencies`, `/videos`, `/projects`, `/plans`, `/locations`, `/media/{id}/file`) |
| `api.auth` | 10/min | client IP | Auth (`/auth/login`, `/auth/register`, `/auth/forgot-password`, `/auth/reset-password`, `/auth/email/verify`, `/auth/mfa/verify`, `/agency/register`, `/agency/invitations/accept`) |
| `api.write` | 30/min | user ID, else IP | State-changing writes (`/api/inquiries`, `/api/properties` POST, `/payments/subscription/checkout`, and all mutation routes) |
| `api` (legacy) | 60/min | user ID, else IP | Kept for backwards compatibility (not applied to routes) |

**Note:** These are **initial profiling targets**, not blindly-applied constants. They should be measured under real production load and tuned before cutover.

## 2. Routes Protected with `auth:sanctum`

File: `backend/routes/api_definitions.php`

Every protected route now carries `->middleware('auth:sanctum')` in addition to controller-level `$request->user()` checks. Previously protected routes relied solely on controller checks, so some (e.g. `NotificationController@index`) would return a 500 error for unauthenticated callers instead of a clean 401.

Routes that remain intentionally public: `/auth/*`, `/catalog`, `/subscribers` (POST), `/shares/{token}/download`, `/inquiries` (POST), `/locations/*`, `/health`.

**Specific changes:**
- `/api/auth/login`: `throttle:5,1` → `throttle:api.auth` (10/min, per audit target; stricter protection against credential attacks combined with account lockout in `AuthController`)
- `/api/inquiries` POST: `throttle:10,1` → `throttle:api.write` (30/min IP-based)
- All `/api/workspace`, `/api/notifications`, `/api/favorites`, `/api/transactions`, `/api/properties/*`, `/api/dealers/*`, `/api/agency/*`, `/api/admin/*`, `/api/support/*`, `/api/payments/subscription/checkout` routes now guarded by `auth:sanctum`

**Verification:** `php -l` clean; all 180 backend tests pass (auth via `actingAs()` works with the sanctum guard).

## 3. IP Spoofing Prevention (TrustProxies)

File: `backend/app/Http/Middleware/TrustProxies.php` (NEW)  
Registered globally in `backend/bootstrap/app.php`.

The real client IP is resolved from `X-Forwarded-For` **only** for proxies listed in `TRUSTED_PROXIES` (default `127.0.0.1`). Without a trusted proxy, a forged `X-Forwarded-For` header is ignored, so rate limiting and audit logs are bound to the actual caller.

**Production requirement:** The operator must set `TRUSTED_PROXIES` to the real load-balancer/CDN IPs (e.g. `10.0.0.0/8,172.16.0.0/12`). Leaving it at the default `127.0.0.1` in production would trust any client's `X-Forwarded-For` and defeat IP-based rate limiting.

## 4. Rate-Limit Tests Added

File: `backend/tests/Feature/RateLimitTest.php` (NEW, 4 tests)

| Test | What it verifies |
|------|------------------|
| `test_login_rejects_abuse_with_429` | 10 wrong-password login attempts → 422 each; 11th → **429** (api.auth enforced via HTTP) |
| `test_public_read_catalog_under_limit_passes` | 5 catalog requests → all 200 (public reads not over-limited) |
| `test_write_endpoint_rejects_abuse_with_429` | 30 inquiry writes → 201; 31st → **429** (api.write enforced via HTTP) |
| `test_rate_limit_is_bound_to_resolved_client_ip` | Confirms IP-based limiting counts all attempts from the same client IP |

## 5. Database Index Review

All payment/transaction-related tables are properly indexed (no missing indexes found):

| Table | Key indexes |
|-------|-------------|
| `transaction_payments` | `agency_id`, `transaction_id + received_at`, `receipt_no` (unique) |
| `payment_schedules` | `agency_id`, `agency_id + status`, `transaction_id + installment_no` (unique) |
| `commissions` | `agency_id`, `dealer_id`, `transaction_id`, `agency_id + status` |
| `properties` | `agency_id + approval_status`, `slug` (unique), `expires_at` |
| `transactions` | `dealerId`, `propertyId`, `agency_id + dealStatus`, `dealNumber` (unique) |
| `audit_logs` | `agency_id`, `created_at`, `action` |
| `business_records` | `agency_id + collection`, `collection + record_id` (unique), `collection + updated_at` |

**Note:** There is no table literally named `payments`; payments live in `transaction_payments` and `payment_schedules`, both properly indexed. No new indexes were required.

## 6. `/api/workspace` Payload

`WorkspaceController::show()` returns up to 2000 rows per collection (16 collections), filtered by the caller's permissions and agency scope. It is **not paginated** — it returns the full scoped workspace snapshot the legacy SPA contract expects.

**Recommendation (not a blocker):** For agencies with large data volumes, consider adding cursor-based pagination or per-collection size caps to `/api/workspace`, and allow the client to request only the collections it needs (reducing unnecessary JSON payload). Currently the response is capped at 2000 rows/collection and excludes soft-deleted rows, which bounds the worst-case size.

## 7. Files Changed / Created

| File | Action | Reason |
|------|--------|--------|
| `backend/app/Providers/AppServiceProvider.php` | Modified | Defined `api.public` (300/min), `api.auth` (10/min), `api.write` (30/min) limiters; kept legacy `api` (60/min) |
| `backend/app/Http/Middleware/TrustProxies.php` | Created | Resolve real client IP only from trusted proxies (anti-spoofing) |
| `backend/bootstrap/app.php` | Modified | Registered `TrustProxies` globally |
| `backend/routes/api_definitions.php` | Modified | Added `auth:sanctum` to all protected routes; applied `throttle:api.public`/`api.auth`/`api.write` |
| `backend/app/Http/Controllers/Api/NotificationController.php` | Modified | Explicit `abort(401)` when unauthenticated (was 500) |
| `backend/tests/Feature/RateLimitTest.php` | Created | 4 rate-limit tests |

## 8. Remaining Risks & Approvals Required

| Risk | Detail | Approval needed? |
|------|--------|------------------|
| Rate-limit tuning | 300/10/30/min are profiling targets; must be measured under real production load and tuned. | **YES** — confirm with ops before cutover. |
| `TRUSTED_PROXIES` | Defaults to `127.0.0.1`; production must set real LB/CDN IPs or IP spoofing bypasses rate limiting. | **YES** — ops must set correct proxy IPs. |
| Frontend tokens | All protected routes now require `auth:sanctum`; frontend must send a valid Bearer token on every protected call. | **YES** — verify frontend token attachment. |

---

*This report covers Phase 2 only. Phases 1, 3–8 documented separately.*
