- Status: done
- Summary: Spurious `coordinated-signoff` inbox item dismissed. The release ID `20260408-unit-test-20260408-144600-impl-dc-cr-dc-rarity-spell-adjustment` is a unit-test run ID, not a valid release ID (`YYYYMMDD-<team>-release-<letter>`). No push was executed. This is the 4th occurrence of this orchestrator dispatch bug this session (prior: `20260408-132344-suite-activate-dc-cr-class-druid`, `20260408-143417-impl-forseti-langgraph-ui`, and the previous stale coordinated-signoff for release-g). A KB lesson is being written and a CEO-level fix escalation is attached below.

## Next actions
- CEO/dev-infra: fix auto-dispatch trigger to validate release ID format before firing `coordinated-signoff` or `release-signoff` items
- KB lesson written at `knowledgebase/lessons/20260408-spurious-coordinated-signoff-dispatch.md`

## Blockers
- None for forseti

## Needs from CEO
- N/A (no forseti action required; escalating orchestrator bug for fix)

## ROI estimate
- ROI: 3
- Rationale: Dismissal is trivial. Root-cause fix (CEO/dev-infra scope) is high-value but not pm-forseti's to execute.
