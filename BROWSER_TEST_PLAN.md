# BROWSER_TEST_PLAN.md â€” TaisarTownHub

**Date:** 2026-11-14 Â· **Environment:** local dev â€” API `http://127.0.0.1:8090`, frontend
`http://127.0.0.1:3000`, MySQL, queue worker running. Playwright Chromium drives the real UI;
no mocking of the app itself. Test data is disposable (`@audit.test`, `probe-` prefixed
records); the owner's real account (`majeedag456@gmail.com`, Rise Ground Properties) is
**never touched**.

## Personas

| Persona | Account | Role (server-resolved) | Source |
|---|---|---|---|
| Guest | â€” (incognito context) | unauthenticated | â€” |
| Buyer / registered user | fresh `buyer.<ts>@audit.test` via the real register form | agency_owner (the app's only self-service role) | created live in the browser |
| Agency Owner | `admin@taisartownhub.com` / `[REDACTED]` | platform admin + agency_owner (Premier Advisory) | seeded |
| Agency Manager | `manager@taisartownhub.com` / `[REDACTED]` | agency_manager | seeded |
| Agent / Dealer | `dealer@taisartownhub.com` / `[REDACTED]` | agent + dealer entity `deal-3` | seeded |
| Document Manager | `operator@taisartownhub.com` / `[REDACTED]` | document_manager | seeded |
| Accountant | created via the **real staff-invitation flow** | accountant | fixture built through the product |
| Viewer | created via the **real staff-invitation flow** | viewer | fixture built through the product |
| Property Manager | created via the **real staff-invitation flow** | property_manager | fixture built through the product |
| Super Admin | `superadmin@taisartownhub.com` / `[REDACTED]` | super_admin (MFA disabled state) | seeded |

Missing roles (accountant / viewer / property_manager) are **not** granted privileges by hand â€”
they are created through the product's own invitation flow so the test also exercises that
workflow. No authentication or permission is bypassed.

## Phase plan

| # | Phase | Tool | Pass criterion |
|---|---|---|---|
| 3 | Public site: every view Ã— desktop/mobile, every section, forms, filters, pagination, back/forward, deep-link refresh, 404 | `browser-audit.mts` re-run + targeted manual probes | 0 console/page errors, 0 failed requests, 0 dead anchors, 0 broken images, overflow = 0 |
| 3b | Public interactivity: every visible button/link on every public route (incl. footer, newsletter, modals) | `browser-interactions.mts` re-run | 0 dead clicks, no duplicate submissions |
| 4 | Role-by-role: admin shell per role (tabs, buttons), API 403 checks for role-restricted surfaces, direct-URL access | `browser-admin-interactions.mts` re-run + new `role-matrix-e2e.mts` | each role sees only its modules; forbidden actions â†’ 403 server-side; 0 dead clicks |
| 4b | Staff lifecycle: invite â†’ accept â†’ module access â†’ revoke â†’ login dead | new script driving the real UI + API | full lifecycle succeeds and revoked access is actually gone |
| 5 | Workflows E2E: registerâ†’verifyâ†’favoriteâ†’inquiry; property createâ†’moderateâ†’publishâ†’public; leadâ†’dealâ†’paymentâ†’commission; document uploadâ†’shareâ†’revoke; agency onboarding | browser + API assertions against the DB | every step persists with the expected status transitions |
| 6 | Interaction edge cases: empty/invalid input, double-submit, toasts, dialogs (real accessible dialogs, not `window.confirm`), keyboard focus | targeted Playwright | validation messages shown; no duplicate writes |
| 7 | Security: cross-tenant (re-run), role escalation, IDOR, mass assignment, unauth access, throttle | `security-probe.mts` re-run + per-role 403 matrix | all attacks rejected with safe codes |
| 8 | UI/UX: responsive nav, drawer, table readability on mobile, focus states, contrast spot-checks, empty states | browser-audit screenshots + targeted probes | overflow 0, focus visible, no clipped tables |
| 9 | Perf/SEO: titles, metas, canonical, sitemap, robots, OG, noindex on private; API/page timings, bundle size | `perf-snapshot.mts`, verify chain | SEO fields present per route; no private page indexable |
| 10 | Bug fixing | â€” | every confirmed bug: root cause â†’ fix â†’ regression test â†’ re-verify |
| 12 | Final gates: backend suite, frontend suite, tsc, build+prerender, Playwright, journeys | all | all green |

## Conventions

- Admin login uses the `/admin-login` modal **scoped to the form containing
  `input[placeholder="Enter password"]`** (the footer newsletter also submits on the page).
- Login-throttle is 5/min per IP: scripts pause â‰¥ 13 s between sign-ins.
- Destructive actions (delete/revoke/approve/payâ€¦) are only clicked on **disposable records**.
- Every finding is recorded into `frontend/audit/*.json` and transcribed into the reports.
