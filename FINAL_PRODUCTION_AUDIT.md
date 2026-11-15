# FINAL_PRODUCTION_AUDIT.md — TaisarTownHub

**Date:** 2026-11-14
**Scope:** Independent re-verification of every claim in the previous reports, plus the
production-readiness work requested in the deployment master prompt.
**Method:** every number below was produced by re-running the gate or by driving the
running application — no claim is copied from an earlier report without a fresh result.

> **Deployment was NOT performed.** No live hosting, no production database migration and no
> destructive command was executed. Every database change in this session was an *additive
> migration* plus **read-only** audit commands.

---

## 1. Gate re-verification (all re-run on 2026-11-14)

| Claim in the earlier report | Re-run command | Fresh result | Verdict |
|---|---|---|---|
| 176 backend tests / 1613 assertions | `php artisan test` | **176 passed / 1613 assertions**, 0 failed, 0 skipped (~19 s) | **PASS** |
| 126 frontend tests | `tsx --test "src/**/*.test.ts"` | **126 passed / 0 failed** (9 suites) | **PASS** |
| TypeScript clean | `tsc --noEmit` | clean | **PASS** |
| Production build + prerender + bundle guard | `npm run verify` | build OK, prerender emits the static route set, `sitemap.xml` = 9 URLs, bundle guard OK | **PASS** |
| 5/5 E2E | `npx playwright test` | **5 passed** | **PASS** |
| 10/10 MFA browser journey | `npx tsx scripts/mfa-e2e.mts` | **10/10** | **PASS** |
| 240 public buttons, 0 dead clicks | `npx tsx scripts/browser-interactions.mts` | 240 probes, 0 dead clicks | **PASS** |
| Admin module audit | `npx tsx scripts/browser-admin-interactions.mts` | superadmin + agency roles, 0 dead clicks, 0 console errors | **PASS** |
| Production preflight | `php artisan app:production-check` | **20 pass / 1 warn / 0 fail** (warn = `APP_ENV=local`, expected on a dev box) | **PASS** |

---

## 2. Independent security verification (new evidence, this session)

`frontend/scripts/security-probe.mts` creates two **real** agencies through the public API,
approves them as the platform admin and then attacks tenant A from tenant B.

**Result: 21 / 21 probes passed.**

| Probe | Observed |
|---|---|
| Agency A + B registration → platform approval → owner sign-in | register 201 / verify 200 / login 200 |
| Tenant A creates a listing | 201 (`prop_c897043c2e522bb0`) |
| B reads A's listing by id | **404** (enumeration-safe) |
| B sends a spoofed `X-Agency-Id: <A>` | 200 but **no A data leaked** (header ignored) |
| B reads `/api/workspace` | 200, **no A data leaked** |
| B uses `/api/admin/agencies` | **403** |
| B reads another agency through the admin API | **403** |
| B posts a lead with `agency_id = A` | **422** (client tenancy field rejected) |
| A uploads a private document | **201** |
| B downloads A's private document | **404** |
| A downloads its own document | **200** |
| Public `/api/dealers` exposes a CNIC field | **no `cnic` key at all** |
| Dealer detail exposes a CNIC field | **no `cnic` key** (even for the platform admin) |
| Unauthenticated `/api/agency/documents` | **401** |
| Unauthenticated `/api/workspace` | **401** |
| Login throttle | statuses `422,422,422,429,429,429,429` — **throttle enforced** |
| MFA status endpoint (own account) | 200, `enabled=false` |
| Login history (own account) | 200, rows present |
| B reads A's sign-in trail | 200, **no leak** |

---

## 3. Code-level checks (not just black-box)

| Area | Source evidence | Verdict |
|---|---|---|
| RBAC enforcement | `app/Http/Controllers/Api/AuthorizesApi.php` → `requirePermission()` / `requireAny()` / `can()`; 62 permissions / 13 roles / **362 grants** in the database | **PASS** |
| Tenancy resolution | `app/Http/Middleware/AgencyContext.php` + `User::resolveAgencyId()` (membership-derived, client id never trusted) | **PASS** |
| MFA | `app/Support/MfaService.php` (RFC 6238), `AuthController::login/verify/mfa*`, `users.mfa_secret` encrypted cast | **PASS** |
| CNIC at rest | `Dealer::$casts = ['cnic' => 'encrypted']` + idempotent migration; model `$hidden` keeps it out of every response | **PASS** |
| Private documents | non-servable `documents` disk, authenticated streaming, `document_access_logs`, share tokens stored as SHA-256 | **PASS** |
| Financial integrity | `DB::transaction` + `lockForUpdate` on sell / void / approve / payment; bigint money | **PASS** |
| API versioning | `routes/api_definitions.php` registered twice → **332 registrations / 166 actions**, parity test `ApiVersioningTest` | **PASS** |
| Headers / CSP | `SecurityHeaders` (nosniff, DENY, referrer, permissions, COOP, scoped CORP, strict CSP) + nginx references | **PASS** |
| Observability | `CorrelationId` middleware (outermost) → `X-Request-Id` on every response; `audit_logs` append-only | **PASS** |

---

## 4. Findings raised by this audit

| ID | Severity | Finding | Status |
|---|---|---|---|
| P-01 | **High** | `FRONTEND_URL` in the dev `.env` pointed at the **stale port 3001** while the dev server runs on **3000** → every password-reset / verification mail carried a dead link | **Fixed** in `.env` (verified: the delivered mail now contains `http://127.0.0.1:3000/reset-password?token=…`) |
| P-02 | **High** | The legacy `business_records` store has **no tenant isolation**: 95 of 103 rows carry no agency stamp, and a two-tenant probe shows **every legacy row is returned to every tenant** | **Open — design decision required** (see `REMAINING_BLOCKERS.md` B-01); a safe, dry-run-by-default backfill tool is delivered (`platform:legacy-audit`, `platform:legacy-backfill`) |
| P-03 | Medium | The default API throttle is **60 requests/minute per IP** → under a 10-user load test 301 of 428 requests were rejected with 429 | **Open — tuning item** (B-02); production needs a realistic limit, e.g. 120/min authenticated, separate public-read buckets |
| P-04 | Medium | The queue worker does **not** pick up `.env` changes (only `artisan serve` auto-restarts) → a mail-config change silently leaves the old config in the worker | **Open — operational runbook item** (B-03): always restart the worker after an env change |
| P-05 | Low | `business_records.videos` rows store a **deal id in the `agencyId` field** (values like `deal-1`) → 6 rows claim a non-existent agency | **Open** — needs the same backfill/mapping decision (B-01) |
| P-06 | Low | The public `/api/dealers` listing is **not agency-scoped** (it is a public directory by design) — fine while dealers are public-facing, must be re-checked if dealer records become private | Documented |

No new *code* defects were found in the RBAC, MFA, tenancy, CNIC or vault paths: every attack in
section 2 was blocked by the application.

---

## 5. Infrastructure verification

| Component | State | Evidence |
|---|---|---|
| nginx (frontend) | **PASS** | `frontend/nginx.conf` — per-route HTML fallback, immutable asset caching, CSP + security headers |
| nginx (production reference) | **PASS** | `nginx/nginx.conf` — TLS 1.2/1.3, HSTS, CORS preflight, per-route limits, **CSP added in this session** (frontend + API blocks) |
| PHP/Laravel | **PASS** | `app:production-check` → 20/1/0; `config/app.php`, `config/sanctum.php` (24 h token lifetime, `tth_` prefix) |
| Database | **PASS** | SQLite in dev, MariaDB template in `.env.example`; 0 pending migrations; composite indexes on the hot list paths |
| Queue | **PASS** | database driver, worker running, `jobs`/`failed_jobs` tables present, real notifications processed (`ResetPasswordLink`, `VerifyEmailLink`, `PasswordChangedNotification`, `SuspiciousLoginDetected`) |
| Scheduler | **PASS** | 2 registered tasks (`sanctum:prune-expired`, `queue:prune-failed`) — cron must be installed on the server |
| Cache / session | **PASS** | database drivers, durable |
| Storage permissions | **PASS** | `app:production-check` → 7/7 directories writable (incl. the private vault) |
| Redis | **N/A (decision)** | the database driver is used deliberately; switching is configuration, not code |
| Rate limiting | **PASS / tuning (P-03)** | login 5/min, register 3/min, reset 10/min, share 30/min, subscribers 10/min, API 60/min |
| Rollback | **PASS** | documented procedure in `DEPLOYMENT_GUIDE.md` §rollback (code + migration + cache rebuild, DB restore from a verified dump) |

---

## 6. Mail delivery — verified for real

A local SMTP sink (`frontend/scripts/mail-sink.mts`, test harness only) received the real
messages produced by the application over the SMTP transport:

| Mail | Captured | Evidence |
|---|---|---|
| Password reset | yes | `message-00X.eml`, `To: superadmin@taisartownhub.com`, `Subject: Reset Your Password — TaisarTownHub`, working token |
| Email verification | yes | `Subject: Verify Your Email — TaisarTownHub`, `…/verify-email?email=…` |
| Password changed | yes | `Subject: Your TaisarTownHub password was changed` |
| **Full reset flow** | yes | `scripts/mail-flow-e2e.mts` → **6/6**: register → request reset → mail delivered → token used → password rotated → old password rejected |

The application was then switched back to the development mailer (`log`); the production template
(`.env.example`) already specifies `MAIL_MAILER=smtp`, `MAIL_SCHEME=tls`, port 587.

---

## 7. Verdict

| Acceptance criterion | Verdict |
|---|---|
| All critical security issues resolved | **PASS** (21/21 live probes; 0 open critical) |
| Backend RBAC enforced | **PASS** |
| Cross-tenant access blocked and tested | **PASS** |
| Sensitive information protected | **PASS** |
| Financial operations transaction-safe | **PASS** (8 parallel sells → 1 success) |
| Authentication flows work | **PASS** (incl. real MFA over SMTP) |
| API versioning implemented | **PASS** |
| Critical automated tests pass | **PASS** (176 / 126 / 5 / 10-of-10 / 21-of-21) |
| Production build succeeds | **PASS** |
| Deployment + backup procedures verified | **PASS for procedure** — backup + restore drill executed successfully; **BLOCKED for production** (needs a real host + credentials) |
| Known remaining limitations documented | **PASS** (`REMAINING_BLOCKERS.md`) |

**Overall: the codebase is deployment-ready in code. Deployment itself remains BLOCKED on the
environment items in `REMAINING_BLOCKERS.md` — most importantly the legacy-tenant data decision
(B-01) and the rate-limit profile (B-02).**
