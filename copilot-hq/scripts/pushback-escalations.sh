#!/usr/bin/env bash
set -euo pipefail

# One-time sweep: for escalations in CEO inbox lacking decision/recommendation context,
# create a clarification inbox item back to the originator.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

active_ceo_agent() {
  local preferred="${ORCHESTRATOR_CEO_AGENT:-}"
  if [ -n "$preferred" ] && [[ "$preferred" == ceo-copilot* ]]; then
    if [ "$(./scripts/is-agent-paused.sh "$preferred" 2>/dev/null || echo false)" != "true" ]; then
      echo "$preferred"
      return
    fi
  fi
  while IFS= read -r id; do
    [ -n "$id" ] || continue
    [[ "$id" == ceo-copilot* ]] || continue
    if [ "$(./scripts/is-agent-paused.sh "$id" 2>/dev/null || echo false)" != "true" ]; then
      echo "$id"
      return
    fi
  done < <(sed -n 's/^[[:space:]]*-[[:space:]]id:[[:space:]]*\([^[:space:]]\+\)[[:space:]]*$/\1/p' org-chart/agents/agents.yaml 2>/dev/null)
  echo "ceo-copilot"
}

has_heading() {
  local heading="$1" file="$2"
  grep -qE "^## ${heading}$" "$file" 2>/dev/null
}

ceo_agent="$(active_ceo_agent)"
ceo_inbox="sessions/${ceo_agent}/inbox"
[ -d "$ceo_inbox" ] || exit 0

count=0
for d in "$ceo_inbox"/*; do
  [ -d "$d" ] || continue
  readme="$d/README.md"
  [ -f "$readme" ] || continue

  origin="$(grep -E '^- Agent:' "$readme" | head -n1 | sed 's/^- Agent: *//')"
  item="$(grep -E '^- Item:' "$readme" | head -n1 | sed 's/^- Item: *//')"
  [ -n "$origin" ] || continue
  [ -n "$item" ] || item="$(basename "$d")"

  # If the escalation README doesn't have Decision/Recommendation sections, push back.
  if ! has_heading "Decision needed" "$readme" || ! has_heading "Recommendation" "$readme"; then
    slug="$(echo "${item}" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-60)"
    dest="sessions/${origin}/inbox/$(date +%Y%m%d)-clarify-escalation-${slug}"
    if [ ! -d "$dest" ]; then
      mkdir -p "$dest"
      cat > "$dest/command.md" <<MD
- command: |
    Clarify escalation quality (required):

    This escalation is missing required context for CEO review.

    Provide a new outbox update for item:
    - ${item}

    Required fields:
    - Product context: website/module/role/feature/work item
    - ## Decision needed (explicit CEO decision)
    - ## Recommendation (what to do and why, with tradeoffs)

    Escalation source (for reference):
    - ${readme}
MD
      echo "2" > "$dest/roi.txt"
      count=$((count+1))
    fi
  fi
done

echo "Pushback created: ${count}"
