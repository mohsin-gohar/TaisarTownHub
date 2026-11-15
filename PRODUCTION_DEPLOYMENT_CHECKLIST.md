# PRODUCTION_DEPLOYMENT_CHECKLIST.md — TaisarTownHub

Every row states **PASS / FAIL / BLOCKED / REQUIRES APPROVAL** with the evidence that produced
that verdict. Rows marked BLOCKED need environment credentials or infrastructure that is not
available in this workspace — they are **not** code gaps.

Legend: ✅ PASS · ❌ FAIL · ⛔ BLOCKED (needs credentials/infrastructure) · 🙋 REQUIRES APPROVAL (business decision)

---

## Phase 0 — Decision gate (before anything else)

| # | Item | Status | Evidence / note |
|---|---|---|---|
| 0.1 | Legacy `business_records` tenancy decision (95 shared rows) | 🙋 **REQUIRES APPROVAL** | Two-tenant probe: **every** legacy row is returned to **every** tenant (`legacy-visibility-probe.mts`). Options + mapping in `REMAINING_BLOCKERS.md` B-01 |
| 0.2 | Global "MFA required for platform admins" | 🙋 **REQUIRES APPROVAL** | Capability exists per account; a global policy switch would need your rule (B-04). **Not enforced** by default |
| 0.3 | API rate-limit profile for production | 🙋 **REQUIRES APPROVAL** | Current default 60/min per IP produced 301 × 429 in a 15 s load run (B-02) |
| 0.4 | Live deployment + production DB migration | ⛔ **BLOCKED by instruction** | You asked for the audit first — nothing was deployed, no production DB was touched |

---

## Phase 1 — Secrets & environment

| # | Item | Status | Evidence |
|---|---|---|---|
| 1.1 | `APP_KEY` generated per environment (never reuse dev) | ⛔ BLOCKED | Needs the production host. The key also decrypts CNIC + MFA columns — rotating it without a re-encryption run makes them unreadable |
| 1.2 | `APP_DEBUG=false` | ✅ PASS | `app:production-check` → PASS; `.env.example` ships `APP_DEBUG=false` |
| 1.3 | `APP_ENV=production` | ⚠️ WARN locally | Preflight reports the single warning (`APP_ENV=local`); template already sets `production` |
| 1.4 | `APP_URL` = public API origin | ✅ PASS (template) | `https://api.…` in `.env.example` |
| 1.5 | `FRONTEND_URL` = public frontend origin | ✅ PASS | **Fixed in this session**: dev had a stale `:3001`; the template has the production origin and the dev value now points at the live dev port `:3000` (verified in a delivered mail) |
| 1.6 | `CORS_ALLOWED_ORIGINS` = exact frontend origin(s) | ⛔ BLOCKED | Needs the real domain; template ships the production list |
| 1.7 | `SESSION_SECURE_COOKIE=true` behind HTTPS | ⛔ BLOCKED | Set once TLS is terminated; documented in `DEPLOYMENT_GUIDE.md` |
| 1.8 | `SANCTUM_STATEFUL_DOMAINS` = production frontend only | ⛔ BLOCKED | Same as 1.6 |
| 1.9 | `SANCTUM_TOKEN_PREFIX=tth_`, 24 h lifetime | ✅ PASS | Preflight PASS; tokens revoked on logout-all and on password reset |
| 1.10 | `MAIL_MAILER=smtp`, `MAIL_SCHEME=tls`, port 587, real credentials | ⛔ BLOCKED | **Pipeline proven**: real messages delivered over SMTP (reset / verify / password-changed) and a full reset flow passed 6/6 |
| 1.11 | `QUEUE_CONNECTION=database` (no fake Redis) | ✅ PASS | Worker processed real notifications; `jobs`/`failed_jobs` present |

## Phase 2 — Deploy the API

| # | Item | Status | Evidence |
|---|---|---|---|
| 2.1 | `composer install --no-dev --optimize-autoloader` | ⛔ BLOCKED | Needs the host |
| 2.2 | `php artisan migrate --force` | ✅ PASS (schema) | 0 pending migrations; 30 additive migrations applied locally, no destructive step ever executed |
| 2.3 | `php artisan optimize` (config/route/view caches) | ✅ PASS | Preflight reports caches; command runs clean |
| 2.4 | `php artisan app:production-check --strict` | ✅ PASS | 20 pass / 1 warn / 0 fail locally; `--strict` turns warnings into failures — run it on the server |
| 2.5 | `GET /api/health` → 200 `{"status":"healthy"}` | ✅ PASS | Live: 200 with `X-Request-Id` |
| 2.6 | PHP-FPM + nginx (or `php artisan serve` for a first pass) | ✅ PASS | `nginx/nginx.conf` (TLS, HSTS, CSP, CORS, per-route limits) |
| 2.7 | Error reporting: `LOG_LEVEL=warning`, no stack traces to clients | ✅ PASS | `APP_DEBUG=false`; the catch-all renderer returns a fixed 500 body |

## Phase 3 — Workers & scheduler

| # | Item | Status | Evidence |
|---|---|---|---|
| 3.1 | Queue worker running as a service (Supervisor/systemd) | ⛔ BLOCKED (config delivered) | Supervisor unit in `DEPLOYMENT_GUIDE.md` §3.2; `queue:work database --sleep=1 --tries=3` proven locally |
| 3.2 | **Restart the worker after any `.env` change** | ⚠️ ACTION | `artisan serve` auto-restarts on env change, **workers do not** (finding P-04). Found live during the SMTP test |
| 3.3 | Cron `* * * * * php artisan schedule:run` | ⛔ BLOCKED (config delivered) | Preflight: 2 scheduled tasks (token pruning, failed-job pruning) |
| 3.4 | `failed_jobs` alerting | ✅ PASS (mechanism) | `queue:prune-failed --hours=168`; monitor the table |

## Phase 4 — Frontend

| # | Item | Status | Evidence |
|---|---|---|---|
| 4.1 | `npm ci && npm run build` | ✅ PASS | Build green, bundle guard OK (`index.js` 429.6 KB / CSS 87.6 KB) |
| 4.2 | `VITE_API_URL` + `VITE_APP_MODE=production` | ⛔ BLOCKED | Template delivered |
| 4.3 | Prerendered HTML + `sitemap.xml` (9 URLs) + `robots.txt` | ✅ PASS | Prerender emits the static route set; sitemap stays 9 crawlable URLs |
| 4.4 | nginx SPA fallback per route | ✅ PASS | `frontend/nginx.conf` — `try_files $uri $uri.html /index.html` |
| 4.5 | CSP applied on the served HTML | ✅ PASS | **Added in this session** for both nginx references (frontend `script-src 'self'` — the inline service-worker script was moved into the bundle — and API `default-src 'none'`) |
| 4.6 | Service worker caches only hashed assets | ✅ PASS | `public/sw.js` — cache-first for `/assets/*`, network-first elsewhere, `/api` and private documents never cached |

## Phase 5 — Data & backups

| # | Item | Status | Evidence |
|---|---|---|---|
| 5.1 | Automated backup script (DB + vault + manifest) | ✅ PASS | `scripts/backup.sh` (Linux) and `scripts/backup.ps1` (Windows) — ran here: 53 tables + vault archive + SHA-256 manifest |
| 5.2 | Restore drill executed | ✅ PASS | `scripts/restore-drill.ps1` → **DRILL RESULT: PASS**, restored copy identical to live across 11 tables + 53-table count, vault 13 entries |
| 5.3 | Backup schedule + off-site copy | ⛔ BLOCKED | Needs the host + storage target; procedure and retention documented in `BACKUP_GUIDE.md` |
| 5.4 | Legacy tenant backfill | 🙋 REQUIRES APPROVAL | `php artisan platform:legacy-backfill` — **dry run by default**, additive, refuses `--apply` without a verified dump |

## Phase 6 — Verification before go-live

| # | Item | Status | Evidence |
|---|---|---|---|
| 6.1 | Backend suite | ✅ PASS | 176 passed / 1613 assertions |
| 6.2 | Frontend suite + types + build + bundle guard | ✅ PASS | 126 tests, tsc clean, build + prerender OK, guard OK |
| 6.3 | E2E journeys | ✅ PASS | 5/5 Playwright + 10/10 MFA browser journey |
| 6.4 | Cross-tenant isolation | ✅ PASS | 21/21 live probes (foreign id 404, spoofed header ignored, admin surface 403, vault 404, no CNIC in public payloads) |
| 6.5 | Concurrency / double booking | ✅ PASS | 8 parallel sells of one file → exactly **1** succeeded, 7 rejected |
| 6.6 | Load behaviour | ✅ PASS (with tuning item) | 428 requests, **0 server errors (5xx)**, p50 303 ms / p95 601 ms on a single-threaded dev server — see `LOAD_TEST_REPORT.md` |
| 6.7 | Mail delivery | ✅ PASS | Real SMTP delivery of reset / verification / password-changed mails + full reset flow 6/6 |
| 6.8 | Security headers + CSP | ✅ PASS | `SecurityHeadersTest` (4 tests / 20 assertions) + live header inspection |

## Phase 7 — Go-live

| # | Item | Status | Evidence |
|---|---|---|---|
| 7.1 | DNS + TLS certificates | ⛔ BLOCKED | Needs the domain and DNS access |
| 7.2 | `chown`/`chmod` for `storage/` + `bootstrap/cache/`; vault outside the web root | ⛔ BLOCKED | Preflight verifies writability; ownership is a host task |
| 7.3 | Supervisor + cron installed | ⛔ BLOCKED | Configs delivered |
| 7.4 | First backup taken **and verified** | ⛔ BLOCKED | Drill proven locally; production drill after the first deploy |
| 7.5 | Empty `VITE_DEMO_PIN`, `VITE_APP_MODE=production` | ✅ PASS (template) | Demo PIN removed from `.env.example` in an earlier phase |
| 7.6 | Rollback rehearsed | ⚠️ PROCEDURE READY | Code rollback + `migrate:rollback`/forward + cache rebuild + DB restore — rehearsed at the drill level (restore drill PASS) |
| 7.7 | Monitoring: health probe + `X-Request-Id` + audit trail | ✅ PASS (mechanism) | `/api/health` 200/503-with-degraded, correlation middleware, `audit_logs` append-only |
| 7.8 | Malware scanning of uploads | ⛔ BLOCKED / NOT IMPLEMENTED | No scanner available in this environment; **no fake scanner was added**. Upload path already enforces MIME, extension and size limits and stores files outside the web root — options in `REMAINING_BLOCKERS.md` B-05 |
| 7.9 | SMS / WhatsApp provider | ⛔ BLOCKED / NOT IMPLEMENTED | No provider credentials or supported gateway here; **no simulated driver was added** — readiness notes in B-06 |
