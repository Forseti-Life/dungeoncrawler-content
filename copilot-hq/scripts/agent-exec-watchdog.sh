#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/inbox/responses"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/agent-exec-watchdog.log"

ts="$(date -Iseconds)"

log() {
  printf '[%s] %s\n' "$ts" "$*" | tee -a "$LOG_FILE" >/dev/null
}

if [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" != "true" ]; then
  log "org disabled; skipping restart"
  exit 0
fi

notify_scoped_pms() {
  local reason="$1"
  local item_id="$(date +%Y%m%d)-agent-exec-loop-restarted"

  python3 - "$reason" "$item_id" <<'PY'
import os, re, ast, sys
from pathlib import Path

reason = sys.argv[1]
item_id = sys.argv[2]

agents_path = Path('org-chart/agents/agents.yaml')
if not agents_path.exists():
  raise SystemExit(0)

agents = []
cur = None
for ln in agents_path.read_text(encoding='utf-8', errors='ignore').splitlines():
  m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
  if m:
    if cur:
      agents.append(cur)
    cur = {
      'id': m.group(1).strip(),
      'role': '',
      'supervisor': '',
      'paused': False,
      'website': '',
    }
    continue
  if not cur:
    continue

  m = re.match(r'^\s*role:\s*(\S+)\s*$', ln)
  if m:
    cur['role'] = m.group(1).strip()
    continue

  m = re.match(r'^\s*supervisor:\s*(\S+)\s*$', ln)
  if m:
    cur['supervisor'] = m.group(1).strip()
    continue

  m = re.match(r'^\s*paused:\s*(\S+)\s*$', ln)
  if m:
    cur['paused'] = m.group(1).strip().lower() in ('true','yes','1','on')
    continue

  m = re.match(r'^\s*website_scope:\s*(.+)\s*$', ln)
  if m and not cur.get('website'):
    try:
      arr = ast.literal_eval(m.group(1).strip())
      if isinstance(arr, list) and arr:
        cur['website'] = str(arr[0])
    except Exception:
      pass

if cur:
  agents.append(cur)

by_id = {a['id']: a for a in agents if a.get('id')}

def first_pm_in_chain(agent_id: str) -> str | None:
  seen = set()
  cur_id = agent_id
  while cur_id and cur_id not in seen:
    seen.add(cur_id)
    a = by_id.get(cur_id)
    if not a:
      return None
    if a.get('role') == 'product-manager':
      return a.get('id')
    cur_id = (a.get('supervisor') or '').strip()
  return None

# Build PM -> scoped agents (by supervisor chain reaching that PM).
pm_to_agents: dict[str, list[str]] = {}
for a in agents:
  if a.get('paused'):
    continue
  aid = a.get('id')
  if not aid:
    continue
  pm = first_pm_in_chain(aid)
  if pm:
    pm_to_agents.setdefault(pm, []).append(aid)

# If a PM seat itself is down, notifying itself is not useful; notify its supervisor instead.
def supervisor_of(agent_id: str) -> str | None:
  a = by_id.get(agent_id)
  if not a:
    return None
  sup = (a.get('supervisor') or '').strip()
  return sup or None

def ensure_inbox_item(target_agent: str, body: str) -> None:
  inbox_dir = Path('sessions') / target_agent / 'inbox' / item_id
  outbox_file = Path('sessions') / target_agent / 'outbox' / f"{item_id}.md"

  # Idempotent: if already processed or already created, do nothing.
  if outbox_file.exists() or inbox_dir.exists():
    return

  inbox_dir.mkdir(parents=True, exist_ok=True)
  cmd_path = inbox_dir / 'command.md'
  cmd_path.write_text("- command: |\n" + "\n".join(["    " + ln for ln in body.splitlines()]) + "\n", encoding='utf-8')

for pm, scoped_agents in sorted(pm_to_agents.items()):
  scoped_agents = sorted(set(scoped_agents))
  notify_target = pm
  if by_id.get(pm, {}).get('role') == 'product-manager':
    # PM is the intended recipient; if PM seat itself is down, route to its supervisor.
    # (We can't reliably detect the PM seat status; so always also allow routing to supervisor in content.)
    pass

  body = """Agent execution loop watchdog fired.

Reason: {reason}

What happened:
- The continuous agent execution loop was detected as NOT running.
- The watchdog restarted it.

Your scoped seats affected:
{agents}

Next actions:
- Check logs: inbox/responses/agent-exec-latest.log and inbox/responses/agent-exec-YYYYMMDD.log
- If repeated restarts occur, escalate to CEO with timestamps and suspected cause.
""".format(
    reason=reason,
    agents="\n".join([f"- {x}" for x in scoped_agents]) or "- (none)",
  ).rstrip() + "\n"

  ensure_inbox_item(notify_target, body)

  # Also notify the PM's supervisor for visibility when the PM seat might be the one impacted.
  sup = supervisor_of(pm)
  if sup and sup != pm:
    ensure_inbox_item(sup, f"FYI: {pm} notified about agent exec loop restart.\n\n" + body)
PY
}

if ./scripts/agent-exec-loop.sh verify >/dev/null 2>&1; then
  exit 0
fi

log "agent exec loop down; attempting restart"

# Attempt restart (best-effort; do not spam multiple loops).
./scripts/agent-exec-loop.sh start 60 >/dev/null 2>&1 || true
sleep 0.2

if ./scripts/agent-exec-loop.sh verify >/dev/null 2>&1; then
  log "restart succeeded"
  notify_scoped_pms "watchdog restart at $ts"
  exit 0
fi

log "restart FAILED"
notify_scoped_pms "watchdog restart FAILED at $ts (manual intervention required)"
exit 1
