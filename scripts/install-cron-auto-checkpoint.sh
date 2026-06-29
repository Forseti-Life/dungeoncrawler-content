#!/usr/bin/env bash
set -euo pipefail

# Backward-compatible cleanup helper for legacy direct auto-checkpoint cron entries.
# Auto-checkpoint is now owned by hq-automation-watchdog -> hq-automation.sh ->
# auto-checkpoint-loop.sh. This script removes old direct cron entries so the
# checkpoint mechanism has a single scheduler.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"

MARKER="# copilot-sessions-hq:auto-checkpoint"
CMD="$ROOT_DIR/scripts/auto-checkpoint.sh"
LOG="$LOG_DIR/auto-checkpoint-cron.log"

current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

# Remove prior entries for this job.
filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$CMD" || true)"

# Write back without reinstalling the direct cron entry.
printf '%s\n' "$filtered" | sed '/^$/d' | crontab -

echo "Removed legacy direct auto-checkpoint cron entry (if present)."
echo "Auto-checkpoint is now managed by hq-automation-watchdog via auto-checkpoint-loop.sh."
