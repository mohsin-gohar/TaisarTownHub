# DEPLOYMENT_GUIDE.md — TaisarTownHub

**Detailed runbook:** [`documents/15_DEPLOYMENT_CICD.md`](documents/15_DEPLOYMENT_CICD.md) (v3.0).
**Pre-flight command:** `php artisan app:production-check --strict` (audits env, keys, DB,
migrations, storage writability, queue/cache/session drivers, Sanctum config, bcrypt rounds and
scheduled tasks).

---

## 1. Topology

```
                    ┌───────────────────────────┐
   Browser ────────▶│ frontend (nginx, static)  │  :80  (SPA + prerendered per-route HTML)
                    └────────────┬──────────────┘
                                 │ /api  (HTTPS, CORS allowlist)
                    ┌────────────▼──────────────┐
                    │ api (php artisan serve /  │  :8080
                    │ php-fpm + nginx)          │
                    └────────────┬──────────────┘
                                 │
              ┌──────────────────┼───────────────────┐
              ▼                  ▼                   ▼
        MariaDB / MySQL     queue worker        private disk
        (app data)          (database driver)   (vault documents)
```

---

## 2. Docker (recommended)

```bash
cd /path/to/TaisarTownHub
cp backend/.env.example backend/.env        # then edit secrets
cp frontend/.env.example frontend/.env      # VITE_API_URL=https://api.example.com/api
docker compose up -d --build
docker compose exec api php artisan migrate --force
docker compose exec api php artisan app:production-check --strict
```

`docker-compose.yml` starts **mysql**, **api**, **queue** and **web**. The document vault is a
named volume mounted at the *real* disk root (`storage/app/private/documents`), so the API container
can read and write it while the web container never can.

| Image | Base | Notes |
|---|---|---|
| `backend/Dockerfile` | php:8.3-cli | entrypoint fails fast without `APP_KEY`, runs `migrate --force`, then serves on 8080 |
| `frontend/Dockerfile` | node:20 → nginx | builds the SPA, prerenders the SEO routes, serves them with per-route HTML fallback |

---

## 3. Manual (non-container) deployment

### 3.1 API

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan key:generate --force          # never reuse the dev key
php artisan migrate --force
php artisan optimize                       # config + route + view caches
php artisan app:production-check --strict  # must print 0 fail
php artisan serve --port=8080              # or php-fpm behind nginx
```

### 3.2 Queue worker

```bash
php artisan queue:work database --sleep=1 --tries=3 --max-time=3600
```

Supervisor example (`/etc/supervisor/conf.d/taisartownhub-worker.conf`):

```ini
[program:tth-worker]
command=php /var/www/taisartownhub/backend/artisan queue:work database --sleep=1 --tries=3
directory=/var/www/taisartownhub/backend
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/taisartownhub/worker.log
```

### 3.3 Scheduler

```cron
* * * * * cd /var/www/taisartownhub/backend && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled: `sanctum:prune-expired --hours=48` (daily) and `queue:prune-failed --hours=168` (weekly).

### 3.4 Frontend

```bash
cd frontend
npm ci
VITE_API_URL=https://api.example.com/api VITE_APP_MODE=production npm run build
# dist/ → nginx root
```

The build emits the SPA shell **plus** prerendered HTML for every public route
(`/`, `/properties`, `/projects`, `/agencies`, `/guide`, `/gallery`, `/services`, `/about`,
`/contact`, `/login`, `/register`, `/admin-login`, `/admin`, `/reset-password`, `/verify-email`),
`sitemap.xml` (9 crawlable URLs) and `robots.txt`.

### 3.5 nginx

`frontend/nginx.conf` is the reference configuration. It serves the prerendered HTML per route,
falls back to the SPA shell, and sets the security headers, including the Content Security Policy:

```
default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none';
form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob: https:;
connect-src 'self' https: http://127.0.0.1:8090; media-src 'self' blob: data: https:;
frame-src 'self' https://www.google.com; worker-src 'self' blob:; manifest-src 'self'
```

The service worker (registered from the bundle, production hosts only) caches **only**
content-hashed `/assets/*` — no API, no private document, no financial response is ever cached.

---

## 4. Environment variables that matter

| Variable | Where | Rule |
|---|---|---|
| `APP_KEY` | API | generated per environment; rotating it invalidates encrypted columns (CNIC, MFA secrets) |
| `APP_DEBUG=false` | API | mandatory in production |
| `APP_URL` | API | public API origin |
| `FRONTEND_URL` | API | used to build mail links (reset/verify) |
| `CORS_ALLOWED_ORIGINS` | API | exact frontend origin(s), comma separated |
| `SESSION_SECURE_COOKIE=true` | API | mandatory behind HTTPS |
| `SANCTUM_TOKEN_PREFIX=tth_` | API | token prefix |
| `QUEUE_CONNECTION=database` | API | the database driver is used deliberately; Redis is **not** assumed |
| `DB_*` | API | MariaDB/MySQL in production |
| `MAIL_*` | API | real SMTP provider for verification/reset/security mail |
| `VITE_API_URL` | Frontend | absolute URL of the API, e.g. `https://api.example.com/api` |
| `VITE_APP_MODE=production` | Frontend | disables the presentation/demo chrome |

---

## 5. CI/CD

`.github/workflows/ci.yml` runs on every push/PR:

1. backend suite — `php artisan test` (SQLite `:memory:`, real migrations)
2. frontend gate — `npm run verify` (tsc → unit tests → build + prerender → bundle leak guard)
3. OpenAPI parse — the contract must stay valid YAML/OpenAPI

Merge gate: all three must be green.

---

## 6. Release checklist

- [ ] `php artisan migrate --force` applied, `php artisan migrate:status` shows no pending
- [ ] `php artisan app:production-check --strict` → 0 fail
- [ ] `GET /api/health` → `200 {"status":"healthy"}`
- [ ] Queue worker running, `jobs` table empty, `failed_jobs` empty
- [ ] Scheduler installed (token pruning + failed-job pruning)
- [ ] `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, CORS restricted to the real frontend origin
- [ ] Frontend build produced, prerendered HTML + `sitemap.xml` present
- [ ] HTTPS enforced, `Strict-Transport-Security` present on responses
- [ ] Backups taken and **verified** (see `BACKUP_GUIDE.md`)
- [ ] Service worker serving the new build (cache version bumped)
