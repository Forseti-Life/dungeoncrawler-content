#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"

release_id="${1:-}"
next_release_id="${2:-}"

if [ -z "$release_id" ]; then
  echo "Usage: $0 <current-release-id> [next-release-id]" >&2
  echo "Example:" >&2
  echo "  $0 20260223-coordinated-release 20260223-coordinated-release-next" >&2
  exit 2
fi

if [ -z "$next_release_id" ]; then
  next_release_id="${release_id}-next"
fi

if [ ! -f "$PRODUCT_TEAMS_JSON" ]; then
  echo "ERROR: missing product team registry: $PRODUCT_TEAMS_JSON" >&2
  exit 2
fi

team_ids="$(python3 - "$PRODUCT_TEAMS_JSON" <<'PY'
import json
import sys

with open(sys.argv[1], 'r', encoding='utf-8') as fh:
    data = json.load(fh)

for team in (data.get('teams') or []):
    if not team.get('active', False):
        continue
    if not team.get('release_preflight_enabled', False):
        continue
    if not team.get('coordinated_release_default', False):
        continue
    team_id = str(team.get('id') or '').strip()
    if team_id:
        print(team_id)
PY
)"

if [ -z "$team_ids" ]; then
  echo "ERROR: no coordinated-release teams configured in $PRODUCT_TEAMS_JSON" >&2
  exit 2
fi

while IFS= read -r team_id; do
  [ -n "$team_id" ] || continue
  ./scripts/release-cycle-start.sh "$team_id" "$release_id" "$next_release_id"
done <<<"$team_ids"

echo "OK: queued coordinated release-cycle QA preflight for current=${release_id} next=${next_release_id}"
