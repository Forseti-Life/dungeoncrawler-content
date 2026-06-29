#!/usr/bin/env bash
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"

python3 - <<'PY'
import re
from pathlib import Path
text = Path('org-chart/agents/agents.yaml').read_text(encoding='utf-8', errors='ignore').splitlines()
agents=[]
cur=None
for line in text:
    m=re.match(r'^\s*-\s+id:\s*(\S+)', line)
    if m:
        if cur: agents.append(cur)
        cur={'id':m.group(1)}
        continue
    if not cur: continue
    m=re.match(r'^\s*role:\s*(\S+)', line)
    if m: cur['role']=m.group(1)
    m=re.match(r'^\s*supervisor:\s*(\S+)', line)
    if m: cur['supervisor']=m.group(1)
if cur: agents.append(cur)

missing=[]
ids=set(a['id'] for a in agents)
for a in agents:
    if 'role' not in a: missing.append((a['id'],'role'))
    if 'supervisor' not in a: missing.append((a['id'],'supervisor'))
    elif a['supervisor'] not in ids: missing.append((a['id'],f"supervisor-not-found:{a['supervisor']}"))

if missing:
    print('INVALID org-chart/agents/agents.yaml:')
    for aid, what in missing:
        print(f"- {aid}: {what}")
    raise SystemExit(1)

print(f"OK: {len(agents)} agents validated (role+supervisor present)")
PY
