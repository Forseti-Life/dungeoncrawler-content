#!/usr/bin/env bash
# ceo-release-boundary-health.sh — Event-driven CEO health check for release handoff.
#
# Runs immediately after post-coordinated-push.sh advances coordinated teams.
# Focus: verify the new current/next release pair is usable right away and that
# the next release process has been seeded without waiting for the periodic CEO loop.

set -euo pipefail

ROOT_DIR="${HQ_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT_DIR"

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"
ACTIVE_DIR="tmp/release-cycle-active"
PUSHED_DIR="tmp/auto-push-dispatched"

PASS="✅ PASS"
FAIL="❌ FAIL"
WARN="⚠️  WARN"
INFO="   ℹ️ "

failures=0

fail() { echo "$FAIL $*"; failures=$((failures + 1)); }
pass() { echo "$PASS $*"; }
warn() { echo "$WARN $*"; }
info() { echo "$INFO $*"; }

echo
echo "=== CEO release boundary health ==="

if [ ! -f "$PRODUCT_TEAMS_JSON" ]; then
  echo "$FAIL product-teams.json missing: $PRODUCT_TEAMS_JSON"
  exit 1
fi

python3 - "$PRODUCT_TEAMS_JSON" "$ACTIVE_DIR" "$PUSHED_DIR" "$ROOT_DIR" <<'PY'
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

teams_json, active_dir, pushed_dir, root = map(Path, sys.argv[1:5])
sys.path.insert(0, str(root / "scripts" / "lib"))
from release_cycle_helpers import coordinated_teams, has_groom_item  # noqa: E402


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8").strip()


def release_feature_count(features_root: Path, release_id: str) -> int:
    count = 0
    pattern = re.compile(rf"^-\s+Release:\s*{re.escape(release_id)}\s*$", re.MULTILINE)
    for fm in features_root.glob("*/feature.md"):
        text = fm.read_text(encoding="utf-8", errors="ignore")
        if "- Status: in_progress" not in text:
            continue
        if pattern.search(text):
            count += 1
    return count


def ready_features(features_root: Path, team_id: str) -> list[str]:
    matches: list[str] = []
    for fm in sorted(features_root.glob("*/feature.md")):
        text = fm.read_text(encoding="utf-8", errors="ignore")
        website = ""
        status = ""
        for line in text.splitlines():
            if line.startswith("- Website:"):
                website = line.split(":", 1)[1].strip().lower()
            elif line.startswith("- Status:"):
                status = line.split(":", 1)[1].strip()
        if status == "ready" and team_id in website:
            matches.append(fm.parent.name)
    return matches


def has_scope_activate_item(pm_inbox: Path, release_id: str) -> bool:
    if not pm_inbox.exists():
        return False
    for item in pm_inbox.iterdir():
        if not item.is_dir() or item.name == "_archived":
            continue
        if "scope-activate" in item.name and release_id in item.name:
            return True
    return False

def write_scope_activate_item(root: Path, pm_agent: str, team_id: str, release_id: str, ready_feats: list[str]) -> Path:
    inbox = root / "sessions" / pm_agent / "inbox"
    inbox.mkdir(parents=True, exist_ok=True)
    item_id = f"{datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')}-scope-activate-{release_id}"
    item_dir = inbox / item_id
    item_dir.mkdir(parents=True, exist_ok=True)
    feats_list = "\n".join(f"- `{feat}`" for feat in ready_feats[:15]) if ready_feats else "- (check features/ dir)"
    readme = (
        f"# Scope Activate: {release_id}\n\n"
        f"- Agent: {pm_agent}\n"
        f"- Status: pending\n"
        f"- Release: {release_id}\n"
        f"- Date: {datetime.now(timezone.utc).strftime('%Y-%m-%d')}\n"
        f"- Dispatched by: ceo-release-boundary-health.sh (release advanced with 0 features scoped)\n\n"
        f"## Task\n\n"
        f"Release `{release_id}` just became the current release and has zero activated features.\n"
        f"Activate features now using:\n\n"
        f"```bash\nbash scripts/pm-scope-activate.sh {team_id} <feature_id>\n```\n\n"
        f"Cap is **10 features** (auto-close fires at 10 or 24h). "
        f"Activate your highest-priority `ready` features first.\n\n"
        f"## Ready features (up to 10)\n{feats_list}\n\n"
        f"## Done when\n"
        f"At least 3 features activated; dev/QA inbox items exist for each.\n"
    )
    (item_dir / "README.md").write_text(readme, encoding="utf-8")
    (item_dir / "roi.txt").write_text("900\n", encoding="utf-8")
    return item_dir


teams = coordinated_teams(teams_json)

features_root = root / "features"
failures = 0

for team in sorted(teams, key=lambda entry: entry.get("id", "")):
    team_id = (team.get("id") or "").strip()
    pm_agent = (team.get("pm_agent") or f"pm-{team_id}").strip()
    release_file = active_dir / f"{team_id}.release_id"
    next_file = active_dir / f"{team_id}.next_release_id"
    sentinel_file = pushed_dir / f"{team_id}.advanced"

    if not release_file.exists():
        print(f"❌ FAIL [{team_id}] missing active release file: {release_file}")
        failures += 1
        continue
    if not next_file.exists():
        print(f"❌ FAIL [{team_id}] missing next release file: {next_file}")
        failures += 1
        continue

    release_id = read_text(release_file)
    next_release_id = read_text(next_file)
    if not release_id or not next_release_id:
        print(f"❌ FAIL [{team_id}] empty release boundary state (release_id={release_id!r}, next_release_id={next_release_id!r})")
        failures += 1
        continue
    if release_id == next_release_id:
        print(f"❌ FAIL [{team_id}] next_release_id matches current release_id ({release_id})")
        failures += 1

    if sentinel_file.exists():
        sentinel_val = read_text(sentinel_file)
        if sentinel_val != release_id:
            print(f"❌ FAIL [{team_id}] advance sentinel stale: {sentinel_val} != current {release_id}")
            failures += 1
        else:
            print(f"✅ PASS [{team_id}] advance sentinel matches current release ({release_id})")
    else:
        print(f"⚠️  WARN [{team_id}] advance sentinel not found: {sentinel_file.name}")

    if has_groom_item(root, pm_agent, next_release_id):
        print(f"✅ PASS [{team_id}] PM grooming coverage exists for {next_release_id}")
    else:
        print(f"❌ FAIL [{team_id}] PM grooming coverage missing for {next_release_id}")
        failures += 1

    feature_count = release_feature_count(features_root, release_id)
    if feature_count > 0:
        print(f"✅ PASS [{team_id}] current release {release_id} has {feature_count} activated feature(s)")
        continue

    pm_inbox = root / "sessions" / pm_agent / "inbox"
    if has_scope_activate_item(pm_inbox, release_id):
        print(f"✅ PASS [{team_id}] scope-activate item already queued for {release_id}")
        continue

    ready = ready_features(features_root, team_id)
    if ready:
        item_dir = write_scope_activate_item(root, pm_agent, team_id, release_id, ready)
        print(f"✅ PASS [{team_id}] queued immediate scope-activate item: {item_dir.name}")
    else:
        print(f"⚠️  WARN [{team_id}] current release {release_id} has 0 activated features and no ready backlog")

if failures:
    raise SystemExit(1)
PY

rc=$?
if [ "$rc" -ne 0 ]; then
  info "Boundary health found release handoff failures."
else
  info "Boundary health passed."
fi

exit "$rc"
