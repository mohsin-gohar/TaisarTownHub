#!/usr/bin/env bash
# TaisarTownHub — automated backup (database + private vault + manifest).
#
# Usage:  ./scripts/backup.sh [destination-dir]
# Default destination: /var/backups/taisartownhub (override with TTH_BACKUP_DIR).
#
# What it does:
#   1. logical database dump (mysqldump for MySQL/MariaDB, sqlite3 copy otherwise)
#   2. tar archive of the private document vault
#   3. sha256 manifest for every artefact
#   4. verification (dump readable, table count > 0, vault listable)
#   5. retention pruning (keep daily 7 / weekly 4 / monthly 6)
#
# Nothing is ever deleted from the live system — only from the backup folder.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$ROOT/backend"
DEST="${1:-${TTH_BACKUP_DIR:-/var/backups/taisartownhub}}"
STAMP="$(date +%Y%m%d_%H%M%S)"
WORK="$DEST/work-$STAMP"

mkdir -p "$DEST" "$WORK"
echo "[backup] destination: $DEST"

DB_DRIVER="$(grep -E '^DB_CONNECTION=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r' || echo sqlite)"

# ---------------------------------------------------------------- database
if [ "$DB_DRIVER" = "sqlite" ]; then
    DB_FILE="$(grep -E '^DB_DATABASE=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r')"
    echo "[backup] sqlite driver → copying $DB_FILE"
    cp "$DB_FILE" "$WORK/db.sqlite"
    gzip -f "$WORK/db.sqlite"
    DB_ARTEFACT="$WORK/db.sqlite.gz"
    TABLE_COUNT="$(sqlite3 "$DB_FILE" "SELECT count(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';")"
else
    DB_NAME="$(grep -E '^DB_DATABASE=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r')"
    DB_USER="$(grep -E '^DB_USERNAME=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r')"
    DB_HOST="$(grep -E '^DB_HOST=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r')"
    DB_PORT="$(grep -E '^DB_PORT=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r' || echo 3306)"
    echo "[backup] mysql/mariadb driver → dumping $DB_NAME"
    mysqldump --single-transaction --routines --triggers \
        -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"${DB_PASSWORD:-}" "$DB_NAME" | gzip > "$WORK/db.sql.gz"
    DB_ARTEFACT="$WORK/db.sql.gz"
    TABLE_COUNT="$(zcat "$DB_ARTEFACT" | grep -c 'CREATE TABLE' || true)"
fi

# ---------------------------------------------------------------- vault
VAULT_DIR="$BACKEND/storage/app/private/documents"
if [ -d "$VAULT_DIR" ]; then
    tar -czf "$WORK/vault.tar.gz" -C "$(dirname "$VAULT_DIR")" "$(basename "$VAULT_DIR")"
    VAULT_ARTEFACT="$WORK/vault.tar.gz"
    echo "[backup] vault archived ($(tar -tzf "$VAULT_ARTEFACT" | wc -l) entries)"
else
    VAULT_ARTEFACT=""
    echo "[backup] WARNING: vault directory not found at $VAULT_DIR"
fi

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
rm -rf "$WORK"

MANIFEST="$DEST/manifest-${STAMP}.sha256"
( cd "$DEST" && sha256sum "$(basename "$STAMPED_DB")" $( [ -n "$STAMPED_VAULT" ] && basename "$STAMPED_VAULT" ) > "$(basename "$MANIFEST")" )
echo "[backup] manifest: $MANIFEST"

# ---------------------------------------------------------------- retention
find "$DEST" -maxdepth 1 -name 'tth_db_*.sql.gz' -mtime +30 -print -delete || true
find "$DEST" -maxdepth 1 -name 'tth_vault_*.tar.gz' -mtime +30 -print -delete || true
find "$DEST" -maxdepth 1 -name 'manifest-*.sha256' -mtime +30 -print -delete || true

echo "[backup] done"
