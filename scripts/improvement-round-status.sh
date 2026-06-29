#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DATE_YYYYMMDD="${1:-$(date +%Y%m%d)}"
TOPIC="${2:-improvement-round}"

configured_agent_ids() {
  python3 - <<'PY'
import yaml
from pathlib import Path
p = Path('org-chart/agents/agents.yaml')
if not p.exists():
    raise SystemExit(0)
data = yaml.safe_load(p.read_text(encoding='utf-8', errors='ignore'))
for agent in data.get('agents', []):
    if not agent.get('paused', False):
        print(agent['id'])
PY
}

wanted="${DATE_YYYYMMDD}-${TOPIC}"

created=0
outboxed=0
blocked=0

while IFS= read -r agent; do

  if compgen -G "sessions/${agent}/inbox/${wanted}" >/dev/null \
    || compgen -G "sessions/${agent}/inbox/${wanted}-*" >/dev/null \
    || compgen -G "sessions/${agent}/artifacts/${wanted}" >/dev/null \
    || compgen -G "sessions/${agent}/artifacts/${wanted}-*" >/dev/null; then
    created=$((created+1))
  fi

  outbox_matches=()
  while IFS= read -r match; do
    outbox_matches+=("$match")
  done < <(
    compgen -G "sessions/${agent}/outbox/${wanted}.md" || true
    compgen -G "sessions/${agent}/outbox/${wanted}-*.md" || true
  )

  if [ "${#outbox_matches[@]}" -gt 0 ]; then
    outboxed=$((outboxed+1))
    has_blocked=false
    for f in "${outbox_matches[@]}"; do
      status_line=$(grep -iE '^\- Status:' "$f" | tail -n 1 || true)
      status=$(echo "$status_line" | sed 's/^- Status: *//I' | tr '[:upper:]' '[:lower:]' | tr -d '\r')
      if [ "$status" = "blocked" ] || [ "$status" = "needs-info" ]; then
        has_blocked=true
        break
      fi
    done
    if [ "$has_blocked" = true ]; then
      blocked=$((blocked+1))
    fi
  fi

done < <(configured_agent_ids)

echo "Improvement round: ${wanted}"
echo "- Agents with item created: $created"
echo "- Agents with outbox update: $outboxed"
echo "- Blocked/needs-info outboxes: $blocked"
