- Status: done
- Summary: Post-release gap review for `20260402-dungeoncrawler-release-c` (the empty "fake-no-signoff" release). The release deadlocked because the orchestrator's FEATURE_CAP trigger counted all in_progress features across all release IDs (not scoped to the current release_id), immediately firing auto-close on a brand-new zero-feature release and making Gate 2 unsatisfiable. Three process gaps identified: (1) BA-owned — feature stubs had no `- Release:` field to stamp which release a feature was activated in, making cross-release count ambiguity undetectable from the feature files themselves; (2) `improvement-round.sh` dispatched inbox items with malformed non-release-ID suffixes (`fake-no-signoff-release` instead of a real release ID), which bypasses the signoff gate regex — dev-infra already has inbox item `20260405-scope-filter-improvement-round-dispatch` covering this; (3) orchestrator FEATURE_CAP not scoped to current release_id — dev-infra KB lesson GAP-IR-20260405 and improvement-round items already cover this. BA fixed gap 1: added `- Release: (set by PM at activation)` field to the canonical feature stub in `ba-dungeoncrawler.instructions.md` with an enforcement rule, and queued `pm-dungeoncrawler` inbox item `20260406-feature-release-field-activation` to update the activation workflow. Committed as `53a93dac`.

## Next actions
- pm-dungeoncrawler: process `sessions/pm-dungeoncrawler/inbox/20260406-feature-release-field-activation/` (ROI 18) — update activation checklist to stamp `- Release:` field
- dev-infra: process `sessions/dev-infra/inbox/20260405-scope-filter-improvement-round-dispatch/` — fix improvement-round.sh naming validation and dispatch scope filter
- dev-infra: implement orchestrator FEATURE_CAP release-scoping fix (per KB GAP-IR-20260405-2)

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 16
- Rationale: Adding the Release field makes future FEATURE_CAP scoping auditable at the feature-file level without relying on orchestrator state files. Directly prevents the cross-release count ambiguity that caused the 5-hour release-c deadlock.
