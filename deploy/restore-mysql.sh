#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 path/to/backup.sql.gz" >&2
  exit 1
fi

backup="$1"
[[ -f "$backup" ]] || { echo "Backup not found: $backup" >&2; exit 1; }

COMPOSE_FILE="${COMPOSE_FILE:-compose.production.yaml}"

echo "This will restore data into the configured production database."
read -r -p "Type RESTORE to continue: " confirmation
[[ "$confirmation" == "RESTORE" ]] || { echo "Cancelled."; exit 1; }

gzip -dc "$backup" | docker compose -f "$COMPOSE_FILE" exec -T mysql sh -c \
  'exec mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'

echo "Restore completed."
