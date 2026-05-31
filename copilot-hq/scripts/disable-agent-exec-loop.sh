#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

MARKER="# copilot-sessions-hq:agent-exec"

legacy_agent_exec_pids() {
  ps -eo pid=,args= 2>/dev/null | awk '/[s]cripts\/agent-exec-loop\.sh run/ {print $1}'
}

# Remove cron entries for the legacy agent exec loop.
current=""
if crontab -l >/dev/null 2>&1; then
  current="$(crontab -l)"
fi

filtered="$(printf '%s\n' "$current" | grep -vF "$MARKER" | grep -vF "$ROOT_DIR/scripts/agent-exec-loop.sh" | grep -vF "$ROOT_DIR/scripts/agent-exec-watchdog.sh" | grep -vF "$ROOT_DIR/scripts/agent-exec-once.sh" || true)"

printf '%s\n' "$filtered" | sed '/^$/d' | crontab -

echo "Removed legacy agent-exec cron entries (marker: $MARKER)"

# Stop any currently running legacy loop.
PIDFILE="$ROOT_DIR/.agent-exec-loop.pid"
if [ -f "$PIDFILE" ]; then
  pid="$(cat "$PIDFILE" 2>/dev/null || true)"
  if [[ "$pid" =~ ^[0-9]+$ ]] && ps -p "$pid" >/dev/null 2>&1; then
    echo "Stopping legacy agent-exec-loop pid $pid"
    kill "$pid" >/dev/null 2>&1 || true
    sleep 0.2
    if ps -p "$pid" >/dev/null 2>&1; then
      kill -9 "$pid" >/dev/null 2>&1 || true
    fi
  fi
fi

while IFS= read -r pid; do
  [[ "$pid" =~ ^[0-9]+$ ]] || continue
  if ps -p "$pid" >/dev/null 2>&1; then
    echo "Stopping stray legacy agent-exec-loop pid $pid"
    kill "$pid" >/dev/null 2>&1 || true
    sleep 0.2
    if ps -p "$pid" >/dev/null 2>&1; then
      kill -9 "$pid" >/dev/null 2>&1 || true
    fi
  fi
done < <(legacy_agent_exec_pids)
