- command: |
    Update pm-scope-activate.sh to write the Release: field when activating features (GAP-RB-03):

    CONTEXT: When `pm-scope-activate.sh` activates a feature into a release, it sets
    `Status: in_progress` but does NOT update the `Release:` field in feature.md.
    This causes features to carry stale release IDs from their original activation cycle,
    making per-release scoping unreliable and audit traces misleading.

    The orchestrator fix in `04e29e01` depends on `Release: <rid>` being correct in
    feature.md to scope the FEATURE_CAP count. If features are activated without updating
    the Release: field, the cap count will silently miss them for the new release ID.

    TASKS:
    1. In `scripts/pm-scope-activate.sh`, find where `Status: ready` is replaced with
       `Status: in_progress` in the feature.md file.
    2. After updating Status, also update (or insert if absent) the `Release:` field to
       the current release ID (passed as the `$RELEASE_ID` argument).
       Use sed or equivalent: `sed -i "s/^- Release: .*/- Release: $RELEASE_ID/" feature.md`
       If no Release: line exists, insert one after the Status: line.
    3. Verify the change doesn't break existing activation logic.
    4. Test: run `./scripts/pm-scope-activate.sh <test-release-id> <site>` against a
       ready feature and confirm both Status: and Release: are updated correctly.

    ACCEPTANCE CRITERIA:
    - After activation, feature.md shows `- Release: <current_release_id>` (not a prior cycle's ID).
    - pm-scope-activate.sh --dry-run (if supported) or a test run shows the correct behavior.
    - Changes committed.

    ROI: 35
    Rationale: Without this fix, the FEATURE_CAP per-release scoping (04e29e01) can silently
    miscount if features were previously tagged to a different release; prevents the
    empty-release loop from recurring via a different path.
- Agent: dev-infra
- Status: pending
