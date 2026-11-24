# FINAL MULTI-TENANT ISOLATION REPORT — TaisarTownHub

**Date:** 2026-11-16
**Code commit:** `a8d217e`

---

## 1. Verdict

**Code/test-level isolation: PASS. Production sign-off: BLOCKED** pending an approved legacy-ownership mapping/backup and a live backend run.

---

## 2. Executed Isolation Evidence

| Suite | Tests | Assertions | Result |
|---|---:|---:|---|
| `WorkspaceApprovalTest` | 28 | 773 | PASS |
| `WorkspaceHardeningTest` | 6 | 20 | PASS |
| `FinanceIsolationTest` | 12 | 71 | PASS |
| `Finance` (full finance filter) | 24 | 172 | PASS |
| Full backend suite | 210 | 1,814 | PASS |

Verified behaviours:

- Registration creates a **pending** agency/owner with no workspace access; platform approval unlocks the original token and uploads.
- Tokens are blocked as soon as the agency is pending, inactive, unverified, rejected, suspended, or deleted.
- Inactive membership never falls back to legacy platform privileges.
- An explicit platform-admin grant survives agency membership changes but not account suspension.
- Merge-save never deletes rows absent from the payload; replace mode requires delete permission and is scoped to the caller’s agency.
- A member cannot overwrite another agency’s row; client-supplied tenancy fields are ignored.
- Finance reads/writes return 404 across agencies; commission rows are agency-scoped; deal numbers remain unique.
- Denied users see public listings only, not the privileged agency view.

---

## 3. Legacy Ownership

Read-only legacy ownership report:

| Category | Count |
|---|---:|
| Unowned legacy records | **110** |
| Confirmed | **26** |
| Ambiguous | **0** |
| Unresolved | **84** |

- Unowned rows remain hidden from agency workspaces (`agency_id IS NULL` is not returned to agency members).
- **No automatic assignment, stamping, backfill, or `--apply` was performed.**
- No production data was modified during this audit.
- A business-approved per-record mapping and a verified backup are still required before any ownership backfill.

---

## 4. Caveats / Blockers

1. The Vercel deployment is frontend-only; no live multi-tenant API exists there. Isolation was proven by the Laravel test suite, not by a live cross-tenant browser walk.
2. Legacy ownership for 84 unresolved records is not resolved. Hiding them is safe but not a permanent data-governance solution.
3. Docker/MySQL/Redis/ClamAV runtime isolation was not exercised (`docker` CLI unavailable).
4. Production database backups/restore tests were not executed.

---

## 5. Recommendation

Keep the current hide-unowned behaviour. Do not backfill until the business supplies an approved mapping and a verified backup. Re-run `WorkspaceApprovalTest`, `WorkspaceHardeningTest`, and `FinanceIsolationTest` against the production-like Docker stack before tenant-isolation sign-off.
