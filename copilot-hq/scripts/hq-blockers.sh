#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/agents.sh
source "./scripts/lib/agents.sh"

mode="${1:-list}"

# Outboxes older than this many days with no active inbox items are stale — flagged
# separately from active blockers so the CEO queue stays clean.
STALE_DAYS=14

count=0
stale_count=0

while IFS= read -r agent; do
  if is_paused "$agent"; then
    continue
  fi
  shopt -s nullglob
  out_files=("sessions/${agent}/outbox"/*.md)
  shopt -u nullglob
  if [ "${#out_files[@]}" -eq 0 ]; then
    continue
  fi

  # Only consider the latest outbox file for "currently blocked".
  latest="$(ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null | head -n 1 || true)"
  [ -n "$latest" ] || continue

  status_line="$(grep -im1 '^\- Status:' "$latest" 2>/dev/null || true)"
  status="$(echo "$status_line" | sed 's/^- Status: *//I' | tr '[:upper:]' '[:lower:]' | tr -d '\r')"

  if [ "$status" != "blocked" ] && [ "$status" != "needs-info" ]; then
    continue
  fi

  # Stale check: if the outbox file is older than STALE_DAYS and the agent has no
  # active inbox items, this is a phantom blocker — count and flag separately so
  # it does not pollute the active CEO queue or trigger stagnation alerts.
  outbox_age_days=0
  if command -v find >/dev/null 2>&1; then
    if find "$latest" -mtime "+${STALE_DAYS}" -maxdepth 0 -print 2>/dev/null | grep -q .; then
      outbox_age_days=$((STALE_DAYS + 1))
    fi
  fi

  has_inbox=0
  shopt -s nullglob
  inbox_items=("sessions/${agent}/inbox"/*)
  shopt -u nullglob
  if [ "${#inbox_items[@]}" -gt 0 ]; then
    has_inbox=1
  fi

  # A blocker is stale if: outbox is old AND agent has no active inbox items.
  # Stale needs-info with no corresponding inbox is a phantom — don't count as active.
  if [ "$outbox_age_days" -gt 0 ] && [ "$has_inbox" -eq 0 ]; then
    stale_count=$((stale_count+1))
    if [ "$mode" = "list" ]; then
      echo "- ${agent}: $(basename "$latest") [status=${status}] [STALE: >=${STALE_DAYS}d, no inbox — CEO cleanup needed]"
    fi
    continue
  fi

  # Validate that needs-info outboxes have a non-empty Needs section (malformed check).
  if [ "$status" = "needs-info" ]; then
    needs_content="$(awk 'BEGIN{p=0}
      /^## Needs from CEO/{p=1;next}
      /^## /{p=0}
      {if(p && NF>0) print}
    ' "$latest" | grep -v '^\s*-\s*N/A\s*$' | grep -v '^\s*$' | head -1 || true)"
    if [ -z "$needs_content" ]; then
      stale_count=$((stale_count+1))
      if [ "$mode" = "list" ]; then
        echo "- ${agent}: $(basename "$latest") [status=${status}] [MALFORMED: needs-info with empty/N/A Needs section — CEO cleanup needed]"
      fi
      continue
    fi
  fi

  count=$((count+1))

  if [ "$mode" = "count" ]; then
    continue
  fi

  echo "- ${agent}: $(basename "$latest") [status=${status}]"
  blockers="$(awk 'BEGIN{p=0}
    /^## Blockers/{p=1;next}
    /^## /{p=0}
    {if(p) print}
  ' "$latest" | sed -n '1,20p' | sed 's/^/    /')"
  needs="$(awk 'BEGIN{p=0}
    /^## Needs from CEO/{p=1;next}
    /^## /{p=0}
    {if(p) print}
  ' "$latest" | sed -n '1,20p' | sed 's/^/    /')"

  if [ -n "$blockers" ]; then
    echo "  Blockers:"
    echo "$blockers"
  fi
  if [ -n "$needs" ]; then
    echo "  Needs from CEO:"
    echo "$needs"
  fi

done < <(configured_agent_ids)

if [ "$mode" = "count" ]; then
  echo "$count"
fi

if [ "$mode" = "list" ] && [ "$stale_count" -gt 0 ]; then
  echo "(${stale_count} stale/malformed blocker(s) listed above — do not trigger stagnation alert)"
fi
