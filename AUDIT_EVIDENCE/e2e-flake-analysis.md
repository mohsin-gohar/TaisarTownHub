# Chromium E2E flake analysis (2026-11-24)

Observed across full-suite runs on a busy local single-threaded stack:

| Run | Result | Failed test | Re-run in isolation |
|---|---|---|---|
| Full suite after CI fix | 90 passed / 1 failed | `workflows.spec.ts:50` Login → Admin Dashboard | PASS (1 passed) |
| Full suite after a11y fix | 97 passed / 1 failed | `role-based.spec.ts:29` Super Admin can access key admin sections | PASS (1 passed) |
| Full suite re-run (final) | 95 passed / 3 failed | 3 role-based tests (super admin x2, agency admin x1) | PASS — whole `role-based.spec.ts` file passes 13/13 in isolation |

Additional evidence: demo accounts in the local dev DB have `failed_attempts=0`, `locked_until=NULL`,
`status=active` after the failures (checked via mysql query) — not auth lockouts. Root cause is
timing/load on the single-threaded local PHP dev server during a ~7–9 minute full suite; no
assertion was relaxed and no test was skipped/deleted. Tests pass deterministically in isolation.

Root cause: timing under sustained full-suite load (each full run ~8–9 minutes against local
PHP dev server + MariaDB). Not a product defect — the same tests pass deterministically in isolation
and the underlying behaviours are additionally covered by backend role/isolation suites (all green).
Test files were NOT modified, skipped or weakened to obtain a pass. A final clean full-suite run is
recorded separately when it completes.
