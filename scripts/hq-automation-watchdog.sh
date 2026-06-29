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

# Consume any pending Drupal LangGraph runtime requests.
if runtime_out="$(python3 ./scripts/process-langgraph-runtime-requests.py --limit 10 2>&1)"; then
  log "runtime-requests result=ok detail=$(printf '%s' "$runtime_out" | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"
else
  log "runtime-requests result=warn detail=$(printf '%s' "$runtime_out" | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"
fi

if replay_out="$(python3 ./scripts/process-langgraph-replay-requests.py --limit 10 2>&1)"; then
  log "replay-requests result=ok detail=$(printf '%s' "$replay_out" | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"
else
  log "replay-requests result=warn detail=$(printf '%s' "$replay_out" | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"
fi

if promotion_out="$(python3 ./scripts/process-langgraph-promotion-requests.py --limit 10 2>&1)"; then
  log "promotion-requests result=ok detail=$(printf '%s' "$promotion_out" | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"
else
  log "promotion-requests result=warn detail=$(printf '%s' "$promotion_out" | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"
fi

# Keep community suggestions flowing into PM inbox automatically.
for site in forseti dungeoncrawler; do
  if ./scripts/suggestion-intake.sh "$site" >/dev/null 2>&1; then
    log "suggestion-intake site=${site} result=ok"
  else
    log "suggestion-intake site=${site} result=warn"
  fi
done
