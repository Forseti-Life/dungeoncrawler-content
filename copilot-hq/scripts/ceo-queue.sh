#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./scripts/ceo-queue.sh <work-item-id> <short-topic> "<command text>" [<pm-agent-id>]
# Queues a command for CEO triage/dispatch without requiring you to know PM ids.
# Optional 4th argument pm-agent-id writes a 'pm:' field so the orchestrator
# can route directly to that PM seat without CEO GenAI triage.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

WORK_ITEM_ID="${1:?work-item-id required}"
TOPIC="${2:?short-topic required}"
TEXT="${3:-}"
PM_AGENT="${4:-}"

TS="$(date +%Y%m%d-%H%M%S)"
FILE="inbox/commands/${TS}-${TOPIC}.md"
mkdir -p inbox/commands

{
  echo "# Command"
  echo ""
  echo "- created_at: $(date -Iseconds)"
  echo "- work_item: ${WORK_ITEM_ID}"
  echo "- topic: ${TOPIC}"
  if [ -n "${PM_AGENT}" ]; then
    echo "- pm: ${PM_AGENT}"
  fi
  echo ""
  echo "## Command text"
  echo "${TEXT}"
} > "$FILE"

echo "Queued: $FILE"
