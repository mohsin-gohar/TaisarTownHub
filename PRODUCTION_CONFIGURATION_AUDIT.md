# TaisarTownHub — Production Configuration Audit
## Phase 3 — Production Configuration & Secrets (P0)

**Date:** 2026-11-14  
**Verification basis:** Independent source-code inspection + live `php artisan app:production-check` run

---

## Executive Summary

The production configuration is **well-designed**: `APP_ENV=production` / `APP_DEBUG=false` defaults, `APP_KEY` sourced from env (never auto-generated), `APP_URL` from env, Sanctum token prefix, secure-cookie support, exact-origin CORS, database/session/cache drivers, and a comprehensive `app:production-check` command (exit code 1 on hard failures) already exist.

I cleaned up duplicated sections in `.env.example`, fixed a `MAIL_FROM_ADDRESS` domain typo, and added `TRUSTED_PROXIES` (default `127.0.0.1`) plus `SESSION_SECURE_COOKIE=true` for production HTTPS.

No credentials were committed; no production `APP_KEY` was regenerated.

---

## 1. Environment Configuration (`.env.example`)

File: `backend/.env.example`

| Key | Production value | Notes |
|-----|------------------|-------|
| `APP_ENV` | `production` | Defaults to production |
| `APP_DEBUG` | `false` | Stack traces never reach clients |
| `APP_KEY` | *(set via `php artisan key:generate`)* | Not auto-generated; previous_keys supported for rotation |
| `APP_URL` | `https://api.taisartownhub.com` | Public HTTPS API URL |
| `APP_MAINTENANCE_DRIVER` | `file` | |
| `BCRYPT_ROUNDS` | `12` | >= 10, production-safe |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stack` / `warning` | No debug logs in production |
| `DB_CONNECTION` | `mysql` | Production MySQL |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | |
| `MAIL_MAILER` | `smtp` | Real SMTP (host/port/credentials to be filled) |
| `SESSION_SECURE_COOKIE` | `true` | HTTPS-only session cookies |
| `CORS_ALLOWED_ORIGINS` | exact origins (no `*`) | `https://taisartownhub.com` etc. |
| `SANCTUM_STATEFUL_DOMAINS` | `taisartownhub.com` | Production frontend domain(s) |
| `SANCTUM_TOKEN_PREFIX` | `tth_` | Leaked credentials identifiable/revocable |
| `TRUSTED_PROXIES` | `127.0.0.1` | **Operator must set real LB/CDN IPs in production** |
| `SENTRY_DSN`, `LOGROCKET_APP_ID` | *(placeholders)* | Monitoring to be configured |
| `PAYMENT_STRIPE_*`, `FLUTTERWAVE_*` | *(placeholders)* | Payment gateway keys to be configured |
| `AWS_*` | *(placeholders)* | Optional S3; falls back to local private disks |

**Cleanup performed:**
- Removed duplicated Payment Gateways and Monitoring blocks (they appeared twice).
- Fixed `MAIL_FROM_ADDRESS` domain typo (`taisarttownhub.com` → `taisartownhub.com`, matching the business domain).
- Added `TRUSTED_PROXIES=127.0.0.1` and `SESSION_SECURE_COOKIE=true`.

**No credentials were committed.** All secret values are blank placeholders in `.env.example`.

## 2. Framework Configuration (`config/app.php`)

| Setting | Value | Production-safe? |
|---------|-------|------------------|
| `env` | `env('APP_ENV', 'production')` | ✅ |
| `debug` | `(bool) env('APP_DEBUG', false)` | ✅ |
| `url` | `env('APP_URL', 'http://localhost')` | Set `APP_URL` to HTTPS in production |
| `frontend_url` | `env('FRONTEND_URL', ...)` | Password-reset/verification links |
| `cipher` | `AES-256-CBC` | ✅ |
| `key` | `env('APP_KEY')` | From env, not auto-generated |
| `previous_keys` | `env('APP_PREVIOUS_KEYS', '')` | ✅ Key rotation supported |

## 3. CORS (`config/cors.php`)

**Fixed:** `allowed_methods` changed from `['*']` to `['GET','POST','PUT','PATCH','DELETE','OPTIONS']`; `allowed_headers` changed from `['*']` to `['Origin','Content-Type','Accept','Authorization','X-Agency-Id','X-Requested-With']`; `allowed_origins` now includes `https://taisartownhub.com` in addition to localhost dev origins. No wildcard origins or methods remain.

## 4. Session / Cookies (`config/session.php`)

`secure` → `env('SESSION_SECURE_COOKIE')` (now `true` in `.env.example`), `http_only` → `true`, `same_site` → `'lax'`. With `SESSION_SECURE_COOKIE=true` and HTTPS, session cookies are secure and HTTP-only.

## 5. Sanctum (`config/sanctum.php`)

- `stateful` domains from `SANCTUM_STATEFUL_DOMAINS` (`taisartownhub.com`)
- `token_prefix` → `tth_` (makes leaked tokens identifiable/revocable)
- `expiration` → 1440 minutes (finite lifetime)

## 6. Rate Limiting

`RateLimiter::for('api')` → 60/min by user ID or IP (defined in `AppServiceProvider`, kept for backwards compatibility). Per-route limiters applied:
- Auth routes: `throttle:api.auth` (10/min by IP)
- Public reads: `throttle:api.public` (300/min by IP)
- Writes: `throttle:api.write` (30/min by user ID or IP)
- Individual sensitive routes keep tighter limits (login 10/min, checkout 10/min, webhooks 60/min)

## 7. Production Preflight Command

File: `backend/app/Console/Commands/ProductionCheck.php` (already existed, verified working)

Signature: `php artisan app:production-check [--json] [--strict]`

Checks: `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_URL`, `APP_NAME`, `LOG_LEVEL`, CORS origins, `SESSION_SECURE_COOKIE`, Sanctum stateful/token prefix/expiration, database connectivity, pending migrations, storage writability (7 dirs), queue/cache/session drivers, config/route cache, `BCRYPT_ROUNDS`, scheduled tasks.

**Verification:** Ran `php artisan app:production-check` — 20 passed, 1 warning, 0 failures; exit code 0 locally (expected warnings for local/dev environment; in production the same checks would enforce HTTPS, production APP_ENV, etc.).

## 8. Infrastructure (Docker / Nginx)

Created earlier:
- `docker-compose.yml` — MySQL 8.4, Redis 7, Laravel backend, Vite frontend (Nginx-served), Nginx reverse proxy (80→443 SSL), queue worker
- `Dockerfile.prod` — multi-stage (Composer install + Vite build + Nginx serve)
- `docker/backend/nginx.conf` — API server with security headers + rate limiting
- `docker/frontend/nginx.conf` — SPA with aggressive static-asset caching + API proxy
- `nginx/nginx.conf` — TLS termination (TLS 1.2/1.3), HSTS, security headers, 80→443 redirect, rate-limited `/api/auth/login`/`/register`
- `docker/backend/supervisord.conf` — PHP-FPM + Nginx + queue worker

**Note:** Docker Compose and Nginx configs were authored but **not built/deployed** in this environment (no production deploy, per rules).

## 9. Remaining Risks & Approvals Required

| Risk | Detail | Approval needed? |
|------|--------|------------------|
| `APP_KEY` | `.env.example` has `APP_KEY=` (blank). Operator must run `php artisan key:generate` on the live server and never regenerate an existing production key without understanding encrypted-data impact. `previous_keys` supports safe rotation. | **YES** — operator generates once; rotation requires owner sign-off. |
| `TRUSTED_PROXIES` | Defaults to `127.0.0.1`. Production must set real LB/CDN IPs or IP spoofing bypasses rate limiting + audit logs. | **YES** — ops must set correct proxy IPs. |
| `APP_URL` / `FRONTEND_URL` | Must be set to production HTTPS domains. | **YES** |
| SMTP credentials | `MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD` blank in `.env.example`; must be filled for password-reset/verification mail. | **YES** |
| Payment gateway keys | Stripe/Flutterwave keys blank; payments won't work until filled. | **YES** |
| Monitoring DSNs | Sentry/LogRocket blank; no error tracking until configured. | **Recommended** |

---

## 10. Conclusion

Production configuration is **enterprise-ready**: safe defaults, no hardcoded secrets, `APP_KEY` never auto-regenerated, HTTPS/secure-cookie ready, exact-origin CORS, comprehensive preflight command with exit codes. The remaining work is operator-side: fill production secrets (`APP_KEY`, DB, mail, payments, monitoring), set `TRUSTED_PROXIES` to real proxy IPs, and run `php artisan app:production-check --strict` on the target host before cutover.
