#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/orchestrator-watchdog.log"

ts="$(date -Iseconds)"

log() {
  printf '[%s] %s\n' "$ts" "$*" | tee -a "$LOG_FILE" >/dev/null
}

if [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" != "true" ]; then
  log "org disabled; skipping restart"
  exit 0
fi

if ./scripts/orchestrator-loop.sh verify >/dev/null 2>&1; then
  log "ok"
  exit 0
fi

log "orchestrator loop down; attempting restart"
./scripts/orchestrator-loop.sh start 60 >/dev/null 2>&1 || true
sleep 0.2

if ./scripts/orchestrator-loop.sh verify >/dev/null 2>&1; then
  log "restart succeeded"
  exit 0
fi

log "restart FAILED (manual intervention required)"
exit 1
