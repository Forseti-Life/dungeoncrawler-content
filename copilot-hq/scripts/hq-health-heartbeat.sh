#!/usr/bin/env bash
# HQ health heartbeat: checks all orchestration loops and restarts any that are down.
#
# Usage: bash scripts/hq-health-heartbeat.sh
# Exit 0: all loops healthy (or successfully restarted)
# Exit 1: one or more loops could not be restarted (manual intervention required)
#
# Intended cron: */2 * * * * .../scripts/hq-health-heartbeat.sh >> /tmp/hq-health-heartbeat.log 2>&1

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

HEARTBEAT_LOG="/tmp/hq-health-heartbeat.log"
ALERT_LOG="/tmp/hq-health-alert.log"
FAILURE_COUNT_FILE="/tmp/orchestrator-restart-failures.count"
ts="$(date -Iseconds)"

log() { printf '[%s] %s\n' "$ts" "$*" | tee -a "$HEARTBEAT_LOG" >/dev/null 2>&1 || true; }
alert() { printf '[%s] WARN %s\n' "$ts" "$*" | tee -a "$ALERT_LOG" | tee -a "$HEARTBEAT_LOG" >/dev/null 2>&1 || true; }

# Load board notification config
BOARD_CONF="${ROOT_DIR}/org-chart/board.conf"
if [ -f "$BOARD_CONF" ]; then
  # shellcheck source=../org-chart/board.conf
  source "$BOARD_CONF"
fi
BOARD_EMAIL="${BOARD_EMAIL:-keith.aumiller@stlouisintegration.com}"
HQ_FROM_EMAIL="${HQ_FROM_EMAIL:-hq-noreply@forseti.life}"
HQ_SITE_NAME="${HQ_SITE_NAME:-forseti.life HQ}"

send_critical_email() {
  local subject="$1"
  local body="$2"
  
  printf "Subject: %s\nTo: %s\nFrom: %s\nContent-Type: text/plain\n\n%s\n" \
    "$subject" "$BOARD_EMAIL" "$HQ_FROM_EMAIL" "$body" \
    | /usr/sbin/sendmail -t \
    && alert "CRITICAL EMAIL SENT to $BOARD_EMAIL: $subject" \
    || alert "CRITICAL EMAIL FAILED to send to $BOARD_EMAIL"
}

increment_failure_count() {
  local count=$(cat "$FAILURE_COUNT_FILE" 2>/dev/null || echo 0)
  count=$((count + 1))
  echo "$count" > "$FAILURE_COUNT_FILE"
  echo "$count"
}

reset_failure_count() {
  rm -f "$FAILURE_COUNT_FILE"
}

legacy_agent_exec_pids() {
  ps -eo pid=,args= 2>/dev/null | awk '/[s]cripts\/agent-exec-loop\.sh run/ {print $1}'
}

check_and_restart_loop() {
  local name="$1"     # human label
  local script="$2"   # path to loop script (relative to ROOT_DIR)
  local start_args="${3:-start}"

  local result=0
  if "$ROOT_DIR/$script" verify >/dev/null 2>&1; then
    log "ok: ${name}"
    reset_failure_count
    return 0
  fi

  alert "${name} is DOWN — attempting restart"
  "$ROOT_DIR/$script" "$start_args" >/dev/null 2>&1 || true
  sleep 1

  if "$ROOT_DIR/$script" verify >/dev/null 2>&1; then
    log "restarted: ${name}"
    reset_failure_count
    return 0
  fi

  alert "${name} restart FAILED — manual intervention required"
  local failure_count
  failure_count=$(increment_failure_count)
  
  if [ "$failure_count" -ge 3 ]; then
    local body="CRITICAL FAILURE: HUMAN NEEDED

Orchestrator restart has failed 3 consecutive times.

Timestamp: $ts
Service: $name
Script: $script
Failure count: $failure_count

The orchestrator has stopped and cannot be automatically restarted.
Manual intervention is required immediately.

Check logs:
  - Heartbeat: $HEARTBEAT_LOG
  - Alerts: $ALERT_LOG

To investigate:
  cd $ROOT_DIR
  ./scripts/orchestrator-loop.sh verify
  tail -50 /var/log/orchestrator.log (if available)
  
To manually restart once issue is fixed:
  ./scripts/orchestrator-loop.sh start 60
"
    send_critical_email "CRITICAL FAILURE: ORCHESTRATOR DOWN (attempt $failure_count)" "$body"
  fi
  
  return 1
}

check_publisher() {
  local pid_file="${ROOT_DIR}/.publish-forseti-agent-tracker-loop.pid"
  if [ ! -f "$pid_file" ]; then
    log "ok: publisher (managed by orchestrator tick)"
    return 0
  fi
  local pid
  pid="$(cat "$pid_file" 2>/dev/null || echo '')"
  if [[ "$pid" =~ ^[0-9]+$ ]] && ps -p "$pid" >/dev/null 2>&1; then
    alert "publisher loop still running (pid=${pid}) — stopping redundant loop"
    "$ROOT_DIR/scripts/publish-forseti-agent-tracker-loop.sh" stop >/dev/null 2>&1 || true
    sleep 1
    if ps -p "$pid" >/dev/null 2>&1; then
      kill "$pid" >/dev/null 2>&1 || true
      sleep 0.2
      if ps -p "$pid" >/dev/null 2>&1; then
        kill -9 "$pid" >/dev/null 2>&1 || true
      fi
    fi
    rm -f "$pid_file"
    log "ok: publisher (managed by orchestrator tick)"
    return 0
  fi
  rm -f "$pid_file"
  log "ok: publisher (managed by orchestrator tick)"
  return 0
}

stop_legacy_agent_exec_loop() {
  local found=0
  if "$ROOT_DIR/scripts/agent-exec-loop.sh" verify >/dev/null 2>&1; then
    found=1
  elif [ -n "$(legacy_agent_exec_pids)" ]; then
    found=1
  fi

  if [ "$found" -ne 1 ]; then
    return 0
  fi

  alert "legacy agent-exec-loop is running — stopping it to avoid duplicate agent execution"
  "$ROOT_DIR/scripts/agent-exec-loop.sh" stop >/dev/null 2>&1 || true
  while IFS= read -r pid; do
    [[ "$pid" =~ ^[0-9]+$ ]] || continue
    kill "$pid" >/dev/null 2>&1 || true
    sleep 0.2
    if ps -p "$pid" >/dev/null 2>&1; then
      kill -9 "$pid" >/dev/null 2>&1 || true
    fi
  done < <(legacy_agent_exec_pids)
  sleep 1

  if "$ROOT_DIR/scripts/agent-exec-loop.sh" verify >/dev/null 2>&1 || [ -n "$(legacy_agent_exec_pids)" ]; then
    alert "legacy agent-exec-loop stop FAILED — manual intervention required"
    return 1
  fi

  log "stopped: legacy agent-exec-loop"
  return 0
}

any_failed=0

check_and_restart_loop "orchestrator-loop" "scripts/orchestrator-loop.sh" "start 60" || any_failed=1
stop_legacy_agent_exec_loop || any_failed=1
check_publisher || true

if [ "$any_failed" -eq 0 ]; then
  log "heartbeat: all loops healthy"
  exit 0
else
  alert "heartbeat: one or more loops could not be restarted — see ${ALERT_LOG}"
  exit 1
fi
