#!/usr/bin/env bash
set -euo pipefail

# Runs ceo-ops-once on an interval (default 5 minutes) and logs.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

PIDFILE=".ceo-ops-loop.pid"
LOGDIR="inbox/responses"
LATEST="$LOGDIR/ceo-ops-latest.log"
mkdir -p "$LOGDIR"

cmd="${1:-start}"
interval="${2:-300}"

read_pid() {
  [ -f "$PIDFILE" ] || { echo ""; return; }
  pid="$(cat "$PIDFILE" 2>/dev/null || true)"
  [[ "$pid" =~ ^[0-9]+$ ]] && echo "$pid" || echo ""
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
      ts="$(date -Iseconds)"
      daylog="$LOGDIR/ceo-ops-$(date +%Y%m%d).log"
      out=$(./scripts/ceo-ops-once.sh 2>&1 || true)
      echo "$out" | tee -a "$daylog" > "$LATEST"
      echo "[$ts] cycle complete" >> "$daylog"
      sleep "$interval"
    done
    ;;
  *)
    echo "Usage: $0 start|stop|status [interval_seconds]" >&2
    exit 1
    ;;
esac
