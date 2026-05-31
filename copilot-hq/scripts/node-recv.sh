#!/usr/bin/env bash
# node-recv.sh — Pull latest from remote and process this node's inbox.
# Usage: node-recv.sh [--dry-run]
#
# For each pending message in sessions/node-<NODE_ID>/inbox/:
#   - command/query/escalation → routed to CEO inbox (master) or dev-jobhunter inbox (worker)
#   - reply   → routed to the originating agent's inbox
#   - fyi     → logged to outbox; no agent dispatch
#
# After routing, marks message status: processed and archives it.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DRY_RUN=0
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=1

# Load local node identity
IDENTITY_FILE="$ROOT_DIR/node-identity.conf"
[[ -f "$IDENTITY_FILE" ]] && source "$IDENTITY_FILE"
THIS_NODE="${NODE_ID:-master}"
NODE_ROLE_LOCAL="${NODE_ROLE:-master}"

INBOX_DIR="$ROOT_DIR/sessions/node-${THIS_NODE}/inbox"
ARCHIVE_DIR="$ROOT_DIR/sessions/node-${THIS_NODE}/outbox"
mkdir -p "$INBOX_DIR" "$ARCHIVE_DIR"

# Inter-node messages always route to the CEO on this node.
# The CEO then delegates to dev agents as needed.
if [[ "$NODE_ROLE_LOCAL" == "master" ]]; then
  DEFAULT_AGENT="ceo-copilot-2"
else
  # Worker CEO seat is the first point of contact — not a dev agent directly.
  DEFAULT_AGENT="${NODE_CEO_AGENT:-ceo-dev-laptop}"
fi

echo "[node-recv] Node: ${THIS_NODE} (${NODE_ROLE_LOCAL}) | Routing to: ${DEFAULT_AGENT}"
echo "[node-recv] Pulling latest from remote..."
git pull --rebase --quiet 2>&1 | tail -2

PROCESSED=0
SKIPPED=0

for MSG_FILE in "$INBOX_DIR"/*.md; do
  [[ -f "$MSG_FILE" ]] || continue
  MSG_BASENAME="$(basename "$MSG_FILE")"

  # Read status from front matter
  STATUS="$(grep -m1 '^status:' "$MSG_FILE" 2>/dev/null | sed 's/status:[[:space:]]*//')"
  if [[ "$STATUS" != "pending" ]]; then
    (( SKIPPED++ )) || true
    continue
  fi

  MSG_ID="$(grep -m1 '^id:' "$MSG_FILE" | sed 's/id:[[:space:]]*//')"
  FROM="$(grep -m1 '^from:' "$MSG_FILE" | sed 's/from:[[:space:]]*//')"
  MSG_TYPE="$(grep -m1 '^type:' "$MSG_FILE" | sed 's/type:[[:space:]]*//')"
  SUBJECT="$(grep -m1 '^subject:' "$MSG_FILE" | sed 's/subject:[[:space:]]*//')"

  echo "[node-recv] Processing: ${MSG_BASENAME} | type=${MSG_TYPE} from=${FROM}"

  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "  [DRY RUN] Would route to ${DEFAULT_AGENT} inbox"
    continue
  fi

  # Route message into agent inbox
  case "$MSG_TYPE" in
    command|query|escalation)
      AGENT_INBOX="$ROOT_DIR/sessions/${DEFAULT_AGENT}/inbox"
      mkdir -p "$AGENT_INBOX"
      ITEM_ID="$(date -u +%Y%m%d)-node-msg-from-${FROM}-$(printf '%s' "$SUBJECT" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g' | cut -c1-40)"
      mkdir -p "$AGENT_INBOX/$ITEM_ID"
      cp "$MSG_FILE" "$AGENT_INBOX/$ITEM_ID/command.md"
      echo "  → Routed to $DEFAULT_AGENT inbox: $ITEM_ID"
      ;;
    reply)
      # Replies go to ceo outbox for review — they are responses to something CEO sent
      DEST="$ROOT_DIR/sessions/${DEFAULT_AGENT}/outbox/${MSG_ID}.md"
      cp "$MSG_FILE" "$DEST"
      echo "  → Saved reply to ${DEFAULT_AGENT} outbox"
      ;;
    fyi)
      echo "  → FYI message logged (no dispatch)"
      ;;
    *)
      echo "  [WARN] Unknown type '${MSG_TYPE}' — routing as command"
      AGENT_INBOX="$ROOT_DIR/sessions/${DEFAULT_AGENT}/inbox"
      mkdir -p "$AGENT_INBOX"
      ITEM_ID="$(date -u +%Y%m%d)-node-msg-from-${FROM}-unknown-type"
      mkdir -p "$AGENT_INBOX/$ITEM_ID"
      cp "$MSG_FILE" "$AGENT_INBOX/$ITEM_ID/command.md"
      ;;
  esac

  # Mark as processed and archive
  sed -i 's/^status: pending/status: processed/' "$MSG_FILE"
  mv "$MSG_FILE" "$ARCHIVE_DIR/${MSG_BASENAME}"
  (( PROCESSED++ )) || true
done

echo "[node-recv] Done. Processed=${PROCESSED} Skipped=${SKIPPED}"

if [[ $PROCESSED -gt 0 && "$DRY_RUN" -eq 0 ]]; then
  git add "sessions/node-${THIS_NODE}/"
  git commit -m "node-recv: processed ${PROCESSED} inbox message(s) on ${THIS_NODE}

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>" 2>/dev/null || true
  git push origin HEAD:main 2>/dev/null || true
fi
