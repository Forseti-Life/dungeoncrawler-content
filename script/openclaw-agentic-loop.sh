#!/bin/bash

set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  ./script/openclaw-agentic-loop.sh --goal "Your goal" [options]

Options:
  --goal <text>            Required loop goal
  --agent <id>             Agent id (default: main)
  --session-id <id>        Reuse an existing session id
  --interval <seconds>     Pause between iterations (default: 20)
  --max-iterations <n>     Maximum loop iterations (default: 10)
  --timeout <seconds>      Agent timeout per iteration (default: 180)
  --stop-token <text>      Completion token on first line (default: STATUS: DONE)
  --remote                 Use gateway mode instead of --local
  --log-file <path>        Log file path (default: ~/.openclaw/loops/<timestamp>.log)
  -h, --help               Show this help

Protocol:
  The agent is prompted to put either "STATUS: CONTINUE" or "STATUS: DONE" on
  the first line of each response. The loop stops when it sees the stop token.
EOF
}

if ! command -v openclaw >/dev/null 2>&1; then
    echo "[ERROR] openclaw is not installed or not on PATH" >&2
    exit 1
fi

GOAL=""
AGENT_ID="main"
SESSION_ID=""
INTERVAL="20"
MAX_ITERATIONS="10"
TIMEOUT="180"
STOP_TOKEN="STATUS: DONE"
LOCAL_MODE="true"
LOG_FILE=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --goal)
            GOAL="$2"
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
        --interval)
            INTERVAL="$2"
            shift 2
            ;;
        --max-iterations)
            MAX_ITERATIONS="$2"
            shift 2
            ;;
        --timeout)
            TIMEOUT="$2"
            shift 2
            ;;
        --stop-token)
            STOP_TOKEN="$2"
            shift 2
            ;;
        --remote)
            LOCAL_MODE="false"
            shift
            ;;
        --log-file)
            LOG_FILE="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "[ERROR] Unknown argument: $1" >&2
            usage
            exit 1
            ;;
    esac
done

if [[ -z "$GOAL" ]]; then
    echo "[ERROR] --goal is required" >&2
    usage
    exit 1
fi

if ! [[ "$INTERVAL" =~ ^[0-9]+$ ]] || ! [[ "$MAX_ITERATIONS" =~ ^[0-9]+$ ]] || ! [[ "$TIMEOUT" =~ ^[0-9]+$ ]]; then
    echo "[ERROR] --interval, --max-iterations, and --timeout must be integers" >&2
    exit 1
fi

if [[ -z "$LOG_FILE" ]]; then
    LOOP_DIR="$HOME/.openclaw/loops"
    mkdir -p "$LOOP_DIR"
    LOG_FILE="$LOOP_DIR/loop-$(date +%Y%m%d-%H%M%S).log"
fi

echo "[INFO] Goal: $GOAL"
echo "[INFO] Agent: $AGENT_ID"
echo "[INFO] Max iterations: $MAX_ITERATIONS"
echo "[INFO] Interval: ${INTERVAL}s"
echo "[INFO] Stop token: $STOP_TOKEN"
echo "[INFO] Log file: $LOG_FILE"

{
    echo "=== OpenClaw Agentic Loop ==="
    echo "Started: $(date -Is)"
    echo "Goal: $GOAL"
    echo "Agent: $AGENT_ID"
    echo "Max iterations: $MAX_ITERATIONS"
    echo "Interval: ${INTERVAL}s"
    echo "Stop token: $STOP_TOKEN"
    echo
} >> "$LOG_FILE"

PREV_RESPONSE=""
CURRENT_SESSION="$SESSION_ID"

for ((i=1; i<=MAX_ITERATIONS; i++)); do
    PREV_TRUNCATED="$(printf '%s' "$PREV_RESPONSE" | tail -c 1200 2>/dev/null || true)"

    PROMPT=$(cat <<EOF
You are running in an execution loop.
Goal: $GOAL
Iteration: $i/$MAX_ITERATIONS

Rules:
1) First line must be exactly one of:
   STATUS: CONTINUE
   STATUS: DONE
2) Then provide concise progress details and next action.
3) If the goal is fully complete, use STATUS: DONE.

Previous iteration output (may be empty):
$PREV_TRUNCATED
EOF
)

    CMD=(openclaw agent --agent "$AGENT_ID" --message "$PROMPT" --timeout "$TIMEOUT" --json)
    if [[ "$LOCAL_MODE" == "true" ]]; then
        CMD+=(--local)
    fi
    if [[ -n "$CURRENT_SESSION" ]]; then
        CMD+=(--session-id "$CURRENT_SESSION")
    fi

    if ! JSON_OUTPUT="$("${CMD[@]}")"; then
        echo "[ERROR] Iteration $i failed to execute openclaw agent" | tee -a "$LOG_FILE"
        exit 1
    fi

    RESPONSE_TEXT=$(printf '%s' "$JSON_OUTPUT" | python3 - <<'PY'
import json, sys
raw = sys.stdin.read()
try:
    data = json.loads(raw)
except Exception:
    print(raw)
    raise SystemExit(0)
payloads = data.get("payloads") or []
if payloads and isinstance(payloads[0], dict):
    print(payloads[0].get("text") or "")
else:
    print(raw)
PY
)

    EXTRACTED_SESSION=$(printf '%s' "$JSON_OUTPUT" | python3 - <<'PY'
import json, sys
raw = sys.stdin.read()
try:
    data = json.loads(raw)
except Exception:
    print("")
    raise SystemExit(0)
print((((data.get("meta") or {}).get("agentMeta") or {}).get("sessionId")) or "")
PY
)

    if [[ -n "$EXTRACTED_SESSION" ]]; then
        CURRENT_SESSION="$EXTRACTED_SESSION"
    fi

    {
        echo "--- Iteration $i @ $(date -Is) ---"
        echo "$RESPONSE_TEXT"
        echo
    } >> "$LOG_FILE"

    echo "----- Iteration $i/$MAX_ITERATIONS -----"
    printf '%s\n' "$RESPONSE_TEXT"

    FIRST_LINE=$(printf '%s\n' "$RESPONSE_TEXT" | head -n1)
    if [[ "$FIRST_LINE" == "$STOP_TOKEN" ]]; then
        echo "[INFO] Stop token detected. Loop complete." | tee -a "$LOG_FILE"
        echo "[INFO] Session ID: ${CURRENT_SESSION:-unknown}" | tee -a "$LOG_FILE"
        exit 0
    fi

    PREV_RESPONSE="$RESPONSE_TEXT"
    if (( i < MAX_ITERATIONS )); then
        sleep "$INTERVAL"
    fi
done

echo "[INFO] Reached max iterations without stop token." | tee -a "$LOG_FILE"
echo "[INFO] Session ID: ${CURRENT_SESSION:-unknown}" | tee -a "$LOG_FILE"
