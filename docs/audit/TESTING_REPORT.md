# Testing Report

Branch: `fix/workspace-transaction-lifecycle-20261126` (HEAD `0903e92` + local audit fixes)
Date: 2026-11-26

## Backend (Laravel / PHPUnit)

Command: `php artisan test` (working dir `backend/`, MySQL 8-compatible XAMPP MariaDB locally)
Result: **540 passed, 0 failed, 4527 assertions, exit code 0**, duration ~180s.

Suites included: Unit + Feature (authentication, MFA, RBAC, tenant isolation, finance lifecycle/isolation, workspace hardening/tenant matrix, dealer files, encryption at rest, malware upload enforcement, maps, MFA enforcement, concurrency).

Notable regression suites:
- `FinanceLifecycleTest`: 31 passed
- `FinanceIsolationTest`: 19 passed
- `MalwareUploadEnforcementTest`: 11 passed
- `BookingConcurrencyTest`: 4 passed
- `WorkspaceTenantMatrixTest`: all data-provider cases passed

Earlier baseline (first run of this audit) was contaminated by a concurrently-running second `php artisan test` process (migrate:fresh racing) and a stale test DB schema; after isolating runs and running `migrate:fresh` on the test DB, the clean baseline is 540/0.

## Frontend (React / TypeScript)

Command: `npm run verify` (working dir `frontend/`)
Result: **exit 0** — tsc clean, 173/173 tests passed, production build succeeded, 29 prerendered pages, bundle check OK.

## Browser (Playwright)

Command: `npx playwright test ... --project=chromium` against local backend (`php artisan serve :8080`) + Playwright webServer (Vite dev on :4173).
Result:
- `cross-browser-smoke.spec.ts`: 7 passed (home, properties, agencies, guide, about, contact, login render with real headings)
- `maps.spec.ts`: 5 passed (title/filters/empty state, category filter, search, lightbox zoom)
- `interactive-elements.spec.ts`: 9 passed (navbar, auth forms, property listings, contact form, 404 recovery)
Total: 21 passed, 0 failed.

Viewport: default Desktop Chrome (1280px). Mobile/tablet viewports (360/390/768/1024/1440/1920) were NOT explicitly exercised — the Playwright project defines a single Desktop Chrome device; responsive behaviour relies on Tailwind responsive classes and is not covered by an automated matrix. This is recorded as a partial/limitation.

Admin/role-journey specs (`admin-pages.spec.ts`, `agency-marketplace.smoke.spec.ts`) require seeded demo accounts and were not run in this pass (see REMAINING_BLOCKERS.md).

## External / not run
- Docker image builds and compose smoke (no Docker daemon locally).
- Live ClamAV scanning (no daemon; covered by fake-clamd tests).
- Lighthouse performance scores (not run).