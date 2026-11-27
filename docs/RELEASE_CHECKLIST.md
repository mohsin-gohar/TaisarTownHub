# TaisarTownHub — Production Release Checklist

**Updated:** 2026-11-27  
**Main:** `5f15402973908886c9e2ef09962b28cad80e78ff`  
**Verdict:** **Frontend deployed; full production release BLOCKED by unavailable Laravel API.**

## Verified gates

| Gate | Status | Evidence |
|---|---|---|
| Tenant privacy / CRM export fixes | PASS | PR #24 merged |
| Legacy gallery redirect | PASS | PR #25 merged; production redirect verified |
| Backend PHPUnit / MySQL 8 | PASS | 426 tests, 3,735 assertions |
| Frontend verify | PASS | 158 tests; typecheck/build/bundle |
| Browser E2E | PASS | 53 Playwright Chromium tests |
| OpenAPI parse | PASS | CI |
| Backend + frontend Docker images | PASS | Docker CI |
| MySQL backup/restore drill | PASS | Docker CI |
| Full-stack integration smoke | PASS | nginx, migrations, MySQL/Redis/ClamAV, auth gateway flows |
| Vercel frontend deployment | PASS | Main deployment READY |
| Production Laravel API | **FAIL / BLOCKED** | Vercel API proxy 502; InfinityFree host shows default page and API 404 |
| Custom domain attachment | BLOCKED | Vercel project lists only `taisartownhub.vercel.app` |
| Production secrets | NOT CONFIGURED / NOT VERIFIED | Vercel env listing empty; backend host unavailable |
| Full file-by-file audit | IN PROGRESS | 565 tracked blobs; historical 706-file ledger unreconciled |

## Required release gates

### Backend and infrastructure
- [ ] Deploy Laravel backend to a supported PHP/container host.
- [ ] Provision MySQL and run migrations with production credentials.
- [ ] Configure Redis for cache, session, and queues.
- [ ] Deploy queue worker and scheduler.
- [ ] Deploy ClamAV and verify fail-closed malware scanning.
- [ ] Configure persistent private storage and verify access controls.
- [ ] Generate a unique production APP_KEY and store secrets securely.
- [ ] Configure exact CORS origins and trusted proxies.

### Domains and frontend
- [ ] Point `api.taisartownhub.com` to the deployed backend.
- [ ] Verify HTTPS/TLS for API.
- [ ] Attach `taisartownhub.com` to the intended Vercel project.
- [ ] Verify DNS/TLS and canonical redirects.
- [ ] Confirm Vercel API proxy returns 200 for `/api/health`.

### Live acceptance
- [ ] Public properties, agencies, maps, and gallery load from the live API.
- [ ] Visitor registration/login/password reset/email verification work.
- [ ] Agency owner/member/staff permissions are verified live.
- [ ] Super-admin workflows are verified live.
- [ ] Tenant isolation is tested with two separate agencies.
- [ ] Private uploads/downloads, quotas, malware rejection, and audit logs work.
- [ ] CRM assignment/export and finance permissions work.
- [ ] Map assets and media storage are present.
- [ ] SMTP, OAuth, and payment integrations are tested or explicitly disabled.

### Operations and audit
- [ ] Production backup and restore rehearsal completed.
- [ ] Monitoring, alerting, and rollback tested.
- [ ] Complete the 565-file review and reconcile the historical 706-file ledger.
- [ ] Resolve all critical/high security findings before sign-off.

## Release rule

**Do not declare 100% complete or production-ready while the API returns 502.** Passing CI proves the tested code and disposable integration environment; it does not prove the live hosting stack is operational. Do not bypass malware scanning or weaken tenant authorization to make deployment appear successful.
