#!/usr/bin/env bash
set -euo pipefail

# Consolidate legacy session directories (accidentally-created "agent ids") back into
# the correct base agent's artifacts for auditability.
#
# Example legacy ids:
#   pm-thetruthperspective-20260220-product
#   pm-forseti-20260220-improvement-20260221-needs-qa-forseti-...
#
# This script:
# - identifies sessions/<id> directories that are NOT configured seats
# - infers base seat by truncating at the first '-YYYYMMDD' occurrence
# - moves the legacy directory into: sessions/<base>/artifacts/legacy-sessions/<legacy-id>/
# - preserves full contents (inbox/outbox/artifacts) for a clean audit trail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

DRY_RUN=false
if [ "${1:-}" = "--dry-run" ]; then
  DRY_RUN=true
fi

configured_agent_ids() {
  python3 - <<'PY'
import re
from pathlib import Path
p = Path('org-chart/agents/agents.yaml')
if not p.exists():
    raise SystemExit(0)
for ln in p.read_text(encoding='utf-8', errors='ignore').splitlines():
    m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
    if m:
        print(m.group(1))
PY
}

is_configured() {
  local agent="$1"
  grep -qxF "$agent" "$tmp_configured"
}

infer_base() {
  local legacy="$1"
  # Base = prefix before first -YYYYMMDD.
  python3 - "$legacy" <<'PY'
import re, sys
s = sys.argv[1]
m = re.search(r'-\d{8}(-|$)', s)
if not m:
    print('')
    raise SystemExit(0)
print(s[:m.start()])
PY
}

tmp_configured="$(mktemp)"
trap 'rm -f "$tmp_configured" 2>/dev/null || true' EXIT
configured_agent_ids > "$tmp_configured"

moved=0
skipped=0

shopt -s nullglob
session_dirs=(sessions/*)
shopt -u nullglob
for d in "${session_dirs[@]}"; do
  [ -d "$d" ] || continue
  legacy="$(basename "$d")"

  # Skip configured seats.
  if is_configured "$legacy"; then
    continue
  fi

  base="$(infer_base "$legacy")"
  if [ -z "$base" ]; then
    skipped=$((skipped+1))
    continue
  fi

  if ! is_configured "$base"; then
    skipped=$((skipped+1))
    continue
  fi

  dest_root="sessions/${base}/artifacts/legacy-sessions"
  dest="${dest_root}/${legacy}"

  if [ -e "$dest" ]; then
    dest="${dest}-$(date +%s)"
  fi

  if $DRY_RUN; then
    echo "DRY-RUN move: sessions/${legacy} -> ${dest}"
    moved=$((moved+1))
    continue
  fi

  mkdir -p "$dest_root"
  mv "sessions/${legacy}" "$dest"
  echo "Moved: sessions/${legacy} -> ${dest}"
  moved=$((moved+1))

done

echo "Legacy session dirs moved: ${moved}"
if [ "$skipped" -gt 0 ]; then
  echo "Skipped (could not infer base / not configured): ${skipped}"
fi
