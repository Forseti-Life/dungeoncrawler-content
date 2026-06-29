#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

PIDFILE=".orchestrator-loop.pid"
LOCKFILE="tmp/.orchestrator-loop.control.lock"
RUN_STATE_DIR="tmp/orchestrator-loop-runs"
LOGDIR="inbox/responses"
LATEST="$LOGDIR/orchestrator-latest.log"
mkdir -p "$LOGDIR"
mkdir -p "$(dirname "$LOCKFILE")"
mkdir -p "$RUN_STATE_DIR"

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

python_bin() {
  local bin="python3"
  [ -x "orchestrator/.venv/bin/python" ] && bin="orchestrator/.venv/bin/python"
  printf '%s\n' "$bin"
}

active_run_pids() {
  ps -eo pid=,args= 2>/dev/null | awk '/[o]rchestrator\/run\.py --once/ {print $1}'
}

active_run_count() {
  active_run_pids | sed '/^$/d' | wc -l | tr -d '[:space:]'
}

active_slot_count() {
  lsof tmp/.agent-exec-semaphore/slot-*.lock 2>/dev/null \
    | awk 'NR>1 {print $NF}' | sort -u | wc -l | tr -d '[:space:]'
}

worker_limit() {
  local py
  py="$(python_bin)"
  "$py" - <<'PY'
import os
from orchestrator.runtime_graph.engine import _exec_worker_limit

cap = max(0, int(os.environ.get("ORCHESTRATOR_AGENT_CAP", "6")))
print(_exec_worker_limit(cap))
PY
}

has_runnable_agent_work() {
  local py
  py="$(python_bin)"
  "$py" - "$ROOT_DIR" <<'PY'
from pathlib import Path
import re
import subprocess
import sys

root = Path(sys.argv[1])
active = set()
out = subprocess.run(["ps", "-eo", "args="], capture_output=True, text=True).stdout.splitlines()
for line in out:
    m = re.search(r"scripts/agent-exec-next\.sh\s+(\S+)", line)
    if m:
        active.add(m.group(1))

for inbox in (root / "sessions").glob("*/inbox"):
    agent = inbox.parent.name
    if agent in active:
        continue
    for item in inbox.iterdir():
        if item.is_dir() and item.name != "_archived":
            print("true")
            raise SystemExit(0)

print("false")
PY
}

open_slot_available() {
  local held limit
  held="$(active_slot_count)"
  limit="$(worker_limit)"
  [ "${held:-0}" -lt "${limit:-1}" ]
}

launch_orchestrator_once() {
  local reason="${1:-interval}"
  if [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" != "true" ]; then
    echo "org disabled; skipping orchestrator run"
    return 0
  fi
  local py run_tag out_file pid_file meta_file
  py="$(python_bin)"
  run_tag="$(date +%s)-$$-$RANDOM"
  out_file="$RUN_STATE_DIR/$run_tag.out"
  pid_file="$RUN_STATE_DIR/$run_tag.pid"
  meta_file="$RUN_STATE_DIR/$run_tag.meta"
  {
    printf 'reason=%s\n' "$reason"
    printf 'started_at=%s\n' "$(date -Iseconds)"
  } > "$meta_file"

  "$py" orchestrator/run.py --once \
    --agent-cap "${ORCHESTRATOR_AGENT_CAP:-6}" \
    ${ORCHESTRATOR_NO_PUBLISH:+--no-publish} \
    --kpi-interval "${ORCHESTRATOR_KPI_INTERVAL:-300}" \
    --log-file "$LATEST" >"$out_file" 2>&1 &
  printf '%s\n' "$!" > "$pid_file"
}

reap_finished_runs() {
  local ts daylog pid pid_file run_tag out_file meta_file reason out_line
  ts="$(date -Iseconds)"
  daylog="$LOGDIR/orchestrator-$(date +%Y%m%d).log"
  for pid_file in "$RUN_STATE_DIR"/*.pid; do
    [ -f "$pid_file" ] || continue
    pid="$(cat "$pid_file" 2>/dev/null || true)"
    [[ "$pid" =~ ^[0-9]+$ ]] || {
      rm -f "$pid_file"
      continue
    }
    if ps -p "$pid" >/dev/null 2>&1; then
      continue
    fi
    run_tag="$(basename "$pid_file" .pid)"
    out_file="$RUN_STATE_DIR/$run_tag.out"
    meta_file="$RUN_STATE_DIR/$run_tag.meta"
    reason="$(grep '^reason=' "$meta_file" 2>/dev/null | head -n1 | cut -d= -f2- || echo unknown)"
    out_line="$(cat "$out_file" 2>/dev/null | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/[[:space:]]+$//')"
    echo "[$ts] reason=$reason pid=$pid ${out_line:-completed}" | tee -a "$daylog" > "$LATEST"
    rm -f "$pid_file" "$out_file" "$meta_file"
  done
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
    poll="${ORCHESTRATOR_LOOP_POLL_SECONDS:-5}"
    cooldown="${ORCHESTRATOR_SLOT_REFILL_COOLDOWN_SECONDS:-5}"
    last_launch=0
    next_due="$(date +%s)"
    while true; do
      now="$(date +%s)"
      reap_finished_runs

      runs="$(active_run_count)"
      runnable="$(has_runnable_agent_work)"

      if [ "$runs" -eq 0 ]; then
        if [ "$runnable" = "true" ] && [ $(( now - last_launch )) -ge "$cooldown" ]; then
          launch_orchestrator_once "backlog-drain"
          last_launch="$now"
          next_due=$(( now + interval ))
        elif [ "$now" -ge "$next_due" ]; then
          launch_orchestrator_once "interval"
          last_launch="$now"
          next_due=$(( now + interval ))
        fi
      else
        if [ "$runnable" = "true" ] && open_slot_available && [ $(( now - last_launch )) -ge "$cooldown" ]; then
          launch_orchestrator_once "slot-refill"
          last_launch="$now"
        elif [ "$now" -ge "$next_due" ] && open_slot_available && [ $(( now - last_launch )) -ge "$cooldown" ]; then
          launch_orchestrator_once "interval-overlap"
          last_launch="$now"
          next_due=$(( now + interval ))
        fi
      fi

      sleep "$poll"
    done
    ;;

  *)
    echo "Usage: $0 start|stop|status|verify|run [interval_seconds]" >&2
    exit 1
    ;;
esac
