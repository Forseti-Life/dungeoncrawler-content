#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"

# Keep a distinct marker so uninstall/replace is easy.
MARKER="# copilot-sessions-hq:orchestrator"
START_CMD="$ROOT_DIR/scripts/orchestrator-loop.sh start 60"
WATCHDOG_CMD="$ROOT_DIR/scripts/orchestrator-watchdog.sh"
LOG="$LOG_DIR/orchestrator-cron.log"

START_LINE="@reboot $START_CMD >> $LOG 2>&1 $MARKER"
WATCHDOG_LINE="*/5 * * * * $WATCHDOG_CMD >> $LOG 2>&1 $MARKER"

current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

# Remove any prior orchestrator entries.
filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$ROOT_DIR/scripts/orchestrator-loop.sh" || true)"

{
  printf '%s\n' "$filtered" | sed '/^$/d'
  echo "$START_LINE"
  echo "$WATCHDOG_LINE"
} | crontab -

echo "Installed cron: $START_LINE"
echo "Installed cron: $WATCHDOG_LINE"
echo "Cron log: $LOG"
