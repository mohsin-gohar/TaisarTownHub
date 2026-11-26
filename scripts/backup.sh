#!/usr/bin/env bash
# TaisarTownHub — automated backup (database + private vault + manifest).
#
# Usage:  ./scripts/backup.sh [destination-dir]
# Default destination: /var/backups/taisartownhub (override with TTH_BACKUP_DIR).
#
# What it does:
#   1. logical database dump via mysqldump (MySQL 8 / MariaDB is the only driver)
#   2. tar archive of the private document vault + quarantine records
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

# Load Laravel's parsed .env values without exposing DB_PASSWORD in process
# arguments or logs. The temporary MySQL option file is mode 0600 and removed
# automatically on every exit path.
php - "$BACKEND" "$MYSQL_DEFAULTS" "$WORK/db-meta" <<'PHP'
<?php
require $argv[1] . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable($argv[1])->safeLoad();

$get = static function (string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $value === false || $value === null ? $default : (string) $value;
};
$escape = static fn (string $value): string => '"' . addcslashes($value, "\\\"\n\r") . '"';

$driver = $get('DB_CONNECTION', 'mysql');
$database = $get('DB_DATABASE');
$host = $get('DB_HOST', '127.0.0.1');
$port = $get('DB_PORT', '3306');
$user = $get('DB_USERNAME');
$password = $get('DB_PASSWORD');

if ($database === '' || $user === '') {
    fwrite(STDERR, "[backup] FATAL: DB_DATABASE and DB_USERNAME must be configured.\n");
    exit(2);
}

$config = "[client]\n"
    . "host=" . $escape($host) . "\n"
    . "port=" . $escape($port) . "\n"
    . "user=" . $escape($user) . "\n"
    . "password=" . $escape($password) . "\n";
if (file_put_contents($argv[2], $config, LOCK_EX) === false || ! chmod($argv[2], 0600)) {
    fwrite(STDERR, "[backup] FATAL: could not create protected MySQL option file.\n");
    exit(2);
}
file_put_contents($argv[3], implode("\n", [$driver, $database, $host, $port, $user]) . "\n", LOCK_EX);
PHP
mapfile -t DB_META < "$WORK/db-meta"
DB_DRIVER="${DB_META[0]:-}"
DB_NAME="${DB_META[1]:-}"
DB_HOST="${DB_META[2]:-}"
DB_PORT="${DB_META[3]:-3306}"
DB_USER="${DB_META[4]:-}"
if [ "$DB_DRIVER" != "mysql" ]; then
    echo "[backup] FATAL: only DB_CONNECTION=mysql is supported (found '$DB_DRIVER')" >&2
    exit 1
fi

echo "[backup] mysql/mariadb driver → dumping $DB_NAME"
mysqldump --defaults-extra-file="$MYSQL_DEFAULTS" --single-transaction --routines --triggers "$DB_NAME" | gzip > "$WORK/db.sql.gz"
DB_ARTEFACT="$WORK/db.sql.gz"
TABLE_COUNT="$(zcat "$DB_ARTEFACT" | grep -c 'CREATE TABLE' || true)"

# ---------------------------------------------------------------- vault
PRIVATE_DIR="$BACKEND/storage/app/private"
if [ ! -d "$PRIVATE_DIR/documents" ]; then
    echo "[backup] FATAL: private documents vault is missing at $PRIVATE_DIR/documents" >&2
    exit 1
fi

VAULT_DIRS=(documents)
if [ -d "$PRIVATE_DIR/quarantine" ]; then
    VAULT_DIRS+=(quarantine)
fi

tar -czf "$WORK/vault.tar.gz" -C "$PRIVATE_DIR" "${VAULT_DIRS[@]}"
VAULT_ARTEFACT="$WORK/vault.tar.gz"
VAULT_ENTRIES="$(tar -tzf "$VAULT_ARTEFACT" | wc -l)"
if [ "$VAULT_ENTRIES" -le 0 ]; then
    echo "[backup] FATAL: private vault archive is empty or unreadable." >&2
    exit 1
fi
echo "[backup] private vault archived ($VAULT_ENTRIES entries: ${VAULT_DIRS[*]})"

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
