# TaisarTownHub — STATUS

**Branch:** `cleanup-and-finish` (from `main` @ `e8418b9`) · **Date:** 2026-11-24
This is the single source of truth for what is verified, what is not, and what is blocked. Status vocabulary: **VERIFIED** (command + real result), **NOT VERIFIED** (exact command the owner must run), **BLOCKED** (needs access/credentials/business decision). No marketing language; no "100%" claims.

## A. Code changes this branch (all committed)

| Commit | Change |
|---|---|
| `d795937` | Docs cleanup: 175 stale AI report files deleted (148 root .md + 11 .txt logs + 15 docs/*.md + AUDIT_EVIDENCE/ + frontend/.screenshots/ + unused `_audit.mjs`); consolidated docs/API.md, docs/DATABASE.md, docs/DEPLOYMENT.md, docs/SECURITY.md, docs/BACKUP_RESTORE.md, docs/DEMO_GUIDE.md; README rewritten |
| `fe6de36` | BLK-06 Option B: legacy `business_records` rows with no owner are platform-owned — hidden from agency workspaces (read path already filtered) and now blocked from agency sync (merge claim + replace soft-delete); new `LegacyPlatformOwnershipTest` (3 cases); `legacy-visibility-probe.mts` prints PASS/FAIL + exit code |
| `0f8b59d` | Gallery: tours without a real video (sample/placeholder URLs) hidden; real videos/posters go in `frontend/public/` or absolute URLs |

## B. Verified in this environment (commands + results)

| Area | Command | Result |
|---|---|---|
| Frontend typecheck | `cd frontend && npm run lint` (= `tsc --noEmit`) | **0 errors, exit 0** |
| Frontend unit tests | `cd frontend && npm run test` | **158 passed / 0 failed** |
| Frontend build + prerender | `cd frontend && npm run build` | **OK — 29 prerendered HTML pages, sitemap 9 URLs** |
| Bundle privacy guard | `cd frontend && npm run check:bundle` | **OK — no private ERP data or demo credentials in public chunks** |
| Playwright UI-only E2E | `npx playwright test e2e/cross-browser-smoke.spec.ts e2e/responsive.spec.ts e2e/maps.spec.ts e2e/interactive-elements.spec.ts e2e/whatsapp.spec.ts --workers=1` | **33 passed / 2 skipped** |
| Playwright page specs | `public-pages.spec.ts` alone (isolated) | **13 passed / 0 failed** |
| Playwright mixed UI specs | `public-pages + sale-only + agency-marketplace + smoke:12/18` | **24 passed / 1 failed** (the failure is `sale-only:22` — needs live API, see below) |
| Guest guard E2E | `admin-pages.spec.ts:49,57` | **2 passed** |
| YAML validity | `python3 -c "import yaml; yaml.safe_load(...)"` for docker-compose.yml, docker-compose.ci.yml, docs/openapi.yaml, .github/workflows/ci.yml, .github/workflows/docker-ci.yml | **all parse OK** |
| Shell script syntax | `bash -n scripts/backup.sh`, `bash -n scripts/wait-for-compose-ready.sh` | **both OK** |
| SEO base checks (prerendered pages) | node script on dist/ pages (title, meta description, viewport, lang) | **PASS on index/properties/agencies/contact/about** |
| Rate-limit/report claims of deleted docs | (none) | superseded by this file |

## C. Not verifiable here — must run elsewhere

| Area | Exact command / requirement | Why not verified here |
|---|---|---|
| Full backend PHP suite | `cd backend && php artisan test` (SQLite default; MySQL 8 via CI) | **no PHP runtime in this sandbox** |
| MySQL 8 backend suite | CI job (`.github/workflows/ci.yml`) runs backend on MySQL 8 — push this branch and confirm green | no PHP/MySQL locally |
| API-dependent E2E (31 tests) | `cd frontend && npm run e2e` with backend on :8080 + seeded `taisartownhub_e2e` DB | failures observed here are all `ECONNREFUSED 127.0.0.1:8080` — backend absent, not product bugs |
| Docker compose stack | `docker compose up -d --build` then `bash scripts/wait-for-compose-ready.sh 60 mysql:healthy redis:healthy clamav:healthy` | no Docker daemon in sandbox |
| ClamAV upload paths (clean/EICAR/fail-closed) | upload via `/api/agency/documents` with the `clamav` service running; EICAR test file must be quarantined/rejected; stop clamav → uploads must 423/hold | needs the compose stack |
| 2 backend replicas | `docker compose up -d --scale backend=2` (nginx discovers replicas via Docker DNS; migrations once in `migrate` service) + stateless check (two logins, `/api/health` on both) | needs Docker |
| Backup + restore drill | `scripts/backup.sh` then `scripts/restore-drill.ps1` (CI job `backup-restore` in docker-ci.yml runs a real drill on MySQL 8) | needs MySQL; CI runs it on push |
| Lighthouse (perf/a11y/SEO) | `npx lighthouse https://<deployed-url> --view` — fix anything <90 | needs deployed HTTPS origin |
| Firefox/WebKit full run | `npx playwright test --project=firefox --project=webkit` (config supports adding projects; `cross-browser-smoke.spec.ts` covers 7 routes) | only Chromium installed here |
| Light load test (10/50/100 users) | `frontend/scripts/load-test.mts` (or k6) against a running API — record p95 | needs live API |
| Backend suite with new BLK-06 test | `cd backend && php artisan test --filter=LegacyPlatformOwnershipTest` | no PHP runtime — **this is the one command to run first after push** |

## D. Blocked (owner decisions / credentials — cannot be done by an agent)

1. **Production secrets:** unique `APP_KEY`, DB/Redis passwords, SMTP, Stripe/Flutterwave, Google OAuth, monitoring ids — deploy-time only, never committed (`backend/.env.example` has all keys, no secrets).
2. **DNS + TLS for taisartownhub.com:** A-record to VPS IP; real certs at `nginx/ssl/fullchain.pem` + `privkey.pem` (`certbot certonly --webroot`).
3. **Legacy data ownership (residual):** the resolver/backfill tooling is in place; a human-approved mapping for the still-unresolved legacy rows must be applied with `platform:legacy-backfill --apply --backup=<verified dump>` (dry-run first: `platform:legacy-backfill`, report: `platform:legacy-ownership`).
4. **Global MFA policy** for platform administrators (currently opt-in per account).
5. **Final production rate-limit values** (current ones are profiling targets; see `backend/app/Providers/AppServiceProvider.php`).
6. **Real Scheme 45 media:** 88 sample-video/stock references remain in seed data only — the UI now hides fake tours; replacing them with licensed videos/posters in `frontend/public/` is an owner asset task.
7. **VPS access (SSH)** for the actual deployment (runbook: `docs/DEPLOYMENT.md` §4).

## E. Push instructions (no push credentials in this sandbox)

```bash
git remote add origin https://github.com/mohsin-gohar/TaisarTownHub.git   # if needed
git push -u origin cleanup-and-finish
```
Then: open PR `cleanup-and-finish` → `main`, watch `ci.yml` (backend MySQL 8 suite + frontend verify + openapi check) and `docker-ci.yml` (images + backup-restore drill + integration smoke) — **green CI is the required proof for the PHP-side claims in §C**.

## F. Residual known items (honest list)

- `Dockerfile.prod` at the repo root is deprecated (kept as reference; per-app Dockerfiles are authoritative).
- The 3 recorded E2E flakes from earlier audits were timing flakes under full-suite load on a single-threaded dev server (`role-based.spec.ts` passes 13/13 in isolation); a clean full-suite run on a machine with the backend up closes this.
- Full per-file audit of all ~750 files has never been completed by any agent (inventoried only) — do not treat any old report claiming otherwise as fact; those reports were deleted in this cleanup for exactly that reason.
