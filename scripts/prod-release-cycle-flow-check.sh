#!/usr/bin/env bash
set -euo pipefail

DEFAULT_HQ_DIR="${HQ_DEPLOY_DIR:-${REPO_DEPLOY_DIR:-/home/ubuntu/forseti.life}}"
HQ_DIR="${1:-$DEFAULT_HQ_DIR}"

if [ ! -d "$HQ_DIR" ] && [ -d "${HQ_DIR}/copilot-hq" ]; then
  HQ_DIR="${HQ_DIR}/copilot-hq"
fi

if [ ! -d "$HQ_DIR" ]; then
  echo "ERROR: HQ directory not found: $HQ_DIR" >&2
  exit 1
fi

cd "$HQ_DIR"

echo "=== Release-cycle flow check ==="
echo "host=$(hostname -f 2>/dev/null || hostname)"
echo "user=$(whoami)"
echo "HQ_DIR=$HQ_DIR"

echo
echo "=== Required files ==="
for f in \
  "org-chart/products/product-teams.json" \
  "scripts/release-cycle-start.sh" \
  "scripts/pm-scope-activate.sh" \
  "scripts/backfill-release-shipping-flow-runtime.sh" \
  "scripts/release-signoff.sh" \
  "scripts/release-signoff-status.sh" \
  "scripts/route-flow-transitions.py" \
  "scripts/agent-exec-next.sh" \
  "scripts/verify-hq-runtime.sh" \
  "orchestrator/run.py" \
  "drupal-langgraph/src/Service/ProcessFlowRegistryService.php"
do
  if [ -f "$f" ]; then
    echo "present: $f"
  else
    echo "MISSING: $f"
  fi
done

echo
echo "=== Flow-managed release framework ==="
if grep -q "release_shipping_flow" "drupal-langgraph/src/Service/ProcessFlowRegistryService.php" 2>/dev/null; then
  echo "present: release_shipping_flow registry definition"
else
  echo "MISSING: release_shipping_flow registry definition"
fi
if grep -q "Flow id: release_shipping_flow" "scripts/release-cycle-start.sh" 2>/dev/null; then
  echo "present: flow-managed Release Code Review seeding"
else
  echo "MISSING: flow-managed Release Code Review seeding"
fi
if grep -q "Flow node: PM Code Review Triage" "orchestrator/dispatch.py" 2>/dev/null; then
  echo "present: flow-managed PM Code Review Triage dispatch"
else
  echo "MISSING: flow-managed PM Code Review Triage dispatch"
fi
if grep -q "Flow node: Coordinated Push" "scripts/release-signoff.sh" 2>/dev/null; then
  echo "present: flow-managed Coordinated Push handoff"
else
  echo "MISSING: flow-managed Coordinated Push handoff"
fi
if grep -q "tmp/flow-runs/agentic_sdlc/" "scripts/pm-scope-activate.sh" 2>/dev/null; then
  echo "present: flow-managed agentic_sdlc runtime seeding"
else
  echo "MISSING: flow-managed agentic_sdlc runtime seeding"
fi
if grep -q "Flow node: Generate Code" "scripts/pm-scope-activate.sh" 2>/dev/null; then
  echo "present: flow-managed Generate Code activation handoff"
else
  echo "MISSING: flow-managed Generate Code activation handoff"
fi
if grep -q "Flow node: Test Cases Review" "scripts/pm-scope-activate.sh" 2>/dev/null; then
  echo "present: flow-managed Test Cases Review activation handoff"
else
  echo "MISSING: flow-managed Test Cases Review activation handoff"
fi
if grep -q "Flow-managed SDLC items rely on route-flow-transitions" "scripts/agent-exec-next.sh" 2>/dev/null; then
  echo "present: legacy QA dispatch suppression for flow-managed SDLC items"
else
  echo "MISSING: legacy QA dispatch suppression for flow-managed SDLC items"
fi

echo
echo "=== Runtime release-cycle state ==="
ls -la tmp/release-cycle-active 2>/dev/null || echo "missing: tmp/release-cycle-active"
for f in tmp/release-cycle-active/*.release_id; do
  [ -f "$f" ] || continue
  team="$(basename "$f" .release_id)"
  cur="$(cat "$f" 2>/dev/null || true)"
  nxt="$(cat "tmp/release-cycle-active/${team}.next_release_id" 2>/dev/null || true)"
  echo "team=$team current=$cur next=$nxt"
done

echo
echo "=== Active release flow runtime ==="
find tmp/flow-runs/release_shipping_flow -maxdepth 2 -type f 2>/dev/null | sort || echo "missing: tmp/flow-runs/release_shipping_flow"

echo
echo "=== Verify release control flags ==="
if [ -f /var/tmp/copilot-sessions-hq/release-cycle-control.json ]; then
  echo "/var/tmp/copilot-sessions-hq/release-cycle-control.json"
  cat /var/tmp/copilot-sessions-hq/release-cycle-control.json
fi
if [ -f tmp/release-cycle-control.json ]; then
  echo "tmp/release-cycle-control.json"
  cat tmp/release-cycle-control.json
fi

echo
echo "=== Dry run one release-cycle step (no publish) ==="
if [ -x ./orchestrator/.venv/bin/python ]; then
  ./orchestrator/.venv/bin/python - <<'PY'
import json
import orchestrator.run as run
log=[]
run._release_cycle_step(log)
print(json.dumps(log, indent=2))
PY
else
  echo "missing orchestrator/.venv/bin/python; cannot execute dry run"
fi

echo "DONE: release-cycle flow check complete"
