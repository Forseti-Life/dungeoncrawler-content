#!/usr/bin/env bash
set -euo pipefail

# Installs/updates a user crontab entry to run Forseti site audit + dispatch.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"

MARKER="# copilot-sessions-hq:qa-site-audit-forseti"
CMD="$ROOT_DIR/scripts/site-audit-run.sh forseti-life"
LOG="$LOG_DIR/qa-site-audit-forseti-cron.log"

# Hourly by default; adjust if you want more/less load.
LINE="15 * * * * $CMD >> $LOG 2>&1 $MARKER"

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
echo "Cron log: $LOG"
