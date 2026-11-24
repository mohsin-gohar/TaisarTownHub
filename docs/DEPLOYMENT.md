# TaisarTownHub — Deployment & Operations

Consolidates the former root `DEPLOYMENT.md`, `DEPLOYMENT_GUIDE.md`, `HOSTINGER_VPS_DEPLOYMENT.md`, `MYSQL_PRIMARY_DATABASE.md` and `FILE_STORAGE_THREAT_MODEL`-adjacent deployment notes. Detailed internal runbook: `documents/15_DEPLOYMENT_CICD.md`.

## 1. Topology

```
                ┌───────────────────────────┐
 Browser ──────▶│ frontend (nginx, static)  │  :80/:443 (SPA + prerendered HTML)
                └────────────┬──────────────┘
                             │ /api  (HTTPS, CORS allowlist)
                ┌────────────▼──────────────┐
                │ backend (PHP-FPM 8.3)     │  internal :9000
                └────────────┬──────────────┘
                             │
        ┌────────────────────┼────────────────────┐
        ▼                    ▼                    ▼
   MySQL 8 (data)      queue worker         private disk
   Redis (cache/queue) scheduler             (vault documents)
                        ClamAV sidecar (fail-closed scans)
```

## 2. Local pre-deploy verification

```bash
cd backend && php artisan test                # full backend suite (SQLite default; MySQL 8 in CI)
cd backend && php artisan app:production-check --strict   # env/keys/DB/storage/queue/Sanctum/bcrypt audit
cd frontend && npm run verify                 # tsc + unit tests + build + prerender + bundle check
```

## 3. Docker (recommended)

```bash
cp backend/.env.example backend/.env          # then edit secrets — see §6
cp frontend/.env.example frontend/.env        # VITE_API_URL=https://taisartownhub.com/api (or same-origin /api)
docker compose up -d --build
docker compose exec api php artisan migrate --force
docker compose exec api php artisan app:production-check --strict
```

Services: `nginx` (TLS gateway), `frontend`, `backend` (PHP-FPM), `worker`, `scheduler`, `mysql` (MySQL 8.4), `redis`, `clamav`, one-shot `migrate`. Volumes: `mysql_data`, `redis_data`, `clamav_data`, `private_vault`, `public_storage`. The vault volume is mounted only on the API container — the web container can never read it. `php artisan serve` is not used in production. The root `Dockerfile.prod` is deprecated; use `backend/Dockerfile.prod` / `frontend/Dockerfile.prod` with `docker-compose.yml`.

## 4. VPS deployment (taisartownhub.com, e.g. Hostinger Ubuntu 22.04/24.04)

1. **DNS:** point `taisartownhub.com` (and `www`, plus the API host if split) A-records at the VPS IP.
2. **TLS:** obtain real certificates (e.g. `certbot certonly --webroot`) and mount them at `nginx/ssl/fullchain.pem` + `nginx/ssl/privkey.pem`. Nothing ships with the repo; CI uses the HTTP-only `docker/nginx.ci.conf`.
3. **Deploy:** clone the repo, prepare env files (§6), `docker compose up -d --build`, then migrate once via the one-shot `migrate` service (`RUN_MIGRATIONS=false` on all replicas/workers/scheduler).
4. **Post-deploy checks:** `/api/health` → 200 `healthy`; homepage renders over HTTPS; login, agency workspace, a private document download and one queued job all succeed; watch logs for one business day before sign-off.
5. **Rollback:** keep previous image tags + a verified DB/storage backup; stop traffic at the gateway, restore previous images, restore data only if a schema/data regression is confirmed, re-run the B-gates on the rollback commit.

## 5. Vercel (frontend-only preview — NOT the production deployment)

The live Vercel deployment serves the SPA only, with the bundled public catalog and no API. It is a preview, not the required full-stack production deployment; set `VITE_API_URL` there if you want it to talk to the API origin.

## 6. Production .env checklist (all keys, NO secrets committed)

| Key | Requirement |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false` | **Mandatory** — debug pages must never render in production |
| `APP_KEY` | unique per environment, generated at deploy (`php artisan key:generate`); rotating it invalidates captcha challenges + encrypted columns |
| `DB_CONNECTION=mysql`, `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD` | MySQL 8; never the CI/test key |
| `DB_ROOT_PASSWORD` (compose) | initial bootstrap only |
| `REDIS_HOST/PORT/PASSWORD` | cache, sessions, queues, rate limits |
| `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis` | |
| `MAIL_MAILER/MAIL_HOST/MAIL_PORT/MAIL_USERNAME/MAIL_PASSWORD/MAIL_FROM_ADDRESS` | real SMTP; verify delivery of verification/reset mail |
| `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS` | exact production origin(s) — never `*` |
| `TRUSTED_PROXIES` | real load-balancer/nginx IPs (X-Forwarded-For spoofing prevention) |
| `CLAMAV_HOST/PORT`, scan enabled | fail-closed upload scanning |
| `STRIPE_*`, `FLUTTERWAVE_*` | live keys + webhook secrets, or explicitly disable payments |
| `GOOGLE_OAUTH_*` | client id/secret + production redirect URI |
| `SENTRY / LOGROCKET / GA` | optional monitoring ids |
| Backup credentials | off-site storage; `APP_KEY` stored separately from data backups |

See `backend/.env.example` for the full commented list. **Never commit real values.**

## 7. ClamAV (fail-closed)

Uploads (documents, versions, media) are scanned on every byte. Without a reachable scanner with current signatures, uploads are held undownloadable (423) — never accepted as clean. Ensure the `clamav` sidecar is healthy and its signatures can download on the target host.

## 8. Firebase (optional, not currently wired)

Firebase keys appear in `frontend/.env.example` as placeholders; no Firebase SDK is installed and no code path uses it. If Firebase Storage is adopted later, upload media via the Storage SDK instead of the current validated client-side path. Do not treat it as an active integration.

## 9. Rate limits

Current values (login 5/min, register/forgot 3/min, writes 30/min, public reads 300/min) are initial profiling targets — tune on production traffic before cutover; see `backend/app/Providers/AppServiceProvider.php`.
