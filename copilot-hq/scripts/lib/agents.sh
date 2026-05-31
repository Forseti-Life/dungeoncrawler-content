#!/usr/bin/env bash
# Shared helpers for working with configured agent seats.
# Source this file from HQ scripts (cwd should be repo root).

# shellcheck shell=bash

# Print one agent id per line from org-chart/agents/agents.yaml.
configured_agent_ids() {
  python3 - <<'PY'
import yaml
from pathlib import Path
p = Path('org-chart/agents/agents.yaml')
if not p.exists():
    raise SystemExit(0)
data = yaml.safe_load(p.read_text(encoding='utf-8', errors='ignore'))
for agent in data.get('agents', []):
    if not agent.get('paused', False):
        print(agent['id'])
PY
}

# Return 0 (true) if the given agent id is a configured seat.
is_configured_agent() {
  local agent_id="$1"
  configured_agent_ids | grep -qx "$agent_id"
}

# Return 0 (true) if the given agent is currently paused.
is_paused() {
  local agent="$1"
  if [ -x "./scripts/is-agent-paused.sh" ]; then
    [ "$(./scripts/is-agent-paused.sh "$agent" 2>/dev/null || echo false)" = "true" ] && return 0
  fi
  return 1
}
