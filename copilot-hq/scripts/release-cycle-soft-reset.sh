#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

base_release_id="${1:-}"

if [ -z "$base_release_id" ]; then
  today="$(date +%Y%m%d)"
  base_release_id="${today}-coordinated-release"
fi

# Keep history; restart by issuing a fresh release id suffix.
slug="$(printf '%s' "$base_release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-70)"
release_id="$slug"

has_release_cycle_artifacts() {
  local rid="$1"
  local escaped
  escaped="$(printf '%s' "$rid" | sed 's/[.[\*^$()+?{}|\\]/\\&/g')"

  if find sessions -type f -path '*/artifacts/release-signoffs/*.md' -name "*${rid}*" -print -quit 2>/dev/null | grep -q .; then
    return 0
  fi

  if find sessions -type f \( -path '*/inbox/*/command.md' -o -path '*/outbox/*.md' \) -print 2>/dev/null \
      | xargs -r grep -E -l "release-preflight-test-suite-${escaped}|Release id:\s*${escaped}" 2>/dev/null \
      | head -n1 | grep -q .; then
    return 0
  fi

  return 1
}

if has_release_cycle_artifacts "$release_id"; then
  idx=2
  while :; do
    candidate="${slug}-r${idx}"
    if ! has_release_cycle_artifacts "$candidate"; then
      release_id="$candidate"
      break
    fi
    idx=$((idx+1))
  done
fi

echo "SOFT_RESET: preserving history and restarting cycle with release_id=${release_id}"
./scripts/coordinated-release-cycle-start.sh "$release_id"

echo "QUEUED: release-cycle preflight items for release_id=${release_id}"
echo "AUTOMATION: cron/watchdog/orchestrator loops will execute QA/preflight and publisher updates."
echo "DONE: soft reset complete (queue-only)"
