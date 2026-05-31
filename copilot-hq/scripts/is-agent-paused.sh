#!/usr/bin/env bash
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"

AGENT_ID="${1:-}"
if [ -z "$AGENT_ID" ]; then
  echo "Usage: $0 <agent-id>" >&2
  exit 2
fi

python3 - "$AGENT_ID" <<'PY'
import sys, re
from pathlib import Path
agent_id = sys.argv[1]
text = Path('org-chart/agents/agents.yaml').read_text(encoding='utf-8', errors='ignore').splitlines()
in_item = False
paused = False
for line in text:
    m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', line)
    if m:
        in_item = (m.group(1).strip() == agent_id)
        continue
    if in_item:
        m = re.match(r'^\s*paused:\s*(.+)\s*$', line)
        if m:
            paused = m.group(1).strip().lower() in ('true','yes','1','on')
            break
print("true" if paused else "false")
PY

