# TaisarTownHub — Deployment & CI/CD Pipeline Configuration (Pillar 15)

**Version:** 2.0.0 · **Last updated:** 2026-11-08

## 1. Environment Configuration

### .env.example (backend)
```
APP_NAME=TaisarTownHub
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.taisartownhub.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taisartownhub
DB_USERNAME=
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=taisartownhub.com
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@taisartownhub.com
```

### Frontend env
```
VITE_API_URL=https://api.taisartownhub.com/api
```

## 2. Build Scripts

### Frontend
```bash
cd frontend
npm ci
npm run lint          # tsc --noEmit
npm run test          # tsx --test
npm run build         # vite build → dist/
npm run check:bundle  # no ERP data/creds in public chunks
npm run verify        # lint + test + build + check:bundle (release gate)
```

### Backend
```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:seed --force   # first deploy only
```

## 3. Deployment (cPanel / shared hosting)

1. Upload backend to `public_html/api` (or subdomain).
2. Point document root to `backend/public`.
3. Set PHP 8.2+, enable extensions (pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath).
4. Create MySQL DB + user; update `.env`.
5. Run migrations via terminal or `php artisan migrate --force`.
6. Upload frontend `dist/` to `public_html/` (or CDN).
7. Set `VITE_API_URL` to production API URL before build.
8. Enable HTTPS (Let's Encrypt).

See `DEPLOYMENT.md` for cPanel-specific commands.

## 4. Server Requirements

- PHP ≥ 8.2 with extensions: pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath, fileinfo.
- MySQL ≥ 8.0 (or MariaDB ≥ 10.6).
- Node ≥ 18 (build only).
- Composer ≥ 2.

## 5. CI/CD Pipeline (GitHub Actions — recommended)

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
    services:
      mysql:
        image: mysql:8.4
        env: { MYSQL_ROOT_PASSWORD: root, MYSQL_DATABASE: taisartownhub }
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.2' }
      - run: cd backend && composer install && php artisan migrate --force && php artisan test
```

## 6. Smoke Test Suite

- `GET /api/health` → 200 `{ status: "healthy" }`.
- `GET /api/catalog` → 200 with projects/properties/videos.
- `POST /api/auth/login` with valid creds → 200 + token.
- `GET /api/workspace` with token → 200.
- Frontend: home page renders hero + sections; no console errors.
- `npm run check:bundle` → no ERP data/creds in public chunks.

## 7. Rollback Strategy

- Keep previous `dist/` and DB backup before deploy.
- Rollback code: `git revert` + redeploy.
- Rollback DB: restore from backup (migrations are forward-only; use backups for rollback).
- Health check after deploy; if fails, revert.

## 8. Monitoring (recommended)

- Uptime monitor on `/api/health`.
- Error tracking (Sentry) for frontend + backend.
- Log aggregation (Laravel logs + access logs).
- DB backup daily (automated).
