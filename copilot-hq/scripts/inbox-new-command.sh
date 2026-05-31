#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./scripts/inbox-new-command.sh <pm-agent-id> <work-item-id> <short-topic> "<command text>"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PM_ID="${1:?pm-agent-id required}"
WORK_ITEM_ID="${2:?work-item-id required}"
TOPIC="${3:?short-topic required}"
TEXT="${4:-}"

TS="$(date +%Y%m%d-%H%M%S)"
FILE="inbox/commands/${TS}-${TOPIC}.md"

cat > "$FILE" <<EOF2
# Command

- created_at: $(date -Iseconds)
- pm: ${PM_ID}
- work_item: ${WORK_ITEM_ID}
- topic: ${TOPIC}

## Command text
${TEXT}
EOF2

echo "Queued: $FILE"
