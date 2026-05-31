#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./scripts/dispatch-pm-request.sh <pm-agent-id> <work-item-id> <short-topic>
# Creates an inbox folder with required templates for PM to fill.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PM_ID="${1:?pm-agent-id required}"
WORK_ITEM_ID="${2:-}"
TOPIC="${3:?short-topic required}"
DATE_YYYYMMDD="$(date +%Y%m%d)"
DATE_ISO="$(date -I)"

DIR="sessions/${PM_ID}/inbox/${DATE_YYYYMMDD}-${TOPIC}"
mkdir -p "$DIR"

# Default ROI for dispatched PM work requests; PM may adjust.
printf '3\n' > "$DIR/roi.txt"

cp templates/00-problem-statement.md "$DIR/00-problem-statement.md"
cp templates/01-acceptance-criteria.md "$DIR/01-acceptance-criteria.md"
cp templates/06-risk-assessment.md "$DIR/06-risk-assessment.md"

cat > "$DIR/README.md" <<EOF
# PM Work Request — ${DATE_ISO}

- PM: ${PM_ID}
- Work item: ${WORK_ITEM_ID}
- Topic: ${TOPIC}

## What to do
1. Fill in the three artifacts in this folder.
2. Add any follow-up questions in this README.
3. Once complete, move the filled artifacts to sessions/${PM_ID}/artifacts/ and leave a brief update.
EOF

echo "Created: $DIR"
