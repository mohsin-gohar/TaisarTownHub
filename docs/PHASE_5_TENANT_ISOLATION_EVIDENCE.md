# Phase 5 Tenant Isolation Evidence

Branch: `audit/phase-5-final-stabilization` @ `fc5ad34`

## Test suites
- `WorkspaceTenantMatrixTest`: 13 agency-scoped collections × read/write/replace isolation — all pass
- `FinanceIsolationTest`: 19 tests — agency-scoped payments, commissions, transactions — all pass

## Verified
- Agency A cannot read Agency B's rows (404/403)
- Agency A cannot mutate Agency B's rows
- Replace mode scoped to writer's agency only
- Soft-deleted rows excluded from workspace dumps
- Finance records tenant-scoped

## Evidence
- Backend: 466 passed, 0 failed (includes tenant isolation tests)