#!/usr/bin/env bash
set -euo pipefail

# Queue a product documentation round for all Product Manager agents.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DATE_YYYYMMDD="${1:-$(date +%Y%m%d)}"
TOPIC="${2:-product-documentation}"

pm_agents="$(
  python3 - <<'PY'
import re
from pathlib import Path
text = Path('org-chart/agents/agents.yaml').read_text(encoding='utf-8', errors='ignore').splitlines()
cur=None
agents=[]
for line in text:
    m=re.match(r'^\s*-\s+id:\s*(\S+)\s*$', line)
    if m:
        if cur: agents.append(cur)
        cur={'id':m.group(1)}
        continue
    if not cur: continue
    m=re.match(r'^\s*role:\s*(\S+)\s*$', line)
    if m: cur['role']=m.group(1)
if cur: agents.append(cur)
for a in agents:
    if a.get('role') == 'product-manager':
        print(a['id'])
PY
)"

for agent in $pm_agents; do
  inbox_dir="sessions/${agent}/inbox/${DATE_YYYYMMDD}-${TOPIC}"
  if [ -d "$inbox_dir" ]; then
    continue
  fi
  mkdir -p "$inbox_dir"
  printf '3\n' > "$inbox_dir/roi.txt"

  # Suggest default destination paths (PM may adjust if repo conventions differ).
  dest="(choose best path in target repo)"
  case "$agent" in
    pm-forseti) dest="/home/ubuntu/forseti.life/docs/product/job-hunter/README.md" ;;
    pm-dungeoncrawler) dest="/home/ubuntu/forseti.life/docs/dungeoncrawler/product-overview.md" ;;
    pm-stlouisintegration) dest="/home/ubuntu/forseti.life/docs/product/stlouisintegration/README.md" ;;
    pm-theoryofconspiracies) dest="/home/ubuntu/forseti.life/docs/product/theoryofconspiracies/README.md" ;;
    pm-thetruthperspective) dest="/home/ubuntu/forseti.life/docs/product/thetruthperspective/README.md" ;;
    pm-forseti-agent-tracker) dest="/home/ubuntu/forseti.life/docs/product/agent-management/copilot-agent-tracker.md" ;;
  esac

  cat > "$inbox_dir/command.md" <<MD
- command: |
    Product documentation round (PM-owned):
    1) Review your owned product/module area and current state.
    2) Generate (or update) product documentation using this template:
       - /home/ubuntu/forseti.life/templates/product-documentation.md
    3) Target destination suggestion:
       - ${dest}

    Acceptance criteria:
    - Documentation covers: overview, scope/non-goals, key workflows, permissions, data/integrations, ops notes, verification, known risks, roadmap.
    - Include the intended file path(s) and paste the full markdown content in your outbox so the executor/CEO can persist it.
    - If you need information from CEO/stakeholders, set Status: needs-info and list exact questions.
MD
done

echo "Created product documentation inbox items for PM agents: ${DATE_YYYYMMDD}-${TOPIC}"
