# QODER Complete Source Audit

> **Historical snapshot (superseded):** this document records an earlier audit state. Do **not** treat its HEAD/test counts/CI status as current. For current verified status see `CODEX_FINAL_VERIFICATION_REPORT.md` and `CODEX_BLOCKERS_AND_UNVERIFIED_CLAIMS.md`.

## Identity (at time of this snapshot)
- Branch: `main`
- HEAD: `a53e3c456cf7fe4e14d7ca73690bb5c9300557f3`
- Working tree: modifications remain; no reset, commit or push performed.
- Tracked files: 582

## Current status addendum (2026-11-19, updated)
- **Current HEAD:** `77cb8a5` (see `git log`); local verification in this pass performed against `a42c49a` docs state / `77cb8a5` code baseline.
- **CI (latest):** [Actions run 44580103286](https://github.com/mohsin-gohar/TaisarTownHub/actions/runs/44580103286) — **4/4 PASS** (Backend SQLite, Backend MySQL 8 integration, Frontend verify, OpenAPI).
- **Verified counts now:** Backend **283 passed / 2311 assertions** (SQLite locally & MySQL in CI); Frontend **136 tests** pass, lint/build/prerender(27 pages)/bundle checks pass.
- **Notable fix since snapshot:** `LegacyTenancyResolver` schema-aware owner resolution (`agency_id`/`agencyId` fallback) resolving MySQL `agency_videos` column mismatch; regression test added.
- **Still blocked/unverified:** real Docker/Redis/ClamAV/Nginx runtime, production deploy/HTTPS/monitoring, comprehensive latest browser QA with screenshots, deployed Lighthouse/CWV and load testing, live MySQL backup/restore/apply sign-off.

## Verification summary (snapshot)
- Read-only source inventory completed.
- Critical security review completed.
- PHP syntax checks passed for repaired controllers.
- Backend suite: 271 passed, 2152 assertions.
- Frontend verification: TypeScript passed; 133 unit tests passed; production build/prerender/bundle check passed.
- Live public browser subset: 17/18 in parallel run; navbar timeout passed when rerun alone. Public routes remained responsive.
- Docker and Redis runtimes are unavailable on this host.

## Repairs applied (snapshot)
1. Public property queries now force `listing_purpose=sale`.
2. Property creation/update reject `rent`.
3. Public property detail/listing payloads now use an explicit field allowlist.
4. Public catalog no longer publishes Projects or raw private media payloads.
5. Legacy catalog rent properties are excluded.
6. Sensitive document category downgrade now requires `documents.view_sensitive`.
7. Docker MySQL/Redis ports are internal and password fallbacks removed.
8. Agency detail access requires `properties.view` for agency-owned private records.

## Source areas not runtime-executed (still relevant)
- Live MySQL 8, Redis 7, ClamAV, multi-instance Nginx, Lighthouse and distributed load tests require infrastructure unavailable in this environment.
