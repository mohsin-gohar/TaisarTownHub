# SECURITY_VERIFICATION_REPORT.md — TaisarTownHub

**Date:** 2026-11-14 · **Scope:** RBAC, MFA, tenant isolation, CNIC encryption, private document
access, secret leakage, rate limiting, concurrency. Every verdict below is backed by a re-run
command or a live probe — the code was inspected, not trusted.

---

## 1. Method

| Layer | Tool | What it proves |
|---|---|---|
| Unit/feature | `php artisan test` (176 tests / 1613 assertions) | The invariants are asserted in code |
| Live attack | `frontend/scripts/security-probe.mts` | Two real tenants attack each other over HTTP |
| Concurrency | `frontend/scripts/concurrency-booking.mts` | Parallel mutation of one record |
| Mail/identity | `frontend/scripts/mail-flow-e2e.mts` | Password reset + rotation over real SMTP |
| Browser | `browser-audit.mts`, `browser-interactions.mts`, `browser-admin-interactions.mts`, `mfa-e2e.mts` | UI-level reachability of every control |

---

## 2. Results

### 2.1 Tenant isolation — **PASS (21/21 live probes)**

| Attack | Expected | Observed |
|---|---|---|
| Tenant B reads tenant A's listing by id | 404 | **404** |
| Tenant B spoofs `X-Agency-Id: A` | ignored | 200, **no A data in the payload** |
| Tenant B pulls `/api/workspace` | no cross-tenant rows | 200, **no A data** |
| Tenant B uses `/api/admin/agencies` | 403 | **403** |
| Tenant B reads agency A via the admin API | 403/404 | **403** |
| Tenant B posts a lead with `agency_id = A` | rejected | **422** (client tenancy field refused) |
| Tenant B downloads tenant A's vault document | 404 | **404** |
| Tenant A downloads its own document | 200 | **200** |
| Tenant B reads tenant A's sign-in trail | no leak | 200, **no leak** |

### 2.2 RBAC — **PASS**

- 62 permissions, 13 roles, **362 grants** (`permissions` / `roles` / `role_permissions`).
- Enforcement is server-side in `AuthorizesApi` (`requirePermission`, `requireAny`, `can`).
  Frontend checks are visibility only.
- Deny-by-default verified by the 403 probes above and by
  `AgencyContextTest`, `WorkspaceHardeningTest`, `CrossAgencyCapabilitiesTest`.
- Matrix (role → grants) is published in `RBAC_MATRIX.md`, generated from the live database.

### 2.3 MFA — **PASS (10/10 browser journey, 9 backend tests)**

| Property | Evidence |
|---|---|
| No token before the second factor | `POST /api/auth/login` → `{mfa_required, challenge, expires_in}`, **no token**; `MfaTest::test_login_with_mfa_returns_a_challenge_and_no_token` |
| Real RFC 6238 verification | `App\Support\MfaService` (HMAC-SHA1, ±1 step drift), proven by a browser journey that computes the code independently |
| Challenge is single-use and expires | `MfaTest::test_challenge_is_single_use_and_expires` (replay → 401, stale → 401) |
| Wrong code never issues a token | `MfaTest::test_wrong_code_is_rejected_and_no_token_is_issued`; browser journey: 422 + visible error |
| Recovery codes are single-use and hashed | `MfaTest::test_recovery_code_works_once_and_is_stored_hashed` |
| Removal requires password **and** code | `MfaTest::test_disabling_mfa_requires_password_and_code` |
| Secret encrypted at rest | `users.mfa_secret` cast `encrypted`; the raw column was compared in the test and differs |
| Brute-force cost | 5 attempts per challenge, challenge dies, endpoint throttled 10/min |

### 2.4 CNIC & sensitive data — **PASS**

| Check | Result |
|---|---|
| Public `/api/dealers` payload contains a `cnic` key | **No** (the model hides it) |
| Dealer detail contains a `cnic` key | **No** — not even for the platform admin |
| CNIC encrypted at rest | `Dealer::$casts['cnic'] = 'encrypted'` + idempotent migration (`EncryptionAtRestTest`) |
| Buyer CNIC on file handover | stored masked (`42101****5671`), raw never returned |
| Private files | non-servable disk, authenticated streaming, access log, SHA-256 share tokens with expiry/revocation/download-cap |
| Secrets in the public bundle | **None** (`check:bundle` OK; no demo credentials, no private ERP fields) |
| Logs | no tokens/CNICs/MFA codes are written; `X-Request-Id` carries only identifiers |

### 2.5 Authentication hardening — **PASS**

Login throttle 5/min (observed `422,422,422,429,429,429,429`), per-account lockout with expiry,
reset revokes every session, verification tokens are SHA-256 hashed with a 60-minute window,
password changes notify the owner (mail + in-app) and revoke other sessions, and every attempt is
written to `login_histories` with suspicious IP/device detection.

### 2.6 Concurrency & financial integrity — **PASS**

8 parallel sell requests for one dealer file: **1 × 200, 7 × 400 ("This file has already been
sold.")** — the `lockForUpdate()` + status re-check held. The backend suite adds
`BookingConcurrencyTest` (5), `FinanceLifecycleTest` (9) and `PlanLimitEnforcementTest` (5).

### 2.7 Headers, CSP & transport — **PASS**

`nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`,
scoped `Cross-Origin-Resource-Policy` (public media cross-origin, everything else same-origin),
`Strict-Transport-Security` on HTTPS, and a **strict Content-Security Policy** on the API
(`default-src 'none'; script-src 'none'; frame-ancestors 'none'`) plus the frontend nginx
(`script-src 'self'` — the inline service-worker script was moved into the bundle).
`SecurityHeadersTest` asserts all of it (4 tests / 20 assertions).

### 2.8 Injection, IDOR, XSS, CSRF

| Class | Control | Evidence |
|---|---|---|
| SQL injection | parameterised queries + `escapeLike()` on every search term | `WorkspaceHardeningTest`, live search probes |
| IDOR | record lookups are agency-scoped and return 404 | foreign-id probes in §2.1 |
| XSS | `strip_tags()` on user-supplied rich text; React escaping | `strip_tags` in Auth/Agency/Support controllers |
| CSRF | stateless Sanctum bearer tokens (no cookie session on the API) | `auth:sanctum` middleware |
| Privilege escalation | tenancy resolved from membership, never from input | `AgencyContextTest`, spoofed-header probe |
| Rate limiting | per-route throttles + per-account lockout | §2.5 |
| Invalid/expired tokens | 401 (bad token), 410 (expired/revoked share) | `e2e/smoke.spec.ts`, share tests |

---

## 3. Open security-relevant items

| ID | Item | Severity | Status |
|---|---|---|---|
| S-01 | Legacy `business_records` rows are **not tenant-scoped** (95 shared rows visible to every tenant) | **High** | Open — needs the mapping decision in `REMAINING_BLOCKERS.md` B-01; the safe tooling (`platform:legacy-audit`, `platform:legacy-backfill`) is delivered |
| S-02 | API rate limit (60/min) is low for production traffic | Medium | Open — B-02 (tuning, needs approval) |
| S-03 | Malware scanning of uploads | Medium | Not implemented (no scanner available); MIME/extension/size limits are enforced — B-05 |
| S-04 | SMS/WhatsApp channels | Low | Not implemented (no provider) — B-06 |
| S-05 | Worker does not reload `.env` automatically | Low | Documented runbook action (restart the worker after env changes) — P-04 |

No **unmitigated critical** security finding remains; S-01 is a data-ownership decision that
must be taken before multi-tenant production data is loaded.
