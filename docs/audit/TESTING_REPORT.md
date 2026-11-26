# Testing Report

**Integrated QA commit:** `1e43393fc728fb9ce11746869a331f5e852d4b58`  
**Release path:** PR #11 merged into QA; PR #5 carries the integrated QA branch toward main.

## GitHub Actions CI

Run: https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45864429885

| Gate | Result |
|---|---:|
| Backend PHP 8.3 + MySQL 8 | 547 passed; 4,563 assertions |
| Frontend tests / lint / TypeScript / build | 176 passed |
| Chromium Playwright | 46 passed, 2 skipped |
| Firefox + WebKit | 14 passed |
| OpenAPI parse | Passed |

SEO build generated 29 prerendered pages and a 9-URL sitemap. The public bundle guard passed with no private ERP data or demo credentials found in public chunks.

## Docker CI

Run: https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45864438598

- Backend/frontend production images and nginx configuration checks passed.
- MySQL backup/restore drill passed.
- Full-stack readiness and two-replica backend checks passed.
- Shared private vault/quarantine volume checks passed.
- API smoke and seeded role-based browser matrix passed.
- 10-worker load: 90,801 requests / 10 seconds, 9,080.1 req/s, zero 5xx.
- Concurrent transaction integrity: 8/8 created with unique deal numbers.

## Additional probe not accepted as a green gate

The separate PR #9 workflow probe recorded 15/20. The failed steps were caused by missing test setup for captcha, trial staff quota, dealer agency context and malware scan completion before sharing. This probe was not merged into the release workflow and is not represented as passed.

## Remaining operational verification

Live DNS/TLS, SMTP, OAuth/payment/monitoring credentials, production ClamAV, production backup target, production data migration, staging smoke/rollback, Lighthouse, deep SEO and independent penetration testing remain external or unverified. No production deployment was performed.