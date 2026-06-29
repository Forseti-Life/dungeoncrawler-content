#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

STRICT=0
if [ "${1:-}" = "--strict" ]; then
  STRICT=1
fi

echo "[1/6] Local node loop status"
./scripts/orchestrator-loop.sh status || true
./scripts/dev-sync-loop.sh status || true

echo "[2/6] Org automation gate"
ORG_STATUS="$(./scripts/org-control.sh status --one-line 2>/dev/null || echo 'enabled=unknown')"
echo "$ORG_STATUS"

echo "[3/6] Release health diagnostics"
set +e
HEALTH_OUT="$(./scripts/ceo-release-health.sh 2>&1)"
HEALTH_RC=$?
set -e
printf '%s\n' "$HEALTH_OUT"

if [ "$HEALTH_RC" -ne 0 ]; then
  echo "[warn] release-health reported actionable failures"
fi

echo "[4/6] Release-cycle shell tests"
pytest -q scripts/tests/test_route_gate_transitions.py scripts/tests/test_post_coordinated_push_advance.py

echo "[5/6] Orchestrator release-priority test"
if [ -x orchestrator/.venv/bin/python ]; then
  orchestrator/.venv/bin/python -m pytest -q orchestrator/tests/test_parallel_release_priority.py
else
  echo "[warn] orchestrator/.venv/bin/python missing; skipping orchestrator pytest"
fi

echo "[6/6] Tick behavior check"
if printf '%s' "$ORG_STATUS" | grep -q 'enabled=true'; then
  if [ -x orchestrator/.venv/bin/python ]; then
    orchestrator/.venv/bin/python orchestrator/run.py --once --no-publish --agent-cap 6
  else
    python3 orchestrator/run.py --once --no-publish --agent-cap 6
  fi
else
  echo "[info] org disabled; tick is expected to skip"
fi

if [ "$STRICT" -eq 1 ] && [ "$HEALTH_RC" -ne 0 ]; then
  echo "FAIL (strict): release health has active failures" >&2
  exit 1
fi

echo "PASS: local release-cycle verification complete"
