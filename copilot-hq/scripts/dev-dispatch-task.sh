#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/command-routing.sh
source "$ROOT_DIR/scripts/lib/command-routing.sh"

TARGET_AGENT="${1:?target agent required}"
WORK_ITEM_ID="${2:?work item id required}"
TOPIC_RAW="${3:?topic required}"
TEXT="${4:?command text required}"

TOPIC="$(sanitize_topic_slug "$TOPIC_RAW")"
TARGET="${DEV_DISPATCH_TARGET:-dev-laptop}"
WEBSITE="${DEV_DISPATCH_WEBSITE:-forseti.life}"
MODULE="${DEV_DISPATCH_MODULE:-job_hunter}"
EXECUTE_MODE="${DEV_DISPATCH_EXECUTE:-now}"
ROI="${DEV_DISPATCH_ROI:-35}"
ISSUE_URL="${DEV_DISPATCH_ISSUE_URL:-}"
DATE_YYYYMMDD="$(date +%Y%m%d)"
BRANCH_DEFAULT_MODULE="$(sanitize_topic_slug "$MODULE")"
BRANCH="${DEV_DISPATCH_BRANCH:-local/${BRANCH_DEFAULT_MODULE}-${DATE_YYYYMMDD}-${TOPIC}}"
TS="$(date +%Y%m%d-%H%M%S)"
FILE="inbox/commands/${TS}-${TOPIC}.md"

mkdir -p inbox/commands

{
  echo "# Command"
  echo ""
  echo "- created_at: $(date -Iseconds)"
  echo "- source_env: production-master"
  echo "- target: ${TARGET}"
  echo "- target_agent: ${TARGET_AGENT}"
  echo "- website: ${WEBSITE}"
  echo "- module: ${MODULE}"
  echo "- work_item: ${WORK_ITEM_ID}"
  echo "- topic: ${TOPIC}"
  echo "- branch: ${BRANCH}"
  echo "- execute: ${EXECUTE_MODE}"
  echo "- roi: ${ROI}"
  if [ -n "$ISSUE_URL" ]; then
    echo "- issue_url: ${ISSUE_URL}"
  fi
  echo ""
  echo "## Command text"
  echo "$TEXT"
} > "$FILE"

echo "Queued dev task: $FILE"
