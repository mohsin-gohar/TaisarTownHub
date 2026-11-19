# CODEX — Blockers, Superseded Claims and Honest Status

**Date:** 2026-11-19 (updated)  
**Purpose:** single place that states what is actually proven now, what older reports got wrong/stale, and what remains blocked.

---

## 1. Repository state (current)

- Local branch: `main`; **HEAD `77cb8a5`** (`fix: LegacyTenancyResolver schema-aware owner column resolution`), clean, in sync with `origin/main`.
- `CODEX_*`/`QODER_*` files with older HEADs, test counts, or CI status are **historical**. Do not treat them as current truth.
- No commit/push made by the assistant during this update.

---

## 2. What is genuinely verified

| Area | Evidence | Result |
|---|---|---|
| GitHub CI (latest `main`) | [Actions run 44580103286](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/44580103286) | **4/4 jobs PASS**: Backend suite (PHP 8.3/SQLite), Backend suite on MySQL 8 (integration), Frontend verify (Node 22), OpenAPI spec parses |
| Backend (SQLite) | CI + local `php artisan test` (this session) | **283 passed / 2311 assertions** |
| Backend (MySQL 8) | CI integration-mysql job | **283 passed / 2311 assertions** on MySQL 8 |
| Legacy resolver MySQL/schema fix | `backend/app/Support/LegacyTenancyResolver.php`, `backend/tests/Feature/LegacyTenancyResolverTest.php` | Schema-aware owner column resolution (`agency_id` / `agencyId` / null fallback); regression coverage for `agency_videos.agencyId` twin; CI MySQL job green |
| Frontend | CI + local `npm test`, `npm run lint`, `npm run build`, `npm run check:bundle` | **136 tests pass** (9 suites); lint/build/prerender (27 pages, sitemap 8 URLs, robots 6 disallows)/bundle privacy check **pass** |
| Seeder/backfill/backup safety (code + tests) | `DatabaseSeeder`, `LegacyBackfillCommand`, `LegacyBackfillBackup`, related tests in repo | Production seeds catalog only (demo fixtures/accounts guarded); composite mapping, verified backup, checksummed rollback journal, idempotent rollback paths covered by tests (as exercised in passing suites) |
| Docker/Nginx architecture (static) | `docker-compose.yml`, `nginx/nginx.conf`, `backend/docker/entrypoint.sh` | Multi-replica-capable config present (no pinned backend host port/name, one-shot migrate, healthcheck, shared private vault, resolver/proxy failover/JSON 503 fallback) — **static only** |

---

## 3. Corrections to older/stale claims

| Stale claim | Current correction |
|---|---|
| HEAD `aa7b9c7` / `2270193` / uncommitted fixes | Current HEAD is **`77cb8a5`**; prior fixes are committed and CI-verified. |
| CI rerun pending / workflow parse failure unresolved | Latest CI run **44580103286 is green (4/4)**. Earlier YAML/parse failures are historical. |
| Backend `276 or 282 passed / 2268 or 2307 assertions` | Current verified count: **283 passed / 2311 assertions** (SQLite & MySQL in CI). |
| "No local MySQL full-suite run possible until push" as current blocker | MySQL 8 integration suite **ran and passed in CI** for `77cb8a5`. Local MySQL runtime may still be unavailable on this machine. |
| Playwright `72/75 passed` as latest-commit CI proof | Prior local runs recorded 72/75; **CI does not run Playwright**. Re-run on latest commit in a healthy env is still required for fresh browser QA evidence. |
| Docker/Redis/ClamAV/LB "verified" | **Not runtime-verified**. Only configuration/static intent is present. |
| Production readiness implied | **Not claimed.** |

`QODER_*` and older `CODEX_*` snapshots are retained as history but must not be cited as current status.

---

## 4. Still blocked / not claimed

- **Docker runtime:** `docker` unavailable locally → no `compose up`, no real multi-replica scaling, no live Nginx LB/failover/health-removal, no container restart/recovery proof.
- **Redis runtime:** no real Redis-backed cache/queue/session continuity across replicas verified.
- **ClamAV runtime:** no real daemon/EICAR, no fail-closed/timeout/quarantine runtime proof.
- **Shared storage/persistence runtime:** volume sharing and persistence across real containers not runtime-proven.
- **Production deployment:** no verified live Laravel API + production MySQL + domain/HTTPS + env/monitoring/alerting.
- **Playwright/browser QA (latest, comprehensive):** full E2E suite and exhaustive manual flows (all roles/dashboards/buttons/forms/CRUD/cross-tenant, incl. 390px) not re-run end-to-end in this environment with screenshot evidence after every latest change.
- **Performance/SEO in production:** no deployed Lighthouse/Core Web Vitals, no live indexing/canonical validation on real domain, no realistic load test.
- **Legacy data backfill apply:** dry-run/reporting covered; `platform:legacy-backfill --apply` not run against live data (77 unresolved rows still require human-approved mapping).
- **"100% complete" / "production-ready":** **not claimed**.

---

## 5. Safety constraints honoured

- Approved navy/black + gold UI theme and existing working behavior preserved.
- No destructive DB operations; no backfill apply on live DB.
- No user files deleted/overwritten; no force-push/reset.
- No commit/push performed as part of this documentation update.
- No mocked/static-only checks presented as runtime proof.
