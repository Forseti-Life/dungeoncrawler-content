#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORKSPACE_ROOT="$(git -C "$ROOT_DIR" rev-parse --show-toplevel 2>/dev/null || cd "$ROOT_DIR/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/command-routing.sh
source "$ROOT_DIR/scripts/lib/command-routing.sh"

# Load local node identity if present (overridable by env vars)
IDENTITY_FILE="$ROOT_DIR/node-identity.conf"
if [[ -f "$IDENTITY_FILE" ]]; then
  # shellcheck source=../node-identity.conf
  source "$IDENTITY_FILE"
fi

TARGET_NAME="${DEV_SYNC_TARGET:-${NODE_ID:-dev-laptop}}"
DEFAULT_AGENT="${DEV_SYNC_DEFAULT_AGENT:-${NODE_DEFAULT_AGENT:-dev-forseti}}"
AUTO_PULL="${DEV_SYNC_AUTO_PULL:-1}"
EXECUTE_DEFAULT="${DEV_SYNC_EXECUTE:-0}"
AUTO_COMMIT="${DEV_SYNC_AUTO_COMMIT:-0}"
AUTO_PUSH="${DEV_SYNC_AUTO_PUSH:-0}"
ALLOW_DIRTY="${DEV_SYNC_ALLOW_DIRTY:-0}"
MAX_COMMANDS="${DEV_SYNC_MAX_COMMANDS:-1}"

git_is_dirty() {
  [ -n "$(git -C "$WORKSPACE_ROOT" status --porcelain 2>/dev/null || true)" ]
}

current_branch() {
  git -C "$WORKSPACE_ROOT" rev-parse --abbrev-ref HEAD 2>/dev/null || echo main
}

ensure_branch() {
  local target_branch="$1"
  [ -n "$target_branch" ] || return 0
  local current
  current="$(current_branch)"
  if [ "$current" = "$target_branch" ]; then
    return 0
  fi
  if git -C "$WORKSPACE_ROOT" show-ref --verify --quiet "refs/heads/$target_branch"; then
    git -C "$WORKSPACE_ROOT" checkout "$target_branch" >/dev/null 2>&1
  else
    git -C "$WORKSPACE_ROOT" checkout -b "$target_branch" >/dev/null 2>&1
  fi
}

maybe_git_pull() {
  if [ "$AUTO_PULL" != "1" ]; then
    return 0
  fi
  if [ "$ALLOW_DIRTY" != "1" ] && git_is_dirty; then
    echo "skip: workspace dirty; refusing auto-pull" >&2
    exit 4
  fi
  git -C "$WORKSPACE_ROOT" pull --rebase --autostash >/dev/null 2>&1 || true
}

maybe_commit_and_push() {
  local topic="$1"
  local work_item="$2"
  local requested_branch="$3"

  if ! git_is_dirty; then
    return 0
  fi

  if [ "$AUTO_COMMIT" = "1" ]; then
    ensure_branch "$requested_branch"
    git -C "$WORKSPACE_ROOT" add -A
    git -C "$WORKSPACE_ROOT" commit -m "dev-sync: ${topic} (${work_item:-no-work-item})" >/dev/null 2>&1 || true
  fi

  if [ "$AUTO_PUSH" = "1" ]; then
    local branch
    branch="$(current_branch)"
    git -C "$WORKSPACE_ROOT" push -u origin "$branch" >/dev/null 2>&1 || true
  fi
}

mkdir -p inbox/processed

if ! [[ "$MAX_COMMANDS" =~ ^[0-9]+$ ]] || [ "$MAX_COMMANDS" -lt 1 ]; then
  MAX_COMMANDS=1
fi

maybe_git_pull

shopt -s nullglob
files=(inbox/commands/*.md)
shopt -u nullglob

processed_count=0

for f in "${files[@]}"; do
  if ! command_matches_local_target "$f" "$TARGET_NAME"; then
    continue
  fi

  target_agent="$(command_target_agent "$f")"
  target_agent="${target_agent:-$DEFAULT_AGENT}"

  # If NODE_ACTIVE_AGENTS is set, skip commands not addressed to this node's agents.
  if [[ -n "${NODE_ACTIVE_AGENTS:-}" ]]; then
    _agent_allowed=0
    for _a in $NODE_ACTIVE_AGENTS; do
      if [[ "$target_agent" == "$_a" ]]; then _agent_allowed=1; break; fi
    done
    if [[ "$_agent_allowed" -eq 0 ]]; then
      echo "skip (agent $target_agent not active on this node): $(basename "$f")"
      continue
    fi
  fi

  topic="$(command_field "$f" "topic")"
  topic="$(sanitize_topic_slug "${topic:-$(basename "$f" .md)}")"
  work_item="$(command_field "$f" "work_item")"
  requested_branch="$(command_field "$f" "branch")"
  execute_mode="$(command_field "$f" "execute")"
  execute_mode="${execute_mode:-}"

  ./scripts/dispatch-dev-request.sh "$target_agent" "$f" >/dev/null
  mv "$f" "inbox/processed/$(basename "$f")"
  echo "claimed: $(basename "$f") -> ${target_agent}"

  should_execute="$EXECUTE_DEFAULT"
  if [ "$execute_mode" = "dispatch-only" ]; then
    should_execute=0
  elif [ "$execute_mode" = "now" ]; then
    should_execute=1
  fi

  if [ "$should_execute" = "1" ]; then
    ./scripts/agent-exec-next.sh "$target_agent" || true
  fi

  maybe_commit_and_push "$topic" "$work_item" "$requested_branch"

  processed_count=$((processed_count + 1))
  if [ "$processed_count" -ge "$MAX_COMMANDS" ]; then
    break
  fi
done

if [ "$processed_count" -eq 0 ]; then
  echo "No ${TARGET_NAME} commands pending."
  exit 2
fi

echo "Processed ${processed_count} ${TARGET_NAME} command(s)."
