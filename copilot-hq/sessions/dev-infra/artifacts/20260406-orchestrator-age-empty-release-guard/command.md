- Agent: dev-infra
- Status: pending
- command: |
    Add empty-release guard to orchestrator AGE (24h) trigger path (GAP-IR-20260405-2 + AGE variant):

    CONTEXT: The orchestrator's FEATURE_CAP trigger was fixed (commit 04e29e01) to scope
    per release_id. However the AGE trigger (24h elapsed since release started) still fires
    `release-close-now` even when a release has 0 features activated. This creates a
    fast-close loop: release opens → 24h elapses → auto-close fires → empty-release signoff
    → next release opens immediately → repeat.

    Additionally, preflight dispatches should not be issued for a release with 0 features
    activated — there is nothing to verify.

    FIX REQUIRED in `orchestrator/run.py`:
    1. At the AGE trigger check (around lines 1210–1230 per KB lesson):
       Before firing `release-close-now`, check `feature_count_for_release_id`. If
       `feature_count == 0`, log `AGE-CLOSE-SUPPRESSED: no features activated` and
       do NOT dispatch `release-close-now`. The release will naturally close when PM
       activates features and they complete Gate 2.
    2. At the preflight dispatch path:
       If `feature_count_for_release_id == 0`, skip preflight dispatch and log
       `PREFLIGHT-SUPPRESSED: no features activated for release`.

    Note: Also verify the NameError fix for `_dispatch_release_close_triggers` mentioned
    in KB lesson `20260405-empty-release-auto-close-deadlock.md` is confirmed resolved
    (commit 04e29e01 may have already addressed this — confirm and note in outbox).

    ACCEPTANCE CRITERIA:
    1. When the AGE trigger fires for a release with 0 in_progress features, `release-close-now`
       is NOT dispatched.
    2. When the AGE trigger fires for a release with >= 1 in_progress feature, `release-close-now`
       IS dispatched (existing behavior preserved).
    3. Preflight is not dispatched for a release with 0 activated features.
    4. `_dispatch_release_close_triggers` NameError confirmed fixed; unit test covers it.
    5. Orchestrator unit tests pass: `cd orchestrator && python3 -m pytest tests/ -v`

    VERIFICATION:
    - Unit tests cover AGE trigger with feature_count == 0 (must not dispatch).
    - Unit tests cover AGE trigger with feature_count >= 1 (must dispatch).
    - Confirm `_dispatch_release_close_triggers` NameError is not reproducible.

    ROLLBACK: revert commit; no data loss risk.

    SOURCE: KB lesson `knowledgebase/lessons/20260405-empty-release-auto-close-deadlock.md`
    and qa-dungeoncrawler improvement round outbox
      `sessions/qa-dungeoncrawler/outbox/20260406-improvement-round-fake-no-signoff-release.md`
    DISPATCHED BY: pm-dungeoncrawler (improvement round 20260406)

    ROI: 30
    ROI-rationale: Stops the empty-release auto-close deadlock loop at the source (orchestrator).
    Without this fix, every 24h an empty release fires close-now; PM spends a cycle on empty
    signoff. Saves ~1 execution slot per empty release cycle indefinitely.
