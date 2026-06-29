#!/usr/bin/env bash
set -euo pipefail

# git-guard.sh
# Safety wrapper: blocks destructive git operations (no exceptions).
# Use: git_guard [git options...] <subcommand> [args...]

git_guard() {
  local args=("$@")
  local i=0 sub="" sub_i=-1

  # Identify the git subcommand, skipping global options like -C/-c.
  while [ $i -lt ${#args[@]} ]; do
    local a="${args[$i]}"

    case "$a" in
      -C|-c|--git-dir|--work-tree)
        i=$((i + 2))
        continue
        ;;
      --no-pager)
        i=$((i + 1))
        continue
        ;;
      -*)
        i=$((i + 1))
        continue
        ;;
      *)
        sub="$a"
        sub_i=$i
        break
        ;;
    esac
  done

  # If we couldn't find a subcommand, just run git as-is.
  if [ "$sub_i" -lt 0 ]; then
    command git "${args[@]}"
    return $?
  fi

  # Remaining args after the subcommand.
  local rest=("${args[@]:$((sub_i + 1))}")

  case "$sub" in
    reset|rebase|clean)
      echo "ERROR: forbidden git command: git ${sub} (policy: no exceptions)" >&2
      return 99
      ;;
    pull)
      for a in "${rest[@]}"; do
        if [[ "$a" == "--rebase" || "$a" == "--autostash" || "$a" == "--rebase=merges" ]]; then
          echo "ERROR: forbidden git pull flag: ${a} (policy: no exceptions)" >&2
          return 99
        fi
      done
      ;;
    push)
      for a in "${rest[@]}"; do
        if [[ "$a" == "--force" || "$a" == "--force-with-lease" ]]; then
          echo "ERROR: forbidden git push flag: ${a} (policy: no exceptions)" >&2
          return 99
        fi
      done
      ;;
    checkout|switch)
      for a in "${rest[@]}"; do
        if [[ "$a" == "-f" || "$a" == "--force" ]]; then
          echo "ERROR: forbidden git ${sub} flag: ${a} (policy: no exceptions)" >&2
          return 99
        fi
      done
      ;;
  esac

  command git "${args[@]}"
}
