# BROWSER_TEST_INVENTORY.md — TaisarTownHub

**Date:** 2026-11-14 · Complete page inventory built from `App.tsx` routing + the live crawl
(`frontend/scripts/browser-audit.mts` → `frontend/audit/report.json`), then verified by
real browser visits (Playwright Chromium, desktop 1366×900 + mobile 375×812).

## Classification legend

| Mark | Meaning |
|---|---|
| ✅ Browser-tested | Actually opened in the running app via Playwright; console/network/DOM recorded |
| 🤖 Automated-test verified | Covered by a scripted journey that drives the real API/UI |
| 📄 Source-inspected only | Read in code, not (yet) opened in a browser |
| ⛔ Not tested (environment) | Blocked by environment limitation — stated explicitly |

## 1. Public views (view-state SPA, `AppContext.currentView` + `KNOWN_PUBLIC_PATHS`)

| # | Route / path | View | Component | Status | Evidence |
|---|---|---|---|---|---|
| 1 | `/` , `/home` | home | Home sections (Hero, Featured, Stats, Testimonials, CTA, Footer) | ✅ | report.json + interactions.json |
| 2 | `/projects` | projects | `ProjectsListingPage` | ✅ | report.json |
| 3 | `/properties` | properties | `PropertiesListingPage` | ✅ | report.json + flows (filter/search/reset) |
| 4 | `/agencies` | agencies | `AgenciesSection` | ✅ | report.json |
| 5 | `/guide`, `/scheme45-guide` | guide | `Scheme45Guide` | ✅ | report.json |
| 6 | `/gallery`, `/media-gallery` | gallery | `MediaGallerySection` | ✅ | report.json |
| 7 | `/services` | services | `ServicesSection` | ✅ | report.json |
| 8 | `/about` | about | `AboutSection` | ✅ | report.json |
| 9 | `/contact` | contact | `ContactSection` | ✅ | report.json + form submit flow |
| 10 | `/login`, `/auth`, `/signin` | login | `AuthPage` (signin tab) | ✅ | interactions flows (invalid + valid) |
| 11 | `/register` | register | `AuthPage` (register tab) | ✅ | interactions flow (validation) + workflows E2E |
| 12 | `/admin-login` | admin-login | `AdminLoginModal` | ✅ | browser-admin-interactions |
| 13 | `/reset-password?token=…` | reset-password | `ResetPasswordPage` (cold load) | 📄 | token flow exercised by mail-flow suite (API level) |
| 14 | `/verify-email?email=…&token=…` | verify-email | `VerifyEmailPage` (cold load) | ✅ | report.json (expected 422 for fake token) |
| 15 | `/* unknown` | not-found | `NotFoundPage` (noindex) | ✅ | interactions flow (404 + recovery) |
| 16 | `/properties?property={id}` deep link | property-detail | `PropertyDetailPage` | ✅ | interactions (detail probes) |
| 17 | `/projects` → project deep link | project-detail | `ProjectDetailPage` | ✅ | interactions (detail probes) |
| 18 | `/agency-access` style invite deep links | agency-access | `AgencyAccessPage` | 📄 | invitation accept is API-verified (role-matrix) |

**Crawl coverage:** 41 route/viewport combinations recorded in `report.json`
(0 console/page errors, 0 failed requests, 0 dead anchors, 0 broken images after N-01 fix;
the only console entry was the expected 422 on `/verify-email` with an invalid token).

## 2. Authenticated admin shell (`/admin`, `AdminLayout` + tabs)

Accessed through the real `/admin-login` modal per role. Tab visibility is
grant-driven; the authoritative walk is `browser-admin-interactions.mts`
(→ `frontend/audit/admin-interactions.json`).

| Tab / module | super_admin | agency_owner | agency_manager | agent | document_manager |
|---|---|---|---|---|---|
| Dashboard (Agency) | ✅ | ✅ | ✅ | ✅ | ✅ (scoped) |
| Staff & Roles | ✅ | ✅ | ✅ (view) | — | — |
| Document Vault | ✅ | ✅ | ✅ | — | ✅ |
| CRM Pipeline | ✅ | ✅ | ✅ | ✅ (own) | — |
| Media Studio | ✅ | ✅ | ✅ | — | — |
| Subscription & Usage | ✅ | ✅ | ✅ | — | — |
| Platform admin modules (agencies, users, plans, invoices, audit, settings, CMS, moderation) | ✅ | — (403 server-side) | — | — | — |

Server-side enforcement of the same grid was independently proven by the API matrix
(`ROLE_BASED_TEST_REPORT.md`, 51/51) — hidden buttons are never the authorization source.

## 3. API journeys (real browser or real HTTP as the product's own client)

| Journey | Script | Result |
|---|---|---|
| Staff lifecycle + 9-role matrix + revocation | `role-matrix-e2e.mts` | 51/51 PASS |
| W1 buyer → favorites → inquiry → CRM | `workflows-e2e.mts` | 28/28 PASS |
| W2 property create → approve → public | `workflows-e2e.mts` | included |
| W3 deal → sell → commission → payment | `workflows-e2e.mts` | included |
| W4 document upload → share → revoke → 410 | `workflows-e2e.mts` | included |
| MFA journey (10 steps) | `mfa-e2e.mts` | PASS (prior session, unchanged code) |
| Mail flows (reset/verify/invite) | `mail-flow-e2e.mts` | PASS (prior session) |
| Security attack suite (21 probes) | `security-probe.mts` | re-run in this pass (see `SECURITY_AND_TENANT_ISOLATION_AUDIT.md`) |
| Public interactivity (247 click probes, 8 flows) | `browser-interactions.mts` | 0 dead clicks / 0 error probes; flows fixed (N-03) |

## 4. Explicitly not covered (with reason)

| Area | Reason |
|---|---|
| Real SMS/WhatsApp channels | No provider configured in the product (documented gap B-06) |
| Real payment gateway | None exists by design; subscription checkout is stubbed at the gateway boundary |
| Malware scanning of uploads | Absent by documented decision B-05 — a real scanner/adapter is a Phase-12 item, never faked |
| Production deployment | Never executed without explicit authorization (rule 18) |
