# Testing Report

Branch: `audit/full-repository-hardening-20261126`
Date: 2026-11-26

## Latest successful GitHub Actions run
Run #652 — commit `8ffd9197d78738202950521663cf39f129a3053f`
https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45859982724

This run includes the dealer commission validation follow-up and all five jobs completed successfully.

All five jobs completed successfully:
- Backend suite (PHP 8.3, MySQL 8): **540 passed, 0 failed, 4,527 assertions**.
- Frontend verify (Node 24): **173 tests, 0 failures**; TypeScript check, production build, 29 prerendered pages, 9-URL sitemap and public bundle guard passed.
- Browser UI regression (Playwright Chromium): **46 passed**.
- Browser cross-engine regression (Firefox + WebKit): **14 combined passed**.
- OpenAPI spec parse: passed.

## Browser coverage
The Chromium workflow runs cross-browser smoke, responsive, maps, interactive-elements, WhatsApp and public-page suites. Responsive tests exercise 11 widths: 320, 360, 375, 390, 414, 430, 768, 1024, 1280, 1440 and 1920px across public routes.
Firefox and WebKit use the cross-browser smoke suite. These tests do not constitute authenticated Super Admin/agency/staff role-journey coverage.

## Follow-up dealer validation change — CI verified
- `2ab933cd8fe228c3c44b5f36ef365fc02c862b5b`: aligns dealer commission update validation with create validation, allowing Fixed/Custom PKR amounts above 100 while retaining a 100% Percentage cap.
- `668c83edf7c141d332daa908e751e5f39734dfaf`: adds regression coverage for valid fixed amount and rejected percentage above 100%.
- `50cf80e91fe0c8b30cfd6079402aa3dfb2b3bc83`: corrects the validator closure type hint.
- `8b660344ca71012f6379f4ffd49ed490694eea84`: removes a brittle assertion that depended on JSON numeric casting.
- All follow-up commits above are included in run #652 and passed the backend, frontend, Chromium, Firefox/WebKit and OpenAPI jobs.

## Environment limitations
PR #22 was merged into this audit branch after its run #652 CI passed. Docker image/Compose verification must run on the updated audit release candidate before it is promoted through PR #11. Real ClamAV, TLS, SMTP, DNS, payment-provider credentials, Lighthouse and production deployment were not tested. No production data or service was modified.


## Integration update
- PR #22 (`fix/workspace-transaction-lifecycle-20261126`) merged into this audit branch at `af3220edfcd0e414b9594131bca852450224c223` after run #652 passed.
- PR #11 now points at the updated audit branch. The prior run #569 and Docker run #569 verified the pre-merge audit SHA `bae7c431`; the merged SHA still requires fresh CI and Docker CI evidence.
