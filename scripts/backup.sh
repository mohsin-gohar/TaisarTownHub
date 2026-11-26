#!/usr/bin/env bash
# TaisarTownHub — automated backup (database + application storage + manifest).
#
# Usage:  ./scripts/backup.sh [destination-dir]
# Default destination: /var/backups/taisartownhub (override with TTH_BACKUP_DIR).
#
# What it does:
#   1. logical database dump via mysqldump (MySQL 8 / MariaDB is the only driver)
#   2. tar archive of private documents, quarantine records and public assets
#   3. sha256 manifest for every artefact
#   4. verification (dump readable, table count > 0, vault listable)
#   5. retention pruning (daily 7 / weekly 4 / monthly 6 backup sets)
#
# Nothing is ever deleted from the live system — only from the backup folder.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$ROOT/backend"
DEST="${1:-${TTH_BACKUP_DIR:-/var/backups/taisartownhub}}"
STAMP="$(date +%Y%m%d_%H%M%S)"
WORK="$DEST/work-$STAMP"
MYSQL_DEFAULTS="$WORK/mysql.cnf"

mkdir -p "$DEST" "$WORK"
trap 'rm -f "$MYSQL_DEFAULTS"; rm -rf "$WORK"' EXIT
echo "[backup] destination: $DEST"

# Load Compose/Laravel DB metadata. Root .env wins for Docker Compose;
# backend/.env is the fallback for non-container local/CI backup runs.
php "$ROOT/scripts/write-mysql-defaults.php" "$ROOT" "$BACKEND" "$MYSQL_DEFAULTS" "$WORK/db-meta"
DB_DRIVER="$(sed -n '1p' "$WORK/db-meta")"
DB_NAME="$(sed -n '2p' "$WORK/db-meta")"
DB_HOST="$(sed -n '3p' "$WORK/db-meta")"
DB_PORT="$(sed -n '4p' "$WORK/db-meta")"
DB_USER="$(sed -n '5p' "$WORK/db-meta")"
if [ "$DB_DRIVER" != "mysql" ]; then
    echo "[backup] FATAL: only DB_CONNECTION=mysql is supported (found '$DB_DRIVER')" >&2
    exit 1
fi

# When the production Compose stack is running, read the named volumes from
# the actual containers. The host-side backend/storage path is NOT the Docker
# named-volume data and must never be mistaken for a production backup.
COMPOSE_SERVICES="$WORK/compose-services.txt"
USE_COMPOSE_BACKEND=false
USE_COMPOSE_MYSQL=false
if command -v docker >/dev/null 2>&1 \
    && docker compose --project-directory "$ROOT" -f "$ROOT/docker-compose.yml" ps --services --status running > "$COMPOSE_SERVICES" 2>/dev/null; then
    if grep -qx 'backend' "$COMPOSE_SERVICES"; then USE_COMPOSE_BACKEND=true; fi
    if grep -qx 'mysql' "$COMPOSE_SERVICES"; then USE_COMPOSE_MYSQL=true; fi
fi

if [ "$USE_COMPOSE_MYSQL" = true ] && [ "$USE_COMPOSE_BACKEND" != true ]; then
    echo "[backup] FATAL: Compose MySQL is running but the backend is not; refusing an incomplete storage backup." >&2
    exit 1
fi
if [ "$DB_HOST" = "mysql" ] && [ "$USE_COMPOSE_MYSQL" != true ]; then
    echo "[backup] FATAL: DB_HOST=mysql but the Compose MySQL service is not running." >&2
    exit 1
fi

# ---------------------------------------------------------------- database
echo "[backup] dumping database $DB_NAME"
if [ "$USE_COMPOSE_MYSQL" = true ]; then
    # The MySQL container has the real root secret in its environment. Write
    # it to a private temporary option file inside that container; never pass
    # the password in docker exec arguments or process command lines.
    docker compose --project-directory "$ROOT" -f "$ROOT/docker-compose.yml" exec -T mysql sh -c '
        set -u
        : "$MYSQL_ROOT_PASSWORD"
        defaults="$(mktemp)" || exit 1
        escaped_password="$(printf "%s" "$MYSQL_ROOT_PASSWORD" | sed '\''s/\\/\\\\/g; s/"/\\"/g'\'')"
        printf "[client]\nuser=root\npassword=\"%s\"\nhost=127.0.0.1\n" "$escaped_password" > "$defaults"
        chmod 600 "$defaults" || { rm -f "$defaults"; exit 1; }
        mysqldump --defaults-extra-file="$defaults" --single-transaction --routines --triggers "$1"
        status=$?
        rm -f "$defaults"
        exit "$status"
    ' tth-backup "$DB_NAME" | gzip > "$WORK/db.sql.gz"
else
    # Host/CI fallback: DB credentials come from the protected mode-0600 file.
    mysqldump --defaults-extra-file="$MYSQL_DEFAULTS" --single-transaction --routines --triggers "$DB_NAME" | gzip > "$WORK/db.sql.gz"
fi
DB_ARTEFACT="$WORK/db.sql.gz"
TABLE_COUNT="$(zcat "$DB_ARTEFACT" | grep -c 'CREATE TABLE' || true)"

# ---------------------------------------------------------------- application storage
VAULT_PATHS="$WORK/vault-paths.txt"
APP_STORAGE="$BACKEND/storage/app"
if [ "$USE_COMPOSE_BACKEND" = true ]; then
    docker compose --project-directory "$ROOT" -f "$ROOT/docker-compose.yml" exec -T backend sh -c '
        set -e
        root=/var/www/html/storage/app
        test -d "$root/private/documents"
        printf "%s\n" private
        if [ -d "$root/public" ]; then printf "%s\n" public; fi
    ' > "$VAULT_PATHS"
    docker compose --project-directory "$ROOT" -f "$ROOT/docker-compose.yml" exec -T backend sh -c \
        'tar -czf - -C /var/www/html/storage/app -T -' < "$VAULT_PATHS" > "$WORK/vault.tar.gz"
    echo "[backup] application storage read from live Docker named volumes"
else
    if [ ! -d "$APP_STORAGE/private/documents" ]; then
        echo "[backup] FATAL: private documents vault is missing at $APP_STORAGE/private/documents" >&2
        exit 1
    fi
    # Archive the whole private root so future private disks are not silently
    # omitted, plus the public volume used for maps and public media.
    printf '%s\n' private > "$VAULT_PATHS"
    if [ -d "$APP_STORAGE/public" ]; then printf '%s\n' public >> "$VAULT_PATHS"; fi
    tar -czf "$WORK/vault.tar.gz" -C "$APP_STORAGE" -T "$VAULT_PATHS"
fi

VAULT_ARTEFACT="$WORK/vault.tar.gz"
VAULT_ENTRIES="$(tar -tzf "$VAULT_ARTEFACT" | wc -l)"
if [ "$VAULT_ENTRIES" -le 0 ]; then
    echo "[backup] FATAL: application storage archive is empty or unreadable." >&2
    exit 1
fi
echo "[backup] application storage archived ($VAULT_ENTRIES entries)"

# ---------------------------------------------------------------- verify
if [ "$TABLE_COUNT" -le 0 ]; then
    echo "[backup] FATAL: dump contains no tables — aborting" >&2
    exit 1
fi
echo "[backup] verification: dump contains $TABLE_COUNT tables"

# ---------------------------------------------------------------- manifest
STAMPED_DB="$DEST/tth_db_${STAMP}.sql.gz"
STAMPED_VAULT="$DEST/tth_vault_${STAMP}.tar.gz"
mv "$DB_ARTEFACT" "$STAMPED_DB"
[ -n "$VAULT_ARTEFACT" ] && mv "$VAULT_ARTEFACT" "$STAMPED_VAULT"

MANIFEST="$DEST/manifest-${STAMP}.sha256"
# Write the manifest explicitly as "<sha256>  <filename>". Some sha256sum
# builds (e.g. Git Bash on Windows) emit "<sha256> *<filename>" for binary
# files, which the restore drill previously could not parse.
(
  cd "$DEST"
  DB_BASE="$(basename "$STAMPED_DB")"
  printf '%s  %s\n' "$(sha256sum "$DB_BASE" | awk '{print $1}')" "$DB_BASE" > "$(basename "$MANIFEST")"
  if [ -n "$VAULT_ARTEFACT" ]; then
    VAULT_BASE="$(basename "$STAMPED_VAULT")"
    printf '%s  %s\n' "$(sha256sum "$VAULT_BASE" | awk '{print $1}')" "$VAULT_BASE" >> "$(basename "$MANIFEST")"
  fi
)
echo "[backup] manifest: $MANIFEST"

# ---------------------------------------------------------------- retention
php "$ROOT/scripts/prune-backups.php" "$DEST"

echo "[backup] done"
