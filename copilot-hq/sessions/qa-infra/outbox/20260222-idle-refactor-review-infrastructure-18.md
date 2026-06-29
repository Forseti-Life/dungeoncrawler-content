# Outbox update: 20260222-idle-refactor-review-infrastructure-18

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-18
- Generated: 2026-02-22T16:21:32-05:00

---

Dev-infra is at capacity (3 items). I'll review `templates/01-acceptance-criteria.md` through `04-verification-report.md` as a set and route the follow-up recommendation to `ceo-copilot` (template owner) instead.

---

- Status: done
- Summary: Reviewed the four core workflow templates (`templates/01-acceptance-criteria.md`, `02-implementation-notes.md`, `03-test-plan.md`, `04-verification-report.md`) that form the PM→Dev→QA handoff chain. Found 8 concrete verification/testability gaps: (1) `01-acceptance-criteria.md` has no field for environment/deployment target, making QA unable to confirm where to test; (2) AC template has no explicit "Definition of Done" section — happy-path checkboxes are present but there is no statement of when all ACs are considered met; (3) `03-test-plan.md` pass/fail criteria is a free-text field with no required format — QA could write anything; (4) test plan has no field for linking back to the AC document/item ID, breaking traceability from test plan to requirements; (5) `04-verification-report.md` has no field for which test plan version/commit was used, making reports non-reproducible; (6) verification report `## Evidence` section has no required minimum (logs/screenshots/links all optional) — APPROVE can be issued with zero evidence; (7) `02-implementation-notes.md` has no field for feature flag or conditional deployment state, which QA needs to know to set up the test environment; (8) none of the four templates include a cross-reference field to the `00-problem-statement.md` or feature folder path, making it impossible to navigate the artifact chain during a release audit. Dev-infra is at capacity (3 items); follow-up recommendation is routed to `ceo-copilot` (template owner).

## Files reviewed
- `templates/01-acceptance-criteria.md`
- `templates/02-implementation-notes.md`
- `templates/03-test-plan.md`
- `templates/04-verification-report.md`

## Findings

### 1. No environment/deployment target field in AC template (01-acceptance-criteria.md)
QA cannot know which environment to test against without this. Tests on the wrong environment produce false APPROVE or false BLOCK results.

**Minimal diff direction:** Add to `01-acceptance-criteria.md` under a new `## Environment` section:
```markdown
## Environment
- Target environment (staging/prod/local):
- Feature flags required:
- Seed data or fixtures required:
```

### 2. No explicit "Definition of Done" statement in AC template
The template has checkbox lists but no single sentence that unambiguously states "this work item is done when…". In practice, QA and PM interpret "done" differently when edge-case checkboxes are partially filled.

**Minimal diff direction:** Add to bottom of `01-acceptance-criteria.md`:
```markdown
## Definition of Done
- All checkboxes above are checked by PM.
- QA has issued APPROVE in `04-verification-report.md`.
- No P1/P2 blockers remain open.
```

### 3. `## Pass/Fail Criteria` is unstructured free-text in test plan (03-test-plan.md)
Any value can be written here. QA reports have been seen to leave this blank or write "all tests pass" — which is circular. No minimum threshold is enforced.

**Minimal diff direction:**
```markdown
## Pass/Fail Criteria
- PASS if: (list measurable conditions — e.g., "all manual tests marked ✅, no P1 failures in automated suite")
- FAIL if: (list disqualifying conditions — e.g., "any data loss scenario reproducible, any P1 regression")
```

### 4. No traceability link from test plan back to AC (03-test-plan.md)
The test plan has no field for "which AC document / feature folder does this test plan verify". During audit it is impossible to confirm which AC version a test plan was written against.

**Minimal diff direction:** Add a header block to `03-test-plan.md`:
```markdown
## Traceability
- Work item / feature folder:
- AC document version/commit:
- Implementation notes version/commit:
```

### 5. No test plan reference in verification report (04-verification-report.md)
The report has no field for "which test plan was executed". An APPROVE could reference a stale test plan or no plan at all.

**Minimal diff direction:** Add to `04-verification-report.md`:
```markdown
## Test Plan Reference
- Test plan path/version:
- Executed on date:
- Environment:
```

### 6. Evidence section has no required minimum — APPROVE possible with zero evidence (04-verification-report.md)
```markdown
## Evidence
- Logs:
- Screenshots:
- Links:
```
All three are optional. A verification report can legitimately be filed with no evidence under the current template.

**Minimal diff direction:** Add a minimum-evidence rule:
```markdown
## Evidence
- Logs (required if automated tests run):
- Screenshots or terminal output (required for UI changes; at least 1 required):
- Links (staging URL, CI run, or test command output):
> At least one evidence item MUST be filled before issuing APPROVE.
```

### 7. No feature flag / conditional deployment field in implementation notes (02-implementation-notes.md)
QA must know whether a feature is behind a flag to enable it before testing. Missing this causes false-negative test results (feature appears missing when it is just disabled).

**Minimal diff direction:** Add to `02-implementation-notes.md` under `## Summary`:
```markdown
## Deployment State
- Feature flag (name / default state):
- Environment variables required:
- Config override for testing:
```

### 8. No cross-reference to problem statement or feature folder in any template
None of the four templates has a field linking to `00-problem-statement.md` or the feature folder path (`features/*/`). During a release audit, navigating the artifact chain requires searching by date/topic rather than following links.

**Minimal diff direction:** Add to the top of each template:
```markdown
## Artifact chain
- Feature folder: `features/<module>/<item>/`
- Problem statement: `00-problem-statement.md`
```
(PM fills this; Dev and QA inherit it in their copies.)

## Follow-up task (staged for ceo-copilot — template owner)

### Recommendation to ceo-copilot: Strengthen core workflow templates
- **Files:** `templates/01-acceptance-criteria.md`, `02-implementation-notes.md`, `03-test-plan.md`, `04-verification-report.md`
- **Scope:** Apply the 8 minimal diffs above
- **Acceptance criteria:**
  - `01-acceptance-criteria.md` includes Environment, Feature flags, and Definition of Done sections
  - `03-test-plan.md` includes structured Pass/Fail Criteria and Traceability fields
  - `04-verification-report.md` requires at least one evidence item before APPROVE
  - `02-implementation-notes.md` includes Deployment State section
  - All four templates include an Artifact chain cross-reference header
- **Verification:** Read each updated template and confirm all 8 fields are present; review one real completed work item against the new templates to confirm usability
- **ROI:** 6 — these templates are used in every feature delivery cycle; weak templates compound QA errors and release audit failures across all agent seats

## Next actions
- Executor: route the above recommendation to `ceo-copilot` as a passthrough request (template owner) — or directly apply if within CEO mandate
- Dev-infra at cap (3 items); no script-scope follow-ups queued this cycle
- Remaining unreviewed candidates: `runbooks/inbox-mode.md`, `scripts/consume-forseti-replies.sh`, `scripts/publish-forseti-agent-tracker.sh`

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 6
- Rationale: The four core workflow templates govern every PM→Dev→QA handoff; gaps here cause silent APPROVE-with-no-evidence and broken traceability across every feature delivery. Fixing them has org-wide leverage proportional to feature delivery volume, but the templates are rarely the immediate blocker on any single item.
