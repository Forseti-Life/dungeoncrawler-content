#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/1-copilot.sh [session-name]

Interactive commands:
  :help        Show this help
  :show        Show current session info
  :session ID  Switch session name
  :mode MODE   Set mode (chat|suggest|explain|shell|git|gh)
  :exit        Quit

Environment overrides:
  COPILOT_BIN               Path to copilot executable
  COPILOT_MODEL             Default: gpt-5.3-codex (chat-capable CLI only)
  COPILOT_REQUIRE_AGENTIC   Default: 1 (set 0 to allow non-agentic fallback)
  COPILOT_REQUIRE_MODEL     Default: 1 (set 0 to allow default model fallback)
  COPILOT_SESSION_NAMESPACE Default: manual
  COPILOT_TIMEOUT_SEC       Default: 900
  COPILOT_TIMEOUT_KILL_SEC  Default: 10
  COPILOT_LOOP_LOG          Default: 1 (set 0 to disable transcript log)
  COPILOT_BEDROCK_FALLBACK  Default: 1 (fallback to Bedrock assistant)
  COPILOT_BEDROCK_SITE      Default: forseti
  COPILOT_BEDROCK_SYSTEM_PROMPT_NODE_ID  Optional prompt node id override
  COPILOT_BEDROCK_HISTORY_LINES          History lines to inject into Bedrock prompts (default: 220)
  COPILOT_BEDROCK_HISTORY_MAX_LINES      Max lines retained in local Bedrock memory file (default: 1200)
  COPILOT_BEDROCK_PROMPT_PREFIX          Optional prompt prefix override
  BEDROCK_ASSIST_SCRIPT     Default: ./scripts/hq-bedrock-chat.sh
USAGE
}

export PATH="$HOME/.npm-global/bin:$HOME/.local/bin:/usr/local/bin:/usr/bin:/bin:${PATH:-}"

COPILOT_BIN="${COPILOT_BIN:-$(command -v copilot 2>/dev/null || true)}"
if [ -z "$COPILOT_BIN" ] && [ -x "$HOME/.npm-global/bin/copilot" ]; then
  COPILOT_BIN="$HOME/.npm-global/bin/copilot"
fi

BEDROCK_ASSIST_SCRIPT="${BEDROCK_ASSIST_SCRIPT:-$ROOT_DIR/scripts/hq-bedrock-chat.sh}"
BEDROCK_FALLBACK="${COPILOT_BEDROCK_FALLBACK:-1}"
BEDROCK_SITE="${COPILOT_BEDROCK_SITE:-forseti}"
BEDROCK_SYSTEM_PROMPT_NODE_ID="${COPILOT_BEDROCK_SYSTEM_PROMPT_NODE_ID:-}"
BEDROCK_PROMPT_PREFIX_DEFAULT=$'You are ceo-copilot, the internal Forseti HQ operations assistant.\\nOperate in repo/runtime context, not public website marketing mode.\\nPriorities: production diagnostics, deploy/runtime health, queue triage, script execution guidance, and concrete next actions.\\nKeep responses concise and operator-focused. If context is missing, ask targeted follow-up questions.'
BEDROCK_PROMPT_PREFIX="${COPILOT_BEDROCK_PROMPT_PREFIX:-$BEDROCK_PROMPT_PREFIX_DEFAULT}"

SESSION_NAME="${1:-interactive-loop}"
SESSION_FILE=""
SESSION_ID=""
BEDROCK_HISTORY_FILE=""
LOG_DIR="inbox/responses/copilot-prompt-loop"
LOG_FILE=""
CLI_MODE="unknown"
PROMPT_MODE="chat"
COPILOT_SUPPORTS_MODEL=0
COPILOT_SUPPORTS_AGENTIC_CHAT=0
CHAT_MODEL="${COPILOT_MODEL:-gpt-5.3-codex}"
SESSION_NAMESPACE=""
BEDROCK_HISTORY_LINES="${COPILOT_BEDROCK_HISTORY_LINES:-220}"
BEDROCK_HISTORY_MAX_LINES="${COPILOT_BEDROCK_HISTORY_MAX_LINES:-1200}"

if ! [[ "$BEDROCK_HISTORY_LINES" =~ ^[0-9]+$ ]]; then
  BEDROCK_HISTORY_LINES=220
fi
if ! [[ "$BEDROCK_HISTORY_MAX_LINES" =~ ^[0-9]+$ ]]; then
  BEDROCK_HISTORY_MAX_LINES=1200
fi

sanitize_name() {
  printf '%s' "$1" | tr -cs 'A-Za-z0-9._-' '-'
}

SESSION_NAMESPACE="$(sanitize_name "${COPILOT_SESSION_NAMESPACE:-manual}")"
if [ -z "$SESSION_NAMESPACE" ]; then
  SESSION_NAMESPACE="manual"
fi

refresh_session() {
  local raw_name="$1"
  SESSION_NAME="$(sanitize_name "$raw_name")"
  [ -n "$SESSION_NAME" ] || SESSION_NAME="interactive-loop"

  SESSION_FILE="$HOME/.copilot/wrappers/${SESSION_NAMESPACE}-${SESSION_NAME}.session"
  mkdir -p "$(dirname "$SESSION_FILE")"
  if [ ! -f "$SESSION_FILE" ] || [ -z "$(head -n1 "$SESSION_FILE" | tr -d ' \t\r\n')" ]; then
    if command -v uuidgen >/dev/null 2>&1; then
      uuidgen > "$SESSION_FILE"
    else
      python3 - <<'PY' > "$SESSION_FILE"
import uuid
print(uuid.uuid4())
PY
    fi
  fi
  SESSION_ID="$(head -n1 "$SESSION_FILE" | tr -d ' \t\r\n')"
  BEDROCK_HISTORY_FILE="$HOME/.copilot/wrappers/${SESSION_NAMESPACE}-${SESSION_NAME}.bedrock-history.log"
  touch "$BEDROCK_HISTORY_FILE"

  mkdir -p "$LOG_DIR"
  LOG_FILE="$LOG_DIR/hq-${SESSION_NAME}.md"
}

append_log() {
  [ "${COPILOT_LOOP_LOG:-1}" = "1" ] || return 0
  local role="$1"
  local body="$2"
  {
    echo ""
    echo "## ${role} $(date -Iseconds)"
    echo ""
    echo "$body"
  } >> "$LOG_FILE"
}

append_bedrock_history() {
  local role="$1"
  local body="$2"

  [ -n "$BEDROCK_HISTORY_FILE" ] || return 0
  {
    echo ""
    echo "### ${role} $(date -Iseconds)"
    echo "$body"
  } >> "$BEDROCK_HISTORY_FILE"

  local tmpfile
  tmpfile="$(mktemp)"
  trap 'rm -f "$tmpfile"' EXIT
  tail -n "$BEDROCK_HISTORY_MAX_LINES" "$BEDROCK_HISTORY_FILE" > "$tmpfile" || true
  mv "$tmpfile" "$BEDROCK_HISTORY_FILE"
  trap - EXIT
}

sanitize_persisted_output() {
  printf '%s\n' "$1" | awk '
    /^\[hq-bedrock-chat\]/ { next }
    /^\[bedrock-assist\]/ { next }
    /^[[:space:]]*\[warning\][[:space:]]*Drush command terminated abnormally\.?[[:space:]]*$/ { next }
    { print }
  '
}

detect_cli_mode() {
  COPILOT_SUPPORTS_MODEL=0
  COPILOT_SUPPORTS_AGENTIC_CHAT=0

  if [ -z "$COPILOT_BIN" ]; then
    CLI_MODE="unknown"
    PROMPT_MODE="chat"
    return 0
  fi

  local help
  help="$($COPILOT_BIN --help 2>&1 || true)"

  if printf '%s' "$help" | grep -q -- '--resume'; then
    CLI_MODE="chat"
    PROMPT_MODE="chat"
    COPILOT_SUPPORTS_AGENTIC_CHAT=1
    if printf '%s' "$help" | grep -q -- '--model'; then
      COPILOT_SUPPORTS_MODEL=1
    fi
    return 0
  fi

  if printf '%s' "$help" | grep -qE 'what-the-shell|git-assist|gh-assist'; then
    CLI_MODE="legacy"
    PROMPT_MODE="shell"
    return 0
  fi

  if printf '%s' "$help" | grep -qE 'suggest[[:space:]]+|explain[[:space:]]+'; then
    CLI_MODE="plugin"
    PROMPT_MODE="suggest"
    return 0
  fi

  CLI_MODE="unknown"
  PROMPT_MODE="chat"
}

enable_bedrock_fallback() {
  if [ "$BEDROCK_FALLBACK" != "1" ]; then
    return 1
  fi
  if [ ! -x "$BEDROCK_ASSIST_SCRIPT" ]; then
    return 1
  fi
  CLI_MODE="bedrock"
  PROMPT_MODE="bedrock"
  return 0
}

refresh_session "$SESSION_NAME"
detect_cli_mode

if [ "${COPILOT_REQUIRE_AGENTIC:-1}" = "1" ] && [ "$COPILOT_SUPPORTS_AGENTIC_CHAT" != "1" ]; then
  if enable_bedrock_fallback; then
    echo "WARN: Copilot agentic chat unavailable; using Bedrock fallback ($BEDROCK_SITE)." >&2
  else
    echo "ERROR: agentic mode requires a chat-capable Copilot CLI (supports --resume, -p, --allow-all)." >&2
    echo "Found mode: $CLI_MODE using ${COPILOT_BIN:-[not-found]}" >&2
    echo "Set COPILOT_REQUIRE_AGENTIC=0 for non-agentic modes, or enable COPILOT_BEDROCK_FALLBACK=1 with an executable BEDROCK_ASSIST_SCRIPT." >&2
    exit 2
  fi
fi

if [ "$CLI_MODE" = "chat" ] && [ "${COPILOT_REQUIRE_MODEL:-1}" = "1" ] && [ "$COPILOT_SUPPORTS_MODEL" != "1" ]; then
  echo "ERROR: this chat-capable Copilot CLI does not expose --model; cannot enforce COPILOT_MODEL=$CHAT_MODEL." >&2
  echo "Set COPILOT_REQUIRE_MODEL=0 to use the CLI default model." >&2
  exit 2
fi

run_prompt() {
  local prompt="$1"
  local model_args=()

  if [ "$CLI_MODE" = "legacy" ]; then
    case "$PROMPT_MODE" in
      shell) "$COPILOT_BIN" what-the-shell "$prompt" ;;
      git) "$COPILOT_BIN" git-assist "$prompt" ;;
      gh) "$COPILOT_BIN" gh-assist "$prompt" ;;
      *) "$COPILOT_BIN" what-the-shell "$prompt" ;;
    esac
    return 0
  fi

  if [ "$CLI_MODE" = "plugin" ]; then
    if [ "$PROMPT_MODE" = "explain" ]; then
      "$COPILOT_BIN" explain "$prompt"
    else
      "$COPILOT_BIN" suggest -t "${COPILOT_SUGGEST_TARGET:-shell}" "$prompt"
    fi
    return 0
  fi

  if [ "$CLI_MODE" = "chat" ]; then
    if [ "$COPILOT_SUPPORTS_MODEL" = "1" ] && [ -n "$CHAT_MODEL" ]; then
      model_args=(--model "$CHAT_MODEL")
    fi
    if command -v timeout >/dev/null 2>&1; then
      timeout -k "${COPILOT_TIMEOUT_KILL_SEC:-10}" "${COPILOT_TIMEOUT_SEC:-900}" \
        "$COPILOT_BIN" --resume "$SESSION_ID" --silent --allow-all "${model_args[@]}" -p "$prompt"
    else
      "$COPILOT_BIN" --resume "$SESSION_ID" --silent --allow-all "${model_args[@]}" -p "$prompt"
    fi
    return 0
  fi

  if [ "$CLI_MODE" = "bedrock" ]; then
    local bedrock_prompt
  if [ "$(basename "$BEDROCK_ASSIST_SCRIPT")" = "hq-bedrock-chat.sh" ]; then
    bedrock_prompt="$prompt"
  elif [ -n "$BEDROCK_PROMPT_PREFIX" ]; then
      bedrock_prompt="$BEDROCK_PROMPT_PREFIX"
      bedrock_prompt+=$'\\n\\nOperator request:\\n'
      bedrock_prompt+="$prompt"
    else
      bedrock_prompt="$prompt"
    fi

    if [ -n "$BEDROCK_SYSTEM_PROMPT_NODE_ID" ]; then
      HQ_BEDROCK_HISTORY_FILE="$BEDROCK_HISTORY_FILE" \
      HQ_BEDROCK_HISTORY_LINES="$BEDROCK_HISTORY_LINES" \
      BEDROCK_OPERATION="hq_copilot_loop" \
      BEDROCK_SYSTEM_PROMPT_NODE_ID="$BEDROCK_SYSTEM_PROMPT_NODE_ID" \
      "$BEDROCK_ASSIST_SCRIPT" "$BEDROCK_SITE" "$bedrock_prompt"
    else
      HQ_BEDROCK_HISTORY_FILE="$BEDROCK_HISTORY_FILE" \
      HQ_BEDROCK_HISTORY_LINES="$BEDROCK_HISTORY_LINES" \
      BEDROCK_OPERATION="hq_copilot_loop" "$BEDROCK_ASSIST_SCRIPT" "$BEDROCK_SITE" "$bedrock_prompt"
    fi
    return 0
  fi

  echo "ERROR: unsupported copilot CLI mode; expected chat (--resume), plugin/legacy, or bedrock fallback." >&2
  return 1
}

echo "Copilot prompt loop"
echo "Session name: $SESSION_NAME"
echo "Session file: $SESSION_FILE"
echo "Session id:   $SESSION_ID"
echo "Namespace:    $SESSION_NAMESPACE"
echo "Transcript:   $LOG_FILE"
echo "Memory file:  $BEDROCK_HISTORY_FILE"
echo "Copilot bin:  ${COPILOT_BIN:-[not-found]}"
echo "CLI mode:     $CLI_MODE"
if [ "$CLI_MODE" = "chat" ]; then
  echo "Agentic:      enabled (--allow-all)"
  if [ "$COPILOT_SUPPORTS_MODEL" = "1" ]; then
    echo "Model:        $CHAT_MODEL"
  else
    echo "Model:        default (this copilot binary does not expose --model)"
  fi
fi
if [ "$CLI_MODE" = "plugin" ] || [ "$CLI_MODE" = "legacy" ]; then
  echo "Prompt mode:  $PROMPT_MODE"
fi
if [ "$CLI_MODE" = "bedrock" ]; then
  echo "Backend:      bedrock-assist"
  echo "Bedrock site: $BEDROCK_SITE"
fi
echo "Type :help for commands."

while true; do
  if [ -t 1 ]; then
    printf 'copilot> '
  fi

  if ! IFS= read -r line; then
    echo
    exit 0
  fi

  line="${line:-}"
  [ -n "$line" ] || continue

  case "$line" in
    :exit|:quit)
      exit 0
      ;;
    :help)
      usage
      continue
      ;;
    :show)
      echo "Session name: $SESSION_NAME"
      echo "Session file: $SESSION_FILE"
      echo "Session id:   $SESSION_ID"
      echo "Namespace:    $SESSION_NAMESPACE"
      echo "Transcript:   $LOG_FILE"
      echo "Memory file:  $BEDROCK_HISTORY_FILE"
      echo "Copilot bin:  ${COPILOT_BIN:-[not-found]}"
      echo "CLI mode:     $CLI_MODE"
      if [ "$CLI_MODE" = "plugin" ] || [ "$CLI_MODE" = "legacy" ]; then
        echo "Prompt mode:  $PROMPT_MODE"
      fi
      if [ "$CLI_MODE" = "chat" ]; then
        echo "Agentic:      enabled (--allow-all)"
      fi
      if [ "$CLI_MODE" = "bedrock" ]; then
        echo "Backend:      bedrock-assist"
        echo "Bedrock site: $BEDROCK_SITE"
      fi
      continue
      ;;
    :session\ *)
      refresh_session "${line#:session }"
      echo "Session set: $SESSION_NAME"
      echo "Session file: $SESSION_FILE"
      echo "Session id:   $SESSION_ID"
      echo "Namespace:    $SESSION_NAMESPACE"
      echo "Transcript:   $LOG_FILE"
      continue
      ;;
    :mode\ *)
      mode_value="${line#:mode }"
      case "$mode_value" in
        chat)
          if [ "$CLI_MODE" != "chat" ]; then
            echo "chat mode is unavailable with this backend"
          else
            PROMPT_MODE="chat"
            echo "Prompt mode set: $PROMPT_MODE"
          fi
          ;;
        suggest|explain)
          if [ "$CLI_MODE" != "plugin" ]; then
            echo "plugin modes are unavailable with this backend"
          else
            PROMPT_MODE="$mode_value"
            echo "Prompt mode set: $PROMPT_MODE"
          fi
          ;;
        shell|git|gh)
          if [ "$CLI_MODE" != "legacy" ]; then
            echo "legacy modes are unavailable with this backend"
          else
            PROMPT_MODE="$mode_value"
            echo "Prompt mode set: $PROMPT_MODE"
          fi
          ;;
        *)
          echo "usage: :mode <chat|suggest|explain|shell|git|gh>"
          ;;
      esac
      continue
      ;;
  esac

  append_log "User" "$line"
  if [ "$CLI_MODE" = "bedrock" ]; then
    append_bedrock_history "User" "$line"
  fi

  start_ts="$(date +%s)"
  echo "[working...]" >&2

  tmp_out="$(mktemp)"
  : > "$tmp_out"
  rc=0

  run_prompt "$line" >"$tmp_out" 2>&1 &
  prompt_pid=$!

  tail -n +1 -f "$tmp_out" &
  tail_pid=$!

  while kill -0 "$prompt_pid" 2>/dev/null; do
    sleep 5
    if kill -0 "$prompt_pid" 2>/dev/null; then
      now_ts="$(date +%s)"
      tick_elapsed=$((now_ts - start_ts))
      if [ "$tick_elapsed" -lt 0 ]; then
        tick_elapsed=0
      fi
      echo "[working... ${tick_elapsed}s]" >&2
    fi
  done

  if ! wait "$prompt_pid"; then
    rc=$?
  fi

  sleep 0.1
  kill "$tail_pid" >/dev/null 2>&1 || true
  wait "$tail_pid" 2>/dev/null || true

  end_ts="$(date +%s)"
  elapsed_sec=$((end_ts - start_ts))
  if [ "$elapsed_sec" -lt 0 ]; then
    elapsed_sec=0
  fi

  out="$(cat "$tmp_out")"
  rm -f "$tmp_out"

  if [ "$rc" -ne 0 ]; then
    persisted_out="$out"
    if [ "$CLI_MODE" = "bedrock" ]; then
      persisted_out="$(sanitize_persisted_output "$out")"
    fi
    echo "[copilot exited with code $rc]"
    append_log "Copilot (error $rc)" "$persisted_out"
    if [ "$CLI_MODE" = "bedrock" ]; then
      append_bedrock_history "Assistant (error $rc)" "$persisted_out"
    fi
    echo "[done in ${elapsed_sec}s]" >&2
    continue
  fi

  persisted_out="$out"
  if [ "$CLI_MODE" = "bedrock" ]; then
    persisted_out="$(sanitize_persisted_output "$out")"
  fi

  append_log "Copilot" "$persisted_out"
  if [ "$CLI_MODE" = "bedrock" ]; then
    append_bedrock_history "Assistant" "$persisted_out"
  fi
  echo "[done in ${elapsed_sec}s]" >&2
  echo

done
