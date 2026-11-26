# TaisarTownHub — Real Estate Marketplace & Agency ERP

Taiser Town Scheme 45, Karachi: a **sale-only public property marketplace** plus multi-tenant agency ERP. The public portal serves searchable listings, verified agencies and maps; the ERP gives agencies properties, inquiries/CRM, a private document vault, finance, subscriptions and team management.

> **Status:** feature-complete application code with automated tests. **Not cleared for a live production launch** — TLS, real secrets and external services are still required. Single source of truth for status: [`docs/STATUS.md`](docs/STATUS.md).

## Stack

`frontend/` React 19 + TypeScript + Vite + Tailwind (public portal + ERP UI, SEO prerender) · `backend/` Laravel 12 REST API (Sanctum token auth, MFA, RBAC) on MySQL 8 · `docker/` + `docker-compose.yml` (nginx, PHP-FPM, MySQL 8, Redis, ClamAV, queue, scheduler) · `nginx/` production TLS gateway · `docs/` consolidated documentation (this tree).

## Run it

```bash
# backend (API on :8080)
cd backend && composer install && cp .env.example .env
# Configure a local database user and local CORS origins in backend/.env.
# Example: DB_USERNAME=root and CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000
php artisan key:generate && php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8080

# frontend (Vite on :3000)
cd frontend && npm ci && cp .env.example .env && npm run dev
# VITE_API_URL=http://127.0.0.1:8080/api

# or the full stack (return to the repository root first)
cd ..
cp .env.example .env
# Replace every CHANGE_ME value. Generate APP_KEY with:
# openssl rand -base64 32, then prefix the output with "base64:"
# Generate DB_PASSWORD, DB_ROOT_PASSWORD and REDIS_PASSWORD with:
# openssl rand -hex 32
docker compose config --quiet
docker compose up -d --build
docker compose exec --user www-data backend php artisan app:production-check --strict
```

Demo accounts and walkthrough: `docs/DEMO_GUIDE.md`. Deployment/VPS/TLS/env checklist: `docs/DEPLOYMENT.md`.

## Test it

```bash
cd backend  && php artisan test        # full backend suite (SQLite default; MySQL 8 in CI)
cd frontend && npm run verify          # tsc + unit tests + build + prerender + bundle check
cd frontend && npm run e2e             # Playwright (backend must be on :8080)
```

CI (`.github/workflows/`) runs the backend suite on MySQL 8, the frontend verify chain, an OpenAPI parse check and a containerised integration stack.

## Key features

- Sale-only public marketplace: verified agencies, search/filters, maps, SEO-prerendered routes.
- Manually controlled, **free** Featured Agencies roster (no paid placement, no fake social proof).
- Tenant-isolated agency workspaces: properties, inquiries/CRM, private document vault (quarantine, share links, audit logging), finance, subscriptions, saved searches, team roles.
- Sanctum token auth, registration, email verification, password reset, MFA, Google OAuth, RBAC (62 permissions / 13 roles), agency invitations.
- Bot-protected public inquiry intake (signed math captcha + honeypot).
- Admin oversight: users, agencies, featured roster, settings, maps, audit logs.

## Docs

| Doc | Content |
|---|---|
| `docs/API.md` | endpoint families, auth, envelopes, rate limits |
| `docs/DATABASE.md` | ERD, table groups, engine policy |
| `docs/DEPLOYMENT.md` | topology, Docker, VPS + DNS/TLS, production env checklist |
| `docs/SECURITY.md` | tenancy, RBAC matrix, vault architecture, MFA |
| `docs/BACKUP_RESTORE.md` | backup/restore + drill checklist |
| `docs/DEMO_GUIDE.md` | local demo quick-start |
| `docs/STATUS.md` | what is verified / not verified / blocked |
| `docs/openapi.yaml` | machine-readable API contract |
| `documents/` | long-form internal design docs |

## Known limitations / external requirements

- No TLS certificate is committed — required before serving real traffic.
- Real `APP_KEY`, database, SMTP, monitoring and payment credentials must be supplied at deploy time; none are baked into images.
- ClamAV must be reachable for fail-closed upload scanning.
- `Dockerfile.prod` at the root is deprecated — use the per-app `Dockerfile.prod` files with `docker-compose.yml`.
