- Status: done
- Completed: 2026-04-13T02:12:30Z

# Product Roadmap Process Refresh — dungeoncrawler

- Agent: pm-dungeoncrawler
- Status: pending
- Priority: high
- Requested by: Board directive

Follow the standard PM process in `org-chart/roles/product-manager.instructions.md` and apply it to the Dungeoncrawler product track and its dedicated roadmap.

## Objective

Make sure the Dungeoncrawler product track is being actively fleshed out through the standard PM chain:

product line -> delivery project -> release-ready feature -> BA/Dev/QA handoff

## Required work

1. Read the updated PM role instructions, especially:
   - `## Product roadmap stewardship (required)`
   - `## Process Flow`
   - `2a) Maintain roadmap + backlog fidelity (always)`
2. Review:
   - the HQ summary entry for `PROJ-007` in `dashboards/PROJECTS.md`
   - the dedicated Dungeoncrawler roadmap used by the product site
3. Reconcile both views against reality:
   - current shipped state
   - active Dungeoncrawler release queue
   - next slices that should be built
   - whether either roadmap view is stale, vague, or missing execution detail
4. Ensure the roadmap is decomposed into concrete follow-through:
   - active delivery project(s)
   - explicit next slice(s)
   - backlog feature stubs for the next buildable work
5. Where the next slice is known, queue work through the standard PM process:
   - `features/<id>/feature.md`
   - BA handoff for acceptance criteria
   - QA handoff for test plan
   - Dev handoff after scope is ready
6. Produce an outbox summary that states:
   - what roadmap entries were updated or confirmed
   - what product gaps still need decomposition
   - what concrete next work items were queued

## Guardrails

- Keep the HQ summary and the dedicated Dungeoncrawler roadmap aligned.
- Do not leave roadmap items as generic aspirations if the next slice can be named.
- If a roadmap item is stale or effectively complete, close or re-baseline it.

## References

- `org-chart/roles/product-manager.instructions.md`
- `dashboards/PROJECTS.md`
- Dungeoncrawler dedicated roadmap
- `runbooks/feature-intake.md`
- `runbooks/intake-to-qa-handoff.md`
