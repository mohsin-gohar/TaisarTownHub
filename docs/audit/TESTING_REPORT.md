# Testing Report

**Verified code commit:** `b7920297d5d0f0b97ed5e1a030fd04b24aa83e93`  
**Release path:** PR #5 carries the integrated QA branch to `main`.

## GitHub Actions CI

Run: https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45877162123

| Gate | Result |
|---|---:|
| Backend PHP 8.3 + MySQL 8 | 547 passed; 4,563 assertions |
| Frontend tests / TypeScript / build | 176 passed |
| Chromium Playwright | 46 passed, 2 skipped |
| Firefox + WebKit | 14 passed |
| OpenAPI parse | Passed |

SEO build generated 29 prerendered pages and a 9-URL sitemap. Public bundle guard found no private ERP data or demo credentials in public chunks.

## Docker CI

Run: https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45877162113

- Backend/frontend production images and CI/production Nginx configuration checks passed.
- MySQL backup/restore drill passed.
- Full-stack readiness, two-replica backend and shared private vault/quarantine volume checks passed.
- Real ClamAV clean-file and threat-signature detection passed.
- API smoke and 13 API-backed role tests passed.
- Public interaction audit: 10 links checked / 0 broken; 234 buttons probed / 0 no-effect, click or error findings; 11 forms/flows / 0 failures.
- Admin interaction audit: Super Admin 11 tabs, Agency Owner 11, Manager 12, Dealer 9, Operator 8; 0 no-effect, click failures or errors.
- Ten-worker load: 42,280 requests in 10 seconds (4,228 req/s); p50 1 ms, p95 4 ms, p99 44 ms, max 250 ms; 40,772 HTTP 429 responses, 114 other 4xx, 0 5xx and 0 network failures.
- Concurrency integrity: 8/8 parallel transactions received unique deal numbers; generated records were voided during test cleanup.

## Additional probe not accepted as a green gate

The superseded PR #9 workflow probe recorded 15/20 due test setup gaps for captcha, trial staff quota, dealer agency context and malware scan completion before sharing. It is not represented as passed.

## Remaining operational verification

Live DNS/TLS, SMTP, OAuth/payment/monitoring credentials, production ClamAV, off-site backup target, production data migration, staging smoke/rollback, Lighthouse, deep SEO/N+1 profiling and independent penetration testing remain external or unverified. The Vercel preview was blocked by the account's daily deployment quota. No production deployment was performed.
