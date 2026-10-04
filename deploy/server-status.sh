#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

COMPOSE=(docker compose -f compose.yaml -f compose.server.yaml)
APP_URL="http://127.0.0.1:8080"
APP_HOST="marketplace-analytics.my.id"
BACKUP_DIR="${BACKUP_DIR:-$HOME/Backups/marketplace-analytic}"
SNAPSHOT_PATH="${SERVER_STATUS_SNAPSHOT_PATH:-$PROJECT_DIR/storage/app/server-status.json}"

warn_count=0
fail_count=0

ok() {
    printf '[OK]   %s\n' "$*"
}

warn() {
    printf '[WARN] %s\n' "$*"
    warn_count=$((warn_count + 1))
}

fail() {
    printf '[FAIL] %s\n' "$*"
    fail_count=$((fail_count + 1))
}

printf '%s\n' '========================================'
printf '%s\n' ' Marketplace Analytics Server Status'
printf '%s\n' '========================================'
printf ' Host       : %s\n' "$(hostname)"
printf ' Checked at : %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')"
printf '\n'

# ---------------------------------------------------------------------------
# System
# ---------------------------------------------------------------------------

printf '%s\n' '[System]'

uptime_value="$(uptime -p)"
load_1m="$(awk '{print $1}' /proc/loadavg)"
memory_used="$(free -h | awk '/^Mem:/ {print $3}')"
memory_total="$(free -h | awk '/^Mem:/ {print $2}')"
disk_line="$(df -h / | awk 'NR==2 {print $3 " / " $2 " (" $5 ")"}')"
disk_pct="$(df --output=pcent / | tail -1 | tr -dc '0-9')"

printf ' Uptime     : %s\n' "$uptime_value"
printf ' Load (1m)  : %s\n' "$load_1m"
printf ' Memory     : %s / %s\n' "$memory_used" "$memory_total"
printf ' Disk /     : %s\n' "$disk_line"

if [[ "$disk_pct" =~ ^[0-9]+$ ]]; then
    if (( disk_pct >= 95 )); then
        fail "Disk / usage is ${disk_pct}%."
    elif (( disk_pct >= 85 )); then
        warn "Disk / usage is ${disk_pct}%."
    else
        ok "Disk / usage is ${disk_pct}%."
    fi
else
    warn "Unable to determine disk usage."
    disk_pct=0
fi

printf '\n'

# ---------------------------------------------------------------------------
# Docker
# ---------------------------------------------------------------------------

docker_snapshot='{}'

printf '%s\n' '[Docker]'

if ! "${COMPOSE[@]}" config --quiet >/dev/null 2>&1; then
    fail "Docker Compose configuration is invalid."
else
    running_services="$("${COMPOSE[@]}" ps --status running --services || true)"

    for service in caddy laravel.test mysql redis adminer; do
        service_health="no-healthcheck"

        if ! grep -qx "$service" <<< "$running_services"; then
            fail "Container service '$service' is not running."

            service_json="$(python3 - "$service" <<'PY'
import json
import sys

print(json.dumps({
    "status": "not-running",
    "health": "unknown",
}, separators=(",", ":")))
PY
)"
        else
            if [[ "$service" == "mysql" || "$service" == "redis" ]]; then
                container_id="$("${COMPOSE[@]}" ps -q "$service")"

                health="$(
                    docker inspect \
                        --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}no-healthcheck{{end}}' \
                        "$container_id" 2>/dev/null || true
                )"

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
            fi

            service_json="$(python3 - "$service_health" <<'PY'
import json
import sys

print(json.dumps({
    "status": "running",
    "health": sys.argv[1],
}, separators=(",", ":")))
PY
)"
        fi

        docker_snapshot="$(
            python3 - "$docker_snapshot" "$service" "$service_json" <<'PY'
import json
import sys

snapshot = json.loads(sys.argv[1])
service = sys.argv[2]
service_json = json.loads(sys.argv[3])

snapshot[service] = service_json

print(json.dumps(snapshot, separators=(",", ":")))
PY
        )"
    done
fi

printf '\n'

# ---------------------------------------------------------------------------
# Application
# ---------------------------------------------------------------------------

application_status="fail"

printf '%s\n' '[Application]'

if curl --silent --show-error --fail --max-time 5 \
    --header "Host: $APP_HOST" \
    --output /dev/null \
    "$APP_URL"; then
    ok "HTTP $APP_URL responded successfully."
    application_status="ok"
else
    fail "HTTP $APP_URL is not responding successfully."
fi

printf '\n'

# ---------------------------------------------------------------------------
# Backup
# ---------------------------------------------------------------------------

backup_integrity="fail"
backup_timer_enabled=false
backup_timer_active=false
latest_backup=""
backup_name=""
backup_size=""

printf '%s\n' '[Backup]'

if systemctl is-enabled --quiet marketplace-analytics-backup.timer 2>/dev/null; then
    ok "Backup timer is enabled."
    backup_timer_enabled=true
else
    warn "Backup timer is not enabled."
fi

if systemctl is-active --quiet marketplace-analytics-backup.timer 2>/dev/null; then
    ok "Backup timer is active."
    backup_timer_active=true
else
    fail "Backup timer is not active."
fi

latest_backup="$(
    find "$BACKUP_DIR" \
        -maxdepth 1 \
        -type f \
        -name 'mysql-*.sql.gz' \
        -printf '%T@ %p\n' 2>/dev/null |
        sort -nr |
        head -1 |
        cut -d' ' -f2- || true
)"

if [[ -n "$latest_backup" ]]; then
    backup_name="$(basename "$latest_backup")"
    backup_size="$(du -h "$latest_backup" | cut -f1)"

    printf ' Latest     : %s\n' "$backup_name"
    printf ' Size       : %s\n' "$backup_size"

    if gzip -t "$latest_backup" 2>/dev/null; then
        ok "Latest backup passes gzip integrity check."
        backup_integrity="ok"
    else
        fail "Latest backup failed gzip integrity check."
        backup_integrity="fail"
    fi
else
    fail "No MySQL backup found in $BACKUP_DIR."
fi

printf '\n'

# ---------------------------------------------------------------------------
# Storage / SMART
# ---------------------------------------------------------------------------

printf '%s\n' '[Storage / SMART]'

smart_health="unknown"

if command -v smartctl >/dev/null 2>&1; then
    smart_health="$(
        sudo -n /usr/bin/smartctl -H /dev/sda 2>/dev/null |
            awk -F: '
                /SMART overall-health/ || /SMART Health Status/ {
                    gsub(/^ +| +$/, "", $2)
                    print $2
                    exit
                }
            ' || true
    )"

    if [[ "$smart_health" == "PASSED" || "$smart_health" == "OK" ]]; then
        ok "SMART /dev/sda: $smart_health."
    elif [[ -n "$smart_health" ]]; then
        fail "SMART /dev/sda: $smart_health."
    else
        warn "SMART check skipped (passwordless sudo unavailable)."
        smart_health="unknown"
    fi
else
    warn "smartctl is not installed."
fi

printf '\n'

# ---------------------------------------------------------------------------
# Snapshot
# ---------------------------------------------------------------------------

mkdir -p "$(dirname "$SNAPSHOT_PATH")"

overall="ok"

if (( fail_count > 0 )); then
    overall="fail"
elif (( warn_count > 0 )); then
    overall="warn"
fi

backup_latest_json="null"
backup_size_json="null"

if [[ -n "$backup_name" ]]; then
    backup_latest_json="$backup_name"
fi

if [[ -n "$backup_size" ]]; then
    backup_size_json="$backup_size"
fi

export SNAPSHOT_PATH
export uptime_value
export load_1m
export memory_used
export memory_total
export disk_pct
export docker_snapshot
export application_status
export APP_URL
export backup_timer_enabled
export backup_timer_active
export backup_latest_json
export backup_size_json
export backup_integrity
export smart_health
export overall

snapshot_tmp="${SNAPSHOT_PATH}.tmp.$$"

python3 - "$snapshot_tmp" <<'PY'
import json
import os
import sys
from datetime import datetime

output_path = sys.argv[1]

data = {
    "schema_version": 1,
    "generated_at": datetime.now().astimezone().isoformat(timespec="seconds"),
    "host": os.uname().nodename,
    "overall": os.environ["overall"],
    "system": {
        "uptime": os.environ["uptime_value"],
        "load_1m": float(os.environ["load_1m"]),
        "memory_used": os.environ["memory_used"],
        "memory_total": os.environ["memory_total"],
    },
    "disk": {
        "percent": int(os.environ["disk_pct"]),
    },
    "docker": json.loads(os.environ["docker_snapshot"]),
    "application": {
        "status": os.environ["application_status"],
        "url": os.environ["APP_URL"],
    },
    "backup": {
        "timer_enabled": os.environ["backup_timer_enabled"] == "true",
        "timer_active": os.environ["backup_timer_active"] == "true",
        "latest": (
            os.environ["backup_latest_json"]
            if os.environ["backup_latest_json"] != "null"
            else None
        ),
        "size": (
            os.environ["backup_size_json"]
            if os.environ["backup_size_json"] != "null"
            else None
        ),
        "integrity": os.environ["backup_integrity"],
    },
    "storage": {
        "smart": os.environ["smart_health"],
    },
}

with open(output_path, "w", encoding="utf-8") as handle:
    json.dump(data, handle, indent=2, ensure_ascii=False)
    handle.write("\n")
PY

mv "$snapshot_tmp" "$SNAPSHOT_PATH"

# Validate the snapshot before considering generation successful.
python3 - "$SNAPSHOT_PATH" <<'PY'
import json
import sys

path = sys.argv[1]

with open(path, encoding="utf-8") as handle:
    data = json.load(handle)

if data.get("schema_version") != 1:
    raise SystemExit("Invalid server status schema version.")

required = {
    "generated_at",
    "host",
    "overall",
    "system",
    "disk",
    "docker",
    "application",
    "backup",
    "storage",
}

missing = required - data.keys()

if missing:
    raise SystemExit(
        "Missing snapshot fields: " + ", ".join(sorted(missing))
    )
PY

printf ' Snapshot   : %s\n' "$SNAPSHOT_PATH"
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
