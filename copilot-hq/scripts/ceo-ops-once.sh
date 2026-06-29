#!/usr/bin/env bash
set -euo pipefail

# CEO scheduled quality check (single run).
# - Checks status, blockers, and priority rankings.
# - Runs the same CEO health diagnostics used during manual orientation.
# - Emits a concise report to stdout and exits non-zero when health checks fail.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

ts="$(date -Iseconds)"

echo "[$ts] Forseti CEO ops cycle"
echo

section() {
  echo
  echo "== $1 =="
}

echo "== Priority rankings =="
python3 - <<'PY'
import pathlib
try:
    import yaml  # type: ignore
except Exception:
    yaml = None
p = pathlib.Path('org-chart/priorities.yaml').read_text(encoding='utf-8')
if yaml:
    data = yaml.safe_load(p) or {}
    pr = (data.get('priorities') or {})
else:
    # Minimal parser: `key: int` lines under `priorities:`
    pr = {}
    in_pr = False
    for line in p.splitlines():
        if line.strip().startswith('#') or not line.strip():
            continue
        if line.strip() == 'priorities:':
            in_pr = True
            continue
        if in_pr and line.startswith('  ') and ':' in line:
            k,v = line.strip().split(':',1)
            pr[k.strip()] = int(v.strip())
items = sorted(pr.items(), key=lambda kv: kv[1], reverse=True)
for k,v in items:
    print(f"- {k}: {v}")
PY

section "HQ status"
set +e
./scripts/hq-status.sh
hq_status_rc=$?
set -e

section "CEO inbox"
find sessions/ceo-copilot-2/inbox -mindepth 1 -maxdepth 1 -type d -printf '%f\n' 2>/dev/null | sort || true

section "Clean-audit Gate 2 backstop"
python3 ./scripts/gate2-clean-audit-backstop.py --source "ceo-ops-once.sh" --queue-followup || true

section "Release/SLA remediation dispatch"
python3 ./scripts/ceo-pipeline-remediate.py --source "ceo-ops-once.sh" || true

section "Project registry link audit"
set +e
python3 ./scripts/project-registry-link-audit.py
project_link_rc=$?
set -e

section "Release health"
set +e
./scripts/ceo-release-health.sh
release_rc=$?
set -e

section "System health"
set +e
./scripts/ceo-system-health.sh --dispatch
system_rc=$?
set -e

section "Blockers (latest per agent outbox)"
./scripts/hq-blockers.sh | head -n 200

section "CEO actions suggested"
# Simple triage suggestions based on current status.
blocked_count=$(./scripts/hq-blockers.sh count 2>/dev/null || echo 0)
if [ "${blocked_count:-0}" -gt 0 ]; then
  echo "- Unblock: review supervisor escalation inbox items under sessions/<supervisor>/inbox/*needs-*/"
fi

matrix_noncompliant_count=$(./scripts/escalation-matrix-compliance.sh count 2>/dev/null || echo 0)
if [ "${matrix_noncompliant_count:-0}" -gt 0 ]; then
    echo "- Escalation compliance: ${matrix_noncompliant_count} blocked/needs-info item(s) are missing 'Matrix issue type'; request matrix-mapped escalation rewrites."
fi

if [ "$release_rc" -ne 0 ]; then
  echo "- Release health failed: review missing Gate 2 approvals, PM signoffs, cross-team signoffs, and orphaned release state."
fi

if [ "$system_rc" -ne 0 ]; then
  echo "- System health failed: review orchestration/runtime warnings and dispatch fixes to the owning seat."
fi

if [ "${hq_status_rc:-0}" -ne 0 ]; then
  echo "- HQ status failed: clear merge/integration blockers or other red status conditions before the next sync/merge."
fi

if [ "${project_link_rc:-0}" -ne 0 ]; then
  echo "- Project registry link audit failed: add missing PROJ linkage so active portfolio initiatives stay visible on the roadmap."
fi

# Prioritize agent-management: ensure tracker PM/Dev/QA have work if backlog exists.
pm_backlog=$(find sessions/pm-forseti-agent-tracker/inbox -mindepth 1 -maxdepth 1 -type d ! -name "_archived" 2>/dev/null | wc -l | awk '{print $1}')
if [ "${pm_backlog:-0}" -gt 0 ]; then
  echo "- Agent-management: pm-forseti-agent-tracker has ${pm_backlog} inbox item(s); ensure outbox artifacts are being produced and prioritize this stream."
fi

echo "- If any team is intentionally deprioritized, record that in the work item update (with reason + next review time)."

if [ "${hq_status_rc:-0}" -eq 0 ] && [ "$release_rc" -eq 0 ] && [ "$system_rc" -eq 0 ] && [ "${project_link_rc:-0}" -eq 0 ] && [ "${blocked_count:-0}" -eq 0 ] && [ "${matrix_noncompliant_count:-0}" -eq 0 ]; then
  echo "- No CEO action required from this scheduled check."
fi

section "Idle work seeding (DISABLED)"
echo "(skipped)"

if [ "${hq_status_rc:-0}" -ne 0 ] || [ "$release_rc" -ne 0 ] || [ "$system_rc" -ne 0 ] || [ "${project_link_rc:-0}" -ne 0 ]; then
  exit 1
fi
