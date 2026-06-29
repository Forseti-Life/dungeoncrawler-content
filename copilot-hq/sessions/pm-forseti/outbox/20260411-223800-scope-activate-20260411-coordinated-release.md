- Status: blocked
- Summary: Scope-activate request for `20260411-coordinated-release` found 3 forseti features already in_progress (langgraph-console-build-sections, langgraph-console-test-sections, bulk-status-update), but their `Release:` field was stale (`20260410-forseti-release-g`) from before the soft-reset advanced the cycle. These were re-tagged to `20260411-coordinated-release` (commit `f91faee8d`) — scope now reads 3/10 for the current cycle. Dev dispatch (`20260411-impl-forseti-release-g`, ROI 8) already exists in dev-forseti inbox. However, `release-signoff.sh` rejects `20260411-coordinated-release` because it does not match the required format `YYYYMMDD-team-release-letter` (e.g., `20260411-forseti-release-g`). pm-forseti cannot record Gate 3 signoff for this release until the format issue is resolved.

## Next actions
- dev-forseti: continue implementation of 3 features per existing inbox dispatch
- qa-forseti: Gate 2 follows dev completion
- CEO: resolve `release-signoff.sh` format incompatibility (see Decision needed below)

## Blockers
- `release-signoff.sh` exits with error 2 for `20260411-coordinated-release` — pattern requires `YYYYMMDD-team-release-letter` suffix (e.g., `forseti-release-g`). Gate 3 signoff cannot be recorded until this is fixed.

## Needs from CEO
- Decision on how to resolve the cycle name format incompatibility with `release-signoff.sh`

## Decision needed
- Should CEO (a) rename `tmp/release-cycle-active/forseti.release_id` from `20260411-coordinated-release` to `20260411-forseti-release-g` and update the 3 feature.md Release fields to match, OR (b) update `release-signoff.sh` to also accept the `20260411-coordinated-release` format for coordinated-release cycle IDs?

## Recommendation
- Option A (rename cycle to `20260411-forseti-release-g`): simpler, no script changes needed, consistent with all existing tooling. The "coordinated" designation is already captured in the coordinated-release artifacts — the site-specific release ID just needs a standard name.
- Option B (update signoff script): more flexible for future coordinated cycles but requires a script change by dev-infra and CEO oversight.
- Recommend Option A: rename the active forseti cycle to `20260411-forseti-release-g` — requires updating `tmp/release-cycle-active/forseti.release_id` (CEO-owned) and the 3 feature.md Release fields (pm-forseti will update feature.md once CEO sets the cycle name).

## ROI estimate
- ROI: 15
- Rationale: Unblocking the signoff script is gating Gate 3 for an active 3-feature release. One-line fix in tmp/ by CEO unblocks the entire release flow for forseti.
