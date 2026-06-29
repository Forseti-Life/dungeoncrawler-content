#!/usr/bin/env bash
set -euo pipefail

# Create role-appropriate work items for *configured* agents when they are idle.
# Continuous mode: when an agent has no non-idle items, keep up to N idle items queued.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

echo "Idle work generation policy (Board directive 2026-02-22):"
echo "- Idle work generation is disabled for all seats (including explorers)."
echo "- No inbox items will be created by scripts/idle-work-generator.sh."
exit 0

DATE_YYYYMMDD="${1:-$(date +%Y%m%d)}"

# New directive (2026-02-22): do NOT generate new idle requests when an agent already
# has 3 items in their inbox queue.
IDLE_QUEUE_LIMIT="${IDLE_QUEUE_LIMIT:-3}"

configured_agents_tsv() {
  python3 - <<'PY'
import ast
import re
from pathlib import Path

p = Path('org-chart/agents/agents.yaml')
if not p.exists():
  raise SystemExit(0)

agents = []
cur = None

def push():
  global cur
  if cur and cur.get('id'):
    agents.append(cur)
  cur = None

for ln in p.read_text(encoding='utf-8', errors='ignore').splitlines():
  m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
  if m:
    push()
    cur = {
      'id': m.group(1).strip(),
      'role': '',
      'paused': False,
      'supervisor': '',
      'website': '',
      'modules': [],
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
    cur['paused'] = m.group(1).strip().lower() in ('true', 'yes', '1', 'on')
    continue

  m = re.match(r'^\s*website_scope:\s*(.+)\s*$', ln)
  if m and not cur.get('website'):
    try:
      arr = ast.literal_eval(m.group(1).strip())
      if isinstance(arr, list) and arr:
        cur['website'] = str(arr[0])
    except Exception:
      pass
    continue

  m = re.match(r'^\s*module_ownership:\s*(.+)\s*$', ln)
  if m and not cur.get('modules'):
    try:
      arr = ast.literal_eval(m.group(1).strip())
      if isinstance(arr, list) and arr:
        cur['modules'] = [str(x) for x in arr if str(x).strip()]
    except Exception:
      pass
    continue

push()

by_id = {a.get('id'): a for a in agents if a.get('id')}

def first_module_for(a: dict) -> str:
  mods = a.get('modules') or []
  return str(mods[0]) if mods else ''

# Inherit module ownership down the supervisor chain (Dev/QA/BA often omit it).
for a in agents:
  if first_module_for(a):
    continue
  sup = (a.get('supervisor') or '').strip()
  seen = set()
  inherited = ''
  while sup and sup not in seen:
    seen.add(sup)
    sup_a = by_id.get(sup)
    if not sup_a:
      break
    inherited = first_module_for(sup_a)
    if inherited:
      break
    sup = (sup_a.get('supervisor') or '').strip()
  if inherited:
    a['modules'] = [inherited]

for a in agents:
  paused = 'true' if a.get('paused') else 'false'
  module = first_module_for(a)
  print(f"{a.get('id','')}\t{a.get('role','')}\t{paused}\t{a.get('website','')}\t{module}")
PY
}

inbox_count() {
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  [ -d "$dir" ] || { echo 0; return; }
  find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | wc -l | awk '{print $1}'
}

create_item() {
  local agent="$1" item_id="$2" command_body="$3"
  local dir="sessions/${agent}/inbox/${item_id}"
  local out="sessions/${agent}/outbox/${item_id}.md"

  # If the item was already processed (outbox exists), don't recreate it.
  if [ -f "$out" ]; then
    return 0
  fi
  if [ -d "$dir" ]; then
    return 0
  fi
  mkdir -p "$dir"
  {
    echo "- command: |"
    printf '%s\n' "$command_body" | sed 's/^/    /'
  } > "$dir/command.md"

  # Default ROI marker (agents may adjust). Used for queue ordering.
  if [ ! -f "$dir/roi.txt" ]; then
    printf '1\n' > "$dir/roi.txt"
  fi
}

# Return an item id that doesn't already exist as inbox folder or outbox md.
# Caps at 20 items per base id per day to avoid infinite queue growth.
unique_item_id() {
  local agent="$1" base="$2"
  local inbox_base="sessions/${agent}/inbox"
  local outbox_base="sessions/${agent}/outbox"

  local cand="$base"
  if [ ! -d "${inbox_base}/${cand}" ] && [ ! -f "${outbox_base}/${cand}.md" ]; then
    echo "$cand"; return
  fi
  for i in $(seq 2 20); do # lint-ok: seq produces integers, no word-split risk
    cand="${base}-${i}"
    if [ ! -d "${inbox_base}/${cand}" ] && [ ! -f "${outbox_base}/${cand}.md" ]; then
      echo "$cand"; return
    fi
  done
  echo ""
}

inbox_has_non_idle_items() {
  local agent="$1"
  local dir="sessions/${agent}/inbox"
  dir="$(readlink -f "$dir" 2>/dev/null || echo "$dir")"
  [ -d "$dir" ] || return 1
  # Non-idle items are anything not containing '-idle-'.
  find "$dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | sed 's|.*/||' \
    | grep -qv -- '-idle-' && return 0
  return 1
}

top_up_to_three() {
  local agent="$1" base_id="$2" body="$3"
  local c
  c="$(inbox_count "$agent")"
  while [ "$c" -lt "$IDLE_QUEUE_LIMIT" ]; do
    local iid
    iid="$(unique_item_id "$agent" "$base_id")"
    [ -n "$iid" ] || return 0
    create_item "$agent" "$iid" "$body" || true
    c="$(inbox_count "$agent")"
  done
}

refactor_review_fallback() {
  cat <<'TXT'

Fallback (required if no meaningful work is possible):
- If you are blocked by missing repo access / missing URL / unclear scope, do NOT stall.
- Instead, pick 1 file within your owned scope (prefer HQ repo) and do a refactor/code-review pass:
  - identify 1–3 concrete improvements (readability, duplication, error handling, naming, test gaps)
  - propose the smallest viable diff OR a precise recommendation to the owning seat
  - include verification steps (if applicable)
TXT
}

queue_followups_instructions() {
  cat <<'TXT'

Queue follow-up work (required):
- Create 1–3 concrete follow-up inbox items for implementation/verification.
- Put them in the correct owner’s inbox (your own seat if you own implementation; otherwise your Dev/QA/PM seat per reporting chain).
- Each follow-up item must include:
  - a clear title/topic
  - exact file path(s)
  - a minimal diff description (or patch snippet)
  - verification steps
  - `roi.txt` with an ROI estimate

Queue discipline:
- Do NOT exceed the idle queue cap (this script will not add more idle items if your queue is already full).
- Prefer fewer, higher-ROI follow-ups over many low-signal items.
TXT
}

review_target_context() {
  local website="$1" module="$2"
  local mdir pick
  mdir="$(module_dir_for "$module")"
  pick="$(random_file_in_dir "$mdir")"
  cat <<TXT
Review target (best-effort suggestions):
- Website scope: ${website}
- Module ownership: ${module}
- Suggested module dir: ${mdir:-"(not found / inaccessible)"}
- Suggested random file: ${pick:-"(not found / inaccessible)"}
TXT
}

slugify() {
  printf '%s' "$1" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-60
}

# Best-effort: locate a module directory inside fors eti repo.
module_dir_for() {
  local module="$1"
  [ -n "$module" ] || { echo ""; return; }

  # Common Drupal custom module locations (fast checks)
  local candidates=(
    "/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/${module}"
    "/home/ubuntu/forseti.life/web/modules/custom/${module}"
    "/home/ubuntu/forseti.life/sites/forseti/modules/custom/${module}"
    "/home/ubuntu/forseti.life/modules/custom/${module}"
  )
  for c in "${candidates[@]}"; do
    if [ -d "$c" ]; then
      echo "$c"; return
    fi
  done

  # Fallback: search by directory name (bounded)
  python3 - "$module" <<'PY'
import os, sys
module = sys.argv[1]
roots = ["/home/ubuntu/forseti.life"]
for root in roots:
    if not os.path.isdir(root):
        continue
    for base, dirs, _files in os.walk(root):
        # prune deep vendor/node_modules for speed
        bn = os.path.basename(base)
        if bn in ("vendor","node_modules",".git","sites-default","libraries"):
            dirs[:] = []
            continue
        if os.path.basename(base) == module:
            print(base)
            raise SystemExit(0)
        if base.count(os.sep) - root.count(os.sep) > 8:
            dirs[:] = []
PY
}

random_file_in_dir() {
  local dir="$1"
  [ -d "$dir" ] || { echo ""; return; }
  python3 - "$dir" <<'PY'
import os, random, sys
root = sys.argv[1]
allow_ext = {".php", ".yml", ".yaml", ".js", ".ts", ".twig", ".md"}
paths = []
for base, dirs, files in os.walk(root):
    bn = os.path.basename(base)
    if bn in ("vendor","node_modules",".git"):
        dirs[:] = []
        continue
    for f in files:
        ext = os.path.splitext(f)[1].lower()
        if ext in allow_ext:
            paths.append(os.path.join(base, f))
if not paths:
    raise SystemExit(0)
print(random.choice(paths))
PY
}

created=0
while IFS=$'\t' read -r agent_id role paused website module; do
  [ -n "$agent_id" ] || continue
  if [ "$paused" = "true" ]; then
    continue
  fi

  # Board policy: only exploration agents are allowed to self-generate idle work.
  if [[ "$agent_id" != agent-explore* ]]; then
    continue
  fi

  # If there is any non-idle work queued, do not inject idle items.
  if inbox_has_non_idle_items "$agent_id"; then
    continue
  fi

  # If we already have 3+ idle items queued, do nothing.
  if [ "$(inbox_count "$agent_id")" -ge "$IDLE_QUEUE_LIMIT" ]; then
    continue
  fi

  # Map exploring seats to the exploration template.
  role="explore-user"

  case "$role" in
    security-analyst)
      base_id="${DATE_YYYYMMDD}-idle-refactor-review-$(slugify "${website:-product}")"
      body=$(cat <<TXT
Security analyst idle cycle (refactor/file review + queue work):

Goal: proactively improve security posture by reviewing code/config/docs for high-leverage hardening.

Pick 1 file within your owned scope that you have not recently reviewed/refactored.

$(review_target_context "${website}" "${module}")

Hard constraints:
- Do NOT modify code directly unless explicitly delegated.
- Do NOT provide weaponized exploit payloads or step-by-step exploitation.

Output in outbox:
- What file you reviewed
- 3–10 concrete findings (security, robustness, access checks, sensitive data handling)
- Suggested minimal diff / patch direction
- Verification steps

ROI discipline:
- Include: ## ROI estimate (ROI 1–infinity) + rationale

$(queue_followups_instructions)
TXT
)
  before="$(inbox_count "$agent_id")"
  top_up_to_three "$agent_id" "$base_id" "$body"
  after="$(inbox_count "$agent_id")"
  created=$((created + (after-before)))
      ;;

    explore-user)
      base_id="${DATE_YYYYMMDD}-idle-explore-playwright-$(slugify "${website:-product}")"
      body=$(cat <<TXT
  Explore idle cycle (Playwright UX exploration + file issues with PM):

  Goal:
  - Use Playwright to navigate real user workflows and surface UX defects, confusing copy, broken flows, and missing guidance.
  - File issues with your PM supervisor (not as self-assigned work items).

  Method (required):
  1) Run a short Playwright-driven exploration pass:
    - Focus on the most common/high-ROI workflows.
    - Prefer actions that cross pages/forms (higher chance of edge cases).
    - Capture evidence: trace/video/screenshot + console errors if any.
  2) Write findings in your outbox with:
    - URL(s)
    - steps to reproduce
    - expected vs actual
    - severity/impact
  3) Open issues with the PM supervisor:
    - Create a needs-* inbox item in the PM seat inbox (your reporting chain supervisor)
    - Include evidence + reproduction steps + a suggested fix direction
    - Include roi.txt (integer >= 1)

  Context:
  $(review_target_context "${website}" "${module}")

  Hard constraints:
  - Do NOT modify code.
  - Do NOT update documentation.
TXT
)
  before="$(inbox_count "$agent_id")"
  top_up_to_three "$agent_id" "$base_id" "$body"
  after="$(inbox_count "$agent_id")"
  created=$((created + (after-before)))
      ;;

    ceo)
      base_id="${DATE_YYYYMMDD}-idle-refactor-review-hq"
      body=$(cat <<'TXT'
CEO idle cycle (refactor/file review + queue work):

Directive: idle tasks are ALWAYS file review for refactoring and concrete improvements, then queue follow-up work.

Pick 1 HQ file that has not had a recent refactor/review (bias toward scripts/runbooks that cause recurring escalations).

Output:
- What file you reviewed
- 3–10 concrete improvements with a minimal diff direction
- Which seat should implement each improvement (owner)
- Verification steps
- ROI estimates per follow-up item

Queue follow-up work (required):
- Create 1–3 follow-up inbox items with roi.txt in the correct owner seat inbox.
TXT
)
  before="$(inbox_count "$agent_id")"
  top_up_to_three "$agent_id" "$base_id" "$body"
  after="$(inbox_count "$agent_id")"
  created=$((created + (after-before)))
      ;;

    product-manager)
      base_id="${DATE_YYYYMMDD}-idle-refactor-review-$(slugify "${website:-product}")"
      if [ "${website:-}" = "infrastructure" ] || [[ "$agent_id" == *-infra ]]; then
        body=$(cat <<TXT
    PM idle cycle (infrastructure refactor/file review + queue work):

    Pick 1 infrastructure-scope file that has not had a recent refactor/review.

    Suggested HQ targets: `scripts/**`, `runbooks/**`, `dashboards/**`.

    Output (in outbox):
    - What file you reviewed
    - 3–10 concrete improvements (operational clarity, acceptance criteria, safety checks, failure modes)
    - Suggested minimal diff direction
    - Delegation plan (BA/Dev/QA/Sec) for 1–3 follow-ups

    ROI discipline:
    - Include: ## ROI estimate (ROI 1–infinity) + rationale

    $(queue_followups_instructions)
TXT
)
      else
        body=$(cat <<TXT
    PM idle cycle (refactor/file review + queue work):

    Pick 1 file in your product scope that has not had a recent refactor/review.

    $(review_target_context "${website}" "${module}")

    Output (in outbox):
    - What file you reviewed
    - 3–10 concrete improvements (requirements clarity, acceptance criteria, edge cases, risk notes)
    - Suggested minimal diff direction
    - Delegation plan (BA/Dev/QA) for 1–3 follow-ups

    ROI discipline:
    - Include: ## ROI estimate (ROI 1–infinity) + rationale

    $(queue_followups_instructions)
TXT
)
      fi
  before="$(inbox_count "$agent_id")"
  top_up_to_three "$agent_id" "$base_id" "$body"
  after="$(inbox_count "$agent_id")"
  created=$((created + (after-before)))
      ;;

    software-developer)
      topic="refactor-review"
      if [ "${website:-}" = "infrastructure" ] || [[ "$agent_id" == *-infra ]]; then
        base_id="${DATE_YYYYMMDD}-idle-refactor-review-infra"
        body=$(cat <<TXT
    Dev idle cycle (infrastructure refactor/file review + queue work):

    Pick 1 infra-scope file that has not had a recent refactor/review.

    Suggested HQ targets: `scripts/**` (especially loops/watchdogs), `runbooks/**`.

    Output (in outbox):
    - What file you reviewed
    - 3–10 concrete improvements + minimal diff direction
    - Which improvements you will implement vs which you will delegate
    - Verification steps

    ROI discipline:
    - Include: ## ROI estimate (ROI 1–infinity) + rationale

    $(queue_followups_instructions)
TXT
)
      else
        slug="$(slugify "${module:-dev}")"
        base_id="${DATE_YYYYMMDD}-idle-${topic}-${slug}"
        body=$(cat <<TXT
    Dev idle cycle (refactor/file review + queue work):

    Pick 1 file in your module scope that has not had a recent refactor/review.

    $(review_target_context "${website}" "${module}")

    Instructions:
    1) Review the file and list 3–10 improvements (naming, duplication, error handling, testability).
    2) Do NOT implement large refactors inside the idle item; instead, queue 1–3 follow-up work items.
    3) If one change is truly tiny and safe, you may implement it, but still queue any remaining work.

    Output (in outbox):
    - What file you reviewed
    - Findings + proposed changes
    - For each follow-up: owner seat, file path(s), acceptance/verification steps, ROI

    $(queue_followups_instructions)
TXT
)
      fi
  before="$(inbox_count "$agent_id")"
  top_up_to_three "$agent_id" "$base_id" "$body"
  after="$(inbox_count "$agent_id")"
  created=$((created + (after-before)))
      ;;

    tester)
      base_id="${DATE_YYYYMMDD}-idle-refactor-review-$(slugify "${website:-qa}")"
      if [ "${website:-}" = "infrastructure" ] || [[ "$agent_id" == *-infra ]]; then
        body=$(cat <<TXT
QA idle cycle (infrastructure refactor/file review + queue work):

Pick 1 infra-scope file (runbook/script/template) that has not had a recent refactor/review.

Output (in outbox):
- What file you reviewed
- 3–10 concrete verification/testability improvements (missing checks, unclear AC, missing evidence)
- Suggested minimal diff direction
- Follow-up tasks to queue (who should implement vs who should verify)

ROI discipline:
- Include: ## ROI estimate (ROI 1–infinity) + rationale

$(queue_followups_instructions)
TXT
)
      else
        body=$(cat <<TXT
QA idle cycle (refactor/file review + queue work):

Pick 1 file in your product scope that has not had a recent refactor/review.

$(review_target_context "${website}" "${module}")

Output (in outbox):
- What file you reviewed
- 3–10 improvements to testability/verification clarity (missing AC, missing negative tests, fragile assumptions)
- Suggested minimal diff direction

ROI discipline:
- Include: ## ROI estimate (ROI 1–infinity) + rationale

$(queue_followups_instructions)
TXT
)
  fi
  before="$(inbox_count "$agent_id")"
  top_up_to_three "$agent_id" "$base_id" "$body"
  after="$(inbox_count "$agent_id")"
  created=$((created + (after-before)))
      ;;

    business-analyst)
      base_id="${DATE_YYYYMMDD}-idle-refactor-review-$(slugify "${website:-ba}")"
      if [ "${website:-}" = "infrastructure" ] || [[ "$agent_id" == *-infra ]]; then
        body=$(cat <<TXT
BA idle cycle (infrastructure refactor/file review + queue work):

Pick 1 infra-scope doc/runbook/script that has not had a recent refactor/review.

Output (in outbox):
- What file you reviewed
- 3–10 clarity improvements (acceptance criteria, unambiguous steps, missing definitions)
- Suggested minimal diff direction
- Follow-up tasks to queue (PM/Dev/QA)

ROI discipline:
- Include: ## ROI estimate (ROI 1–infinity) + rationale

$(queue_followups_instructions)
TXT
)
      else
        body=$(cat <<TXT
BA idle cycle (refactor/file review + queue work):

Pick 1 file in your product scope that has not had a recent refactor/review.

$(review_target_context "${website}" "${module}")

Output (in outbox):
- What file you reviewed
- 3–10 improvements to requirements clarity (AC gaps, undefined terms, missing edge cases)
- Suggested minimal diff direction

ROI discipline:
- Include: ## ROI estimate (ROI 1–infinity) + rationale

$(queue_followups_instructions)
TXT
)
  fi
  before="$(inbox_count "$agent_id")"
  top_up_to_three "$agent_id" "$base_id" "$body"
  after="$(inbox_count "$agent_id")"
  created=$((created + (after-before)))
      ;;

    *)
      # For specialized/internal agents, do nothing by default.
      ;;
  esac

done < <(configured_agents_tsv)

echo "Idle work items created: ${created}"
