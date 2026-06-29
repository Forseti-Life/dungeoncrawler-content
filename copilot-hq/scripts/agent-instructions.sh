#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

AGENT_ID="${1:-}"
if [ -z "$AGENT_ID" ]; then
  echo "Usage: $0 <agent-id>" >&2
  exit 1
fi

CTX="$(
  python3 - "$AGENT_ID" <<'PY'
import sys, re, pathlib, ast

agent_id = sys.argv[1]
text = pathlib.Path("org-chart/agents/agents.yaml").read_text(encoding="utf-8", errors="ignore").splitlines()

in_item = False
role = ""
website = ""

for line in text:
  m = re.match(r"^\s*-\s+id:\s*(.+)\s*$", line)
  if m:
    in_item = (m.group(1).strip() == agent_id)
    continue
  if not in_item:
    continue

  m = re.match(r"^\s*role:\s*(.+)\s*$", line)
  if m and not role:
    role = m.group(1).strip()

  m = re.match(r"^\s*website_scope:\s*(.+)\s*$", line)
  if m and not website:
    try:
      arr = ast.literal_eval(m.group(1).strip())
      if isinstance(arr, list) and arr and arr[0] != '*':
        website = str(arr[0])
    except Exception:
      pass

print(f"{role}\t{website}")
PY
)"

ROLE="$(printf '%s' "$CTX" | awk -F'\t' '{print $1}')"
WEBSITE="$(printf '%s' "$CTX" | awk -F'\t' '{print $2}')"

echo "== Org-wide =="
cat org-chart/org-wide.instructions.md
echo

if [ -n "$ROLE" ] && [ -f "org-chart/roles/${ROLE}.instructions.md" ]; then
  echo "== Role: ${ROLE} =="
  cat "org-chart/roles/${ROLE}.instructions.md"
  echo
else
  echo "== Role: (unknown) =="
  echo "No role instructions found for agent '${AGENT_ID}' (role='${ROLE}')."
  echo
fi

if [ -n "${WEBSITE:-}" ] && [ -f "org-chart/sites/${WEBSITE}/site.instructions.md" ]; then
  echo "== Site: ${WEBSITE} =="
  cat "org-chart/sites/${WEBSITE}/site.instructions.md"
  echo
fi

if [ -f "org-chart/agents/instructions/${AGENT_ID}.instructions.md" ]; then
  echo "== Seat: ${AGENT_ID} =="
  cat "org-chart/agents/instructions/${AGENT_ID}.instructions.md"
  echo
fi

echo "== Repo instructions =="
echo "- Follow the target repo's instructions.md (example: forseti.life/.github/instructions/instructions.md)."
