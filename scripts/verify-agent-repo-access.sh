#!/usr/bin/env bash
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"

FORS_REPO="/home/ubuntu/forseti.life"

repo_check() {
  local repo="$1"
  local name="$2"
  if [ ! -d "$repo" ]; then
    echo "ERROR: missing repo dir: $name ($repo)" >&2
    return 1
  fi
  test -r "$repo" || { echo "ERROR: no read access: $name ($repo)" >&2; return 1; }
  test -w "$repo" || { echo "ERROR: no write access: $name ($repo)" >&2; return 1; }
  git -C "$repo" rev-parse --is-inside-work-tree >/dev/null 2>&1 || { echo "ERROR: not a git worktree: $name ($repo)" >&2; return 1; }
}

repo_check "$FORS_REPO" "forseti.life"

agent_ids="$(
  python3 - <<'PY'
import re
from pathlib import Path
text = Path('org-chart/agents/agents.yaml').read_text(encoding='utf-8', errors='ignore').splitlines()
for line in text:
    m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', line)
    if m:
        print(m.group(1))
PY
)"

echo "Repo access: OK (canonical HQ root = forseti.life monorepo)"
echo "Agents:"

for a in $agent_ids; do
  session_file="$HOME/.copilot/wrappers/hq-${a}.session"
  mkdir -p "$(dirname "$session_file")"
  touch "$session_file" 2>/dev/null || { echo "  - $a: FAIL session_file"; continue; }
  test -r "$session_file" && test -w "$session_file" || { echo "  - $a: FAIL session_file_rw"; continue; }

  # Session dirs should be creatable (used by agent executor).
  mkdir -p "sessions/$a/inbox" "sessions/$a/outbox" "sessions/$a/artifacts" 2>/dev/null || { echo "  - $a: FAIL sessions_dir"; continue; }

  echo "  - $a: OK"
done
