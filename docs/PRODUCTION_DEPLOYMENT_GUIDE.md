# Production Deployment Guide

## Prerequisites

- Docker + Compose, MySQL 8.4, Redis 7, ClamAV 1.4.
- PHP 8.3 (FPM), Node 20.
- `.env` with APP_KEY, DB credentials, REDIS_PASSWORD, MAIL_*, Stripe/Flutterwave keys, SENTRY_DSN.

## Steps

1. Copy `.env.example` → `.env`, fill secrets (never commit).
2. `docker compose up -d` (mysql, redis, clamav, migrate, backend, frontend, nginx, worker, scheduler).
3. Migrations run once via `migrate` service.
4. Scale backend: `docker compose up -d --scale backend=2`.
5. Verify health: `php scripts/healthcheck.php`, `/healthz`.

## Rollback

- `docker compose down` (keep volumes) or restore from backup.
- Re-deploy previous image tag.

## Health checks

- Backend: `php scripts/healthcheck.php`
- Nginx: `/healthz`
- Frontend: `/health`
- Worker: `php artisan queue:monitor redis:default`
- Scheduler: `php artisan schedule:list`

## Notes

- APP_DEBUG=false, APP_ENV=production in production.
- MALWARE_SCAN_ENABLED=true, MALWARE_SCAN_FAIL_CLOSED=true.
- Shared volumes: private_vault, public_storage.
