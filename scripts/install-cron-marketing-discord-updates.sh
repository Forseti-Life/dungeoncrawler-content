#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"

MARKER="# copilot-sessions-hq:marketing-discord-feature-updates"
CMD="$ROOT_DIR/scripts/marketing/run-discord-feature-updates.sh"
LOG="$LOG_DIR/marketing-discord-feature-updates-cron.log"

# Configuration defaults (overridable via environment at install time).
CRON_SCHEDULE="${MARKETING_DISCORD_CRON_SCHEDULE:-*/30 * * * *}"
WINDOW_DAYS="${MARKETING_FEATURE_UPDATE_DAYS:-3}"
MAX_ITEMS="${MARKETING_FEATURE_UPDATE_MAX_ITEMS:-10}"
SKIP_EMPTY="${MARKETING_FEATURE_UPDATE_SKIP_EMPTY:-1}"

WEBHOOK_ENV=""
if [[ -n "${DISCORD_WEBHOOK_FILE:-}" ]]; then
  WEBHOOK_ENV="DISCORD_WEBHOOK_FILE=${DISCORD_WEBHOOK_FILE}"
elif [[ -n "${DISCORD_WEBHOOK_URL:-}" ]]; then
  WEBHOOK_ENV="DISCORD_WEBHOOK_URL=${DISCORD_WEBHOOK_URL}"
else
  echo "ERROR: set DISCORD_WEBHOOK_FILE (recommended) or DISCORD_WEBHOOK_URL before installing cron." >&2
  exit 1
fi

LINE="${CRON_SCHEDULE} MARKETING_FEATURE_UPDATE_DAYS=${WINDOW_DAYS} MARKETING_FEATURE_UPDATE_MAX_ITEMS=${MAX_ITEMS} MARKETING_FEATURE_UPDATE_SKIP_EMPTY=${SKIP_EMPTY} ${WEBHOOK_ENV} ${CMD} >> ${LOG} 2>&1 ${MARKER}"

current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$CMD" || true)"

{
  printf '%s\n' "$filtered" | sed '/^$/d'
  echo "$LINE"
} | crontab -

echo "Installed cron: ${CRON_SCHEDULE} ${CMD}"
