#!/usr/bin/env bash
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"

now=$(date +%s)

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

# SLA thresholds (seconds)
SLA_OUTBOX=${SLA_OUTBOX:-900}      # 15m: inbox item should get an outbox status
SLA_ESCALATE=${SLA_ESCALATE:-300}  # 5m: blocked/needs-info should produce supervisor escalation

supervisor_for() {
  ./scripts/supervisor-for.sh "$1"
}

oldest_inbox_item() {
  local agent="$1" dir="sessions/${agent}/inbox"
  [ -d "$dir" ] || { echo ""; return; }
  find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | while IFS= read -r d; do printf '%s %s\n' "$(stat -c '%Y' "$d" 2>/dev/null || echo 0)" "$(basename "$d")"; done | sort -n | head -n 1 | awk '{print $2}'
}

inbox_item_epoch() {
  local agent="$1" item="$2" dir="sessions/${agent}/inbox/${item}"
  [ -d "$dir" ] || { echo 0; return; }
  stat -c %Y "$dir" 2>/dev/null || echo 0
}

latest_outbox_file() {
  local agent="$1"
  ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null | head -n 1 || true
}

outbox_for_item_exists() {
  local agent="$1" item="$2"
  [ -f "sessions/${agent}/outbox/${item}.md" ]
}

outbox_status() {
  local f="$1"
  grep -iE '^\- Status:' "$f" 2>/dev/null | tail -n 1 | sed 's/^- Status: *//I' | tr '[:upper:]' '[:lower:]' | tr -d '\r' | tr ' _' '-' | sed 's/[^a-z-].*$//'
}

needs_escalation_exists() {
  local supervisor="$1" agent="$2" item="$3"
  # Escalations are named with a slug, so verify via README content.
  local d
  for d in sessions/"${supervisor}"/inbox/*-needs-"${agent}"-*; do
    [ -f "$d/README.md" ] || continue
    grep -qF -- "- Agent: ${agent}" "$d/README.md" || continue
    if grep -qF -- "- Item: ${item}" "$d/README.md"; then
      return 0
    fi
  done
  for d in sessions/"${supervisor}"/inbox/*; do
    [ -f "$d/README.md" ] || continue
    if grep -qF -- "- Escalated agent: ${agent}" "$d/README.md"; then
      if grep -qF -- "- Escalated item: ${item}" "$d/README.md"; then
        return 0
      fi
    fi
    # Backward compatibility for older CEO remediation items that only embedded
    # the blocked agent/item in prose.
    if grep -qF -- "Agent \`${agent}\` has latest outbox \`${item}.md\`" "$d/README.md"; then
      return 0
    fi
  done
  return 1
}

echo "SLA report @ $(date -Iseconds)"

breach=0

while IFS= read -r agent; do

  item="$(oldest_inbox_item "$agent")"
  if [ -n "$item" ] && ! outbox_for_item_exists "$agent" "$item"; then
    t=$(inbox_item_epoch "$agent" "$item")
    age=$((now - t))
    if [ "$age" -gt "$SLA_OUTBOX" ]; then
      echo "BREACH outbox-lag: ${agent} inbox=${item} age=${age}s"
      breach=1
    fi
  fi

  f="$(latest_outbox_file "$agent")"
  if [ -n "$f" ]; then
    st="$(outbox_status "$f")"
    if [ "$st" = "blocked" ] || [ "$st" = "needs-info" ]; then
      sup="$(supervisor_for "$agent")"
      base="$(basename "$f" .md)"
      if ! needs_escalation_exists "$sup" "$agent" "$base"; then
        echo "BREACH missing-escalation: ${agent} status=${st} outbox=${base}.md supervisor=${sup}"
        breach=1
      fi
    fi
  fi

done < <(configured_agent_ids)

if [ "$breach" -eq 0 ]; then
  echo "OK: no SLA breaches"
fi
