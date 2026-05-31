#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DEFAULT_STATE_FILE="/var/tmp/copilot-sessions-hq/org-control.json"
LEGACY_STATE_FILE="tmp/org-control.json"
STATE_FILE="${ORG_CONTROL_FILE:-$DEFAULT_STATE_FILE}"

# Ensure the state directory exists (especially for /var/tmp default).
mkdir -p "$(dirname "$STATE_FILE")" 2>/dev/null || true

cmd="${1:-status}"
shift || true

reason=""
updated_by="${USER:-unknown}"
one_line=0
json_out=0
no_converge=0

while [ "$#" -gt 0 ]; do
  case "$1" in
    --reason)
      shift
      reason="${1:-}"
      ;;
    --by)
      shift
      updated_by="${1:-$updated_by}"
      ;;
    --one-line)
      one_line=1
      ;;
    --json)
      json_out=1
      ;;
    --no-converge)
      no_converge=1
      ;;
    *)
      echo "ERROR: unknown arg: $1" >&2
      exit 2
      ;;
  esac
  shift || true
done

# Safety: if invoked by a web server user (e.g., Drupal), do not spawn long-running
# loop processes under that user; rely on the cron watchdog to converge instead.
run_user="$(id -un 2>/dev/null || echo '')"
if [ "$run_user" = "www-data" ] && [ "$no_converge" -ne 1 ] && [ "${ORG_CONTROL_ALLOW_WEB_CONVERGE:-0}" != "1" ]; then
  no_converge=1
fi

read_state_json() {
  python3 - <<PY
import json
from pathlib import Path

p = Path("$STATE_FILE")
if not p.exists():
  # Backward-compatible fallback to legacy state file.
  legacy = Path("$LEGACY_STATE_FILE")
  if legacy.exists():
    p = legacy
  else:
    print(json.dumps({"enabled": True, "updated_at": None, "updated_by": None, "reason": None}))
    raise SystemExit(0)

try:
    data = json.loads(p.read_text(encoding="utf-8", errors="ignore") or "{}")
except Exception:
    data = {}

if not isinstance(data, dict):
    data = {}

data.setdefault("enabled", True)
data.setdefault("updated_at", None)
data.setdefault("updated_by", None)
data.setdefault("reason", None)

print(json.dumps(data))
PY
}

write_state_json() {
  local enabled="$1"
  local tmp
  local state_dir
  state_dir="$(dirname "$STATE_FILE")"

  # In sticky directories (like /tmp and /var/tmp), non-owners cannot rename/replace
  # another user's file. If the state file exists and we don't own it, we must
  # overwrite it in-place (we chmod 666 after writes).
  if [ -k "$state_dir" ] && [ -e "$STATE_FILE" ] && [ ! -O "$STATE_FILE" ]; then
    tmp=""
  fi

  # Prefer atomic write (tmp + mv) when we can create files in the state dir.
  if [ -z "${tmp:-}" ] && [ -w "$state_dir" ]; then
    tmp="${STATE_FILE}.tmp.$$"
  else
    tmp=""
  fi

  if [ -n "$tmp" ]; then
    python3 - "$enabled" "$updated_by" "$reason" <<'PY' >"$tmp"
import json
import sys
from datetime import datetime, timezone

enabled_s, updated_by, reason = sys.argv[1:4]
enabled = enabled_s.strip().lower() in ("1", "true", "yes", "on")

payload = {
    "enabled": enabled,
    "updated_at": datetime.now(timezone.utc).isoformat().replace('+00:00', 'Z'),
    "updated_by": updated_by or None,
    "reason": reason or None,
}
print(json.dumps(payload, sort_keys=True))
PY
    if mv "$tmp" "$STATE_FILE" 2>/dev/null; then
      chmod 666 "$STATE_FILE" 2>/dev/null || true
      return 0
    fi
    # Fallback: if atomic replace failed (common in sticky dirs), overwrite in-place.
    rm -f "$tmp" 2>/dev/null || true
  fi

  # Fallback: if the directory isn't writable (e.g. Drupal's www-data user),
  # try to overwrite the existing state file in-place.
  if [ ! -w "$STATE_FILE" ]; then
    echo "ERROR: state directory not writable and state file not writable: $STATE_FILE" >&2
    exit 1
  fi

  python3 - "$enabled" "$updated_by" "$reason" <<'PY' >"$STATE_FILE"
import json
import sys
from datetime import datetime, timezone

enabled_s, updated_by, reason = sys.argv[1:4]
enabled = enabled_s.strip().lower() in ("1", "true", "yes", "on")

payload = {
    "enabled": enabled,
    "updated_at": datetime.now(timezone.utc).isoformat().replace('+00:00', 'Z'),
    "updated_by": updated_by or None,
    "reason": reason or None,
}
print(json.dumps(payload, sort_keys=True))
PY

  chmod 666 "$STATE_FILE" 2>/dev/null || true
}

print_status() {
  local js
  js="$(read_state_json)"
  if [ "$json_out" -eq 1 ]; then
    printf '%s\n' "$js"
    return 0
  fi

  python3 - "$js" "$one_line" <<'PY'
import json, sys

data = json.loads(sys.argv[1])
one_line = sys.argv[2] == "1"

enabled = bool(data.get("enabled", True))
updated_at = data.get("updated_at")
updated_by = data.get("updated_by")
reason = data.get("reason")

if one_line:
    print(f"enabled={str(enabled).lower()} updated_at={updated_at or '-'} updated_by={updated_by or '-'} reason={reason or '-'}")
else:
    print(f"Org automation enabled: {'YES' if enabled else 'NO'}")
    if updated_at or updated_by or reason:
        print(f"Last change: {updated_at or '-'} by {updated_by or '-'}")
        if reason:
            print(f"Reason: {reason}")
PY
}

case "$cmd" in
  status)
    print_status
    ;;
  enable)
    write_state_json true
    print_status
    printf 'State file: %s\n' "$STATE_FILE" 2>/dev/null || true
    if [ "$no_converge" -ne 1 ] && [ -x "./scripts/hq-automation.sh" ]; then
      ./scripts/hq-automation.sh converge >/dev/null 2>&1 || true
    fi
    ;;
  disable)
    write_state_json false
    print_status
    printf 'State file: %s\n' "$STATE_FILE" 2>/dev/null || true
    if [ "$no_converge" -ne 1 ] && [ -x "./scripts/hq-automation.sh" ]; then
      ./scripts/hq-automation.sh converge >/dev/null 2>&1 || true
    fi
    ;;
  *)
    echo "Usage: $0 status|enable|disable [--reason TEXT] [--by NAME] [--one-line] [--json] [--no-converge]" >&2
    exit 2
    ;;
esac
