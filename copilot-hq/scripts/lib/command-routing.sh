#!/usr/bin/env bash

command_field() {
  local file="$1"
  local key="$2"
  [ -f "$file" ] || return 0
  grep -m1 -E "^\- ${key}:" "$file" 2>/dev/null | sed -E "s/^\- ${key}:[[:space:]]*//" || true
}

command_target() {
  local file="$1"
  command_field "$file" "target" | tr '[:upper:]' '[:lower:]'
}

command_target_agent() {
  local file="$1"
  command_field "$file" "target_agent"
}

command_is_hq_target() {
  local file="$1"
  local target
  target="$(command_target "$file")"
  case "$target" in
    ""|hq|orchestrator|ceo)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

command_matches_local_target() {
  local file="$1"
  local wanted="${2:-dev-laptop}"
  local target
  target="$(command_target "$file")"
  [ -n "$target" ] || return 1
  [ "$target" = "$(printf '%s' "$wanted" | tr '[:upper:]' '[:lower:]')" ]
}

sanitize_topic_slug() {
  local raw="${1:-task}"
  printf '%s' "$raw" \
    | tr '[:upper:]' '[:lower:]' \
    | sed -E 's/[^a-z0-9._-]+/-/g; s/^-+//; s/-+$//; s/-{2,}/-/g'
}
