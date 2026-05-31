#!/usr/bin/env bash
set -euo pipefail

DEFAULT_HQ_DIR="${HQ_DEPLOY_DIR:-${REPO_DEPLOY_DIR:-$HOME/forseti.life}/copilot-hq}"
HQ_DIR="${1:-$DEFAULT_HQ_DIR}"

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
  "scripts/release-signoff-status.sh" \
  "scripts/verify-hq-runtime.sh" \
  "orchestrator/run.py"
do
  if [ -f "$f" ]; then
    echo "present: $f"
  else
    echo "MISSING: $f"
  fi
done

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
