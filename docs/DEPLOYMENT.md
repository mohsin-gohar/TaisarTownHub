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
   MySQL 8 (data)      queue worker         shared private volumes
   Redis (cache/queue) scheduler             (documents + quarantine)
                        public storage + ClamAV sidecar
```

## 2. Local pre-deploy verification

```bash
cd backend && php artisan test                # full backend suite (SQLite default; MySQL 8 in CI)
cd backend && php artisan app:production-check --strict   # env/keys/DB/storage/queue/Sanctum/bcrypt audit
cd frontend && npm run verify                 # tsc + unit tests + build + prerender + bundle check
```

## 3. Docker (recommended)

The repository-root `.env` is the Docker Compose source of truth. Do not copy `backend/.env.example` to `backend/.env` and assume Compose will read it; Compose does not load that file.

```bash
cp .env.example .env
# Replace every CHANGE_ME value before starting the stack.
# Generate APP_KEY: openssl rand -base64 32, then prefix with "base64:"
# Generate DB_PASSWORD, DB_ROOT_PASSWORD and REDIS_PASSWORD: openssl rand -hex 32
docker compose config --quiet
docker compose up -d --build
# The one-shot migrate service runs before backend/worker/scheduler start.
docker compose ps -a
docker compose logs migrate
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan app:production-check --strict --json
```

Services: `nginx` (TLS gateway), `frontend`, `backend` (PHP-FPM), `worker`, `scheduler`, `mysql` (MySQL 8.4), `redis`, `clamav`, one-shot `migrate`. Volumes: `mysql_data`, `redis_data`, `clamav_data`, `private_vault`, `quarantine_storage`, `public_storage`. The private document, quarantine and public-storage volumes are shared by the backend replicas and required workers; the frontend container receives no private volume. `php artisan serve` is not used in production. The root `Dockerfile.prod` is deprecated; use `backend/Dockerfile.prod` / `frontend/Dockerfile.prod` with `docker-compose.yml`.

## 4. VPS deployment (taisartownhub.com, e.g. Hostinger Ubuntu 22.04/24.04)

1. **DNS:** point `taisartownhub.com` (and `www`, plus the API host if split) A-records at the VPS IP.
2. **TLS:** obtain real certificates (e.g. `certbot certonly --webroot`) and mount them at `nginx/ssl/fullchain.pem` + `nginx/ssl/privkey.pem`. Nothing ships with the repo; CI uses the HTTP-only `docker/nginx.ci.conf`.
3. **Deploy:** clone the repo, copy root `.env.example` to `.env`, replace every placeholder, run `docker compose config --quiet`, then `docker compose up -d --build`. Compose runs the one-shot `migrate` service before application replicas; never run migrations independently on each replica.
4. **Post-deploy checks:** `/api/health` → 200 `healthy`; homepage renders over HTTPS; login, agency workspace, a private document download and one queued job all succeed; watch logs for one business day before sign-off.
5. **Rollback:** keep previous image tags + a verified DB/storage backup; stop traffic at the gateway, restore previous images, restore data only if a schema/data regression is confirmed, re-run the B-gates on the rollback commit.

## 5. Vercel (frontend-only preview — NOT the production deployment)

A Vercel frontend-only deployment serves the SPA without the backend, database or private file volumes. Treat it as a preview/static public site, not the required full-stack production deployment; set `VITE_API_URL` there if it should call a separately hosted API.

## 6. Production .env checklist (all keys, NO secrets committed)

| Key | Requirement |
|---|---|
| `APP_ENV=production`, `APP_DEBUG=false` | **Mandatory** — debug pages must never render in production |
| `APP_KEY` | unique per environment, generated once (`base64:` + 32 random bytes); keep the same key for restore and all replicas; rotating it invalidates captcha challenges + encrypted columns |
| `DB_CONNECTION=mysql`, `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD` | MySQL 8; never the CI/test key |
| `DB_ROOT_PASSWORD` (compose) | initial bootstrap only |
| `REDIS_HOST/PORT/PASSWORD` | cache, sessions, queues, rate limits |
| `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=redis` | |
| `MAIL_MAILER/MAIL_HOST/MAIL_PORT/MAIL_USERNAME/MAIL_PASSWORD/MAIL_FROM_ADDRESS` | real SMTP; verify delivery of verification/reset mail |
| `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS` | exact production origin(s) — never `*` |
| `TRUSTED_PROXIES` | real load-balancer/nginx IPs (X-Forwarded-For spoofing prevention) |
| `APP_NAME`, `BCRYPT_ROUNDS`, `LOG_CHANNEL`, `LOG_LEVEL`, `SANCTUM_TOKEN_PREFIX` | explicit production runtime defaults; warning-level logs to stderr |
| `MALWARE_SCAN_ENABLED=true`, `MALWARE_SCAN_FAIL_CLOSED=true`, `MALWARE_SCANNER=clamav`, `CLAMD_SOCKET=clamav:3310` | fail-closed upload scanning |
| `STRIPE_*`, `FLUTTERWAVE_*` | Provider credentials alone do not make billing live: the agency UI currently uses platform-assigned subscriptions; Stripe checkout is API-only and Flutterwave has no checkout-initiation flow. Complete and verify the intended provider flow before enabling self-service payments |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | passed into the backend container; configure the exact API callback URI with Google |
| `SENTRY / LOGROCKET / GA` | optional monitoring ids |
| Backup credentials | off-site storage; `APP_KEY` stored separately from data backups |

See the repository-root `.env.example` for Docker Compose variables and `backend/.env.example` only for local non-container Laravel work. **Never commit real values.**

## 7. ClamAV (fail-closed)

Uploads (documents, versions, media) are scanned on every byte. Without a reachable scanner with current signatures, uploads are held undownloadable (423) — never accepted as clean. Ensure the `clamav` sidecar is healthy and its signatures can download on the target host.

## 8. Firebase (optional, not currently wired)

Firebase keys appear in `frontend/.env.example` as placeholders; no Firebase SDK is installed and no code path uses it. If Firebase Storage is adopted later, upload media via the Storage SDK instead of the current validated client-side path. Do not treat it as an active integration.

## 9. Rate limits

Current values (login 5/min, register/forgot 3/min, writes 30/min, public reads 300/min) are initial profiling targets — tune on production traffic before cutover; see `backend/app/Providers/AppServiceProvider.php`.
