# TaisarTownHub — Independent Verification Report

**Date:** 2026-11-27  
**Repository:** `mohsin-gohar/TaisarTownHub`  
**Main commit:** `c006c2bb6b4a210a8db7139aa30d5bad9ab693a1`  
**Merged PRs:** [#24 — tenant privacy and public marketplace fixes](https://github.com/mohsin-gohar/TaisarTownHub/pull/24), [#25 — Vercel legacy gallery redirect](https://github.com/mohsin-gohar/TaisarTownHub/pull/25), [#26 — documentation status refresh](https://github.com/mohsin-gohar/TaisarTownHub/pull/26)

## Executive verdict

**Not approved for full production sign-off.** The frontend is deployed on Vercel and the merged code has passing CI/Docker verification. The production Laravel API origin is unavailable, so live API-backed workflows cannot be considered operational.

## Scope and audit limitations

- Current main tree: **565 tracked file blobs**.
- The historical `COMPLETE_FILE_AUDIT_LEDGER.md` references 706 files and still marks the audit in progress.
- A complete line-by-line review of all 565 files has not been completed.
- This report records specific reviewed code, automated evidence, and read-only live observations. It does not claim every page, button, role, integration, or file has been exhaustively verified.

## Merged code fixes

### PR #24 — security and public marketplace

- Property purchase/selling prices and internal notes require an explicit finance/sensitive-data permission, including for owning-agency members.
- An agency-scoped permission cannot reveal another agency's confidential property economics.
- `pending_changes` is hidden by default and only exposed to moderators with `properties.approve`.
- CRM CSV export applies the same own/unassigned lead scope as the CRM list when the user lacks `crm.assign_lead`.
- Public video comments no longer expose internal `user_id` values; server-side attribution remains.
- Agency-directory API failure no longer produces a contradictory empty-filter state.
- Placeholder video entries are filtered and the gallery displays an honest empty state.
- Unconfigured generic social-network homepage links are hidden.
- Desktop hero filters have readable widths and responsive browser checks were added.
- Root and frontend Vercel configs proxy `/api/:path*` to `https://api.taisartownhub.com/api/:path*`.

### PR #25 — production route correction

The deployed production route `/video-walkthroughs` returned 404 despite the React app recognizing the alias. Both Vercel config roots now permanently redirect it to `/gallery`. The redirect was verified after the production deployment became READY.

## Automated verification evidence

### Main code after PR #24

- Backend PHPUnit/MySQL 8: **426 passed, 3,735 assertions**.
- Frontend verification: **158 tests passed**, including TypeScript, build, and bundle checks.
- Playwright Chromium: **53 passed**.
- OpenAPI specification parsing passed.
- Backend and frontend Docker image builds passed.
- MySQL backup and isolated restore drill passed.
- Full-stack Docker integration passed: nginx config validation, MySQL/Redis/ClamAV readiness, migrations, production-safe catalog seeding, backend/frontend/worker/scheduler readiness, and gateway-level register/login/me/logout smoke tests.

### PR #25

All required checks passed before merge: frontend verification, backend suite, Playwright, OpenAPI parsing, backend/frontend Docker builds, MySQL backup/restore, and full-stack integration.

**Important:** CI used disposable test infrastructure. It does not prove the production host, production database, DNS, mail, or payment gateways are configured.

## Read-only production observations

| Check | Result |
|---|---|
| Vercel deployment for main `c006c2b` | READY |
| `/video-walkthroughs` | Redirects to `/gallery` |
| Vercel `/api/health` proxy | 502 |
| InfinityFree backend homepage | Default InfinityFree welcome page |
| InfinityFree `/api/health` | 404 |
| Vercel project domains | Only `taisartownhub.vercel.app`; custom domain not attached |
| Vercel environment variables | Empty listing |

The frontend proxy target `api.taisartownhub.com` is not serving the Laravel API. The InfinityFree endpoint checked separately is not serving Laravel either. Agency/map API data, authentication, and agency workspaces therefore remain blocked in production.

## Required before full production sign-off

1. Deploy Laravel to a supported PHP/container host with persistent private storage.
2. Provision MySQL, Redis, ClamAV, queue worker, and scheduler as required by the repository's deployment architecture.
3. Configure production secrets securely: APP_KEY, DB credentials, CORS, trusted proxies, SMTP, OAuth/payment credentials (or explicitly disable unused integrations), and monitoring.
4. Point `api.taisartownhub.com` to the working backend and verify TLS and health.
5. Attach the custom frontend domain to the intended Vercel project and verify DNS/TLS.
6. Test live agency directory, maps, authentication, agency dashboard, tenant isolation, private file upload/download, exports, and all critical role permissions.
7. Verify deployed map assets and private storage.
8. Run an actual production backup/restore rehearsal; configure alerts and rollback.
9. Finish the 565-file review and reconcile the old 706-file ledger.

## Changes not performed

No production database was modified, no production files were uploaded, no DNS records were changed, no live payment integration was enabled, and no backend was deployed. Do not disable fail-closed malware scanning to work around an unsupported hosting plan.
