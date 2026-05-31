#!/usr/bin/env bash
set -euo pipefail

# Dispatch exactly one queued command (oldest by filename) to the owning PM.
# Returns:
#   0 = dispatched one
#   2 = nothing to do

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/command-routing.sh
source "$ROOT_DIR/scripts/lib/command-routing.sh"

shopt -s nullglob
files=(inbox/commands/*.md)
shopt -u nullglob

if [ "${#files[@]}" -eq 0 ]; then
  exit 2
fi

IFS=$'\n' files_sorted=($(printf '%s\n' "${files[@]}" | sort))
unset IFS
f=""
for candidate in "${files_sorted[@]}"; do
  if ! command_is_hq_target "$candidate"; then
    continue
  fi
  # CEO commands do NOT include an explicit PM; those are handled by inbox-loop.
  if ! grep -q '^\- pm:' "$candidate"; then
    f="$candidate"
    break
  fi
done

if [ -z "$f" ]; then
  exit 2
fi

wi=$(grep -m1 '^\- work_item:' "$f" | sed 's/^- work_item: *//' || true)
topic=$(grep -m1 '^\- topic:' "$f" | sed 's/^- topic: *//' || true)

if [ -z "$wi" ] || [ -z "$topic" ]; then
  echo "Skipping (missing work_item/topic): $f" >&2
  mkdir -p inbox/processed
  mv "$f" "inbox/processed/$(basename "$f")"
  exit 0
fi

feature="features/${wi}/feature.md"
if [ ! -f "$feature" ]; then
  echo "Skipping (missing feature brief): $wi" >&2
  mkdir -p inbox/processed
  mv "$f" "inbox/processed/$(basename "$f")"
  exit 0
fi

pm=$(grep -m1 '^\- PM owner:' "$feature" | sed 's/^- PM owner: *//' || true)
if [ -z "$pm" ]; then
  echo "Skipping (missing PM owner in feature brief): $wi" >&2
  mkdir -p inbox/processed
  mv "$f" "inbox/processed/$(basename "$f")"
  exit 0
fi

./scripts/dispatch-pm-request.sh "$pm" "$wi" "$topic" >/dev/null
latest_dir=$(ls -dt "sessions/${pm}/inbox/"*"-${topic}" 2>/dev/null | head -n 1 || true)
if [ -n "$latest_dir" ]; then
  cp "$f" "$latest_dir/command.md"
fi

mkdir -p inbox/processed
mv "$f" "inbox/processed/$(basename "$f")"

echo "Dispatched $(basename "$f") -> $pm (work item: $wi, topic: $topic)"
