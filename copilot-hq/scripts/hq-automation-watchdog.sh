#!/usr/bin/env bash
set -euo pipefail

# Runs fast convergence checks. Intended to be triggered by cron every minute.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/hq-automation-watchdog.log"

ts="$(date -Iseconds)"

log() {
  printf '[%s] %s\n' "$ts" "$*" >> "$LOG_FILE" 2>/dev/null || true
}

enabled="$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)"

./scripts/hq-automation.sh converge --no-require-enabled >/dev/null 2>&1 || true
log "enabled=${enabled} converge=done"

# Keep community suggestions flowing into PM inbox automatically.
for site in forseti dungeoncrawler; do
  if ./scripts/suggestion-intake.sh "$site" >/dev/null 2>&1; then
    log "suggestion-intake site=${site} result=ok"
  else
    log "suggestion-intake site=${site} result=warn"
  fi
done
