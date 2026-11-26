# TaisarTownHub API

This directory contains the Laravel 12 REST API for the TaisarTownHub property marketplace and multi-tenant agency ERP.

## Runtime stack

- PHP 8.3+
- MySQL 8 (production Compose currently targets MySQL 8.4)
- Laravel 12 + Sanctum bearer-token authentication
- Redis for production cache, sessions and queues
- ClamAV for fail-closed upload scanning
- Nginx + PHP-FPM in production

The root-level `docker-compose.yml` and `docs/DEPLOYMENT.md` are the canonical production deployment definition. Do not deploy this API with `php artisan serve` or the deprecated root `Dockerfile.prod`.

## Local development

1. Install PHP dependencies and create the local environment file:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. Create a MySQL database and configure `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`. Set the exact frontend origin in `CORS_ALLOWED_ORIGINS`.

3. Run migrations and the local seeders:

   ```bash
   php artisan migrate --seed
   php artisan serve --host=127.0.0.1 --port=8080
   ```

4. Point the frontend `VITE_API_URL` at `http://127.0.0.1:8080/api`.

Never use production credentials or a production database for local development or automated tests.

## Backend tests

The PHPUnit configuration intentionally uses **MySQL only**; SQLite is not a supported substitute for this suite. Create a dedicated, disposable database named `taisartownhub_test` on MySQL 8 and provide its credentials through `.env.testing` (this file is ignored by Git).

Minimum `.env.testing` database settings:

```dotenv
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taisartownhub_test
DB_USERNAME=root
DB_PASSWORD=your-local-test-database-password
```

Then run:

```bash
php artisan test
```

The test suite resets its schema. **Never point `.env.testing` at a database containing real or needed data.**

## Production readiness

Run the strict preflight inside the same production container/configuration that will be deployed:

```bash
php artisan app:production-check --strict --json
```

The command checks runtime configuration, secrets, MySQL/migrations, storage and vault encryption, malware scanning, Redis/cache/session/queue, mail configuration, OAuth configuration, cached config/routes, bcrypt strength and scheduler registration.

The preflight is a configuration gate, not a substitute for live-provider verification, a real backup/restore rehearsal, DNS/TLS validation or a staged deployment.

## Security rules

- Keep `.env`, `.env.testing`, private keys, provider secrets and real customer data out of Git.
- Never disable fail-closed malware scanning in production.
- Do not expose `storage/app/private` or the quarantine disk through Nginx or a public symlink.
- Run schema migrations once through the Compose `migrate` service; web replicas and workers must not race migrations.
- Use the authenticated API for tenant-owned writes; never trust client-supplied `agency_id`, owner IDs or platform permissions.
