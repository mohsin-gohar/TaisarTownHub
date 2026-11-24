# TaisarTownHub — Demo Guide

(Consolidated from the former root `DEMO_START_GUIDE.md`; environment-specific notes updated: MySQL is the supported local dev database, see `docs/DEPLOYMENT.md`.)

Quick-start for a local demonstration of the agency-first marketplace.

## Prerequisites

- Node.js 20+ · PHP 8.2+ (mbstring, xml, curl, zip) · Composer · Git
- MySQL 8 (dev); the test suite defaults to SQLite `:memory:` via `phpunit.xml`.

## Backend (API on :8080)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# set DB_* for MySQL (create `taisartownhub` first)
php artisan migrate:fresh --seed   # demo agencies + properties (never in production)
php artisan serve --host=127.0.0.1 --port=8080
```

Verify: `http://127.0.0.1:8080/api/agencies` returns JSON.

## Frontend (Vite on :3000)

```bash
cd frontend
npm ci
cp .env.example .env   # VITE_API_URL=http://127.0.0.1:8080/api
npm run dev
```

Expected pages: homepage (navy/gold 12-section layout), `/agencies` directory, `/agencies/{slug}` profiles, `/properties?purpose=sale`, property detail, contact (captcha-gated inquiry form).

## Demo accounts (`DemoStaffSeeder`)

| Role | Email | Password |
|---|---|---|
| Super Admin | superadmin@taisartownhub.com | Super@Admin2026! |
| Agency Owner | admin@taisartownhub.com | Admin@Taisar2026 |
| Agency Manager | manager@taisartownhub.com | Manager@2026 |
| Dealer | dealer@taisartownhub.com | Dealer@2026 |
| Operator | operator@taisartownhub.com | Operator@2026 |

Recreate missing accounts with `php artisan db:seed --class=DemoStaffSeeder`. Demo credentials are local-only — never reuse in production.

## Demo workflow

**Visitor:** homepage → agencies → agency profile → property detail (no owner CNIC/private data exposed) → contact form (captcha + honeypot) → inquiry lands in the agency's CRM.

**Agency user:** login → agency dashboard → Secure File Vault (upload CNIC/ownership docs — validated, malware-scanned, stored under `storage/app/private/`, download via signed URLs) → property visibility toggle (hidden listings vanish from all public API results immediately) → CRM leads.

## Build / test commands

```bash
cd frontend && npm run verify   # tsc + unit tests + build + prerender + bundle check
cd backend  && php artisan test
```

## Troubleshooting

1. **Login fails** — demo accounts come from `DemoStaffSeeder`; re-seed. `password123` is not a valid demo password.
2. **Properties missing on agency profile** — check `approval_status = approved`, `marketplace_visibility = public`, and agency ownership (tenant isolation).
3. **Prerender fails (`dist/index.html` not found)** — run `vite build` first.
4. **Inquiry form shows no security question** — the backend must be reachable on the configured `VITE_API_URL`; without it the form fails politely instead of submitting.
