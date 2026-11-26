# TaisarTownHub — Deployment & Production Readiness (Pillar 15)

**Version:** 3.0.0 · **Last updated:** 2026-11-10

This document is the go-live runbook for the Laravel 12 API (`backend/`) and the
React 19 + Vite frontend (`frontend/`). The application is token-authenticated
(Sanctum bearer tokens), serves private documents/media only through
authenticated API endpoints, and keeps the public marketplace on the same API.

---

## 1. Environment configuration

### Backend (`.env`)

Start from `backend/.env.example` (production defaults). Minimum production set:

```dotenv
APP_NAME="TaisarTownHub API"
APP_ENV=production
APP_KEY=base64:...            # generate on the server
APP_DEBUG=false
APP_URL=https://api.taisartownhub.com
LOG_LEVEL=warning
BCRYPT_ROUNDS=12

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taisartownhub
DB_USERNAME=taisartownhub
DB_PASSWORD=...

SESSION_SECURE_COOKIE=true    # HTTPS only
SESSION_DRIVER=database
SANCTUM_STATEFUL_DOMAINS=taisartownhub.com
SANCTUM_TOKEN_PREFIX=tth_

# Exact frontend origin(s), comma separated, never a wildcard or localhost.
CORS_ALLOWED_ORIGINS=https://taisartownhub.com

QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@taisartownhub.com
```

Generate the key once per environment:

```bash
php artisan key:generate --force
```

Never copy a development `APP_KEY` to production (it would invalidate encrypted
sessions/cookies and weaken token handling).

### Frontend (`.env.production` or CI variables)

```dotenv
VITE_API_URL=https://api.taisartownhub.com/api
VITE_APP_MODE=production
VITE_DEMO_PIN=
```

`VITE_*` values are inlined at build time — rebuild the frontend after changing
the API URL. Production builds must not enable demo mode or a demo PIN.

---

## 2. Release gates

Both gates must be green before any deploy.

### Backend

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan test
php artisan app:production-check          # on the target server
```

### Frontend

```bash
cd frontend
npm ci
npm run verify        # tsc --noEmit + 111 API/permission tests + vite build + bundle guard
```

`check:bundle` fails if private ERP data, demo credentials, or server-only module
code leaks into a public chunk.

---

## 3. Server requirements

- PHP **8.2+** with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
  `ctype`, `json`, `bcmath`, `fileinfo`, `gd`/`imagick` (optional thumbnails).
- MySQL **8.0+** or MariaDB **10.6+** (`utf8mb4_unicode_ci`).
- Node **20+** (build machine only).
- Composer **2.6+**.
- Nginx (recommended) or Apache; HTTPS certificate (Let's Encrypt).
- Writable `storage/` and `bootstrap/cache/` for the PHP-FPM user.

---

## 4. First deploy

```bash
# 1. Code + dependencies
git clone <repo> /var/www/taisartownhub
cd /var/www/taisartownhub/backend
composer install --no-dev --optimize-autoloader

# 2. Environment
cp .env.example .env
# edit .env (section 1) then:
php artisan key:generate --force

# 3. Schema + reference data (first deploy only)
php artisan migrate --force
php artisan db:seed --force

# 4. Permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
# Optional: only needed if you later serve files from the `public` disk.
php artisan storage:link

# 5. Production caches
php artisan optimize

# 6. Preflight (must exit 0; review warnings)
php artisan app:production-check
```

The private document vault lives at `storage/app/private/documents` and is
**never** symlinked into `public/`. Do not create a web-server alias for it.

### Subsequent releases

```bash
php artisan down --retry=30
git pull --ff-only
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart          # workers pick up new code
php artisan up
```

Keep the previous release directory and a database dump (section 8) until the
health check and smoke suite pass.

---

## 5. Frontend build & serving

```bash
cd frontend
npm ci
VITE_API_URL=https://api.taisartownhub.com/api npm run build
# publish dist/ to /var/www/taisartownhub-frontend
```

SPA routing needs a fallback to `index.html` (see Nginx config). Hashed assets
under `/assets/` may be cached forever; `index.html` must be revalidated.

---

## 6. Queue worker and scheduler

The app uses the `database` queue and cache/session drivers. Run a supervised
worker (Supervisor example):

```ini
[program:taisartownhub-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/taisartownhub/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/taisartownhub/backend/storage/logs/worker.log
stopwaitsecs=3600
```

Restart workers on every deploy: `php artisan queue:restart`.

Scheduler cron (daily Sanctum token pruning + weekly failed-job pruning):

```cron
* * * * * cd /var/www/taisartownhub/backend && php artisan schedule:run >> /dev/null 2>&1
```

Verify registrations with `php artisan schedule:list`.

---

## 7. Nginx reference

```nginx
# ---------- API: api.taisartownhub.com ----------
server {
    listen 443 ssl http2;
    server_name api.taisartownhub.com;
    root /var/www/taisartownhub/backend/public;

    ssl_certificate     /etc/letsencrypt/live/api.taisartownhub.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.taisartownhub.com/privkey.pem;

    client_max_body_size 30m;          # keep in sync with storage.max_upload_mb
    add_header X-Content-Type-Options nosniff always;

    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* { deny all; }
}

# ---------- Frontend: taisartownhub.com ----------
server {
    listen 443 ssl http2;
    server_name taisartownhub.com www.taisartownhub.com;
    root /var/www/taisartownhub-frontend;
    index index.html;

    ssl_certificate     /etc/letsencrypt/live/taisartownhub.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/taisartownhub.com/privkey.pem;

    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location / { try_files $uri $uri/ /index.html; }

    location = /index.html {
        add_header Cache-Control "no-cache";
    }
}
```

The API already emits `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy`, COOP/CORP and HSTS (HTTPS only) from
`SecurityHeaders` middleware. Public media responses opt into
`Cross-Origin-Resource-Policy: cross-origin` so marketing pages on the frontend
domain can embed them; all private API routes stay `same-origin`.

### cPanel / shared hosting

1. Upload `backend/` outside the web root and point the (sub)domain document root
   to `backend/public`.
2. Upload `frontend/dist/` to the main domain document root.
3. Set PHP 8.3+, create MySQL, edit `.env`, run migrations via terminal.
4. Add the scheduler cron using the absolute `artisan` path; Supervisor is often
   unavailable — use the host's process manager or `queue:work` under `nohup`
   with monitoring.

---

## 8. Backup & restore

**Before every deploy / migration**, use the repository helper rather than placing database passwords in shell arguments:

```bash
cd /var/www/taisartownhub
export TTH_BACKUP_DIR=/secure/backups/taisartownhub
bash scripts/backup.sh "$TTH_BACKUP_DIR"
```

The helper creates a consistent MySQL dump, archives `backend/storage/app/private/documents`, `private/quarantine` (when present), and `public` (when present), verifies the archive and SHA-256 manifest, then applies the daily 7 / weekly 4 / monthly 6 retention policy. The database dump and storage archive are one backup set and must be restored together. Keep `.env` and `APP_KEY` in a separate secrets vault — never in Git or beside the backup data.

Restore only into the intended recovery environment:

```bash
gunzip -c /secure/backups/taisartownhub/tth_db_<STAMP>.sql.gz | mysql -u "$DB_USERNAME" -p "$DB_DATABASE"
tar -xzf /secure/backups/taisartownhub/tth_vault_<STAMP>.tar.gz -C backend/storage/app
php artisan migrate:status
php artisan app:production-check --strict
```

Run the isolated restore drill with `scripts/restore-drill.ps1` at least quarterly and before major schema migrations. The drill must verify checksums, table/index/foreign-key/row counts, storage archive extraction and scratch cleanup.

---

## 9. Health, smoke & monitoring

- Liveness: `GET /api/health` → 200 `{status:"healthy", database:"MySQL connected"}`.
  The endpoint runs `select 1`; it returns **503** with `status:"degraded"` when
  the database is unreachable, so an uptime monitor will page correctly.
- Post-deploy smoke: health, `GET /api/catalog`, login, `GET /api/auth/me`,
  `GET /api/workspace`, one agency-scoped read, then logout.
- Uptime monitor on `/api/health` every 60 s from an external region.
- Error tracking (Sentry or equivalent) for API + frontend; alert on 5xx rate.
- Centralise Nginx access/error logs and `storage/logs/laravel.log`.
- Watch queue depth (`jobs` table), failed jobs, disk usage and DB connections.

---

## 10. Security checklist (go-live)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS enforced with HSTS.
- [ ] Fresh `APP_KEY`; `.env` unreadable by other users (chmod 600).
- [ ] `CORS_ALLOWED_ORIGINS` lists only the production frontend origin.
- [ ] `SESSION_SECURE_COOKIE=true`; `SANCTUM_STATEFUL_DOMAINS` production-only.
- [ ] Sanctum token expiry finite (24 h) and pruning scheduled.
- [ ] `storage/app/private/documents` outside the web root; no symlink exposure.
- [ ] `php artisan app:production-check --strict` passes on the server.
- [ ] Database and document-vault backups verified.
- [ ] Demo PIN empty, demo mode off, no seeded demo credentials exposed in chunks.
- [ ] Rate limits active (login 5/min, register 3/min, API throttle).
- [ ] Queue worker + scheduler running under Supervisor/cron.
- [ ] Log rotation configured (`logrotate` or `LOG_STACK=daily`).

---

## 11. CI/CD pipeline (GitHub Actions)

```yaml
name: CI
on: [push, pull_request]

jobs:
  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: 20 }
      - run: cd frontend && npm ci && npm run verify

  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2', extensions: 'pdo_mysql, mbstring, bcmath, fileinfo' }
      - run: cd backend && composer install --prefer-dist --no-interaction
      - run: cd backend && cp .env.example .env && php artisan key:generate
      - run: cd backend && php artisan test
```

`php artisan app:production-check --strict` is a **target-server** gate (it reads
the real `.env`, database and filesystem), not a CI step. Run it after copying
the release and before switching traffic.

For CD, deploy only after both jobs pass, take a backup first, run
`php artisan migrate --force && php artisan optimize`, restart workers, then
curl `/api/health` and abort/rollback on non-200.

---

## 12. Rollback

1. Put the app in maintenance mode (`php artisan down`).
2. Switch the release symlink / redeploy the previous commit and `dist/`.
3. Restore the pre-deploy database dump **only** if the release included
   destructive migrations (this project uses additive migrations, so code
   rollback is usually enough).
4. `php artisan optimize:clear && php artisan optimize && php artisan queue:restart`.
5. `php artisan up`; verify `/api/health` and the smoke suite; monitor 5xx.
