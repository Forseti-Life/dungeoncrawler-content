#!/usr/bin/env bash
# Shared helpers for detecting unresolved merge state in the current repo.

# shellcheck shell=bash

merge_health_reset() {
  MERGE_HEALTH_IN_GIT_REPO=0
  MERGE_HEALTH_HAS_ISSUES=0
  MERGE_HEALTH_MERGE_HEAD=0
  MERGE_HEALTH_CHERRY_PICK_HEAD=0
  MERGE_HEALTH_REVERT_HEAD=0
  MERGE_HEALTH_REBASE_IN_PROGRESS=0
  MERGE_HEALTH_UNMERGED_COUNT=0
  MERGE_HEALTH_TRACKED_CHANGE_COUNT=0
  MERGE_HEALTH_UNTRACKED_COUNT=0
  MERGE_HEALTH_GIT_DIR=""
  MERGE_HEALTH_SUMMARY=""
  MERGE_HEALTH_UNMERGED_FILES=()
  MERGE_HEALTH_TRACKED_CHANGE_FILES=()
  MERGE_HEALTH_UNTRACKED_FILES=()
}

merge_health_scan() {
  local repo_root="${1:-.}"
  merge_health_reset

  if ! git -C "$repo_root" rev-parse --git-dir >/dev/null 2>&1; then
    MERGE_HEALTH_SUMMARY="not a git repository"
    return 0
  fi

  MERGE_HEALTH_IN_GIT_REPO=1
  MERGE_HEALTH_GIT_DIR="$(git -C "$repo_root" rev-parse --git-dir 2>/dev/null || echo "")"

  if git -C "$repo_root" rev-parse -q --verify MERGE_HEAD >/dev/null 2>&1; then
    MERGE_HEALTH_MERGE_HEAD=1
    MERGE_HEALTH_HAS_ISSUES=1
  fi

  if git -C "$repo_root" rev-parse -q --verify CHERRY_PICK_HEAD >/dev/null 2>&1; then
    MERGE_HEALTH_CHERRY_PICK_HEAD=1
    MERGE_HEALTH_HAS_ISSUES=1
  fi

  if git -C "$repo_root" rev-parse -q --verify REVERT_HEAD >/dev/null 2>&1; then
    MERGE_HEALTH_REVERT_HEAD=1
    MERGE_HEALTH_HAS_ISSUES=1
  fi

  if [ -d "$(git -C "$repo_root" rev-parse --git-path rebase-merge 2>/dev/null || echo "")" ] || \
     [ -d "$(git -C "$repo_root" rev-parse --git-path rebase-apply 2>/dev/null || echo "")" ]; then
    MERGE_HEALTH_REBASE_IN_PROGRESS=1
    MERGE_HEALTH_HAS_ISSUES=1
  fi

  while IFS= read -r path; do
    [ -n "$path" ] || continue
    MERGE_HEALTH_UNMERGED_FILES+=("$path")
  done < <(git -C "$repo_root" diff --name-only --diff-filter=U 2>/dev/null || true)

  MERGE_HEALTH_UNMERGED_COUNT="${#MERGE_HEALTH_UNMERGED_FILES[@]}"
  if [ "$MERGE_HEALTH_UNMERGED_COUNT" -gt 0 ]; then
    MERGE_HEALTH_HAS_ISSUES=1
  fi

  while IFS= read -r line; do
    [ -n "$line" ] || continue
    local status="${line:0:2}"
    local path="${line:3}"
    case "$status" in
      "??")
        MERGE_HEALTH_UNTRACKED_FILES+=("$path")
        ;;
      *U|U*|AA|DD)
        continue
        ;;
      *)
        MERGE_HEALTH_TRACKED_CHANGE_FILES+=("$path")
        ;;
    esac
  done < <(git -C "$repo_root" status --porcelain 2>/dev/null || true)

  MERGE_HEALTH_TRACKED_CHANGE_COUNT="${#MERGE_HEALTH_TRACKED_CHANGE_FILES[@]}"
  MERGE_HEALTH_UNTRACKED_COUNT="${#MERGE_HEALTH_UNTRACKED_FILES[@]}"
  if [ "$MERGE_HEALTH_TRACKED_CHANGE_COUNT" -gt 0 ]; then
    MERGE_HEALTH_HAS_ISSUES=1
  fi

  if [ "$MERGE_HEALTH_HAS_ISSUES" -eq 0 ]; then
    MERGE_HEALTH_SUMMARY="no active merge conflicts, unfinished integration state, or dirty tracked changes"
    return 0
  fi

  local summary_parts=()
  if [ "$MERGE_HEALTH_MERGE_HEAD" -eq 1 ]; then
    summary_parts+=("MERGE_HEAD present")
  fi
  if [ "$MERGE_HEALTH_REBASE_IN_PROGRESS" -eq 1 ]; then
    summary_parts+=("rebase in progress")
  fi
  if [ "$MERGE_HEALTH_CHERRY_PICK_HEAD" -eq 1 ]; then
    summary_parts+=("CHERRY_PICK_HEAD present")
  fi
  if [ "$MERGE_HEALTH_REVERT_HEAD" -eq 1 ]; then
    summary_parts+=("REVERT_HEAD present")
  fi
  if [ "$MERGE_HEALTH_UNMERGED_COUNT" -gt 0 ]; then
    summary_parts+=("${MERGE_HEALTH_UNMERGED_COUNT} unmerged file(s)")
  fi
  if [ "$MERGE_HEALTH_TRACKED_CHANGE_COUNT" -gt 0 ]; then
    summary_parts+=("${MERGE_HEALTH_TRACKED_CHANGE_COUNT} tracked local change(s)")
  fi
  if [ "$MERGE_HEALTH_UNTRACKED_COUNT" -gt 0 ]; then
    summary_parts+=("${MERGE_HEALTH_UNTRACKED_COUNT} untracked file(s)")
  fi

  local joined=""
  local part
  for part in "${summary_parts[@]}"; do
    if [ -n "$joined" ]; then
      joined+=", "
    fi
    joined+="$part"
  done
  MERGE_HEALTH_SUMMARY="$joined"
}

merge_health_issue_lines() {
  local max_lines="${1:-10}"
  local emitted=0

  if [ "$MERGE_HEALTH_MERGE_HEAD" -eq 1 ]; then
    echo "MERGE_HEAD present: unfinished merge detected"
    emitted=$((emitted + 1))
  fi

  if [ "$MERGE_HEALTH_REBASE_IN_PROGRESS" -eq 1 ] && [ "$emitted" -lt "$max_lines" ]; then
    echo "Rebase state present: finish or abort the in-progress rebase before merging"
    emitted=$((emitted + 1))
  fi

  if [ "$MERGE_HEALTH_CHERRY_PICK_HEAD" -eq 1 ] && [ "$emitted" -lt "$max_lines" ]; then
    echo "CHERRY_PICK_HEAD present: finish or abort the in-progress cherry-pick"
    emitted=$((emitted + 1))
  fi

  if [ "$MERGE_HEALTH_REVERT_HEAD" -eq 1 ] && [ "$emitted" -lt "$max_lines" ]; then
    echo "REVERT_HEAD present: finish or abort the in-progress revert"
    emitted=$((emitted + 1))
  fi

  local path
  for path in "${MERGE_HEALTH_UNMERGED_FILES[@]}"; do
    if [ "$emitted" -ge "$max_lines" ]; then
      break
    fi
    echo "Unmerged: $path"
    emitted=$((emitted + 1))
  done

  if [ "$MERGE_HEALTH_UNMERGED_COUNT" -gt "$emitted" ]; then
    echo "Additional unmerged files: $((MERGE_HEALTH_UNMERGED_COUNT - emitted))"
  fi

  for path in "${MERGE_HEALTH_TRACKED_CHANGE_FILES[@]}"; do
    if [ "$emitted" -ge "$max_lines" ]; then
      break
    fi
    echo "Tracked change: $path"
    emitted=$((emitted + 1))
  done

  if [ "$MERGE_HEALTH_TRACKED_CHANGE_COUNT" -gt 0 ] && [ "$MERGE_HEALTH_TRACKED_CHANGE_COUNT" -gt "$emitted" ]; then
    echo "Additional tracked changes: $((MERGE_HEALTH_TRACKED_CHANGE_COUNT - emitted))"
  fi

  for path in "${MERGE_HEALTH_UNTRACKED_FILES[@]}"; do
    if [ "$emitted" -ge "$max_lines" ]; then
      break
    fi
    echo "Untracked file: $path"
    emitted=$((emitted + 1))
  done

  if [ "$MERGE_HEALTH_UNTRACKED_COUNT" -gt 0 ] && [ "$MERGE_HEALTH_UNTRACKED_COUNT" -gt "$emitted" ]; then
    echo "Additional untracked files: $((MERGE_HEALTH_UNTRACKED_COUNT - emitted))"
  fi
}
