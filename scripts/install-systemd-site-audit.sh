#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

SERVICE_NAME="copilot-sessions-hq-site-audit.service"
TIMER_NAME="copilot-sessions-hq-site-audit.timer"

UNIT_DIR="$HOME/.config/systemd/user"
SERVICE_SRC="$ROOT_DIR/scripts/systemd/$SERVICE_NAME"
TIMER_SRC="$ROOT_DIR/scripts/systemd/$TIMER_NAME"
SERVICE_DEST="$UNIT_DIR/$SERVICE_NAME"
TIMER_DEST="$UNIT_DIR/$TIMER_NAME"

if ! command -v systemctl >/dev/null 2>&1; then
  echo "ERROR: systemctl not found; cannot install systemd unit" >&2
  exit 1
fi

if [ ! -f "$SERVICE_SRC" ]; then
  echo "ERROR: missing unit template: $SERVICE_SRC" >&2
  exit 1
fi
if [ ! -f "$TIMER_SRC" ]; then
  echo "ERROR: missing unit template: $TIMER_SRC" >&2
  exit 1
fi

mkdir -p "$UNIT_DIR"
cp "$SERVICE_SRC" "$SERVICE_DEST"
cp "$TIMER_SRC" "$TIMER_DEST"

systemctl --user daemon-reload

systemctl --user enable "$TIMER_NAME"
systemctl --user restart "$TIMER_NAME" 2>/dev/null || systemctl --user start "$TIMER_NAME"

echo "Installed systemd user units: $SERVICE_NAME + $TIMER_NAME"
echo "Status (timer):"
systemctl --user --no-pager status "$TIMER_NAME" | sed -n '1,20p' || true

echo "Status (last run):"
systemctl --user --no-pager status "$SERVICE_NAME" | sed -n '1,25p' || true

if command -v journalctl >/dev/null 2>&1; then
  echo "Logs (tail):"
  journalctl --user -u "$SERVICE_NAME" -n 30 --no-pager || true
fi
