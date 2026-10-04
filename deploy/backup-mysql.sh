#!/usr/bin/env bash
set -euo pipefail

COMPOSE_FILE="${COMPOSE_FILE:-compose.production.yaml}"
BACKUP_DIR="${BACKUP_DIR:-./backups}"
mkdir -p "$BACKUP_DIR"

timestamp="$(date +%Y%m%d-%H%M%S)"
output="$BACKUP_DIR/mysql-${timestamp}.sql.gz"

docker compose -f "$COMPOSE_FILE" exec -T mysql \
  sh -c 'exec mysqldump --single-transaction --quick --routines --triggers -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip > "$output"

echo "Backup written to $output"
