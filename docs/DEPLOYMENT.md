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
   Redis (cache/queue) scheduler             (vault documents + quarantine)
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
cp frontend/.env.example frontend/.env        # VITE_API_URL=https://api.taisartownhub.com/api (or same-origin /api)
docker compose up -d --build
# The one-shot migrate service runs automatically during docker compose up.
docker compose exec backend php artisan app:production-check --strict
```

Services: `nginx` (TLS gateway), `frontend`, `backend` (PHP-FPM), `worker`, `scheduler`, `mysql` (MySQL 8.4), `redis`, `clamav`, one-shot `migrate`. Volumes: `mysql_data`, `redis_data`, `clamav_data`, `private_vault`, `public_storage`. The `private_vault` volume is mounted at `storage/app/private` in the API, queue worker, and scheduler so both `documents` and `quarantine` disks survive restarts and remain available to background jobs. It is never mounted in the frontend or nginx gateway, so public web-serving containers cannot read private files. `php artisan serve` is not used in production. The root `Dockerfile.prod` is deprecated; use `backend/Dockerfile.prod` / `frontend/Dockerfile.prod` with `docker-compose.yml`.

## 4. VPS deployment (taisartownhub.com, e.g. Hostinger Ubuntu 22.04/24.04)

1. **DNS:** point `taisartownhub.com` (and `www`, plus the API host if split) A-records at the VPS IP.
2. **TLS:** obtain real certificates (e.g. `certbot certonly --webroot`) and mount them at `nginx/ssl/fullchain.pem` + `nginx/ssl/privkey.pem`. Nothing ships with the repo; CI uses the HTTP-only `docker/nginx.ci.conf`.
3. **Deploy:** clone the repo, prepare env files (§6), `docker compose up -d --build`, then migrate once via the one-shot `migrate` service (`RUN_MIGRATIONS=false` on all replicas/workers/scheduler).
4. **Post-deploy checks:** `/api/health` → 200 `healthy`; homepage renders over HTTPS; login, agency workspace, a private document download and one queued job all succeed; watch logs for one business day before sign-off.
5. **Rollback:** keep previous image tags + a verified DB/storage backup; stop traffic at the gateway, restore previous images, restore data only if a schema/data regression is confirmed, re-run the B-gates on the rollback commit.

## 5. Vercel (frontend preview + API reverse proxy — NOT the full-stack production deployment)

Vercel serves the SPA; it does not host the Laravel runtime or MySQL database. Both root and `frontend/vercel.json` route `/api/:path*` to `https://api.taisartownhub.com/api/:path*` before the SPA catch-all, so the frontend can keep its same-origin `/api` default regardless of the Vercel project root directory. The API origin must be deployed, healthy, and configured with the correct database, storage, queue, and secrets. If you use a different API host, set `VITE_API_URL` to its full `/api` URL and update the Vercel rewrite plus CSP `connect-src` together.

A successful Vercel frontend build is not proof that the Laravel API is available. Verify `https://api.taisartownhub.com/api/health`, `/api/agencies`, `/api/maps`, login, agency workspace, and private-document authorization against the deployed backend before calling the site production-ready.

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
| `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS` | exact SPA origin(s), including `taisartownhub.vercel.app` until custom-domain DNS is live — never `*` |
| `TRUSTED_PROXIES` | real load-balancer/nginx IPs (X-Forwarded-For spoofing prevention) |
| `CLAMAV_HOST/PORT`, scan enabled | fail-closed upload scanning |
| `STRIPE_*`, `FLUTTERWAVE_*` | live keys + webhook secrets, or explicitly disable payments |
| `FRONTEND_URL` | SPA origin used by OAuth callbacks, password reset, and verification emails; set to the registered custom domain after DNS is live |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Google OAuth credentials + API callback URI, or leave unset to disable Google Sign-In |
| `SENTRY / LOGROCKET / GA` | optional monitoring ids |
| Backup credentials | off-site storage; `APP_KEY` stored separately from data backups |

See `backend/.env.example` for the full commented list. **Never commit real values.**

## 7. ClamAV (fail-closed)

Uploads (documents, versions, media) are scanned on every byte. Without a reachable scanner with current signatures, uploads are held undownloadable (423) — never accepted as clean. Ensure the `clamav` sidecar is healthy and its signatures can download on the target host.

## 8. Firebase (optional, not currently wired)

Firebase keys appear in `frontend/.env.example` as placeholders; no Firebase SDK is installed and no code path uses it. If Firebase Storage is adopted later, upload media via the Storage SDK instead of the current validated client-side path. Do not treat it as an active integration.

## 9. Rate limits

Current values (login 5/min, register/forgot 3/min, writes 30/min, public reads 300/min) are initial profiling targets — tune on production traffic before cutover; see `backend/app/Providers/AppServiceProvider.php`.
