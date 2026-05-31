#!/usr/bin/env bash
set -euo pipefail

# Installs a cron entry that runs notify-pending.sh periodically.
# NOTE: per CEO directive, do not run this while crons are disabled.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"

MARKER="# copilot-sessions-hq:notify-pending"
CMD="$ROOT_DIR/scripts/notify-pending.sh"
LOG="$LOG_DIR/notify-pending-cron.log"

LINE="*/10 * * * * $CMD >> $LOG 2>&1 $MARKER"

current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$CMD" || true)"

{
  printf '%s\n' "$filtered" | sed '/^$/d'
  echo "$LINE"
} | crontab -

echo "Installed cron: $LINE"

