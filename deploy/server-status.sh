#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

COMPOSE=(docker compose -f compose.yaml -f compose.server.yaml)
APP_URL="http://127.0.0.1:8080"
BACKUP_DIR="${BACKUP_DIR:-$HOME/Backups/marketplace-analytic}"

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
                elif [[ "$health" == "no-healthcheck" ]]; then
                    ok "$service: running (no healthcheck)."
                else
                    fail "$service: health=$health."
                fi
            else
                ok "$service: running."
            fi
        fi
    done
fi
printf '\n'

printf '%s\n' '[Application]'
if curl --silent --show-error --fail --max-time 5 \
    --header 'Host: marketplace-analytics.my.id' \
    --output /dev/null "$APP_URL"; then
    ok "HTTP $APP_URL responded successfully."
else
    fail "HTTP $APP_URL is not responding successfully."
fi
printf '\n'

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
    else
        fail "Latest backup failed gzip integrity check."
    fi
else
    fail "No MySQL backup found in $BACKUP_DIR."
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
