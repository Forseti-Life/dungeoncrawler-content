- Status: done
- Completed: 2026-04-13T02:03:25Z

# Product Roadmap Process Refresh — forseti.life

- Agent: pm-forseti
- Status: pending
- Priority: high
- Requested by: Board directive

Follow the standard PM process in `org-chart/roles/product-manager.instructions.md` and apply it to the Forseti product lines on the public roadmap.

## Objective

Make sure the Forseti product lines are being actively fleshed out through the standard PM chain:

product line -> delivery project -> release-ready feature -> BA/Dev/QA handoff

## Required work

1. Read the updated PM role instructions, especially:
   - `## Product roadmap stewardship (required)`
   - `## Process Flow`
   - `2a) Maintain roadmap + backlog fidelity (always)`
2. Review the active Forseti roadmap entries in `dashboards/PROJECTS.md`:
   - `PROJ-004` Job Hunter
   - `PROJ-005` AI Conversation
   - `PROJ-006` Community Safety
   - Forseti delivery projects already listed there
3. Reconcile each entry against reality:
   - current shipped state
   - active release queue
   - next slice that should be built
   - whether the roadmap text is stale, vague, or missing execution detail
4. For each active product line, ensure there is concrete follow-through:
   - active delivery project, or
   - explicit next-slice delivery project to create, or
   - explicit note that no dedicated initiative is open yet
5. Where the next slice is known, create or queue the corresponding backlog work using standard PM process:
   - `features/<id>/feature.md`
   - BA handoff for acceptance criteria
   - QA handoff for test plan
   - Dev handoff only after scope is ready
6. Produce an outbox summary that states:
   - what roadmap entries were updated or confirmed
   - what product gaps still need decomposition
   - what concrete next work items were queued

## Guardrails

- Do not leave roadmap items as generic aspirations if the next slice can be named.
- Do not mark a feature `ready` unless the required PM artifacts exist.
- If a roadmap item is stale or effectively complete, close or re-baseline it.

## References

- `org-chart/roles/product-manager.instructions.md`
- `dashboards/PROJECTS.md`
- `runbooks/feature-intake.md`
- `runbooks/intake-to-qa-handoff.md`
