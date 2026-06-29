#!/usr/bin/env bash
set -euo pipefail

# prod-troubleshoot-assistant.sh
#
# Gathers production diagnostics and asks Bedrock assistant for troubleshooting
# guidance. This script does not execute remediation commands.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/prod-troubleshoot-assistant.sh [site] [question]
  ./scripts/prod-troubleshoot-assistant.sh [site] [question] --execute-approved [--yes]

Examples:
  ./scripts/prod-troubleshoot-assistant.sh forseti "Why are suggestions stuck in new?"
  ./scripts/prod-troubleshoot-assistant.sh forseti "Investigate runtime drift" --execute-approved
  ./scripts/prod-troubleshoot-assistant.sh dungeoncrawler "Check release blockers" --execute-approved --yes

Notes:
  - --execute-approved only executes commands emitted as: APPROVED_CMD: <command>
  - Each command must pass a strict allowlist (read-only/operational-safe checks).
  - --yes auto-confirms each approved command; otherwise interactive prompt is required.
USAGE
}

SITE="forseti"
QUESTION="Investigate current production runtime health and suggest next actions."
EXECUTE_APPROVED=0
AUTO_YES=0

if [ "$#" -gt 0 ] && [ "${1:-}" != "--execute-approved" ] && [ "${1:-}" != "--yes" ] && [ "${1:-}" != "-h" ] && [ "${1:-}" != "--help" ]; then
  SITE="$1"
  shift
fi

if [ "$#" -gt 0 ] && [ "${1:-}" != "--execute-approved" ] && [ "${1:-}" != "--yes" ] && [ "${1:-}" != "-h" ] && [ "${1:-}" != "--help" ]; then
  QUESTION="$1"
  shift
fi

while [ "$#" -gt 0 ]; do
  case "$1" in
    --execute-approved)
      EXECUTE_APPROVED=1
      ;;
    --yes)
      AUTO_YES=1
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "ERROR: unknown argument: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
  shift
done

if [ ! -x "./scripts/bedrock-assist.sh" ]; then
  echo "ERROR: scripts/bedrock-assist.sh is required" >&2
  exit 1
fi

tmpfile="$(mktemp)"
trap 'rm -f "$tmpfile"' EXIT
assistant_out="$(mktemp)"
trap 'rm -f "$tmpfile" "$assistant_out"' EXIT

is_safe_allowed_command() {
  local cmd="$1"

  # Block shell metacharacters to prevent chaining/injection.
  if printf '%s' "$cmd" | grep -qE '([;&|<>`]|\$\()'; then
    return 1
  fi

  # Strict allowlist: read-only diagnostics and safe HQ status checks only.
  [[ "$cmd" =~ ^\./scripts/verify-hq-runtime\.sh(\ --strict)?$ ]] && return 0
  [[ "$cmd" =~ ^\./scripts/hq-automation\.sh\ (status|converge)$ ]] && return 0
  [[ "$cmd" =~ ^\./scripts/org-control\.sh\ status(\ --one-line)?$ ]] && return 0
  [[ "$cmd" =~ ^\./scripts/hq-blockers\.sh(\ count)?$ ]] && return 0
  [[ "$cmd" =~ ^tail\ -n\ [0-9]+\ inbox/responses/[A-Za-z0-9._/-]+$ ]] && return 0
  [[ "$cmd" =~ ^systemctl\ --user\ status\ [A-Za-z0-9_.@-]+$ ]] && return 0
  [[ "$cmd" =~ ^journalctl\ --user\ -u\ [A-Za-z0-9_.@-]+\ -n\ [0-9]+(\ --no-pager)?$ ]] && return 0
  [[ "$cmd" =~ ^ls\ -la?\ [A-Za-z0-9._/-]+$ ]] && return 0
  [[ "$cmd" =~ ^cat\ [A-Za-z0-9._/-]+$ ]] && return 0

  return 1
}

run_approved_commands() {
  local src="$1"
  mapfile -t cmds < <(grep -E '^APPROVED_CMD:[[:space:]]+' "$src" | sed -E 's/^APPROVED_CMD:[[:space:]]+//')

  if [ "${#cmds[@]}" -eq 0 ]; then
    echo "[prod-troubleshoot] no APPROVED_CMD lines found; nothing to execute" >&2
    return 0
  fi

  echo "[prod-troubleshoot] reviewing ${#cmds[@]} approved command(s)" >&2
  local idx=0
  for cmd in "${cmds[@]}"; do
    idx=$((idx + 1))
    if ! is_safe_allowed_command "$cmd"; then
      echo "[prod-troubleshoot] SKIP (not allowlisted): $cmd" >&2
      continue
    fi

    if [ "$AUTO_YES" != "1" ]; then
      printf '[prod-troubleshoot] Execute [%d/%d]? %s [y/N]: ' "$idx" "${#cmds[@]}" "$cmd" >&2
      read -r reply
      case "${reply:-}" in
        y|Y|yes|YES) ;;
        *)
          echo "[prod-troubleshoot] SKIP (operator declined): $cmd" >&2
          continue
          ;;
      esac
    fi

    echo "[prod-troubleshoot] RUN: $cmd" >&2
    bash -lc "$cmd" || echo "[prod-troubleshoot] WARN: command failed: $cmd" >&2
  done
}

{
  echo "# Production diagnostics snapshot"
  echo "- Timestamp: $(date -Iseconds)"
  echo "- Host: $(hostname 2>/dev/null || echo unknown)"
  echo "- Site context: $SITE"
  echo

  echo "## org-control"
  ./scripts/org-control.sh status --one-line 2>&1 || true
  echo

  echo "## hq-automation status"
  ./scripts/hq-automation.sh status 2>&1 || true
  echo

  echo "## runtime verify (bedrock mode)"
  env HQ_AGENTIC_BACKEND=bedrock BEDROCK_ASSIST_SCRIPT="$ROOT_DIR/scripts/bedrock-assist.sh" ./scripts/verify-hq-runtime.sh 2>&1 || true
  echo

  echo "## latest orchestrator log tail"
  tail -n 60 inbox/responses/orchestrator-latest.log 2>&1 || true
  echo

  echo "## watchdog log tail"
  tail -n 60 inbox/responses/hq-automation-watchdog.log 2>&1 || true
  echo

  echo "## blockers"
  ./scripts/hq-blockers.sh 2>&1 || true
  echo
} > "$tmpfile"

PROMPT="You are a production operations assistant for copilot-sessions-hq.

User request:
$QUESTION

Use only the diagnostics below. Provide:
1) Current health summary
2) Most likely root causes (ranked)
3) Exact shell commands to verify each root cause
4) Safe remediation sequence (ordered), clearly mark any destructive actions
5) Rollback guidance if remediation fails

If and only if there are safe commands worth running now, emit machine-readable lines exactly in this format:
APPROVED_CMD: <single shell command>

Rules for APPROVED_CMD lines:
- Only include read-only or low-risk operational checks.
- Do not include pipes, redirects, command chaining, or subshells.
- One command per line.

Diagnostics:
$(cat "$tmpfile")"

if ! BEDROCK_OPERATION=prod_troubleshoot_assistant ./scripts/bedrock-assist.sh "$SITE" "$PROMPT" | tee "$assistant_out"; then
  echo "[prod-troubleshoot] WARN: bedrock-assist returned non-zero status; continuing with captured output" >&2
fi

if [ ! -s "$assistant_out" ]; then
  echo "[prod-troubleshoot] ERROR: assistant produced no output" >&2
  exit 1
fi

if [ "$EXECUTE_APPROVED" = "1" ]; then
  run_approved_commands "$assistant_out"
fi
