# Acceptance Criteria (PM-owned)
# Topic: release-kpi-stagnation — dungeoncrawler
# Date: 2026-02-28

## Gap analysis reference

This is a process/pipeline health item, not a code feature. Gap analysis applies to the release pipeline state:
- Gate 2 QA violations: **Partial** (2 open; genai-debug routes returning 500/404 to administrator — dev item exists)
- QA testgen: **Partial** (9 items handed off; 0 test plans returned yet)
- Feature in dev-impl: **None** (no feature has started implementation)
- Scoreboard updated: **Partial** (baseline set 2026-02-27; no updates since)

## Happy Path
- [ ] `[EXTEND]` `20260228-dungeoncrawler-release` QA audit reaches 0 violations (currently 2 genai-debug violations — dev fix in inbox `20260228-094300-qa-findings-dungeoncrawler-2`)
- [ ] `[NEW]` All 9 in-progress features have `features/<id>/03-test-plan.md` written by QA before next Stage 0 (verified by running the grooming status check in seat instructions)
- [ ] `[NEW]` At least 1 dc-cr-* feature reaches `status: shipped` during `20260228-dungeoncrawler-release-next`
- [ ] `[EXTEND]` Scoreboard `knowledgebase/scoreboards/dungeoncrawler.md` is updated with actual metrics once the first feature ships (currently all N/A)

## Edge Cases
- [ ] `[NEW]` If qa-dungeoncrawler does not process testgen items before next Stage 0, PM selects scope from `planned` features that already have AC (dc-cr-action-economy, dc-cr-ancestry-system have AC; test plans can be generated in parallel with dev in that scenario)
- [ ] `[NEW]` If genai-debug violations cannot be fixed without scope-defining input, PM decides: accept risk (document in release notes) or defer route to next cycle

## Failure Modes
- [ ] `[TEST-ONLY]` If stagnation persists for 3 consecutive release cycles with 0 features shipped, escalate to CEO per DECISION_OWNERSHIP_MATRIX (repeated unclean-release pattern)
- [ ] `[NEW]` Invalid pipeline state (feature `in_progress` but QA handoff not run) must be caught at grooming step, not at Stage 0

## Permissions / Access Control
- Not applicable to this process item

## Data Integrity
- [ ] `[EXTEND]` Scoreboard is updated on each release ship event to maintain an auditable KPI history
- [ ] Rollback path: if first shipped feature causes a regression, rollback via `git revert` of the merge commit (standard Drupal config rollback)

## Knowledgebase check
- `knowledgebase/lessons/20260228-ba-feature-type-defaults-new-without-gap-analysis.md` — gap analysis before AC prevents wasted dev scope
- `knowledgebase/scoreboards/dungeoncrawler.md` — KPI baseline (2026-02-27); update on first ship
