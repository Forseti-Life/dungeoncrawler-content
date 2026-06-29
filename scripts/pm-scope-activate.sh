#!/usr/bin/env bash
# pm-scope-activate.sh — Activate or re-activate a feature's test plan into the live QA suite
#
# Run this at Stage 0 when selecting a feature into release scope, or when
# re-queueing an already-scoped legacy item back into the flow-managed SDLC lane.
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
#   1. Validates the feature is groomed (all 3 artifacts exist, status: ready/done,
#      or already-scoped in_progress for the active release)
#   2. Writes a Dev inbox item: "implement <feature-id> for this release"
#   3. Writes a QA inbox item: "activate test plan for <feature-id> into suite.json"
#   4. Updates feature.md status → in_progress (scoped for this release) or preserves
#      the existing scoped status when re-queueing a legacy item
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
PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"
QA_AGENT="qa-${SITE}"
QA_INBOX="sessions/${QA_AGENT}/inbox"
FEATURE_DEV_OWNER="$(grep -im1 "^- Dev owner:" "$FEATURE_BRIEF" | sed 's/.*Dev owner:[[:space:]]*//' | tr -d '\r' || true)"
DEV_AGENT="${FEATURE_DEV_OWNER:-dev-${SITE}}"
DEV_INBOX="sessions/${DEV_AGENT}/inbox"
PM_AGENT="pm-${SITE}"
TEAM_ID="${SITE}"
TEAM_LABEL="${SITE}"
DATE_TAG="$(date +%Y%m%d-%H%M%S)"
ITEM_DIR=""

if [ -f "$PRODUCT_TEAMS_JSON" ]; then
  TEAM_LOOKUP="$(python3 - "$PRODUCT_TEAMS_JSON" "$SITE" <<'PY'
import json
import sys

path, site = sys.argv[1], sys.argv[2].strip().lower()
with open(path, 'r', encoding='utf-8') as fh:
    data = json.load(fh)

for team in data.get("teams", []):
    team_id = str(team.get("id") or "").strip()
    team_site = str(team.get("site") or "").strip()
    aliases = [str(alias).strip().lower() for alias in team.get("aliases", []) if str(alias).strip()]
    candidates = {team_id.lower(), team_site.lower(), *aliases}
    if site not in candidates:
        continue
    print("\t".join([
        team_id or site,
        str(team.get("label") or team_id or site).strip(),
        str(team.get("pm_agent") or f"pm-{site}").strip(),
        str(team.get("qa_agent") or f"qa-{site}").strip(),
        str(team.get("dev_agent") or f"dev-{site}").strip(),
    ]))
    break
PY
  )"
  if [ -n "${TEAM_LOOKUP:-}" ]; then
    IFS=$'\t' read -r TEAM_ID TEAM_LABEL PM_AGENT QA_AGENT REGISTRY_DEV_AGENT <<<"$TEAM_LOOKUP"
    QA_INBOX="sessions/${QA_AGENT}/inbox"
    if [ -z "${FEATURE_DEV_OWNER:-}" ] && [ -n "${REGISTRY_DEV_AGENT:-}" ]; then
      DEV_AGENT="${REGISTRY_DEV_AGENT}"
      DEV_INBOX="sessions/${DEV_AGENT}/inbox"
    fi
  fi
fi

ITEM_DIR="${QA_INBOX}/${DATE_TAG}-suite-activate-${FEATURE_ID}"

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

# Check feature status and current release context.
STATUS="$(grep -m1 "^- Status:" "$FEATURE_BRIEF" | sed 's/.*Status: //' | tr -d '[:space:]')"
CURRENT_RELEASE="$(grep -im1 "^- Release:" "$FEATURE_BRIEF" | sed 's/.*Release:[[:space:]]*//' | tr -d '\r' | xargs || true)"

# Enforce 20-feature release scope cap (per site) unless explicitly overridden.
# Board/CEO can temporarily widen the cap for an exceptional release batch by
# exporting PM_SCOPE_ACTIVATE_RELEASE_CAP before invoking this script.
RELEASE_CAP="${PM_SCOPE_ACTIVATE_RELEASE_CAP:-20}"
ACTIVE_RELEASE_ID=""
IS_ALREADY_SCOPED_TO_ACTIVE_RELEASE=0
ACTIVE_DIR="tmp/release-cycle-active"
RELEASE_ID_FILE="${ACTIVE_DIR}/${SITE}.release_id"
if [ -f "$RELEASE_ID_FILE" ]; then
  ACTIVE_RELEASE_ID="$(tr -d '[:space:]' < "$RELEASE_ID_FILE")"
fi
if [ -n "$ACTIVE_RELEASE_ID" ] && [ -n "$CURRENT_RELEASE" ] && [ "$CURRENT_RELEASE" = "$ACTIVE_RELEASE_ID" ]; then
  IS_ALREADY_SCOPED_TO_ACTIVE_RELEASE=1
fi

ACTIVATION_MODE="implementation"
if [ "$STATUS" = "ready" ]; then
  ACTIVATION_MODE="implementation"
elif [ "$STATUS" = "done" ]; then
  ACTIVATION_MODE="verification"
elif [ "$STATUS" = "in_progress" ] && [ "$IS_ALREADY_SCOPED_TO_ACTIVE_RELEASE" = "1" ]; then
  ACTIVATION_MODE="implementation"
else
  echo "ERROR: Feature status is '$STATUS', expected 'ready', 'done', or an already-scoped 'in_progress' item for the active release." >&2
  echo "  pm-scope-activate supports groomed backlog (ready), dev-complete backlog (done), and legacy requeue for in_progress features already scoped to the active release." >&2
  exit 1
fi

if [ -n "$ACTIVE_RELEASE_ID" ]; then
  if [ "$IS_ALREADY_SCOPED_TO_ACTIVE_RELEASE" = "1" ]; then
    SCOPED_COUNT="0"
  else
  # Scope cap applies to the CURRENT release only.
  # Status: done is also counted when it is explicitly tagged to the active release,
  # which preserves the roadmap's "done in code, not yet shipped" semantics while
  # still treating the feature as active release scope.
  SCOPED_COUNT="$(python3 - "$SITE" "$ACTIVE_RELEASE_ID" <<'PY'
import pathlib
import re
import sys

site = sys.argv[1]
release_id = sys.argv[2]
count = 0
for fm in pathlib.Path("features").glob("*/feature.md"):
    text = fm.read_text(encoding="utf-8", errors="ignore")
    if not re.search(rf"^-\s+Website:.*{re.escape(site)}", text, re.MULTILINE | re.IGNORECASE):
        continue
    if not re.search(r"^-\s+Status:\s*(in_progress|done)\s*$", text, re.MULTILINE | re.IGNORECASE):
        continue
    if not re.search(rf"^-\s+Release:\s*(?:\n\s*)*{re.escape(release_id)}\s*$", text, re.MULTILINE | re.IGNORECASE):
        continue
    count += 1
print(count)
PY
)"
    if [ "${SCOPED_COUNT:-0}" -ge "$RELEASE_CAP" ]; then
      echo "ERROR: Release scope cap reached for site '${SITE}' ($SCOPED_COUNT/$RELEASE_CAP scoped features for release ${ACTIVE_RELEASE_ID})." >&2
      echo "  Defer this feature to the next release or remove another feature from scope first." >&2
      exit 1
    fi
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

FLOW_RUN_DIR="tmp/flow-runs/agentic_sdlc/${FEATURE_ID}"
mkdir -p "$FLOW_RUN_DIR" 2>/dev/null || true
cat >"$FLOW_RUN_DIR/product-team.json" <<JSON
{
  "id": "${TEAM_ID}",
  "label": "${TEAM_LABEL}",
  "site": "${SITE}",
  "pm_agent": "${PM_AGENT}",
  "qa_agent": "${QA_AGENT}",
  "dev_agent": "${DEV_AGENT}"
}
JSON

echo "[pm-scope-activate] Activating: $FEATURE_ID for site: $SITE"
echo "[pm-scope-activate] All grooming artifacts present ✓"

# Write Dev implementation or release-support inbox item (durable owner handoff for the active release)
DEV_ITEM_KIND="impl"
DEV_ITEM_TITLE="Implementation required: ${FEATURE_ID}"
DEV_ITEM_ROI="200"
DEV_ACTION_LINE_3="3. Implement the feature for release \`${ACTIVE_RELEASE_ID}\`"
DEV_ACTION_LINE_6="6. Coordinate with \`${QA_AGENT}\` for Gate 2 verification once implementation is ready"
DEV_ACCEPTANCE_1="- Implementation committed with hash recorded in outbox"
DEV_CONTEXT="This feature has been activated into the current release scope. Dev now owns the implementation handoff for this release."
if [ "$ACTIVATION_MODE" = "verification" ]; then
  DEV_ITEM_KIND="release-support"
  DEV_ITEM_TITLE="Release support required: ${FEATURE_ID}"
  DEV_ITEM_ROI="120"
  DEV_ACTION_LINE_3="3. Confirm the existing implementation/commit hashes that should ship in release \`${ACTIVE_RELEASE_ID}\`"
  DEV_ACTION_LINE_6="6. Stay available for fix-forward support if QA finds a release-blocking defect"
  DEV_ACCEPTANCE_1="- Existing implementation commit hash(es) and rollback notes recorded in outbox"
  DEV_CONTEXT="This feature is already implemented (Status: done) and has been activated into the current release scope for QA verification and ship readiness."
fi
DEV_ITEM_DIR="${DEV_INBOX}/${DATE_TAG}-${DEV_ITEM_KIND}-${FEATURE_ID}"

mkdir -p "$DEV_ITEM_DIR"
echo "$DEV_ITEM_ROI" > "$DEV_ITEM_DIR/roi.txt"

cat > "$DEV_ITEM_DIR/command.md" <<EOF
- Flow id: agentic_sdlc
- Flow run id: ${FEATURE_ID}
- Flow node: Generate Code
- Flow owner seat: ${DEV_AGENT}
- Flow previous node: PM Scope Decision
- Product team id: ${TEAM_ID}
- Product team label: ${TEAM_LABEL}
- Release id: ${ACTIVE_RELEASE_ID}
- Feature id: ${FEATURE_ID}
- Available flow outcomes: Scope decision required
- Flow direct route available: yes

# Flow handoff: agentic_sdlc / Generate Code

This feature has been activated into release \`${ACTIVE_RELEASE_ID}\` and now enters the implementation lane of the SDLC flow.

$(if [ "$IS_ALREADY_SCOPED_TO_ACTIVE_RELEASE" = "1" ]; then echo "This is a legacy requeue of an already-scoped release item so the work resumes from the start of the flow-managed SDLC lane."; fi)

## Required action
1. Review \`features/${FEATURE_ID}/feature.md\`, \`features/${FEATURE_ID}/01-acceptance-criteria.md\`, and \`features/${FEATURE_ID}/03-test-plan.md\`.
2. Complete the Dev responsibilities for \`Generate Code\` as \`${DEV_AGENT}\`.
3. If implementation is ready for the normal review path, finish with \`- Status: done\` and no \`- Flow outcome:\` line.
4. If implementation cannot continue until PM re-baselines scope (hold/defer/consolidate/split), finish with \`- Status: done\` and \`- Flow outcome: Scope decision required\`.
5. Include commit hashes or concrete repo-state evidence for any implementation you claim complete.
EOF

cat > "$DEV_ITEM_DIR/README.md" <<EOF
# ${DEV_ITEM_TITLE}

- Agent: ${DEV_AGENT}
- Feature: ${FEATURE_ID}
- Release: ${ACTIVE_RELEASE_ID}
- Status: pending
- Created: $(date -Iseconds)
- Dispatched by: pm-scope-activate.sh (Stage 0 release activation)

## Context

${DEV_CONTEXT}

- Flow-managed SDLC run: \`agentic_sdlc / ${FEATURE_ID}\`

## Action required
1. Review feature brief: \`features/${FEATURE_ID}/feature.md\`
2. Review acceptance criteria: \`features/${FEATURE_ID}/01-acceptance-criteria.md\`
${DEV_ACTION_LINE_3}
4. Run existing tests to ensure no regressions
5. Write outbox with implementation notes and commit hash(es)
${DEV_ACTION_LINE_6}

## Acceptance criteria
${DEV_ACCEPTANCE_1}
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
- Flow id: agentic_sdlc
- Flow run id: ${FEATURE_ID}
- Flow node: Test Cases Review
- Flow owner seat: ${QA_AGENT}
- Flow previous node: PM Scope Decision
- Product team id: ${TEAM_ID}
- Product team label: ${TEAM_LABEL}
- Release id: ${ACTIVE_RELEASE_ID}
- Feature id: ${FEATURE_ID}
- Available flow outcomes: Approved | Changes requested

# Flow handoff: agentic_sdlc / Test Cases Review

This feature has been selected into the current release scope. Activate its test plan into the live QA suite and confirm the release-ready verification coverage for the SDLC test branch.

$(if [ "$IS_ALREADY_SCOPED_TO_ACTIVE_RELEASE" = "1" ]; then echo "This is a legacy requeue of an already-scoped release item so QA coverage is re-established from the beginning of the flow-managed lane."; fi)

**Now** is when you add tests to \`suite.json\` and \`qa-permissions.json\`.
EOF

if [ "$ACTIVATION_MODE" = "verification" ]; then
cat >> "$ITEM_DIR/command.md" <<EOF
The feature is already implemented and is being pulled into the current release as a ship candidate. Tests must be live for Stage 4 regression and release verification.
EOF
else
cat >> "$ITEM_DIR/command.md" <<EOF
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.
EOF
fi

cat >> "$ITEM_DIR/command.md" <<EOF

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

2. **Add permission rules to** \`org-chart/sites/${SITE}/qa-permissions.json\`  
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
   - If the test branch is ready to proceed, finish with \`- Status: done\` and \`- Flow outcome: Approved\`.
   - If QA finds the test branch incomplete or needing revision before release validation, finish with \`- Status: done\` and \`- Flow outcome: Changes requested\`.

### Test plan (written during grooming)

${TEST_PLAN_CONTENT}

### Acceptance criteria (reference)

$(cat "$AC_FILE")
EOF

cp "$FEATURE_BRIEF" "$ITEM_DIR/feature.md"
cp "$TEST_PLAN" "$ITEM_DIR/03-test-plan.md"

# Mark feature in_progress (scoped for this release)
python3 - "$FEATURE_BRIEF" "$ACTIVE_RELEASE_ID" "$STATUS" "$IS_ALREADY_SCOPED_TO_ACTIVE_RELEASE" <<'PY'
import pathlib, sys, datetime
p = pathlib.Path(sys.argv[1])
release_id = sys.argv[2] if len(sys.argv) > 2 else ""
original_status = sys.argv[3] if len(sys.argv) > 3 else ""
already_scoped_to_active_release = sys.argv[4] == "1" if len(sys.argv) > 4 else False
today = datetime.date.today().isoformat()
text = p.read_text(encoding='utf-8')
already_scoped = original_status in {"in_progress", "done"}
if original_status == "ready":
    text = text.replace('- Status: ready', '- Status: in_progress')
# GAP-RB-03: update or insert Release: field to current release ID
import re as _re
if release_id:
    if _re.search(r'^-\s+Release:[ \t]*', text, flags=_re.MULTILINE | _re.IGNORECASE):
        # Update existing (possibly stale) Release: field
        text = _re.sub(
            r'^(-\s+Release:[ \t]*).*$',
            r'\g<1>' + release_id,
            text, count=1, flags=_re.MULTILINE | _re.IGNORECASE
        )
    else:
        # Insert Release after the existing status line.
        text = _re.sub(
            r'(^-\s+Status:[ \t]*(?:in_progress|done)[ \t]*$)',
            r'\1\n- Release: ' + release_id,
            text, count=1, flags=_re.MULTILINE | _re.IGNORECASE
        )
if '## Latest updates' in text:
    lines = text.split('\n')
    for i, line in enumerate(lines):
        if line.strip() == '## Latest updates':
            if original_status == "done":
                if already_scoped_to_active_release:
                    lines.insert(i+1, f'\n- {today}: Re-queued into flow-managed SDLC for release verification after legacy handoff drift.')
                else:
                    lines.insert(i+1, f'\n- {today}: Scoped into release for QA verification; implementation was already complete.')
            else:
                if already_scoped_to_active_release:
                    lines.insert(i+1, f'\n- {today}: Re-queued into flow-managed SDLC from legacy release scope.')
                else:
                    lines.insert(i+1, f'\n- {today}: Scoped into release — suite activation sent to QA.')
            break
    text = '\n'.join(lines)
p.write_text(text, encoding='utf-8')
if original_status == "done":
    print(f"Updated {p}: kept status=done and stamped Release → {release_id}")
else:
    print(f"Updated {p}: status → in_progress")
PY

echo "[pm-scope-activate] QA activation item queued: $ITEM_DIR"
echo "[pm-scope-activate] Dev item queued: $DEV_ITEM_DIR"
if [ "$IS_ALREADY_SCOPED_TO_ACTIVE_RELEASE" = "1" ]; then
  echo "[pm-scope-activate] Feature $FEATURE_ID was already scoped to release ${ACTIVE_RELEASE_ID} and has been re-queued into flow-managed SDLC."
elif [ "$ACTIVATION_MODE" = "verification" ]; then
  echo "[pm-scope-activate] Feature $FEATURE_ID remains status=done and is now scoped to release ${ACTIVE_RELEASE_ID}."
else
  echo "[pm-scope-activate] Feature $FEATURE_ID is now in_progress for this release."
fi
echo ""
echo "Next: add $FEATURE_ID to your release 01-change-list.md"
