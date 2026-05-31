#!/usr/bin/env bash
# pm-scope-activate.sh — Activate a groomed feature's test plan into the live QA suite
#
# Run this at Stage 0 when selecting a feature into release scope.
# This is the step that moves test cases from the spec (03-test-plan.md) into
# suite.json and qa-permissions.json so they run in this release's Stage 4.
#
# Usage:
#   ./scripts/pm-scope-activate.sh <site> <feature-id>
#
# Prerequisites:
#   features/<id>/feature.md          (status: ready)
#   features/<id>/01-acceptance-criteria.md
#   features/<id>/03-test-plan.md     (written by QA during grooming)
#
# What it does:
#   1. Validates the feature is groomed (all 3 artifacts exist, status: ready)
#   2. Writes a Dev inbox item: "implement <feature-id> for this release"
#   3. Writes a QA inbox item: "activate test plan for <feature-id> into suite.json"
#   4. Updates feature.md status → in_progress (scoped for this release)
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SITE="${1:-}"
FEATURE_ID="${2:-}"

if [ -z "$SITE" ] || [ -z "$FEATURE_ID" ]; then
  echo "Usage: $0 <site> <feature-id>" >&2
  exit 1
fi

# Normalise SITE: strip .life domain suffix so both "forseti" and "forseti.life"
# resolve to the same registered agent ID (qa-forseti, not qa-forseti.life).
SITE="${SITE%.life}"

FEATURE_DIR="features/${FEATURE_ID}"
FEATURE_BRIEF="${FEATURE_DIR}/feature.md"
AC_FILE="${FEATURE_DIR}/01-acceptance-criteria.md"
TEST_PLAN="${FEATURE_DIR}/03-test-plan.md"
QA_AGENT="qa-${SITE}"
QA_INBOX="sessions/${QA_AGENT}/inbox"
DEV_AGENT="$(grep -im1 "^- Dev owner:" "$FEATURE_BRIEF" | sed 's/.*Dev owner:[[:space:]]*//' | tr -d '\r' || true)"
DEV_AGENT="${DEV_AGENT:-dev-${SITE}}"
DEV_INBOX="sessions/${DEV_AGENT}/inbox"
DATE_TAG="$(date +%Y%m%d-%H%M%S)"
ITEM_DIR="${QA_INBOX}/${DATE_TAG}-suite-activate-${FEATURE_ID}"
DEV_ITEM_DIR="${DEV_INBOX}/${DATE_TAG}-impl-${FEATURE_ID}"

# Validate groomed gate
missing=()
[ ! -f "$FEATURE_BRIEF" ]  && missing+=("$FEATURE_BRIEF")
[ ! -f "$AC_FILE" ]        && missing+=("$AC_FILE")
[ ! -f "$TEST_PLAN" ]      && missing+=("$TEST_PLAN")

if [ ${#missing[@]} -gt 0 ]; then
  echo "ERROR: Feature is not fully groomed. Missing artifacts:" >&2
  for f in "${missing[@]}"; do echo "  - $f" >&2; done
  echo "" >&2
  echo "A feature must be fully groomed before it can be scoped into a release." >&2
  echo "Complete grooming first: suggestion-triage.sh → AC → pm-qa-handoff.sh → qa-pm-testgen-complete.sh" >&2
  exit 1
fi

# Check feature status is 'ready'
STATUS="$(grep -m1 "^- Status:" "$FEATURE_BRIEF" | sed 's/.*Status: //' | tr -d '[:space:]')"
if [ "$STATUS" != "ready" ]; then
  echo "ERROR: Feature status is '$STATUS', expected 'ready'." >&2
  echo "  Only groomed (status: ready) features can be scoped into a release." >&2
  exit 1
fi

# Enforce 20-feature release scope cap (per site)
RELEASE_CAP=20
ACTIVE_RELEASE_ID=""
ACTIVE_DIR="tmp/release-cycle-active"
RELEASE_ID_FILE="${ACTIVE_DIR}/${SITE}.release_id"
if [ -f "$RELEASE_ID_FILE" ]; then
  ACTIVE_RELEASE_ID="$(tr -d '[:space:]' < "$RELEASE_ID_FILE")"
fi
if [ -n "$ACTIVE_RELEASE_ID" ]; then
  # GAP-B-02: scope cap count to the CURRENT release only (not all in_progress across releases).
  # Features from prior release cycles that remain in_progress must not block new activations.
  # Fallback: if ACTIVE_RELEASE_ID is empty (no active release), count globally (original behavior).
  SCOPED_COUNT="$(grep -rl "^- Status: in_progress" features/ 2>/dev/null \
    | xargs grep -l "^- Website:.*${SITE}" 2>/dev/null \
    | xargs grep -l "^- Release:.*${ACTIVE_RELEASE_ID}" 2>/dev/null \
    | wc -l | tr -d '[:space:]' || echo 0)"
  if [ "${SCOPED_COUNT:-0}" -ge "$RELEASE_CAP" ]; then
    echo "ERROR: Release scope cap reached for site '${SITE}' ($SCOPED_COUNT/$RELEASE_CAP features in_progress for release ${ACTIVE_RELEASE_ID})." >&2
    echo "  Defer this feature to the next release or remove another feature from scope first." >&2
    exit 1
  fi
else
  # No active release — fall back to global in_progress count for the site
  SCOPED_COUNT="$(grep -rl "^- Status: in_progress" features/ 2>/dev/null \
    | xargs grep -l "^- Website:.*${SITE}" 2>/dev/null \
    | wc -l | tr -d '[:space:]' || echo 0)"
  if [ "${SCOPED_COUNT:-0}" -ge "$RELEASE_CAP" ]; then
    echo "ERROR: Release scope cap reached for site '${SITE}' ($SCOPED_COUNT/$RELEASE_CAP features in_progress)." >&2
    echo "  Defer this feature to the next release or remove another feature from scope first." >&2
    exit 1
  fi
fi

BOARD_REQUIRED="$(grep -im1 "^- Board security review required:" "$FEATURE_BRIEF" | sed 's/.*required:[[:space:]]*//' | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]' || true)"
if [ "$BOARD_REQUIRED" = "yes" ] || [ "$BOARD_REQUIRED" = "true" ]; then
  BOARD_APPROVAL="sessions/pm-${SITE}/artifacts/board-security-approvals/${FEATURE_ID}.md"
  if [ ! -f "$BOARD_APPROVAL" ]; then
    echo "ERROR: Feature requires board security review approval before scope activation." >&2
    echo "Missing approval artifact: $BOARD_APPROVAL" >&2
    exit 1
  fi
fi

# Security acceptance criteria gate (GAP-CR-1 / 20260405)
# Features must have either:
#   a) A "## Security acceptance criteria" section (case-insensitive, non-empty), OR
#   b) A "- Security AC exemption: <reason>" field in feature.md (for no-security-surface features)
SEC_EXEMPTION="$(grep -im1 "^- Security AC exemption:" "$FEATURE_BRIEF" | sed 's/.*exemption:[[:space:]]*//' | tr -d '[:space:]' || true)"
if [ -z "$SEC_EXEMPTION" ]; then
  # No exemption — require the section to exist and be non-empty
  SEC_SECTION_LINE="$(grep -im1 "^## Security acceptance criteria" "$FEATURE_BRIEF" || true)"
  if [ -z "$SEC_SECTION_LINE" ]; then
    echo "ERROR: feature.md is missing a '## Security acceptance criteria' section." >&2
    echo "" >&2
    echo "  Every feature must document its security surface before scope activation." >&2
    echo "  Add the following to ${FEATURE_BRIEF}:" >&2
    echo "" >&2
    echo "  ## Security acceptance criteria" >&2
    echo "  - Authentication/permission surface: <who can access>" >&2
    echo "  - CSRF expectations: <which routes need CSRF token>" >&2
    echo "  - Input validation: <what is validated and where>" >&2
    echo "  - PII/logging constraints: <what must not be logged>" >&2
    echo "" >&2
    echo "  If this feature has NO security surface (e.g. pure content/display only), add:" >&2
    echo "  - Security AC exemption: <brief reason, e.g. 'static content, no routes, no user input'>" >&2
    exit 1
  fi
  # Section exists — verify it has at least one non-blank, non-header line after it
  SEC_CONTENT="$(awk '/^## Security acceptance criteria/{found=1;next} found && /^## /{exit} found{print}' "$FEATURE_BRIEF" | grep -v "^[[:space:]]*$" | head -1 || true)"
  if [ -z "$SEC_CONTENT" ]; then
    echo "ERROR: '## Security acceptance criteria' section in ${FEATURE_BRIEF} is empty." >&2
    echo "  Add at least one acceptance criterion (authentication surface, CSRF, input validation, PII/logging)." >&2
    exit 1
  fi
fi

echo "[pm-scope-activate] Activating: $FEATURE_ID for site: $SITE"
echo "[pm-scope-activate] All grooming artifacts present ✓"

# Write Dev implementation inbox item (durable owner handoff for the active release)
mkdir -p "$DEV_ITEM_DIR"
echo "200" > "$DEV_ITEM_DIR/roi.txt"

cat > "$DEV_ITEM_DIR/README.md" <<EOF
# Implementation required: ${FEATURE_ID}

- Agent: ${DEV_AGENT}
- Feature: ${FEATURE_ID}
- Release: ${ACTIVE_RELEASE_ID}
- Status: pending
- Created: $(date -Iseconds)
- Dispatched by: pm-scope-activate.sh (Stage 0 release activation)

## Context

This feature has been activated into the current release scope. Dev now owns the implementation handoff for this release.

## Action required
1. Review feature brief: \`features/${FEATURE_ID}/feature.md\`
2. Review acceptance criteria: \`features/${FEATURE_ID}/01-acceptance-criteria.md\`
3. Implement the feature for release \`${ACTIVE_RELEASE_ID}\`
4. Run existing tests to ensure no regressions
5. Write outbox with implementation notes and commit hash(es)
6. Coordinate with \`${QA_AGENT}\` for Gate 2 verification once implementation is ready

## Acceptance criteria
- Implementation committed with hash recorded in outbox
- No regression failures from existing test suites
EOF

cp "$FEATURE_BRIEF" "$DEV_ITEM_DIR/feature.md"
cp "$AC_FILE" "$DEV_ITEM_DIR/01-acceptance-criteria.md"
cp "$TEST_PLAN" "$DEV_ITEM_DIR/03-test-plan.md"

# Write QA activation inbox item
mkdir -p "$ITEM_DIR"
echo "7" > "$ITEM_DIR/roi.txt"

TEST_PLAN_CONTENT="$(cat "$TEST_PLAN")"

cat > "$ITEM_DIR/command.md" <<EOF
# Suite Activation: ${FEATURE_ID}

**From:** pm-${SITE}  
**To:** qa-${SITE}  
**Date:** $(date -Iseconds)  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to \`suite.json\` and \`qa-permissions.json\`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** \`qa-suites/products/${SITE}/suite.json\`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with \`"feature_id": "${FEATURE_ID}"\`**  
   This links the test to the living requirements doc at \`features/${FEATURE_ID}/\`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   \`\`\`json
   {
     "id": "${FEATURE_ID}-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "${FEATURE_ID}",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   \`\`\`

2. **Add permission rules to** \`org-chart/sites/${SITE}.life/qa-permissions.json\`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with \`"feature_id": "${FEATURE_ID}"\`**  
   Example:
   \`\`\`json
   {
     "id": "${FEATURE_ID}-<route-slug>",
     "feature_id": "${FEATURE_ID}",
     "path_regex": "/your-new-route",
     "notes": "Added for feature ${FEATURE_ID}",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   \`\`\`

3. **Validate the suite:**
   \`\`\`bash
   python3 scripts/qa-suite-validate.py
   \`\`\`

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

${TEST_PLAN_CONTENT}

### Acceptance criteria (reference)

$(cat "$AC_FILE")
EOF

cp "$FEATURE_BRIEF" "$ITEM_DIR/feature.md"
cp "$TEST_PLAN" "$ITEM_DIR/03-test-plan.md"

# Mark feature in_progress (scoped for this release)
python3 - "$FEATURE_BRIEF" "$ACTIVE_RELEASE_ID" <<'PY'
import pathlib, sys, datetime
p = pathlib.Path(sys.argv[1])
release_id = sys.argv[2] if len(sys.argv) > 2 else ""
today = datetime.date.today().isoformat()
text = p.read_text(encoding='utf-8')
text = text.replace('- Status: ready', '- Status: in_progress')
# GAP-RB-03: update or insert Release: field to current release ID
import re as _re
if release_id:
    if _re.search(r'^-\s+Release:\s*', text, flags=_re.MULTILINE | _re.IGNORECASE):
        # Update existing (possibly stale) Release: field
        text = _re.sub(
            r'^(-\s+Release:\s*).*$',
            r'\g<1>' + release_id,
            text, count=1, flags=_re.MULTILINE | _re.IGNORECASE
        )
    else:
        # Insert Release: after Status: in_progress line
        text = _re.sub(
            r'(^-\s+Status:\s*in_progress\s*$)',
            r'\1\n- Release: ' + release_id,
            text, count=1, flags=_re.MULTILINE | _re.IGNORECASE
        )
if '## Latest updates' in text:
    lines = text.split('\n')
    for i, line in enumerate(lines):
        if line.strip() == '## Latest updates':
            lines.insert(i+1, f'\n- {today}: Scoped into release — suite activation sent to QA.')
            break
    text = '\n'.join(lines)
p.write_text(text, encoding='utf-8')
print(f"Updated {p}: status → in_progress")
PY

echo "[pm-scope-activate] QA activation item queued: $ITEM_DIR"
echo "[pm-scope-activate] Dev implementation item queued: $DEV_ITEM_DIR"
echo "[pm-scope-activate] Feature $FEATURE_ID is now in_progress for this release."
echo ""
echo "Next: add $FEATURE_ID to your release 01-change-list.md"
