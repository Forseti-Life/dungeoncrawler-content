#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

configured_agent_ids() {
  python3 - <<'PY'
import re
from pathlib import Path
p = Path('org-chart/agents/agents.yaml')
if not p.exists():
    raise SystemExit(0)
for ln in p.read_text(encoding='utf-8', errors='ignore').splitlines():
    m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
    if m:
        print(m.group(1))
PY
}

ts="$(date -Iseconds)"
any=0

# Pull replies from Drupal UI into HQ inboxes before executing agents.
./scripts/consume-forseti-replies.sh >/dev/null 2>&1 || true

# NOTE: Idle work generation is disabled (Board directive 2026-02-22).

while IFS= read -r agent; do

  if [ "$(./scripts/is-agent-paused.sh "$agent")" = "true" ]; then
    continue
  fi

  if out=$(./scripts/agent-exec-next.sh "$agent" 2>&1); then
    any=1
    if [ -n "${out:-}" ]; then
      echo "[$ts] $out"
    else
      echo "[$ts] processed agent=${agent}"
    fi
  else
    rc=$?
    if [ "$rc" -ne 2 ]; then
      echo "[$ts] ERROR($rc) $agent: $out"
    fi
  fi
done < <(configured_agent_ids)

if [ "$any" -eq 0 ]; then
  echo "[$ts] idle (no inbox items)"
fi
