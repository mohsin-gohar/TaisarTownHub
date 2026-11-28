# Phase 1.8 — Environment & Configuration Inspection
Date: 2026-11-28
Repository: mohsin-gohar/TaisarTownHub
Branch: audit/phase-1-file-inventory-20261128

## Scope inspected
- Root and backend/frontend environment templates and ignore rules
- Root Docker Compose and CI override
- Backend/frontend production and development Dockerfiles
- PHP runtime ini, Laravel entrypoint and selected Laravel config (CORS, Sanctum, cache, queue, session, filesystem, services)
- Vite, TypeScript, npm and Node version configuration
- Vercel configuration at root and frontend
- Nginx gateway and frontend/backend Nginx/Supervisor configs
- CI workflow and deployment runbook

No real `.env` file was present in the tracked tree. Root `.env.example` is now tracked; `.gitignore` excludes actual environment files while allowing the example template.

## Confirmed issues and repairs

### 1. Docker Compose environment-file instructions were incorrect — fixed
Before:
- `docs/DEPLOYMENT.md` instructed operators to copy `backend/.env.example` to `backend/.env` before running root `docker compose`.
- Compose interpolation (`${VAR}`) reads the root `.env` file or shell environment, not `backend/.env`.
- The backend Docker build also excludes `.env` through `backend/.dockerignore`; runtime settings are injected by Compose.

Repair:
- Added root `.env.example` containing every variable referenced by `docker-compose.yml`.
- Corrected the Docker runbook to copy root `.env.example` to root `.env`.
- Clarified that backend/frontend environment templates are for direct non-container Laravel/Vite workflows.

### 2. APP_KEY was not required during Compose interpolation — fixed
Before:
- Compose passed `APP_KEY: ${APP_KEY}` to migrate, backend, worker and scheduler. Missing values could get as far as container startup before the Laravel entrypoint rejected the configuration.

Repair:
- All four Compose service references now use a required interpolation expression with an actionable error message.
- Root `.env.example` documents APP_KEY as required and unique per environment.

### 3. Deployment guide described SQLite as the local test default — fixed
Before:
- `docs/DEPLOYMENT.md` described `php artisan test` as using SQLite by default.
- `backend/phpunit.xml` explicitly configures MySQL and the `taisartownhub_test` database.

Repair:
- Updated the runbook to state that backend tests use the MySQL test database and require matching local `DB_*` configuration; CI uses MySQL 8.

### 4. Backend environment template did not match the documented active frontend origins — fixed
Before:
- Deployment documentation says the Vercel frontend origin must be allowed until custom-domain DNS is live.
- `backend/.env.example` only listed the apex custom domain in CORS and Sanctum stateful domains, and used the custom domain as FRONTEND_URL.

Repair:
- Added the known Vercel frontend, apex domain and www domain to the template's CORS allowlist and Sanctum stateful domains.
- Set the template FRONTEND_URL to the current Vercel frontend and documented switching it to the custom domain after cutover.
- Operators should remove any origin that is not actually serving the frontend.

## Configuration consistency reviewed
- Node: `.nvmrc` and Docker use Node 20; frontend engines require Node >=20; CI uses Node 22, which satisfies the range.
- PHP: Composer requires PHP ^8.2; production backend image and CI use PHP 8.3.
- Frontend: root Vercel config and frontend Vercel config both route `/api/*` to the API and provide SPA fallback; their header lists are not identical (known drift risk, previously noted in task 1.6).
- Docker production path: root Compose uses `backend/Dockerfile.prod`, `frontend/Dockerfile.prod` and `nginx/nginx.conf`.
- Legacy path: root `Dockerfile.prod` is explicitly deprecated; `docker/backend/nginx.conf`, `docker/backend/supervisord.conf`, and `docker/frontend/nginx.conf` belong to that historical single-image layout. Do not use them as the authoritative production configuration.
- Frontend .env values are build-time Vite inputs. Docker uses explicit build args; copying frontend/.env alone does not override those Compose build args.
- Firebase variables in `frontend/.env.example` are placeholders only; deployment documentation confirms Firebase is not wired into the app.
- Backend malware scanning is enabled fail-closed in the production template and Compose; PHPUnit/CI deliberately override it for isolated tests.

## Files changed
- `.env.example` — new root Compose environment template
- `backend/.env.example` — align CORS, Sanctum and frontend URL defaults with documented Vercel/custom-domain topology
- `docker-compose.yml` — fail early when APP_KEY is missing
- `docs/DEPLOYMENT.md` — correct Compose env-file instructions and MySQL test guidance

## Verification
- Compared all variable references in `docker-compose.yml` with root `.env.example`: no referenced Compose variable is missing from the template.
- Confirmed all four APP_KEY-consuming services use required interpolation.
- Fetched the changed files back from GitHub after commits.
- No real secret values were added.
- No application source code changed.
- CI was not triggered.

## Remaining operational requirement
A real deployment operator must populate root `.env` with unique APP_KEY, DB_ROOT_PASSWORD, DB_PASSWORD and REDIS_PASSWORD values, configure SMTP and optional integration credentials, and verify DNS/TLS/proxy addresses. The repository cannot safely invent or commit those production secrets.
