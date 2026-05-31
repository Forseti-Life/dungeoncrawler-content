#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

AGENT_ID="${1:-}"
if [ -z "$AGENT_ID" ]; then
  echo "Usage: $0 <agent-id>" >&2
  exit 1
fi

# Allow explicit per-agent supervisor in agents.yaml as `supervisor: <id>`.
SUPERVISOR_FROM_YAML="$(
  python3 - "$AGENT_ID" <<'PY'
import sys, re, pathlib
agent_id = sys.argv[1]
text = pathlib.Path('org-chart/agents/agents.yaml').read_text(encoding='utf-8', errors='ignore').splitlines()
in_item = False
supervisor = ''
for line in text:
    m = re.match(r'^\s*-\s+id:\s*(.+)\s*$', line)
    if m:
        in_item = (m.group(1).strip() == agent_id)
        continue
    if in_item:
        m = re.match(r'^\s*supervisor:\s*(.+)\s*$', line)
        if m:
            supervisor = m.group(1).strip()
            break
print(supervisor)
PY
)"

if [ -n "$SUPERVISOR_FROM_YAML" ]; then
  echo "$SUPERVISOR_FROM_YAML"
  exit 0
fi

# Heuristic org chart (good enough to start).
case "$AGENT_ID" in
  board)
    # board is the top of the chain — no supervisor above it.
    echo ""; exit 0 ;;
  ceo-copilot|ceo-copilot-2|ceo-copilot-3)
    echo "board"; exit 0 ;;
  pm-*)
    echo "ceo-copilot"; exit 0 ;;
  dev-forseti-agent-tracker|qa-forseti-agent-tracker)
    echo "pm-forseti-agent-tracker"; exit 0 ;;
  dev-forseti|qa-forseti)
    echo "pm-forseti"; exit 0 ;;
  dev-dungeoncrawler|qa-dungeoncrawler)
    echo "pm-dungeoncrawler"; exit 0 ;;
  dev-*)
    echo "pm-${AGENT_ID#dev-}"; exit 0 ;;
  qa-*)
    echo "pm-${AGENT_ID#qa-}"; exit 0 ;;
  agent-*)
    echo "ceo-copilot"; exit 0 ;;
  *)
    echo "ceo-copilot"; exit 0 ;;
esac
