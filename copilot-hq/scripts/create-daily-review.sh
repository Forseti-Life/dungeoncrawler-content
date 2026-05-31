#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/agents.sh
source "./scripts/lib/agents.sh"

DATE_YYYYMMDD="${1:-$(date +%Y%m%d)}"
DATE_ISO="$(date -d "${DATE_YYYYMMDD}" +%Y-%m-%d 2>/dev/null || date +%Y-%m-%d)"

REVIEW_DIR="knowledgebase/reviews/daily/${DATE_YYYYMMDD}"
mkdir -p "$REVIEW_DIR/feedback"

# CEO summary
cp -n templates/daily-review.md "$REVIEW_DIR/daily-review.md"
sed -i "s/YYYY-MM-DD/${DATE_ISO}/g" "$REVIEW_DIR/daily-review.md"

mapfile -t AGENTS < <(configured_agent_ids)

for a in "${AGENTS[@]}"; do
  f="$REVIEW_DIR/feedback/${a}.md"
  if [ ! -f "$f" ]; then
    cp templates/daily-review-feedback.md "$f"
    sed -i "s/YYYY-MM-DD/${DATE_ISO}/g" "$f"
    sed -i "s/^\- Agent:.*/- Agent: ${a}/g" "$f"
  fi
  # Drop a pointer into the agent inbox for visibility.
  mkdir -p "sessions/${a}/inbox/${DATE_YYYYMMDD}-daily-review"
  cat > "sessions/${a}/inbox/${DATE_YYYYMMDD}-daily-review/README.md" <<EOM
# Daily Review Request — ${DATE_ISO}

Please fill out:
- ../../../../${REVIEW_DIR}/feedback/${a}.md

Then move any resulting items into:
- knowledgebase/lessons/ (if a lesson)
- knowledgebase/proposals/ (if instructions/process proposal)
EOM

done

echo "Created: ${REVIEW_DIR}"
