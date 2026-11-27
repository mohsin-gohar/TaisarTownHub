# TaisarTownHub — Current Project Status

**Review date:** 2026-11-27  
**Current main:** `5f15402973908886c9e2ef09962b28cad80e78ff`  
**Production frontend:** [taisartownhub.vercel.app](https://taisartownhub.vercel.app)

## Release verdict

**Frontend code is deployed and CI is green. The full SaaS is not production-ready because the Laravel API is not running on its configured public origin.**

Do not interpret successful CI or a Vercel frontend deployment as proof that live authentication, agency workspaces, property mutations, private files, payments, or background jobs work in production.

## Work merged to main

- **PR #24** — tenant privacy, property financial-field authorization, moderation-field privacy, CRM export row scoping, public video-comment privacy, gallery/agency UI fixes, and Vercel API proxy.
- **PR #25** — permanent Vercel redirect from `/video-walkthroughs` to the canonical `/gallery` route, with a regression test for both Vercel config roots.

## Verified automated checks

On the merged PR #24 code:
- Backend PHPUnit/MySQL 8: **426 passed, 3,735 assertions**.
- Frontend verification: **158 tests passed**; TypeScript, build, and bundle checks passed.
- Playwright Chromium: **53 passed**.
- OpenAPI parsing passed.
- Docker backend and frontend image builds passed.
- MySQL backup and isolated restore drill passed.
- Full-stack Docker integration passed: nginx configuration, MySQL, Redis, ClamAV, migrations, backend/frontend/worker/scheduler readiness, and gateway-level register/login/me/logout smoke tests.

On PR #25, frontend verification, backend suite, Playwright, Docker image builds, backup/restore, and full-stack integration also passed. Both PRs are merged.

These are CI/test-environment results, not production-host results.

## Live deployment observations

- Vercel production deployment for main commit `5f15402` reached **READY**.
- `/video-walkthroughs` was verified to redirect to `/gallery`.
- The production `/api/health` proxy request returns **502** because the configured API origin is unavailable.
- `https://taisartownhubbackend.infinityfree.io/` served the InfinityFree default welcome page during the check; `/api/health` returned 404. Laravel is not being served there.
- The Vercel project currently lists only `taisartownhub.vercel.app`; `taisartownhub.com` is not attached to this Vercel project.
- The Vercel project environment-variable listing returned no variables. No production secrets were read or changed.

## Repository audit coverage

The current main Git tree contains **565 tracked file blobs**. The historical audit ledger refers to 706 files and remains incomplete. A complete line-by-line review of all 565 files, all routes, every control, and every role has **not** been completed. No report should claim otherwise.

## Remaining production gates

1. Deploy the Laravel backend to a supported production host. The current InfinityFree endpoint is only showing its default page; shared hosting is not suitable for the documented Redis/worker/scheduler/ClamAV/fail-closed upload architecture.
2. Provision and configure production MySQL, Redis, private persistent storage, ClamAV, queue worker, and scheduler.
3. Generate a unique production `APP_KEY`; configure database credentials, exact CORS origins, trusted proxies, SMTP, OAuth/payment secrets (or explicitly disable unused integrations), and monitoring.
4. Point `api.taisartownhub.com` to the deployed API and verify HTTPS/TLS plus `/api/health`, `/api/agencies`, `/api/maps`, login, agency workspace, and private-document authorization.
5. Attach `taisartownhub.com` to the intended Vercel project and verify DNS/TLS before advertising it as the production frontend.
6. Verify the map image assets and private storage on the actual backend host.
7. Run live role-based browser acceptance for visitor, agency owner/member, staff, and super-admin; test every critical form, action, upload, export, and permission boundary.
8. Complete a production backup/restore rehearsal and configure monitoring, alerts, and rollback.
9. Reconcile the historical file-audit ledger and finish the file-by-file review.

## Safety note

No production database migration, production file upload, DNS change, payment setup, or backend deployment was performed during this review. Do not disable fail-closed malware scanning to force an unsupported host to accept uploads.
