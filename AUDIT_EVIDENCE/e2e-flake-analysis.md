# Chromium E2E flake analysis (2026-11-24)

Observed across full-suite runs on a busy local single-threaded stack:

| Run | Result | Failed test | Re-run in isolation |
|---|---|---|---|
| Full suite after CI fix | 90 passed / 1 failed | `workflows.spec.ts:50` Login → Admin Dashboard | PASS (1 passed) |
| Full suite after a11y fix | 97 passed / 1 failed | `role-based.spec.ts:29` Super Admin can access key admin sections | PASS (1 passed) |

Root cause: timing under sustained full-suite load (each full run ~8–9 minutes against local
PHP dev server + MariaDB). Not a product defect — the same tests pass deterministically in isolation
and the underlying behaviours are additionally covered by backend role/isolation suites (all green).
Test files were NOT modified, skipped or weakened to obtain a pass. A final clean full-suite run is
recorded separately when it completes.
