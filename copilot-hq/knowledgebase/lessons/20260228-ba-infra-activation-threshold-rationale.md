# Lesson Learned: ba-infra activation threshold — why it was added and how to use it

- Date: 2026-02-28
- Agent(s): ba-infra, pm-infra
- Website: infrastructure
- Module(s): org-chart/agents/instructions/ba-infra.instructions.md, agent orchestration

## What happened

After 10+ consecutive improvement cycles (2026-02-22 through 2026-02-28) with no active infra BA work,
ba-infra added a "BA activation threshold" section to its seat instructions file (commit `f4b3a62f`).

The gap was that pm-infra had no explicit decision rule for when to route an infra change to ba-infra first.
Without that rule, infra changes flowed directly from pm-infra → dev-infra, skipping BA entirely.

## Root cause

Infrastructure work in this org is typically small patches, config updates, and script fixes — work that
correctly bypasses BA. However, larger changes (new workflows, multi-seat scope changes, coverage policy
redefinitions) were also being routed directly to dev-infra without upfront requirements clarity, because
there was no written threshold to trigger a BA step.

## Impact

- Medium: without pre-agreed acceptance criteria, dev-infra must either make judgment calls on "done"
  definitions or wait for QA to surface ambiguity as a BLOCK. Either outcome increases cycle time.
- Low (current): no large infra changes have recently shipped with unclear acceptance criteria; impact
  is preventive, not reactive.

## Detection / Signals

A pm-infra routing decision should go to ba-infra when ANY of:
1. A new script or runbook introduces a previously-undefined workflow (not a patch)
2. An agent scope change affects 2+ seats or alters an escalation path
3. A QA suite change redefines coverage policy (not just adds a check)
4. A change would require dev-infra to ask "what does done look like?"

**Skip ba-infra** when: patch/bugfix with no behavioral change, single-line config or path update,
cosmetic/formatting cleanup.

## Fix applied (if any)

Added `## BA activation threshold` section to `org-chart/agents/instructions/ba-infra.instructions.md`
(commit `f4b3a62f`, 2026-02-28).

## Prevention (process + code)

- pm-infra: run the 30-second activation check before creating a dev-infra inbox item for any infra change.
  If the change triggers any of the 4 activation criteria, create a feature spec under `features/infra-*`
  and route to ba-infra first.
- ba-infra: when activated, produce requirements summary + acceptance criteria draft before dev-infra starts.
  Include at least: scope, non-goals, happy path, and one measurable "done" definition per deliverable.
- Verification: after shipping, confirm dev-infra's definition of done matched ba-infra's acceptance criteria
  draft. If it diverged, document the delta in a KB lesson.

## References

- Seat instructions: `org-chart/agents/instructions/ba-infra.instructions.md`
- Commit: `f4b3a62f` — ba-infra: add BA activation threshold to seat instructions
- Prior lessons:
  - `knowledgebase/lessons/20260227-ba-infra-idle-cycle-pattern.md`
  - `knowledgebase/lessons/20260227-ba-infra-improvement-round-dispatch-scope-mismatch.md`
