#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SYSTEMD_DIR="/etc/systemd/system"
LIBEXEC_DIR="/usr/local/libexec"
SERVICE_NAME="marketplace-analytics-server-status.service"
TIMER_NAME="marketplace-analytics-server-status.timer"
INSTALL_PATH="$LIBEXEC_DIR/marketplace-analytics-server-status"

# Stop the timer/service while replacing the executable so the installed
# systemd target is always the same version as the repository.
sudo systemctl stop "$TIMER_NAME" "$SERVICE_NAME" 2>/dev/null || true

sudo install -d -m 0755 "$LIBEXEC_DIR"
sudo install -m 0755     "$PROJECT_DIR/deploy/server-status.sh"     "$INSTALL_PATH"

sudo install -m 0644     "$PROJECT_DIR/deploy/systemd/$SERVICE_NAME"     "$SYSTEMD_DIR/$SERVICE_NAME"

sudo install -m 0644     "$PROJECT_DIR/deploy/systemd/$TIMER_NAME"     "$SYSTEMD_DIR/$TIMER_NAME"

sudo systemctl daemon-reload
sudo systemctl enable --now "$TIMER_NAME"

echo "Server status timer installed."
echo "Executable: $INSTALL_PATH"
sudo systemctl status "$TIMER_NAME" --no-pager
