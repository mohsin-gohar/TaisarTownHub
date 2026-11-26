# Testing Report

Branch: `fix/workspace-transaction-lifecycle-20261126`
Date: 2026-11-26

## Latest successful GitHub Actions run
Run #641 — commit `d4ba0e4a2e82620a8cf95dc7c4b0b192f11b8e05`
https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45854444158

All five jobs completed successfully:
- Backend suite (PHP 8.3, MySQL 8): **540 passed, 0 failed, 4,527 assertions**.
- Frontend verify (Node 24): **173 tests, 0 failures**; TypeScript check, production build, 29 prerendered pages, 9-URL sitemap and public bundle guard passed.
- Browser UI regression (Playwright Chromium): **46 passed**.
- Browser cross-engine regression (Firefox + WebKit): **14 combined passed**.
- OpenAPI spec parse: passed.

## Browser coverage
The Chromium workflow runs cross-browser smoke, responsive, maps, interactive-elements, WhatsApp and public-page suites. Responsive tests exercise 11 widths: 320, 360, 375, 390, 414, 430, 768, 1024, 1280, 1440 and 1920px across public routes.
Firefox and WebKit use the cross-browser smoke suite. These tests do not constitute authenticated Super Admin/agency/staff role-journey coverage.

## Follow-up change after run #641
- Commit `2ab933cd8fe228c3c44b5f36ef365fc02c862b5b`: aligns dealer commission update validation with create validation, allowing Fixed/Custom PKR amounts above 100 while retaining a 100% cap.
- Commit `668c83edf7c141d332daa908e751e5f39734dfaf`: adds regression coverage for valid fixed amount and rejected percentage above 100.
- These commits have not yet been run by GitHub Actions. Do not mark them verified until a new run passes.

## Environment limitations
Docker image/Compose tests were not triggered for PR #22's audit-branch base. Real ClamAV, TLS, SMTP, DNS, payment-provider credentials, Lighthouse and production deployment were not tested. No production data or service was modified.
