#!/usr/bin/env bash
# board-daily-reminder.sh
# Sends a daily digest email to the Board (Keith) if there are pending
# inbox/commands/ items awaiting human action.
# Designed to be run via cron once a day.
#
# Usage: bash scripts/board-daily-reminder.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Load board notification config
BOARD_CONF="${ROOT_DIR}/org-chart/board.conf"
if [ -f "$BOARD_CONF" ]; then
  # shellcheck source=../org-chart/board.conf
  source "$BOARD_CONF"
fi
BOARD_EMAIL="${BOARD_EMAIL:-keith.aumiller@stlouisintegration.com}"
HQ_FROM_EMAIL="${HQ_FROM_EMAIL:-hq-noreply@forseti.life}"
HQ_SITE_NAME="${HQ_SITE_NAME:-forseti.life HQ}"

COMMANDS_DIR="${ROOT_DIR}/inbox/commands"

# Collect pending .md files (skip hidden files and subdirs)
mapfile -t pending < <(find "$COMMANDS_DIR" -maxdepth 1 -name "*.md" | sort)

COUNT="${#pending[@]}"

if [ "$COUNT" -eq 0 ]; then
  echo "No pending board items — skipping reminder."
  exit 0
fi

echo "Found ${COUNT} pending board item(s) — sending reminder to ${BOARD_EMAIL}"

# Build item list for email body
item_list=""
for f in "${pending[@]}"; do
  fname="$(basename "$f")"
  topic="$(grep -m1 '^- topic:' "$f" 2>/dev/null | sed 's/^- topic: *//' || echo "$fname")"
  created="$(grep -m1 '^- created_at:' "$f" 2>/dev/null | sed 's/^- created_at: *//' || echo "unknown")"
  item_list="${item_list}  • [${created}] ${topic} (${fname})\n"
done

body="$(printf "You have %d pending board item(s) in the %s orchestrator awaiting your attention.\n\nPending items:\n%b\nTo act on these, write a response to the file in:\n  %s/inbox/commands/\n\nOr direct a CEO action by editing the command file and running:\n  bash scripts/dispatch-pm-request.sh <pm-agent> <work-item> <topic>\n\nThis reminder fires daily while items remain unprocessed.\n" \
  "$COUNT" "$HQ_SITE_NAME" "$item_list" "$ROOT_DIR")"

printf "Subject: [HQ Board] Reminder: %d pending item(s) awaiting your action\nTo: %s\nFrom: %s\nContent-Type: text/plain\n\n%s\n" \
  "$COUNT" "$BOARD_EMAIL" "$HQ_FROM_EMAIL" "$body" \
  | /usr/sbin/sendmail -t \
  && echo "Reminder sent to ${BOARD_EMAIL}" \
  || echo "WARN: reminder email failed (sendmail returned non-zero)"
