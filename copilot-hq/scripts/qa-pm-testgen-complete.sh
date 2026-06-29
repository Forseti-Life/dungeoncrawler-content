#!/usr/bin/env bash
# qa-pm-testgen-complete.sh — QA signals PM that test generation is done for a feature
#
# Usage:
#   ./scripts/qa-pm-testgen-complete.sh <site> <feature-id> [summary]
#
# Example:
#   ./scripts/qa-pm-testgen-complete.sh forseti forseti-safety-chat-history \
#     "Added 4 test cases to role-url-audit suite; suite validated"
#
# What it does:
#   1. Validates features/<id>/03-test-plan.md exists (QA must write it first)
#   2. Marks features/<id>/feature.md status: ready (groomed gate passed)
#   3. Writes a PM inbox item: feature is groomed and ready for next Stage 0
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SITE="${1:-}"
FEATURE_ID="${2:-}"
SUMMARY="${3:-Test cases generated and suite validated.}"

if [ -z "$SITE" ] || [ -z "$FEATURE_ID" ]; then
  echo "Usage: $0 <site> <feature-id> [summary]" >&2
  exit 1
fi

FEATURE_DIR="features/${FEATURE_ID}"
TEST_PLAN="${FEATURE_DIR}/03-test-plan.md"
FEATURE_BRIEF="${FEATURE_DIR}/feature.md"
ACCEPTANCE_CRITERIA="${FEATURE_DIR}/01-acceptance-criteria.md"
OVERLAY_MANIFEST="qa-suites/products/${SITE}/features/${FEATURE_ID}.json"
PM_AGENT="pm-${SITE}"
PM_INBOX="sessions/${PM_AGENT}/inbox"
DATE_TAG="$(date +%Y%m%d-%H%M%S)"
PM_ITEM="${PM_INBOX}/${DATE_TAG}-testgen-complete-${FEATURE_ID}"

# Validate QA output exists
if [ ! -f "$ACCEPTANCE_CRITERIA" ]; then
  echo "ERROR: Acceptance criteria not found: $ACCEPTANCE_CRITERIA" >&2
  echo "  PM handoff is incomplete; cannot mark feature ready." >&2
  exit 1
fi

if [ ! -f "$TEST_PLAN" ]; then
  echo "ERROR: Test plan not found: $TEST_PLAN" >&2
  echo "  Write the test plan before signaling completion." >&2
  exit 1
fi

TEST_PLAN_LINES="$(wc -l < "$TEST_PLAN")"
if [ "$TEST_PLAN_LINES" -lt 5 ]; then
  echo "ERROR: $TEST_PLAN appears to be a stub ($TEST_PLAN_LINES lines)." >&2
  echo "  Complete the test plan before signaling completion." >&2
  exit 1
fi

if [ ! -f "$OVERLAY_MANIFEST" ]; then
  echo "ERROR: Feature overlay manifest not found: $OVERLAY_MANIFEST" >&2
  echo "  Create the runnable suite overlay before signaling completion." >&2
  exit 1
fi

python3 scripts/qa-suite-validate.py --product "$SITE" --feature-id "$FEATURE_ID"

echo "[qa-pm-testgen-complete] Feature: $FEATURE_ID | Site: $SITE"
echo "[qa-pm-testgen-complete] Test plan: $TEST_PLAN ($TEST_PLAN_LINES lines) ✓"
echo "[qa-pm-testgen-complete] Feature overlay: $OVERLAY_MANIFEST ✓"

# Mark feature as groomed/ready in feature.md
if [ -f "$FEATURE_BRIEF" ]; then
  python3 - "$FEATURE_BRIEF" "$FEATURE_ID" <<'PY'
import pathlib, sys, datetime
p = pathlib.Path(sys.argv[1])
feature_id = sys.argv[2]
today = datetime.date.today().isoformat()
text = p.read_text(encoding='utf-8')
# Update status to ready
text = text.replace('- Status: in_progress', '- Status: ready')
text = text.replace('- Status: planned', '- Status: ready')
# Append to latest updates
if '## Latest updates' in text:
    lines = text.split('\n')
    for i, line in enumerate(lines):
        if line.strip() == '## Latest updates':
            lines.insert(i+1, f'\n- {today}: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.')
            break
    text = '\n'.join(lines)
p.write_text(text, encoding='utf-8')
print(f"Updated {p}: status → ready")
PY
fi

# Write PM notification inbox item
mkdir -p "$PM_ITEM"
echo "3" > "$PM_ITEM/roi.txt"

cat > "$PM_ITEM/command.md" <<EOF
# Grooming Complete: ${FEATURE_ID}

**From:** qa-${SITE}  
**To:** pm-${SITE}  
**Date:** $(date -Iseconds)  
**Feature:** ${FEATURE_ID}

## Status: GROOMED — Ready for next Stage 0

This feature has passed the grooming gate and is eligible for the next release scope selection.

**All four grooming artifacts exist:**
- \`${FEATURE_DIR}/feature.md\` ✓
- \`${FEATURE_DIR}/01-acceptance-criteria.md\` ✓
- \`${FEATURE_DIR}/03-test-plan.md\` ✓
- \`${OVERLAY_MANIFEST}\` ✓

## QA summary

${SUMMARY}

## Next action (PM)

This feature is now in the **ready pool** for next Stage 0 scope selection.
No action needed now — it will be available when the next release cycle starts.

If you want to review the test plan:
  cat ${TEST_PLAN}

If you want to review the runnable suite metadata:
  cat ${OVERLAY_MANIFEST}
EOF

echo "[qa-pm-testgen-complete] PM notified: $PM_ITEM"
echo "[qa-pm-testgen-complete] Feature $FEATURE_ID is now GROOMED and ready for next Stage 0."
