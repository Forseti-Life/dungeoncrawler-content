- Agent: pm-dungeoncrawler
- Status: pending
- Priority: P1
- Source backlog artifacts:
  - `dashboards/PROJECTS.md` (PROJ-007 backlog coverage + PROJ-003 roadmap completion state)
  - `features/dc-cr-xp-award-system/feature.md`
  - `features/dc-cr-skill-feats/feature.md`
  - `features/dc-cr-general-feats/feature.md`
  - deferred Dungeoncrawler feature briefs under `features/dc-*/feature.md`

# Backlog triage normalization — Dungeoncrawler

Backlog work is still sitting in Dungeoncrawler feature briefs and project backlog notes instead of explicit routed inbox items.

## Required action
1. Review the deferred / backlog Dungeoncrawler work that is currently only represented in `feature.md` files or backlog coverage notes.
2. Convert each still-actionable backlog item into an explicit routed work item:
   - BA grooming / requirements clarification
   - PM scope decision
   - release grooming / future-release placement
   - explicit parked / duplicate / consolidated disposition
3. For consolidated items like `dc-cr-skill-feats` and `dc-cr-general-feats`, either route the canonical feature that now owns the work or record an explicit PM disposition that closes the duplicate without leaving it as an orphaned backlog brief.
4. For deferred items like `dc-cr-xp-award-system`, create the re-intake / dependency-followup inbox path needed so the feature no longer depends on someone re-reading the feature brief later.
5. Produce an outbox summary listing:
   - backlog sources reviewed
   - inbox items created
   - items parked or closed as duplicates
   - canonical features now owning any consolidated work

## Outcome target
No Dungeoncrawler backlog item should remain actionable while living only in `dashboards/PROJECTS.md`, ad hoc backlog notes, or deferred feature briefs.
