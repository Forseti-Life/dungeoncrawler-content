#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

PIDFILE=".orchestrator-loop.pid"
LOCKFILE="tmp/.orchestrator-loop.control.lock"
LOGDIR="inbox/responses"
LATEST="$LOGDIR/orchestrator-latest.log"
mkdir -p "$LOGDIR"
mkdir -p "$(dirname "$LOCKFILE")"

cmd="${1:-start}"
interval="${2:-60}"

read_pid() {
  [ -f "$PIDFILE" ] || { echo ""; return; }
  pid="$(cat "$PIDFILE" 2>/dev/null || true)"
  [[ "$pid" =~ ^[0-9]+$ ]] && echo "$pid" || echo ""
}

loop_pids() {
  ps -eo pid=,args= 2>/dev/null | awk '/[s]cripts\/orchestrator-loop\.sh run/ {print $1}'
}

stop_pid() {
  local pid="$1"
  [[ "$pid" =~ ^[0-9]+$ ]] || return 0
  if ps -p "$pid" >/dev/null 2>&1; then
    kill "$pid" >/dev/null 2>&1 || true
    sleep 0.2
    if ps -p "$pid" >/dev/null 2>&1; then
      kill -9 "$pid" >/dev/null 2>&1 || true
    fi
  fi
}

is_running() {
  pid="$(read_pid)"
  if [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1; then
    return 0
  fi
  [ -n "$(loop_pids)" ]
}

run_orchestrator_once() {
  if [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" != "true" ]; then
    echo "org disabled; skipping orchestrator run"
    return 0
  fi
  local python_bin="python3"
  [ -x "orchestrator/.venv/bin/python" ] && python_bin="orchestrator/.venv/bin/python"

  "$python_bin" orchestrator/run.py --once \
    --agent-cap "${ORCHESTRATOR_AGENT_CAP:-6}" \
    ${ORCHESTRATOR_NO_PUBLISH:+--no-publish} \
    --kpi-interval "${ORCHESTRATOR_KPI_INTERVAL:-300}" \
    --log-file "$LATEST"
}

case "$cmd" in
  start)
    exec 9>"$LOCKFILE"
    flock -n 9 || { echo "Start already in progress"; exit 0; }
    if is_running; then
      echo "Already running (pid $(read_pid))"
      exit 0
    fi
    setsid "$0" run "$interval" </dev/null >/dev/null 2>&1 &
    pid=$!
    echo "$pid" > "$PIDFILE"
    echo "Started (pid $pid)"
    ;;

  status)
    tracked_pid="$(read_pid)"
    extra_pids="$(loop_pids | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/^ //; s/ $//')"
    if [ -n "$tracked_pid" ] && ps -p "$tracked_pid" >/dev/null 2>&1; then
      if [ -n "$extra_pids" ] && [ "$extra_pids" != "$tracked_pid" ]; then
        echo "running (pid $tracked_pid; visible pid(s): $extra_pids)"
      else
        echo "running (pid $tracked_pid)"
      fi
    elif [ -n "$extra_pids" ]; then
      echo "running (untracked pid(s): $extra_pids)"
    else
      echo "not running"
    fi
    ;;

  verify)
    if is_running; then
      tracked_pid="$(read_pid)"
      if [ -n "$tracked_pid" ] && ps -p "$tracked_pid" >/dev/null 2>&1; then
        echo "ok (running pid $tracked_pid)"
      else
        echo "ok (running untracked pid(s): $(loop_pids | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/^ //; s/ $//'))"
      fi
      exit 0
    fi
    echo "ERROR: orchestrator loop not running" >&2
    exit 1
    ;;

  stop)
    exec 9>"$LOCKFILE"
    flock -n 9 || { echo "Stop already in progress"; exit 0; }
    pid="$(read_pid)"
    stopped_any=0
    if [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1; then
      stop_pid "$pid"
      stopped_any=1
    fi
    while IFS= read -r loop_pid; do
      [[ "$loop_pid" =~ ^[0-9]+$ ]] || continue
      [ "$loop_pid" = "$pid" ] && continue
      stop_pid "$loop_pid"
      stopped_any=1
    done < <(loop_pids)
    rm -f "$PIDFILE" >/dev/null 2>&1 || true
    if [ "$stopped_any" -eq 1 ]; then
      echo "Stopped orchestrator loop(s)"
      exit 0
    fi
    echo "Not running"
    ;;

  run)
    echo $$ > "$PIDFILE"
    while true; do
      tick_start="$(date +%s)"
      ts="$(date -Iseconds)"
      daylog="$LOGDIR/orchestrator-$(date +%Y%m%d).log"
      out="$(run_orchestrator_once 2>&1 || true)"
      out_line="$(printf '%s' "$out" | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"
      echo "[$ts] $out_line" | tee -a "$daylog" > "$LATEST"
      # Sleep only the remaining portion of the interval so that
      # interval is the period between tick *starts*, not tick end + sleep.
      tick_end="$(date +%s)"
      elapsed=$(( tick_end - tick_start ))
      remaining=$(( interval - elapsed ))
      [ "$remaining" -lt 1 ] && remaining=1
      sleep "$remaining"
    done
    ;;

  *)
    echo "Usage: $0 start|stop|status|verify|run [interval_seconds]" >&2
    exit 1
    ;;
esac
