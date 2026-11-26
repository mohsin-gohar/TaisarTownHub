# Complete Audit Report

Repository: `mohsin-gohar/TaisarTownHub`
Repair branch: `fix/workspace-transaction-lifecycle-20261126`
Date: 2026-11-26

## Verified repository state
- PR #22 is open and Draft; base is `audit/full-repository-hardening-20261126`, not `main`.
- Main baseline observed: `f26b070e2f3ccae60ac9e033dc41065a65eddfa8`; main was not changed or merged.
- Latest fully verified GitHub Actions run before the follow-up dealer validation fix: run #641, commit `d4ba0e4a2e82620a8cf95dc7c4b0b192f11b8e05`, all five jobs successful.
- Follow-up code fix: `2ab933cd8fe228c3c44b5f36ef365fc02c862b5b`; validator syntax correction: `50cf80e91fe0c8b30cfd6079402aa3dfb2b3bc83`; regression test commits: `668c83edf7c141d332daa908e751e5f39734dfaf` and `8b660344ca71012f6379f4ffd49ed490694eea84`. These later commits have not yet been covered by a CI run.

## Findings fixed
1. Dealer creation referenced an undefined effective commission type; defined the type before validation.
2. Dealer commission validation was inconsistent: create allowed fixed/custom PKR amounts above 100, but update capped every type at 100. Update now applies the same type-aware upper bound as create: Percentage is capped at 100; Fixed/Custom can use the configured whole amount bound. A regression test covers both cases. **This follow-up is awaiting CI verification.**
3. Platform super-admin commission updates were blocked by mandatory agency context; nullable platform scope is used with permission checks.
4. Workspace platform lookups incorrectly applied `agency_id IS NULL`; platform scope now resolves agency-owned rows while tenant users remain scoped.
5. Workspace metadata-only payment/commission edits were incorrectly rejected by movement guards.
6. Workspace cancellation gates omitted `cancel_transactions` and `legacy.cancel_transactions`.
7. Frontend collected-funds calculation double-counted recorded advances when a ledger already represented them; legacy fallback/refund handling was corrected.

## Latest verified CI evidence — run #641
Commit: `d4ba0e4a2e82620a8cf95dc7c4b0b192f11b8e05`
- Backend PHP 8.3 + MySQL 8: 540 passed, 0 failed, 4,527 assertions.
- Frontend verify: 173 tests passed, 0 failed; TypeScript, build, 29 prerendered pages, 9-URL sitemap and public bundle guard passed.
- Chromium Playwright: 46 passed.
- Firefox + WebKit: 14 combined passed.
- OpenAPI YAML validation passed.
Run: https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45854444158

## Scope and limitations
- Source inspection was risk-ranked and targeted; this was not a literal line-by-line review of every tracked file.
- Public browser routes were exercised, including responsive checks across 11 widths (320–1920px). Authenticated admin/agency role journeys remain unverified because seeded role accounts were unavailable.
- Docker image/compose tests did not run for PR #22 because its base is the audit branch; the Docker workflow only triggers for main/QA targets. The older main run is not evidence for this branch's current Docker changes.
- Lighthouse, deeper structured-data/OG validation, systematic N+1 measurement, real ClamAV, TLS, SMTP, DNS, payment-provider credentials and live deployment remain unverified or externally dependent.
- The new dealer update validation fix and its added regression test are after run #641 and must pass CI before being marked verified.

## Integration safety
The repair branch is not a direct fast-forward of main: GitHub compare reports 550 commits ahead, 2 behind, and 139 changed files relative to main. PR #22 targets the audit branch, not main. Do not merge this branch directly into main; perform a deliberate integration/rebase review first.
