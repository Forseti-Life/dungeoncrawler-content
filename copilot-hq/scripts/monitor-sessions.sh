#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/agents.sh
source "./scripts/lib/agents.sh"

DATE_YYYYMMDD="${1:-$(date +%Y%m%d)}"

echo "== Hub files health =="
if [ -d "knowledgebase/reviews/cycle/${DATE_YYYYMMDD}" ]; then
  echo "Cycle review present: knowledgebase/reviews/cycle/${DATE_YYYYMMDD}"
  echo
  echo "Feedback stubs (count):"
  ls -1 "knowledgebase/reviews/cycle/${DATE_YYYYMMDD}/feedback" | wc -l | awk '{print $1}'
  echo
  echo "Agents missing inbox pointer:"
  missing=0
  while IFS= read -r agent; do
    if [ ! -f "sessions/${agent}/inbox/${DATE_YYYYMMDD}-cycle-review/README.md" ]; then
      echo "- $agent"
      missing=$((missing+1))
    fi
  done < <(configured_agent_ids)
  if [ "$missing" -eq 0 ]; then
    echo "(none)"
  fi
else
  echo "No cycle review for ${DATE_YYYYMMDD} (reviews are per release cycle, not daily)"
fi

echo
echo "Scoreboards present:"
ls -1 knowledgebase/scoreboards/*.md 2>/dev/null | wc -l | awk '{print $1}'
