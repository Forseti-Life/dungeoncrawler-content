#!/usr/bin/env bash
set -euo pipefail

# Strict ownership audit across git-tracked files for both repos.
# Goal: ensure every tracked path is covered by an ownership rule.

HQ_ROOT="/home/ubuntu/forseti.life"
FORS_ROOT="/home/ubuntu/forseti.life"

echo "== Ownership audit (strict, git-tracked) =="

audit_repo() {
  local name="$1" root="$2"

  echo
  echo "-- ${name}: inventory (top-level) --"
  (cd "$root" && ls -1A | sed -n '1,200p')

  echo
  echo "-- ${name}: uncovered paths (if any) --"

  python3 - "$name" "$root" <<'PY'
import os, sys, subprocess

name = sys.argv[1]
root = sys.argv[2]

def git_ls_files(repo: str) -> list[str]:
  try:
    out = subprocess.check_output(["git", "-C", repo, "ls-files"], text=True)
  except Exception as e:
    print(f"ERROR: {name}: unable to run git ls-files in {repo}: {e}")
    return []
  return [ln.strip() for ln in out.splitlines() if ln.strip()]

def owner_for_hq(path: str) -> str:
  # Prefix rules (first match wins).
  rules = [
    ("org-chart/", "ceo-copilot-2"),
    ("runbooks/", "ceo-copilot-2"),
    ("scripts/", "dev-infra"),
    ("dashboards/", "ceo-copilot-2"),
    ("templates/", "ceo-copilot-2"),
    ("features/infrastructure-", "pm-infra"),
    ("features/infra-", "pm-infra"),
    ("features/", "pm-* (by website/module)"),
    ("knowledgebase/", "ceo-copilot-2 (curator)"),
    ("inbox/", "ceo-copilot-2"),
    ("sessions/", "<seat-id> (per seat)"),
    ("tmp/", "ceo-copilot-2"),
    ("README.md", "ceo-copilot-2"),
    (".gitignore", "ceo-copilot-2"),
  ]
  for prefix, owner in rules:
    if path == prefix or path.startswith(prefix):
      return owner
  # Default: CEO owns any other tracked repo root files.
  return "ceo-copilot-2"

def owner_for_forseti(path: str) -> str:
  # Specific module ownership first.
  if path.startswith("sites/forseti/web/modules/custom/job_hunter/"):
    return "pm-forseti / dev-forseti / qa-forseti / ba-forseti"
  if path.startswith("sites/forseti/web/modules/custom/copilot_agent_tracker/"):
    return "pm-forseti-agent-tracker / dev-forseti-agent-tracker / qa-forseti-agent-tracker / ba-forseti-agent-tracker"
  if path.startswith("sites/forseti/web/modules/custom/"):
    return "pm-forseti (default PM) / dev-forseti / qa-forseti / ba-forseti"
  # Dependency and infrastructure areas.
  if path.startswith("sites/forseti/vendor/") or path.startswith("sites/forseti/web/core/"):
    return "ceo-copilot-2"
  if path.startswith("sites/forseti/web/modules/contrib/"):
    return "ceo-copilot-2"
  # Infrastructure scope: anything outside website directories.
  if not path.startswith("sites/"):
    return "pm-infra / dev-infra / qa-infra / ba-infra"
  # Default: CEO owns any other areas under sites/ not covered above.
  return "ceo-copilot-2"

paths = git_ls_files(root)
if not paths:
  print("(no files or not a git repo)")
  raise SystemExit(0)

uncovered = []
owners = {}

for p in paths:
  if name == "HQ":
    owner = owner_for_hq(p)
  else:
    owner = owner_for_forseti(p)
  if not owner:
    uncovered.append(p)
  owners[owner] = owners.get(owner, 0) + 1

if uncovered:
  for p in uncovered[:200]:
    print(p)
  if len(uncovered) > 200:
    print(f"... ({len(uncovered) - 200} more)")
else:
  print("(none)")

print("\n-- Ownership summary --")
for owner, count in sorted(owners.items(), key=lambda kv: (-kv[1], kv[0])):
  print(f"{count}\t{owner}")
PY
}

audit_repo "HQ" "$HQ_ROOT"
audit_repo "forseti.life" "$FORS_ROOT"

echo
echo "Maps:"
echo "- HQ: org-chart/ownership/file-ownership.md"
echo "- forseti.life: org-chart/ownership/forseti-life-file-ownership.md"
