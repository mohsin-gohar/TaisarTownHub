# CODEX — Blockers, Superseded Claims and Honest Status

**Date:** 2026-11-19
**Purpose:** single place that states what is actually proven, what is stale in older reports, and what remains blocked.

---

## 1. Repository state

- Local branch: `main`; HEAD `aa7b9c7` (the previous report's `2270193` is stale).
- **No commit, no push, no force-push, no reset** by the assistant. All fixes are uncommitted.
- Working tree changes: `ci.yml` quote fix, production seeder guard, composite mapping + verified backup/rollback, Docker Compose/Nginx multi-backend changes, entrypoint migration mode, 2 new backend test files, 1 new responsive E2E spec, plus the two rewritten `CODEX_*` reports.

---

## 2. What is genuinely verified now

| Area | Evidence | Result |
|---|---|---|
| Backend full suite | `php artisan test` | **282 passed / 2307 assertions** |
| Legacy/seeder fixes | targeted filter | **11 passed / 60 assertions** |
| Frontend | `npm test`, `npm run verify` | **136 passed**; full verify chain pass |
| Playwright | `npx playwright test` | **75 passed / 0 failed** (incl. 3 responsive) |
| Responsive | 360/768/1280 px × 11 routes | no page-level horizontal overflow |
| CI YAML | `yaml.safe_load` | parseable after quoting the two `run` scalars; **CI rerun not done** |
| Compose/Nginx structure | static assertions | no pinned backend/host port; one-shot migrate; shared vault; 3 LB locations with resolver; JSON 503 fallback |
| Backup integrity | `LegacyBackfillBackup::verify()` on real `.sqlite.gz` | format `sqlite-database-copy`, 84 tables, SHA-256 equals `backup.ps1` manifest |
| Restore drill | `scripts/restore-drill.ps1` into scratch dir | **PASS**, all sampled table counts match live DB |
| Tenant isolation / security | 282-test backend suite + `security.spec.ts` / `role-based.spec.ts` | pass; no regression observed |

---

## 3. Corrections to older reports

| Old claim | Current correction |
|---|---|
| `HEAD 2270193` | Latest user commit is `aa7b9c7`; `2270193` is historical. |
| Backend `276 passed / 2268 assertions` | **282 passed / 2307 assertions**. |
| Playwright `72 passed` | **75 passed** (3 new responsive viewport tests). |
| "CI missing-`.env` fix" | Fixed the earlier 267 warnings, **then** commit `aa7b9c7` introduced a YAML syntax error that failed CI outright. That parse error is now fixed locally but needs a push to rerun. |
| "Docker/Nginx load balancing" | Previously a single backend/upstream. Now real multi-replica Compose + Nginx config exists, **statically verified only** (no runtime). |
| "Backup restore not proven" | Scratch-directory restore drill now **PASS** for the local SQLite DB. Production/MySQL restore is still unproven. |
| Any implication that ClamAV, Redis, Docker, multi-instance runtime, or deployed Lighthouse were proven | Still **not proven** on this host. |

`QODER_*` reports are historical and left in place; `CODEX_*` reports supersede them where they conflict.

---

## 4. Still blocked / not claimed

- **CI green:** requires commit + push; explicit permission not given. The workflow is locally valid but no GitHub rerun has happened.
- **Docker runtime:** `docker` not installed → no Compose up, no scaled replicas, no real load-balancer traffic test, no container healthcheck execution, no ClamAV scan.
- **Nginx runtime:** `nginx` binary not installed → config syntax checked by static assertions only, not `nginx -t` or a live proxy test.
- **Redis runtime:** no `redis-server`/`redis-cli` → no shared cache/session/queue continuity test across replicas.
- **MySQL runtime:** no local MySQL server/client usable → no local MySQL full-suite run; the CI `integration-mysql` job exists but needs a push.
- **Legacy backfill apply:** never run against the local live DB. Still 77 unresolved ownership rows; composite mapping must be human-approved before any `--apply`.
- **Production backup restore / rollback:** journal rollback is proven in tests; scratch restore drill is proven for local SQLite. No deployed MySQL restore or production rollback was performed.
- **Deployed environment:** no HTTPS/DNS/hosted app → no field Core Web Vitals, Lighthouse, or production performance proof.
- **Reference screenshot:** visual sign-off from the user is still required.
- **"100% complete" / "production-ready":** not claimed.

---

## 5. Safety constraints honoured

- No destructive DB command on live data; backfill apply was not run. Test DB rows only were stamped and rolled back.
- No commit/push without explicit permission.
- No user files overwritten or deleted; new files are additive and generated rollback journals use unique random suffixes under `storage/app`.
- Approved navy/black + gold theme, typography, layout, spacing, cards and animations untouched (only a test file added to the frontend).
- Docker/XAMPP processes were not killed; the local MySQL installation was not touched.
- Backup/restore drill wrote only to a temp scratch directory, never over the live database.
