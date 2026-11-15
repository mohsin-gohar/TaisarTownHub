#!/bin/sh
# G-15 — API container entrypoint.
# Fails fast when required secrets are absent (no silent defaults).

set -e

if [ -z "$APP_KEY" ]; then
  echo "[entrypoint] ERROR: APP_KEY is required (run 'php artisan key:generate' on the host and pass it as env)." >&2
  exit 1
fi

if [ -z "$DB_HOST" ] && [ "$DB_CONNECTION" != "sqlite" ]; then
  echo "[entrypoint] ERROR: DB_HOST is required for the configured DB_CONNECTION." >&2
  exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan migrate --force

# Queue worker is a separate compose service (same image); this entrypoint
# only serves HTTP so a web crash cannot take the queue down with it.
exec php artisan serve --host=0.0.0.0 --port=8080
