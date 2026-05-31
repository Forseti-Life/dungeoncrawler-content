#!/usr/bin/env bash
set -euo pipefail

# Publish HQ agent status into Forseti Drupal module copilot_agent_tracker.
# Uses drush php:eval (no HTTP server required).

HQ_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$HQ_ROOT"
# shellcheck source=lib/site-paths.sh
. "$HQ_ROOT/scripts/lib/site-paths.sh"

LOCKFILE="tmp/.publish-forseti-agent-tracker.lock"
mkdir -p "$(dirname "$LOCKFILE")"
exec 9>"$LOCKFILE"
if ! flock -n 9; then
  echo "Publisher already running; skip overlapping invocation."
  exit 0
fi

FORSETI_SITE_DIR="${FORSETI_SITE_DIR:-/var/www/html/forseti}"
# Back-compat: older variable name used throughout this script.
FORSITI_SITE_DIR="$FORSETI_SITE_DIR"
DRUSH_BIN="${FORSETI_SITE_DIR}/vendor/bin/drush"

if [ ! -x "$DRUSH_BIN" ]; then
  echo "Missing drush: $DRUSH_BIN" >&2
  exit 1
fi

org_priorities_json() {
  # Returns JSON array like: [{"key":"agent-management","score":200}, ...]
  # Uses PyYAML if available; otherwise a minimal YAML subset parser.
  python3 - <<'PY'
import json
import pathlib

p_path = pathlib.Path('org-chart/priorities.yaml')
if not p_path.exists():
  print('[]')
  raise SystemExit(0)

text = p_path.read_text(encoding='utf-8', errors='ignore')

try:
  import yaml  # type: ignore
except Exception:
  yaml = None

priorities = {}
if yaml:
  try:
    data = yaml.safe_load(text) or {}
    priorities = (data.get('priorities') or {}) if isinstance(data, dict) else {}
  except Exception:
    priorities = {}
else:
  in_pr = False
  for line in text.splitlines():
    s = line.strip()
    if not s or s.startswith('#'):
      continue
    if s == 'priorities:':
      in_pr = True
      continue
    if not in_pr:
      continue
    if line.startswith('  ') and ':' in line:
      k, v = s.split(':', 1)
      k = k.strip()
      v = v.strip()
      try:
        priorities[k] = int(v)
      except Exception:
        continue

items = []
for k, v in priorities.items():
  try:
    score = int(v)
  except Exception:
    continue
  items.append({'key': str(k), 'score': score})

items.sort(key=lambda x: x.get('score', 0), reverse=True)
print(json.dumps(items))
PY
}

# role lookup from agents.yaml
role_for() {
  local agent_id="$1"
  python3 - "$agent_id" <<'PY'
import sys, re
from pathlib import Path

agent_id = sys.argv[1]
path = Path('org-chart/agents/agents.yaml')
if not path.exists():
  print('')
  raise SystemExit(0)

text = path.read_text(encoding='utf-8', errors='ignore').splitlines()
in_item = False
role = ''
for line in text:
    m = re.match(r'^\s*-\s+id:\s*(.+)\s*$', line)
    if m:
        in_item = (m.group(1).strip() == agent_id)
        continue
    if in_item:
        m = re.match(r'^\s*role:\s*(.+)\s*$', line)
        if m:
            role = m.group(1).strip()
            break
print(role)
PY
}

website_for() {
  local agent_id="$1"
  python3 - "$agent_id" <<'PY'
import sys, re, ast
from pathlib import Path

agent_id = sys.argv[1]
path = Path('org-chart/agents/agents.yaml')
if not path.exists():
  print('')
  raise SystemExit(0)

text = path.read_text(encoding='utf-8', errors='ignore').splitlines()
in_item = False
val = ''
for line in text:
    m = re.match(r'^\s*-\s+id:\s*(.+)\s*$', line)
    if m:
        in_item = (m.group(1).strip() == agent_id)
        continue
    if in_item:
        m = re.match(r'^\s*website_scope:\s*(.+)\s*$', line)
        if m:
            raw = m.group(1).strip()
            try:
                arr = ast.literal_eval(raw)
                if isinstance(arr, list) and arr:
                    val = str(arr[0])
            except Exception:
                pass
            break
if val == '*':
    val = ''
print(val)
PY
}

module_for() {
  local agent_id="$1"
  python3 - "$agent_id" <<'PY'
import sys, re, ast
from pathlib import Path

agent_id = sys.argv[1]
path = Path('org-chart/agents/agents.yaml')
if not path.exists():
  print('')
  raise SystemExit(0)

text = path.read_text(encoding='utf-8', errors='ignore').splitlines()
in_item = False
val = ''
for line in text:
    m = re.match(r'^\s*-\s+id:\s*(.+)\s*$', line)
    if m:
        in_item = (m.group(1).strip() == agent_id)
        continue
    if in_item:
        m = re.match(r'^\s*module_ownership:\s*(.+)\s*$', line)
        if m:
            raw = m.group(1).strip()
            try:
                arr = ast.literal_eval(raw)
                if isinstance(arr, list) and arr:
                    val = str(arr[0])
            except Exception:
                pass
            break
print(val)
PY
}

latest_mtime_epoch() {
  local path="$1"
  if [ ! -e "$path" ]; then
    echo 0
    return
  fi
  python3 - "$path" <<'PY'
import sys
from pathlib import Path

p = Path(sys.argv[1])
if not p.exists():
  print(0)
  raise SystemExit(0)

best = 0
try:
  for child in p.rglob('*'):
    try:
      st = child.stat()
    except Exception:
      continue
    m = int(st.st_mtime)
    if m > best:
      best = m
except Exception:
  best = 0

print(best)
PY
}

agent_exec_yes() {
  local agent="$1"
  local out_epoch
  out_epoch=$(latest_mtime_epoch "sessions/${agent}/outbox")
  [ "$out_epoch" -gt 0 ] || { echo "no"; return; }
  local now; now=$(date +%s)
  local delta=$((now-out_epoch))
  if [ "$delta" -le 1800 ]; then echo "yes"; else echo "no"; fi
}

agent_active_inbox_info() {
  # Returns tab-separated: item_id\tstarted_at_iso\tpid
  # Best-effort: validates that the recorded pid is still running.
  local agent="$1"
  local p="sessions/${agent}/artifacts/active-inbox-item.json"
  if [ ! -f "$p" ]; then
    echo $'\t\t'
    return
  fi

  python3 - "$p" "$agent" <<'PY' 2>/dev/null || { echo $'\t\t'; exit 0; }
import json
import os
import sys
from pathlib import Path

path = Path(sys.argv[1])
agent_expected = (sys.argv[2] or '').strip()

try:
  obj = json.loads((path.read_text(encoding='utf-8', errors='ignore') or '').strip() or '{}')
except Exception:
  print('\t\t')
  raise SystemExit(0)

agent_id = str(obj.get('agent_id') or '').strip()
if agent_expected and agent_id and agent_id != agent_expected:
  print('\t\t')
  raise SystemExit(0)

item_id = str(obj.get('item_id') or '').strip()
started = str(obj.get('started_at_iso') or '').strip()
pid_raw = obj.get('pid')

pid = 0
try:
  pid = int(pid_raw)
except Exception:
  pid = 0

alive = False
if pid > 0:
  try:
    os.kill(pid, 0)
    alive = True
  except Exception:
    alive = False

# Additional validation to reduce false positives from PID reuse.
if alive and agent_expected:
  try:
    cmdline_path = Path(f"/proc/{pid}/cmdline")
    cmdline = cmdline_path.read_text(encoding='utf-8', errors='ignore')
    cmd = cmdline.replace('\x00', ' ')
    if 'agent-exec-next.sh' not in cmd or agent_expected not in cmd:
      alive = False
  except Exception:
    # If we can't read cmdline, keep best-effort alive=True based on kill(0).
    pass

if not alive:
  # Stale marker; clear it so dashboards don't lie after a crash/kill.
  try:
    path.unlink()
  except Exception:
    pass
  print('\t\t')
  raise SystemExit(0)

sys.stdout.write(f"{item_id}\t{started}\t{pid}\n")
PY
}

format_started_local() {
  local started_iso="${1:-}"
  [ -n "$started_iso" ] || { echo ""; return; }
  local out=""
  out="$(date -d "$started_iso" '+%Y-%m-%d %H:%M:%S %Z' 2>/dev/null || true)"
  if [ -n "$out" ]; then
    echo "$out"
  else
    echo "$started_iso"
  fi
}

elapsed_from_started_iso() {
  local started_iso="${1:-}"
  [ -n "$started_iso" ] || { echo ""; return; }

  python3 - "$started_iso" <<'PY' 2>/dev/null || { echo ""; exit 0; }
import datetime as dt
import sys

raw = (sys.argv[1] or '').strip()
if not raw:
  print('')
  raise SystemExit(0)

try:
  if raw.endswith('Z'):
    raw = raw[:-1] + '+00:00'
  started = dt.datetime.fromisoformat(raw)
  if started.tzinfo is None:
    started = started.replace(tzinfo=dt.timezone.utc)
  now = dt.datetime.now(dt.timezone.utc)
  sec = int((now - started).total_seconds())
  if sec < 0:
    sec = 0

  days = sec // 86400
  rem = sec % 86400
  hours = rem // 3600
  rem = rem % 3600
  mins = rem // 60

  parts = []
  if days > 0:
    parts.append(f"{days}d")
  if hours > 0:
    parts.append(f"{hours}h")
  if mins > 0:
    parts.append(f"{mins}m")
  if not parts:
    parts.append("<1m")

  print(' '.join(parts))
except Exception:
  print('')
PY
}

agent_next_inbox() {
  # Choose the next inbox item by EFFECTIVE ROI so UI ordering matches executor ordering.
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  [ -d "$dir" ] || { echo ""; return; }

  find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | sed 's|.*/||' \
    | while IFS= read -r name; do
        [ -n "$name" ] || continue
        eff="$(agent_inbox_item_effective_roi "$agent" "$name")"
        [[ "$eff" =~ ^[0-9]+$ ]] || eff=1
        printf '%s\t%s\n' "$eff" "$name"
      done \
    | python3 -c $'import sys\n\nbest_eff=-1\nbest_name=""\n\nfor ln in sys.stdin:\n  ln=ln.rstrip("\\n")\n  if not ln.strip():\n    continue\n  parts=ln.split("\\t",1)\n  if len(parts)!=2:\n    continue\n  eff_s,name=parts[0].strip(),parts[1].strip()\n  if not name:\n    continue\n  try:\n    eff=int(eff_s)\n  except Exception:\n    eff=1\n  if eff>best_eff or (eff==best_eff and (best_name=="" or name<best_name)):\n    best_eff=eff\n    best_name=name\n\nprint(best_name)'
}

agent_inbox_item_roi() {
  local agent="$1" item="$2"
  [ -n "$item" ] || { echo 1; return; }
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  local roi_file="$dir/$item/roi.txt"
  local roi="1"
  if [ -f "$roi_file" ]; then
    roi="$(head -n 1 "$roi_file" 2>/dev/null | tr -d '\r' | tr -cd '0-9' || echo 0)"
  else
    if [[ "$item" =~ (^|-)roi-([0-9]{1,9})(-|$) ]]; then
      roi="${BASH_REMATCH[2]}"
    fi
  fi
  [[ "$roi" =~ ^[0-9]+$ ]] || roi=1
  [ "$roi" -ge 1 ] 2>/dev/null || roi=1
  echo "$roi"
}

# Organizational priority weighting (shared helper).
if [ -f "$HQ_ROOT/scripts/lib/org-priority.sh" ]; then
  # shellcheck source=/dev/null
  . "$HQ_ROOT/scripts/lib/org-priority.sh"
fi

agent_inbox_item_effective_roi() {
  # Effective ROI = base ROI + org priority bonus.
  # Note: base ROI itself is bumped on completion for anti-staleness.
  # Mirrors scripts/agent-exec-next.sh behavior so UI ordering matches execution ordering.
  local agent="$1" item="$2"
  [ -n "$item" ] || { echo 1; return; }
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  local item_dir="$dir/$item"
  [ -d "$item_dir" ] || { echo 1; return; }

  local base roi_max aging_per_day aging_max
  base="$(agent_inbox_item_roi "$agent" "$item")"
  [[ "$base" =~ ^[0-9]+$ ]] || base=1
  [ "$base" -ge 1 ] 2>/dev/null || base=1

  roi_max="${AGENT_EXEC_ROI_MAX:-10000}"

  if [[ "$roi_max" =~ ^[0-9]+$ ]] && [ "$roi_max" -ge 1 ] 2>/dev/null && [ "$base" -gt "$roi_max" ] 2>/dev/null; then
    base="$roi_max"
  fi

  local org_bonus
  org_bonus="$(org_priority_bonus_for_item "$item_dir" "$item" "$base")"
  [[ "$org_bonus" =~ ^[0-9]+$ ]] || org_bonus=0

  echo $((base + org_bonus))
}

agent_inbox_count() {
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  [ -d "$dir" ] || { echo 0; return; }
  find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | wc -l | awk '{print $1}'
}

agent_inbox_items_json() {
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  [ -d "$dir" ] || { echo "[]"; return; }
  find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | sed 's|.*/||' \
    | while IFS= read -r name; do
        [ -n "$name" ] || continue
        eff="$(agent_inbox_item_effective_roi "$agent" "$name")"
        printf '%s\t%s\n' "$eff" "$name"
      done \
    | python3 -c $'import json\nimport sys\n\nrows=[]\nfor ln in sys.stdin:\n  ln=ln.rstrip("\\n")\n  if not ln.strip():\n    continue\n  parts=ln.split("\\t",1)\n  if len(parts)!=2:\n    continue\n  eff_s,name=parts[0].strip(),parts[1].strip()\n  if not name:\n    continue\n  try:\n    eff=int(eff_s)\n  except Exception:\n    eff=0\n  rows.append((eff,name))\n\nrows.sort(key=lambda t:(-t[0], t[1]))\nprint(json.dumps([name for _,name in rows[:25]]))'
}

agent_inbox_items_detail_json() {
  # Returns JSON array of objects for top inbox items by effective ROI.
  # Each object includes limited, sanitized detail for display in Drupal.
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  [ -d "$dir" ] || { echo "[]"; return; }

  find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | sed 's|.*/||' \
    | while IFS= read -r name; do
        [ -n "$name" ] || continue
        base="$(agent_inbox_item_roi "$agent" "$name")"
        [[ "$base" =~ ^[0-9]+$ ]] || base=1
        [ "$base" -ge 1 ] 2>/dev/null || base=1

        eff="$(agent_inbox_item_effective_roi "$agent" "$name")"
        [[ "$eff" =~ ^[0-9]+$ ]] || eff="$base"
        [ "$eff" -ge 1 ] 2>/dev/null || eff="$base"

        printf '%s\t%s\t%s\n' "$eff" "$base" "$name"
      done \
    | {
        py=$(cat <<'PY'
import json
import sys
from pathlib import Path

root = Path(sys.argv[1])

rows = []
for ln in sys.stdin:
  ln = ln.rstrip('\n')
  if not ln.strip():
    continue
  parts = ln.split("\t")
  if len(parts) < 3:
    continue
  eff_s, base_s, item_id = parts[0].strip(), parts[1].strip(), parts[2].strip()
  if not item_id:
    continue
  try:
    eff = int(eff_s)
  except Exception:
    eff = 1
  try:
    base = int(base_s)
  except Exception:
    base = 1
  if eff < 1:
    eff = 1
  if base < 1:
    base = 1
  rows.append((eff, item_id, base))

rows.sort(key=lambda t: (-t[0], t[1]))
rows = rows[:25]

def safe_read(p: Path, limit_lines: int = 220, limit_chars: int = 12000) -> str:
  if not p.exists() or not p.is_file():
    return ""
  txt = p.read_text(encoding="utf-8", errors="ignore")
  txt = "\n".join(txt.splitlines()[:limit_lines]).strip()
  return txt[:limit_chars]

items = []
for eff, item_id, base in rows:
  d = root / item_id
  if not d.exists() or not d.is_dir():
    continue

  try:
    mtime = int(d.stat().st_mtime)
  except Exception:
    mtime = 0

  file_names = []
  try:
    file_names = sorted([p.name for p in d.iterdir() if p.is_file()])
  except Exception:
    file_names = []

  body = ""
  body_source = ""
  for fname in ("README.md", "command.md", "request.md", "notes.md"):
    txt = safe_read(d / fname)
    if txt:
      body = txt
      body_source = fname
      break
  if not body and file_names:
    txt = safe_read(d / file_names[0])
    if txt:
      body = txt
      body_source = file_names[0]

  preview = " ".join(body.splitlines()).strip()
  if len(preview) > 280:
    preview = preview[:277] + "..."

  items.append({
    "item_id": item_id,
    "roi": base,
    "effective_roi": eff,
    "mtime": mtime,
    "files": file_names[:40],
    "body_source": body_source,
    "body": body,
    "preview": preview,
  })

print(json.dumps(items))
PY
)
        python3 -c "$py" "$dir"
      }
}

agent_outbox_results_json() {
  # Returns JSON object summarizing recent outbox updates for an agent.
  # Includes:
  # - recent: newest-first list of outbox items with status/summary/roi/excerpt
  # - counts_7d: counts by Status over the last 7 days (by file mtime)
  # - last_mtime: newest outbox mtime
  local agent="$1"
  local dir="sessions/${agent}/outbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  [ -d "$dir" ] || { echo "{}"; return; }

  find "$dir" -maxdepth 1 -type f -name '*.md' 2>/dev/null \
    | while IFS= read -r f; do printf '%s\t%s\n' "$(stat -c '%Y' "$f" 2>/dev/null || echo 0)" "$(basename "$f")"; done \
    | sort -r \
    | python3 scripts/lib/agent-outbox-results.py "$dir"
}

role_kpis_json() {
  # Returns JSON object for the given role name from org-chart/kpis.md:
  # { value: str, cost: [str], quality: [str], speed: [str] }
  # If not found/parsable, returns {}.
  local role="$1"
  python3 - "$role" <<'PY'
import json
import re
import sys
from pathlib import Path

role = (sys.argv[1] or '').strip()

role_to_heading_prefix = {
  'ceo': '### CEO',
  'product-manager': '### Product Manager',
  'business-analyst': '### Business Analyst',
  'software-developer': '### Software Developer',
  'tester': '### Tester',
  'security-analyst': '### Security Analyst',
}

prefix = role_to_heading_prefix.get(role)
if not prefix:
  print("{}")
  raise SystemExit(0)

p = Path('org-chart/kpis.md')
if not p.exists():
  print("{}")
  raise SystemExit(0)

lines = p.read_text(encoding='utf-8', errors='ignore').splitlines()

start = None
for i, ln in enumerate(lines):
  if ln.strip().startswith(prefix):
    start = i
    break

if start is None:
  print("{}")
  raise SystemExit(0)

end = len(lines)
for j in range(start + 1, len(lines)):
  if lines[j].startswith('### '):
    end = j
    break

block = lines[start:end]

value = ''
cost = []
quality = []
speed = []

mode = None
for raw in block:
  ln = raw.strip()
  if not ln:
    continue

  m = re.match(r'^\*\*Value I add:\*\*\s*(.+)\s*$', ln)
  if m:
    value = m.group(1).strip()
    continue

  if ln == '**Cost KPIs**':
    mode = 'cost'
    continue
  if ln == '**Quality KPIs**':
    mode = 'quality'
    continue
  if ln == '**Speed KPIs**':
    mode = 'speed'
    continue

  if ln.startswith('**') and ln.endswith('**'):
    mode = None
    continue

  if mode in ('cost', 'quality', 'speed') and ln.startswith('- '):
    item = ln[2:].strip()
    if not item:
      continue
    if mode == 'cost':
      cost.append(item)
    elif mode == 'quality':
      quality.append(item)
    else:
      speed.append(item)

out = {
  'value': value,
  'cost': cost[:8],
  'quality': quality[:8],
  'speed': speed[:8],
}

if not out['value'] and not out['cost'] and not out['quality'] and not out['speed']:
  print("{}")
else:
  print(json.dumps(out))
PY
}

ceo_inbox_messages_json() {
  local dir
  dir="$(python3 - <<'PY'
import os
import re
from pathlib import Path

env_pref = os.environ.get("ORCHESTRATOR_CEO_AGENT", "").strip()

def paused(agent_id: str) -> bool:
  script = Path("scripts/is-agent-paused.sh")
  if not script.exists():
    return False
  import subprocess
  p = subprocess.run(["bash", str(script), agent_id], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
  return p.returncode == 0 and (p.stdout or "").strip().lower() == "true"

if env_pref.startswith("ceo-copilot") and not paused(env_pref):
  print(f"sessions/{env_pref}/inbox")
  raise SystemExit(0)

agents = Path("org-chart/agents/agents.yaml")
if agents.exists():
  for ln in agents.read_text(encoding="utf-8", errors="ignore").splitlines():
    m = re.match(r"^\s*-\s+id:\s*(\S+)\s*$", ln)
    if not m:
      continue
    aid = m.group(1).strip()
    if aid.startswith("ceo-copilot") and not paused(aid):
      print(f"sessions/{aid}/inbox")
      raise SystemExit(0)

print("sessions/ceo-copilot/inbox")
PY
)"
  [ -d "$dir" ] || { echo "[]"; return; }
  python3 - "$dir" <<'PY'
import json
import pathlib
import re
import sys

inbox = pathlib.Path(sys.argv[1])
messages = []

agents_file = pathlib.Path("org-chart/agents/agents.yaml")
agent_ids = []
if agents_file.exists():
  for ln in agents_file.read_text(encoding="utf-8", errors="ignore").splitlines():
    m = re.match(r"^\s*-\s+id:\s*(.+?)\s*$", ln)
    if m:
      agent_ids.append(m.group(1).strip())
agent_ids.sort(key=len, reverse=True)


def is_waiting_on_keith_item(item_id: str) -> bool:
  # Only treat escalation/decision items as "messages".
  # CEO internal work items (e.g., ceo-bestpractices-*) should NOT appear in Keith's inbox.
  return bool(re.match(r"^\d{8}-(needs|needs-escalated)-", item_id))


def from_agent_for_item(item_id: str) -> str:
  m = re.match(r"^\d{8}-(needs|needs-escalated)-", item_id)
  if not m:
    return ""
  rest = item_id[m.end() :]
  for aid in agent_ids:
    if rest.startswith(aid + "-"):
      return aid
  m2 = re.match(r"^([a-z0-9-]{1,128})-", rest)
  return m2.group(1) if m2 else ""


def section(text: str, heading: str) -> str:
  lines = text.splitlines()
  out = []
  inside = False
  for ln in lines:
    if ln.strip() == f"## {heading}":
      inside = True
      continue
    if inside and ln.startswith("## "):
      break
    if inside:
      out.append(ln)
  s = "\n".join(out).strip()
  return "\n".join(s.splitlines()[:20]).strip()


for p in sorted([x for x in inbox.iterdir() if x.is_dir()]):
  item_id = p.name
  if not is_waiting_on_keith_item(item_id):
    continue

  from_agent = from_agent_for_item(item_id)
  subject = item_id

  body = ""
  for fname in ("README.md", "command.md"):
    f = p / fname
    if f.exists():
      body = f.read_text(encoding="utf-8", errors="ignore")
      break
  body = "\n".join(body.splitlines()[:200]).strip()

  # Fallback: escalation README includes "- Agent: <id>".
  if not from_agent:
    m_agent = re.search(r"^\\- Agent:\\s*(\\S+)", body, re.M)
    if m_agent:
      from_agent = m_agent.group(1).strip()

  website = ""
  module = ""
  role = ""
  m2 = re.search(r"^\\- Website:\\s*(.+)$", body, re.M)
  if m2:
    website = m2.group(1).strip()
  m2 = re.search(r"^\\- Module:\\s*(.+)$", body, re.M)
  if m2:
    module = m2.group(1).strip()
  m2 = re.search(r"^\\- Role:\\s*(.+)$", body, re.M)
  if m2:
    role = m2.group(1).strip()

  decision_needed = section(body, "Decision needed")
  recommendation = section(body, "Recommendation")

  # Never emit an unreplyable message row.
  if not from_agent:
    continue

  messages.append(
    {
      "item_id": item_id,
      "from_agent": from_agent,
      "subject": subject,
      "body": body,
      "website": website,
      "module": module,
      "role": role,
      "decision_needed": decision_needed,
      "recommendation": recommendation,
    }
  )

  if len(messages) >= 25:
    break

print(json.dumps(messages))
PY
}

ceo_release_notes_json() {
  # Returns JSON array of recent release candidates + shipped releases.
  # This is the data source for the Drupal "Release Notes" admin report page.
  #
  # Data sources (in priority order):
  #   1. sessions/pm-*/artifacts/release-candidates/<release-id>/  — full candidate dirs
  #   2. sessions/pm-*/artifacts/release-signoffs/<release-id>.md  — signoff-only fallback
  #   3. sessions/ceo-copilot*/artifacts/releases/<release-id>/    — legacy CEO-owned artifacts
  python3 - <<'PY'
import json
import os
from pathlib import Path

session_root = Path('sessions')

def safe_read(p: Path, limit_lines: int = 80, limit_chars: int = 5000) -> str:
  if not p.exists() or not p.is_file():
    return ''
  txt = p.read_text(encoding='utf-8', errors='ignore')
  txt = '\n'.join(txt.splitlines()[:limit_lines])
  return txt[:limit_chars]

def dir_mtime(p: Path) -> int:
  try:
    return int(p.stat().st_mtime)
  except Exception:
    return 0

def file_mtime(p: Path) -> int:
  try:
    return int(p.stat().st_mtime)
  except Exception:
    return 0

entries = []

# 1. PM release-candidate folders (authoritative — full artifact set)
for pm_dir in sorted(session_root.glob('pm-*')):
  rc_root = pm_dir / 'artifacts' / 'release-candidates'
  if not rc_root.is_dir():
    continue
  for d in rc_root.iterdir():
    if not d.is_dir():
      continue
    release_notes_raw = safe_read(d / '05-release-notes.md') or safe_read(d / 'release-notes.md')
    # Infer state: if a push-dispatched marker exists it's shipped, else pending.
    pushed_marker = Path('tmp/auto-push-dispatched') / (d.name.replace('/', '-') + '.pushed')
    state = 'shipped' if pushed_marker.exists() else 'pending'
    entries.append({
      'release_id': d.name,
      'state': state,
      'mtime': dir_mtime(d),
      'plan': safe_read(d / '00-release-plan.md'),
      'change_list': safe_read(d / '01-change-list.md'),
      'test_evidence': safe_read(d / '02-test-evidence.md'),
      'risk_security': safe_read(d / '03-risk-security.md'),
      'rollback': safe_read(d / '04-rollback.md'),
      'release_notes': release_notes_raw,
    })

# 2. PM signoff files — fallback for releases that were signed off but have no candidate dir
signoff_release_ids: set = set()
for pm_dir in sorted(session_root.glob('pm-*')):
  so_dir = pm_dir / 'artifacts' / 'release-signoffs'
  if not so_dir.is_dir():
    continue
  for f in so_dir.iterdir():
    if f.suffix == '.md' and not f.stem.startswith('_'):
      signoff_release_ids.add(f.stem)

existing_ids = {e['release_id'] for e in entries}
for release_id in sorted(signoff_release_ids - existing_ids):
  # Collect all PM signoff files for this release
  signoff_texts = []
  latest_mtime = 0
  for pm_dir in sorted(session_root.glob('pm-*')):
    sf = pm_dir / 'artifacts' / 'release-signoffs' / f'{release_id}.md'
    if sf.exists():
      signoff_texts.append(f'**{pm_dir.name}**\n' + safe_read(sf))
      latest_mtime = max(latest_mtime, file_mtime(sf))
  pushed_marker = Path('tmp/auto-push-dispatched') / (release_id + '.pushed')
  state = 'shipped' if pushed_marker.exists() else 'signed-off'
  entries.append({
    'release_id': release_id,
    'state': state,
    'mtime': latest_mtime,
    'plan': '',
    'change_list': '',
    'test_evidence': '',
    'risk_security': '',
    'rollback': '',
    'release_notes': '\n\n---\n\n'.join(signoff_texts),
  })

# 3. Legacy CEO-owned artifacts/releases dirs
for ceo_dir in sorted(session_root.glob('ceo-copilot*')):
  shipped_dir = ceo_dir / 'artifacts' / 'releases'
  if not shipped_dir.is_dir():
    continue
  for d in shipped_dir.iterdir():
    if not d.is_dir() or d.name in {e['release_id'] for e in entries}:
      continue
    entries.append({
      'release_id': d.name,
      'state': 'shipped',
      'mtime': dir_mtime(d),
      'plan': safe_read(d / '00-release-plan.md'),
      'change_list': safe_read(d / '01-change-list.md'),
      'test_evidence': safe_read(d / '02-test-evidence.md'),
      'risk_security': safe_read(d / '03-risk-security.md'),
      'rollback': safe_read(d / '04-rollback.md'),
      'release_notes': safe_read(d / '05-release-notes.md') or safe_read(d / 'release-notes.md'),
    })

# Dedupe by release id, keep entry with highest mtime.
dedup = {}
for e in entries:
  rid = str(e.get('release_id') or '').strip()
  if not rid:
    continue
  prev = dedup.get(rid)
  if prev is None or int(e.get('mtime') or 0) >= int(prev.get('mtime') or 0):
    dedup[rid] = e

entries = list(dedup.values())
entries.sort(key=lambda e: int(e.get('mtime') or 0), reverse=True)
print(json.dumps(entries[:20]))
PY
}

qa_test_counts_json() {
  # Returns JSON object: {unit:int,functional:int,integration:int,total:int,source:str}
  # For non-QA agents, returns {}.
  local agent="$1"
  local website="$2"
  python3 - "$agent" "$website" <<'PY'
import json
import re
import sys
from pathlib import Path

agent = (sys.argv[1] or '').strip()
website = (sys.argv[2] or '').strip()

def site_key() -> str:
  if website == 'forseti.life' or agent == 'qa-forseti' or agent.endswith('-forseti'):
    return 'forseti.life'
  if website in ('dungeoncrawler', 'dungeoncrawler.forseti.life') or agent == 'qa-dungeoncrawler' or agent.endswith('-dungeoncrawler'):
    return 'dungeoncrawler'
  return ''

sk = site_key()
if not sk:
  print('{}')
  raise SystemExit(0)

p = Path('org-chart') / 'sites' / sk / 'qa-test-roster.md'
if not p.exists():
  print(json.dumps({'unit': 0, 'functional': 0, 'integration': 0, 'total': 0, 'source': str(p)}))
  raise SystemExit(0)

section = ''
counts = {'unit': 0, 'functional': 0, 'integration': 0}

for raw in p.read_text(encoding='utf-8', errors='ignore').splitlines():
  line = raw.strip()
  if not line:
    continue
  m = re.match(r'^##\s+(.+?)\s*$', line)
  if m:
    h = m.group(1).strip().lower()
    if 'unit' in h:
      section = 'unit'
    elif 'functional' in h:
      section = 'functional'
    elif 'integration' in h:
      section = 'integration'
    else:
      section = ''
    continue
  if section in counts and re.match(r'^-\s+\[(x| |)\]\s+\S', line, re.IGNORECASE):
    counts[section] += 1
  elif section in counts and line.startswith('- '):
    # Also allow plain bullet items.
    counts[section] += 1

out = {
  'unit': int(counts['unit']),
  'functional': int(counts['functional']),
  'integration': int(counts['integration']),
  'total': int(sum(counts.values())),
  'source': str(p),
}

print(json.dumps(out))
PY
}

qa_last_audit_json() {
  # Returns JSON object summarizing the most recent scripted QA audit run for a QA seat.
  # If no artifacts exist, returns {}.
  local agent="$1"
  python3 - "$agent" <<'PY'
import json
import os
import sys
from pathlib import Path

agent = (sys.argv[1] or '').strip()

def cfg_for_agent(a: str):
  if a == 'qa-forseti':
    return {
      'label': 'forseti-life',
      'dir': Path('sessions/qa-forseti/artifacts/auto-site-audit/latest'),
    }
  if a == 'qa-dungeoncrawler':
    return {
      'label': 'dungeoncrawler',
      'dir': Path('sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest'),
    }
  return None

cfg = cfg_for_agent(agent)
if not cfg:
  print('{}')
  raise SystemExit(0)

latest = cfg['dir']
label = cfg['label']

try:
  real_dir = latest.resolve(strict=True)
except Exception:
  # latest may not exist yet.
  print('{}')
  raise SystemExit(0)

def load(p: Path):
  if not p.exists():
    return None
  try:
    return json.loads(p.read_text(encoding='utf-8', errors='ignore'))
  except Exception:
    return None

def count_http_fail(results):
  fail = 0
  total = 0
  for r in results or []:
    if not isinstance(r, dict):
      continue
    total += 1
    st = int(r.get('status') or 0)
    if st == 0 or st >= 400:
      fail += 1
  return total, fail

url_total = 0
url_fail = 0
route_total = 0
route_fail = 0
roles_run = set()

# Anonymous files.
crawl = load(real_dir / f"{label}-crawl.json") or {}
validate = load(real_dir / f"{label}-validate.json") or {}
routes = load(real_dir / f"{label}-custom-routes.json") or {}

ct, cf = count_http_fail(crawl.get('results'))
vt, vf = count_http_fail(validate.get('results'))
url_total += ct + vt
url_fail += cf + vf

checks = routes.get('checks') if isinstance(routes, dict) else None
rt, rf = count_http_fail(checks)
route_total += rt
route_fail += rf

# Per-role directories (optional, depends on cookies configured).
roles_dir = real_dir / 'roles'
if roles_dir.exists() and roles_dir.is_dir():
  for d in roles_dir.iterdir():
    if not d.is_dir():
      continue
    role_id = d.name
    roles_run.add(role_id)

    crawl_r = load(d / f"{label}-crawl.json") or {}
    validate_r = load(d / f"{label}-validate.json") or {}
    routes_r = load(d / f"{label}-custom-routes.json") or {}

    ct, cf = count_http_fail(crawl_r.get('results'))
    vt, vf = count_http_fail(validate_r.get('results'))
    url_total += ct + vt
    url_fail += cf + vf

    checks = routes_r.get('checks') if isinstance(routes_r, dict) else None
    rt, rf = count_http_fail(checks)
    route_total += rt
    route_fail += rf

perm = load(real_dir / 'permissions-validation.json') or {}
violation_count = int(perm.get('violation_count') or 0)
probe_issue_count = int(perm.get('probe_issue_count') or 0)
roles_run_perm = perm.get('roles_run') or []
for r in roles_run_perm:
  if isinstance(r, str) and r:
    roles_run.add(r)

findings = load(real_dir / 'findings-summary.json') or {}
findings_counts = findings.get('counts') if isinstance(findings, dict) else None
findings_missing_assets_404s = 0
findings_failures = 0
findings_permission_violations = 0
has_findings_summary = False
if isinstance(findings_counts, dict):
  findings_missing_assets_404s = int(findings_counts.get('missing_assets_404s') or 0)
  findings_failures = int(findings_counts.get('failures') or 0)
  findings_permission_violations = int(findings_counts.get('permission_violations') or 0)
  has_findings_summary = True

# Prefer findings-summary counts for release-stage fail totals. Raw crawl/route
# fail counts are still useful diagnostics but can over-count relative to defect
# buckets shown to dev/PM in findings-summary.
if has_findings_summary:
  url_fail = findings_missing_assets_404s + findings_failures
  route_fail = 0
  violation_count = findings_permission_violations

base_url = None
for obj in (validate, crawl, perm):
  if isinstance(obj, dict) and obj.get('base_url'):
    base_url = str(obj.get('base_url'))
    break

clean = (url_fail == 0 and route_fail == 0 and violation_count == 0)

out = {
  'label': label,
  'base_url': base_url,
  'run_dir': str(real_dir),
  'run_id': real_dir.name,
  'roles_covered': sorted(roles_run),
  'url_checks_total': url_total,
  'url_checks_failed': url_fail,
  'route_checks_total': route_total,
  'route_checks_failed': route_fail,
  'permission_violation_count': violation_count,
  'probe_issue_count': probe_issue_count,
  'counts_source': 'findings-summary' if has_findings_summary else 'raw-checks',
  'findings_missing_assets_404s': findings_missing_assets_404s,
  'findings_failures': findings_failures,
  'findings_permission_violations': findings_permission_violations,
  'failed_checks_total': int(url_fail + route_fail + violation_count),
  'status': 'clean' if clean else 'issues',
}

print(json.dumps(out))
PY
}

stage3_velocity_cache_json() {
  python3 scripts/stage3-velocity.py --team all --window-minutes 15 --json 2>/dev/null || echo '{"teams":[]}'
}

stage3_velocity_for_agent_json() {
  local agent="$1"
  local cache_file="${STAGE3_VELOCITY_CACHE_FILE:-tmp/.stage3-velocity-cache.json}"
  python3 - "$agent" "$cache_file" <<'PY'
import json
import sys
from pathlib import Path

agent = (sys.argv[1] or '').strip()
cache_file = Path(sys.argv[2])

try:
  velocity = json.loads((cache_file.read_text(encoding='utf-8', errors='ignore') or '').strip() or '{"teams":[]}')
except Exception:
  velocity = {"teams": []}

teams_by_id = {}
for t in (velocity.get('teams') or []):
  tid = str((t or {}).get('team_id') or '').strip()
  if tid:
    teams_by_id[tid] = t

cfg_path = Path('org-chart/products/product-teams.json')
if not cfg_path.exists():
  print('{}')
  raise SystemExit(0)

try:
  cfg = json.loads(cfg_path.read_text(encoding='utf-8', errors='ignore'))
except Exception:
  print('{}')
  raise SystemExit(0)

team_id = ''
for team in (cfg.get('teams') or []):
  if not team.get('active', False):
    continue
  if not (team.get('site_audit') or {}).get('enabled', False):
    continue
  if agent in {
    str(team.get('dev_agent') or '').strip(),
    str(team.get('qa_agent') or '').strip(),
    str(team.get('pm_agent') or '').strip(),
  }:
    team_id = str(team.get('id') or '').strip()
    break

if not team_id:
  print('{}')
  raise SystemExit(0)

row = teams_by_id.get(team_id) or {}
print(json.dumps(row if isinstance(row, dict) else {}))
PY
}

STAGE3_VELOCITY_CACHE_FILE="tmp/.stage3-velocity-cache.json"
mkdir -p "$(dirname "$STAGE3_VELOCITY_CACHE_FILE")"
stage3_velocity_cache_json > "$STAGE3_VELOCITY_CACHE_FILE"

publish_one() {
  local agent="$1"
  local role website module status action summary inbox_count next_inbox exec inbox_items_json inbox_items_detail_json next_inbox_roi next_inbox_effective_roi priorities_json release_notes_json outbox_results_json configured_seats_json
  local active_inbox active_inbox_started active_inbox_pid
  local active_inbox_started_local active_inbox_elapsed active_inbox_effective_roi
  local role_kpis
  local qa_test_counts_json
  local qa_last_audit_json
  local stage3_velocity_json
  local EMPTY_OBJ='{}'

  role="$(role_for "$agent")"
  website="$(website_for "$agent")"
  module="$(module_for "$agent")"

  role_kpis="$(role_kpis_json "$role")"
  qa_test_counts_json="{}"
  qa_last_audit_json="{}"
  stage3_velocity_json="{}"
  if [ "$role" = "tester" ] || [[ "$agent" == qa-* ]]; then
    qa_test_counts_json="$(qa_test_counts_json "$agent" "$website")"
    qa_last_audit_json="$(qa_last_audit_json "$agent")"
  fi
  stage3_velocity_json="$(stage3_velocity_for_agent_json "$agent")"

  inbox_count="$(agent_inbox_count "$agent")"
  next_inbox="$(agent_next_inbox "$agent")"
  next_inbox_roi="$(agent_inbox_item_roi "$agent" "$next_inbox")"
  next_inbox_effective_roi="$(agent_inbox_item_effective_roi "$agent" "$next_inbox")"
  inbox_items_json="$(agent_inbox_items_json "$agent")"
  inbox_items_detail_json="$(agent_inbox_items_detail_json "$agent")"
  outbox_results_json="$(agent_outbox_results_json "$agent")"
  exec="$(agent_exec_yes "$agent")"

  IFS=$'\t' read -r active_inbox active_inbox_started active_inbox_pid < <(agent_active_inbox_info "$agent" || echo $'\t\t')
  active_inbox="$(printf '%s' "${active_inbox:-}" | tr -d '\r' | xargs 2>/dev/null || true)"
  active_inbox_started="$(printf '%s' "${active_inbox_started:-}" | tr -d '\r' | xargs 2>/dev/null || true)"
  active_inbox_pid="$(printf '%s' "${active_inbox_pid:-}" | tr -d '\r' | tr -cd '0-9' || true)"
  active_inbox_started_local="$(format_started_local "$active_inbox_started")"
  active_inbox_elapsed="$(elapsed_from_started_iso "$active_inbox_started")"
  active_inbox_effective_roi="$(agent_inbox_item_effective_roi "$agent" "$active_inbox")"
  [[ "$active_inbox_effective_roi" =~ ^[0-9]+$ ]] || active_inbox_effective_roi="$next_inbox_effective_roi"

  if [ "${DEBUG_AGENT:-}" = "$agent" ]; then
    echo "DEBUG agent=${agent} outbox_results_json_bytes=$(printf '%s' "${outbox_results_json:-}" | wc -c | awk '{print $1}')" >&2
  fi

  if [ "$(./scripts/is-agent-paused.sh "$agent" 2>/dev/null || echo false)" = "true" ]; then
    status="paused"
  elif [ -n "${active_inbox:-}" ]; then
    status="in_progress"
  elif [ "$exec" = "yes" ] && [ "$inbox_count" -gt 0 ]; then
    status="in_progress"
  else
    status="idle"
  fi

  if [ -n "$active_inbox" ]; then
    action="inbox:${active_inbox} (ROI ${active_inbox_effective_roi}"
    if [ -n "$active_inbox_started_local" ]; then
      action+="; Start ${active_inbox_started_local}"
    fi
    if [ -n "$active_inbox_elapsed" ]; then
      action+="; Spent ${active_inbox_elapsed}"
    fi
    action+=")"
  elif [ "$inbox_count" -gt 0 ] && [ -n "$next_inbox" ]; then
    action="inbox:${next_inbox} (ROI ${next_inbox_effective_roi}"
    if [ "$exec" = "yes" ]; then
      action+="; Exec recent"
    fi
    action+=")"
  else
    action="idle"
  fi

  summary="HQ->Drupal sync: ${status} (${action})"

  priorities_json="$(org_priorities_json)"

  extra_ceo_messages="[]"
  release_notes_json="[]"
  configured_seats_json="[]"
  case "$agent" in
    ceo-copilot|ceo-copilot-*)
      extra_ceo_messages="$(ceo_inbox_messages_json)"
      release_notes_json="$(ceo_release_notes_json)"
      configured_seats_json="$(python3 - <<'PY'
import json
import re
from pathlib import Path

p = Path('org-chart/agents/agents.yaml')
ids = []
if p.exists():
  for ln in p.read_text(encoding='utf-8', errors='ignore').splitlines():
    m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
    if m:
      ids.append(m.group(1))
print(json.dumps(ids))
PY
)"
      ;;
  esac

  tmpdir="$(mktemp -d)"
  # shellcheck disable=SC2064
  trap "rm -rf '$tmpdir'" RETURN

  printf '%s' "${inbox_items_json:-[]}" >"$tmpdir/inbox_items.json"
  printf '%s' "${inbox_items_detail_json:-[]}" >"$tmpdir/inbox_items_detail.json"
  printf '%s' "${outbox_results_json:-$EMPTY_OBJ}" >"$tmpdir/outbox_results.json"
  printf '%s' "${role_kpis:-$EMPTY_OBJ}" >"$tmpdir/role_kpis.json"
  printf '%s' "${extra_ceo_messages:-[]}" >"$tmpdir/inbox_messages.json"
  printf '%s' "${priorities_json:-[]}" >"$tmpdir/org_priorities.json"
  printf '%s' "${configured_seats_json:-[]}" >"$tmpdir/configured_seats.json"
  printf '%s' "${release_notes_json:-[]}" >"$tmpdir/release_notes.json"
  printf '%s' "${qa_test_counts_json:-$EMPTY_OBJ}" >"$tmpdir/qa_test_counts.json"
  printf '%s' "${qa_last_audit_json:-$EMPTY_OBJ}" >"$tmpdir/qa_last_audit.json"
  printf '%s' "${stage3_velocity_json:-$EMPTY_OBJ}" >"$tmpdir/stage3_velocity.json"

  if [ "${DEBUG_AGENT:-}" = "$agent" ]; then
    cp -f "$tmpdir/outbox_results.json" "/tmp/copilot_agent_tracker.${agent}.outbox_results.json" 2>/dev/null || true
  fi

  if [ "${DEBUG_AGENT:-}" = "$agent" ]; then
    if python3 -c "import json; import pathlib; json.loads((pathlib.Path('$tmpdir/outbox_results.json').read_text(encoding='utf-8', errors='ignore') or '').strip() or '{}'); print('DEBUG outbox_results.json OK')" 2>/dev/null; then
      :
    else
      echo "DEBUG agent=${agent} outbox_results.json_parse=FAIL" >&2
      echo "DEBUG agent=${agent} outbox_results.json_head=$(head -c 200 "$tmpdir/outbox_results.json" | tr '\n' ' ' | tr '\r' ' ' )" >&2
    fi
  fi

  json=$(python3 - "$agent" "$role" "$website" "$module" "$action" "$status" "$summary" "$inbox_count" "$exec" "$next_inbox" "$next_inbox_roi" "$next_inbox_effective_roi" \
    "$active_inbox" "$active_inbox_started" "$active_inbox_pid" \
    "$tmpdir/inbox_items.json" "$tmpdir/inbox_items_detail.json" "$tmpdir/outbox_results.json" "$tmpdir/role_kpis.json" "$tmpdir/inbox_messages.json" "$tmpdir/org_priorities.json" "$tmpdir/configured_seats.json" "$tmpdir/release_notes.json" "$tmpdir/qa_test_counts.json" "$tmpdir/qa_last_audit.json" "$tmpdir/stage3_velocity.json" <<'PY'
import json
import sys
from pathlib import Path

(agent, role, website, module, action, status, summary, inbox_count, exec_flag, next_inbox,
 next_inbox_roi, next_inbox_effective_roi, active_inbox, active_inbox_started, active_inbox_pid,
 inbox_items_path, inbox_items_detail_path, outbox_results_path, role_kpis_path,
 inbox_messages_path, org_priorities_path, configured_seats_path, release_notes_path, qa_test_counts_path, qa_last_audit_path, stage3_velocity_path) = sys.argv[1:27]

def load_json(path: str, default):
  try:
    txt = Path(path).read_text(encoding='utf-8', errors='ignore')
  except Exception:
    return default
  txt = (txt or '').strip()
  if not txt:
    return default
  try:
    return json.loads(txt)
  except Exception:
    return default

payload = {
  "agent_id": agent,
  "session_id": None,
  "work_item_id": None,
  "role": role or None,
  # Use empty strings (not NULL) so Drupal merge() overwrites prior hardcoded values.
  "website": website if website != "" else "",
  "module": module if module != "" else "",
  "action": action or None,
  "status": status or None,
  "summary": summary,
  "details": None,
  "metadata": json.dumps({
    "source": "copilot-sessions-hq",
    "org_priorities": load_json(org_priorities_path, []),
    "configured_seats": load_json(configured_seats_path, []),
    "release_notes": load_json(release_notes_path, []),
    "inbox_count": int(inbox_count),
    "next_inbox": next_inbox,
    "active_inbox": active_inbox,
    "active_inbox_started": active_inbox_started,
    "active_inbox_pid": int(active_inbox_pid) if str(active_inbox_pid).isdigit() and int(active_inbox_pid) > 0 else None,
    "next_inbox_roi": int(next_inbox_roi) if str(next_inbox_roi).isdigit() and int(next_inbox_roi) >= 1 else 1,
    "next_inbox_effective_roi": int(next_inbox_effective_roi) if str(next_inbox_effective_roi).isdigit() and int(next_inbox_effective_roi) >= 1 else (int(next_inbox_roi) if str(next_inbox_roi).isdigit() and int(next_inbox_roi) >= 1 else 1),
    "inbox_items": load_json(inbox_items_path, []),
    "inbox_items_detail": load_json(inbox_items_detail_path, []),
    "outbox_results": load_json(outbox_results_path, {}),
    "role_kpis": load_json(role_kpis_path, {}),
    "qa_test_counts": load_json(qa_test_counts_path, {}),
    "qa_last_audit": load_json(qa_last_audit_path, {}),
    "stage3_velocity": load_json(stage3_velocity_path, {}),
    "inbox_messages": load_json(inbox_messages_path, []),
    "exec": exec_flag,
  }),
}
print(json.dumps(payload))
PY
)

  payload_file="$tmpdir/payload.json"
  printf '%s' "$json" >"$payload_file"

  (cd "$FORSITI_SITE_DIR" && "$DRUSH_BIN" -q php:eval \
    "\$payload = json_decode(file_get_contents('$payload_file'), TRUE); \$storage = \\Drupal::service('copilot_agent_tracker.storage'); \$storage->recordEvent(\$payload);"
  )
}

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

count=0

if [ "$#" -gt 0 ]; then
  for agent in "$@"; do
    [ -n "$agent" ] || continue
    publish_one "$agent"
    count=$((count+1))
  done
else
  while IFS= read -r agent; do
    publish_one "$agent"
    count=$((count+1))
  done < <(configured_agent_ids)
fi

echo "Published ${count} agent(s) to Forseti copilot_agent_tracker."
