#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"

MARKER="# copilot-sessions-hq:hq-automation"
WATCHDOG_CMD="$ROOT_DIR/scripts/hq-automation-watchdog.sh"
LOG="$LOG_DIR/hq-automation-cron.log"

WATCHDOG_LINE="* * * * * $WATCHDOG_CMD >> $LOG 2>&1 $MARKER"

current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$ROOT_DIR/scripts/hq-automation" || true)"

{
  printf '%s\n' "$filtered" | sed '/^$/d'
  echo "$WATCHDOG_LINE"
} | crontab -

echo "Installed cron: $WATCHDOG_LINE"
echo "Cron log: $LOG"
