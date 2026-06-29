#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${HQ_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
SCRIPT_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/lib" && pwd)"
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

if ! owner_row="$(python3 - "$PRODUCT_TEAMS_JSON" "$release_id" <<'PY'
import json
import sys

with open(sys.argv[1], 'r', encoding='utf-8') as fh:
  data = json.load(fh)

release_id = (sys.argv[2] or '').strip().lower()
best_team = None
best_len = 0

for team in (data.get('teams') or []):
    if not team.get('active', False):
        continue
    team_id = str(team.get('id') or '').strip().lower()
    aliases = [str(a).strip().lower() for a in (team.get('aliases') or []) if str(a).strip()]
    candidates = [team_id] + aliases
    for cand in candidates:
        if cand and cand in release_id and len(cand) > best_len:
            best_len = len(cand)
            best_team = team

if best_team:
    print(f"{str(best_team.get('id') or '').strip()}\t{str(best_team.get('pm_agent') or '').strip()}")
PY
  2>&1)"; then
  echo "$owner_row" >&2
  exit 2
fi

if [ -z "$owner_row" ]; then
  echo "ERROR: could not infer owning team for release '${release_id}' from $PRODUCT_TEAMS_JSON" >&2
  exit 2
fi

IFS=$'\t' read -r owner_team_id owner_pm_agent <<<"$owner_row"
signoff_file="sessions/${owner_pm_agent}/artifacts/release-signoffs/${slug}.md"
ready=false
if [ -f "$signoff_file" ]; then
  ready=true
fi

gate1b_json="$(
  python3 - "$ROOT_DIR" "$SCRIPT_LIB_DIR" "$release_id" "$owner_pm_agent" <<'PY'
from pathlib import Path
import json
import sys

root = Path(sys.argv[1])
fallback_lib = Path(sys.argv[2])
release_id = sys.argv[3]
pm_agent = sys.argv[4]

for lib_dir in (root / "scripts" / "lib", fallback_lib):
    if lib_dir.exists() and str(lib_dir) not in sys.path:
        sys.path.insert(0, str(lib_dir))

from gate1b_artifacts import latest_gate1b_artifact, latest_gate1b_risk_acceptance  # type: ignore

artifact = latest_gate1b_artifact(root / "sessions" / "agent-code-review" / "outbox", release_id)
risk = latest_gate1b_risk_acceptance(root / "sessions" / pm_agent / "artifacts" / "risk-acceptances", release_id)
clear = bool(risk) or bool(artifact and artifact.verdict == "APPROVE")
payload = {
    "gate1b_clear": clear,
    "gate1b_artifact": str(artifact.path) if artifact else None,
    "gate1b_verdict": artifact.verdict if artifact else None,
    "gate1b_risk_acceptance": str(risk) if risk else None,
}
print(json.dumps(payload))
PY
)"

cohort_json="$(
  python3 - "$ROOT_DIR" "$SCRIPT_LIB_DIR" "$PRODUCT_TEAMS_JSON" "$owner_team_id" "$release_id" <<'PY'
from pathlib import Path
import json
import sys

root = Path(sys.argv[1])
fallback_lib = Path(sys.argv[2])
config_path = Path(sys.argv[3])
owner_team_id = sys.argv[4]
release_id = sys.argv[5]

for lib_dir in (root / "scripts" / "lib", fallback_lib):
    if lib_dir.exists() and str(lib_dir) not in sys.path:
        sys.path.insert(0, str(lib_dir))

from release_cycle_helpers import release_cohort  # type: ignore

slug = "".join(ch if ch.isalnum() or ch in "._-" else "-" for ch in release_id).strip("-")[:80]
rows = []
for team in release_cohort(config_path, owner_team_id):
    pm_agent = str(team.get("pm_agent") or "").strip()
    signoff_file = root / "sessions" / pm_agent / "artifacts" / "release-signoffs" / f"{slug}.md"
    rows.append(
        {
            "team_id": str(team.get("id") or "").strip(),
            "pm_agent": pm_agent,
            "signoff_file": str(signoff_file),
            "signed_off": signoff_file.exists(),
        }
    )

if not rows:
    signoff_file = root / "sessions" / sys.argv[4] / "artifacts" / "release-signoffs" / f"{slug}.md"
    rows.append(
        {
            "team_id": owner_team_id,
            "pm_agent": owner_team_id,
            "signoff_file": str(signoff_file),
            "signed_off": signoff_file.exists(),
        }
    )

print(json.dumps(rows))
PY
)"

if [ "${fmt:-}" = "--json" ]; then
  python3 - "$release_id" "$slug" "$ready" "$owner_team_id" "$owner_pm_agent" "$signoff_file" "$gate1b_json" "$cohort_json" <<'PY'
import json
import sys

release_id, slug, ready, owner_team_id, owner_pm_agent, signoff_file, gate1b_json, cohort_json = sys.argv[1:]
gate1b = json.loads(gate1b_json)
required = json.loads(cohort_json)
signed_off_count = sum(1 for row in required if row.get("signed_off"))

out = {
  "release_id": release_id,
  "slug": slug,
  "owner_team_id": owner_team_id,
  "owner_pm_agent": owner_pm_agent,
  "required_pm_signoffs": required,
  "required_count": len(required),
  "signed_off_count": signed_off_count,
  "gate1b_clear": bool(gate1b.get("gate1b_clear")),
  "gate1b_artifact": gate1b.get("gate1b_artifact"),
  "gate1b_verdict": gate1b.get("gate1b_verdict"),
  "gate1b_risk_acceptance": gate1b.get("gate1b_risk_acceptance"),
  "ready_for_official_push": bool(gate1b.get("gate1b_clear")) and signed_off_count == len(required),
}

print(json.dumps(out))
PY
  exit 0
fi

echo "Release id: ${release_id}"
echo "- required PM signoffs: $(python3 - "$cohort_json" <<'PY'
import json
import sys
print(len(json.loads(sys.argv[1])))
PY
)"
echo "- owner team: ${owner_team_id}"
echo "- ${owner_team_id} (${owner_pm_agent}) signoff: ${ready} (${signoff_file})"
echo "- Gate 1b clear: $(python3 - "$gate1b_json" <<'PY'
import json
import sys
data = json.loads(sys.argv[1])
print("true" if data.get("gate1b_clear") else "false")
PY
)"
echo "- ready for official push:   $(python3 - "$ready" "$gate1b_json" <<'PY'
import json
import sys
ready = sys.argv[1] == "true"
gate1b = json.loads(sys.argv[2])
print("true" if ready and gate1b.get("gate1b_clear") else "false")
PY
)"

if [ "$ready" != true ]; then
  exit 1
fi

if ! python3 - "$gate1b_json" <<'PY'
import json
import sys
raise SystemExit(0 if json.loads(sys.argv[1]).get("gate1b_clear") else 1)
PY
then
  exit 1
fi
