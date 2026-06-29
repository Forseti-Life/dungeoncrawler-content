#!/usr/bin/env bash
set -euo pipefail

# hq-bedrock-chat.sh — HQ-focused Bedrock free-form assistant wrapper.
#
# This wrapper composes a deterministic internal operations persona prompt from
# copilot-hq instruction files, then delegates model invocation to
# scripts/bedrock-assist.sh.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/hq-bedrock-chat.sh [site] "prompt text"
  echo "prompt text" | ./scripts/hq-bedrock-chat.sh [site]

Defaults:
  site: forseti
  agent id: ceo-copilot

Environment:
  HQ_BEDROCK_AGENT_ID            Agent seat context (default: ceo-copilot)
  HQ_BEDROCK_MAX_FILE_LINES      Lines to read from each instructions file (default: 220)
  HQ_BEDROCK_HISTORY_FILE         Optional rolling memory file to include recent context
  HQ_BEDROCK_HISTORY_LINES        Lines to tail from memory file (default: 180)
  HQ_BEDROCK_SYSTEM_PROMPT_NODE_ID  Optional prompt node id override forwarded to bedrock-assist
  HQ_BEDROCK_OPERATION           Operation label (default: hq_freeform_chat)
  BEDROCK_ASSIST_SCRIPT          Override delegate script path (default: ./scripts/bedrock-assist.sh)
USAGE
}

SITE="${1:-forseti}"
PROMPT=""

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
  usage
  exit 0
fi

if [ "$#" -ge 2 ]; then
  shift
  PROMPT="$*"
elif [ "$#" -eq 1 ]; then
  shift
  if [ ! -t 0 ]; then
    PROMPT="$(cat)"
  fi
else
  if [ ! -t 0 ]; then
    PROMPT="$(cat)"
  fi
fi

if [ -z "$(printf '%s' "$PROMPT" | tr -d '[:space:]')" ]; then
  echo "ERROR: prompt is required" >&2
  usage >&2
  exit 2
fi

ASSIST_SCRIPT="${BEDROCK_ASSIST_SCRIPT:-$ROOT_DIR/scripts/bedrock-assist.sh}"
if [ ! -x "$ASSIST_SCRIPT" ]; then
  echo "ERROR: bedrock assistant script missing or not executable: $ASSIST_SCRIPT" >&2
  exit 2
fi

AGENT_ID="${HQ_BEDROCK_AGENT_ID:-ceo-copilot}"
MAX_FILE_LINES="${HQ_BEDROCK_MAX_FILE_LINES:-220}"
HISTORY_FILE="${HQ_BEDROCK_HISTORY_FILE:-}"
HISTORY_LINES="${HQ_BEDROCK_HISTORY_LINES:-180}"
OPERATION="${HQ_BEDROCK_OPERATION:-hq_freeform_chat}"

if ! [[ "$MAX_FILE_LINES" =~ ^[0-9]+$ ]]; then
  MAX_FILE_LINES=220
fi

if ! [[ "$HISTORY_LINES" =~ ^[0-9]+$ ]]; then
  HISTORY_LINES=180
fi

safe_read_file() {
  local path="$1"
  if [ -f "$path" ]; then
    {
      echo ""
      echo "--- FILE: $path ---"
      sed -n "1,${MAX_FILE_LINES}p" "$path"
    }
  fi
}

read_history_tail() {
  local path="$1"
  if [ -n "$path" ] && [ -f "$path" ]; then
    {
      echo ""
      echo "--- RECENT CONVERSATION MEMORY ($path) ---"
      tail -n "$HISTORY_LINES" "$path"
    }
  fi
}

agent_role() {
  python3 - "$AGENT_ID" <<'PY'
import re
import sys
from pathlib import Path

agent_id = sys.argv[1]
p = Path("org-chart/agents/agents.yaml")
if not p.exists():
    print("")
    raise SystemExit(0)

in_item = False
role = ""
for line in p.read_text(encoding="utf-8", errors="ignore").splitlines():
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

PREFIX=$'You are the internal Forseti HQ operations assistant for production administration.\n'
PREFIX+=$'You are NOT a public website support persona.\n'
PREFIX+=$'Primary objective: help with production diagnostics, script execution, deploy/runtime health, and concrete operator actions.\n'
PREFIX+=$'When uncertain, ask targeted follow-up questions and include exact commands.\n'
PREFIX+=$'Keep responses concise, actionable, and internally focused.\n\n'
PREFIX+="Active seat context: ${AGENT_ID}"

CONTEXT=""
CONTEXT+="$(safe_read_file "org-chart/org-wide.instructions.md")"
CONTEXT+="$(safe_read_file "org-chart/DECISION_OWNERSHIP_MATRIX.md")"
CONTEXT+="$(safe_read_file "org-chart/ownership/file-ownership.md")"
CONTEXT+="$(safe_read_file "org-chart/roles/ceo.instructions.md")"
CONTEXT+="$(safe_read_file "org-chart/agents/instructions/${AGENT_ID}.instructions.md")"
HISTORY_CONTEXT="$(read_history_tail "$HISTORY_FILE")"

if [ -n "$ROLE" ]; then
  CONTEXT+="$(safe_read_file "org-chart/roles/${ROLE}.instructions.md")"
fi

COMPOSED_PROMPT="$PREFIX"
if [ -n "$(printf '%s' "$CONTEXT" | tr -d '[:space:]')" ]; then
  COMPOSED_PROMPT+=$'\n\nInternal instructions context follows. Apply this context while answering.\n'
  COMPOSED_PROMPT+="$CONTEXT"
fi
if [ -n "$(printf '%s' "$HISTORY_CONTEXT" | tr -d '[:space:]')" ]; then
  COMPOSED_PROMPT+=$'\n\nUse this recent transcript to preserve short-term conversation continuity.\n'
  COMPOSED_PROMPT+="$HISTORY_CONTEXT"
fi
COMPOSED_PROMPT+=$'\n\nOperator request:\n'
COMPOSED_PROMPT+="$PROMPT"

echo "[hq-bedrock-chat] agent=$AGENT_ID site=$SITE op=$OPERATION" >&2

if [ -n "${HQ_BEDROCK_SYSTEM_PROMPT_NODE_ID:-}" ]; then
  BEDROCK_OPERATION="$OPERATION" \
  BEDROCK_SYSTEM_PROMPT_NODE_ID="$HQ_BEDROCK_SYSTEM_PROMPT_NODE_ID" \
  "$ASSIST_SCRIPT" "$SITE" "$COMPOSED_PROMPT"
else
  BEDROCK_OPERATION="$OPERATION" "$ASSIST_SCRIPT" "$SITE" "$COMPOSED_PROMPT"
fi
