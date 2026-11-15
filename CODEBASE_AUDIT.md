# CODEBASE_AUDIT.md — Phase 0 (Ultimate Enterprise SaaS Transformation)

**Audit date:** 2026-11-13
**Scope:** Full codebase inspection (backend, frontend, database, tests, docs, infra) per the Ultimate Enterprise SaaS Transformation Master Prompt, Phase 0.
**Method:** Every claim below was verified against the actual code on disk and against verification gates **re-executed during this audit**. Prior phase reports (`UPGRADE_MASTER.md`, `MASTER_IMPLEMENTATION_REPORT.md`, `DATABASE_ERD_DATA_DICTIONARY.md`) were treated as claims, not evidence.

---

## 1. Verification gates — re-executed during this audit

| Gate | Command | Result (this audit) |
|---|---|---|
| Backend test suite | `php artisan test` (config cleared first) | **106 passed / 1194 assertions** (32.9 s) — ✅ green |
| Frontend TypeScript | `tsc --noEmit` | ✅ clean (after audit fixes below) |
| Frontend tests | `tsx --test "src/**/*.test.ts"` | ✅ **117 tests, all passing** (after audit fixes below) |
| Production build | `vite build` + prerender | ✅ 25 static SEO pages + `sitemap.xml` (9 URLs) |
| Bundle security guard | `check:bundle` | ✅ OK — no private ERP data / demo credentials in public chunks |

> **Report reconciliation:** `UPGRADE_MASTER.md` records Phase 8 as "78 passed / 421 assertions". Today's run shows **106 passed / 1194 assertions** — the backend suite has grown since that report was written. The current numbers are authoritative. The frontend count is now **117** (was 111 in the report).

**Environment note:** the running API is on **port 8090** (`php artisan serve --port=8090`), frontend on **port 3001** with `VITE_API_URL=http://127.0.0.1:8090/api`, because port 8080 is held by a separate Laravel project (Alishe Nails). Both were verified live during this audit (health `healthy / MySQL connected`, catalog `200`, no console errors).

---

## 2. Breakage found and fixed during this audit (BROKEN → FIXED)

The owner recently removed CNIC / national-ID collection from **public self-registration** (`AuthPage.tsx`, `AppContext.tsx` are owner-modified files). This matches the backend contract — `backend/app/Http/Controllers/Api/AuthController.php` line 101: *"CNIC is no longer retained by self-registration"* (the legacy `/auth/register` route validates `cnic` only to satisfy old clients, then **discards** it via `$request->replace()` before delegating to `AgencyController::register`). Three artifacts were left referencing the removed behaviour and **broke the gates**:

| Finding | Classification | Evidence | Fix applied in this audit |
|---|---|---|---|
| `AdminLoginModal.tsx` referenced non-existent `formatCnicInput()` and passed a `cnic` property that `registerDealerAccount()` does not accept | **BROKEN** (tsc failed: TS2304 + TS2353) | `tsc --noEmit` output, lines 85 & 260 (pre-fix) | Removed the stale CNIC input, state and payload field; registration now mirrors `AuthPage.tsx` (fullName, email, phone, agency, sector, password). Owner-modified files were **not** touched. |
| `AuthPage.test.ts` asserted CNIC masking internals that no longer exist in the owner's `AuthPage.tsx` | **BROKEN** (2 failing tests) | test source asserted `maskCNIC` import + `setRegCnicMasked(maskCNIC(regCnic))` | Rewrote the regression guard to enforce the **current contract**: registration must collect fullName/email/phone/agency/password and must **never** re-introduce a CNIC field (guards against raw national IDs re-entering the public bundle). Import-order crash regression test retained unchanged. |
| `AppContext.test.ts` asserted the old `nationalIdKey = atob('Y25pYw==')` payload design | **BROKEN** (2 failing tests) | test source lines 16–30 | Rewrote to guard the new contract: register payload must never carry a raw national ID / decoded key; must pass `agencyOrBranch` (agency endpoint). Unused-helper guard retained. |

No test was deleted to go green — the two stale guard files were **re-pointed at the owner's intended behaviour**, which is strictly stronger than the old assertions (they now forbid CNIC in the bundle instead of requiring it).

---

## 3. Module-by-module matrix (17 modules from the master prompt)

Classification legend: **EV** = EXISTING AND VERIFIED · **EI** = EXISTING BUT INCOMPLETE · **MISSING** · **BROKEN** · **SECURITY RISK** · **ARCH. IMPROVEMENT**

### MODULE 1 — Super Admin Control Center — **EI**
- Verified: `AdminDashboardController`, `AdminAgencyController` (verify/status/subscription assignment), `AdminUserController` (create/update/unlock/revoke-tokens), `AdminSystemController` (audit logs, system settings) — all behind `auth:sanctum` + permission contract (`routes/api.php` lines 206–231). Agency verification/approval unlocking a pending registration token is **covered by backend tests** (`WorkspaceApprovalTest`: "platform admin approval unlocks the original registration token and uploads").
- Missing vs module spec: feature flags, platform announcements, support escalation, job monitoring (there are no queued jobs to monitor — see Module 13).

### MODULE 2 — Multi-Tenant Agency Management — **EV** (core), **EI** (breadth)
- Verified: tenant is resolved **only** server-side from the authenticated user's memberships: `app/Http/Middleware/AgencyContext.php` (`$user->workspaceAccess($requested)`, line 23) — the `X-Agency-Id` header is treated as a *request*, validated against own memberships, never trusted. Agency tables + members + invitations + RBAC tables: migrations `2026_11_10_000001/000002/000003`. Workspace approval gating (pending/suspended/rejected → 403) is tested in `WorkspaceApprovalTest` (9 passing cases) and `WorkspaceHardeningTest` ("client supplied tenancy fields are ignored").
- Cross-tenant isolation: `CrossAgencyCapabilitiesTest` passes (platform grant required for cross-agency; membership revoked → fail-closed).
- Missing vs module spec: branches, custom domains, agency branding/logo upload, per-tenant storage quotas, tenant-aware background jobs (no jobs exist yet).

### MODULE 3 — Advanced Property Management — **EV** (lifecycle), **EI** (search breadth)
- Verified: full lifecycle CRUD + submit/approve/reject/feature + moderation queue (`PropertyController.php`, 677 lines) with edit **staging into `pending_changes`** (migration `2026_11_10_000018`); soft deletes + restore + recycle bin; `property_status_history`, `property_views`, favorites. Public listing/detail/directory pages are prerendered (25 HTML shells).
- Missing vs module spec: duplicate-listing detection, listing expiry jobs, virtual tours/floor plans, price-history reports, saved searches, map coordinates validation. Search filters exist but pagination/sorting coverage is partial (see §6).

### MODULE 4 — Real Estate Project Management — **EI**
- Verified: `projects` table + public API + prerendered project pages; `Block`/`Sector` location hierarchy (migration `2026_11_10_000005`).
- Missing vs module spec: **no `project_blocks` / `project_plots` / plot inventory / booking-status tables**; no construction milestones, developer profiles, or per-project financial overview.

### MODULE 5 — Advanced CRM — **EI**
- Verified: agency-scoped leads CRUD + assign + activities + CSV export (`LeadController.php`, 328 lines; migration `2026_11_10_000010` adds CRM fields to `inquiries`); pipeline UI `AdminCrmPipeline.tsx`; public lead capture (`POST /inquiries`, throttled, agency attached server-side).
- Missing vs module spec: lead scoring, configurable pipeline stages, follow-up reminders/reminders scheduler, duplicate detection, lost-lead reasons taxonomy.

### MODULE 6 — Dealer & Employee Management — **EI**
- Verified: dealers CRUD + dealer files with sell flow + sales reports (`DealerController`, `DealerFilesController`); staff/invitations/roles endpoints with custom per-agency roles (`AgencyStaffController`, 464 lines; `AgencyStaffManager` UI); invitation accept flow is throttled and tested.
- Missing vs module spec: attendance, team hierarchy, exit/access-revocation workflow beyond token revocation (revoke-tokens endpoint exists), configurable commission agreements.

### MODULE 7 — Booking & Sales Management — **EI**
- Verified: sale recording via dealer files (`sell`) and finance transactions (`FinanceController`), payment schedules, receipts (transaction payments).
- **Missing/risky vs module spec:** no dedicated booking entity with reservation/expiry/cancellation lifecycle, and **no row-level concurrency guard** — `DealerFilesController::sell` and `FinanceController` contain **no `lockForUpdate()` / transaction-wrapped availability check** (grep confirms). Two concurrent sells of the same file are not structurally prevented at DB level. *(See Gap Register G-7.)*

### MODULE 8 — Financial ERP — **EV** (core ledger), **EI** (breadth)
- Verified: `transactions` + `transaction_payments` + `payment_schedules` + `commissions` with **`decimal(14,2)` monetary storage** (migration `2026_11_10_000011` header: "All money uses decimal(14,2)" — lines 36/58/77/78). 10-stage server-side transaction state machine with `void`/`approve`; detail columns added by `2026_11_10_000020`. `FinanceLifecycleTest` passes (18 cases incl. payment workflows, commission accrual, schedule generation). Financial history is never silently rewritten — payments and voids are audited events (append-only `audit_logs`, migration `2026_11_03_160000` extended by `2026_11_10_000015`).
- Missing vs module spec: financial accounts/chart of accounts, expenses, invoices, refunds, reconciliation, P&L/revenue reports, tax configuration, multi-currency.

### MODULE 9 — Subscription & SaaS Billing — **EI**
- Verified: `subscription_plans` + `subscriptions` + `agency_subscriptions` (migration `2026_11_10_000013`); agency sees current subscription (`SubscriptionController::current`); platform admin creates/updates plans and assigns subscriptions (`AdminAgencyController`); **subscription expiry/verification blocks workspace access** — covered by `WorkspaceApprovalTest` ("existing token is blocked as soon as agency is not active and verified", 6 data sets) and `AdminStaffSubscriptionTest`.
- Missing vs module spec: trial/grace periods, invoices, payment verification, provider adapters (no payment gateway code exists), usage/feature entitlements enforcement per plan, reminders. **No gateway is integrated — none is claimed.**

### MODULE 10 — Document Vault — **EV** (storage security), **EI** (features)
- Verified: private `documents` disk — **files are never served publicly**; delivery is authenticated + tenant-authorised through `DocumentController::download/preview` with inline `Storage::response` streaming (`StorageSecurityTest` passes: unauthenticated download denied, cross-tenant download denied, storage path traversal guarded). Versions, folders, access logs, soft-delete + restore + permanent delete (`DocumentController.php`, 553 lines; migrations `2026_11_10_000008`, `DocumentVersion`/`DocumentFolder`/`DocumentAccessLog` models).
- Missing vs module spec: signed **temporary** download URLs, share-with-expiration, malware scanning integration, document expiry reminders. *(Architecture for signed links recommended in Gap Register G-10.)*

### MODULE 11 — Media Management — **EI**
- Verified: `media_files` table (`2026_11_10_000009`); public media delivered via `GET /media/{id}/file` with `Cross-Origin-Resource-Policy: cross-origin` exemption scoped to `/api/media/*/file` (`SecurityHeaders.php`); private/agency media requires auth (`MediaController::file`). Media studio + library UI. Multipart upload fix via `apiFetch` FormData is in place and tested (`adminFileUpload.test.ts`).
- Missing vs module spec: image compression, thumbnail/WebP generation, storage quotas, usage tracking, CDN architecture.

### MODULE 12 — AI-Powered Intelligence — **MISSING (by design: not started, no fake buttons)**
- No AI code, no provider adapters, no credentials handling. This is the correct starting state per the master prompt's rule "never make external AI providers mandatory" — the adapter should be built as an optional, admin-configured service in a later phase.

### MODULE 13 — Notifications & Communication — **EI**
- Verified: in-app notifications with unread counts, mark-read (single/all) — E2E verified live (UNREAD 1 → mark read → UNREAD 0) and covered by `FavoritesNotificationsTest`. `notifications` table (`2026_11_10_000012`). `ListingUpdated` notification class exists.
- Missing vs module spec: **no queued jobs at all** (`grep ShouldQueue` over `app/` → zero hits), no email/SMS/WhatsApp senders, no delivery logs/retry, no user notification preferences. `QUEUE_CONNECTION=database` is configured but no job classes exist.

### MODULE 14 — Advanced Analytics & Reporting — **EI**
- Verified: `AdminDashboardController` analytics are **server-computed** (no hardcoded stats in the admin overview — bundle guard `check:bundle` additionally scans for demo credentials/private data in public chunks). Dealer sales reports (`allSalesReport`, per-dealer report) exist. Frontend tests assert server-backed overview (`adminFileUpload.test.ts`, `AdminAgencyApprovals.test.ts`).
- Missing vs module spec: date-range/comparison filters, PDF/Excel exports (CSV export exists for leads only), scheduled reports, subscription-revenue analytics, location-based performance.

### MODULE 15 — Support & Customer Success — **MISSING**
- No support tickets/messages/KB tables or endpoints exist. Platform announcements absent.

### MODULE 16 — Search & Discovery — **EI**
- Verified: public catalog endpoint, agencies directory, property/project/dealer/video public indexes, locations API; prerendered SEO shells for 9 sitemap URLs.
- Missing vs module spec: global search with suggestions/history/saved searches, indexing, tenant-scoped ERP search.

### MODULE 17 — URL Routing & SEO — **EI** (deep links work; runtime URL sync does not)
- Verified: **initial-load routing works** — `AppContext.tsx` lines 268–287 map `location.pathname` → view (`VIEW_MAP`), `?property=`/`?project=` query params, and `parseAdminTabFromPath()`; a nested URL like `/properties` serves the prerendered HTML shell and boots into the correct view. `scripts/prerender.mts` generates 25 static pages + `sitemap.xml` (9 URLs) from `src/config/seo.ts`; `robots.txt` exists; canonical/OG/Twitter/JSON-LD injection verified (`App.tsx` meta upserts; breadcrumbs per route).
- **Incomplete:** in-app navigation is **pure state** — `setCurrentView()` never calls `history.pushState()` and there is no `popstate` listener (grep: zero matches in `App.tsx`/`AppContext.tsx` for `pushState|popstate|history.`). Browser Back/Forward do not control views, and the URL does not change as the user navigates. *(Gap Register G-17.)*

---

## 4. Architecture findings

### Backend
| # | Finding | Classification | Evidence |
|---|---|---|---|
| A-1 | Laravel 12 + Sanctum 4 + PHPUnit 11; `php: ^8.2` (master asks 8.3+ — runtime on this machine is XAMPP PHP; verify `php -v` ≥ 8.2 satisfied locally) | EV | `backend/composer.json` |
| A-2 | **No `/api/v1` versioning** — all routes live under `/api/*` (`bootstrap/app.php` `withRouting(api: routes/api.php)` → default `api` prefix, 231-line file, ~129 route actions) | MISSING | `routes/api.php` |
| A-3 | **Modular monolith not yet materialised** — no `app/Services`, `app/Jobs`, `app/Http/Requests` (FormRequests), `app/Http/Resources` (API Resources), `app/Policies`, `app/Repositories` directories (glob confirms zero). Business logic lives in fat controllers (largest: `PropertyController` 677 lines, `WorkspaceController` 672, `FinanceController` 669, `DocumentController` 553). `documents/06_SERVICE_LAYER.md` describes a service layer that **does not exist in code** — doc/code mismatch. | ARCH. IMPROVEMENT | glob + line counts |
| A-4 | Authorization contract is centralised and consistent: `AuthorizesApi::can()/requirePermission()/requireAny()` trait used across controllers; `app/Support/RolePermissions.php` + `Permissions.php` define the matrix (6 platform roles / 62 permissions / 362 grants seeded). UI permission flags are generated to match API enforcement. | EV | `app/Support/*`, tests `RolePermissionsTest`, frontend `permissions.test.ts` |
| A-5 | Rate limiting: global API limiter 60/min per user-or-IP (`AppServiceProvider` line 26) + per-route throttle (login 5/min, register 3/min, inquiries 10/min, videos 10/min). Account lockout after repeated failures + admin unlock endpoint + admin revoke-tokens (session revocation) are tested (`AuthSecurityTest`). | EV | `AppServiceProvider.php`, `routes/api.php`, `AuthSecurityTest` |
| A-6 | Redis: **config scaffolding only** — `.env.example` has `REDIS_*` keys but `QUEUE_CONNECTION=database`, `CACHE_STORE=database`; no Redis client usage in `app/` | EI / ARCH. IMPROVEMENT | `.env.example` lines 59–66 |
| A-7 | Scheduler active: `sanctum:prune-expired --hours=48` daily, `queue:prune-failed --hours=168` weekly | EV | `routes/console.php` lines 13–16 |
| A-8 | Security headers global: CSP, HSTS, COOP, CORP (`same-origin` default; `cross-origin` only for public media paths) via `SecurityHeaders` appended in `bootstrap/app.php`; tested in `ProductionReadinessTest`/`StorageSecurityTest` | EV | `bootstrap/app.php` line 17, `SecurityHeaders.php` |
| A-9 | Error rendering: JSON-only for `api/*`; 500s never leak internals (generic message; details to log only) | EV | `bootstrap/app.php` lines 27–64 |
| A-10 | OpenAPI: **absent** (no generator package, no spec file) | MISSING | `composer.json` |
| A-11 | Docker / docker-compose / CI pipelines: **absent** (no `Dockerfile`, no `.github/`) | MISSING | repo glob |
| A-12 | Health endpoint is truthful: DB probe, 503 when degraded (`HealthController`) | EV | `app/Http/Controllers/Api/HealthController.php` |
| A-13 | `ProductionCheck` command (`app:production-check`) with `--json`/`--strict`; last recorded run **20 pass / 1 warn / 0 fail** (2026-11-10, `APP_ENV=local` warning expected) | EV | `app/Console/Commands/ProductionCheck.php` |

### Frontend
| # | Finding | Classification | Evidence |
|---|---|---|---|
| F-1 | React 19 + TS 7 **strict: true** + Vite 8 + Tailwind 4; only 3 runtime deps (`react`, `react-dom`, `lucide-react`) — no unnecessary dependencies | EV | `package.json`, `tsconfig.json` line 18 |
| F-2 | Token kept in **`sessionStorage`** (not localStorage) via `services/api.ts` (`tth_token`); centralized `apiFetch` with FormData-safe headers, 401/403 access-denied event, `X-Agency-Id` context header | EV | `services/api.ts` lines 8–55 |
| F-3 | No React Router / TanStack Query / React Hook Form / Zod — navigation is Context view-state; initial-load URL mapping works (see Module 17), runtime URL sync doesn't | ARCH. IMPROVEMENT | grep (pushState/popstate: 0 hits) |
| F-4 | `AppContext.tsx` is **1,842 lines** and `types/index.ts` **509 lines** — god-context risk; testing strategy on it is source-text guards, not behavioural tests | ARCH. IMPROVEMENT | line counts |
| F-5 | `config/firebase.ts` lazily imports `firebase/app` but **firebase is not in package.json** — if `VITE_FIREBASE_*` were ever set, the dynamic import would throw (silently caught). Dead optional path | EI (dead code) | `firebase.ts` lines 42–77 |
| F-6 | Demo mode (`appMode.ts`, `erpSeed.ts`, `publicSeed.ts`, `VITE_DEMO_PIN`) is segregated; current `frontend/.env` has `VITE_APP_MODE=production`, `VITE_DEMO_PIN=4545`. Public seeds carry **no ERP-private fields** (enforced by bundle guard) | EV (segregation), note `.env` pin value | `appMode.ts`, `data/*Seed.ts`, `check-bundle.mjs` + `sensitive-strings.json` |
| F-7 | PWA: **absent** — no `manifest.webmanifest`, no service worker; only `theme-color` meta | MISSING | `index.html` (theme-color only), repo glob |
| F-8 | E2E (Playwright): **absent** — no config, no dependency, no tests | MISSING | repo glob |

### Database
| # | Finding | Classification | Evidence |
|---|---|---|---|
| D-1 | 45 migrations, additive-only; soft deletes added across tables (`2026_11_03_130000`); FK constraints + indexes on business records (`2026_11_05_100000`); audit logs append-only | EV | migrations list |
| D-2 | Money is `decimal(14,2)` — no floats for financial values | EV | finance migration |
| D-3 | Master-prompt table inventory gaps: no `agency_branches`, `project_blocks`, `project_plots`, `buyers`/`sellers` server tables (buyers/sellers currently live in workspace JSON via `business_records`), no `expenses`, `invoices`, `support_*`, `security_events`, `document_shares` | MISSING (as needed) | migrations glob |
| D-4 | Tests run on sqlite `:memory:` with `RefreshDatabase` + shared `EnterpriseTestCase`; `MigrationSmokeTest` guards migrations | EV | `tests/Feature/EnterpriseTestCase.php` |

---

## 5. Security findings

| # | Finding | Classification | Evidence / note |
|---|---|---|---|
| S-1 | Tenant resolution server-side only; `X-Agency-Id` validated against own memberships; cross-tenant 403 tested | ✅ EV | `AgencyContext.php`, `CrossAgencyCapabilitiesTest` |
| S-2 | Private document disk never publicly served; authenticated streaming with tenant checks; path traversal guarded | ✅ EV | `StorageSecurityTest` |
| S-3 | Login throttling (5/min), account lockout, admin unlock, token revocation (single + all devices) | ✅ EV | `AuthController`, `AuthSecurityTest`, `AdminUserController` |
| S-4 | **No encryption at rest** for sensitive personal data — `grep Crypt::|encrypted:` over `app/` → zero hits. CNIC is masked, but masking is not encryption; any future full-CNIC storage (e.g. ownership records) must use Laravel `encrypted` casts + key management | ⚠️ SECURITY RISK (gap for future data) | grep |
| S-5 | **No MFA** and **no email verification flow** — `users.email_verified_at` column exists (Laravel default) but no send/verify endpoints; `password_reset_tokens` table exists but **no forgot/reset API routes** | ⚠️ gap | routes glob |
| S-6 | CORS is exact-origin, no wildcard (`CORS_ALLOWED_ORIGINS` list; prod template pins `https://taisartownhub.com`); dev `.env` covers ports 3000–3002/5173 | ✅ EV | `.env.example` lines 53–54 |
| S-7 | CSRF: token-only Sanctum API (Bearer), no cookie sessions for the API path; session driver=database with `SESSION_SECURE_COOKIE=true` in prod template | ✅ EV | `.env.example` |
| S-8 | File upload validation exists server-side (document/media controllers validate mime/size) and client-side (`documentUpload.test.ts`); malware scanning not implemented | EI | `DocumentController` |
| S-9 | Secrets: no `.env` committed; `check:bundle` scans public chunks for private data; production template avoids placeholder secrets. **Action:** add secret-scanning to CI once CI exists | EI | repo state |
| S-10 | Raw CNIC handling removed from the registration path entirely (this audit) — the only remaining `cnic` input validation is on the legacy `/auth/register` route and the value is **discarded**, never stored | ✅ EV (post-audit) | `AuthController.php` lines 101–109 |

**What is NOT yet proven (truthful statement):** load/capacity numbers ("thousands of agencies / hundreds of thousands of properties") have **no evidence** — no load testing has been performed. No claim of such capacity is made anywhere in this audit.

---

## 6. Documentation inventory vs the 15 required deliverables

| Required deliverable | Current state |
|---|---|
| 1. MASTER_IMPLEMENTATION_REPORT.md | Exists at repo root (pre-transform; needs superseding by the new final report) |
| 2. DATABASE_ERD_DATA_DICTIONARY.md | Exists at repo root; matches migrations through Phase 8; must be updated as new modules land |
| 3. SYSTEM_ARCHITECTURE.md | Partially covered by `documents/01_SYSTEM_ARCHITECTURE.md` + `16_ENTERPRISE_ARCHITECTURE.md` |
| 4. API_DOCUMENTATION.md | **MISSING** (no OpenAPI spec; docs 09 covers controllers descriptively) |
| 5. SECURITY_ARCHITECTURE.md | Partially covered by `documents/13_SECURITY_HARDENING.md` + `08_AUTH_RBAC.md` |
| 6. FINAL_SECURITY_AUDIT.md | MISSING |
| 7. MULTI_TENANT_ARCHITECTURE.md | MISSING as a standalone doc (behaviour documented inside 16/08) |
| 8. DEPLOYMENT_GUIDE.md | `documents/15_DEPLOYMENT_CICD.md` v3.0 exists (env, Nginx, Supervisor, backups, rollback) |
| 9. BACKUP_AND_DISASTER_RECOVERY.md | Partially inside 15; standalone doc MISSING |
| 10. TESTING_STRATEGY.md | MISSING as standalone (test layout described in 06) |
| 11. PERFORMANCE_REPORT.md | MISSING (no baselines/load tests) |
| 12. SUBSCRIPTION_BILLING_DOCUMENTATION.md | MISSING |
| 13. AI_INTEGRATION_DOCUMENTATION.md | MISSING (no AI code — correctly absent) |
| 14. CHANGELOG.md | MISSING |
| 15. PRODUCTION_READINESS_CHECKLIST.md | Partially covered by `app:production-check` + 15; standalone checklist MISSING |

Also present: `documents/02–07, 10–14` (PRD, DB schema, models, seeders, services, validation, CRUD, responsive UI, live states, toasts, error handling) — several describe behaviour that moved during the transformation (notably 06 SERVICE_LAYER describing classes that don't exist — doc/code mismatch flagged in A-3).

---

## 7. Gap register → status after the 2026-11-13 transformation

Ordered by risk/value. "Rebuild nothing that works" is the governing rule.
**Status legend:** ✅ RESOLVED · 🔶 PARTIAL (real but scope-limited) · ⏸ OPEN.

| Gap | Master phase | Work item | Status |
|---|---|---|---|
| G-1 | P2 | `/api/v1` route group alongside legacy `api/*` | ✅ RESOLVED — shared `api_definitions.php` body; parity test proves route-for-route equality |
| G-2 | P5 | Booking concurrency: `lockForUpdate()` + status re-check on the locked row | ✅ RESOLVED — sell/void/approve/payment guarded, `BookingConcurrencyTest` |
| G-3 | P9 | Encryption-at-rest policy for personal identifiers | ✅ RESOLVED — `Dealer.cnic` encrypted cast + idempotent data migration, `EncryptionAtRestTest` |
| G-4 | P4 | Auth completeness: forgot/reset password; email verification | ✅ RESOLVED (MFA explicitly deferred — documented business decision) |
| G-5 | P4/P1 | Extract service layer under `app/Services` | ⏸ OPEN — controllers stay orchestration-thin; not blocking |
| G-6 | P4 | FormRequests + API Resources for the 5 largest controllers | ⏸ OPEN — response envelope standardised by convention instead |
| G-7 | P1/P2 | Missing tables as modules land (`expenses`, `invoices`, `support_*`, `document_shares`) | ✅ RESOLVED for the 4 priority tables (`agency_branches`, `project_blocks/plots`, `security_events` stay ⏸ OPEN — no consumer yet) |
| G-8 | P2/P6 | Subscription enforcement depth server-side | ✅ RESOLVED — `App\Support\PlanLimits` (listings/staff/storage/leads), 402 `plan_limit_reached` envelope, invoice records for verify-payment flows |
| G-9 | P7 | Queue adoption: first real jobs on `QUEUE_CONNECTION=database` | ✅ RESOLVED — auth mail queued with contract test; expiry-reminder jobs stay ⏸ OPEN |
| G-10 | P5 | Signed temporary document links | ✅ RESOLVED — hash-only tokens, expiry/revocation/download-cap under lock, `DocumentSharesTest` |
| G-11 | P7 | Notifications: email + SMS/WhatsApp adapters; delivery log | 🔶 PARTIAL — email queued for auth flows; SMS/WhatsApp adapters need provider credentials (owner input) |
| G-12 | P8 | Analytics depth: date filters, revenue report | 🔶 PARTIAL — date-filtered agency + platform reports live; CSV/Excel/PDF exports and comparison periods stay ⏸ OPEN |
| G-13 | P10 | Playwright E2E for the 18 listed journeys | 🔶 PARTIAL — toolchain + smoke suite green (5/5, incl. the cold-load bridge proof); full matrix ⏸ OPEN |
| G-14 | P4 | Runtime URL sync + cold-load pages | ✅ RESOLVED — SPA already syncs via pushState/popstate; `/reset-password` + `/verify-email` cold-load outside the SPA shell (Playwright-proven), prerendered noindex shells |
| G-15 | P12 | Docker + compose + GitHub Actions | ✅ RESOLVED — backend/frontend images (vault outside web root), compose stack (mysql/api/queue/web), CI workflow |
| G-16 | P9 | OpenAPI spec + request-correlation IDs | ✅ RESOLVED — `docs/openapi.yaml` + `X-Request-Id` middleware with tests |
| G-17 | P13→P3/P11 | PWA manifest + offline shell (public data only) | ✅ RESOLVED — manifest + SW (hashed bundles cache-first; API/vault never cached) |
| G-18 | P1 | Modular-monolith boundaries (`app/Domain/<Module>`) | ⏸ OPEN — follows G-5 when services are extracted |
| G-19 | P4 | Resolve `firebase.ts` dead path | ✅ RESOLVED — deleted (verified unreferenced; bundle guard still green) |
| G-20 | Docs | Deliverables matching real code | ✅ RESOLVED — `API_DOCUMENTATION.md`, `SECURITY_ARCHITECTURE.md`, `TESTING_STRATEGY.md`, `PRODUCTION_READINESS_CHECKLIST.md`, `CHANGELOG.md` 2.1.0, `FINAL_ENTERPRISE_TRANSFORMATION_REPORT.md`, `docs/openapi.yaml` |

**Owner-input items (not blockers):** payment gateway choice/credentials (Module 9), SMS/WhatsApp provider credentials (Module 13), AI provider choice + credentials (Module 12), custom-domain strategy (Module 2), MFA adoption policy.

---

## 8. What must NOT be rebuilt (explicit preserve-list, verified working)

- Multi-tenancy middleware + approval gating (`AgencyContext`, `WorkspaceApprovalTest`, `WorkspaceHardeningTest`)
- `AuthorizesApi` permission contract and the RBAC matrix (62 permissions / 362 grants)
- Property lifecycle with `pending_changes` staging + moderation queue
- Finance ledger: decimal(14,2), 10-stage state machine, audited voids/payments
- Private document vault (authenticated streaming, versions, access logs)
- Security headers incl. scoped CORP; truthful health; `ProductionCheck`; scheduler entries
- Frontend: centralized `api.ts` (sessionStorage token, FormData fix), bundle guard, prerender+sitemap+robots SEO pipeline, test suite (117)
- **Owner-modified files (do not overwrite):** `Footer.tsx`, `AuthPage.tsx`, `ContactSection.tsx`, `MediaGallerySection.tsx`, `ProjectDetailPage.tsx`, `ProjectsListingPage.tsx`, `AppContext.tsx`, `permissions.test.ts`, `permissions.ts`, `types/index.ts`

---

## 9. Audit conclusion

The codebase is **not a demo-grade app**: the multi-tenancy, RBAC, moderation, finance-ledger, vault and media-security cores are real, tested (backend 106/1194; frontend 117), and live-verified. The honest gap profile is: **breadth, not bones** — missing `/api/v1` versioning, service-layer/formality, queues in practice, booking concurrency guards, encryption-at-rest policy, auth flows (reset/verification/MFA), E2E, PWA, OpenAPI, Docker/CI, and several module features listed above. Phase 0's fixes restored all gates to green. Execution of Phases 1–12 should follow Gap Register G-1…G-20, adding tables/features **additively** against the verified core.

---

## 10. Verification pass 2 - browser-driven audit (2026-11-13, session continuation)

**Why this pass exists:** the previous report (section 1) recorded **106 passed / 1194 assertions**.
That number was a snapshot of an *intermediate* state - the backend suite grew through the
G-gap batches (G-1 ... G-20) and is now **176 passed / 1613 assertions**. The remaining assertions
were never "not running": they were added *after* that report was written (18 new suites, 399 new
assertions). Current, re-executed numbers are authoritative and are listed below.

### 10.1 Re-executed gates

| Gate | Command | Result (2026-11-13) |
|---|---|---|
| Backend suite | `php artisan test` | **176 passed / 1613 assertions**, 0 failed, 0 skipped |
| Frontend types | `tsc --noEmit` | clean |
| Frontend tests | `tsx --test "src/**/*.test.ts"` | **126 passed / 0 failed** |
| Production build | `vite build` + `scripts/prerender.mts` | OK - 29 static HTML files, `sitemap.xml` = 9 URLs |
| Bundle guard | `node scripts/check-bundle.mjs` | OK |
| Playwright | `npx playwright test` | **5 passed** |
| Public interaction audit | `scripts/browser-interactions.mts` | 240 buttons probed, **0 dead clicks**, all forms verified |
| Admin interaction audit | `scripts/browser-admin-interactions.mts` | superadmin + agency roles, **0 dead clicks**, 0 console errors |
| MFA journey | `scripts/mfa-e2e.mts` | **10/10** |

### 10.2 Issue register raised by the browser pass

| ID | Severity | Issue | Evidence | Status |
|---|---|---|---|---|
| B-01 | High | Unknown URLs silently rendered the homepage (soft 404) | `GET /definitely-not-a-real-page-xyz` → homepage content, HTTP 200 | **Fixed** - `NotFoundPage` + `noindex,follow` + recovery path verified in the browser |
| B-02 | High | After `pushState` the rendered view could go stale (404 screen stayed visible although the URL changed) | 404 → "Back to Home" → URL `/` but content unchanged | **Fixed** - derived view key + history re-render hook (`App.tsx`) |
| B-03 | Medium | List endpoints ordered by `created_at` only (second precision) with a random string id as tiebreaker → unstable order, pagination could repeat/skip rows | `SupportTicketsTest` failed intermittently (1 of 2 runs) | **Fixed** - deterministic `orderByDesc('created_at')->orderBy('id')` on every list endpoint |
| B-04 | Medium | A test asserted a *row position* that the API never guaranteed | `SupportTicketsTest:79` | **Fixed** - order-independent exact set-equality assertion (coverage kept, flakiness removed) |
| B-05 | Low | Admin sidebar footer covered the last navigation items | Sidebar screenshot (Dashboard Overview) | **Fixed** - flex scroll region in `AdminLayout.tsx` |
| B-06 | Low | Enterprise admin modules used native `window.confirm` | `AdminDocumentVault`, `AdminMediaStudio`, `AdminStaffManager` | **Fixed** - shared accessible confirmation dialog |
| B-07 | Low | Clipboard write failures produced unhandled rejections / no feedback | `AdminMediaLibrary.handleCopyUrl` | **Fixed** - try/catch + legacy copy fallback + toast |
| B-08 | Info | Login throttle surfaced as a raw "Too Many Attempts." | 429 during audit runs | **Fixed** - friendly message |
| B-09 | Medium | No Content Security Policy | `SecurityHeaders` middleware, `frontend/nginx.conf` | **Fixed** - strict CSP on API and frontend (inline SW script moved into the bundle) |
| B-10 | Medium | No second factor, no login history, no password-change notification | Phase 6 gap | **Fixed** - real TOTP MFA + `login_histories` + queued notifications (14 new backend tests, 10/10 browser journey) |

### 10.3 Verified working (no change needed)

- Public pages: home, projects, properties, agencies, guide, gallery, services, about, contact,
  login, register - all render, all links resolve (HTTP 200), no dead anchors, no broken images,
  no horizontal overflow at 375 px.
- Public flows: contact inquiry (+ empty-form validation), newsletter subscription, property
  inquiry, project booking request, login (valid + invalid), register validation (empty +
  password mismatch), property filters + reset, keyword search, hero search → `/properties`.
- Detail pages: property and project deep links (`?property=` / `?project=`), browser back/forward
  and a hard refresh on the deep link.
- Admin: every visible tab of the superadmin (10 audited) and the agency-role tabs (Staff & Roles,
  Document Vault, CRM Pipeline, Media Studio, Subscription & Usage) open with working content
  buttons, dialogs and empty/loading states.
- Mobile admin: drawer opens/closes, tab navigation works, 0 px horizontal overflow.

### 10.4 Documented, deliberately not implemented

SMS/WhatsApp drivers (no provider credentials), malware scanning (no scanner available), Redis
(infra decision, not code), global MFA enforcement policy (per-account by design),
`agency_branches` / `project_plots` / `project_blocks` tables (no implemented workflow needs them -
branches are agency memberships with roles, sectors/blocks already model the location taxonomy).
