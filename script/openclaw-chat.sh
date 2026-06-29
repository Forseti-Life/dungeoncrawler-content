#!/bin/bash

set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  ./script/openclaw-chat.sh [options] --message "Your prompt"
  ./script/openclaw-chat.sh [options] "Your prompt"

Options:
  --message <text>       Prompt text (positional message also supported)
  --agent <id>           Agent id (default: main)
  --session-id <id>      Continue an existing session
  --timeout <seconds>    Agent timeout (default: 120)
  --remote               Use gateway mode instead of --local
  --json                 Print raw JSON output instead of text payload
  -h, --help             Show this help
EOF
}

if ! command -v openclaw >/dev/null 2>&1; then
    echo "[ERROR] openclaw is not installed or not on PATH" >&2
    exit 1
fi

AGENT_ID="main"
SESSION_ID=""
TIMEOUT="120"
LOCAL_MODE="true"
RAW_JSON="false"
MESSAGE=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --message)
            MESSAGE="$2"
            shift 2
            ;;
        --agent)
            AGENT_ID="$2"
            shift 2
            ;;
        --session-id)
            SESSION_ID="$2"
            shift 2
            ;;
        --timeout)
            TIMEOUT="$2"
            shift 2
            ;;
        --remote)
            LOCAL_MODE="false"
            shift
            ;;
        --json)
            RAW_JSON="true"
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            if [[ -z "$MESSAGE" ]]; then
                MESSAGE="$1"
                shift
            else
                echo "[ERROR] Unknown argument: $1" >&2
                usage
                exit 1
            fi
            ;;
    esac
done

if [[ -z "$MESSAGE" ]]; then
    echo "[ERROR] Message is required" >&2
    usage
    exit 1
fi

CMD=(openclaw agent --agent "$AGENT_ID" --message "$MESSAGE" --timeout "$TIMEOUT" --json)
if [[ "$LOCAL_MODE" == "true" ]]; then
    CMD+=(--local)
fi
if [[ -n "$SESSION_ID" ]]; then
    CMD+=(--session-id "$SESSION_ID")
fi

JSON_OUTPUT="$("${CMD[@]}")"

if [[ "$RAW_JSON" == "true" ]]; then
    printf '%s\n' "$JSON_OUTPUT"
    exit 0
fi

printf '%s' "$JSON_OUTPUT" | python3 - <<'PY'
import json, sys

raw = sys.stdin.read()
try:
    data = json.loads(raw)
except Exception:
    print(raw)
    raise SystemExit(0)

payloads = data.get("payloads") or []
if payloads and isinstance(payloads[0], dict):
    text = payloads[0].get("text")
    if text:
        print(text)
        raise SystemExit(0)

print(raw)
PY
