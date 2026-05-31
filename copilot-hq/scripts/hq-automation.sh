#!/usr/bin/env bash
set -euo pipefail

# Converges HQ background processes to match org-control enabled/disabled state.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

cmd="${1:-status}"
shift || true

require_enabled=1
force=0

while [ "$#" -gt 0 ]; do
  case "$1" in
    --force)
      force=1
      ;;
    --no-require-enabled)
      require_enabled=0
      ;;
    *)
      echo "ERROR: unknown arg: $1" >&2
      exit 2
      ;;
  esac
  shift || true
done

org_enabled() {
  [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" = "true" ]
}

start_loops() {
  if [ "$require_enabled" -eq 1 ] && [ "$force" -ne 1 ] && ! org_enabled; then
    echo "Org automation disabled; refusing to start loops (use --force to override)." >&2
    exit 1
  fi

  # Single orchestrator handles: command dispatch, agent execution (CEO included),
  # health monitoring, KPI monitoring, and publish.
  ./scripts/orchestrator-loop.sh start 60 >/dev/null 2>&1 || true

  # Continuous full-site QA loop is intentionally opt-in.
  if [ "${HQ_AUTOMATION_ENABLE_CONTINUOUS_QA:-0}" = "1" ]; then
    ./scripts/site-audit-loop.sh start 300 >/dev/null 2>&1 || true
  else
    ./scripts/site-audit-loop.sh stop >/dev/null 2>&1 || true
  fi
  ./scripts/auto-checkpoint-loop.sh start 7200 >/dev/null 2>&1 || true
}

stop_loops() {
  ./scripts/auto-checkpoint-loop.sh stop >/dev/null 2>&1 || true
  ./scripts/publish-forseti-agent-tracker-loop.sh stop >/dev/null 2>&1 || true
  ./scripts/site-audit-loop.sh stop >/dev/null 2>&1 || true
  ./scripts/agent-exec-loop.sh stop >/dev/null 2>&1 || true
  ./scripts/orchestrator-loop.sh stop >/dev/null 2>&1 || true
  # Stop legacy loops if still running from a previous install
  ./scripts/ceo-inbox-loop.sh stop >/dev/null 2>&1 || true
  ./scripts/inbox-loop.sh stop >/dev/null 2>&1 || true
  ./scripts/ceo-health-loop.sh stop >/dev/null 2>&1 || true
  ./scripts/2-ceo-opsloop.sh stop >/dev/null 2>&1 || true
}

status() {
  echo "Org enabled: $(./scripts/is-org-enabled.sh 2>/dev/null || echo false)"
  echo

  for s in \
    orchestrator-loop \
    site-audit-loop \
    auto-checkpoint-loop
  do
    if [ -x "./scripts/${s}.sh" ]; then
      printf '%-34s %s\n' "${s}:" "$(./scripts/${s}.sh status 2>/dev/null || echo 'unknown')"
    fi
  done

  echo
  echo "Disabled/legacy loops (should be stopped):"
  for s in publish-forseti-agent-tracker-loop improvement-round-loop ceo-inbox-loop inbox-loop ceo-health-loop 2-ceo-opsloop agent-exec-loop; do
    if [ -x "./scripts/${s}.sh" ]; then
      printf '  %-32s %s\n' "${s}:" "$(./scripts/${s}.sh status 2>/dev/null || echo 'unknown')"
    fi
  done
}

case "$cmd" in
  status)
    status
    ;;
  start)
    start_loops
    status
    ;;
  stop)
    stop_loops
    status
    ;;
  converge)
    if org_enabled; then
      start_loops
    else
      stop_loops
    fi
    status
    ;;
  *)
    echo "Usage: $0 status|start|stop|converge [--force]" >&2
    exit 2
    ;;
esac
