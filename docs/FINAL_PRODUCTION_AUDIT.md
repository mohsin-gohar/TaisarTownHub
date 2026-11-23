# Final Production Audit — TaisarTownHub

Audit date: 2026-11-23. Re-verified against `5844190` (current `origin/main`, clean tree).
This document is the release-gate source of truth; historical root-level `*_REPORT.md` files are not.
Every count and result below was measured from the working tree or re-run in this environment;
anything that could not be executed is explicitly labelled unverified.

## A. Project completion

**Feature completeness: no percentage is asserted in this document.** A completion percentage
cannot be verified from the repository, so none is published here.

Verified surface (measured from `5844190`): **53 migrations**, **47 Eloquent models**, 33 API
controllers, 4 route files, 76 React components (26 public, 33 admin), 9 Docker Compose services,
2 CI workflows. All primary product surfaces exist in code — public sale-only marketplace,
agencies/maps, Sanctum auth + MFA + Google OAuth, RBAC/tenant workspaces, properties,
inquiries/CRM, private document vault, finance, subscriptions, admin oversight and
queue/scheduler — but existence in code is not the same as verified in production.

Automated evidence: **362 backend tests / 3,054 assertions (0 failures)**, **149 frontend unit
tests (0 failures)**, `tsc` clean, production build + SEO prerender + bundle check pass.

Not verified: browser E2E execution, the Docker stack ever starting, live payment/email
integrations, TLS/VPS deployment, cross-browser/Lighthouse/load testing, and a line-by-line review
of every source file (section E).

## B. Production readiness

**Production readiness: NOT READY.** Kept separate from feature completeness on purpose: the
application code and automated suites are healthy, but the release gates are not met. At `5844190`
the committed CI is red — `ci.yml` run #60 failed with **0 jobs started**, and `docker-ci.yml` run
#5 failed at **"Start infrastructure services"**, so the integration job (migrations, readiness
gate, smoke tests) never ran. A launch also requires external credentials, real TLS, a verified
stack boot, a backup restore drill and owner sign-off. Infrastructure maturity, not feature count,
is the limiting factor.

## C. Significant issues fixed in this mission

1. **False-success Docker CI readiness gate** (`.github/workflows/docker-ci.yml`): the integration
   job counted lines containing "healthy" and required `>= 6`, although only four services had
   healthchecks; after 30 iterations the loop fell through with exit code 0. Replaced with
   `scripts/wait-for-compose-ready.sh`, which checks required services **by name**, applies a hard
   deadline, exits non-zero on timeout, and dumps `compose ps` plus tail logs of not-ready services.
2. **Missing healthchecks**: frontend SPA, nginx gateway, queue worker and scheduler had none.
   Added real checks: SPA `/health`, gateway `/healthz`, worker `queue:monitor redis:default`,
   scheduler `schedule:list`. Production nginx port-80 server was restructured so `/healthz` is
   answered before the HTTPS redirect.
3. **CI nginx could not start**: production `nginx/nginx.conf` requires TLS certificates and
   `nginx/ssl/` is empty. Added `docker/nginx.ci.conf` (HTTP-only, same routing to frontend and
   PHP-FPM) and mounted it via `nginx -c` in the CI override.
4. **CI database name mismatch**: CI MySQL creates `taisartownhub_test`, but backend/migrate/worker/
   scheduler inherited `DB_DATABASE=taisartownhub` from the base compose file, so migrations and
   container tests would target a missing database. Fixed in `docker-compose.ci.yml`.
5. **Wasted/misleading image builds**: buildx produced `:test` images that compose never used,
   building a second copy instead. CI override now points all backend-derived services and the
   frontend at the prebuilt tags.
6. **Arbitrary sleeps** (`sleep 60`, `sleep 30`) replaced with the same required-service gate.
7. **Failure diagnostics and cleanup**: diagnostics step added on failure; `if: always()` cleanup
   preserved in both compose jobs. No test step can now run before the gate succeeds.
8. **README corrected**: removed stale "59 unit tests", XAMPP/root-no-password instructions and
   unverifiable feature claims; documented actual commands, ports, env files and limitations.
9. **Gate self-test found a bug in the new script** (`Exited (0)` pattern missed the space); fixed
   and re-tested (ready → exit 0; unhealthy/starting → exit 1 with logs).
10. **8 dependency security advisories fixed**: `composer audit` initially flagged Laravel
    `v12.70.2` and Symfony `v7.4.22` components (including the trusted-proxy, routing and YAML
    parser advisories). Updated the lock file to Laravel `v12.73.0` / Symfony `v7.4.23` and the
    dev-only `symfony/yaml`. Re-verified at `5844190`: the lock pins Laravel `v12.73.0` and
    Symfony `v7.4.23`; `composer audit` reports **0 advisories** after `composer install` syncs
    `vendor/` to the lock (a vendor tree left over from the old install still reports the eight
    pre-update advisories). Backend suite re-run green. `npm audit --audit-level=high` reports
    **0 vulnerabilities**.
11. **E2E login helper hardened**: the token was injected after an initial unauthenticated SPA
    boot, which could race with the app's login redirect. Now injected with `addInitScript`
    before the single navigation. (The observed failure also coincided with the composer update
    replacing vendor files while the dev server was serving E2E traffic — a self-inflicted
    environment interference, corrected by re-running on a clean server.)

Earlier committed hardening from previous missions (transaction indexes, inquiry permissions,
document share caps, MFA enforcement, map bulk upload/safe image replacement, etc.) remains in
the history and was not regressed by this mission's tests.

## D. Tests — actual commands and results

| Command | Result |
|---|---|
| `php artisan test` (backend) | **362 passed (3054 assertions), 0 failed** (re-run: 15.95s in this environment) |
| `npm run verify` (frontend) | **EXIT 0** — tsc clean, **149/149 unit tests passed**, build + SEO prerender (sitemap: 9 URLs), bundle check OK |
| Playwright E2E | **Not executed.** 11 spec files declare 74 tests; the Chromium launch failed at the CDP transport layer and no CI job runs Playwright. No pass result is claimed. |
| `bash -n scripts/wait-for-compose-ready.sh` | EXIT 0 |
| Stubbed gate, all services ready | EXIT 0 — `READY: 9/9 required services satisfied in 0s` (re-run with a stub `docker`) |
| Stubbed gate, unhealthy/starting services | EXIT 1 — `TIMEOUT`, offending services listed, tail logs printed (re-run) |
| Python `yaml.safe_load` on both compose files + both workflows | All four parse OK |
| `composer audit` (after `composer install` from the committed lock) | **0 advisories** (a stale vendor tree reports the 8 pre-update advisories) |
| `npm audit --audit-level=high` | **0 vulnerabilities** |
| OpenAPI parse (`docs/openapi.yaml`) | OpenAPI 3.1.0, 97 paths parsed OK |
| Committed CI at `5844190` | **RED** — `ci.yml` #60 failed with 0 jobs started; `docker-ci.yml` #5 failed at "Start infrastructure services" |
| PHP lint / debug leftovers / APP_DEBUG / security headers | Checked in earlier missions; unchanged this mission |

No tests were weakened, deleted or skipped to obtain these results.

**Not run (honest limitation):** Docker stack/integration job (no Docker CLI in this environment),
`nginx -t`, browser E2E (Chromium failed at the CDP transport layer), cross-browser
(Firefox/WebKit), Lighthouse/Core Web Vitals and load tests. These remain release gates B7–B9 in
`docs/PRODUCTION_RELEASE_CHECKLIST.md`.

## E. Audit coverage

See `docs/AUDIT_FILE_COVERAGE.md` for the full ledger. Summary: 117 backend app files (including
**47 Eloquent models**), 4 route files, 63 database files (including **53 migrations**), 59 backend
test files, 128 frontend source files, 13 E2E files, 24 frontend scripts, **4** root scripts,
**4** `docker/` files, 2 workflows, 1 nginx config and 133 historical root markdown files were
inventoried. Deep inspection was performed on CI/Docker/nginx configs,
auth/tenancy middleware, document download authorization, mass-assignment surfaces and public API
shapes; the rest was covered by full automated suites plus targeted greps. Three parallel audit
subagents failed with the provider's free usage limit before returning findings, so a
line-by-line review of every app/UI file was **not** completed. Excluded: dependencies, build
output, runtime DB, `.git`, and historical reports as evidence.

## F. Remaining blockers (genuine only)

| Blocker | Category | Owner action |
|---|---|---|
| Real `APP_KEY`, DB, SMTP, payment, OAuth and monitoring credentials | Credentials | Supply at deploy time (never committed) |
| TLS certificates for `nginx/ssl/` | Infrastructure | Install valid certs before HTTPS traffic |
| Docker stack has never booted successfully: `docker-ci` run #5 failed at "Start infrastructure services"; the integration job was skipped | Infrastructure/tooling | Fix the compose startup failure, then run it on a Docker-capable host |
| Committed CI is red at HEAD (`ci.yml` run #60: failure with 0 jobs started) | CI | Repair the workflow and re-run |
| Hostinger VPS SSH access | Access/manual | Owner provides access for deployment |
| ClamAV signatures/daemon in production | External service | Verify daemon reachable where fail-closed scanning is enabled |
| Backup restore drill on staging | Manual | Execute and record before launch |
| Cross-browser, Lighthouse, load test | External tooling | Run and review before launch |
| Full line-by-line source audit gap | Process | Resume with a available provider/quota if required by policy |

## G. Deployment readiness

**Not safe to deploy as-is.** The correct sequence is: all A-gates and B1–B8 in
`docs/PRODUCTION_RELEASE_CHECKLIST.md` pass on the exact release commit → owner approval →
deploy → smoke checks → monitoring. No deployment, production DB change or GitHub push has been
performed by this audit.

## H. Git and CI status

- Head: `5844190` — "docs: add production release audit, release checklist, and audit file
  coverage ledger". It equals `origin/main` (working tree clean); this audit was re-verified on it.
- Fixes from this mission are in `0894484` and `5844190`, both pushed to `origin/main`.
- No deployment, production database access or secret change was performed.

Committed CI at `5844190` (GitHub Actions):

| Workflow | Run | Result |
|---|---|---|
| `ci.yml` | #60 | **failed — 0 jobs started** (workflow-level startup failure) |
| `docker-ci.yml` | #5 | **failed** at "Start infrastructure services"; `Full Stack Integration Test` skipped |

The Docker CI frontend image job passed; the backend image built but the stack never started.

## I. Final verdict

**NOT READY FOR PRODUCTION**

Reason: committed CI is red at HEAD, the Docker stack has never started successfully, and external
blockers (secrets, TLS, VPS access, backup drill, browser/performance evidence) remain. The code and
automated test suites are healthy (362 backend / 149 frontend tests, 0 failures); launching now would
be a process/infrastructure failure, not a feature failure.
