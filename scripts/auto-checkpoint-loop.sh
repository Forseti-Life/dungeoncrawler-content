#!/usr/bin/env bash
set -euo pipefail

echo_disabled() {
  echo "disabled"
}

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

PIDFILE=".auto-checkpoint-loop.pid"
LOGDIR="inbox/responses"
LATEST="$LOGDIR/auto-checkpoint-latest.log"
mkdir -p "$LOGDIR"

cmd="${1:-start}"
interval="${2:-${HQ_AUTO_CHECKPOINT_INTERVAL_SECONDS:-600}}"   # 10 minutes

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
    pid="$(read_pid)"
    if [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1; then
      kill "$pid" >/dev/null 2>&1 || true
      sleep 0.2
      ps -p "$pid" >/dev/null 2>&1 && kill -9 "$pid" >/dev/null 2>&1 || true
    fi
    rm -f "$PIDFILE"
    echo_disabled
    exit 0
    ;;

  status)
    echo_disabled
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
    rm -f "$PIDFILE"
    echo_disabled
    ;;

  run)
    rm -f "$PIDFILE"
    ts="$(date -Iseconds)"
    daylog="$LOGDIR/auto-checkpoint-$(date +%Y%m%d).log"
    echo "[$ts] DISABLED: auto-checkpoint loop is permanently turned off" | tee -a "$daylog" > "$LATEST"
    echo_disabled
    exit 0
    ;;

  *)
    echo "Usage: $0 start|stop|status [interval_seconds]" >&2
    exit 1
    ;;
esac
