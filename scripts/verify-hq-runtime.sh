#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

STRICT=0
if [ "${1:-}" = "--strict" ]; then
  STRICT=1
fi

fail() {
  echo "VERIFY: FAIL - $*" >&2
  exit 1
}

warn() {
  echo "VERIFY: WARN - $*" >&2
}

pass() {
  echo "VERIFY: OK   - $*"
}

org_enabled="$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)"
[ "$org_enabled" = "true" ] || fail "org automation disabled"
pass "org automation enabled"

backend_mode="${HQ_AGENTIC_BACKEND:-local-server}"

case "$backend_mode" in
  local-server)
    [ -x "$ROOT_DIR/scripts/genai-wrapper.sh" ] || fail "backend(local-server): GenAI wrapper missing"
    pass "backend(local-server): local llama-server executor mode configured"
    ;;
  *)
    fail "invalid HQ_AGENTIC_BACKEND='$backend_mode' (expected local-server)"
    ;;
esac

service_active=0
if command -v systemctl >/dev/null 2>&1 && systemctl --user show-environment >/dev/null 2>&1; then
  if systemctl --user is-active --quiet copilot-sessions-hq-orchestrator.service; then
    service_active=1
    pass "systemd orchestrator service active"
  fi
fi

loop_status="$(./scripts/orchestrator-loop.sh status 2>/dev/null || echo not-running)"
loop_active=0
if [[ "$loop_status" == running* ]]; then
  loop_active=1
  pass "orchestrator loop active"
fi

if [ "$service_active" -eq 0 ] && [ "$loop_active" -eq 0 ]; then
  fail "no orchestrator runtime active (systemd service or loop wrapper)"
fi

pass "publisher handled by orchestrator tick"
pass "auto-checkpoint automation disabled by policy"

release_ctrl=""
if [ -f /var/tmp/copilot-sessions-hq/release-cycle-control.json ]; then
  release_ctrl="/var/tmp/copilot-sessions-hq/release-cycle-control.json"
elif [ -f tmp/release-cycle-control.json ]; then
  release_ctrl="tmp/release-cycle-control.json"
fi

if [ -x ./scripts/is-release-cycle-enabled.sh ]; then
  release_enabled="$(./scripts/is-release-cycle-enabled.sh 2>/dev/null || echo invalid)"
else
  release_enabled="invalid"
fi

if [ -n "$release_ctrl" ]; then
  if [ "$release_enabled" = "false" ]; then
    if [ "$STRICT" -eq 1 ]; then
      fail "release-cycle automation disabled ($release_ctrl)"
    fi
    warn "release-cycle automation disabled ($release_ctrl)"
  elif [ "$release_enabled" = "invalid" ]; then
    if [ "$STRICT" -eq 1 ]; then
      fail "release-cycle automation state unreadable ($release_ctrl)"
    fi
    warn "release-cycle automation state unreadable ($release_ctrl)"
  else
    pass "release-cycle automation enabled"
  fi
else
  warn "release-cycle control file not found"
fi

log_file="inbox/responses/orchestrator-latest.log"
if [ ! -f "$log_file" ]; then
  fail "orchestrator latest log missing ($log_file)"
fi
age_secs="$(
  python3 - <<'PY'
import os, time
p = "inbox/responses/orchestrator-latest.log"
print(int(time.time() - os.path.getmtime(p)))
PY
)"
if [ "$age_secs" -gt 300 ]; then
  if [ "$STRICT" -eq 1 ]; then
    fail "orchestrator log stale (${age_secs}s)"
  fi
  warn "orchestrator log appears stale (${age_secs}s)"
else
  pass "orchestrator log freshness ${age_secs}s"
fi

echo "VERIFY: PASS - runtime checks complete"
