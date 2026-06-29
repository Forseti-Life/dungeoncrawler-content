#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=scripts/lib/agents.sh
source "./scripts/lib/agents.sh"

has_matrix_mapping() {
  local file="$1"
  grep -qiE '^[[:space:]]*(\-|\*|)[[:space:]]*(Matrix issue type|Issue type \(matrix\)):' "$file" 2>/dev/null
}

mode="${1:-list}"
count=0

while IFS= read -r agent; do
  if is_paused "$agent"; then
    continue
  fi

  latest="$(ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null | head -n 1 || true)"
  [ -n "$latest" ] || continue

  status_line="$(grep -im1 '^\- Status:' "$latest" 2>/dev/null || true)"
  status="$(echo "$status_line" | sed 's/^- Status: *//I' | tr '[:upper:]' '[:lower:]' | tr -d '\r')"

  if [ "$status" != "blocked" ] && [ "$status" != "needs-info" ]; then
    continue
  fi

  if has_matrix_mapping "$latest"; then
    continue
  fi

  count=$((count+1))
  if [ "$mode" = "count" ]; then
    continue
  fi

  echo "- ${agent}: $(basename "$latest") [status=${status}] missing Matrix issue type mapping"
done < <(configured_agent_ids)

if [ "$mode" = "count" ]; then
  echo "$count"
fi
