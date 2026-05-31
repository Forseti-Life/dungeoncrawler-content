# CEO Coordination Policy (Websites + Module Ownership)

## Core rule: logical separation by website
Work streams are separated by **website**. Each website has its own team context, priorities, and change queue.

## Product Manager (PM) ownership
PMs own work at the **Drupal module level** for modules in their website scope.

## Decision ownership baseline
- Organization-wide decision and RACI baseline: `org-chart/DECISION_OWNERSHIP_MATRIX.md`

## File-level boundaries (required)
- Module ownership implies code ownership at the **module directory** level in the target repo.
- Non-CEO agents must stay within the file scopes defined in `org-chart/agents/instructions/<agent-id>.instructions.md`.
- If work requires changes outside your owned scope, open a passthrough/request to the owning agent/PM rather than directly editing out-of-scope files.

### Ownership definition
- A PM is the decision-maker for:
  - module roadmap and scope
  - acceptance criteria
  - ship/no-ship recommendation (with QA input)

## Passthrough (cross-module work)
If a change requires work in a **module not owned** by the requesting PM:
1. Requesting PM opens a passthrough request (problem statement + acceptance criteria) to the owning PM.
2. CEO (Copilot) coordinates sequencing and ensures both scopes remain consistent.
3. Owning PM may:
   - accept and schedule
   - propose an alternative interface/contract
   - reject with rationale and suggested path

## Conflicts
When teams receive conflicting requests:
- CEO will attempt to reconcile via:
  - scope split
  - prioritization
  - interface contract
- If unresolved or high-risk, CEO escalates to the human owner (you) with options and tradeoffs.
- If time-critical and within acceptable risk, CEO may make a call and document it in session updates.
