#!/usr/bin/env bash
# Shared helpers for prioritizing active release work.

# shellcheck shell=bash

release_priority__active_release_ids() {
  local active_dir="tmp/release-cycle-active"
  [ -d "$active_dir" ] || return 0

  find "$active_dir" -maxdepth 1 -type f -name '*.release_id' -print0 2>/dev/null \
    | while IFS= read -r -d '' file; do
        head -n 1 "$file" 2>/dev/null | tr -d '\r'
      done \
    | awk 'NF'
}

release_priority__item_text() {
  local item_dir="$1"
  local rel_path path
  for rel_path in README.md command.md; do
    path="$item_dir/$rel_path"
    [ -f "$path" ] || continue
    cat "$path"
    printf '\n'
  done
}

release_priority__item_mentions_release() {
  local item_dir="$1"
  shift || true

  [ -d "$item_dir" ] || return 1

  local item_name
  item_name="$(basename "$item_dir")"
  local text
  text="$(release_priority__item_text "$item_dir")"

  local rid
  for rid in "$@"; do
    [ -n "$rid" ] || continue
    if [[ "$item_name" == *"$rid"* ]] || [[ "$text" == *"$rid"* ]]; then
      return 0
    fi
  done

  return 1
}

release_priority__is_current_release_item() {
  local item_dir="$1"
  [ -d "$item_dir" ] || return 1

  mapfile -t release_ids < <(release_priority__active_release_ids)
  if [ "${#release_ids[@]}" -gt 0 ] && release_priority__item_mentions_release "$item_dir" "${release_ids[@]}"; then
    return 0
  fi

  local text
  text="$(release_priority__item_text "$item_dir")"
  grep -qiE '\bcurrent release\b' <<<"$text"
}

release_priority__is_release_blocker_item() {
  local item_dir="$1"
  local item_name="${2:-$(basename "$item_dir")}"

  release_priority__is_current_release_item "$item_dir" || return 1

  local lower_name lower_text
  lower_name="$(printf '%s' "$item_name" | tr '[:upper:]' '[:lower:]')"
  lower_text="$(release_priority__item_text "$item_dir" | tr '[:upper:]' '[:lower:]')"

  grep -qE \
    'signoff|awaiting-signoff|coordinated-signoff|code-review-followup|code review follow-up|gate[[:space:]-]*1b|gate[[:space:]-]*2|release-cleanup|release-close|push-ready|blocker|blocking|follow-up' \
    <<<"$lower_name"$'\n'"$lower_text"
}

release_priority__lane_for_item() {
  local item_dir="$1"
  local item_name="${2:-$(basename "$item_dir")}"

  if release_priority__is_release_blocker_item "$item_dir" "$item_name"; then
    echo 0
  else
    echo 1
  fi
}
