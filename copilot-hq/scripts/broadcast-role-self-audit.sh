#!/usr/bin/env bash
set -euo pipefail

# Broadcast a role/self-audit prompt to all agents by creating a per-agent inbox folder:
#   sessions/<agent-id>/inbox/<YYYYMMDD>-role-self-audit/{command.md,roi.txt}
#
# This is intentionally session-local (not inbox/commands) because inbox/commands only supports
# explicit PM dispatch today.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

TOPIC="role-self-audit"
DATE_YYYYMMDD="$(date +%Y%m%d)"
ROI_DEFAULT="5"

usage() {
  cat <<EOF
Usage: $0 [--date YYYYMMDD] [--roi N]

Options:
  --date YYYYMMDD   Override date prefix used in inbox folder name (default: today)
  --roi N           ROI value to write to roi.txt (default: ${ROI_DEFAULT})
EOF
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --date)
      DATE_YYYYMMDD="${2:?--date requires YYYYMMDD}"
      shift 2
      ;;
    --roi)
      ROI_DEFAULT="${2:?--roi requires a number}"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "ERROR: unknown arg: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

extract_role() {
  local agent_id="$1"
  awk -v id="$agent_id" '
    $0 ~ "- id: "id"$" {found=1; next}
    found && $1=="role:" {print $2; exit}
    found && $0 ~ "- id:" {exit}
  ' org-chart/agents/agents.yaml
}

write_command() {
  local agent_id="$1"
  local role="$2"

  cat <<EOF
- command: |
    Self-audit (required): instructions compliance + role process improvements

    Review:
    - Org-wide: org-chart/org-wide.instructions.md
    - Your seat: org-chart/agents/instructions/${agent_id}.instructions.md
    - Your role: org-chart/roles/${role}.instructions.md (and org-chart/roles/${role}.md)
    - Relevant runbooks in runbooks/

    Assess:
    1) Are you following your instructions and role responsibilities? Where do you deviate and why?
    2) Given our current stack (HQ inbox loops, sessions artifacts, local LLM runner/orchestrator), what best practices should you adopt?
    3) What role-specific processes/checklists/automation would measurably improve throughput/quality for your role?

    Deliverables:
    - Write an outbox report: sessions/${agent_id}/outbox/${DATE_YYYYMMDD}-${TOPIC}.md
      Include: current workflow, gaps vs instructions, 1–3 process changes to adopt now, and any doc/script changes needed.
    - If a change requires supervisor/CEO action, include:
      - ## Decision needed
      - ## Recommendation (with tradeoffs)
EOF
}

created=0
skipped=0
missing_role_doc=0

shopt -s nullglob
session_dirs=(sessions/*)
shopt -u nullglob
for session_dir in "${session_dirs[@]}"; do
  agent_id="$(basename "$session_dir")"
  if [[ "$agent_id" == "shared-context" ]]; then
    continue
  fi

  inbox_dir="$session_dir/inbox"
  if [[ ! -d "$inbox_dir" ]]; then
    continue
  fi

  role="$(extract_role "$agent_id" || true)"
  if [[ -z "$role" ]]; then
    role="software-developer"
  fi

  if [[ ! -f "org-chart/roles/${role}.instructions.md" ]]; then
    missing_role_doc=$((missing_role_doc+1))
  fi

  target="$inbox_dir/${DATE_YYYYMMDD}-${TOPIC}"
  if [[ -e "$target" ]]; then
    skipped=$((skipped+1))
    continue
  fi

  mkdir -p "$target"
  write_command "$agent_id" "$role" > "$target/command.md"
  printf '%s\n' "$ROI_DEFAULT" > "$target/roi.txt"
  created=$((created+1))
done

echo "Created: $created"
echo "Skipped (already existed): $skipped"
if [ "$missing_role_doc" -gt 0 ]; then
  echo "WARN: missing role doc for $missing_role_doc agent(s) (role name didn't map to org-chart/roles/*)" >&2
fi
