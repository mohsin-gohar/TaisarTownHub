# Tenant Isolation Audit

**Branch:** `fix/complete-audit-20261117` · **Date:** 2026-11-17

## Resolved agency context

`AgencyContext` validates the requested `X-Agency-Id` against the authenticated user's memberships and workspace approval, then stores the trusted value in a request attribute. Controllers must read it through `AuthorizesApi::agencyId()`; client body values are never authoritative. This layer is sound; the confirmed bugs were controllers ignoring it.

## Verified with two independent agencies

| Module | Before | After |
|---|---|---|
| Enterprise finance (`FinanceController`) | `findScoped()` already scoped list/detail/update/void/payments/commissions/reports | unchanged; extended with new regression tests |
| Legacy transactions (`TransactionController`) | global list, NULL-tenant creates, foreign property/dealer accepted | scoped list, stamped ownership, scoped `exists` validation |
| Dealer files | no agency binding; reports crossed agencies | dealer identity bound via active membership; cross-agency report denied; platform report needs `admin.view_reports` |
| Support data | ticket list filtered by permission lane and agency | verified by browser in prior increment; no new change |
| Saved searches | ownership from authenticated user only | verified by backend isolation tests in prior increment |

## Tests executed

- `LegacyTransactionIsolationTest` — 5 tests / 18 assertions, pass.
- `FinanceIsolationTest` — 16 tests / 87 assertions, pass.
- `DealerFilesAuthorizationTest` — 7 tests / 17 assertions, pass.
- Pre-existing `WorkspaceHardeningTest`, `CrossAgencyCapabilitiesTest`, `FinanceReportsTest`, `StorageSecurityTest`, `PlanLimitEnforcementTest` — pass in full suite.

## Gaps / not reproducible

- True simultaneous (multi-process) concurrency for deal numbers and dealer-file sales was not executed; SQLite serialises tests and `lockForUpdate()` is a no-op there. The unique index + retry path is exercised logically, not under real MySQL contention.
- `X-Agency-Id` missing/invalid/switched, inactive membership, pending/suspended agency and revoked access are covered by the pre-existing `WorkspaceApprovalTest`/`WorkspaceHardeningTest` matrix (all pass in full suite).
- Dealer-only deal visibility policy remains inconsistent between legacy (self-only) and finance (whole agency) APIs. Encoded as a known policy decision, not silently changed.
