#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SYSTEMD_DIR="/etc/systemd/system"

sudo install -m 0644     "$PROJECT_DIR/deploy/systemd/marketplace-analytics-server-status.service"     "$SYSTEMD_DIR/marketplace-analytics-server-status.service"

sudo install -m 0644     "$PROJECT_DIR/deploy/systemd/marketplace-analytics-server-status.timer"     "$SYSTEMD_DIR/marketplace-analytics-server-status.timer"

sudo systemctl daemon-reload
sudo systemctl enable --now marketplace-analytics-server-status.timer

echo "Server status timer installed."
sudo systemctl status marketplace-analytics-server-status.timer --no-pager
