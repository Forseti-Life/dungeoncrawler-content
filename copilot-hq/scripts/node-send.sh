#!/usr/bin/env bash
# node-send.sh — Send a message from this node to another node's inbox.
# Usage: node-send.sh <target-node> <type> <subject> <body-text>
#   target-node: master | dev-laptop
#   type:        command | query | reply | escalation | fyi
#   subject:     short subject line (used as filename slug)
#   body-text:   message body (or path to a file prefixed with @)
#
# After writing the message, this script commits and pushes so the target
# node receives it on its next git pull.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

TARGET_NODE="${1:?target-node required (master|dev-laptop)}"
MSG_TYPE="${2:?type required (command|query|reply|escalation|fyi)}"
SUBJECT_RAW="${3:?subject required}"
BODY="${4:?body required (or @/path/to/file)}"

# Load local node identity
IDENTITY_FILE="$ROOT_DIR/node-identity.conf"
[[ -f "$IDENTITY_FILE" ]] && source "$IDENTITY_FILE"
FROM_NODE="${NODE_ID:-unknown}"

# Resolve body from file if prefixed with @
if [[ "$BODY" == @* ]]; then
  BODY_FILE="${BODY:1}"
  [[ -f "$BODY_FILE" ]] || { echo "ERROR: file not found: $BODY_FILE" >&2; exit 1; }
  BODY="$(cat "$BODY_FILE")"
fi

# Slug the subject for the filename
SLUG="$(printf '%s' "$SUBJECT_RAW" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+//; s/-+$//')"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
MSG_ID="${TIMESTAMP}-${FROM_NODE}-${SLUG}"
MSG_FILE="$ROOT_DIR/sessions/node-${TARGET_NODE}/inbox/${MSG_ID}.md"

cat > "$MSG_FILE" << EOF
---
id: ${MSG_ID}
from: ${FROM_NODE}
to: ${TARGET_NODE}
type: ${MSG_TYPE}
subject: ${SUBJECT_RAW}
sent_at: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
status: pending
---

${BODY}
EOF

echo "[node-send] Wrote: sessions/node-${TARGET_NODE}/inbox/${MSG_ID}.md"

# Commit and push
git add "sessions/node-${TARGET_NODE}/inbox/${MSG_ID}.md"
git commit -m "node-msg: ${FROM_NODE} → ${TARGET_NODE}: ${SUBJECT_RAW}

type: ${MSG_TYPE}
id: ${MSG_ID}

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>"
git push origin HEAD:main
echo "[node-send] Pushed to remote. ${TARGET_NODE} will receive on next pull."
