#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DEFAULT_STATE_FILE="/var/tmp/copilot-sessions-hq/org-control.json"
LEGACY_STATE_FILE="tmp/org-control.json"
STATE_FILE="${ORG_CONTROL_FILE:-$DEFAULT_STATE_FILE}"

# Backward-compatible migration:
# - Prefer the new default path when it exists.
# - If the new default path is missing but the legacy path exists, read legacy.
if [ -z "${ORG_CONTROL_FILE:-}" ] && [ ! -f "$STATE_FILE" ] && [ -f "$LEGACY_STATE_FILE" ]; then
    STATE_FILE="$LEGACY_STATE_FILE"
fi

python3 - <<PY
import json
from pathlib import Path

p = Path("$STATE_FILE")
if not p.exists():
    print("true")
    raise SystemExit(0)

try:
    data = json.loads(p.read_text(encoding="utf-8", errors="ignore") or "{}")
except Exception:
    data = {}

enabled = data.get("enabled", True)
print("true" if bool(enabled) else "false")
PY
