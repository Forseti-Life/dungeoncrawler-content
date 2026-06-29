#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${HQ_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT_DIR"

python3 - <<'PY'
import json
from pathlib import Path

root = Path.cwd()
teams_path = root / "org-chart" / "products" / "product-teams.json"
active_dir = root / "tmp" / "release-cycle-active"

if not teams_path.exists():
    raise SystemExit("ERROR: missing org-chart/products/product-teams.json")

if not active_dir.exists():
    print("OK: no active release-cycle state to backfill")
    raise SystemExit(0)

teams_data = json.loads(teams_path.read_text(encoding="utf-8"))
team_map = {
    str(team.get("id") or "").strip(): team
    for team in teams_data.get("teams", [])
    if str(team.get("id") or "").strip()
}

seeded = 0
skipped = 0
for release_file in sorted(active_dir.glob("*.release_id")):
    team_id = release_file.name.replace(".release_id", "")
    team = team_map.get(team_id)
    if team is None:
        print(f"SKIP: unknown team for {release_file.name}")
        skipped += 1
        continue

    release_id = release_file.read_text(encoding="utf-8").strip()
    if not release_id:
        print(f"SKIP: blank release id for {team_id}")
        skipped += 1
        continue

    run_dir = root / "tmp" / "flow-runs" / "release_shipping_flow" / release_id.lower().replace("/", "-")
    run_dir.mkdir(parents=True, exist_ok=True)
    payload = {
        "id": str(team.get("id") or "").strip(),
        "label": str(team.get("label") or "").strip(),
        "site": str(team.get("site") or "").strip(),
        "pm_agent": str(team.get("pm_agent") or "").strip(),
        "qa_agent": str(team.get("qa_agent") or "").strip(),
        "dev_agent": str(team.get("dev_agent") or "").strip(),
    }
    target = run_dir / "product-team.json"
    target.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    print(f"SEEDED: {team_id} -> {target}")
    seeded += 1

print(f"DONE: seeded={seeded} skipped={skipped}")
PY
