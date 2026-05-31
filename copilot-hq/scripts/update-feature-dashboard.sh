#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

out="dashboards/FEATURE_PROGRESS.md"

{
  echo "# Feature Progress"
  echo
  echo "Generated: $(date -Iseconds)"
  echo
  echo "| Work item | Website | Module | Status | Priority | PM | Dev | QA |"
  echo "|----------|---------|--------|--------|----------|----|-----|----|"

  shopt -s nullglob
  features_glob=(features/*/feature.md)
  shopt -u nullglob
  for f in "${features_glob[@]}"; do
    id=$(grep -m1 '^\- Work item id:' "$f" | sed 's/^- Work item id: *//' || true)
    website=$(grep -m1 '^\- Website:' "$f" | sed 's/^- Website: *//' || true)
    module=$(grep -m1 '^\- Module:' "$f" | sed 's/^- Module: *//' || true)
    status=$(grep -m1 '^\- Status:' "$f" | sed 's/^- Status: *//' || true)
    prio=$(grep -m1 '^\- Priority:' "$f" | sed 's/^- Priority: *//' || true)
    pm=$(grep -m1 '^\- PM owner:' "$f" | sed 's/^- PM owner: *//' || true)
    dev=$(grep -m1 '^\- Dev owner:' "$f" | sed 's/^- Dev owner: *//' || true)
    qa=$(grep -m1 '^\- QA owner:' "$f" | sed 's/^- QA owner: *//' || true)

    # Fallbacks
    id=${id:-$(basename "$(dirname "$f")")}

    echo "| ${id} | ${website:-} | ${module:-} | ${status:-} | ${prio:-} | ${pm:-} | ${dev:-} | ${qa:-} |"
  done
} > "$out"

echo "Wrote: $out"
