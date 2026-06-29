- Status: done
- Summary: Dismissed malformed release-signoff dispatch for `20260408-143417-impl-forseti-langgraph-ui`. This is a dev task run ID (the `dev-forseti-agent-tracker` impl inbox item for forseti-langgraph-ui), not a valid release ID — valid release IDs follow the pattern `YYYYMMDD-<team>-release-<letter>`. The orchestrator's QA-APPROVE-triggered auto-dispatch is firing a `release-signoff` item using the QA unit test run ID as the release identifier. No signoff was recorded; no code was pushed. The real release for this work is `20260408-forseti-release-g`, and its Gate 2 APPROVE outbox dispatch is already queued to `qa-forseti` (inbox `20260408-gate2-forseti-release-g`, ROI 15). Once qa-forseti produces that file, `scripts/release-signoff.sh forseti 20260408-forseti-release-g` will be re-attempted.

## Next actions
- Await `qa-forseti` Gate 2 APPROVE outbox for `20260408-forseti-release-g` (dispatched)
- Then: `bash scripts/release-signoff.sh forseti 20260408-forseti-release-g`
- Then: dispatch `pm-dungeoncrawler` to co-sign `20260408-forseti-release-g`
- Then: coordinated push as release operator

## Blockers
- None for this item. Waiting on qa-forseti Gate 2 APPROVE consolidation (in-flight).

## Needs from CEO
- N/A (informational): This is the third occurrence of the orchestrator dispatch bug where a unit-test/task run ID is used as a release ID. Prior occurrences: `20260408-132344-suite-activate-dc-cr-class-druid` (×2). CEO/dev-infra fix to `release-signoff.sh` or the auto-dispatch trigger to validate release ID format (`YYYYMMDD-<team>-release-<letter>`) would eliminate these phantom items.

## ROI estimate
- ROI: 3
- Rationale: This was a no-op dismiss; zero implementation value. The ROI of fixing the root-cause dispatch bug is higher (~10) — surfaces as a CEO/dev-infra backlog item.
