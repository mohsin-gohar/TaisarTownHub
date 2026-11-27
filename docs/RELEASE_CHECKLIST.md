# TaisarTownHub — Production Release Checklist

**Updated:** 2026-11-27  
**Application baseline:** `3584c9ca544ef2df6ffdb440f938a11b815140db` (PR #28 merged)  
**Verdict:** **Code/CI gates PASS; full production release BLOCKED by unavailable backend and DNS.**

## Verified gates

| Gate | Status | Evidence |
|---|---|---|
| Tenant privacy / CRM export fixes | PASS | PR #24 merged |
| Legacy gallery redirect | PASS | PR #25 merged; production redirect verified |
| Symfony security advisories | PASS | Updated to Symfony 7.4.24; Composer audit reports 0 advisories |
| Stripe SDK compatibility | PASS | `stripe/stripe-php` 17.6.1 under `^17.0` |
| CI dependency security gates | PASS | Composer audit and npm audit steps added; both green |
| Backend PHPUnit / MySQL 8 | PASS | 426 tests, 3,735 assertions |
| Frontend verify | PASS | 159 tests; typecheck/build/prerender/bundle |
| Browser E2E | PASS | 105 local Playwright tests; 53 CI Chromium regression tests |
| OpenAPI parse | PASS | CI |
| Backend + frontend Docker images | PASS | Docker CI |
| MySQL backup/restore drill | PASS | 58 tables, 95 foreign keys, row counts verified |
| Full-stack integration smoke | PASS | nginx, migrations, MySQL/Redis/ClamAV, auth gateway flows |
| Vercel frontend deployment | PASS | Main application baseline deployment READY |
| Vercel custom-domain attachment | PASS | Apex and www attached; www redirects to apex with 308 |
| Custom-domain registration/DNS/TLS | **BLOCKED** | `taisartownhub.com` is available to register (not purchased); apex/www DNS return NXDOMAIN |
| Production Laravel API | **FAIL / BLOCKED** | `api.taisartownhub.com` DNS does not exist; Vercel API proxy returns 502 |
| Production secrets | NOT CONFIGURED / NOT VERIFIED | No production secrets were read or changed |
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
- [ ] Configure SMTP, OAuth/payment secrets (or explicitly disable unused integrations), monitoring, and alerting.

### Domains and frontend
- [ ] Register/purchase `taisartownhub.com` (read-only registry lookup currently reports it available; no purchase has been made).\n- [ ] After registration, set apex A records to Vercel's current recommended values: `216.198.79.1` and `64.29.17.1`.
- [ ] Set `www` CNAME to `fbb47a846f572c5c.vercel-dns-017.com` (or the current Vercel-provided target).
- [ ] Verify HTTPS/TLS and canonical redirect for apex/www.
- [ ] Select and deploy the API host, then point `api.taisartownhub.com` to the host-provided DNS target.
- [ ] Verify HTTPS/TLS for API.
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

**Do not declare 100% complete or production-ready while the API returns 502 or the custom domains do not resolve.** Passing CI proves the tested code and disposable integration environment; it does not prove the live hosting stack is operational. Do not bypass malware scanning or weaken tenant authorization to make deployment appear successful.
