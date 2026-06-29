#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/persona-copilot-session.sh <persona-or-seat> [options]

Persona values:
  ceo | ceo-copilot-2
  architect | architect-copilot

Options:
  --activate-current           Bind persona to current active Copilot session id
  --set-session-id <id>        Explicitly bind persona to a given Copilot session id
  --session-id                 Output only the resolved Copilot session id
  --path                       Output only the resolved Copilot session path
  --help                       Show this help

Notes:
  - Session map is stored at ~/.copilot/persona-sessions/<seat>.session
  - Current session is detected from COPILOT_SESSION_ID when present, otherwise
    from the newest folder in ~/.copilot/session-state/
  - Resolution order: explicit set > activate-current > stored map
USAGE
}

normalize_persona() {
  local raw="${1,,}"
  raw="${raw// /-}"
  raw="${raw//_/-}"
  case "$raw" in
    ceo|ceo-copilot|ceo-copilot-2)
      echo "ceo-copilot-2"
      ;;
    architect|architect-copilot)
      echo "architect-copilot"
      ;;
    *)
      return 1
      ;;
  esac
}

detect_current_session_id() {
  if [ -n "${COPILOT_SESSION_ID:-}" ] && [ -d "$STATE_ROOT/$COPILOT_SESSION_ID" ]; then
    printf '%s' "$COPILOT_SESSION_ID"
    return 0
  fi

  local newest=""
  newest="$(ls -1td "$STATE_ROOT"/* 2>/dev/null | head -1 || true)"
  if [ -n "$newest" ]; then
    basename "$newest"
  fi
}

persist_mapping() {
  local id="$1"
  mkdir -p "$MAP_DIR"
  printf '%s\n' "$id" > "$MAP_FILE"
}

if [ "${1:-}" = "--help" ] || [ "${1:-}" = "-h" ] || [ $# -eq 0 ]; then
  usage
  exit 0
fi

PERSONA_INPUT="$1"
shift

if ! SEAT_ID="$(normalize_persona "$PERSONA_INPUT")"; then
  echo "ERROR: unsupported persona/seat '$PERSONA_INPUT' (expected ceo/architect)." >&2
  exit 2
fi

STATE_ROOT="${COPILOT_STATE_ROOT:-$HOME/.copilot/session-state}"
MAP_DIR="${COPILOT_PERSONA_SESSION_MAP_DIR:-$HOME/.copilot/persona-sessions}"
MAP_FILE="$MAP_DIR/${SEAT_ID}.session"

ACTIVATE_CURRENT=0
OUTPUT_MODE="meta"
SET_SESSION_ID=""

while [ $# -gt 0 ]; do
  case "$1" in
    --activate-current)
      ACTIVATE_CURRENT=1
      shift
      ;;
    --set-session-id)
      [ $# -ge 2 ] || { echo "ERROR: --set-session-id requires a value." >&2; exit 2; }
      SET_SESSION_ID="$2"
      shift 2
      ;;
    --session-id)
      OUTPUT_MODE="session_id"
      shift
      ;;
    --path)
      OUTPUT_MODE="path"
      shift
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "ERROR: unknown option '$1'." >&2
      exit 2
      ;;
  esac
done

CURRENT_SESSION_ID="$(detect_current_session_id || true)"
STORED_SESSION_ID=""
if [ -f "$MAP_FILE" ]; then
  STORED_SESSION_ID="$(head -n1 "$MAP_FILE" | tr -d ' \t\r\n')"
fi

RESOLVED_SESSION_ID=""
SOURCE="none"

if [ -n "$SET_SESSION_ID" ]; then
  RESOLVED_SESSION_ID="$SET_SESSION_ID"
  SOURCE="explicit-set"
  persist_mapping "$RESOLVED_SESSION_ID"
elif [ "$ACTIVATE_CURRENT" -eq 1 ] && [ -n "$CURRENT_SESSION_ID" ]; then
  RESOLVED_SESSION_ID="$CURRENT_SESSION_ID"
  SOURCE="activated-current"
  persist_mapping "$RESOLVED_SESSION_ID"
elif [ -n "$STORED_SESSION_ID" ] && [ -d "$STATE_ROOT/$STORED_SESSION_ID" ]; then
  RESOLVED_SESSION_ID="$STORED_SESSION_ID"
  SOURCE="stored-map"
fi

RESOLVED_PATH=""
if [ -n "$RESOLVED_SESSION_ID" ]; then
  RESOLVED_PATH="$STATE_ROOT/$RESOLVED_SESSION_ID"
fi

case "$OUTPUT_MODE" in
  session_id)
    printf '%s\n' "$RESOLVED_SESSION_ID"
    ;;
  path)
    printf '%s\n' "$RESOLVED_PATH"
    ;;
  meta)
    printf 'seat=%s\n' "$SEAT_ID"
    printf 'resolved_session_id=%s\n' "$RESOLVED_SESSION_ID"
    printf 'resolved_session_path=%s\n' "$RESOLVED_PATH"
    printf 'current_session_id=%s\n' "$CURRENT_SESSION_ID"
    printf 'stored_session_id=%s\n' "$STORED_SESSION_ID"
    printf 'source=%s\n' "$SOURCE"
    printf 'map_file=%s\n' "$MAP_FILE"
    ;;
esac
