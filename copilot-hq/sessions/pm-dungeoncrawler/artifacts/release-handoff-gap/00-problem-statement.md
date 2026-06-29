# Problem Statement: Release Handoff Gap
## Context (2026-03-28)

**What is the gap?**
During the `20260327-dungeoncrawler-release-b` full investigation, a critical process gap was identified: the PM signoff artifact at `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260327-dungeoncrawler-release-b.md` was pre-populated by the orchestrator referencing the prior coordinated release `20260326-dungeoncrawler-release-b`. The file reads "Status: signed-off / Signed by: orchestrator" — but at the time, Gate 2 (QA unit test verification) had not been performed for any of the 4 release-b features.

This means `scripts/release-signoff-status.sh` shows `dungeoncrawler (pm-dungeoncrawler) signoff: true` based on a false/stale artifact, while pm-forseti signoff is still false. If pm-forseti's signoff had also been pre-populated, the coordinated push gate would appear satisfied with zero Gate 2 verification done.

**Why now:**
- The gap is live and could recur on any cycle where the orchestrator handles release state transitions
- The safe state was preserved by chance (pm-forseti signoff = false), not by process
- A lesson learned + process fix must be recorded before the next coordinated release

## Goals (Outcomes)
- Document the gap and its root cause
- Define a PM-level process check that prevents treating orchestrator-generated signoffs as Gate 2 approval
- Add a KB lesson learned so future PM seats recognize the pattern immediately
- Update pm-dungeoncrawler seat instructions with an explicit pre-signoff validation step

## Non-Goals
- Changing the orchestrator's behavior (that is ceo-copilot/dev-infra scope)
- Blocking the current release-b from progressing — QA Gate 2 is queued and will proceed normally
- Rewriting the signoff script (dev-infra scope)

## Users / Personas
- pm-dungeoncrawler: must not trust orchestrator-generated signoff artifacts without validation
- pm-forseti (release operator): must verify both PM signoffs are Gate-2-backed before pushing
- ceo-copilot: needs to be aware that the orchestrator's state machine may produce stale signoff artifacts

## Constraints
- Security: none (this is a process/documentation gap, not a security issue)
- Performance: not a factor
- Backward compatibility: the fix is process-only; no script changes required from pm-dungeoncrawler

## Success Metrics
- pm-dungeoncrawler seat instructions updated with: "before recording or accepting a release signoff, verify Gate 2 APPROVE evidence exists in qa-dungeoncrawler outbox for all scoped features"
- KB lesson learned written at `knowledgebase/lessons/20260328-orchestrator-premature-signoff-artifact.md`
- Release-handoff checklist in seat instructions includes a "validate Gate 2 evidence before trusting signoff artifact" step

## Dependencies
- None external; pm-dungeoncrawler owns its seat instructions and can write KB lessons directly

## Risks
- If this gap recurs on a cycle where pm-forseti signoff is also pre-populated, a push with zero QA verification could happen
- Recurrence likelihood is medium — the orchestrator transition logic doesn't currently check Gate 2 state before writing signoff artifacts

## Knowledgebase check
- Related pattern: `knowledgebase/lessons/20260322-escalation-gates-rationale.md` (gate rationale; relevant background)
- No existing lesson for "orchestrator-generated premature signoff artifact" — this gap creates a new lesson
