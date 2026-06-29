#!/usr/bin/env bash
# Post-execution gate-transition router.
#
# Called by agent-exec-loop.sh after each agent execution to detect gate
# completion signals in the outbox and create follow-on inbox items.
#
# Usage: route-gate-transitions.sh <agent-id> [inbox-item-name]
#
# Non-blocking: always exits 0 so routing failures never abort the exec loop.
# Idempotent: skips creation if the target inbox item or its outbox already exists.

set -uo pipefail

AGENT="${1:-}"
ITEM_NAME="${2:-}"  # optional: name of the inbox item that was just processed

[ -n "$AGENT" ] || exit 0

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"
TIMESTAMP="$(date +%Y%m%d)"

log() { echo "[route-gate] $*" >&2; }

# Resolve the outbox file from the processed item name, or fall back to newest.
find_outbox_file() {
  local agent="$1" item="$2"
  local outbox_dir="sessions/${agent}/outbox"
  [ -d "$outbox_dir" ] || return 0

  if [ -n "$item" ] && [ -f "${outbox_dir}/${item}.md" ]; then
    echo "${outbox_dir}/${item}.md"
    return 0
  fi
  # Fall back to most recently modified .md file.
  ls -t "${outbox_dir}"/*.md 2>/dev/null | head -1
}

# Look up team record from product-teams.json by field name + value.
lookup_team_by_field() {
  local field="$1" value="$2"
  python3 - "$PRODUCT_TEAMS_JSON" "$field" "$value" 2>/dev/null <<'PY'
import json, sys
cfg, field, value = sys.argv[1], sys.argv[2], sys.argv[3]
with open(cfg, encoding='utf-8') as f:
    data = json.load(f)
for team in data.get('teams', []):
    if not team.get('active', False):
        continue
    if str(team.get(field, '')).strip() == value.strip():
        print(json.dumps(team))
        sys.exit(0)
sys.exit(1)
PY
}

# Extract a field from a team JSON blob.
team_field() {
  local team_json="$1" field="$2"
  echo "$team_json" | python3 -c "import json,sys; t=json.load(sys.stdin); print(t.get('$field',''))" 2>/dev/null || true
}

# Extract release-id from outbox content: looks for explicit "Release id:" or "release_id" markers.
extract_release_id() {
  local content="$1" filename_fallback="$2"
  local rid
  # Try explicit markers first.
  rid="$(printf '%s' "$content" | grep -iE '^\s*-\s*Release[ _]id:' | head -1 | sed 's/.*:\s*//' | tr -d '\r' | xargs 2>/dev/null || true)"
  if [ -z "$rid" ]; then
    # Try inline: "release 20260328-dungeoncrawler-release-b"
    rid="$(printf '%s' "$content" | grep -oE '[0-9]{8}-[A-Za-z0-9._-]+' | head -1 || true)"
  fi
  if [ -z "$rid" ]; then
    rid="$filename_fallback"
  fi
  printf '%s' "$rid"
}

# Extract ROI integer from outbox content (looks for "ROI: <n>" or "- ROI: <n>").
extract_roi() {
  local content="$1" default_roi="${2:-10}"
  local roi
  roi="$(printf '%s' "$content" | grep -oE 'ROI:\s*[0-9]+' | head -1 | grep -oE '[0-9]+' || true)"
  if [[ "$roi" =~ ^[0-9]+$ ]] && [ "$roi" -ge 1 ] 2>/dev/null; then
    echo "$roi"
  else
    echo "$default_roi"
  fi
}

# Create an inbox item idempotently (skip if already exists).
create_inbox_item() {
  local target_agent="$1" item_name="$2" roi="$3" command_content="$4"
  local inbox_dir="sessions/${target_agent}/inbox/${item_name}"
  local outbox_check="sessions/${target_agent}/outbox/${item_name}.md"

  if [ -d "$inbox_dir" ] || [ -f "$outbox_check" ]; then
    log "skip (exists): ${target_agent}/${item_name}"
    return 0
  fi

  mkdir -p "$inbox_dir" || { log "ERROR: mkdir failed for $inbox_dir"; return 0; }
  printf '%s\n' "$roi" > "$inbox_dir/roi.txt"
  printf '%s\n' "$command_content" > "$inbox_dir/command.md"
  log "created: sessions/${target_agent}/inbox/${item_name}"
}

# ─── Main routing logic ────────────────────────────────────────────────────────

OUTBOX_FILE="$(find_outbox_file "$AGENT" "$ITEM_NAME")" || true
[ -n "$OUTBOX_FILE" ] && [ -f "$OUTBOX_FILE" ] || exit 0

OUTBOX_CONTENT="$(cat "$OUTBOX_FILE" 2>/dev/null || true)"
[ -n "$OUTBOX_CONTENT" ] || exit 0

OUTBOX_BASE="$(basename "$OUTBOX_FILE" .md)"
ROUTE_DATE="$(printf '%s' "$OUTBOX_BASE" | sed -n 's/^\([0-9]\{8\}\).*/\1/p')"
if ! [[ "$ROUTE_DATE" =~ ^[0-9]{8}$ ]]; then
  ROUTE_DATE="$TIMESTAMP"
fi

# ─── Pattern 1 & 2: QA seat gate signals ──────────────────────────────────────
if [[ "$AGENT" == qa-* ]]; then
  TEAM_JSON="$(lookup_team_by_field "qa_agent" "$AGENT" || true)"
  [ -n "$TEAM_JSON" ] || { log "warn: no team found for qa_agent=${AGENT}"; }

  if [ -n "$TEAM_JSON" ]; then
    DEV_AGENT="$(team_field "$TEAM_JSON" "dev_agent")"
    PM_AGENT="$(team_field "$TEAM_JSON" "pm_agent")"
    TEAM_ID="$(team_field "$TEAM_JSON" "id")"

    STATUS_LINE="$(printf '%s' "$OUTBOX_CONTENT" | grep -m1 '^- Status:' || true)"

    # Pattern 1: QA BLOCK → Dev fix routing
    if echo "$STATUS_LINE" | grep -qi "done" && printf '%s' "$OUTBOX_CONTENT" | grep -q "BLOCK"; then
      SKIP_DEV_ROUTING=false
      if printf '%s' "$OUTBOX_CONTENT" | grep -qiE 'No new Dev inbox items created|consumes the audit artifact directly'; then
        SKIP_DEV_ROUTING=true
        log "skip dev routing for ${OUTBOX_BASE}: explicit delegation in QA outbox"
      fi

      if [ -n "$DEV_AGENT" ] && [ "$SKIP_DEV_ROUTING" != "true" ]; then
        RELEASE_ID="$(extract_release_id "$OUTBOX_CONTENT" "$OUTBOX_BASE")"
        ROI="$(extract_roi "$OUTBOX_CONTENT" 10)"
        NEXT_ACTIONS="$(printf '%s' "$OUTBOX_CONTENT" | awk '/^## Next actions/{found=1; next} found && /^## /{exit} found{print}' | head -20 | sed '/^[[:space:]]*$/d')"

        ITEM_NAME_OUT="${ROUTE_DATE}-fix-from-qa-block-${TEAM_ID}"
        CMD="# Dev fix: QA BLOCK from ${AGENT}

QA issued a BLOCK. Address all failing tests and re-submit for verification.

## Source
- QA outbox: ${OUTBOX_FILE}
- Release scope: ${RELEASE_ID}

## QA recommended fixes
${NEXT_ACTIONS}

## Required action
1. Address all failing tests listed in the QA outbox above.
2. Commit a fix and write an outbox update with commit hash.
3. QA will re-verify on the next cycle.
"
        create_inbox_item "$DEV_AGENT" "$ITEM_NAME_OUT" "$ROI" "$CMD"
      fi
    fi

    # Pattern 2: Gate 2 APPROVE → PM signoff routing
    # Guard: only fire on genuine Gate 2 aggregate APPROVE files.
    # Unit-test and suite-activate outboxes also contain "APPROVE" but must NOT trigger PM signoff.
    # A genuine Gate 2 outbox must have "gate2-approve" in its filename OR contain the header
    # "Gate 2 — QA Verification Report".  (Fix 2026-04-08: phantom signoff items from unit-test outboxes)
    IS_GATE2_APPROVE=false
    if echo "$OUTBOX_BASE" | grep -qi "gate2.approve\|gate2-approve"; then
      IS_GATE2_APPROVE=true
    elif printf '%s' "$OUTBOX_CONTENT" | grep -qi "Gate 2.*QA Verification Report"; then
      IS_GATE2_APPROVE=true
    fi
    if echo "$STATUS_LINE" | grep -qi "done" && printf '%s' "$OUTBOX_CONTENT" | grep -q "APPROVE" && [ "$IS_GATE2_APPROVE" = "true" ]; then
      if [ -n "$PM_AGENT" ]; then
        RELEASE_ID="$(extract_release_id "$OUTBOX_CONTENT" "$OUTBOX_BASE")"
        ITEM_NAME_OUT="${ROUTE_DATE}-release-signoff-${RELEASE_ID}"
        CMD="# Release signoff: ${RELEASE_ID}

Gate 2 QA APPROVE received from ${AGENT}. PM signoff required.

## Source
- QA outbox: ${OUTBOX_FILE}
- Release id: ${RELEASE_ID}

## Required action
Run: bash scripts/release-signoff.sh ${TEAM_ID} ${RELEASE_ID}
"
        create_inbox_item "$PM_AGENT" "$ITEM_NAME_OUT" "50" "$CMD"
      fi
    fi
  fi
fi

# ─── Pattern 3: PM non-forseti signoff → pm-forseti coordinated signoff ───────
if [[ "$AGENT" == pm-* ]] && [[ "$AGENT" != "pm-forseti" ]]; then
  # Detect signoff: outbox mentions "SIGNED_OFF" or references release-signoffs artifact
  if printf '%s' "$OUTBOX_CONTENT" | grep -qiE "SIGNED_OFF|release-signoff|signoff"; then
    # Find the most recently written signoff artifact for this PM.
    SIGNOFF_DIR="sessions/${AGENT}/artifacts/release-signoffs"
    if [ -d "$SIGNOFF_DIR" ]; then
      LATEST_SIGNOFF="$(ls -t "${SIGNOFF_DIR}"/*.md 2>/dev/null | head -1 || true)"
      if [ -n "$LATEST_SIGNOFF" ] && [ -f "$LATEST_SIGNOFF" ]; then
        RELEASE_ID="$(grep -m1 'Release id:' "$LATEST_SIGNOFF" 2>/dev/null | sed 's/.*:\s*//' | tr -d '\r' | xargs 2>/dev/null || true)"
        if [ -n "$RELEASE_ID" ]; then
          SLUG="$(printf '%s' "$RELEASE_ID" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-80)"
          ITEM_NAME_OUT="${ROUTE_DATE}-coordinated-signoff-${SLUG}"
          CMD="# Coordinated signoff: ${RELEASE_ID}

${AGENT} has signed off on release ${RELEASE_ID}. Coordinated signoff required from pm-forseti.

## Source
- PM signoff artifact: ${LATEST_SIGNOFF}
- Release id: ${RELEASE_ID}

## Required action
1. Review all PM signoffs: bash scripts/release-signoff-status.sh ${RELEASE_ID}
2. If all required PMs have signed: run bash scripts/release-signoff.sh forseti ${RELEASE_ID}
3. Proceed with coordinated push per runbooks/shipping-gates.md Gate 4.
"
          create_inbox_item "pm-forseti" "$ITEM_NAME_OUT" "200" "$CMD"
        fi
      fi
    fi
  fi
fi

exit 0
