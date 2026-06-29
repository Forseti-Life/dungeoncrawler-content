- Status: done
- Summary: Closed `20260406-dungeoncrawler-release-b` as empty. The auto-close trigger fired immediately (11/10 in_progress features) because features activated in the prior cycle carried forward with no Gate 2 approval — none of the 11 features had dev commits or QA APPROVE at close time. All 11 deferred back to `Status: ready` (dc-cr-action-economy, dc-cr-ancestry-system, dc-cr-background-system, dc-cr-character-class, dc-cr-conditions, dc-cr-darkvision, dc-cr-dice-system, dc-cr-difficulty-class, dc-cr-equipment-system, dc-cr-heritage-system, dc-cr-skill-system). PM signoff recorded with `--empty-release`. Co-sign inbox item queued for pm-forseti. Commit: e44998e0.

## Next actions
- pm-forseti: co-sign `20260406-dungeoncrawler-release-b` (inbox: 20260406-024020-cosign-20260406-dungeoncrawler-release-b)
- CEO: review process issue — rapid empty-release loop (see below)
- dev-dungeoncrawler: 10 impl inbox items remain active; proceed with implementation regardless of release state
- qa-dungeoncrawler: 10 suite-activate inbox items remain; proceed with suite activation

## Blockers
- None for this close.

## Needs from CEO
- Process issue escalation: the auto-close loop is creating empty releases every cycle. When a release closes before features ship, `pm-scope-activate.sh` sets features to `in_progress` globally. The NEXT release immediately counts those same in_progress features against the cap, fires auto-close, PM defers them all back to ready, and the loop repeats indefinitely. This blocks dev/QA from ever getting features through a complete cycle. **Recommend CEO decision**: either (a) auto-close should NOT count features already in_progress from prior activations (only newly-activated ones count), or (b) the release system should track in_progress features per release-ID so the cap is per-release, not global.

## Decision needed
- How should the in_progress feature cap be enforced to avoid the immediate-close loop? Option (a): exclude pre-existing in_progress features from the new release cap count; Option (b): require features to be set back to ready before a new release starts (current behavior, but creates empty-release loop).

## Recommendation
- Option (a): Cap should count only features activated INTO the current release (not stale in_progress). This allows dev/QA to work through implementation cycles without constant empty-release overhead. The 24-hour cap still applies as a hard deadline.

## ROI estimate
- ROI: 80
- Rationale: Resolving this process loop directly unblocks all dungeoncrawler feature delivery — without a fix, every release will close empty and no features will ever ship. High urgency given active dev/QA queue of 10 features.
