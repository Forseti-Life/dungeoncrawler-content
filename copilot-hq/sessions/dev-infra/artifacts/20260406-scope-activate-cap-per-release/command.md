- command: |
    Scope pm-scope-activate.sh 20-feature cap count to current release (GAP-B-02):

    CONTEXT: `scripts/pm-scope-activate.sh` enforces the 20-feature release cap at
    activation time. The current count on line ~75 is:

        SCOPED_COUNT="$(grep -rl "^- Status: in_progress" features/ 2>/dev/null \
          | xargs grep -l "^- Website:.*${SITE}" 2>/dev/null | wc -l | tr -d '[:space:]')"

    This counts ALL in_progress features for the site — regardless of which release they
    belong to. If 20+ features from prior release cycles remain in_progress (common when
    dev work spans multiple cycles), the cap gate fires and blocks activation for the NEW
    release, even though the current release has zero activations.

    The orchestrator auto-close trigger was fixed in commit 04e29e01 to scope per release_id.
    The activation gate must be brought into alignment with the same semantics.

    Note: the Release: field injection was added to pm-scope-activate.sh (line ~210-216)
    in a prior cycle. This means newly activated features will have `- Release: <rid>`,
    enabling this fix to work correctly for new activations.

    TASKS:
    1. In `scripts/pm-scope-activate.sh`, locate the SCOPED_COUNT assignment (approx. line 75).
    2. Add a Release: filter to scope the count to the current release only:

        SCOPED_COUNT="$(grep -rl "^- Status: in_progress" features/ 2>/dev/null \
          | xargs grep -l "^- Website:.*${SITE}" 2>/dev/null \
          | xargs grep -l "^- Release:.*${ACTIVE_RELEASE_ID}" 2>/dev/null \
          | wc -l | tr -d '[:space:]')"

    3. Guard: if ACTIVE_RELEASE_ID is empty, fall back to the original global count
       (preserve current behavior when no release is active).
    4. Update the error message to clarify the scope:
       "Release scope cap reached for site '${SITE}' ($SCOPED_COUNT/$RELEASE_CAP
        features in_progress for release $ACTIVE_RELEASE_ID)."
    5. Test: activate a feature when other in_progress features from a prior release_id exist;
       confirm the cap count does not include them.

    ACCEPTANCE CRITERIA:
    - Cap count is scoped to `Release: <ACTIVE_RELEASE_ID>` when ACTIVE_RELEASE_ID is set.
    - Activation of a new feature in a fresh release succeeds even when 20+ features from
      prior releases remain in_progress.
    - Cap still blocks activation when the current release itself has 20 activations.
    - No regression: when ACTIVE_RELEASE_ID is empty/unset, behavior is unchanged.

    RELATED ITEM:
    - `sessions/dev-infra/inbox/20260406-scope-activate-release-field-update/` (Release: field
      injection fix, ROI 35) — must be applied first or simultaneously for Release: filtering
      to work on newly activated features.

    VERIFICATION:
    - Manually activate a feature with 20 unrelated in_progress features for the same site;
      confirm activation succeeds.

    ROI: 35
    Rationale: Prevents activation blockage when features from prior release cycles remain
    in_progress. Directly enables forward progress in release cycles where dev work spans
    multiple cycles. Complements the orchestrator fix in 04e29e01.
- Agent: dev-infra
- Status: pending
- Routed-by: agent-code-review
- Review-source: sessions/agent-code-review/outbox/20260406-improvement-round-20260406-dungeoncrawler-release-b.md
- Gap: GAP-B-02
