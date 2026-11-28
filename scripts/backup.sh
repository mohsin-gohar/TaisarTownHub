#!/usr/bin/env bash
# TaisarTownHub — automated backup (database + private vault + manifest).
#
# Usage:  ./scripts/backup.sh [destination-dir]
# Default destination: /var/backups/taisartownhub (override with TTH_BACKUP_DIR).
#
# What it does:
#   1. logical database dump via mysqldump (MySQL 8 / MariaDB is the only driver)
#   2. tar archive of the private document vault
#   3. sha256 manifest for every artefact
#   4. verification (dump readable, table count > 0, vault listable)
#   5. retention pruning (keep daily 7 / weekly 4 / monthly 6)
#
# Nothing is ever deleted from the live system — only from the backup folder.
set -euo pipefail
umask 077

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKEND="$ROOT/backend"
DEST="${1:-${TTH_BACKUP_DIR:-/var/backups/taisartownhub}}"
STAMP="$(date +%Y%m%d_%H%M%S)"
WORK=""

mkdir -p "$DEST"
WORK="$(mktemp -d "$DEST/.work-$STAMP-XXXXXX")"
cleanup() { if [ -n "$WORK" ] && [ -d "$WORK" ]; then rm -rf -- "$WORK"; fi; }\ntrap cleanup EXIT INT TERM
echo "[backup] destination: $DEST"

DB_DRIVER="$(grep -E '^DB_CONNECTION=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r' || echo mysql)"
if [ "$DB_DRIVER" != "mysql" ]; then
    echo "[backup] FATAL: only DB_CONNECTION=mysql is supported (found '$DB_DRIVER')" >&2
    exit 1
fi

# ---------------------------------------------------------------- database
DB_NAME="$(grep -E '^DB_DATABASE=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r')"
DB_USER="$(grep -E '^DB_USERNAME=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r')"
DB_HOST="$(grep -E '^DB_HOST=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r')"
DB_PORT="$(grep -E '^DB_PORT=' "$BACKEND/.env" | cut -d= -f2 | tr -d '\r' || echo 3306)"
# DB_PASSWORD may legitimately be empty; read it from .env without echoing it.
DB_PASSWORD="$(grep -E '^DB_PASSWORD=' "$BACKEND/.env" | cut -d= -f2- | tr -d '\r')"\n[ -n "$DB_NAME" ] || { echo "[backup] FATAL: DB_DATABASE is empty" >&2; exit 1; }\n[ -n "$DB_USER" ] || { echo "[backup] FATAL: DB_USERNAME is empty" >&2; exit 1; }
echo "[backup] mysql/mariadb driver → dumping $DB_NAME"
export MYSQL_PWD="$DB_PASSWORD"
mysqldump --single-transaction --routines --triggers --events --hex-blob \
    -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip > "$WORK/db.sql.gz"
unset MYSQL_PWD
DB_ARTEFACT="$WORK/db.sql.gz"
[ -s "$DB_ARTEFACT" ] || { echo "[backup] FATAL: database dump is empty" >&2; exit 1; }
gzip -t "$DB_ARTEFACT" || { echo "[backup] FATAL: database gzip stream is corrupt" >&2; exit 1; }
TABLE_COUNT="$(zcat "$DB_ARTEFACT" | grep -c 'CREATE TABLE' || true)"
zcat "$DB_ARTEFACT" | grep 'Dump completed' >/dev/null || { echo "[backup] FATAL: dump completion marker missing" >&2; exit 1; }

# ---------------------------------------------------------------- vault
VAULT_DIR="$BACKEND/storage/app/private/documents"
if [ -d "$VAULT_DIR" ]; then
    tar -czf "$WORK/vault.tar.gz" -C "$(dirname "$VAULT_DIR")" "$(basename "$VAULT_DIR")"
    VAULT_ARTEFACT="$WORK/vault.tar.gz"
    tar -tzf "$VAULT_ARTEFACT" > "$WORK/vault.list" || { echo "[backup] FATAL: vault archive integrity check failed" >&2; exit 1; }
    [ -s "$WORK/vault.list" ] || { echo "[backup] FATAL: vault archive contains no entries" >&2; exit 1; }
    echo "[backup] vault archived ($(wc -l < "$WORK/vault.list") entries)"
else
    VAULT_ARTEFACT=""
    echo "[backup] FATAL: private vault directory missing at $VAULT_DIR; database-only backup is forbidden" >&2
    exit 1
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
find "$DEST" -maxdepth 1 -type f \( -name 'tth_db_*.sql.gz' -o -name 'tth_vault_*.tar.gz' -o -name 'manifest-*.sha256' \) -mtime +180 -print -delete || true

echo "[backup] done"
