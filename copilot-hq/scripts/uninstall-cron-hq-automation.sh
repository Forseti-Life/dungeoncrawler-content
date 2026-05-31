#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

MARKER="# copilot-sessions-hq:hq-automation"

current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$ROOT_DIR/scripts/hq-automation" || true)"
printf '%s\n' "$filtered" | sed '/^$/d' | crontab -

echo "Removed HQ automation cron entries (marker: $MARKER)"
