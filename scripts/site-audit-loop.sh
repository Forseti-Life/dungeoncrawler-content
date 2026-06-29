#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

PIDFILE=".site-audit-loop.pid"
LOGDIR="inbox/responses"
LATEST="$LOGDIR/site-audit-latest.log"
LOCKFILE="tmp/.site-audit-run.lock"
RUN_LOCKFILE="tmp/.site-audit-loop.run.lock"
mkdir -p "$LOGDIR" "$(dirname "$LOCKFILE")"

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
    exec 8>"$RUN_LOCKFILE"
    if ! flock -n 8; then
      ts="$(date -Iseconds)"
      daylog="$LOGDIR/site-audit-$(date +%Y%m%d).log"
      echo "[$ts] site-audit loop already running; exiting duplicate runner" | tee -a "$daylog" > "$LATEST"
      exit 0
    fi

    echo $$ > "$PIDFILE"
    while true; do
      if [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" != "true" ]; then
        sleep "$interval"
        continue
      fi

      ts="$(date -Iseconds)"
      daylog="$LOGDIR/site-audit-$(date +%Y%m%d).log"

      exec 9>"$LOCKFILE"
      if flock -n 9; then
        out="$(./scripts/site-audit-run.sh 2>&1 || true)"
      else
        out="site-audit already running; skip overlap"
      fi

      echo "[$ts] $out" | tee -a "$daylog" > "$LATEST"
      sleep "$interval"
    done
    ;;

  *)
    echo "Usage: $0 start|stop|status [interval_seconds]" >&2
    exit 1
    ;;
esac
