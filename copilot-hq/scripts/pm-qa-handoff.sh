#!/usr/bin/env bash
# pm-qa-handoff.sh — Formally hand an accepted feature from PM to QA for test generation
#
# Usage:
#   ./scripts/pm-qa-handoff.sh <site> <feature-id>
#
# Example:
#   ./scripts/pm-qa-handoff.sh forseti forseti-safety-chat-history
#
# What it does:
#   1. Reads features/<feature-id>/feature.md and 01-acceptance-criteria.md
#   2. Writes a QA inbox item with the feature brief + AC attached
#   3. QA's job: generate test cases → feature overlay manifest + 03-test-plan.md
#
# Prerequisites:
#   - features/<feature-id>/feature.md must exist
#   - features/<feature-id>/01-acceptance-criteria.md must exist (complete, not stub)
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SITE="${1:-}"
FEATURE_ID="${2:-}"

if [ -z "$SITE" ] || [ -z "$FEATURE_ID" ]; then
  echo "Usage: $0 <site> <feature-id>" >&2
  echo "  Example: $0 forseti forseti-safety-chat-history" >&2
  exit 1
fi

FEATURE_DIR="features/${FEATURE_ID}"
FEATURE_BRIEF="${FEATURE_DIR}/feature.md"
ACCEPTANCE_CRITERIA="${FEATURE_DIR}/01-acceptance-criteria.md"
QA_AGENT="qa-${SITE}"
QA_INBOX="sessions/${QA_AGENT}/inbox"
DATE_TAG="$(date +%Y%m%d-%H%M%S)"
ITEM_DIR="${QA_INBOX}/${DATE_TAG}-testgen-${FEATURE_ID}"

# Validate prerequisites
if [ ! -f "$FEATURE_BRIEF" ]; then
  echo "ERROR: Feature brief not found: $FEATURE_BRIEF" >&2
  echo "  Run: ./scripts/suggestion-triage.sh $SITE <nid> accept $FEATURE_ID" >&2
  exit 1
fi

if [ ! -f "$ACCEPTANCE_CRITERIA" ]; then
  echo "ERROR: Acceptance criteria not found: $ACCEPTANCE_CRITERIA" >&2
  echo "  Create it from: templates/01-acceptance-criteria.md" >&2
  echo "  PM must complete AC before handoff — it is the QA contract." >&2
  exit 1
fi

# Check AC is not a stub (must have more than the template header)
AC_LINES="$(wc -l < "$ACCEPTANCE_CRITERIA")"
if [ "$AC_LINES" -lt 10 ]; then
  echo "ERROR: $ACCEPTANCE_CRITERIA appears to be a stub (only $AC_LINES lines)." >&2
  echo "  Complete the Acceptance Criteria before handing off to QA." >&2
  exit 1
fi

echo "[pm-qa-handoff] Site: $SITE | Feature: $FEATURE_ID"
echo "[pm-qa-handoff] Feature brief: $FEATURE_BRIEF"
echo "[pm-qa-handoff] Acceptance criteria: $ACCEPTANCE_CRITERIA ($AC_LINES lines)"

mkdir -p "$ITEM_DIR"

# Write the command for QA
cat > "$ITEM_DIR/command.md" <<EOF
# Test Plan Design: ${FEATURE_ID}

**From:** pm-${SITE}  
**To:** qa-${SITE}  
**Date:** $(date -Iseconds)  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT edit the live product manifest \`qa-suites/products/${SITE}/suite.json\` yet.
Instead, create a **feature-scoped suite overlay** at:
\`qa-suites/products/${SITE}/features/${FEATURE_ID}.json\`

That overlay is the runnable SoT for this feature during grooming. The live release manifest is compiled from selected overlays at Stage 0.

### Required outputs

1. **Create** \`features/${FEATURE_ID}/03-test-plan.md\` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Create** \`qa-suites/products/${SITE}/features/${FEATURE_ID}.json\` from \`templates/qa-feature-suite.json\`:
   - Declare at least one runnable suite entry for this feature
   - Include \`owner_seat\`, \`source_path\`, \`env_requirements\`, and \`release_checkpoint\`
   - Point \`test_plan\` at \`features/${FEATURE_ID}/03-test-plan.md\`
   - Validate with:
     \`\`\`bash
     python3 scripts/qa-suite-validate.py --product ${SITE} --feature-id ${FEATURE_ID}
     \`\`\`
2. **Signal completion:**
    \`\`\`bash
    ./scripts/qa-pm-testgen-complete.sh ${SITE} ${FEATURE_ID} "<brief summary>"
   \`\`\`
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit \`qa-suites/products/${SITE}/suite.json\`
- Do NOT edit \`org-chart/sites/${SITE}.life/qa-permissions.json\`
Those release-scope changes happen at Stage 0 of the next release when this feature is selected into scope.
During grooming, keep all feature-specific runnable metadata in the overlay manifest.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan + overlay during grooming, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | \`role-url-audit\` suite entry — HTTP 200 for role X |
| Route blocked for role Y | \`role-url-audit\` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | \`qa-permissions.json\` rule + role audit entry |

See full process: \`runbooks/intake-to-qa-handoff.md\`

## Acceptance Criteria (attached below)

$(cat "$ACCEPTANCE_CRITERIA")
EOF

# Copy feature brief for reference
cp "$FEATURE_BRIEF" "$ITEM_DIR/feature.md"
cp "$ACCEPTANCE_CRITERIA" "$ITEM_DIR/01-acceptance-criteria.md"

# Write ROI (test generation is high value — unblocks Dev knowing what to build to)
echo "4" > "$ITEM_DIR/roi.txt"

# Mark feature brief as handed off (idempotent: already in_progress is a no-op)
if ! python3 - "$FEATURE_BRIEF" <<'PY'
import pathlib, sys, datetime, re, os

p = pathlib.Path(sys.argv[1])
text = p.read_text(encoding='utf-8')
today = datetime.date.today().isoformat()

# Detect current status
m = re.search(r'^- Status:\s*(.+)$', text, re.MULTILINE)
if not m:
    print(f"ERROR: no '- Status:' line found in {p}", file=sys.stderr)
    sys.exit(1)

current_status = m.group(1).strip()
if current_status == 'in_progress':
    print(f"OK (idempotent): {p} already has Status: in_progress — no change made")
    sys.exit(0)

# Replace any existing status with in_progress
text = re.sub(r'^(- Status:\s*).*$', r'\g<1>in_progress', text, flags=re.MULTILINE, count=1)

# Append handoff note to Latest updates section
if '## Latest updates' in text:
    text = text.replace(
        '## Latest updates',
        f'## Latest updates\n\n- {today}: Handed off to QA for test generation (pm-qa-handoff.sh)',
        1
    )

p.write_text(text, encoding='utf-8')
print(f"Updated {p}: Status: {current_status} → in_progress, handoff noted")
PY
then
  echo "ERROR: failed to update feature.md status to in_progress: $FEATURE_BRIEF" >&2
  exit 1
fi

# Verify the status was written correctly
if ! grep -q -- '- Status: in_progress' "$FEATURE_BRIEF"; then
  echo "ERROR: verification failed — 'Status: in_progress' not found in $FEATURE_BRIEF after update" >&2
  exit 1
fi

echo "[pm-qa-handoff] QA inbox item written: $ITEM_DIR"
echo "[pm-qa-handoff] Feature brief updated: status → in_progress"
echo ""
echo "Next steps:"
echo "  QA will generate test cases and write: features/${FEATURE_ID}/03-test-plan.md"
echo "  Dev can start implementation once QA reports back (or in parallel using the AC directly)."
