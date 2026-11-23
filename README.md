# TaisarTownHub — Real Estate Marketplace & Agency ERP

Taiser Town Scheme 45, Karachi ke liye ek **sale-only public property marketplace** aur uske saath multi-tenant agency ERP. Public portal searchable listings, agencies aur maps dikhata hai; ERP agenciyon ko properties, inquiries, documents, finance, subscriptions aur team management deta hai.

> **Status:** Feature-complete application code with automated tests. It is **not yet cleared for a live production launch** — TLS certificates, real secrets/credentials and external services are still required. See `docs/FINAL_PRODUCTION_AUDIT.md` and `docs/PRODUCTION_RELEASE_CHECKLIST.md`.

---

## Repository layout

```text
frontend/   React 19 + TypeScript + Vite + Tailwind — public portal + ERP UI
backend/    Laravel 12 REST API (Sanctum), MySQL in production, SQLite for local tests
  routes/api.php             Entry point: mounts api_definitions.php at /api and /api/v1
  routes/api_definitions.php All endpoint definitions and middleware groups
  database/migrations        Schema (incl. maps, full-text and composite indexes)
  database/seeders           Demo agencies/properties/maps (development only)
docker/     Backend/frontend runtime configs + CI-only HTTP nginx config
nginx/      Production HTTPS gateway config (certificates go in nginx/ssl/)
scripts/    Backup/restore scripts + Docker CI readiness gate
docs/       openapi.yaml + production release gate documents
.github/    CI and Docker CI workflows
```

---

## Local development

### 1. Backend (Laravel API, port 8080)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Default local DB is SQLite (backend/database/database.sqlite).
# For MySQL, set DB_* in .env and create the database first.
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8080
```

API health probe: `http://127.0.0.1:8080/api/health`

### 2. Frontend (Vite, port 3000)

```bash
cd frontend
npm ci
cp .env.example .env      # VITE_API_URL normally http://127.0.0.1:8080/api
npm run dev
```

Open `http://localhost:3000`. From the repository root the same commands are available as `npm run dev:backend`, `npm run dev:frontend`, `npm run build` and `npm run verify`.

### 3. Docker (full stack)

```bash
# Required environment first: DB_ROOT_PASSWORD, DB_PASSWORD, REDIS_PASSWORD,
# APP_KEY plus the payment/mail/monitoring keys listed in backend/.env.example.
docker compose up -d --build
```

MySQL, Redis, ClamAV, one-shot migrations, PHP-FPM backend, frontend SPA, nginx gateway, queue worker and scheduler are all defined in `docker-compose.yml`. Production TLS files must be mounted at `nginx/ssl/fullchain.pem` and `nginx/ssl/privkey.pem`; CI uses `docker/nginx.ci.conf` (HTTP only) instead. See `DEPLOYMENT.md`, `HOSTINGER_VPS_DEPLOYMENT.md` and `FILE_STORAGE_DEPLOYMENT_GUIDE.md`.

---

## Testing (verified commands)

```bash
cd backend && php artisan test          # 362 passed (3054 assertions)
cd backend && composer audit            # 0 advisories (Laravel 12.73 / Symfony 7.4.23)
cd frontend && npm run verify          # tsc + 149 unit tests + build + prerender + bundle check
cd frontend && npm run e2e             # Playwright (starts Vite automatically; backend must be on :8080)
bash scripts/wait-for-compose-ready.sh 60 mysql:healthy redis:healthy   # Docker readiness gate helper
```

CI runs the backend suite on SQLite and MySQL 8, the frontend verify chain, an OpenAPI parse check, image builds and a containerised integration stack (`.github/workflows/ci.yml`, `.github/workflows/docker-ci.yml`).

---

## Key features

- Sale-only public marketplace with verified agencies, property search/filters and maps.
- Manually controlled, **free** Featured Agencies roster (no automatic paid placement, no fake social proof).
- Tenant-isolated agency workspaces: properties, inquiries/CRM, private document vault with quarantine and audit logging, finance/transactions, subscriptions, saved searches and team roles.
- Sanctum token auth, registration, email verification, password reset, MFA, Google OAuth, RBAC permissions and agency invitations.
- Admin oversight: users, agencies, featured roster, platform settings, maps and audit logs.
- SEO-prerendered public routes, responsive UI, PWA manifest and security headers.

## Known limitations / external requirements

- No TLS certificate is committed — required before serving real traffic.
- Real `APP_KEY`, database, SMTP, monitoring and payment-provider credentials must be supplied at deploy time; none are baked into images.
- ClamAV must be running for document uploads where fail-closed scanning is enabled.
- Browser QA automates Chromium only; other browsers, load testing and Lighthouse Core Web Vitals have not been run in this repository.
- The repo root contains many historical audit reports from earlier missions; the release-gate documents in `docs/` are the current source of truth.
