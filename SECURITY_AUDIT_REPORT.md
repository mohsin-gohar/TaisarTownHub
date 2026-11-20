# Security Audit Report — TaisarTownHub

**HEAD:** `28c32edb7bd9f6267e5a58bb97239b371c4b1084`
**Date:** 2026-11-20

## Verified security posture
- Laravel backend suite: **320 passed, 2587 assertions** on clean isolated run.
- One earlier rerun after restoring the SQLite backup failed one local-state test because the restored vault contained files the test expects empty; after cleaning local test-artifact files, the clean isolated run passes **320/2587**.
- Targeted OAuth/storage/security suites: **35 passed, 267 assertions** after latest changes.
- Public bundle scan: `check:bundle` passes with no private ERP data or demo credentials in public chunks.
- Production readiness check: **20 PASS, 1 WARN, 0 FAIL** (`php artisan app:production-check --json`).
- Routes: backend API routes verified via `php artisan route:list` (public/auth/admin/OAuth/properties/documents/media).
- Migrations: `php artisan migrate:status` shows all migrations `[1] Ran`; `php artisan migrate --pretend` reports nothing to migrate (after backup restore).
- Security headers: backend tests confirm restrictive headers on public API responses.
- Tenant isolation/RBAC: targeted suites cover agency scoping, token revocation, permission gates, and cross-agency denial.

## Verified controls in this pass
- Google OAuth: CSRF/state binding, verified email, safe account linking, replay protection, account-status gates, MFA challenge.
- Password auth: same blocked-account rules, case-insensitive legacy emails, normalized emails.
- Storage: per-file limits, extension allowlist, MIME/magic validation, malware scan behavior, encrypted vault storage, tenant isolation.
- Featured Agencies: free launch, truthful Featured badge, Super Admin selection/order, expiry, no Sponsored badge.

## Security gaps still not fully proven
- **Live ClamAV/ClamAV runtime**: fake-clamd protocol tests pass, but the real `clamd` daemon/service was not started in this environment.
- **Live MySQL integration**: MySQL binaries are installed but service not reachable on 3306; SQLite is the verified local path.
- **Docker runtime**: not executed locally (Docker unavailable).
- **Payment sandbox**: not executed; no credentials configured locally.
- **Role/admin browser E2E**: not verified in this environment due login 422/500 and intermittent dev-server availability.
- **Public agency profile was hardened to sale-only stats** during this audit; verify live if cross-checking against a pre-fix artifact.

## Do not treat as production-ready until
1. MySQL service is reachable and the MySQL integration suite is run.
2. Docker runtime is built/started (backend, frontend, MySQL, Redis, ClamAV, Nginx, worker).
3. Real ClamAV daemon is running and live upload+scan is verified.
4. Live payment sandbox is configured if payment flow is desired to return.
5. Role/admin E2E is rerun after service availability is restored.
6. Load/stress and backup/restore drills are run on a controlled environment.
