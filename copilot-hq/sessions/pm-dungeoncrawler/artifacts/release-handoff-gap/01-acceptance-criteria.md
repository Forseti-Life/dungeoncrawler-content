# Acceptance Criteria: Release Handoff Gap Fix

## Gap analysis reference
All items are process/documentation changes. No code changes required from pm-dungeoncrawler.

## Happy Path

### Seat instructions update (pm-dungeoncrawler)
- [x] `[NEW]` `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` contains an explicit step: "Before recording or accepting a PM signoff, verify that QA Gate 2 APPROVE evidence exists in `sessions/qa-dungeoncrawler/outbox/` for all features in the release scope. Do NOT treat orchestrator-generated signoff artifacts as Gate 2 approval."
- [x] `[NEW]` The pre-signoff checklist in seat instructions includes: "Check that signoff artifact was NOT pre-populated by orchestrator (look for 'Signed by: orchestrator' with a prior release reference)."

### KB lesson learned
- [x] `[NEW]` `knowledgebase/lessons/20260328-orchestrator-premature-signoff-artifact.md` created with:
  - Root cause: orchestrator transition wrote `20260327-dungeoncrawler-release-b` signoff referencing `20260326` coordinated release before Gate 2 was run
  - Detection: full investigation spotted "Signed by: orchestrator / 20260326-dungeoncrawler-release-b shipped" text in a `20260327` artifact
  - Resolution: pm-dungeoncrawler must validate Gate 2 evidence independently; do not rely on orchestrator signoff state
  - Prevention: seat instructions updated (this item)

### Current release correction (process only)
- [x] `[EXTEND]` pm-dungeoncrawler must re-run `scripts/release-signoff.sh dungeoncrawler 20260327-dungeoncrawler-release-b` AFTER all 4 QA Gate 2 APPROVEs are in, to replace the stale orchestrator-generated artifact with a real PM signoff

## Edge Cases
- [ ] `[TEST-ONLY]` If QA returns BLOCK for any feature: do NOT record signoff; fix cycle proceeds normally
- [ ] `[NEW]` If a signoff artifact exists but "Signed by: orchestrator" with a mismatched release reference: treat as invalid; re-run the signoff script after Gate 2 completion

## Failure Modes
- [ ] `[TEST-ONLY]` Orchestrator pre-populates a valid signoff (same release ID, post-Gate-2): this is acceptable — PM validates it is consistent, then proceeds
- [ ] `[NEW]` Orchestrator pre-populates signoff with wrong release reference: detected by PM during handoff validation; do NOT proceed without correcting

## Permissions / Access Control
- [ ] `[TEST-ONLY]` Only pm-dungeoncrawler writes to `sessions/pm-dungeoncrawler/artifacts/release-signoffs/` — this remains unchanged
- [ ] `[TEST-ONLY]` orchestrator-generated artifacts are read-only context for other agents; pm-dungeoncrawler is accountable for validating them

## Data Integrity
- [ ] The stale signoff artifact for `20260327-dungeoncrawler-release-b` will be overwritten by `scripts/release-signoff.sh` after Gate 2 completes — no permanent data loss
- [ ] Rollback: if the release-signoff script fails, pm-dungeoncrawler can manually write the signoff artifact with standard format

## Knowledgebase check
- New lesson created this item: `knowledgebase/lessons/20260328-orchestrator-premature-signoff-artifact.md`
- Related: `knowledgebase/lessons/20260322-escalation-gates-rationale.md`
