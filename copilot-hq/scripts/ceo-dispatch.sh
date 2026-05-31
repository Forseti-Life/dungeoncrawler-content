#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   ./scripts/ceo-dispatch.sh
# Dispatches queued CEO commands by resolving PM owner from features/<work-item>/feature.md.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

shopt -s nullglob
files=(inbox/commands/*.md)
shopt -u nullglob

if [ "${#files[@]}" -eq 0 ]; then
  echo "No commands pending."
  exit 0
fi

for f in "${files[@]}"; do
  wi=$(grep -m1 '^\- work_item:' "$f" | sed 's/^- work_item: *//' || true)
  topic=$(grep -m1 '^\- topic:' "$f" | sed 's/^- topic: *//' || true)

  if [ -z "$wi" ] || [ -z "$topic" ]; then
    echo "Skipping (missing work_item/topic): $f"
    continue
  fi

  feature="features/${wi}/feature.md"
  if [ ! -f "$feature" ]; then
    echo "Skipping (missing feature brief for work item): $wi"
    continue
  fi

  pm=$(grep -m1 '^\- PM owner:' "$feature" | sed 's/^- PM owner: *//' || true)
  if [ -z "$pm" ]; then
    echo "Skipping (missing PM owner in feature brief): $wi"
    continue
  fi

  ./scripts/dispatch-pm-request.sh "$pm" "$wi" "$topic" >/dev/null

  latest_dir=$(ls -dt "sessions/${pm}/inbox/"*"-${topic}" 2>/dev/null | head -n 1 || true)
  if [ -n "$latest_dir" ]; then
    cp "$f" "$latest_dir/command.md"
  fi

  mkdir -p inbox/processed
  mv "$f" "inbox/processed/$(basename "$f")"
  echo "Dispatched: $(basename "$f") -> $pm (work item: $wi)"
done
