#!/usr/bin/env bash
set -euo pipefail

# CEO operational health loop.
# Every N seconds:
# - snapshots hq-status
# - writes an alert summary if agents have pending inbox but are not executing
# - (optionally) queues a single unblock command at a cooldown interval

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

PIDFILE=".ceo-health-loop.pid"
LOGDIR="inbox/responses"
LATEST="$LOGDIR/ceo-health-latest.log"
mkdir -p "$LOGDIR"

cmd="${1:-start}"
interval="${2:-300}"   # 5 minutes

read_pid() {
  [ -f "$PIDFILE" ] || { echo ""; return; }
  pid="$(cat "$PIDFILE" 2>/dev/null || true)"
  [[ "$pid" =~ ^[0-9]+$ ]] && echo "$pid" || echo ""
}

is_running() {
  pid="$(read_pid)"
  [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1
}

cooldown_ok() {
  # Prevent spamming auto-queue alerts.
  local statefile="$LOGDIR/.ceo-health-last-queue"
  local now; now=$(date +%s)
  local last=0
  if [ -f "$statefile" ]; then
    last=$(cat "$statefile" 2>/dev/null || echo 0)
  fi
  if ! [[ "$last" =~ ^[0-9]+$ ]]; then last=0; fi
  # 1 hour cooldown
  [ $((now-last)) -ge 3600 ]
}

mark_queued_now() {
  local statefile="$LOGDIR/.ceo-health-last-queue"
  date +%s > "$statefile"
}

autoexec_cooldown_ok() {
  # Bound self-healing runs to avoid churn.
  local statefile="$LOGDIR/.ceo-health-last-autoexec"
  local now; now=$(date +%s)
  local last=0
  if [ -f "$statefile" ]; then
    last=$(cat "$statefile" 2>/dev/null || echo 0)
  fi
  if ! [[ "$last" =~ ^[0-9]+$ ]]; then last=0; fi
  # 2 minute cooldown (faster recovery for queued-but-idle seats)
  [ $((now-last)) -ge 120 ]
}

mark_autoexec_now() {
  local statefile="$LOGDIR/.ceo-health-last-autoexec"
  date +%s > "$statefile"
}

case "$cmd" in
  start)
    if is_running; then
      echo "Already running (pid $(read_pid))"
      exit 0
    fi
    setsid bash -c "'$0' run '$interval'" >/dev/null 2>&1 &
    pid=$!
    echo "$pid" > "$PIDFILE"
    echo "Started (pid $pid)"
    echo "To stop: send SIGTERM to pid $pid"
    ;;

  status)
    if is_running; then
      echo "running (pid $(read_pid))"
    else
      echo "not running"
    fi
    ;;

  stop)
    pid="$(read_pid)"
    if [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1; then
      kill "$pid" >/dev/null 2>&1 || true
      sleep 0.2
      ps -p "$pid" >/dev/null 2>&1 && kill -9 "$pid" >/dev/null 2>&1 || true
      echo "Stopped (pid $pid)"
      exit 0
    fi
    echo "Not running"
    ;;

  run)
    echo $$ > "$PIDFILE"
    while true; do
      if [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" != "true" ]; then
        sleep "$interval"
        continue
      fi
      ts_iso="$(date -Iseconds)"
      ts_day="$(date +%Y%m%d)"
      daylog="$LOGDIR/ceo-health-${ts_day}.log"

      status_out="$(./scripts/hq-status.sh 2>&1 || true)"

blocked_out="$(./scripts/hq-blockers.sh 2>&1 || true)"
blocked_count="$(./scripts/hq-blockers.sh count 2>&1 || echo 0)"
  matrix_noncompliant_out="$(./scripts/escalation-matrix-compliance.sh 2>&1 || true)"
  matrix_noncompliant_count="$(./scripts/escalation-matrix-compliance.sh count 2>&1 || echo 0)"
    kpi_monitor_out="$(python3 ./scripts/release-kpi-monitor.py --stagnation-seconds 3600 --cooldown-seconds 3600 --auto-remediate 2>&1 || true)"

      # Parse agent table lines: "agent ... Inbox Exec ...".
      idle_with_inbox=0
      idle_agents=""
      preflight_agents=""
      backlog_pm_tracker=0
      while IFS= read -r line; do
        # Skip headers.
        [[ "$line" =~ ^Agent\  ]] && continue
        [[ "$line" =~ ^-+\  ]] && continue
        # Detect agent rows by having at least 4 columns and Exec being yes/no.
        agent=$(echo "$line" | awk '{print $1}')
        inbox=$(echo "$line" | awk '{print $2}')
        exec=$(echo "$line" | awk '{print $3}')
        next_item=$(echo "$line" | awk '{print $4}')
        if [[ "$exec" != "yes" && "$exec" != "no" ]]; then
          continue
        fi
        if [[ "$inbox" =~ ^[0-9]+$ ]] && [ "$inbox" -gt 0 ] && [ "$exec" = "no" ]; then
          idle_with_inbox=$((idle_with_inbox+1))
          idle_agents+="${agent}"$'\n'
          if [[ "$next_item" == *"release-preflight-test-suite-"* ]]; then
            preflight_agents+="${agent}"$'\n'
          fi
        fi
        if [ "$agent" = "pm-forseti-agent-tracker" ] && [[ "$inbox" =~ ^[0-9]+$ ]]; then
          backlog_pm_tracker="$inbox"
        fi
      done <<< "$(echo "$status_out" | awk 'BEGIN{p=0} /^Agent/{p=1} {if(p) print}')"

      {
        echo "[$ts_iso] CEO health check"
        echo
        echo "$status_out"
        echo
        if [ "$idle_with_inbox" -gt 0 ]; then
          echo "ALERT: ${idle_with_inbox} agent(s) have pending inbox but Exec=no."
          echo "Most likely cause: we have dispatch/queue loops, but no agent executor running that consumes sessions/<agent>/inbox and produces outbox/artifacts."

if [ "$blocked_count" -gt 0 ]; then
  echo
  echo "BLOCKED items detected (latest outbox status):"
  echo "$blocked_out"
fi

        if [ "$matrix_noncompliant_count" -gt 0 ]; then
          echo
          echo "ESCALATION MATRIX COMPLIANCE: ${matrix_noncompliant_count} blocked/needs-info item(s) missing Matrix issue type mapping."
          echo "$matrix_noncompliant_out"
        fi
        else
          echo "OK: No idle agents with pending inbox detected."
        fi

        if [ "$backlog_pm_tracker" -ge 5 ]; then
          echo "NOTE: pm-forseti-agent-tracker backlog is ${backlog_pm_tracker} inbox items."
        fi
        echo
        echo "Release KPI monitor:"
        echo "$kpi_monitor_out"
        handoff_count="$(printf '%s\n' "$kpi_monitor_out" | grep -c 'HANDOFF-GAP' || true)"
        if [ "${handoff_count:-0}" -gt 0 ]; then
          echo
          echo "AUTO-HANDOFF: detected HANDOFF-GAP state(s) in release monitor (count=${handoff_count})"
        fi
        stale_inbox_count="$(printf '%s\n' "$kpi_monitor_out" | grep -c 'STALE-INBOX' || true)"
        if [ "${stale_inbox_count:-0}" -gt 0 ]; then
          echo
          echo "STALE-INBOX-ALERT: ${stale_inbox_count} high-ROI inbox item(s) unprocessed >24h:"
          printf '%s\n' "$kpi_monitor_out" | grep 'STALE-INBOX' | sed 's/^/  /'
        fi
        echo
        echo "----"
      } | tee -a "$daylog" > "$LATEST"

      # Self-heal path: attempt bounded direct execution for stuck agents.
      # Priority: release-preflight QA items first, then other stalled agents.
      if [ "$idle_with_inbox" -gt 0 ] && autoexec_cooldown_ok; then
        ran=0
        successes=0
        failures=0
        while IFS= read -r agent; do
          [ -n "$agent" ] || continue
          # Bound each health tick remediation to avoid resource spikes.
          [ "$ran" -ge 5 ] && break
          if ./scripts/agent-exec-next.sh "$agent" >/dev/null 2>&1; then
            successes=$((successes+1))
          else
            failures=$((failures+1))
          fi
          ran=$((ran+1))
        done < <(printf '%s%s' "$preflight_agents" "$idle_agents" | awk 'NF && !seen[$0]++')

        if [ "$ran" -gt 0 ]; then
          mark_autoexec_now
          {
            echo "[$ts_iso] AUTO-REMEDIATE: attempted direct execution for stalled agents (ran=${ran}, ok=${successes}, failed=${failures})"
          } | tee -a "$daylog" >> "$LATEST"
        fi
      fi

      # Optional escalation: queue an unblock request (cooldown 1h) when idle agents exist, or when any agents report blocked.

      if ( [ "$idle_with_inbox" -gt 0 ] || [ "$blocked_count" -gt 0 ] ) && cooldown_ok; then
        ./scripts/ceo-queue.sh forseti-copilot-agent-tracker unblock-execution \
          "Investigate why agent inbox items are not being executed (Exec=no). Primary executor is orchestrator-loop managed by hq-automation converge. Check: ./scripts/hq-automation.sh status, ./scripts/orchestrator-loop.sh status, and cron watchdog logs under inbox/responses/." \
          >/dev/null || true
        mark_queued_now
      fi

      sleep "$interval"
    done
    ;;

  *)
    echo "Usage: $0 start|stop|status [interval_seconds]" >&2
    exit 1
    ;;
esac
