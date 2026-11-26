# Remaining Blockers

Date: 2026-11-26

## Externally dependent (cannot be resolved in this environment)
1. **Docker daemon not available** (`docker` not recognized) — image builds, compose smoke, multi-replica scaling, and live ClamAV cannot be verified locally. CI (GitHub Actions, ubuntu) covers image builds.
2. **No ClamAV locally** (no `clamscan`, port 3310 refused) — real upload scanning verified only by fake-clamd tests; production uses the `clamav` service (fail-closed).
3. **No seeded demo accounts in this session's DB for browser role journeys** — `admin-pages.spec.ts` and `agency-marketplace.smoke.spec.ts` require `superadmin@taisartownhub.com` etc. (dev DB has 5 users/1 agency/4 properties, but browser role specs not run). Public-page smoke (21 tests) passed.
4. **TLS certificates / SMTP / DNS / third-party keys** (Stripe, Flutterwave, Sentry, GA, LogRocket) — must be provisioned by the owner per `docs/DEPLOYMENT.md`.
5. **Performance (Lighthouse)** — not run; no scores recorded. Frontend bundle check in `npm run verify` passed.
6. **Mobile/tablet viewport matrix** — Playwright project defines a single Desktop Chrome device; 360/390/768/1024/1440/1920 not explicitly exercised.

## Locally addressable but not done this pass
- Deeper SEO audit (structured data, OG tags, sitemap contents) — basic presence confirmed only.
- N+1 query audit across all controllers — not systematically measured; pagination present on list endpoints.
- `gh` CLI absent — PR/CI status checked via GitHub REST API (success for `main`).

## Push / deploy
- Changes are local and uncommitted on `fix/workspace-transaction-lifecycle-20261126`. No push performed (authorization required). `main` untouched.