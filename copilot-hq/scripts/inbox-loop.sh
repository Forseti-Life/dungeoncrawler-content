#!/usr/bin/env bash
set -euo pipefail

# Continuous inbox loop for explicit-PM inbox commands.
# - Processes commands in order (by filename, as glob expansion returns sorted in most shells,
#   but we explicitly sort).
# - Logs activity to inbox/responses/inbox-loop-latest.log and date-stamped log.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PIDFILE=".inbox-loop.pid"
LOGDIR="inbox/responses"
mkdir -p inbox/commands inbox/processed "$LOGDIR"

cmd="${1:-start}"
interval="${2:-2}"

read_pid() {
  if [ ! -f "$PIDFILE" ]; then
    echo ""
    return
  fi
  pid="$(cat "$PIDFILE" 2>/dev/null || true)"
  if [[ "$pid" =~ ^[0-9]+$ ]]; then
    echo "$pid"
  else
    echo ""
  fi
}

is_running() {
  pid="$(read_pid)"
  [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1
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
      daylog="$LOGDIR/inbox-loop-${ts_day}.log"
      latest="$LOGDIR/inbox-loop-latest.log"

      if out=$(./scripts/inbox-dispatch-next.sh 2>&1); then
        echo "[$ts_iso] $out" | tee -a "$daylog" >> "$latest"
      else
        rc=$?
        if [ "$rc" -ne 2 ]; then
          echo "[$ts_iso] ERROR($rc): $out" | tee -a "$daylog" >> "$latest"
        fi
      fi

      # Small pause to avoid tight loops if a command is malformed.
      sleep "$interval"
    done
    ;;

  *)
    echo "Usage: $0 start|stop|status [interval_seconds]" >&2
    exit 1
    ;;
esac
