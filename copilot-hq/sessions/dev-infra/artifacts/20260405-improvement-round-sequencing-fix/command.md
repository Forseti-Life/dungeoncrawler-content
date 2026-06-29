- Agent: dev-infra
- Status: pending
- command: |
    Fix improvement-round dispatch to only trigger after a release has actually shipped.

    Root cause (GAP-26B-02, recurring): Improvement-round inbox items are dispatched for
    releases that have not yet executed. Examples observed:
    - `20260327-improvement-round-20260327-dungeoncrawler-release-b`: dispatched while
      20260327-release-b was still in Stage 2 (QA had not run). All agents fast-exited.
    - `20260327-improvement-round` (bare, no release suffix): dispatched with ROI=56 and no
      identifiable target release; all agents fast-exited.

    These premature dispatches cause wasted fast-exit cycles across 4 agent seats per release
    and pollute outbox history with no-op entries.

    Task: Find the script or orchestrator logic that dispatches improvement-round inbox items
    and add a guard:

    1. Locate the dispatch point (search `scripts/` for improvement-round creation logic).
    2. Add a condition: only dispatch an improvement-round inbox item for a given release-id if
       a PM signoff artifact exists at
       `sessions/pm-<site>/artifacts/release-signoffs/<release-id>.md`
       AND the artifact does NOT contain "Signed by: orchestrator" (i.e., it was a real PM
       signoff, not an orchestrator pre-population).
    3. If the guard fails: log a warning and skip dispatch. Do NOT create the inbox item.

    Acceptance criteria:
    - Running the dispatch logic for a release-id with no PM signoff artifact produces no
      inbox item (zero files created).
    - Running it for a release-id with a valid (non-orchestrator) PM signoff creates the
      inbox item as before.
    - Add a comment at the dispatch point referencing GAP-26B-02 so future maintainers
      understand the guard.
    - Commit hash in outbox.

    Verification:
      # Before applying: check if any existing improvement-round items are for unshipped releases.
      # After applying: dry-run the dispatch script for an unshipped release-id; confirm no inbox
      # item is created.

    ROI rationale: Eliminates per-cycle 4-agent fast-exit waste. Each improvement-round premature
    dispatch consumes at least 4 agent execution slots with zero output value.
