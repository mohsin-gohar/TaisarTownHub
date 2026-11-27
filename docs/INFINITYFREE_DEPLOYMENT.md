# InfinityFree Laravel Backend Deployment Guide

Backend target: `https://taisartownhubbackend.infinityfree.io`
Frontend (already live on Vercel): `https://taisartownhub.com`

## 1. Compatibility assessment

InfinityFree is shared hosting. The following Laravel features are **NOT supported** there:

| Feature | Status on InfinityFree | Required change |
|---|---|---|
| SSH / Composer CLI | Not available | Upload `vendor/` (~72 MB) manually |
| `php artisan` CLI | Not available | Run migrations via phpMyAdmin SQL import; no `vault:encrypt`, `purge-quarantine` CLI |
| Redis | Not available | `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=sync` |
| Queue workers / scheduler | Not available | `QUEUE_CONNECTION=sync` (jobs run inline); no cron — saved-search alerts won't run automatically |
| ClamAV daemon | Not available | `MALWARE_SCAN_ENABLED=false` (uploads accepted without scanning — security reduction) |
| TLS termination by app | Handled by host | Set `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`; trust proxy |
| File permissions | Limited | Ensure `storage/` and `bootstrap/cache/` are writable (755/775) |
| PHP version | Select 8.2+ in control panel | `composer.json` requires `^8.2` |

## 2. Pre-upload (local) steps

1. Copy `backend/.env.example` → `backend/.env` and fill:
   - `APP_ENV=production`, `APP_DEBUG=false`
   - `APP_URL=https://taisartownhubbackend.infinityfree.io`
   - `APP_KEY=` (generate locally: `php artisan key:generate --show`, paste the value)
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from InfinityFree MySQL panel
   - `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=sync`
   - `MAIL_MAILER=smtp` + real SMTP credentials (or leave blank; mail will fail)
   - `CORS_ALLOWED_ORIGINS=https://taisartownhub.com`
   - `FRONTEND_URL=https://taisartownhub.com`
   - `MALWARE_SCAN_ENABLED=false` (ClamAV unavailable on shared hosting)
   - `FILE_VAULT_ENCRYPTION=true` (uses APP_KEY; no CLI needed at runtime)
   - `TRUSTED_PROXIES=127.0.0.1` (InfinityFree proxy)
2. Export the local database schema + seed data:
   ```
   mysqldump -u root -p taisartownhub > taisartownhub.sql
   ```
   Import `taisartownhub.sql` via InfinityFree phpMyAdmin.
3. Clear caches locally (so no stale paths ship):
   ```
   php artisan config:clear; php artisan cache:clear; php artisan view:clear; php artisan route:clear
   ```
   Remove `bootstrap/cache/*.php` except `.gitignore`.

## 3. Upload structure (InfinityFree htdocs)

InfinityFree document root is `htdocs/`. Laravel's entry point is `public/index.php`, so restructure:

```
htdocs/
├── .htaccess              (root protection — see section 4)
├── index.php              (moved from public/, paths adjusted)
├── .htaccess.public       (Laravel's public/.htaccess, renamed)
├── favicon.ico
├── robots.txt
├── sitemap.xml
├── app/
├── bootstrap/
├── config/
├── database/
├── resources/
├── routes/
├── storage/
├── vendor/
├── .env                   (uploaded, NOT publicly readable — protected by .htaccess)
└── (no artisan needed on server)
```

Steps:
1. Upload the entire `backend/` directory contents into `htdocs/`.
2. Move `htdocs/public/index.php` → `htdocs/index.php` and adjust paths:
   - `__DIR__.'/../vendor/autoload.php'` → `__DIR__.'/vendor/autoload.php'`
   - `__DIR__.'/../bootstrap/app.php'` → `__DIR__.'/bootstrap/app.php'`
   - `__DIR__.'/../storage/framework/maintenance.php'` → `__DIR__.'/storage/framework/maintenance.php'`
3. Move `htdocs/public/.htaccess` → `htdocs/.htaccess` (Laravel's rewrite rules).
4. Add a protection `.htaccess` (see section 4) BEFORE the Laravel rewrite rules, or merge into the same file.
5. Delete `htdocs/public/` (now empty) or leave it (harmless).

## 4. Root .htaccess (protection + Laravel rewrite)

Place this as `htdocs/.htaccess`:

```apache
# Block direct access to sensitive files/directories
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
RedirectMatch 404 /\.env
RedirectMatch 404 /vendor/
RedirectMatch 404 /storage/
RedirectMatch 404 /bootstrap/
RedirectMatch 404 /config/
RedirectMatch 404 /database/
RedirectMatch 404 /routes/
RedirectMatch 404 /app/
RedirectMatch 404 /resources/
RedirectMatch 404 /tests/
RedirectMatch 404 /scripts/
RedirectMatch 404 /\.git

# Laravel front controller
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

## 5. Adjusted index.php (InfinityFree)

`htdocs/index.php`:

```php
<?php
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

## 6. InfinityFree-specific .env overrides

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://taisartownhubbackend.infinityfree.io
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
MALWARE_SCAN_ENABLED=false
MALWARE_SCAN_FAIL_CLOSED=false
TRUSTED_PROXIES=127.0.0.1
SESSION_SECURE_COOKIE=true
LOG_LEVEL=warning
```

## 7. Connect Vercel frontend to backend

In Vercel project settings → Environment Variables:
- `VITE_API_URL=https://taisartownhubbackend.infinityfree.io/api`

Redeploy the frontend. Verify `frontend/src/services/api.ts` uses `VITE_API_URL`.

## 8. CORS verification

Backend `.env`:
```
CORS_ALLOWED_ORIGINS=https://taisartownhub.com
SANCTUM_STATEFUL_DOMAINS=taisartownhub.com
```

Test with:
```
curl -i -X OPTIONS https://taisartownhubbackend.infinityfree.io/api/public/properties \
  -H "Origin: https://taisartownhub.com" \
  -H "Access-Control-Request-Method: GET"
```
Expect `Access-Control-Allow-Origin: https://taisartownhub.com`.

## 9. Test checklist (browser/API)

- [ ] `GET https://taisartownhubbackend.infinityfree.io/up` → 200
- [ ] `GET https://taisartownhubbackend.infinityfree.io/api/public/properties` → 200 JSON
- [ ] Login with demo account → returns Sanctum token
- [ ] Authenticated `/api/auth/me` → 200
- [ ] Create property (agency user) → 201
- [ ] Upload image → accepted (malware scan disabled)
- [ ] Vercel frontend loads properties from backend (no CORS errors in console)

## 10. Unsupported features / hosting limitations (InfinityFree)

1. **Malware scanning (ClamAV)** — disabled; uploads are NOT scanned. Security reduction. Recommend VPS for production.
2. **Queue workers** — `QUEUE_CONNECTION=sync`; heavy jobs (email, alerts) run inline and may slow requests.
3. **Scheduler/cron** — saved-search alerts and vault purge won't run automatically; set up external cron (e.g., cron-job.org) hitting a web endpoint, or run manually.
4. **Redis** — not available; using database cache/session (slower but functional).
5. **CLI/artisan** — no `php artisan` on server; migrations must be imported via phpMyAdmin; `vault:encrypt`, `purge-quarantine` must be run locally then uploaded.
6. **File storage** — local disk only; no S3. Backups are manual.
7. **Rate limiting** — uses database; may be slower under load.
8. **No HTTPS redirect at app level** — rely on host's HTTPS; ensure `APP_URL` and `SESSION_SECURE_COOKIE=true`.
9. **Storage permissions** — may need 777 on `storage/` and `bootstrap/cache/` (security trade-off on shared hosting).
10. **No monitoring/logging aggregation** — logs go to `storage/logs/`; review manually.

## Recommendation
InfinityFree can host the Laravel backend for **demo/staging** purposes. For **real production** with ClamAV, queues, cron, and reliable storage, use a VPS (DigitalOcean/Linode/Hetzner) or Laravel Forge. Do not claim production-grade security on shared hosting.