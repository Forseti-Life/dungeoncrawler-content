#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"

release_id="${1:-}"
fmt="${2:-}"

if [ -z "$release_id" ]; then
  echo "Usage: $0 <release-id> [--json]" >&2
  exit 2
fi

slug="$(printf '%s' "$release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-80)"

if [ ! -f "$PRODUCT_TEAMS_JSON" ]; then
  echo "ERROR: missing product team registry: $PRODUCT_TEAMS_JSON" >&2
  exit 2
fi

if ! rows="$(python3 - "$PRODUCT_TEAMS_JSON" <<'PY'
import json
import sys

with open(sys.argv[1], 'r', encoding='utf-8') as fh:
    data = json.load(fh)

for team in (data.get('teams') or []):
    if not team.get('active', False):
        continue
    if not team.get('coordinated_release_default', False):
        continue
    team_id = str(team.get('id') or '').strip()
    pm_agent = str(team.get('pm_agent') or '').strip()
    if not team_id or not pm_agent:
        continue
    print(f"{team_id}\t{pm_agent}")
PY
  2>&1)"; then
  echo "$rows" >&2
  exit 2
fi

if [ -z "$rows" ]; then
  echo "ERROR: no coordinated-release PM seats configured in $PRODUCT_TEAMS_JSON" >&2
  exit 2
fi

ready=true
required_count=0
rows_with_status=""
while IFS=$'\t' read -r team_id pm_agent; do
  [ -n "$team_id" ] || continue
  [ -n "$pm_agent" ] || continue
  required_count=$((required_count + 1))
  signoff_file="sessions/${pm_agent}/artifacts/release-signoffs/${slug}.md"
  has_signoff=false
  if [ -f "$signoff_file" ]; then
    has_signoff=true
  else
    ready=false
  fi
  rows_with_status+="${team_id}"$'\t'"${pm_agent}"$'\t'"${signoff_file}"$'\t'"${has_signoff}"$'\n'
done <<<"$rows"

if [ "${fmt:-}" = "--json" ]; then
  python3 - "$release_id" "$slug" "$ready" "$rows_with_status" <<'PY'
import json
import sys

release_id, slug, ready, rows = sys.argv[1:]

required = []
for line in rows.splitlines():
  parts = line.split('\t')
  if len(parts) != 4:
    continue
  team_id, pm_agent, signoff_file, has_signoff = parts
  required.append(
    {
      "team_id": team_id,
      "pm_agent": pm_agent,
      "signoff_file": signoff_file,
      "signed_off": (has_signoff == "true"),
    }
  )

out = {
  "release_id": release_id,
  "slug": slug,
  "required_pm_signoffs": required,
  "required_count": len(required),
  "signed_off_count": sum(1 for r in required if r["signed_off"]),
  "ready_for_official_push": (ready == "true"),
}

# Backward-compatible keys for legacy consumers when those seats are present.
for r in required:
  if r["pm_agent"] == "pm-forseti":
    out["pm_forseti_signed_off"] = r["signed_off"]
    out["pm_forseti_file"] = r["signoff_file"]
  if r["pm_agent"] == "pm-dungeoncrawler":
    out["pm_dungeoncrawler_signed_off"] = r["signed_off"]
    out["pm_dungeoncrawler_file"] = r["signoff_file"]

print(json.dumps(out))
PY
  exit 0
fi

echo "Release id: ${release_id}"
echo "- required coordinated PM signoffs: ${required_count}"
while IFS=$'\t' read -r team_id pm_agent signoff_file has_signoff; do
  [ -n "$team_id" ] || continue
  printf '%s\n' "- ${team_id} (${pm_agent}) signoff: ${has_signoff} (${signoff_file})"
done <<<"$rows_with_status"
echo "- ready for official push:   ${ready}"

if [ "$ready" != true ]; then
  exit 1
fi
