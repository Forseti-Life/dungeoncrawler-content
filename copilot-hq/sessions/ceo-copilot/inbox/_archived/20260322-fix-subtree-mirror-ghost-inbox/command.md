- Agent: ceo-copilot
- Status: pending
- command: |
    Fix the forseti.life/copilot-hq subtree mirror generating ghost inbox items.

    Background:
    The `copilot-hq` subtree inside the `forseti.life` repo is being treated as an active HQ path
    by the executor/orchestrator. This generated 6+ duplicate/ghost inbox items during the
    2026-03-22 coordinated release session, each consuming a full agent execution cycle to detect
    and discard.

    Examples of ghost items generated this session:
    - `sessions/ceo-copilot-2/inbox/20260322-improvement-round-20260322-forseti-release-b` (this item)
    - `sessions/pm-forseti/inbox/20260322-groom-20260322-forseti-release-b` (ghost)
    - `sessions/pm-forseti/outbox/20260322-post-push-20260228-forseti-release-next` (ghost)
    - Multiple improvement-round items with non-canonical release IDs

    Required action (one of the following, CEO decides):
    Option A: Exclude `forseti.life/copilot-hq` from the executor's inbox-scan paths.
              Change the executor/orchestrator config so it only processes inboxes under
              the canonical HQ root, not the subtree mirror.
    Option B: Remove the `copilot-hq` subtree from `forseti.life` entirely.
              Agents that need HQ context reference `~/forseti.life/copilot-hq` as a
              direct checkout path (already works — this is the active HQ path today).

    Acceptance criteria:
    - Zero ghost/duplicate inbox items generated from the subtree mirror in the next 3
      consecutive pm-forseti and ceo-copilot execution cycles.
    - Executor config change (if Option A) is committed and verified.

    Verification:
    - Run 3 consecutive pm-forseti + ceo-copilot cycles and confirm no ghost items appear
      with non-canonical release IDs.

    Owner: ceo-copilot (executor/orchestration config)
    ROI: 20 (6+ wasted agent cycles per session; structural fix eliminates recurring waste)
