#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

UNIT_NAME="copilot-sessions-hq-master-node.service"
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
  MASTER_NODE_INTERVAL="${MASTER_NODE_INTERVAL:-60}"

systemctl --user enable "$UNIT_NAME"
systemctl --user restart "$UNIT_NAME" 2>/dev/null || systemctl --user start "$UNIT_NAME"

echo "Installed and started systemd user service: $UNIT_NAME"
systemctl --user --no-pager status "$UNIT_NAME" | sed -n '1,16p' || true
