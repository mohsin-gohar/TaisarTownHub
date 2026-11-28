# Phase 7 — Remaining Issues

Branch: `audit/opencode-phase-7-agency-erp-workflows`
Date: 2026-11-28

## Fixed in this branch

1. **P1 — Custom/system role privilege escalation** (`AgencyStaffController`):
   - `storeRole()` — delegation check
   - `updateRole()` — delegation check
   - `updateMember()` — delegation check for ALL roles (custom + system), owner exempt
   - 3 new regression tests + updated existing test

## Carried from critical repairs (already fixed, verified)

- Agency staff permission escalation (platform-level + delegation)
- Inquiry tenant isolation
- SecureFileStore write-result checks
- ClamAV incomplete-read fail-closed
- AgencyPromotionService concurrency locks
- MediaController::sell atomicity
- FinanceController::storeSchedules transaction
- Role/Sector/Block SoftDeletes

## Open — P2 (documented, not fixed)

1. **MapController::reorder() N+1** — individual updates in loop, admin-only, small dataset. Low impact. Not fixed to avoid unnecessary refactoring.

## Open — external blockers (owner-provisioned)

- ClamAV live daemon (fake-clamd tests only)
- Docker daemon (image builds, compose smoke unverified)
- TLS / SMTP / DNS / third-party keys
- Browser E2E persona journeys (requires running servers)
- Lighthouse performance (LCP 13.7s, TBT 1940ms, Perf 44 — unchanged from Phase 6)
- Systematic N+1 profiling across all controllers
- Deeper SEO validation

## Kilocode overlap

- Kilocode's active worktree (`kilo/gap-worktree`) runs tests against `taisartownhub_test` concurrently. No file overlap with this branch's changes (AgencyStaffController, AdminStaffSubscriptionTest, docs). Isolation achieved via separate DB. No changes overwritten.

## Verdict

No unresolved Phase 7 P0/P1 defects. All safely achievable code-level work complete.
