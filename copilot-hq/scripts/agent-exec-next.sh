#!/usr/bin/env bash
set -euo pipefail

# Consume exactly one inbox item for a given agent by generating an outbox update
# using the local Copilot CLI (separate session id per agent).

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
# shellcheck source=lib/site-paths.sh
. "$ROOT_DIR/scripts/lib/site-paths.sh"

# shellcheck source=lib/agents.sh
source "./scripts/lib/agents.sh"

AGENT_ID="${1:?agent id required}"

# Per-agent execution lock: prevents concurrent runs for the same seat.
# (Protects against duplicate loop processes and manual invocations.)
AGENT_LOCKFILE="tmp/.agent-exec-next.${AGENT_ID}.lock"
mkdir -p "$(dirname "$AGENT_LOCKFILE")" 2>/dev/null || true
if command -v flock >/dev/null 2>&1; then
  exec 8>"$AGENT_LOCKFILE" || exit 1
  if ! flock -n 8 2>/dev/null; then
    echo "skip agent=${AGENT_ID} reason=locked lockfile=${AGENT_LOCKFILE}" >&2
    exit 0
  fi
fi

# Guardrail: only allow configured agent "seats".
if ! is_configured_agent "$AGENT_ID"; then
  echo "ERROR: unknown agent id (not in org-chart/agents/agents.yaml): ${AGENT_ID}" >&2
  exit 3
fi

# Cron typically runs with a minimal PATH; resolve available GenAI backends.
export PATH="$HOME/.npm-global/bin:$HOME/.local/bin:/usr/local/bin:/usr/bin:/bin:${PATH:-}"
COPILOT_BIN="$(command -v copilot 2>/dev/null || true)"
if [ -z "$COPILOT_BIN" ] && [ -x "$HOME/.npm-global/bin/copilot" ]; then
  COPILOT_BIN="$HOME/.npm-global/bin/copilot"
fi
BEDROCK_ASSIST_SCRIPT="${BEDROCK_ASSIST_SCRIPT:-$ROOT_DIR/scripts/bedrock-assist.sh}"
AGENTIC_BACKEND="${HQ_AGENTIC_BACKEND:-auto}"

INBOX_DIR="sessions/${AGENT_ID}/inbox"
OUTBOX_DIR="sessions/${AGENT_ID}/outbox"
ART_DIR="sessions/${AGENT_ID}/artifacts"

mkdir -p "$INBOX_DIR" "$OUTBOX_DIR" "$ART_DIR"

# Allow inbox to be a symlink to a shared queue; resolve to the real directory.
INBOX_DIR="$(readlink -f "$INBOX_DIR" 2>/dev/null || echo "$INBOX_DIR")"

# Archive an inbox item folder to artifacts reliably (avoid failures on existing dest).
archive_inbox_item() {
  local src="$1" name="$2" dest_dir="$3"
  [ -d "$src" ] || return 0
  mkdir -p "$dest_dir"
  local dest="$dest_dir/$name"
  if [ -e "$dest" ]; then
    dest="${dest}-$(date +%s)"
  fi
  mv "$src" "$dest" 2>/dev/null || rm -rf "$src"
}

# Find next inbox folder (oldest by name) and acquire an execution lock.
# This allows multiple workers to share the same inbox directory safely.
LOCK_TTL_SECONDS="${AGENT_EXEC_LOCK_TTL_SECONDS:-3600}"
next=""
LOCK_DIR=""

# ROI safety + anti-starvation controls.
# Base ROI still comes from roi.txt (or roi-<n> in folder name).
# Anti-staleness policy:
# - Do NOT use time-based aging (too slow / inconsistent with work rate).
# - Instead, on each completed item, bump the ROI of *all other agents'* queued
#   inbox items by +1 (capped). This makes queued work "age" in proportion to
#   total throughput, preventing indefinite starvation.
ROI_MAX="${AGENT_EXEC_ROI_MAX:-10000}"

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

bump_other_agents_queued_roi() {
  local completing_agent="$1"
  local lock_file="tmp/.roi-completion-bump.lock"
  mkdir -p "$(dirname "$lock_file")" 2>/dev/null || true

  # Best-effort concurrency guard: don't block execution if another worker
  # is already applying the bump.
  if command -v flock >/dev/null 2>&1; then
    exec 9>"$lock_file" || return 0
    flock -n 9 2>/dev/null || return 0
  fi

  while IFS= read -r agent; do
    [ -n "$agent" ] || continue
    [ "$agent" = "$completing_agent" ] && continue

    if [ -x "./scripts/is-agent-paused.sh" ]; then
      if [ "$(./scripts/is-agent-paused.sh "$agent" 2>/dev/null || echo false)" = "true" ]; then
        continue
      fi
    fi

    local inbox_dir="sessions/${agent}/inbox"
    inbox_dir="$(readlink -f "$inbox_dir" 2>/dev/null || echo "$inbox_dir")"
    [ -d "$inbox_dir" ] || continue

    find "$inbox_dir" -mindepth 1 -maxdepth 1 -type d ! -name "_archived" -print0 2>/dev/null \
      | while IFS= read -r -d '' item_dir; do
          [ -d "$item_dir" ] || continue
          local roi_file="$item_dir/roi.txt"
          local roi="1"
          if [ -f "$roi_file" ]; then
            roi="$(head -n 1 "$roi_file" 2>/dev/null | tr -d '\r' | tr -cd '0-9' || echo 0)"
          fi
          [[ "$roi" =~ ^[0-9]+$ ]] || roi=1
          [ "$roi" -ge 1 ] 2>/dev/null || roi=1
          roi=$((roi + 1))
          if [[ "$ROI_MAX" =~ ^[0-9]+$ ]] && [ "$ROI_MAX" -ge 1 ] 2>/dev/null && [ "$roi" -gt "$ROI_MAX" ] 2>/dev/null; then
            roi="$ROI_MAX"
          fi
          printf '%s\n' "$roi" > "${roi_file}.tmp" 2>/dev/null && mv "${roi_file}.tmp" "$roi_file" 2>/dev/null || true
        done
  done < <(configured_agent_ids)
}

# Organizational priority weighting (shared helper).
if [ -f "$ROOT_DIR/scripts/lib/org-priority.sh" ]; then
  # shellcheck source=/dev/null
  . "$ROOT_DIR/scripts/lib/org-priority.sh"
fi

roi_for_item_dir() {
  local item_dir="$1" item_name="$2"
  local roi_file="$item_dir/roi.txt"
  local roi="1"

  if [ -f "$roi_file" ]; then
    roi="$(head -n 1 "$roi_file" 2>/dev/null | tr -d '\r' | tr -cd '0-9' || echo 0)"
  else
    # Fallback: parse ROI from name like roi-500-foo.
    if [[ "$item_name" =~ (^|-)roi-([0-9]{1,9})(-|$) ]]; then
      roi="${BASH_REMATCH[2]}"
    fi
  fi
  [[ "$roi" =~ ^[0-9]+$ ]] || roi=1
  [ "$roi" -ge 1 ] 2>/dev/null || roi=1

  # Cap base ROI to limit priority stuffing if roi.txt is tampered.
  if [[ "$ROI_MAX" =~ ^[0-9]+$ ]] && [ "$ROI_MAX" -ge 1 ] 2>/dev/null; then
    if [ "$roi" -gt "$ROI_MAX" ] 2>/dev/null; then
      roi="$ROI_MAX"
    fi
  fi

  # Organizational priority bonus.
  local org_bonus
  org_bonus="$(org_priority_bonus_for_item "$item_dir" "$item_name" "$roi")"
  [[ "$org_bonus" =~ ^[0-9]+$ ]] || org_bonus=0

  echo $((roi + org_bonus))
}

roi_sorted_candidates() {
  local inbox_dir="$1"
  find "$inbox_dir" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | sed 's|.*/||' \
    | while IFS= read -r name; do
        [ -n "$name" ] || continue
        [[ "$name" == _archived ]] && continue
        local dir="$inbox_dir/$name"
        local roi
        roi="$(roi_for_item_dir "$dir" "$name")"
        printf '%s\t%s\n' "$roi" "$name"
      done \
    | sort -t $'\t' -k1,1nr -k2,2
}

mapfile -t candidates < <(roi_sorted_candidates "$INBOX_DIR" | awk -F'\t' '{print $2}' || true)
for candidate in "${candidates[@]}"; do
  [ -n "$candidate" ] || continue
  inbox_item_candidate="$INBOX_DIR/$candidate"
  lock_dir="$inbox_item_candidate/.exec-lock"
  inwork_file="$inbox_item_candidate/.inwork"

  # If the companion exec-lock belongs to a dead PID, clear both markers
  # immediately instead of waiting for TTL-based stale detection.
  if [ -d "$lock_dir" ] && [ -f "$lock_dir/pid" ]; then
    lock_pid="$(head -n 1 "$lock_dir/pid" 2>/dev/null | tr -d '\r' | tr -cd '0-9' || true)"
    if [[ "$lock_pid" =~ ^[0-9]+$ ]] && [ -n "$lock_pid" ]; then
      if [ -z "$(ps -p "$lock_pid" -o args= 2>/dev/null || true)" ]; then
        rm -rf "$lock_dir" 2>/dev/null || true
        rm -f "$inwork_file" 2>/dev/null || true
      fi
    fi
  fi

  # Inwork marker: a simpler coordination layer for shared inboxes (e.g. all
  # three ceo-copilot threads share sessions/ceo-copilot/inbox/).
  # If another agent already claimed this item, skip it immediately.
  # Only clear the marker if it is clearly stale (TTL exceeded).
  if [ -f "$inwork_file" ]; then
    inwork_mtime="$(stat -c %Y "$inwork_file" 2>/dev/null || echo 0)"
    now_epoch_iw="$(date +%s)"
    if [ "$inwork_mtime" -gt 0 ] && [ $((now_epoch_iw - inwork_mtime)) -lt "$LOCK_TTL_SECONDS" ]; then
      continue  # another agent is actively working this item
    fi
    rm -f "$inwork_file" 2>/dev/null || true  # stale marker — clear and try to claim
  fi

  # If a stale exec-lock exists (e.g., previous executor crash), clear it.
  if [ -d "$lock_dir" ]; then
    lock_pid=""
    if [ -f "$lock_dir/pid" ]; then
      lock_pid="$(head -n 1 "$lock_dir/pid" 2>/dev/null | tr -d '\r' | tr -cd '0-9' || true)"
    fi
    # Only clear if the owning PID is definitively gone (no args at all).
    if [[ "$lock_pid" =~ ^[0-9]+$ ]] && [ -n "$lock_pid" ]; then
      if [ -z "$(ps -p "$lock_pid" -o args= 2>/dev/null || true)" ]; then
        rm -rf "$lock_dir" 2>/dev/null || true
      fi
    fi

    lock_mtime="$(stat -c %Y "$lock_dir" 2>/dev/null || echo 0)"
    now_epoch="$(date +%s)"
    if [ "$lock_mtime" -gt 0 ] && [ $((now_epoch - lock_mtime)) -gt "$LOCK_TTL_SECONDS" ]; then
      rm -rf "$lock_dir" 2>/dev/null || true
    fi
  fi

  if mkdir "$lock_dir" 2>/dev/null; then
    echo "$AGENT_ID" > "$lock_dir/owner" 2>/dev/null || true
    echo "$$" > "$lock_dir/pid" 2>/dev/null || true
    date -Iseconds > "$lock_dir/created" 2>/dev/null || true
    # Write inwork marker so other threads sharing this inbox skip this item.
    echo "$AGENT_ID" > "$inwork_file" 2>/dev/null || true
    INWORK_FILE="$inwork_file"
    next="$candidate"
    LOCK_DIR="$lock_dir"
    break
  fi
done

if [ -z "$next" ]; then
  exit 2
fi

SEMAPHORE_DIR="tmp/.agent-exec-semaphore"
SLOT_FD=""
SLOT_FILE=""

acquire_global_slot() {
  local max="$1"
  [[ "$max" =~ ^[0-9]+$ ]] || max=5
  [ "$max" -ge 1 ] 2>/dev/null || max=1

  # If flock is unavailable, skip the global concurrency guard (best-effort).
  if ! command -v flock >/dev/null 2>&1; then
    return 0
  fi

  mkdir -p "$SEMAPHORE_DIR" 2>/dev/null || true
  for i in $(seq 1 "$max" 2>/dev/null); do # lint-ok: seq produces integers
    local f="$SEMAPHORE_DIR/slot-${i}.lock"
    # Open a unique FD per attempt; if we win, keep it open to hold the lock.
    exec {fd}>"$f" || continue
    if flock -n "$fd" 2>/dev/null; then
      SLOT_FD="$fd"
      SLOT_FILE="$f"
      return 0
    fi
    # Release FD if lock not acquired.
    eval "exec ${fd}>&-" 2>/dev/null || true
  done
  return 1
}

release_global_slot() {
  if [ -n "${SLOT_FD:-}" ]; then
    eval "exec ${SLOT_FD}>&-" 2>/dev/null || true
  fi
  SLOT_FD=""
  SLOT_FILE=""
}

# Ensure we don't wedge the queue if the executor errors before archiving the inbox item.
cleanup_lock() {
  release_global_slot
  if [ -n "${ACTIVE_ITEM_FILE:-}" ] && [ -f "${ACTIVE_ITEM_FILE:-}" ]; then
    rm -f "$ACTIVE_ITEM_FILE" 2>/dev/null || true
  fi
  if [ -n "${INWORK_FILE:-}" ] && [ -f "${INWORK_FILE:-}" ]; then
    rm -f "$INWORK_FILE" 2>/dev/null || true
  fi
  if [ -n "${LOCK_DIR:-}" ] && [ -d "${LOCK_DIR:-}" ]; then
    rm -rf "$LOCK_DIR" 2>/dev/null || true
  fi
}
trap cleanup_lock EXIT

inbox_item="$INBOX_DIR/$next"
out_file="$OUTBOX_DIR/$next.md"
ACTIVE_ITEM_FILE="$ART_DIR/active-inbox-item.json"
is_qa_findings_item=0
if echo "$next" | grep -q -- '-qa-findings-'; then
  is_qa_findings_item=1
fi

# If we've already produced an outbox entry for this item, consider it done and archive.
if [ -f "$out_file" ]; then
  existing_status_line="$(grep -iE '^\- Status:' "$out_file" 2>/dev/null | head -n 1 || true)"
  existing_status="$(echo "$existing_status_line" | sed 's/^- Status: *//I' | tr '[:upper:]' '[:lower:]' | tr -d '\r' | tr ' _' '-')"
  if [ "$existing_status" != "in-progress" ]; then
    archive_inbox_item "$inbox_item" "$next" "$ART_DIR"
    exit 0
  fi
fi

# Publish "active item" marker for dashboards while this executor is running.
python3 - "$ACTIVE_ITEM_FILE" "$AGENT_ID" "$next" "$$" <<'PY' 2>/dev/null || true
import json
import socket
import sys
import time
from pathlib import Path

path = Path(sys.argv[1])
agent_id = sys.argv[2]
item_id = sys.argv[3]
pid = int(sys.argv[4])

payload = {
  "agent_id": agent_id,
  "item_id": item_id,
  "pid": pid,
  "host": socket.gethostname(),
  "started_at": int(time.time()),
  "started_at_iso": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
}

path.parent.mkdir(parents=True, exist_ok=True)
tmp = path.with_suffix(path.suffix + '.tmp')
tmp.write_text(json.dumps(payload, sort_keys=True), encoding='utf-8')
tmp.replace(path)
PY

# Build prompt context from known files.
read_file() {
  local p="$1"
  if [ -f "$p" ]; then
    echo "\n--- FILE: $p ---"
    sed -n '1,200p' "$p"
  fi
}

agent_role() {
  python3 - "$AGENT_ID" <<'PY'
import sys, re, pathlib
agent_id = sys.argv[1]
text = pathlib.Path("org-chart/agents/agents.yaml").read_text(encoding="utf-8", errors="ignore").splitlines()
in_item = False
role = ""
for line in text:
    m = re.match(r"^\s*-\s+id:\s*(.+)\s*$", line)
    if m:
        in_item = (m.group(1).strip() == agent_id)
        continue
    if in_item:
        m = re.match(r"^\s*role:\s*(.+)\s*$", line)
        if m:
            role = m.group(1).strip()
            break
print(role)
PY
}

ROLE="$(agent_role)"

agent_context() {
  python3 - "$AGENT_ID" <<'PY'
import sys, re, ast, pathlib
agent_id = sys.argv[1]
text = pathlib.Path("org-chart/agents/agents.yaml").read_text(encoding="utf-8", errors="ignore").splitlines()
in_item = False
role = ""
website = ""
module = ""
for line in text:
    m = re.match(r"^\s*-\s+id:\s*(.+)\s*$", line)
    if m:
        in_item = (m.group(1).strip() == agent_id)
        continue
    if in_item:
        m = re.match(r"^\s*role:\s*(.+)\s*$", line)
        if m:
            role = m.group(1).strip()
        m = re.match(r"^\s*website_scope:\s*(.+)\s*$", line)
        if m and not website:
            try:
                arr = ast.literal_eval(m.group(1).strip())
                if isinstance(arr, list) and arr and arr[0] != '*':
                    website = str(arr[0])
            except Exception:
                pass
        m = re.match(r"^\s*module_ownership:\s*(.+)\s*$", line)
        if m and not module:
            try:
                arr = ast.literal_eval(m.group(1).strip())
                if isinstance(arr, list) and arr:
                    module = str(arr[0])
            except Exception:
                pass
print(f"{role}\t{website}\t{module}")
PY
}

ctx="$(agent_context)"
CTX_ROLE="$(printf '%s' "$ctx" | awk -F'\t' '{print $1}')"
CTX_WEBSITE="$(printf '%s' "$ctx" | awk -F'\t' '{print $2}')"
CTX_MODULE="$(printf '%s' "$ctx" | awk -F'\t' '{print $3}')"

# Record exec start time for tool-written outbox fallback detection.
_EXEC_START_TS="$(date +%s)"

PROMPT="You are the agent '${AGENT_ID}'.\n\nYou have access to these repos:\n- HQ: /home/ubuntu/forseti.life/copilot-hq\n- Forseti Drupal: /home/ubuntu/forseti.life\n\nYou have one inbox item folder: ${inbox_item}.\n\nYou have full read/write tool access (--allow-all) to all files in both repos. You MAY and SHOULD use tools (bash, edit, create) to directly write files within your owned scope — especially your own seat instructions file: org-chart/agents/instructions/${AGENT_ID}.instructions.md. The executor writes the outbox text response; you are responsible for any other file changes (instruction refreshes, artifacts, code) via direct tool calls. Do NOT claim filesystem permission problems (\"Permission denied\", \"can't write files\") unless you verified it with a command and can show the exact error output.\n\nOUTBOX OUTPUT RULE (CRITICAL — executor will fail if violated):\nYour outbox update MUST be your FINAL TEXT RESPONSE (the last text you print). Do NOT create or write any outbox file using tools. The executor captures your text response from stdout and writes it to the outbox for you. Writing the outbox as a file via tools will cause the executor to see an empty response and quarantine this inbox item.\n\nTask: Write a concise outbox update in markdown using this required structure:\n\n- Status: done | in_progress | blocked | needs-info\n- Summary: <one paragraph>\n\nThe first two lines MUST be exactly:\n- Status: ...\n- Summary: ...\n(no bold, no extra punctuation)\n\n## Next actions\n- ...\n\n## Blockers\n- If blocked/needs-info, be explicit.\n\n## Needs from CEO\n- If blocked/needs-info, list exactly what you need (missing context, resources, clarification, URLs, acceptance criteria, credentials, etc.)\n\nIf Status is blocked or needs-info, you MUST also include:\n\n## Decision needed\n- <the decision you need from supervisor/CEO>\n\n## Recommendation\n- <what you recommend and why>\n\nIf the request is unclear, set Status: needs-info and list the clarifying questions under \"Needs from CEO\".\n\nDO NOT claim you executed code changes unless you actually did."

PROMPT+="\n\nGit rule (required when you change code):\n- If you modify any tracked repo files, you MUST run: git status, git diff (or summary), then git add + git commit.\n- Include the commit hash(es) in your outbox update.\n- Do NOT push unless explicitly assigned as the release operator."

PROMPT+="\n\nEscalation heading rule: when blocked/needs-info, put your ask under ONE of these headings (pick the closest):\n- ## Needs from Supervisor\n- ## Needs from CEO\n- ## Needs from Board\n(Use Supervisor by default; CEO only if your supervisor is CEO; Board only if you are CEO escalating to the human owner.)"

PROMPT+="\n\nNeeds-info validity rule (CRITICAL): If you set Status: needs-info, the '## Needs from CEO' (or '## Needs from Supervisor') section MUST contain at least one specific, non-N/A item. A needs-info outbox with an empty or N/A-only Needs section is a malformed response — the orchestrator will flag it as a phantom blocker and it will NOT be routed to a supervisor. If you have no actual needs, set Status: done or Status: blocked with a specific blocker. Never use needs-info as a hedge."

PROMPT+="\n\n## ROI estimate\n- ROI: <integer 1-infinity>\n- Rationale: <1-3 sentences>\n\nROI guidance: higher ROI = higher org value/urgency/leverage. Use ROI to prioritize next actions and to justify escalations/delegations. Be reasonable relative to your current queue (avoid inflating everything)."

PROMPT+="$(read_file "org-chart/org-wide.instructions.md")"
PROMPT+="$(read_file "org-chart/DECISION_OWNERSHIP_MATRIX.md")"
PROMPT+="$(read_file "org-chart/ownership/file-ownership.md")"
if [ -n "$ROLE" ] && [ -f "org-chart/roles/${ROLE}.instructions.md" ]; then
  PROMPT+="$(read_file "org-chart/roles/${ROLE}.instructions.md")"
fi

# Per-site instructions (website-specific; loaded via agent website_scope).
if [ -n "$CTX_WEBSITE" ] && [ "$CTX_WEBSITE" != "*" ] && [ -f "org-chart/sites/${CTX_WEBSITE}/site.instructions.md" ]; then
  PROMPT+="$(read_file "org-chart/sites/${CTX_WEBSITE}/site.instructions.md")"
fi

# Agent-owned instructions (self-managed per seat).
if [ -f "org-chart/agents/instructions/${AGENT_ID}.instructions.md" ]; then
  PROMPT+="$(read_file \"org-chart/agents/instructions/${AGENT_ID}.instructions.md\")"
else
  PROMPT+="\n\n## Agent scope file missing\n- No per-seat scope file found at org-chart/agents/instructions/${AGENT_ID}.instructions.md\n- Take a best-guess scope (smallest safe) and escalate to your supervisor to confirm/define scope."
fi

PROMPT+="$(read_file "templates/product-documentation.md")"

PROMPT+="$(read_file "$inbox_item/command.md")"
PROMPT+="$(read_file "$inbox_item/README.md")"
PROMPT+="$(read_file "$inbox_item/00-problem-statement.md")"
PROMPT+="$(read_file "$inbox_item/01-acceptance-criteria.md")"
PROMPT+="$(read_file "$inbox_item/06-risk-assessment.md")"

# Per-agent Copilot session id.
SESSION_FILE="$HOME/.copilot/wrappers/hq-${AGENT_ID}.session"
mkdir -p "$(dirname "$SESSION_FILE")"
if [ ! -f "$SESSION_FILE" ] || [ -z "$(head -n1 "$SESSION_FILE" | tr -d ' \t\r\n')" ]; then
  if command -v uuidgen >/dev/null 2>&1; then
    uuidgen > "$SESSION_FILE"
  else
    python3 - <<'PY' > "$SESSION_FILE"
import uuid
print(uuid.uuid4())
PY
  fi
fi
SESSION_ID="$(head -n1 "$SESSION_FILE" | tr -d ' \t\r\n')"

# ── Local LLM routing ────────────────────────────────────────────────────────
# Check if this agent has a local model assigned via llm/routing.yaml.
# If the model file is present on disk, invoke llm/runner.py instead of Copilot.
# Falls back to Copilot if: no model assigned, model not downloaded, LLM_DISABLE=1,
# runner not found, or runner returns empty output.

_llm_resolve_model() {
  # Returns the model file path if a local model is assigned and present on disk.
  # Returns empty string to signal "use Copilot".
  local agent="$1"
  local routing="$ROOT_DIR/llm/routing.yaml"
  local manifest="$ROOT_DIR/llm/model-manifest.yaml"
  local models_dir="$ROOT_DIR/llm/models"

  [ "${LLM_DISABLE:-0}" = "1" ] && return 0
  [ -f "$routing" ] || return 0
  [ -f "$manifest" ] || return 0

  # Prefer venv Python; fall back to system python3.
  local py
  if [ -x "$ROOT_DIR/llm/.venv/bin/python3" ]; then
    py="$ROOT_DIR/llm/.venv/bin/python3"
  elif [ -n "${LLM_PYTHON_BIN:-}" ] && [ -x "${LLM_PYTHON_BIN}" ]; then
    py="$LLM_PYTHON_BIN"
  else
    py="$(command -v python3 2>/dev/null || true)"
  fi
  [ -n "$py" ] || return 0

  "$py" - "$agent" "$routing" "$manifest" "$models_dir" 2>/dev/null <<'PY'
import sys, pathlib

agent_id   = sys.argv[1]
routing_p  = pathlib.Path(sys.argv[2])
manifest_p = pathlib.Path(sys.argv[3])
models_dir = pathlib.Path(sys.argv[4])
repo_root  = routing_p.parent.parent

# Import shared routing lib; fall back to inline resolution if not yet available.
sys.path.insert(0, str(repo_root))
try:
    from llm.lib.routing import load_yaml, resolve_model_file
    try:
        routing  = load_yaml(routing_p)
        manifest = load_yaml(manifest_p)
    except ImportError:
        sys.exit(0)  # pyyaml not installed yet
    agents_yaml = repo_root / "org-chart" / "agents" / "agents.yaml"
    model_file = resolve_model_file(agent_id, routing, manifest, models_dir, agents_yaml)
    if model_file:
        print(str(model_file))
except Exception:
    sys.exit(0)  # Any import/resolution error → fall back to Copilot silently
PY
}

_llm_resolve_route_id() {
  local agent="$1"
  local routing="$ROOT_DIR/llm/routing.yaml"

  [ -f "$routing" ] || return 0

  local py
  if [ -x "$ROOT_DIR/llm/.venv/bin/python3" ]; then
    py="$ROOT_DIR/llm/.venv/bin/python3"
  elif [ -n "${LLM_PYTHON_BIN:-}" ] && [ -x "${LLM_PYTHON_BIN}" ]; then
    py="$LLM_PYTHON_BIN"
  else
    py="$(command -v python3 2>/dev/null || true)"
  fi
  [ -n "$py" ] || return 0

  "$py" - "$agent" "$routing" 2>/dev/null <<'PY'
import sys, pathlib

agent_id = sys.argv[1]
routing_p = pathlib.Path(sys.argv[2])
repo_root = routing_p.parent.parent

sys.path.insert(0, str(repo_root))
try:
    from llm.lib.routing import load_yaml, resolve_model_id
    try:
        routing = load_yaml(routing_p)
    except ImportError:
        sys.exit(0)
    agents_yaml = repo_root / "org-chart" / "agents" / "agents.yaml"
    route_id = resolve_model_id(agent_id, routing, agents_yaml)
    if route_id:
        print(str(route_id))
except Exception:
    sys.exit(0)
PY
}

ROUTED_ROUTE_ID="$(_llm_resolve_route_id "$AGENT_ID")"
LOCAL_MODEL_FILE="$(_llm_resolve_model "$AGENT_ID")"
LOCAL_RUNNER="$ROOT_DIR/llm/runner.py"

resolve_backend() {
  case "${ROUTED_ROUTE_ID:-}" in
    copilot)
      if [ -n "$COPILOT_BIN" ]; then
        echo "copilot"
        return 0
      fi
      echo "ERROR: routing requested copilot for ${AGENT_ID} but copilot CLI is not available." >&2
      return 1
      ;;
    bedrock)
      if [ -x "$BEDROCK_ASSIST_SCRIPT" ]; then
        echo "bedrock"
        return 0
      fi
      echo "ERROR: routing requested bedrock for ${AGENT_ID} but bedrock-assist is not executable: $BEDROCK_ASSIST_SCRIPT" >&2
      return 1
      ;;
  esac

  if [ -n "${LOCAL_MODEL_FILE:-}" ]; then
    echo "local"
    return 0
  fi

  case "$AGENTIC_BACKEND" in
    copilot)
      if [ -n "$COPILOT_BIN" ]; then
        echo "copilot"
        return 0
      fi
      echo "ERROR: HQ_AGENTIC_BACKEND=copilot but copilot CLI not found in PATH." >&2
      return 1
      ;;
    bedrock)
      if [ -x "$BEDROCK_ASSIST_SCRIPT" ]; then
        echo "bedrock"
        return 0
      fi
      echo "ERROR: HQ_AGENTIC_BACKEND=bedrock but script not executable: $BEDROCK_ASSIST_SCRIPT" >&2
      return 1
      ;;
    auto)
      if [ -n "$COPILOT_BIN" ]; then
        echo "copilot"
        return 0
      fi
      if [ -x "$BEDROCK_ASSIST_SCRIPT" ]; then
        echo "bedrock"
        return 0
      fi
      echo "ERROR: no GenAI backend available (copilot missing, bedrock-assist missing)." >&2
      return 1
      ;;
    *)
      echo "ERROR: invalid HQ_AGENTIC_BACKEND='$AGENTIC_BACKEND' (expected: auto|copilot|bedrock)" >&2
      return 1
      ;;
  esac
}

GENAI_BACKEND="$(resolve_backend)" || exit 1

# Global concurrency guard: limit total concurrent agent executions across ALL
# loops/processes on this host. Bedrock is less tolerant of bursty parallel
# requests than Copilot, so default it to a smaller lane unless explicitly
# overridden.
if [ -n "${AGENT_EXEC_MAX_CONCURRENT:-}" ]; then
  MAX_CONCURRENT_EXECUTIONS="${AGENT_EXEC_MAX_CONCURRENT}"
elif [ "$GENAI_BACKEND" = "bedrock" ]; then
  MAX_CONCURRENT_EXECUTIONS="${AGENT_EXEC_MAX_CONCURRENT_BEDROCK:-4}"
else
  MAX_CONCURRENT_EXECUTIONS=6
fi

# If the host is already at concurrency capacity, release the per-item lock and
# treat this as "nothing to do" so loops don't spam errors.
if ! acquire_global_slot "$MAX_CONCURRENT_EXECUTIONS"; then
  rm -rf "$LOCK_DIR" 2>/dev/null || true
  LOCK_DIR=""
  exit 2
fi

_throttle_copilot_api() {
  # Global rate-limit guard: enforces a minimum delay between Copilot API calls
  # across ALL concurrent agent executions (shared timestamp file).
  # Override minimum delay via COPILOT_API_MIN_DELAY_SECONDS (default: 900).
  local min_delay="${COPILOT_API_MIN_DELAY_SECONDS:-900}"
  local ts_file="$ROOT_DIR/tmp/.last-copilot-api-call"
  local cooldown_file="$ROOT_DIR/tmp/.copilot-api-rate-limit-until"
  mkdir -p "$ROOT_DIR/tmp"

  # Use a separate lock file so only one agent updates the timestamp at a time.
  local lock_file="$ROOT_DIR/tmp/.copilot-api-throttle.lock"
  (
    if command -v flock >/dev/null 2>&1; then
      exec 9>"$lock_file"
      flock 9
    fi

    local now elapsed wait_for
    now=$(date +%s)

    if [ -f "$cooldown_file" ]; then
      local cooldown_until
      cooldown_until="$(cat "$cooldown_file" 2>/dev/null || echo 0)"
      if [[ "$cooldown_until" =~ ^[0-9]+$ ]] && [ "$cooldown_until" -gt "$now" ]; then
        wait_for=$(( cooldown_until - now ))
        echo "THROTTLE: waiting ${wait_for}s for Copilot rate-limit cooldown (until=${cooldown_until})" >&2
        sleep "$wait_for"
        now=$(date +%s)
      fi
    fi

    if [ -f "$ts_file" ]; then
      local last
      last=$(cat "$ts_file" 2>/dev/null || echo 0)
      elapsed=$(( now - last ))
      if [ "$elapsed" -lt "$min_delay" ]; then
        wait_for=$(( min_delay - elapsed ))
        echo "THROTTLE: waiting ${wait_for}s before next Copilot API call (last=${last}, now=${now}, min_delay=${min_delay})" >&2
        sleep "$wait_for"
      fi
    fi

    # Record the time of this API call.
    date +%s > "$ts_file"
  )
}

copilot_rate_limited() {
  local text="${1:-}"
  printf '%s\n' "$text" | grep -qiE "hit a rate limit|too many requests|please try again in [0-9]+ (seconds?|minutes?)"
}

copilot_rate_limit_backoff_seconds() {
  local text="${1:-}"
  local fallback="${COPILOT_RATE_LIMIT_BACKOFF_SECONDS:-300}"
  local match value

  match="$(printf '%s\n' "$text" | grep -oiE 'Please try again in [0-9]+ minutes?' | head -n 1 || true)"
  if [ -n "$match" ]; then
    value="$(printf '%s\n' "$match" | grep -oE '[0-9]+' | head -n 1 || true)"
    if [[ "$value" =~ ^[0-9]+$ ]]; then
      echo $(( value * 60 + 15 ))
      return 0
    fi
  fi

  match="$(printf '%s\n' "$text" | grep -oiE 'Please try again in [0-9]+ seconds?' | head -n 1 || true)"
  if [ -n "$match" ]; then
    value="$(printf '%s\n' "$match" | grep -oE '[0-9]+' | head -n 1 || true)"
    if [[ "$value" =~ ^[0-9]+$ ]]; then
      echo $(( value + 15 ))
      return 0
    fi
  fi

  echo "$fallback"
}

set_copilot_rate_limit_cooldown() {
  local seconds="${1:-0}"
  local cooldown_file="$ROOT_DIR/tmp/.copilot-api-rate-limit-until"
  local until
  [[ "$seconds" =~ ^[0-9]+$ ]] || seconds=0
  [ "$seconds" -gt 0 ] 2>/dev/null || return 0
  until=$(( $(date +%s) + seconds ))
  mkdir -p "$ROOT_DIR/tmp"
  printf '%s\n' "$until" > "$cooldown_file"
  echo "THROTTLE: recorded Copilot rate-limit cooldown for ${seconds}s (until=${until})" >&2
}

run_copilot() {
  local prompt="$1"
  echo "DEBUG run_copilot: AGENT_ID=${AGENT_ID} SESSION_ID=${SESSION_ID}" >&2
  # Enforce inter-call rate-limit delay before hitting the Copilot API.
  _throttle_copilot_api
  local _copilot_response
  # Prevent orchestration hangs if the Copilot CLI stalls.
  # Default: 15 minutes; override via COPILOT_TIMEOUT_SEC.
  if command -v timeout >/dev/null 2>&1; then
    _copilot_response="$(timeout -k "${COPILOT_TIMEOUT_KILL_SEC:-10}" "${COPILOT_TIMEOUT_SEC:-900}" \
      "$COPILOT_BIN" --resume "$SESSION_ID" --model auto --silent --allow-all -p "$prompt" 2>&1 || true)"
  else
    _copilot_response="$("$COPILOT_BIN" --resume "$SESSION_ID" --model auto --silent --allow-all -p "$prompt" 2>&1 || true)"
  fi
  # If response is empty OR lacks the required "- Status:" header, the model finished
  # its tool work without emitting a properly structured text response.
  # Follow up in the same session (which retains tool context) to extract the outbox.
  local _needs_followup=0
  if [ -z "$(printf '%s' "$_copilot_response" | tr -d ' \t\r\n')" ]; then
    _needs_followup=1
    echo "WARN: empty first-pass response from ${AGENT_ID}; requesting text outbox via follow-up prompt" >&2
  elif ! printf '%s\n' "$_copilot_response" | grep -qiE '^\- Status:'; then
    _needs_followup=1
    echo "WARN: first-pass response from ${AGENT_ID} missing '- Status:' header; requesting structured outbox via follow-up prompt" >&2
  fi
  if [ "$_needs_followup" -eq 1 ]; then
    _throttle_copilot_api
    local _followup="Your previous response did not start with '- Status:'. The executor requires your outbox to begin with that line. Output ONLY the outbox markdown now — no prose, no tool calls. First line MUST be exactly: - Status: done (or blocked/in_progress/needs-info)."
    if command -v timeout >/dev/null 2>&1; then
      _copilot_response="$(timeout -k "${COPILOT_TIMEOUT_KILL_SEC:-10}" "120" \
        "$COPILOT_BIN" --resume "$SESSION_ID" --model auto --silent -p "$_followup" 2>&1 || true)"
    else
      _copilot_response="$("$COPILOT_BIN" --resume "$SESSION_ID" --model auto --silent -p "$_followup" 2>&1 || true)"
    fi
  fi
  printf '%s\n' "$_copilot_response"
}

bedrock_site_for_context() {
  if [ -n "${CTX_WEBSITE:-}" ] && [ "${CTX_WEBSITE}" != "*" ]; then
    echo "$CTX_WEBSITE"
    return 0
  fi
  case "$AGENT_ID" in
    *dungeoncrawler*) echo "dungeoncrawler" ;;
    *) echo "forseti" ;;
  esac
}

run_bedrock() {
  local prompt="$1"
  local site
  local contract
  site="$(bedrock_site_for_context)"
  contract=$'Return plain markdown only.\n'
  contract+=$'The first line must be exactly "- Status: <value>".\n'
  contract+=$'The second line must be exactly "- Summary: <value>".\n'
  contract+=$'Do not emit tool calls, tool responses, XML, JSON, or analysis preambles.\n'
  contract+=$'If you need to continue investigating, use "- Status: in_progress" and summarize the next concrete step.\n\n'
  BEDROCK_OPERATION="hq_agent_exec_${AGENT_ID}" \
    "$BEDROCK_ASSIST_SCRIPT" "$site" "${contract}${prompt}" 2>/dev/null || true
}

run_primary_backend() {
  local prompt="$1"
  case "$GENAI_BACKEND" in
    local) echo "" ;;
    copilot) run_copilot "$prompt" ;;
    bedrock) run_bedrock "$prompt" ;;
    *) echo "" ;;
  esac
}

backend_retry_delay_seconds() {
  case "$GENAI_BACKEND" in
    bedrock) echo "${BEDROCK_RETRY_DELAY_SECONDS:-15}" ;;
    *) echo 0 ;;
  esac
}

if [ -n "$LOCAL_MODEL_FILE" ] && [ -f "$LOCAL_RUNNER" ]; then
  # Prefer venv Python for the runner.
  _LLM_PY="$(command -v python3)"
  [ -x "$ROOT_DIR/llm/.venv/bin/python3" ] && _LLM_PY="$ROOT_DIR/llm/.venv/bin/python3"
  [ -n "${LLM_PYTHON_BIN:-}" ] && [ -x "$LLM_PYTHON_BIN" ] && _LLM_PY="$LLM_PYTHON_BIN"

  # Generate response via local model.
  response="$("$_LLM_PY" "$LOCAL_RUNNER" --session "$SESSION_ID" --model "$LOCAL_MODEL_FILE" --prompt "$PROMPT" 2>/dev/null || true)"
  # Retry once on empty.
  if [ -z "$(printf '%s' "$response" | tr -d ' \t\r\n')" ]; then
    response="$("$_LLM_PY" "$LOCAL_RUNNER" --session "$SESSION_ID" --model "$LOCAL_MODEL_FILE" --prompt "$PROMPT" 2>/dev/null || true)"
  fi
  # If local model still returns empty, fall back to the selected backend.
  if [ -z "$(printf '%s' "$response" | tr -d ' \t\r\n')" ]; then
    response="$(run_primary_backend "$PROMPT")"
    if [ -z "$(printf '%s' "$response" | tr -d ' \t\r\n')" ]; then
      _retry_delay="$(backend_retry_delay_seconds)"
      if [ "${_retry_delay:-0}" -gt 0 ] 2>/dev/null; then
        sleep "$_retry_delay"
      fi
      response="$(run_primary_backend "$PROMPT")"
    fi
  fi
else
  # Model: always pass --model auto so sessions use Copilot's multi-model routing
  # and avoid hitting single-model weekly rate limits.
  # Generate response first so we can validate structure.
  response="$(run_primary_backend "$PROMPT")"
  # Retry once if the backend returns an empty response.
  if [ -z "$(printf '%s' "$response" | tr -d ' \t\r\n')" ]; then
    _retry_delay="$(backend_retry_delay_seconds)"
    if [ "${_retry_delay:-0}" -gt 0 ] 2>/dev/null; then
      sleep "$_retry_delay"
    fi
    response="$(run_primary_backend "$PROMPT")"
  fi
fi
# Fallback: if response is still empty, check if the agent wrote an outbox file directly via tools
# (a known failure mode where the model uses create/edit tools for the outbox instead of returning text).
# Only accept outbox files created AFTER this exec run started (not old files from prior runs).
if [ -z "$(printf '%s' "$response" | tr -d ' \t\r\n')" ]; then
  _latest_outbox="$(ls -t "$OUTBOX_DIR"/*.md 2>/dev/null | head -1 || true)"
  if [ -n "$_latest_outbox" ] && [ -f "$_latest_outbox" ]; then
    _outbox_mtime=$(stat -c %Y "$_latest_outbox" 2>/dev/null || echo 0)
    # Only use if created/modified AFTER this exec started (fresh file from this run)
    if [ "$_outbox_mtime" -ge "${_EXEC_START_TS:-0}" ] && grep -qiE '^\- Status:' "$_latest_outbox" 2>/dev/null; then
      echo "WARN: empty text response from ${AGENT_ID}; recovering from tool-written outbox file: $_latest_outbox" >&2
      response="$(cat "$_latest_outbox")"
    fi
  fi
fi
# Normalize common formatting mistakes (e.g. "- **Status:** done", "Status: done").
response="$(printf '%s\n' "$response" | sed -E 's/^[[:space:]]*-[[:space:]]+\\*\\*Status\\*\\*:/- Status:/I; s/^[[:space:]]*-[[:space:]]+\\*\\*Summary\\*\\*:/- Summary:/I; s/^[[:space:]]*\\*\\*Status\\*\\*:/- Status:/I; s/^[[:space:]]*\\*\\*Summary\\*\\*:/- Summary:/I; s/^[[:space:]]*Status:/- Status:/I; s/^[[:space:]]*Summary:/- Summary:/I')"
if ! echo "$response" | grep -qiE '^\- Status:'; then
  if [ "$is_qa_findings_item" -eq 1 ]; then
    response=$'- Status: in_progress\n- Summary: QA findings item acknowledged; remediation work is in progress and will continue on this queue item until fixes are completed and handed off to QA.\n\n## Next actions\n- Review findings-summary evidence and prioritize highest-impact failures first.\n- Apply fixes and post clear QA handoff markers after each fix.\n- Continue until all required tests pass, then mark done.\n\n## Blockers\n- None right now.\n\n## Needs from CEO\n- N/A\n\n'"$response"
  else
    _retry_count=0
    _saw_rate_limit=0
    if copilot_rate_limited "$response"; then
      _saw_rate_limit=1
      _rate_limit_backoff="$(copilot_rate_limit_backoff_seconds "$response")"
      set_copilot_rate_limit_cooldown "$_rate_limit_backoff"
      echo "WARN: Copilot rate-limited for ${AGENT_ID}; preserving inbox item for later retry" >&2
    else
      # Retry up to 2 more times with 30s backoff before writing a failure record.
      while [ "$_retry_count" -lt 2 ]; do
        _retry_count=$((_retry_count + 1))
        echo "WARN: agent response missing status header; retry ${_retry_count}/2 for ${AGENT_ID} (sleep 30s)" >&2
        sleep 30
        response="$(run_primary_backend "$PROMPT")"
        response="$(printf '%s\n' "$response" | sed -E 's/^[[:space:]]*-[[:space:]]+\\*\\*Status\\*\\*:/- Status:/I; s/^[[:space:]]*-[[:space:]]+\\*\\*Summary\\*\\*:/- Summary:/I; s/^[[:space:]]*\\*\\*Status\\*\\*:/- Status:/I; s/^[[:space:]]*\\*\\*Summary\\*\\*:/- Summary:/I; s/^[[:space:]]*Status:/- Status:/I; s/^[[:space:]]*Summary:/- Summary:/I')"
        if copilot_rate_limited "$response"; then
          _saw_rate_limit=1
          _rate_limit_backoff="$(copilot_rate_limit_backoff_seconds "$response")"
          set_copilot_rate_limit_cooldown "$_rate_limit_backoff"
          echo "WARN: Copilot rate-limited for ${AGENT_ID}; stopping retries for this cycle" >&2
          break
        fi
        if echo "$response" | grep -qiE '^\- Status:'; then
          break
        fi
      done
    fi
    # If still no valid status header after retries, write a failure record instead of a stub outbox.
    if ! echo "$response" | grep -qiE '^\- Status:'; then
      mkdir -p "tmp/executor-failures"
      _fail_ts="$(date +%Y%m%dT%H%M%S)"
      _fail_file="tmp/executor-failures/${_fail_ts}-${AGENT_ID}.md"
      _failure_reason="agent response missing required status header after ${_retry_count} retries"
      if [ "$_saw_rate_limit" -eq 1 ]; then
        _failure_reason="Copilot rate limit encountered; executor preserved inbox item for a later retry"
      fi
      cat >"$_fail_file" <<FAILMD
# Executor failure: ${AGENT_ID}

- Agent: ${AGENT_ID}
- Inbox item: ${next}
- Failed at: $(date -Iseconds)
- Retries attempted: ${_retry_count}
- Failure reason: ${_failure_reason}
- Action: no stub outbox written; stagnation detector should query tmp/executor-failures/ for systemic signal

## Raw response (first 500 chars)
$(printf '%s' "$response" | head -c 500)
FAILMD
      echo "ERROR: executor validation failure for ${AGENT_ID} after ${_retry_count} retries; failure record written to ${_fail_file}" >&2
      # Auto-prune: keep only the 200 most-recent failure records to prevent unbounded accumulation.
      _failure_count=$(ls "tmp/executor-failures/" | wc -l)
      if [ "$_failure_count" -gt 200 ]; then
        ls -t "tmp/executor-failures/" | tail -n +"201" | while IFS= read -r _old; do
          rm -f "tmp/executor-failures/$_old"
        done
      fi
      _repeat_threshold="${EXECUTOR_STATUS_HEADER_REPEAT_THRESHOLD:-3}"
      _repeat_failures="$(
        find "tmp/executor-failures" -maxdepth 1 -type f -name "*-${AGENT_ID}.md" 2>/dev/null \
          | while IFS= read -r _f; do
              if grep -qF -- "- Inbox item: ${next}" "$_f" 2>/dev/null; then
                echo "$_f"
              fi
            done \
          | wc -l | tr -d ' '
      )"
      [[ "$_repeat_failures" =~ ^[0-9]+$ ]] || _repeat_failures=0
      if [ "$_repeat_failures" -lt "$_repeat_threshold" ]; then
        # Do NOT write a stub outbox yet — preserve inbox item for a limited number of retries.
        exit 0
      fi

      echo "WARN: quarantining ${AGENT_ID} item ${next} after ${_repeat_failures} repeated no-status failures" >&2
      response="$(cat <<EOF
- Status: needs-info
- Summary: Executor quarantined inbox item ${next} after ${_repeat_failures} repeated cycles without a valid status-header response from ${AGENT_ID}; automatic retries have stopped to prevent infinite backlog churn.

## Next actions
- Supervisor should decide whether to manually close, rewrite, or re-dispatch ${next}.
- If the work is already effectively verified, write a canonical outbox verdict and archive the inbox item.
- If similar quarantines recur for this seat, investigate backend/session/prompt behavior instead of retrying the same item.

## Blockers
- Executor backend did not return a valid '- Status:' header for this inbox item after ${_retry_count} retries in the latest cycle.

## Needs from Supervisor
- Decide whether ${next} should be manually closed, rewritten with tighter scope, or investigated as a seat/backend issue.

## Decision needed
- Should this quarantined inbox item be manually closed or re-dispatched?

## Recommendation
- Do not allow further automatic retries for the same unchanged item. Either close it with manual evidence or rewrite the dispatch with tighter scope before re-queueing.

## ROI estimate
- ROI: 34
- Rationale: Quarantining repeated executor failures preserves queue health and supervisor attention by converting infinite retry churn into one actionable escalation.
EOF
)"
    fi
  fi
fi

{
  # Required format: the first two lines must be "- Status:" and "- Summary:".
  # Put executor metadata at the bottom to avoid breaking strict parsers.
  echo "$response"
  echo
  echo "---"
  echo "- Agent: ${AGENT_ID}"
  echo "- Source inbox: ${inbox_item}"
  echo "- Generated: $(date -Iseconds)"
} > "$out_file"

echo "processed agent=${AGENT_ID} item=${next} outbox=$(basename "$out_file")"

status_line="$(grep -iE '^\- Status:' "$out_file" 2>/dev/null | tail -n 1 || true)"
status="$(echo "$status_line" | sed 's/^- Status: *//I' | tr '[:upper:]' '[:lower:]' | tr -d '\r')"
status="$(echo "$status" | tr ' _' '-' | sed 's/[^a-z-].*$//')"

# Stamp command.md with Status: done so orchestrator skips re-dispatch.
if [ "$status" = "done" ] && [ -f "$inbox_item/command.md" ]; then
  completed_ts="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
  # Prepend only if not already stamped to avoid duplicate markers.
  if ! grep -qiE '^\- Status:\s*done' "$inbox_item/command.md" 2>/dev/null; then
    { printf -- '- Status: done\n- Completed: %s\n\n' "$completed_ts"; cat "$inbox_item/command.md"; } \
      > "$inbox_item/command.md.tmp" && mv "$inbox_item/command.md.tmp" "$inbox_item/command.md"
  fi
fi

# Option B: if the work item references a canonical GitHub issue, post an update
# comment back to that issue (and close on done).
issue_url=""
if [ -f "$inbox_item/command.md" ]; then
  issue_url="$(grep -Eo 'https?://github\.com/[^[:space:]]+/issues/[0-9]+' "$inbox_item/command.md" 2>/dev/null | head -n 1 || true)"
fi

if [ -n "${issue_url:-}" ] && [ -x "./scripts/github-issues-comment.py" ]; then
  summary_line="$(grep -iE '^\- Summary:' "$out_file" 2>/dev/null | head -n 1 || true)"
  summary_txt="$(printf '%s' "$summary_line" | sed 's/^- Summary: *//I' | tr -d '\r')"
  comment=$(
    cat <<EOF
HQ agent update

- Agent: ${AGENT_ID}
- Inbox item: ${next}
- Status: ${status}
- Summary: ${summary_txt}

Full HQ outbox:
- sessions/${AGENT_ID}/outbox/$(basename "$out_file")
EOF
  )

  close_flag=""
  if [ "$status" = "done" ]; then
    close_flag="--close"
  fi

  python3 ./scripts/github-issues-comment.py --issue-url "$issue_url" --comment "$comment" $close_flag >/dev/null 2>&1 || true
fi

extract_section() {
  local heading="$1" file="$2"
  awk -v h="$heading" 'BEGIN{p=0} $0 ~ ("^## " h "$"){p=1;next} /^## /{p=0} {if(p) print}' "$file" | sed -n '1,40p'
}

has_heading() {
  local heading="$1" file="$2"
  grep -qE "^## ${heading}$" "$file" 2>/dev/null
}

qa_agent_for_context() {
  # Best-effort mapping from site scope to QA seat.
  # If unknown, default to qa-infra.
  local website="${CTX_WEBSITE:-}"
  case "$website" in
    forseti.life)
      echo "qa-forseti"
      return 0
      ;;
    dungeoncrawler|dungeoncrawler.forseti.life)
      echo "qa-dungeoncrawler"
      return 0
      ;;
  esac
  echo "qa-infra"
}

regression_checklist_path_for_context() {
  local website="${CTX_WEBSITE:-}"
  if [ -z "$website" ] || [ "$website" = "*" ]; then
    echo ""
    return 0
  fi
  echo "org-chart/sites/${website}/qa-regression-checklist.md"
}

regression_checklist_baseline_for_context() {
  local website="${CTX_WEBSITE:-}"
  case "$website" in
    infrastructure)
      echo "operator-audit mode only — \`python3 scripts/qa-suite-validate.py\`, \`bash scripts/lint-scripts.sh\` (when present), \`bash -n scripts/*.sh\`, and static scope/instruction checks."
      ;;
    "")
      echo "targeted verification only; no site baseline available for this context."
      ;;
    "*")
      echo "targeted verification only; no site baseline available for wildcard context."
      ;;
    *)
      echo "URL validation + role-based permission checks (see \`runbooks/role-based-url-audit.md\`)."
      ;;
  esac
}

qa_unit_test_step3_for_context() {
  local website="${CTX_WEBSITE:-}"
  case "$website" in
    infrastructure)
      cat <<'EOF'
    3) Run infrastructure operator-audit checks for this scope:
       - python3 scripts/qa-suite-validate.py
       - bash scripts/lint-scripts.sh  (when present)
       - bash -n scripts/*.sh  (as applicable to the changed surface)
       - Do NOT run scripts/site-audit-run.sh or URL/Playwright audits for infrastructure
EOF
      ;;
    *)
      cat <<'EOF'
    3) Run the automated URL validation + role-based permission checks for this site (requires ALLOW_PROD_QA=1):
       - scripts/site-audit-run.sh (see runbooks/role-based-url-audit.md)
EOF
      ;;
  esac
}

append_regression_checklist_item() {
  local checklist_path="$1" item_id="$2" outbox_path="$3"
  [ -n "$checklist_path" ] || return 0

  mkdir -p "$(dirname "$checklist_path")" 2>/dev/null || true
  if [ ! -f "$checklist_path" ]; then
    cat >"$checklist_path" <<'MD'
# QA Regression Checklist

This file is a running list of targeted regression checks derived from completed Dev items.

- Automated baseline (always): BASELINE_PLACEHOLDER
- Manual/targeted checks: one checklist entry per completed Dev item.

## Checklist

MD
    local baseline_text
    baseline_text="$(regression_checklist_baseline_for_context)"
    python3 - "$checklist_path" "$baseline_text" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
baseline = sys.argv[2]
text = path.read_text(encoding="utf-8")
text = text.replace("BASELINE_PLACEHOLDER", baseline, 1)
path.write_text(text, encoding="utf-8")
PY
  fi

  # Avoid duplicates.
  if grep -qF "$item_id" "$checklist_path" 2>/dev/null; then
    return 0
  fi

  echo "- [ ] ${item_id} — targeted regression check (see dev outbox: ${outbox_path})" >>"$checklist_path"
}

notify_qa_unit_test_on_done() {
  local item_id="$1" outbox_path="$2"

  local qa_agent today slug qa_item qa_inbox_dir qa_outbox_file checklist_path
  qa_agent="$(qa_agent_for_context)"
  today="$(date +%Y%m%d)"
  slug="$(echo "$item_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-60)"
  qa_item="${today}-unit-test-${slug}"
  qa_inbox_dir="sessions/${qa_agent}/inbox/${qa_item}"
  qa_outbox_file="sessions/${qa_agent}/outbox/${qa_item}.md"

  # Don't spam duplicates.
  if [ -d "$qa_inbox_dir" ] || [ -f "$qa_outbox_file" ]; then
    return 0
  fi

  mkdir -p "$qa_inbox_dir" 2>/dev/null || true
  printf '%s\n' "5" >"$qa_inbox_dir/roi.txt" 2>/dev/null || true

  checklist_path="$(regression_checklist_path_for_context)"
  append_regression_checklist_item "$checklist_path" "$item_id" "$outbox_path"

  local step3_text
  step3_text="$(qa_unit_test_step3_for_context)"

  cat >"$qa_inbox_dir/command.md" <<MD
- command: |
    Targeted QA unit test for completed Dev item.

    - Completed item: ${item_id}
    - Dev seat: ${AGENT_ID}
    - Dev outbox: ${outbox_path}

    Required actions:
    1) Run a targeted verification for *this item* (derive steps from Dev outbox + acceptance criteria).
    2) Ensure this check exists in the regression checklist and keep it evergreen:
       - ${checklist_path:-'(no site checklist path available)'}
${step3_text}

    Deliverable:
    - Write a Verification Report with explicit APPROVE/BLOCK and evidence.
MD

  cat >"$qa_inbox_dir/README.md" <<MD
# QA unit test dispatch

## Scope
- QA seat: ${qa_agent}
- Completed dev item: ${item_id}
- Dev outbox evidence: ${outbox_path}
- Verification mode: targeted regression/unit verification for the changed surface only

## Acceptance criteria
1. QA reviews the completed dev item and derives a targeted verification plan from the dev outbox and any referenced acceptance criteria.
2. The regression checklist contains an evergreen entry for this completed item:
   - ${checklist_path:-'(no site checklist path available)'}
3. Infrastructure-scoped items use operator-audit checks only; they must not use site-audit, URL, or Playwright flows.
4. The QA outbox states an explicit APPROVE/BLOCK verdict and includes concrete evidence.

## Verification method
- Use the commands in \`command.md\` for the scoped QA check.
- Record exact commands and outputs in the outbox summary/body.
MD
}

queue_qa_full_regression_if_last_fix() {
  local item_id="$1" outbox_path="$2"

  # Trigger only for QA findings repair-loop items.
  if ! echo "$item_id" | grep -q -- '-qa-findings-'; then
    return 0
  fi

  local website qa_agent pending_count today slug qa_item qa_inbox_dir qa_outbox_file
  website="${CTX_WEBSITE:-}"
  if [ -z "$website" ] || [ "$website" = "*" ]; then
    return 0
  fi

  qa_agent="$(qa_agent_for_context)"
  if [ -z "$qa_agent" ]; then
    return 0
  fi

  # If there are still pending QA-findings items for this site in Dev inbox,
  # this was not the last fix item; skip full-regression queueing.
  pending_count="$({
    find "sessions/${AGENT_ID}/inbox" -mindepth 1 -maxdepth 1 -type d -name '*-qa-findings-*' -print 2>/dev/null \
      | while IFS= read -r d; do
          [ -f "$d/command.md" ] || continue
          if grep -qiE "Site label:|Base URL:" "$d/command.md"; then
            if grep -qi "$website" "$d/command.md"; then
              echo "$d"
            fi
          fi
        done
  } | wc -l | tr -d ' ')"

  if [ "${pending_count:-0}" -gt 0 ]; then
    return 0
  fi

  today="$(date +%Y%m%d)"
  slug="$(echo "$item_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-50)"
  qa_item="${today}-full-regression-${slug}"
  qa_inbox_dir="sessions/${qa_agent}/inbox/${qa_item}"
  qa_outbox_file="sessions/${qa_agent}/outbox/${qa_item}.md"

  if [ -d "$qa_inbox_dir" ] || [ -f "$qa_outbox_file" ]; then
    return 0
  fi

  mkdir -p "$qa_inbox_dir" 2>/dev/null || true
  printf '%s\n' "9" >"$qa_inbox_dir/roi.txt" 2>/dev/null || true

  cat >"$qa_inbox_dir/command.md" <<MD
- command: |
    Final full regression gate for release-cycle repair loop.

    - Product/site: ${website}
    - Triggered by completed Dev findings item: ${item_id}
    - Dev outbox evidence: ${outbox_path}

    Required actions:
    1) Run a full regression for this product/site (all required suites + scripted URL/route/permission checks).
    2) Update PASS/FAIL evidence and call out any remaining failures explicitly.
    3) If all required tests PASS, notify PM release coordinator that this product is ready for ship gate.
    4) If any test FAILS, notify Dev with concrete failing items and evidence, and continue the repair loop.

    Deliverable:
    - Outbox report with explicit APPROVE/BLOCK and links to evidence artifacts.
MD
}

# Dev -> QA handoff: when Dev completes, request targeted QA and add to regression list.
if [ "$status" = "done" ] && [[ "$AGENT_ID" == dev-* ]]; then
  notify_qa_unit_test_on_done "$next" "$out_file"
  queue_qa_full_regression_if_last_fix "$next" "$out_file"
fi

is_escalation_item_id() {
  # Avoid creating recursive escalation loops only for explicit escalation
  # artifacts, not for normal decision-routing "needs-*" inbox items.
  local item_id="$1"
  if echo "$item_id" | grep -qE '^[0-9]{8}-needs-escalated-'; then
    return 0
  fi
  if echo "$item_id" | grep -qE '^[0-9]{8}-clarify-escalation-'; then
    return 0
  fi
  if echo "$item_id" | grep -qE '(^|-)needs-escalated-'; then
    return 0
  fi
  if echo "$item_id" | grep -qE '(^|-)clarify-escalation-'; then
    return 0
  fi
  return 1
}

if [ "$status" = "blocked" ] || [ "$status" = "needs-info" ]; then
  LOGDIR="inbox/responses"
  mkdir -p "$LOGDIR"
  ts_iso="$(date -Iseconds)"
  ts_day="$(date +%Y%m%d)"
  daylog="$LOGDIR/blocked-${ts_day}.log"
  latest="$LOGDIR/blocked-latest.log"
  echo "[$ts_iso] ${AGENT_ID} blocked on ${next} (status=$status)" | tee -a "$daylog" > "$latest"

  # If the current inbox item is already an escalation artifact, do not create
  # further escalation items (prevents recursive queue growth).
  if is_escalation_item_id "$next"; then
    echo "[$ts_iso] ${AGENT_ID} escalation suppressed for recursive artifact ${next}" >> "$daylog"
    exit 0
  fi

  # CEO threads (ceo-copilot-2/3) already roll up to ceo-copilot and share CEO
  # oversight; avoid creating noisy supervisor inbox items for CEO-internal work.
  if [[ "$AGENT_ID" == ceo-copilot-* ]] && [ "$AGENT_ID" != "ceo-copilot" ]; then
    exit 0
  fi

  slug="$(echo "${next}" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-60)"

  # If the agent produced a formatting-only/empty response, do NOT escalate to
  # supervisor/CEO. Push back to the agent to rewrite first.
  if ! has_heading "Decision needed" "$out_file" || ! has_heading "Recommendation" "$out_file"; then
    clar_item="${INBOX_DIR}/$(date +%Y%m%d)-clarify-escalation-${slug}"
    if [ ! -d "$clar_item" ]; then
      mkdir -p "$clar_item"
      cat > "$clar_item/command.md" <<MD
- command: |
    Clarify escalation quality (required):

    Your last outbox for item ${next} was escalated up-chain, but it is missing required context.

    Update your outbox to include:
    - Product context: website/module/role/feature/work item
    - ## Decision needed
    - ## Recommendation (with tradeoffs)

    Reference:
    - Original outbox: sessions/${AGENT_ID}/outbox/$(basename "$out_file")
    - If/when rewritten with Decision needed + Recommendation, escalation will be created automatically.
MD
    fi

    # Exit early: avoid generating noisy Waiting-on-Keith items for formatting failures.
    exit 0
  fi

  # Escalate up-chain: create a supervisor inbox item with extracted needs.
  supervisor="$(./scripts/supervisor-for.sh "$AGENT_ID")"

  # Board routing: when supervisor is "board" (human owner), write to inbox/commands/
  # via ceo-queue.sh rather than creating a session inbox item that loops back.
  # Distinguish informed (CEO has a plan, FYI only) vs blocked (needs a decision/action).
  if [ "$supervisor" = "board" ]; then
    board_topic="board-escalation-${status}-${slug:0:40}"
    board_text="$(
      echo "[${status^^}] ${AGENT_ID} → Board escalation"
      echo "Item: ${next}"
      echo "Agent: ${AGENT_ID} | Status: ${status}"
      echo ""
      extract_section "Decision needed" "$out_file" | head -10
      echo ""
      echo "Recommendation:"
      extract_section "Recommendation" "$out_file" | head -10
      echo ""
      echo "Needs from Board:"
      awk 'BEGIN{p=0} /^## Needs from (Supervisor|CEO|Board)$/{p=1;next} /^## /{p=0} {if(p) print}' "$out_file" | head -20
      echo ""
      echo "Outbox: sessions/${AGENT_ID}/outbox/$(basename "$out_file")"
    )"
    ./scripts/ceo-queue.sh "$next" "$board_topic" "$board_text" 2>/dev/null || true
    # No further loop — board queue written, we're done escalating.
    exit 0
  fi

  # Self-loop guard: if supervisor resolves to the same agent (e.g., YAML misconfiguration),
  # route to Board instead of re-queuing to the same seat inbox.
  if [ "$supervisor" = "$AGENT_ID" ]; then
    board_topic="board-self-loop-${AGENT_ID}-${slug:0:40}"
    board_text="$(
      echo "[SELF-LOOP DETECTED] ${AGENT_ID} supervisor resolved to itself — routing to Board"
      echo "Item: ${next}"
      echo "Agent: ${AGENT_ID} | Status: ${status}"
      echo ""
      echo "Action required: verify supervisor field in org-chart/agents/agents.yaml for ${AGENT_ID}"
      echo ""
      extract_section "Decision needed" "$out_file" | head -10
      echo ""
      echo "Outbox: sessions/${AGENT_ID}/outbox/$(basename "$out_file")"
    )"
    ./scripts/ceo-queue.sh "$next" "$board_topic" "$board_text" 2>/dev/null || true
    exit 0
  fi

  SUP_INBOX="sessions/${supervisor}/inbox"
  mkdir -p "$SUP_INBOX"
  sup_item="${SUP_INBOX}/$(date +%Y%m%d)-needs-${AGENT_ID}-${slug}"
  if [ ! -d "$sup_item" ]; then
    mkdir -p "$sup_item"
    {
      echo "# Escalation: ${AGENT_ID} is ${status}"
      echo
      echo "- Website: ${CTX_WEBSITE}"
      echo "- Module: ${CTX_MODULE}"
      echo "- Role: ${CTX_ROLE}"
      echo "- Agent: ${AGENT_ID}"
      echo "- Item: ${next}"
      echo "- Status: ${status}"
      echo "- Supervisor: ${supervisor}"
      echo "- Outbox file: sessions/${AGENT_ID}/outbox/$(basename "$out_file")"
      echo "- Created: $(date -Iseconds)"
      echo
      echo "## Decision needed"
      extract_section "Decision needed" "$out_file"
      echo
      echo "## Recommendation"
      extract_section "Recommendation" "$out_file"
      echo
      echo "## ROI estimate"
      extract_section "ROI estimate" "$out_file"
      echo
      echo "## Needs from Supervisor (up-chain)"
      awk 'BEGIN{p=0}
        /^## Needs from (Supervisor|CEO|Board)$/{p=1;next}
        /^## /{p=0}
        {if(p) print}
      ' "$out_file" | sed -n '1,30p'
      echo
      echo "## Blockers"
      awk 'BEGIN{p=0} /^## Blockers/{p=1;next} /^## /{p=0} {if(p) print}' "$out_file" | sed -n '1,30p'
      echo
      echo "## Full outbox (context)"
      sed -n '1,200p' "$out_file"
    } > "$sup_item/README.md"
  fi

  # 3x escalation rule: if an agent is blocked/needs-info three times in a row,
  # escalate to supervisor's supervisor (superior).
  # Skip when supervisor is already "board" — already at the top of the chain.
  if [ "$supervisor" = "board" ]; then
    exit 0
  fi
  streak_file="tmp/escalation-streaks/${AGENT_ID}.json"
  mkdir -p "$(dirname "$streak_file")" 2>/dev/null || true
  superior="$(./scripts/supervisor-for.sh "$supervisor")"
  base_outbox="$(basename "$out_file" .md)"
  streak="$(
    python3 - "$streak_file" "$base_outbox" <<'PY'
import json, sys, pathlib
path = pathlib.Path(sys.argv[1])
key = sys.argv[2]
data = {"streak": 0, "last_superior_escalation": ""}
if path.exists():
    try:
        data.update(json.loads(path.read_text(encoding="utf-8")))
    except Exception:
        pass
data["streak"] = int(data.get("streak") or 0) + 1
should = (data["streak"] >= 3 and data.get("last_superior_escalation") != key)
data["should_escalate"] = bool(should)
path.parent.mkdir(parents=True, exist_ok=True)
path.write_text(json.dumps(data, indent=2, sort_keys=True) + "\n", encoding="utf-8")
print("1" if should else "0")
PY
  )"

  if [ "$streak" = "1" ] && [ -n "$superior" ] && [ "$superior" != "$supervisor" ] && [ "$superior" != "board" ]; then
    SUP2_INBOX="sessions/${superior}/inbox"
    mkdir -p "$SUP2_INBOX"
    sup2_item="${SUP2_INBOX}/$(date +%Y%m%d)-needs-escalated-${AGENT_ID}-${slug}"
    if [ ! -d "$sup2_item" ]; then
      mkdir -p "$sup2_item"
      {
        echo "# Superior escalation (3x): ${AGENT_ID} is ${status}"
        echo
        echo "- Agent: ${AGENT_ID}"
        echo "- Item: ${next}"
        echo "- Status: ${status}"
        echo "- Supervisor: ${supervisor}"
        echo "- Superior: ${superior}"
        echo "- Outbox file: sessions/${AGENT_ID}/outbox/$(basename "$out_file")"
        echo "- Created: $(date -Iseconds)"
        echo
        echo "## Context"
        echo "This agent has produced 3 blocked/needs-info escalations in a row."
        echo
        echo "## Full outbox (context)"
        sed -n '1,200p' "$out_file"
      } > "$sup2_item/README.md"
    fi

    # Reset streak after superior escalation so we don't spam.
    python3 - "$streak_file" "$base_outbox" <<'PY'
import json, sys, pathlib
path = pathlib.Path(sys.argv[1])
key = sys.argv[2]
data = {"streak": 0, "last_superior_escalation": key}
path.parent.mkdir(parents=True, exist_ok=True)
path.write_text(json.dumps(data, indent=2, sort_keys=True) + "\n", encoding="utf-8")
PY
  fi
else
  # Reset escalation streak on progress so "3x in a row" means consecutive blocked/needs-info.
  streak_file="${ART_DIR}/escalation-streak.json"
  python3 - "$streak_file" <<'PY'
import json, sys, pathlib
path = pathlib.Path(sys.argv[1])
data = {"streak": 0, "last_superior_escalation": ""}
if path.exists():
    try:
        prev = json.loads(path.read_text(encoding="utf-8"))
        data["last_superior_escalation"] = prev.get("last_superior_escalation", "")
    except Exception:
        pass
path.parent.mkdir(parents=True, exist_ok=True)
path.write_text(json.dumps(data, indent=2, sort_keys=True) + "\n", encoding="utf-8")
PY
fi

# Archive inbox item to artifacts after producing outbox, except in-progress
# items which should remain queued for continued execution.
if [ "$status" = "in-progress" ]; then
  printf '%s\n' "$(date -Iseconds)" > "$inbox_item/.last-progress-at" 2>/dev/null || true
else
  mkdir -p "$ART_DIR"
  archive_inbox_item "$inbox_item" "$next" "$ART_DIR"
fi

# Completion-based anti-staleness: bump other agents' queued ROI by +1.
bump_other_agents_queued_roi "$AGENT_ID" || true

# Gate transition routing: detect QA BLOCK/APPROVE signals and create follow-on
# inbox items. Must run after archive so the outbox file is readable.
if [ -x "$ROOT_DIR/scripts/route-gate-transitions.sh" ]; then
  "$ROOT_DIR/scripts/route-gate-transitions.sh" "$AGENT_ID" "$next" 2>/dev/null || true
fi

echo "${AGENT_ID}: processed ${next}"
