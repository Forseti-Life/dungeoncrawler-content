#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

UNIT_NAME="copilot-sessions-hq-orchestrator.service"
UNIT_SRC="$ROOT_DIR/scripts/systemd/$UNIT_NAME"
UNIT_DIR="$HOME/.config/systemd/user"
UNIT_DEST="$UNIT_DIR/$UNIT_NAME"

if ! command -v systemctl >/dev/null 2>&1; then
  echo "ERROR: systemctl not found; cannot install systemd unit" >&2
  exit 1
fi

mkdir -p "$UNIT_DIR"
cp "$UNIT_SRC" "$UNIT_DEST"

# Reload units.
systemctl --user daemon-reload

# Ensure runtime backend settings are visible to the user service manager.
systemctl --user set-environment \
  HQ_AGENTIC_BACKEND="${HQ_AGENTIC_BACKEND:-auto}" \
  BEDROCK_ASSIST_SCRIPT="${BEDROCK_ASSIST_SCRIPT:-$ROOT_DIR/scripts/bedrock-assist.sh}" \
  BEDROCK_MAX_TOKENS="${BEDROCK_MAX_TOKENS:-700}"

# Enable and start (or restart if already running).
systemctl --user enable "$UNIT_NAME"
systemctl --user restart "$UNIT_NAME" 2>/dev/null || systemctl --user start "$UNIT_NAME"

echo "Installed and started systemd user service: $UNIT_NAME"
echo "Status:"
systemctl --user --no-pager status "$UNIT_NAME" | sed -n '1,16p' || true

echo "Logs (tail):"
# journalctl may not be available in minimal containers; best-effort.
if command -v journalctl >/dev/null 2>&1; then
  journalctl --user -u "$UNIT_NAME" -n 20 --no-pager || true
else
  echo "journalctl not found"
fi
