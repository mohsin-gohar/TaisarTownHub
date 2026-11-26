#!/bin/sh
# G-15 — API container entrypoint.
#
# Responsibilities:
#   1. fail fast when required secrets are absent (no silent defaults)
#   2. cache config + routes
#   3. run migrations ONLY for the web entrypoint (see RUN_MIGRATIONS)
#   4. hand over to the command Docker Compose passed in (CMD/command),
#      so the queue-worker service really runs `php artisan queue:work`
#      instead of booting a second web server.
#
# Env:
#   APP_KEY           (required) - Laravel application key
#   DB_HOST           (required unless DB_CONNECTION=sqlite)
#   RUN_MIGRATIONS    'true' (default) | 'false'  - set 'false' for replicas/workers
#   MIGRATIONS_ONLY   'true' - run migrations then exit (one-shot migrate service)
#   APP_PORT          (optional) - port for the default web command

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

# Public disk symlink (maps / public media). The nginx gateway mounts the
# shared public_storage volume directly at /storage/..., so a symlink is only
# needed when the runtime user can safely create it for a single-container
# deployment. Never emit a production ERROR merely because a read-only app
# image runs as www-data.
if [ -L public/storage ]; then
  echo "[entrypoint] public/storage symlink already exists; leaving it unchanged."
elif [ -w public ]; then
  php artisan storage:link --force
else
  echo "[entrypoint] Skipping storage:link: public/ is not writable; nginx serves the shared public_storage volume directly."
fi

# Migrations run ONCE in the compose `migrate` service. Web replicas and queue
# workers set RUN_MIGRATIONS=false so they can never race on schema changes.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  php artisan migrate --force --no-interaction
fi

# One-shot migration service: do not exec the web command after migrating.
if [ "${MIGRATIONS_ONLY:-false}" = "true" ]; then
  echo "[entrypoint] migrations complete; exiting (MIGRATIONS_ONLY=true)."
  exit 0
fi

# Respect the command supplied by docker-compose (`command:`) / the image CMD.
if [ "$#" -gt 0 ]; then
  exec "$@"
fi

exec php artisan serve --host=0.0.0.0 --port="${APP_PORT:-8080}"
