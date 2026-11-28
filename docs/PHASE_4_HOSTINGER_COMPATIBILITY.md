# Phase 4 Hostinger Compatibility

Branch: `audit/phase-4-production-readiness` @ `3b800ee`

## Compatible (shared hosting)
- PHP 8.2+ (select in control panel)
- MySQL 8 (available on Hostinger)
- Laravel 12 (with adjustments)
- .htaccess rewrite rules
- File uploads (with size limits)
- Composer dependencies (upload vendor/)

## NOT compatible (shared hosting)
- Redis (use database cache/session)
- Queue workers (use sync mode)
- Cron/scheduler (limited; use external cron)
- ClamAV (disable malware scanning)
- Docker containers
- Persistent background processes

## Required adjustments
- `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=sync`
- `MALWARE_SCAN_ENABLED=false`
- `TRUSTED_PROXIES` set to Hostinger proxy IPs
- `APP_URL` = production domain
- Storage permissions 755/775

## Recommendation
Shared hosting works for demo/staging. Production requires VPS for ClamAV, queues, cron, Redis.