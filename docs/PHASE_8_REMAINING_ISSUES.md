# Phase 8 — Remaining Issues

Branch: `audit/phase-8-super-admin-operations`
Date: 2026-11-29

## Fixed / verified in this branch

1. **Phase 7 outstanding #1** — 2× `CrmLeadsTest` CSV export failures: test used `getContent()` on `StreamedResponse` (always `false`). Changed to `streamedContent()`. Production CSV export was correct. **FIXED_AND_VERIFIED.**
2. **Phase 7 outstanding #2** — `TransactionFinanceDataIsolationTest` cross-agency failure: pre-created tokens caused Sanctum `RequestGuard` user caching across in-process requests. Changed to fresh token before each user-switched request. Production tenant isolation verified correct. **FIXED_AND_VERIFIED.**
3. **P1 payment reconciliation** — duplicate `receipt_no` threw uncaught DB unique violation → 500. Added `Rule::unique` validation → 422. Regression test added. **FIXED_AND_VERIFIED.**
4. **Phase 8 role matrix** — 6 new tests covering all platform surfaces. **VERIFIED.**

## Pre-existing / external blockers (10 full-suite failures)

All 10 reproduce identically on the exact baseline with changes stashed:
- FK constraint failures from migration `2026_11_28_000002_complete_legacy_foreign_keys_and_indexes.php`: `transactions_property_fk` (synthetic propertyId) and `transactions_dealer_fk` (synthetic dealer ID) — documented in PR #37 (Kilocode gap closure) as pre-existing test-fixture issues.
- Precision assertion `'5000.00'` vs `5000` in `AdminStaffSubscriptionTest`.
- `DatabaseSchemaContractTest`, `FinanceIsolationTest`, `LegacyUnownedDataHandlingTest` assertions.

Owner/dependency: repository maintainer; these are NOT Phase 8 defects and were NOT weakened or skipped. Kilocode PR #37 addresses a subset; not merged into main.

## Architecture limitations (documented, not defects)

- **CMS** has no dedicated CRUD API/table — single workspace business-record blob (`cms/default`) with `edit_cms` gate and allowlisted keys. No per-field versioning/audit. PARTIALLY VERIFIED.
- **Audit log** `updateSetting()` records old/new values verbatim; safe with current catalog (no secrets), but operators should not store secrets in `system_settings`.
- **MapController::reorder()** N+1 (admin-only, small dataset) — inherited from Phase 7.

## Not tested (no evidence claimed)

- Browser E2E persona journeys (no live Playwright session)
- Docker image builds / compose smoke (no daemon)
- ClamAV live scanning (no daemon)
- TLS / DNS / SMTP / third-party keys
- Lighthouse re-measurement / N+1 profiling / deep SEO

## Kilocode / other-agent overlaps

- No files from PR #37, PR #38, or other active branches modified. No merges performed.
- Separate worktree + separate `taisartownhub_phase8` DB avoided the concurrent-test DB corruption seen in earlier phases.

## PR creation

`gh` CLI unavailable and no `GITHUB_TOKEN`/`GH_TOKEN` in environment (git uses Windows Credential Manager). Branch pushed; PR must be opened manually from GitHub web UI:
- Base: `main` (review only — do not merge)
- Head: `audit/phase-8-super-admin-operations`
- Title: `Phase 8 — Super-admin Operations Completion (OpenCode)`

## Verdict

**PHASE 8 PARTIALLY VERIFIED** — all nine scope areas reviewed, server-side authorization matrix proven, three Phase 7 outstanding failures and one P1 reconciliation defect fixed with regression tests. Backend 495 passed / 10 pre-existing failures; frontend 160/0; audits clean. Browser E2E and infrastructure items remain BLOCKED/NOT TESTED with evidence and owners documented above.
