# TaisarTownHub — Current Release Readiness

Date: 2026-11-26

## Repository and PR state
- Repair branch: `fix/workspace-transaction-lifecycle-20261126`
- This status page is maintained on the repair branch; see the branch HEAD and PR #22 for the latest documentation commit.
- Main baseline: `f26b070e2f3ccae60ac9e033dc41065a65eddfa8` (unchanged).
- PR #22: open, Draft, unmerged; head branch `fix/workspace-transaction-lifecycle-20261126`, base branch `audit/full-repository-hardening-20261126`.
- No merge into main and no production deployment/database modification.
- Compare against main: 564 commits ahead, 2 behind, 139 changed files. This repair is part of a stacked open-PR chain (PR #22 targets the head of PR #11; other prerequisite PRs #5 and #9–#16 remain open). This is a substantial divergence; do not merge directly into main without a deliberate integration/rebase review.

## Latest verified CI
GitHub Actions run #641 on commit `d4ba0e4a2e82620a8cf95dc7c4b0b192f11b8e05` completed successfully:
- Backend PHP 8.3 + MySQL 8: 540 passed, 0 failed, 4,527 assertions.
- Frontend verify: 173 passed, 0 failed; TypeScript/build/bundle checks green; 29 prerendered pages and 9 sitemap URLs.
- Chromium Playwright: 46 passed.
- Firefox + WebKit: 14 combined passed.
- OpenAPI parse: passed.
Run: https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/45854444158

## Additional follow-up fixes
- `2ab933cd8fe228c3c44b5f36ef365fc02c862b5b`: fixed DealerController update validation so Fixed/Custom commission amounts can exceed 100 PKR while Percentage remains capped at 100%.
- `668c83edf7c141d332daa908e751e5f39734dfaf`: added regression test for that behavior.
- `50cf80e91fe0c8b30cfd6079402aa3dfb2b3bc83`: corrected the closure type hint; `8b660344ca71012f6379f4ffd49ed490694eea84` made the regression assertion independent of JSON numeric casting.
- All dealer follow-up commits were made after CI run #641. They require a completed green CI run before the fix can be marked verified.

## Responsive and browser coverage
The Chromium workflow passed 46 tests, including responsive checks across 11 viewport widths (320, 360, 375, 390, 414, 430, 768, 1024, 1280, 1440, 1920px) on public routes. Firefox and WebKit cross-browser smoke passed 14 combined tests. Authenticated Super Admin, agency-owner and staff role journeys remain unverified because seeded demo accounts were unavailable.

## Remaining blockers
1. Complete CI against the latest source-fix and documentation commits; run #641 does not cover commits after `d4ba0e4`.
2. Run Docker image/Compose CI for an appropriate PR target; PR #22 currently targets the audit branch, so Docker CI was not triggered for it.
3. Provide seeded safe demo accounts for authenticated role-journey browser tests.
4. Run Lighthouse and deeper performance/N+1 measurement.
5. Complete deep SEO/structured-data/Open Graph validation.
6. Verify live ClamAV, TLS, DNS, SMTP, payment-provider and monitoring integrations with owner-controlled credentials.
7. Complete protected production-data audit, backup/restore and staging/rollback rehearsals before launch.

## Inspection scope
The repository was inspected in risk-ranked batches. The coverage report records targeted reads and exclusions; this is not a claim that every tracked file has been read line by line. See `docs/audit/FILE_INSPECTION_COVERAGE.md`.

## Release decision
The repair branch has substantial automated verification, but **production launch is not yet cleared**. PR integration is not a simple merge: resolve the stacked PR dependencies and 564-commit divergence from main in a planned sequence. External infrastructure, authenticated role journeys, deeper operational/performance checks, integration review against main, and CI for the follow-up dealer fix remain open.