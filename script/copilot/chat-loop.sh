#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Usage:
  ./script/copilot/chat-loop.sh [options] [-- <extra copilot args>]

Starts a simple command-line chat loop that sends each message to the Copilot CLI
and keeps context by reusing a persisted session id.

Options:
  --new                 Start a fresh session (ignores saved session id)
  --session-id <id>     Use a specific session id (overrides saved session id)
  --session-file <path> Where to store the session id (default: ~/.copilot/wrappers/<repo>.session)
  --model <model>       Pass through as: copilot --model <model>
  --allow-all           Pass through as: copilot --allow-all
  --allow-all-tools     Pass through as: copilot --allow-all-tools
  --yolo                Pass through as: copilot --yolo
  -h, --help            Show this help

In-loop commands:
  :exit                 Quit
  :new                  Start a new session (new session id)
  :session              Print current session id
EOF
}

if ! command -v copilot >/dev/null 2>&1; then
  echo "[ERROR] copilot CLI not found on PATH" >&2
  echo "Install: npm install -g @github/copilot" >&2
  exit 1
fi

NEW_SESSION=false
SESSION_ID=""
SESSION_FILE=""
MODEL=""
PASSTHRU_ARGS=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    --new)
      NEW_SESSION=true
      shift
      ;;
    --session-id)
      SESSION_ID="${2:-}"
      shift 2
      ;;
    --session-file)
      SESSION_FILE="${2:-}"
      shift 2
      ;;
    --model)
      MODEL="${2:-}"
      shift 2
      ;;
    --allow-all|--allow-all-tools|--yolo)
      PASSTHRU_ARGS+=("$1")
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    --)
      shift
      PASSTHRU_ARGS+=("$@")
      break
      ;;
    *)
      # Unknown args: pass through to copilot.
      PASSTHRU_ARGS+=("$1")
      shift
      ;;
  esac
done

if [[ -z "$SESSION_FILE" ]]; then
  REPO_NAME="$(basename "$(pwd)")"
  SESSION_FILE="$HOME/.copilot/wrappers/${REPO_NAME}.session"
fi

mkdir -p "$(dirname "$SESSION_FILE")"

new_uuid() {
  if command -v uuidgen >/dev/null 2>&1; then
    uuidgen
    return
  fi

  python3 - <<'PY'
import uuid
print(uuid.uuid4())
PY
}

load_or_create_session() {
  if [[ -n "$SESSION_ID" ]]; then
    return
  fi

  if [[ "$NEW_SESSION" == "true" ]]; then
    SESSION_ID="$(new_uuid)"
    printf '%s\n' "$SESSION_ID" > "$SESSION_FILE"
    return
  fi

  if [[ -f "$SESSION_FILE" ]]; then
    SESSION_ID="$(head -n1 "$SESSION_FILE" | tr -d ' \t\r\n')"
  fi

  if [[ -z "$SESSION_ID" ]]; then
    SESSION_ID="$(new_uuid)"
    printf '%s\n' "$SESSION_ID" > "$SESSION_FILE"
  fi
}

load_or_create_session

echo "=== Copilot Chat Loop ==="
echo "Session: $SESSION_ID"
echo "Type :exit to quit, :new to start a new session"
echo

BASE_ARGS=(--resume "$SESSION_ID")
if [[ -n "$MODEL" ]]; then
  BASE_ARGS+=(--model "$MODEL")
fi

# Keep output readable in a loop.
BASE_ARGS+=(--silent)

while true; do
  # -r: don't treat backslashes specially
  # -e: readline editing if available
  read -r -e -p "you> " MESSAGE || echo

  MESSAGE="${MESSAGE:-}"
  if [[ -z "$MESSAGE" ]]; then
    continue
  fi

  case "$MESSAGE" in
    :exit|:quit)
      exit 0
      ;;
    :session)
      echo "$SESSION_ID"
      continue
      ;;
    :new)
      SESSION_ID="$(new_uuid)"
      printf '%s\n' "$SESSION_ID" > "$SESSION_FILE"
      echo "[new session] $SESSION_ID"
      BASE_ARGS=(--resume "$SESSION_ID")
      if [[ -n "$MODEL" ]]; then
        BASE_ARGS+=(--model "$MODEL")
      fi
      BASE_ARGS+=(--silent)
      continue
      ;;
  esac

  # One prompt per line, but keep conversation context via --resume.
  copilot "${BASE_ARGS[@]}" "${PASSTHRU_ARGS[@]}" -p "$MESSAGE" || true
  echo

done
