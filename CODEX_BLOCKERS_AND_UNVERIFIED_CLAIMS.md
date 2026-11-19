# CODEX — Blockers, Superseded Claims and Honest Status

**Date:** 2026-11-19  
**Purpose:** single place that says what is actually proven, what is stale in older reports, and what remains blocked.

---

## 1. Repository state

- Local branch: `main`
- HEAD at the start of this work: `9a2646b`; latest user commit `2270193` (the older `QODER_COMPLETE_SOURCE_AUDIT.md` and `QODER_UI_THEME_REGRESSION_REPORT.md` cite `a53e3c4`, which is **stale**).
- **No commit, no push, no force-push, no reset.** All work is in the working tree.
- Uncommitted changes include backend governance/rate-limit fixes, frontend theme migration, E2E isolation helpers, the CI missing-`.env` warning fix, the shared legacy tenancy resolver and its tests, and 5 RBAC capability-boundary E2E tests.

---

## 2. What is genuinely verified now

| Area | Evidence | Result |
|---|---|---|
| Backend full suite | `php artisan test` | **276 passed / 2268 assertions** (41.40 s, zero warnings) |
| Backend legacy resolver (new) | `php artisan test --filter=LegacyTenancyResolverTest` | **5 passed / 21 assertions** |
| Frontend unit tests | `npm test` | **136 passed / 0 failed** |
| Frontend gate | `npm run verify` | lint + tests + build + prerender + bundle check all pass |
| Playwright E2E | `npx playwright test --workers=2` | **72 passed / 0 failed** (incl. 5 new RBAC capability tests) |
| CI missing-`.env` warning fix | `.github/workflows/ci.yml` | root cause reproduced locally; minimal `.env` → 271/2247 zero warnings (final 276/2268). **CI rerun still needs a push — not done.** |
| Public marketplace shape | governed `/api/properties` | exactly 4 genuine approved public sale listings; legacy `/api/catalog` returns 0 properties by design |
| Browser QA | `qa-audit.mts` | 84 checks, 0 console errors, 0 page errors, 0 failed requests, no overflow/broken images |
| Tenant isolation | `security-probe.mts` | **21 passed / 0 failed** after applying the pending additive `add_vault_scan_lifecycle` migration; see `CODEX_SECURITY_TENANT_ISOLATION_REPORT.md` |
| Theme probe | `qa-theme.mts` | light inventory band + white cards present, no horizontal overflow, 0 console errors |
| SEO artifacts | `dist/` inspection | 27 prerendered pages, 8-URL sitemap, 6 disallow robots rules, correct canonical/JSON-LD including fixed `/admin-login` |
| Rate-limit regression | `RateLimitTest` | 100 sequential public catalog reads pass (the old global `api` fallback limiter had capped them at 60) |
| Inquiry honesty | live contact form | success shown only after a real API response; 1 real `Pending` inquiry observed in DB, then removed |

---

## 3. Corrections to older `QODER_*` reports

The older reports were generally cautious and explicitly refused production-readiness. I did **not** find proof of invented Lighthouse/ClamAV/Redis numbers in the files I inspected. The issues are stale facts and one now-invalid statement:

| Old claim | Current correction |
|---|---|
| `HEAD a53e3c4` | Current local HEAD is `2270193` (latest user commit). |
| Backend `271 passed, 2152 assertions` / `271 passed, 2247 assertions` | Now **276 passed, 2268 assertions** (5 new legacy tenancy tests; 21 new assertions). |
| Frontend `133 unit tests` | Now **136 unit tests**. |
| Playwright E2E `67 passed` | Now **72 passed** (5 new RBAC capability tests). |
| `QODER_UI_THEME_REGRESSION_REPORT.md`: "No source-wide UI redesign was performed" | **Superseded.** A full palette migration and new light listings theme were performed on 2026-11-19; see `CODEX_THEME_REDESIGN_REPORT.md`. |
| `QODER_PAGE_BY_PAGE_BROWSER_AUDIT.md`: live frontend at `:3002` | Current local E2E/QA frontend is `:4173`; backend `:8080`. |
| `QODER_REMAINING_BLOCKERS.md`: authenticated role workflows "INCOMPLETE" | Superseded by the Playwright role matrix; final result is recorded in `CODEX_FINAL_VERIFICATION_REPORT.md`. |
| Any implication that ClamAV, Redis, Docker, multi-instance or deployed Lighthouse were proven | Still **not proven**. Docker/Redis/ClamAV executables are unavailable on this host; no deployed environment exists. |

These `CODEX_*` reports supersede the `QODER_*` files where they conflict. The `QODER_*` files are left in place as historical records, not deleted.

---

## 4. Still blocked / not claimed

- **Docker runtime:** `docker` command not found → no Compose stack, no ClamAV daemon, no EICAR scan, no multi-container test.
- **Redis runtime:** no `redis-server`/`redis-cli` → no shared cache/session/queue/lock or multi-instance continuity test.
- **MySQL runtime:** disposable instance was stopped; XAMPP MySQL on 3306/3307 must not be touched → no fresh MySQL migration/concurrency run locally (the repository CI job exists but was not rerun here).
- **CI green-with-warnings fix:** root cause found and the workflow patched locally, but a CI rerun cannot happen until the changes are committed/pushed. **Explicit user permission is required — not done.**
- **Legacy ownership backfill:** only the read-only resolver/report was fixed and verified; 77 rows remain unresolved for human mapping. `platform:legacy-backfill --apply` was **not** run against the local DB; no ownership was stamped.
- **Deployed environment:** no HTTPS/DNS/hosted app → no real Lighthouse, field Core Web Vitals, backup restore, or production rollback proof.
- **Reference screenshot:** this model cannot view images. The redesign is based on programmatically extracted colours; the user must visually confirm it.
- **"100% complete" / "production-ready":** not claimed. Code and local runtime gates pass; infrastructure and visual sign-off do not.

---

## 5. Safety constraints honoured

- Original `APP_KEY` restored and never regenerated (encrypted dealer CNIC / MFA data depends on it).
- No destructive DB command, no deletion of real data. Test listings were made `visibility=private`, reversible.
- Docker/XAMPP processes were not killed.
- No push to `main` without explicit permission.
- Login rate-limit probes use isolated TEST-NET addresses; shared IP buckets were not poisoned by deliberate failures.
