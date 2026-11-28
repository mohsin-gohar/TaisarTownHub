# TaisarTownHub — Current Project Status

**Review date:** 2026-11-27  
**Application baseline:** `3584c9ca544ef2df6ffdb440f938a11b815140db` (PR #28 merged)  
**Production frontend:** [taisartownhub.vercel.app](https://taisartownhub.vercel.app)

## Release verdict

**Application code and CI are verified; the full SaaS is not production-ready because the Laravel API host and required DNS records are unavailable.**

Do not interpret passing CI or a READY Vercel frontend deployment as proof that live authentication, agency workspaces, property mutations, private files, payments, or background jobs work in production.

## Work merged to main

- **PR #24** — tenant privacy, property financial-field authorization, moderation-field privacy, CRM export row scoping, public video-comment privacy, gallery/agency UI fixes, and Vercel API proxy.
- **PR #25** — permanent Vercel redirect from `/video-walkthroughs` to `/gallery`.
- **PR #26 and #27** — production-readiness documentation refreshes.
- **PR #28** — Symfony security-advisory patches, Stripe SDK update, and Composer/npm audit gates in CI.

## Verified automated checks

On the merged application baseline after PR #28:

- Backend PHPUnit/MySQL 8: **426 passed, 3,735 assertions**.
- Frontend verification: **159 tests passed**; TypeScript, production build, 29-page prerender, and bundle checks passed.
- Local Playwright Chromium acceptance: **105 passed**, including visitor, agency roles, super-admin, maps, responsive layouts, and workflows.
- CI Playwright Chromium regression: **53 passed**.
- OpenAPI parsing passed.
- Composer audit: **0 advisories**; npm audit: **0 vulnerabilities**.
- Composer manifest validation with `--strict` passed.
- Backend and frontend Docker image builds passed.
- MySQL backup/restore drill passed: isolated restore verified 58 tables, 95 foreign keys, and matching row counts.
- Full-stack Docker integration passed: nginx, MySQL, Redis, ClamAV, migrations, backend/frontend/worker/scheduler readiness, and gateway-level authentication smoke tests.

These are repository/CI and local test-environment results, not production-host results.

## Live deployment observations

- Vercel production deployment for application baseline `3584c9c` reached **READY**.
- The Vercel project has `taisartownhub.vercel.app`, `taisartownhub.com`, and `www.taisartownhub.com` attached. `www` is configured to redirect to the apex domain with HTTP 308. **However, a Vercel registrar availability lookup reports `taisartownhub.com` is available to register; its Vercel domain record has no purchase timestamp or nameservers. The domain is not currently registered/purchased.**
- **The custom-domain DNS is still misconfigured.** DNS lookups for `taisartownhub.com` and `www.taisartownhub.com` returned “DNS name does not exist”. Vercel reports `misconfigured: true`.
- Vercel's current recommended apex A records are `216.198.79.1` and `64.29.17.1`; the recommended `www` CNAME is `fbb47a846f572c5c.vercel-dns-017.com`. These records have **not** been changed at a registrar; first the domain must be purchased/registered.
- DNS lookup for `api.taisartownhub.com` also returned “DNS name does not exist”. The backend host must be selected and deployed before its DNS target can be configured.
- The Vercel `/api/health` proxy remains **502** because the configured API origin is unavailable.
- The InfinityFree backend endpoint is not serving the Laravel API; its response is a hosting-provider page/challenge, not a healthy API.
- No production secrets were read or changed.

## Repository audit coverage

The current main Git tree contains **565 tracked file blobs**. The historical audit ledger refers to 706 files and remains incomplete. A complete line-by-line review of all 565 files, all routes, every control, and every role has **not** been completed. No report should claim otherwise.

## Remaining production gates

1. Deploy Laravel to a supported production host. The documented architecture requires MySQL, Redis, persistent private storage, ClamAV, queue worker, and scheduler; do not disable fail-closed malware scanning to force deployment to unsupported shared hosting.
2. Provision and configure production MySQL, Redis, private persistent storage, ClamAV, worker, and scheduler.
3. Generate a unique production `APP_KEY`; configure database credentials, exact CORS origins, trusted proxies, SMTP, OAuth/payment secrets (or explicitly disable unused integrations), and monitoring.
4. Configure registrar DNS for `taisartownhub.com` and `www` using the Vercel-provided records, then verify HTTPS/TLS and canonical redirects.
5. Point `api.taisartownhub.com` to the selected backend host and verify HTTPS/TLS plus `/api/health`, `/api/agencies`, `/api/maps`, login, agency workspace, and private-document authorization.
6. Verify map image assets and private storage on the actual backend host.
7. Run live role-based browser acceptance for visitor, agency owner/member, staff, and super-admin; test critical forms, actions, uploads, exports, and permission boundaries.
8. Complete a production backup/restore rehearsal and configure monitoring, alerts, and rollback.
9. Reconcile the historical file-audit ledger and finish the file-by-file review.

## Safety note

No production database migration, production file upload, registrar DNS change, payment setup, or backend deployment was performed. Vercel domain attachment is configured, but its DNS is not. Keep malware scanning fail-closed and do not weaken tenant authorization to make deployment appear successful.
