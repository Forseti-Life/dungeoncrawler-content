#!/usr/bin/env bash
# llm/validate.sh — Validate the local LLM environment end-to-end.
#
# Checks:
#   1. Python + required packages
#   2. model-manifest.yaml + routing.yaml structure
#   3. Which models are present on disk
#   4. Agent routing resolution (which agent → which model)
#   5. (Optional) Live inference test with a minimal prompt
#
# Usage:
#   ./llm/validate.sh               # Full validation (no inference)
#   ./llm/validate.sh --test-run    # Also run a live inference test on first available model
#   ./llm/validate.sh --agent <id>  # Show routing for a specific agent ID

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LLM_DIR="$ROOT_DIR/llm"

if [ -x "$LLM_DIR/.venv/bin/python3" ]; then
  PYTHON_BIN="$LLM_DIR/.venv/bin/python3"
elif [ -n "${LLM_PYTHON_BIN:-}" ] && [ -x "$LLM_PYTHON_BIN" ]; then
  PYTHON_BIN="$LLM_PYTHON_BIN"
else
  PYTHON_BIN="$(command -v python3 2>/dev/null || true)"
  [ -n "$PYTHON_BIN" ] || { echo "ERROR: python3 not found. Run: llm/setup.sh" >&2; exit 1; }
fi

cd "$ROOT_DIR"

ARG="${1:-}"
ARG2="${2:-}"

# ── agent routing lookup ──────────────────────────────────────────────────────

if [ "$ARG" = "--agent" ]; then
  [ -n "$ARG2" ] || { echo "Usage: $0 --agent <agent-id>" >&2; exit 1; }
  "$PYTHON_BIN" - "$ARG2" <<'PY'
import sys, pathlib

agent_id = sys.argv[1]
sys.path.insert(0, str(pathlib.Path(".").resolve()))

from llm.lib.routing import load_yaml, resolve_model_id, parse_agents_yaml

routing     = load_yaml(pathlib.Path("llm/routing.yaml"))
manifest    = load_yaml(pathlib.Path("llm/model-manifest.yaml"))
agents_yaml = pathlib.Path("org-chart/agents/agents.yaml")
models_dir  = pathlib.Path("llm/models")

# Determine resolution label
agents   = parse_agents_yaml(agents_yaml)
role     = (agents.get(agent_id) or {}).get("role") or ""
model_id = (routing.get("agents") or {}).get(agent_id)
if model_id:
    resolution = "agent override"
elif role and (routing.get("roles") or {}).get(role):
    model_id   = routing["roles"][role]
    resolution = f"role ({role})"
else:
    model_id   = routing.get("default", "copilot")
    resolution = "default"

model_info = next((m for m in manifest.get("models", []) if m.get("id") == model_id), None)

print(f"\nAgent:      {agent_id}")
print(f"Model ID:   {model_id}")
print(f"Resolution: {resolution}")
if model_id == "copilot":
    print("Backend:    GitHub Copilot CLI (external)")
elif model_info:
    filename = model_info.get("filename", "")
    local    = models_dir / filename
    status   = "present on disk" if local.exists() else "NOT downloaded"
    print(f"File:       llm/models/{filename}")
    print(f"Status:     {status}")
    if not local.exists():
        print(f"\n  To download: ./llm/download-models.sh {model_id}")
        print("  (Will fall back to Copilot CLI until downloaded)")
else:
    print(f"  WARNING: model '{model_id}' not found in model-manifest.yaml")
print()
PY
  exit 0
fi

# ── full validation ──────────────────────────────────────────────────────────

echo ""
echo "=== LLM Validate: copilot-sessions-hq ==="
echo ""

# Python deps + dirs
"$PYTHON_BIN" "$LLM_DIR/runner.py" --dry-run

# Model routing summary
echo ""
echo "--- Agent routing summary ---"
"$PYTHON_BIN" - <<'PY'
import sys, pathlib
sys.path.insert(0, str(pathlib.Path(".").resolve()))

from llm.lib.routing import load_yaml, resolve_model_id, parse_agents_yaml

routing     = load_yaml(pathlib.Path("llm/routing.yaml"))
manifest    = load_yaml(pathlib.Path("llm/model-manifest.yaml"))
agents_yaml = pathlib.Path("org-chart/agents/agents.yaml")
models_dir  = pathlib.Path("llm/models")

model_files = {m["id"]: m.get("filename", "") for m in manifest.get("models", [])}
agents      = parse_agents_yaml(agents_yaml)

print(f"\n  {'AGENT':<40} {'ROLE':<20} {'MODEL':<25} STATUS")
print(f"  {'-'*40} {'-'*20} {'-'*25} {'-'*15}")
for agent_id, info in sorted(agents.items()):
    if info.get("paused"):
        continue
    role     = info.get("role") or ""
    model_id = resolve_model_id(agent_id, routing, agents_yaml)

    if model_id == "copilot":
        status = "copilot (external)"
    else:
        fname = model_files.get(model_id, "")
        status = "local [ready]" if fname and (models_dir / fname).exists() else "local [not downloaded]"

    print(f"  {agent_id:<40} {role:<20} {model_id:<25} {status}")
print()
PY

# Optional live test
if [ "$ARG" = "--test-run" ]; then
  echo ""
  echo "--- Live inference test ---"
  TEST_MODEL="$("$PYTHON_BIN" - <<'PY'
import sys, pathlib
sys.path.insert(0, str(pathlib.Path(".").resolve()))
from llm.lib.routing import load_yaml
models_dir = pathlib.Path("llm/models")
manifest = load_yaml(pathlib.Path("llm/model-manifest.yaml"))
for m in manifest.get("models", []):
    f = models_dir / m.get("filename", "")
    if f.exists():
        print(str(f))
        break
PY
  )"
  if [ -z "$TEST_MODEL" ]; then
    echo "  [SKIP] No models downloaded yet. Run: ./llm/download-models.sh"
  else
    echo "  Testing with: $(basename "$TEST_MODEL")"
    RESPONSE="$("$PYTHON_BIN" "$LLM_DIR/runner.py" \
      --session "validate-test" \
      --model "$TEST_MODEL" \
      --no-history \
      --prompt "Reply with exactly: VALIDATION OK" 2>/dev/null || true)"
    if [ -n "$RESPONSE" ]; then
      echo "  Response: $RESPONSE"
      echo "  [OK] Inference test passed"
    else
      echo "  [FAIL] Inference returned empty response"
    fi
  fi
fi

echo ""
echo "=== Validation complete ==="
echo ""
