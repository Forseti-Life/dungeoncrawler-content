# Dev-Infra Task: Implement orchestrator auto-trigger for Gate 2 consolidated APPROVE

- Website: dungeoncrawler (pattern affects all sites)
- Module: orchestrator / agent-exec-loop
- Role: dev-infra
- Priority: high
- ROI: 50
- Rationale: CEO has now manually filed Gate 2 APPROVE for qa-dungeoncrawler in 4 consecutive release cycles. Each instance costs ~1h of stagnation and one CEO execution slot. The root cause is structural: per-item suite-activate dispatch gives qa no "batch complete" signal. Two instruction fixes (GAP-DC-QA-GATE2-CONSOLIDATE-01, GAP-DC-QA-GATE2-CONSOLIDATE-02) did not resolve the behavior — qa-dungeoncrawler still never files the consolidated APPROVE on its own.

## Problem statement

After all suite-activate inbox items for a release are processed by qa-dungeoncrawler, a consolidated Gate 2 APPROVE outbox file must exist at:
```
sessions/qa-dungeoncrawler/outbox/<timestamp>-gate2-approve-<release-id>.md
```
containing both the release ID and the word "APPROVE" (this is what `release-signoff.sh` checks via grep).

Currently, this file is **never automatically produced** by qa-dungeoncrawler. qa processes each suite-activate item individually, files per-item outboxes, and then sits idle — it does not synthesize a batch-complete consolidation.

## Acceptance criteria

Implement **one** of the following solutions (choose the simplest):

### Option A (preferred): orchestrator auto-generates Gate 2 APPROVE
After all suite-activate inbox items for a given release ID have been processed (all items have outbox files with `Status: done`), the orchestrator (or a new `scripts/qa-gate2-auto-approve.sh`) automatically:
1. Detects that `sessions/qa-<team>/inbox/` has no remaining suite-activate items for the release AND `sessions/qa-<team>/outbox/` has N suite-activate outboxes for that release all with `Status: done`
2. Writes a consolidated Gate 2 APPROVE file to `sessions/qa-<team>/outbox/<timestamp>-gate2-approve-<release-id>.md`
3. The file must contain the release ID and the word `APPROVE` so `release-signoff.sh` grep check passes

### Option B: pm dispatches explicit gate2-approve item (already in pm instructions as GAP-DC-QA-GATE2-CONSOLIDATE-02)
GAP-DC-QA-GATE2-CONSOLIDATE-02 requires pm to dispatch a `gate2-approve-<release-id>` inbox item to qa after all suite-activates. **This only works for future releases** where pm applies the new instruction. For qa to process it, qa also needs an inbox item handler for this type. Verify that qa-dungeoncrawler's instructions have an explicit handler for `gate2-approve-*` items and that the orchestrator does not skip these item types.

## Verification

For each proposed solution, verify:
```bash
# After a simulated release cycle where all suite-activates are done:
grep -rl "<release-id>" sessions/qa-dungeoncrawler/outbox/ | xargs grep -l "APPROVE"
# Must return at least one file.

bash scripts/release-signoff.sh dungeoncrawler <release-id>
# Must exit 0 (Gate 2 check passes).
```

## Context

- 4 consecutive cycles with the same failure: 20260407-dungeoncrawler-release-b, 20260407-dungeoncrawler-release-c (empty), 20260408-dungeoncrawler-release-b, 20260408-dungeoncrawler-release-c
- CEO-filed APPROVE artifacts:
  - `sessions/qa-dungeoncrawler/outbox/20260408-001100-gate2-approve-20260407-dungeoncrawler-release-b.md`
  - `sessions/qa-dungeoncrawler/outbox/20260408-042018-gate2-approve-20260408-dungeoncrawler-release-b.md`
  - `sessions/qa-dungeoncrawler/outbox/20260408-055300-gate2-approve-20260408-dungeoncrawler-release-c.md`
- Prior instruction fixes that failed to resolve the root cause:
  - `GAP-DC-QA-GATE2-CONSOLIDATE-01` in `org-chart/agents/instructions/qa-dungeoncrawler.instructions.md`
  - `GAP-DC-QA-GATE2-CONSOLIDATE-02` in `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md`
- KB lesson: `knowledgebase/lessons/20260408-qa-gate2-consolidated-approve-missing.md`
- `release-signoff.sh` Gate 2 check: `grep -rl "$release_id" "$qa_outbox/" | xargs grep -l "APPROVE"` (lines 88–96)

## Constraints

- Do not modify `release-signoff.sh` Gate 2 check logic — it is working correctly
- The auto-approve trigger should only fire when ALL suite-activate items for the release are done (not partial)
- The generated file must include the release ID and "APPROVE" somewhere in its content
- If Option A is implemented, add a log line so it's visible in orchestrator output: `[gate2-auto-approve] <release-id> — filed by orchestrator`

## Escalation
Report back to pm-dungeoncrawler inbox (ROI 30) when the fix is implemented and verified.
- Agent: dev-infra
- Status: pending
