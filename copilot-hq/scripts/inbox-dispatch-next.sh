#!/usr/bin/env bash
set -euo pipefail

# Dispatch exactly one queued *explicit PM* command (oldest by filename).
# Explicit PM commands are created by inbox-new-command.sh and include:
#   - pm:
#   - topic:
#
# Returns:
#   0 = dispatched one
#   2 = nothing to do (no explicit-PM commands pending)

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

shopt -s nullglob
files=(inbox/commands/*.md)
shopt -u nullglob

if [ "${#files[@]}" -eq 0 ]; then
  exit 2
fi

IFS=$'\n' files_sorted=($(printf '%s\n' "${files[@]}" | sort))
unset IFS

picked=""
for f in "${files_sorted[@]}"; do
  if grep -q '^\- pm:' "$f"; then
    picked="$f"
    break
  fi
done

if [ -z "$picked" ]; then
  exit 2
fi

pm=$(grep -m1 '^\- pm:' "$picked" | sed 's/^- pm: *//' || true)
wi=$(grep -m1 '^\- work_item:' "$picked" | sed 's/^- work_item: *//' || true)
topic=$(grep -m1 '^\- topic:' "$picked" | sed 's/^- topic: *//' || true)

if [ -z "$pm" ] || [ -z "$topic" ]; then
  echo "Skipping (missing pm/topic): $picked" >&2
  exit 0
fi

./scripts/dispatch-pm-request.sh "$pm" "$wi" "$topic" >/dev/null

latest_dir=$(ls -dt "sessions/${pm}/inbox/"*"-${topic}" 2>/dev/null | head -n 1 || true)
if [ -n "$latest_dir" ]; then
  cp "$picked" "$latest_dir/command.md"
fi

mkdir -p inbox/processed
mv "$picked" "inbox/processed/$(basename "$picked")"

echo "Dispatched $(basename "$picked") -> $pm (work item: $wi, topic: $topic)"

