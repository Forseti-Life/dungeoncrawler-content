#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

WORK_ITEM="${1:-forseti-dev-master-worker-sync}"
TOPIC_BASE="${2:-jobhunter-local-node-smoke}"
TOPIC="${TOPIC_BASE}-$(date +%H%M%S)"
TEXT="${3:-Local node smoke test: validate master-node dispatch to worker-node claim and inbox conversion.}"

echo "[1/6] Verifying local node loops"
master_status="$(./scripts/orchestrator-loop.sh status || true)"
worker_status="$(./scripts/dev-sync-loop.sh status || true)"
echo "master: ${master_status}"
echo "worker: ${worker_status}"

if ! printf '%s' "$master_status" | grep -qi 'running'; then
  echo "[2/6] Starting master node loop"
  ./scripts/orchestrator-loop.sh start 60 >/dev/null
fi

if ! printf '%s' "$worker_status" | grep -qi 'running'; then
  echo "[2/6] Starting worker node loop"
  ./scripts/dev-sync-loop.sh start 300 >/dev/null
fi

echo "[3/6] Creating master command envelope"
DEV_DISPATCH_EXECUTE=dispatch-only \
  ./scripts/dev-dispatch-task.sh \
  dev-forseti \
  "$WORK_ITEM" \
  "$TOPIC" \
  "$TEXT" >/dev/null

cmd_file="$(ls -t inbox/commands/*"$TOPIC"*.md | head -n 1)"
if [ -z "$cmd_file" ] || [ ! -f "$cmd_file" ]; then
  echo "FAIL: command file not created"
  exit 1
fi
cmd_name="$(basename "$cmd_file")"

echo "[4/6] Confirming HQ dispatcher skips worker-targeted command"
./scripts/ceo-dispatch-next.sh >/dev/null 2>&1 || true
if [ ! -f "$cmd_file" ]; then
  echo "FAIL: HQ dispatcher consumed worker-targeted command: $cmd_name"
  exit 1
fi

echo "[5/6] Worker claims command"
DEV_SYNC_AUTO_PULL=0 DEV_SYNC_EXECUTE=0 DEV_SYNC_MAX_COMMANDS=1 ./scripts/dev-sync-once.sh >/dev/null

processed_file="inbox/processed/$cmd_name"
if [ ! -f "$processed_file" ]; then
  echo "FAIL: command was not moved to processed: $cmd_name"
  exit 1
fi

inbox_dir="$(ls -dt sessions/dev-forseti/inbox/*"$TOPIC"* 2>/dev/null | head -n 1 || true)"
if [ -z "$inbox_dir" ] || [ ! -d "$inbox_dir" ]; then
  echo "FAIL: worker inbox item not created for topic: $TOPIC"
  exit 1
fi

echo "[6/6] Validating command metadata"
if ! grep -qi '^\- target: dev-laptop' "$inbox_dir/command.md"; then
  echo "FAIL: command metadata missing target in $inbox_dir/command.md"
  exit 1
fi
if ! grep -qi '^\- target_agent: dev-forseti' "$inbox_dir/command.md"; then
  echo "FAIL: command metadata missing target_agent in $inbox_dir/command.md"
  exit 1
fi

echo "PASS"
echo "- command: $cmd_name"
echo "- processed: $processed_file"
echo "- inbox: $inbox_dir"
