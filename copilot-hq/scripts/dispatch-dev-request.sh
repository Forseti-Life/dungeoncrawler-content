#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/command-routing.sh
source "$ROOT_DIR/scripts/lib/command-routing.sh"

AGENT_ID="${1:?target agent required}"
COMMAND_FILE="${2:?command file required}"

if [ ! -f "$COMMAND_FILE" ]; then
  echo "ERROR: command file not found: $COMMAND_FILE" >&2
  exit 1
fi

DATE_YYYYMMDD="$(date +%Y%m%d)"
DATE_ISO="$(date -Iseconds)"
TOPIC="$(command_field "$COMMAND_FILE" "topic")"
TOPIC="$(sanitize_topic_slug "${TOPIC:-$(basename "$COMMAND_FILE" .md)}")"
WORK_ITEM_ID="$(command_field "$COMMAND_FILE" "work_item")"
WEBSITE="$(command_field "$COMMAND_FILE" "website")"
MODULE="$(command_field "$COMMAND_FILE" "module")"
BRANCH="$(command_field "$COMMAND_FILE" "branch")"
ISSUE_URL="$(command_field "$COMMAND_FILE" "issue_url")"
ROI="$(command_field "$COMMAND_FILE" "roi")"

if ! [[ "$ROI" =~ ^[0-9]+$ ]]; then
  ROI="3"
fi

DIR="sessions/${AGENT_ID}/inbox/${DATE_YYYYMMDD}-${TOPIC}"
mkdir -p "$DIR"

printf '%s\n' "$ROI" > "$DIR/roi.txt"
cp "$COMMAND_FILE" "$DIR/command.md"

cat > "$DIR/README.md" <<EOF
# Dev Work Request — ${DATE_ISO}

- Agent: ${AGENT_ID}
- Work item: ${WORK_ITEM_ID}
- Topic: ${TOPIC}
- Website: ${WEBSITE}
- Module: ${MODULE}
- Branch: ${BRANCH}

## What to do
1. Read command.md first.
2. Make the smallest safe change set that satisfies the command.
3. Write an outbox update with verification evidence.
4. If blocked, be explicit about what production must decide next.

## Source
- Command file: ${COMMAND_FILE}
EOF

if [ -n "$ISSUE_URL" ]; then
  cat >> "$DIR/README.md" <<EOF
- Issue: ${ISSUE_URL}
EOF
fi

cat > "$DIR/00-problem-statement.md" <<EOF
# Problem Statement

- Work item: ${WORK_ITEM_ID}
- Topic: ${TOPIC}
- Production is assigning this task to the local worker seat ${AGENT_ID}.
- Primary target is ${MODULE} on ${WEBSITE}.
EOF

cat > "$DIR/01-acceptance-criteria.md" <<EOF
# Acceptance Criteria

- The command in command.md is implemented or advanced safely.
- Any code or documentation changes include a concise outbox summary.
- Verification steps are recorded in the outbox update.
- If implementation cannot complete, blocker details are explicit.
EOF

cat > "$DIR/06-risk-assessment.md" <<EOF
# Risk Assessment

- Production remains the priority authority.
- Prefer small diffs and explicit verification.
- Do not embed secrets in artifacts or logs.
- Escalate rather than guessing on destructive changes.
EOF

echo "Created dev inbox item: $DIR"
