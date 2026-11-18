# Complete Browser QA Report

## Run identity
- Date: 2026-11-18
- Branch/HEAD: `main` / `2eb9dae8e81607e3e9b63f09a34d416f2ee78450`
- App: React/Vite at `http://127.0.0.1:3001`; Laravel API at `http://127.0.0.1:8080`
- Playwright latest final run: **63 passed, 4 failed** (Chromium, 2 workers, 8.8 minutes).
- Focused post-polish sale/agency run: **10 passed, 0 failed** (Chromium).

## Routes opened
`/`, `/properties`, `/agencies`, `/agencies/unknown-audit-slug`, `/guide`, `/gallery`, `/services`, `/about`, `/contact`, `/login`, `/register`, `/admin-login`, `/admin`, `/reset-password`, `/verify-email`, `/projects`, `/project-detail`, and an unknown route. Both project paths redirected to `/properties?purpose=sale`; unknown route rendered the 404 page.

## Repairs verified
- Restored public route dispatch; routes had been falling through to the homepage.
- Restored 404/noindex route behavior and dynamic agency-profile cold loads.
- Legacy project URLs resolve to sale listings, including cold loads.
- Homepage search criteria reach the properties filter.
- Agency profile requests and filters are sale-only.
- Removed invented home ratings, social proof, statistics and demo testimonials.
- Restored approved `/logo.jpg` in desktop/mobile navigation.
- Fixed API JSON BOM and database-driver health label.
- Fixed 1024px footer email overflow; all six required viewport widths now pass.

## Latest retest failures
- Super Admin demo login: the configured `superadmin@taisartownhub.com` account did not authenticate, so `/admin-login` remained visible.
- Agency Admin demo login: the configured `admin@taisartownhub.com` account did not authenticate, so `/admin-login` remained visible.
- Workflow: Login -> Admin Dashboard repeated the absent Super Admin credential blocker.
- Workflow: protected route access after logout timed out without a verified logout/session fixture.
- Manager, Dealer, and Operator login cases passed. Guest/admin-route protection and public security checks passed.

## Browser evidence
Screenshots are in `frontend/.screenshots/audit/`: homepage desktop/mobile, properties, agencies, gallery, login mobile, 404, and unknown-agency profile. The route sweep recorded an expected 404 for the deliberately unknown agency and one Google Maps request aborted by navigation; neither blocked page rendering.

## Limits
The 67 E2E tests are the repository's existing browser coverage plus updated sale-only expectations, not exhaustive click coverage for every control on every page. Lighthouse was not run. MySQL 8/Docker runtime testing was blocked: the local Laravel environment uses SQLite and Docker is unavailable.
