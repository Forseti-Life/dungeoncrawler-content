#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

ARGS=()

# Default to suppressing "no updates" posts in scheduled automation.
SKIP_EMPTY="${MARKETING_FEATURE_UPDATE_SKIP_EMPTY:-1}"
if [[ "$SKIP_EMPTY" == "1" || "$SKIP_EMPTY" == "true" || "$SKIP_EMPTY" == "TRUE" ]]; then
  ARGS+=(--skip-empty)
fi

if [[ "${MARKETING_FEATURE_UPDATE_DRY_RUN:-0}" == "1" ]]; then
  ARGS+=(--dry-run)
fi

exec python3 "$ROOT_DIR/scripts/marketing/discord_feature_updates.py" "${ARGS[@]}" "$@"
