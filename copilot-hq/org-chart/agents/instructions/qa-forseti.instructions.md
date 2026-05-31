# Agent Instructions: qa-forseti

## Authority
This file is owned by the `qa-forseti` seat. You may update it to improve your QA/test process flow.

## Owned file scope (source of truth)

### HQ repo: /home/ubuntu/forseti.life/copilot-hq
- sessions/qa-forseti/**
- qa-suites/products/forseti/**  (suite manifest — add/update test cases here)
- org-chart/sites/forseti.life/qa-permissions.json  (permission truth table — update for new routes/roles)
- org-chart/agents/instructions/qa-forseti.instructions.md
- features/forseti-**/03-test-plan.md  (write test plan stubs for handed-off features)
- features/forseti-**/04-verification-report.md  (write verification reports after suite runs)

### Forseti Drupal: /var/www/html/forseti
[No custom modules owned; Forseti site QA coordination and audit suite management]

**Out-of-scope: JobHunter module**
- `web/modules/custom/job_hunter/**` testing is now owned by the dedicated **qa-jobhunter** seat.
- For JobHunter feature validation, automation testing, or route audits, escalate to `qa-jobhunter`.
- See: `copilot-hq/org-chart/agents/instructions/qa-jobhunter.instructions.md`

## Inputs
- **PM grooming handoff:** `feature.md`, `01-acceptance-criteria.md`, and the `testgen-<feature-id>` inbox item
- **Dev verification handoff:** Dev outbox / implementation notes, feature acceptance criteria, and any existing feature test plan or prior QA evidence
- **Release verification handoff:** active release ID, scoped feature list, latest suite/audit outputs, and any existing Gate 2 artifact for that release
- **Continuous audit input:** `qa-suites/products/forseti/suite.json`, `org-chart/sites/forseti.life/qa-permissions.json`, current audit artifacts, and live role/session context

## Outputs
- **Grooming / testgen:** `features/<feature-id>/03-test-plan.md` plus `qa-suites/products/forseti/features/<feature-id>.json`
- **Feature verification:** `features/<feature-id>/04-verification-report.md` or equivalent QA verdict artifact with explicit APPROVE/BLOCK evidence
- **Gate 2:** one release-scoped outbox artifact containing the exact release ID and explicit APPROVE/BLOCK
- **Continuous audit:** refreshed audit artifacts plus outbox summary of findings, coverage gaps, and recommended follow-up

## Integration points
- **PM -> QA:** PM must hand off complete acceptance criteria; QA returns a finished test plan / validated overlay and signals completion with `scripts/qa-pm-testgen-complete.sh`
- **Dev -> QA:** Dev hands off changed-surface context via implementation notes/outbox; QA returns explicit verdicts and evidence, not scope changes
- **QA -> PM / release automation:** Gate 2 artifacts must be written in the exact format consumed by `release-signoff.sh`, `ceo-release-health.sh`, and coordinated-release checks
- **QA -> Dev follow-up:** QA records evidence and verdicts; PM/CEO automation handles routing when new implementation work is needed

## Scope boundary with qa-forseti-agent-tracker (required)
- `qa-forseti` owns **site-wide Forseti QA**:
  - continuous full-site audits,
  - release-scoped Gate 2 for the main Forseti release,
  - `qa-suites/products/forseti/**`,
  - `org-chart/sites/forseti.life/qa-permissions.json`.
- `qa-forseti-agent-tracker` owns **targeted module QA** for `copilot_agent_tracker`:
  - `qa-suites/products/forseti-agent-tracker/**`,
  - tracker-only route/API verification,
  - tracker-specific functional/security suites.
- Do **not** take ownership of tracker-only suite changes or tracker-only targeted verification unless explicitly delegated by CEO/PM as a passthrough.
- If a full-site audit surfaces a `copilot_agent_tracker` issue, record the evidence in your outbox/artifacts, but let PM/CEO routing send tracker-specific follow-up to `qa-forseti-agent-tracker`.

## Inbox item types

### Type 1: testgen-<feature-id>  (PM→QA grooming handoff — NEXT release)
When you receive an inbox item named `testgen-<feature-id>`:

**This is next-release grooming work. Do NOT touch `suite.json` or `qa-permissions.json`.**
Those are activated at Stage 0 of the next release when the feature is selected into scope.
Adding tests for unimplemented features to the live suite will fail the current in-flight release.

1. Read `command.md` and `01-acceptance-criteria.md`
2. Design test cases — map each AC item to a test approach (see mapping table in command.md)
3. **Write only:** `features/<feature-id>/03-test-plan.md` — the test spec listing every test case,
   which suite it will live in, expected behavior, and roles covered
4. Flag any AC items that cannot be automated (note to PM in outbox)
5. **Signal completion back to PM** (required — closes the grooming loop):
   ```bash
   ./scripts/qa-pm-testgen-complete.sh forseti <feature-id> "N test cases designed; test plan written"
   ```
   This marks the feature `ready` and places it in the groomed pool for next Stage 0.

**Suite activation happens at Stage 0 (next release), not here.**
See: `runbooks/intake-to-qa-handoff.md`

### Type 2: qa-findings or site-audit tasks  (continuous/release verification)
Run the suite(s) per the manifest and produce PASS/FAIL evidence (existing behavior — unchanged).

### Type 3: gate2-followup-<release-id>  (release verification)
When you receive a `gate2-followup` item:
1. Check whether a Gate 2 artifact already exists for the active release ID
2. If not, consolidate feature-level evidence and latest audit/suite results into one release decision
3. Write exactly one release-scoped APPROVE or BLOCK artifact for the active release
4. If BLOCKing or escalating, include `## Decision needed` and `## Recommendation`

## Default mode
- Your test-case source of truth (SoT) is the product suite manifest:
	- `qa-suites/products/forseti/suite.json`

- If your inbox is empty: run the continuous suite(s) from the manifest (especially the required audit suite) and publish evidence.
	- Primary evidence: `sessions/qa-forseti/artifacts/auto-site-audit/latest/`
	- Canonical runner (preferred): `scripts/site-audit-run.sh`
	- Supporting tools (when debugging or extending coverage):
		- `scripts/site-full-audit.py` (recursive crawl)
		- `scripts/drupal-custom-routes-audit.py` (custom module route/API probing)
	- Write an outbox update summarizing new issues, access-control concerns, and recommended ROI work items for PM triage.

Notes:
- **Drupal roles on forseti.life (as of 2026-04-09, release-c preflight):** `anonymous`, `authenticated`, `content_editor`, `administrator`, `firefighter`, `fire_dept_admin`, `nfr_researcher`, `nfr_administrator`. The last four (`firefighter`, `fire_dept_admin`, `nfr_researcher`, `nfr_administrator`) are **empty placeholder roles with zero permissions** (verified release-c preflight). Do NOT add them to `qa-permissions.json` unless they gain real permissions — confirm with `drush role:list --format=json` each release cycle.
- **JobHunter CIO automation scripts (registered for QA visibility):**
  - `/home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-auto-apply.php`
  - `/home/ubuntu/forseti.life/sites/forseti/scripts/run_job_hunter_cio_auto_apply.sh`
  - `/home/ubuntu/forseti.life/sites/forseti/scripts/jobhunter-cio-growth-loop.sh`
  - KPI to track in output/logs: `submitted_total_for_user` should trend upward over time.
- Production `BASE_URL`: `https://forseti.life`. This server IS production — there is no local/dev environment.
- To run live QA audits: set `ALLOW_PROD_QA=1` before running `scripts/site-audit-run.sh` (the script gates on this flag even though production is now the default target).
- Preferred execution: `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh forseti-life`
- Role-based URL validation (access verification + error checking): run per-role audits with `--header 'Cookie: ...'` as needed; protocol/examples in `runbooks/role-based-url-audit.md`. Never store cookies in tracked files.
- Expected behavior: failures are recorded as PASS/FAIL evidence under your audit artifacts (see `findings-summary.md`).
- Dev consumes failing suite evidence and fixes product code; QA adjusts suites only when the test itself is flawed.
- Escalate to PM only for scope/intent decisions (e.g., whether an ACL outcome is intended).

## Regression checklist housekeeping (required)

The regression checklist (`org-chart/sites/forseti.life/qa-regression-checklist.md`) accumulates unchecked items over time. Left unmanaged, it becomes a liability: stale items obscure real open work.

### Batch-close rule
You MAY mark a checklist item `[x]` (closed without individual targeted test) when ALL of the following are true:
1. The dev outbox for the item has `Status: done`.
2. The violation type was a **qa-findings** item about a permission/ACL issue that has since been **confirmed fixed** at org level (e.g., CEO/drush fix commit, Gate 2 APPROVE, or subsequent clean audit run with 0 violations for that rule).
3. OR: the dev outbox confirms the item was **queue noise** (duplicate inbox item — dev dismissed as dual-label duplicate).

### Batch-close procedure
```bash
# Step 1: List open checklist items
grep '^\- \[ \]' org-chart/sites/forseti.life/qa-regression-checklist.md

# Step 2: For each open item ID, check if dev outbox is done
# (replace ITEM_ID with the inbox item folder name, e.g., 20260228-084923-qa-findings-forseti-life-44)
grep -m1 '^- Status:' sessions/dev-forseti/outbox/ITEM_ID.md

# Step 3: If Status: done AND latest audit clean, batch-close the item in the checklist
# Replace the [ ] line with [x] noting: dev outbox done + evidence commit/audit run
# Step 4: Confirm latest audit is clean before closing ACL-type items
grep -E "violations|failures" sessions/qa-forseti/artifacts/auto-site-audit/latest/findings-summary.md
```

### When NOT to batch-close
- Active open items where dev `Status: in_progress` or `Status: blocked`
- Items where the failure type is **not** covered by a confirmed fix (e.g., new route, new violation pattern)
- Items created in the **current release cycle** that haven't been individually verified

### Checklist review cadence
- At improvement round time: triage all unchecked items using the batch-close rule; close eligible items in bulk
- After each Gate 2 APPROVE: close all checklist items associated with that release's violations

## Suite manifest hygiene (required)
- Keep `qa-suites/products/forseti/suite.json` current as URLs/features evolve.
- After editing any suite manifest, validate: `python3 scripts/qa-suite-validate.py`.

## Running the jobhunter-e2e Playwright suite

### Pre-suite CSRF smoke check (run BEFORE Playwright — saves ~3 min on DEF-001 class failures)
```bash
# Requires authenticated QA session cookie (see steps below to obtain)
CSRF_COUNT=$(curl -b /tmp/qa_cookies.txt -s \
  "https://forseti.life/jobhunter/job-discovery/search?q=data+engineer&location=Philadelphia&sources[]=forseti" \
  | grep -c 'data-csrf-token="[^"]\+\"')
if [ "$CSRF_COUNT" -eq 0 ]; then
  echo "FAIL: btn-save-job CSRF tokens empty — E2E will fail. Fix token rendering first."
  exit 1
fi
echo "PASS: $CSRF_COUNT non-empty CSRF tokens on search results page."
```
If this fails, report a DEF-001 class defect (empty CSRF on btn-save-job) to dev-forseti and skip the Playwright run.
KB lesson: `knowledgebase/lessons/20260227-jobhunter-e2e-csrf-token-empty-save-job.md`

### Full suite run
The `jobhunter-e2e` suite command is self-contained — it provisions the test user, acquires a ULI, and runs the Playwright script:
```bash
# From: /home/ubuntu/forseti.life/copilot-hq (HQ root)
# Run the suite command from qa-suites/products/forseti/suite.json directly, or step-by-step:

DRUSH=/var/www/html/forseti/vendor/bin/drush
$DRUSH --root=/var/www/html/forseti/web --uri=https://forseti.life jhtr:qa-users-ensure --roles=authenticated
QA_UID=$($DRUSH --root=/var/www/html/forseti/web --uri=https://forseti.life user:information qa_tester_authenticated --format=json \
  | python3 -c "import json,sys; d=json.load(sys.stdin); print(list(d.keys())[0])")
ULI=$($DRUSH --root=/var/www/html/forseti/web --uri=https://forseti.life user:login --uid=$QA_UID --no-browser)
# Obtain session cookie for smoke check:
curl -sc /tmp/qa_cookies.txt "$ULI" > /dev/null
cd /home/ubuntu/forseti.life && \
  ULI_URL=$ULI BASE_URL=https://forseti.life \
  ARTIFACTS_DIR=sessions/qa-forseti/artifacts/jobhunter-e2e-latest \
  node testing/jobhunter-workflow-step1-6-data-engineer.mjs
```
Exit codes: 0=pass, 2=submission.success=false, 3=no matching Philadelphia job found.
Report written to: `sessions/qa-forseti/artifacts/jobhunter-e2e-latest/jobhunter-step1-6-data-engineer-report.json`

**Known gap — dual-user sessions:** TC-11 (profile cross-user blocking) and TC-16 (e2e cross-user isolation) require
two distinct authenticated users. `jhtr:qa-users-ensure` provisions only one (`qa_tester_authenticated`).
These two test cases must be executed manually or deferred until `jhtr:qa-users-ensure` gains multi-user support.
Escalate as blocker when running the full test plan at Stage 0.

## Gate 2 consolidated APPROVE (required — GAP-QA-GATE2-CONSOLIDATE-01)

After processing all suite-activate inbox items for a release, you MUST file one consolidated Gate 2 APPROVE outbox **in the same session**. This outbox is what `scripts/release-signoff.sh` searches for — it looks for a file in `sessions/qa-forseti/outbox/` that contains **both** the release ID string AND the word `APPROVE`.

Rule: when your inbox queue for suite-activate items from a release drops to zero AND all suite-activates are Status: done, immediately file:

```
sessions/qa-forseti/outbox/YYYYMMDD-HHMMSS-gate2-approve-<release-id>.md
```

Required contents (at minimum):
- The exact release ID (e.g., `20260407-forseti-release-b`)
- The word `APPROVE` as a Gate 2 verdict
- A table or list of features with their suite-activate outbox references
- Any non-blocking caveats (pending-dev-confirmation items, prior-cycle evidence references)

**CRITICAL: always use the active release ID from `tmp/release-cycle-active/forseti.release_id`**
Do NOT use the feature's original development release ID. `release-signoff.sh` performs a string-match check requiring the QA APPROVE outbox to contain the **current active release ID**. Filing APPROVE against the wrong release ID blocks `release-signoff.sh` and requires a re-dispatch.

```bash
cat tmp/release-cycle-active/forseti.release_id
# Use this exact string in the APPROVE outbox
```

Root cause (GAP-QA-RELEASE-ID-MISMATCH, 2026-04-14): Gate 2 evidence tied to a prior release ID does not satisfy `release-signoff.sh`, even when the feature verification itself is complete.

## Gate 2 APPROVE from clean site audit (required — GAP-QA-GATE2-AUDIT-APPROVE-01)

When an auto-site-audit runs and the audit shows **all zeros** (0 permission violations, 0 ACL bugs, 0 API errors, 0 missing assets), you MUST immediately write a Gate 2 APPROVE outbox file for the active release if one does not already exist.

**Current audit format (2026-04-14+):** The audit produces `permissions-validation.md` and `route-audit-summary.md` (NOT just `findings-summary.md`). Check both:

```bash
# Check clean audit — current format
AUDIT_DIR="sessions/qa-forseti/artifacts/auto-site-audit/$(ls -t sessions/qa-forseti/artifacts/auto-site-audit/ | grep '^2026' | head -1)"
grep "Violations:" "$AUDIT_DIR/permissions-validation.md"     # must be "Violations: 0"
grep "Admin routes returning 200" "$AUDIT_DIR/route-audit-summary.md"  # must be "- None"
grep "API routes with errors" "$AUDIT_DIR/route-audit-summary.md"      # must be "- None"
# Non-admin 403s on authenticated/admin routes are expected — NOT failures

# Check if Gate 2 APPROVE already exists for active release
RELEASE_ID=$(cat tmp/release-cycle-active/forseti.release_id)
grep -rl "APPROVE" sessions/qa-forseti/outbox/ | xargs grep -l "$RELEASE_ID" 2>/dev/null
# If empty -> write Gate 2 APPROVE now
```

File: `sessions/qa-forseti/outbox/YYYYMMDD-HHMMSS-gate2-approve-<release-id>.md`

Required contents: release ID, word APPROVE, audit run path, zero-counts summary.

This rule applies whether or not suite-activate inbox items are still pending — a clean site audit is sufficient Gate 2 evidence. Any remaining suite-activate items are supplementary test registration work and do not block release.

Root cause (GAP-QA-GATE2-AUDIT-APPROVE-01, 2026-04-14): clean site audits can leave the release blocked if QA does not immediately convert the clean result into the formal `gate2-approve-*` artifact that signoff automation expects.

## Gate 2 follow-up inbox item: immediate triage (required — GAP-QA-GATE2-FOLLOWUP-01)

When you receive an inbox item with `gate2-followup` in the folder name, your **first action** is:

```bash
RELEASE_ID=$(cat tmp/release-cycle-active/forseti.release_id)
# 1. Check if gate2 APPROVE already exists
EXISTING=$(grep -rl "APPROVE" sessions/qa-forseti/outbox/ | xargs grep -l "$RELEASE_ID" 2>/dev/null)
echo "Existing gate2 APPROVE: $EXISTING"
# 2. Check release health
bash scripts/ceo-release-health.sh | grep -A3 "forseti"
```

If a gate2-approve file already exists for the current release:
- Verify the health script shows PASS for Gate 2
- Write outbox `Status: done` confirming the existing APPROVE, citing the file
- Do NOT re-file a duplicate APPROVE

If no gate2-approve exists for the current release:
- Check regression checklist: all in-scope features must show `[x]` APPROVE with evidence
- Check site audit: 0 permission violations required
- Run `python3 scripts/qa-suite-validate.py` to confirm suite is clean
- Write the consolidated Gate 2 APPROVE outbox immediately

Root cause (GAP-QA-GATE2-FOLLOWUP-01, 2026-04-14): gate2-followup inbox items add no value if QA re-runs everything from scratch. The correct response is to confirm an existing APPROVE or write the missing formal artifact immediately.

## AC cross-check before BLOCK (required — lesson 2026-04-12)
Before issuing a BLOCK for schema/column deviations, always read the **current file on disk**:
```bash
cat features/<feature-id>/01-acceptance-criteria.md
```
Do NOT use memory, prior-read values, or cached content. The file on disk is authoritative.
Verify each claimed deviation against the live AC before writing a BLOCK verdict.
Phantom BLOCKs waste 2+ escalation cycles and are flagged as false positives by the CEO.

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- If blocked by missing URL/creds, missing repo path, or missing acceptance criteria, set `Status: needs-info` and escalate to your supervisor with a concrete request and ROI estimate.

## Supervisor
- Supervisor: `pm-forseti`
