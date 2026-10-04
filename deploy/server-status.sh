#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

COMPOSE=(docker compose -f compose.yaml -f compose.server.yaml)
APP_URL="http://127.0.0.1:8080"
BACKUP_DIR="${BACKUP_DIR:-$HOME/Backups/marketplace-analytic}"
SNAPSHOT_PATH="${SERVER_STATUS_SNAPSHOT_PATH:-$PROJECT_DIR/storage/app/server-status.json}"

warn_count=0
fail_count=0

ok()   { printf '[OK]   %s\n' "$*"; }
warn() { printf '[WARN] %s\n' "$*"; warn_count=$((warn_count + 1)); }
fail() { printf '[FAIL] %s\n' "$*"; fail_count=$((fail_count + 1)); }

printf '%s\n' '========================================'
printf '%s\n' ' Marketplace Analytics Server Status'
printf '%s\n' '========================================'
printf ' Host       : %s\n' "$(hostname)"
printf ' Checked at : %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')"
printf '\n'

printf '%s\n' '[System]'
printf ' Uptime     : %s\n' "$(uptime -p)"
printf ' Load (1m)  : %s\n' "$(awk '{print $1}' /proc/loadavg)"
printf ' Memory     : %s\n' "$(free -h | awk '/^Mem:/ {print $3 " / " $2}')"
disk_line="$(df -h / | awk 'NR==2 {print $3 " / " $2 " (" $5 ")"}')"
printf ' Disk /     : %s\n' "$disk_line"

disk_pct="$(df --output=pcent / | tail -1 | tr -dc '0-9')"
if (( disk_pct >= 95 )); then
    fail "Disk / usage is ${disk_pct}%."
elif (( disk_pct >= 85 )); then
    warn "Disk / usage is ${disk_pct}%."
else
    ok "Disk / usage is ${disk_pct}%."
fi
printf '\n'

docker_snapshot=''
printf '%s\n' '[Docker]'
if ! "${COMPOSE[@]}" config --quiet >/dev/null 2>&1; then
    fail "Docker Compose configuration is invalid."
else
    for service in caddy laravel.test mysql redis adminer; do
        if ! "${COMPOSE[@]}" ps --status running --services | grep -qx "$service"; then
            fail "Container service '$service' is not running."
        else
            if [[ "$service" == "mysql" || "$service" == "redis" ]]; then
                container_id="$("${COMPOSE[@]}" ps -q "$service")"
                health="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}no-healthcheck{{end}}' "$container_id" 2>/dev/null || true)"
                if [[ "$health" == "healthy" ]]; then
                    ok "$service: running / healthy."
                    service_health="$health"
                elif [[ "$health" == "no-healthcheck" ]]; then
                    ok "$service: running (no healthcheck)."
                    service_health="$health"
                else
                    fail "$service: health=$health."
                    service_health="$health"
                fi
            else
                ok "$service: running."
                service_health="no-healthcheck"
            fi
            docker_snapshot="${docker_snapshot}${docker_snapshot:+,}\"$service\":{\"status\":\"running\",\"health\":\"$service_health\"}"
        fi
    done
fi
printf '\n'

application_status="fail"
printf '%s\n' '[Application]'
if curl --silent --show-error --fail --max-time 5 \
    --header 'Host: marketplace-analytics.my.id' \
    --output /dev/null "$APP_URL"; then
    ok "HTTP $APP_URL responded successfully."
    application_status="ok"
else
    fail "HTTP $APP_URL is not responding successfully."
fi
printf '\n'

backup_integrity="fail"
printf '%s\n' '[Backup]'
if systemctl is-enabled --quiet marketplace-analytics-backup.timer 2>/dev/null; then
    ok "Backup timer is enabled."
else
    warn "Backup timer is not enabled."
fi

if systemctl is-active --quiet marketplace-analytics-backup.timer 2>/dev/null; then
    ok "Backup timer is active."
else
    fail "Backup timer is not active."
fi

latest_backup="$(find "$BACKUP_DIR" -maxdepth 1 -type f -name 'mysql-*.sql.gz' -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -1 | cut -d' ' -f2- || true)"
if [[ -n "$latest_backup" ]]; then
    printf ' Latest     : %s\n' "$(basename "$latest_backup")"
    printf ' Size       : %s\n' "$(du -h "$latest_backup" | cut -f1)"
    if gzip -t "$latest_backup" 2>/dev/null; then
        ok "Latest backup passes gzip integrity check."
        backup_integrity="ok"
    else
        fail "Latest backup failed gzip integrity check."
        backup_integrity="fail"
    fi
else
    fail "No MySQL backup found in $BACKUP_DIR."
    backup_integrity="fail"
fi
printf '\n'

printf '%s\n' '[Storage / SMART]'
if command -v smartctl >/dev/null 2>&1; then
    smart_health="$(sudo -n /usr/bin/smartctl -H /dev/sda 2>/dev/null | awk -F: '/SMART overall-health|SMART Health Status/ {gsub(/^ +| +$/, "", $2); print $2; exit}' || true)"
    if [[ "$smart_health" == "PASSED" || "$smart_health" == "OK" ]]; then
        ok "SMART /dev/sda: $smart_health."
    elif [[ -n "$smart_health" ]]; then
        fail "SMART /dev/sda: $smart_health."
    else
        warn "SMART check skipped (passwordless sudo unavailable)."
    fi
else
    warn "smartctl is not installed."
fi
printf '\n'

mkdir -p "$(dirname "$SNAPSHOT_PATH")"
disk_pct_snapshot="${disk_pct:-0}"
backup_name=""
backup_size=""
if [[ -n "${latest_backup:-}" ]]; then
    backup_name="$(basename "$latest_backup")"
    backup_size="$(du -h "$latest_backup" | cut -f1)"
fi
smart_snapshot="${smart_health:-unknown}"
overall="ok"
if (( fail_count > 0 )); then overall="fail"; elif (( warn_count > 0 )); then overall="warn"; fi
cat > "$SNAPSHOT_PATH" <<JSON
{
  "schema_version": 1,
  "generated_at": "$(date --iso-8601=seconds)",
  "host": "$(hostname)",
  "overall": "$overall",
  "system": {"uptime": "$(uptime -p)", "load_1m": $(awk "{print \\$1}" /proc/loadavg), "memory_used": "$(free -h | awk "/^Mem:/ {print \\$3}")", "memory_total": "$(free -h | awk "/^Mem:/ {print \\$2}")"},
  "disk": {"percent": $disk_pct_snapshot},
  "docker": {$docker_snapshot},
  "application": {"status": "$application_status", "url": "$APP_URL"},
  "backup": {"timer_enabled": $(systemctl is-enabled --quiet marketplace-analytics-backup.timer 2>/dev/null && echo true || echo false), "latest": ${backup_name:+\"$backup_name\"}${backup_name:-null}, "size": ${backup_size:+\"$backup_size\"}${backup_size:-null}, "integrity": "$backup_integrity"},
  "storage": {"smart": "$smart_snapshot"}
}
JSON

printf '%s\n' '========================================'
if (( fail_count > 0 )); then
    printf ' Status: FAIL (%d failure(s), %d warning(s))\n' "$fail_count" "$warn_count"
    exit 1
elif (( warn_count > 0 )); then
    printf ' Status: WARN (%d warning(s))\n' "$warn_count"
    exit 0
else
    printf ' Status: OK\n'
    exit 0
fi
printf '%s\n' '========================================'
