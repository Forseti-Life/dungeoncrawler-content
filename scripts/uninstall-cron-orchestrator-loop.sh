#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

MARKER="# copilot-sessions-hq:orchestrator"

current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$ROOT_DIR/scripts/orchestrator-loop.sh" | grep -vF "$ROOT_DIR/scripts/orchestrator-watchdog.sh" || true)"

printf '%s\n' "$filtered" | sed '/^$/d' | crontab -

echo "Removed orchestrator cron entries (marker: $MARKER)"

# Stop the cron-managed loop if it is running.
PIDFILE="$ROOT_DIR/.orchestrator-loop.pid"
if [ -f "$PIDFILE" ]; then
  pid="$(cat "$PIDFILE" 2>/dev/null || true)"
  if [[ "$pid" =~ ^[0-9]+$ ]] && ps -p "$pid" >/dev/null 2>&1; then
    echo "Stopping cron-managed orchestrator-loop pid $pid"
    kill "$pid" >/dev/null 2>&1 || true
  fi
fi
