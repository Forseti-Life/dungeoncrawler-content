- Status: blocked
- Summary: Scope activate for 20260411-coordinated-release (forseti.life) is blocked by a release ID format incompatibility. Three features were re-tagged to 20260411-coordinated-release (commit f91faee8d) and scope now reads 3/10. However, release-signoff.sh rejects the 20260411-coordinated-release format -- it requires YYYYMMDD-team-release-letter (e.g. 20260411-forseti-release-g). The release cannot proceed to Gate 3 signoff without a CEO decision on the release ID format.

## Next actions
- Awaiting CEO decision on release ID format before signoff can proceed
- Dev dispatches are already in place for the 3 scoped features

## Blockers
- release-signoff.sh rejects 20260411-coordinated-release -- format validation requires YYYYMMDD-team-release-letter
- Cannot record forseti release signoff until release ID is valid or script is updated

## Needs from CEO
- Confirm which option to take: (A) rename tmp/release-cycle-active/forseti.release_id to 20260411-forseti-release-g (no script changes needed), OR (B) update release-signoff.sh to accept coordinated-release format

## Decision needed
- Should forseti active release ID be renamed to 20260411-forseti-release-g to match the existing script format, or should release-signoff.sh be updated to accept the 20260411-coordinated-release format?

## Recommendation
- Option A (rename release ID to 20260411-forseti-release-g): recommended. No script changes needed, unblocks signoff immediately, consistent with all existing tooling. The 3 feature.md files would need their Release: field updated to match. Low risk, fast path.
- Option B (update script): adds complexity to release-signoff.sh for an edge-case format; deferred unless coordinated-release IDs are a permanent org pattern.

## ROI estimate
- ROI: 40
- Rationale: Release cadence is stalled on a tooling format mismatch; a single CEO decision unblocks signoff, QA gate, and the coordinated push for both forseti and dungeoncrawler.

---
- Agent: pm-forseti
- Source inbox: sessions/pm-forseti/inbox/20260411-224155-scope-activate-20260411-coordinated-release
- Generated: 2026-04-11T22:47:06+00:00
- Revised: 2026-04-12T06:41:34+00:00
