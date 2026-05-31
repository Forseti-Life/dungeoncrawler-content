#!/usr/bin/env bash
# Install all required HQ orchestration cron entries idempotently.
#
# Usage: bash scripts/install-crons.sh
#
# Running this script multiple times will NOT add duplicate cron entries.
# Each entry is tagged with a unique comment marker (# copilot-sessions-hq:<tag>).
# The script adds only entries whose tag is not already present in the crontab.
#
# Recovery usage (after cron wipe or environment migration):
#   bash /home/ubuntu/forseti.life/copilot-hq/scripts/install-crons.sh
#   crontab -l | grep copilot-sessions-hq   # verify

set -euo pipefail

HQ_ROOT="/home/ubuntu/forseti.life/copilot-hq"
LOG_DIR="${HQ_ROOT}/inbox/responses"

# Cron entries: each line is "TAG|SCHEDULE|COMMAND"
# TAG is used as the idempotency key (matched against "# copilot-sessions-hq:<tag>" comment).
ENTRIES=(
  "orchestrator-reboot|@reboot|ORCHESTRATOR_AGENT_CAP=6 ${HQ_ROOT}/scripts/orchestrator-loop.sh start 60 >> ${LOG_DIR}/orchestrator-cron.log 2>&1"
  "orchestrator-watchdog|*/5 * * * *|ORCHESTRATOR_AGENT_CAP=6 ${HQ_ROOT}/scripts/orchestrator-watchdog.sh >> ${LOG_DIR}/orchestrator-cron.log 2>&1"
  "hq-automation|* * * * *|${HQ_ROOT}/scripts/hq-automation-watchdog.sh >> ${LOG_DIR}/hq-automation-cron.log 2>&1"
  "ceo-ops|0 */2 * * *|${HQ_ROOT}/scripts/ceo-ops-once.sh >> ${LOG_DIR}/ceo-ops-cron.log 2>&1"
  "auto-checkpoint|0 */2 * * *|${HQ_ROOT}/scripts/auto-checkpoint.sh >> ${LOG_DIR}/auto-checkpoint-cron.log 2>&1"
  "hq-health-heartbeat|*/2 * * * *|${HQ_ROOT}/scripts/hq-health-heartbeat.sh >> /tmp/hq-health-heartbeat.log 2>&1"
)

# Load current crontab (ignore error if empty).
current_crontab="$(crontab -l 2>/dev/null || true)"
current_crontab="$(printf '%s\n' "$current_crontab" \
  | grep -vF "# copilot-sessions-hq:agent-exec-reboot" \
  | grep -vF "# copilot-sessions-hq:agent-exec-watchdog" \
  | grep -vF "${HQ_ROOT}/scripts/agent-exec-loop.sh" \
  | grep -vF "${HQ_ROOT}/scripts/agent-exec-watchdog.sh" \
  | grep -vF "${HQ_ROOT}/scripts/agent-exec-once.sh" || true)"

added=0
skipped=0

for entry in "${ENTRIES[@]}"; do
  IFS='|' read -r tag schedule command <<< "$entry"
  marker="# copilot-sessions-hq:${tag}"

  if printf '%s\n' "$current_crontab" | grep -qF "$marker"; then
    echo "skip (exists): ${tag}"
    skipped=$((skipped + 1))
    continue
  fi

  new_line="${schedule} ${command} ${marker}"
  current_crontab="${current_crontab}
${new_line}"
  echo "added: ${tag}"
  added=$((added + 1))
done

# Write back only if changes were made.
if [ "$added" -gt 0 ]; then
  printf '%s\n' "$current_crontab" | crontab -
  echo "install-crons: ${added} entries added, ${skipped} already present."
  echo "Verify: crontab -l | grep copilot-sessions-hq"
else
  echo "install-crons: all entries already present (${skipped} entries). No changes made."
fi
