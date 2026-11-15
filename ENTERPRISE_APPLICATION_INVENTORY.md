# ENTERPRISE_APPLICATION_INVENTORY.md — TaisarTownHub

**Date:** 2026-11-14 · **Method:** built from the live database (`platform:inventory`), the actual
routes file, `App.tsx` and the seeded user table — not from prior reports.

---

## 1. Stack & architecture (as found, preserved)

| Layer | Technology | Notes |
|---|---|---|
| Backend | Laravel 12, PHP 8.x | `backend/`, API served by `artisan serve :8090` in dev |
| Frontend | React 19 + TypeScript + Vite + Tailwind | `frontend/`, dev `:3000`, nginx for production |
| Database | MySQL (dev box, XAMPP) | 53 tables; SQLite backup tooling adapts per `.env` |
| Auth | Sanctum bearer tokens (`tth_` prefix, 24 h) + real TOTP MFA | `auth:sanctum`, deny-by-default RBAC |
| Queue | database driver + worker | notifications: reset, verify, password-changed, suspicious-login |
| Routing model | **view-state** SPA (no react-router paths) | `AppContext.currentView` + `KNOWN_PUBLIC_PATHS` guard → 404 for unknown paths; deep links (`/admin/<tab>`, `/?view=…`) resolved server-side of history |

## 2. Public pages / views (from `App.tsx`)

| View | Component | Aliases in `KNOWN_PUBLIC_PATHS` |
|---|---|---|
| home | Home page sections (hero, featured, stats, testimonials, CTA, footer) | `/`, `/home` |
| projects | `ProjectsListingPage` | `/projects` |
| project-detail | `ProjectDetailPage` | deep link via project id |
| properties | `PropertiesListingPage` | `/properties` |
| property-detail | `PropertyDetailPage` | deep link via property id |
| agencies | `AgenciesSection` | `/agencies` |
| guide | `Scheme45Guide` | `/guide`, `/scheme45-guide` |
| gallery | `MediaGallerySection` | `/gallery`, `/media-gallery` |
| services | `ServicesSection` | `/services` |
| about | `AboutSection` | `/about` |
| contact | `ContactSection` | `/contact` |
| login / register | `AuthPage` (modal tab) | `/login`, `/auth`, `/signin`, `/register` |
| admin-login | `AdminLoginModal` | `/admin-login` |
| admin | `AdminLayout` + tab components (authed) | `/admin`, `/admin/<tab>` |
| reset-password | `ResetPasswordPage` (cold-load, token) | `/reset-password` |
| verify-email | `VerifyEmailPage` (cold-load, token) | `/verify-email` |
| not-found | `NotFoundPage` + noindex | anything else |

Unknown-but-recognised path prefixes: `/admin/…` deep links; everything else → 404 view.

## 3. RBAC (live database, 2026-11-14)

- **62 permissions · 13 roles · 362 grants · 332 API registrations (166 actions × `/api` + `/api/v1`).**

| Role | Scope | Grants | Seeded account |
|---|---|---:|---|
| `super_admin` | platform | 62 | `superadmin@taisartownhub.com` |
| `admin` | platform | 60 | `admin@taisartownhub.com` (also agency_owner of the demo agency) |
| `agency_owner` | agency | 50 | `admin@` (Premier Advisory), `majeedag456@gmail.com` (Rise Ground) |
| `agency_manager` | agency | 49 | `manager@taisartownhub.com` |
| `property_manager` | agency | 27 | *not seeded* → created via the real invitation flow in this audit |
| `agent` (dealer) | agency | 22 | `dealer@taisartownhub.com` |
| `accountant` | agency | 20 | *not seeded* → created via the real invitation flow in this audit |
| `document_manager` | agency | 16 | `operator@taisartownhub.com` |
| `viewer` | agency | 8 | *not seeded* → created via the real invitation flow in this audit |
| `pl_manager` | platform | 20 | — |
| `pl_moderator` | platform | 13 | — |
| `pl_operator` | platform | 12 | — |
| `pl_support` | platform | 3 | — |

Legacy `users.role` column (`super_admin`, `admin`, `manager`, `operator`, `dealer`) is a display
discriminator; **authorisation uses `agency_members.role_id` + role grants** resolved
server-side (`AgencyContext` middleware, `AuthorizesApi::requirePermission/requireAny/can`).

## 4. API surface (166 actions)

Families: auth (login/MFA/reset/verify/history), agency register + members/invitations,
properties CRUD + moderation + features, projects, dealers, dealer-files (sell/sales-report),
inquiries, favorites, agency documents (folders, upload, download, shares, access logs),
finance (transactions, payments, schedules, commissions, expenses), CRM leads, workspace,
notifications, support tickets, subscribers, locations, catalog, moderation queue, shares,
admin (agencies, users, roles, subscriptions, invoices, plans, audit-logs, security events,
dashboard, reports, settings, CMS), health.

Hot lists carry deterministic ordering (`orderByDesc(created_at)->orderBy('id')`) and
agency scoping; money is bigint PKR; CNIC encrypted + masked; vault on a non-servable disk.

## 5. Automated tests already in the repo

| Suite | Count | Where |
|---|---:|---|
| Backend feature/unit | 176 tests / 1613 assertions | `backend/tests/**` (RBAC, tenancy, MFA, vault, finance lifecycle, concurrency, headers, versioning, plan limits…) |
| Frontend unit | 126 tests | `frontend/src/**/*.test.ts` (permissions, mfa, api client, app context…) |
| Playwright E2E | 5 specs | `frontend/e2e/smoke.spec.ts` |
| Browser journey: MFA | 10 steps | `scripts/mfa-e2e.mts` |
| Browser audit crawler | all public routes × desktop/mobile | `scripts/browser-audit.mts` |
| Public button interaction | 240 probes | `scripts/browser-interactions.mts` |
| Admin per-role interaction | superadmin + manager + dealer | `scripts/browser-admin-interactions.mts` |
| Security attack suite | 21 probes | `scripts/security-probe.mts` |
| Mail flow (real SMTP) | 6 checks | `scripts/mail-flow-e2e.mts` + `scripts/mail-sink.mts` |
| Concurrency (double-sell) | 8 parallel sells | `scripts/concurrency-booking.mts` |
| Load harness | configurable | `scripts/load-test.mts` |
| Legacy data probes | 2 scripts | `legacy-visibility-probe.mts`, `platform:legacy-audit` |

## 6. Feature-completeness scan (code + DB)

| Area | State |
|---|---|
| Auth + MFA + login history + suspicious-login alerts | **complete** |
| Agency onboarding → verify → workspace | **complete** |
| Staff invitations (create/accept/revoke, role-scoped) | **complete** |
| Properties CRUD + moderation (approve/publish/feature) + plan limits | **complete** |
| Projects CRUD + detail pages | **complete** |
| Dealers + dealer-files (sell once, commission, sales report) | **complete** |
| Finance: transactions, payments, schedules, commissions, expenses, void/approve | **complete** |
| Documents: folders, upload validation, private storage, shares (hashed/expiring), access log | **complete** |
| CRM leads (assign/update/export) | **complete** |
| Favorites (per account) | **complete** |
| Subscriptions: plans, invoices, approval | **complete** (no real gateway by design) |
| Support tickets/messages | **complete** (table empty in dev) |
| CMS / media gallery / testimonials / subscribers | **complete** |
| Notifications (in-app) + email via queue | **complete** |
| Malware scanning of uploads | **absent** (documented decision B-05, no fake scanner) |
| SMS/WhatsApp | **absent** (no provider — B-06) |
| Legacy `business_records` JSON store | present, **not tenant-scoped** (B-01 decision pending) |

## 7. Known open items carried into this audit

| ID | Item |
|---|---|
| B-01 | Legacy store shared across tenants — decision pending (tooling delivered) |
| B-02 | API rate limit 60/min — production profile approval pending |
| B-03 | Queue worker must be restarted after `.env` changes |
| B-04 | Global MFA-required policy — decision pending |
| P-05 | `videos` rows store a deal id in `agencyId` (legacy data bug) |
