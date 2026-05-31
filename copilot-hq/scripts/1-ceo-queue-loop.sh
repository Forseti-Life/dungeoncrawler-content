#!/usr/bin/env bash
set -euo pipefail

# Interactive-ish loop wrapper around:
#   ./scripts/ceo-queue.sh <work-item-id> <topic> "<command text>"
# So you can just type commands repeatedly without retyping the full CLI.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/ceo-queue-loop.sh [<work-item-id> <topic>]

Then type one command per line to enqueue.

Commands:
  :exit | :quit              Exit
  :topic <topic>             Change topic
  :wi <work-item-id>         Change work item
  :show                      Show current work item/topic
  :help                      Show this help

Notes:
- Empty lines are ignored.
- Lines starting with # are ignored.
USAGE
}

WORK_ITEM_ID="${1:-}"
TOPIC="${2:-}"

if [ -z "$WORK_ITEM_ID" ] || [ -z "$TOPIC" ]; then
  if [ -t 0 ]; then
    echo "Enter defaults for this queue session (used for each line you type)."
    read -r -p "Work item id: " WORK_ITEM_ID
    read -r -p "Topic: " TOPIC
  fi

  if [ -z "$WORK_ITEM_ID" ] || [ -z "$TOPIC" ]; then
    usage >&2
    exit 1
  fi
fi

echo "=== CEO Queue Loop ==="
echo "Work item: $WORK_ITEM_ID"
echo "Topic:     $TOPIC"
echo "Type :help for commands."

prompt() {
  if [ -t 1 ]; then
    printf 'ceo> '
  fi
}

while true; do
  prompt
  if ! IFS= read -r line; then
    echo
    exit 0
  fi

  line="${line:-}"
  if [ -z "$line" ]; then
    continue
  fi
  if [[ "$line" =~ ^# ]]; then
    continue
  fi

  case "$line" in
    :exit|:quit)
      exit 0
      ;;
    :help)
      usage
      continue
      ;;
    :show)
      echo "Work item: $WORK_ITEM_ID"
      echo "Topic:     $TOPIC"
      continue
      ;;
    :topic\ *)
      TOPIC="${line#:topic }"
      echo "Topic set: $TOPIC"
      continue
      ;;
    :wi\ *)
      WORK_ITEM_ID="${line#:wi }"
      echo "Work item set: $WORK_ITEM_ID"
      continue
      ;;
  esac

  ./scripts/ceo-queue.sh "$WORK_ITEM_ID" "$TOPIC" "$line" >/dev/null
  echo "Queued."
done
