#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

UNIT_NAME="copilot-sessions-hq-dev-sync.service"
UNIT_SRC="$ROOT_DIR/scripts/systemd/$UNIT_NAME"
UNIT_DIR="$HOME/.config/systemd/user"
UNIT_DEST="$UNIT_DIR/$UNIT_NAME"

if ! command -v systemctl >/dev/null 2>&1; then
  echo "ERROR: systemctl not found; cannot install systemd unit" >&2
  exit 1
fi

mkdir -p "$UNIT_DIR"
cp "$UNIT_SRC" "$UNIT_DEST"

systemctl --user daemon-reload
systemctl --user set-environment \
  DEV_SYNC_TARGET="${DEV_SYNC_TARGET:-dev-laptop}" \
  DEV_SYNC_DEFAULT_AGENT="${DEV_SYNC_DEFAULT_AGENT:-dev-forseti}" \
  DEV_SYNC_INTERVAL="${DEV_SYNC_INTERVAL:-300}" \
  DEV_SYNC_AUTO_PULL="${DEV_SYNC_AUTO_PULL:-1}" \
  DEV_SYNC_EXECUTE="${DEV_SYNC_EXECUTE:-1}" \
  DEV_SYNC_AUTO_COMMIT="${DEV_SYNC_AUTO_COMMIT:-0}" \
  DEV_SYNC_AUTO_PUSH="${DEV_SYNC_AUTO_PUSH:-0}"

systemctl --user enable "$UNIT_NAME"
systemctl --user restart "$UNIT_NAME" 2>/dev/null || systemctl --user start "$UNIT_NAME"

echo "Installed and started systemd user service: $UNIT_NAME"
systemctl --user --no-pager status "$UNIT_NAME" | sed -n '1,16p' || true
