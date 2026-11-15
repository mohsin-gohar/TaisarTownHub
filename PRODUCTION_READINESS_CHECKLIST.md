# Production Readiness Checklist

Status legend: ☐ = required before switching real traffic · ☑ = already
verified in this codebase · ⚠ = decision/open work (not a blocker for
launching with current scope).

## Code gates (all green — re-run before every release)

- ☑ Backend: `php artisan test` → **154 passed / 1506 assertions**
- ☑ Frontend: `npm run verify` (lint, 117 tests, build + prerender, bundle
  privacy guard)
- ☑ Route parity legacy ↔ `/api/v1` proven by test
- ☑ Migrations are additive only; no destructive operations without a
  verified backup + sign-off
- ☐ Re-run all gates on the release commit (CI: `.github/workflows/ci.yml`)

## Environment & secrets

- ☐ `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://api.<domain>`,
  `CORS_ALLOWED_ORIGINS=https://<frontend domain>`,
  `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS=<frontend domain>`
- ☐ Fresh `APP_KEY` on the server (never reuse the dev key)
- ☐ `FRONTEND_URL` set (mail links point at the SPA domain)
- ☐ `VITE_APP_MODE=production`, empty `VITE_DEMO_PIN`, HTTPS `VITE_API_URL`
  for the frontend build
- ☐ Secrets injected via environment/secret manager — never committed
- ☑ `.env.example` files document every required variable

## Database

- ☐ MySQL 8 with daily backups + one restore drill **proved**
- ☐ `php artisan migrate --force` (additive-only migrations)
- ☑ Encryption key rotation procedure documented (CNIC encrypted cast)
- ⚠ Off-site encrypted DB backups (depends on infrastructure choice)

## Private vault

- ☐ Vault volume mounted at `storage/app/private/documents` (docker-compose
  maps the `vault` volume), or S3 with server-side encryption
- ☐ Backup policy for the vault (files are irreplaceable legal documents)
- ☑ Vault is outside the web root; only policy-checked endpoints stream files

## Runtime

- ☐ HTTPS + TLS certificates; HSTS
- ☐ Supervisor (or compose `restart: unless-stopped`) for
  `php artisan queue:work database`
- ☐ `schedule:run` cron entry (listing/verification housekeeping)
- ☐ `php artisan optimize` (config/route/view caches)
- ☐ Log rotation; ship `X-Request-Id`-tagged logs to your aggregator
- ☐ Health probe wiring: `GET /api/health` (or `/up`) → LB/uptime monitor

## Observability

- ☐ Alert on `failed_jobs` growth and 5xx rate
- ☐ Audit-log retention policy (table + optional export)
- ⚠ Metrics/APM integration (open choice — no vendor lock assumed)

## Hardening notes

- Rate limits are in place (auth 5/min etc.) — tune the generic API throttle
  to expected traffic.
- CORS: keep the allow-list to the frontend origin only.
- When moving the API behind a proxy, trust only the correct
  `X-Forwarded-*` headers.

## Explicit non-goals for this release (documented decisions)

- MFA/SSO — deferred.
- Redis/Horizon — deferred until queue volume justifies it.
- Full 18-journey E2E matrix — Playwright smoke only (G-13).
