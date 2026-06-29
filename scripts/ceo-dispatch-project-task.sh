#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PROJECT_INPUT="${1:?project id or alias required}"
WORK_ITEM_ID="${2:?work item id required}"
TOPIC_RAW="${3:?topic required}"
TEXT="${4:?command text required}"

ASSIGN_FILE="${CEO_DEV_NODE_ASSIGNMENTS_FILE:-$ROOT_DIR/org-chart/products/dev-node-assignments.json}"
TEAMS_FILE="${CEO_PRODUCT_TEAMS_FILE:-$ROOT_DIR/org-chart/products/product-teams.json}"

# Load node identity for the canonical remote URL (if present)
IDENTITY_FILE="$ROOT_DIR/node-identity.conf"
if [[ -f "$IDENTITY_FILE" ]]; then
  # shellcheck source=../node-identity.conf
  # shellcheck disable=SC1091
  source "$IDENTITY_FILE"
fi

# Project assignment is a master-node-only operation.
# Worker nodes do not assign or dispatch project tasks.
_NODE_ROLE="${NODE_ROLE:-master}"
if [[ "$_NODE_ROLE" == "worker" ]]; then
  echo "ERROR: ceo-dispatch-project-task.sh must only run on the master node." >&2
  echo "       This machine is registered as NODE_ROLE=worker (NODE_ID=${NODE_ID:-unknown})." >&2
  echo "       Project assignment is performed by the CEO on the master node." >&2
  exit 1
fi
PROJECTS_REMOTE_URL="${CEO_PROJECTS_URL:-${NODE_PROJECTS_URL:-}}"
LOCAL_ROADMAP="$ROOT_DIR/dashboards/PROJECTS.md"

# Prefer the live remote roadmap; fall back to local copy.
if [[ -n "$PROJECTS_REMOTE_URL" ]] && command -v curl &>/dev/null; then
  _REMOTE_TMP="$(mktemp /tmp/projects-roadmap-XXXXXX.md)"
  if curl -fsSL --max-time 10 "$PROJECTS_REMOTE_URL" -o "$_REMOTE_TMP" 2>/dev/null; then
    ROADMAP_FILE="$_REMOTE_TMP"
  else
    ROADMAP_FILE="$LOCAL_ROADMAP"
    echo "[warn] could not fetch remote roadmap; using local copy" >&2
  fi
else
  ROADMAP_FILE="${CEO_ROADMAP_FILE:-$LOCAL_ROADMAP}"
fi

if [ ! -f "$ROADMAP_FILE" ]; then
  echo "ERROR: roadmap file not found: $ROADMAP_FILE" >&2
  exit 1
fi

if [ ! -f "$TEAMS_FILE" ]; then
  echo "ERROR: product teams file not found: $TEAMS_FILE" >&2
  exit 1
fi

resolved="$({
python3 - "$PROJECT_INPUT" "$ROADMAP_FILE" "$ASSIGN_FILE" "$TEAMS_FILE" <<'PY'
import json
import sys
from pathlib import Path

project_input = sys.argv[1].strip().lower()
roadmap_file = sys.argv[2]
assign_file = sys.argv[3]
teams_file = sys.argv[4]

with open(teams_file, 'r', encoding='utf-8') as f:
    teams_data = json.load(f)

teams = teams_data.get('teams', [])


def parse_roadmap_assignments(path: str):
  text = Path(path).read_text(encoding='utf-8', errors='ignore')
  lines = text.splitlines()
  start = None
  for i, ln in enumerate(lines):
    if ln.strip() == "## Development Node Assignment Registry":
      start = i + 1
      break
  if start is None:
    return {}

  table_lines = []
  for ln in lines[start:]:
    if ln.startswith("## "):
      break
    if ln.strip().startswith("|"):
      table_lines.append(ln)

  parsed = {}
  for ln in table_lines:
    row = ln.strip()
    if not row.startswith("|"):
      continue
    cells = [c.strip() for c in row.strip('|').split('|')]
    if len(cells) < 6:
      continue
    if cells[0].lower() == "project key":
      continue
    if cells[0].startswith('---'):
      continue

    project_key, target, target_agent, website, module, execute = cells[:6]
    key = project_key.strip().lower()
    if not key:
      continue
    parsed[key] = {
      "target": target.strip(),
      "target_agent": target_agent.strip(),
      "website": website.strip(),
      "module": module.strip(),
      "execute": execute.strip(),
    }

  return parsed


roadmap_assignments = parse_roadmap_assignments(roadmap_file)

assignments = {"default": {}, "projects": {}}
if Path(assign_file).exists():
  with open(assign_file, 'r', encoding='utf-8') as f:
    assignments = json.load(f)

canonical = None
for team in teams:
    team_id = str(team.get('id', '')).strip()
    aliases = [str(a).strip().lower() for a in team.get('aliases', [])]
    keys = {team_id.lower(), *aliases}
    if project_input in keys:
        canonical = team_id
        break

if canonical is None:
    print("ERROR: unknown project/alias", file=sys.stderr)
    sys.exit(2)

team = next((t for t in teams if str(t.get('id')) == canonical), {})
project_assign = roadmap_assignments.get(canonical, {})
if not project_assign:
  project_assign = (assignments.get('projects') or {}).get(canonical, {})
default_assign = assignments.get('default') or {}

target = str(project_assign.get('target', default_assign.get('target', 'dev-laptop'))).strip() or 'dev-laptop'
target_agent = str(project_assign.get('target_agent', team.get('dev_agent', ''))).strip()
if not target_agent:
    print("ERROR: target_agent unresolved", file=sys.stderr)
    sys.exit(3)

website = str(project_assign.get('website', team.get('site', 'forseti.life'))).strip() or 'forseti.life'
module = str(project_assign.get('module', canonical)).strip() or canonical
execute = str(project_assign.get('execute', default_assign.get('execute', 'dispatch-only'))).strip() or 'dispatch-only'

print(f"PROJECT_ID={canonical}")
print(f"TARGET={target}")
print(f"TARGET_AGENT={target_agent}")
print(f"WEBSITE={website}")
print(f"MODULE={module}")
print(f"EXECUTE={execute}")
print(f"ROUTING_SOURCE={'roadmap' if canonical in roadmap_assignments else 'fallback-json'}")
PY
} 2>&1)"

if printf '%s\n' "$resolved" | grep -q '^ERROR:'; then
  printf '%s\n' "$resolved" >&2
  exit 1
fi

eval "$resolved"

DEV_DISPATCH_TARGET="$TARGET" \
DEV_DISPATCH_WEBSITE="$WEBSITE" \
DEV_DISPATCH_MODULE="$MODULE" \
DEV_DISPATCH_EXECUTE="$EXECUTE" \
./scripts/dev-dispatch-task.sh "$TARGET_AGENT" "$WORK_ITEM_ID" "$TOPIC_RAW" "$TEXT"

echo "project=$PROJECT_ID target=$TARGET target_agent=$TARGET_AGENT website=$WEBSITE module=$MODULE execute=$EXECUTE source=$ROUTING_SOURCE"
