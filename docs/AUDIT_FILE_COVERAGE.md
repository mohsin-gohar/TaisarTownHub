# Audit File Coverage Ledger

Mission: complete repository audit → fix → test → re-audit (final gate documents).
Baseline commit audited: `6c9439f` (clean, equal to `origin/main` at start).
CI/readiness fixes committed locally afterwards: `0894484`.

## 1. Source inventory (file counts measured from the working tree)

| Area | Files | Notes |
|---|---:|---|
| `backend/app` | 117 | Controllers, models, middleware, services, providers, console |
| `backend/routes` | 4 | `api.php`, `api_definitions.php`, `console.php`, `web.php` |
| `backend/config` | 15 | Laravel configuration |
| `backend/database` | 63 | Migrations, seeders, factories, sqlite dev DB, init SQL |
| `backend/tests` | 59 | 55 `*Test.php` suites + support/helpers |
| `backend/resources` | 6 | Blade/mail assets |
| `frontend/src` | 128 | Pages, components, domain, services, context, styles |
| `frontend/e2e` | 13 | 11 Playwright spec files + config/helpers |
| `frontend/scripts` | 24 | Test runner, prerender, bundle check, scripts |
| `scripts/` (root) | 5 | Backup/restore helpers + CI readiness gate (added this mission) |
| `docker/` | 5 | Runtime configs + CI-only nginx config (added this mission) |
| `nginx/` | 1 | Production HTTPS gateway config |
| `.github/workflows` | 2 | `ci.yml`, `docker-ci.yml` |
| Root `*.md` | 133 | Historical audit/report documents (not executable source) |

Excluded from meaningful source review (and why): `.git/`, `vendor/`, `node_modules/`,
`frontend/dist/`, compiled caches, binary images, `backend/database/database.sqlite`
(runtime data), and the 133 historical root markdown reports (claims from those were
re-verified selectively; they are not treated as evidence).

## 2. What was actually inspected this mission

### Fully read and verified (with intent to find defects)
- Git state (branch, HEAD, remote, tree clean), root listing, `README.md`, both CI workflows,
  `docker-compose.yml`, `docker-compose.ci.yml`, `nginx/nginx.conf`, `docker/nginx.ci.conf` (new),
  `backend/scripts/healthcheck.php`, `backend/scripts/wait-for-db.php`, `backend/docker/entrypoint.sh`,
  `backend/bootstrap/app.php`, `backend/routes/api.php`, `backend/phpunit.xml`,
  `backend/Dockerfile.prod`, `frontend/Dockerfile.prod`, `frontend/nginx.conf`,
  `frontend/index.html`, `frontend/playwright.config.ts`, both `.env.example` files,
  root/frontend `package.json`, `scripts/wait-for-compose-ready.sh` (new).
- Authorization/tenancy core: `backend/app/Http/Middleware/AgencyContext.php`,
  `backend/app/Http/Controllers/Api/AuthorizesApi.php`,
  private-document download/preview/delete flows and `findScoped()` in
  `backend/app/Http/Controllers/Api/DocumentController.php`.
- `backend/app/Http/Controllers/Api/AuthController.php` (Google OAuth state/nonce handling,
  suspended-account checks) and MFA route coverage in `backend/routes/api_definitions.php`.
- Mass-assignment surface: `$fillable` of `User`, `Agency`, `Property`; search for
  `$request->all()` / `create($request…)` / `fill($request…)` across all controllers.
- `HealthController`, public `AgencyController::index`, `MapController::publicIndex`
  (verified the exact JSON shapes asserted by CI smoke tests).

### Verified through executable evidence (not per-file manual review)
- Entire backend test suite: `php artisan test` → 362 passed / 3054 assertions (see final audit).
- Entire frontend verification chain: tsc + 149 unit tests + build/prerender + bundle check.
- Full Playwright suite: 84 tests, Chromium (see final audit for the final tally).
- CI gate logic: ready/failure paths exercised locally with a fake `docker` test double:
  ready → exit 0 with `9/9 required services satisfied`; unhealthy/starting → exit 1 with
  `TIMEOUT`, the not-ready service list and tail logs.
- YAML parse check of both compose files and both workflows; Bash `bash -n` syntax check of
  the readiness script.

### Targeted (grep-based, not line-by-line)
- All route definitions in `backend/routes/api_definitions.php` (auth/throttle/middleware placement
  for auth, password reset, MFA, public catalog, admin endpoints).
- Global middleware/exception handling in `backend/bootstrap/app.php`.
- Secrets scan of all tracked files (no live credential patterns; only `.env.example` files are tracked).
- Frontend build bundle scan (`npm run check:bundle`) for leaked private ERP data/demo credentials.
- Frontend test inventory: 24 `*.test.ts` files under `frontend/src`.

## 3. Honest limitations of this ledger

1. The three parallel audit subagents launched at the start of this mission (backend file-by-file,
   frontend file-by-file, documentation consistency) **failed with the provider's free usage limit**
   before returning any findings. They are not counted as coverage.
2. A line-by-line human-equivalent review of all 117 backend app files and all 128 frontend source
   files was therefore **not completed** in this session. Coverage of those areas is via the full
   automated suites plus the targeted security checks listed above. This is a real audit gap and is
   reflected in the production-readiness percentage and in the release checklist.
3. Docker itself is **not installed** in this environment (`docker` command unavailable), so the
   compose stack and the integration job could not be executed locally. The readiness gate was
   verified with a deterministic stub instead; the YAML was parsed statically; the nginx configs
   could not be run through `nginx -t` locally.
4. Browser QA used Playwright's Chromium project only. No Firefox/WebKit, Lighthouse, or load-test
   run was performed.

## 4. Re-audit result for files changed this mission

| File | Change | Re-verification |
|---|---|---|
| `.github/workflows/docker-ci.yml` | Timed, required-service readiness gate; diagnostics; cleanup preserved | YAML parse; gate logic tested with stub |
| `docker-compose.yml` | Added meaningful healthchecks to frontend, nginx, worker, scheduler | YAML parse; commands verified to exist (`queue:monitor`, `schedule:list` in artisan) |
| `docker-compose.ci.yml` | CI DB name fixed, images reused, nginx CI config mounted | YAML parse; mounts/config keys checked against base compose |
| `nginx/nginx.conf` | `/healthz` before HTTPS redirect | Edit diff reviewed; syntax not executable locally |
| `docker/nginx.ci.conf` (new) | HTTP-only gateway for CI | Routing mirrored from production config; not executable locally |
| `scripts/wait-for-compose-ready.sh` (new) | Fail-closed readiness gate | `bash -n`; ready and timeout paths executed with stub |
| `README.md` | Corrected stale claims (test counts, ports, features) | Counts cross-checked against test logs |
| `backend/composer.lock` | Patched Laravel/Symfony + transitive deps (24 packages) | `composer audit` 0 advisories; backend suite re-run green |
| `frontend/e2e/helpers/adminLogin.ts` | Deterministic token injection before SPA boot | `tsc --noEmit` clean; focused E2E pass; full rerun |
| `docs/FINAL_PRODUCTION_AUDIT.md`, `docs/PRODUCTION_RELEASE_CHECKLIST.md`, `docs/AUDIT_FILE_COVERAGE.md` | New release-gate documents | Cross-checked all counts against recorded test output |
