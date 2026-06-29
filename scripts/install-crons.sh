#!/usr/bin/env bash
# Install all required HQ orchestration cron entries idempotently.
#
# Usage: bash scripts/install-crons.sh
#
# Running this script multiple times will NOT add duplicate cron entries.
# Each managed entry is tagged with a unique comment marker (# copilot-sessions-hq:<tag>).
# Re-running the script refreshes the managed commands in place so path migrations and
# command changes are propagated to the installed crontab.
#
# Recovery usage (after cron wipe or environment migration):
#   bash /home/ubuntu/forseti.life/scripts/install-crons.sh
#   crontab -l | grep copilot-sessions-hq   # verify

set -euo pipefail

HQ_ROOT="/home/ubuntu/forseti.life"
LOG_DIR="${HQ_ROOT}/inbox/responses"

# Cron entries: each line is "TAG|SCHEDULE|COMMAND"
# TAG is used as the idempotency key (matched against "# copilot-sessions-hq:<tag>" comment).
ENTRIES=(
  "orchestrator-reboot|@reboot|ORCHESTRATOR_AGENT_CAP=6 ${HQ_ROOT}/scripts/orchestrator-loop.sh start 60 >> ${LOG_DIR}/orchestrator-cron.log 2>&1"
  "orchestrator-watchdog|*/5 * * * *|ORCHESTRATOR_AGENT_CAP=6 ${HQ_ROOT}/scripts/orchestrator-watchdog.sh >> ${LOG_DIR}/orchestrator-cron.log 2>&1"
  "hq-automation|* * * * *|${HQ_ROOT}/scripts/hq-automation-watchdog.sh >> ${LOG_DIR}/hq-automation-cron.log 2>&1"
  "ceo-ops|*/10 * * * *|python3 ${HQ_ROOT}/scripts/ceo-ops-scheduler.py >> ${LOG_DIR}/ceo-ops-cron.log 2>&1"
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

for entry in "${ENTRIES[@]}"; do
  IFS='|' read -r tag schedule command <<< "$entry"
  marker="# copilot-sessions-hq:${tag}"
  current_crontab="$(printf '%s\n' "$current_crontab" | grep -vF "$marker" || true)"

  new_line="${schedule} ${command} ${marker}"
  current_crontab="${current_crontab}
${new_line}"
  echo "ensured: ${tag}"
  added=$((added + 1))
done

# Write back the refreshed managed entry set.
printf '%s\n' "$current_crontab" | crontab -
echo "install-crons: refreshed ${added} managed entries."
echo "Verify: crontab -l | grep copilot-sessions-hq"
