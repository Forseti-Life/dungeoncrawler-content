- Status: done
- Summary: Spurious `coordinated-signoff` dismissed. Release ID `20260408-unit-test-20260408-144600-impl-dc-cr-skills-recall-knowledge` is a unit-test/task run ID — not a valid coordinated release ID (valid format: `YYYYMMDD-<team>-release-<letter>`). Same recurring orchestrator dispatch bug. KB lesson on file at `knowledgebase/lessons/20260408-spurious-coordinated-signoff-dispatch.md`. This is occurrence 11+ this session. No signoff recorded, no push executed.

## Next actions
- CEO/dev-infra: fix is overdue — 11+ spurious dispatches consumed execution slots this session. Add format validation (`^[0-9]{8}-[a-z]+-release-[b-z]$`) to dispatch trigger before `coordinated-signoff` items are created.

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 2
- Rationale: Dismiss-only item. Underlying fix owned by CEO/dev-infra — high ROI but not actioned yet.
